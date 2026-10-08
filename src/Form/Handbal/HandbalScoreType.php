<?php

declare(strict_types=1);

namespace App\Form\Handbal;

use App\Entity\Handbal\HandbalScore;
use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Repository\Handbal\HandbalSpelerRepository;
use App\Repository\Handbal\HandbalKeeperRepository;
use App\Repository\Handbal\HandbalRondeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HandbalScoreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var HandbalSpelerRepository $spelerRepository */
        $spelerRepository = $options['speler_repository'];
        /** @var HandbalKeeperRepository $keeperRepository */
        $keeperRepository = $options['keeper_repository'];
        /** @var HandbalRondeRepository $rondeRepository */
        $rondeRepository = $options['ronde_repository'];

        $spelers = $spelerRepository->findActiveOrderedByName();
        $keepers = $keeperRepository->findActiveOrderedByName();
        $rondes = $rondeRepository->findAllOrderedByDateDesc();

        $builder
            ->add('speler', EntityType::class, [
                'label' => 'Speler',
                'class' => HandbalSpeler::class,
                'choices' => $spelers,
                'choice_label' => fn(HandbalSpeler $speler) => $speler->__toString(),
                'placeholder' => 'Kies speler',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('keeper', EntityType::class, [
                'label' => 'Keeper',
                'class' => HandbalKeeper::class,
                'choices' => $keepers,
                'choice_label' => fn(HandbalKeeper $keeper) => $keeper->__toString(),
                'placeholder' => 'Kies keeper',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('ronde', EntityType::class, [
                'label' => 'Ronde',
                'class' => HandbalRonde::class,
                'choices' => $rondes,
                'choice_label' => fn(HandbalRonde $ronde) => $ronde->__toString(),
                'placeholder' => 'Kies ronde',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('gescored', CheckboxType::class, [
                'label' => 'Gescored (vink aan voor doelpunt, laat leeg voor redding keeper)',
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
            'data_class' => HandbalScore::class,
            'speler_repository' => null,
            'keeper_repository' => null,
            'ronde_repository' => null,
        ]);
    }
}