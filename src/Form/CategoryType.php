<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Naam',
                'attr' => ['placeholder' => 'Naam van de categorie'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'attr' => ['placeholder' => 'Wordt automatisch gemaakt uit de naam'],
                'help' => 'Laat leeg om automatisch uit de naam te maken',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschrijving',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Optionele beschrijving'],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actief',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}