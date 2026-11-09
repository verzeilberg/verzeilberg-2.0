<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Spam protection without third parties: a small sum in words ("drie plus vier") that the visitor answers.
 * The answer lives in the session; every submission (right or wrong) gets a new sum, so a bot can't
 * keep guessing the same one or replay a solved one.
 */
class MathCaptchaType extends AbstractType
{
    private const SESSION_KEY = 'math_captcha';
    private const WORDS = [1 => 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen', 'tien'];

    public function __construct(private RequestStack $requestStack) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $session = $this->session();
            $expected = $session->get(self::SESSION_KEY)['answer'] ?? null;
            $given = trim((string) $event->getData());

            if ($expected === null || $given !== (string) $expected) {
                $event->getForm()->addError(new FormError('Dat antwoord klopt niet. Probeer de nieuwe som.'));
            }

            $this->newSum($session);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $sum = $this->session()->get(self::SESSION_KEY) ?? $this->newSum($this->session());
        $view->vars['label'] = sprintf('Hoeveel is %s plus %s? (antwoord in cijfers)', self::WORDS[$sum['a']], self::WORDS[$sum['b']]);
        $view->vars['value'] = '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => true,
            'attr' => ['class' => 'form-control', 'inputmode' => 'numeric', 'autocomplete' => 'off'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    /** @return array{a: int, b: int, answer: int} */
    private function newSum(SessionInterface $session): array
    {
        $a = random_int(1, 10);
        $b = random_int(1, 10);
        $sum = ['a' => $a, 'b' => $b, 'answer' => $a + $b];
        $session->set(self::SESSION_KEY, $sum);

        return $sum;
    }

    private function session(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
