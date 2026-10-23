<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\BlogCommentRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminNotificationExtension extends AbstractExtension implements ResetInterface
{
    /** @var array{comments: int, contacts: int, users: int}|null counted once per request */
    private ?array $counts = null;

    public function __construct(
        private BlogCommentRepository $blogCommentRepository,
        private ContactRepository $contactRepository,
        private UserRepository $userRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            // {{ admin_counts().comments }}: what waits for an admin (the badges in the admin menu)
            new TwigFunction('admin_counts', $this->counts(...)),
        ];
    }

    /** @return array{comments: int, contacts: int, users: int} comments waiting for approval, unread contact messages, registrations waiting for approval */
    public function counts(): array
    {
        return $this->counts ??= [
            'comments' => $this->blogCommentRepository->countPendingApproval(),
            'contacts' => $this->contactRepository->countUnread(),
            'users' => $this->userRepository->countPendingApproval(),
        ];
    }

    /** Between requests (a worker process or the test client handles several) */
    public function reset(): void
    {
        $this->counts = null;
    }
}
