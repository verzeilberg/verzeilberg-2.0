<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Agenda test data; every test runs in a transaction that is rolled back afterwards.
 */
trait AgendaTestTrait
{
    protected EntityManagerInterface $em;
    protected Connection $connection;

    protected function beginAgendaTransaction(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
    }

    protected function rollBackAgendaTransaction(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    protected function createAgenda(string $title = 'Test agenda', bool $public = false, string $color = '#336699'): Agenda
    {
        $agenda = (new Agenda())->setTitle($title . ' ' . uniqid())->setPublic($public)->setColor($color);
        $this->em->persist($agenda);
        $this->em->flush();

        return $agenda;
    }

    protected function createItem(Agenda $agenda, string $title, string $start, string $end, bool $allDay = false): AgendaItem
    {
        $item = (new AgendaItem())->setAgenda($agenda)->setTitle($title);
        if ($allDay) {
            $item->setAllDayPeriod(new \DateTimeImmutable($start), new \DateTimeImmutable($end));
        } else {
            $item->setStartAt(new \DateTimeImmutable($start))->setEndAt(new \DateTimeImmutable($end));
        }
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }
}
