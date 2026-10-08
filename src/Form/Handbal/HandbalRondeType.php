<?php

declare(strict_types=1);

namespace App\Form\Handbal;

use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use App\Entity\Handbal\HandbalKeeper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class HandbalRondeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('seizoen', EntityType::class, [
                'class' => HandbalSeizoen::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('s')
                    ->where('s.actief = true AND s.deleted = false')
                    ->orderBy('s.startDatum', 'DESC'),
                'choice_label' => fn($s) => $s->getNaam() . ' (' . $s->getStartDatum()->format('d-m-Y') . ' t/m ' . $s->getEindDatum()->format('d-m-Y') . ')',
                'label' => 'Seizoen',
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Kies een seizoen...',
            ])
            ->add('datum', DateType::class, [
                'label' => 'Datum',
                'required' => true,
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'd-m-Y',
                'attr' => ['class' => 'form-control', 'type' => 'text'],
            ])
            ->add('keeper', EntityType::class, [
                'class' => HandbalKeeper::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('k')
                    ->where('k.actief = true AND k.deleted = false')
                    ->orderBy('k.naam', 'ASC'),
                'choice_label' => fn($k) => $k->getNaam() . ($k->getRugnummer() ? ' (#' . $k->getRugnummer() . ')' : ''),
                'label' => 'Keeper voor deze ronde',
                'required' => false,
                'placeholder' => 'Kies een keeper...',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Opslaan',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HandbalRonde::class,
            'constraints' => [
                new Callback([$this, 'validateDateWithinSeason']),
            ],
        ]);
    }

    public function validateDateWithinSeason($ronde, ExecutionContextInterface $context): void
    {
        if (!$ronde->getSeizoen() || !$ronde->getDatum()) {
            return;
        }

        $seizoen = $ronde->getSeizoen();
        $datum = $ronde->getDatum();

        if ($datum < $seizoen->getStartDatum() || $datum > $seizoen->getEindDatum()) {
            $context->buildViolation('De datum moet liggen tussen ' . $seizoen->getStartDatum()->format('d-m-Y') . ' en ' . $seizoen->getEindDatum()->format('d-m-Y') . '.')
                ->atPath('datum')
                ->addViolation();
        }
    }
}