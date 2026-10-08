<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agenda;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agenda>
 */
class AgendaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agenda::class);
    }

    /** @return Agenda[] agendas that are not archived, by title */
    public function findActive(): array
    {
        return $this->findBy(['deleted' => false], ['title' => 'ASC']);
    }

    /** @return Agenda[] */
    public function findArchived(): array
    {
        return $this->findBy(['deleted' => true], ['title' => 'ASC']);
    }

    /** @return Agenda[] agendas shown on the site */
    public function findPublic(): array
    {
        return $this->findBy(['deleted' => false, 'public' => true], ['title' => 'ASC']);
    }
}
