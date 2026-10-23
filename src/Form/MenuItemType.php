<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MenuItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $menu = $options['menu'] ?? null;

        $builder
            ->add('label', TextType::class, [
                'label' => 'Label',
                'attr' => ['placeholder' => 'Label van het menu-item'],
            ])
            ->add('link', TextType::class, [
                'label' => 'Link (URL of route)',
                'required' => false,
                'attr' => ['placeholder' => '/pad of route_naam'],
                'help' => 'Laat leeg voor kopjes in een dropdown. Gebruik routenamen zoals "app_home" of URL\'s.',
            ])
            ->add('icon', TextType::class, [
                'label' => 'Pictogramklasse',
                'required' => false,
                'attr' => ['placeholder' => 'fas fa-home'],
                'help' => 'FontAwesome-pictogramklasse (bijv. fas fa-home).',
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Volgorde',
                'attr' => ['min' => 0],
            ])
            ->add('authorizedFor', TextType::class, [
                'label' => 'Zichtbaar voor (rol)',
                'required' => false,
                'attr' => ['placeholder' => 'ROLE_ADMIN, ROLE_EDITOR'],
                'help' => 'Rollen die dit item zien, gescheiden door komma\'s. Leeg = zichtbaar voor iedereen.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actief',
                'required' => false,
            ])
            ->add('openInNewTab', CheckboxType::class, [
                'label' => 'Openen in nieuw tabblad',
                'required' => false,
            ])
            ->add('cssClass', TextType::class, [
                'label' => 'CSS-klasse',
                'required' => false,
                'attr' => ['placeholder' => 'custom-class'],
            ]);

        if ($menu) {
            $builder->add('parent', EntityType::class, [
                'label' => 'Bovenliggend item',
                'required' => false,
                'placeholder' => '— Hoofdniveau —',
                'class' => MenuItem::class,
                'choice_label' => fn(MenuItem $item) => str_repeat('— ', $item->getDepth()) . $item->getLabel(),
                'query_builder' => fn($repo) => $repo->createQueryBuilder('mi')
                    ->andWhere('mi.menu = :menu')
                    ->andWhere('mi.id != :id')
                    ->setParameter('menu', $menu)
                    ->setParameter('id', $options['item_id'] ?? 0)
                    ->orderBy('mi.sortOrder', 'ASC'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuItem::class,
            'menu' => null,
            'item_id' => null,
        ]);
    }
}