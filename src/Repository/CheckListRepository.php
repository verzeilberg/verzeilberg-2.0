<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckList;
use App\Entity\Friendship;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class CheckListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private PaginatorInterface $paginator
    ) {
        parent::__construct($registry, CheckList::class);
    }

    /** @param User|null $owner only the checklists of this user; null for all */
    public function findAllOrdered(int $page = 1, int $limit = 10, ?User $owner = null): PaginationInterface
    {
        $qb = $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = false')
            ->orderBy('cl.dateCreated', 'DESC');
        $this->filterOwner($qb, $owner);

        return $this->paginator->paginate($qb->getQuery(), $page, $limit);
    }

    /** @return CheckList[] the user's own checklists that aren't archived, by name */
    public function findOwnedBy(User $owner): array
    {
        return $this->createQueryBuilder('cl')
            ->andWhere('cl.owner = :owner')
            ->andWhere('cl.deleted = false')
            ->setParameter('owner', $owner)
            ->orderBy('cl.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function filterOwner(QueryBuilder $qb, ?User $owner): void
    {
        if ($owner !== null) {
            $qb->andWhere('cl.owner = :owner')->setParameter('owner', $owner);
        }
    }

    /**
     * Checklists that can be filled in outside the admin: public ones, and for a logged-in user also
     * the logged-in ones and the friends-only ones of their friends (and their own).
     *
     * @return CheckList[]
     */
    public function findOpenForSubmissions(?User $user): array
    {
        $qb = $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = false')
            ->orderBy('cl.name', 'ASC');

        if ($user === null) {
            return $qb->andWhere('cl.access = :public')
                ->setParameter('public', CheckList::ACCESS_PUBLIC)
                ->getQuery()
                ->getResult();
        }

        $friendOwners = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Friendship::class, 'f')
            ->andWhere('f.status = :accepted')
            ->andWhere('(f.requester = :user AND f.addressee = cl.owner) OR (f.addressee = :user AND f.requester = cl.owner)');

        return $qb
            ->andWhere($qb->expr()->orX(
                'cl.access IN (:open)',
                $qb->expr()->andX(
                    'cl.access = :friends',
                    $qb->expr()->orX('cl.owner = :user', $qb->expr()->exists($friendOwners->getDQL()))
                )
            ))
            ->setParameter('open', [CheckList::ACCESS_PUBLIC, CheckList::ACCESS_USER])
            ->setParameter('friends', CheckList::ACCESS_FRIENDS)
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /** @param User|null $owner only the checklists of this user; null for all */
    public function findAllArchived(int $page = 1, int $limit = 10, ?User $owner = null): PaginationInterface
    {
        $qb = $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = true')
            ->orderBy('cl.dateCreated', 'DESC');
        $this->filterOwner($qb, $owner);

        return $this->paginator->paginate($qb->getQuery(), $page, $limit);
    }

    public function findById(int $id): ?CheckList
    {
        return $this->createQueryBuilder('cl')
            ->andWhere('cl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdWithRelations(int $id): ?CheckList
    {
        return $this->createQueryBuilder('cl')
            ->leftJoin('cl.checkListFields', 'clf')
            ->addSelect('clf')
            ->leftJoin('clf.fieldType', 'ft')
            ->addSelect('ft')
            ->leftJoin('cl.checkListItems', 'cli')
            ->addSelect('cli')
            ->andWhere('cl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}