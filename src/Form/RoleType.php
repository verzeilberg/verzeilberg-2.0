<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Role $role */
        $role = $builder->getData();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Naam',
                'disabled' => $role->getId() !== null && $role->isSystemRole(),
                'attr' => ['placeholder' => 'bijv. ROLE_EDITOR', 'class' => 'font-monospace'],
                'help' => $role->isSystemRole() ? 'Systeemrol; de naam kan niet worden gewijzigd.' : 'Begint met ROLE_, alleen hoofdletters en _.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschrijving',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Wie krijgt deze rol?'],
            ])
            ->add('parentRoles', EntityType::class, [
                'label' => 'Erft rechten van',
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => 'Deze rol krijgt ook alle rechten van de rollen die je hier aanvinkt.',
                'query_builder' => function (EntityRepository $repo) use ($role) {
                    $qb = $repo->createQueryBuilder('r')
                        ->andWhere('r.deleted = false')
                        ->orderBy('r.name', 'ASC');
                    if ($role->getId() !== null) {
                        $qb->andWhere('r.id != :self')->setParameter('self', $role->getId());
                    }

                    return $qb;
                },
            ])
            ->add('permissions', EntityType::class, [
                'label' => 'Rechten',
                'class' => Permission::class,
                'choice_label' => fn(Permission $p) => $p->getName(),
                'choice_attr' => fn(Permission $p) => ['title' => (string) $p->getDescription()],
                'group_by' => fn(Permission $p) => $p->getGroup(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => fn(EntityRepository $repo) => $repo->createQueryBuilder('p')
                    ->andWhere('p.deleted = false')
                    ->orderBy('p.name', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'constraints' => [new Callback($this->validateNoInheritanceLoop(...))],
        ]);
    }

    /** A role may not (indirectly) inherit from itself */
    public function validateNoInheritanceLoop(Role $role, ExecutionContextInterface $context): void
    {
        foreach ($role->getParentRoles() as $parent) {
            if (in_array($role, $parent->getAncestorsAndSelf(), true)) {
                $context->buildViolation(sprintf('%s erft al van %s; terug erven zou een lus maken.', $parent->getName(), $role->getName()))
                    ->atPath('parentRoles')
                    ->addViolation();
            }
        }
    }
}
