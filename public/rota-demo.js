/* Dulogix interactive operations rota. Edits are persisted through the authenticated API. */
(() => {
'use strict';
const root = document.querySelector('main');
const info = document.createElement('div');
info.className = 'toolbar';
info.innerHTML = '<strong>Rota planning</strong><span id="saveStatus" aria-live="polite">Ready</span><button id="undoEdit" type="button">↶ Undo</button><button id="historyEdit" type="button">Change history</button><button id="unlockEdit" type="button" hidden>🔒 Unlock past week</button>';
root.insertBefore(info, root.firstChild.nextSibling);
const undoButton=info.querySelector('#undoEdit');
const driverSection=document.querySelector('#drivers')?.closest('section');
if(driverSection){const undoBar=document.createElement('div');undoBar.className='rota-driver-undo';undoBar.append(undoButton);driverSection.parentNode.insertBefore(undoBar,driverSection);}
const extraStyle = document.createElement('style');
extraStyle.textContent = 'td input[type=number]{width:74px;text-align:center}td select{width:100%;min-width:80px;background:white}td.changed{outline:2px solid #e3aa36;outline-offset:-3px}td.conflict{background:#ffe5e5!important}button:disabled,select:disabled,input:disabled{opacity:.65;cursor:not-allowed}.history{max-height:240px;overflow:auto}.history li{padding:6px;border-bottom:1px solid #e4eaf1}';
document.head.append(extraStyle);
const weekKey = () => monday().toISOString().slice(0,10);
const apiUrl = () => '/api/rota/'+weekKey();
const csrf = document.querySelector('meta[name="rota-csrf"]').content;
let loadSequence=0;
const defaults = () => ({planning:{'Route Target':Array(7).fill(''),'Current Demand Signal':Array(7).fill(''),'ECP':Array(7).fill(''),'Required drivers':Array(7).fill(''),'DA Capacity':Array(7).fill(''),'Standby':Array(7).fill('')},allocations:drivers.map(d=>d.allocation.slice()),pending:drivers.map(d=>Boolean(d.pending)),audit:[],unlocked:false});
let state,undoStack=[],busy=false,allocationOptions=[],planningDraft=null;
window.getRotaAllocations=()=>state?.allocations;
const safeText = s => String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function load(){const seq=++loadSequence;planningDraft=null;state=defaults();undoStack=[];render();document.getElementById('saveStatus').textContent='Loading saved rota…';try{const response=await fetch(apiUrl(),{credentials:'same-origin'});if(!response.ok)throw Error('HTTP '+response.status);const data=await response.json();if(seq!==loadSequence)return;allocationOptions=Array.isArray(data.allocationOptions)?data.allocationOptions:[];drivers.splice(0,drivers.length,...data.drivers.map(d=>({...d,availability:Array(7).fill(null),allocation:Array(7).fill(''),pending:false})));state=defaults();for(const c of data.cells){if(c.driver==='planning'&&state.planning[c.field]&&c.day>=0&&c.day<7)state.planning[c.field][c.day]=c.value;else if(c.field==='availability'&&c.day>=0&&c.day<7){const index=drivers.findIndex(d=>d.key===c.driver);if(index>=0)drivers[index].availability[c.day]=c.value==='IN'?true:c.value==='OFF'?false:null;}else if(c.field==='allocation'&&c.day>=0&&c.day<7){const index=drivers.findIndex(d=>d.key===c.driver);if(index>=0)state.allocations[index][c.day]=c.value;}}planningDraft=structuredClone(state.planning);document.getElementById('saveStatus').textContent='All changes saved';render()}catch(err){if(seq===loadSequence){document.getElementById('saveStatus').textContent='⚠ Could not load saved rota';console.error(err)}}}
function updateUndo(){document.getElementById('undoEdit').disabled=busy||locked()||undoStack.length===0}
function locked(){return offset<0}
async function change(kind,driver,day,value,recordUndo=true){if(locked())return;const old=kind==='allocation'?state.allocations[driver][day]:state.planning[kind][day];if(String(old)===String(value))return;const week=weekKey();busy=true;updateUndo();document.getElementById('saveStatus').textContent='Saving…';try{const response=await fetch('/api/rota/'+week+'/cell',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({driver:kind==='allocation'?drivers[driver].key:'planning',field:kind,day,value:String(value)})});if(!response.ok){const result=await response.json().catch(()=>({}));throw Error(result.error||'HTTP '+response.status)}if(week!==weekKey())return;if(recordUndo)undoStack.push({week,kind,driver,day,previous:String(old),value:String(value)});if(kind==='allocation')state.allocations[driver][day]=value;else state.planning[kind][day]=value;document.getElementById('saveStatus').textContent='Saved · '+new Date().toLocaleTimeString('en-GB');render();return true}catch(err){document.getElementById('saveStatus').textContent='⚠ Save failed: '+err.message;render();return false}finally{busy=false;updateUndo()}}
function row(label,values,editable=false){return '<tr><td class="label">'+safeText(label)+'</td>'+values.map((v,i)=>{if(!editable)return '<td class="'+(Number(v)<0?'short':'')+'">'+safeText(v)+'</td>';return '<td><input aria-label="'+safeText(label+' '+days[i])+'" type="number" min="0" step="1" data-field="'+safeText(label)+'" data-day="'+i+'" value="'+safeText(v===''?'0':v)+'" '+(locked()?'disabled':'')+'></td>'}).join('')+'</tr>'}
function counts(){const available=days.map((_,i)=>drivers.filter(d=>d.availability[i]).length),allocated=days.map((_,i)=>drivers.filter((d,j)=>['HD','FD','RA'].includes(state.allocations[j][i])).length),required=(planningDraft||state.planning)['Required drivers'].map(v=>Number(v)||0);return {available,allocated,required}}
function renderInteractive(){
const mon=monday(),sun=new Date(mon.getTime()+6*86400000);
document.getElementById('week').textContent='Week '+isoWeek(mon)+' · '+fmt(mon)+' – '+fmt(sun)+' '+sun.getUTCFullYear();
document.getElementById('lock').textContent=locked()?'🔒 Historical week locked':'Current planning week';
document.getElementById('unlockEdit').hidden=true;
document.getElementById('unlockEdit').textContent=locked()?'🔒 Unlock past week':'🔓 Lock past week';
const header='<tr><th>Planning field</th>'+days.map((d,i)=>'<th>'+d+'<br><small>'+fmt(new Date(mon.getTime()+i*86400000))+'</small></th>').join('')+'</tr>';
document.querySelector('#summary thead').innerHTML=header;
const c=counts(),diff=(a,b)=>a.map((n,i)=>{const x=n-b[i];return (x>0?'+':'')+x});const shown=planningDraft||state.planning;
document.querySelector('#summary tbody').innerHTML=['Route Target','Current Demand Signal','ECP','Required drivers'].map(n=>row(n,shown[n],true)).join('')+row('Approved available',c.available)+row('Allocated (headcount)',c.allocated)+row('Availability surplus / shortfall',diff(c.available,c.required))+row('Allocation surplus / shortfall',diff(c.allocated,c.required))+row('Available but unallocated',days.map((_,i)=>drivers.filter((d,j)=>d.availability[i]&&!['HD','FD','RA'].includes(state.allocations[j][i])).length))+['DA Capacity','Standby'].map(n=>row(n,shown[n],true)).join('');
document.querySelector('#drivers thead').innerHTML='<tr><th>Driver / type</th><th>Row</th>'+days.map(d=>'<th>'+d+'</th>').join('')+'<th>Action</th></tr>';
const q=document.getElementById('search').value.toLowerCase(),f=document.getElementById('filter').value,options=[{code:'',label:'— Select —',active:true},...allocationOptions];
document.querySelector('#drivers tbody').innerHTML=drivers.map((d,j)=>({d,j})).filter(({d})=>[d.name,d.phone||'',d.email||'',d.type||''].join(' ').toLowerCase().includes(q)).filter(({d,j})=>f==='all'||(f==='pending'&&state.pending[j])||(f==='conflict'&&d.availability.some((a,i)=>a===false&&!['','OFF'].includes(state.allocations[j][i])))).map(({d,j},displayIndex)=>{
const alloc=state.allocations[j],hasConflict=d.availability.some((a,i)=>a===false&&!['','OFF'].includes(alloc[i]));
return '<tr class="avail"><td rowspan="2"><span class="driver-row-number">'+(displayIndex+1)+'.</span> <b>'+safeText(d.name)+'</b><br><small>'+safeText(d.type)+'</small><br><small class="driver-contact">'+safeText(d.phone||'No phone')+'<br>'+safeText(d.email||'No email')+(state.pending[j]?' · 🔔 Pending':'')+'</small></td><td>Availability</td>'+d.availability.map(a=>'<td class="'+(a===null?'availability-empty':a?'availability-yes':'availability-no')+'">'+(a===null?'—':a?'YES':'NO')+'</td>').join('')+'<td>'+(state.pending[j]?'<button data-review="'+j+'" '+(locked()?'disabled':'')+'>Review 🔔</button>':'—')+'</td></tr><tr class="alloc"><td>Allocation</td>'+alloc.map((v,i)=>'<td class="'+(d.availability[i]===false&&!['','OFF'].includes(v)?'conflict':'')+'"><select aria-label="'+safeText(d.name)+' '+days[i]+' allocation" data-driver="'+j+'" data-day="'+i+'" '+(locked()?'disabled':'')+'>'+options.filter(o=>o.active||o.code===v).map(o=>'<option value="'+safeText(o.code)+'" '+(o.code===v?'selected':'')+'>'+safeText(o.code?(o.code+' ('+o.label+')'):o.label)+'</option>').join('')+'</select></td>').join('')+'<td><button class="send" disabled title="Sending requires authentication and WhatsApp setup">📲 Send rota</button>'+(hasConflict?' ⚠':'')+'</td></tr>'}).join('');
updateUndo();planningSave.disabled=locked()||busy||!planningDraft||JSON.stringify(planningDraft)===JSON.stringify(state.planning);window.refreshWhatsApp?.();
}
render=renderInteractive;
const planningCard=document.getElementById('weekly-operations-planning');
const planningControls=document.createElement('div');planningControls.className='planning-save-bar';
planningControls.innerHTML='<button id="planning-save" type="button" disabled>💾 Save planning</button><span id="planning-status" role="status">No unsaved changes</span>';
planningCard.querySelector('.scroll').after(planningControls);
const planningSave=document.getElementById('planning-save'),planningStatus=document.getElementById('planning-status');
planningSave.onclick=async()=>{
 if(busy||locked()||!planningDraft)return;
 const normalized = obj => Object.fromEntries(Object.entries(obj).map(([name, values]) => [name, values.map(value => String(value ?? ''))])); const values=normalized(planningDraft),expected=normalized(state.planning);
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
document.getElementById('prev').onclick=()=>{offset--;load();render()};
document.getElementById('next').onclick=()=>{offset++;load();render()};
document.querySelector('#summary').addEventListener('input',e=>{const el=e.target;if(!el.matches('[data-field]')||!planningDraft)return;planningDraft[el.dataset.field][Number(el.dataset.day)]=el.value;planningSave.disabled=locked()||busy||JSON.stringify(planningDraft)===JSON.stringify(state.planning);planningStatus.textContent=planningSave.disabled?'No unsaved changes':'Unsaved changes';if(el.dataset.field==='Required drivers'){const day=Number(el.dataset.day),c=counts(),summary=document.querySelector('#summary tbody');for(const [rowIndex,base] of [[6,c.available[day]],[7,c.allocated[day]]]){const cell=summary.rows[rowIndex]?.cells[day+1];if(cell){const delta=base-c.required[day];cell.textContent=delta>0?'+'+delta:String(delta);cell.classList.toggle('short',delta<0);}}}});
document.querySelector('#summary').addEventListener('focusout',e=>{const el=e.target;if(!el.matches('[data-field]')||!planningDraft||el.value!=='')return;el.value='0';el.dispatchEvent(new Event('input',{bubbles:true}));});
document.querySelector('#drivers').addEventListener('change',e=>{const el=e.target;if(el.matches('[data-driver]'))change('allocation',Number(el.dataset.driver),Number(el.dataset.day),el.value)});
document.querySelector('#drivers').addEventListener('click',e=>{const el=e.target.closest('[data-review]');if(!el||locked())return;const j=Number(el.dataset.review);const decision=confirm('Mark the availability notification for '+drivers[j].name+' as reviewed?');if(!decision)return;state.pending[j]=false;state.audit.push({at:new Date().toISOString(),kind:'Demo notification acknowledged',driver:drivers[j].name,day:'—',before:'Pending',after:'Reviewed',reason:'Sample data only'});render()});
document.getElementById('undoEdit').onclick=async()=>{if(busy||locked()||!undoStack.length)return;const edit=undoStack[undoStack.length-1];if(edit.week!==weekKey()){undoStack=[];updateUndo();return}const current=edit.kind==='allocation'?state.allocations[edit.driver][edit.day]:state.planning[edit.kind][edit.day];if(String(current)!==edit.value){undoStack.pop();updateUndo();return}const success=await change(edit.kind,edit.driver,edit.day,edit.previous,false);if(success)undoStack.pop();updateUndo()};
document.getElementById('unlockEdit').onclick=()=>{if(offset>=0)return;if(!locked()){state.unlocked=false;render();return}const reason=prompt('DEMO ONLY: Reason to unlock historical week (not authorised or saved to server):');if(!reason||!reason.trim())return;state.unlocked=true;state.unlockReason=reason.trim();state.audit.push({at:new Date().toISOString(),kind:'Demo historical unlock',driver:'—',day:'—',before:'Locked',after:'Unlocked',reason:state.unlockReason});render()};
document.getElementById('historyEdit').onclick=()=>alert('Server audit entries are recorded in MariaDB. An admin audit-history viewer will be added next.');
document.querySelector('#weekly-operations-planning > .note').textContent='Planning values save only when you click Save planning.';
document.querySelectorAll('.note')[1].textContent='Allocations are saved against registered driver accounts. A blank availability cell means no response.';
})();
