<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Event;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['placeholder' => 'Titel van het evenement'],
            ])
            ->add('eventStartDate', DateType::class, [
                'label' => 'Startdatum',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('eventEndDate', DateType::class, [
                'label' => 'Einddatum',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('eventStartTime', TimeType::class, [
                'label' => 'Starttijd',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Optioneel'],
            ])
            ->add('eventEndTime', TimeType::class, [
                'label' => 'Eindtijd',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Optioneel'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Categorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Kies een categorie',
                'required' => false,
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->andWhere('c.enabled = true')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('labelText', TextareaType::class, [
                'label' => 'Labeltekst (korte beschrijving)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Korte beschrijving voor overzichten'],
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Volledige beschrijving',
                'required' => false,
                'attr' => ['rows' => 10, 'placeholder' => 'Uitgebreide beschrijving van het evenement'],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Lengtegraad',
                'required' => false,
                'attr' => ['placeholder' => 'bijv. 4.8952'],
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Breedtegraad',
                'required' => false,
                'attr' => ['placeholder' => 'bijv. 52.3702'],
            ])
            ->add('eventImageFile', FileType::class, [
                'label' => 'Afbeelding',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*'],
                'help' => 'Upload een afbeelding voor dit evenement. Toegestaan: JPG, PNG, WebP. Laat leeg om de huidige afbeelding te houden.',
            ])
            ->add('event_image_cropped', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}