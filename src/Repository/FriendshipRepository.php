<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Friendship;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class FriendshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Friendship::class);
    }

    /** The request or friendship between two users, whichever of them asked */
    public function findBetween(User $a, User $b): ?Friendship
    {
        return $this->createQueryBuilder('f')
            ->andWhere('(f.requester = :a AND f.addressee = :b) OR (f.requester = :b AND f.addressee = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function areFriends(User $a, User $b): bool
    {
        return $this->findBetween($a, $b)?->isAccepted() ?? false;
    }

    /** @return list<User> the user's friends (active, not deleted), by name */
    public function findFriendsOf(User $user, bool $onlyPublic = false): array
    {
        $friendships = $this->withUsers($this->createQueryBuilder('f'))
            ->andWhere('f.requester = :user OR f.addressee = :user')
            ->andWhere('f.status = :accepted')
            ->setParameter('user', $user)
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->getQuery()
            ->getResult();

        $friends = array_filter(
            array_map(fn(Friendship $f) => $f->getOther($user), $friendships),
            fn(User $friend) => $friend->isActive() && !$friend->isDeleted() && (!$onlyPublic || $friend->isProfilePublic())
        );
        usort($friends, fn(User $a, User $b) => strcasecmp($a->getFullName(), $b->getFullName()));

        return $friends;
    }

    /** @return list<Friendship> requests others sent to the user, newest first */
    public function findIncomingRequests(User $user): array
    {
        return $this->pendingQuery()
            ->andWhere('f.addressee = :user')
            ->andWhere('r.deleted = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Friendship> requests the user sent that aren't answered yet, newest first */
    public function findOutgoingRequests(User $user): array
    {
        return $this->pendingQuery()
            ->andWhere('f.requester = :user')
            ->andWhere('a.deleted = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function countIncomingRequests(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->join('f.requester', 'r')
            ->andWhere('f.addressee = :user')
            ->andWhere('f.status = :pending')
            ->andWhere('r.deleted = false')
            ->setParameter('user', $user)
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function pendingQuery(): QueryBuilder
    {
        return $this->withUsers($this->createQueryBuilder('f'))
            ->andWhere('f.status = :pending')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->orderBy('f.dateRequested', 'DESC');
    }

    /** Fetches both users and their details in the same query, for avatars and names */
    private function withUsers(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->join('f.requester', 'r')->addSelect('r')
            ->leftJoin('r.details', 'rd')->addSelect('rd')
            ->join('f.addressee', 'a')->addSelect('a')
            ->leftJoin('a.details', 'ad')->addSelect('ad');
    }
}
