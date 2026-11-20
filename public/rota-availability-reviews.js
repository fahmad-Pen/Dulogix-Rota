(()=>{
'use strict';
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let pending=[];
const tbody=()=>document.querySelector('#drivers tbody');
function decorate(){
 const body=tbody();if(!body)return;
 body.querySelectorAll('.availability-review-row').forEach(el=>el.remove());
 body.querySelectorAll('.availability-change-marker').forEach(el=>el.remove());
 body.querySelectorAll('tr.avail').forEach(tr=>{tr.classList.remove('availability-review-highlight');tr.querySelectorAll('.availability-review-changed-cell').forEach(cell=>cell.classList.remove('availability-review-changed-cell'));});
 for(const p of pending){
  const driver=Array.from(body.querySelectorAll('tr.avail')).find(tr=>tr.querySelector('b')?.textContent.trim()===p.name);
  if(!driver)continue;
  driver.classList.add('availability-review-highlight');
  const cells=driver.querySelectorAll('td');
  const changedDays=new Set(p.changes.map(c=>['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].indexOf(c.day)));
  for(const day of changedDays){const cell=cells[2+day];if(cell){cell.classList.add('availability-review-changed-cell');const marker=document.createElement('span');marker.className='availability-change-marker';marker.textContent=' 🔔';marker.title='Availability changed — review required';cell.append(marker);}}
  const row=document.createElement('tr');row.className='availability-review-row';const td=document.createElement('td');td.colSpan=10;td.className='availability-review-detail-cell';
  const summary=document.createElement('details');summary.innerHTML='<summary class="availability-review-summary">🔔 Availability changed — Review required · '+p.count+' change'+(p.count===1?'':'s')+'</summary><div class="availability-review-content">'+p.changes.map(c=>'<div class="availability-review-change">'+esc(c.day)+': '+esc(c.old)+' → <strong>'+esc(c.new)+'</strong> <small>('+new Date(c.at).toLocaleString('en-GB')+')</small></div>').join('')+'<p class="availability-review-help">Check the allocation cells above, make any necessary changes, then mark reviewed. This does not approve availability.</p><button type="button" class="review-driver-availability" data-driver-key="'+esc(p.driverKey)+'">✓ Mark reviewed</button></div>';
  td.append(summary);row.append(td);
  // Keep the notification below the complete driver record, including Status.
  // The Status row can follow Allocation directly or after other inline rows.
  let end=driver.nextElementSibling;
  while(end && !end.classList.contains('status') && !end.classList.contains('avail'))end=end.nextElementSibling;
  if(end?.classList.contains('status'))end.after(row);
  else (driver.nextElementSibling||driver).after(row);
 }
}
async function load(){
 try{const r=await fetch('/api/rota/availability-reviews',{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);const data=await r.json();pending=data.week===document.querySelector('#week-calendar')?.value?data.pending:[];window.rotaAvailabilityChangedKeys=new Set(pending.map(p=>p.driverKey));if(document.querySelector('#filter')?.value==='availability-changed')document.querySelector('#filter').dispatchEvent(new Event('change'));decorate();}
 catch(e){console.warn('Availability notifications unavailable',e);}
}
document.querySelector('#drivers').addEventListener('click',async e=>{
 const btn=e.target.closest('.review-driver-availability');if(!btn)return;
 const p=pending.find(x=>x.driverKey===btn.dataset.driverKey);if(!p)return;
 if(!confirm('Have you reviewed '+p.name+'’s availability changes and checked their allocations?'))return;
 btn.disabled=true;
 try{const r=await fetch('/api/rota/availability-reviews/'+p.driverKey.slice(7),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="rota-csrf"]').content},body:JSON.stringify({latestId:p.latestId})});if(!r.ok){const raw=await r.text();let d={};try{d=JSON.parse(raw)}catch(e){}throw Error(d.error||('Review failed (HTTP '+r.status+'). Please refresh and try again.'));}await load();}
 catch(err){alert(err.message);await load();}
});
const observer=new MutationObserver(()=>{if(!document.querySelector('#drivers tbody .availability-review-row')&&pending.length)decorate();});
observer.observe(tbody(),{childList:true});
window.addEventListener('rota-week-changed',()=>{pending=[];window.rotaAvailabilityChangedKeys=new Set();load();});
window.refreshAvailabilityReviews=load;
load();setInterval(load,60000);
})();