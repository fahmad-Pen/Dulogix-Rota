<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RotaController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfManager): Response
    {
        if (!$this->getUser()) return $this->redirectToRoute('app_login');
        if (!$this->isGranted('ROLE_OPERATIONS') || !SectionAccess::allowed($this->getUser(), 'rota')) return $this->redirectToRoute($this->isGranted('ROLE_DRIVER') ? 'driver_daily_check' : 'account_profile');
        $html = <<<'HTML'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dulogix | Weekly Rota</title><link rel="icon" type="image/png" href="/favicon.png">
<link rel="stylesheet" href="/weekly-rota.css?v=3"><link rel="stylesheet" href="/rota-demo-extra.css?v=2"><link rel="stylesheet" href="/rota-whatsapp-composer.css?v=2"><link rel="stylesheet" href="/du-navigation.css?v=1"><link rel="stylesheet" href="/rota-compact.css?v=8"></head><body>
<header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" class="rota-header-logo"></a></h1>__DULOGIX_NAV__</header>
<main>
<div class="toolbar"><div class="week"><button id="prev" aria-label="Previous week">◀</button><strong id="week"></strong><button id="next" aria-label="Next week">▶</button><label for="week-calendar" class="rota-jump-label">Jump to week:</label><input id="week-calendar" type="date" aria-label="Choose a date to view its rota week" title="Choose any date; Dulogix will open the Monday–Sunday week containing it"></div><div class="week-export"><span id="lock" class="pill"></span><button type="button" id="export-week-pdf" title="Print the complete selected week or save it as a PDF">🖨 Export PDF</button></div></div>
<section id="rota-deadline-panel" class="card" aria-live="polite"><strong>Weekly actions</strong><p>Loading deadlines…</p></section>
<details class="card planning-card" id="weekly-operations-planning"><summary>Weekly operations planning</summary><div class="scroll"><table id="summary"><thead></thead><tbody></tbody></table></div><p class="note">Planning targets are provisional until configured by operations.</p></details>
<section class="card"><div class="driver-heading"><h2>Driver availability and allocations <span class="pill">Driver accounts</span></h2><div class="driver-filters"><input id="search" placeholder="Search driver name" aria-label="Search driver name"> <select id="filter"><option value="all">All drivers</option><option value="conflict">Conflicts</option><option value="pending">Pending changes</option><option value="not-submitted">Not submitted availability</option><option value="availability-changed">Availability changed — review required</option></select></div></div><div class="scroll"><table id="drivers"><thead></thead><tbody></tbody></table></div><p class="note">Driver availability and management allocations are recorded separately.</p></section>
</main><footer>Dulogix · Operations</footer>
<script src="/weekly-rota-inline-1.js?v=1"></script><script src="/weekly-rota-inline-2.js?v=1"></script><script src="/weekly-rota-inline-3.js?v=1"></script><script src="/weekly-rota-inline-4.js?v=2"></script><script src="/weekly-rota-control-ids.js?v=1" defer></script><script src="/rota-demo.js?v=47" defer></script><script src="/dulogix-image-share.js?v=1"></script><link rel="stylesheet" href="/rota-share.css?v=3"><script src="/rota-share.js?v=16" defer></script><script src="/rota-whatsapp.js?v=18" defer></script><script src="/rota-deadlines.js?v=12" defer></script><script src="/rota-availability-reviews.js?v=7" defer></script></body></html>
HTML;
        $token = htmlspecialchars($csrfManager->getToken('rota_edit')->getValue(), ENT_QUOTES, 'UTF-8');
        if (!$this->isGranted('ROLE_ADMIN')) { $html = str_replace('<a href="/admin/settings" class="rota-admin-nav-link">⚙ Admin Settings</a><a href="/driver/availability?preview=1" class="rota-admin-nav-link">Driver preview</a>', '', $html); }
        $html = str_replace('__DULOGIX_NAV__', SectionAccess::navigation($this->getUser()), $html);
        $html = str_replace('</head>', '<link rel="stylesheet" href="/du-navigation.css?v=1"></head>', $html);
        return new Response(str_replace('</head>', '<meta name="rota-csrf" content="'.$token.'"></head>', $html));
    }
}