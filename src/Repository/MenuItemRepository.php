<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /** @return MenuItem[] */
    public function findRootItemsByMenu(Menu $menu): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.menu = :menu')
            ->andWhere('mi.parent IS NULL')
            ->andWhere('mi.enabled = true')
            ->andWhere('mi.deleted = false')
            ->setParameter('menu', $menu)
            ->orderBy('mi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return MenuItem[] */
    public function findChildren(MenuItem $parent): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.parent = :parent')
            ->andWhere('mi.enabled = true')
            ->andWhere('mi.deleted = false')
            ->setParameter('parent', $parent)
            ->orderBy('mi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMenuAndEnabled(Menu $menu, bool $enabled = true): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.menu = :menu')
            ->andWhere('mi.enabled = :enabled')
            ->andWhere('mi.deleted = false')
            ->setParameter('menu', $menu)
            ->setParameter('enabled', $enabled)
            ->orderBy('mi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxSortOrder(Menu $menu, ?MenuItem $parent = null): int
    {
        $qb = $this->createQueryBuilder('mi')
            ->select('COALESCE(MAX(mi.sortOrder), 0)')
            ->andWhere('mi.menu = :menu')
            ->andWhere('mi.deleted = false')
            ->setParameter('menu', $menu);

        if ($parent !== null) {
            $qb->andWhere('mi.parent = :parent')
               ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('mi.parent IS NULL');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return MenuItem[] */
    public function findTreeByMenu(Menu $menu): array
    {
        $items = $this->findByMenuAndEnabled($menu);
        $tree = [];

        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[$item->getId()] = $item;
        }

        foreach ($items as $item) {
            if ($item->getParent() === null) {
                $tree[] = $item;
            } else {
                $parentId = $item->getParent()->getId();
                if (isset($itemMap[$parentId])) {
                    // Children are already loaded via the Collection
                }
            }
        }

        return $tree;
    }
}