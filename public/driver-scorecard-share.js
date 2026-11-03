(()=>{
'use strict';
const dialog=document.getElementById('driver-scorecard-dialog');
const content=document.getElementById('driver-scorecard-content');
const action=document.getElementById('driver-scorecard-share');
const imageAction=document.getElementById('driver-scorecard-share-image');
if(!dialog||!content||!action)return;
async function imageFromReport(){
 const node=content.querySelector('.sc-page');
 if(!node)throw Error('Open a scorecard first.');
 if(typeof window.html2canvas!=='function')throw Error('Image renderer is unavailable.');
 // Render a separate mobile layout; never resize or alter the A4 report.
 const mobile=node.cloneNode(true);
 const style=document.createElement('style');
 style.textContent='.sc-mobile-export{width:580px!important;max-width:none!important;padding:24px!important;box-sizing:border-box!important;background:white!important}.sc-mobile-export .sc-category-grid,.sc-mobile-export .sc-explainer-grid,.sc-mobile-export .sc-grid{grid-template-columns:minmax(0,1fr)!important}.sc-mobile-export .sc-category-grid{gap:14px!important}.sc-mobile-export .sc-category-grid>.sc-section{padding:18px!important}.sc-mobile-export .sc-line{font-size:16px!important;line-height:1.5!important}.sc-mobile-export .sc-line small{font-size:14px!important}.sc-mobile-export .sc-category-grid .sc-section h3{font-size:20px!important}.sc-mobile-export .sc-track{height:10px!important}.sc-mobile-export .sc-metric{font-weight:700!important}.sc-mobile-export .sc-focus,.sc-mobile-export .sc-explainer{font-size:15px!important}.sc-mobile-export .sc-title{font-size:24px!important}.sc-mobile-export .sc-meta{font-size:15px!important}';
 mobile.classList.add('sc-mobile-export');
 mobile.style.cssText='position:absolute;top:0;left:0;z-index:-1;pointer-events:none;height:auto;max-height:none;overflow:visible';
 document.head.append(style);document.body.append(mobile);
 try{
  await document.fonts?.ready;
  await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
  const canvas=await window.html2canvas(mobile,{backgroundColor:'#ffffff',scale:Math.min(5,16384/Math.max(580,mobile.scrollHeight)),useCORS:true,allowTaint:false,logging:false,scrollX:0,scrollY:0,width:580,height:mobile.scrollHeight});
  const output=document.createElement('canvas');
  output.width=canvas.width;output.height=canvas.height;
  const ctx=output.getContext('2d');
  ctx.filter='saturate(1.25) contrast(1.04)';
  ctx.drawImage(canvas,0,0);
  const png=await new Promise(resolve=>output.toBlob(resolve,'image/png'));
  if(!png)throw Error('Unable to create scorecard PNG.');
  return png;
 }finally{mobile.remove();style.remove();}
}

window.driverScorecardMobileImage=imageFromReport;
async function pdfFromReport(){
 if(!window.jspdf?.jsPDF)throw Error('PDF renderer unavailable. Refresh the page.');
 const png=await imageFromReport();
 const url=URL.createObjectURL(png);
 try{
  const img=new Image();img.src=url;await img.decode();
  const pdf=new window.jspdf.jsPDF({orientation:'portrait',unit:'mm',format:'a4',compress:true});
  const pageWidth=210,pageHeight=297,margin=8;
  const contentWidth=pageWidth-margin*2,contentHeight=pageHeight-margin*2;
  const pixelHeight=Math.floor(img.width*contentHeight/contentWidth);
  let offset=0,index=0;
  while(offset<img.height){
   const part=document.createElement('canvas');
   part.width=img.width;part.height=Math.min(pixelHeight,img.height-offset);
   const ctx=part.getContext('2d');
   ctx.fillStyle='#fff';ctx.fillRect(0,0,part.width,part.height);
   ctx.drawImage(img,0,offset,img.width,part.height,0,0,img.width,part.height);
   if(index++)pdf.addPage();
   pdf.addImage(part.toDataURL('image/png'),'PNG',margin,margin,contentWidth,part.height*contentWidth/img.width,undefined,'FAST');
   offset+=part.height;
  }
  return pdf.output('blob');
 }finally{URL.revokeObjectURL(url);}
}
window.driverScorecardPdf=pdfFromReport;
async function sharePdf(button){
 const blob=await pdfFromReport();
 const filename='Dulogix-scorecard-'+(button.dataset.id||'driver')+'.pdf';
 const file=new File([blob],filename,{type:'application/pdf'});
 if(!navigator.share||!navigator.canShare?.({files:[file]})){
  throw Error('Direct PDF sharing is unavailable in this browser. Open this page on a supported phone to share directly to WhatsApp.');
 }
 await navigator.share({files:[file],title:'Dulogix weekly scorecard - '+button.dataset.driver});
 return true;
}
window.driverScorecardSharePdf=sharePdf;

async function shareImage(button){
 const blob=await imageFromReport();
 await window.openDulogixPreparedImage({
  blob,filename:'Dulogix-scorecard-'+(button.dataset.id||'driver')+'.png',
  title:'Share scorecard image - '+button.dataset.driver,
  phone:button.dataset.phone,
  description:'Dulogix weekly scorecard for '+button.dataset.driver
 });
 const previousScroll=dialog.scrollTop,previousContentScroll=content.scrollTop;
 dialog.close();
 const close=document.getElementById('rota-share-close');
 if(close)close.addEventListener('click',()=>{
  if(!dialog.open){dialog.showModal();dialog.scrollTop=previousScroll;content.scrollTop=previousContentScroll;}
 },{once:true});
}
window.driverScorecardShareImage=shareImage;
let busy=false;
if(imageAction)imageAction.addEventListener('click',async(e)=>{
 e.preventDefault();e.stopPropagation();
 if(busy)return;
 const button=window.driverScorecardCurrentButton;
 if(!button){alert('Open a driver scorecard first.');return;}
 busy=true;imageAction.disabled=true;
 const old=imageAction.textContent;imageAction.textContent='Preparing high-resolution image...';
 try{await shareImage(button);}
 catch(error){alert('Unable to share image: '+error.message);}
 finally{busy=false;imageAction.disabled=false;imageAction.textContent=old;}
});
action.addEventListener('click',async(e)=>{
 e.preventDefault();e.stopPropagation();
 if(busy)return;
 const button=window.driverScorecardCurrentButton;
 if(!button){alert('Open a driver scorecard first.');return}
 busy=true;action.disabled=true;
 const old=action.textContent;action.textContent='Preparing PDF...';
 try{
  await sharePdf(button);
  // Keep the A4 report open after the native share sheet closes.
 }catch(error){alert('Unable to share scorecard: '+error.message)}
 finally{busy=false;action.disabled=false;action.textContent=old}
});
})();
