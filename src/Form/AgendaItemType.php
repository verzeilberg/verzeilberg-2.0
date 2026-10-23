<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Repository\AgendaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An agenda item with separate date and time fields. For a whole-day item the times are ignored and
 * the item runs from the start of the first day until the end of the last day.
 */
class AgendaItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agenda', EntityType::class, [
                'class' => Agenda::class,
                'choice_label' => 'title',
                'label' => 'Agenda',
                // Agendas that follow a Google calendar are filled by the sync, not by hand
                'query_builder' => fn (AgendaRepository $repository) => $repository->createQueryBuilder('a')
                    ->andWhere('a.deleted = false')
                    ->andWhere('a.googleAccount IS NULL')
                    ->orderBy('a.title', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('allDay', CheckboxType::class, [
                'label' => 'Hele dag',
                'required' => false,
                'attr' => ['class' => 'form-check-input js-all-day'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('startDate', DateType::class, ['label' => 'Startdatum', 'mapped' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-control']])
            ->add('startTime', TimeType::class, ['label' => 'Starttijd', 'mapped' => false, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-control js-time']])
            ->add('endDate', DateType::class, ['label' => 'Einddatum', 'mapped' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-control']])
            ->add('endTime', TimeType::class, ['label' => 'Eindtijd', 'mapped' => false, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-control js-time']])
            ->add('location', TextType::class, [
                'label' => 'Locatie',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschrijving',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ]);

        // Fill the date and time fields from startAt/endAt
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $item = $event->getData();
            if (!$item instanceof AgendaItem) {
                return;
            }
            $form = $event->getForm();
            $form->get('startDate')->setData($item->getStartAt());
            $form->get('endDate')->setData($item->getEndAt());
            if (!$item->isAllDay()) {
                $form->get('startTime')->setData($item->getStartAt());
                $form->get('endTime')->setData($item->getEndAt());
            }
        });

        // Combine the date and time fields into startAt/endAt (before validation, so the period is validated too)
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $item = $event->getData();
            if (!$item instanceof AgendaItem) {
                return;
            }
            $form = $event->getForm();
            $startDate = $form->get('startDate')->getData();
            $endDate = $form->get('endDate')->getData() ?? $startDate;
            if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable) {
                return;
            }

            if ($item->isAllDay()) {
                $item->setAllDayPeriod($startDate, $endDate);

                return;
            }

            $startTime = $form->get('startTime')->getData();
            $endTime = $form->get('endTime')->getData();
            if (!$startTime instanceof \DateTimeImmutable) {
                $form->get('startTime')->addError(new FormError('Vul een starttijd in, of kies "Hele dag".'));

                return;
            }
            if (!$endTime instanceof \DateTimeImmutable) {
                $form->get('endTime')->addError(new FormError('Vul een eindtijd in, of kies "Hele dag".'));

                return;
            }
            $item->setStartAt($startDate->setTime((int) $startTime->format('H'), (int) $startTime->format('i')));
            $item->setEndAt($endDate->setTime((int) $endTime->format('H'), (int) $endTime->format('i')));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AgendaItem::class]);
    }
}
