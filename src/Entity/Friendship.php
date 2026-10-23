<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FriendshipRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A friend request from one user to another; once accepted the two are friends.
 * There is at most one row per pair: declining, cancelling or unfriending removes it.
 */
#[ORM\Entity(repositoryClass: FriendshipRepository::class)]
#[ORM\Table(name: 'friendship')]
#[ORM\UniqueConstraint(name: 'friendship_pair', columns: ['requester_id', 'addressee_id'])]
class Friendship
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $addressee;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateRequested;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateAccepted = null;

    public function __construct(User $requester, User $addressee)
    {
        $this->requester = $requester;
        $this->addressee = $addressee;
        $this->dateRequested = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function getAddressee(): User
    {
        return $this->addressee;
    }

    /** The other user of the pair, seen from $user */
    public function getOther(User $user): User
    {
        return $this->requester === $user ? $this->addressee : $this->requester;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function accept(): self
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->dateAccepted = new \DateTimeImmutable();
        return $this;
    }

    public function getDateRequested(): \DateTimeImmutable
    {
        return $this->dateRequested;
    }

    public function getDateAccepted(): ?\DateTimeImmutable
    {
        return $this->dateAccepted;
    }
}
