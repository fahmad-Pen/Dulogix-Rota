<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserExcelController extends AbstractController
{
    private const HEADERS=['Username','Full Name','Email','Country Code','Phone','Account Type','Operational Type','Manager Permissions','Password Suffix'];
    private function requireAdmin():void{$this->denyAccessUnlessGranted('ROLE_ADMIN');}
    private static function escape(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_XML1,'UTF-8');}
    private static function excelColumn(int $i):string{$s='';for($i++;$i;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;return $s;}
    private static function rowXml(array $cells,int $row):string{
        $xml='<row r="'.$row.'">';
        foreach($cells as $i=>$v){
            $value=(string)($v??'');
            if(preg_match('/^[=+\-@\t\r]/u',$value) && !preg_match('/^\+[1-9][0-9]{0,3}$/',$value))$value="'".$value;
            $xml.='<c r="'.self::excelColumn($i).$row.'" t="inlineStr"><is><t xml:space="preserve">'.self::escape($value).'</t></is></c>';
        }
        return $xml.'</row>';
    }
    #[Route('/admin/users/excel/export',name:'admin_users_excel_export',methods:['GET'])]
    public function export(EntityManagerInterface $em):Response{
        $this->requireAdmin();
        $rows=[self::HEADERS];
        foreach($em->getRepository(User::class)->findBy([],['id'=>'ASC']) as $user){
            $role=in_array('ROLE_ADMIN',$user->getRoles(),true)?'Administrator':(in_array('ROLE_MANAGER',$user->getRoles(),true)?'Manager':'Driver');
            $phone=$user->getPhone()??'';$code='';$local=$phone;
            foreach(['+44','+1','+91','+92','+353','+971'] as $prefix)if(str_starts_with($phone,$prefix)){$code=$prefix;$local=substr($phone,strlen($prefix));break;}
            $rows[]=[$user->getUsername(),$user->getDisplayName(),$user->getEmail(),$code,$local,$role,$role==='Driver'?$user->getDriverType():'N/A',$role==='Manager'?implode(',',$user->getSections()):'N/A',''];
        }
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="17"/><cols><col min="1" max="9" width="23" customWidth="1"/></cols><sheetData>';
        foreach($rows as $i=>$row)$xml.=self::rowXml($row,$i+1);
        $xml.='</sheetData></worksheet>';
        $zip=new \ZipArchive();$file=tempnam(sys_get_temp_dir(),'du-users-');
        if($file===false||$zip->open($file,\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Unable to create Excel export');
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Users" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml);$zip->close();
        $contents=file_get_contents($file);unlink($file);
        return new Response($contents,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="dulogix-users-template.xlsx"','Cache-Control'=>'private, no-store']);
    }
    private static function readXlsx(string $path):array{
        $zip=new \ZipArchive();if($zip->open($path)!==true)throw new \RuntimeException('Not a valid XLSX file.');
        try{
            $shared=[];$strings=$zip->getFromName('xl/sharedStrings.xml');
            if($strings!==false){
                $doc=new \DOMDocument();if(!@$doc->loadXML($strings,LIBXML_NONET))throw new \RuntimeException('Invalid Excel strings.');
                $xp=new \DOMXPath($doc);$xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach($xp->query('//x:si') as $si){$str='';foreach($xp->query('.//x:t',$si) as $t)$str.=$t->textContent;$shared[]=$str;}
            }
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');if($sheet===false||strlen($sheet)>5000000)throw new \RuntimeException('Excel first sheet missing or too large.');
            $doc=new \DOMDocument();if(!@$doc->loadXML($sheet,LIBXML_NONET))throw new \RuntimeException('Invalid Excel worksheet.');
            $xp=new \DOMXPath($doc);$xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$rows=[];
            foreach($xp->query('//x:sheetData/x:row') as $row){
                $number=(int)$row->getAttribute('r');if($number<1||$number>2001)throw new \RuntimeException('Maximum 2000 Excel rows.');
                $cells=array_fill(0,9,'');
                foreach($xp->query('./x:c',$row) as $cell){
                    preg_match('/^[A-Z]+/',$cell->getAttribute('r'),$match);$column=0;
                    foreach(str_split($match[0]??'') as $char)$column=$column*26+ord($char)-64;
                    if($column<1||$column>9)continue;
                    $type=$cell->getAttribute('t');$v=$xp->query('./x:v',$cell)->item(0);$inline=$xp->query('./x:is',$cell)->item(0);
                    $value=$type==='inlineStr'?$inline?->textContent:($v?->textContent??'');
                    if($type==='s')$value=$shared[(int)$value]??'';
                    if($type==='e'||$xp->query('./x:f',$cell)->length)throw new \RuntimeException('Formula/error cells are not supported (row '.$number.').');
                    $cells[$column-1]=trim((string)$value);
                }
                $rows[$number]=$cells;
            }
            return $rows;
        }finally{$zip->close();}
    }
    #[Route('/admin/users/excel/import',name:'admin_users_excel_import',methods:['POST'])]
    public function import(Request $request,EntityManagerInterface $em,UserPasswordHasherInterface $hasher):Response{
        $this->requireAdmin();
        if(!$this->isCsrfTokenValid('import_users_excel',(string)$request->request->get('_token')))throw $this->createAccessDeniedException('Invalid CSRF token');
        $file=$request->files->get('excel_file');
        if(!$file||!$file->isValid()||strtolower($file->getClientOriginalExtension())!=='xlsx'||$file->getSize()>2000000)return new Response('Select a valid XLSX file under 2 MB.',422);
        try{$rows=self::readXlsx($file->getPathname());}catch(\Throwable $e){return new Response('Excel import failed: '.htmlspecialchars($e->getMessage()),422);}
        if(($rows[1]??null)!==self::HEADERS)return new Response('Excel columns do not match the exported template. Keep the header row unchanged.',422);
        $existing=[];$existingEmails=[];foreach($em->getRepository(User::class)->findAll() as $u){$existing[mb_strtolower($u->getUsername())]=true;$existingEmails[mb_strtolower($u->getEmail())]=true;}
        $seen=[];$seenEmails=[];$pending=[];$errors=[];$skipped=0;
        foreach($rows as $line=>$r){
            if($line===1||count(array_filter($r,static fn($v)=>$v!==''))===0)continue;
            [$username,$name,$email,$code,$phone,$role,$type,$permissions,$suffix]=$r;
            $code=ltrim(trim($code),"' "); // Accept Excel's text-entry apostrophe from earlier exports.
            $username=mb_strtolower($username);$email=mb_strtolower($email);
            if(isset($existing[$username])){$skipped++;continue;}
            $issues=[];
            if(!preg_match('/^[a-z0-9._-]{3,80}$/',$username))$issues[]='invalid username';
            if(isset($seen[$username]))$issues[]='duplicate username in file';
            if($name===''||mb_strlen($name)>120)$issues[]='full name required (max 120)';
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))$issues[]='invalid email';
            if(isset($existingEmails[$email])||isset($seenEmails[$email]))$issues[]='email already in use';
            if(!preg_match('/^\+[1-9][0-9]{0,3}$/',$code))$issues[]='country code must look like +44';
            $digits=preg_replace('/[\s().-]+/','',$phone);
            $fullPhone=$code.ltrim($digits,'0');
            if($phone===''||!ctype_digit($digits)||!preg_match('/^\+[1-9][0-9]{7,14}$/',$fullPhone))$issues[]='invalid phone';
            $roleCode=['Driver'=>'ROLE_DRIVER','Manager'=>'ROLE_MANAGER','Administrator'=>'ROLE_ADMIN'][$role]??null;
            if(!$roleCode)$issues[]='invalid account type';
            if($role==='Driver'&&!in_array($type,['DRIVER','JUMPER'],true))$issues[]='operational type must be DRIVER or JUMPER';
            if($role!=='Driver'&&$type!=='N/A')$issues[]='operational type must be N/A';
            $sections=$permissions==='N/A'?[]:array_map('trim',explode(',',$permissions));
            if($role==='Manager'&&(!$sections||array_diff($sections,array_keys(SectionAccess::SECTIONS))))$issues[]='manager permissions must be rota, vehicle and/or operations';
            if($role!=='Manager'&&$permissions!=='N/A')$issues[]='manager permissions must be N/A';
            if($suffix===''||($role==='Driver'?!DriverPasswordPolicy::valid($username.$suffix):strlen($username.$suffix)<12))$issues[]=$role==='Driver'?'password must be 8+ characters with a number and special character':'password must be at least 12 characters';
            if($issues){$errors[]='Row '.$line.': '.implode('; ',$issues);continue;}
            $seen[$username]=true;$seenEmails[$email]=true;
            $pending[]=[$username,$name,$email,$fullPhone,$roleCode,$role==='Driver'?$type:'NONE',$sections,$username.$suffix];
        }
        if($errors)return new Response('<h2>Import not performed</h2><p>No accounts were created. Correct these errors and try again:</p><ul><li>'.implode('</li><li>',array_map(static fn($s)=>htmlspecialchars($s,ENT_QUOTES),$errors)).'</li></ul><a href="/admin/users">Back to User Management</a>',422,['Content-Type'=>'text/html; charset=UTF-8']);
        foreach($pending as [$username,$name,$email,$phone,$role,$type,$sections,$password]){
            $user=(new User())->setUsername($username)->setDisplayName($name)->setEmail($email)->setPhone($phone)->setRoles([$role])->setDriverType($type)->setSections($role==='ROLE_MANAGER'?$sections:[]);
            $user->setPassword($hasher->hashPassword($user,$password));$em->persist($user);
        }
        $em->flush();
        $this->addFlash('user_import',count($pending).' users imported; '.$skipped.' existing usernames skipped.');
        return $this->redirectToRoute('admin_users');
    }
}
