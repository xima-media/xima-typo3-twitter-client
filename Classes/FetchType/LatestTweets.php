<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\FetchType;

use Abraham\TwitterOAuth\TwitterOAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTwitterClient\Domain\Model\Account;
use Xima\XimaTwitterClient\Domain\Repository\TweetRepository;
use Xima\XimaTwitterClient\Exception\TwitterApiException;

class LatestTweets implements FetchTypeInterface
{
    protected Account $account;

    protected TweetRepository $tweetRepository;

    protected Folder $imageFolder;

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    public function setTweetRepository(TweetRepository $tweetRepository): void
    {
        $this->tweetRepository = $tweetRepository;
    }

    public function fetchTweets(TwitterOAuth $connection, string $userId): int
    {
        $params = [
            'expansions' => 'author_id,attachments.media_keys',
            'max_results' => (string)$this->account->getMaxResults(),
            'media.fields' => 'url,type,media_key,preview_image_url,alt_text',
            'user.fields' => 'name,id,profile_image_url',
            'tweet.fields' => 'created_at',
        ];

        $options = GeneralUtility::trimExplode(',', $this->account->getFetchOptions(), true);
        if (count($options) > 0) {
            $params['exclude'] = implode(',', $options);
        }

        $response = $connection->get('users/' . $userId . '/tweets', $params);

        if (isset($response->errors) && count($response->errors) > 0) {
            throw TwitterApiException::fromApiResponse($response);
        }

        if (!isset($response->data) || count($response->data) === 0) {
            throw TwitterApiException::noTweetsFound($this->account->getUsername());
        }

        $tweetsToPersist = $this->filterResponse($response);

        return $this->saveTweets($tweetsToPersist);
    }

    protected function saveTweets($response): int
    {
        $data = ['tx_ximatwitterclient_domain_model_tweet' => [], 'sys_file_reference' => []];
        foreach ($response->data as $key => $tweet) {
            $attachmentIds = [];

            foreach ($tweet?->attachments->media_keys ?? [] as $key2 => $mediaKey) {
                $sysFileIdentifier = $this->saveAttachment($response, $mediaKey);

                if (!$sysFileIdentifier) {
                    continue;
                }

                $attachmentIds[] = 'NEW' . $key . 'FILE' . $key2;

                $data['sys_file_reference']['NEW' . $key . 'FILE' . $key2] = [
                    'table_local' => 'sys_file',
                    'uid_local' => $sysFileIdentifier,
                    'tablenames' => 'tx_ximatwitterclient_domain_model_tweet',
                    'uid_foreign' => 'NEW' . $key,
                    'fieldname' => 'attachments',
                    'pid' => $this->account->getPid(),
                ];
            }

            $data['tx_ximatwitterclient_domain_model_tweet']['NEW' . $key] = [
                'account' => $this->account->getUid(),
                'pid' => $this->account->getPid(),
                'id' => $tweet->id,
                'author_id' => $tweet->author_id,
                'text' => $tweet->text,
                'attachments' => implode(',', $attachmentIds),
                'date' => (new \DateTime($tweet->created_at))->getTimestamp(),
            ];

            $user = $this->getUserForTweet($response, $tweet);
            if ($user) {
                $profileImageId = $this->downloadImage($user->profile_image_url);
                $data['tx_ximatwitterclient_domain_model_tweet']['NEW' . $key]['username'] = $user->username;
                $data['tx_ximatwitterclient_domain_model_tweet']['NEW' . $key]['name'] = $user->name;
                $data['tx_ximatwitterclient_domain_model_tweet']['NEW' . $key]['profile_image'] = 'NEW' . $key . 'PROFILE';
                $data['sys_file_reference']['NEW' . $key . 'PROFILE'] = [
                    'table_local' => 'sys_file',
                    'uid_local' => $profileImageId,
                    'tablenames' => 'tx_ximatwitterclient_domain_model_tweet',
                    'uid_foreign' => 'NEW' . $key,
                    'fieldname' => 'profile_image',
                    'pid' => $this->account->getPid(),
                ];
            }
        }

        if (count($data['tx_ximatwitterclient_domain_model_tweet'])) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();
        }

        return count($data['tx_ximatwitterclient_domain_model_tweet']);
    }

    protected function getUserForTweet($response, $tweet): ?\stdClass
    {
        foreach ($response->includes->users as $user) {
            if ($user->id === $tweet->author_id) {
                return $user;
            }
        }
        return null;
    }

    protected function saveAttachment($response, string $mediaKey): ?int
    {
        $uid = null;

        foreach ($response->includes->media as $media) {
            if ($media->media_key !== $mediaKey) {
                continue;
            }

            if ($media->type === 'photo') {
                $uid = $this->downloadImage($media->url);
            }

            if ($media->type === 'video') {
                $uid = $this->downloadImage($media->preview_image_url);
            }
        }

        return $uid;
    }

    protected function downloadImage(string $imageUrl): int
    {
        $filename = basename($imageUrl);

        if ($this->imageFolder->hasFile($filename)) {
            /** @var File $existingFile */
            $existingFile = $this->imageFolder->getFile($filename);
            return $existingFile->getUid();
        }

        $file = $this->imageFolder->createFile($filename);
        $tempFile = $file->getForLocalProcessing();
        $client = GeneralUtility::makeInstance(Client::class);
        $resource = Utils::tryFopen($tempFile, 'w');
        $client->request('GET', $imageUrl, ['sink' => $resource]);
        $file->setContents(file_get_contents($tempFile));

        return $file->getUid();
    }

    protected function filterResponse($response): \stdClass
    {
        $ids = $this->getTweetIdsFromResponse($response);
        $tweetKeys = $this->tweetRepository->findTweetsByIds($ids);
        $idsToIgnore = array_unique(array_map(static fn ($tweet) => $tweet['id'], $tweetKeys));

        foreach ($response->data as $key => $tweet) {
            if (in_array($tweet->id, $idsToIgnore)) {
                unset($response->data[$key]);
            }
        }

        return $response;
    }

    /**
     * @return string[]
     */
    public function getTweetIdsFromResponse($tweets): array
    {
        $ids = [];
        foreach ($tweets->data as $tweet) {
            $ids[] = $tweet->id;
        }
        return $ids;
    }

    public function setImageFolder(Folder $folder): void
    {
        $this->imageFolder = $folder;
    }
}
