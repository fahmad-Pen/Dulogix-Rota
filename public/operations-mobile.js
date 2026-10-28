(()=>{
const rows=[...document.querySelectorAll('tbody tr[data-allocation]')];
const mobile=()=>window.matchMedia('(max-width:767px)').matches;
for(const row of rows){
 const nameCell=row.cells[0],detailCell=row.cells[2],form=row.querySelector('form.entry');
 if(!form)continue;
 const parts=[...form.children].filter(element=>element.type!=='hidden');
 const fields=[['Ementor',parts[4]],['Clear Quote',parts[5]],['FISCO',parts[6]],['Returns',parts[7]]];
 const button=document.createElement('button');button.type='button';button.className='ops-mobile-toggle';button.setAttribute('aria-expanded','false');
 const title=document.createElement('span');title.className='ops-mobile-title';
 const arrow=document.createElement('span');arrow.className='ops-mobile-arrow';arrow.textContent='▸';
 const name=document.createElement('span');name.innerHTML=nameCell.innerHTML;
 const allocation=document.createElement('span');allocation.className='ops-mobile-allocation';allocation.textContent=row.cells[1].textContent.trim();
 title.append(arrow,name,allocation);
 const indicators=document.createElement('span');indicators.className='ops-mobile-indicators';
 for(const [label,source] of fields){
  const raw=source?.textContent.trim()??'';
  const done=label==='Ementor'||label==='Clear Quote'?raw.toLowerCase()==='yes':raw!==''&&raw!=='—'&&raw!=='-';
  const chip=document.createElement('span');chip.className='ops-mobile-chip '+(done?'ops-complete':'ops-incomplete');
  chip.textContent=(done?'✓ ':'✕ ')+label;chip.title=label+': '+(done?'submitted/completed':'not completed or missing');
  indicators.append(chip);
 }
 button.append(title,indicators);nameCell.append(button);
 detailCell.classList.add('ops-mobile-detail');
 const labels=['Van','Start time','Rota status','Comments','Ementor','Clear Quote','FISCO score','Number of returns'];
 parts.slice(0,8).forEach((part,index)=>{const wrapper=document.createElement('div');wrapper.className='ops-mobile-field ops-mobile-field-'+index;const label=document.createElement('span');label.className='ops-mobile-label';label.textContent=labels[index];part.before(wrapper);wrapper.append(label,part);});
 button.addEventListener('click',()=>{
  if(!mobile())return;
  const opening=!row.classList.contains('ops-expanded');
  rows.forEach(other=>{other.classList.remove('ops-expanded');const b=other.querySelector('.ops-mobile-toggle');if(b){b.setAttribute('aria-expanded','false');b.querySelector('.ops-mobile-arrow').textContent='▸';}});
  if(opening){row.classList.add('ops-expanded');button.setAttribute('aria-expanded','true');arrow.textContent='▾';requestAnimationFrame(()=>requestAnimationFrame(()=>{window.scrollTo({top:Math.max(0,row.getBoundingClientRect().top+window.scrollY-12),behavior:'smooth'});}));}
 });
}
})();