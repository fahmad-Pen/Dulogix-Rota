<?php
namespace App\Controller;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SectionGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokens) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST => ['check', -20]]; }
    public function check(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        $path = $event->getRequest()->getPathInfo();
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User || !in_array('ROLE_MANAGER', $user->getRoles(), true)) return;
        $method = $event->getRequest()->getMethod();
        // Scorecard distribution belongs to Driver Performance, not Rota Management.
        if (str_starts_with($path, '/api/driver-performance/')) {
            if (SectionAccess::performanceLevel($user) === 'none') {
                throw new AccessDeniedHttpException('Driver Performance is not assigned to your account.');
            }
            if (!in_array($method, ['GET','HEAD','OPTIONS'], true)
                && !preg_match('~^/api/driver-performance/[^/]+/distribution/\d+$~', $path)
                && SectionAccess::performanceLevel($user) !== 'full') {
                throw new AccessDeniedHttpException('Read-only access: editing is not permitted.');
            }
            return;
        }

        $section = null;
        if ($path === '/' || str_starts_with($path, '/rota/') || str_starts_with($path, '/api/rota/') || str_starts_with($path, '/api/whatsapp/')) $section = 'rota';
        elseif (str_starts_with($path, '/vehicles')) $section = 'vehicle';
        elseif (str_starts_with($path, '/operations/')) $section = 'operations';
        elseif (str_starts_with($path, '/admin/users')) $section = 'rota';
        if ($section === null) return;
        if (!SectionAccess::allowed($user, $section)) {
            throw new AccessDeniedHttpException('This section is not assigned to your account.');
        }
        // Read-only managers may view, filter, export, print and share, but never alter records.
        $sharing = (bool) preg_match('~^/api/rota/[^/]+/distribution/\d+$~', $path)
            || (bool) preg_match('~^/api/rota/[^/]+/whatsapp/\d+$~', $path)
            || (bool) preg_match('~^/api/whatsapp/general/\d+$~', $path);
        if (!in_array($method, ['GET','HEAD','OPTIONS'], true) && !$sharing && !SectionAccess::canEdit($user, $section)) {
            throw new AccessDeniedHttpException('Read-only access: editing is not permitted.');
        }
    }
}
