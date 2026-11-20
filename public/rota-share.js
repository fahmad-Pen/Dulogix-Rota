(()=>{
'use strict';
const days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const allocationLabels={FD:'Full Day',HD:'Half Day',OFF:'Off',OFFICE:'Office',STANDBY:'Standby'};
function isoWeek(date){const d=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth(),date.getUTCDate()));d.setUTCDate(d.getUTCDate()+4-(d.getUTCDay()||7));const year=d.getUTCFullYear();const start=new Date(Date.UTC(year,0,1));return {year,week:Math.ceil((((d-start)/86400000)+1)/7)};}
const overlay=document.createElement('div');
overlay.id='rota-share-overlay';

overlay.innerHTML='<div class="rota-generated-0"><h2 class="rota-generated-1">Share driver rota</h2><img id="rota-share-preview" alt="Weekly rota card preview" class="rota-generated-2"><p id="rota-share-status" aria-live="polite"></p><div class="rota-generated-3"><button id="rota-copy-image" type="button" class="rota-generated-4">📋 Copy image + open WhatsApp</button><button id="rota-mobile-direct" type="button" class="rota-generated-5">📋 Copy + open driver</button><button id="rota-share-now" type="button" class="rota-generated-6">📲 Share on phone</button><button id="rota-share-close" type="button">Close</button></div></div>';
document.body.append(overlay);
const preview=overlay.querySelector('#rota-share-preview'),status=overlay.querySelector('#rota-share-status'),shareButton=overlay.querySelector('#rota-share-now');
const previewFrame=document.createElement('div');previewFrame.className='rota-share-preview-frame';

preview.parentElement.insertBefore(previewFrame,preview);
previewFrame.append(preview);
const previewControls=document.createElement('div');previewControls.className='rota-share-preview-controls';

previewControls.innerHTML='<button type="button" id="sc-preview-fit">Fit preview</button><button type="button" id="sc-preview-actual">View at 100% (scroll)</button><span id="sc-preview-size"></span>';
previewFrame.after(previewControls);
const previewSize=previewControls.querySelector('#sc-preview-size');
previewControls.querySelector('#sc-preview-fit').onclick=()=>{preview.classList.remove('rota-preview-actual')};
previewControls.querySelector('#sc-preview-actual').onclick=()=>{preview.classList.add('rota-preview-actual')};
preview.addEventListener('load',()=>{previewSize.textContent=preview.naturalWidth+' ? '+preview.naturalHeight+' pixels'});

let file=null,clipboardImage=null,shareText='',target='';
let design={heading:'WEEKLY DRIVER ROTA',footer:'Please contact operations about any allocation changes.',header_color:'#142d4e',accent_color:'#146343',row_color:'#edf3fa'};
const copyButton=overlay.querySelector('#rota-copy-image'),mobileDirect=overlay.querySelector('#rota-mobile-direct');
const scorecardCopy=document.createElement('button');
scorecardCopy.type='button';
scorecardCopy.id='scorecard-copy-only';
scorecardCopy.textContent='Copy full-resolution image';

const scorecardOpen=document.createElement('button');
scorecardOpen.type='button';
scorecardOpen.id='scorecard-whatsapp-only';
scorecardOpen.textContent='Open driver WhatsApp';

let preparedOnCopied=null;
const scorecardActions=document.createElement('div');scorecardActions.className='rota-share-scorecard-actions';

scorecardActions.append(scorecardCopy,scorecardOpen);
overlay.querySelector('#rota-share-close').parentElement.after(scorecardActions);
scorecardCopy.onclick=async()=>{
 if(!file)return;
 try{
  await window.DulogixImageShare.copyPng(clipboardImage||file);
  status.textContent='Image copied. In the driver chat press Ctrl+V, select HD in the image preview, then Send.';
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  // Copy has succeeded: close the preview and restore the report confirmation screen.
  if(typeof preparedOnCopied==='function')preparedOnCopied();
  overlay.querySelector('#rota-share-close').click();
  window.DulogixImageShare.openWhatsApp(number);
 }catch(e){status.textContent='Unable to copy image: '+e.message}
};
scorecardOpen.onclick=()=>{
 try{const number=new URL(target).pathname.split('/').filter(Boolean).pop();window.DulogixImageShare.openWhatsApp(number)}
 catch(e){status.textContent='Unable to open WhatsApp: '+e.message}
};
const documentActions=document.createElement('div');
documentActions.id='scorecard-document-actions';

