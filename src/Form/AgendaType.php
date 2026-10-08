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
                'label' => 'Title',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Colour',
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('public', CheckboxType::class, [
                'label' => 'Show on the site',
                'required' => false,
                'help' => 'Public agendas are shown (read-only) on /agenda and their iCal feed can be found there.',
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Agenda::class]);
    }
}
