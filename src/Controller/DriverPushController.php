<?php
declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DriverPushController extends AbstractController
{
    #[Route('/driver/push/config', name: 'driver_push_config', methods: ['GET'])]
    public function config(DriverPushNotifier $notifier): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_DRIVER');
        $keys = $notifier->keys();
        return new JsonResponse(['enabled' => true, 'publicKey' => $keys['public_key']]);
    }

    #[Route('/driver/push/subscription', name: 'driver_push_subscription', methods: ['POST', 'DELETE'])]
    public function subscription(Request $request, Connection $db): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_DRIVER');
        if (!$this->isCsrfTokenValid('driver_daily_check', (string) $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['error' => 'Invalid security token'], 403);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) return new JsonResponse(['error' => 'Invalid subscription'], 422);
        $endpoint = $data['endpoint'] ?? '';
        if (!is_string($endpoint) || strlen($endpoint) > 2048 || !str_starts_with($endpoint, 'https://')) {
            return new JsonResponse(['error' => 'Invalid endpoint'], 422);
        }
        // Push endpoints are server-side HTTP destinations; never allow arbitrary URLs (SSRF).
        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', 'push.services.mozilla.com', 'wns2-pn1p.notify.windows.com'];
        $isKnown = in_array($host, $allowed, true) || str_ends_with($host, '.push.apple.com') || str_ends_with($host, '.notify.windows.com');
        if (!$isKnown || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return new JsonResponse(['error' => 'Unsupported push endpoint'], 422);
        }
        $hash = hash('sha256', $endpoint);
        $driverId = (int) $this->getUser()->getId();
        if ($request->isMethod('DELETE')) {
            $db->delete('driver_push_subscription', ['driver_id' => $driverId, 'endpoint_hash' => $hash]);
            return new JsonResponse(['ok' => true]);
        }
        $keys = $data['keys'] ?? [];
        $publicKey = $keys['p256dh'] ?? '';
        $authToken = $keys['auth'] ?? '';
        if (!is_string($publicKey) || !is_string($authToken) || strlen($publicKey) > 255 || strlen($authToken) > 255 ||
            !preg_match('/^[A-Za-z0-9_-]{40,}$/D', $publicKey) || !preg_match('/^[A-Za-z0-9_-]{12,}$/D', $authToken)) {
            return new JsonResponse(['error' => 'Invalid subscription keys'], 422);
        }
        $db->executeStatement('INSERT INTO driver_push_subscription (driver_id,endpoint_hash,endpoint,public_key,auth_token,created_at) VALUES (:driver,:hash,:endpoint,:key,:auth,:created) ON DUPLICATE KEY UPDATE driver_id=VALUES(driver_id),public_key=VALUES(public_key),auth_token=VALUES(auth_token)', [
            'driver' => $driverId, 'hash' => $hash, 'endpoint' => $endpoint, 'key' => $publicKey, 'auth' => $authToken,
            'created' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return new JsonResponse(['ok' => true]);
    }
}
