<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
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
                'label' => 'E-mail',
                'attr' => ['placeholder' => 'gebruiker@example.com'],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Volledige naam',
                'attr' => ['placeholder' => 'Jan Jansen'],
            ])
            ->add('doctrineRoles', EntityType::class, [
                'label' => 'Rollen',
                'class' => Role::class,
                'choice_label' => fn(Role $role) => $role->getName() . ($role->getDescription() ? ' — ' . $role->getDescription() : ''),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'ROLE_ADMIN mag alles. Andere rollen krijgen toegang via hun rechten.',
                'query_builder' => fn(EntityRepository $repo) => $repo->createQueryBuilder('r')
                    ->andWhere('r.deleted = false')
                    ->orderBy('r.name', 'ASC'),
            ]);

        if (!$isEdit) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Wachtwoord', 'attr' => ['placeholder' => 'Wachtwoord']],
                'second_options' => ['label' => 'Herhaal wachtwoord', 'attr' => ['placeholder' => 'Herhaal wachtwoord']],
                'mapped' => false,
                'required' => true,
            ]);
        } else {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Nieuw wachtwoord (leeg laten om het huidige te houden)', 'attr' => ['placeholder' => 'Nieuw wachtwoord'], 'required' => false],
                'second_options' => ['label' => 'Bevestig nieuw wachtwoord', 'attr' => ['placeholder' => 'Bevestig nieuw wachtwoord'], 'required' => false],
                'mapped' => false,
                'required' => false,
            ]);
        }

        $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Actief' => User::STATUS_ACTIVE,
                    'Inactief' => User::STATUS_RETIRED,
                    'Wacht op goedkeuring' => User::STATUS_PENDING,
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