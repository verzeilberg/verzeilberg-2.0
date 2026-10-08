<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CheckList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('access', ChoiceType::class, [
                'label' => 'Access',
                'choices' => [
                    'Admin only' => CheckList::ACCESS_ADMIN,
                    'Logged-in users' => CheckList::ACCESS_USER,
                    'Everyone (public)' => CheckList::ACCESS_PUBLIC,
                ],
                'help' => 'Who may fill in the checklist outside the admin.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('layoutColumns', ChoiceType::class, [
                'label' => 'Layout',
                'choices' => [
                    '2 columns' => 2,
                    '1 column' => 1,
                ],
                'expanded' => false,
                'multiple' => false,
                'help' => 'How the fields of an item are laid out.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('itemsPerPage', ChoiceType::class, [
                'label' => 'Items per page',
                'choices' => [
                    '10' => 10,
                    '20' => 20,
                    '50' => 50,
                    '100' => 100,
                ],
                'expanded' => false,
                'multiple' => false,
                'help' => 'Number of items per page in the lists.',
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckList::class,
        ]);
    }
}