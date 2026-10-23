<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CheckListField;
use App\Entity\CheckListItem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Service\CheckListConditionEvaluator;

class CheckListItemType extends AbstractType
{
    /** Name of the honeypot field, see the "honeypot" option */
    public const HONEYPOT_FIELD = 'website';

    public function __construct(
        private CheckListConditionEvaluator $conditionEvaluator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldsOption = $options['fields'] ?? null;

        // Spam protection for forms outside the admin: a field people don't see (and leave empty) but bots fill in
        if ($options['honeypot']) {
            $builder->add(self::HONEYPOT_FIELD, TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Laat dit veld leeg',
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ]);
        }

        // Add dynamic fields based on checklist's fields
        $checkListOption = $options['check_list'] ?? null;
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($checkListOption, $fieldsOption) {
            $item = $event->getData();
            $form = $event->getForm();
            $checkList = $item->getCheckList() ?? $checkListOption;

            if ($checkList) {
                $fields = $fieldsOption ?? $checkList->getAllCheckListFields();
                // itemContent is the single source of truth for the answers (AnswerGiven is derived from it)
                $itemContent = $item->getItemContent();

                foreach ($fields as $field) {
                    $formFieldName = $field->getFormFieldName();
                    $fieldName = 'field_' . $formFieldName;

                    if (!$form->has($fieldName)) {
                        $this->addFieldToForm($form, $field);
                    }
                    // Fieldsets have no form field; file inputs can't be pre-filled (the template shows the stored file)
                    if (!$form->has($fieldName) || $field->getFormType() === 'file') {
                        continue;
                    }
                    if (isset($itemContent[$fieldName])) {
                        $value = $itemContent[$fieldName];
                    } elseif ($item->getId() === null && ($default = $this->getDefaultValue($field)) !== null) {
                        // A new item starts with the field's default value
                        $value = $default;
                    } else {
                        continue;
                    }

                    $value = $this->normalizeStoredValue($field, $value);
                    if ($value !== null) {
                        $form->get($fieldName)->setData($value);
                    }
                }
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($checkListOption, $fieldsOption) {
            $data = $event->getData();
            $form = $event->getForm();
            $checkList = $checkListOption;

            if ($checkList) {
                $fields = $fieldsOption ?? $checkList->getAllCheckListFields();
                /** @var CheckListItem|null $item */
                $item = $form->getData();
                $storedContent = $item?->getItemContent() ?? [];

                foreach ($fields as $field) {
                    $fieldName = 'field_' . $field->getFormFieldName();
                    if (!$form->has($fieldName)) {
                        $this->addFieldToForm($form, $field);
                    }

                    // A readonly input is still posted; ignore whatever was sent and keep the stored value
                    // (or the default value for a new item)
                    if (is_array($data) && $this->isReadonly($field)) {
                        $stored = $storedContent[$fieldName] ?? ($item?->getId() === null ? $this->getDefaultValue($field) : null);
                        if ($stored !== null) {
                            $data[$fieldName] = (string) $stored;
                        } else {
                            unset($data[$fieldName]);
                        }
                    }
                }

                // Fields whose "show only when" condition isn't met: nothing is submitted, required and other rules don't apply
                if (is_array($data)) {
                    $hidden = $this->conditionEvaluator->getHiddenFieldIds($fields, fn (string $name) => $data['field_' . $name] ?? null);
                    foreach ($fields as $field) {
                        if (isset($hidden[(int) $field->getId()]) && $form->has('field_' . $field->getFormFieldName())) {
                            unset($data['field_' . $field->getFormFieldName()]);
                            $this->addFieldToForm($form, $field, false);
                        }
                    }
                }

                $event->setData($data);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($checkListOption, $fieldsOption) {
            $item = $event->getData();
            $form = $event->getForm();
            $checkList = $checkListOption;

            if ($checkList && $item) {
                $fields = $fieldsOption ?? $checkList->getAllCheckListFields();
                $previousContent = $item->getItemContent();
                $itemContent = [];
                $hidden = $this->conditionEvaluator->getHiddenFieldIds(
                    $fields,
                    fn (string $name) => $form->has('field_' . $name) ? $form->get('field_' . $name)->getData() : null
                );

                foreach ($fields as $field) {
                    $fieldName = 'field_' . $field->getFormFieldName();
                    // A field hidden by its condition is not saved (also not a previously stored value)
                    if (isset($hidden[(int) $field->getId()])) {
                        continue;
                    }
                    if ($form->has($fieldName)) {
                        $value = $form->get($fieldName)->getData();

                        // Uploaded files are moved by CheckListService::storeItemUploads(); keep the stored path until then
                        if ($field->getFormType() === 'file') {
                            $hasStoredFile = !empty($previousContent[$fieldName]);
                            if ($hasStoredFile) {
                                $itemContent[$fieldName] = $previousContent[$fieldName];
                            }
                            if ($field->isRequired() && !$hasStoredFile && !$value instanceof UploadedFile) {
                                $form->get($fieldName)->addError(new FormError($this->getRequiredMessage($field)));
                            }
                            continue;
                        }

                        if ($value !== null && $value !== '') {
                            // Convert DateTime objects to strings for JSON storage
                            if ($value instanceof \DateTimeInterface) {
                                $phpFormat = $this->getPhpDateFormat($field);
                                if ($field->getFormType() === 'time') {
                                    $phpFormat = 'H:i';
                                }
                                $value = $value->format($phpFormat);
                            }
                            $itemContent[$fieldName] = $value;
                        }
                    }
                }

                $item->setItemContent($itemContent);
            }
        });
    }

    /**
     * @param bool $validate false for a field hidden by its condition: not required and no validation rules
     */
    private function addFieldToForm(\Symfony\Component\Form\FormInterface $form, CheckListField $field, bool $validate = true): void
    {
        $fieldName = 'field_' . $field->getFormFieldName();
        $fieldType = $field->getFormType();

        // A fieldset only groups its child fields in the template; it has no value of its own
        if ($fieldType === 'fieldset') {
            return;
        }

        $options = $field->getOptionsAsArray();
        $required = $field->isRequired();

        $fieldOptions = [
            'label' => $field->getName(),
            'required' => $required,
            'mapped' => false,
            'attr' => ['class' => 'form-control'],
        ];

        // Own placeholder from the field options, otherwise a default per type below
        $placeholder = $field->getPlaceholder();

        $isReadonly = $this->isReadonly($field);
        $isDisabled = $this->isDisabled($field);
        if ($isDisabled) {
            // Symfony ignores submitted data for a disabled field and skips its validation
            $fieldOptions['disabled'] = true;
        } elseif ($isReadonly) {
            $fieldOptions['attr']['readonly'] = 'readonly';
        }
        if (!$isDisabled && $this->hasLockOption($field, 'autofocus')) {
            $fieldOptions['attr']['autofocus'] = 'autofocus';
        }

        // File fields are checked in POST_SUBMIT, because an already stored file also satisfies "required".
        // Readonly/disabled fields can't be filled in, so they are never required.
        if ($required && $fieldType !== 'file' && !$isReadonly && !$isDisabled) {
            $fieldOptions['constraints'] = [
                new NotBlank(message: $this->getRequiredMessage($field)),
            ];
        }

        switch ($fieldType) {
            case 'text':
                $textOptions = $this->withMaxLength($field, $fieldOptions);
                $this->addChild($form, $validate, $fieldName, TextType::class, array_merge($textOptions, [
                    'attr' => array_merge($textOptions['attr'], [
                        'placeholder' => $placeholder ?? 'Enter ' . strtolower($field->getName()),
                    ]),
                ]));
                break;

            case 'email':
                $allowedDomains = $this->getAllowedEmailDomains($field);
                $this->addChild($form, $validate, $fieldName, EmailType::class, array_merge($fieldOptions, [
                    'constraints' => array_merge($fieldOptions['constraints'] ?? [], [
                        new Email(message: 'Dit is geen geldig e-mailadres.'),
                        new Callback(function (mixed $value, ExecutionContextInterface $context) use ($allowedDomains): void {
                            if (!is_string($value) || $value === '' || !$allowedDomains || !str_contains($value, '@')) {
                                return;
                            }
                            $domain = strtolower(substr($value, strrpos($value, '@') + 1));
                            foreach ($allowedDomains as $allowed) {
                                // ".nl" matches any domain ending in .nl, "verzeilberg.nl" only that domain
                                if (str_starts_with($allowed, '.') ? str_ends_with($domain, $allowed) : $domain === $allowed) {
                                    return;
                                }
                            }
                            $context->addViolation('Dit e-maildomein is niet toegestaan. Toegestaan: ' . implode(', ', $allowedDomains));
                        }),
                    ]),
                    'attr' => array_merge($fieldOptions['attr'], [
                        'placeholder' => $placeholder ?? 'name@example.com',
                    ]),
                ]));
                break;

            case 'number':
                $this->addChild($form, $validate, $fieldName, IntegerType::class, array_merge($fieldOptions, [
                    'attr' => array_merge($fieldOptions['attr'], [
                        'placeholder' => $placeholder ?? 'Enter ' . strtolower($field->getName()),
                        'min' => 0,
                        'step' => 1,
                    ]),
                    'constraints' => array_merge($fieldOptions['constraints'] ?? [], [
                        new PositiveOrZero(message: 'Het getal moet 0 of hoger zijn.'),
                    ]),
                ]));
                break;

            case 'textarea':
                $textareaOptions = $this->withMaxLength($field, $fieldOptions);
                $this->addChild($form, $validate, $fieldName, \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, array_merge($textareaOptions, [
                    'attr' => array_merge($textareaOptions['attr'], array_filter(['rows' => 3, 'placeholder' => $placeholder])),
                ]));
                break;

            case 'checkbox':
                $choices = [];
                foreach ($field->getAnswers() as $answer) {
                    $choices[$answer->getLabel()] = $answer->getValue();
                }
                $this->addChild($form, $validate, $fieldName, \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, array_merge($fieldOptions, [
                    'choices' => $choices,
                    'expanded' => true,
                    'multiple' => true,
                    'attr' => ['class' => 'form-check-input'],
                    'label_attr' => ['class' => 'form-check-label'],
                ]));
                break;

            case 'select':
                $choices = [];
                foreach ($field->getAnswers() as $answer) {
                    $choices[$answer->getLabel()] = $answer->getValue();
                }
                $this->addChild($form, $validate, $fieldName, \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, array_merge($fieldOptions, [
                    'choices' => $choices,
                    'placeholder' => $placeholder ?? 'Kies...',
                    'attr' => ['class' => 'form-select'],
                ]));
                break;

            case 'radio':
                $choices = [];
                foreach ($field->getAnswers() as $answer) {
                    $choices[$answer->getLabel()] = $answer->getValue();
                }
                $this->addChild($form, $validate, $fieldName, \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, array_merge($fieldOptions, [
                    'choices' => $choices,
                    'expanded' => true,
                    'multiple' => false,
                    'attr' => ['class' => 'form-check-input'],
                    'label_attr' => ['class' => 'form-check-label'],
                ]));
                break;

            case 'date':
                $storedOptions = $field->getOptionsAsArray();
                $validDateOptions = [
                    'action', 'allow_extra_fields', 'allow_file_upload', 'attr', 'attr_translation_parameters',
                    'auto_initialize', 'block_name', 'block_prefix', 'by_reference', 'calendar',
                    'choice_translation_domain', 'compound', 'constraints', 'csrf_field_name', 'csrf_message',
                    'csrf_protection', 'csrf_token_id', 'csrf_token_manager', 'data', 'data_class', 'days',
                    'disabled', 'empty_data', 'error_bubbling', 'error_mapping', 'extra_fields_message',
                    'form_attr', 'format', 'getter', 'help', 'help_attr', 'help_html', 'help_translation_parameters',
                    'html5', 'inherit_data', 'input', 'input_format', 'invalid_message', 'invalid_message_parameters',
                    'is_empty_callback', 'label', 'label_attr', 'label_format', 'label_html', 'label_translation_parameters',
                    'labels', 'mapped', 'method', 'model_timezone', 'months', 'placeholder', 'post_max_size_message',
                    'priority', 'property_path', 'required', 'row_attr', 'setter', 'translation_domain', 'trim',
                    'upload_max_size_message', 'validation_groups', 'view_timezone', 'widget', 'years',
                ];
                $filteredOptions = array_intersect_key($storedOptions, array_flip($validDateOptions));
                $this->addChild($form, $validate, $fieldName, \Symfony\Component\Form\Extension\Core\Type\DateType::class, array_merge($fieldOptions, $filteredOptions, [
                    'widget' => 'single_text',
                    'format' => $this->getIcuDateFormat($field),
                    'html5' => false,
                    'attr' => [
                        'class' => 'form-control js-datepicker',
                        'data-date-format' => $this->getPhpDateFormat($field),
                        'autocomplete' => 'off',
                    ] + array_filter(['placeholder' => $placeholder]),
                ]));
                break;

            case 'file':
                $constraints = [];
                if (isset($options['maxUploadSize']) && $options['maxUploadSize']) {
                    $constraints[] = new File(
                        maxSize: $options['maxUploadSize'] . 'M',
                        maxSizeMessage: 'Het bestand is te groot. Maximaal: {{ limit }} {{ suffix }}'
                    );
                }
                if (isset($options['allowedFileTypes']) && $options['allowedFileTypes']) {
                    // Accepts extensions ("pdf, jpg") as well as MIME types ("application/pdf")
                    $allowedTypes = $this->getAllowedFileTypes($field);
                    $mimeTypes = array_values(array_filter($allowedTypes, fn (string $type) => str_contains($type, '/')));
                    $extensions = array_values(array_diff($allowedTypes, $mimeTypes));
                    if ($extensions) {
                        $constraints[] = new File(
                            extensions: $extensions,
                            extensionsMessage: 'Ongeldig bestandstype. Toegestaan: {{ extensions }}'
                        );
                    }
                    if ($mimeTypes) {
                        $constraints[] = new File(
                            mimeTypes: $mimeTypes,
                            mimeTypesMessage: 'Ongeldig bestandstype. Toegestaan: {{ types }}'
                        );
                    }
                }
                $fieldOptions = array_merge($fieldOptions, ['constraints' => $constraints, 'required' => false]);
                $this->addChild($form, $validate, $fieldName, FileType::class, array_merge($fieldOptions, [
                    'attr' => ['class' => 'form-control'],
                ]));
                break;

            case 'time':
                $storedOptions = $field->getOptionsAsArray();
                $validTimeOptions = [
                    'action', 'allow_extra_fields', 'allow_file_upload', 'attr', 'attr_translation_parameters',
                    'auto_initialize', 'block_name', 'block_prefix', 'by_reference',
                    'choice_translation_domain', 'compound', 'constraints', 'csrf_field_name', 'csrf_message',
                    'csrf_protection', 'csrf_token_id', 'csrf_token_manager', 'data', 'data_class',
                    'disabled', 'empty_data', 'error_bubbling', 'error_mapping', 'extra_fields_message',
                    'form_attr', 'getter', 'help', 'help_attr', 'help_html', 'help_translation_parameters',
                    'html5', 'inherit_data', 'input', 'input_format', 'invalid_message', 'invalid_message_parameters',
                    'is_empty_callback', 'label', 'label_attr', 'label_format', 'label_html', 'label_translation_parameters',
                    'mapped', 'method', 'model_timezone', 'placeholder', 'post_max_size_message',
                    'priority', 'property_path', 'required', 'row_attr', 'setter', 'translation_domain',
                    'trim', 'upload_max_size_message', 'validation_groups', 'view_timezone', 'widget',
                    'with_minutes', 'with_seconds',
                ];
                $filteredOptions = array_intersect_key($storedOptions, array_flip($validTimeOptions));
                $this->addChild($form, $validate, $fieldName, TimeType::class, array_merge($fieldOptions, $filteredOptions, [
                    'widget' => 'single_text',
                    'attr' => ['class' => 'form-control'],
                ]));
                break;

            case 'url':
                $this->addChild($form, $validate, $fieldName, UrlType::class, array_merge($fieldOptions, [
                    'default_protocol' => 'https',
                    'constraints' => array_merge($fieldOptions['constraints'] ?? [], [
                        new Url(message: 'Dit is geen geldige URL.'),
                    ]),
                    'attr' => array_merge($fieldOptions['attr'], [
                        'placeholder' => $placeholder ?? 'https://',
                    ]),
                ]));
                break;

            case 'hidden':
                $this->addChild($form, $validate, $fieldName, HiddenType::class, $fieldOptions);
                break;

            default:
                $this->addChild($form, $validate, $fieldName, TextType::class, $fieldOptions);
        }
    }

    /**
     * Adds a "restrictions" view var to every field: human readable rules shown behind the info icon.
     * Kept next to addFieldToForm() so the explanation always matches what is validated.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var CheckListItem|null $item */
        $item = $form->getData();
        $checkList = $options['check_list'] ?? $item?->getCheckList();
        if (!$checkList) {
            return;
        }

        foreach ($options['fields'] ?? $checkList->getAllCheckListFields() as $field) {
            $fieldName = 'field_' . $field->getFormFieldName();
            if (isset($view[$fieldName])) {
                $view[$fieldName]->vars['restrictions'] = $this->getFieldRestrictions($field);
            }
        }
    }

    /** @return string[] */
    private function getFieldRestrictions(CheckListField $field): array
    {
        $options = $field->getOptionsAsArray();
        $restrictions = [];

        if ($this->isReadonly($field) || $this->isDisabled($field)) {
            return ['Dit veld kan niet worden gewijzigd'];
        }

        switch ($field->getFormType()) {
            case 'text':
            case 'textarea':
                if ($maxLength = $this->getMaxLength($field)) {
                    $restrictions[] = sprintf('Maximaal %d tekens', $maxLength);
                }
                break;

            case 'email':
                $restrictions[] = 'Een geldig e-mailadres, bijv. naam@example.com';
                if ($domains = $this->getAllowedEmailDomains($field)) {
                    $restrictions[] = 'Toegestane domeinen: ' . implode(', ', array_map(
                        fn (string $domain) => str_starts_with($domain, '.') ? 'alles wat eindigt op ' . $domain : $domain,
                        $domains
                    ));
                }
                break;

            case 'file':
                if (!empty($options['maxUploadSize'])) {
                    $restrictions[] = sprintf('Maximaal %s MB', $options['maxUploadSize']);
                }
                if ($types = $this->getAllowedFileTypes($field)) {
                    $restrictions[] = 'Toegestane bestandstypen: ' . implode(', ', $types);
                }
                break;

            case 'date':
                $restrictions[] = sprintf(
                    'Formaat: %s (bijv. %s)',
                    strtr($this->getIcuDateFormat($field), ['yyyy' => 'jjjj', 'dd' => 'dd', 'MM' => 'mm']),
                    (new \DateTimeImmutable('2024-12-31'))->format($this->getPhpDateFormat($field))
                );
                break;

            case 'number':
                $restrictions[] = 'Alleen hele getallen, 0 of hoger';
                break;

            case 'url':
                $restrictions[] = 'Een geldige URL, bijv. https://example.com';
                $restrictions[] = 'https:// wordt toegevoegd als http(s):// ontbreekt';
                break;
        }

        return $restrictions;
    }

    /**
     * @param class-string         $type
     * @param array<string, mixed> $options
     */
    private function addChild(\Symfony\Component\Form\FormInterface $form, bool $validate, string $fieldName, string $type, array $options): void
    {
        if (!$validate) {
            $options['constraints'] = [];
            $options['required'] = false;
        }

        $form->add($fieldName, $type, $options);
    }

    /**
     * Default value of a field for a new item, in the same shape as a stored value
     * (checkbox: list of values, other fields: string).
     */
    private function getDefaultValue(CheckListField $field): string|array|null
    {
        $default = trim((string) ($field->getOptionsAsArray()['defaultValue'] ?? ''));
        if ($default === '' || in_array($field->getFormType(), ['fieldset', 'file'], true)) {
            return null;
        }

        if ($field->getFormType() === 'checkbox') {
            return array_values(array_filter(array_map('trim', explode(',', $default)), fn (string $value) => $value !== ''));
        }

        return $default;
    }

    /** Field types for which the field admin offers readonly, disabled and autofocus. */
    private const LOCKABLE_TYPES = ['text', 'textarea', 'number'];

    private function hasLockOption(CheckListField $field, string $option): bool
    {
        return in_array($field->getFormType(), self::LOCKABLE_TYPES, true)
            && !empty($field->getOptionsAsArray()[$option]);
    }

    /** Readonly only matters when the field isn't disabled as well (disabled wins). */
    private function isReadonly(CheckListField $field): bool
    {
        return $this->hasLockOption($field, 'readonly') && !$this->isDisabled($field);
    }

    private function isDisabled(CheckListField $field): bool
    {
        return $this->hasLockOption($field, 'disabled');
    }

    /** Configured maximum length for text/textarea fields, or null when not limited. */
    private function getMaxLength(CheckListField $field): ?int
    {
        $options = $field->getOptionsAsArray();
        $maxLength = (int) ($options['maxLengthValue'] ?? 0);

        return !empty($options['maxlength']) && $maxLength > 0 ? $maxLength : null;
    }

    /**
     * @param array<string, mixed> $fieldOptions
     *
     * @return array<string, mixed> $fieldOptions with the max length enforced (constraint + maxlength attribute)
     */
    private function withMaxLength(CheckListField $field, array $fieldOptions): array
    {
        $maxLength = $this->getMaxLength($field);
        if ($maxLength === null) {
            return $fieldOptions;
        }

        $fieldOptions['constraints'] = array_merge($fieldOptions['constraints'] ?? [], [
            new Length(max: $maxLength, maxMessage: 'Maximum {{ limit }} characters allowed.'),
        ]);
        $fieldOptions['attr']['maxlength'] = $maxLength;

        return $fieldOptions;
    }

    /** @return string[] lowercased domains; entries starting with "." match any domain ending with it */
    private function getAllowedEmailDomains(CheckListField $field): array
    {
        $domains = (string) ($field->getOptionsAsArray()['allowedEmailDomains'] ?? '');

        return array_values(array_filter(array_map(
            fn (string $domain) => strtolower(ltrim(trim($domain), '@')),
            explode(',', $domains)
        )));
    }

    /** @return string[] extensions ("pdf") and/or MIME types ("application/pdf") */
    private function getAllowedFileTypes(CheckListField $field): array
    {
        $types = (string) ($field->getOptionsAsArray()['allowedFileTypes'] ?? '');

        return array_values(array_filter(array_map(
            fn (string $type) => strtolower(ltrim(trim($type), '.')),
            explode(',', $types)
        )));
    }

    /**
     * Converts a stored itemContent value to what the field's form type expects.
     */
    private function normalizeStoredValue(CheckListField $field, mixed $value): mixed
    {
        $formType = $field->getFormType();

        // DateType/TimeType need DateTime objects
        if (in_array($formType, ['date', 'time'], true)) {
            if (!is_string($value) || $value === '') {
                return null;
            }
            $phpFormat = $formType === 'time' ? 'H:i' : $this->getPhpDateFormat($field);
            $date = \DateTime::createFromFormat($phpFormat, $value);

            return $date !== false ? $date : null;
        }

        // Checkbox (multiple) keeps an array, radio/select use a single value
        if (in_array($formType, ['checkbox', 'radio', 'select'], true)) {
            if ($formType === 'checkbox') {
                return is_array($value) ? array_values($value) : [$value];
            }

            return is_array($value) ? ($value === [] ? null : reset($value)) : $value;
        }

        return $value;
    }

    private function getRequiredMessage(CheckListField $field): string
    {
        return $field->getRequiredMessage() ?: 'Dit veld is verplicht.';
    }

    /**
     * ICU date pattern (e.g. "dd-MM-yyyy") from the field options; strips a trailing example like " (31-12-2024)".
     */
    private function getIcuDateFormat(CheckListField $field): string
    {
        $dateFormat = (string) ($field->getOptionsAsArray()['dateFormat'] ?? '');
        $dateFormat = trim((string) preg_replace('/\s*\(.*\)$/', '', $dateFormat));

        return $dateFormat !== '' ? $dateFormat : 'yyyy-MM-dd';
    }

    /**
     * PHP/flatpickr equivalent of the ICU date pattern (e.g. "d-m-Y").
     */
    private function getPhpDateFormat(CheckListField $field): string
    {
        return strtr($this->getIcuDateFormat($field), ['yyyy' => 'Y', 'MM' => 'm', 'dd' => 'd']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckListItem::class,
            'check_list' => null,
            'fields' => null,
            // Adds an invisible spam trap field (public forms)
            'honeypot' => false,
        ]);
    }
}