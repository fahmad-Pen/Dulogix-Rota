<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DriverRequirementsController extends AbstractController
{
    private function esc(?string $value): string { return htmlspecialchars($value??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    private function manager(): void { $this->denyAccessUnlessGranted('ROLE_OPERATIONS'); }
    private function admin(): void { $this->denyAccessUnlessGranted('ROLE_ADMIN'); }

    #[Route('/admin/settings/driver-requirements',name:'admin_driver_requirements',methods:['GET','POST'])]
    public function settings(Request $request,Connection $db): Response {
        $this->admin();$message='';
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('driver_requirements',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $name=trim((string)$request->request->get('name'));
            $action=(string)$request->request->get('action');
            if($name===''||mb_strlen($name)>120){$message='Enter a name between 1 and 120 characters.';}
            elseif($action==='add'){
                $db->insert('driver_requirement',['name'=>$name,'sort_order'=>(int)$db->fetchOne('SELECT COALESCE(MAX(sort_order),0)+1 FROM driver_requirement'),'created_at'=>date('Y-m-d H:i:s')]);
                return $this->redirectToRoute('admin_driver_requirements');
            }elseif($action==='rename'){
                $id=(int)$request->request->get('id');
                $db->update('driver_requirement',['name'=>$name],['id'=>$id]);
                return $this->redirectToRoute('admin_driver_requirements');
            }
        }
        $token=$this->esc($this->container->get('security.csrf.token_manager')->getToken('driver_requirements')->getValue());
        $html='<h1>Driver training & requirements</h1><p>Administrators may add or rename requirements. Requirements cannot be deleted, so existing completion history is preserved.</p>';
        if($message!=='')$html.='<p style="color:#b42318">'.$this->esc($message).'</p>';
        $html.='<div class="card"><h2>Add requirement</h2><form method="post"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="add"><input name="name" maxlength="120" required placeholder="New requirement"><button>Add</button></form></div><div class="card"><h2>Existing requirements</h2>';
        foreach($db->fetchAllAssociative('SELECT id,name FROM driver_requirement ORDER BY sort_order,id') as $row){
            $html.='<form method="post" style="margin:12px 0"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="'.(int)$row['id'].'"><input name="name" maxlength="120" required value="'.$this->esc($row['name']).'"><button>Rename</button></form>';
        }
        $html.='</div>';
        return $this->page($html);
    }

    #[Route('/admin/users/{id}/requirements',name:'driver_requirements',requirements:['id'=>'\\d+'],methods:['GET','POST'])]
    public function checklist(int $id,Request $request,Connection $db,EntityManagerInterface $em): Response {
        $this->manager();
        $driver=$em->getRepository(User::class)->find($id);
        if(!$driver||!in_array('ROLE_DRIVER',$driver->getRoles(),true))throw $this->createNotFoundException();
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('driver_checklist_'.$id,(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $req=(int)$request->request->get('requirement');
            if(!$db->fetchOne('SELECT id FROM driver_requirement WHERE id=?',[$req]))throw $this->createNotFoundException();
            $actor=$this->getUser();
            $actorId=$actor->getId();
            $existing=$db->fetchOne('SELECT requirement_id FROM driver_requirement_completion WHERE driver_id=? AND requirement_id=?',[$id,$req]);
            $now=date('Y-m-d H:i:s');
            if($existing){
                $db->delete('driver_requirement_completion',['driver_id'=>$id,'requirement_id'=>$req]);
                $action='unchecked';
            }else{
                $db->insert('driver_requirement_completion',['driver_id'=>$id,'requirement_id'=>$req,'completed_by_id'=>$actorId,'completed_at'=>$now]);
                $action='checked';
            }
            $db->insert('driver_requirement_event',['driver_id'=>$id,'requirement_id'=>$req,'actor_id'=>$actorId,'action'=>$action,'created_at'=>$now]);
            return $this->redirectToRoute('driver_requirements',['id'=>$id]);
        }
        $token=$this->esc($this->container->get('security.csrf.token_manager')->getToken('driver_checklist_'.$id)->getValue());
        $rows=$db->fetchAllAssociative('SELECT r.id,r.name,c.completed_at,u.display_name AS checked_by FROM driver_requirement r LEFT JOIN driver_requirement_completion c ON c.requirement_id=r.id AND c.driver_id=? LEFT JOIN app_user u ON u.id=c.completed_by_id ORDER BY r.sort_order,r.id',[$id]);
        $html='<h1>Training & requirements</h1><p><a href="/admin/users/edit/'.$id.'">← Back to '.$this->esc($driver->getDisplayName()).'</a></p><p>Tick each item when the driver completes it. The person and time are recorded automatically. Unticking is also audited. <strong>History retention: at least 12 months.</strong></p><div class="card"><table><thead><tr><th>Completed</th><th>Requirement</th><th>Checked by</th><th>Checked at (UTC)</th></tr></thead><tbody>';
        foreach($rows as $row){
            $checked=$row['completed_at']!==null;
            $html.='<tr><td><form method="post"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="requirement" value="'.(int)$row['id'].'"><button type="submit" title="'.($checked?'Uncheck':'Mark complete').'">'.($checked?'☑':'☐').'</button></form></td><td>'.$this->esc($row['name']).'</td><td>'.$this->esc($row['checked_by']??'—').'</td><td>'.$this->esc($row['completed_at']??'—').'</td></tr>';
        }
        $html.='</tbody></table></div><details class="card"><summary>Completion change history (retained for at least 12 months)</summary><table><thead><tr><th>When (UTC)</th><th>Requirement</th><th>Action</th><th>By</th></tr></thead><tbody>';
        foreach($db->fetchAllAssociative('SELECT e.created_at,e.action,r.name,u.display_name FROM driver_requirement_event e JOIN driver_requirement r ON r.id=e.requirement_id JOIN app_user u ON u.id=e.actor_id WHERE e.driver_id=? ORDER BY e.id DESC LIMIT 250',[$id]) as $event){
            $html.='<tr><td>'.$this->esc($event['created_at']).'</td><td>'.$this->esc($event['name']).'</td><td>'.$this->esc($event['action']).'</td><td>'.$this->esc($event['display_name']).'</td></tr>';
        }
        $html.='</tbody></table></details>';
        return $this->page($html);
    }

    private function page(string $content): Response {
        return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Driver requirements · Dulogix</title>'.SectionAccess::CSS.'<style>body{background:#f4f7fb;color:#17314e;font:15px system-ui;margin:0}main{max-width:1050px;margin:32px auto;padding:0 18px}.card{background:white;border:1px solid #dbe3ee;border-radius:9px;padding:20px;margin:15px 0}input,button{padding:10px;margin-right:8px;border:1px solid #cbd7e4;border-radius:5px}button{background:#194b76;color:white;cursor:pointer}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #dbe3ee;padding:12px;text-align:left}a{color:#194b76}</style></head><body><main><p><a href="/admin/settings">← Settings</a></p>'.$content.'</main></body></html>');
    }
}
