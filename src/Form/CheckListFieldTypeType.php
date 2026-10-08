<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CheckListFieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckListFieldTypeType extends AbstractType
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
            ->add('formType', ChoiceType::class, [
                'label' => 'Form Type',
                'choices' => [
                    'Text' => 'text',
                    'Number' => 'number',
                    'Textarea' => 'textarea',
                    'Checkbox' => 'checkbox',
                    'Select' => 'select',
                    'Radio' => 'radio',
                    'Date' => 'date',
                    'Time' => 'time',
                    'Email' => 'email',
                    'File' => 'file',
                    'Hidden' => 'hidden',
                ],
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckListFieldType::class,
        ]);
    }
}