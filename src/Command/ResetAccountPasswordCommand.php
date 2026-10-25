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

#[AsCommand(name: 'app:reset-account-password', description: 'Reset an existing account password interactively')]
final class ResetAccountPasswordCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>Interactive terminal required.</error>');
            return Command::FAILURE;
        }
        $helper = $this->getHelper('question');
        $name = trim((string) $helper->ask($input, $output, new Question('Existing username: ')));
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => mb_strtolower($name)]);
        if (!$user) {
            $output->writeln('<error>Account not found.</error>');
            return Command::FAILURE;
        }
        $question = new Question('New password (hidden): ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = (string) $helper->ask($input, $output, $question);
        if (strlen($password) < 12) {
            $output->writeln('<error>Use at least 12 characters.</error>');
            return Command::FAILURE;
        }
        $confirm = new Question('Repeat new password (hidden): ');
        $confirm->setHidden(true);
        $confirm->setHiddenFallback(false);
        if ($password !== (string) $helper->ask($input, $output, $confirm)) {
            $output->writeln('<error>Passwords do not match. Nothing changed.</error>');
            return Command::FAILURE;
        }
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();
        $output->writeln('<info>Password updated successfully.</info>');
        return Command::SUCCESS;
    }
}
