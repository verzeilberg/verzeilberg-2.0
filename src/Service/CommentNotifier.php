<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogComment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Mails the site's owner about a new blog comment, with a link to approve it when moderation is on.
 * A mail that can't be sent is logged; the comment is saved anyway.
 */
class CommentNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $notificationEmail,
    ) {
    }

    public function commentPosted(BlogComment $comment): void
    {
        $email = (new Email())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
            ->to($this->notificationEmail)
            ->subject(($comment->isApproved() ? 'Nieuwe reactie: ' : 'Reactie wacht op goedkeuring: ') . $comment->getBlogPost()->getTitle())
            ->html($this->twig->render('email/comment_notification.html.twig', ['comment' => $comment]));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Mailing about a new comment failed: ' . $e->getMessage());
        }
    }
}
