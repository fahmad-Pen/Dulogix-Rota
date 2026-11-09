(()=>{
'use strict';
window.addEventListener('beforeprint',()=>{
 document.querySelectorAll('form.entry textarea[name="comments"]').forEach(el=>{
  let mirror=el.nextElementSibling;
  if(!mirror||!mirror.classList.contains('ops-print-comment')){
   mirror=document.createElement('span');mirror.className='ops-print-comment';el.after(mirror);
  }
  mirror.textContent=el.value;
 });
 document.querySelectorAll('.entry input[type="time"],.entry select,.entry textarea').forEach(el=>{
  el.dataset.printEmpty=el.value===''?'1':'0';
 });
});
document.querySelectorAll('form.entry').forEach(form=>{
 const score=form.elements.fisco_score,returns=form.elements.returns_count;
 if(!score||!returns)return;
 const save=async()=>{
  const s=score.value.trim(),n=returns.value.trim(),status=form.querySelector('.save-state');
  if((s!==''&&(!/^\d{1,3}$/.test(s)||Number(s)>850))||(n!==''&&!/^\d{1,3}$/.test(n))){status.textContent='Invalid FICO/returns';return}
  score.disabled=returns.disabled=true;status.textContent='Saving score?';
  const body=new FormData();
  body.set('_token',form.elements._token.value);
  body.set('driver_id',form.elements.driver_id.value);
  body.set('action','score_override');
  body.set('fisco_score',s);body.set('returns_count',n);
  try{
   const response=await fetch(location.pathname+location.search,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body,credentials:'same-origin'});
   const result=await response.json();
   if(!response.ok||!result.ok)throw Error(result.error||'Unable to save');
   status.textContent='Score saved ?';
  }catch(error){status.textContent=error.message;status.classList.add('error')}
  finally{score.disabled=returns.disabled=false}
 };
 let timer;
 for(const el of [score,returns]){
  el.addEventListener('change',()=>{clearTimeout(timer);save()});
 }
});
})();
