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

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.enabled = true')
            ->orderBy('rp.priority', 'DESC')
            ->addOrderBy('rp.routePattern', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByRoute(string $route, ?string $method = null): ?RoutePermission
    {
        $permissions = $this->findAllEnabled();

        foreach ($permissions as $permission) {
            if ($permission->matches($route, $method)) {
                return $permission;
            }
        }

        return null;
    }

    public function findByPermissionName(string $permissionName): array
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.permissionName = :name')
            ->andWhere('rp.enabled = true')
            ->orderBy('rp.priority', 'DESC')
            ->setParameter('name', $permissionName)
            ->getQuery()
            ->getResult();
    }
}