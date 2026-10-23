<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserDetails;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * First administrator of a new installation (bin/setup.sh runs it), or a new password / admin rights for an
 * existing account. Also creates the base roles when they don't exist yet (a new database has none).
 */
#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Create an administrator account (or give an existing account admin rights and a new password)',
)]
class CreateAdminCommand extends Command
{
    /** Base roles; the hierarchy itself is in config/packages/security.yaml */
    private const ROLES = [
        'ROLE_ADMIN' => 'Administrator with full access',
        'ROLE_EDITOR' => 'Content editor',
        'ROLE_AUTHOR' => 'Content author',
        'ROLE_USER' => 'Regular user',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail address (the login name)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Full name, e.g. "Jan Jansen"')
            ->addOption('password-from-env', null, InputOption::VALUE_REQUIRED, 'Read the password from this environment variable (for scripts) instead of asking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $this->ensureRoles();
        if ($created !== []) {
            $io->note('Created the roles: ' . implode(', ', $created));
        }

        $email = trim((string) ($input->getOption('email') ?? $io->ask('E-mail address (login)', null, function (?string $value): string {
            if (!filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Enter a valid e-mail address.');
            }

            return trim((string) $value);
        })));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Enter a valid e-mail address.');

            return Command::INVALID;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user !== null && !$io->confirm(sprintf('"%s" already exists. Give it admin rights and a new password?', $email), true)) {
            return Command::SUCCESS;
        }

        $name = null;
        if ($user === null) {
            $name = trim((string) ($input->getOption('name') ?? $io->ask('Full name', null, fn (?string $value) => trim((string) $value) !== '' ? trim((string) $value) : throw new \RuntimeException('Enter a name.'))));
        }

        // The password is checked before anything is created
        $password = $this->password($input, $io);
        if ($password === null) {
            return Command::INVALID;
        }

        if ($user === null) {
            $user = (new User())->setEmail($email)->setFullName((string) $name)->setStatus(User::STATUS_ACTIVE);
            [$firstName, $lastName] = array_pad(explode(' ', (string) $name, 2), 2, '');
            $user->setDetails((new UserDetails())->setFirstName($firstName)->setLastName($lastName)->setUser($user));
            $this->em->persist($user);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setStatus(User::STATUS_ACTIVE);
        foreach (['ROLE_ADMIN', 'ROLE_USER'] as $roleName) {
            $role = $this->roleRepository->findOneBy(['name' => $roleName]);
            if ($role !== null) {
                $user->addRole($role); // skips a role the user already has
            }
        }
        $this->em->flush();

        $io->success(sprintf('%s can log in at /login as administrator.', $email));

        return Command::SUCCESS;
    }

    /** @return list<string> the roles that were created */
    private function ensureRoles(): array
    {
        $created = [];
        foreach (self::ROLES as $name => $description) {
            if ($this->roleRepository->findOneBy(['name' => $name]) === null) {
                $this->em->persist((new Role())->setName($name)->setDescription($description));
                $created[] = $name;
            }
        }
        $this->em->flush();

        return $created;
    }

    private function password(InputInterface $input, SymfonyStyle $io): ?string
    {
        $variable = $input->getOption('password-from-env');
        if ($variable !== null) {
            $password = (string) getenv((string) $variable);
        } else {
            $password = (string) $io->askHidden('Password (at least 8 characters, not shown)');
            if ($password !== (string) $io->askHidden('Repeat the password')) {
                $io->error('The passwords are not the same.');

                return null;
            }
        }
        if (mb_strlen($password) < 8) {
            $io->error('The password must be at least 8 characters.');

            return null;
        }

        return $password;
    }
}
