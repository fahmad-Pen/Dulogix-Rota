(()=>{
'use strict';
const triggers=[...document.querySelectorAll('.driver-change-trigger')];
if(!triggers.length)return;
const css=document.createElement('style');
css.textContent='tbody tr[data-driver-id]>td:first-child{display:grid;grid-template-columns:22px 22px minmax(0,1fr);column-gap:5px;align-items:start;text-align:left}tbody tr[data-driver-id]>td:first-child>.ops-print-choice{grid-column:1;grid-row:1}tbody tr[data-driver-id]>td:first-child>.row-number{grid-column:2;grid-row:1;min-width:0}tbody tr[data-driver-id]>td:first-child>.driver-name-actions{grid-column:3;grid-row:1;min-width:0;display:flex;align-items:center;align-content:start;gap:4px;flex-wrap:wrap}.driver-name-actions strong{min-width:0;overflow-wrap:break-word}.driver-name-actions .driver-change-trigger{flex:none}.driver-change-source{background:#eaf3fb;border:1px solid #c7dbea;border-radius:9px;padding:12px;margin:14px 0}.driver-change-source strong{display:block;margin-top:4px;font-size:16px}.driver-change-source small{display:block;margin-top:5px;color:#52677c}.driver-change-trigger{margin-left:5px;padding:1px 5px;border:1px solid #c6d7e8;background:#eaf3fb;color:#174d78;border-radius:5px;font-size:17px;line-height:1.1;vertical-align:middle;cursor:pointer}.driver-change-trigger:hover{background:#d1e8fa}.driver-change-modal{border:0;border-radius:14px;padding:0;width:min(510px,calc(100vw - 24px));max-height:90vh;box-shadow:0 22px 80px #10284055}.driver-change-modal::backdrop{background:#10243b99}.driver-change-content{padding:24px;color:#19344f;font:14px system-ui}.driver-change-content h2{margin:0 0 8px;font-size:21px}.driver-change-content p{line-height:1.5}.driver-change-content label{display:block;font-weight:700;margin:15px 0 5px}.driver-change-content input[type=search],.driver-change-content select{width:100%;padding:11px;border:1px solid #b9c9d8;border-radius:7px}.driver-change-preview{background:#f1f6fb;padding:13px;border-radius:9px;margin-top:14px;white-space:pre-line;line-height:1.65}.driver-change-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}.driver-change-actions button{padding:10px 14px;border-radius:7px}.driver-change-cancel{background:#edf2f7;color:#19344f}.driver-change-confirm{background:#176849;color:white}.driver-change-warning{color:#a43c30;font-weight:600}';
document.head.append(css);
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
