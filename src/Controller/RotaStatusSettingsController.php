<?php
namespace App\Controller;

use App\Entity\RotaStatusOption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RotaStatusSettingsController extends AbstractController
{
    #[Route('/admin/settings/rota/status', name: 'rota_status_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repo = $em->getRepository(RotaStatusOption::class);
        $error = '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rota_status_options', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $action = (string) $request->request->get('action', 'save');
            $old = trim((string) $request->request->get('original_code', ''));
            $code = trim((string) $request->request->get('code', ''));
            $order = filter_var($request->request->get('sort_order'), FILTER_VALIDATE_INT);
            $active = $request->request->get('active') === '1';
            $option = $old !== '' ? $repo->find($old) : null;
            if ($action === 'delete') {
                if (!$option) $error = 'Status option not found.';
                else {
                    $used = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM rota_cell WHERE field_key = :field AND value = :code', ['field' => 'status', 'code' => $old]);
                    if ($used) {
                        $option->active = false;
                        $em->flush();
                        $error = 'Status is used by saved rotas and was deactivated to preserve history.';
                    } else {
                        $em->remove($option);
                        $em->flush();
                        return $this->redirectToRoute('rota_status_settings');
                    }
                }
            } elseif ($code === '' || mb_strlen($code) > 80 || $order === false) {
                $error = 'Enter a status name of 1–80 characters and a whole-number order.';
            } elseif ($old !== '' && !$option) {
                $error = 'Original status not found.';
            } elseif ($old !== $code && $repo->find($code)) {
                $error = 'That status already exists.';
            } else {
                if ($option && $old !== $code) {
                    $em->getConnection()->executeStatement('UPDATE rota_cell SET value = :new WHERE field_key = :field AND value = :old', ['new' => $code, 'field' => 'status', 'old' => $old]);
                    $em->remove($option);
                    $em->flush();
                    $option = null;
                }
                if (!$option) {
                    $option = new RotaStatusOption();
                    $option->code = $code;
                    $em->persist($option);
                }
                $option->sortOrder = $order;
                $option->active = $active;
                $em->flush();
                return $this->redirectToRoute('rota_status_settings');
            }
        }
        $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $token = $esc($csrfManager->getToken('rota_status_options')->getValue());
        $rows = '';
        foreach ($repo->findBy([], ['sortOrder' => 'ASC', 'code' => 'ASC']) as $option) {
            $code = $esc($option->code);
            $rows .= '<form class="row" method="post"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="original_code" value="'.$code.'"><input name="code" maxlength="80" value="'.$code.'" required aria-label="Status name"><input name="sort_order" type="number" value="'.$option->sortOrder.'" required aria-label="Order"><select name="active"><option value="1"'.($option->active?' selected':'').'>Active</option><option value="0"'.(!$option->active?' selected':'').'>Inactive</option></select><button name="action" value="save">Save</button><button class="delete" name="action" value="delete" formnovalidate onclick="return confirm(\'Delete this status? Used statuses will be deactivated.\')">Delete</button></form>';
        }
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rota status settings · Dulogix</title><style>body{font:13px system-ui;background:#f4f7fb;color:#17314e;margin:0}main{max-width:780px;margin:24px auto;padding:0 16px}.card{background:#fff;border:1px solid #dbe3ee;border-radius:10px;padding:18px;margin:14px 0}h1{font-size:25px}.row{display:grid;grid-template-columns:minmax(170px,1fr) 65px 95px 65px 65px;gap:7px;margin:8px 0}input,select,button{padding:8px;border:1px solid #bac9dc;border-radius:6px;min-width:0;font-size:12px}button{background:#18436c;color:white;cursor:pointer}.delete{background:#9f2735}@media(max-width:600px){.row{grid-template-columns:1fr 1fr}.row input[name=code]{grid-column:span 2}}</style></head><body><main><p><a href="/admin/settings/rota">← Allocation types</a></p><h1>Settings · Rota status options</h1><p>Manage the Status dropdown on the driver rota. Renaming a status updates saved rota cells. A status already used in saved rotas is deactivated rather than deleted.</p>'.($error?'<p role="alert">'.$esc($error).'</p>':'').'<section class="card"><h2>Current statuses</h2>'.$rows.'</section><section class="card"><h2>Add status</h2><form class="row" method="post"><input type="hidden" name="_token" value="'.$token.'"><input name="code" maxlength="80" placeholder="Status name" required><input type="number" name="sort_order" value="10" required><select name="active"><option value="1">Active</option><option value="0">Inactive</option></select><button name="action" value="save">Add</button></form></section></main></body></html>';
        return new Response($html);
    }
}
