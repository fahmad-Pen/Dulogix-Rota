<?php
require dirname(__DIR__).'/vendor/autoload.php';
$path=tempnam(sys_get_temp_dir(),'lane').'.xlsx';
$zip=new \ZipArchive();
if($zip->open($path,\ZipArchive::CREATE)!==true)throw new \RuntimeException('Zip create failed');
$zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="HOME" sheetId="1" r:id="rId1"/><sheet name="LANE PLAN" sheetId="2" r:id="rId2"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
$names=['A'=>'DULO','B'=>'WAVE','C'=>'COLOR','D'=>'HOLDING LANE TIME','G'=>'STG. LOCATION','H'=>'ROUTE','I'=>'ASSIGNED DA'];
$header='';foreach($names as $col=>$value)$header.='<c r="'.$col.'1" t="inlineStr"><is><t>'.$value.'</t></is></c>';
$row='';foreach(['C'=>'YELLOW','D'=>'10:30','G'=>'A.11','H'=>'CA_A215','I'=>'A17ZQBVEHI48YL'] as $col=>$value)$row.='<c r="'.$col.'2" t="inlineStr"><is><t>'.$value.'</t></is></c>';
$zip->addFromString('xl/worksheets/sheet2.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$header.'</row><row r="2">'.$row.'</row></sheetData></worksheet>');
$zip->close();
try {
 $rows=(new \App\Controller\LanePlanReader())->read($path);
 if(count($rows)!==1 || $rows['A17ZQBVEHI48YL']['route']!=='CA_A215')throw new \RuntimeException('Reader did not match sample data');
 echo "PASS: LANE PLAN Excel reader with named worksheet and inline strings\n";
}finally{unlink($path);}
