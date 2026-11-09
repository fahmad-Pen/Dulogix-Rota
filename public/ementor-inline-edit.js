(()=>{const style=document.createElement('style');style.textContent='input.ementor-score-input::-webkit-inner-spin-button,input.ementor-score-input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}input.ementor-score-input{-moz-appearance:textfield;appearance:textfield}';document.head.append(style)})();
(()=>{
'use strict';
const cfg=window.ementorEditConfig;
if(!cfg||!cfg.editable)return;
document.querySelectorAll('#performance-drivers tbody tr').forEach(row=>{
 const report=row.querySelector('.driver-report-open');
 const scoreCell=row.cells[2];
 if(!report||!scoreCell)return;
 const badge=scoreCell.querySelector('span');
 const missing=!badge;
 const input=document.createElement('input');
 input.type='number';input.className='ementor-score-input';input.min='0';input.max='850';input.step='0.01';
 input.value=report.dataset.fico && report.dataset.fico.trim()!=='' && Number.isFinite(Number(report.dataset.fico))?report.dataset.fico:'';
 input.dataset.saved=input.value;
 input.setAttribute('aria-label','Ementor score for '+report.dataset.driver);
 input.style.cssText='width:56px;max-width:100%;box-sizing:border-box;font-size:13px;text-align:center;padding:6px 5px;border:1px solid #a8b9cb;border-radius:5px;font-weight:700;background:'+(missing?'#f1f5f9':getComputedStyle(badge).backgroundColor)+';color:'+(missing?'#17314e':getComputedStyle(badge).color);
 input.placeholder='Add';input.title='Ementor score (0?850)';
 input.style.appearance='textfield';input.style.MozAppearance='textfield';
 if(missing)scoreCell.replaceChildren(input);else badge.replaceWith(input);
 const note=document.createElement('div');note.setAttribute('role','status');note.style.cssText='font-size:11px;min-height:14px';input.after(note);
 const save=async()=>{
  if(input.value===input.dataset.saved)return;
  if(!input.checkValidity()||input.value.trim()===''){note.textContent='Invalid score';return;}
  input.disabled=true;note.textContent='Saving?';
  try{
   const body=new URLSearchParams({ementor_override_action:'save',_token:cfg.token,driver_id:report.dataset.userId,score:input.value});
   const res=await fetch('/driver-performance?tab=weekly&week='+encodeURIComponent(cfg.week),{method:'POST',credentials:'same-origin',body});
   const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'Save failed');
   input.dataset.saved=input.value;
   note.textContent='Saved';
   const score=Number(input.value);
   const palette=score<800?['#ffc7ce','#9c0006']:score<810?['#ffeb9c','#9c5700']:['#c6efce','#006100'];
   input.style.backgroundColor=palette[0];
   input.style.color=palette[1];
   report.dataset.fico=input.value;
  }catch(e){note.textContent=e.message;input.value=input.dataset.saved;}
  finally{input.disabled=false;}
 };
 input.addEventListener('change',save);
 input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();input.blur();}});
});
})();
