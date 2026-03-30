<?php

namespace Xima\XimaTwitterClient\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Repository;

class TweetRepository extends Repository
{
    /**
     * Constructs a new Repository
     */
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    /**
     * @param string[] $ids
     * @return list<array<string, mixed>>
     */
    public function findTweetsByIds(array $ids): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatwitterclient_domain_model_tweet');
        $qb->getRestrictions()->removeAll();
        return $qb->select('id')
            ->from('tx_ximatwitterclient_domain_model_tweet')
            ->where(
                $qb->expr()->in('id', $qb->quoteArrayBasedValueListToStringList($ids))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findOlderThan(\DateTimeInterface $dateTime): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatwitterclient_domain_model_tweet');
        $qb->getRestrictions()->removeAll();
        return $qb->select('uid')
            ->from('tx_ximatwitterclient_domain_model_tweet')
            ->where(
                $qb->expr()->lt('date', $qb->createNamedParameter($dateTime->getTimestamp(), Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
