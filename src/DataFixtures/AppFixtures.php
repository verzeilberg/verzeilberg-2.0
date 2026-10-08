<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\BlogComment;
use App\Entity\BlogFile;
use App\Entity\BlogImage;
use App\Entity\BlogPost;
use App\Entity\BlogYouTube;
use App\Entity\Event;
use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserDetails;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('nl_NL');

        // Create permissions
        $permissions = $this->createPermissions($manager);

        // Create roles
        $roles = $this->createRoles($manager, $permissions);

        // Create admin user
        $admin = $this->createAdminUser($manager, $roles['admin']);

        // Create test user
        $user = $this->createTestUser($manager, $roles['user']);

        // Create menus
        $this->createMenus($manager);

        // Create blog categories
        $categories = $this->createBlogCategories($manager);

        // Create blog posts
        $posts = $this->createBlogPosts($manager, $admin, $categories);

        // Create blog comments
        $this->createBlogComments($manager, $posts, $admin, $user);

        // Create events
        $this->createEvents($manager, $categories);

        $manager->flush();
    }

    private function createPermissions(ObjectManager $manager): array
    {
        $permissionNames = [
            'blog.view' => 'View blog posts',
            'blog.create' => 'Create blog posts',
            'blog.edit' => 'Edit blog posts',
            'blog.delete' => 'Delete blog posts',
            'blog.publish' => 'Publish blog posts',
            'event.view' => 'View events',
            'event.create' => 'Create events',
            'event.edit' => 'Edit events',
            'event.delete' => 'Delete events',
            'user.view' => 'View users',
            'user.edit' => 'Edit users',
            'user.delete' => 'Delete users',
            'admin.access' => 'Access admin panel',
            'menu.manage' => 'Manage menus',
        ];

        $permissions = [];
        foreach ($permissionNames as $name => $description) {
            $permission = new Permission();
            $permission->setName($name);
            $permission->setDescription($description);
            $manager->persist($permission);
            $permissions[$name] = $permission;
        }

        $manager->flush();
        return $permissions;
    }

    private function createRoles(ObjectManager $manager, array $permissions): array
    {
        // Admin role - all permissions
        $adminRole = new Role();
        $adminRole->setName('ROLE_ADMIN');
        $adminRole->setDescription('Administrator with full access');
        foreach ($permissions as $permission) {
            $adminRole->addPermission($permission);
        }
        $manager->persist($adminRole);

        // Editor role - blog and event management
        $editorRole = new Role();
        $editorRole->setName('ROLE_EDITOR');
        $editorRole->setDescription('Content editor');
        $editorPermissions = [
            'blog.view', 'blog.create', 'blog.edit', 'blog.publish',
            'event.view', 'event.create', 'event.edit',
            'user.view',
        ];
        foreach ($editorPermissions as $permName) {
            $editorRole->addPermission($permissions[$permName]);
        }
        $manager->persist($editorRole);

        // Author role - can create/edit own content
        $authorRole = new Role();
        $authorRole->setName('ROLE_AUTHOR');
        $authorRole->setDescription('Content author');
        $authorPermissions = [
            'blog.view', 'blog.create', 'blog.edit',
            'event.view',
        ];
        foreach ($authorPermissions as $permName) {
            $authorRole->addPermission($permissions[$permName]);
        }
        $manager->persist($authorRole);

        // User role - basic access
        $userRole = new Role();
        $userRole->setName('ROLE_USER');
        $userRole->setDescription('Regular user');
        $userRole->addPermission($permissions['blog.view']);
        $userRole->addPermission($permissions['event.view']);
        $manager->persist($userRole);

        $manager->flush();

        return [
            'admin' => $adminRole,
            'editor' => $editorRole,
            'author' => $authorRole,
            'user' => $userRole,
        ];
    }

    private function createAdminUser(ObjectManager $manager, Role $adminRole): User
    {
        $admin = new User();
        $admin->setEmail('admin@verzeilberg.nl');
        $admin->setFullName('Admin User');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setStatus(User::STATUS_ACTIVE);
        $admin->addRole($adminRole);

        $details = new UserDetails();
        $details->setFirstName('Admin');
        $details->setLastName('User');
        $details->setCatchLine('Site administrator');
        $details->setBio('Administrator of Verzeilberg.nl');
        $details->setLocation('Netherlands');
        $details->setUser($admin);
        $admin->setDetails($details);

        $manager->persist($admin);
        $manager->persist($details);

        return $admin;
    }

    private function createTestUser(ObjectManager $manager, Role $userRole): User
    {
        $user = new User();
        $user->setEmail('user@verzeilberg.nl');
        $user->setFullName('Test User');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $user->setStatus(User::STATUS_ACTIVE);
        $user->addRole($userRole);

        $details = new UserDetails();
        $details->setFirstName('Test');
        $details->setLastName('User');
        $details->setCatchLine('Regular user');
        $details->setBio('Test user for development');
        $details->setLocation('Netherlands');
        $details->setUser($user);
        $user->setDetails($details);

        $manager->persist($user);
        $manager->persist($details);

        return $user;
    }

    private function createMenus(ObjectManager $manager): void
    {
        // Main menu
        $mainMenu = new Menu();
        $mainMenu->setName('main');
        $manager->persist($mainMenu);

        $mainItems = [
            ['label' => 'Home', 'link' => 'app_home', 'icon' => 'fa-home', 'order' => 1],
            ['label' => 'About', 'link' => 'app_about', 'icon' => 'fa-info-circle', 'order' => 2],
            ['label' => 'Events', 'link' => 'app_events', 'icon' => 'fa-calendar-alt', 'order' => 3],
            ['label' => 'Games', 'link' => 'app_games', 'icon' => 'fa-gamepad', 'order' => 4],
        ];

        foreach ($mainItems as $itemData) {
            $item = new MenuItem();
            $item->setLabel($itemData['label']);
            $item->setLink($itemData['link']);
            $item->setIcon($itemData['icon']);
            $item->setSortOrder($itemData['order']);
            $item->setMenu($mainMenu);
            $manager->persist($item);
        }

        // Footer menu
        $footerMenu = new Menu();
        $footerMenu->setName('footer');
        $manager->persist($footerMenu);

        $footerItems = [
            ['label' => 'Contact', 'link' => '#', 'icon' => 'fa-envelope', 'order' => 1],
            ['label' => 'Privacy', 'link' => '#', 'icon' => 'fa-shield-alt', 'order' => 2],
            ['label' => 'Terms', 'link' => '#', 'icon' => 'fa-file-contract', 'order' => 3],
        ];

        foreach ($footerItems as $itemData) {
            $item = new MenuItem();
            $item->setLabel($itemData['label']);
            $item->setLink($itemData['link']);
            $item->setIcon($itemData['icon']);
            $item->setSortOrder($itemData['order']);
            $item->setMenu($footerMenu);
            $manager->persist($item);
        }
    }

    private function createBlogCategories(ObjectManager $manager): array
    {
        $categoryData = [
            ['name' => 'Sailing', 'slug' => 'sailing', 'description' => 'Sailing adventures, regattas, and maritime experiences'],
            ['name' => 'Technology', 'slug' => 'tech', 'description' => 'Tech projects, software development, and open source'],
            ['name' => 'Life', 'slug' => 'life', 'description' => 'Personal stories, travel, and life adventures'],
        ];

        $categories = [];
        foreach ($categoryData as $data) {
            $category = new Category();
            $category->setName($data['name']);
            $category->setSlug($data['slug']);
            $category->setDescription($data['description']);
            $category->setEnabled(true);
            $manager->persist($category);
            $categories[$data['slug']] = $category;
        }
        $manager->flush();

        return $categories;
    }

    private function createBlogPosts(ObjectManager $manager, User $author, array $categories): array
    {
        $posts = [
            [
                'title' => 'Sailing the IJsselmeer: A Perfect Weekend Getaway',
                'slug' => 'sailing-ijsselmeer-weekend-getaway',
                'introText' => 'Discover the beauty of the IJsselmeer, the largest lake in the Netherlands, perfect for a weekend sailing adventure.',
                'content' => <<<HTML
<h2>Introduction</h2>
<p>The IJsselmeer, once the Zuiderzee, is now the largest freshwater lake in the Netherlands. It offers perfect conditions for both novice and experienced sailors.</p>

<h2>Best Routes</h2>
<ul>
    <li><strong>Amsterdam → Enkhuizen:</strong> Historic harbor with beautiful VOC architecture</li>
    <li><strong>Enkhuizen → Stavoren:</strong> Cross the lake to Friesland</li>
    <li><strong>Stavoren → Makkum:</strong> Charming fishing village</li>
</ul>

<h2>Tips for Beginners</h2>
<p>Check the weather forecast, bring layers, and don't forget your sailing license if required. The lake can get choppy with strong winds.</p>

<h2>Conclusion</h2>
<p>A weekend on the IJsselmeer combines relaxation with adventure. Highly recommended for anyone in the Netherlands!</p>
HTML,
                'status' => BlogPost::STATUS_ONLINE,
                'categories' => ['sailing'],
                'dateOnline' => new \DateTimeImmutable('-5 days'),
            ],
            [
                'title' => 'Migrating from Laminas to Symfony 8.1: Lessons Learned',
                'slug' => 'migrating-laminas-symfony-81-lessons',
                'introText' => 'Our journey migrating Verzeilberg.nl from a legacy Laminas (Zend Framework) application to modern Symfony 8.1 with PHP 8.4.',
                'content' => <<<HTML
<h2>Why We Migrated</h2>
<p>After years on Laminas (formerly Zend Framework), we needed modern features: PHP 8.4 support, better developer experience, and a more active ecosystem.</p>

<h2>Key Challenges</h2>
<ul>
    <li>Entity migration from Laminas annotations to PHP 8 attributes</li>
    <li>Custom authentication/authorization to Symfony Security</li>
    <li>Laminas MVC controllers to Symfony controllers</li>
    <li>View helpers to Twig extensions</li>
</ul>

<h2>Benefits Gained</h2>
<p>Symfony 8.1 provides: better dependency injection, MakerBundle for scaffolding, improved security, and a vibrant community.</p>

<h2>Migration Strategy</h2>
<p>We used the Strangler Fig pattern: gradually replacing modules while keeping the old system running.</p>
HTML,
                'status' => BlogPost::STATUS_ONLINE,
                'categories' => ['tech'],
                'dateOnline' => new \DateTimeImmutable('-3 days'),
            ],
            [
                'title' => 'Why I Switched from MacBook to Linux for Development',
                'slug' => 'switched-macbook-linux-development',
                'introText' => 'After 10 years on macOS, I made the switch to Linux (Fedora) as my daily driver. Here\'s why and how it went.',
                'content' => <<<HTML
<h2>The Catalyst</h2>
<p>Apple's direction with M-series chips and limited repairability pushed me to explore alternatives.</p>

<h2>Why Fedora?</h2>
<ul>
    <li>Cutting-edge packages (PHP 8.4, latest tools)</li>
    <li>Excellent Wayland support</li>
    <li>Strong container/virtualization support</li>
    <li>GNOME desktop is polished</li>
</ul>

<h2>Development Experience</h2>
<p>Docker runs natively (no VM), VS Code works perfectly, and the terminal is first-class. PHP, Node, Go, Rust all install via dnf.</p>

<h2>Challenges</h2>
<p>Some proprietary apps don't run (Adobe Creative Suite), but alternatives exist. Battery life on laptop is slightly worse.</p>

<h2>Verdict</h2>
<p>No regrets. The freedom and performance are worth it.</p>
HTML,
                'status' => BlogPost::STATUS_ONLINE,
                'categories' => ['tech', 'life'],
                'dateOnline' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'title' => 'Round Texel 2024: Race Report',
                'slug' => 'round-texel-2024-race-report',
                'introText' => 'The famous Round Texel race is the largest catamaran race in the world. Here\'s my experience from the 2024 edition.',
                'content' => <<<HTML
<h2>Pre-Race</h2>
<p>Early start at 06:00 from Texel. Weather forecast: SW 4-5 Beaufort, perfect for our F18 catamaran.</p>

<h2>The Race</h2>
<p>Start was clean. First reach to the north buoy was fast - hitting 18 knots. The long leg along the North Sea coast was challenging with waves.</p>

<h2>Results</h2>
<p>Finished 47th out of 120 in our class. Not bad for our first time! The atmosphere in the harbor afterwards was incredible.</p>

<h2>Next Year</h2>
<p>Definitely returning. Better preparation, lighter boat, aiming for top 30.</p>
HTML,
                'status' => BlogPost::STATUS_ONLINE,
                'categories' => ['sailing'],
                'dateOnline' => new \DateTimeImmutable('+2 days'), // Scheduled
            ],
            [
                'title' => 'Building a Custom Mechanical Keyboard',
                'slug' => 'building-custom-mechanical-keyboard',
                'introText' => 'A deep dive into building my first custom mechanical keyboard: parts selection, assembly, and the joy of typing on something truly yours.',
                'content' => <<<HTML
<h2>Why Custom?</h2>
<p>After years of membrane keyboards, I wanted something that feels great and matches my aesthetic.</p>

<h2>Parts List</h2>
<ul>
    <li><strong>PCB:</strong> QMK/VIA compatible 65%</li>
    <li><strong>Switches:</strong> Gateron Oil Kings (linear, lubed)</li>
    <li><strong>Keycaps:</strong> GMK Olivia++ (beautiful teal/pink)</li>
    <li><strong>Case:</strong> Aluminum 65% with brass weight</li>
    <li><strong>Stabilizers:</strong> Durock V2 (clipped & lubed)</li>
</ul>

<h2>Assembly Process</h2>
<p>Soldering switches, lubing stabilizers, foam mod, tape mod. Took about 4 hours total.</p>

<h2>Result</h2>
<p>Incredible typing experience. Thock sound profile, zero ping, perfect weight. Worth every euro and hour.</p>
HTML,
                'status' => BlogPost::STATUS_ONLINE,
                'categories' => ['tech', 'life'],
                'dateOnline' => new \DateTimeImmutable('-10 days'),
            ],
            [
                'title' => 'Draft Post: Future Sailing Plans',
                'slug' => 'future-sailing-plans',
                'introText' => 'Planning next season\'s adventures...',
                'content' => '<p>This is a draft post with ideas for next year.</p>',
                'status' => BlogPost::STATUS_DRAFT,
                'categories' => ['sailing'],
            ],
            [
                'title' => 'Archived: Old Tech Stack Comparison',
                'slug' => 'old-tech-stack-comparison',
                'introText' => 'Comparison of PHP frameworks from 2018.',
                'content' => '<p>This post is outdated and archived.</p>',
                'status' => BlogPost::STATUS_ARCHIVED,
                'categories' => ['tech'],
                'dateOnline' => new \DateTimeImmutable('-2 years'),
            ],
        ];

        foreach ($posts as $postData) {
            $post = new BlogPost();
            $post->setTitle($postData['title']);
            $post->setSlug($postData['slug']);
            $post->setIntroText($postData['introText']);
            $post->setContent($postData['content']);
            $post->setStatus($postData['status']);
            $post->setDateOnline($postData['dateOnline'] ?? new \DateTimeImmutable());
            $post->setTimeOnline(new \DateTimeImmutable('09:00:00'));

            foreach ($postData['categories'] as $catSlug) {
                if (isset($categories[$catSlug])) {
                    $post->addCategory($categories[$catSlug]);
                }
            }

            // Add YouTube video to some posts
            if (in_array($postData['slug'], ['sailing-ijsselmeer-weekend-getaway', 'round-texel-2024-race-report'])) {
                $youtube = new BlogYouTube();
                $youtube->setVideoId('dQw4w9WgXcQ');
                $youtube->setTitle('Related Video');
                $youtube->setSortOrder(0);
                $post->addYouTube($youtube);
            }

            // Add placeholder image using placehold.co
            $image = new BlogImage();
            $slug = $postData['slug'];
            $image->setFilename("{$slug}.jpg");
            $image->setPath("https://placehold.co/800x600/png?text=" . urlencode($postData['title']) . "&font=roboto");
            $image->setAlt($postData['title']);
            $image->setSortOrder(0);
            $post->addImage($image);

            $manager->persist($post);
            $createdPosts[$postData['slug']] = $post;
        }

        $manager->flush();
        return $createdPosts;
    }

    private function createEvents(ObjectManager $manager, array $categories): void
    {
        $events = [
            [
                'title' => 'Sailing Regatta IJsselmeer 2025',
                'startDate' => new \DateTimeImmutable('+2 weeks'),
                'endDate' => new \DateTimeImmutable('+2 weeks +1 day'),
                'startTime' => new \DateTimeImmutable('09:00:00'),
                'endTime' => new \DateTimeImmutable('18:00:00'),
                'text' => <<<HTML
<h2>Annual IJsselmeer Regatta</h2>
<p>Join us for the biggest sailing event of the year on the IJsselmeer! This two-day regatta welcomes sailors of all levels.</p>

<h3>Schedule</h3>
<ul>
    <li><strong>Day 1 (Saturday):</strong> Registration 08:00, Skipper's meeting 09:00, First warning signal 10:00</li>
    <li><strong>Day 2 (Sunday):</strong> Racing continues 10:00, Prize ceremony 16:00</li>
</ul>

<h3>Classes</h3>
<ul>
    <li>Monohull (ORC/IRC)</li>
    <li>Catamaran (F18, A-Cat, Hobie)</li>
    <li>Keelboat (J/70, Melges 24)</li>
</ul>

<h3>Registration</h3>
<p>Early bird: €75 until 1 month before. Regular: €95. Includes meals, dockage, and after-party.</p>
HTML,
                'labelText' => 'Annual regatta on the IJsselmeer',
                'category' => 'sailing',
                'enabled' => true,
            ],
            [
                'title' => 'Tech Meetup: Symfony & PHP 8.4',
                'startDate' => new \DateTimeImmutable('+1 month'),
                'endDate' => new \DateTimeImmutable('+1 month'),
                'startTime' => new \DateTimeImmutable('18:30:00'),
                'endTime' => new \DateTimeImmutable('21:30:00'),
                'text' => <<<HTML
<h2>Symfony & PHP 8.4 Tech Meetup</h2>
<p>Monthly developer meetup focusing on modern PHP development with Symfony 8.1 and PHP 8.4 features.</p>

<h3>Agenda</h3>
<ul>
    <li>18:30 - Doors open, networking & drinks</li>
    <li>19:00 - Talk: "What's new in PHP 8.4" (30 min)</li>
    <li>19:30 - Talk: "Symfony 8.1: The Good Parts" (30 min)</li>
    <li>20:00 - Break & networking</li>
    <li>20:20 - Lightning talks (5 min each)</li>
    <li>21:00 - Open discussion & social</li>
</ul>

<h3>Speakers</h3>
<p>Local developers sharing real-world experience. Want to speak? Contact us!</p>

<h3>Venue</h3>
<p>Co-working space in Amsterdam city center. Exact location announced 1 week before.</p>
HTML,
                'labelText' => 'Monthly PHP/Symfony developer meetup',
                'category' => 'tech',
                'enabled' => true,
            ],
            [
                'title' => 'Summer Sailing Camp for Kids',
                'startDate' => new \DateTimeImmutable('+3 months'),
                'endDate' => new \DateTimeImmutable('+3 months +4 days'),
                'startTime' => new \DateTimeImmutable('09:00:00'),
                'endTime' => new \DateTimeImmutable('16:00:00'),
                'text' => <<<HTML
<h2>Kids Sailing Summer Camp</h2>
<p>Five-day sailing camp for children aged 8-16. Learn to sail in a fun, safe environment with certified instructors.</p>

<h3>What Kids Will Learn</h3>
<ul>
    <li>Basic sailing terminology and knots</li>
    <li>Rigging and de-rigging Optimist/Topper dinghies</li>
    <li>Points of sail, tacking, and gybing</li>
    <li>Capsize recovery (supervised)</li>
    <li>Water safety and navigation basics</li>
</ul>

<h3>Daily Schedule</h3>
<ul>
    <li>09:00 - Arrival & briefing</li>
    <li>09:30 - On-water session 1</li>
    <li>12:00 - Lunch break (bring packed lunch)</li>
    <li>13:00 - On-water session 2</li>
    <li>15:30 - Debrief & theory</li>
    <li>16:00 - Pickup</li>
</ul>

<h3>Requirements</h3>
<p>Swimming diploma A or equivalent. No prior sailing experience needed. All equipment provided.</p>

<h3>Price</h3>
<p>€295 for 5 days (includes boat rental, life jacket, certification).</p>
HTML,
                'labelText' => '5-day summer sailing camp for kids 8-16',
                'category' => 'sailing',
                'enabled' => true,
            ],
            [
                'title' => 'Open Source Contribution Workshop',
                'startDate' => new \DateTimeImmutable('+6 weeks'),
                'endDate' => new \DateTimeImmutable('+6 weeks'),
                'startTime' => new \DateTimeImmutable('10:00:00'),
                'endTime' => new \DateTimeImmutable('16:00:00'),
                'text' => <<<HTML
<h2>Open Source Contribution Workshop</h2>
<p>Full-day hands-on workshop to make your first (or next) open source contribution. Perfect for developers of all levels.</p>

<h3>What You'll Learn</h3>
<ul>
    <li>Finding good first issues on GitHub/GitLab</li>
    <li>Setting up a development environment</li>
    <li>Writing tests and documentation</li>
    <li>Creating pull requests that get merged</li>
    <li>Communicating with maintainers</li>
</ul>

<h3>Projects We'll Work On</h3>
<ul>
    <li>Symfony components</li>
    <li>PHPStan / Psalm</li>
    <li>Popular PHP libraries (Doctrine, API Platform, etc.)</li>
    <li>Bring your own project!</li>
</ul>

<h3>Prerequisites</h3>
<p>Basic Git knowledge, PHP development environment (Docker provided), GitHub/GitLab account.</p>

<h3>Price</h3>
<p>Free! Sponsored by local tech companies. Lunch included.</p>
HTML,
                'labelText' => 'Full-day workshop for open source contributions',
                'category' => 'tech',
                'enabled' => true,
            ],
            [
                'title' => 'Past Event: Round Texel 2024',
                'startDate' => new \DateTimeImmutable('-3 months'),
                'endDate' => new \DateTimeImmutable('-3 months +1 day'),
                'startTime' => new \DateTimeImmutable('06:00:00'),
                'endTime' => new \DateTimeImmutable('20:00:00'),
                'text' => <<<HTML
<h2>Round Texel 2024 - Race Report</h2>
<p>The 40th edition of the world's largest catamaran race. Over 500 boats, 1000+ sailors, one epic day.</p>

<h3>Conditions</h3>
<p>SW 5-6 Beaufort, 2-3 meter waves on the North Sea side. Challenging but spectacular!</p>

<h3>Results</h3>
<ul>
    <li>F18 Overall: 1st - Team Nacra, 2nd - Wild Cat, 3rd - Team Hobie</li>
    <li>A-Cat: 1st - Glenn Ashby (AUS), 2nd - Mischa Heemskerk (NED)</li>
    <li>Hobie 16: 1st - Team Netherlands</li>
</ul>

<h3>Photos & Video</h3>
<p>Check our <a href="#">photo gallery</a> and <a href="#">aftermovie</a>.</p>
HTML,
                'labelText' => 'World\'s largest catamaran race - 2024 edition',
                'category' => 'sailing',
                'enabled' => true,
            ],
            [
                'title' => 'Past Event: PHP Conference Netherlands 2024',
                'startDate' => new \DateTimeImmutable('-1 month'),
                'endDate' => new \DateTimeImmutable('-1 month +1 day'),
                'startTime' => new \DateTimeImmutable('09:00:00'),
                'endTime' => new \DateTimeImmutable('18:00:00'),
                'text' => <<<HTML
<h2>PHP Conference Netherlands 2024</h2>
<p>Two days of PHP talks, workshops, and networking. 300+ developers, 20 speakers, 3 tracks.</p>

<h3>Keynote Speakers</h3>
<ul>
    <li>Nicolas Grekas (Symfony Core) - "Symfony 7: The Road Ahead"</li>
    <li>Derick Rethans (PHP Internals) - "PHP 8.3 Performance"</li>
    <li>Ocramius (Doctrine) - "Modern PHP Architecture"</li>
</ul>

<h3>Highlights</h3>
<ul>
    <li>Workshop: "TDD with PHPUnit 11"</li>
    <li>Talk: "Event Sourcing in Practice"</li>
    <li>Panel: "PHP in 2025 and Beyond"</li>
    <li>Hackathon: Open Source Sprint</li>
</ul>

<h3>Resources</h3>
<p><a href="#">Slide decks</a> | <a href="#">Recordings</a> | <a href="#">Photos</a></p>
HTML,
                'labelText' => 'Annual PHP conference - 2024 edition',
                'category' => 'tech',
                'enabled' => true,
            ],
        ];

        foreach ($events as $eventData) {
            $event = new Event();
            $event->setTitle($eventData['title']);
            $event->setEventStartDate($eventData['startDate']);
            $event->setEventEndDate($eventData['endDate']);
            $event->setEventStartTime($eventData['startTime']);
            $event->setEventEndTime($eventData['endTime']);
            $event->setText($eventData['text']);
            $event->setLabelText($eventData['labelText']);
            $event->setEnabled($eventData['enabled']);

            if (isset($categories[$eventData['category']])) {
                $event->setCategory($categories[$eventData['category']]);
            }

            // Add placeholder image using placehold.co
            $image = new \App\Entity\BlogImage();
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $eventData['title']));
            $image->setFilename("{$slug}.jpg");
            $image->setPath("https://placehold.co/800x400/png?text=" . urlencode($eventData['title']) . "&font=roboto");
            $image->setAlt($eventData['title']);
            $event->setEventImage($image);

            $manager->persist($event);
        }

        $manager->flush();
    }

    private function createBlogComments(ObjectManager $manager, array $posts, User $admin, User $user): void
    {
        $comments = [
            [
                'postSlug' => 'sailing-ijsselmeer-weekend-getaway',
                'author' => $user,
                'content' => 'Great article! I sailed the IJsselmeer last summer and it was amazing. The route from Amsterdam to Enkhuizen is my favorite - the historic harbor is beautiful. Can\'t wait to do the full loop next year!',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'sailing-ijsselmeer-weekend-getaway',
                'author' => $admin,
                'content' => 'Thanks for the kind words! The IJsselmeer is indeed a fantastic sailing area. If you do the full loop, make sure to stop at Stavoren for the best kibbeling in Friesland. Happy sailing!',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'migrating-laminas-symfony-81-lessons',
                'author' => $user,
                'content' => 'Excellent write-up! We\'re considering a similar migration from Laminas to Symfony. The Strangler Fig pattern worked well for us too. Did you face any issues with Doctrine migrations for the existing data?',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'migrating-laminas-symfony-81-lessons',
                'author' => $admin,
                'content' => 'Glad it was helpful! The Doctrine migrations were actually one of the smoother parts since we could map the old entities to new ones. The biggest challenge was the custom authentication - we ended up writing a custom UserProvider. Happy to share more details if you\'re interested!',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'switched-macbook-linux-development',
                'author' => $user,
                'content' => 'I made the same switch to Fedora 6 months ago and haven\'t looked back. The battery life on my ThinkPad X1 Carbon is actually better than it was on macOS. VS Code + Docker + Podman is a killer combo.',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'switched-macbook-linux-development',
                'author' => $admin,
                'content' => 'Nice! Fedora on ThinkPad is a great combination. Have you tried the Fedora Silverblue (immutable) variant? It\'s interesting for development with toolbx containers.',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'round-texel-2024-race-report',
                'author' => $user,
                'content' => 'Congrats on finishing 47th! That\'s impressive for a first Round Texel. The conditions sound intense - 5-6 Bft on the North Sea side is no joke. Will you be back for the 41st edition?',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'round-texel-2024-race-report',
                'author' => $admin,
                'content' => 'Thanks! Yes, definitely coming back. Already started planning boat upgrades for next year. The goal is top 30. See you at the start line!',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'building-custom-mechanical-keyboard',
                'author' => $user,
                'content' => 'GMK Olivia++ is such a beautiful set! I built a similar 65% with Gateron Oil Kings last month. The thock is incredible. Did you use any foam mod or just tape mod?',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'building-custom-mechanical-keyboard',
                'author' => $admin,
                'content' => 'Thanks! I did both - Poron foam in the case and tape mod on the PCB. The combination really kills the ping and gives that deep thock sound. Highly recommend the combo!',
                'isApproved' => true,
            ],
            [
                'postSlug' => 'sailing-ijsselmeer-weekend-getaway',
                'author' => $user,
                'content' => 'What about anchoring spots? Any recommendations for overnight stays?',
                'isApproved' => false,
            ],
            [
                'postSlug' => 'migrating-laminas-symfony-81-lessons',
                'author' => $user,
                'content' => 'This is spam buy viagra online',
                'isApproved' => false,
            ],
        ];

        foreach ($comments as $commentData) {
            if (!isset($posts[$commentData['postSlug']])) {
                continue;
            }

            $post = $posts[$commentData['postSlug']];
            $comment = new BlogComment();
            $comment->setContent($commentData['content']);
            $comment->setBlogPost($post);
            $comment->setAuthor($commentData['author']);
            $comment->setApproved($commentData['isApproved']);

            $manager->persist($comment);
        }

        $manager->flush();
    }
}
