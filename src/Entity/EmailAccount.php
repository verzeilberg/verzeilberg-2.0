<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\EmailAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailAccountRepository::class)]
#[ORM\Table(name: 'email_account')]
#[ORM\HasLifecycleCallbacks]
class EmailAccount
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var Collection<int, EmailMessage> */
    #[ORM\OneToMany(mappedBy: 'account', targetEntity: EmailMessage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    /** @return Collection<int, EmailMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $imapHost;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    private int $imapPort = 993;

    #[ORM\Column(length: 50)]
    private string $encryption = 'ssl';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $username;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $password;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sentFolder = 'INBOX.Sent';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $draftsFolder = 'INBOX.Drafts';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trashFolder = 'INBOX.Trash';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpHost = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $smtpPort = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $smtpEncryption = 'tls';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpUsername = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpPassword = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $useImapForSmtp = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalMessages = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $unreadMessages = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lastSyncedUid = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getImapHost(): string
    {
        return $this->imapHost;
    }

    public function setImapHost(string $imapHost): self
    {
        $this->imapHost = $imapHost;
        return $this;
    }

    public function getImapPort(): int
    {
        return $this->imapPort;
    }

    public function setImapPort(int $imapPort): self
    {
        $this->imapPort = $imapPort;
        return $this;
    }

    public function getEncryption(): string
    {
        return $this->encryption;
    }

    public function setEncryption(string $encryption): self
    {
        $this->encryption = $encryption;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        if ($password !== null) {
            $this->password = $password;
        }
        return $this;
    }

    public function getSentFolder(): ?string
    {
        return $this->sentFolder;
    }

    public function setSentFolder(?string $sentFolder): self
    {
        $this->sentFolder = $sentFolder;
        return $this;
    }

    public function getDraftsFolder(): ?string
    {
        return $this->draftsFolder;
    }

    public function setDraftsFolder(?string $draftsFolder): self
    {
        $this->draftsFolder = $draftsFolder;
        return $this;
    }

    public function getTrashFolder(): ?string
    {
        return $this->trashFolder;
    }

    public function setTrashFolder(?string $trashFolder): self
    {
        $this->trashFolder = $trashFolder;
        return $this;
    }

    public function getSmtpHost(): ?string
    {
        return $this->smtpHost;
    }

    public function setSmtpHost(?string $smtpHost): self
    {
        $this->smtpHost = $smtpHost;
        return $this;
    }

    public function getSmtpPort(): ?int
    {
        return $this->smtpPort;
    }

    public function setSmtpPort(?int $smtpPort): self
    {
        $this->smtpPort = $smtpPort;
        return $this;
    }

    public function getSmtpEncryption(): ?string
    {
        return $this->smtpEncryption;
    }

    public function setSmtpEncryption(?string $smtpEncryption): self
    {
        $this->smtpEncryption = $smtpEncryption;
        return $this;
    }

    public function getSmtpUsername(): ?string
    {
        return $this->smtpUsername;
    }

    public function setSmtpUsername(?string $smtpUsername): self
    {
        $this->smtpUsername = $smtpUsername;
        return $this;
    }

    public function getSmtpPassword(): ?string
    {
        return $this->smtpPassword;
    }

    public function setSmtpPassword(?string $smtpPassword): self
    {
        $this->smtpPassword = $smtpPassword;
        return $this;
    }

    public function isUseImapForSmtp(): bool
    {
        return $this->useImapForSmtp;
    }

    public function setUseImapForSmtp(bool $useImapForSmtp): self
    {
        $this->useImapForSmtp = $useImapForSmtp;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;
        return $this;
    }

    public function getTotalMessages(): int
    {
        return $this->totalMessages;
    }

    public function setTotalMessages(int $totalMessages): self
    {
        $this->totalMessages = $totalMessages;
        return $this;
    }

    public function getUnreadMessages(): int
    {
        return $this->unreadMessages;
    }

    public function setUnreadMessages(int $unreadMessages): self
    {
        $this->unreadMessages = $unreadMessages;
        return $this;
    }

    public function getLastSyncedUid(): ?int
    {
        return $this->lastSyncedUid;
    }

    public function setLastSyncedUid(?int $lastSyncedUid): self
    {
        $this->lastSyncedUid = $lastSyncedUid;
        return $this;
    }

    public function getConnectionString(): string
    {
        return sprintf('{%s:%d/%s}%s', $this->imapHost, $this->imapPort, $this->encryption, $this->sentFolder ?? 'INBOX');
    }

    public function __toString(): string
    {
        return $this->name . ' (' . $this->email . ')';
    }
}