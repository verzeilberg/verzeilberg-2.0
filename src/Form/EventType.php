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
                'label' => 'Title',
                'attr' => ['placeholder' => 'Event title'],
            ])
            ->add('eventStartDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('eventEndDate', DateType::class, [
                'label' => 'End Date',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('eventStartTime', TimeType::class, [
                'label' => 'Start Time',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Optional'],
            ])
            ->add('eventEndTime', TimeType::class, [
                'label' => 'End Time',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Optional'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Category',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select category',
                'required' => false,
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->andWhere('c.enabled = true')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('labelText', TextareaType::class, [
                'label' => 'Label Text (short description)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Short description for listings'],
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Full Description',
                'required' => false,
                'attr' => ['rows' => 10, 'placeholder' => 'Detailed event description'],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Longitude',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. 4.8952'],
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Latitude',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. 52.3702'],
            ])
            ->add('eventImageFile', FileType::class, [
                'label' => 'Event Image',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*'],
                'help' => 'Upload an image for this event. Supported formats: JPG, PNG, WebP. Leave empty to keep current image.',
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