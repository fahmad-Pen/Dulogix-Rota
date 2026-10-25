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
        if (str_starts_with($path, '/admin/') || str_starts_with($path, '/api/')) {
            $user = $this->tokens->getToken()?->getUser();
            if ($user instanceof User && in_array('ROLE_MANAGER', $user->getRoles(), true)
                && !SectionAccess::allowed($user, 'rota')) {
                throw new AccessDeniedHttpException('Rota Management is not assigned to your account.');
            }
        }
    }
}
