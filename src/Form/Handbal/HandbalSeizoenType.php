<?php

declare(strict_types=1);

namespace App\Form\Handbal;

use App\Entity\Handbal\HandbalSeizoen;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HandbalSeizoenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('naam', TextType::class, [
                'label' => 'Naam',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Bijv. Seizoen 2024-2025'],
            ])
            ->add('startDatum', DateType::class, [
                'label' => 'Startdatum',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('eindDatum', DateType::class, [
                'label' => 'Einddatum',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('actief', CheckboxType::class, [
                'label' => 'Actief',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Opslaan',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HandbalSeizoen::class,
        ]);
    }
}