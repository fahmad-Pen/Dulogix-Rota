(()=>{
'use strict';
const $=id=>document.getElementById(id),date=$('shift-date');
if(!date)return;
const fields={pre:[$('shift-ementor'),$('shift-clear-quote')],post:[$('shift-fisco'),$('shift-returns')]};
const states={pre:$('shift-pre-state'),post:$('shift-post-state')};
const timers={},busy={pre:false,post:false},queued={pre:false,post:false};
let loading=false;
async function load(){
 loading=true;Object.values(states).forEach(el=>el.textContent='Loading…');
 try{
  const r=await fetch('/driver/daily-check/data?date='+encodeURIComponent(date.value),{credentials:'same-origin'});
  const raw=await r.text();let j;try{j=JSON.parse(raw)}catch(e){throw Error('Server returned an error ('+r.status+'). Please refresh or contact your manager.')}if(!r.ok)throw Error(j.error||'Unable to load');
  $('shift-ementor').checked=!!Number(j.data.ementor);
  $('shift-clear-quote').checked=!!Number(j.data.clear_quote);
  $('shift-fisco').value=j.data.fisco_score??'';
  $('shift-returns').value=j.data.returns_count??'';
  Object.values(states).forEach(el=>el.textContent='Ready');
 }catch(e){Object.values(states).forEach(el=>el.textContent='Unable to load: '+e.message)}
 finally{loading=false}
}
function validate(){
 const score=$('shift-fisco').value,returns=$('shift-returns').value;
 if(score!==''&&(!/^\d{1,3}$/.test(score)||Number(score)>850))return 'Fisco Score must be a whole number from 0 to 850.';
 if(returns!==''&&!/^\d{1,9}$/.test(returns))return 'Returns must be a non-negative whole number.';
 return '';
}
async function save(section){
 if(loading)return;
 if(busy[section]){queued[section]=true;return}
 if(section==='post'){const error=validate();if(error){states.post.textContent=error;return}}
 busy[section]=true;states[section].textContent='Saving…';
 const body=new URLSearchParams({_token:window.DULOGIX_SHIFT_TOKEN,date:date.value,section,
  ementor:$('shift-ementor').checked?'1':'0',clear_quote:$('shift-clear-quote').checked?'1':'0',
  fisco_score:$('shift-fisco').value,returns_count:$('shift-returns').value});
 try{
  const r=await fetch('/driver/daily-check/data',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});
  const raw=await r.text();let j;try{j=JSON.parse(raw)}catch(e){throw Error('Server returned an error ('+r.status+'). Please retry.')}if(!r.ok||!j.ok)throw Error(j.error||'Save failed');
  states[section].textContent='Saved ✓ '+j.saved_at;
 }catch(e){states[section].textContent='Not saved — '+e.message}
 finally{busy[section]=false;if(queued[section]){queued[section]=false;save(section)}}
}
fields.pre.forEach(el=>el.addEventListener('change',()=>save('pre')));
fields.post.forEach(el=>{el.addEventListener('input',()=>{clearTimeout(timers.post);timers.post=setTimeout(()=>save('post'),700)});el.addEventListener('change',()=>{clearTimeout(timers.post);save('post')})});
date.addEventListener('change',()=>{clearTimeout(timers.post);load()});
load();
})();
