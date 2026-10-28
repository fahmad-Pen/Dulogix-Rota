<?php
namespace App\Controller;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
final class GlobalNavigationListener
{
    public function __construct(private Security $security) {}

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        $response = $event->getResponse();
        if ($response->getStatusCode() !== 200 || !str_contains((string)$response->headers->get('Content-Type', ''), 'text/html')) return;
        $user = $this->security->getUser();
        if (!$user instanceof User) return;
        $html = $response->getContent();
        if (!is_string($html) || !str_contains($html, '</header>') || str_contains($html, 'id="du-main-nav"')) return;
        $html = preg_replace('~</header>~i', SectionAccess::navigation($user).'</header>', $html, 1);
        if (str_contains($html, '</head>')) $html = preg_replace('~</head>~i', SectionAccess::CSS.'</head>', $html, 1);
        $response->setContent($html);
    }
}
