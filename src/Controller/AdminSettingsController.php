<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class AdminSettingsController extends AbstractController
{
    #[Route('/admin/settings', name:'admin_settings', methods:['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return new Response(<<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Settings · Dulogix</title><link rel="icon" href="/favicon.png"><style>body{margin:0;background:#f4f7fb;font:16px system-ui;color:#17314e}header{background:#142d4e;padding:14px 22px}header img{height:36px;background:white;border-radius:5px}main{max-width:1000px;margin:28px auto;padding:0 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px}.card{background:white;border:1px solid #dbe3ee;border-radius:12px;padding:22px}.card h2{margin:0 0 8px;font-size:19px}a{color:#174a7d}.button{display:inline-block;padding:10px 15px;border-radius:7px;background:#174a7d;color:white;text-decoration:none;margin-top:12px}.badge{font-size:12px;background:#fff0d0;padding:4px 8px;border-radius:6px}</style></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a></header><main><p><a href="/">← Operations rota</a></p><h1>Admin Settings</h1><p>Central configuration for Dulogix. Settings marked planned are not editable yet.</p><div class="grid"><section class="card"><h2>Dropdown options</h2><p>Manage the labels, order and active status of rota allocation options (FD, HD, Office, Standby, Ride Along).</p><a class="button" href="/admin/settings/rota">Manage allocation dropdowns</a></section><section class="card"><h2>Other dropdowns</h2><p>Manage reusable option lists for new Dulogix modules.</p><a class="button" href="/admin/settings/options">Manage dropdown lists</a></section><section class="card"><h2>WhatsApp Business</h2><p>Save or change the business sending number. Direct sending is not yet active.</p><a class="button" href="/admin/settings/whatsapp">Configure WhatsApp</a></section><section class="card"><h2>Rota card design</h2><p>Configure the card heading, footer and colours.</p><a class="button" href="/admin/settings/rota-card">Edit rota card design</a></section><section class="card"><h2>Rota deadlines & reminders</h2><p>Configure weekly deadlines, dashboard alerts and the availability broadcast message.</p><a class="button" href="/admin/settings/rota-deadlines">Manage deadlines</a></section></div></main></body></html>
HTML);
    }
}
