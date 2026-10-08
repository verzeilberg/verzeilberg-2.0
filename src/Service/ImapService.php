<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailAccount;
use App\Entity\EmailMessage;
use App\Repository\EmailMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpImap\Mailbox;
use PhpImap\IncomingMail;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class ImapService
{
    public function __construct(
        private EmailMessageRepository $emailMessageRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function testConnection(EmailAccount $account): array
    {
        try {
            $mailbox = $this->createMailbox($account);
            $mailbox->getMailboxInfo();
            $this->closeMailbox($mailbox);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncAccount(EmailAccount $account, string $folder = 'INBOX', int $limit = 50): array
    {
        try {
            $mailbox = $this->createMailbox($account, $folder);

            // Get total and unread counts
            $mailboxInfo = $mailbox->getMailboxInfo();
            $account->setTotalMessages($mailboxInfo->Nmsgs ?? 0);
            $account->setUnreadMessages($mailboxInfo->Unread ?? 0);

            // Build search criteria for incremental sync
            $searchCriteria = 'ALL';
            if ($account->getLastSyncedUid() !== null) {
                // Only fetch messages with UID greater than last synced
                $searchCriteria = 'UID ' . ($account->getLastSyncedUid() + 1) . ':*';
            } elseif ($limit > 0) {
                // Fallback: fetch recent messages if no previous sync
                $searchCriteria = 'RECENT';
            }

            // Fetch messages matching criteria
            $mailIds = $mailbox->searchMailbox($searchCriteria);
            
            // Apply limit if specified
            if ($limit > 0 && count($mailIds) > $limit) {
                // Get the most recent messages (highest UIDs)
                $mailIds = array_slice($mailIds, -$limit);
            }
            
            // If no new messages and we have a last synced UID, we're done
            if (empty($mailIds) && $account->getLastSyncedUid() !== null) {
                $account->setLastSyncedAt(new \DateTimeImmutable());
                $this->em->flush();

                return [
                    'success' => true,
                    'synced' => 0,
                    'new' => 0,
                    'total' => $account->getTotalMessages(),
                    'unread' => $account->getUnreadMessages(),
                ];
            }

            $synced = 0;
            $new = 0;
            $maxUid = $account->getLastSyncedUid() ?? 0;

            foreach ($mailIds as $mailId) {
                try {
                    // false: syncing must not mark the message as read on the mail server
                    $mail = $mailbox->getMail($mailId, false);
                    // Track max UID
                    $uid = (int) $mail->id;
                    if ($uid > $maxUid) {
                        $maxUid = $uid;
                    }

                    $message = $this->saveMessage($account, $folder, $mail);
                    $synced++;
                    if (!$message->getId()) {
                        $new++;
                    }
                } catch (\Throwable $e) {
                    // Skip messages that no longer exist (UID does not exist)
                    error_log("DEBUG: Skipping UID $mailId - " . $e->getMessage());
                    continue;
                }
            }

            // Update last synced UID
            if ($maxUid > 0) {
                $account->setLastSyncedUid($maxUid);
            }
            $account->setLastSyncedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->closeMailbox($mailbox);

            return [
                'success' => true,
                'synced' => $synced,
                'new' => $new,
                'total' => $account->getTotalMessages(),
                'unread' => $account->getUnreadMessages(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncAllAccounts(): array
    {
        $accounts = $this->em->getRepository(EmailAccount::class)->findAllEnabled();
        $results = [];

        foreach ($accounts as $account) {
            $results[$account->getName()] = $this->syncAccount($account);
        }

        return $results;
    }

    public function getFolders(EmailAccount $account): array
    {
        try {
            // Create mailbox without folder to get full listing
            $host = $account->getImapHost();
            $port = $account->getImapPort();
            $encryption = $account->getEncryption();
            $username = $account->getUsername();
            $password = $account->getPassword();
            $connectionString = sprintf('{%s:%d/%s}', $host, $port, $encryption);

            $attachmentsDir = sys_get_temp_dir() . '/verzeilberg_imap_attachments_' . $account->getId();
            if (!is_dir($attachmentsDir)) {
                mkdir($attachmentsDir, 0755, true);
            }

            $mailbox = new \PhpImap\Mailbox($connectionString, $username, $password, $attachmentsDir);
            $mailbox->setTimeouts(30);

            $mailboxes = $mailbox->getListingFolders();
            $this->closeMailbox($mailbox);

            // php-imap returns full names ("{host:993/imap/ssl}INBOX"); keep the folder name
            $folders = array_values(array_filter(array_map(
                fn (string $name) => preg_replace('/^\{[^}]*\}/', '', $name) ?? $name,
                $mailboxes
            ), fn (string $name) => $name !== ''));

            // Sort folders: INBOX first, then system folders, then alphabetically
            $priority = [
                'INBOX' => 0,
                'Postvak IN' => 0,
                'Inbox' => 0,
                'Sent' => 1,
                'Verzonden' => 1,
                'Drafts' => 2,
                'Concepten' => 2,
                'Spam' => 3,
                'Junk' => 3,
                'Trash' => 4,
                'Prullenbak' => 4,
                'Deleted' => 4,
            ];
            
            usort($folders, function ($a, $b) use ($priority) {
                $pa = $priority[$a] ?? 99;
                $pb = $priority[$b] ?? 99;
                if ($pa !== $pb) {
                    return $pa - $pb;
                }
                return strcmp($a, $b);
            });

            error_log("DEBUG getFolders: Found " . count($folders) . " folders: " . json_encode($folders));
            return ['success' => true, 'folders' => $folders];
        } catch (\Throwable $e) {
            error_log("DEBUG getFolders error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function markAsRead(EmailMessage $message): void
    {
        try {
            $mailbox = $this->createMailbox($message->getAccount(), $message->getFolder());
            $mailbox->markMailAsRead((int) $message->getUid());
            $this->closeMailbox($mailbox);

            $message->setRead(true);
            $this->em->flush();
        } catch (\Throwable $e) {
            // Ignore IMAP errors, just update local
            $message->setRead(true);
            $this->em->flush();
        }
    }

    public function markAsUnread(EmailMessage $message): void
    {
        try {
            $mailbox = $this->createMailbox($message->getAccount(), $message->getFolder());
            $mailbox->markMailAsUnread((int) $message->getUid());
            $this->closeMailbox($mailbox);

            $message->setRead(false);
            $this->em->flush();
        } catch (\Throwable $e) {
            $message->setRead(false);
            $this->em->flush();
        }
    }

    public function deleteMessage(EmailMessage $message): void
    {
        try {
            $mailbox = $this->createMailbox($message->getAccount(), $message->getFolder());
            $mailbox->deleteMail((int) $message->getUid());
            $this->closeMailbox($mailbox);

            $message->setDeleted(true);
            $this->em->flush();
        } catch (\Throwable $e) {
            $message->setDeleted(true);
            $this->em->flush();
        }
    }

    public function moveMessage(EmailMessage $message, string $targetFolder): void
    {
        try {
            $mailbox = $this->createMailbox($message->getAccount(), $message->getFolder());
            $mailbox->moveMail($message->getUid(), $targetFolder);
            $this->closeMailbox($mailbox);

            $message->setFolder($targetFolder);
            $this->em->flush();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to move message: ' . $e->getMessage());
        }
    }

    public function sendEmail(EmailAccount $account, array $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $cc = [], array $bcc = [], array $attachments = []): bool
    {
        try {
            $fromEmail = $account->getEmail();
            $fromName = $account->getName();

            // Determine SMTP settings
            if ($account->isUseImapForSmtp()) {
                // Use IMAP credentials for SMTP (explicitly enabled)
                $smtpHost = $account->getImapHost();
                $smtpPort = $account->getImapPort() === 993 ? 465 : 587;
                $smtpEncryption = $account->getEncryption() === 'ssl' ? 'ssl' : 'tls';
                $smtpUsername = $account->getUsername();
                $smtpPassword = $account->getPassword();
            } elseif ($account->getSmtpHost() && $account->getSmtpUsername()) {
                // Use dedicated SMTP settings
                $smtpHost = $account->getSmtpHost();
                $smtpPort = $account->getSmtpPort();
                $smtpEncryption = $account->getSmtpEncryption();
                $smtpUsername = $account->getSmtpUsername();
                $smtpPassword = $account->getSmtpPassword();
            } else {
                throw new \RuntimeException('SMTP not configured. Set useImapForSmtp=true or configure dedicated SMTP settings (smtpHost, smtpUsername, etc.)');
            }

            // Create transport for this specific account
            $dsn = sprintf(
                'smtp://%s:%s@%s:%d?encryption=%s',
                urlencode($smtpUsername),
                urlencode($smtpPassword),
                $smtpHost,
                $smtpPort,
                $smtpEncryption
            );

            // EsmtpTransport expects bool for $tls: true=SSL (port 465), false=STARTTLS (port 587), null=auto
            $tls = match ($smtpEncryption) {
                'ssl' => true,
                'tls' => false,
                default => null,
            };

            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtpHost,
                $smtpPort,
                $tls
            );
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to(...array_map(fn($e) => new Address($e), $to))
                ->subject($subject)
                ->text($bodyText);

            if ($bodyHtml) {
                $email->html($bodyHtml);
            }

            if (!empty($cc)) {
                $email->cc(...array_map(fn($e) => new Address($e), $cc));
            }

            if (!empty($bcc)) {
                $email->bcc(...array_map(fn($e) => new Address($e), $bcc));
            }

            // Handle attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'], $attachment['name'])) {
                    $email->attachFromPath($attachment['path'], $attachment['name'], $attachment['mimeType'] ?? null);
                }
            }

            // Throws when sending fails
            $mailer->send($email);

            // Save a copy in the sent folder via IMAP (optional)
            $this->saveToSentFolder($account, $email);

            return true;
        } catch (\Throwable $e) {
            // Log error with more context
            error_log('Failed to send email: ' . $e->getMessage() . ' | Host: ' . ($smtpHost ?? 'unknown') . ' Port: ' . ($smtpPort ?? 'unknown') . ' Encryption: ' . ($smtpEncryption ?? 'unknown'));
            return false;
        }
    }

    /**
     * Appends the sent message (as sent, incl. attachments) to the account's sent folder.
     */
    private function saveToSentFolder(EmailAccount $account, Email $email): void
    {
        $sentFolder = $account->getSentFolder();
        if (!$sentFolder) {
            return;
        }

        try {
            $mailbox = $this->createMailbox($account, $sentFolder);
            // The mailbox is opened on the sent folder, so append to it ('' = the current folder)
            $mailbox->appendMessageToMailbox($email->toString(), '', '\\Seen');
            $this->closeMailbox($mailbox);
        } catch (\Throwable $e) {
            // Ignore - the sent folder is optional
        }
    }

    /** Closes the connection; deleted messages are expunged on disconnect */
    private function closeMailbox(Mailbox $mailbox): void
    {
        $mailbox->disconnect();
    }

    /** {host:port/encryption}folder */
    private function getConnectionString(EmailAccount $account, string $folder = ''): string
    {
        return sprintf('{%s:%d/%s}%s', $account->getImapHost(), $account->getImapPort(), $account->getEncryption(), $folder);
    }

    private function createMailbox(EmailAccount $account, string $folder = 'INBOX'): Mailbox
    {
        $host = $account->getImapHost();
        $port = $account->getImapPort();
        $encryption = $account->getEncryption();
        $username = $account->getUsername();
        $password = $account->getPassword();

        // PhpImap expects: {host:port/encryption}mailbox (charset not supported in connection string)
        $connectionString = $this->getConnectionString($account, $folder);

        $attachmentsDir = sys_get_temp_dir() . '/verzeilberg_imap_attachments_' . $account->getId();
        if (!is_dir($attachmentsDir)) {
            mkdir($attachmentsDir, 0755, true);
        }

        $mailbox = new Mailbox($connectionString, $username, $password, $attachmentsDir);
        // Connection timeouts of 30 seconds (open/read/write/close)
        $mailbox->setTimeouts(30);

        return $mailbox;
    }

    private function saveMessage(EmailAccount $account, string $folder, IncomingMail $mail): EmailMessage
    {
        $existing = $this->emailMessageRepository->findByUid($account, $folder, (string) $mail->id);

        if ($existing) {
            return $existing;
        }

        $message = new EmailMessage();
        $message->setAccount($account);
        $message->setFolder($folder);
        $message->setUid((string) $mail->id);
        $message->setMessageId($mail->messageId ?? null);
        $message->setSubject($mail->subject ?? null);
        $message->setFromAddress($mail->fromAddress ?? null);
        $message->setFromName($mail->fromName ?? null);
        $message->setToAddresses($mail->toString ?? null);
        // cc is [address => name]
        $message->setCcAddresses($mail->cc ? implode(', ', array_keys($mail->cc)) : null);
        $message->setBccAddresses(null);
        $message->setSentAt($mail->date ? new \DateTimeImmutable($mail->date) : null);
        $message->setReceivedAt(new \DateTimeImmutable());
        $message->setBodyText($mail->textPlain);
        $message->setBodyHtml($mail->textHtml);
        $message->setAttachments($mail->getAttachments());
        $message->setRead(false);
        $message->setFlagged(false);
        $message->setAnswered(false);
        $message->setDeleted(false);
        $message->setDraft(false);

        $this->em->persist($message);

        return $message;
    }

public function getAttachment(EmailAccount $account, string $folder, string $uid, int $partNumber, ?string $attachmentName = null, ?string $attachmentId = null): ?array
    {
        try {
            $mailbox = $this->createMailbox($account, $folder);

            // First check if the UID still exists in the folder
            $mailIds = $mailbox->searchMailbox("UID $uid");
            error_log("DEBUG: searchMailbox('UID $uid') in folder '$folder' returned: " . json_encode($mailIds));
            if (empty($mailIds)) {
                error_log("DEBUG: UID $uid not found in folder $folder, searching local temp directory for attachment '$attachmentName'");

                // Search local temp directory for files matching this UID and attachment name
                $attachmentsDir = sys_get_temp_dir() . '/verzeilberg_imap_attachments_' . $account->getId();
                if (is_dir($attachmentsDir)) {
                    // PhpImap saves files as UID_INTERNALID_SANITIZED_FILENAME
                    // Normalize both the saved filename and attachment name for matching
                    $normalize = static fn($s) => preg_replace('/[^a-zA-Z0-9.]+/', '_', $s);
                    $normalizedAttachName = $normalize(basename($attachmentName));
                    $allFiles = glob($attachmentsDir . '/' . $uid . '_*');
                    if (!empty($allFiles)) {
                        foreach ($allFiles as $file) {
                            $savedFilename = basename($file);
                            // Extract the filename part after UID_INTERNALID_
                            $parts = explode('_', $savedFilename, 3);
                            if (count($parts) >= 3) {
                                $savedFilenamePart = $parts[2];
                            } else {
                                $savedFilenamePart = $savedFilename;
                            }
                            if (str_ends_with($normalize($savedFilenamePart), $normalizedAttachName)) {
                                error_log("DEBUG: Found local file: $file");
                                return [
                                    'name' => $attachmentName,
                                    'mimeType' => mime_content_type($file) ?: 'application/octet-stream',
                                    'filePath' => $file,
                                    'size' => filesize($file),
                                ];
                            }
                        }
                    }
                }

                error_log("DEBUG: Attachment '$attachmentName' not found in local storage, trying IMAP fetch");
            }

            $mail = $mailbox->getMail((int) $uid, false);

            $attachments = $mail->getAttachments();

            error_log("DEBUG: Found " . count($attachments) . " attachments in fetched mail");
            foreach ($attachments as $key => $att) {
                error_log("DEBUG: Attachment key=$key, id={$att->id}, name={$att->name}, filePath={$att->filePath}");
            }

            // Get the attachment by numeric index (since keys might be attachment IDs)
            $attachmentArray = array_values($attachments);

            // Convert 1-based partNumber to 0-based array index
            $arrayIndex = $partNumber - 1;

            // Try to find the attachment by name or ID first
            $attachment = null;
            if ($attachmentName) {
                foreach ($attachmentArray as $att) {
                    if ($att->name === $attachmentName || ($attachmentId && $att->id === $attachmentId)) {
                        $attachment = $att;
                        error_log("DEBUG: Found attachment by name/id match");
                        break;
                    }
                }
            }

            // Fallback to index-based lookup
            if (!$attachment && isset($attachmentArray[$arrayIndex])) {
                $attachment = $attachmentArray[$arrayIndex];
                error_log("DEBUG: Found attachment by index: $arrayIndex");
            }

            if (!$attachment) {
                error_log("DEBUG: No attachment found at index $arrayIndex or by name");
                return null;
            }

            error_log("DEBUG: Selected attachment: id={$attachment->id}, name={$attachment->name}, filePath={$attachment->filePath}");

            // If filePath is set and file exists, return file path for streaming
            if ($attachment->filePath && file_exists($attachment->filePath)) {
                return [
                    'name' => $attachment->name ?? 'attachment',
                    'mimeType' => mime_content_type($attachment->filePath) ?: 'application/octet-stream',
                    'filePath' => $attachment->filePath,
                    'size' => filesize($attachment->filePath),
                ];
            }

            // Fallback: php-imap fetches and decodes the attachment content itself
            try {
                return [
                    'name' => $attachment->name ?? 'attachment',
                    'mimeType' => $attachment->mimeType ?: 'application/octet-stream',
                    'content' => $attachment->getContents(),
                ];
            } catch (\Throwable $e) {
                error_log("DEBUG: IMAP fallback error: " . $e->getMessage());
            }

            return null;
        } catch (\Throwable $e) {
            error_log("DEBUG: Exception in getAttachment: " . $e->getMessage());
            return null;
        }
    }
}
