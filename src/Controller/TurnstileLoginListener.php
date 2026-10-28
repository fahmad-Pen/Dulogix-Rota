<?php
namespace App\Controller;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: CheckPassportEvent::class, priority: 2048)]
final class TurnstileLoginListener
{
    public function __construct(
        #[Autowire('%env(TURNSTILE_SECRET_KEY)%')] private readonly string $secret,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) return;
        if ($request->getPathInfo() !== '/login' || !$request->isMethod('POST')) return;
        $token = $request->request->get('cf-turnstile-response');
        if (!is_string($token) || $token === '' || strlen($token) > 2048 || $this->secret === '') {
            throw new CustomUserMessageAuthenticationException('Security verification failed. Please try again.');
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['secret'=>$this->secret,'response'=>$token,'remoteip'=>$request->getClientIp()]),
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        $data = is_string($result) ? json_decode($result, true) : null;
        if (!is_array($data) || ($data['success'] ?? false) !== true) {
            throw new CustomUserMessageAuthenticationException('Security verification failed. Please try again.');
        }
    }
}
