<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use App\Entity\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => ['placeholder' => 'e.g. ROLE_ADMIN'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Role description'],
            ])
            ->add('parentRoles', EntityType::class, [
                'label' => 'Parent Roles (inherits permissions)',
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'form-select', 'size' => 5],
                'query_builder' => fn($repo) => $repo->createQueryBuilder('r')
                    ->andWhere('r.deleted = false')
                    ->orderBy('r.name', 'ASC'),
            ])
            ->add('permissions', EntityType::class, [
                'label' => 'Permissions',
                'class' => Permission::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'form-select', 'size' => 8],
                'query_builder' => fn($repo) => $repo->createQueryBuilder('p')
                    ->andWhere('p.deleted = false')
                    ->orderBy('p.name', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
        ]);
    }
}