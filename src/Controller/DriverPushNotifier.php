<?php
declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

final class DriverPushNotifier
{
    public function __construct(private readonly Connection $db, private readonly LoggerInterface $logger) {}

    public function keys(): array
    {
        $row = $this->db->fetchAssociative('SELECT public_key,private_key FROM driver_push_vapid WHERE id=1');
        if ($row) return $row;
        $keys = VAPID::createVapidKeys();
        $this->db->executeStatement('INSERT IGNORE INTO driver_push_vapid (id,public_key,private_key) VALUES (1,:pub,:priv)', ['pub' => $keys['publicKey'], 'priv' => $keys['privateKey']]);
        return $this->db->fetchAssociative('SELECT public_key,private_key FROM driver_push_vapid WHERE id=1');
    }

    public function schedule(Request $request, string $date, array $drivers): void
    {
        $request->attributes->set('_driver_push', ['date' => $date, 'drivers' => array_values(array_unique(array_map('intval', $drivers)))]);
    }

    #[AsEventListener(event: 'kernel.terminate')]
    public function deliver(TerminateEvent $event): void
    {
        $job = $event->getRequest()->attributes->get('_driver_push');
        if (!is_array($job)) return;
        foreach ($job['drivers'] as $driver) {
            try { $this->notify((string) $job['date'], (int) $driver); }
            catch (\Throwable $e) { $this->logger->warning('Driver push failed: {error}', ['error' => $e->getMessage()]); }
        }
    }

    private function notify(string $date, int $driver): void
    {
        $row = $this->db->fetchAssociative('SELECT p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments,a.instruction_hash FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id LEFT JOIN operations_instruction_ack a ON a.report_date=p.report_date AND a.driver_id=p.driver_id WHERE p.report_date=:day AND p.driver_id=:driver', ['day' => $date, 'driver' => $driver]);
        if (!$row) return;
        $snapshot = [];
        foreach (['holding_time','route','stg_location','van_registration','manager_comments'] as $key) $snapshot[$key] = (string) ($row[$key] ?? '');
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($row['instruction_hash'] !== null && hash_equals((string) $row['instruction_hash'], $hash)) return;
        $subs = $this->db->fetchAllAssociative('SELECT id,endpoint,public_key,auth_token FROM driver_push_subscription WHERE driver_id=:driver', ['driver' => $driver]);
        if (!$subs) return;
        $claimed = $this->db->executeStatement('INSERT IGNORE INTO driver_push_cycle (report_date,driver_id,pending_since,notified_at) VALUES (:day,:driver,NOW(),NOW())', ['day' => $date, 'driver' => $driver]);
        if (!$claimed) return;
        $keys = $this->keys();
        $push = new WebPush(['VAPID' => ['subject' => 'mailto:notifications@dulogix.co.uk', 'publicKey' => $keys['public_key'], 'privateKey' => $keys['private_key']]]);
        $payload = json_encode(['title' => 'Dulogix instructions updated', 'body' => 'Please review and confirm your latest instructions.', 'url' => '/driver/daily-check'], JSON_THROW_ON_ERROR);
        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create(['endpoint' => $sub['endpoint'], 'keys' => ['p256dh' => $sub['public_key'], 'auth' => $sub['auth_token']]]);
                $result = $push->sendOneNotification($subscription, $payload, ['TTL' => 86400]);
                if ($result->isSubscriptionExpired()) $this->db->delete('driver_push_subscription', ['id' => $sub['id']]);
            } catch (\Throwable $e) {
                $this->logger->warning('Driver push endpoint failed: {error}', ['error' => $e->getMessage()]);
            }
        }
    }

    public function acknowledged(string $date, int $driver): void
    {
        $this->db->delete('driver_push_cycle', ['report_date' => $date, 'driver_id' => $driver]);
    }
}
