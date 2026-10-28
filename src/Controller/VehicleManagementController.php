<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleManagementController extends AbstractController
{
    private const TYPES = ['Diesel','EV','Petrol','Hybrid'];

    private function access(): void {
        if (!SectionAccess::allowed($this->getUser(), 'vehicle')) throw $this->createAccessDeniedException('Vehicle Management is not assigned to your account.');
    }
    private function cleanReg(string $reg): string { return strtoupper(preg_replace('/\s+/', ' ', trim($reg))); }
    private function valid(string $reg, string $type): bool { return (bool)preg_match('/^[A-Z0-9 -]{2,24}$/D', $reg) && in_array($type, self::TYPES, true); }
    private function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

    #[Route('/vehicles', name: 'vehicle_dashboard', methods: ['GET','POST'])]
    public function dashboard(Request $request, Connection $db): Response {
        $this->access();
        $message = '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('vehicle_manage', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $action = (string)$request->request->get('action');
            if ($action === 'add') {
                $reg = $this->cleanReg((string)$request->request->get('registration'));
                $type = (string)$request->request->get('vehicle_type');
                if (!$this->valid($reg,$type)) $message = 'Enter a valid registration and vehicle type.';
                else {
                    try {
                        $db->insert('vehicle',['registration'=>$reg,'vehicle_type'=>$type,'created_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'updated_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
                        return $this->redirectToRoute('vehicle_dashboard',['saved'=>1]);
                    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) { $message='This vehicle registration already exists.'; }
                }
            } elseif ($action === 'toggle') {
                $reg=$this->cleanReg((string)$request->request->get('registration'));
                $ground=(string)$request->request->get('grounded');
                if (!in_array($ground,['0','1'],true)) $message='Invalid vehicle status.';
                else {
                    $db->update('vehicle',['grounded'=>(int)$ground,'updated_at'=>date('Y-m-d H:i:s')],['registration'=>$reg]);
                    return $this->redirectToRoute('vehicle_dashboard',['status'=>$ground==='1'?'grounded':'active']);
                }
            } elseif ($action === 'import') {
                $upload = $request->files->get('file');
                if (!$upload || !$upload->isValid() || $upload->getSize()>2_000_000) $message='Choose an Excel .xlsx file under 2 MB.';
                else {
                    try {
                        $rows = $this->readXlsx($upload->getPathname());
                        if (count($rows)>2001) throw new \RuntimeException('Maximum 2,000 rows per import.');
                        $inserted=0;$skipped=0;
                        foreach ($rows as $index=>$row) {
                            if ($index===0 && strtolower(trim($row[0]??''))==='vehicle reg') continue;
                            if (!array_filter($row,static fn($v)=>trim((string)$v)!=='')) continue;
                            $reg=$this->cleanReg((string)($row[0]??''));
                            $type=ucfirst(strtolower(trim((string)($row[1]??''))));
                            if (strtolower($type)==='ev') $type='EV';
                            if (!$this->valid($reg,$type)) { $skipped++;continue; }
                            try {
                                $db->insert('vehicle',['registration'=>$reg,'vehicle_type'=>$type,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                                $inserted++;
                            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) { $skipped++; }
                        }
                        $message="$inserted vehicles imported; $skipped skipped (invalid or duplicate).";
                    } catch (\Throwable $e) { $message='Import failed: '.$e->getMessage(); }
                }
            }
        }
        $requested=$request->query->all()['type']??[];
        if (is_string($requested)) $requested=[$requested];
        if (!is_array($requested)) $requested=[];
        $filters=array_values(array_intersect(self::TYPES,$requested));
        $state=(string)$request->query->get('state','all');
        if (!in_array($state,['all','active','grounded'],true)) $state='all';
        $where=[];$params=[];
        if ($filters) { $where[]='vehicle_type IN ('.implode(',',array_fill(0,count($filters),'?')).')';$params=array_merge($params,$filters); }
        if ($state!=='all') { $where[]='grounded = ?';$params[]=$state==='grounded'?1:0; }
        $vehicles=$db->fetchAllAssociative('SELECT registration,vehicle_type,grounded FROM vehicle'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY registration',$params);
        $totalVehicles=(int)$db->fetchOne('SELECT COUNT(*) FROM vehicle');
        $nav=SectionAccess::navigation($this->getUser());
        $token=$this->esc($this->container->get('security.csrf.token_manager')->getToken('vehicle_manage')->getValue());
        $filterOptions='';
        $addOptions='';
        foreach(self::TYPES as $type) {
            $class=strtolower($type);
            $filterOptions.='<label class="type-filter '.$class.'"><input type="checkbox" name="type[]" value="'.$type.'"'.(in_array($type,$filters,true)?' checked':'').'>'.$type.'</label>';
            $addOptions.='<option value="'.$type.'">'.$type.'</option>';
        }
        $exportQuery=($filters||$state!=='all')?'?'.http_build_query(['type'=>$filters,'state'=>$state]):'';
        $rows='';
        foreach($vehicles as $index=>$vehicle) {
            $grounded=(bool)$vehicle['grounded'];$reg=$this->esc($vehicle['registration']);
            $rows.='<tr><td>'.($index+1).'</td><td>'.$reg.'</td><td>'.$this->esc($vehicle['vehicle_type']).'</td><td><span class="vehicle-state '.($grounded?'is-grounded':'is-active').'">'.($grounded?'Grounded':'Active').'</span></td><td><form method="post" class="vehicle-action"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="toggle"><input type="hidden" name="registration" value="'.$reg.'"><input type="hidden" name="grounded" value="'.($grounded?'0':'1').'"><button type="submit" class="'.($grounded?'activate-btn':'ground-btn').'" onclick="return confirm(&quot;'.($grounded?'Return this vehicle to active service?':'Ground this vehicle? It will no longer be available for operations assignments.').'&quot;)">'.($grounded?'Reactivate':'Ground').'</button></form></td></tr>';
        }
        if (!$rows) $rows='<tr><td colspan="5">No vehicles match these filters.</td></tr>';
        $notice=$message!==''?'<p class="notice">'.$this->esc($message).'</p>':($request->query->has('saved')?'<p class="notice">Vehicle added.</p>':($request->query->has('status')?'<p class="notice">Vehicle status updated.</p>':''));
        $html='<!doctype html><html lang="en"><head><link rel="icon" type="image/png" href="/favicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vehicle Management · Dulogix</title>'.SectionAccess::CSS.'<style>body{margin:0;background:#f4f7fb;color:#17314e;font:15px system-ui,sans-serif}header{background:#152d49;color:white;padding:18px 28px}main{max-width:1150px;margin:30px auto;padding:0 18px}.card{background:#fff;border:1px solid #dbe3ee;border-radius:10px;padding:24px;margin-bottom:18px}h1{margin-top:0}.row{display:flex;align-items:end;flex-wrap:wrap;gap:14px}.row label{display:grid;gap:6px}input,select,button,.button{padding:10px 12px;border:1px solid #c5d3e3;border-radius:6px;font:inherit}button,.button{background:#1a4c77;color:#fff;cursor:pointer;text-decoration:none}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{padding:12px;border-bottom:1px solid #e0e7ef;text-align:left}th{background:#eef3f9}.notice{padding:12px;background:#e6f3ff;border-radius:6px}.muted{color:#63758b}.type-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.type-filter{display:inline-flex;align-items:center;gap:9px;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer}.type-filter input{margin:0;accent-color:#fff;width:18px;height:18px}.type-filter.ev{background:#168342;color:#fff}.type-filter.diesel{background:#181818;color:#fff}.type-filter.petrol{background:#b9e9ac;color:#153b22}.type-filter.hybrid{background:#6945aa;color:#fff}.vehicle-filter-panel{display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:15px;background:#f5f8fc;border:1px solid #dbe3ee;border-radius:10px}.filter-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.type-filter{border:2px solid transparent;opacity:.8}.type-filter:has(input:checked){outline:3px solid #b9d5ef;outline-offset:2px;opacity:1}.type-filter input{accent-color:#1a4c77}.filter-reset{background:#fff;color:#1a4c77}.vehicle-state{display:inline-block;border-radius:999px;padding:5px 12px;font-weight:700}.is-active{background:#dcfce7;color:#166534}.is-grounded{background:#fee2e2;color:#991b1b}.vehicle-action{margin:0}.vehicle-action button{margin:0;padding:7px 12px}.ground-btn{background:#9f2e38}.activate-btn{background:#16734a}#vehicle-search{margin-top:14px;width:100%;max-width:380px}#vehicle-table tbody tr:nth-child(even){background:#f1f5fa}@media(max-width:650px){.vehicle-filter-panel{align-items:stretch}.filter-group{width:100%}#vehicle-table th,#vehicle-table td{padding:8px 5px;font-size:12px}}</style></head><body><header><strong style="margin-right:28px">Dulogix</strong>'.$nav.'</header><main><h1>Vehicle Management</h1>'.$notice.'<div class="card"><h2>Add vehicle</h2><form method="post" class="row"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="add"><label>Vehicle Reg<input name="registration" maxlength="24" required placeholder="AB12 CDE"></label><label>Vehicle Type<select name="vehicle_type">'.$addOptions.'</select></label><button>Add vehicle</button></form></div><div class="card"><h2>Vehicle register</h2><form method="get" class="vehicle-filter-panel" id="vehicle-type-filters"><div class="filter-group"><strong>Vehicle type</strong>'.$filterOptions.'</div><div class="filter-group"><label for="vehicle-state-filter"><strong>Status</strong></label><select name="state" id="vehicle-state-filter"><option value="all"'.($state==='all'?' selected':'').'>All vehicles</option><option value="active"'.($state==='active'?' selected':'').'>Active</option><option value="grounded"'.($state==='grounded'?' selected':'').'>Grounded</option></select></div><a href="/vehicles" class="button filter-reset">Clear filters</a></form><script>document.querySelectorAll(\'#vehicle-type-filters input[type=checkbox]\').forEach(function(box){box.addEventListener(\'change\',function(){document.getElementById(\'vehicle-type-filters\').requestSubmit();});});document.getElementById("vehicle-state-filter").addEventListener("change",function(){document.getElementById("vehicle-type-filters").requestSubmit();});</script><label for="vehicle-search">Search by vehicle registration</label><input id="vehicle-search" type="search" placeholder="Enter registration…" oninput="document.querySelectorAll(\'#vehicle-table tbody tr\').forEach(row=>row.hidden=!row.cells[1].textContent.toLowerCase().includes(this.value.toLowerCase().trim()))"><p class="muted"><strong>Total vehicles: '.$totalVehicles.'</strong> &nbsp;|&nbsp; <strong>Showing: '.count($vehicles).'</strong>'.($filters?' ('.$this->esc(implode(', ',$filters)).')':'').'</p><table id="vehicle-table"><thead><tr><th>#</th><th>Vehicle Reg</th><th>Vehicle Type</th><th>Status</th><th>Action</th></tr></thead><tbody>'.$rows.'</tbody></table></div><div class="card"><h2>Import / Export vehicles</h2><p class="muted">Upload an .xlsx file with two columns: Vehicle Reg and Vehicle Type (Diesel, EV, Petrol or Hybrid). Existing registrations are skipped.</p><form method="post" enctype="multipart/form-data" class="row"><input type="hidden" name="_token" value="'.$token.'"><input type="hidden" name="action" value="import"><input type="file" name="file" accept=".xlsx" required><button>Import Excel</button></form><p><a class="button" href="/vehicles/export'.$exportQuery.'">Export Excel (.xlsx)</a></p></div></main></body></html>';
        return new Response($html);
    }

    #[Route('/vehicles/export', name: 'vehicle_export', methods: ['GET'])]
    public function export(Request $request, Connection $db): Response {
        $this->access();
        $requested=$request->query->all()['type']??[];
        if (is_string($requested)) $requested=[$requested];
        if (!is_array($requested)) $requested=[];
        $filters=array_values(array_intersect(self::TYPES,$requested));
        $state=(string)$request->query->get('state','all');
        if (!in_array($state,['all','active','grounded'],true)) $state='all';
        $placeholders=implode(',',array_fill(0,count($filters),'?'));
        $where=[];$params=[];if($filters){$where[]='vehicle_type IN ('.$placeholders.')';$params=$filters;}if($state!=='all'){$where[]='grounded = ?';$params[]=$state==='grounded'?1:0;}
        $rows=$db->fetchAllAssociative('SELECT registration,vehicle_type FROM vehicle'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY registration',$params);
        $values=[['Vehicle Reg','Vehicle Type']];
        foreach($rows as $row) $values[]=[$row['registration'],$row['vehicle_type']];
        $file=tempnam(sys_get_temp_dir(),'vehicle_xlsx_');
        $zip=new \ZipArchive();
        if ($zip->open($file,\ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Cannot generate Excel file.');
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Vehicles" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/></cols><sheetData>';
        foreach($values as $i=>$row){$xml.='<row r="'.($i+1).'">';foreach($row as $j=>$value){$column=$j===0?'A':'B';$xml.='<c r="'.$column.($i+1).'" t="inlineStr"><is><t>'.htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$xml.='</row>';}
        $xml.='</sheetData><autoFilter ref="A1:B'.count($values).'"/></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml);
        $zip->close();
        $response=new Response(file_get_contents($file),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="dulogix-vehicles.xlsx"','Cache-Control'=>'no-store']);
        unlink($file);
        return $response;
    }

    private function readXlsx(string $path): array {
        $zip=new \ZipArchive();
        if ($zip->open($path)!==true) throw new \RuntimeException('Invalid Excel workbook.');
        try {
            if ($zip->numFiles>150) throw new \RuntimeException('Workbook is too large.');
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet===false || strlen($sheet)>3_000_000) throw new \RuntimeException('First worksheet not found or too large.');
            $shared=[];
            $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml!==false) {
                if(strlen($sharedXml)>2_000_000) throw new \RuntimeException('Shared strings too large.');
                $doc=new \DOMDocument();$doc->loadXML($sharedXml,LIBXML_NONET);
                foreach($doc->getElementsByTagNameNS('*','si') as $si) $shared[]=trim($si->textContent);
            }
            $doc=new \DOMDocument();$doc->loadXML($sheet,LIBXML_NONET);
            $rows=[];
            foreach($doc->getElementsByTagNameNS('*','row') as $row) {
                $values=['',''];
                foreach($row->getElementsByTagNameNS('*','c') as $cell) {
                    $ref=$cell->getAttribute('r');$col=preg_replace('/[0-9]/','',$ref);
                    $index=$col==='A'?0:($col==='B'?1:-1);
                    if($index<0)continue;
                    $value='';
                    foreach($cell->childNodes as $child) if($child instanceof \DOMElement && in_array($child->localName,['v','is'],true)) $value=$child->textContent;
                    if($cell->getAttribute('t')==='s') $value=$shared[(int)$value]??'';
                    $values[$index]=trim($value);
                }
                $rows[]=$values;
            }
            return $rows;
        } finally { $zip->close(); }
    }
}
