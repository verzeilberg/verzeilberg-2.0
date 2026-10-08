<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RoutePermission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoutePermissionType extends AbstractType
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $adminRoutes = $this->getAdminRoutes();

        $builder
            ->add('routePattern', ChoiceType::class, [
                'label' => 'Route Pattern',
                'choices' => $adminRoutes,
                'placeholder' => 'Select a route...',
                'required' => false,
                'attr' => ['class' => 'form-select font-monospace'],
                'help' => 'Select an admin route or type a custom pattern. {id} matches any value.',
            ])
            ->add('permissionName', TextType::class, [
                'label' => 'Permission Name',
                'attr' => [
                    'placeholder' => 'BLOG_CREATE',
                    'class' => 'form-control font-monospace text-uppercase',
                ],
                'help' => 'Permission name (without PERM_ prefix).',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('methods', ChoiceType::class, [
                'label' => 'HTTP Methods',
                'choices' => [
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Select methods. Leave empty for all methods.',
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Priority',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0'],
                'help' => 'Higher priority rules checked first.',
            ])
            ->add('enabled', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Enabled' => true,
                    'Disabled' => false,
                ],
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoutePermission::class,
        ]);
    }

    private function getAdminRoutes(): array
    {
        $routes = [];
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection as $name => $route) {
            if (str_starts_with($name, 'admin_')) {
                $path = $route->getPath();
                $methods = $route->getMethods();
                $methodStr = $methods ? ' [' . implode(',', $methods) . ']' : '';
                $routes[$name . $methodStr] = $path;
            }
        }

        ksort($routes);
        return $routes;
    }
}