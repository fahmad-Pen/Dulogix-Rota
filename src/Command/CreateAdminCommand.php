<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-admin', description: 'Create a local administrator interactively')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Run this command interactively; passwords are never accepted as command arguments.');
            return Command::FAILURE;
        }
        $helper = $this->getHelper('question');
        $username = mb_strtolower(trim((string) $io->ask('Administrator username', 'admin')));
        $email = trim((string) $io->ask('Administrator email (optional for initial account)', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Enter a valid email address.');
            return Command::FAILURE;
        }
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username) || $this->em->getRepository(User::class)->findOneBy(['username' => $username]) || ($email !== '' && $this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)]))) {
            $io->error('That account already exists.');
            return Command::FAILURE;
        }
        $name = trim((string) $io->ask('Display name'));
        if ($name === '') return Command::FAILURE;
        $question = new Question('Password (hidden): ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = (string) $helper->ask($input, $output, $question);
        if (strlen($password) < 12) {
            $io->error('Password must be at least 12 characters.');
            return Command::FAILURE;
        }
        $user = (new User())->setUsername($username)->setEmail($email)->setDisplayName($name)->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $io->success('Administrator account created.');
        return Command::SUCCESS;
    }
}
