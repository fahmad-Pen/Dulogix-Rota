<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Production-only diagnostics; never records query strings, payloads or headers. */
final class ProductionDiagnosticsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', -128],
            KernelEvents::RESPONSE => ['onResponse', -128],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($this->environment !== 'prod' || !$event->isMainRequest()) return;
        $e = $event->getThrowable();
        $this->write('exception', $event->getRequest()->getPathInfo(), [
            'type' => get_class($e),
            'source' => basename($e->getFile()).':'.$e->getLine(),
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($this->environment !== 'prod' || !$event->isMainRequest()) return;
        $request = $event->getRequest();
        $status = $event->getResponse()->getStatusCode();
        $start = $request->server->get('REQUEST_TIME_FLOAT');
        $duration = is_numeric($start) ? round((microtime(true) - (float) $start) * 1000) : null;
        $threshold = max(250, (int) (getenv('DULOGIX_SLOW_REQUEST_MS') ?: 2000));
        // Capture Operations Report timings even when the page is below the global slow threshold.
        $isOperationsReport = $request->isMethod('GET') && $request->getPathInfo() === '/operations/report';
        if ($status >= 500 || ($duration !== null && ($duration >= $threshold || $isOperationsReport))) {
            $this->write($status >= 500 ? 'http_error' : ($duration !== null && $duration >= $threshold ? 'slow_request' : 'operations_timing'), $request->getPathInfo(), [
                'status' => $status, 'duration_ms' => $duration, 'method' => $request->getMethod(),
            ]);
        }
    }

    private function write(string $kind, string $path, array $details): void
    {
        $path = preg_replace('/[0-9a-f]{16,}|\\d{6,}/i', '[id]', $path) ?? '[invalid]';
        $entry = json_encode([
            'at' => gmdate('c'), 'event' => $kind,
            'path' => substr($path, 0, 180),
        ] + $details, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if ($entry === false) return;

        $dir = $this->projectDir.'/var/log';
        $file = $dir.'/production-diagnostics.log';
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log('[dulogix] '.$entry);
            return;
        }
        $lock = @fopen($dir.'/.production-diagnostics.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            error_log('[dulogix] '.$entry);
            if ($lock) fclose($lock);
            return;
        }
        try {
            // Daily rotation permits reliable 30-day retention without deleting recent errors.
            // Also rotate oversized logs to bound disk usage during busy days.
            $today = gmdate('Y-m-d');
            $activeDay = is_file($file) ? gmdate('Y-m-d', (int) filemtime($file)) : $today;
            if (is_file($file) && ($activeDay !== $today || filesize($file) >= 10 * 1024 * 1024)) {
                $suffix = '.'.$activeDay;
                $archive = $file.$suffix;
                for ($i = 1; is_file($archive); ++$i) $archive = $file.$suffix.'.'.$i;
                @rename($file, $archive);
            }
            // Cleanup occurs on writes; no cron is required. Keep today's active log
            // and archives from the preceding 30 days.
            foreach (glob($file.'.*') ?: [] as $archive) {
                if (preg_match('/\\.(\\d{4}-\\d{2}-\\d{2})(?:\\.\\d+)?$/', $archive, $matches)) {
                    if ($matches[1] < gmdate('Y-m-d', time() - 30 * 86400)) @unlink($archive);
                } elseif (preg_match('/\\.\\d+$/', $archive) && filemtime($archive) < time() - 30 * 86400) {
                    // Remove expired archives created by the previous numbered rotation scheme.
                    @unlink($archive);
                }
            }
            if (@file_put_contents($file, $entry."\n", FILE_APPEND | LOCK_EX) === false) {
                error_log('[dulogix] '.$entry);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
