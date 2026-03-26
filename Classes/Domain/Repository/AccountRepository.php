<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use Xima\XimaTwitterClient\Domain\Model\Account;

class AccountRepository extends Repository
{
    /**
     * @return QueryResultInterface<Account>
     */
    public function findAllIgnorePid(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->setQuerySettings($query->getQuerySettings()->setRespectStoragePage(false));
        return $query->execute();
    }

    public function findByUidIgnorePid(int $uid): ?Account
    {
        $query = $this->createQuery();
        $query->setQuerySettings($query->getQuerySettings()->setRespectStoragePage(false));
        $query->matching($query->equals('uid', $uid));

        /** @var Account|null $result */
        $result = $query->execute()->getFirst();
        return $result;
    }
}
