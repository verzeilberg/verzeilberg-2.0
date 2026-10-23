<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogComment;
use App\Entity\Friendship;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

/**
 * Mails users about what others did: a reply to their comment, a friend request, an accepted request.
 * Only to active users who didn't switch notifications off on their profile (User::canReceiveNotifications()).
 * A mail that can't be sent is logged.
 */
class ActivityNotifier
{
    /** At most one friend request mail per pair a day, also when a request is withdrawn and sent again */
    private const FRIEND_REQUEST_INTERVAL = 86400;

    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private CacheInterface $cache,
    ) {
    }

    /** Call when a reply becomes visible to everyone: posted without moderation, or approved by an admin */
    public function commentPublished(BlogComment $comment): void
    {
        $parentAuthor = $comment->getParent()?->getAuthor();
        if ($parentAuthor === null || !$comment->isPublished() || $comment->isWrittenBy($parentAuthor) || !$parentAuthor->canReceiveNotifications()) {
            return;
        }

        $this->send($parentAuthor, sprintf('%s reageerde op je reactie', $comment->getAuthor()?->getFullName() ?? 'Iemand'), 'email/comment_reply.html.twig', [
            'comment' => $comment,
            'recipient' => $parentAuthor,
        ]);
    }

    public function friendRequested(Friendship $friendship): void
    {
        $addressee = $friendship->getAddressee();
        $requester = $friendship->getRequester();
        if (!$addressee->canReceiveNotifications()) {
            return;
        }

        $this->cache->get(sprintf('friend_request_mail_%d_%d', $requester->getId(), $addressee->getId()), function (ItemInterface $item) use ($addressee, $requester): bool {
            $item->expiresAfter(self::FRIEND_REQUEST_INTERVAL);
            $this->send($addressee, sprintf('%s wil je vriend worden', $requester->getFullName()), 'email/friend_request.html.twig', [
                'recipient' => $addressee,
                'other' => $requester,
                'accepted' => false,
            ]);

            return true;
        });
    }

    public function friendAccepted(Friendship $friendship): void
    {
        $requester = $friendship->getRequester();
        if (!$requester->canReceiveNotifications()) {
            return;
        }

        $this->send($requester, sprintf('%s heeft je vriendschapsverzoek geaccepteerd', $friendship->getAddressee()->getFullName()), 'email/friend_request.html.twig', [
            'recipient' => $requester,
            'other' => $friendship->getAddressee(),
            'accepted' => true,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function send(User $recipient, string $subject, string $template, array $context): void
    {
        try {
            $this->mailer->send((new Email())
                ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
                ->to($recipient->getEmail())
                ->subject($subject)
                ->html($this->twig->render($template, $context)));
        } catch (\Throwable $e) {
            $this->logger->error('Mailing a notification failed: ' . $e->getMessage());
        }
    }
}
