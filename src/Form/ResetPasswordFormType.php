<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ResetPasswordFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nieuw wachtwoord',
                'attr' => ['placeholder' => 'Nieuw wachtwoord', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Vul een wachtwoord in'),
                    new Length(min: 8, max: 4096, minMessage: 'Je wachtwoord moet minstens {{ limit }} tekens lang zijn'),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Herhaal wachtwoord',
                'attr' => ['placeholder' => 'Bevestig nieuw wachtwoord', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Herhaal je wachtwoord'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResetPasswordFormModel::class,
        ]);
    }
}