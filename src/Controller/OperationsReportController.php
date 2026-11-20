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
    #[Route('/operations/instruction-status',name:'operations_instruction_status',methods:['GET'])]
    public function instructionStatus(Request $request, Connection $db): JsonResponse
    {
        if (!SectionAccess::allowed($this->getUser(), 'operations')) {
            throw $this->createAccessDeniedException();
        }
        $day=(string)$request->query->get('date','');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if(!$date||$date->format('Y-m-d')!==$day)return new JsonResponse(['ok'=>false,'error'=>'Invalid date'],422);
        $statuses=[];
        $counts=['sent'=>0,'read'=>0,'unread'=>0];
        $items=$db->fetchAllAssociative('SELECT p.driver_id,p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments,a.instruction_hash,a.instruction_snapshot,a.acknowledged_at FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id LEFT JOIN operations_instruction_ack a ON a.report_date=p.report_date AND a.driver_id=p.driver_id WHERE p.report_date=:day',['day'=>$day]);
        foreach($items as $item){
            $snapshot=[];
            foreach(['holding_time','route','stg_location','van_registration','manager_comments'] as $field)$snapshot[$field]=(string)($item[$field]??'');
            $hash=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $read=$item['instruction_hash']!==null&&hash_equals((string)$item['instruction_hash'],$hash);
            $previous=$item['instruction_snapshot']!==null?json_decode((string)$item['instruction_snapshot'],true):null;
            $changed=[];
            if(!$read&&is_array($previous))foreach($snapshot as $field=>$value)if($value!==(string)($previous[$field]??''))$changed[]=$field;
            $statuses[(int)$item['driver_id']]=['state'=>$read?'read':'unread','time'=>$read&&$item['acknowledged_at']?(new \DateTimeImmutable($item['acknowledged_at']))->format('H:i'):null,'changed_fields'=>$changed];
            ++$counts['sent'];++$counts[$read?'read':'unread'];
        }
        return new JsonResponse(['ok'=>true,'statuses'=>$statuses,'counts'=>$counts]);
    }

    #[Route('/operations/report',name:'operations_report',methods:['GET','POST'])]
    public function report(Request $request,Connection $db,EntityManagerInterface $em,DriverPushNotifier $pushNotifier): Response
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
        foreach($db->fetchAllAssociative('SELECT driver_id,ementor,clear_quote,fisco_score,returns_count,pre_updated_at,post_updated_at,ementor_submitted_at,clear_quote_submitted_at,fisco_submitted_at,returns_submitted_at FROM driver_daily_check WHERE shift_date=:day',['day'=>$day]) as $check)$dailyChecks[(int)$check['driver_id']]=$check;
        $laneRows=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,color,holding_time,stg_location,route FROM operations_lane_plan WHERE report_date=:day',['day'=>$day]) as $lane)$laneRows[(int)$lane['driver_id']]=$lane;
        $scoreOverrides=[];
        foreach($db->fetchAllAssociative('SELECT driver_id,fisco_score,returns_count,updated_at FROM operations_daily_score_override WHERE report_date=:day',['day'=>$day]) as $override)$scoreOverrides[(int)$override['driver_id']]=$override;
        $fixed=[];foreach($db->fetchAllAssociative('SELECT driver_id,van_registration FROM operations_fixed_van') as $f)$fixed[(int)$f['driver_id']]=$f['van_registration'];
        $message='';
        if($request->isMethod('POST') && $request->request->get('action')==='publish_lane'){
            if(!$operationsFull)throw $this->createAccessDeniedException();
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $published=0;
            $db->beginTransaction();
            try {
                // A new publication replaces the previous snapshot for this date.
                $db->delete('operations_lane_publication',['report_date'=>$day]);
                foreach($laneRows as $driverId=>$lane){
                    $holding=trim((string)($lane['holding_time']??''));
                    $route=trim((string)($lane['route']??''));
                    $stg=trim((string)($lane['stg_location']??''));
                    if($holding==='' && $route==='' && $stg==='')continue;
                    $van=trim((string)($entries[$driverId]['van_registration']??''));
                    if($van==='' && isset($fixed[$driverId]) && in_array($fixed[$driverId],$vans,true))$van=$fixed[$driverId];
                    $db->insert('operations_lane_publication',[
                        'report_date'=>$day,'driver_id'=>$driverId,'holding_time'=>$holding?:null,
                        'route'=>$route?:null,'stg_location'=>$stg?:null,
                        'van_registration'=>$van?:null,'published_by'=>$this->getUser()->getId(),
                        'published_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')
                    ]);
                    ++$published;
                }
                // Comments are independently visible even without a published route.
                // Recreate comment-only instruction rows after replacing route publication.
                foreach($db->fetchAllAssociative("SELECT driver_id FROM operations_daily WHERE report_date=:day AND TRIM(COALESCE(comments,''))<>''", ['day'=>$day]) as $commentRow){
                    $commentDriver=(int)$commentRow['driver_id'];
                    if($db->fetchOne('SELECT 1 FROM operations_lane_publication WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$commentDriver]))continue;
                    $db->insert('operations_lane_publication',['report_date'=>$day,'driver_id'=>$commentDriver,'holding_time'=>null,'route'=>null,'stg_location'=>null,'van_registration'=>null,'published_by'=>$this->getUser()->getId(),'published_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')]);
                }
                $db->commit();
                $pushNotifier->schedule($request, $day, $db->fetchFirstColumn('SELECT driver_id FROM operations_lane_publication WHERE report_date=:day',['day'=>$day]));
            }catch(\Throwable $e){$db->rollBack();throw $e;}
            $this->addFlash($published?'lane_success':'lane_error',$published
                ?'Published holding times, routes, STG and assigned vans for '.$published.' drivers on '.$day.'.'
                :'No lane assignments found to publish for '.$day.'.');
            return $this->redirectToRoute('operations_report',['date'=>$day]);
        }
        if($request->isMethod('POST') && $request->request->get('action')==='route_upload'){
            if(!$operationsFull)throw $this->createAccessDeniedException();
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $file=$request->files->get('route_file');
            if(!$file || !$file->isValid() || $file->getSize()>15*1024*1024 || strtolower($file->getClientOriginalExtension())!=='xlsx')
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Select an .xlsx route sheet under 15 MB.');
            try{
                $records=(new LanePlanReader())->read($file->getPathname(),true);
                $matching=[];$unmatched=[];
                foreach($records as $transporterId=>$record){
                    $driver=$em->getRepository(User::class)->findOneBy(['transporterId'=>$transporterId]);
                    if(!$driver || !$driver->isActive() || !in_array($driver->getDriverType(),['DRIVER','JUMPER'],true)){$unmatched[]=['id'=>$transporterId,'name'=>$record['name']??'','route'=>$record['route']];continue;}
                    $matching[$driver->getId()]=$record['route'];
                }
                if(!$matching)throw new \InvalidArgumentException('No active drivers matched the Transporter IDs.');
                $db->beginTransaction();
                try{
                    $db->delete('operations_lane_plan',['report_date'=>$day]);
                    $stamp=(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s');
                    foreach($matching as $driverId=>$route)$db->insert('operations_lane_plan',[
                        'report_date'=>$day,'driver_id'=>$driverId,'route'=>$route,'color'=>'','holding_time'=>'','stg_location'=>'',
                        'uploaded_by'=>$this->getUser()->getId(),'uploaded_at'=>$stamp
                    ]);
                    $db->commit();
                }catch(\Throwable $e){$db->rollBack();throw $e;}
                $this->addFlash('lane_success','Step 1: '.count($matching).' drivers assigned routes for '.$day.'; '.count($unmatched).' unmatched Transporter IDs.');
                if($unmatched)$this->addFlash('lane_unmatched',$unmatched);
            }catch(\InvalidArgumentException $e){$this->addFlash('lane_error',$e->getMessage());}
            return $this->redirectToRoute('operations_report',['date'=>$day]);
        }
        if($request->isMethod('POST') && $request->request->get('action')==='lane_upload'){
            if(!$operationsFull)throw $this->createAccessDeniedException();
            if(!$this->isCsrfTokenValid('operations_report',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
            $file=$request->files->get('lane_file');
            if(!$file || !$file->isValid() || $file->getSize()>15*1024*1024 || strtolower($file->getClientOriginalExtension())!=='xlsx')
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Select an .xlsx lane plan under 15 MB.');
            try {
                $records=(new LanePlanReader())->read($file->getPathname());
                $assignments=$db->fetchAllAssociative('SELECT driver_id,route FROM operations_lane_plan WHERE report_date=:day',['day'=>$day]);
                if(!$assignments)throw new \InvalidArgumentException('Import Step 1 routes for this date before Step 2.');
                $matched=0;$missing=[];
                $db->beginTransaction();
                try{
                    foreach($assignments as $assignment){
                        $route=trim((string)$assignment['route']);
                        if(!isset($records[strtoupper($route)])){$missing[]=$route;continue;}
                        $record=$records[strtoupper($route)];
                        $db->update('operations_lane_plan',$record,['report_date'=>$day,'driver_id'=>(int)$assignment['driver_id']]);
                        ++$matched;
                    }
                    if(!$matched)throw new \InvalidArgumentException('No route codes from Step 1 matched the LANE PLAN.');
                    $db->commit();
                }catch(\Throwable $e){$db->rollBack();throw $e;}
                $missingRoutes=array_values(array_unique($missing));
                sort($missingRoutes,SORT_NATURAL|SORT_FLAG_CASE);
                $this->addFlash('lane_success','Step 2: '.$matched.' driver assignments enriched from the lane plan by route; '.count($missingRoutes).' routes not found.');
                if($missingRoutes)$this->addFlash('lane_missing_routes',$missingRoutes);
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
                $submitted=$db->fetchOne('SELECT fisco_score FROM driver_daily_check WHERE shift_date=:day AND driver_id=:id AND fisco_score IS NOT NULL',['day'=>$day,'id'=>$id]);
                $score=trim((string)$request->request->get('fisco_score',''));
                $returns=trim((string)$request->request->get('returns_count',''));
                if(($score!==''&&(!preg_match('/^\d{1,3}$/D',$score)||(int)$score>850))||($returns!==''&&!preg_match('/^\d{1,3}$/D',$returns)))
                    return new JsonResponse(['ok'=>false,'error'=>'FICO Score must be 0?850 and returns 0?999.'],422);
                $values=['fisco_score'=>$score===''?null:(int)$score,'updated_by'=>$this->getUser()->getId(),'updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
                $values['returns_count']=$returns===''?null:(int)$returns;
                $old=$db->fetchAssociative('SELECT fisco_score,returns_count FROM operations_daily_score_override WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id]);
                $driverSubmission=$db->fetchAssociative('SELECT fisco_score,fisco_submitted_at FROM driver_daily_check WHERE shift_date=:day AND driver_id=:id AND fisco_score IS NOT NULL',['day'=>$day,'id'=>$id]);
                $previousScore=$driverSubmission && (!$old || $driverSubmission['fisco_submitted_at']>$db->fetchOne('SELECT updated_at FROM operations_daily_score_override WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id])) ? (int)$driverSubmission['fisco_score'] : ($old && $old['fisco_score']!==null ? (int)$old['fisco_score'] : ($submitted!==false ? (int)$submitted : null));
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
                    // Once this day's route is published, keep its displayed van current.
                    // Do not create a publication for dates/drivers not yet published.
                    $publication=$db->fetchAssociative('SELECT holding_time,route,stg_location FROM operations_lane_publication WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id]);
                    if($publication){
                        // A comment-only publication must not imply a route or van assignment.
                        $hasRoute=trim((string)($publication['holding_time']??''))!=='' || trim((string)($publication['route']??''))!=='' || trim((string)($publication['stg_location']??''))!=='';
                        if($hasRoute)$db->update('operations_lane_publication',['van_registration'=>$van?:null],['report_date'=>$day,'driver_id'=>$id]);
                        elseif($comments==='')$db->delete('operations_lane_publication',['report_date'=>$day,'driver_id'=>$id]);
                    }elseif($comments!==''){
                        $db->insert('operations_lane_publication',['report_date'=>$day,'driver_id'=>$id,'holding_time'=>null,'route'=>null,'stg_location'=>null,'van_registration'=>null,'published_by'=>$this->getUser()->getId(),'published_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')]);
                    }
                    $db->commit();
                    $pushNotifier->schedule($request, $day, [$id]);
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
        $rota=[];foreach($cells as $cell)if(in_array($cell->fieldKey,['allocation','status','availability'],true))$rota[$cell->driverKey][$cell->fieldKey]=$cell->value;
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
        $allocationOptions='<option value="">—</option>';
        foreach($em->getRepository(RotaAllocationOption::class)->findBy([],['sortOrder'=>'ASC','code'=>'ASC']) as $option){if($option->active){$filterOptions.='<option value="'.$esc($option->code).'">'.$esc($option->code).' — '.$esc($option->label).'</option>';$allocationOptions.='<option value="'.$esc($option->code).'">'.$esc($option->code).'</option>';}}
        $statusCodes = array_map(static fn($o) => $o->code, $em->getRepository(\App\Entity\RotaStatusOption::class)->findBy(['active'=>true], ['sortOrder'=>'ASC']));
        // Match the driver's acknowledgement against the exact current instructions.
        $instructionStatus=[];
        $instructionCounts=['sent'=>0,'read'=>0,'unread'=>0];
        $publishedInstructions=$db->fetchAllAssociative('SELECT p.driver_id,p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments,a.instruction_hash,a.instruction_snapshot,a.acknowledged_at FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id LEFT JOIN operations_instruction_ack a ON a.report_date=p.report_date AND a.driver_id=p.driver_id WHERE p.report_date=:day',['day'=>$day]);
        foreach($publishedInstructions as $publishedInstruction){
            $instructionId=(int)$publishedInstruction['driver_id'];
            $snapshot=[];
            foreach(['holding_time','route','stg_location','van_registration','manager_comments'] as $field)$snapshot[$field]=(string)($publishedInstruction[$field]??'');
            $hash=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $read=$publishedInstruction['instruction_hash']!==null&&hash_equals((string)$publishedInstruction['instruction_hash'],$hash);
            $previous=$publishedInstruction['instruction_snapshot']!==null?json_decode((string)$publishedInstruction['instruction_snapshot'],true):null;
            $changed=[];
            if(!$read&&is_array($previous))foreach($snapshot as $field=>$value)if($value!==(string)($previous[$field]??''))$changed[]=$field;
            $instructionStatus[$instructionId]=['read'=>$read,'time'=>$read?$publishedInstruction['acknowledged_at']:null,'changed_fields'=>$changed];
            ++$instructionCounts['sent'];
            ++$instructionCounts[$read?'read':'unread'];
        }
        $rows='';
        $token=$esc($this->container->get('security.csrf.token_manager')->getToken('operations_report')->getValue());
        foreach($drivers as $rowIndex=>$driver){
            $id=$driver->getId();$entry=$entries[$id]??[];$current=$entry['van_registration']??'';
            $isFixed=isset($fixed[$id]);
            $options='<option value="">Select</option>';
            if($current!=='')$options.='<option value="'.$esc($current).'" selected>'.$esc(substr((string)$current,-3)).'</option>';
            $key='driver-'.$id;
            $allocation=(string)($rota[$key]['allocation']??'');
            $availability=(string)($rota[$key]['availability']??'');
            $allocationWarning=$allocation!==''&&$availability!=='IN';
            $allocationMarkup='<select id="ops-allocation-'.$id.'" class="ops-allocation'.($allocationWarning?' allocation-warning':($allocation==='IN'?' allocation-in':($allocation!==''?' allocation-other':''))).'" aria-label="Allocation for '.$esc($driver->getDisplayName()).'" data-original="'.$esc($allocation).'" data-availability="'.$esc($availability).'">';
            $allocationMarkup.=str_replace('value="'.$esc($allocation).'"','value="'.$esc($allocation).'" selected',$allocationOptions).'</select><span class="ops-allocation-print">'.$esc($allocation!==''?$allocation:'—').'</span>';
            $auditStamp=static function(?string $raw)use($tz,$esc):string{
                if(!$raw)return 'Not submitted';
                $formatted=(new \DateTimeImmutable($raw,$tz))->format('d/m/Y H:i').' UK';
                return '<span class="stamp-date">'.$esc(substr($formatted,0,10)).'</span><span class="stamp-time">'.$esc(substr($formatted,11)).'</span>';
            };
            $ementorSubmitted=!empty($dailyChecks[$id]['ementor_submitted_at']);
            $clearSubmitted=!empty($dailyChecks[$id]['clear_quote_submitted_at']);
            $ementorMarkup='<span class="check-audit"><span class="flag '.($ementorSubmitted?'flag-yes':'flag-no').'">'.($ementorSubmitted?'Yes':'No').'</span><small class="stamp">'.$auditStamp($dailyChecks[$id]['ementor_submitted_at']??null).'</small></span>';
            $clearQuoteMarkup='<span class="check-audit"><span class="flag '.($clearSubmitted?'flag-yes':'flag-no').'">'.($clearSubmitted?'Yes':'No').'</span><small class="stamp">'.$auditStamp($dailyChecks[$id]['clear_quote_submitted_at']??null).'</small></span>';
            $original=$dailyChecks[$id]??[];
            $override=$scoreOverrides[$id]??[];
            $driverLater=!empty($original['fisco_submitted_at']) && (!isset($override['updated_at']) || $original['fisco_submitted_at']>$override['updated_at']);
            $scoreValue=$driverLater?($original['fisco_score']??null):(array_key_exists('fisco_score',$override)?$override['fisco_score']:($original['fisco_score']??null));
            $returnsValue=array_key_exists('returns_count',$override)?$override['returns_count']:($original['returns_count']??null);
            $submittedAt=!empty($original['fisco_submitted_at'])?(new \DateTimeImmutable($original['fisco_submitted_at'], $tz))->format('d/m/Y H:i').' UK':'Not submitted';
            $scoreStamp=$auditStamp($original['fisco_submitted_at']??null);
            $returnsStamp=$auditStamp($original['returns_submitted_at']??null);
            $originalNote='Driver: '.$esc($original['fisco_score']??'?').' / '.$esc($original['returns_count']??'?').' ? '.$esc($submittedAt);
            $scoreMarkup='<span class="score-edit"><input type="number" name="fisco_score" min="0" max="850" step="1" aria-label="Manager FICO Score" value="'.$esc($scoreValue??'').'"><small>Driver: '.$esc($original['fisco_score']??'?').'</small><small class="stamp">'.$scoreStamp.'</small></span>';
            $returnsMarkup='<span class="score-edit"><input type="number" name="returns_count" min="0" max="999" step="1" aria-label="Manager No. of Returns" value="'.$esc($returnsValue??'').'"><small>Driver: '.$esc($original['returns_count']??'?').'</small><small class="stamp">'.$returnsStamp.'</small></span>';
            $lane=$laneRows[$id]??[];
            $laneMarkup='';
            foreach(['color','holding_time','stg_location','route'] as $field) {
                $value=trim((string)($lane[$field]??''));
                $colorName=strtolower($value);
                $colorClass=$field==='color' && in_array($colorName,['red','yellow','green','blue','orange','purple','pink','white','black','grey','gray'],true) ? ' lane-color lane-color-'.$colorName : '';
                $laneMarkup.='<span class="lane-value'.$colorClass.'">'.($value!==''?$esc($value):'—').'</span>';
            }
            $swapButton=$operationsFull && isset($laneRows[$id])?'<button type="button" class="driver-change-trigger" data-driver-id="'.$id.'" title="Change or swap route driver" aria-label="Change or swap route for '.$esc($driver->getDisplayName()).'">⇄</button>':'';
            $instruction=$instructionStatus[$id]??null;
            $instructionState=$instruction===null?'unpublished':($instruction['read']?'read':'unread');
            $instructionLabel=$instruction===null?'Not published':($instruction['read']?'Instructions read':'Sent · Not read');
            $instructionTime=$instruction!==null&&$instruction['read']&&$instruction['time']?' · '.(new \DateTimeImmutable($instruction['time'],$tz))->format('H:i'):'';
            $changedFields=$instruction['changed_fields']??[];
            $instructionBadge='<span class="instruction-status instruction-'.$instructionState.'"><span class="instruction-dot" aria-hidden="true"></span>'.$esc($instructionLabel.$instructionTime).'</span>';
            $rows.='<tr data-changed-fields="'.$esc(implode(',', $changedFields)).'" data-instructions="'.$instructionState.'" data-allocation="'.$esc($rota[$key]['allocation']??'').'" data-driver-id="'.$id.'"><td><span class="driver-heading"><span class="row-number">'.($rowIndex+1).'.</span><span class="driver-name-actions"><strong>'.$esc($driver->getDisplayName()).'</strong>'.$swapButton.'</span></span><small class="driver-transporter-id">ID: '.$esc($driver->getTransporterId()?:'—').'</small>'.$instructionBadge.'</td><td>'.$allocationMarkup.'</td><td colspan="12"><form method="post" class="entry"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="driver_id" value="'.$id.'"><span class="van-cell"><span class="van-controls"><select name="van" aria-label="Van for '.$esc($driver->getDisplayName()).'">'.$options.'</select><label class="fixed-van" title="Use this van as this driver’s default on future working days"><input type="checkbox" name="fixed" value="1"'.($isFixed?' checked':'').'> Fixed</label><input type="hidden" name="fixed_change" value="0"></span><span class="van-fuel" data-type="'.$esc($vehicleTypes[$current]??'').'">'.$esc($vehicleTypes[$current]??'').'</span></span><input type="time" name="start_time" aria-label="Start time" value="'.$esc($entry['start_time']??'').'">'.$laneMarkup.'<span class="status status-pill '.(strcasecmp(trim((string)($rota[$key]['status']??'')),'sick')===0?'status-sick':'').'">'.$esc(trim((string)($rota[$key]['status']??''))!==''?$rota[$key]['status']:'—').'</span><textarea name="comments" rows="3" maxlength="2000" aria-label="Comments" placeholder="Comments">'.$esc($entry['comments']??'').'</textarea>' .$ementorMarkup.$clearQuoteMarkup.$scoreMarkup.$returnsMarkup.'<span class="save-state" aria-live="polite"></span></form></td></tr>';
        }
        if (!$operationsFull) {
            // Read-only operational fields for Performance Managers with Operations access.
            $rows=preg_replace('/(<form method="post" class="entry">.*?)(<span class="score-edit"><input type="number" name="fisco_score")/s', '$1$2', $rows);
        }
        $laneUpload='';
        if($operationsFull)$laneUpload='<form method="post" enctype="multipart/form-data" class="lane-upload"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="route_upload"><label><strong>Step 1 · Get routes (.xlsx)</strong> <input type="file" name="route_file" accept=".xlsx" required></label><button type="submit">Import routes for '.$esc($day).'</button></form>';
        $step2Upload=$operationsFull?'<form method="post" enctype="multipart/form-data" class="lane-upload"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="lane_upload"><label><strong>Step 2 · Import LANE PLAN (.xlsx)</strong> <input type="file" name="lane_file" accept=".xlsx" required></label><button type="submit">Import Lane Plan for '.$esc($day).'</button></form>':'';
        $laneUpload.='<div class="lane-step-two">'.$step2Upload.'</div>';
        if($operationsFull){
            $publishedCount=(int)$db->fetchOne('SELECT COUNT(*) FROM operations_lane_publication WHERE report_date=:day',['day'=>$day]);
            $laneUpload.='<form method="post" class="lane-publish"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="publish_lane"><button type="submit"'.(!$laneRows?' disabled title="Import a lane plan first"':'').'>Publish HT &amp; Route</button><span class="publish-state">'.($publishedCount?$publishedCount.' drivers published':'Not published').'</span></form>';
        }
        foreach($request->getSession()->getFlashBag()->get('lane_success') as $notice)$laneUpload.='<p class="notice">'.$esc($notice).'</p>';
        foreach($request->getSession()->getFlashBag()->get('lane_error') as $notice)$laneUpload.='<p class="notice">'.$esc($notice).'</p>';
        foreach($request->getSession()->getFlashBag()->get('lane_unmatched') as $unmatched){
            $laneUpload.='<details class="notice" open><summary><strong>Unmatched drivers from Step 1 ('.count($unmatched).')</strong></summary><ul>';
            foreach($unmatched as $person)$laneUpload.='<li>'.$esc($person['name']!==''?$person['name']:'Name not supplied').' — '.$esc($person['id']).' ('.$esc($person['route']).')</li>';
            $laneUpload.='</ul></details>';
        }
        foreach($request->getSession()->getFlashBag()->get('lane_missing_routes') as $missingRoutes){
            $laneUpload.='<details class="notice" open><summary><strong>Routes not found in Step 2 ('.count($missingRoutes).')</strong></summary><ul>';
            foreach($missingRoutes as $missingRoute)$laneUpload.='<li>'.$esc($missingRoute).'</li>';
            $laneUpload.='</ul></details>';
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
        $nav=str_replace('<script>(function(){var toggle=document.querySelector(".du-hamburger"),nav=document.getElementById("du-main-nav");if(toggle&&nav){toggle.addEventListener("click",function(){var open=nav.classList.toggle("du-nav-open");toggle.setAttribute("aria-expanded",String(open));toggle.setAttribute("aria-label",open?"Close menu":"Open menu");});}if(!window.matchMedia("(hover: hover) and (pointer: fine)").matches)return;document.querySelectorAll(".du-menu").forEach(function(menu){menu.addEventListener("mouseenter",function(){menu.open=true;});menu.addEventListener("mouseleave",function(){menu.open=false;});});})();</script>', '', SectionAccess::navigation($this->getUser()));
        $rotaToken=$esc($this->container->get('security.csrf.token_manager')->getToken('rota_edit')->getValue());
        $html='<!doctype html><html lang="en"><head><link rel="icon" type="image/png" href="/favicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Operations Report · Dulogix</title><link rel="stylesheet" href="/operations-report-page.css?v=1"><link rel="stylesheet" href="/operations-mobile.css?v=1"><link rel="stylesheet" href="/operations-filters.css?v=1"><link rel="stylesheet" href="/operations-print-selection.css?v=1"><link rel="stylesheet" href="/operations-share.css?v=1"><link rel="stylesheet" href="/operations-driver-change.css?v=1"><link rel="stylesheet" href="/operations-nav.css?v=1"><link rel="stylesheet" href="/operations-print-cleanup.css?v=3"></head><body><header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" class="ops-header-logo"></a></h1>'.$nav.'</header><main><h1>Operations Report</h1><div class="print-date">'.$esc($date->format('l, d F Y')).'</div><div class="datebar"><a class="day-arrow" href="?date='.$prev.'" title="Previous day" aria-label="Previous day">‹</a><input id="report-date" type="date" value="'.$day.'" aria-label="Report date"><a class="day-arrow" href="?date='.$next.'" title="Next day" aria-label="Next day">›</a><strong>'.$esc($date->format('l, d F Y')).'</strong><span class="filter-label" id="allocation-filter-label">Allocation</span><select id="allocation-filter">'.$filterOptions.'</select><span class="fuel-filters" aria-label="Vehicle fuel filters"><label class="fuel-filter-ev"><input id="fuel-ev" type="checkbox" checked> EV</label><label class="fuel-filter-diesel"><input id="fuel-diesel" type="checkbox" checked> Diesel</label></span><button type="button" id="print-report" class="print-button">Print / PDF (A4)</button><button type="button" id="share-driver-times" class="print-button ops-share-driver-button">Share Driver Times</button><span id="share-feedback" role="status" aria-live="polite"></span></div>'.($message?'<p class="notice">'.$esc($message).'</p>':'').($request->query->has('saved')?'<p>Saved successfully.</p>':'').$laneUpload.'<div class="instruction-overview"><strong id="ops-instruction-summary">Instructions: '.$instructionCounts['read'].' of '.$instructionCounts['sent'].' read</strong><span id="ops-instruction-pending">'.$instructionCounts['unread'].' awaiting acknowledgement</span><label><input type="checkbox" id="ops-unread-only"> Show only instructions not read</label></div><div class="card"><table><thead><tr><th>Driver</th><th>Allocation</th><th colspan="12"><div class="column-head"><span>Van</span><span>Start time</span><span>Color</span><span>Holding</span><span>STG</span><span>Route</span><span>Status</span><span>Comments</span><span>Ementor</span><span>Clear Quote</span><span>FICO Score</span><span>No. of<br>returns</span><span></span></div></th></tr></thead><tbody>'.$rows.'</tbody></table></div><p>Allocation and status are read from the rota. Van, start time and comments are saved for the selected day.</p></main><script id="ops-vehicle-types" type="application/json">'.json_encode($vehicleTypes, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="ops-vans" type="application/json">'.json_encode(array_values($vans), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="ops-status-codes" type="application/json">'.json_encode($statusCodes, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script id="share-data" type="application/json">'.$shareJson.'</script><script id="ops-rota-config" type="application/json">'.json_encode(['week'=>$week->format('Y-m-d'),'day'=>$index,'token'=>$rotaToken,'canEdit'=>SectionAccess::canEdit($this->getUser(),'rota'),'readOnly'=>!$operationsFull,'ficoOnly'=>$ficoOnly], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script src="/operations-nav.js?v=1"></script><script src="/operations-rota-config.js?v=1"></script><script src="/operations-allocation.js?v=4"></script><script src="/operations-report.js?v=31"></script><script src="/operations-driver-change.js?v=5"></script><script src="/operations-score-overrides.js?v=5"></script><script src="/operations-mobile.js?v=4"></script><script src="/operations-share.js?v=2"></script><script src="/operations-print-selection.js?v=6"></script></body></html>';
        return new Response($html);
    }
}
