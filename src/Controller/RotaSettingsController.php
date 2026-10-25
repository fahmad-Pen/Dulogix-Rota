<?php
namespace App\Controller;
use App\Entity\RotaAllocationOption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class RotaSettingsController extends AbstractController
{
    #[Route('/admin/settings/rota',name:'rota_settings',methods:['GET','POST'])]
    public function index(Request $request,EntityManagerInterface $em, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repo=$em->getRepository(RotaAllocationOption::class);
        $error='';
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('rota_options',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $action=(string)$request->request->get('action','save');
            $oldCode=strtoupper(trim((string)$request->request->get('original_code','')));
            $code=strtoupper(trim((string)$request->request->get('code','')));
            $label=trim((string)$request->request->get('label',''));
            $order=filter_var($request->request->get('sort_order'),FILTER_VALIDATE_INT);
            $active=$request->request->get('active')==='1';
            if($action==='delete'){
                $option=$repo->find($oldCode);
                if(!$option){$error='Allocation option not found.';}
                else {
                    $used=(int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM rota_cell WHERE field_key = :field AND value = :code',['field'=>'allocation','code'=>$oldCode]);
                    if($used>0){$option->active=false;$em->flush();$error='This option is used by saved rotas, so it was deactivated rather than deleted to preserve history.';}
                    else {$em->remove($option);$em->flush();return $this->redirectToRoute('rota_settings');}
                }
            } elseif(!preg_match('/^[A-Z][A-Z0-9_-]{0,23}$/',$code)||mb_strlen($label)>80||$order===false){
                $error='Code must begin with a letter and contain at most 24 letters, numbers, underscores or hyphens; order must be a whole number.';
            } else {
                $option=$oldCode!==''?$repo->find($oldCode):null;
                if($oldCode!==''&&!$option){$error='Original allocation option not found.';}
                elseif($oldCode!==$code&&$repo->find($code)){$error='That allocation code is already in use.';}
                else {
                    if($option&&$oldCode!==$code){
                        $em->getConnection()->executeStatement('UPDATE rota_cell SET value = :new WHERE field_key = :field AND value = :old',['new'=>$code,'field'=>'allocation','old'=>$oldCode]);
                        $em->remove($option);$em->flush();$option=null;
                    }
                    if(!$option){$option=new RotaAllocationOption();$option->code=$code;$em->persist($option);}
                    $option->label=$label;$option->sortOrder=$order;$option->active=$active;$em->flush();
                    return $this->redirectToRoute('rota_settings');
                }
            }
        }
        $options=$repo->findBy([],['sortOrder'=>'ASC','code'=>'ASC']);
        $escape=static fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
        $token=$escape($csrfManager->getToken('rota_options')->getValue());
        $rows='';
        foreach($options as $option){
            $code=$escape($option->code);
            $rows.='<form method="post" class="row"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="original_code" value="'.$code.'"><input name="code" value="'.$code.'" maxlength="24" required aria-label="Code"><input name="label" value="'.$escape($option->label).'" maxlength="80" aria-label="Description (optional)"><input type="number" name="sort_order" value="'.$option->sortOrder.'" required aria-label="Order"><select name="active"><option value="1"'.($option->active?' selected':'').'>Active</option><option value="0"'.(!$option->active?' selected':'').'>Inactive</option></select><button name="action" value="save">Save</button><button class="delete" name="action" value="delete" formnovalidate onclick="return confirm(\'Delete this option? If used by saved rotas it will be deactivated instead.\')">Delete</button></form>';
        }
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rota settings · Dulogix</title><link rel="icon" href="/favicon.png"><style>body{font:16px system-ui;background:#f4f7fb;color:#17314e;margin:0}header{background:#142d4e;padding:14px 25px}header img{height:34px;background:white;border-radius:5px}main{max-width:1050px;margin:28px auto;padding:0 16px}.card{background:white;border:1px solid #dbe3ee;border-radius:10px;padding:22px;margin:15px 0}.row{display:grid;grid-template-columns:110px minmax(160px,1fr) 90px 115px 85px 85px;gap:9px;margin:10px 0}input,select,button{padding:9px;border:1px solid #bac9dc;border-radius:6px;min-width:0}button{background:#18436c;color:white;cursor:pointer}.delete{background:#9f2735}a{color:#18436c}@media(max-width:650px){.row{grid-template-columns:1fr 1fr}.row input[name=label]{grid-column:span 2}}</style></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a></header><main><p><a href="/">← Rota</a></p><h1>Settings · Allocation types</h1><p>Only administrators can change these options. Description is optional. Changing a code updates saved allocations; deleting an option used by rotas deactivates it to preserve history.</p>'.($error?'<p role="alert">'.$escape($error).'</p>':'').'<section class="card"><h2>Current options</h2>'.$rows.'</section><section class="card"><h2>Add allocation option</h2><form class="row" method="post"><input type="hidden" name="_token" value="'.$token.'"><input name="code" maxlength="24" placeholder="Code" required><input name="label" maxlength="80" placeholder="Description (optional)"><input type="number" name="sort_order" value="10"><select name="active"><option value="1">Active</option><option value="0">Inactive</option></select><button name="action" value="save">Add</button></form></section></main></body></html>';
        return new Response($html);
    }
}
