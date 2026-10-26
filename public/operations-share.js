(()=>{
'use strict';
const button=document.getElementById('share-driver-times'),feedback=document.getElementById('share-feedback');
if(!button)return;
const overlay=document.createElement('div');
overlay.style.cssText='position:fixed;inset:0;background:#10243ac9;z-index:1000;display:none;align-items:center;justify-content:center;padding:14px;overflow:auto';
overlay.innerHTML='<div style="background:white;border-radius:12px;padding:18px;width:min(570px,100%);max-height:95vh;overflow:auto"><h2 style="margin:0 0 12px">Share Driver Start Times</h2><img id="ops-share-preview" alt="Driver start times report image" style="display:block;width:100%;border:1px solid #dce5ef;border-radius:8px"><p id="ops-share-status" aria-live="polite"></p><div style="display:flex;gap:8px;flex-wrap:wrap"><button id="ops-copy-share" type="button" style="background:#137b53;color:white;font-size:15px;padding:12px;flex:1">📋 Copy &amp; Share</button><button id="ops-phone-share" type="button" style="background:#137b53;color:white;font-size:15px;padding:12px;flex:1">📲 Share on phone</button><button id="ops-share-close" type="button" style="font-size:15px;padding:12px">Close</button></div></div>';
document.body.append(overlay);
const preview=overlay.querySelector('#ops-share-preview'),status=overlay.querySelector('#ops-share-status'),copy=overlay.querySelector('#ops-copy-share'),phone=overlay.querySelector('#ops-phone-share');
const mobile=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
copy.hidden=mobile;phone.hidden=!mobile;
overlay.querySelector('#ops-share-close').onclick=()=>overlay.style.display='none';
overlay.addEventListener('click',e=>{if(e.target===overlay)overlay.style.display='none'});
let file=null;
function draw(data){
 const rows=data.drivers,width=900,rowHeight=60,height=270+rows.length*rowHeight+38;
 const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
 const c=canvas.getContext('2d');c.fillStyle='#f2f6fb';c.fillRect(0,0,width,height);
 c.fillStyle='#142d4e';c.fillRect(20,20,860,height-40);
 c.fillStyle='white';c.fillRect(34,34,832,height-68);
 c.fillStyle='#142d4e';c.fillRect(34,34,832,132);
 c.fillStyle='white';c.font='bold 40px system-ui';c.fillText('DULOGIX',64,90);
 c.font='bold 26px system-ui';c.fillText('DRIVER START TIMES',64,134);
 c.fillStyle='#142d4e';c.font='bold 27px system-ui';c.fillText('Operations Date: '+data.date,64,211);
 c.fillStyle='#eaf1f8';c.fillRect(50,228,800,46);
 c.fillStyle='#142d4e';c.font='bold 22px system-ui';c.fillText('Driver Name',68,258);c.fillText('Time',735,258);
 rows.forEach((r,i)=>{const y=274+i*rowHeight;
  c.fillStyle=i%2===0?'#ffffff':'#f3f7fb';c.fillRect(50,y,800,rowHeight);
  c.fillStyle='#19344f';c.font='22px system-ui';c.fillText(r.name,68,y+38,640);
  c.font='bold 23px system-ui';c.fillText(r.time,735,y+38);
  c.strokeStyle='#dce5ee';c.beginPath();c.moveTo(50,y+rowHeight);c.lineTo(850,y+rowHeight);c.stroke();
 });
 return canvas;
}
button.addEventListener('click',async()=>{
 const data=JSON.parse(document.getElementById('share-data').textContent);
 if(!data.drivers.length){feedback.textContent='No IN drivers with saved start times for this date.';return}
 status.textContent='Preparing image…';copy.disabled=true;phone.disabled=true;overlay.style.display='flex';
 try{
  const canvas=draw(data);
  const logo=new Image();logo.src='/dulogix-logo.png';
  try{await logo.decode();const c=canvas.getContext('2d');c.fillStyle='#142d4e';c.fillRect(34,34,832,132);const scale=Math.min(250/logo.width,105/logo.height);c.drawImage(logo,56,45,logo.width*scale,logo.height*scale);c.fillStyle='white';c.font='bold 27px system-ui';c.fillText('DRIVER START TIMES',335,112,495)}catch(e){}
  preview.src=canvas.toDataURL('image/png');
  const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
  if(!blob)throw Error('Unable to create image');
  file=new File([blob],'Dulogix-Driver-Times-'+data.date.replaceAll('/','-')+'.png',{type:'image/png'});
  copy.disabled=false;phone.disabled=false;
  status.textContent=mobile?'Tap Share on phone and select WhatsApp, then choose the group.':'Review the image, then click Copy & Share. Choose the WhatsApp group, press Ctrl+V and Send.';
 }catch(e){status.textContent='Unable to prepare image: '+e.message}
});
copy.onclick=async()=>{
 if(!file)return;
 try{
  if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard unavailable. Use Chrome or Edge on a secure connection.');
  await navigator.clipboard.write([new ClipboardItem({'image/png':file})]);
  status.textContent='Image copied. Open the WhatsApp group, press Ctrl+V, review the image, then Send. If prompted to open WhatsApp, allow it.';
  window.location.href='whatsapp://send';
 }catch(e){status.textContent='Unable to copy image: '+e.message}
};
phone.onclick=async()=>{
 if(!file)return;
 if(!navigator.share||!navigator.canShare?.({files:[file]})){status.textContent='Image sharing is not supported by this browser.';return}
 try{await navigator.share({files:[file],title:'Dulogix Driver Start Times'});status.textContent='Share menu completed. Confirm delivery in WhatsApp.'}
 catch(e){if(e.name!=='AbortError')status.textContent='Unable to share: '+e.message}
};
})();
