(()=>{
'use strict';
const selected=new Set();
let queue=[],kind='rota',message='',opened=null,completed=0,total=0,distribution={};
const panel=document.createElement('section');
panel.className='card wa-panel';
panel.innerHTML='<details id="wa-panel-details"><summary>💬 WhatsApp messages <span id="wa-count" class="wa-count">0 selected</span><span class="wa-hint">Compose &amp; send ▾</span></summary><div class="wa-panel-body"><div class="wa-select-controls"><button id="wa-all" type="button">Select all</button><button id="wa-none" type="button">Clear</button><span class="note">Tick drivers in the rota below to choose recipients.</span></div><textarea id="wa-message" rows="2" maxlength="2000" placeholder="Write a message to drivers…"></textarea><div class="wa-actions"><button id="wa-general-selected" type="button">💬 Message selected</button><button id="wa-rota-selected" type="button">📲 Send selected rota cards</button><button id="wa-general-all" type="button">📋 Copy broadcast</button><button id="wa-next" type="button" hidden>Open WhatsApp for next driver</button><button id="wa-confirm" type="button" hidden>Mark as sent &amp; continue</button></div><p id="wa-progress" class="note" aria-live="polite"></p><p class="wa-footnote">WhatsApp drafts are not sent automatically. Press Send in WhatsApp.</p></div></details>';
document.querySelector('#drivers').closest('section').before(panel);
const summary=document.createElement('p');summary.id='wa-distribution-summary';summary.className='note';document.querySelector('#drivers').closest('section').querySelector('.driver-heading').after(summary);
const composer=document.createElement('div');
composer.className='wa-composer';
composer.innerHTML='<button type="button" id="wa-emoji-toggle" aria-label="Choose emoji" title="Choose emoji">😊</button><span class="wa-composer-label">Message composer · add emojis, then choose recipients</span><div id="wa-emoji-picker" class="wa-emoji-picker" hidden></div>';
const messageBox=panel.querySelector('#wa-message');
messageBox.before(composer);
const emojiPicker=composer.querySelector('#wa-emoji-picker');
const emojis=['😀','😃','😊','😍','🥰','😎','😂','🤣','😉','🙂','🙌','👏','👍','👎','🙏','💪','👋','🤝','❤️','💙','💚','🔥','🎉','⭐','✨','✅','❌','⚠️','📢','📅','🚚','🚗','🕒','📍','☀️','🌧️','☕','💬'];
for(const emoji of emojis){const button=document.createElement('button');button.type='button';button.textContent=emoji;button.setAttribute('aria-label','Insert '+emoji);button.addEventListener('click',()=>{const start=messageBox.selectionStart,end=messageBox.selectionEnd;messageBox.setRangeText(emoji,start,end,'end');messageBox.focus();emojiPicker.hidden=true;});emojiPicker.append(button);}
composer.querySelector('#wa-emoji-toggle').onclick=()=>{emojiPicker.hidden=!emojiPicker.hidden};
const composerStyle=document.createElement('style');
composerStyle.textContent='.wa-composer{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:12px 0 4px;padding:9px 12px;border:1px solid #cbded6;border-radius:14px;background:#eaf7f0}.wa-composer #wa-emoji-toggle{font-size:22px;border:0;background:white;border-radius:50%;width:43px;height:43px;cursor:pointer}.wa-composer-label{font-size:13px;color:#456457}.wa-emoji-picker{width:100%;display:grid;grid-template-columns:repeat(auto-fill,minmax(40px,1fr));gap:4px;padding:8px;background:white;border-radius:10px;box-shadow:0 2px 10px #1232}.wa-emoji-picker[hidden]{display:none}.wa-emoji-picker button{border:0;background:transparent;font-size:23px;padding:5px;cursor:pointer}.wa-emoji-picker button:hover{background:#e1f6e8}#wa-message{border:1px solid #a8cfb8;border-radius:14px;padding:14px;font:15px system-ui;line-height:1.5;resize:vertical;background:#fff}';
document.head.append(composerStyle);
const $=id=>document.getElementById(id);
const driverId=d=>Number(d.key.slice(7));
const week=()=>monday().toISOString().slice(0,10);
const currentWeek=()=>offset===0;
function refresh(){
const visible=new Set(drivers.map(d=>d.key));
for(const key of selected)if(!visible.has(key))selected.delete(key);
$('wa-count').textContent=selected.size+' selected';
const table=$('drivers');const heading=table.querySelector('thead th:first-child');if(heading&&!heading.querySelector('#wa-select-all')){const all=document.createElement('input');all.type='checkbox';all.id='wa-select-all';all.setAttribute('aria-label','Select all drivers');all.title='Select all drivers';all.style.marginRight='9px';heading.prepend(all);}const all=$('wa-select-all');if(all){all.checked=drivers.length>0&&drivers.every(d=>selected.has(d.key));all.indeterminate=selected.size>0&&!all.checked;}
const counts={sent:0,'not-sent':0,resend:0};drivers.forEach(d=>{counts[distribution[d.key]?.status||'not-sent']++;});summary.textContent='Rota distribution: 🟢 '+counts.sent+' sent | 🔴 '+counts['not-sent']+' not sent | 🟠 '+counts.resend+' resend required';
const rows=document.querySelectorAll('#drivers tbody tr.avail');
rows.forEach(row=>{
 const name=row.querySelector('td:first-child b')?.textContent;
 const d=drivers.find(x=>x.name===name);
 if(!d)return;
 const td=row.querySelector('td:first-child');
 if(!td.querySelector('[data-wa-select]')){
  const box=document.createElement('input');box.type='checkbox';box.dataset.waSelect=d.key;box.setAttribute('aria-label','Select '+d.name);box.style.marginRight='6px';td.prepend(box);
 }
 td.querySelector('[data-wa-select]').checked=selected.has(d.key);
 const allocationAction=row.nextElementSibling?.lastElementChild;
 const statusAction=row.nextElementSibling?.nextElementSibling?.lastElementChild;
 if(!allocationAction||!statusAction)return;
 if(statusAction.textContent.trim()==='—')statusAction.textContent='';
 const old=allocationAction.querySelector('button.send');
 if(old){old.disabled=!currentWeek();old.dataset.waRota=d.key;old.title='Open draft; send manually in WhatsApp';const info=distribution[d.key]||{status:'not-sent'};let badge=allocationAction.querySelector('[data-wa-status]');if(!badge){badge=document.createElement('small');badge.dataset.waStatus='';badge.style.display='block';allocationAction.append(badge);}badge.textContent=info.status==='sent'?'🟢 Sent':info.status==='resend'?'🟠 Resend required':'🔴 Not sent';badge.title=info.at?'Confirmed by '+(info.by||'manager')+' on '+new Date(info.at).toLocaleString('en-GB'):'No sending confirmation';}
 const messageButton=allocationAction.querySelector('[data-wa-general]')||row.lastElementChild.querySelector('[data-wa-general]')||statusAction.querySelector('[data-wa-general]');
 if(messageButton){statusAction.append(messageButton);messageButton.style.marginLeft='0';messageButton.style.whiteSpace='nowrap';messageButton.style.display='inline-flex';messageButton.style.alignItems='center';messageButton.style.justifyContent='center';messageButton.style.gap='4px';messageButton.style.padding='5px 9px';messageButton.style.width='auto';}
 else {
  const b=document.createElement('button');b.textContent='💬 Message';b.dataset.waGeneral=d.key;b.style.whiteSpace='nowrap';b.style.display='inline-flex';b.style.alignItems='center';b.style.justifyContent='center';b.style.gap='4px';b.style.padding='5px 9px';b.style.width='auto';statusAction.append(b);
 }
});
}
window.refreshWhatsApp=()=>{refresh();loadDistribution();};
async function loadDistribution(){try{const response=await fetch('/api/rota/'+week()+'/distribution',{credentials:'same-origin',cache:'no-store'});if(!response.ok)return;const data=await response.json();distribution=data.drivers||{};refresh();}catch(e){}}
async function confirmRota(d){const response=await fetch('/api/rota/'+week()+'/distribution/'+driverId(d),{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':document.querySelector('meta[name="rota-csrf"]').content}});const data=await response.json();if(!response.ok)throw Error(data.error||'Unable to save confirmation');await loadDistribution();}
$('wa-all').onclick=()=>{drivers.forEach(d=>selected.add(d.key));refresh()};
$('wa-none').onclick=()=>{selected.clear();refresh()};
document.querySelector('#drivers').addEventListener('change',e=>{if(e.target.id!=='wa-select-all')return;if(e.target.checked)drivers.forEach(d=>selected.add(d.key));else selected.clear();refresh();});
document.querySelector('#drivers').addEventListener('change',e=>{const key=e.target.dataset.waSelect;if(!key)return;e.target.checked?selected.add(key):selected.delete(key);refresh()});
function start(type,targets){
 if(!targets.length)return alert('Select at least one driver.');
 if(type==='rota'&&!currentWeek())return alert('Weekly rota messages are only for the coming week.');
 const content=$('wa-message').value.trim();
 if(type==='general'&&!content)return alert('Write a general message first.');
 if(type==='general'&&content.length>2000)return alert('Message is too long.');
 kind=type;message=content;queue=targets.slice();opened=null;completed=0;total=queue.length;$('wa-confirm').hidden=true;
 panel.querySelector('#wa-panel-details').open=true;
 $('wa-progress').textContent=queue.length+' draft(s) ready. Send each manually.';
 $('wa-next').hidden=false;
 $('wa-next').textContent='Open WhatsApp for next driver ('+queue.length+' remaining)';
}
async function next(){
 if(!queue.length){$('wa-next').hidden=true;return;}
 if(opened)return alert('Confirm the previous draft, or choose to skip it before opening another.');
 const d=queue[0];
 const popup=kind==='rota'?null:window.open('about:blank','_blank');
 if(kind!=='rota'&&!popup)return alert('Please allow popups for this application.');
 try{
  const url=kind==='rota'?'/api/rota/'+week()+'/whatsapp/'+driverId(d):'/api/whatsapp/general/'+driverId(d);
  const response=await fetch(url,kind==='rota'?{credentials:'same-origin'}:{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="rota-csrf"]').content},body:JSON.stringify({message})});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'Unable to prepare draft');
  if(kind==='rota'){await window.openDulogixRotaCard(data);opened=d;$('wa-progress').textContent='Rota card prepared for '+d.name+' ('+(completed+1)+' of '+total+'). Share manually, then confirm.';$('wa-next').hidden=true;$('wa-confirm').hidden=false;return;}
  const number=new URL(data.url).pathname.split('/').filter(Boolean).pop();
  const draft=new URL(data.url).searchParams.get('text')||'';
  popup.close();
  window.location.href='whatsapp://send?phone='+encodeURIComponent(number)+'&text='+encodeURIComponent(draft);
  opened=d;
  $('wa-progress').textContent='Draft opened for '+d.name+' ('+(completed+1)+' of '+total+'). Not confirmed sent. Confirm after sending in WhatsApp.';
  $('wa-next').hidden=true;$('wa-confirm').hidden=false;
  $('wa-next').textContent='Open WhatsApp for next driver ('+queue.length+' remaining)';
 }catch(error){popup?.close();$('wa-progress').textContent=d.name+': '+error.message+' — skipped only if you choose another recipient.';alert(error.message);}
}
$('wa-next').onclick=next;
$('wa-confirm').onclick=async()=>{if(!opened)return;try{if(kind==='rota')await confirmRota(opened);}catch(e){alert(e.message);return;}const name=opened.name;queue.shift();completed++;opened=null;$('wa-confirm').hidden=true;$('wa-next').hidden=!queue.length;$('wa-next').textContent='Open WhatsApp for next driver ('+queue.length+' remaining)';$('wa-progress').textContent='Marked as sent by manager for '+name+'. '+completed+' of '+total+' processed.'+(queue.length?' Open the next draft.':' All recipients processed.');};
$('wa-general-selected').onclick=()=>start('general',drivers.filter(d=>selected.has(d.key)));
$('wa-rota-selected').onclick=()=>{start('rota',drivers.filter(d=>selected.has(d.key)));if(queue.length)next();};
$('wa-general-all').textContent='Copy for WhatsApp Broadcast List';
$('wa-general-all').onclick=()=>{const m=$('wa-message').value.trim();if(!m)return alert('Enter a message');navigator.clipboard.writeText(m).then(()=>$('wa-progress').textContent='Copied for your existing broadcast list. Paste and send manually.').catch(()=>{$('wa-message').focus();$('wa-message').select();$('wa-progress').textContent='Clipboard unavailable. Copy the selected text and paste into your broadcast list.';});};
document.querySelector('#drivers').addEventListener('click',e=>{
 const general=e.target.closest('[data-wa-general]');
 const rota=e.target.closest('[data-wa-rota]');
 if(general){const d=drivers.find(x=>x.key===general.dataset.waGeneral);if(d)start('general',[d]);}
 if(rota){const d=drivers.find(x=>x.key===rota.dataset.waRota);if(d){start('rota',[d]);next();}}
});
refresh();loadDistribution();
})();