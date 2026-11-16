(()=>{
'use strict';
const config=window.DULOGIX_OPS_ROTA;
if(!config)return;
document.querySelectorAll('.ops-allocation').forEach(select=>{
 const row=select.closest('tr');
 const state=row.querySelector('.save-state');
 if(!config.canEdit){select.disabled=true;select.title='Rota editing permission required';return;}
 select.addEventListener('change',async()=>{
  const previous=select.dataset.original, value=select.value;
  if(value===previous)return;
  select.disabled=true;
  state.textContent='Saving allocation…';
  state.classList.remove('error');
  try{
   const response=await fetch('/api/rota/'+config.week+'/cell',{
    method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-CSRF-Token':config.token},
    body:JSON.stringify({driver:'driver-'+row.dataset.driverId,field:'allocation',day:config.day,value})
   });
   const result=await response.json();
   if(!response.ok||!result.saved)throw Error(result.error||'Allocation not saved');
   select.dataset.original=value;
   const printed=select.parentElement.querySelector('.ops-allocation-print');
   if(printed)printed.textContent=value||'—';
   row.dataset.allocation=value;
   select.classList.toggle('allocation-warning',value!==''&&select.dataset.availability!=='IN');
   select.classList.toggle('allocation-in',value==='IN'&&select.dataset.availability==='IN');
   select.classList.toggle('allocation-other',value!==''&&value!=='IN');
   select.title=select.classList.contains('allocation-warning')?'Driver has not confirmed availability for this allocation':'';
   state.textContent='Allocation saved ✓';
  }catch(error){
   select.value=previous;
   state.textContent=error.message;
   state.classList.add('error');
  }finally{select.disabled=false;}
 });
});
})();
