<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EmailAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Accountnaam',
                'attr' => ['placeholder' => 'Persoonlijke Gmail'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mailadres',
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('imapHost', TextType::class, [
                'label' => 'IMAP-host',
                'attr' => ['placeholder' => 'imap.gmail.com'],
            ])
            ->add('imapPort', IntegerType::class, [
                'label' => 'IMAP-poort',
                'attr' => ['placeholder' => '993'],
            ])
            ->add('encryption', TextType::class, [
                'label' => 'Versleuteling',
                'attr' => ['placeholder' => 'ssl'],
                'help' => 'ssl, tls of none',
            ])
            ->add('username', TextType::class, [
                'label' => 'Gebruikersnaam',
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Wachtwoord / app-wachtwoord',
                'attr' => ['placeholder' => 'App-wachtwoord', 'autocomplete' => 'new-password'],
                'required' => false,
                'help' => 'Laat leeg om het huidige wachtwoord te houden.',
            ])
            ->add('sentFolder', TextType::class, [
                'label' => 'Map verzonden items',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Sent'],
            ])
            ->add('draftsFolder', TextType::class, [
                'label' => 'Map concepten',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Drafts'],
            ])
            ->add('trashFolder', TextType::class, [
                'label' => 'Map prullenbak',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Trash'],
            ])
            ->add('useImapForSmtp', CheckboxType::class, [
                'label' => 'IMAP-gegevens ook voor SMTP gebruiken',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Als dit aan staat, worden IMAP-host, -poort en -inloggegevens ook voor verzenden gebruikt.',
            ])
            ->add('smtpHost', TextType::class, [
                'label' => 'SMTP-host',
                'required' => false,
                'attr' => ['placeholder' => 'smtp.gmail.com'],
            ])
            ->add('smtpPort', IntegerType::class, [
                'label' => 'SMTP-poort',
                'required' => false,
                'attr' => ['placeholder' => '587'],
            ])
            ->add('smtpEncryption', TextType::class, [
                'label' => 'SMTP-versleuteling',
                'required' => false,
                'attr' => ['placeholder' => 'tls'],
                'help' => 'tls, ssl of none',
            ])
            ->add('smtpUsername', TextType::class, [
                'label' => 'SMTP-gebruikersnaam',
                'required' => false,
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('smtpPassword', PasswordType::class, [
                'label' => 'SMTP-wachtwoord',
                'required' => false,
                'attr' => ['placeholder' => 'App-wachtwoord', 'autocomplete' => 'new-password'],
                'help' => 'Laat leeg om het huidige wachtwoord te houden.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actief',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailAccount::class,
        ]);
    }
}