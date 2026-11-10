(()=>{
const cfg=window.scorecardDistributionConfig,dlg=document.getElementById('driver-scorecard-dialog'),buttons=[...document.querySelectorAll('.driver-report-open[data-user-id]')];if(!cfg||!dlg||!buttons.length)return;
const base='/api/driver-performance/'+cfg.week+'/distribution',panel=document.createElement('section');panel.className='card sc-panel';panel.style.cssText='padding:20px;margin:20px 0;background:#f1f8ff;border:1px solid #cbdde9;border-radius:14px';
panel.innerHTML='<details><summary style="cursor:pointer;font-weight:800">WhatsApp Scorecard Distribution <span id="sc-count"></span></summary><div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0"><button id="sc-all" type="button">Select all</button><button id="sc-clear" type="button">Clear</button><label>Format <select id="sc-format"><option value="image">Image (high resolution)</option><option value="pdf">PDF</option></select></label><button id="sc-start" type="button">Share selected scorecards</button><button id="sc-next" type="button" hidden>Prepare next driver</button><button id="sc-confirm" type="button" hidden>Mark as sent &amp; continue</button><button id="sc-skip" type="button" hidden>Skip</button></div><p id="sc-summary"></p><p id="sc-progress" role="status">Select drivers using the checkboxes in the report table.</p><small>Confirm only after sending manually in WhatsApp.</small></details>';
const table=buttons[0].closest('table');if(table)table.parentElement.before(panel);else buttons[0].closest('section')?.before(panel);
const $=id=>panel.querySelector('#'+id),boxes=[...document.querySelectorAll('.driver-row-select')];let saved={},queue=[],opened=null,done=0,total=0,busy=false,format='image';
const signature=async b=>{const data={...b.dataset};delete data.phone;delete data.userId;const bytes=new TextEncoder().encode(JSON.stringify({data,scorecardRules:window.driverScorecardConfig||null,version:'mobile-scorecard-v1'}));const hash=await crypto.subtle.digest('SHA-256',bytes);return [...new Uint8Array(hash)].map(x=>x.toString(16).padStart(2,'0')).join('')};
async function refresh(){let counts={sent:0,'not-sent':0,resend:0};$('sc-count').textContent='('+boxes.filter(x=>x.checked).length+' selected)';for(const b of buttons){const d=saved[b.dataset.userId],state=!d?'not-sent':d.signature===await signature(b)?'sent':'resend';counts[state]++;let badge=b.parentElement.querySelector('.sc-sent-status');if(!badge){badge=document.createElement('small');badge.className='sc-sent-status';badge.style.cssText='display:block;text-align:center;font-size:13px;font-weight:800;margin:8px 0;white-space:nowrap;letter-spacing:-.2px';b.after(badge)}badge.replaceChildren();const dot=document.createElement('span');dot.className='sc-status-dot';dot.setAttribute('aria-hidden','true');dot.style.cssText='display:inline-block;width:12px;height:12px;border-radius:50%;background:currentColor;vertical-align:middle;margin-right:5px';badge.append(dot,document.createTextNode(state==='sent'?'Sent':state==='resend'?'Resend':'Not sent'));badge.style.color=state==='sent'?'#087a39':state==='resend'?'#b56b00':'#b42335';badge.title=d?.at?'Confirmed by '+d.by+' at '+d.at:'Not confirmed'}$('sc-summary').textContent=counts.sent+' sent | '+counts['not-sent']+' not sent | '+counts.resend+' resend required'}
async function load(){try{const r=await fetch(base,{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('Unable to load status');saved=(await r.json()).drivers||{};await refresh()}catch(e){$('sc-progress').textContent=e.message}}
boxes.forEach(b=>b.addEventListener('change',refresh));$('sc-all').onclick=()=>{boxes.forEach(b=>b.checked=true);refresh()};$('sc-clear').onclick=()=>{boxes.forEach(b=>b.checked=false);refresh()};
function controls(){$('sc-next').hidden=!queue.length||!!opened;$('sc-confirm').hidden=!opened;$('sc-skip').hidden=!opened}
async function prepare(){if(busy||opened||!queue.length)return;busy=true;const b=queue[0];$('sc-progress').textContent='Preparing '+b.dataset.driver+' ('+(done+1)+' of '+total+')...';try{if(dlg.open)dlg.close();window.driverScorecardOpenReport(b);await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));if(format==='image'){
 const blob=await window.driverScorecardMobileImage();
 dlg.close();
 await window.openDulogixPreparedImage({
  blob,clipboardBlob:window.driverScorecardClipboardPng,filename:'Dulogix-scorecard-'+b.dataset.id+'.jpg',
  title:'Share scorecard image - '+b.dataset.driver,
  phone:b.dataset.phone,
  description:'Dulogix weekly scorecard for '+b.dataset.driver,
  asDocument:true
 });
}else{
 await window.driverScorecardDownloadPdf(b);
 dlg.close();
}
opened=b;$('sc-progress').textContent='Prepared for '+b.dataset.driver+'. After sending the scorecard in WhatsApp, click Mark as sent & continue.'}catch(e){if(dlg.open)dlg.close();$('sc-progress').textContent=b.dataset.driver+': '+e.message+'. Retry or skip.'}finally{busy=false;controls()}}
$('sc-start').onclick=()=>{if(queue.length&&!confirm('Replace current queue?'))return;format=$('sc-format').value;queue=buttons.filter(b=>b.closest('tr')?.querySelector('.driver-row-select:checked'));if(!queue.length){$('sc-progress').textContent='Select a driver first.';return}done=0;total=queue.length;opened=null;controls();prepare()};
$('sc-next').onclick=prepare;$('sc-skip').onclick=()=>{if(!opened)return;queue.shift();done++;opened=null;controls();$('sc-progress').textContent='Skipped. Prepare next driver.'};
$('sc-confirm').onclick=async()=>{if(!opened||busy)return;const b=opened;busy=true;$('sc-confirm').disabled=true;try{const r=await fetch(base+'/'+b.dataset.userId,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':cfg.csrf},body:JSON.stringify({signature:await signature(b)})});const d=await r.json();if(!r.ok)throw Error(d.error||'Save failed');queue.shift();done++;opened=null;await load();$('sc-progress').textContent='Confirmed sent for '+b.dataset.driver+'. '+done+' of '+total+' processed.'+(queue.length?' Prepare next driver.':' Complete.')}catch(e){$('sc-progress').textContent='Confirmation failed: '+e.message}finally{busy=false;$('sc-confirm').disabled=false;controls()}};
// Individual report: allow delivery confirmation without starting a batch.
const individual=document.createElement('button');
individual.type='button';individual.className='sc-individual-confirm';
individual.textContent='Confirm sent in WhatsApp';
individual.style.cssText='background:#18577f;color:white;border:0;border-radius:8px;padding:10px 15px;font-weight:750;cursor:pointer';
individual.hidden=true;
dlg.querySelector('.scorecard-actions')?.prepend(individual);
let individualDriver=null;
buttons.forEach(b=>b.addEventListener('click',()=>{individualDriver=b;individual.hidden=false;individual.textContent='Confirm sent in WhatsApp'}));
individual.onclick=async()=>{
 if(!individualDriver||busy)return;
 busy=true;individual.disabled=true;
 try{
  const b=individualDriver;
  const r=await fetch(base+'/'+b.dataset.userId,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':cfg.csrf},body:JSON.stringify({signature:await signature(b)})});
  const data=await r.json();if(!r.ok)throw Error(data.error||'Save failed');
  await load();individual.textContent='Sent confirmed';
 }catch(e){alert('Unable to confirm delivery: '+e.message)}
 finally{busy=false;individual.disabled=false}
};
const styles=document.createElement('style');
styles.textContent='.sc-panel summary{font-size:19px;font-weight:800;color:#143e61}.sc-panel details>div{gap:10px!important}.sc-panel button,.sc-panel select{border-radius:8px;padding:9px 12px;border:1px solid #b7cce0}.sc-panel #sc-start,.sc-panel #sc-confirm{background:#18577f;color:white;font-weight:750;border-color:#18577f}.sc-panel #sc-summary{font-weight:750;font-size:15px;margin:18px 0}.sc-panel #sc-progress{background:white;border:1px solid #d3e2ef;border-radius:9px;padding:12px}.sc-sent-status{letter-spacing:.1px}';document.head.append(styles);
load();
})();