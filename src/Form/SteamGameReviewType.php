<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Steam\SteamGameReview;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * My review of a game (admin game page). The rating is rendered as stars, see admin/steam/_review_form.html.twig.
 */
class SteamGameReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Beoordeling',
                // "1 - Bad" => 1 … "5 - Excellent" => 5; shown as stars
                'choices' => array_combine(
                    array_map(fn (int $rating, string $label) => $rating . ' - ' . $label, array_keys(SteamGameReview::RATINGS), SteamGameReview::RATINGS),
                    array_keys(SteamGameReview::RATINGS)
                ),
                'expanded' => true,
                'placeholder' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Review',
                'attr' => ['class' => 'form-control', 'rows' => 10, 'placeholder' => 'Wat vond je van de game?'],
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Gepubliceerd (zichtbaar op de openbare gamepagina)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SteamGameReview::class]);
    }
}
