<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RotaController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfManager): Response
    {
        if (!$this->getUser()) return $this->redirectToRoute('app_login');
        if (!$this->isGranted('ROLE_OPERATIONS') || !SectionAccess::allowed($this->getUser(), 'rota')) return $this->redirectToRoute($this->isGranted('ROLE_DRIVER') ? 'driver_daily_check' : 'account_profile');
        $html = <<<'HTML'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dulogix | Weekly Rota</title><link rel="icon" type="image/png" href="/favicon.png">
<style>
:root{font-family:system-ui,sans-serif;color:#19283c;background:#f4f7fb}*{box-sizing:border-box}body{margin:0}header{background:#142d4e;color:white;padding:20px 28px;display:flex;justify-content:space-between;align-items:center}h1{font-size:22px;margin:0}header small{color:#bdd5ef}main{padding:22px;max-width:1600px;margin:auto}.toolbar,.card{background:white;border:1px solid #dbe3ee;border-radius:12px;padding:16px;margin-bottom:16px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.week{display:flex;align-items:center;gap:12px}.week button,button{border:1px solid #b8c9dd;background:white;border-radius:7px;padding:9px 12px;cursor:pointer}.week strong{min-width:210px;text-align:center}input,select{padding:8px;border:1px solid #c4d1df;border-radius:6px;max-width:100%}.pill{background:#fff3d4;color:#795700;border-radius:20px;padding:6px 10px;font-size:12px}.scroll{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1050px}th,td{padding:10px 8px;border:1px solid #e1e8f1;text-align:center;font-size:13px}th:first-child,td:first-child{text-align:left;min-width:155px;position:sticky;left:0;background:white}thead th{background:#edf3fb}td.label{text-align:left;font-weight:600}tr.avail{background:#f1f9f3}tr.alloc{background:#eef5ff}tr.avail td:first-child{background:#f1f9f3}tr.alloc td:first-child{background:#eef5ff}.yes{color:#147446;font-weight:700}.no{color:#a23b40;font-weight:700}.alert{color:#b24b00;font-weight:700}.send{background:#147b5a;color:white;border:0;white-space:nowrap}.muted{color:#6c7c8e}.metric{font-weight:700}.short{color:#b3303a}.surplus{color:#13784e}section h2{font-size:17px;margin:0 0 12px}.note{color:#6b7583;font-size:13px}footer{padding:0 22px 24px;color:#6b7583;font-size:12px}@media(max-width:600px){header{padding:16px}main{padding:10px}.toolbar{align-items:stretch}.week{justify-content:center}}
@media print{@page{size:A3 landscape;margin:10mm}html,body,main,section,table,tr,th,td,select,input,.card,.toolbar,*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{font-size:10px}header,footer,.driver-filters,.week button,#export-week-pdf,.week-export .pill,button,.send,.note{display:none!important}main{padding:0!important;max-width:none!important}.toolbar,.card{border:0!important;box-shadow:none!important;padding:5px 0!important;margin:0 0 8px!important}.week{justify-content:flex-start!important}.week strong{min-width:0!important;text-align:left!important;font-size:16px}.scroll{overflow:visible!important}table{width:100%!important;min-width:0!important;table-layout:auto!important}th,td{font-size:9px!important;padding:4px 3px!important;white-space:normal!important}th:first-child,td:first-child{position:static!important;min-width:0!important}thead{display:table-header-group}tr{break-inside:avoid}details.planning-card:not([open])>summary{display:block}select,input{border:0!important;background:transparent!important;appearance:none!important;-webkit-appearance:none!important;font-size:9px!important;padding:0!important}#rota-deadline-panel{break-inside:avoid}summary{font-weight:bold!important}}.week-export{display:flex;align-items:center;gap:10px}@media(max-width:600px){.week-export{width:100%;justify-content:flex-end}}</style><link rel="stylesheet" href="/rota-compact.css?v=8"></head><body>
<header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" style="height:39px;width:auto;vertical-align:middle;background:white;border-radius:5px;padding:3px"></a></h1>__DULOGIX_NAV__</header>
<main>
<div class="toolbar"><div class="week"><button id="prev" aria-label="Previous week">◀</button><strong id="week"></strong><button id="next" aria-label="Next week">▶</button><label for="week-calendar" style="margin-left:12px;font-size:14px">Jump to week:</label><input id="week-calendar" type="date" aria-label="Choose a date to view its rota week" title="Choose any date; Dulogix will open the Monday–Sunday week containing it"></div><div class="week-export"><span id="lock" class="pill"></span><button type="button" id="export-week-pdf" title="Print the complete selected week or save it as a PDF">🖨 Export PDF</button></div></div>
<section id="rota-deadline-panel" class="card" aria-live="polite"><strong>Weekly actions</strong><p>Loading deadlines…</p></section>
<details class="card planning-card" id="weekly-operations-planning"><summary>Weekly operations planning</summary><div class="scroll"><table id="summary"><thead></thead><tbody></tbody></table></div><p class="note">Planning targets are provisional until configured by operations.</p></details>
<section class="card"><div class="driver-heading"><h2>Driver availability and allocations <span class="pill">Driver accounts</span></h2><div class="driver-filters"><input id="search" placeholder="Search driver name" aria-label="Search driver name"> <select id="filter"><option value="all">All drivers</option><option value="conflict">Conflicts</option><option value="pending">Pending changes</option><option value="not-submitted">Not submitted availability</option><option value="availability-changed">Availability changed — review required</option></select></div></div><div class="scroll"><table id="drivers"><thead></thead><tbody></tbody></table></div><p class="note">Driver availability and management allocations are recorded separately.</p></section>
</main><footer>Dulogix · Operations</footer>
<script>
const days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
const drivers=[];
const ukParts=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/London',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).filter(p=>p.type!=='literal').map(p=>[p.type,Number(p.value)]));const ukToday=new Date(Date.UTC(ukParts.year,ukParts.month-1,ukParts.day));const daysUntilNextMonday=7-((ukToday.getUTCDay()+6)%7);const start=new Date(ukToday.getTime()+daysUntilNextMonday*86400000);let offset=0;
function monday(){return new Date(start.getTime()+offset*604800000)}
function isoWeek(date){const d=new Date(date);d.setUTCDate(d.getUTCDate()+4-(d.getUTCDay()||7));const y=new Date(Date.UTC(d.getUTCFullYear(),0,1));return Math.ceil((((d-y)/86400000)+1)/7)}
function fmt(d){return d.toLocaleDateString('en-GB',{day:'numeric',month:'short',timeZone:'UTC'})}
function render(){const mon=monday(),sun=new Date(mon.getTime()+6*86400000),isPast=mon<start;document.getElementById('week').textContent='Week '+isoWeek(mon)+' · '+fmt(mon)+' – '+fmt(sun)+' '+sun.getUTCFullYear();document.getElementById('lock').textContent=isPast?'🔒 Historical week · locked':'Planning week';
const head='<tr><th>Planning field</th>'+days.map((d,i)=>'<th>'+d+'<br><small>'+fmt(new Date(mon.getTime()+i*86400000))+'</small></th>').join('')+'</tr>';document.querySelector('#summary thead').innerHTML=head;
const rows=[['Route Target','—'],['Current Demand Signal','—'],['ECP','—'],['Required drivers', [2,2,2,2,2,1,1]],['Available drivers',days.map((_,i)=>drivers.filter(d=>d.availability[i]).length)],['Allocated drivers',days.map((_,i)=>drivers.filter(d=>['IN','HD','JUMPER'].includes(d.allocation[i])).length)],['Availability surplus / shortfall',null],['Allocation surplus / shortfall',null],['DA Capacity',null],['Standby',null]];
const required=rows[3][1],available=rows[4][1],allocated=rows[5][1];rows[6][1]=days.map((_,i)=>available[i]-required[i]);rows[7][1]=days.map((_,i)=>allocated[i]-required[i]);
document.querySelector('#summary tbody').innerHTML=rows.map(([name,vals])=>'<tr><td class="label">'+name+'</td>'+days.map((_,i)=>{const val=Array.isArray(vals)?vals[i]:vals;const cls=String(name).includes('shortfall')?(val<0?'short':'surplus'):'';return '<td class="'+cls+'">'+(val===null||val==='—'?'—':val>0&&cls?'+'+val:val)+'</td>'}).join('')+'</tr>').join('');
const q=document.getElementById('search').value.toLowerCase(),filter=document.getElementById('filter').value;
document.querySelector('#drivers thead').innerHTML='<tr><th>Driver / type</th><th>Row</th>'+days.map(d=>'<th>'+d+'</th>').join('')+'<th>Action</th></tr>';
document.querySelector('#drivers tbody').innerHTML=drivers.filter(d=>d.name.toLowerCase().includes(q)).filter(d=>filter==='all'||(filter==='pending'&&d.pending)||(filter==='conflict'&&d.availability.some((a,i)=>!a&&d.allocation[i]!=='OFF'))).map(d=>'<tr class="avail"><td rowspan="2"><b>'+d.name+'</b><br><small>'+d.type+(d.pending?' · 🔔 Pending':'')+'</small></td><td>Availability</td>'+d.availability.map(a=>'<td class="'+(a?'yes':'no')+'">'+(a?'YES':'NO')+'</td>').join('')+'<td>—</td></tr><tr class="alloc"><td>Allocation</td>'+d.allocation.map((a,i)=>'<td class="'+(!d.availability[i]&&a!=='OFF'?'alert':'')+'">'+a+(!d.availability[i]&&a!=='OFF'?' ⚠':'')+'</td>').join('')+'<td><button class="send" disabled title="Available after secure implementation">📲 Send rota</button></td></tr>').join('');
}document.getElementById('prev').onclick=()=>{offset--;render()};document.getElementById('next').onclick=()=>{offset++;render()};document.getElementById('search').oninput=render;document.getElementById('filter').onchange=render;render();
</script><script>document.getElementById('export-week-pdf').addEventListener('click',()=>{const details=[...document.querySelectorAll('main details')];const previouslyOpen=details.map(el=>el.open);details.forEach(el=>el.open=true);const restore=()=>{details.forEach((el,i)=>el.open=previouslyOpen[i]);window.removeEventListener('afterprint',restore)};window.addEventListener('afterprint',restore);window.print()});</script><script>try{const p=document.getElementById('weekly-operations-planning');p.open=localStorage.getItem('dulogix-weekly-planning-open')==='true';p.addEventListener('toggle',()=>localStorage.setItem('dulogix-weekly-planning-open',String(p.open)));}catch(e){}</script><script>
(()=> {
 const bar=document.createElement('div');
 bar.setAttribute('aria-hidden','true');
 bar.style.cssText='position:fixed;top:0;z-index:9999;display:none;overflow:hidden;pointer-events:none;background:#e8eef6;border-bottom:2px solid #8ca6c3;box-shadow:0 2px 5px #0002';
 const strip=document.createElement('div');
 strip.style.cssText='display:flex;align-items:stretch;width:max-content';
 bar.appendChild(strip);document.body.appendChild(bar);
 function update(){
  const table=document.getElementById('drivers'),wrap=table?.parentElement,head=table?.querySelector('thead tr');
  if(!table||!wrap||!head)return;
  const rect=wrap.getBoundingClientRect(),headRect=head.getBoundingClientRect();
  const active=rect.top<0&&rect.bottom>headRect.height&&rect.width>0;
  bar.style.display=active?'block':'none';
  if(!active)return;
  bar.style.left=rect.left+'px';bar.style.width=rect.width+'px';
  bar.style.height=headRect.height+'px';
  const cells=[...head.cells],originals=[...strip.children];
  if(originals.length!==cells.length){strip.replaceChildren(...cells.map(()=>document.createElement('div')));}
  cells.forEach((cell,i)=>{
   const item=strip.children[i],width=cell.getBoundingClientRect().width;
   item.textContent=cell.textContent;
   item.style.cssText='box-sizing:border-box;flex:0 0 '+width+'px;width:'+width+'px;display:flex;align-items:center;justify-content:center;padding:5px 7px;background:#e8eef6;border-right:1px solid #d6e1ed;color:#29496f;font-weight:700;font-size:14px;white-space:nowrap';
  });
  strip.style.height=headRect.height+'px';
  strip.style.transform='translateX(-'+wrap.scrollLeft+'px)';
 }
 let pending=false;
 function schedule(){if(pending)return;pending=true;requestAnimationFrame(()=>{pending=false;update()})}
 window.addEventListener('scroll',schedule,{passive:true});
 window.addEventListener('resize',schedule,{passive:true});
 document.addEventListener('scroll',schedule,{passive:true,capture:true});
 document.addEventListener('DOMContentLoaded',schedule);
})();
</script><script src="/rota-demo.js?v=37" defer></script><script src="/rota-share.js?v=6" defer></script><script src="/rota-whatsapp.js?v=13" defer></script><script src="/rota-deadlines.js?v=9" defer></script><script src="/rota-availability-reviews.js?v=6" defer></script></body></html>
HTML;
        $token = htmlspecialchars($csrfManager->getToken('rota_edit')->getValue(), ENT_QUOTES, 'UTF-8');
        if (!$this->isGranted('ROLE_ADMIN')) { $html = str_replace('<a href="/admin/settings" style="color:#fff;margin-right:14px;font-size:12px">⚙ Admin Settings</a><a href="/driver/availability?preview=1" style="color:#fff;margin-right:14px;font-size:12px">Driver preview</a>', '', $html); }
        $html = str_replace('__DULOGIX_NAV__', SectionAccess::navigation($this->getUser()), $html);
        $html = str_replace('</head>', SectionAccess::CSS.'</head>', $html);
        return new Response(str_replace('</head>', '<meta name="rota-csrf" content="'.$token.'"></head>', $html));
    }
}
