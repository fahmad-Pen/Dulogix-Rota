(()=>{
'use strict';
const panel=document.getElementById('rota-deadline-panel');
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmt=s=>new Date(s).toLocaleString('en-GB',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit',timeZone:'Europe/London'});
const names={broadcast:'Initial availability broadcast',outstanding:'Final availability broadcast',availability:'Driver availability deadline',completion:'Complete next week’s rota',distribution:'Send individual rota cards'};
const order=['broadcast','outstanding','availability','completion','distribution'];
let data;
let loadSequence=0;
const selectedWeek=()=>monday().toISOString().slice(0,10);
window.addEventListener('rota-week-changed',()=>load());
const record=key=>key==='broadcast'?data.broadcastSent:key==='outstanding'?data.followupSent:data.checks?.[key];
function render(){
 const now=Date.now(),pending=data.outstanding.length+data.partial.length;
 let rows='';
 for(const key of order){
  const v=data.deadlines[key];if(!v?.enabled)continue;
  const rec=record(key),due=new Date(v.at).getTime(),late=rec&&new Date(rec.at).getTime()>due;
  const status=rec?(late?'Completed late':'Completed'):now>due?'Overdue':due-now<=data.warningHours*3600000?'Due soon':'Scheduled';
  const locked=false;
  rows+='<div style="display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #e4eaf1"><input type="checkbox" data-check="'+key+'" '+(rec?'checked ':'')+'style="width:18px;height:18px;margin-top:3px"><div><strong>'+(rec?'✅':now>due?'🔴':'📅')+' '+esc(names[key])+'</strong> <small>· '+fmt(v.at)+'</small><div style="font-size:12px;color:#62758a">'+status+(rec?' · '+esc(rec.manager)+' · '+fmt(rec.at):locked?' · Available at scheduled time':'')+'</div></div></div>';
 }
 let copies='';
 for(const key of ['broadcast','outstanding'])if(data.deadlines[key]?.enabled)copies+='<button type="button" data-copy="'+key+'" style="margin:8px 8px 8px 0">📋 Copy '+(key==='broadcast'?'initial message':'final reminder')+'</button>';
 panel.innerHTML='<h2>📅 Weekly actions · rota commencing '+esc(data.week)+'</h2><p><strong>'+data.submitted+' / '+data.total+'</strong> drivers submitted availability · <strong>'+pending+'</strong> outstanding</p>'+rows+copies+'<p class="note">Tick when completed. WhatsApp messages are sent manually; Dulogix records the manager and time.</p><details><summary>View '+pending+' drivers awaiting full availability</summary><div style="padding:10px">'+(pending?[...data.outstanding,...data.partial].map(d=>'<div>'+esc(d.name)+' — '+(d.days?d.days+'/7 days':'Not started')+'</div>').join(''):'Everyone has submitted.')+'</div></details>';
 panel.querySelectorAll('[data-check]').forEach(box=>box.onchange=async()=>{
  const key=box.dataset.check,checked=box.checked;
  if(!confirm(checked?'Confirm '+names[key]+' is completed?':'Remove completion for '+names[key]+'?')){box.checked=!checked;return;}
  const broadcast=key==='broadcast'||key==='outstanding';
  const early=Date.now()<new Date(data.deadlines[key].at).getTime();
  const endpoint=broadcast?'/api/rota/deadlines/broadcast-sent':'/api/rota/deadlines/check';
  const body=new URLSearchParams(broadcast?{stage:key,checked:checked?'1':'0',week:data.week}:{action:key,checked:checked?'1':'0',week:data.week});
  try{
   const r=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':document.querySelector('meta[name="rota-csrf"]').content,'Content-Type':'application/x-www-form-urlencoded'},body});
   if(!r.ok){const e=await r.json().catch(()=>({}));throw Error(e.error||'Could not save');}
  }catch(e){alert(e.message);}
  await load();
 });
 panel.querySelectorAll('[data-copy]').forEach(btn=>btn.onclick=async()=>{
  const key=btn.dataset.copy,deadline=fmt(data.deadlines.availability.at);
  const msg=key==='broadcast'?data.message:'🔔 DULOGIX – Final Availability Reminder\n\nPlease submit your availability for next week before the deadline.\n\n📅 Submit here: https://app.dulogix.co.uk/driver/availability\n\n✅ If you have already submitted your availability, please ignore this reminder. Thank you!';
  const content=msg+'\n\nDeadline: '+deadline+' (UK time).';
  try{await navigator.clipboard.writeText(content);alert('Message copied. Send in WhatsApp, then tick the checkbox.');}
  catch(e){const area=document.createElement('textarea');area.value=content;panel.append(area);area.select();alert('Copy the selected message.');}
 });
}
async function load(){const seq=++loadSequence;const week=selectedWeek();panel.innerHTML='<strong>Weekly actions</strong><p>Loading selected week…</p>';try{const r=await fetch('/api/rota/deadlines?week='+encodeURIComponent(week),{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);const result=await r.json();if(seq!==loadSequence||week!==selectedWeek())return;data=result;render();}catch(e){if(seq===loadSequence)panel.innerHTML='<h2>Weekly actions</h2><p>Could not load actions. Please refresh.</p>';}}
load();setInterval(load,120000);
})();
