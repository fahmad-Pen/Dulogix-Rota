<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfManager, #[Autowire('%env(TURNSTILE_SITE_KEY)%')] string $turnstileSiteKey): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute(SectionAccess::allowed($this->getUser(), 'rota') ? 'app_home' : (SectionAccess::allowed($this->getUser(), 'vehicle') ? 'vehicle_dashboard' : ($this->isGranted('ROLE_DRIVER') ? 'driver_daily_check' : 'account_profile')));
        }
        $error = $authenticationUtils->getLastAuthenticationError();
        $email = htmlspecialchars($authenticationUtils->getLastUsername(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inactive = $error instanceof \Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
        $message = $error ? '<p class="error">'.($inactive ? 'Your account is not active. Please contact your manager.' : ($error instanceof \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException ? 'Security verification failed. Please try again.' : 'Invalid username or password.')).'</p>' : '';
        $siteKey = htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfManager->getToken('authenticate')->getValue(), ENT_QUOTES, 'UTF-8');
        return new Response(<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · Sign in</title><link rel="icon" type="image/png" href="/favicon.png"><link rel="stylesheet" href="/login-page.css?v=1"><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></head><body><main><img src="/dulogix-logo.png" alt="Dulogix" class="login-logo"><h1 class="login-visually-hidden">Dulogix</h1><p>Operations platform · Secure sign in</p>{$message}<form method="post" action="/login"><label for="email">Username</label><input id="email" name="_username" type="text" autocomplete="username" required value="{$email}"><label for="password">Password</label><input id="password" name="_password" type="password" autocomplete="current-password" required><input type="hidden" name="_csrf_token" value="{$csrf}"><div class="cf-turnstile" data-sitekey="{$siteKey}" class="login-turnstile"></div><button type="submit">Sign in</button></form></main></body></html>
HTML);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by Symfony firewall.');
    }
}