(()=>{
const dialog=document.getElementById('top-five-dialog'),open=document.getElementById('top-five-open'),close=document.getElementById('top-five-close');
if(!dialog||!open)return;
const threshold=dialog.querySelector('#top-five-threshold'),selectionForm=dialog.querySelector('#top-five-selection-form');
if(threshold&&selectionForm){
 const savedThreshold=Number(threshold.value);
 const candidates=[...selectionForm.querySelectorAll('.top-five-candidate')];
 const count=dialog.querySelector('#top-five-count');
 const save=selectionForm.querySelector('button[type="submit"]');
 const orderField=dialog.querySelector('#top-five-order-value');
 const orderBox=dialog.querySelector('#top-five-order');
 let order=JSON.parse(orderField.value||'[]');
 function renderOrder(){
  const selected=candidates.filter(row=>row.querySelector('input').checked);
  const ids=selected.map(row=>row.querySelector('input').value);
  order=order.filter(id=>ids.includes(id));
  ids.forEach(id=>{if(!order.includes(id))order.push(id);});
  orderField.value=JSON.stringify(order);
  orderBox.replaceChildren();
  order.forEach((id,i)=>{
   const row=selected.find(r=>r.querySelector('input').value===id);
   const item=document.createElement('div');item.style.cssText='display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #e1e7ee';
   const label=document.createElement('span');label.style.cssText='flex:1;min-width:0';label.textContent=(i+1)+'. '+row.querySelector('span').textContent.split(' · Rank ')[0];item.append(label);
   for(const [symbol,delta] of [['↑',-1],['↓',1]]){
    const button=document.createElement('button');button.type='button';button.textContent=symbol;button.setAttribute('aria-label','Move '+label.textContent+(delta<0?' up':' down'));button.disabled=i+delta<0||i+delta>=order.length;
    button.addEventListener('click',()=>{const next=i+delta;[order[i],order[next]]=[order[next],order[i]];renderOrder();});item.append(button);
   }
   orderBox.append(item);
  });
 }
 function update(){
  const minimum=Number(threshold.value);
  const valid=threshold.value!==''&&Number.isInteger(minimum)&&minimum>=0;
  const dirty=!valid||minimum!==savedThreshold;
  let checked=0,visible=0;
  candidates.forEach(row=>{
   const qualifies=valid&&Number(row.dataset.delivered)>=minimum;
   row.hidden=!qualifies;
   row.style.display=qualifies?'grid':'none';
   const input=row.querySelector('input');
   if(!qualifies)input.checked=false;
   if(input.checked)checked++;
   if(qualifies)visible++;
  });
  candidates.forEach(row=>{const input=row.querySelector('input');input.disabled=!input.checked&&checked>=5;});
  save.disabled=dirty||checked>5;
  count.textContent=visible+' qualifying drivers · '+checked+'/5 selected'+(dirty?' · Save threshold before selecting drivers':'');
  renderOrder();
 }
 threshold.addEventListener('input',update);
 selectionForm.addEventListener('change',update);
 selectionForm.addEventListener('submit',event=>{if(save.disabled)event.preventDefault();});
 update();
}
open.addEventListener('click',()=>dialog.showModal());
close.addEventListener('click',()=>dialog.close());
dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});
const feedback=dialog.querySelector('#top-five-feedback');
const entries=()=>[...dialog.querySelectorAll('.top-five-entry')].map((row,i)=>({rank:i+1,name:row.querySelector('.top-five-name').textContent.trim(),score:row.querySelector('.top-five-score').textContent.trim()}));
const week=()=>dialog.querySelector('.top-five-week').textContent.trim();
function message(){return '🏆 DULOGIX — TOP 5 DRIVERS\n'+week()+'\n\n'+entries().map(x=>'🏆 '+x.rank+'. '+x.name+' — '+x.score).join('\n')+'\n\nDulogix Driver Performance'}
const logo=new Image();logo.src='/dulogix-logo.png';
function rounded(c,x,y,w,h,r){c.beginPath();c.roundRect(x,y,w,h,r);c.fill()}
function drawWrapped(c,text,x,y,maxWidth,lineHeight){let line='';for(const word of text.split(/\s+/)){const next=line?line+' '+word:word;if(c.measureText(next).width>maxWidth&&line){c.fillText(line,x,y);y+=lineHeight;line=word;}else line=next;}if(line)c.fillText(line,x,y);return y;}
async function imageFile(){
const canvas=document.createElement('canvas');canvas.width=900;canvas.height=1030;
const c=canvas.getContext('2d');
c.fillStyle='#f2f6fb';c.fillRect(0,0,900,1030);
c.fillStyle='#17456c';rounded(c,28,28,844,974,22);
c.fillStyle='white';rounded(c,45,45,810,940,16);
c.fillStyle='#17456c';rounded(c,45,45,810,185,16);
c.fillStyle='white';c.font='bold 35px system-ui';c.fillText('TOP 5 DRIVERS',80,126);
c.font='24px system-ui';c.fillText(week(),80,180);
try{await logo.decode();const ratio=Math.min(210/logo.width,120/logo.height);c.drawImage(logo,620,65,logo.width*ratio,logo.height*ratio)}catch(e){c.font='bold 27px system-ui';c.fillText('DULOGIX',660,110)}
const celebration=dialog.querySelector('.top-five-celebration');
const celebrationTitle=celebration?.querySelector('.top-five-celebration-title')?.textContent.trim()||'';
const celebrationMessage=celebration?.querySelector('p')?.textContent.trim()||'';
c.fillStyle='#fff7df';rounded(c,66,247,768,125,12);
c.textAlign='center';c.fillStyle='#9a6500';c.font='bold 29px system-ui';c.fillText(celebrationTitle,450,285,730);
c.fillStyle='#314c62';c.font='19px system-ui';drawWrapped(c,celebrationMessage,450,315,710,25);c.textAlign='left';
entries().forEach((x,i)=>{
const y=385+i*110;c.fillStyle=i%2===0?'#edf3fa':'#f8fafc';rounded(c,66,y,768,94,12);
c.font='35px system-ui';c.fillStyle='#b17a1c';c.fillText('🏆',86,y+58);
c.font='bold 28px system-ui';c.fillStyle='#18344f';c.fillText(String(x.rank)+'. '+x.name,153,y+40,510);
c.font='22px system-ui';c.fillStyle='#53677d';c.fillText('Rank '+x.rank,154,y+73);
c.textAlign='right';c.font='bold 28px system-ui';c.fillStyle='#087a39';c.fillText(x.score,811,y+57,190);c.textAlign='left';
});
c.fillStyle='#53677d';c.font='22px system-ui';c.fillText('Dulogix Driver Performance',80,972);
const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
if(!blob)throw Error('Unable to generate leaderboard image');
return new File([blob],'Dulogix-top-5-drivers.png',{type:'image/png'});
}
async function copyImage(){
if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard not supported. Use Chrome or Edge on desktop.');
const file=await imageFile();
await navigator.clipboard.write([new ClipboardItem({'image/png':file})]);
return file;
}
dialog.querySelector('#top-five-copy').addEventListener('click',async()=>{
try{await copyImage();feedback.textContent='Leaderboard IMAGE copied. Paste with Ctrl+V into any WhatsApp chat.'}
catch(e){feedback.textContent=e.message}
});
dialog.querySelector('#top-five-whatsapp').addEventListener('click',async()=>{
try{
const file=await copyImage();
feedback.textContent='Leaderboard IMAGE copied. Open any WhatsApp chat and press Ctrl+V to paste, then Send.';
if(/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)&&navigator.canShare?.({files:[file]})){await navigator.share({files:[file],title:'Dulogix Top 5 Drivers'});return}
window.location.href='whatsapp://send';
}catch(e){if(e.name!=='AbortError')feedback.textContent='Image not copied: '+e.message}
});
})();
