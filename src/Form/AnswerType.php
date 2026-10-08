<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Answer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Label',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Display label'],
            ])
            ->add('value', TextType::class, [
                'label' => 'Value',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Internal value'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Answer::class,
        ]);
    }
}