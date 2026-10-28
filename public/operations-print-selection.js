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
toolbar.append(allLabel,clear,selected,count);
const bar=printButton.closest('.datebar');bar?.after(toolbar);
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
}
all.addEventListener('change',()=>{visible().forEach(({row,box})=>{box.checked=all.checked;row.classList.toggle('ops-print-picked',box.checked)});refresh()});
clear.addEventListener('click',()=>{checkboxes.forEach(({row,box})=>{box.checked=false;row.classList.remove('ops-print-picked')});refresh()});
selected.addEventListener('click',()=>{if(!checkboxes.some(({box})=>box.checked))return;document.body.classList.add('ops-print-only-selected');document.activeElement.blur();window.print()});
window.addEventListener('afterprint',()=>document.body.classList.remove('ops-print-only-selected'));
printButton.addEventListener('click',()=>document.body.classList.remove('ops-print-only-selected'),true);
new MutationObserver(refresh).observe(table.tBodies[0],{subtree:true,attributes:true,attributeFilter:['hidden']});
refresh();
})();