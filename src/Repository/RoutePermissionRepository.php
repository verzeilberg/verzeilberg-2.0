<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoutePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoutePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutePermission::class);
    }

    /** @return list<RoutePermission> most specific rule first (see RoutePermission::getSpecificity()) */
    public function findAllEnabled(): array
    {
        return $this->sortBySpecificity($this->createQueryBuilder('rp')
            ->join('rp.permission', 'p')
            ->addSelect('p')
            ->andWhere('rp.enabled = true')
            ->andWhere('rp.deleted = false')
            ->getQuery()
            ->getResult());
    }

    /** @return list<RoutePermission> */
    public function findAllForOverview(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('rp')
            ->join('rp.permission', 'p')
            ->addSelect('p')
            ->andWhere('rp.deleted = false');

        if ($search !== '') {
            $qb->andWhere('rp.routePattern LIKE :search OR p.name LIKE :search OR rp.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->sortBySpecificity($qb->getQuery()->getResult());
    }

    public function findByRoute(string $route, ?string $method = null): ?RoutePermission
    {
        foreach ($this->findAllEnabled() as $rule) {
            if ($rule->matches($route, $method)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param list<RoutePermission> $rules
     * @return list<RoutePermission>
     */
    private function sortBySpecificity(array $rules): array
    {
        usort($rules, fn(RoutePermission $a, RoutePermission $b) => [$b->getSpecificity(), $a->getRoutePattern()] <=> [$a->getSpecificity(), $b->getRoutePattern()]);

        return $rules;
    }
}
