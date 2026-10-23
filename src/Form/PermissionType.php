<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Naam',
                'attr' => ['placeholder' => 'bijv. blog.edit', 'class' => 'font-monospace'],
                'help' => 'Kleine letters, gescheiden door punten. Het deel vóór de eerste punt groepeert de rechten (blog.view, blog.edit → "blog").',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschrijving',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Wat mag je met dit recht?'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Permission::class,
        ]);
    }
}
