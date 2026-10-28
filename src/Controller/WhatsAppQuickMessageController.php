<?php
namespace App\Controller;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
final class WhatsAppQuickMessageController extends AbstractController {
 #[Route('/api/whatsapp/quick-messages', name:'whatsapp_quick_messages', methods:['GET'])]
 public function list(Connection $db): JsonResponse {
  if (!$this->getUser() || !SectionAccess::allowed($this->getUser(),'rota')) throw $this->createAccessDeniedException();
  return $this->json($db->fetchAllAssociative('SELECT id, label, message FROM whatsapp_quick_message ORDER BY sort_order ASC, id ASC'));
 }
 #[Route('/admin/whatsapp-messages', name:'admin_whatsapp_messages', methods:['GET','POST'])]
 public function manage(Request $request, Connection $db, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfManager): Response {
  $this->denyAccessUnlessGranted('ROLE_ADMIN');
  if ($request->isMethod('POST')) {
   if (!$this->isCsrfTokenValid('wa_quick_messages',(string)$request->request->get('_token'))) throw $this->createAccessDeniedException('Invalid CSRF token.');
   $action=(string)$request->request->get('action','save');
   $id=max(0,(int)$request->request->get('id',0));
   if ($action==='delete' && $id) $db->delete('whatsapp_quick_message',['id'=>$id]);
   elseif ($action==='save') {
    $label=trim((string)$request->request->get('label',''));
    $message=trim((string)$request->request->get('message',''));
    $order=(int)$request->request->get('sort_order',0);
    if ($label==='' || $message==='' || mb_strlen($label)>80 || mb_strlen($message)>2000) {
     $this->addFlash('error','Enter a button name (up to 80 characters) and message (up to 2,000 characters).');
    } elseif ($id && $db->fetchOne('SELECT id FROM whatsapp_quick_message WHERE id = ?',[$id])) {
     $db->update('whatsapp_quick_message',['label'=>$label,'message'=>$message,'sort_order'=>$order],['id'=>$id]);
    } else $db->insert('whatsapp_quick_message',['label'=>$label,'message'=>$message,'sort_order'=>$order]);
   }
   return $this->redirectToRoute('admin_whatsapp_messages');
  }
  $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $token=$e($csrfManager->getToken('wa_quick_messages')->getValue());
  $rows=$db->fetchAllAssociative('SELECT * FROM whatsapp_quick_message ORDER BY sort_order ASC,id ASC');
  $forms='';
  foreach($rows as $row){
   $forms.='<form method="post" class="item"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="id" value="'.$e($row['id']).'"><label>Button name<input name="label" maxlength="80" required value="'.$e($row['label']).'"></label><label>Message<textarea name="message" maxlength="2000" required rows="3">'.$e($row['message']).'</textarea></label><label>Order<input name="sort_order" type="number" value="'.$e($row['sort_order']).'"></label><div><button name="action" value="save">Save changes</button> <button name="action" value="delete" class="danger" formnovalidate onclick="return confirm(\'Delete this quick message?\')">Delete</button></div></form>';
  }
  $flashes='';foreach($request->getSession()->getFlashBag()->all() as $type=>$messages)foreach($messages as $message)$flashes.='<p role="alert">'.$e($message).'</p>';
  $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WhatsApp Quick Messages · Dulogix</title><link rel="icon" href="/favicon.png"><style>body{margin:0;background:#f2f6fb;color:#173b60;font:15px system-ui}header{background:#142d4e;padding:16px 24px}main{max-width:950px;margin:24px auto;padding:0 15px}h1{font-size:25px}.item{background:white;border:1px solid #d7e2ed;border-radius:10px;padding:18px;margin:12px 0;display:grid;gap:12px}label{display:grid;gap:5px;font-weight:600}input,textarea{font:inherit;padding:9px;border:1px solid #b9c9d8;border-radius:6px;width:100%;box-sizing:border-box}button{background:#173b60;color:white;border:0;border-radius:6px;padding:10px 15px;cursor:pointer}.danger{background:#a62e39}.hint{color:#5b7189}</style>'.SectionAccess::CSS.'</head><body><header>'.SectionAccess::navigation($this->getUser()).'</header><main><h1>WhatsApp Quick Messages</h1><p class="hint">Create buttons for rota managers. Clicking a button copies its message only; it does not send anything.</p>'.$flashes.'<h2>Add quick message</h2><form method="post" class="item"><input type="hidden" name="_token" value="'.$token.'"><label>Button name<input name="label" maxlength="80" required placeholder="Availability reminder"></label><label>Message<textarea name="message" maxlength="2000" rows="4" required placeholder="Enter the message managers will copy"></textarea></label><label>Display order<input name="sort_order" type="number" value="0"></label><div><button name="action" value="save">Add button</button></div></form><h2>Existing messages</h2>'.($forms?:'<p>No quick messages yet.</p>').'</main></body></html>';
  return new Response($html);
 }
}
