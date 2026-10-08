<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SearchPhraseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchPhraseRepository::class)]
#[ORM\Table(name: 'search_phrases')]
class SearchPhrase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $searchPhrase;

    #[ORM\Column(type: 'integer')]
    private int $count = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateSearched;

    #[ORM\Column(length: 255)]
    private string $type;

    public function __construct()
    {
        $this->dateSearched = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSearchPhrase(): string
    {
        return $this->searchPhrase;
    }

    public function setSearchPhrase(string $searchPhrase): self
    {
        $this->searchPhrase = $searchPhrase;
        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    public function incrementCount(): self
    {
        $this->count++;
        return $this;
    }

    public function getDateSearched(): \DateTimeImmutable
    {
        return $this->dateSearched;
    }

    public function setDateSearched(\DateTimeImmutable $dateSearched): self
    {
        $this->dateSearched = $dateSearched;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function updateDateSearched(): self
    {
        $this->dateSearched = new \DateTimeImmutable();
        return $this;
    }
}