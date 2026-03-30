<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Xima\XimaTwitterClient\Domain\Repository\TweetRepository;

class CleanupTweetsCommand extends Command
{
    private const DEFAULT_LIFETIME_DAYS = 180;

    private const TABLE_TWEET = 'tx_ximatwitterclient_domain_model_tweet';

    private const TABLE_FILE_REFERENCE = 'sys_file_reference';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TweetRepository $tweetRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove tweets (and their images) older than a given lifetime')
            ->addOption(
                'lifetime',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum age of tweets in days (default: 180)',
                (string)self::DEFAULT_LIFETIME_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be deleted without actually deleting',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool)$input->getOption('dry-run');
        $lifetimeDays = (int)$input->getOption('lifetime');

        if ($lifetimeDays <= 0) {
            $io->error('Lifetime must be a positive number of days.');
            return Command::FAILURE;
        }

        $io->title('Twitter Cleanup');

        $threshold = new \DateTimeImmutable(sprintf('-%d days', $lifetimeDays));
        $io->text(sprintf('Removing tweets older than %s (%d days)...', $threshold->format('Y-m-d H:i:s'), $lifetimeDays));

        $oldTweets = $this->tweetRepository->findOlderThan($threshold);
        $tweetCount = count($oldTweets);

        if ($tweetCount === 0) {
            $io->info('No tweets found to clean up.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d tweet(s) to remove.', $tweetCount));

        if ($isDryRun) {
            $io->warning('Dry run: No tweets were deleted.');
            return Command::SUCCESS;
        }

        $tweetUids = array_map(static fn (array $row): int => (int)$row['uid'], $oldTweets);
        $deletedFiles = $this->deleteFilesAndReferences($tweetUids);
        $deletedTweets = $this->deleteTweets($tweetUids);

        $io->newLine();
        $io->success(sprintf('Deleted %d tweet(s) and %d file reference(s).', $deletedTweets, $deletedFiles));

        $this->logger->info('Cleaned up old tweets', [
            'deletedTweets' => $deletedTweets,
            'deletedFiles' => $deletedFiles,
            'thresholdDate' => $threshold->format('Y-m-d H:i:s'),
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param int[] $tweetUids
     */
    private function deleteFilesAndReferences(array $tweetUids): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FILE_REFERENCE);
        $qb->getRestrictions()->removeAll();

        $fileReferences = $qb->select('uid', 'uid_local')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $qb->expr()->in('uid_foreign', $qb->quoteArrayBasedValueListToIntegerList($tweetUids)),
                $qb->expr()->eq('tablenames', $qb->createNamedParameter(self::TABLE_TWEET)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $deletedFiles = 0;

        foreach ($fileReferences as $fileReference) {
            try {
                $file = $this->resourceFactory->getFileObject((int)$fileReference['uid_local']);
                $file->delete();
                $deletedFiles++;
            } catch (\Exception $e) {
                $this->logger->warning('Could not delete file', [
                    'fileReferenceUid' => $fileReference['uid'],
                    'fileUid' => $fileReference['uid_local'],
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $deletedFiles;
    }

    /**
     * @param int[] $tweetUids
     */
    private function deleteTweets(array $tweetUids): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE_TWEET);
        $qb->getRestrictions()->removeAll();

        return (int)$qb->delete(self::TABLE_TWEET)
            ->where(
                $qb->expr()->in('uid', $qb->quoteArrayBasedValueListToIntegerList($tweetUids)),
            )
            ->executeStatement();
    }
}
