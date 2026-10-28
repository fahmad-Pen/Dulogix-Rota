<?php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:logs:prune', description: 'Remove availability audit entries older than 21 days and other rota audit entries older than three months')]
final class PruneAuditLogsCommand extends Command
{
    public function __construct(private readonly Connection $connection) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-3 months');
        $availabilityCutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-21 days');
        $availabilityCount = $this->connection->executeStatement('DELETE FROM rota_audit WHERE field_key = :field AND created_at < :cutoff', ['field' => 'availability', 'cutoff' => $availabilityCutoff->format('Y-m-d H:i:s')]);
        $otherCount = $this->connection->executeStatement('DELETE FROM rota_audit WHERE field_key <> :field AND created_at < :cutoff', ['field' => 'availability', 'cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        $output->writeln(sprintf('Removed %d availability audit records older than 21 days (before %s).', $availabilityCount, $availabilityCutoff->format('Y-m-d')));
        $output->writeln(sprintf('Removed %d other rota audit records older than three months (before %s).', $otherCount, $cutoff->format('Y-m-d')));
        return Command::SUCCESS;
    }
}
