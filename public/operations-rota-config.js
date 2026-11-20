(()=>{
'use strict';
const element=document.getElementById('ops-rota-config');
if(!element)return;
const config=JSON.parse(element.textContent);
window.DULOGIX_OPS_ROTA={week:config.week,day:config.day,token:config.token,canEdit:config.canEdit};
if(config.readOnly){
 document.querySelectorAll('form.entry').forEach(form=>{
  form.querySelectorAll('select,input,textarea').forEach(el=>{
   if(el.type!=='hidden' && ((el.name!=='fisco_score' && el.name!=='returns_count') || !config.ficoOnly)){
    el.disabled=true;
    if(el.name==='returns_count')el.dataset.ficoOnly=true;
   }
  });
 });
}
})();
