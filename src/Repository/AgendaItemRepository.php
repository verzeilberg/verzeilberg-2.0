<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgendaItem>
 */
class AgendaItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgendaItem::class);
    }

    /**
     * Items of the given agendas that (partly) take place in [from, to): also items that start before
     * or end after the period, such as a week-long event in a month view.
     *
     * @param Agenda[] $agendas
     *
     * @return AgendaItem[] ordered by start
     */
    public function findInPeriod(array $agendas, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($agendas === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->addSelect('a')
            ->join('i.agenda', 'a')
            ->andWhere('i.agenda IN (:agendas)')
            ->andWhere('i.startAt < :to')
            ->andWhere('i.endAt >= :from')
            ->setParameter('agendas', $agendas)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('i.allDay', 'DESC')
            ->addOrderBy('i.startAt', 'ASC')
            ->addOrderBy('i.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUid(Agenda $agenda, string $uid): ?AgendaItem
    {
        return $this->findOneBy(['agenda' => $agenda, 'uid' => $uid]);
    }
}
