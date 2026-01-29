<?php

namespace Xima\XimaTwitterClient\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class TweetRepository extends Repository
{
    /**
     * @param string[] $ids
     * @return string[]
     * @throws DBALException
     * @throws Exception
     */
    public function findTweetsByIds(array $ids): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatwitterclient_domain_model_tweet');
        $qb->getRestrictions()->removeAll();
        return $qb->select('id')
            ->from('tx_ximatwitterclient_domain_model_tweet')
            ->where(
                $qb->expr()->in('id', $qb->quoteArrayBasedValueListToStringList($ids))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