documentActions.innerHTML='<button type="button" id="scorecard-save-document" class="rota-generated-7">Download original image</button><button type="button" id="scorecard-open-chat" class="rota-generated-8">Open driver WhatsApp</button>';
overlay.querySelector('#rota-share-close').parentElement.after(documentActions);
documentActions.querySelector('#scorecard-save-document').onclick=()=>{
 if(!file)return;
 window.DulogixImageShare.downloadPng(file,file.name);
 status.textContent='High-quality JPG downloaded. Click Open driver WhatsApp, then Attach > Document and select the JPG to retain its quality.';
};
documentActions.querySelector('#scorecard-open-chat').onclick=()=>{
 if(!file)return;
 try{
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  window.DulogixImageShare.openWhatsApp(number);
 }catch(e){status.textContent='Unable to open WhatsApp: '+e.message;}
};
// One scorecard action row: download, open chat, optional clipboard copy.
const downloadOriginal=documentActions.querySelector('#scorecard-save-document');
downloadOriginal.textContent='Download high-quality JPG';
scorecardActions.append(downloadOriginal);
scorecardActions.classList.add('rota-share-scorecard-column');
scorecardOpen.remove();


scorecardCopy.textContent='Copy image + open driver WhatsApp';


documentActions.remove();


const isMobile=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
copyButton.hidden=isMobile;shareButton.hidden=!isMobile;mobileDirect.hidden=!/Android/i.test(navigator.userAgent);
// Keep close and sharing controls visible above the preview in both rota and report modes.
const dialog=overlay.querySelector('.rota-generated-0');
const heading=overlay.querySelector('.rota-generated-1');
const closeButton=overlay.querySelector('#rota-share-close');
const header=document.createElement('div');
header.className='rota-share-header';
heading.before(header);
header.append(heading,closeButton);
closeButton.textContent='×';
closeButton.setAttribute('aria-label','Close share preview');
closeButton.title='Close';
const actionRow=overlay.querySelector('.rota-generated-3');
previewFrame.before(actionRow,scorecardActions);
closeButton.onclick=()=>{overlay.classList.remove('rota-share-visible')};
// Only clicks on the dark backdrop close the dialog; clicks inside remain interactive.
overlay.addEventListener('click',event=>{
 if(event.target===overlay)closeButton.click();
});

