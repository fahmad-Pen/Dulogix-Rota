<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RetentionPolicyController extends AbstractController
{
    #[Route('/admin/settings/retention', name: 'admin_retention_policy', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return new Response(<<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Retention policy · Dulogix</title><style>body{margin:0;background:#f4f7fb;color:#17314e;font:13px system-ui}main{max-width:880px;margin:22px auto;padding:0 16px}h1{font-size:25px}.card{background:#fff;border:1px solid #dbe3ee;border-radius:9px;padding:17px;margin:12px 0}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #e0e7f0}th{background:#edf3fb}.badge{display:inline-block;padding:4px 9px;border-radius:5px;background:#fff0d0;color:#704b00}.note{line-height:1.5;color:#475d73}a{color:#174a7d}</style></head><body><main><p><a href="/admin/settings">← Admin Settings</a></p><h1>Data retention policy</h1><p class="note">This page documents the agreed Dulogix rota retention rules. It is informational; changing or deleting data is not possible from this page.</p><section class="card"><h2>Retention periods</h2><table><thead><tr><th>Record type</th><th>Retention</th><th>Disposal policy</th></tr></thead><tbody><tr><td>Driver allocations</td><td>12 months</td><td>Delete from live database</td></tr><tr><td>Driver availability</td><td>12 months</td><td>Delete from live database</td></tr><tr><td>Driver statuses</td><td>12 months</td><td>Delete from live database</td></tr><tr><td>Weekly Operations Planning</td><td>12 months</td><td>Delete from live database</td></tr><tr><td>Weekly Actions and completion records</td><td>12 months</td><td>Delete from live database</td></tr><tr><td>Driver training requirement completion and check/uncheck history</td><td>At least 12 months</td><td>Permanent driver-linked audit records; no automatic deletion currently configured</td></tr><tr><td>Ordinary rota audit/change-history logs</td><td>3 months</td><td>Delete when pruning task runs</td></tr><tr><td>Historical unlock/relock and correction audit (planned)</td><td>12 months</td><td>Separate retention rule to implement before activation</td></tr></tbody></table></section><section class="card"><h2>Implementation status</h2><p><span class="badge">12-month cleanup available; automatic scheduling pending</span></p><p class="note">The 12-month cleanup command is implemented locally with a safe dry-run default (app:rota:prune). Actual deletion requires --execute and is not scheduled yet. The three-month audit-log pruning command covers all rota_audit records, including driver availability edits; a GitHub Actions workflow is configured to run it daily when deployed and enabled.</p><p class="note">Before activating automatic deletion: confirm any applicable statutory retention obligations, verify backups, review the dry-run report, and approve scheduling. No archive copies are planned.</p></section></main></body></html>
HTML);
    }
}
