<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $this->getFieldType($options['setting_type'] ?? 'string');
        
        $builder
            ->add('value', $fieldType, [
                'label' => 'Waarde',
                'required' => false,
            ]);

        // Add data transformer for boolean type
        if ($fieldType === CheckboxType::class) {
            $builder->get('value')
                ->addModelTransformer(new CallbackTransformer(
                    // Entity -> Form: string to boolean
                    function (?string $value): bool {
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    },
                    // Form -> Entity: boolean to string
                    function (bool $value): string {
                        return $value ? '1' : '0';
                    }
                ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Setting::class,
            'setting_type' => 'string',
        ]);
    }

    private function getFieldType(string $type): string
    {
        return match ($type) {
            'boolean' => CheckboxType::class,
            'text' => TextareaType::class,
            default => TextType::class,
        };
    }
}