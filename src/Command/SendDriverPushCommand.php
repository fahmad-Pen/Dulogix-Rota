<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:send-driver-push', description: 'Send due driver instruction alerts; run once per minute')]
final class SendDriverPushCommand extends Command
{
    public function __construct(private readonly Connection $db) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $public = (string) ($_SERVER['DULOGIX_VAPID_PUBLIC_KEY'] ?? $_ENV['DULOGIX_VAPID_PUBLIC_KEY'] ?? getenv('DULOGIX_VAPID_PUBLIC_KEY') ?: '');
        $private = (string) ($_SERVER['DULOGIX_VAPID_PRIVATE_KEY'] ?? $_ENV['DULOGIX_VAPID_PRIVATE_KEY'] ?? getenv('DULOGIX_VAPID_PRIVATE_KEY') ?: '');
        if ($public === '' || $private === '') {
            $output->writeln('Push is not configured; no alerts sent.');
            return Command::SUCCESS;
        }
        // Prevent overlapping cron invocations. Lock is released even on failure.
        $lock = fopen(sys_get_temp_dir().'/dulogix-driver-push.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return Command::SUCCESS;
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/London'));
            $day = $now->format('Y-m-d');
            $rows = $this->db->fetchAllAssociative('SELECT p.report_date,p.driver_id,p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments,a.instruction_hash FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id LEFT JOIN operations_instruction_ack a ON a.report_date=p.report_date AND a.driver_id=p.driver_id WHERE p.report_date=:today', ['today' => $day]);
            $push = new WebPush(['VAPID' => ['subject' => 'mailto:notifications@dulogix.co.uk', 'publicKey' => $public, 'privateKey' => $private]]);
            foreach ($rows as $row) {
                $snapshot = [
                    'holding_time' => (string) ($row['holding_time'] ?? ''),
                    'route' => (string) ($row['route'] ?? ''),
                    'stg_location' => (string) ($row['stg_location'] ?? ''),
                    'van_registration' => (string) ($row['van_registration'] ?? ''),
                    'manager_comments' => (string) ($row['manager_comments'] ?? ''),
                ];
                $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $date = (string) $row['report_date'];
                $driver = (int) $row['driver_id'];
                $read = $row['instruction_hash'] !== null && hash_equals((string) $row['instruction_hash'], $hash);
                $cycle = $this->db->fetchAssociative('SELECT pending_since,notified_at,last_ack_hash FROM driver_push_cycle WHERE report_date=:day AND driver_id=:id', ['day' => $date, 'id' => $driver]);
                if ($read) {
                    if ($cycle) $this->db->delete('driver_push_cycle', ['report_date' => $date, 'driver_id' => $driver]);
                    continue;
                }
                if (!$cycle) {
                    $this->db->executeStatement('INSERT IGNORE INTO driver_push_cycle (report_date,driver_id,pending_since,last_ack_hash) VALUES (:day,:id,:since,:ack)', ['day' => $date, 'id' => $driver, 'since' => $now->format('Y-m-d H:i:s'), 'ack' => $row['instruction_hash']]);
                    continue;
                }
                // A new acknowledgement resets the alert cycle, even if Operations edits again before this job runs.
                if ($cycle['last_ack_hash'] !== $row['instruction_hash']) {
                    $this->db->update('driver_push_cycle', ['pending_since' => $now->format('Y-m-d H:i:s'), 'notified_at' => null, 'last_ack_hash' => $row['instruction_hash']], ['report_date' => $date, 'driver_id' => $driver]);
                    continue;
                }
                if ($cycle['notified_at'] !== null || $cycle['pending_since'] === null ||
                    new \DateTimeImmutable((string) $cycle['pending_since'], new \DateTimeZone('Europe/London')) > $now->modify('-30 seconds')) continue;
                $subscriptions = $this->db->fetchAllAssociative('SELECT id,endpoint,public_key,auth_token FROM driver_push_subscription WHERE driver_id=:id', ['id' => $driver]);
                if (!$subscriptions) continue;
                $sent = false;
                foreach ($subscriptions as $sub) {
                    try {
                        $subscription = Subscription::create(['endpoint' => $sub['endpoint'], 'keys' => ['p256dh' => $sub['public_key'], 'auth' => $sub['auth_token']]]);
                        $payload = json_encode(['title' => 'Dulogix instructions updated', 'body' => 'Please review and confirm your latest instructions.', 'url' => '/driver/daily-check'], JSON_THROW_ON_ERROR);
                        $result = $push->sendOneNotification($subscription, $payload, ['TTL' => 86400]);
                        if ($result->isSuccess()) $sent = true;
                        elseif ($result->isSubscriptionExpired()) $this->db->delete('driver_push_subscription', ['id' => $sub['id']]);
                    } catch (\Throwable $e) {
                        $output->writeln('Push delivery failed for subscription '.$sub['id'].': '.get_class($e));
                    }
                }
                if ($sent) {
                    $this->db->update('driver_push_cycle', ['notified_at' => $now->format('Y-m-d H:i:s')], ['report_date' => $date, 'driver_id' => $driver]);
                    $output->writeln('Alert sent for driver '.$driver.' on '.$date);
                }
            }
            // Keep state bounded; subscriptions persist until revoked or expired.
            $this->db->executeStatement('DELETE FROM driver_push_cycle WHERE report_date < :old', ['old' => $now->modify('-7 days')->format('Y-m-d')]);
            return Command::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
