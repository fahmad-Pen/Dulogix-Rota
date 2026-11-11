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
        $operationsFull=SectionAccess::canEdit($this->getUser(),'operations');
        // Operations read + Performance full grants FICO-only editing, not other Operations writes.
        $ficoOnly=!$operationsFull && SectionAccess::performanceLevel($this->getUser())==='full';
        $tz=new \DateTimeZone('Europe/London');
        $day=(string)$request->query->get('date',(new \DateTimeImmutable('today',$tz))->format('Y-m-d'));
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
        if(!$date||$date->format('Y-m-d')!==$day)throw $this->createNotFoundException('Invalid date');
        $week=$date->modify('monday this week');
        $index=(int)$date->format('N')-1;
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $drivers=array_values(array_filter($em->getRepository(User::class)->findAll(),static fn(User $u)=>$u->isActive()&&in_array($u->getDriverType(),['DRIVER','JUMPER'],true)&&!in_array('ROLE_MANAGER',$u->getRoles(),true)&&!in_array('ROLE_ADMIN',$u->getRoles(),true)));
        usort($drivers,static fn(User $a,User $b)=>strnatcasecmp($a->getDisplayName(),$b->getDisplayName()));
        $vehicleTypes=[];foreach($db->fetchAllAssociative('SELECT registration,vehicle_type FROM vehicle') as $vehicle){$vehicleTypes[(string)$vehicle['registration']]=(string)$vehicle['vehicle_type'];}
        $vans=$db->fetchFirstColumn('SELECT registration FROM vehicle WHERE grounded = 0 ORDER BY registration');
        $saved=$db->fetchAllAssociative('SELECT driver_id,van_registration,start_time,comments FROM operations_daily WHERE report_date = :day',['day'=>$day]);
        $entries=[];foreach($saved as $item)$entries[(int)$item['driver_id']]=$item;
        $dailyChecks=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,ementor,clear_quote,fisco_score,returns_count,pre_updated_at,post_updated_at FROM driver_daily_check WHERE shift_date=:day',['day'=>$day]) as $check)$dailyChecks[(int)$check['driver_id']]=$check;
        $laneRows=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,color,holding_time,stg_location,route FROM operations_lane_plan WHERE report_date=:day',['day'=>$day]) as $lane)$laneRows[(int)$lane['driver_id']]=$lane;
        $scoreOverrides=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,fisco_score,returns_count,updated_at FROM operations_daily_score_override WHERE report_date=:day',['day'=>$day]) as $override)$scoreOverrides[(int)$override['driver_id']]=$override;
        $fixed=[];foreach($db->fetchAllAssociative('SELECT driver_id,van_registration FROM operations_fixed_van') as $f)$fixed[(int)$f['driver_id']]=$f['van_registration'];
        $message='';
        if($request->isMethod('POST') && $request->request->get('action')==='lane_upload'){
            if(!$operationsFull)throw $this->createAccessDeniedException();
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $file=$request->files->get('lane_file');
            if(!$file || !$file->isValid() || $file->getSize()>15*1024*1024 || strtolower($file->getClientOriginalExtension())!=='xlsx')
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Select an .xlsx lane plan under 15 MB.');
            try {
                $records=(new LanePlanReader())->read($file->getPathname());
                $matching=[];$unmatched=[];
                foreach($records as $transporterId=>$record){
                    $driver=$em->getRepository(User::class)->findOneBy(['transporterId'=>$transporterId]);
                    if(!$driver || !$driver->isActive() || !in_array($driver->getDriverType(),['DRIVER','JUMPER'],true)){
                        $unmatched[]=$transporterId;continue;
                    }
                    $matching[$driver->getId()]=$record;
                }
                if(!$matching)throw new \InvalidArgumentException('No matching active driver Transporter IDs found.');
                $db->beginTransaction();
                try {
                    $db->delete('operations_lane_plan',['report_date'=>$day]);
                    foreach($matching as $driverId=>$record)$db->insert('operations_lane_plan',['report_date'=>$day,'driver_id'=>$driverId]+$record+['uploaded_by'=>$this->getUser()->getId(),'uploaded_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')]);
                    $db->commit();
                }catch(\Throwable $e){$db->rollBack();throw $e;}
                $this->addFlash('lane_success',count($matching).' drivers matched; '.count($unmatched).' unmatched IDs. '.($unmatched?'Unmatched: '.implode(', ',$unmatched):''));
            }catch(\InvalidArgumentException $e){$this->addFlash('lane_error',$e->getMessage());}
            return $this->redirectToRoute('operations_report',['date'=>$day]);
        }
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $id=(int)$request->request->get('driver_id');
            $driver=null;foreach($drivers as $candidate)if($candidate->getId()===$id)$driver=$candidate;
            if(!$driver)throw $this->createNotFoundException('Active driver not found');
            if($request->request->get('action')==='score_override'){
                if (!$operationsFull && !$ficoOnly) throw $this->createAccessDeniedException();
                if ($ficoOnly && $request->request->has('returns_count')) throw $this->createAccessDeniedException();
                $submitted=$db->fetchOne('SELECT fisco_score FROM driver_daily_check WHERE shift_date=:day AND driver_id=:id AND fisco_score IS NOT NULL',['day'=>$day,'id'=>$id]);
                $score=trim((string)$request->request->get('fisco_score',''));
                $returns=trim((string)$request->request->get('returns_count',''));
                if(($score!==''&&(!preg_match('/^\d{1,3}$/D',$score)||(int)$score>850))||($returns!==''&&!preg_match('/^\d{1,3}$/D',$returns)))
                    return new JsonResponse(['ok'=>false,'error'=>'FICO Score must be 0?850 and returns 0?999.'],422);
                $values=['fisco_score'=>$score===''?null:(int)$score,'updated_by'=>$this->getUser()->getId(),'updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
                if ($operationsFull) $values['returns_count']=$returns===''?null:(int)$returns;
                $old=$db->fetchAssociative('SELECT fisco_score,returns_count FROM operations_daily_score_override WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id]);
                if ($ficoOnly && !$old) $values['returns_count']=null;
                $driverSubmission=$db->fetchAssociative('SELECT fisco_score,post_updated_at FROM driver_daily_check WHERE shift_date=:day AND driver_id=:id AND fisco_score IS NOT NULL',['day'=>$day,'id'=>$id]);
                $previousScore=$driverSubmission && (!$old || $driverSubmission['post_updated_at']>$db->fetchOne('SELECT updated_at FROM operations_daily_score_override WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id])) ? (int)$driverSubmission['fisco_score'] : ($old && $old['fisco_score']!==null ? (int)$old['fisco_score'] : ($submitted!==false ? (int)$submitted : null));
                $db->beginTransaction();
                try {
                    if($old!==false)$db->update('operations_daily_score_override',$values,['report_date'=>$day,'driver_id'=>$id]);
                    else $db->insert('operations_daily_score_override',['report_date'=>$day,'driver_id'=>$id]+$values);
                    if ($previousScore !== ($score===''?null:(int)$score)) {
                        $db->insert('operations_fico_audit',[
                            'driver_id'=>$id,'report_date'=>$day,'old_score'=>$previousScore,
                            'new_score'=>$score===''?null:(int)$score,'actor_id'=>$this->getUser()->getId(),
                            'created_at'=>$values['updated_at']
                        ]);
                    }
                    $db->commit();
                } catch (\Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
                return new JsonResponse(['ok'=>true,'saved_at'=>$values['updated_at']]);
            }
            if (!$operationsFull) throw $this->createAccessDeniedException();
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
        $statusCodes = array_map(static fn($o) => $o->code, $em->getRepository(\App\Entity\RotaStatusOption::class)->findBy(['active'=>true], ['sortOrder'=>'ASC']));
        $rows='';
        $token=$esc($this->container->get('security.csrf.token_manager')->getToken('operations_report')->getValue());
        foreach($drivers as $rowIndex=>$driver){
            $id=$driver->getId();$entry=$entries[$id]??[];$current=$entry['van_registration']??'';
            $isFixed=isset($fixed[$id]);
            $options='<option value="">Select</option>';
            if($current!=='')$options.='<option value="'.$esc($current).'" selected>'.$esc(substr((string)$current,-3)).'</option>';
            $key='driver-'.$id;
            $ementor=!empty($dailyChecks[$id]['ementor'])?'Yes':'No';
            $clearQuote=!empty($dailyChecks[$id]['clear_quote'])?'Yes':'No';
            $preTime=!empty($dailyChecks[$id]['pre_updated_at'])?(new \DateTimeImmutable($dailyChecks[$id]['pre_updated_at'],$tz))->format('d/m/Y H:i').' UK':'Not submitted';
            $preStamp=$preTime==='Not submitted'?'Not submitted':'<span class="stamp-date">'.$esc(substr($preTime,0,10)).'</span><span class="stamp-time">'.$esc(substr($preTime,11)).'</span>';
            $ementorMarkup='<span class="check-audit"><span class="flag '.($ementor==='Yes'?'flag-yes':'flag-no').'">'.$ementor.'</span><small class="stamp">'.$preStamp.'</small></span>';
            $clearQuoteMarkup='<span class="check-audit"><span class="flag '.($clearQuote==='Yes'?'flag-yes':'flag-no').'">'.$clearQuote.'</span><small class="stamp">'.$preStamp.'</small></span>';
            $original=$dailyChecks[$id]??[];
            $override=$scoreOverrides[$id]??[];
            $driverLater=!empty($original['post_updated_at']) && (!isset($override['updated_at']) || $original['post_updated_at']>$override['updated_at']);
            $scoreValue=$driverLater?($original['fisco_score']??null):(array_key_exists('fisco_score',$override)?$override['fisco_score']:($original['fisco_score']??null));
            $returnsValue=array_key_exists('returns_count',$override)?$override['returns_count']:($original['returns_count']??null);
            $submittedAt=!empty($original['post_updated_at'])?(new \DateTimeImmutable($original['post_updated_at'], $tz))->format('d/m/Y H:i').' UK':'Not submitted';
            $postStamp=$submittedAt==='Not submitted'?'Not submitted':'<span class="stamp-date">'.$esc(substr($submittedAt,0,10)).'</span><span class="stamp-time">'.$esc(substr($submittedAt,11)).'</span>';
            $originalNote='Driver: '.$esc($original['fisco_score']??'?').' / '.$esc($original['returns_count']??'?').' ? '.$esc($submittedAt);
            $scoreMarkup='<span class="score-edit"><input type="number" name="fisco_score" min="0" max="850" step="1" aria-label="Manager FICO Score" value="'.$esc($scoreValue??'').'"><small>Driver: '.$esc($original['fisco_score']??'?').'</small><small class="stamp">'.$postStamp.'</small></span>';
            $returnsMarkup='<span class="score-edit"><input type="number" name="returns_count" min="0" max="999" step="1" aria-label="Manager No. of Returns" value="'.$esc($returnsValue??'').'"><small>Driver: '.$esc($original['returns_count']??'?').'</small><small class="stamp">'.$postStamp.'</small></span>';
            if ($ficoOnly) {
                $returnsMarkup='<span class="score-edit"><strong>'.$esc($returnsValue??'—').'</strong><small>Driver: '.$esc($original['returns_count']??'?').'</small></span>';
            }
            $lane=$laneRows[$id]??[];
            $laneMarkup='';
            foreach(['color','holding_time','stg_location','route'] as $field) {
                $value=trim((string)($lane[$field]??''));
                $colorName=strtolower($value);
                $colorClass=$field==='color' && in_array($colorName,['red','yellow','green','blue','orange','purple','pink','white','black','grey','gray'],true) ? ' lane-color lane-color-'.$colorName : '';
                $laneMarkup.='<span class="lane-value'.$colorClass.'">'.($value!==''?$esc($value):'—').'</span>';
            }
            $rows.='<tr data-allocation="'.$esc($rota[$key]['allocation']??'').'"><td><span class="row-number">'.($rowIndex+1).'.</span> <strong>'.$esc($driver->getDisplayName()).'</strong></td><td>'.$esc(trim((string)($rota[$key]['allocation']??''))!==''?$rota[$key]['allocation']:'—').'</td><td colspan="12"><form method="post" class="entry"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="driver_id" value="'.$id.'"><span class="van-cell"><span class="van-controls"><select name="van" aria-label="Van for '.$esc($driver->getDisplayName()).'">'.$options.'</select><label class="fixed-van" title="Use this van as this driver’s default on future working days"><input type="checkbox" name="fixed" value="1"'.($isFixed?' checked':'').'> Fixed</label><input type="hidden" name="fixed_change" value="0"></span><span class="van-fuel" data-type="'.$esc($vehicleTypes[$current]??'').'">'.$esc($vehicleTypes[$current]??'').'</span></span><input type="time" name="start_time" aria-label="Start time" value="'.$esc($entry['start_time']??'').'">'.$laneMarkup.'<span class="status status-pill '.(strcasecmp(trim((string)($rota[$key]['status']??'')),'sick')===0?'status-sick':'').'">'.$esc(trim((string)($rota[$key]['status']??''))!==''?$rota[$key]['status']:'—').'</span><textarea name="comments" rows="3" maxlength="2000" aria-label="Comments" placeholder="Comments">'.$esc($entry['comments']??'').'</textarea>' .$ementorMarkup.$clearQuoteMarkup.$scoreMarkup.$returnsMarkup.'<span class="save-state" aria-live="polite"></span></form></td></tr>';
        }
        if (!$operationsFull) {
            // Read-only operational fields for Performance Managers with Operations access.
            $rows=preg_replace('/(<form method="post" class="entry">.*?)(<span class="score-edit"><input type="number" name="fisco_score")/s', '$1$2', $rows);
        }
        $laneUpload='';
        if($operationsFull)$laneUpload='<form method="post" enctype="multipart/form-data" class="lane-upload"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="lane_upload"><label><strong>Upload LANE PLAN (.xlsx)</strong> <input type="file" name="lane_file" accept=".xlsx" required></label><button type="submit">Import for '.$esc($day).'</button></form>';
        foreach($request->getSession()->getFlashBag()->get('lane_success') as $notice)$laneUpload.='<p class="notice">'.$esc($notice).'</p>';
        foreach($request->getSession()->getFlashBag()->get('lane_error') as $notice)$laneUpload.='<p class="notice">'.$esc($notice).'</p>';
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
        $html='<!doctype html><html lang="en"><head><link rel="icon" type="image/png" href="/favicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Operations Report · Dulogix</title><style>body{margin:0;background:#f2f6fb;color:#19344f;font:14px system-ui}header{background:#173b60;padding:12px 22px;color:white}main{max-width:1450px;margin:25px auto;padding:0 18px}.card{background:white;border:1px solid #dce5ee;border-radius:10px;padding:20px;overflow:auto}.datebar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:15px 0}a{color:#185b90}table{width:100%;border-collapse:collapse;min-width:900px}th,td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0}th{background:#eaf1f8}.entry{display:grid;grid-template-columns:125px 110px 100px minmax(160px,1fr) 65px;gap:9px;align-items:center}select,input,textarea,button{font:inherit;padding:8px;border:1px solid #b9c9d8;border-radius:6px;box-sizing:border-box}textarea{resize:vertical}button{background:#176849;color:white;cursor:pointer}.notice{background:#fff3d4;padding:12px;border-radius:6px}.status{min-width:90px}@media(max-width:950px){.entry{min-width:700px}}header{background:#142d4e;color:white;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:66px}header h1{margin:0;font-size:20px}main{max-width:1600px;padding:0 12px}main h1{font-size:21px;margin:12px 0}.card{padding:9px}.datebar{margin:9px 0;gap:10px}table{min-width:1240px}th,td{font-size:12px;padding:5px 7px}th{white-space:nowrap}.entry,.column-head{display:grid;grid-template-columns:135px 100px 80px minmax(170px,1fr) 65px 85px 75px 92px 54px;gap:5px;align-items:center}.entry select,.entry input,.entry textarea,.entry button{font-size:12px;padding:5px;min-width:0}.entry textarea[name="comments"]{width:145px;max-width:100%;height:68px;min-height:68px;max-height:68px;line-height:18px;font-size:13px;overflow-y:auto;overflow-x:hidden;resize:none}.entry button{padding:6px 5px}.flag,.pending{text-align:center;font-size:12px}.flag{font-weight:600}.column-head{font-weight:700}.column-head span{text-align:left}.column-head span:nth-child(7),.column-head span:nth-child(8){text-align:center}@media(max-width:950px){.entry{min-width:0}}.day-arrow{display:inline-grid;place-items:center;width:34px;height:34px;border:1px solid #c2d0de;border-radius:7px;background:white;color:#173b60;text-decoration:none;font-size:26px;line-height:1}.day-arrow:hover{background:#e8f0f8}.datebar input{height:34px;padding:4px 8px}.save-state{font-size:11px;color:#16734a;white-space:nowrap}.save-state.error{color:#b52c35}.entry textarea{max-width:210px}.card{overflow-x:auto}table{min-width:0;width:100%;table-layout:fixed}table th:first-child{width:155px}table th:nth-child(2){width:120px}td[colspan],th[colspan]{min-width:0}.entry,.column-head{width:100%;min-width:0;grid-template-columns:minmax(95px,1.1fr) minmax(85px,.8fr) minmax(42px,.45fr) minmax(170px,1.6fr) minmax(55px,.6fr) minmax(75px,.75fr) minmax(65px,.7fr) minmax(85px,.8fr) 52px}.entry select,.entry input,.entry textarea{width:100%}@media(max-width:1250px){table{min-width:1120px}}.print-date{display:none}.flag{display:inline-block;justify-self:center;border-radius:999px;padding:4px 9px;font-weight:700}.entry .status{min-width:0;max-width:100%;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.status-pill{display:inline-block;justify-self:start;align-self:center;width:max-content;border-radius:999px;padding:4px 9px;font-weight:700}.status-pill:empty{display:inline-block}.status-sick{color:#b91c1c;background:#fee2e2}.flag-yes{color:#166534;background:#dcfce7}.flag-no{color:#b91c1c;background:#fee2e2}.row-number{display:inline-block;min-width:23px;color:#61758a;font-weight:700}tbody tr:nth-child(even){background:#edf4fb}tbody tr:nth-child(odd){background:#fff}.filter-label{font-weight:600}.datebar select{max-width:200px;padding:7px}.print-button{padding:8px 12px;background:#173b60}.entry .save-state{overflow:hidden;text-overflow:ellipsis}.van-cell{display:flex;align-items:center;gap:4px;min-width:0}.van-cell select{flex:1;min-width:0}.fixed-van{font-size:10px;white-space:nowrap;display:flex;align-items:center;gap:2px}.fixed-van input{width:12px;height:12px;margin:0}.entry .van-cell{width:100%}table th:nth-child(2),table td:nth-child(2){text-align:center}.column-head span{text-align:center}.entry>select,.entry>input[type=time],.entry>textarea{justify-self:center}.entry .status-pill{justify-self:center}.entry .save-state{text-align:center}@media print{@page{size:A4 landscape;margin:10mm}html,body{background:white!important;color:black!important}header,.datebar,.save-state,main>p{display:none!important}.fixed-van{display:none!important}.van-cell{display:block!important}main{margin:0!important;padding:0!important;max-width:none!important}main h1{font-size:19px;margin:0 0 5px}.print-date{display:block;font-size:12px;margin-bottom:9px}.card{border:0;padding:0;overflow:visible}table{width:100%!important;min-width:0!important;table-layout:fixed}th,td{font-size:11px!important;padding:3px 3px!important;overflow-wrap:anywhere}table th:first-child{width:19%!important}table th:nth-child(2){width:8%!important}.entry,.column-head{grid-template-columns:8% 9% 7% 20% 8% 11% 11% 12% 0;gap:2px} .entry select,.entry input,.entry textarea{font-size:12px!important;border:0!important;background:transparent!important;appearance:none!important;padding:0!important;height:auto!important;resize:none!important;color:black!important}.entry .save-state{display:none!important}.entry input[type=time]::-webkit-calendar-picker-indicator{display:none!important;-webkit-appearance:none!important}.entry input[type=time]::-webkit-inner-spin-button{display:none!important}.entry input[type=time]{pointer-events:none!important;width:100%!important;text-align:center!important}.flag{font-size:11px;padding:1px 4px}.check-audit small,.score-edit small{font-size:9px!important;color:#222!important}.entry .score-edit input{width:50px!important;text-align:center!important}.column-head span:nth-child(7),.column-head span:nth-child(8){text-align:center!important} .entry select:has(option:checked[value=""]){visibility:hidden!important}.entry textarea::placeholder,.entry input::placeholder{color:transparent!important}.entry textarea:placeholder-shown{visibility:hidden!important}.entry input[type=time]:not(:valid){visibility:hidden!important}.entry input[data-print-empty="1"],.entry select[data-print-empty="1"],.entry textarea[data-print-empty="1"]{visibility:hidden!important}.check-audit,.score-edit{gap:1px!important;line-height:1.05!important}.entry{align-items:center!important}.entry input,.entry textarea,.entry select{line-height:1.1!important}tr{break-inside:avoid;page-break-inside:avoid}thead{display:table-header-group}tr[hidden]{display:none!important}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}} @media print{.entry,.column-head{grid-template-columns:8% 9% 7% 20% 8% 11% 11% 12% 0!important;align-items:center!important}.column-head span{text-align:center!important}.entry .van-cell,.entry input[type=time],.entry .status-pill{justify-self:center!important;align-self:center!important;text-align:center!important}.entry .van-cell{width:100%!important;min-width:0!important;display:flex!important;justify-content:center!important;align-items:center!important}.entry .van-cell select{flex:0 1 auto!important;width:100%!important;max-width:100%!important;min-width:0!important;text-align:center!important;text-align-last:center!important;margin:0 auto!important}.entry textarea[name="comments"]{display:none!important}.entry .ops-print-comment{display:flex!important;align-items:center;justify-content:center;text-align:center;justify-self:stretch;align-self:stretch;white-space:pre-wrap;overflow-wrap:anywhere;font-size:11px;line-height:1.2;min-height:34px;padding:2px 3px;box-sizing:border-box}.entry .ops-print-comment:empty{visibility:hidden!important}}.ops-print-comment{display:none} .van-cell{flex-direction:column;justify-content:center}.van-controls{display:flex;align-items:center;justify-content:center;gap:3px;min-width:0}.van-fuel{font-size:11px;font-weight:700;line-height:1.2;text-align:center;color:#111;min-height:13px}.van-fuel[data-type="EV"]{color:#16803a}.van-fuel:empty{visibility:hidden}.fuel-filters{display:inline-flex;align-items:center;gap:10px}.fuel-filters label{display:inline-flex;align-items:center;gap:3px;font-weight:700;font-size:12px;white-space:nowrap}.fuel-filter-ev{color:#16803a}.fuel-filter-diesel{color:#111}@media print{.fuel-filters{display:none!important}.entry .van-cell{flex-direction:column!important}.entry .van-controls{display:flex!important;justify-content:center!important}.entry .van-fuel{font-size:9px!important}} .check-audit{display:flex;flex-direction:column;align-items:center;gap:3px;min-width:0}.check-audit small{font-size:9px;color:#52687e;line-height:1.2;text-align:center;overflow-wrap:anywhere}.score-edit{display:flex;flex-direction:column;gap:3px;min-width:0}.score-edit input{width:100%;text-align:center}.score-edit small{font-size:9px;color:#52687e;line-height:1.2;overflow-wrap:anywhere}.allocation-multi{position:relative;display:inline-block;min-width:200px}.allocation-trigger{width:100%;text-align:left;background:white;color:#173b60;border:1px solid #b9c9d8;padding:8px 12px}.allocation-panel{position:absolute;z-index:30;top:100%;left:0;min-width:230px;max-height:300px;overflow:auto;background:white;border:1px solid #b9c9d8;box-shadow:0 8px 20px #0002;border-radius:6px;padding:7px}.allocation-panel[hidden]{display:none}.allocation-panel label{display:flex;align-items:center;gap:8px;padding:7px;cursor:pointer;white-space:nowrap}.allocation-panel label:hover{background:#eaf1f8}.allocation-panel input{width:auto;accent-color:#176849}.allocation-sort{background:transparent;color:#173b60;border:0;font-weight:700;padding:4px;cursor:pointer}@media print{.allocation-panel{display:none!important}} .entry .van-cell{display:flex;align-items:center;gap:3px;min-width:0}.entry .van-cell select{flex:0 0 62px;width:62px!important;min-width:0;max-width:62px;padding:4px 1px;font-size:11px}.entry .fixed-van{display:inline-flex;align-items:center;gap:2px;white-space:nowrap}.entry input[type=time]{width:88px!important;min-width:0;max-width:88px;padding:4px 0;font-size:11px}.entry .score-edit{width:60px;max-width:100%;align-items:center}.entry .score-edit input{width:50px;max-width:100%;padding:6px 2px;text-align:center}.entry .score-edit small{white-space:normal;overflow-wrap:anywhere}input[type=number]{appearance:textfield;-moz-appearance:textfield}input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}.stamp{display:flex;flex-direction:column;align-items:center;white-space:nowrap}.stamp-date,.stamp-time{display:block;white-space:nowrap}.score-edit{align-items:center}.score-edit small{text-align:center}.column-head span:nth-child(7),.column-head span:nth-child(8){display:flex;align-items:center;justify-content:center;text-align:center;min-height:40px;line-height:1.15}.entry .score-edit{justify-self:center} .entry,.column-head{grid-template-columns:80px 88px 75px 78px 65px 90px 75px minmax(145px,1fr) 65px 75px 60px 70px 42px!important;min-width:1090px}.entry .van-controls{display:flex;flex-direction:column;align-items:center;gap:3px;width:100%;min-width:0}.entry .van-controls select{width:100%;max-width:100%;flex:none}.entry .fixed-van{justify-content:center}.entry>input[name=start_time]{box-sizing:border-box;width:100%!important;min-width:0!important;max-width:100%!important;justify-self:stretch!important;padding:6px 1px!important;text-align:center;font-variant-numeric:tabular-nums}.column-head span:nth-child(2){text-align:center}.lane-color{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:4px 5px;border-radius:5px;font-weight:800;border:1px solid #0002}.lane-color-red{background:#e53935;color:#fff}.lane-color-yellow{background:#ffe45c;color:#302600}.lane-color-green{background:#268b48;color:#fff}.lane-color-blue{background:#2879d0;color:#fff}.lane-color-orange{background:#ff9d36;color:#2c1900}.lane-color-purple{background:#7948ad;color:#fff}.lane-color-pink{background:#f6a3c5;color:#42162a}.lane-color-white{background:#fff;color:#172b40;border-color:#b5c3cf}.lane-color-black{background:#20242a;color:#fff}.lane-color-grey,.lane-color-gray{background:#89939d;color:#fff}.lane-value{text-align:center;overflow-wrap:anywhere;font-size:12px;font-weight:700}.lane-upload{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 0}.lane-upload button{padding:9px 15px;background:#176849;color:white;border:0;border-radius:6px;cursor:pointer}@media print{.lane-upload{display:none!important}.entry,.column-head{grid-template-columns:8% 8% 7% 7% 6% 8% 7% 15% 7% 8% 7% 7% 5%!important;min-width:0!important}.lane-value{font-size:9px!important}} </style><link rel="stylesheet" href="/operations-mobile.css?v=1">'.SectionAccess::CSS.'</head><body><header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" style="height:39px;width:auto;vertical-align:middle;background:white;border-radius:5px;padding:3px"></a></h1>'.$nav.'</header><main><h1>Operations Report</h1><div class="print-date">'.$esc($date->format('l, d F Y')).'</div><div class="datebar"><a class="day-arrow" href="?date='.$prev.'" title="Previous day" aria-label="Previous day">‹</a><input id="report-date" type="date" value="'.$day.'" aria-label="Report date"><a class="day-arrow" href="?date='.$next.'" title="Next day" aria-label="Next day">›</a><strong>'.$esc($date->format('l, d F Y')).'</strong><label class="filter-label" for="allocation-filter">Allocation</label><select id="allocation-filter">'.$filterOptions.'</select><span class="fuel-filters" aria-label="Vehicle fuel filters"><label class="fuel-filter-ev"><input id="fuel-ev" type="checkbox" checked> EV</label><label class="fuel-filter-diesel"><input id="fuel-diesel" type="checkbox" checked> Diesel</label></span><button type="button" id="print-report" class="print-button">Print / PDF (A4)</button><button type="button" id="share-driver-times" class="print-button" style="background:#087d53">Share Driver Times</button><span id="share-feedback" role="status" aria-live="polite"></span></div>'.($message?'<p class="notice">'.$esc($message).'</p>':'').($request->query->has('saved')?'<p>Saved successfully.</p>':'').$laneUpload.'<div class="card"><table><thead><tr><th>Driver</th><th>Allocation</th><th colspan="12"><div class="column-head"><span>Van</span><span>Start time</span><span>Color</span><span>Holding</span><span>STG</span><span>Route</span><span>Status</span><span>Comments</span><span>Ementor</span><span>Clear Quote</span><span>FICO Score</span><span>No. of<br>returns</span><span></span></div></th></tr></thead><tbody>'.$rows.'</tbody></table></div><p>Allocation and status are read from the rota. Van, start time and comments are saved for the selected day.</p></main><script id="ops-vehicle-types" type="application/json">'.json_encode($vehicleTypes, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="ops-vans" type="application/json">'.json_encode(array_values($vans), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="ops-status-codes" type="application/json">'.json_encode($statusCodes, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="share-data" type="application/json">'.$shareJson.'</script><script src="/operations-report.js?v=26"></script><script src="/operations-score-overrides.js?v=4"></script><script src="/operations-mobile.js?v=4"></script><script src="/operations-share.js?v=2"></script><script src="/operations-print-selection.js?v=4"></script></body></html>';
        if (!$operationsFull) {
            $html=str_replace('</body>', '<script>document.querySelectorAll("form.entry").forEach(f=>{f.querySelectorAll("select,input,textarea").forEach(el=>{if(el.type!=="hidden" && (el.name!=="fisco_score" || '.($ficoOnly?'false':'true').')){el.disabled=true;if(el.name===\"returns_count\")el.dataset.ficoOnly=\"1\";}});});</script></body>', $html);
        }
        return new Response($html);
    }
}
