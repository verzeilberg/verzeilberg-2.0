<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use App\Repository\MenuItemRepository;
use App\Repository\Steam\SteamGameRepository;
use App\Service\HomepageSections;
use App\Service\Steam\SteamPrivacy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
        private BlogPostRepository $blogPostRepository,
        private EventRepository $eventRepository,
        private SteamGameRepository $steamGameRepository,
        private SteamPrivacy $steamPrivacy,
        private HomepageSections $homepageSections,
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $mainMenu = $this->menuRepository->findByName('main');
        $menuItems = $mainMenu ? $this->menuItemRepository->findRootItemsByMenu($mainMenu) : [];
        // Hidden blocks are not queried at all
        $latestPosts = $this->homepageSections->isVisible('blog') ? $this->blogPostRepository->findOnlinePosts(4, 0) : [];
        $upcomingEvents = $this->homepageSections->isVisible('event') ? $this->eventRepository->findUpcoming(1) : [];
        $showGames = $this->homepageSections->isVisible('games') && !$this->steamPrivacy->isActivityHidden();

        $featuredPost = null;
        $otherPosts = [];

        if (!empty($latestPosts)) {
            $featuredPost = array_shift($latestPosts);
            $otherPosts = $latestPosts;
        }

        return $this->render('home/index.html.twig', [
            'mainMenu' => $mainMenu,
            'menuItems' => $menuItems,
            'featuredPost' => $featuredPost,
            'otherPosts' => $otherPosts,
            'upcomingEvent' => $upcomingEvents ? $upcomingEvents[0] : null,
            // "Recently played" shows when games were played, so it follows the Steam privacy switch
            'recentGames' => $showGames ? $this->steamGameRepository->findRecentlyPlayed(4) : [],
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }
}
