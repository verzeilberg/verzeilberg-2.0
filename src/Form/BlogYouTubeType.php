<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BlogYouTube;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogYouTubeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => false,
            ])
            ->add('videoId', TextType::class, [
                'label' => 'Video ID',
                'attr' => ['placeholder' => 'e.g., dQw4w9WgXcQ'],
            ])
            ->add('playlistId', TextType::class, [
                'label' => 'Playlist ID (optional)',
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sort Order',
                'required' => false,
            ])
            ->add('autoPlay', CheckboxType::class, [
                'label' => 'Auto Play',
                'required' => false,
            ])
            ->add('showControls', CheckboxType::class, [
                'label' => 'Show Controls',
                'required' => false,
            ])
            ->add('showInfo', CheckboxType::class, [
                'label' => 'Show Info',
                'required' => false,
            ])
            ->add('modestBranding', CheckboxType::class, [
                'label' => 'Modest Branding',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogYouTube::class,
        ]);
    }
}