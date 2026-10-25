(()=>{
'use strict';
const days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const allocationLabels={FD:'Full Day',HD:'Half Day',OFF:'Off',OFFICE:'Office',STANDBY:'Standby'};
function isoWeek(date){const d=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth(),date.getUTCDate()));d.setUTCDate(d.getUTCDate()+4-(d.getUTCDay()||7));const year=d.getUTCFullYear();const start=new Date(Date.UTC(year,0,1));return {year,week:Math.ceil((((d-start)/86400000)+1)/7)};}
const overlay=document.createElement('div');
overlay.id='rota-share-overlay';
overlay.style.cssText='position:fixed;inset:0;background:#10243ac9;z-index:1000;display:none;align-items:center;justify-content:center;padding:14px;overflow:auto';
overlay.innerHTML='<div style="background:white;border-radius:12px;padding:18px;width:min(540px,100%);max-height:95vh;overflow:auto"><h2 style="margin:0 0 12px">Share driver rota</h2><img id="rota-share-preview" alt="Weekly rota card preview" style="width:100%;border:1px solid #dce5ef;border-radius:8px"><p id="rota-share-status" aria-live="polite"></p><div style="display:flex;gap:8px;flex-wrap:wrap"><button id="rota-copy-image" type="button" style="background:#137b53;color:white;font-size:15px;padding:12px;flex:1">📋 Copy image + open WhatsApp</button><button id="rota-mobile-direct" type="button" style="background:#137b53;color:white;font-size:15px;padding:12px;flex:1">📋 Copy + open driver</button><button id="rota-share-now" type="button" style="font-size:15px;padding:12px;flex:1">📲 Share on phone</button><button id="rota-share-close" type="button">Close</button></div></div>';
document.body.append(overlay);
const preview=overlay.querySelector('#rota-share-preview'),status=overlay.querySelector('#rota-share-status'),shareButton=overlay.querySelector('#rota-share-now');
let file=null,shareText='',target='';
let design={heading:'WEEKLY DRIVER ROTA',footer:'Please contact operations about any allocation changes.',header_color:'#142d4e',accent_color:'#146343',row_color:'#edf3fa'};
const copyButton=overlay.querySelector('#rota-copy-image'),mobileDirect=overlay.querySelector('#rota-mobile-direct');
const isMobile=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
copyButton.hidden=isMobile;shareButton.hidden=!isMobile;mobileDirect.hidden=!/Android/i.test(navigator.userAgent);
overlay.querySelector('#rota-share-close').onclick=()=>{overlay.style.display='none'};
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
  c.fillStyle='#1c3654';c.font='bold 27px system-ui';c.fillText(days[i],94,y+48);
  c.textAlign='right';c.font='bold 26px system-ui';c.fillStyle=value==='Not assigned'?'#9a5b17':design.accent_color;c.fillText(value,802,y+48,475);c.textAlign='left';
 }
 c.fillStyle='#53677d';c.font='21px system-ui';c.fillText(design.footer.slice(0,140),80,1085,740);
 return canvas;
}
async function open(data){
 file=null;status.textContent='Preparing rota card…';shareButton.disabled=true;copyButton.disabled=true;mobileDirect.disabled=true;overlay.style.display='flex';
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
 const popup=window.open('about:blank','_blank');
 if(!popup){status.textContent='Allow popups for Dulogix, then try again.';return}
 try{
  if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard not supported in this browser. Try Chrome or Edge on desktop.');
  await navigator.clipboard.write([new ClipboardItem({'image/png':file})]);
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  popup.close();
  window.location.href='whatsapp://send?phone='+encodeURIComponent(number);
  status.textContent='Image copied. If your browser asks to open WhatsApp, allow it. In the desktop app press Ctrl+V, check the driver and image, then Send. If the app does not open, launch WhatsApp yourself and find the driver.';
 }catch(e){popup.close();status.textContent='Unable to copy image: '+e.message}
};
mobileDirect.onclick=async()=>{
 if(!file)return;
 try{
  if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard unavailable on this Android browser. Use Share on phone.');
  await navigator.clipboard.write([new ClipboardItem({'image/png':file})]);
  const number=new URL(target).pathname.split('/').filter(Boolean).pop();
  status.textContent='Image copied. Open the driver chat in WhatsApp, long-press the message box and tap Paste. If WhatsApp cannot paste the image, return here and use Share on phone.';
  window.location.href='whatsapp://send?phone='+encodeURIComponent(number);
 }catch(e){status.textContent='Copy + open driver not available: '+e.message+' Use Share on phone to attach the image.';}
};
shareButton.onclick=async()=>{
 if(!file)return;
 if(!navigator.share||!navigator.canShare?.({files:[file]})){status.textContent='This browser does not support direct image sharing. Please open this page on a phone with a supported browser.';return}
 try{await navigator.share({files:[file],title:'Dulogix weekly rota',text:shareText});status.textContent='Share menu completed. Confirm delivery in WhatsApp.'}
 catch(e){if(e.name!=='AbortError')status.textContent='Could not share: '+e.message}
};
window.openDulogixRotaCard=open;
})();