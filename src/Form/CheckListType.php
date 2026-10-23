<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CheckList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckListType extends AbstractType
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
            ->add('access', ChoiceType::class, [
                'label' => 'Toegang',
                'choices' => [
                    'Privé (alleen de eigenaar en beheerders)' => CheckList::ACCESS_ADMIN,
                    'Vrienden (alleen vrienden van de eigenaar)' => CheckList::ACCESS_FRIENDS,
                    'Ingelogde gebruikers' => CheckList::ACCESS_USER,
                    'Iedereen (openbaar)' => CheckList::ACCESS_PUBLIC,
                ],
                'help' => 'Wie de checklist mag zien en invullen. Hij staat op hun pagina Checklists.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('layoutColumns', ChoiceType::class, [
                'label' => 'Layout',
                'choices' => [
                    '2 kolommen' => 2,
                    '1 kolom' => 1,
                ],
                'expanded' => false,
                'multiple' => false,
                'help' => 'Hoe de velden van een item worden ingedeeld.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('itemsPerPage', ChoiceType::class, [
                'label' => 'Items per pagina',
                'choices' => [
                    '10' => 10,
                    '20' => 20,
                    '50' => 50,
                    '100' => 100,
                ],
                'expanded' => false,
                'multiple' => false,
                'help' => 'Aantal items per pagina in de overzichten.',
                'attr' => ['class' => 'form-select'],
            ]);

        // The default order: a level for every possible choice (orderBy1/orderDir1, orderBy2/orderDir2, ...), so each
        // field can be used once. The page shows the next level only after a choice in the previous one
        // (js/modules/checklist/order-levels.js). Not mapped: they are stored together as CheckList::$defaultOrder.
        $checkList = $options['data'] ?? null;
        $current = $checkList instanceof CheckList ? $checkList->getChosenOrder() : [];
        $choices = $this->orderChoices($checkList instanceof CheckList ? $checkList : null);
        // A field that has been deleted since no longer counts
        $current = array_values(array_filter($current, fn (array $level) => in_array($level['by'], $choices, true)));
        $levels = count($choices);
        for ($level = 1; $level <= $levels; ++$level) {
            $builder
                ->add('orderBy' . $level, ChoiceType::class, [
                    'label' => $level === 1 ? 'Sorteren op' : 'Daarna op',
                    'mapped' => false,
                    'required' => false,
                    'choices' => $choices,
                    // Empty first level: no order chosen, so on creation date
                    'placeholder' => $level === 1 ? '— (aanmaakdatum)' : '—',
                    'data' => $current[$level - 1]['by'] ?? null,
                    'attr' => ['class' => 'form-select'],
                ])
                ->add('orderDir' . $level, ChoiceType::class, [
                    'label' => 'Richting',
                    'mapped' => false,
                    'choices' => [
                        'Oplopend (oud → nieuw, A → Z, 1 → 9)' => 'asc',
                        'Aflopend (nieuw → oud, Z → A, 9 → 1)' => 'desc',
                    ],
                    'data' => $current[$level - 1]['dir'] ?? 'asc',
                    'attr' => ['class' => 'form-select'],
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($levels): void {
            $checkList = $event->getData();
            if (!$checkList instanceof CheckList) {
                return;
            }
            $order = [];
            for ($level = 1; $level <= $levels; ++$level) {
                $order[] = ['by' => $event->getForm()->get('orderBy' . $level)->getData(), 'dir' => $event->getForm()->get('orderDir' . $level)->getData()];
            }
            // Leaves out empty levels and fields that an earlier level already uses
            $checkList->setDefaultOrder($order);
        });
    }

    /** @return array<string, string> label => value for findItemsForList(): the fixed columns, then the checklist's own fields */
    private function orderChoices(?CheckList $checkList): array
    {
        $choices = [
            'Aanmaakdatum' => 'created',
            'Volgorde van toevoegen' => 'id',
            'Aangemaakt door' => 'createdBy',
        ];
        foreach ($checkList?->getAllCheckListFields() ?? [] as $field) {
            if (!$field->isDeleted() && !in_array($field->getFormType(), ['fieldset', 'hidden', 'file'], true)) {
                $choices['Veld: ' . $field->getName()] = (string) $field->getId();
            }
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckList::class,
        ]);
    }
}