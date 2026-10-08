<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SearchPhrase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator as ORMPaginator;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class SearchPhraseRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private PaginatorInterface $paginator
    ) {
        parent::__construct($registry, SearchPhrase::class);
    }

    public function findByPhrase(string $phrase): ?SearchPhrase
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.searchPhrase = :phrase')
            ->setParameter('phrase', $phrase)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllOrdered(int $page = 1, int $limit = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('sp')
            ->orderBy('sp.dateSearched', 'DESC')
            ->getQuery();

        return $this->paginator->paginate($query, $page, $limit);
    }

    public function getSearchPhrasesQuery()
    {
        return $this->createQueryBuilder('sp')
            ->orderBy('sp.dateSearched', 'DESC')
            ->getQuery();
    }
}