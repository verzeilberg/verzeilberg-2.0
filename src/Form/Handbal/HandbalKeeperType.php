<?php

declare(strict_types=1);

namespace App\Form\Handbal;

use App\Entity\Handbal\HandbalKeeper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HandbalKeeperType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('naam', TextType::class, [
                'label' => 'Naam',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Naam keeper'],
            ])
            ->add('rugnummer', TextType::class, [
                'label' => 'Rugnummer',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Rugnummer'],
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
            'data_class' => HandbalKeeper::class,
        ]);
    }
}