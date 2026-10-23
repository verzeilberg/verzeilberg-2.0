<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserDetails;
use App\Form\ChangePasswordFormType;
use App\Form\Model\ChangePasswordFormModel;
use App\Form\Model\ProfileFormModel;
use App\Form\Model\RegistrationFormModel;
use App\Form\Model\ResetPasswordFormModel;
use App\Form\ProfileFormType;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\CheckListRepository;
use App\Repository\UserRepository;
use App\Security\Voter\CheckListVoter;
use App\Service\RegistrationNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

class AuthController extends AbstractController
{
    private const REGISTERED_MESSAGE = 'Welkom! We hebben je een e-mail gestuurd: klik op de link daarin om je e-mailadres te bevestigen. Daarna keurt een beheerder je account goed.';
    private const TOO_MANY_MAILS_MESSAGE = 'Er zijn net al een paar mails verstuurd. Wacht een kwartier en probeer het dan opnieuw.';

    /** Avatars live in {app.uploads_dir}/avatars; UserDetails::$avatar holds "uploads/avatars/{file}" for asset() */
    private const AVATAR_PATH = 'uploads/avatars/';

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
    public function register(Request $request, RegistrationNotifier $registrationNotifier, RateLimiterFactoryInterface $registrationLimiter): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $registrationData = new RegistrationFormModel();
        $form = $this->createForm(RegistrationFormType::class, $registrationData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A filled in honeypot means a bot: act as if it worked, but save nothing
            if ($form->get(RegistrationFormType::HONEYPOT_FIELD)->getData()) {
                $this->addFlash('success', self::REGISTERED_MESSAGE);
                return $this->redirectToRoute('app_login');
            }

            if (!$registrationLimiter->create('ip-' . $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'Er zijn vanaf jouw internetverbinding te veel accounts aangemaakt. Probeer het over een uur opnieuw.');
                return $this->render('auth/register.html.twig', [
                    'form' => $form,
                ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
            }

            $user = new User();
            $user->setEmail($registrationData->email);
            $user->setFullName($registrationData->firstName . ' ' . $registrationData->lastName);
            $user->setPassword($this->passwordHasher->hashPassword($user, $registrationData->plainPassword));
            // The user confirms their address, then an admin approves the account before it can log in
            $user->setStatus(User::STATUS_PENDING);
            $token = bin2hex(random_bytes(32));
            $user->setEmailVerificationToken($token);

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

            $registrationNotifier->confirmEmail($user, $this->generateUrl('app_register_confirm', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL));

            $this->addFlash('success', self::REGISTERED_MESSAGE);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    /** The link in the confirmation mail; only then the site's owner hears of the registration */
    #[Route('/register/confirm/{token}', name: 'app_register_confirm', methods: ['GET'])]
    public function confirmEmail(string $token, UserRepository $userRepository, RegistrationNotifier $registrationNotifier): Response
    {
        $user = $userRepository->findByEmailVerificationToken($token);

        if ($user === null || !$user->isPending()) {
            $this->addFlash('error', 'Deze bevestigingslink is ongeldig of al gebruikt.');
            return $this->redirectToRoute('app_login');
        }

        $user->markEmailVerified();
        $this->em->flush();
        $registrationNotifier->userRegistered($user);

        $this->addFlash('success', 'Je e-mailadres is bevestigd. Een beheerder keurt je account zo snel mogelijk goed; je krijgt een e-mail zodra je kunt inloggen.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/register/resend', name: 'app_register_resend', methods: ['GET', 'POST'])]
    public function resendConfirmation(Request $request, UserRepository $userRepository, RegistrationNotifier $registrationNotifier, RateLimiterFactoryInterface $accountMailLimiter): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register_resend', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            } elseif (!$accountMailLimiter->create('ip-' . $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', self::TOO_MANY_MAILS_MESSAGE);
            } elseif ($email = trim($request->request->getString('email'))) {
                $user = $userRepository->findByEmail($email);
                if ($user !== null && $user->isPending() && !$user->isEmailVerified()) {
                    $token = bin2hex(random_bytes(32));
                    $user->setEmailVerificationToken($token);
                    $this->em->flush();
                    $registrationNotifier->confirmEmail($user, $this->generateUrl('app_register_confirm', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL));
                }

                // Don't reveal whether the address is known
                $this->addFlash('success', 'Als er voor dit e-mailadres een account wacht op bevestiging, is er een nieuwe bevestigingslink gestuurd.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/register_resend.html.twig', [
            'email' => $request->query->getString('email'),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository, RateLimiterFactoryInterface $accountMailLimiter): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if (!$this->isCsrfTokenValid('forgot_password', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            } elseif (!$accountMailLimiter->create('ip-' . $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', self::TOO_MANY_MAILS_MESSAGE);
            } elseif ($email) {
                $user = $userRepository->findByEmail($email);

                if ($user && $user->isActive()) {
                    // Generate a secure token
                    $token = bin2hex(random_bytes(32));
                    $user->setPasswordResetToken($token);
                    $user->setPasswordResetTokenCreationDate(new \DateTimeImmutable());
                    $this->em->flush();

                    // Send email with reset link
                    $resetLink = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                    $this->sendPasswordResetEmail($user, $resetLink);
                }

                // Don't reveal if email exists or not for security
                $this->addFlash('success', 'Als dit e-mailadres bij ons bekend is, is er een link gestuurd om je wachtwoord opnieuw in te stellen.');
            }
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    private function sendPasswordResetEmail(User $user, string $resetLink): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg.nl'))
            ->to($user->getEmail())
            ->subject('Stel je wachtwoord opnieuw in')
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
            $this->addFlash('error', 'Deze link om je wachtwoord opnieuw in te stellen is ongeldig of verlopen.');
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

            $this->addFlash('success', 'Je wachtwoord is opnieuw ingesteld. Je kunt nu inloggen.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(CheckListRepository $checkListRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('auth/profile.html.twig', [
            'user' => $user,
            // Users with checklist.own make and manage their own checklists from here
            'checkLists' => $this->isGranted(CheckListVoter::PERM_OWN) ? $checkListRepository->findOwnedBy($user) : [],
        ]);
    }

    /** Switches the user's page in the user directory between public and private */
    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request, #[Autowire('%app.uploads_dir%')] string $uploadsDir): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        $model = ProfileFormModel::fromUser($user);
        $form = $this->createForm(ProfileFormType::class, $model);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $details = $user->getDetails() ?? (new UserDetails())->setUser($user);
            $user->setDetails($details);
            $model->applyTo($user, $details);

            $avatarDir = $uploadsDir . '/avatars';
            $oldAvatar = $details->getAvatar();

            if ($model->avatarFile !== null) {
                $filename = sprintf('%d-%s.%s', $user->getId(), bin2hex(random_bytes(6)), $model->avatarFile->guessExtension() ?? 'jpg');
                try {
                    $model->avatarFile->move($avatarDir, $filename);
                    $details->setAvatar(self::AVATAR_PATH . $filename);
                } catch (FileException) {
                    $this->addFlash('error', 'Je avatar kon niet worden opgeslagen, probeer het opnieuw.');
                    return $this->redirectToRoute('app_profile_edit');
                }
            } elseif ($model->removeAvatar) {
                $details->setAvatar(null);
            }

            // Only delete files this page uploaded, never a path set elsewhere
            if ($oldAvatar !== null && $oldAvatar !== $details->getAvatar() && str_starts_with($oldAvatar, self::AVATAR_PATH)) {
                (new Filesystem())->remove($avatarDir . '/' . basename($oldAvatar));
            }

            $this->em->persist($details);
            $this->em->flush();

            $this->addFlash('success', 'Je profiel is opgeslagen.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('auth/profile_edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, RememberMeHandlerInterface $rememberMeHandler): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        $data = new ChangePasswordFormModel();
        $form = $this->createForm(ChangePasswordFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data->plainPassword));
            // A reset link that was still open no longer works
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenCreationDate(null);
            $this->em->flush();

            // A new password hash logs out the other devices (and their "remember me" cookies).
            // This session keeps the user with the new hash, so it stays logged in without a new login
            // (which would ask for the two-factor code again); only its "remember me" cookie is renewed
            if ($request->cookies->has('REMEMBERME')) {
                $rememberMeHandler->createRememberMeCookie($user);
            }

            $this->addFlash('success', 'Je wachtwoord is gewijzigd. Hier blijf je ingelogd; op andere apparaten moet je opnieuw inloggen.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('auth/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/profile/visibility', name: 'app_profile_visibility', methods: ['POST'])]
    public function profileVisibility(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_visibility', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setProfilePublic($request->request->getBoolean('profilePublic'));
        $this->em->flush();

        $this->addFlash('success', $user->isProfilePublic()
            ? 'Je pagina is nu openbaar en staat in het gebruikersoverzicht.'
            : 'Je pagina is nu privé en staat niet meer in het gebruikersoverzicht.');

        return $this->redirectToRoute('app_profile');
    }
}
