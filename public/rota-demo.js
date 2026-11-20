/* Dulogix interactive operations rota. Edits are persisted through the authenticated API. */
(() => {
'use strict';
const root = document.querySelector('main');
const info = document.createElement('div');
info.className = 'toolbar';
info.innerHTML = '<strong>Rota planning</strong><span id="saveStatus" aria-live="polite">Ready</span><button id="undoEdit" type="button">↶ Undo</button><button id="unlockEdit" type="button" hidden>🔒 Unlock past week</button>';
root.insertBefore(info, root.firstChild.nextSibling);
const undoButton=info.querySelector('#undoEdit');
const driverSection=document.querySelector('#drivers')?.closest('section');
if(driverSection){const undoBar=document.createElement('div');undoBar.className='rota-driver-undo';undoBar.append(undoButton);driverSection.parentNode.insertBefore(undoBar,driverSection);}
// Rota layout styles are loaded from /rota-demo-extra.css.


document.querySelector('#drivers').parentElement.id='drivers-scroll';
// Show the two live surplus figures within each existing weekday heading.
// Do not change the original sticky table header or its column layout.
{
 const syncSurplus=()=>{
  // The server renders an empty thead; weekday cells appear only after render().
  // Query the current cells each time, rather than capturing an empty list.
  const weekdayHeaders=[...document.querySelectorAll('#drivers thead tr:first-child th')].slice(2,9);
  const rows=[...document.querySelectorAll('#summary tbody tr')].filter(tr=>/^Drivers (Availability|Allocation) surplus \/ shortfall$/i.test(tr.cells[0]?.textContent.trim()||''));
  weekdayHeaders.forEach((th,day)=>{
   let figures=th.querySelector('.rota-day-surplus');
   if(!figures){
    const layout=document.createElement('span');layout.className='rota-day-heading-layout';
    const date=document.createElement('span');date.className='rota-day-heading-date';
    while(th.firstChild)date.append(th.firstChild);
    figures=document.createElement('span');figures.className='rota-day-surplus';
    layout.append(date,figures);th.append(layout);
   }
   figures.replaceChildren();
   if(rows.length!==2)return;
   rows.forEach((row,index)=>{
    const source=row.cells[day+1];
    const value=document.createElement('span');
    value.className='rota-day-surplus-value';
    value.textContent=source?.textContent.trim()||'0';
    value.title=index===0?'Availability surplus / shortfall':'Allocation surplus / shortfall';
    if(source?.classList.contains('short')||Number(value.textContent)<0)value.classList.add('short');
    figures.append(value);
   });
  });
 };
 const summaryBody=document.querySelector('#summary tbody');
 if(summaryBody)new MutationObserver(syncSurplus).observe(summaryBody,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['class']});
 // render() replaces the entire driver thead on week changes and filters.
 // Reapply the figures whenever that header is rebuilt.
 const driverHead=document.querySelector('#drivers thead');
 if(driverHead)new MutationObserver(syncSurplus).observe(driverHead,{childList:true});
 syncSurplus();

}
// Daily filters are shared by the toolbar and clickable weekday headings.
let rotaDayFilter='all',rotaStatusFilter='all',rotaAllocationFilter='all';
const dailyFilters=document.createElement('div');
dailyFilters.className='rota-daily-filters';
dailyFilters.innerHTML='<label>Day <select id="rota-day-filter"><option value="all">All days</option>'+days.map((day,i)=>'<option value="'+i+'">'+day+'</option>').join('')+'</select></label><label>Show <select id="rota-status-filter"><option value="all">All drivers</option><option value="available-unallocated">Available but not allocated</option><option value="allocated-unavailable">Allocated but not available</option><option value="available-allocated">Available and allocated</option></select></label><button type="button" id="rota-clear-day-filter">Clear</button><span id="rota-daily-filter-count" aria-live="polite"></span>';
const allocationFilterLabel=document.createElement('label');
allocationFilterLabel.textContent='Allocation type ';
const allocationFilterSelect=document.createElement('select');
allocationFilterSelect.id='rota-allocation-filter';
allocationFilterLabel.append(allocationFilterSelect);
dailyFilters.querySelector('#rota-clear-day-filter').before(allocationFilterLabel);
function refreshAllocationFilter(){
 const previous=rotaAllocationFilter;
 allocationFilterSelect.innerHTML='<option value="all">All allocation types</option><option value="driver">Driver (working)</option><option value="unallocated">Unallocated</option>'+allocationOptions.map(o=>'<option value="'+safeText(o.code)+'">'+safeText(o.label||o.code)+'</option>').join('');
 allocationFilterSelect.value=previous;
 if(allocationFilterSelect.selectedIndex<0){rotaAllocationFilter='all';allocationFilterSelect.value='all';}
}
allocationFilterSelect.addEventListener('change',()=>{rotaAllocationFilter=allocationFilterSelect.value;render();});
function matchesAllocationFilter(j){
 if(rotaAllocationFilter==='all')return true;
 const allocations=rotaDayFilter==='all'?state.allocations[j]:[state.allocations[j][Number(rotaDayFilter)]];
 if(rotaAllocationFilter==='driver')return allocations.some(code=>isWorkingAllocation(code)&&!isSpecialAllocation(code,'ra')&&!isSpecialAllocation(code,'office'));
 if(rotaAllocationFilter==='unallocated')return allocations.some(code=>!code);
 return allocations.some(code=>String(code)===rotaAllocationFilter);
}
const filterSection=document.querySelector('#drivers').closest('section');
const filterHeading=filterSection.querySelector('.driver-heading');
filterHeading?.after(dailyFilters);
const daySelect=dailyFilters.querySelector('#rota-day-filter'),statusSelect=dailyFilters.querySelector('#rota-status-filter');
const clearDayFilter=dailyFilters.querySelector('#rota-clear-day-filter');
function matchesDailyFilter(d,j){
 if(rotaDayFilter==='all'||rotaStatusFilter==='all')return true;
 const day=Number(rotaDayFilter),available=d.availability[day]===true;
 const allocated=isWorkingAllocation(state.allocations[j][day]);
 if(rotaStatusFilter==='available-unallocated')return available&&!allocated;
 if(rotaStatusFilter==='allocated-unavailable')return allocated&&!available;
 if(rotaStatusFilter==='available-allocated')return available&&allocated;
 return true;
}
function updateDailyFilter(day,status){
 rotaDayFilter=day;rotaStatusFilter=status;
 daySelect.value=day;statusSelect.value=status;
 render();
}
daySelect.addEventListener('change',()=>updateDailyFilter(daySelect.value,rotaStatusFilter));
statusSelect.addEventListener('change',()=>updateDailyFilter(rotaDayFilter==='all'?'0':rotaDayFilter,statusSelect.value));
clearDayFilter.addEventListener('click',()=>{rotaAllocationFilter='all';allocationFilterSelect.value='all';updateDailyFilter('all','all');});
document.querySelector('#drivers thead').addEventListener('click',event=>{
 const th=event.target.closest('th[data-rota-day]');
 if(!th)return;
 const day=th.dataset.rotaDay;
 updateDailyFilter(day,rotaStatusFilter==='all'?'available-unallocated':rotaStatusFilter);
});

const expandedDrivers = new Set();
let driverNameAscending=true;
const driverSortButton=document.createElement('button');driverSortButton.type='button';driverSortButton.className='rota-name-sort';driverSortButton.textContent='Driver A–Z ↑';driverSortButton.title='Toggle driver name sorting A–Z or Z–A';driverSortButton.addEventListener('click',()=>{driverNameAscending=!driverNameAscending;driverSortButton.textContent=driverNameAscending?'Driver A–Z ↑':'Driver Z–A ↓';render()});

const accordionControls=document.createElement('label');accordionControls.className='rota-expand-all';accordionControls.innerHTML='<input type="checkbox" id="rota-expand-all"> <span>Expand all drivers</span>';
const rotaHeading=document.querySelector('#drivers').closest('section').querySelector('.driver-heading');
if(rotaHeading)rotaHeading.append(driverSortButton,accordionControls);else document.querySelector('#drivers').closest('section').insertBefore(driverSortButton,document.querySelector('#drivers').parentElement);
accordionControls.querySelector('input').addEventListener('change',event=>{expandedDrivers.clear();if(event.target.checked)drivers.forEach(d=>expandedDrivers.add(d.key));render();});





function renderMobileDayCards(){
 if(!window.matchMedia('(max-width:767px)').matches)return;
 const tbody=document.querySelector('#drivers tbody');
 tbody.querySelectorAll('tr.driver-accordion-heading').forEach(heading=>{
  const availability=heading.nextElementSibling,allocation=availability?.nextElementSibling,status=allocation?.nextElementSibling;
  if(!availability?.classList.contains('avail')||!allocation?.classList.contains('alloc')||!status?.classList.contains('status'))return;
  const card=document.createElement('tr');card.className='rota-mobile-detail';card.hidden=availability.hidden;
  const outer=document.createElement('td');outer.colSpan=10;
  const details=document.createElement('div');details.className='rota-mobile-details';
  const identity=document.createElement('div');identity.className='rota-mobile-identity';
  identity.append(...Array.from(availability.cells[0].childNodes));details.append(identity);
  const week=document.createElement('div');week.className='rota-mobile-week';
  days.forEach((day,i)=>{
   const block=document.createElement('div');block.className='rota-mobile-day';
   const label=document.createElement('strong');label.textContent=day;block.append(label);
   const av=availability.cells[i+2],al=allocation.cells[i+1],st=status.cells[i+1];
   for(const [title,source] of [['Availability',av],['Allocation',al],['Status',st]]){
    const line=document.createElement('div');line.className='rota-mobile-field';
    const caption=document.createElement('span');caption.textContent=title;
    const value=document.createElement('div');value.className='rota-mobile-value';
    if(title==='Availability'){value.textContent=source.textContent.trim()||'—';value.classList.add(source.classList.contains('availability-yes')?'yes':source.classList.contains('availability-no')?'no':'unknown');}
    else {value.append(...Array.from(source.childNodes));}
    line.classList.add('rota-mobile-'+title.toLowerCase());if(title==='Availability')line.classList.add(source.classList.contains('availability-yes')?'is-yes':source.classList.contains('availability-no')?'is-no':'is-unknown');if(title==='Allocation')line.classList.add(source.classList.contains('allocation-full-day')?'is-working':source.classList.contains('conflict')?'is-conflict':'is-unallocated');if(title==='Status')line.classList.add(source.classList.contains('status-alert')?'is-alert':'is-normal');line.append(caption,value);block.append(line);
   }
   week.append(block);
  });
  details.append(week);
  const actions=document.createElement('div');actions.className='rota-mobile-actions';
  for(const cell of [availability.cells[9],allocation.cells[8],status.cells[8]])actions.append(...Array.from(cell.childNodes));
  details.append(actions);outer.append(details);card.append(outer);
  status.after(card);availability.remove();allocation.remove();status.remove();
 });
}



const weekKey = () => monday().toISOString().slice(0,10);
const apiUrl = () => '/api/rota/'+weekKey();
function nextUkMonday(){const monday=new Date(currentUkMonday()+'T00:00:00Z');monday.setUTCDate(monday.getUTCDate()+7);return monday.toISOString().slice(0,10)}
function currentUkMonday(){const p=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/London',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).filter(x=>x.type!=='literal').map(x=>[x.type,Number(x.value)]));const date=new Date(Date.UTC(p.year,p.month-1,p.day));date.setUTCDate(date.getUTCDate()-((date.getUTCDay()+6)%7));return date.toISOString().slice(0,10)}
const csrf = document.querySelector('meta[name="rota-csrf"]').content;
let loadSequence=0;
const defaults = () => ({planning:{'Route Target':Array(7).fill(''),'Current Demand Signal':Array(7).fill(''),'ECP':Array(7).fill(''),'Required drivers':Array(7).fill(''),'DA Capacity':Array(7).fill(''),'Standby':Array(7).fill('')},allocations:drivers.map(d=>d.allocation.slice()),statuses:drivers.map(()=>Array(7).fill('')),pending:drivers.map(d=>Boolean(d.pending)),audit:[],unlocked:false});
let rotaPermission=false;
let state,undoStack=[],busy=false,allocationOptions=[],statusOptions=[],planningDraft=null,weekLock={historical:false,canEdit:true,history:[]};
const driverStatuses=()=>[{code:'',active:true},...statusOptions];
window.getRotaAllocations=()=>state?.allocations;
const safeText = s => String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function load(){const seq=++loadSequence;planningDraft=null;state=defaults();weekLock={historical:false,canEdit:false,history:[]};undoStack=[];render();document.getElementById('saveStatus').textContent='Loading saved rota…';try{const response=await fetch(apiUrl(),{credentials:'same-origin'});if(!response.ok)throw Error('HTTP '+response.status);const data=await response.json();if(seq!==loadSequence)return;allocationOptions=Array.isArray(data.allocationOptions)?data.allocationOptions:[];refreshAllocationFilter();statusOptions=Array.isArray(data.statusOptions)?data.statusOptions:[];drivers.splice(0,drivers.length,...data.drivers.map(d=>({...d,availability:Array(7).fill(null),allocation:Array(7).fill(''),pending:false})));state=defaults();for(const c of data.cells){if(c.driver==='planning'&&state.planning[c.field]&&c.day>=0&&c.day<7)state.planning[c.field][c.day]=c.value;else if(c.field==='availability'&&c.day>=0&&c.day<7){const index=drivers.findIndex(d=>d.key===c.driver);if(index>=0)drivers[index].availability[c.day]=c.value==='IN'?true:c.value==='OFF'?false:null;}else if(c.field==='status'&&c.day>=0&&c.day<7){const index=drivers.findIndex(d=>d.key===c.driver);if(index>=0)state.statuses[index][c.day]=c.value;}else if(c.field==='allocation'&&c.day>=0&&c.day<7){const index=drivers.findIndex(d=>d.key===c.driver);if(index>=0)state.allocations[index][c.day]=c.value;}}weekLock.historical=Boolean(data.historical);rotaPermission=Boolean(data.canEdit);weekLock.canEdit=rotaPermission;planningDraft=structuredClone(state.planning);loadLock();document.getElementById('saveStatus').textContent='All changes saved';render()}catch(err){if(seq===loadSequence){document.getElementById('saveStatus').textContent='⚠ Could not load saved rota';console.error(err)}}}
const auditPanel=document.createElement('details');auditPanel.className='card';auditPanel.innerHTML='<summary>📋 Week Change & Unlock History (12-month retention)</summary><div id="week-audit-body">Loading…</div>';document.querySelector('.week').closest('.toolbar').after(auditPanel);
async function loadLock(){const week=weekKey();try{const response=await fetch('/api/rota/'+week+'/lock',{credentials:'same-origin'});if(!response.ok)throw Error('Unable to load lock');const data=await response.json();if(week!==weekKey())return;weekLock={...data,canEdit:rotaPermission && Boolean(data.canEdit)};const box=document.getElementById('week-audit-body');box.innerHTML=data.history.length?'<table><thead><tr><th>When (UTC)</th><th>Manager</th><th>Action</th><th>Details</th></tr></thead><tbody>'+data.history.map(h=>'<tr><td>'+safeText(h.created_at)+'</td><td>'+safeText(h.actor||'Former user')+'</td><td>'+safeText(h.event_type)+'</td><td>'+safeText(h.details)+'</td></tr>').join('')+'</tbody></table>':'No historical corrections recorded for this week.';render();}catch(e){document.getElementById('week-audit-body').textContent='Unable to load history';}}
function updateUndo(){document.getElementById('undoEdit').disabled=busy||locked()||undoStack.length===0}
function locked(){return !weekLock.canEdit}
async function change(kind,driver,day,value,recordUndo=true){if(locked())return;const old=kind==='allocation'?state.allocations[driver][day]:kind==='status'?state.statuses[driver][day]:kind==='availability'?(drivers[driver].availability[day]===null?'':drivers[driver].availability[day]?'IN':'OFF'):state.planning[kind][day];if(String(old)===String(value))return;const week=weekKey();busy=true;updateUndo();document.getElementById('saveStatus').textContent='Saving…';try{const response=await fetch('/api/rota/'+week+'/cell',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({driver:['allocation','status','availability'].includes(kind)?drivers[driver].key:'planning',field:kind,day,value:String(value)})});if(!response.ok){const result=await response.json().catch(()=>({}));throw Error(result.error||'HTTP '+response.status)}if(week!==weekKey())return;if(recordUndo)undoStack.push({week,kind,driver,day,previous:String(old),value:String(value)});if(kind==='allocation')state.allocations[driver][day]=value;else if(kind==='status')state.statuses[driver][day]=value;else if(kind==='availability')drivers[driver].availability[day]=value==='IN';else state.planning[kind][day]=value;document.getElementById('saveStatus').textContent='Saved · '+new Date().toLocaleTimeString('en-GB');render();return true}catch(err){document.getElementById('saveStatus').textContent='⚠ Allocation not saved';render();if(kind==='allocation'){const select=document.querySelector('#drivers select[data-driver="'+driver+'"][data-day="'+day+'"]');const row=select?.closest('tr');if(row){const notice=document.createElement('tr');notice.className='allocation-error-row';const cell=document.createElement('td');cell.colSpan=row.cells.length;cell.className='allocation-error-message';cell.setAttribute('role','alert');cell.textContent='⚠ '+err.message;notice.append(cell);(row.nextElementSibling||row).after(notice);}else document.getElementById('saveStatus').textContent='⚠ Save failed: '+err.message;}else document.getElementById('saveStatus').textContent='⚠ Save failed: '+err.message;return false}finally{busy=false;updateUndo()}}
function row(label,values,editable=false){return '<tr><td class="label">'+safeText(label)+'</td>'+values.map((v,i)=>{if(!editable)return '<td class="'+(Number(v)<0?'short':'')+'">'+safeText(v)+'</td>';return '<td><input aria-label="'+safeText(label+' '+days[i])+'" type="number" min="0" step="1" data-field="'+safeText(label)+'" data-day="'+i+'" value="'+safeText(v===''?'0':v)+'" '+(locked()?'disabled':'')+'></td>'}).join('')+'</tr>'}
function isWorkingAllocation(code){const option=allocationOptions.find(o=>o.code===String(code||''));return Boolean(option?.countsHeadcount)}
function isSpecialAllocation(code,type){const option=allocationOptions.find(o=>o.code===String(code||''));return [String(code||''),String(option?.label||'')].some(v=>v.trim().toLowerCase()===type)}
function counts(){const available=days.map((_,i)=>drivers.filter(d=>d.availability[i]).length),ra=days.map((_,i)=>drivers.filter((d,j)=>isSpecialAllocation(state.allocations[j][i],'ra')).length),office=days.map((_,i)=>drivers.filter((d,j)=>isSpecialAllocation(state.allocations[j][i],'office')).length),allocated=days.map((_,i)=>drivers.filter((d,j)=>isWorkingAllocation(state.allocations[j][i])&&!isSpecialAllocation(state.allocations[j][i],'ra')&&!isSpecialAllocation(state.allocations[j][i],'office')).length),required=(planningDraft||state.planning)['Required drivers'].map(v=>Number(v)||0),standby=days.map((_,i)=>drivers.filter((d,j)=>isSpecialAllocation(state.allocations[j][i],'standby')).length);return {available,allocated,ra,office,required,standby}}
function renderInteractive(){
const mon=monday(),sun=new Date(mon.getTime()+6*86400000);
document.getElementById('week').textContent='Week '+isoWeek(mon)+' · '+fmt(mon)+' – '+fmt(sun)+' '+sun.getUTCFullYear();document.getElementById('week-calendar').value=weekKey();
document.getElementById('lock').textContent=weekLock.historical?(locked()?'🔒 Past Week — Locked':'🔓 Past Week — Correction Open'):(weekKey()===currentUkMonday()?'🟢 Live Rota':weekKey()===nextUkMonday()?'🟡 Planning':weekKey()>currentUkMonday()?'🟡 Future Planning':'🟠 Review Period');
document.getElementById('unlockEdit').hidden=!weekLock.historical||!rotaPermission;
document.getElementById('unlockEdit').textContent=locked()?'🔓 Unlock for correction':'🔒 Lock now';
const header='<tr><th>Planning field</th>'+days.map((d,i)=>'<th>'+d+'<br><small>'+fmt(new Date(mon.getTime()+i*86400000))+'</small></th>').join('')+'</tr>';
document.querySelector('#summary thead').innerHTML=header;
const c=counts(),diff=(a,b)=>a.map((n,i)=>{const x=n-b[i];return (x>0?'+':'')+x});const shown=planningDraft||state.planning;
document.querySelector('#summary tbody').innerHTML=['Route Target','Current Demand Signal','ECP','Required drivers'].map(n=>row(n,shown[n],true)).join('')+row('Approved available',c.available)+row('Allocated Driver(headcount)',c.allocated)+row('Drivers Availability surplus / shortfall',diff(c.available,c.required))+row('Drivers Allocation surplus / shortfall',diff(c.allocated,c.required))+row('Allocated RA(headcount)',c.ra)+row('Allocated Office(headcount)',c.office)+row('Available but unallocated',days.map((_,i)=>drivers.filter((d,j)=>d.availability[i]&&!isWorkingAllocation(state.allocations[j][i])).length))+row('DA Capacity',shown['DA Capacity'],true)+row('Standby',c.standby);
document.querySelector('#drivers thead').innerHTML='<tr><th>Driver / type</th><th>Row</th>'+days.map((d,i)=>'<th data-rota-day="'+i+'" title="Filter drivers for '+d+'" class="'+(rotaDayFilter===String(i)?'rota-day-active':'')+'">'+d+'<br><small>'+fmt(new Date(mon.getTime()+i*86400000))+'</small></th>').join('')+'<th>Action</th></tr>';
const q=document.getElementById('search').value.toLowerCase(),f=document.getElementById('filter').value,options=[{code:'',label:'— Select —',active:true},...allocationOptions];
document.querySelector('#drivers tbody').innerHTML=drivers.map((d,j)=>({d,j})).sort((a,b)=>(driverNameAscending?1:-1)*a.d.name.localeCompare(b.d.name,undefined,{sensitivity:'base',numeric:true})).filter(({d})=>[d.name,d.phone||'',d.email||'',d.type||''].join(' ').toLowerCase().includes(q)).filter(({d,j})=>matchesDailyFilter(d,j)&&matchesAllocationFilter(j)).filter(({d,j})=>f==='all'||(f==='pending'&&state.pending[j])||(f==='not-submitted'&&d.availability.every(a=>a===null))||(f==='availability-changed'&&window.rotaAvailabilityChangedKeys?.has(d.key))||(f==='conflict'&&d.availability.some((a,i)=>a===false&&!['','OFF'].includes(state.allocations[j][i])))).map(({d,j},displayIndex)=>{
const alloc=state.allocations[j],hasConflict=d.availability.some((a,i)=>a===false&&!['','OFF'].includes(alloc[i]));
const availableDays=d.availability.filter(a=>a===true).length,offDays=d.availability.filter(a=>a===false).length,unsubmittedDays=d.availability.filter(a=>a===null).length;
const workingDays=alloc.filter(a=>isWorkingAllocation(a)).length,allocatedDays=alloc.filter(a=>a&&!['OFF'].includes(a)).length;
const conflictDays=d.availability.filter((a,i)=>a===false&&!['','OFF'].includes(alloc[i])).length;
const statusDays=state.statuses[j].filter(Boolean).length;
const training=(d.training||[]),trainingDone=training.filter(t=>t.done).length;
const chips='<span class="rota-chip green">✓ '+availableDays+' available</span><span class="rota-chip red">'+offDays+' off</span>'+(unsubmittedDays?'<span class="rota-chip amber">'+unsubmittedDays+' not submitted</span>':'')+'<span class="rota-chip blue">'+allocatedDays+' allocated</span>'+(statusDays?'<span class="rota-chip purple">'+statusDays+' status updates</span>':'')+(conflictDays?'<span class="rota-chip red">⚠ '+conflictDays+' conflicts</span>':'')+(state.pending[j]?'<span class="rota-chip amber">🔔 Pending review</span>':'')+(training.length?'<span class="rota-chip '+(trainingDone===training.length?'green':'amber')+'">Training '+trainingDone+'/'+training.length+'</span>':'');
return '<tr class="driver-accordion-heading"><td colspan="10"><button type="button" class="driver-accordion-toggle" data-toggle-driver="'+j+'" aria-expanded="'+expandedDrivers.has(d.key)+'"><span class="chevron">'+(expandedDrivers.has(d.key)?'▾':'▸')+'</span><span>'+(displayIndex+1)+'. '+safeText(d.name)+' <small>· '+safeText(d.type)+(hasConflict?' · ⚠ Conflict':'')+(state.pending[j]?' · 🔔 Pending':'')+'</small></span><span class="rota-driver-summary">'+chips+'</span><span class="rota-distribution-indicator" data-rota-distribution="'+safeText(d.key)+'">⚪ Checking rota…</span></button></td></tr><tr class="avail driver-accordion-detail driver-group-'+(displayIndex%2?'even':'odd')+'" '+(expandedDrivers.has(d.key)?'':'hidden')+'><td rowspan="3"><span class="driver-row-number">'+(displayIndex+1)+'.</span> <b>'+safeText(d.name)+'</b><br><small>'+safeText(d.type)+'</small><br><small class="driver-contact">'+safeText(d.phone||'No phone')+'<br>'+safeText(d.email||'No email')+(state.pending[j]?' · 🔔 Pending':'')+'</small><div class="driver-training" aria-label="Training completion">'+(d.training||[]).map(t=>'<span class="'+(t.done?'training-done':'training-missing')+'" title="'+(t.done?'Completed: ':'Not completed: ')+safeText(t.name)+'">'+(t.done?'✓ ':'✗ ')+safeText(t.name)+'</span>').join('')+'</div></td><td>Availability</td>'+d.availability.map(a=>'<td class="'+(a===null?'availability-empty':a?'availability-yes':'availability-no')+'">'+(a===null?'—':a?'YES':'NO')+'</td>').join('')+'<td>'+'<a class="edit-availability" href="/rota/driver/'+encodeURIComponent(String(d.key).replace(/^driver-/,''))+'/availability?week='+encodeURIComponent(weekKey())+'">Edit availability</a>'+(state.pending[j]?'<button data-review="'+j+'" '+(locked()?'disabled':'')+'>Review 🔔</button>':'')+'</td></tr><tr class="alloc driver-accordion-detail driver-group-'+(displayIndex%2?'even':'odd')+'" '+(expandedDrivers.has(d.key)?'':'hidden')+'><td>Allocation</td>'+alloc.map((v,i)=>'<td class="'+(isWorkingAllocation(v)?'allocation-full-day ':'')+(d.availability[i]===false&&!['','OFF'].includes(v)?'conflict':'')+'"><select aria-label="'+safeText(d.name)+' '+days[i]+' allocation" data-driver="'+j+'" data-day="'+i+'" '+(locked()?'disabled':'')+'>'+options.filter(o=>o.active||o.code===v).map(o=>'<option value="'+safeText(o.code)+'" '+(o.code===v?'selected':'')+'>'+safeText(o.code?(o.code+' ('+o.label+')'):o.label)+'</option>').join('')+'</select></td>').join('')+'<td><button class="send" disabled title="Sending requires authentication and WhatsApp setup">📲 Send rota</button>'+(hasConflict?' ⚠':'')+'</td></tr><tr class="status driver-accordion-detail driver-group-'+(displayIndex%2?'even':'odd')+'" '+(expandedDrivers.has(d.key)?'':'hidden')+'><td>Status</td>'+state.statuses[j].map((v,i)=>'<td class="'+(v?'status-alert':'')+'"><select aria-label="'+safeText(d.name)+' '+days[i]+' status" data-kind="status" data-driver="'+j+'" data-day="'+i+'" '+(locked()?'disabled':'')+'>'+driverStatuses().filter(o=>o.active||o.code===v).map(o=>'<option value="'+safeText(o.code)+'" '+(o.code===v?'selected':'')+'>'+safeText(o.code||'— Select —')+'</option>').join('')+'</select></td>').join('')+'<td>—</td></tr>'}).join('');
document.querySelectorAll('#drivers tbody tr.driver-accordion-detail').forEach(row=>{Array.from(row.children).forEach((cell,i)=>{const firstDay=row.classList.contains('avail')?2:1;if(i>=firstDay&&i<firstDay+7)cell.dataset.dayLabel=days[i-firstDay];});});
dailyFilters.querySelector('#rota-daily-filter-count').textContent=(rotaDayFilter==='all'||rotaStatusFilter==='all')&&rotaAllocationFilter==='all'?'':document.querySelectorAll('#drivers tbody tr.driver-accordion-heading').length+' matching drivers';
const expandAllCheckbox=document.getElementById('rota-expand-all');if(expandAllCheckbox){const shownDrivers=drivers.filter(d=>[d.name,d.phone||'',d.email||'',d.type||''].join(' ').toLowerCase().includes(q));expandAllCheckbox.checked=shownDrivers.length>0&&shownDrivers.every(d=>expandedDrivers.has(d.key));expandAllCheckbox.indeterminate=shownDrivers.some(d=>expandedDrivers.has(d.key))&&!expandAllCheckbox.checked;expandAllCheckbox.nextElementSibling.textContent=expandAllCheckbox.checked?'Collapse all drivers':'Expand all drivers';}
updateUndo();planningSave.disabled=locked()||busy||!planningDraft||JSON.stringify(planningDraft)===JSON.stringify(state.planning);window.refreshWhatsApp?.();renderMobileDayCards();
}
let lastMobileWidth=window.matchMedia('(max-width:767px)').matches;window.addEventListener('resize',()=>{const mobile=window.matchMedia('(max-width:767px)').matches;if(mobile!==lastMobileWidth){lastMobileWidth=mobile;if(mobile&&expandedDrivers.size>1){const keep=expandedDrivers.values().next().value;expandedDrivers.clear();expandedDrivers.add(keep);}render();}});
render=renderInteractive;
const planningCard=document.getElementById('weekly-operations-planning');
const planningControls=document.createElement('div');planningControls.className='planning-save-bar';
planningControls.innerHTML='<button id="planning-save" type="button" disabled>💾 Save planning</button><span id="planning-status" role="status">No unsaved changes</span>';
planningCard.querySelector('.scroll').after(planningControls);
const planningSave=document.getElementById('planning-save'),planningStatus=document.getElementById('planning-status');
planningSave.onclick=async()=>{
 if(busy||locked()||!planningDraft)return;
 const normalized = obj => Object.fromEntries(Object.entries(obj).filter(([name])=>name!=='Standby').map(([name, values]) => [name, values.map(value => String(value ?? ''))])); const values=normalized(planningDraft),expected=normalized(state.planning);
 for(const arr of Object.values(values))for(const v of arr)if(v!==''&&(!/^[0-9]{1,7}$/.test(v))){planningStatus.textContent='Use non-negative whole numbers';return;}
 busy=true;planningSave.disabled=true;planningStatus.textContent='Saving planning…';
 try{
 const response=await fetch(apiUrl()+'/targets',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({values,expected})});
 const result=await response.json().catch(()=>({}));if(!response.ok)throw Error(result.error||'HTTP '+response.status);
 state.planning=values;planningDraft=structuredClone(values);planningStatus.textContent='Saved '+result.changed+' change(s)';render();
 }catch(e){planningStatus.textContent='⚠ '+e.message;planningSave.disabled=false;}finally{busy=false;updateUndo();}
};
load();
render();
document.getElementById('week-calendar').addEventListener('change',event=>{const value=event.target.value;if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return;const selected=new Date(value+'T00:00:00Z');if(Number.isNaN(selected.getTime()))return;selected.setUTCDate(selected.getUTCDate()-((selected.getUTCDay()+6)%7));offset=Math.round((selected.getTime()-start.getTime())/604800000);load();render();window.dispatchEvent(new Event('rota-week-changed'))});
document.getElementById('prev').onclick=()=>{offset--;load();render();window.dispatchEvent(new Event('rota-week-changed'))};
document.getElementById('next').onclick=()=>{offset++;load();render();window.dispatchEvent(new Event('rota-week-changed'))};
document.querySelector('#summary').addEventListener('input',e=>{const el=e.target;if(!el.matches('[data-field]')||!planningDraft)return;planningDraft[el.dataset.field][Number(el.dataset.day)]=el.value;planningSave.disabled=locked()||busy||JSON.stringify(planningDraft)===JSON.stringify(state.planning);planningStatus.textContent=planningSave.disabled?'No unsaved changes':'Unsaved changes';if(el.dataset.field==='Required drivers'){const day=Number(el.dataset.day),c=counts(),summary=document.querySelector('#summary tbody');for(const [rowIndex,base] of [[6,c.available[day]],[7,c.allocated[day]]]){const cell=summary.rows[rowIndex]?.cells[day+1];if(cell){const delta=base-c.required[day];cell.textContent=delta>0?'+'+delta:String(delta);cell.classList.toggle('short',delta<0);}}}});
document.querySelector('#summary').addEventListener('focusout',e=>{const el=e.target;if(!el.matches('[data-field]')||!planningDraft||el.value!=='')return;el.value='0';el.dispatchEvent(new Event('input',{bubbles:true}));});
document.getElementById('filter').addEventListener('change',()=>render());
document.getElementById('search').addEventListener('input',()=>render());
document.querySelector('#drivers').addEventListener('change',e=>{const el=e.target;if(el.matches('[data-driver]'))change(el.dataset.kind||'allocation',Number(el.dataset.driver),Number(el.dataset.day),el.value)});
document.querySelector('#drivers').addEventListener('click',e=>{const toggle=e.target.closest('[data-toggle-driver]');if(toggle){const key=drivers[Number(toggle.dataset.toggleDriver)]?.key;if(key){if(expandedDrivers.has(key)){expandedDrivers.delete(key);}else{if(window.matchMedia('(max-width:767px)').matches)expandedDrivers.clear();expandedDrivers.add(key);}render();if(window.matchMedia('(max-width:767px)').matches&&expandedDrivers.has(key)){requestAnimationFrame(()=>requestAnimationFrame(()=>{const heading=Array.from(document.querySelectorAll('#drivers [data-toggle-driver]')).find(button=>Number(button.dataset.toggleDriver)===Number(toggle.dataset.toggleDriver));if(!heading)return;const top=heading.getBoundingClientRect().top+window.scrollY-12;window.scrollTo({top:Math.max(0,top),behavior:'smooth'});}));}}return;}const el=e.target.closest('[data-review]');if(!el||locked())return;const j=Number(el.dataset.review);const decision=confirm('Mark the availability notification for '+drivers[j].name+' as reviewed?');if(!decision)return;state.pending[j]=false;state.audit.push({at:new Date().toISOString(),kind:'Demo notification acknowledged',driver:drivers[j].name,day:'—',before:'Pending',after:'Reviewed',reason:'Sample data only'});render()});
document.getElementById('undoEdit').onclick=async()=>{if(busy||locked()||!undoStack.length)return;const edit=undoStack[undoStack.length-1];if(edit.week!==weekKey()){undoStack=[];updateUndo();return}const current=edit.kind==='allocation'?state.allocations[edit.driver][edit.day]:edit.kind==='status'?state.statuses[edit.driver][edit.day]:edit.kind==='availability'?(drivers[edit.driver].availability[edit.day]?'IN':'OFF'):state.planning[edit.kind][edit.day];if(String(current)!==edit.value){undoStack.pop();updateUndo();return}const success=await change(edit.kind,edit.driver,edit.day,edit.previous,false);if(success)undoStack.pop();updateUndo()};
document.getElementById('unlockEdit').onclick=async()=>{if(!weekLock.historical)return;let action='relock',reason='';if(locked()){action='unlock';if(!confirm('Historical corrections are audited and visible for 12 months. Your name, reason and all changes will be recorded. Continue?'))return;reason=prompt('Reason for unlocking this historical week (minimum 5 characters):')||'';if(reason.trim().length<5)return;}else if(!confirm('Lock this week now?'))return;try{const response=await fetch('/api/rota/'+weekKey()+'/lock',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,reason})});const result=await response.json();if(!response.ok)throw Error(result.error||'Unable to change lock');await loadLock();}catch(e){alert(e.message)}};
setInterval(()=>{if(weekLock.historical)loadLock()},60000);
document.querySelector('#weekly-operations-planning > .note').textContent='Planning values save only when you click Save planning.';
document.querySelectorAll('.note')[1].textContent='Allocations are saved against registered driver accounts. A blank availability cell means no response.';
})();