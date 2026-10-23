<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use App\Entity\RoutePermission;
use App\Security\AdminRouteAccess;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RoutePermissionType extends AbstractType
{
    public function __construct(private AdminRouteAccess $adminRouteAccess)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('routePattern', TextType::class, [
                'label' => 'Route(s)',
                'attr' => [
                    'class' => 'font-monospace',
                    'placeholder' => 'admin_blog_*',
                    'list' => 'admin-route-suggestions',
                    'autocomplete' => 'off',
                ],
                'help' => 'Een routenaam, of gebruik * als jokerteken: admin_blog_* geldt voor elke blogbeheerpagina.',
            ])
            ->add('permission', EntityType::class, [
                'label' => 'Recht dat toegang geeft',
                'class' => Permission::class,
                'choice_label' => fn(Permission $p) => $p->getName() . ($p->getDescription() ? ' — ' . $p->getDescription() : ''),
                'group_by' => fn(Permission $p) => $p->getGroup(),
                'placeholder' => 'Kies een recht...',
                'query_builder' => fn(EntityRepository $repo) => $repo->createQueryBuilder('p')
                    ->andWhere('p.deleted = false')
                    ->orderBy('p.name', 'ASC'),
            ])
            ->add('methods', ChoiceType::class, [
                'label' => 'Alleen voor deze HTTP-methoden',
                'choices' => array_combine(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Laat alles uit voor elke methode. Vink alleen GET aan om bekijken toe te staan maar opslaan niet.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actief',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Notitie',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoutePermission::class,
            'constraints' => [new Callback($this->validateRoutePattern(...))],
        ]);
    }

    public function validateRoutePattern(RoutePermission $rule, ExecutionContextInterface $context): void
    {
        if ($rule->getRoutePattern() === '') {
            return;
        }

        $matched = array_filter(
            array_keys($this->adminRouteAccess->getAdminRoutes()),
            fn(string $route) => $rule->matchesRouteName($route),
        );

        if ($matched === []) {
            $context->buildViolation('Dit past bij geen enkele beheerroute. Kies er een uit de suggesties of controleer de spelling.')
                ->atPath('routePattern')
                ->addViolation();
            return;
        }

        $adminOnly = array_filter($matched, AdminRouteAccess::isAdminOnlyRoute(...));
        if (count($adminOnly) === count($matched)) {
            $context->buildViolation('Het beheer van gebruikers, rollen en rechten is altijd alleen voor ROLE_ADMIN; een regel kan dat niet openzetten.')
                ->atPath('routePattern')
                ->addViolation();
        }
    }
}
