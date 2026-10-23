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
                'label' => 'Naam',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('formType', ChoiceType::class, [
                'label' => 'Formuliertype',
                'choices' => [
                    'Tekst' => 'text',
                    'Getal' => 'number',
                    'Tekstvak' => 'textarea',
                    'Selectievakje' => 'checkbox',
                    'Keuzelijst' => 'select',
                    'Keuzerondjes' => 'radio',
                    'Datum' => 'date',
                    'Tijd' => 'time',
                    'E-mail' => 'email',
                    'Bestand' => 'file',
                    'Verborgen' => 'hidden',
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