(()=>{
'use strict';
const triggers=[...document.querySelectorAll('.driver-change-trigger')];
if(!triggers.length)return;
const rows=[...document.querySelectorAll('tr[data-driver-id]')];
const drivers=rows.map(row=>{
 const fields=row.querySelectorAll('.lane-value');
 const form=row.querySelector('form.entry');
 return {
  id:row.dataset.driverId,
  name:row.querySelector('td strong')?.textContent.trim()||'',
  route:fields[3]?.textContent.trim()||'',
  holding:fields[1]?.textContent.trim()||'',
  stg:fields[2]?.textContent.trim()||'',
  van:form?.elements.van?.value||'',
  start:form?.elements.start_time?.value||'',
  fixed:!!form?.elements.fixed?.checked
 };
});
const hasRoute=d=>d.route&&d.route!=='—';
const dialog=document.createElement('dialog');dialog.className='driver-change-modal';
dialog.innerHTML='<form method="post" action="/operations/change-driver" class="driver-change-content"><h2>Change / Swap Driver</h2><p>Choose a replacement driver. If they already have a route, the two assignments will be swapped.</p><div class="driver-change-source" aria-live="polite"><span>Current driver</span><strong></strong><small></small></div><input type="hidden" name="_token"><input type="hidden" name="date"><input type="hidden" name="from_driver"><input type="hidden" name="to_driver"><label for="driver-change-search">Search driver</label><input id="driver-change-search" type="search" placeholder="Name or driver ID" autocomplete="off"><label for="driver-change-select">Replacement driver</label><select id="driver-change-select" size="7" required></select><div class="driver-change-preview" aria-live="polite">Choose a driver to preview the change.</div><div class="driver-change-actions"><button type="button" class="driver-change-cancel">Cancel</button><button type="submit" class="driver-change-confirm" disabled>Confirm change</button></div></form>';
document.body.append(dialog);
const form=dialog.querySelector('form'),search=dialog.querySelector('#driver-change-search'),select=dialog.querySelector('#driver-change-select'),preview=dialog.querySelector('.driver-change-preview'),confirm=dialog.querySelector('.driver-change-confirm');
let source=null;
function resolveVans(source,target,swap){
 if(swap){if(source.fixed||target.fixed)return [source.van,target.van];return [target.van,source.van];}
 return [source.fixed?source.van:'',target.fixed?target.van:(source.fixed?target.van:(source.van||target.van))];
}
function showPreview(){
 const target=drivers.find(d=>d.id===select.value);
 confirm.disabled=!target;
 if(!target){preview.textContent='Choose a driver to preview the change.';return;}
 const swap=hasRoute(target);
 const [sourceVan,targetVan]=resolveVans(source,target,swap);
 const warnings=[];
 if(!sourceVan&&swap)warnings.push(source.name+' will need a van.');
 if(!targetVan)warnings.push(target.name+' will need a van.');
 const lines=[
  (swap?'SWAP ROUTES':'CHANGE DRIVER'),
  source.name+': '+(swap?target.route+' (Holding '+target.holding+', STG '+target.stg+')':'Unassigned')+' | Start: '+(swap?(target.start||'Not set'):'Not set')+' | Van: '+(sourceVan||'Not assigned'),
  target.name+': '+source.route+' (Holding '+source.holding+', STG '+source.stg+') | Van: '+(targetVan||'Not assigned'),
  'Fixed vans stay with their drivers. If either driver has a fixed van, existing non-fixed vans are also retained.',
  'If already published, both drivers’ assignments update immediately.'
 ];
 if(warnings.length)lines.push('Attention: '+warnings.join(' '));
 preview.textContent=lines.join('\n');
 form.elements.to_driver.value=target.id;
 confirm.textContent=swap?'Confirm swap':'Confirm change';
}
function populate(){
 const q=search.value.toLocaleLowerCase().trim();
 const previous=select.value;
 select.replaceChildren();
 for(const d of drivers){
  if(d.id===source.id||!(d.name+' '+d.id).toLocaleLowerCase().includes(q))continue;
  const option=document.createElement('option');
  option.value=d.id;
  option.textContent=d.name+(hasRoute(d)?' — '+d.route:' — Unassigned')+(d.fixed?' · Fixed van':'');
  select.append(option);
 }
 if([...select.options].some(o=>o.value===previous))select.value=previous;
 else select.selectedIndex=-1;
 showPreview();
}
for(const button of triggers)button.addEventListener('click',()=>{
 source=drivers.find(d=>d.id===button.dataset.driverId);
 if(!source||!hasRoute(source)){window.alert('This driver has no imported route to change.');return;}
 form.elements.from_driver.value=source.id;
 form.elements.date.value=new URLSearchParams(location.search).get('date')||document.querySelector('input[type=date]')?.value||'';
 form.elements._token.value=button.closest('tr')?.querySelector('input[name="_token"]')?.value||'';
 const sourceBox=dialog.querySelector('.driver-change-source');sourceBox.querySelector('strong').textContent=source.name;sourceBox.querySelector('small').textContent='Route: '+source.route+' · Holding: '+source.holding+' · STG: '+source.stg+' · Start: '+(source.start||'Not set')+' · Van: '+(source.van||'Not assigned')+(source.fixed?' (Fixed)':'');
 search.value='';populate();dialog.showModal();search.focus();
});
search.addEventListener('input',populate);
select.addEventListener('change',showPreview);
dialog.querySelector('.driver-change-cancel').addEventListener('click',()=>dialog.close());
form.addEventListener('submit',e=>{if(!source||!select.value||confirm.disabled){e.preventDefault();return;}confirm.disabled=true;});
})();
