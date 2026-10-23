<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class ContactService
{
    public function __construct(
        private ContactRepository $contactRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $notificationEmail,
    ) {
    }

    public function getContacts(int $page = 1, int $limit = 10): PaginationInterface
    {
        return $this->contactRepository->findAllOrdered($page, $limit);
    }

    public function getContactById(int $id): ?Contact
    {
        return $this->contactRepository->find($id);
    }

    public function deleteContact(Contact $contact): void
    {
        $this->em->remove($contact);
        $this->em->flush();
    }

    public function saveContact(Contact $contact): void
    {
        $this->em->persist($contact);
        $this->em->flush();
    }

public function sendConfirmationEmail(Contact $contact): void
    {
        $email = (new Email())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
            ->to(new Address($contact->getEmail(), $contact->getName()))
            ->replyTo('noreply@verzeilberg.nl')
            ->subject('Contact met Verzeilberg: ' . $contact->getSubject())
            ->html($this->twig->render('email/contact_confirmation.html.twig', [
                'contact' => $contact,
            ]));

        $this->mailer->send($email);
    }

    public function sendReplyEmail(Contact $contact, string $replyMessage, string $adminName): void
    {
        $email = (new Email())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
            ->to(new Address($contact->getEmail(), $contact->getName()))
            ->replyTo('noreply@verzeilberg.nl')
            ->subject('Re: ' . $contact->getSubject())
            ->html($this->twig->render('email/contact_reply.html.twig', [
                'contact' => $contact,
                'replyMessage' => $replyMessage,
                'adminName' => $adminName,
            ]));

        $this->mailer->send($email);
    }

    public function sendNotificationEmail(Contact $contact): void
    {
        $email = (new Email())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
            ->to($this->notificationEmail)
            ->subject('Nieuw contactformulier: ' . $contact->getSubject())
            ->html($this->twig->render('email/contact_notification.html.twig', [
                'contact' => $contact,
            ]));

        $this->mailer->send($email);
    }
}