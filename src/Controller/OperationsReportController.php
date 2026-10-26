<?php
namespace App\Controller;

use App\Entity\RotaCell;
use App\Entity\RotaAllocationOption;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class OperationsReportController extends AbstractController
{
    #[Route('/operations/report',name:'operations_report',methods:['GET','POST'])]
    public function report(Request $request,Connection $db,EntityManagerInterface $em): Response
    {
        if(!SectionAccess::allowed($this->getUser(),'operations'))throw $this->createAccessDeniedException();
        $tz=new \DateTimeZone('Europe/London');
        $day=(string)$request->query->get('date',(new \DateTimeImmutable('today',$tz))->format('Y-m-d'));
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
        if(!$date||$date->format('Y-m-d')!==$day)throw $this->createNotFoundException('Invalid date');
        $week=$date->modify('monday this week');
        $index=(int)$date->format('N')-1;
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $drivers=array_values(array_filter($em->getRepository(User::class)->findAll(),static fn(User $u)=>$u->isActive()&&in_array($u->getDriverType(),['DRIVER','JUMPER'],true)&&!in_array('ROLE_MANAGER',$u->getRoles(),true)&&!in_array('ROLE_ADMIN',$u->getRoles(),true)));
        usort($drivers,static fn(User $a,User $b)=>strnatcasecmp($a->getDisplayName(),$b->getDisplayName()));
        $vans=$db->fetchFirstColumn('SELECT registration FROM vehicle ORDER BY registration');
        $requirements=[];
        foreach($db->fetchAllAssociative('SELECT c.driver_id, r.name FROM driver_requirement_completion c JOIN driver_requirement r ON r.id=c.requirement_id WHERE LOWER(r.name) IN (:mentor,:quote)', ['mentor'=>'ementor','quote'=>'clear quote'], ['mentor'=>\Doctrine\DBAL\ParameterType::STRING,'quote'=>\Doctrine\DBAL\ParameterType::STRING]) as $item){$requirements[(int)$item['driver_id']][mb_strtolower($item['name'])]=true;}
        $saved=$db->fetchAllAssociative('SELECT driver_id,van_registration,start_time,comments FROM operations_daily WHERE report_date = :day',['day'=>$day]);
        $entries=[];foreach($saved as $item)$entries[(int)$item['driver_id']]=$item;
        $dailyChecks=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,fisco_score,returns_count FROM driver_daily_check WHERE shift_date=:day',['day'=>$day]) as $check)$dailyChecks[(int)$check['driver_id']]=$check;
        $fixed=[];foreach($db->fetchAllAssociative('SELECT driver_id,van_registration FROM operations_fixed_van') as $f)$fixed[(int)$f['driver_id']]=$f['van_registration'];
        $message='';
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $id=(int)$request->request->get('driver_id');
            $driver=null;foreach($drivers as $candidate)if($candidate->getId()===$id)$driver=$candidate;
            if(!$driver)throw $this->createNotFoundException('Active driver not found');
            $van=trim((string)$request->request->get('van'));
            $time=trim((string)$request->request->get('start_time'));
            $comments=trim((string)$request->request->get('comments'));
            $makeFixed=$request->request->get('fixed')==='1';
            $fixedChange=$request->request->get('fixed_change')==='1';
            if(($makeFixed&&$van==='')||($van!==''&&!in_array($van,$vans,true))||($time!==''&&!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$time))||mb_strlen($comments)>2000){
                $message='Please check the van, time and comments (maximum 2,000 characters).';
                if($request->isXmlHttpRequest())return new JsonResponse(['ok'=>false,'error'=>$message],422);
            }else{
                try{
                    $db->beginTransaction();
                    if($fixedChange){
                        if($makeFixed){
                            $owner=$db->fetchOne('SELECT driver_id FROM operations_fixed_van WHERE van_registration=:van AND driver_id<>:id',['van'=>$van,'id'=>$id]);
                            if($owner!==false)throw new \DomainException('This van is already fixed to another driver.');
                            $db->executeStatement('DELETE FROM operations_fixed_van WHERE driver_id=:id',['id'=>$id]);
                            $db->insert('operations_fixed_van',['driver_id'=>$id,'van_registration'=>$van]);
                        }else{$db->delete('operations_fixed_van',['driver_id'=>$id]);}
                    }
                    if($van!==''){
                        $fixedOwner=$db->fetchOne('SELECT driver_id FROM operations_fixed_van WHERE van_registration=:van AND driver_id<>:id',['van'=>$van,'id'=>$id]);
                        if($fixedOwner!==false){
                            $ownerId=(int)$fixedOwner;
                            $ownerDaily=$db->fetchAssociative('SELECT van_registration FROM operations_daily WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$ownerId]);
                            $ownerStatus=$em->getRepository(RotaCell::class)->findOneBy(['weekStart'=>$week,'dayIndex'=>$index,'driverKey'=>'driver-'.$ownerId,'fieldKey'=>'status']);
                            $ownerAllocation=$em->getRepository(RotaCell::class)->findOneBy(['weekStart'=>$week,'dayIndex'=>$index,'driverKey'=>'driver-'.$ownerId,'fieldKey'=>'allocation']);
                            $absent=mb_strtoupper(trim((string)($ownerAllocation?->value??'')))!=='IN';
                            if(!$ownerDaily&&!$absent)throw new \DomainException('This van is reserved as another driver’s fixed van. Release it for the day before assigning it.');
                        }
                    }
                    $existing=$db->fetchOne('SELECT id FROM operations_daily WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id]);
                    $values=['van_registration'=>$van?:null,'start_time'=>$time?:null,'comments'=>$comments];
                    if($existing)$db->update('operations_daily',$values,['id'=>$existing]);
                    else $db->insert('operations_daily',['report_date'=>$day,'driver_id'=>$id]+$values);
                    $db->commit();
                    if($request->isXmlHttpRequest())return new JsonResponse(['ok'=>true,'driver'=>$id,'van'=>$van,'fixed'=>$makeFixed]);
                    return $this->redirectToRoute('operations_report',['date'=>$day,'saved'=>1]);
                }catch(\DomainException $e){
                    if($db->isTransactionActive())$db->rollBack();
                    if($request->isXmlHttpRequest())return new JsonResponse(['ok'=>false,'error'=>$e->getMessage()],409);
                    $message=$e->getMessage();
                }catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e){
                    if($db->isTransactionActive())$db->rollBack();
                    $message='This van is already assigned to another driver on this date. Choose another van.';
                    if($request->isXmlHttpRequest())return new JsonResponse(['ok'=>false,'error'=>$message],409);
                }catch(\Throwable $e){if($db->isTransactionActive())$db->rollBack();throw $e;}
            }
        }
        $cells=$em->getRepository(RotaCell::class)->findBy(['weekStart'=>$week,'dayIndex'=>$index]);
        $rota=[];foreach($cells as $cell)if(in_array($cell->fieldKey,['allocation','status'],true))$rota[$cell->driverKey][$cell->fieldKey]=$cell->value;
        // Daily explicit assignments take priority. Fixed vans are defaults only for eligible drivers.
        $assigned=[];foreach($entries as $id=>$item)if($item['van_registration'])$assigned[$item['van_registration']]=$id;
        foreach($drivers as $driver){
            $id=$driver->getId();$key='driver-'.$id;
            $status=mb_strtolower(trim((string)($rota[$key]['status']??'')));
            $absent=mb_strtoupper(trim((string)($rota[$key]['allocation']??'')))!=='IN';
            if(!array_key_exists($id,$entries)&&isset($fixed[$id])&&!$absent&&in_array($fixed[$id],$vans,true)&&!isset($assigned[$fixed[$id]])){
                $entries[$id]=['van_registration'=>$fixed[$id],'start_time'=>null,'comments'=>''];
                $assigned[$fixed[$id]]=$id;
            }
        }
        $filterOptions='<option value="all">All allocations</option><option value="">Unallocated</option>';
        foreach($em->getRepository(RotaAllocationOption::class)->findBy([],['sortOrder'=>'ASC','code'=>'ASC']) as $option){if($option->active)$filterOptions.='<option value="'.$esc($option->code).'">'.$esc($option->code).' — '.$esc($option->label).'</option>';}
        $rows='';
        $token=$esc($this->container->get('security.csrf.token_manager')->getToken('operations_report')->getValue());
        foreach($drivers as $driver){
            $id=$driver->getId();$entry=$entries[$id]??[];$current=$entry['van_registration']??'';
            $isFixed=isset($fixed[$id]);
            $options='<option value="">Select van</option>';
            foreach($vans as $van){$taken=isset($assigned[$van])&&$assigned[$van]!==$id;$options.='<option value="'.$esc($van).'"'.($current===$van?' selected':'').($taken?' disabled':'').'>'.$esc($van).'</option>';}
            $key='driver-'.$id;
            $ementor=isset($requirements[$id]['ementor'])?'Yes':'No';
            $clearQuote=isset($requirements[$id]['clear quote'])?'Yes':'No';
            $rows.='<tr data-allocation="'.$esc($rota[$key]['allocation']??'').'"><td><strong>'.$esc($driver->getDisplayName()).'</strong></td><td>'.$esc(trim((string)($rota[$key]['allocation']??''))!==''?$rota[$key]['allocation']:'—').'</td><td colspan="8"><form method="post" class="entry"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="driver_id" value="'.$id.'"><span class="van-cell"><select name="van" aria-label="Van for '.$esc($driver->getDisplayName()).'">'.$options.'</select><label class="fixed-van" title="Use this van as this driver’s default on future working days"><input type="checkbox" name="fixed" value="1"'.($isFixed?' checked':'').'> Fixed</label><input type="hidden" name="fixed_change" value="0"></span><input type="time" name="start_time" aria-label="Start time" value="'.$esc($entry['start_time']??'').'"><span class="status status-pill '.(strcasecmp(trim((string)($rota[$key]['status']??'')),'sick')===0?'status-sick':'').'">'.$esc(trim((string)($rota[$key]['status']??''))!==''?$rota[$key]['status']:'—').'</span><textarea name="comments" rows="1" maxlength="2000" aria-label="Comments" placeholder="Comments">'.$esc($entry['comments']??'').'</textarea><span class="flag '.($ementor==='Yes'?'flag-yes':'flag-no').'">'.$ementor.'</span><span class="flag '.($clearQuote==='Yes'?'flag-yes':'flag-no').'">'.$clearQuote.'</span><span class="pending">'.$esc($dailyChecks[$id]['fisco_score']??'—').'</span><span class="pending">'.$esc($dailyChecks[$id]['returns_count']??'—').'</span><span class="save-state" aria-live="polite"></span></form></td></tr>';
        }
        $shareRows=[];
        foreach($drivers as $driver){
            $id=$driver->getId();
            $allocation=strtoupper(trim((string)($rota['driver-'.$id]['allocation']??'')));
            $time=substr((string)($entries[$id]['start_time']??''),0,5);
            if($allocation==='IN'&&preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$time)){
                $shareRows[]=['name'=>$driver->getDisplayName(),'time'=>$time];
            }
        }
        usort($shareRows,static fn($a,$b)=>strcmp($a['time'],$b['time'])?:strnatcasecmp($a['name'],$b['name']));
        $shareJson=json_encode(['date'=>$date->format('d/m/Y'),'drivers'=>$shareRows],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $prev=$date->modify('-1 day')->format('Y-m-d');$next=$date->modify('+1 day')->format('Y-m-d');
        $nav=SectionAccess::navigation($this->getUser());
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Operations Report · Dulogix</title><style>body{margin:0;background:#f2f6fb;color:#19344f;font:14px system-ui}header{background:#173b60;padding:12px 22px;color:white}main{max-width:1450px;margin:25px auto;padding:0 18px}.card{background:white;border:1px solid #dce5ee;border-radius:10px;padding:20px;overflow:auto}.datebar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:15px 0}a{color:#185b90}table{width:100%;border-collapse:collapse;min-width:900px}th,td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0}th{background:#eaf1f8}.entry{display:grid;grid-template-columns:160px 125px 120px minmax(200px,1fr) 65px;gap:9px;align-items:center}select,input,textarea,button{font:inherit;padding:8px;border:1px solid #b9c9d8;border-radius:6px;box-sizing:border-box}textarea{resize:vertical}button{background:#176849;color:white;cursor:pointer}.notice{background:#fff3d4;padding:12px;border-radius:6px}.status{min-width:90px}@media(max-width:950px){.entry{min-width:700px}}header{background:#142d4e;color:white;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:66px}header h1{margin:0;font-size:20px}main{max-width:1600px;padding:0 12px}main h1{font-size:21px;margin:12px 0}.card{padding:9px}.datebar{margin:9px 0;gap:10px}table{min-width:1240px}th,td{font-size:12px;padding:5px 7px}th{white-space:nowrap}.entry,.column-head{display:grid;grid-template-columns:135px 100px 80px minmax(170px,1fr) 65px 85px 75px 92px 54px;gap:5px;align-items:center}.entry select,.entry input,.entry textarea,.entry button{font-size:12px;padding:5px;min-width:0}.entry textarea{width:100%;max-width:210px;height:32px;min-height:32px}.entry button{padding:6px 5px}.flag,.pending{text-align:center;font-size:12px}.flag{font-weight:600}.column-head{font-weight:700}.column-head span{text-align:left}@media(max-width:950px){.entry{min-width:0}}.day-arrow{display:inline-grid;place-items:center;width:34px;height:34px;border:1px solid #c2d0de;border-radius:7px;background:white;color:#173b60;text-decoration:none;font-size:26px;line-height:1}.day-arrow:hover{background:#e8f0f8}.datebar input{height:34px;padding:4px 8px}.save-state{font-size:11px;color:#16734a;white-space:nowrap}.save-state.error{color:#b52c35}.entry textarea{max-width:210px}.card{overflow-x:auto}table{min-width:0;width:100%;table-layout:fixed}table th:first-child{width:155px}table th:nth-child(2){width:120px}td[colspan],th[colspan]{min-width:0}.entry,.column-head{width:100%;min-width:0;grid-template-columns:minmax(95px,1.1fr) minmax(85px,.8fr) minmax(55px,.6fr) minmax(170px,1.45fr) minmax(55px,.6fr) minmax(75px,.75fr) minmax(65px,.7fr) minmax(85px,.8fr) 52px}.entry select,.entry input,.entry textarea{width:100%}@media(max-width:1250px){table{min-width:1120px}}.print-date{display:none}.flag{display:inline-block;justify-self:center;border-radius:999px;padding:4px 9px;font-weight:700}.entry .status{min-width:0;max-width:100%;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.status-pill{display:inline-block;justify-self:start;align-self:center;width:max-content;border-radius:999px;padding:4px 9px;font-weight:700}.status-pill:empty{display:inline-block}.status-sick{color:#b91c1c;background:#fee2e2}.flag-yes{color:#166534;background:#dcfce7}.flag-no{color:#b91c1c;background:#fee2e2}.filter-label{font-weight:600}.datebar select{max-width:200px;padding:7px}.print-button{padding:8px 12px;background:#173b60}.entry .save-state{overflow:hidden;text-overflow:ellipsis}.van-cell{display:flex;align-items:center;gap:4px;min-width:0}.van-cell select{flex:1;min-width:0}.fixed-van{font-size:10px;white-space:nowrap;display:flex;align-items:center;gap:2px}.fixed-van input{width:12px;height:12px;margin:0}.entry .van-cell{width:100%}table th:nth-child(2),table td:nth-child(2){text-align:center}.column-head span{text-align:center}.entry>select,.entry>input[type=time],.entry>textarea{justify-self:center}.entry .status-pill{justify-self:center}.entry .save-state{text-align:center}@media print{@page{size:A4 portrait;margin:9mm}html,body{background:white!important;color:black!important}header,.datebar,.save-state,main>p{display:none!important}.fixed-van{display:none!important}.van-cell{display:block!important}main{margin:0!important;padding:0!important;max-width:none!important}main h1{font-size:15px;margin:0 0 3px}.print-date{display:block;font-size:10px;margin-bottom:7px}.card{border:0;padding:0;overflow:visible}table{width:100%!important;min-width:0!important;table-layout:fixed}th,td{font-size:7px!important;padding:3px 2px!important;overflow-wrap:anywhere}table th:first-child{width:15%!important}table th:nth-child(2){width:9%!important}.entry,.column-head{grid-template-columns:14% 11% 8% 22% 8% 10% 10% 12% 0;gap:1px} .entry select,.entry input,.entry textarea{font-size:7px!important;border:0!important;background:transparent!important;appearance:none!important;padding:0!important;height:auto!important;resize:none!important;color:black!important}.entry .save-state{display:none!important}.entry input[type=time]::-webkit-calendar-picker-indicator{display:none!important;-webkit-appearance:none!important}.entry input[type=time]::-webkit-inner-spin-button{display:none!important}.entry input[type=time]{pointer-events:none!important;width:100%!important;text-align:center!important}.flag{font-size:8px;padding:2px 5px}tr{break-inside:avoid}thead{display:table-header-group}tr[hidden]{display:none!important}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}} </style>'.SectionAccess::CSS.'</head><body><header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" style="height:39px;width:auto;vertical-align:middle;background:white;border-radius:5px;padding:3px"></a></h1>'.$nav.'</header><main><h1>Operations Report</h1><div class="print-date">'.$esc($date->format('l, d F Y')).'</div><div class="datebar"><a class="day-arrow" href="?date='.$prev.'" title="Previous day" aria-label="Previous day">‹</a><input id="report-date" type="date" value="'.$day.'" aria-label="Report date"><a class="day-arrow" href="?date='.$next.'" title="Next day" aria-label="Next day">›</a><strong>'.$esc($date->format('l, d F Y')).'</strong><label class="filter-label" for="allocation-filter">Allocation</label><select id="allocation-filter">'.$filterOptions.'</select><button type="button" id="print-report" class="print-button">Print / PDF (A4)</button><button type="button" id="share-driver-times" class="print-button" style="background:#087d53">Share Driver Times</button><span id="share-feedback" role="status" aria-live="polite"></span></div>'.($message?'<p class="notice">'.$esc($message).'</p>':'').($request->query->has('saved')?'<p>Saved successfully.</p>':'').'<div class="card"><table><thead><tr><th>Driver</th><th>Allocation</th><th colspan="8"><div class="column-head"><span>Van</span><span>Start time</span><span>Status</span><span>Comments</span><span>Ementor</span><span>Clear Quote</span><span>Fiscoscore</span><span>No. of returns</span><span></span></div></th></tr></thead><tbody>'.$rows.'</tbody></table></div><p>Allocation and status are read from the rota. Van, start time and comments are saved for the selected day.</p></main><script id="share-data" type="application/json">'.$shareJson.'</script><script src="/operations-report.js?v=8"></script><script src="/operations-share.js?v=2"></script></body></html>';
        return new Response($html);
    }
}
