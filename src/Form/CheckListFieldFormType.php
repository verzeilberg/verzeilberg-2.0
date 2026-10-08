<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Answer;
use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckListFieldFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('introText', TextareaType::class, [
                'label' => 'Intro Text',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('order', NumberType::class, [
                'label' => 'Order',
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 0],
                'data' => 999,
            ])
            ->add('parent', EntityType::class, [
                'class' => CheckListField::class,
                'choices' => $options['parent_choices'],
                'choice_label' => 'name',
                'label' => 'Fieldset',
                'required' => false,
                'placeholder' => '— None (top level) —',
                'help' => 'Place this field in a fieldset. You can also do this by dragging the field in the Fields tab.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('defaultValue', TextType::class, [
                'label' => 'Default value',
                'mapped' => false,
                'required' => false,
                'help' => 'Pre-filled for a new item. For a choice field: the value of the answer (checkbox: several values separated by commas).',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'Placeholder',
                'mapped' => false,
                'required' => false,
                'help' => 'Example text shown in the empty field (for a dropdown: the text of the empty choice). Leave empty for the default.',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('conditionField', ChoiceType::class, [
                'label' => 'Show only when',
                'mapped' => false,
                'required' => false,
                'choices' => array_combine(
                    array_map(fn (array $source) => $source['name'], $options['condition_sources']),
                    array_keys($options['condition_sources'])
                ) ?: [],
                'placeholder' => '— Always show —',
                'help' => 'Only show this field (or fieldset) when a choice field has one of the selected answers.',
                'attr' => ['class' => 'form-select', 'data-condition-sources' => json_encode($options['condition_sources'])],
            ])
            // JSON list of answer values, filled by the checkboxes the field form script renders for the chosen field
            ->add('conditionValues', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('showInOverview', CheckboxType::class, [
                'label' => 'Show in Overview',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('fieldType', EntityType::class, [
                'label' => 'Field Type',
                'class' => CheckListFieldType::class,
                'choice_label' => 'name',
                'placeholder' => 'Select field type',
                'required' => true,
                // The field form script shows the matching options by form type (not by database id)
                'choice_attr' => fn (CheckListFieldType $type) => ['data-form-type' => $type->getFormType()],
                'attr' => ['class' => 'form-select'],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('ft')
                        ->orderBy('ft.name', 'ASC');
                },
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'Required',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('requiredMessage', TextType::class, [
                'label' => 'Required Message',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('answers', EntityType::class, [
                'label' => 'Predefined Answers (for select/radio/checkbox)',
                'class' => Answer::class,
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'form-select', 'size' => 5],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('a')
                        ->orderBy('a.label', 'ASC');
                },
            ])
            ->add('readonly', CheckboxType::class, [
                'label' => 'Read only',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('disabled', CheckboxType::class, [
                'label' => 'Disabled',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('maxlength', CheckboxType::class, [
                'label' => 'Max length',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('maxLengthValue', NumberType::class, [
                'label' => 'Max length value',
                'required' => false,
                'mapped' => false,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 255],
            ])
            ->add('autofocus', CheckboxType::class, [
                'label' => 'Autofocus',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('resize', CheckboxType::class, [
                'label' => 'Resize',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('measurements', CheckboxType::class, [
                'label' => 'Dimensions',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('textareaWidth', NumberType::class, [
                'label' => 'Width',
                'required' => false,
                'mapped' => false,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'width', 'min' => 1, 'step' => 1],
            ])
            ->add('textareaHeight', NumberType::class, [
                'label' => 'Height',
                'required' => false,
                'mapped' => false,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'height', 'min' => 1, 'step' => 1],
            ])
            ->add('measurementsType', ChoiceType::class, [
                'label' => 'Unit',
                'required' => false,
                'mapped' => false,
                'choices' => ['px' => 'px', '%' => '%'],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('prependappend', CheckboxType::class, [
                'label' => 'Prepend/Append',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
                'data' => true,
            ])
            ->add('preapp', ChoiceType::class, [
                'label' => 'Position',
                'required' => true,
                'mapped' => false,
                'choices' => ['Prepend' => 'pre', 'Append' => 'app'],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('apppreinput', TextType::class, [
                'label' => 'Value',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'VB: ml'],
            ])
            ->add('maxUploadSize', NumberType::class, [
                'label' => 'Max Upload Size (MB)',
                'required' => false,
                'mapped' => false,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 100, 'step' => 1],
            ])
            ->add('allowedFileTypes', TextType::class, [
                'label' => 'Allowed File Types',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'pdf,jpg,png or application/pdf,image/jpeg,image/png'],
            ])
            ->add('dateFormat', ChoiceType::class, [
                'label' => 'Date Format',
                'required' => false,
                'mapped' => false,
                'choices' => [
                    'dd-MM-yyyy (31-12-2024)' => 'dd-MM-yyyy',
                    'yyyy-MM-dd (2024-12-31)' => 'yyyy-MM-dd',
                    'MM/dd/yyyy (12/31/2024)' => 'MM/dd/yyyy',
                    'dd/MM/yyyy (31/12/2024)' => 'dd/MM/yyyy',
                    'yyyy/MM/dd (2024/12/31)' => 'yyyy/MM/dd',
                    'dd.MM.yyyy (31.12.2024)' => 'dd.MM.yyyy',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('showTime', CheckboxType::class, [
                'label' => 'Show Time',
                'required' => false,
                'mapped' => false,
                'value' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('allowedEmailDomains', TextType::class, [
                'label' => 'Allowed Email Domains',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'verzeilberg.nl,hotmail.com,.nl,.eu'],
            ]);

        $conditionSources = $options['condition_sources'];
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($conditionSources) {
            $field = $event->getData();
            $form = $event->getForm();

            // formFieldName is not in the form: it is generated once when the field is created
            // (CheckListService::ensureUniqueFormFieldName) and never changes, so renaming keeps the stored answers

            $options = $field->getOptionsAsArray();

            // Selected field type determines which option fields are processed
            $formType = $form->has('fieldType') ? $form->get('fieldType')->getData()?->getFormType() : null;
            $isCheckboxType = $formType === 'checkbox';

            // Handle main option fields (text/textarea/radio/email/number/date/time/datetime) - NOT for checkbox type
            if (!$isCheckboxType) {
                $optionFields = ['readonly', 'disabled', 'maxlength', 'maxLengthValue', 'autofocus', 'resize', 'measurements', 'textareaWidth', 'textareaHeight', 'measurementsType', 'preapp', 'apppreinput'];
                $booleanFields = ['readonly', 'disabled', 'maxlength', 'autofocus', 'resize', 'measurements'];
                foreach ($optionFields as $optionField) {
                    if ($form->has($optionField)) {
                        $value = $form->get($optionField)->getData();
                        // Convert checkbox value (string "1" or boolean true) to boolean ONLY for boolean fields
                        if (in_array($optionField, $booleanFields)) {
                            if ($value === '1' || $value === true) {
                                $value = true;
                            } elseif ($value === '0' || $value === false || $value === '') {
                                $value = false;
                            }
                        }
                        if ($value !== null && $value !== '') {
                            $options[$optionField] = $value;
                        } elseif (isset($options[$optionField])) {
                            unset($options[$optionField]);
                        }
                    }
                }
            }

            // Handle prepend/append specially
            if ($form->has('prependappend') && $form->get('prependappend')->getData()) {
                $options['prependappend'] = [
                    'on' => true,
                    'preapp' => $form->get('preapp')->getData() ?? 'pre',
                    'value' => $form->get('apppreinput')->getData() ?? '',
                    'type' => $form->get('apppreinput')->getData() ? 'text' : 'icon',
                ];
            } else {
                unset($options['prependappend']);
            }

            // Handle File field options
            if ($form->has('maxUploadSize')) {
                $value = $form->get('maxUploadSize')->getData();
                if ($value !== null && $value !== '') {
                    $options['maxUploadSize'] = (int)$value;
                }
            }
            if ($form->has('allowedFileTypes')) {
                $value = $form->get('allowedFileTypes')->getData();
                if ($value !== null && $value !== '') {
                    $options['allowedFileTypes'] = $value;
                }
            }

            // Handle Date field options
            if ($form->has('dateFormat')) {
                $value = $form->get('dateFormat')->getData();
                if ($value !== null && $value !== '') {
                    $options['dateFormat'] = $value;
                }
            }
            if ($form->has('showTime')) {
                $value = $form->get('showTime')->getData();
                if ($value !== null && $value !== '') {
                    $options['showTime'] = (bool)$value;
                }
            }

            // Default value (not for fieldsets and files)
            if ($form->has('defaultValue')) {
                $default = trim((string) $form->get('defaultValue')->getData());
                if ($default !== '' && !in_array($field->getFormType(), ['fieldset', 'file'], true)) {
                    $options['defaultValue'] = $default;
                } else {
                    unset($options['defaultValue']);
                }
            }

            // Placeholder (only for types that show one, see CheckListField::PLACEHOLDER_TYPES)
            if ($form->has('placeholder')) {
                $placeholder = trim((string) $form->get('placeholder')->getData());
                if ($placeholder !== '' && $field->supportsPlaceholder()) {
                    $options['placeholder'] = $placeholder;
                } else {
                    unset($options['placeholder']);
                }
            }

            // "Show only when" condition: only values that are answers of the chosen field are kept
            if ($form->has('conditionField')) {
                $sourceName = $form->get('conditionField')->getData();
                $source = $sourceName !== null ? ($conditionSources[$sourceName] ?? null) : null;
                $values = json_decode((string) $form->get('conditionValues')->getData(), true);
                $values = is_array($values) && $source !== null
                    ? array_values(array_intersect(array_map('strval', $values), array_map('strval', array_keys($source['answers']))))
                    : [];
                if ($source !== null && $values !== []) {
                    $options['condition'] = ['field' => $sourceName, 'values' => $values];
                } else {
                    unset($options['condition']);
                    if ($source !== null) {
                        $form->get('conditionField')->addError(new \Symfony\Component\Form\FormError('Select at least one answer for which this field is shown.'));
                    }
                }
            }

            // Handle Email field options
            if ($form->has('allowedEmailDomains')) {
                $value = $form->get('allowedEmailDomains')->getData();
                if ($value !== null && $value !== '') {
                    $options['allowedEmailDomains'] = $value;
                }
            }

            $field->setOptionsAsArray($options);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckListField::class,
            // Fieldsets this field may be placed in, see CheckListService::getAllowedParentFieldsets()
            'parent_choices' => [],
            // Fields this field can depend on, see CheckListService::getConditionSources()
            'condition_sources' => [],
        ]);
        $resolver->setAllowedTypes('parent_choices', 'array');
        $resolver->setAllowedTypes('condition_sources', 'array');
    }
}