<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserDetails;
use App\Form\Model\RegistrationFormModel;
use App\Form\Model\ResetPasswordFormModel;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private MailerInterface $mailer
    ) {}

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $registrationData = new RegistrationFormModel();
        $form = $this->createForm(RegistrationFormType::class, $registrationData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setEmail($registrationData->email);
            $user->setFullName($registrationData->firstName . ' ' . $registrationData->lastName);
            $user->setPassword($this->passwordHasher->hashPassword($user, $registrationData->plainPassword));
            $user->setStatus(User::STATUS_ACTIVE);

            // Create user details from form data
            $details = new UserDetails();
            $details->setFirstName($registrationData->firstName);
            $details->setLastName($registrationData->lastName);
            $details->setUser($user);
            $user->setDetails($details);

            // Assign default ROLE_USER
            $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_USER']);
            if ($role) {
                $user->addRole($role);
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Welcome! Your account has been created. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if ($email) {
                $user = $userRepository->findByEmail($email);

                if ($user && $user->isActive()) {
                    // Generate a secure token
                    $token = bin2hex(random_bytes(32));
                    $user->setPasswordResetToken($token);
                    $user->setPasswordResetTokenCreationDate(new \DateTimeImmutable());
                    $this->em->flush();

                    // Send email with reset link
                    $resetLink = $this->generateUrl('app_reset_password', ['token' => $token], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
                    $this->sendPasswordResetEmail($user, $resetLink);

                    $this->addFlash('success', 'If the email exists, a password reset link has been sent.');
                } else {
                    // Don't reveal if email exists or not for security
                    $this->addFlash('success', 'If the email exists, a password reset link has been sent.');
                }
            }
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    private function sendPasswordResetEmail(User $user, string $resetLink): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg.nl'))
            ->to($user->getEmail())
            ->subject('Reset your password')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetLink' => $resetLink,
                'expiryHours' => 1,
            ]);

        $this->mailer->send($email);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, UserRepository $userRepository): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = $userRepository->findByPasswordResetToken($token);

        // Token validity: 1 hour
        $tokenExpiry = new \DateTimeImmutable('-1 hour');

        if (!$user || !$user->getPasswordResetTokenCreationDate() || $user->getPasswordResetTokenCreationDate() < $tokenExpiry) {
            $this->addFlash('error', 'Invalid or expired password reset token.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $resetData = new ResetPasswordFormModel();
        $form = $this->createForm(ResetPasswordFormType::class, $resetData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $resetData->plainPassword));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenCreationDate(null);
            $this->em->flush();

            $this->addFlash('success', 'Your password has been reset. You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('auth/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}