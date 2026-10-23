<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EmailAccount;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailComposeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', EntityType::class, [
                'label' => 'Van account',
                'class' => EmailAccount::class,
                'choice_label' => 'name',
                'placeholder' => 'Kies een account',
                'query_builder' => fn($repo) => $repo->createQueryBuilder('a')
                    ->andWhere('a.enabled = true')
                    ->orderBy('a.name', 'ASC'),
            ])
            ->add('to', EmailType::class, [
                'label' => 'Aan',
                'attr' => ['placeholder' => 'ontvanger@example.com'],
                'required' => true,
            ])
            ->add('cc', EmailType::class, [
                'label' => 'CC',
                'required' => false,
                'attr' => ['placeholder' => 'cc@example.com'],
            ])
            ->add('bcc', EmailType::class, [
                'label' => 'BCC',
                'required' => false,
                'attr' => ['placeholder' => 'bcc@example.com'],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Onderwerp',
                'attr' => ['placeholder' => 'Onderwerp van de e-mail'],
            ])
            ->add('bodyText', TextareaType::class, [
                'label' => 'Bericht (tekst)',
                'attr' => ['rows' => 10, 'placeholder' => 'Je bericht...'],
            ])
            ->add('bodyHtml', TextareaType::class, [
                'label' => 'Bericht (HTML)',
                'required' => false,
                'attr' => ['rows' => 15, 'placeholder' => '<p>HTML-bericht...</p>'],
            ])
            ->add('attachments', FileType::class, [
                'label' => 'Bijlagen',
                'required' => false,
                'multiple' => true,
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}