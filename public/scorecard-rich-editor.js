(()=>{
'use strict';
const editor=document.getElementById('scorecard-rich-editor'),field=document.getElementById('scorecard-rich-value');
if(!editor||!field)return;
const style=document.createElement('style');
style.textContent='.scorecard-rich-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0;padding:10px;border:1px solid #b9cbdc;border-radius:10px;background:#edf4fb}.scorecard-rich-toolbar button,.scorecard-rich-toolbar label{padding:7px 10px;background:white;border:1px solid #b9cbdc;border-radius:7px;cursor:pointer;font-size:14px}.scorecard-rich-toolbar label{display:flex;align-items:center;gap:8px}.scorecard-rich-toolbar input[type=color]{width:35px;height:28px;padding:0;border:0}.scorecard-rich-editor{min-height:210px;max-height:520px;overflow:auto;padding:14px;border:1px solid #b9cbdc;border-radius:10px;background:white;line-height:1.5}.scorecard-rich-editor:focus{outline:2px solid #357ca9}.scorecard-rich-editor[contenteditable=false]{background:#f4f7fa}.scorecard-rich-editor p{margin:0 0 8px}.scorecard-rich-editor ul{padding-left:25px}';document.head.append(style);
const sync=()=>{field.value=editor.innerHTML};
field.closest('form')?.addEventListener('submit',sync);
editor.addEventListener('input',sync);
// Keep a non-collapsed selection even while the native colour picker has focus.
let savedRange=null;
function rememberSelection(){
 const selection=window.getSelection();
 if(!selection||!selection.rangeCount)return;
 const range=selection.getRangeAt(0);
 if(!range.collapsed&&editor.contains(range.commonAncestorContainer))savedRange=range.cloneRange();
}
function restoreSelection(){
 editor.focus();
 if(!savedRange)return false;
 const selection=window.getSelection();
 selection.removeAllRanges();selection.addRange(savedRange);
 return true;
}
function applyColour(property,value){
 if(!savedRange||savedRange.collapsed)return;
 const range=savedRange.cloneRange();
 if(!editor.contains(range.commonAncestorContainer))return;
 const span=document.createElement('span');
 span.style[property]=value;
 // Extracting and reinserting supports selections spanning several text nodes.
 span.appendChild(range.extractContents());
 range.insertNode(span);
 const selection=window.getSelection();
 selection.removeAllRanges();
 const updated=document.createRange();updated.selectNodeContents(span);
 selection.addRange(updated);
 savedRange=updated.cloneRange();
 sync();
}
editor.addEventListener('mouseup',rememberSelection);
editor.addEventListener('keyup',rememberSelection);
document.addEventListener('selectionchange',()=>{
 const selection=window.getSelection();
 if(selection?.rangeCount&&!selection.isCollapsed&&editor.contains(selection.getRangeAt(0).commonAncestorContainer))rememberSelection();
});
const toolbar=document.querySelector('.scorecard-rich-toolbar');
toolbar?.addEventListener('pointerdown',rememberSelection,true);
document.querySelectorAll('.scorecard-rich-toolbar [data-command]').forEach(control=>{
 if(control.type==='color'){
  const property=control.dataset.command==='foreColor'?'color':'backgroundColor';
  control.addEventListener('input',()=>applyColour(property,control.value));
  control.addEventListener('change',()=>applyColour(property,control.value));
 }else control.addEventListener('click',()=>{
  restoreSelection();
  document.execCommand(control.dataset.command,false,null);
  rememberSelection();sync();
 });
});
})();
