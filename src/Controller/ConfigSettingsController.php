<?php
namespace App\Controller;
use App\Entity\AppSetting;
use App\Entity\DropdownOption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
final class ConfigSettingsController extends AbstractController {
 #[Route('/admin/settings/options',name:'admin_dropdown_options',methods:['GET','POST'])]
 public function options(Request $request,EntityManagerInterface $em,CsrfTokenManagerInterface $csrf): Response {
  $this->denyAccessUnlessGranted('ROLE_ADMIN');
  $error='';$repo=$em->getRepository(DropdownOption::class);
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('config_options',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
   $list=strtolower(trim((string)$request->request->get('list_key')));
   $code=strtoupper(trim((string)$request->request->get('code')));
   $label=trim((string)$request->request->get('label'));
   $order=filter_var($request->request->get('sort_order'),FILTER_VALIDATE_INT);
   if(!preg_match('/^[a-z][a-z0-9_]{1,79}$/',$list)||!preg_match('/^[A-Z][A-Z0-9_-]{0,23}$/',$code)||$label===''||mb_strlen($label)>100||$order===false){$error='Invalid list, code, label or order.';}
   else{
    $option=$repo->findOneBy(['listKey'=>$list,'code'=>$code]);
    if(!$option){$option=new DropdownOption();$option->listKey=$list;$option->code=$code;$em->persist($option);}
    $option->label=$label;$option->sortOrder=$order;$option->active=$request->request->get('active')==='1';$em->flush();
    return $this->redirectToRoute('admin_dropdown_options');
   }
  }
  $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $token=$esc($csrf->getToken('config_options')->getValue());
  $rows='';
  foreach($repo->findBy([],['listKey'=>'ASC','sortOrder'=>'ASC','code'=>'ASC']) as $o){
   $rows.='<form method="post" class="row"><input type="hidden" name="_token" value="'.$token.'"><input name="list_key" value="'.$esc($o->listKey).'" readonly><input name="code" value="'.$esc($o->code).'" readonly><input name="label" value="'.$esc($o->label).'" required><input name="sort_order" type="number" value="'.$o->sortOrder.'"><select name="active"><option value="1"'.($o->active?' selected':'').'>Active</option><option value="0"'.(!$o->active?' selected':'').'>Inactive</option></select><button>Save</button></form>';
  }
  $form='<form method="post" class="row"><input type="hidden" name="_token" value="'.$token.'"><input name="list_key" placeholder="List key, e.g. vehicle_type" required><input name="code" placeholder="Code" required><input name="label" placeholder="Display label" required><input name="sort_order" type="number" value="10"><select name="active"><option value="1">Active</option><option value="0">Inactive</option></select><button>Add</button></form>';
  return new Response($this->page('Central dropdown options','Reusable option lists for upcoming Dulogix modules. Existing rota allocation options remain in their own editor.',($error?'<p role="alert">'.$esc($error).'</p>':'').'<section class="card"><h2>Configured options</h2>'.($rows?:'<p>No generic options configured yet.</p>').'</section><section class="card"><h2>Add option</h2>'.$form.'</section>'));
 }
 #[Route('/admin/settings/whatsapp',name:'admin_whatsapp_settings',methods:['GET','POST'])]
 public function whatsapp(Request $request,EntityManagerInterface $em,CsrfTokenManagerInterface $csrf): Response {
  $this->denyAccessUnlessGranted('ROLE_ADMIN');$repo=$em->getRepository(AppSetting::class);
  $setting=$repo->find('whatsapp_business_number');$error='';$saved=false;
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('wa_settings',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
   $number=trim((string)$request->request->get('number'));
   if($number!==''&&!preg_match('/^\+[1-9][0-9]{7,14}$/',$number))$error='Use international format, e.g. +447...';
   else {if(!$setting){$setting=new AppSetting();$setting->key='whatsapp_business_number';$em->persist($setting);}$setting->value=$number;$em->flush();$saved=true;}
  }
  $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $token=$esc($csrf->getToken('wa_settings')->getValue());
  $body=($saved?'<p style="color:green">Saved. Messaging is not activated.</p>':'').($error?'<p role="alert">'.$esc($error).'</p>':'').'<section class="card"><h2>Business sending number</h2><form method="post"><input type="hidden" name="_token" value="'.$token.'"><label>WhatsApp Business number <input name="number" placeholder="+447..." value="'.$esc($setting?->value??'').'" autocomplete="off"></label><p><button>Save number</button></p></form><p>Saving a number does not connect WhatsApp or enable automatic sending. Verified Meta credentials, approved templates and delivery status handling are still required.</p></section>';
  return new Response($this->page('WhatsApp Business settings','Configure the sending number once it is confirmed.',$body));
 }
 private function page(string $title,string $intro,string $body): string {
  return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title,ENT_QUOTES).' · Dulogix</title><style>body{font:15px system-ui;margin:0;background:#f4f7fb;color:#17314e}header{background:#142d4e;padding:14px}header img{height:35px;background:white;border-radius:5px}main{max-width:1100px;margin:25px auto;padding:0 15px}.card{background:white;padding:20px;margin:14px 0;border-radius:10px;border:1px solid #dce4ed}.row{display:grid;grid-template-columns:1.3fr 1fr 2fr 90px 110px 75px;gap:7px;margin:10px 0}input,select,button{padding:9px;min-width:0;border:1px solid #bdcad8;border-radius:5px}button{background:#18436c;color:white}a{color:#18436c}@media(max-width:850px){.row{grid-template-columns:1fr 1fr}.row input[name=label]{grid-column:span 2}}</style></head><body><header><a href="/admin/settings"><img src="/dulogix-logo.png" alt="Dulogix"></a></header><main><p><a href="/admin/settings">← Admin Settings</a></p><h1>'.htmlspecialchars($title,ENT_QUOTES).'</h1><p>'.htmlspecialchars($intro,ENT_QUOTES).'</p>'.$body.'</main></body></html>';
 }
}
