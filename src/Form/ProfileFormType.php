<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ProfileFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Voornaam',
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Achternaam',
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('catchLine', TextType::class, [
                'label' => 'Slogan',
                'required' => false,
                'attr' => ['placeholder' => 'Eén zin over jezelf'],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => 'Vertel anderen iets over jezelf'],
            ])
            ->add('location', TextType::class, [
                'label' => 'Locatie',
                'required' => false,
                'attr' => ['placeholder' => 'bijv. Amsterdam', 'autocomplete' => 'address-level2'],
            ])
            ->add('website', UrlType::class, [
                'label' => 'Website',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://', 'autocomplete' => 'url'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telefoon',
                'required' => false,
                'help' => 'Alleen zichtbaar voor jou, nooit op je openbare pagina.',
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Geboortedatum',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Alleen zichtbaar voor jou, nooit op je openbare pagina.',
                'attr' => ['autocomplete' => 'bday'],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Avatar',
                'required' => false,
                'help' => 'JPG, PNG, WebP of GIF, maximaal 4 MB. Een vierkante foto werkt het best.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif'],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'label' => 'Mijn avatar verwijderen',
                'required' => false,
            ])
            ->add('emailNotifications', CheckboxType::class, [
                'label' => 'Stuur me een e-mail als iemand op mijn reactie reageert of me een vriendschapsverzoek stuurt',
                'required' => false,
            ]);

        $social = $builder->create('socialLinks', FormType::class, ['label' => false, 'required' => false]);
        foreach (ProfileFormModel::SOCIAL_PLATFORMS as $platform => $label) {
            $social->add($platform, UrlType::class, [
                'label' => $label,
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://'],
            ]);
        }
        $builder->add($social);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfileFormModel::class,
        ]);
    }
}
