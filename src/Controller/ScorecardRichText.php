<?php
namespace App\Controller;
final class ScorecardRichText {
 public static function sanitize(string $html): string {
  if ($html === '') return '';
  if (!preg_match('~</?[a-z][^>]*>~i', $html)) return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
  $dom = new \DOMDocument('1.0','UTF-8');
  $previous=libxml_use_internal_errors(true);
  $dom->loadHTML('<?xml encoding="UTF-8"><div id="safe-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();libxml_use_internal_errors($previous);
  $root=$dom->getElementById('safe-root');
  if (!$root) return htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $allowed=['div','p','br','b','strong','i','em','u','s','span','font','ul','ol','li'];
  $walk=function(\DOMNode $parent)use(&$walk,$allowed):void{
   foreach(iterator_to_array($parent->childNodes) as $child){
    if($child instanceof \DOMComment){$parent->removeChild($child);continue;}
    if(!$child instanceof \DOMElement)continue;
    $tag=strtolower($child->tagName);
    if(!in_array($tag,$allowed,true)){
     if(in_array($tag,['script','style','iframe','svg','object','form','img'],true)){$parent->removeChild($child);continue;}
     $walk($child);while($child->firstChild)$parent->insertBefore($child->firstChild,$child);$parent->removeChild($child);continue;
    }
    $style=$child->getAttribute('style');$color=$child->getAttribute('color');
    while($child->attributes->length)$child->removeAttributeNode($child->attributes->item(0));
    $safe=[];
    if($tag==='font'&&preg_match('/^#[0-9a-f]{6}$/i',$color))$safe[]='color:'.$color;
    foreach(explode(';',$style) as $declaration){
     if(!preg_match('/^\s*(color|background-color|font-size|text-align)\s*:\s*(.*?)\s*$/i',$declaration,$parts))continue;
     $property=strtolower($parts[1]);$value=strtolower($parts[2]);
     if(in_array($property,['color','background-color'],true)&&preg_match('/^(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|[a-z]{3,20})$/',$value))$safe[]=$property.':'.$value;
     if($property==='font-size'&&preg_match('/^(1[0-9]|2[0-8])px$/',$value))$safe[]=$property.':'.$value;
     if($property==='text-align'&&in_array($value,['left','center','right'],true))$safe[]=$property.':'.$value;
    }
    if($safe)$child->setAttribute('style',implode(';',$safe));
    $walk($child);
   }
  };
  $walk($root);$out='';foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);return $out;
 }
}
