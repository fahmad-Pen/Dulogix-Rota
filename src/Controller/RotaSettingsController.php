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
            $code=strtoupper(trim((string)$request->request->get('code')));
            $label=trim((string)$request->request->get('label'));
            $order=filter_var($request->request->get('sort_order'),FILTER_VALIDATE_INT);
            $active=$request->request->get('active')==='1';
            if(!preg_match('/^[A-Z][A-Z0-9_-]{0,23}$/',$code)||$label===''||mb_strlen($label)>80||$order===false){$error='Invalid code, label or order.';}
            else {
                $option=$repo->find($code);
                if(!$option){$option=new RotaAllocationOption();$option->code=$code;$em->persist($option);}
                $option->label=$label;$option->sortOrder=$order;$option->active=$active;$em->flush();
                return $this->redirectToRoute('rota_settings');
            }
        }
        $options=$repo->findBy([],['sortOrder'=>'ASC','code'=>'ASC']);
        $escape=static fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
        $token=$escape($csrfManager->getToken('rota_options')->getValue());
        $rows='';
        foreach($options as $option){$rows.='<form method="post" class="row"><input type="hidden" name="_token" value="'.$token.'"><input name="code" value="'.$escape($option->code).'" readonly aria-label="Code"><input name="label" value="'.$escape($option->label).'" required aria-label="Description"><input type="number" name="sort_order" value="'.$option->sortOrder.'" aria-label="Order"><select name="active"><option value="1"'.($option->active?' selected':'').'>Active</option><option value="0"'.(!$option->active?' selected':'').'>Inactive</option></select><button>Save</button></form>';}
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rota settings · Dulogix</title><link rel="icon" href="/favicon.png"><style>body{font:16px system-ui;background:#f4f7fb;color:#17314e;margin:0}header{background:#142d4e;padding:14px 25px}header img{height:34px;background:white;border-radius:5px}main{max-width:1050px;margin:28px auto;padding:0 16px}.card{background:white;border:1px solid #dbe3ee;border-radius:10px;padding:22px;margin:15px 0}.row{display:grid;grid-template-columns:110px minmax(160px,1fr) 90px 115px 85px;gap:9px;margin:10px 0}input,select,button{padding:9px;border:1px solid #bac9dc;border-radius:6px;min-width:0}button{background:#18436c;color:white;cursor:pointer}a{color:#18436c}@media(max-width:650px){.row{grid-template-columns:1fr 1fr}.row input[name=label]{grid-column:span 2}}</style></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a></header><main><p><a href="/">← Rota</a></p><h1>Settings · Allocation types</h1><p>Only administrators can change these options. Inactive options remain in saved historical allocations.</p>'.($error?'<p role="alert">'.$escape($error).'</p>':'').'<section class="card"><h2>Current options</h2>'.$rows.'</section><section class="card"><h2>Add allocation option</h2><form class="row" method="post"><input type="hidden" name="_token" value="'.$token.'"><input name="code" maxlength="24" placeholder="Code" required><input name="label" maxlength="80" placeholder="Description" required><input type="number" name="sort_order" value="10"><select name="active"><option value="1">Active</option><option value="0">Inactive</option></select><button>Add</button></form></section></main></body></html>';
        return new Response($html);
    }
}
