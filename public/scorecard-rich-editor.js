(()=>{
'use strict';
const editor=document.getElementById('scorecard-rich-editor'),field=document.getElementById('scorecard-rich-value');
if(!editor||!field)return;

const sync=()=>{field.value=editor.innerHTML};
field.closest('form')?.addEventListener('submit',sync);
editor.addEventListener('input',sync);
let savedRange=null;
function rememberSelection(){
 const selection=window.getSelection();
 if(!selection||!selection.rangeCount||selection.isCollapsed)return;
 const range=selection.getRangeAt(0);
 if(editor.contains(range.commonAncestorContainer))savedRange=range.cloneRange();
}
function restoreSelection(){
 if(!savedRange)return false;
 if(!editor.contains(savedRange.commonAncestorContainer))return false;
 editor.focus();
 const selection=window.getSelection();
 selection.removeAllRanges();selection.addRange(savedRange);
 return true;
}
function applyColour(property,value){
 if(!savedRange||savedRange.collapsed||!editor.contains(savedRange.commonAncestorContainer))return;
 restoreSelection();
 const range=savedRange.cloneRange();
 const span=document.createElement('span');
 span.style[property]=value;
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
if(!toolbar)return;
const colours=['#183653','#000000','#ffffff','#ff0000','#e92336','#b91c1c','#ff6600','#ffa500','#ffff00','#facc15','#00ff00','#008000','#15803d','#22c55e','#00ffff','#00bfff','#0000ff','#2563eb','#7c3aed','#ff00ff','#ec4899','#64748b','#c7f1ce','#c6efce','#fde68a','#bfdbfe','#fbcfe8','#ffffcc','#e2f0d9','#d9ead3','#f4cccc','#d9eaf7'];
let openPalette=null;
function closePalette(){if(openPalette){openPalette.hidden=true;openPalette=null;}}
toolbar.querySelectorAll('input[type="color"][data-command]').forEach(input=>{
 const property=input.dataset.command==='foreColor'?'color':'backgroundColor';
 const group=document.createElement('div');group.className='scorecard-colour-group';
 const trigger=document.createElement('button');trigger.type='button';
 trigger.setAttribute('aria-haspopup','true');trigger.setAttribute('aria-expanded','false');
 trigger.textContent=property==='color'?'Text colour':'Highlight';
 const swatch=document.createElement('span');swatch.className='scorecard-colour-swatch';swatch.style.background=input.value;trigger.appendChild(swatch);
 const palette=document.createElement('div');palette.className='scorecard-colour-palette';palette.hidden=true;palette.setAttribute('aria-label',property==='color'?'Text colours':'Highlight colours');
 for(const colour of colours){
  const choice=document.createElement('button');choice.type='button';choice.style.background=colour;choice.title=colour;choice.setAttribute('aria-label','Apply '+colour);
  choice.addEventListener('pointerdown',e=>e.preventDefault());
  choice.addEventListener('click',()=>{applyColour(property,colour);swatch.style.background=colour;closePalette();trigger.setAttribute('aria-expanded','false');});
  palette.appendChild(choice);
 }
 trigger.addEventListener('pointerdown',rememberSelection);
 trigger.addEventListener('click',()=>{
  const opening=palette.hidden;closePalette();palette.hidden=!opening;openPalette=opening?palette:null;trigger.setAttribute('aria-expanded',String(opening));
 });
 group.append(trigger,palette);
 input.closest('label')?.replaceWith(group);
});
document.addEventListener('pointerdown',e=>{if(!e.target.closest('.scorecard-colour-group'))closePalette();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closePalette();});
toolbar.querySelectorAll('[data-command]').forEach(control=>{
 control.addEventListener('pointerdown',rememberSelection);
 control.addEventListener('click',()=>{
  restoreSelection();document.execCommand(control.dataset.command,false,null);rememberSelection();sync();
 });
});
})();
