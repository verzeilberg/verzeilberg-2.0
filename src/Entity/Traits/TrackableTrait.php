<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

trait TrackableTrait
{
    #[ORM\Column(name: 'date_created', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateCreated = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'date_changed', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateChanged = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(name: 'date_deleted', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDeleted = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'deleted_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $deletedBy = null;

    #[ORM\Column(name: 'deleted', type: 'boolean', options: ['default' => false])]
    private bool $deleted = false;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function initializeDateCreated(): void
    {
        if ($this->dateCreated === null) {
            $this->dateCreated = new \DateTimeImmutable();
        }
    }

    public function getDateCreated(): \DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeImmutable $dateCreated): self
    {
        $this->dateCreated = $dateCreated;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getDateChanged(): ?\DateTimeImmutable
    {
        return $this->dateChanged;
    }

    public function setDateChanged(?\DateTimeImmutable $dateChanged): self
    {
        $this->dateChanged = $dateChanged;
        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): self
    {
        $this->changedBy = $changedBy;
        return $this;
    }

    public function getDateDeleted(): ?\DateTimeImmutable
    {
        return $this->dateDeleted;
    }

    public function setDateDeleted(?\DateTimeImmutable $dateDeleted): self
    {
        $this->dateDeleted = $dateDeleted;
        return $this;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;
        if ($deleted && $this->dateDeleted === null) {
            $this->dateDeleted = new \DateTimeImmutable();
        }
        return $this;
    }

    public function markAsDeleted(?User $deletedBy = null): self
    {
        $this->deleted = true;
        $this->dateDeleted = new \DateTimeImmutable();
        $this->deletedBy = $deletedBy;
        return $this;
    }

    public function touch(?User $changedBy = null): self
    {
        $this->dateChanged = new \DateTimeImmutable();
        $this->changedBy = $changedBy;
        return $this;
    }
}