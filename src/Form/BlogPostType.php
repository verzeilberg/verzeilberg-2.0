<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BlogPost;
use App\Entity\Category;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogPostType extends AbstractType
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        // A new post gets its slug from the title before validation, so the UniqueEntity check on the slug
        // reports a duplicate as a form error ("This slug is already in use") instead of a database error.
        // An existing post keeps its slug, so its URL doesn't change when the title is edited.
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $post = $event->getData();
            // Read the title from the form: the entity's typed property isn't set when no title was submitted
            $title = (string) $event->getForm()->get('title')->getData();
            if ($post instanceof BlogPost && $post->getId() === null && $title !== '') {
                $post->setSlug((string) $this->slugger->slug($title)->lower());
            }
        });

        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => ['placeholder' => 'Enter blog post title'],
            ])
            ->add('introText', TextareaType::class, [
                'label' => 'Introduction',
                'attr' => ['rows' => 3, 'placeholder' => 'Short introduction (shown in lists)'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'attr' => ['rows' => 20],
            ])
            ->add('status', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Draft' => BlogPost::STATUS_DRAFT,
                    'Online' => BlogPost::STATUS_ONLINE,
                    'Offline' => BlogPost::STATUS_OFFLINE,
                    'Archived' => BlogPost::STATUS_ARCHIVED,
                ],
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('categories', EntityType::class, [
                'label' => 'Categories',
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->andWhere('c.enabled = true')
                    ->orderBy('c.name', 'ASC'),
                'required' => false,
            ])
            ->add('dateOnline', DateType::class, [
                'label' => 'Publish Date',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Leave empty for immediate'],
            ])
            ->add('timeOnline', TimeType::class, [
                'label' => 'Publish Time',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'data' => new \DateTimeImmutable('09:00:00'),
            ])
            ->add('dateOffline', DateType::class, [
                'label' => 'Unpublish Date',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['placeholder' => 'Leave empty for no expiry'],
            ])
            ->add('timeOffline', TimeType::class, [
                'label' => 'Unpublish Time',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'data' => new \DateTimeImmutable('23:59:59'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogPost::class,
            'is_edit' => false,
        ]);
    }
}