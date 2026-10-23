<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Agenda;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AgendaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Kleur',
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('public', CheckboxType::class, [
                'label' => 'Tonen op de site',
                'required' => false,
                'help' => 'Openbare agenda\'s worden (alleen-lezen) getoond op /agenda; daar staat ook de iCal-feed.',
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Agenda::class]);
    }
}
