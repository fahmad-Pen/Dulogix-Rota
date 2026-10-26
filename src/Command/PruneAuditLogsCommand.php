<?php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:logs:prune', description: 'Remove rota audit entries older than three months')]
final class PruneAuditLogsCommand extends Command
{
    public function __construct(private readonly Connection $connection) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-3 months');
        $count = $this->connection->executeStatement('DELETE FROM rota_audit WHERE created_at < :cutoff', ['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        $output->writeln(sprintf('Removed %d audit records older than %s.', $count, $cutoff->format('Y-m-d')));
        return Command::SUCCESS;
    }
}
