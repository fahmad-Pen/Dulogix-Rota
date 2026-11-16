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
const checks={
 pre:[['ementor','shift-ementor','ementor_submitted_at','Ementor'],['clear_quote','shift-clear-quote','clear_quote_submitted_at','Clear Quote']],
 post:[['fisco_score','shift-fisco','fisco_submitted_at','FICO Score'],['returns_count','shift-returns','returns_submitted_at','No. of Returns']]
};
function refresh(){
 for(const section of ['pre','post']){
  const pending=[];
  for(const [,id,stamp,label] of checks[section]){
   const saved=!!record?.[stamp],el=$(id);
   el.disabled=date.value!==today||saved||loading;
   (el.closest('label')||el.closest('.field'))?.classList.toggle('shift-check-submitted',saved);
   if(!saved)pending.push(label);
  }
  buttons[section].hidden=date.value!==today||!pending.length;
  buttons[section].disabled=loading||!pending.length;
  states[section].textContent=date.value!==today?'Read-only — previous shift':pending.length
   ?'Still to submit: '+pending.join(', ')+'.'
   :'All checks submitted and locked.';
 }
}
async function load(preserveDraft=false){
 if(loading)return;
 const draft=preserveDraft&&record?{
  date:date.value,ementor:$('shift-ementor').checked,clearQuote:$('shift-clear-quote').checked,
  fico:$('shift-fisco').value,returns:$('shift-returns').value
 }:null;
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
   // Keep the original instructions card, acknowledgement and change highlighting.
   // When only a manager comment is published, omit the nonexistent route details.
   const hasRoute=!!(assignment.holding_time||assignment.route||assignment.stg_location);
   routeCard.querySelector('h2').textContent=hasRoute?'Your route assignment':'Instructions from Operations';
   routeMessage.hidden=!hasRoute;
   routeVan.hidden=!hasRoute;
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
  if(draft&&draft.date===date.value){
   if(!record.ementor_submitted_at)$('shift-ementor').checked=draft.ementor;
   if(!record.clear_quote_submitted_at)$('shift-clear-quote').checked=draft.clearQuote;
   if(!record.fisco_submitted_at)$('shift-fisco').value=draft.fico;
   if(!record.returns_submitted_at)$('shift-returns').value=draft.returns;
  }
 }catch(e){$('shift-route-card').hidden=true;record=null;states.pre.textContent=states.post.textContent='Unable to load: '+e.message;return}
 finally{loading=false;if(record)refresh()}
}
async function save(section){
 if(loading||date.value!==today||!record)return;
 const pending=checks[section].filter(([,id,stamp])=>!record[stamp]&&(
  section==='pre'?$(id).checked:$(id).value.trim()!==''
 ));
 if(!pending.length){states[section].textContent='Nothing selected. Check or enter an outstanding item first.';return;}
 if(section==='post'){
  const score=$('shift-fisco').value,returns=$('shift-returns').value;
  if(!record.fisco_submitted_at&&score!==''&&(!/^\d{1,3}$/.test(score)||Number(score)>850)){
   states.post.textContent='Enter a FICO Score of 0–850.';return;
  }
  if(!record.returns_submitted_at&&returns!==''&&!/^\d{1,9}$/.test(returns)){
   states.post.textContent='Enter a non-negative whole number of returns.';return;
  }
 }
 const names=pending.map(([, , ,label])=>label).join(', ');
 if(!confirm('Submit '+names+' for today?\n\nOnly these items will be locked.'))return;
 loading=true;buttons[section].disabled=true;
 fields[section].forEach(el=>el.disabled=true);
 states[section].textContent='Saving…';
 const body=new URLSearchParams({_token:window.DULOGIX_SHIFT_TOKEN,date:date.value,section,
  ementor:pending.some(([key])=>key==='ementor')?'1':'0',
  clear_quote:pending.some(([key])=>key==='clear_quote')?'1':'0',
  fisco_score:pending.some(([key])=>key==='fisco_score')?$('shift-fisco').value:'',
  returns_count:pending.some(([key])=>key==='returns_count')?$('shift-returns').value:''});
 try{
  const r=await fetch('/driver/daily-check/data',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});
  const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Save failed');
  for(const [key,,stamp] of pending)if(j.submitted.includes(key))record[stamp]=j.saved_at;
  states[section].textContent=names+' submitted.';
 }catch(e){
  loading=false;refresh();
  states[section].textContent='Not saved — '+e.message;
  return;
 }
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
date.addEventListener('change',()=>load());
window.addEventListener('dulogix:refresh-instructions',()=>load(true));
load();
})();
