(()=>{
'use strict';
const $=id=>document.getElementById(id),date=$('shift-date');
if(!date)return;
const fields={pre:[$('shift-ementor'),$('shift-clear-quote')],post:[$('shift-fisco'),$('shift-returns')]};
const states={pre:$('shift-pre-state'),post:$('shift-post-state')};
const buttons={pre:$('shift-save-pre'),post:$('shift-save-post')};
let loading=false,record=null;
const today=date.max;
function formatTime(raw){
 if(!raw)return '';
 const parts=raw.replace('T',' ').split(' ');
 return parts[0].split('-').reverse().join('/')+' '+(parts[1]||'').slice(0,5)+' (UK time)';
}
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
  $('shift-ementor').checked=!!Number(record.ementor);
  $('shift-clear-quote').checked=!!Number(record.clear_quote);
  $('shift-fisco').value=record.fisco_score??'';
  $('shift-returns').value=record.returns_count??'';
 }catch(e){record=null;states.pre.textContent=states.post.textContent='Unable to load: '+e.message;return}
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
buttons.pre.addEventListener('click',()=>save('pre'));
buttons.post.addEventListener('click',()=>save('post'));
date.addEventListener('change',load);
load();
})();
