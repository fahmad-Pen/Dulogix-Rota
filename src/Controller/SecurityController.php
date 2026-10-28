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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · Sign in</title><link rel="icon" type="image/png" href="/favicon.png"><style>
*{box-sizing:border-box}body{margin:0;background:#f5f7fa;color:#22344a;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh}main{background:#fff;border:1px solid #e0e7ef;border-radius:8px;width:min(380px,94vw);padding:24px}h1{font-size:22px;margin:0 0 5px}p{color:#66768a;font-size:13px;margin:0 0 20px}label{display:block;font-size:12px;font-weight:600;margin:12px 0 5px}input{width:100%;border:1px solid #cad6e3;border-radius:5px;padding:10px;font-size:14px}button{width:100%;background:#153c64;color:#fff;border:0;border-radius:5px;padding:11px;margin-top:18px;cursor:pointer}.error{color:#a62d35;margin:8px 0}</style><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></head><body><main><img src="/dulogix-logo.png" alt="Dulogix" style="display:block;width:min(270px,100%);height:auto;margin:0 auto 16px"><h1 style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Dulogix</h1><p>Operations platform · Secure sign in</p>{$message}<form method="post" action="/login"><label for="email">Username</label><input id="email" name="_username" type="text" autocomplete="username" required value="{$email}"><label for="password">Password</label><input id="password" name="_password" type="password" autocomplete="current-password" required><input type="hidden" name="_csrf_token" value="{$csrf}"><div class="cf-turnstile" data-sitekey="{$siteKey}" style="margin-top:16px"></div><button type="submit">Sign in</button></form></main></body></html>
HTML);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by Symfony firewall.');
    }
}
