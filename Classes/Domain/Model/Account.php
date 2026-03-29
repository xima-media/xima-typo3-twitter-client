<?php

namespace Xima\XimaTwitterClient\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Account extends AbstractEntity
{
    protected string $username = '';

    protected string $fetchOptions = '';

    protected int $maxResults = 0;

    public function getMaxResults(): int
    {
        return $this->maxResults;
    }

    public function getFetchOptions(): string
    {
        return $this->fetchOptions;
    }

    public function getFetchType(): string
    {
        return $this->fetchType;
    }

    public function __construct(protected string $fetchType)
    {
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