function round(ctx,x,y,w,h,r){ctx.beginPath();ctx.roundRect(x,y,w,h,r);ctx.fill()}
function createCard(data){
 const canvas=document.createElement('canvas');canvas.width=900;canvas.height=1170;
 const c=canvas.getContext('2d');c.fillStyle='#f2f6fb';c.fillRect(0,0,900,1170);
 c.fillStyle=design.header_color;round(c,30,30,840,1110,24);
 c.fillStyle='white';round(c,48,48,804,1074,17);
 c.fillStyle=design.header_color;round(c,48,48,804,200,17);
 c.fillStyle='white';c.font='bold 44px system-ui';c.fillText('DULOGIX',82,120);
 c.font='23px system-ui';c.fillText(design.heading.slice(0,50),82,169,740);
 c.font='bold 35px system-ui';c.fillStyle='#142d4e';c.fillText(String(data.driverName).slice(0,36),82,310);
 const start=new Date(data.weekStart+'T12:00:00Z');
 c.font='25px system-ui';c.fillStyle='#52677e';const wk=isoWeek(start);c.fillText('Week '+wk.week+' · commencing '+start.toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}),82,358);
 for(let i=0;i<7;i++){
  const y=395+i*91;const code=String(data.allocations[i]||'').trim();const value=code?(allocationLabels[code.toUpperCase()]?code+' ('+allocationLabels[code.toUpperCase()]+')':code):'Not assigned';
  c.fillStyle=i%2===0?design.row_color:'#f8fafc';round(c,70,y,760,78,10);
  const dayDate=new Date(start.getTime()+i*86400000);
  const dayLabel=days[i]+' '+dayDate.toLocaleDateString('en-GB',{day:'2-digit',month:'short',timeZone:'UTC'});
  c.fillStyle='#1c3654';c.font='bold 27px system-ui';c.fillText(dayLabel,94,y+48);
  c.textAlign='right';c.font='bold 26px system-ui';c.fillStyle=value==='Not assigned'?'#9a5b17':design.accent_color;c.fillText(value,802,y+48,475);c.textAlign='left';
 }
 c.fillStyle='#53677d';c.font='21px system-ui';c.fillText(design.footer.slice(0,140),80,1085,740);
 return canvas;
}
async function open(data){
 scorecardActions.classList.remove('rota-share-actions-visible');
 shareButton.hidden=!isMobile;
 copyButton.hidden=isMobile;
 mobileDirect.hidden=!/Android/i.test(navigator.userAgent);
 file=null;clipboardImage=null;status.textContent='Preparing rota card…';shareButton.disabled=true;copyButton.disabled=true;mobileDirect.disabled=true;overlay.classList.add('rota-share-visible');
 try{const response=await fetch('/api/rota-card/design',{credentials:'same-origin',cache:'no-store'});if(response.ok)design=await response.json();}catch(e){}
 const canvas=createCard(data);
 const logo=new Image();logo.src='/dulogix-logo.png';
 try{await logo.decode();const ctx=canvas.getContext('2d');ctx.fillStyle=design.header_color;ctx.fillRect(48,48,804,200);const ratio=Math.min(280/logo.width,155/logo.height);ctx.drawImage(logo,75,62,logo.width*ratio,logo.height*ratio);ctx.fillStyle='white';ctx.font='bold 26px system-ui';ctx.fillText(design.heading.slice(0,50),395,142,425)}catch(e){}
 preview.src=canvas.toDataURL('image/png');
 const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
 if(!blob){status.textContent='Unable to prepare image.';return}
 file=new File([blob],'Dulogix-rota-'+data.weekStart+'.png',{type:'image/png'});
 target=data.url;shareText='Dulogix weekly rota for '+data.driverName+' · week commencing '+data.weekStart;
 shareButton.disabled=false;copyButton.disabled=false;mobileDirect.disabled=false;status.textContent=/Android/i.test(navigator.userAgent)?'Try Copy + open driver to open the correct WhatsApp chat, then paste the image. If paste is unavailable, use Share on phone instead.':isMobile?'Tap Share on phone and choose WhatsApp.':'Click Copy image + open WhatsApp. In the driver chat press Ctrl+V, check the image, then Send.';
}
copyButton.onclick=async()=>{
 if(!file)return;
 try{
  if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard not supported in this browser. Try Chrome or Edge on desktop.');
  await window.DulogixImageShare.copyPng(clipboardImage||file);
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  window.DulogixImageShare.openWhatsApp(number);
  status.textContent='Image copied. If your browser asks to open WhatsApp, allow it. In the desktop app press Ctrl+V, check the driver and image, then Send. If the app does not open, launch WhatsApp yourself and find the driver.';
 }catch(e){status.textContent='Unable to copy image: '+e.message}
};
mobileDirect.onclick=async()=>{
 if(!file)return;
 try{
  if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard unavailable on this Android browser. Use Share on phone.');
  await window.DulogixImageShare.copyPng(clipboardImage||file);
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  status.textContent='Image copied. Open the driver chat in WhatsApp, long-press the message box and tap Paste. If WhatsApp cannot paste the image, return here and use Share on phone.';
  window.DulogixImageShare.openWhatsApp(number);
 }catch(e){status.textContent='Copy + open driver not available: '+e.message+' Use Share on phone to attach the image.';}
};
shareButton.onclick=async()=>{
 if(!file)return;
 if(!navigator.share||!navigator.canShare?.({files:[file]})){status.textContent='This browser does not support direct image sharing. Please open this page on a phone with a supported browser.';return}
 try{await navigator.share({files:[file],title:'Dulogix weekly rota',text:shareText});status.textContent='Share menu completed. Confirm delivery in WhatsApp.'}
 catch(e){if(e.name!=='AbortError')status.textContent='Could not share: '+e.message}
};
window.openDulogixRotaCard=open;
window.openDulogixPreparedImage=async(data)=>{
 preparedOnCopied=typeof data.onCopied==='function'?data.onCopied:null;
 const documentMode=!!data.asDocument;
 const number=window.DulogixImageShare.phone(data.phone);
 if(!number)throw Error('Driver phone number is missing or invalid');
 file=new File([data.blob],data.filename||'Dulogix-report.jpg',{type:data.blob.type||'image/jpeg'});
 clipboardImage=data.clipboardBlob?.type==='image/png'?data.clipboardBlob:null;
 target='https://wa.me/'+number;
 shareText=data.description||data.title||'Dulogix report';
 overlay.querySelector('h2').textContent=data.title||'Share image';
 const old=preview.dataset.blobUrl;if(old)URL.revokeObjectURL(old);
 const url=URL.createObjectURL(file);preview.dataset.blobUrl=url;preview.src=url;preview.classList.remove('rota-preview-actual');
 overlay.classList.add('rota-share-visible');
 overlay.classList.toggle('scorecard-share-mode',documentMode);
 scorecardActions.classList.toggle('rota-share-actions-visible',documentMode);
 copyButton.textContent='?? Copy image + open WhatsApp';
 shareButton.disabled=false;copyButton.disabled=false;mobileDirect.disabled=false;
 shareButton.hidden=!isMobile;
 copyButton.hidden=documentMode||isMobile;
 mobileDirect.hidden=!/Android/i.test(navigator.userAgent);
 status.textContent=documentMode?'Click Copy image + open driver WhatsApp. Paste with Ctrl+V, select HD in the image preview, then Send.':'Image ready. Copy and open WhatsApp to paste into the driver chat.';
};

})();