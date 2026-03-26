<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\Command;

use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTwitterClient\Domain\Model\Account;
use Xima\XimaTwitterClient\Domain\Repository\AccountRepository;
use Xima\XimaTwitterClient\Domain\Repository\TweetRepository;
use Xima\XimaTwitterClient\Exception\ConfigurationException;
use Xima\XimaTwitterClient\Exception\TwitterApiException;
use Xima\XimaTwitterClient\FetchType\FetchTypeInterface;

class FetchTweetsCommand extends Command
{
    private const REQUIRED_CONFIG_KEYS = ['api_key', 'api_secret', 'access_key', 'access_secret'];

    private ?TwitterOAuth $connection = null;

    /** @var array<string, string> */
    private array $extConf = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly AccountRepository $accountRepository,
        private readonly TweetRepository $tweetRepository,
        private readonly ResourceFactory $resourceFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Fetch tweets from configured Twitter accounts')
            ->addOption(
                'account',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Fetch tweets for a specific account UID only'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate configuration without fetching tweets'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool)$input->getOption('dry-run');
        $accountUid = $input->getOption('account');

        Bootstrap::initializeBackendAuthentication();

        $io->title('Twitter Import');

        // Validate configuration
        try {
            $this->loadAndValidateConfiguration();
        } catch (ConfigurationException $e) {
            $this->logError('Configuration error', $e);
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($isDryRun) {
            $io->success('Configuration is valid.');
            return Command::SUCCESS;
        }

        // Initialize connection
        try {
            $this->initConnection();
        } catch (TwitterOAuthException $e) {
            $this->logError('Failed to initialize Twitter connection', $e);
            $io->error('Failed to initialize Twitter connection: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Get image storage folder
        try {
            $imageFolder = $this->getImageStorage();
        } catch (ConfigurationException | FolderDoesNotExistException $e) {
            $this->logError('Image storage error', $e);
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // Fetch accounts
        $accounts = $this->getAccounts($accountUid);

        if (count($accounts) === 0) {
            $io->warning('No Twitter accounts configured.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Processing %d account(s)...', count($accounts)));
        $io->newLine();

        // Process accounts
        $results = $this->processAccounts($accounts, $imageFolder, $io);

        // Output summary
        $this->outputSummary($results, $io);

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @throws ConfigurationException
     */
    private function loadAndValidateConfiguration(): void
    {
        try {
            $this->extConf = $this->extensionConfiguration->get('xima_twitter_client');
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException $e) {
            throw ConfigurationException::missingApiCredentials('extension configuration');
        }

        foreach (self::REQUIRED_CONFIG_KEYS as $key) {
            if (empty($this->extConf[$key])) {
                throw ConfigurationException::missingApiCredentials($key);
            }
        }
    }

    private function initConnection(): void
    {
        $this->connection = new TwitterOAuth(
            $this->extConf['api_key'],
            $this->extConf['api_secret'],
            $this->extConf['access_key'],
            $this->extConf['access_secret']
        );
        $this->connection->setTimeouts(30, 30);
        $this->connection->setApiVersion('2');
    }

    /**
     * @throws TwitterApiException
     */
    private function fetchUserId(string $username): string
    {
        $connection = new TwitterOAuth(
            $this->extConf['api_key'],
            $this->extConf['api_secret'],
            $this->extConf['access_key'],
            $this->extConf['access_secret']
        );
        $connection->setApiVersion('2');

        try {
            $content = $connection->get('users/by', ['usernames' => $username]);
        } catch (TwitterOAuthException $e) {
            throw new TwitterApiException(
                sprintf('Failed to fetch user ID for "%s": %s', $username, $e->getMessage()),
                1673361746,
                $e
            );
        }

        if (isset($content->errors) && count($content->errors) > 0) {
            throw TwitterApiException::fromApiResponse($content);
        }

        if (
            !isset($content->data[0]->id) ||
            count($content->data) !== 1 ||
            strtolower($username) !== strtolower($content->data[0]->username)
        ) {
            throw TwitterApiException::userNotFound($username);
        }

        return $content->data[0]->id;
    }

    /**
     * @throws ConfigurationException
     * @throws FolderDoesNotExistException
     */
    private function getImageStorage(): Folder
    {
        $path = $this->extConf['image_storage'] ?? '';

        if (empty($path)) {
            throw ConfigurationException::invalidImageStorage('(empty)');
        }

        try {
            return $this->resourceFactory->getFolderObjectFromCombinedIdentifier($path);
        } catch (\Exception $e) {
            throw ConfigurationException::invalidImageStorage($path, $e);
        }
    }

    /**
     * @return Account[]
     */
    private function getAccounts(?string $accountUid): array
    {
        if ($accountUid !== null) {
            $account = $this->accountRepository->findByUidIgnorePid((int)$accountUid);
            return $account !== null ? [$account] : [];
        }

        return $this->accountRepository->findAllIgnorePid()->toArray();
    }

    /**
     * @param Account[] $accounts
     * @return array{total: int, success: int, failed: int, tweets: int, errors: array<string, string>}
     */
    private function processAccounts(array $accounts, Folder $imageFolder, SymfonyStyle $io): array
    {
        $results = [
            'total' => count($accounts),
            'success' => 0,
            'failed' => 0,
            'tweets' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            $accountIdentifier = sprintf('%s (UID: %d)', $account->getUsername(), $account->getUid());

            try {
                $newTweets = $this->processAccount($account, $imageFolder);
                $results['tweets'] += $newTweets;
                $results['success']++;

                $io->text(sprintf(
                    '<info>[OK]</info> %s: %d new tweet(s)',
                    $accountIdentifier,
                    $newTweets
                ));

                $this->logger->info('Successfully fetched tweets', [
                    'account' => $account->getUsername(),
                    'uid' => $account->getUid(),
                    'newTweets' => $newTweets,
                ]);
            } catch (ConfigurationException $e) {
                $results['failed']++;
                $results['errors'][$accountIdentifier] = $e->getMessage();
                $this->logAccountError($account, 'Configuration error', $e, $io);
            } catch (TwitterApiException $e) {
                $results['failed']++;
                $results['errors'][$accountIdentifier] = $e->getMessage();
                $this->logAccountError($account, 'Twitter API error', $e, $io);
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][$accountIdentifier] = $e->getMessage();
                $this->logAccountError($account, 'Unexpected error', $e, $io);
            }
        }

        return $results;
    }

    /**
     * @throws ConfigurationException
     * @throws TwitterApiException
     */
    private function processAccount(Account $account, Folder $imageFolder): int
    {
        $fetchType = GeneralUtility::makeInstance($account->getFetchType());

        if (!$fetchType instanceof FetchTypeInterface) {
            throw ConfigurationException::invalidFetchType($account->getFetchType());
        }

        $fetchType->setAccount($account);
        $fetchType->setImageFolder($imageFolder);
        $fetchType->setTweetRepository($this->tweetRepository);

        $userId = $this->fetchUserId($account->getUsername());

        return $fetchType->fetchTweets($this->connection, $userId);
    }

    private function logAccountError(Account $account, string $context, \Throwable $e, SymfonyStyle $io): void
    {
        $io->text(sprintf(
            '<error>[FAIL]</error> %s (UID: %d): %s',
            $account->getUsername(),
            $account->getUid(),
            $e->getMessage()
        ));

        $this->logger->error($context, [
            'account' => $account->getUsername(),
            'uid' => $account->getUid(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
    }

    private function logError(string $context, \Throwable $e): void
    {
        $this->logger->error($context, [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
    }

    /**
     * @param array{total: int, success: int, failed: int, tweets: int, errors: array<string, string>} $results
     */
    private function outputSummary(array $results, SymfonyStyle $io): void
    {
        $io->newLine();
        $io->section('Summary');

        $io->definitionList(
            ['Accounts processed' => (string)$results['total']],
            ['Successful' => (string)$results['success']],
            ['Failed' => (string)$results['failed']],
            ['New tweets imported' => (string)$results['tweets']]
        );

        if ($results['failed'] > 0) {
            $io->error(sprintf('%d account(s) failed to import.', $results['failed']));

            if ($io->isVerbose()) {
                $io->section('Error Details');
                foreach ($results['errors'] as $account => $error) {
                    $io->text(sprintf('<comment>%s:</comment> %s', $account, $error));
                }
            } else {
                $io->text('Run with -v for detailed error messages.');
            }
        } elseif ($results['tweets'] > 0) {
            $io->success(sprintf('Successfully imported %d new tweet(s).', $results['tweets']));
        } else {
            $io->info('No new tweets found.');
        }
    }
}
