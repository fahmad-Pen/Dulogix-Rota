(()=>{
'use strict';
const $=id=>document.getElementById(id),date=$('shift-date');
if(!date)return;
const fields={pre:[$('shift-ementor'),$('shift-clear-quote')],post:[$('shift-fisco'),$('shift-returns')]};
const states={pre:$('shift-pre-state'),post:$('shift-post-state')};
const buttons={pre:$('shift-save-pre'),post:$('shift-save-post')};
let loading=false,record=null,currentHash=null;
const today=date.max;
function formatTime(raw){
 if(!raw)return '';
 const parts=raw.replace('T',' ').split(' ');
 return parts[0].split('-').reverse().join('/')+' '+(parts[1]||'').slice(0,5)+' (UK time)';
}
function addVan(parent,label,value){parent.append(document.createTextNode(label));const strong=document.createElement('strong');strong.textContent=value;parent.append(strong)}
function refresh(){
 for(const section of ['pre','post']){
  const saved=!!record?.[section==='pre'?'pre_updated_at':'post_updated_at'];
  const editable=date.value===today&&!saved&&!loading;
  fields[section].forEach(el=>el.disabled=!editable);
  buttons[section].disabled=!editable;
  buttons[section].hidden=date.value!==today||saved;
  states[section].textContent=saved?'Submitted '+formatTime(record[section==='pre'?'pre_updated_at':'post_updated_at'])+' ? Locked':date.value!==today?'Read-only ? previous shift':'Not submitted';
 }
}
async function load(){
 loading=true;
 for(const section of ['pre','post']){buttons[section].disabled=true;fields[section].forEach(el=>el.disabled=true);states[section].textContent='Loading?';}
 try{
  const r=await fetch('/driver/daily-check/data?date='+encodeURIComponent(date.value),{credentials:'same-origin'});
  const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Unable to load');
  record=j.data;
  const routeCard=$('shift-route-card'),routeMessage=$('shift-route-message'),routeVan=$('shift-route-van');
  const assignment=j.assignment;
  const ack=j.acknowledgement;
  currentHash=ack?.instruction_hash||null;
  const status=$('shift-instruction-status'),ackButton=$('shift-ack'),changes=$('shift-instruction-changes');
  routeCard.classList.toggle('is-read',!!ack?.read);
  status.classList.toggle('instruction-read',!!ack?.read);
  status.textContent=ack?.read?'✓ Instructions read and acknowledged':ack?.updated?'⚠ Updated instructions — please review the changes below':'⚠ Instructions awaiting acknowledgement';
  ackButton.hidden=!assignment||!!ack?.read;
  changes.replaceChildren();
  const labels={holding_time:'Holding time',route:'Route',stg_location:'Staging location',van_registration:'Assigned van',manager_comments:'Manager Comments'};
  for(const key of ack?.changed_fields||[]){const li=document.createElement('li');li.textContent=(labels[key]||key)+' updated';changes.append(li);}
  changes.hidden=!changes.children.length;
  const changed=new Set(ack?.read?[]:ack?.changed_fields||[]);
  const withdrawn=!assignment&&!!j.assignment_withdrawn;
  routeCard.hidden=!assignment&&!withdrawn;
  routeCard.classList.toggle('is-withdrawn',withdrawn);
  routeMessage.hidden=withdrawn;
  routeVan.hidden=withdrawn;
  $('shift-manager-comments').hidden=withdrawn;
  status.hidden=withdrawn;
  changes.hidden=withdrawn||!changes.children.length;
  if(withdrawn){
   routeMessage.hidden=false;
   routeMessage.textContent='Your route assignment has been withdrawn by Operations. Please contact your manager for updated instructions.';
   ackButton.hidden=true;
  }
  if(assignment){
   const hold=assignment.holding_time||'not yet specified';
   const route=assignment.route||'not yet specified';
   const stg=assignment.stg_location||'not yet specified';
   routeMessage.replaceChildren();
   const add=(text,strong,key)=>{const el=document.createElement(strong?'strong':'span');el.textContent=text;if(key&&changed.has(key))el.classList.add('instruction-value-changed');routeMessage.append(el);};
   add('For this shift, your holding time is ');add(hold,true,'holding_time');
   add(', your route is ');add(route,true,'route');
   add(', and your staging location (STG) is ');add(stg,true,'stg_location');add('.');
   routeVan.replaceChildren();
   addVan(routeVan,'Assigned van: ',assignment.van_registration||'Not assigned yet');
   routeVan.classList.toggle('instruction-value-changed',changed.has('van_registration'));
   const comments=(assignment.manager_comments||'').trim();
   $('shift-manager-comments').hidden=!comments;
   $('shift-manager-comments-text').textContent=comments;
   $('shift-manager-comments-text').classList.toggle('instruction-value-changed',changed.has('manager_comments'));
  }

  $('shift-ementor').checked=!!Number(record.ementor);
  $('shift-clear-quote').checked=!!Number(record.clear_quote);
  $('shift-fisco').value=record.fisco_score??'';
  $('shift-returns').value=record.returns_count??'';
 }catch(e){$('shift-route-card').hidden=true;record=null;states.pre.textContent=states.post.textContent='Unable to load: '+e.message;return}
 finally{loading=false;if(record)refresh()}
}
async function save(section){
 if(loading||date.value!==today||!record)return;
 const key=section==='pre'?'pre_updated_at':'post_updated_at';
 if(record[key])return;
 if(section==='post'){
  const score=$('shift-fisco').value,returns=$('shift-returns').value;
  if(score===''||returns===''){states.post.textContent='Enter both FICO Score and No. of Returns before saving.';return}
  if(!/^\d{1,3}$/.test(score)||Number(score)>850||!/^\d{1,9}$/.test(returns)){states.post.textContent='Enter a FICO Score of 0?850 and a non-negative whole number of returns.';return}
 }
 if(!confirm('Are you sure you want to submit '+(section==='pre'?'Pre-Shift':'Post-Shift')+' for today?\n\nOnce saved, you cannot change it.'))return;
 loading=true;buttons[section].disabled=true;fields[section].forEach(el=>el.disabled=true);
 states[section].textContent='Saving?';
 const body=new URLSearchParams({_token:window.DULOGIX_SHIFT_TOKEN,date:date.value,section,
  ementor:$('shift-ementor').checked?'1':'0',clear_quote:$('shift-clear-quote').checked?'1':'0',
  fisco_score:$('shift-fisco').value,returns_count:$('shift-returns').value});
 try{
  const r=await fetch('/driver/daily-check/data',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});
  const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Save failed');
  record[key]=j.saved_at;
 }catch(e){states[section].textContent='Not saved ? '+e.message;loading=false;await load();return}
 loading=false;refresh();
}
$('shift-ack').addEventListener('click',async()=>{
 if(!currentHash)return;
 const button=$('shift-ack');button.disabled=true;
 try{
  const body=new URLSearchParams({_token:window.DULOGIX_SHIFT_TOKEN,date:date.value,section:'ack',instruction_hash:currentHash});
  const response=await fetch('/driver/daily-check/data',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});
  const result=await response.json();if(!response.ok||!result.ok)throw Error(result.error||'Unable to acknowledge');
  await load();
 }catch(error){$('shift-instruction-status').textContent=error.message+' Please refresh and review.';button.disabled=false;}
});
buttons.pre.addEventListener('click',()=>save('pre'));
buttons.post.addEventListener('click',()=>save('post'));
date.addEventListener('change',load);
load();
})();
