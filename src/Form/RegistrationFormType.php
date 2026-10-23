<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\RegistrationFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistrationFormType extends AbstractType
{
    /** Name of the honeypot field: hidden for people, bots fill it in */
    public const HONEYPOT_FIELD = 'website';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Voornaam',
                'constraints' => [
                    new NotBlank(message: 'Vul je voornaam in'),
                    new Length(min: 2, max: 50),
                ],
                'attr' => ['placeholder' => 'Voornaam', 'autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Achternaam',
                'constraints' => [
                    new NotBlank(message: 'Vul je achternaam in'),
                    new Length(min: 2, max: 50),
                ],
                'attr' => ['placeholder' => 'Achternaam', 'autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mailadres',
                'constraints' => [
                    new NotBlank(message: 'Vul een e-mailadres in'),
                    new Email(message: 'Vul een geldig e-mailadres in'),
                ],
                'attr' => ['placeholder' => 'jij@example.com', 'autocomplete' => 'email'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'De wachtwoorden moeten gelijk zijn.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'Wachtwoord',
                    'attr' => ['placeholder' => 'Wachtwoord', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Herhaal wachtwoord',
                    'attr' => ['placeholder' => 'Herhaal wachtwoord', 'autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Vul een wachtwoord in'),
                    new Length(min: 8, max: 4096, minMessage: 'Je wachtwoord moet minstens {{ limit }} tekens lang zijn'),
                ],
            ])
            ->add('termsAccepted', CheckboxType::class, [
                'label' => 'Ik ga akkoord met de gebruiksvoorwaarden en het privacybeleid',
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'Je moet akkoord gaan met de voorwaarden'),
                ],
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add(self::HONEYPOT_FIELD, TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Laat dit veld leeg',
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationFormModel::class,
        ]);
    }
}