<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\EmailMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Personal data the site doesn't need to keep is deleted after six months: contact form messages, and the local
 * copies of emails with their attachment files. The emails themselves stay on the mail server.
 * Registrations nobody approved (or whose address was never confirmed) are deleted after 30 days.
 * Runs once a day by itself (DataRetentionListener), or by hand: bin/console app:privacy:purge
 */
class DataRetention
{
    public const KEEP = '-6 months';
    public const KEEP_PENDING_REGISTRATIONS = '-30 days';

    public function __construct(
        private EntityManagerInterface $em,
        private string $attachmentsPattern = '',
    ) {
        // The folders ImapService saves attachments in
        $this->attachmentsPattern = $attachmentsPattern ?: sys_get_temp_dir() . '/verzeilberg_imap_attachments_*/*';
    }

    /** @return array{contacts: int, emails: int, attachments: int, registrations: int} the numbers deleted */
    public function purge(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $cutoff = $now->modify(self::KEEP);

        $contacts = $this->em->createQuery('DELETE FROM ' . Contact::class . ' c WHERE c.dateCreated < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();

        // The date of the email itself; the moment it was fetched when it has none
        $emails = $this->em->createQuery('DELETE FROM ' . EmailMessage::class . ' m WHERE COALESCE(m.receivedAt, m.sentAt, m.dateCreated) < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();

        $attachments = 0;
        foreach (glob($this->attachmentsPattern) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff->getTimestamp() && @unlink($file)) {
                ++$attachments;
            }
        }

        // Through the entity manager, so their roles and details go too
        $registrations = $this->em->getRepository(User::class)->findPendingCreatedBefore($now->modify(self::KEEP_PENDING_REGISTRATIONS));
        foreach ($registrations as $user) {
            $this->em->remove($user);
        }
        $this->em->flush();

        return ['contacts' => (int) $contacts, 'emails' => (int) $emails, 'attachments' => $attachments, 'registrations' => count($registrations)];
    }
}
