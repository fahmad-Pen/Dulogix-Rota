<?php

namespace App\Controller;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Most Dulogix screens render their own HTML rather than extending base.html.twig.
 * Add PWA metadata to those full HTML pages without touching API/JSON responses.
 */
final class PwaHeadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -64]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->isMethod('GET')) return;
        $response = $event->getResponse();
        if ($response->getStatusCode() !== 200) return;
        $contentType = strtolower($response->headers->get('Content-Type', ''));
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) return;
        $html = $response->getContent();
        if (!is_string($html) || stripos($html, '</head>') === false) return;
        // When no Content-Type is set, only touch complete HTML documents.
        if ($contentType === '' && !preg_match('/^\\s*(?:<!doctype\\s+html|<html\\b)/i', $html)) return;
        if (stripos($html, 'rel="manifest"') !== false) return;
        $head = <<<'HTML'
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Dulogix">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<script defer src="/pwa-register.js"></script>
HTML;
        if ($event->getRequest()->getPathInfo() === '/driver/daily-check') {
            $head .= "\n<script defer src=\"/driver-push.js\"></script>";
        }
        $html = preg_replace('~</head>~i', $head."\n</head>", $html, 1);
        if ($html === null) return;
        $response->setContent($html);
        $response->headers->remove('Content-Length');
    }
}
