<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BlogImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'attr' => ['placeholder' => 'Optional name'],
            ])
            ->add('alt', TextType::class, [
                'label' => 'Alt Text',
                'required' => false,
                'attr' => ['placeholder' => 'Alt text for accessibility'],
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sort Order',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogImage::class,
        ]);
    }
}