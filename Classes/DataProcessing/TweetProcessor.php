<?php

namespace Xima\XimaTwitterClient\DataProcessing;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use Xima\XimaTwitterClient\Domain\Model\Tweet;

class TweetProcessor implements DataProcessorInterface
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    /**
     * Fetches records from the database as an array
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     * @throws Exception
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $queryConfiguration = ['uidInList.' => ['field' => 'twitter'], 'pidInList' => 0];
        if (isset($processorConfiguration['accountUids'])) {
            $queryConfiguration = ['uidInList' => $processorConfiguration['accountUids'], 'pidInList' => 0];
        }
        $accounts = $cObj->getRecords('tx_ximatwitterclient_domain_model_account', $queryConfiguration);

        $accountUids = array_map(fn ($account) => $account['uid'], $accounts);

        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatwitterclient_domain_model_tweet');
        $query = $qb->select('*')
            ->from('tx_ximatwitterclient_domain_model_tweet')
            ->where(
                $qb->expr()->in('account', $qb->quoteArrayBasedValueListToStringList($accountUids))
            )
            ->orderBy('date', 'DESC');

        $maxItemConf = $processorConfiguration['maxItems'] ?? '';
        $maxItems = MathUtility::canBeInterpretedAsInteger($maxItemConf) ? (int)$maxItemConf : 0;
        if ($maxItems) {
            $query->setMaxResults($maxItemConf);
        }

        $results = $query->executeQuery()->fetchAllAssociative();

        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        $tweets = $dataMapper->map(Tweet::class, $results);

        $processedData['tweets'] = $tweets;

        return $processedData;
    }
}
