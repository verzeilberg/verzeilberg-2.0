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
                'attr' => ['placeholder' => 'Menu item label'],
            ])
            ->add('link', TextType::class, [
                'label' => 'Link (URL or route)',
                'required' => false,
                'attr' => ['placeholder' => '/path or route_name'],
                'help' => 'Leave empty for dropdown headers. Use route names like "app_home" or URLs.',
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icon Class',
                'required' => false,
                'attr' => ['placeholder' => 'fas fa-home'],
                'help' => 'FontAwesome icon class (e.g., fas fa-home).',
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sort Order',
                'attr' => ['min' => 0],
            ])
            ->add('authorizedFor', TextType::class, [
                'label' => 'Authorized For (Role)',
                'required' => false,
                'attr' => ['placeholder' => 'ROLE_ADMIN, ROLE_EDITOR'],
                'help' => 'Comma-separated roles that can see this item. Empty = visible to all.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Enabled',
                'required' => false,
            ])
            ->add('openInNewTab', CheckboxType::class, [
                'label' => 'Open in New Tab',
                'required' => false,
            ])
            ->add('cssClass', TextType::class, [
                'label' => 'CSS Class',
                'required' => false,
                'attr' => ['placeholder' => 'custom-class'],
            ]);

        if ($menu) {
            $builder->add('parent', EntityType::class, [
                'label' => 'Parent Item',
                'required' => false,
                'placeholder' => '— Root Level —',
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