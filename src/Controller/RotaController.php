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
        if (!$this->isGranted('ROLE_OPERATIONS') || !SectionAccess::allowed($this->getUser(), 'rota')) return $this->redirectToRoute($this->isGranted('ROLE_DRIVER') ? 'driver_availability' : 'account_profile');
        $html = <<<'HTML'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dulogix | Weekly Rota</title><link rel="icon" type="image/png" href="/favicon.png">
<style>
:root{font-family:system-ui,sans-serif;color:#19283c;background:#f4f7fb}*{box-sizing:border-box}body{margin:0}header{background:#142d4e;color:white;padding:20px 28px;display:flex;justify-content:space-between;align-items:center}h1{font-size:22px;margin:0}header small{color:#bdd5ef}main{padding:22px;max-width:1600px;margin:auto}.toolbar,.card{background:white;border:1px solid #dbe3ee;border-radius:12px;padding:16px;margin-bottom:16px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.week{display:flex;align-items:center;gap:12px}.week button,button{border:1px solid #b8c9dd;background:white;border-radius:7px;padding:9px 12px;cursor:pointer}.week strong{min-width:210px;text-align:center}input,select{padding:8px;border:1px solid #c4d1df;border-radius:6px;max-width:100%}.pill{background:#fff3d4;color:#795700;border-radius:20px;padding:6px 10px;font-size:12px}.scroll{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1050px}th,td{padding:10px 8px;border:1px solid #e1e8f1;text-align:center;font-size:13px}th:first-child,td:first-child{text-align:left;min-width:155px;position:sticky;left:0;background:white}thead th{background:#edf3fb}td.label{text-align:left;font-weight:600}tr.avail{background:#f1f9f3}tr.alloc{background:#eef5ff}tr.avail td:first-child{background:#f1f9f3}tr.alloc td:first-child{background:#eef5ff}.yes{color:#147446;font-weight:700}.no{color:#a23b40;font-weight:700}.alert{color:#b24b00;font-weight:700}.send{background:#147b5a;color:white;border:0;white-space:nowrap}.muted{color:#6c7c8e}.metric{font-weight:700}.short{color:#b3303a}.surplus{color:#13784e}section h2{font-size:17px;margin:0 0 12px}.note{color:#6b7583;font-size:13px}footer{padding:0 22px 24px;color:#6b7583;font-size:12px}@media(max-width:600px){header{padding:16px}main{padding:10px}.toolbar{align-items:stretch}.week{justify-content:center}}
</style><link rel="stylesheet" href="/rota-compact.css?v=8"></head><body>
<header><h1><a href="/"><img src="/dulogix-logo.png" alt="Dulogix" style="height:39px;width:auto;vertical-align:middle;background:white;border-radius:5px;padding:3px"></a></h1>__DULOGIX_NAV__</header>
<main>
<section id="rota-deadline-panel" class="card" aria-live="polite"><strong>Weekly actions</strong><p>Loading deadlines…</p></section>
<div class="toolbar"><div class="week"><button id="prev" aria-label="Previous week">◀</button><strong id="week"></strong><button id="next" aria-label="Next week">▶</button></div><span id="lock" class="pill"></span></div>
<details class="card planning-card" id="weekly-operations-planning"><summary>Weekly operations planning</summary><div class="scroll"><table id="summary"><thead></thead><tbody></tbody></table></div><p class="note">Planning targets are provisional until configured by operations.</p></details>
<section class="card"><div class="driver-heading"><h2>Driver availability and allocations <span class="pill">Driver accounts</span></h2><div class="driver-filters"><input id="search" placeholder="Search driver name" aria-label="Search driver name"> <select id="filter"><option value="all">All drivers</option><option value="conflict">Conflicts</option><option value="pending">Pending changes</option></select></div></div><div class="scroll"><table id="drivers"><thead></thead><tbody></tbody></table></div><p class="note">Driver availability and management allocations are recorded separately.</p></section>
</main><footer>Dulogix · Operations</footer>
<script>
const days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
const drivers=[];
const start=new Date(Date.UTC(2026,9,26));let offset=0;
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
</script><script>try{const p=document.getElementById('weekly-operations-planning');p.open=localStorage.getItem('dulogix-weekly-planning-open')==='true';p.addEventListener('toggle',()=>localStorage.setItem('dulogix-weekly-planning-open',String(p.open)));}catch(e){}</script><script src="/rota-demo.js?v=17" defer></script><script src="/rota-share.js?v=5" defer></script><script src="/rota-whatsapp.js?v=8" defer></script><script src="/rota-deadlines.js?v=8" defer></script><script src="/rota-availability-reviews.js?v=4" defer></script></body></html>
HTML;
        $token = htmlspecialchars($csrfManager->getToken('rota_edit')->getValue(), ENT_QUOTES, 'UTF-8');
        if (!$this->isGranted('ROLE_ADMIN')) { $html = str_replace('<a href="/admin/settings" style="color:#fff;margin-right:14px;font-size:12px">⚙ Admin Settings</a><a href="/driver/availability?preview=1" style="color:#fff;margin-right:14px;font-size:12px">Driver preview</a>', '', $html); }
        $html = str_replace('__DULOGIX_NAV__', SectionAccess::navigation($this->getUser()), $html);
        $html = str_replace('</head>', SectionAccess::CSS.'</head>', $html);
        return new Response(str_replace('</head>', '<meta name="rota-csrf" content="'.$token.'"></head>', $html));
    }
}
