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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'constraints' => [
                    new NotBlank(message: 'Please enter your first name'),
                    new Length(min: 2, max: 50),
                ],
                'attr' => ['placeholder' => 'First name', 'autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [
                    new NotBlank(message: 'Please enter your last name'),
                    new Length(min: 2, max: 50),
                ],
                'attr' => ['placeholder' => 'Last name', 'autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'constraints' => [
                    new NotBlank(message: 'Please enter an email address'),
                    new Email(message: 'Please enter a valid email address'),
                ],
                'attr' => ['placeholder' => 'you@example.com', 'autocomplete' => 'email'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['placeholder' => 'Enter password', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['placeholder' => 'Confirm password', 'autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(min: 8, max: 4096, minMessage: 'Your password should be at least {{ limit }} characters'),
                ],
            ])
            ->add('termsAccepted', CheckboxType::class, [
                'label' => 'I agree to the Terms of Service and Privacy Policy',
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'You must accept the terms and conditions'),
                ],
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationFormModel::class,
        ]);
    }
}