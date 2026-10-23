<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('salutation', ChoiceType::class, [
                'label' => 'Aanhef',
                'choices' => [
                    'De heer'   => Contact::SALUTATION_MR,
                    'Mevrouw'   => Contact::SALUTATION_MRS,
                    'Geen voorkeur' =>Contact::SALUTATION_UND,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                // Rendered per option as Bootstrap form-checks in contact/index.html.twig
            ])
            ->add('name', TextType::class, [
                'label' => 'Naam',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Je naam'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'jij@email.nl'],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Onderwerp',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Onderwerp van je bericht'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Bericht',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Je bericht...'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Versturen',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
