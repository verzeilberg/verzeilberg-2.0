<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\EmailMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailMessageRepository::class)]
#[ORM\Table(name: 'email_message')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['account_id', 'folder'])]
#[ORM\Index(columns: ['uid'])]
class EmailMessage
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmailAccount::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EmailAccount $account;

    #[ORM\Column(length: 100)]
    private string $folder;

    #[ORM\Column(length: 100)]
    private string $uid;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $fromAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $toAddresses = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ccAddresses = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bccAddresses = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attachments = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFlagged = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isAnswered = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDraft = false;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): EmailAccount
    {
        return $this->account;
    }

    public function setAccount(EmailAccount $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(?string $fromAddress): self
    {
        $this->fromAddress = $fromAddress;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;
        return $this;
    }

    public function getToAddresses(): ?string
    {
        return $this->toAddresses;
    }

    public function setToAddresses(?string $toAddresses): self
    {
        $this->toAddresses = $toAddresses;
        return $this;
    }

    public function getCcAddresses(): ?string
    {
        return $this->ccAddresses;
    }

    public function setCcAddresses(?string $ccAddresses): self
    {
        $this->ccAddresses = $ccAddresses;
        return $this;
    }

    public function getBccAddresses(): ?string
    {
        return $this->bccAddresses;
    }

    public function setBccAddresses(?string $bccAddresses): self
    {
        $this->bccAddresses = $bccAddresses;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): self
    {
        $this->receivedAt = $receivedAt;
        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): self
    {
        $this->bodyText = $bodyText;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): self
    {
        $this->bodyHtml = $bodyHtml;
        return $this;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setRead(bool $read): self
    {
        $this->isRead = $read;
        return $this;
    }

    public function isFlagged(): bool
    {
        return $this->isFlagged;
    }

    public function setFlagged(bool $flagged): self
    {
        $this->isFlagged = $flagged;
        return $this;
    }

    public function isAnswered(): bool
    {
        return $this->isAnswered;
    }

    public function setAnswered(bool $answered): self
    {
        $this->isAnswered = $answered;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->isDeleted = $deleted;
        return $this;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function setDraft(bool $draft): self
    {
        $this->isDraft = $draft;
        return $this;
    }

    public function getPreview(int $length = 200): string
    {
        $text = $this->bodyText ?: '';
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text;
    }

    public function __toString(): string
    {
        return $this->subject ?? '(no subject)';
    }
}