<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'user@example.com'],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['placeholder' => 'John Doe'],
            ])
            ->add('doctrineRoles', EntityType::class, [
                'label' => 'Roles',
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'form-select', 'size' => 5],
                'query_builder' => fn($repo) => $repo->createQueryBuilder('r')
                    ->orderBy('r.name', 'ASC'),
            ]);

        if (!$isEdit) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password', 'attr' => ['placeholder' => 'Enter password']],
                'second_options' => ['label' => 'Confirm Password', 'attr' => ['placeholder' => 'Confirm password']],
                'mapped' => false,
                'required' => true,
            ]);
        } else {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New Password (leave empty to keep current)', 'attr' => ['placeholder' => 'New password'], 'required' => false],
                'second_options' => ['label' => 'Confirm New Password', 'attr' => ['placeholder' => 'Confirm new password'], 'required' => false],
                'mapped' => false,
                'required' => false,
            ]);
        }

        $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => User::STATUS_ACTIVE,
                    'Inactive' => User::STATUS_RETIRED,
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}