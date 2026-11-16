<?php
namespace App\Controller;

final class LanePlanReader
{
    public function read(string $path, bool $routesSheet=false): array
    {
        $zip=new \ZipArchive();
        if ($zip->open($path)!==true) throw new \InvalidArgumentException('Invalid Excel file.');
        try {
            $load=static function(string $name) use($zip): \SimpleXMLElement {
                $contents=$zip->getFromName($name);
                if($contents===false)throw new \InvalidArgumentException('Missing Excel component: '.$name);
                $xml=simplexml_load_string($contents);
                if(!$xml)throw new \InvalidArgumentException('Invalid Excel XML.');
                return $xml;
            };
            $book=$load('xl/workbook.xml');
            $book->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $relId=null;
            foreach($book->xpath('//m:sheet') as $tab)if(strcasecmp((string)$tab['name'],$routesSheet?'Routes':'LANE PLAN')===0){
                $attr=$tab->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relId=(string)$attr['id'];break;
            }
            if(!$relId)throw new \InvalidArgumentException('Required worksheet tab missing.');
            $rels=$load('xl/_rels/workbook.xml.rels');$target=null;
            foreach($rels->children() as $rel)if((string)$rel['Id']===$relId){$target=(string)$rel['Target'];break;}
            if(!$target)throw new \InvalidArgumentException('LANE PLAN tab reference missing.');
            $target=ltrim($target,'/');
            if(!str_starts_with($target,'xl/'))$target='xl/'.$target;
            if(str_contains($target,'..'))throw new \InvalidArgumentException('Invalid worksheet path.');
            $strings=[];
            if($zip->locateName('xl/sharedStrings.xml')!==false){
                $shared=$load('xl/sharedStrings.xml');
                $shared->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach($shared->xpath('//m:si') as $item){$item->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$strings[]=implode('',array_map(static fn($n)=>(string)$n,$item->xpath('.//m:t'))); }
            }
            $sheet=$load($target);
            $sheet->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $records=[];$header=true;
            foreach($sheet->xpath('//m:sheetData/m:row') as $row){
                $row->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cells=[];
                foreach($row->xpath('./m:c') as $cell){
                    $cell->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    preg_match('/^[A-Z]+/',(string)$cell['r'],$m);
                    $value=(string)($cell->v??'');
                    if((string)$cell['t']==='s')$value=$strings[(int)$value]??'';
                    if((string)$cell['t']==='inlineStr')$value=implode('',array_map(static fn($n)=>(string)$n,$cell->xpath('.//m:t')));
                    $cells[$m[0]??'']=trim($value);
                }
                if($header){
                    foreach($routesSheet?['A'=>'ROUTE CODE','C'=>'TRANSPORTER ID']:['C'=>'COLOR','D'=>'HOLDING LANE TIME','G'=>'STG. LOCATION','H'=>'ROUTE'] as $col=>$name)
                        if(strtoupper($cells[$col]??'')!==$name)throw new \InvalidArgumentException('Unexpected LANE PLAN header in column '.$col);
                    $header=false;continue;
                }
                $id=strtoupper(trim($cells[$routesSheet?'C':'H']??''));
                if($routesSheet){
                    $route=trim($cells['A']??'');
                    if($route===''||$id==='')continue;
                    $names=array_map('trim',explode('|',(string)($cells['D']??'')));
                    foreach(explode('|',$id) as $position=>$personId){
                        $personId=trim($personId);
                        if(!preg_match('/^[A-Z0-9]{8,40}$/',$personId))continue;
                        if(isset($records[$personId]))throw new \InvalidArgumentException('Duplicate Transporter ID: '.$personId);
                        $records[$personId]=['route'=>substr($route,0,60),'name'=>substr($names[$position]??($names[0]??''),0,120)];
                    }
                    continue;
                }
                if($id==='')continue;
                if(isset($records[$id]))throw new \InvalidArgumentException('Duplicate route: '.$id);
                $records[$id]=['color'=>substr($cells['C']??'',0,30),'holding_time'=>substr($cells['D']??'',0,30),'stg_location'=>substr($cells['G']??'',0,40)];
            }
            if(!$records)throw new \InvalidArgumentException('No valid routes found in spreadsheet.');
            return $records;
        }finally{$zip->close();}
    }
}
