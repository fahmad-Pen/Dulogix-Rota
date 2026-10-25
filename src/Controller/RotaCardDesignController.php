<?php
namespace App\Controller;
use App\Entity\AppSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
final class RotaCardDesignController extends AbstractController {
 public const DEFAULTS=['heading'=>'WEEKLY DRIVER ROTA','footer'=>'Please contact operations about any allocation changes.','header_color'=>'#142d4e','accent_color'=>'#146343','row_color'=>'#edf3fa'];
 #[Route('/admin/settings/rota-card',name:'admin_rota_card_design',methods:['GET','POST'])]
 public function edit(Request $request,EntityManagerInterface $em,CsrfTokenManagerInterface $csrf): Response {
  $this->denyAccessUnlessGranted('ROLE_ADMIN');
  $repo=$em->getRepository(AppSetting::class);$values=self::DEFAULTS;$error='';$saved=false;
  foreach($values as $key=>$default){$setting=$repo->find('rota_card_'.$key);if($setting)$values[$key]=$setting->value;}
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('rota_card_design',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
   $incoming=[];
   foreach(self::DEFAULTS as $key=>$default){
    $value=trim((string)$request->request->get($key,$default));
    if(str_ends_with($key,'_color')){$valid=(bool)preg_match('/^#[0-9a-fA-F]{6}$/',$value);}
    else {$valid=$value!==''&&mb_strlen($value)<=($key==='heading'?50:140);}
    if(!$valid){$error='Invalid '.$key.'. Please check the text length or colour format.';break;}
    $incoming[$key]=$value;
   }
   if(!$error){foreach($incoming as $key=>$value){$setting=$repo->find('rota_card_'.$key);if(!$setting){$setting=new AppSetting();$setting->key='rota_card_'.$key;$em->persist($setting);}$setting->value=$value;}$em->flush();$values=$incoming;$saved=true;}
  }
  $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $token=$esc($csrf->getToken('rota_card_design')->getValue());
  $form='<form method="post"><input type="hidden" name="_token" value="'.$token.'">';
  foreach(['heading'=>'Card heading','footer'=>'Footer message'] as $key=>$label){$form.='<label>'.$label.'<input name="'.$key.'" maxlength="'.($key==='heading'?50:140).'" value="'.$esc($values[$key]).'" required></label>';}
  foreach(['header_color'=>'Header colour','accent_color'=>'Allocation text colour','row_color'=>'Alternating row colour'] as $key=>$label){$form.='<label>'.$label.'<input type="color" name="'.$key.'" value="'.$esc($values[$key]).'"></label>';}
  $form.='<button>Save card design</button></form>';
  return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rota card design · Dulogix</title><style>body{font:16px system-ui;background:#f4f7fb;color:#17314e;margin:0}header{background:#142d4e;padding:14px}header img{height:35px;background:white;border-radius:5px}main{max-width:750px;margin:25px auto;padding:0 16px}.card{background:white;border:1px solid #dbe3ee;border-radius:12px;padding:24px}label{display:block;margin:15px 0;font-weight:600}input{display:block;margin-top:6px;padding:9px;max-width:100%;width:100%;box-sizing:border-box;border:1px solid #bdcad8;border-radius:6px}input[type=color]{width:95px;height:48px;padding:3px}button{background:#18436c;color:white;border:0;border-radius:6px;padding:12px 20px;cursor:pointer}a{color:#18436c}</style></head><body><header><a href="/admin/settings"><img src="/dulogix-logo.png" alt="Dulogix"></a></header><main><p><a href="/admin/settings">← Admin Settings</a></p><h1>Rota card design</h1><p>These settings apply to all new rota images shared from Dulogix.</p>'.($saved?'<p style="color:green">Card design saved.</p>':'').($error?'<p role="alert">'.$esc($error).'</p>':'').'<section class="card">'.$form.'</section></main></body></html>');
 }
 #[Route('/api/rota-card/design',name:'rota_card_design_public',methods:['GET'])]
 public function read(EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');$values=self::DEFAULTS;
  foreach($values as $key=>$default){$setting=$em->getRepository(AppSetting::class)->find('rota_card_'.$key);if($setting)$values[$key]=$setting->value;}
  return $this->json($values)->setPrivate()->setMaxAge(0);
 }
}
