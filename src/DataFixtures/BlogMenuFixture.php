<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Menu;
use App\Entity\MenuItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BlogMenuFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $menu = $manager->getRepository(Menu::class)->findOneBy(['name' => 'main']);
        
        if (!$menu) {
            return;
        }

        // Check if Blog already exists
        $existingBlog = $manager->getRepository(\App\Entity\MenuItem::class)
            ->findOneBy(['menu' => $menu, 'label' => 'Blog']);
        
        if ($existingBlog) {
            return;
        }

        $maxSortOrder = $manager->getRepository(\App\Entity\MenuItem::class)
            ->createQueryBuilder('mi')
            ->select('COALESCE(MAX(mi.sortOrder), 0)')
            ->where('mi.menu = :menu')
            ->andWhere('mi.parent IS NULL')
            ->andWhere('mi.deleted = false')
            ->setParameter('menu', $menu)
            ->getQuery()
            ->getSingleScalarResult();

        $blogItem = new \App\Entity\MenuItem();
        $blogItem->setLabel('Blog');
        $blogItem->setLink('blog_index');
        $blogItem->setIcon('fa-blog');
        $blogItem->setSortOrder((int)$maxSortOrder + 1);
        $blogItem->setMenu($menu);
        $blogItem->setEnabled(true);

        $manager->persist($blogItem);
        $manager->flush();
    }
}