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
                'label' => 'Account Name',
                'attr' => ['placeholder' => 'Personal Gmail'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('imapHost', TextType::class, [
                'label' => 'IMAP Host',
                'attr' => ['placeholder' => 'imap.gmail.com'],
            ])
            ->add('imapPort', IntegerType::class, [
                'label' => 'IMAP Port',
                'attr' => ['placeholder' => '993'],
            ])
            ->add('encryption', TextType::class, [
                'label' => 'Encryption',
                'attr' => ['placeholder' => 'ssl'],
                'help' => 'ssl, tls, or none',
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password / App Password',
                'attr' => ['placeholder' => 'App password', 'autocomplete' => 'new-password'],
                'required' => false,
                'help' => 'Leave empty to keep current password.',
            ])
            ->add('sentFolder', TextType::class, [
                'label' => 'Sent Folder',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Sent'],
            ])
            ->add('draftsFolder', TextType::class, [
                'label' => 'Drafts Folder',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Drafts'],
            ])
            ->add('trashFolder', TextType::class, [
                'label' => 'Trash Folder',
                'required' => false,
                'attr' => ['placeholder' => 'INBOX.Trash'],
            ])
            ->add('useImapForSmtp', CheckboxType::class, [
                'label' => 'Use IMAP credentials for SMTP',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'If checked, uses IMAP host/port/credentials for sending too.',
            ])
            ->add('smtpHost', TextType::class, [
                'label' => 'SMTP Host',
                'required' => false,
                'attr' => ['placeholder' => 'smtp.gmail.com'],
            ])
            ->add('smtpPort', IntegerType::class, [
                'label' => 'SMTP Port',
                'required' => false,
                'attr' => ['placeholder' => '587'],
            ])
            ->add('smtpEncryption', TextType::class, [
                'label' => 'SMTP Encryption',
                'required' => false,
                'attr' => ['placeholder' => 'tls'],
                'help' => 'tls, ssl, or none',
            ])
            ->add('smtpUsername', TextType::class, [
                'label' => 'SMTP Username',
                'required' => false,
                'attr' => ['placeholder' => 'you@gmail.com'],
            ])
            ->add('smtpPassword', PasswordType::class, [
                'label' => 'SMTP Password',
                'required' => false,
                'attr' => ['placeholder' => 'App password', 'autocomplete' => 'new-password'],
                'help' => 'Leave empty to keep current password.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Enabled',
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