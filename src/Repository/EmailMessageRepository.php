<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailAccount;
use App\Entity\EmailMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmailMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailMessage::class);
    }

    public function findByAccount(EmailAccount $account, string $folder = 'INBOX', int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.account = :account')
            ->andWhere('m.folder = :folder')
            ->andWhere('m.isDeleted = false')
            ->setParameter('account', $account)
            ->setParameter('folder', $folder)
            ->orderBy('m.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findUnreadByAccount(EmailAccount $account): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.account = :account')
            ->andWhere('m.isRead = false')
            ->andWhere('m.isDeleted = false')
            ->setParameter('account', $account)
            ->orderBy('m.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByAccount(EmailAccount $account, string $folder = 'INBOX'): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.account = :account')
            ->andWhere('m.folder = :folder')
            ->andWhere('m.isDeleted = false')
            ->setParameter('account', $account)
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnreadByAccount(EmailAccount $account): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.account = :account')
            ->andWhere('m.isRead = false')
            ->andWhere('m.isDeleted = false')
            ->setParameter('account', $account)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUid(EmailAccount $account, string $folder, string $uid): ?EmailMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.account = :account')
            ->andWhere('m.folder = :folder')
            ->andWhere('m.uid = :uid')
            ->setParameter('account', $account)
            ->setParameter('folder', $folder)
            ->setParameter('uid', $uid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByMessageId(string $messageId): ?EmailMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}