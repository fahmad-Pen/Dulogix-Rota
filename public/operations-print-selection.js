(()=>{
const table=document.querySelector('main .card table');
const printButton=document.getElementById('print-report');
if(!table||!printButton)return;
const rows=[...table.querySelectorAll('tbody tr[data-allocation]')];
const style=document.createElement('style');
style.textContent='.ops-print-choice{display:inline-flex;align-items:center;gap:6px;margin-right:8px;vertical-align:middle}.ops-print-choice input{width:16px;height:16px;accent-color:#176849;cursor:pointer}.ops-select-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:8px 0}.ops-select-toolbar button{background:#173b60;color:#fff;padding:8px 12px}.ops-select-toolbar .ops-select-all{background:white;color:#173b60}.ops-select-count{font-weight:600;color:#52677c}.ops-select-toolbar .ops-print-selected{background:#087d53}@media print{.ops-select-toolbar,.ops-print-choice{display:none!important}body.ops-print-only-selected tbody tr[data-allocation]:not(.ops-print-picked){display:none!important}body.ops-print-only-selected tbody tr[data-allocation].ops-print-picked{display:table-row!important}}';
document.head.append(style);
const toolbar=document.createElement('div');toolbar.className='ops-select-toolbar';
const allLabel=document.createElement('label');allLabel.className='ops-print-choice';allLabel.style.marginRight='0';
const all=document.createElement('input');all.type='checkbox';all.setAttribute('aria-label','Select all displayed records for PDF');
allLabel.append(all,document.createTextNode('Select all displayed'));
const clear=document.createElement('button');clear.type='button';clear.className='ops-select-all';clear.textContent='Clear selection';
const selected=document.createElement('button');selected.type='button';selected.className='ops-print-selected';selected.textContent='Print selected / PDF (A4)';
const count=document.createElement('span');count.className='ops-select-count';count.setAttribute('aria-live','polite');
const exportAll=document.createElement('button');exportAll.type='button';exportAll.textContent='Download Excel (all)';
const exportSelected=document.createElement('button');exportSelected.type='button';exportSelected.textContent='Download Excel (selected)';
toolbar.append(allLabel,clear,selected,exportAll,exportSelected,count);
printButton.insertAdjacentElement('afterend',exportAll);
exportAll.style.marginLeft='8px';
const bar=printButton.closest('.datebar');bar?.after(toolbar);

const compactStyle=document.createElement('style');
compactStyle.textContent=`#ops-compact-print{display:none}@media print{@page{size:A4 portrait;margin:11mm}body.ops-compact-print-active{background:#fff!important;margin:0!important;padding:0!important}body.ops-compact-print-active> :not(#ops-compact-print){display:none!important}body.ops-compact-print-active #ops-compact-print{display:block!important;position:static!important;visibility:visible!important;width:100%!important;margin:0!important;padding:0!important;color:#172d42!important;font:10pt Arial,sans-serif!important}#ops-compact-print h1{font-size:17pt;margin:0 0 5mm}#ops-compact-print .date{font-size:11pt;margin-bottom:6mm}#ops-compact-print table{display:table!important;width:100%!important;min-width:0!important;border-collapse:collapse!important;table-layout:fixed!important}#ops-compact-print thead{display:table-header-group!important}#ops-compact-print tr{display:table-row!important;break-inside:avoid;page-break-inside:avoid}#ops-compact-print th,#ops-compact-print td{display:table-cell!important;border:1px solid #8797a6!important;padding:6px 4px!important;white-space:normal!important;overflow-wrap:anywhere!important;vertical-align:middle!important;font-size:9pt!important;height:17mm!important}#ops-compact-print th{background:#e6eef6!important;text-align:left!important}#ops-compact-print th:nth-child(1){width:18%}#ops-compact-print th:nth-child(2){width:12%}#ops-compact-print th:nth-child(3){width:12%}#ops-compact-print th:nth-child(4){width:11%}#ops-compact-print th:nth-child(5){width:17%}#ops-compact-print th:nth-child(6){width:21%}#ops-compact-print th:nth-child(7){width:9%}#ops-compact-print .comments{white-space:pre-wrap!important}}`;
document.head.append(compactStyle);
window.addEventListener('afterprint',()=>{document.body.classList.remove('ops-compact-print-active');document.getElementById('ops-compact-print')?.remove();});
const compactPrint=document.createElement('button');
compactPrint.type='button';
compactPrint.className='print-button';
compactPrint.textContent='Print / PDF (A4) Few columns';
compactPrint.style.marginLeft='8px';
printButton.insertAdjacentElement('afterend',compactPrint);
function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
compactPrint.addEventListener('click',()=>{
 const displayed=[...table.querySelectorAll('tbody tr[data-allocation]')].filter(row=>!row.hidden);
 if(!displayed.length){alert('No displayed drivers to print.');return;}
 const hasSavedValues=displayed.some(row=>{const f=row.querySelector('form.entry');return f&&(f.elements.van?.value||f.elements.start_time?.value||f.elements.comments?.value||f.elements.fisco_score?.value);});
 if(!hasSavedValues&&!window.confirm('No saved Van, Start Time, Comments or FICO scores were found for the displayed drivers on this date. This will print a mostly blank driver sign-off sheet. Continue?'))return;
 const date=document.getElementById('report-date')?.value||'';
 const bodyRows=displayed.map(row=>{
  const form=row.querySelector('form.entry');
  const name=row.cells[0].querySelector('strong')?.textContent.trim()||'';
  const van=form.elements.van.value ? (form.elements.van.selectedOptions[0]?.textContent.trim()||form.elements.van.value) : '—';
  const time=form.elements.start_time.value||'—';
  const status=form.querySelector('.status-pill')?.textContent.trim()||'';
  const comments=form.elements.comments.value||'';
  const fico=form.elements.fisco_score?.value||row.querySelector('input[name="fisco_score"]')?.value||'—';
  return '<tr><td>'+escapeHtml(name)+'</td><td>'+escapeHtml(van)+'</td><td>'+escapeHtml(time)+'</td><td>'+escapeHtml(status)+'</td><td class="signature"></td><td class="comments">'+escapeHtml(comments)+'</td><td>'+escapeHtml(fico)+'</td></tr>';
 }).join('');
 const page='<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Dulogix Daily Operations - '+escapeHtml(date)+'</title><link rel="icon" href="/favicon.png"><style>@page{size:A4 portrait;margin:11mm}*{box-sizing:border-box}body{font:10pt Arial,sans-serif;color:#172d42;margin:0}h1{font-size:17pt;margin:0 0 5mm}.date{font-size:11pt;margin-bottom:6mm}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #8797a6;padding:6px 4px;overflow-wrap:anywhere;vertical-align:middle}th{background:#e6eef6;font-size:9pt;text-align:left}td{height:17mm}th:nth-child(1){width:18%}th:nth-child(2){width:12%}th:nth-child(3){width:12%}th:nth-child(4){width:11%}th:nth-child(5){width:17%}th:nth-child(6){width:21%}th:nth-child(7){width:9%}.signature{background:#fff}.comments{white-space:pre-wrap}tr{break-inside:avoid;page-break-inside:avoid}thead{display:table-header-group}@media screen{body{max-width:210mm;margin:20px auto;padding:11mm;box-shadow:0 0 12px #aaa}}@media print{body{margin:0}}</style></head><body><h1>Dulogix — Daily Operations</h1><div class="date">Date: '+escapeHtml(date)+'</div><table><thead><tr><th>Driver Name</th><th>Van</th><th>Start Time</th><th>Status</th><th>Signature</th><th>Comments</th><th>FICO Score</th></tr></thead><tbody>'+bodyRows+'</tbody></table></body></html>';
 // Print from the current document: zero-sized iframes can produce blank PDFs in Chrome.
 const previous=document.getElementById('ops-compact-print');
 if(previous)previous.remove();
 const printable=document.createElement('section');
 printable.id='ops-compact-print';
 printable.innerHTML=page.slice(page.indexOf('<body>')+6,page.lastIndexOf('</body>'));
 document.body.append(printable);
 document.body.classList.remove('ops-print-only-selected');
 document.body.classList.add('ops-compact-print-active');
 window.print();

});

const checkboxes=rows.map(row=>{
 const label=document.createElement('label');label.className='ops-print-choice';
 const box=document.createElement('input');box.type='checkbox';box.setAttribute('aria-label','Select '+(row.cells[0].querySelector('strong')?.textContent.trim()||'driver')+' for PDF');
 label.append(box);row.cells[0].prepend(label);
 box.addEventListener('change',()=>{row.classList.toggle('ops-print-picked',box.checked);refresh()});
 return {row,box};
});
function visible(){return checkboxes.filter(item=>!item.row.hidden)}
function refresh(){
 const shown=visible(),chosen=checkboxes.filter(item=>item.box.checked);
 all.checked=shown.length>0&&shown.every(item=>item.box.checked);
 all.indeterminate=shown.some(item=>item.box.checked)&&!all.checked;
 count.textContent=chosen.length+' selected';
 selected.disabled=chosen.length===0;
 exportSelected.disabled=chosen.length===0;
}
all.addEventListener('change',()=>{visible().forEach(({row,box})=>{box.checked=all.checked;row.classList.toggle('ops-print-picked',box.checked)});refresh()});
clear.addEventListener('click',()=>{checkboxes.forEach(({row,box})=>{box.checked=false;row.classList.remove('ops-print-picked')});refresh()});
selected.addEventListener('click',()=>{if(!checkboxes.some(({box})=>box.checked))return;document.body.classList.add('ops-print-only-selected');document.activeElement.blur();window.print()});
function cellValue(cell){
 const clone=cell.cloneNode(true);
 clone.querySelectorAll('.ops-print-choice,button,script,style,[hidden],[aria-hidden="true"],input[type="hidden"],svg').forEach(el=>el.remove());
 const originals=[...cell.querySelectorAll('select,input,textarea')].filter(el=>!el.closest('.ops-print-choice'));
 clone.querySelectorAll('select,input,textarea').forEach((el,i)=>{
  const original=originals[i];let value='';
  if(original?.tagName==='SELECT')value=original.selectedOptions[0]?.textContent||'';
  else if(original?.type==='checkbox')value=original.checked?'Yes':'No';
  else value=original?.value||'';
  el.replaceWith(document.createTextNode(value));
 });
 clone.querySelectorAll('*').forEach(el=>{if(el.getAttribute('style')?.match(/display\s*:\s*none|visibility\s*:\s*hidden/i))el.remove()});
 return clone.textContent.replace(/\s+/g,' ').trim();
}
async function downloadExcel(onlySelected){
 const chosen=checkboxes.filter(({row,box})=>!row.hidden&&(!onlySelected||box.checked));
 if(!chosen.length)return;
 const headings=['Driver','Allocation','Van','Fixed','Start time','Rota status','Comments','Ementor','Clear Quote','FISCO score','Number of returns'];
 const rowsToExport=[headings,...chosen.map(({row})=>{
  const form=row.querySelector('form.entry');
  const values=[...form.querySelectorAll('.flag,.pending')].map(el=>el.textContent.trim());
  return [
   row.cells[0].querySelector('strong')?.textContent.trim()||'',
   row.dataset.allocation||'',
   form.elements.van.selectedOptions[0]?.textContent.trim()||'',
   form.elements.fixed.checked?'Yes':'No',
   form.elements.start_time.value||'',
   form.querySelector('.status-pill')?.textContent.trim()||'',
   form.elements.comments.value||'',
   ...values
  ];
 })];
 if(!window.dulogixSaveXlsx){
  await new Promise((resolve,reject)=>{
   const script=document.createElement('script');script.src='/operations-xlsx.js';
   script.onload=resolve;script.onerror=reject;document.head.append(script);
  });
 }
 window.dulogixSaveXlsx(rowsToExport,'Dulogix-Operations-'+(onlySelected?'Selected-':'')+new Date().toISOString().slice(0,10)+'.xlsx');
}
exportAll.addEventListener('click',()=>downloadExcel(false));
exportSelected.addEventListener('click',()=>downloadExcel(true));
window.addEventListener('afterprint',()=>document.body.classList.remove('ops-print-only-selected'));
printButton.addEventListener('click',()=>document.body.classList.remove('ops-print-only-selected'),true);
new MutationObserver(refresh).observe(table.tBodies[0],{subtree:true,attributes:true,attributeFilter:['hidden']});
refresh();
})();
