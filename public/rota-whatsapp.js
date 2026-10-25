(()=>{
'use strict';
const selected=new Set();
let queue=[],kind='rota',message='';
const panel=document.createElement('section');
panel.className='card wa-panel';
panel.innerHTML='<details id="wa-panel-details"><summary>💬 WhatsApp messages <span id="wa-count" class="wa-count">0 selected</span><span class="wa-hint">Compose &amp; send ▾</span></summary><div class="wa-panel-body"><div class="wa-select-controls"><button id="wa-all" type="button">Select all</button><button id="wa-none" type="button">Clear</button><span class="note">Tick drivers in the rota below to choose recipients.</span></div><textarea id="wa-message" rows="2" maxlength="2000" placeholder="Write a message to drivers…"></textarea><div class="wa-actions"><button id="wa-general-selected" type="button">💬 Message selected</button><button id="wa-general-all" type="button">📋 Copy broadcast</button><button id="wa-next" type="button" hidden>Open next WhatsApp draft</button></div><p id="wa-progress" class="note" aria-live="polite"></p><p class="wa-footnote">WhatsApp drafts are not sent automatically. Press Send in WhatsApp.</p></div></details>';
document.querySelector('#drivers').closest('section').before(panel);
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
 const action=row.nextElementSibling?.lastElementChild;
 if(!action)return;
 const old=action.querySelector('button.send');
 if(old){old.disabled=!currentWeek();old.dataset.waRota=d.key;old.title='Open draft; send manually in WhatsApp';}
 if(!action.querySelector('[data-wa-general]')){
  const b=document.createElement('button');b.textContent='💬 Message';b.dataset.waGeneral=d.key;b.style.marginLeft='5px';action.append(b);
 }
});
}
window.refreshWhatsApp=refresh;
$('wa-all').onclick=()=>{drivers.forEach(d=>selected.add(d.key));refresh()};
$('wa-none').onclick=()=>{selected.clear();refresh()};
document.querySelector('#drivers').addEventListener('change',e=>{const key=e.target.dataset.waSelect;if(!key)return;e.target.checked?selected.add(key):selected.delete(key);refresh()});
function start(type,targets){
 if(!targets.length)return alert('Select at least one driver.');
 if(type==='rota'&&!currentWeek())return alert('Weekly rota messages are only for the coming week.');
 const content=$('wa-message').value.trim();
 if(type==='general'&&!content)return alert('Write a general message first.');
 if(type==='general'&&content.length>2000)return alert('Message is too long.');
 kind=type;message=content;queue=targets.slice();
 panel.querySelector('#wa-panel-details').open=true;
 $('wa-progress').textContent=queue.length+' draft(s) ready. Send each manually.';
 $('wa-next').hidden=false;
 $('wa-next').textContent='Open next WhatsApp draft ('+queue.length+' remaining)';
}
async function next(){
 if(!queue.length){$('wa-next').hidden=true;return;}
 const d=queue[0];
 const popup=kind==='rota'?null:window.open('about:blank','_blank');
 if(kind!=='rota'&&!popup)return alert('Please allow popups for this application.');
 try{
  const url=kind==='rota'?'/api/rota/'+week()+'/whatsapp/'+driverId(d):'/api/whatsapp/general/'+driverId(d);
  const response=await fetch(url,kind==='rota'?{credentials:'same-origin'}:{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="rota-csrf"]').content},body:JSON.stringify({message})});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'Unable to prepare draft');
  if(kind==='rota'){await window.openDulogixRotaCard(data);queue.shift();$('wa-progress').textContent='Rota card ready for '+d.name+'. '+queue.length+' remaining.';$('wa-next').hidden=!queue.length;return;}
  const number=new URL(data.url).pathname.split('/').filter(Boolean).pop();
  const draft=new URL(data.url).searchParams.get('text')||'';
  popup.close();
  window.location.href='whatsapp://send?phone='+encodeURIComponent(number)+'&text='+encodeURIComponent(draft);
  queue.shift();
  $('wa-progress').textContent='Draft opened for '+d.name+'. Not confirmed sent. '+queue.length+' remaining.';
  $('wa-next').hidden=!queue.length;
  $('wa-next').textContent='Open next WhatsApp draft ('+queue.length+' remaining)';
 }catch(error){popup?.close();$('wa-progress').textContent=d.name+': '+error.message+' — skipped only if you choose another recipient.';alert(error.message);}
}
$('wa-next').onclick=next;
$('wa-general-selected').onclick=()=>start('general',drivers.filter(d=>selected.has(d.key)));
$('wa-general-all').textContent='Copy for WhatsApp Broadcast List';
$('wa-general-all').onclick=()=>{const m=$('wa-message').value.trim();if(!m)return alert('Enter a message');navigator.clipboard.writeText(m).then(()=>$('wa-progress').textContent='Copied for your existing broadcast list. Paste and send manually.').catch(()=>{$('wa-message').focus();$('wa-message').select();$('wa-progress').textContent='Clipboard unavailable. Copy the selected text and paste into your broadcast list.';});};
document.querySelector('#drivers').addEventListener('click',e=>{
 const general=e.target.closest('[data-wa-general]');
 const rota=e.target.closest('[data-wa-rota]');
 if(general){const d=drivers.find(x=>x.key===general.dataset.waGeneral);if(d)start('general',[d]);}
 if(rota){const d=drivers.find(x=>x.key===rota.dataset.waRota);if(d){start('rota',[d]);next();}}
});
refresh();
})();