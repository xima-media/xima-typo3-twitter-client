<?php

namespace Xima\XimaTwitterClient\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Tweet extends AbstractEntity
{
    protected string $text = '';

    protected ?Account $account;

    protected string $id = '';

    protected string $authorId = '';

    protected string $username = '';

    protected string $name = '';

    protected ?FileReference $profileImage = null;

    /**
     * @var ObjectStorage<FileReference>|null
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected ?ObjectStorage $attachments = null;

    public function getText(): string
    {
        return $this->text;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProfileImage(): ?FileReference
    {
        return $this->profileImage;
    }

    public function getAttachments(): ?ObjectStorage
    {
        return $this->attachments;
    }

    public function __construct()
    {
        $this->attachments = new ObjectStorage();
    }

    public function getTextAsHtml(): string
    {
        $html = preg_replace('/(https:\/\/[^\s]+)/', '<a href="$0">$0</a>', $this->text);
        return preg_replace('/@([^\s]+)/', '<a href="https://twitter.com/$1">$0</a>', $html);
    }

}