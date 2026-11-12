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
 style.textContent='.sc-mobile-export{font-size:19px!important;line-height:1.5!important}.sc-mobile-export .sc-rank-badge{font-size:17px!important}.sc-mobile-export .sc-overall{padding:20px!important}.sc-mobile-export{width:2048px!important;max-width:none!important;padding:30px!important;box-sizing:border-box!important;background:white!important}.sc-mobile-export .sc-category-grid,.sc-mobile-export .sc-explainer-grid,.sc-mobile-export .sc-grid{grid-template-columns:minmax(0,1fr)!important}.sc-mobile-export .sc-category-grid{gap:14px!important}.sc-mobile-export .sc-category-grid>.sc-section{padding:18px!important}.sc-mobile-export .sc-line{font-size:20px!important;line-height:1.5!important}.sc-mobile-export .sc-line small{font-size:18px!important}.sc-mobile-export .sc-category-grid .sc-section h3{font-size:24px!important}.sc-mobile-export .sc-track{height:10px!important}.sc-mobile-export .sc-metric{font-weight:700!important}.sc-mobile-export .sc-focus,.sc-mobile-export .sc-explainer{font-size:19px!important}.sc-mobile-export .sc-title{font-size:30px!important}.sc-mobile-export .sc-meta{font-size:19px!important}';
 // The export uses the original report's computed typography, scaled below.
 // Keep typography, logo and metric columns proportional to the 2048px export.
 style.textContent += '.sc-mobile-export{padding:65px!important}.sc-mobile-export .sc-brand img{width:420px!important;max-height:220px!important}.sc-mobile-export .sc-brand{gap:45px!important}.sc-mobile-export .sc-category-grid,.sc-mobile-export .sc-grid,.sc-mobile-export .sc-explainer-grid{grid-template-columns:minmax(0,1fr)!important}.sc-mobile-export .sc-category-grid>.sc-section,.sc-mobile-export .sc-left-column>.sc-section{padding:35px!important}.sc-mobile-export .sc-line{display:grid!important;grid-template-columns:minmax(0,1fr) auto auto!important;align-items:center!important;gap:22px!important;padding:20px 5px!important}.sc-mobile-export .sc-line>span{min-width:0!important;overflow-wrap:anywhere!important}.sc-mobile-export .sc-line strong{white-space:normal!important;text-align:right!important}.sc-mobile-export .sc-line small{white-space:normal!important;text-align:right!important;min-width:145px!important}.sc-mobile-export .sc-safety .sc-line{grid-template-columns:minmax(0,1fr) 170px 200px!important}.sc-mobile-export .sc-progress small{min-width:260px!important}.sc-mobile-export .sc-track{height:18px!important}.sc-mobile-export .sc-overall{padding:35px!important;gap:30px!important}.sc-mobile-export .sc-grading-legend{display:flex!important;flex-wrap:wrap!important;gap:12px!important}.sc-mobile-export .sc-grading-legend i{display:none!important}.sc-mobile-export .sc-donut{width:260px!important;height:260px!important}.sc-mobile-export .sc-core{width:195px!important;height:195px!important}.sc-mobile-export .sc-priorities>div{gap:30px!important}';
 // Percentage awards need a dedicated column wide enough for "20% / 20%".
 style.textContent += '.sc-mobile-export .sc-line{grid-template-columns:minmax(0,1fr) minmax(140px,max-content) 330px!important;column-gap:30px!important}.sc-mobile-export .sc-line small{width:330px!important;min-width:330px!important;max-width:330px!important;font-size:38px!important;white-space:nowrap!important;line-height:1.25!important;overflow:visible!important}.sc-mobile-export .sc-safety .sc-line{grid-template-columns:minmax(0,1fr) 190px 330px!important}.sc-mobile-export .sc-line strong{min-width:0!important;white-space:nowrap!important}.sc-mobile-export .sc-progress small{font-size:38px!important;min-width:340px!important;white-space:nowrap!important}.sc-mobile-export .sc-category-grid>.sc-section{min-width:0!important}';
 // Export-only spacing: preserve breathing room around badges, scores and explanatory text.
 style.textContent += '.sc-mobile-export .sc-line{grid-template-columns:minmax(0,1fr) minmax(190px,max-content) 390px!important;column-gap:44px!important;padding:24px 35px!important}.sc-mobile-export .sc-safety .sc-line{grid-template-columns:minmax(0,1fr) 230px 390px!important}.sc-mobile-export .sc-line .sc-metric{padding:12px 24px!important;margin-right:18px!important;min-width:150px!important;box-sizing:border-box!important}.sc-mobile-export .sc-line small{width:390px!important;min-width:390px!important;max-width:390px!important;font-size:54px!important;padding:0 22px!important;box-sizing:border-box!important;text-align:right!important}.sc-mobile-export .sc-progress small{font-size:54px!important;min-width:390px!important;padding:0 22px!important;box-sizing:border-box!important}.sc-mobile-export .sc-recommended h3,.sc-mobile-export .sc-explainer h3{padding:24px 35px!important;line-height:1.3!important}.sc-mobile-export .sc-explainer h3 small{display:block!important;margin:12px 0 0!important;line-height:1.4!important}.sc-mobile-export .sc-focus-line{grid-template-columns:220px minmax(0,1fr)!important;align-items:stretch!important;line-height:1.4!important}.sc-mobile-export .sc-focus-line b{padding:20px 28px!important;overflow-wrap:anywhere!important}.sc-mobile-export .sc-focus-line span{padding:20px 35px!important;overflow-wrap:anywhere!important}.sc-mobile-export .sc-explainer-grid{padding:22px 35px!important}.sc-mobile-export .sc-explainer-grid>div{padding:18px 0!important}';
 mobile.classList.add('sc-mobile-export');
 mobile.style.cssText='position:absolute;top:0;left:0;z-index:-1;pointer-events:none;height:auto;max-height:none;overflow:visible';
 document.head.append(style);document.body.append(mobile);
 // Double every computed font size from the original scorecard, including badges,
 // small print, table labels and elements with inline font-size declarations.
 const originalElements=[node,...node.querySelectorAll('*')];
 const exportElements=[mobile,...mobile.querySelectorAll('*')];
 originalElements.forEach((source,i)=>{
  const target=exportElements[i];if(!target)return;
  const px=parseFloat(getComputedStyle(source).fontSize);
  if(Number.isFinite(px)&&px>0){
   const tag=source.tagName.toLowerCase();
   const isHeading=/^h[1-6]$/.test(tag)||source.classList.contains('sc-title');
   const minimum=isHeading?90:58;
   target.style.setProperty('font-size',Math.max(px*4,minimum)+'px','important');
   target.style.setProperty('line-height','1.4','important');
  }
 });

 // Export is 2048px wide and fonts are enlarged 4x; scale the POD detail table too.
 const podTable=mobile.querySelector('.sc-pod-reasons');
 if(podTable){
  podTable.style.setProperty('width','1120px','important');
  podTable.style.setProperty('max-width','100%','important');
  podTable.style.setProperty('margin-left','65px','important');
  podTable.querySelectorAll('th').forEach(cell=>{
   cell.style.setProperty('font-size','38px','important');
   cell.style.setProperty('line-height','1.2','important');
   cell.style.setProperty('white-space','normal','important');
   cell.style.setProperty('word-break','normal','important');
   cell.style.setProperty('overflow-wrap','normal','important');
   cell.style.setProperty('padding','0 8px','important');
  });
  podTable.querySelectorAll('td').forEach(cell=>{
   cell.style.setProperty('font-size','44px','important');
   cell.style.setProperty('padding','12px 8px 0','important');
  });
 }
 // Preserve visible hierarchy for indented metric labels in the shared image.
 mobile.querySelectorAll('.sc-line>span[style*="padding-left"]').forEach(label=>{
  label.style.setProperty('padding-left','85px','important');
 });

 mobile.querySelectorAll('.sc-line small,.sc-progress small').forEach(el=>{el.style.setProperty('font-size','54px','important');el.style.setProperty('white-space','nowrap','important');});
 mobile.querySelectorAll('.sc-focus-line b').forEach(el=>{el.style.setProperty('min-width','0','important');});
 // Keep short focus labels on one line, regardless of the original 4x typography scaling.
 mobile.querySelectorAll('.sc-focus-line').forEach(row=>{
  row.style.setProperty('grid-template-columns','280px minmax(0,1fr)','important');
  const label=row.querySelector('b');
  if(label){
   label.style.setProperty('white-space','nowrap','important');
   label.style.setProperty('overflow-wrap','normal','important');
   label.style.setProperty('word-break','normal','important');
   label.style.setProperty('font-size','42px','important');
   label.style.setProperty('padding','20px 25px','important');
  }
 });


 try{
  await document.fonts?.ready;
  await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
  const canvas=await window.html2canvas(mobile,{backgroundColor:'#ffffff',scale:1,useCORS:true,allowTaint:false,logging:false,scrollX:0,scrollY:0,width:2048,height:mobile.scrollHeight});
  if(canvas.width!==2048)throw Error('Unexpected export width: '+canvas.width+'px');
  const output=document.createElement('canvas');
  output.width=canvas.width;output.height=canvas.height;
  const ctx=output.getContext('2d');
  ctx.filter='saturate(1.25) contrast(1.04)';
  ctx.drawImage(canvas,0,0);
  // Clipboard PNG and downloadable JPEG are encoded from the SAME source canvas.
  // Do not decode and re-rasterize the JPEG when copying to WhatsApp.
  const clipboardPng=await new Promise(resolve=>output.toBlob(resolve,'image/png'));
  if(!clipboardPng)throw Error('Unable to prepare full-resolution clipboard image.');
  window.driverScorecardClipboardPng=clipboardPng;
  const jpg=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',1.0));
  if(!jpg)throw Error('Unable to create scorecard JPEG.');
  return jpg;
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
window.driverScorecardDownloadPdf=async function(button){const blob=await pdfFromReport();const file=new File([blob],'Dulogix-scorecard-'+(button.dataset.id||'driver')+'.pdf',{type:'application/pdf'});downloadPdf(file);};
let preparedPdf=null,preparedId=null;
const nativeMobileShare=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
function preparedActionLabel(){return nativeMobileShare?'Share prepared PDF':'Download prepared PDF';}
dialog.addEventListener('close',()=>{preparedPdf=null;preparedId=null;action.textContent='Share PDF';});
function downloadPdf(file){
 const url=URL.createObjectURL(file);
 const link=document.createElement('a');link.href=url;link.download=file.name;
 document.body.append(link);link.click();link.remove();
 setTimeout(()=>URL.revokeObjectURL(url),60000);
}
function sharePdf(button){
 const file=preparedPdf;
 if(!file||preparedId!==button.dataset.id)throw Error('Prepare the PDF first.');
 if(nativeMobileShare&&navigator.share&&navigator.canShare?.({files:[file]})){
  // Call share synchronously during the click gesture, before any await.
  return navigator.share({files:[file],title:'Dulogix weekly scorecard - '+button.dataset.driver});
 }
 downloadPdf(file);
 return Promise.resolve();
}
window.driverScorecardSharePdf=sharePdf;

async function shareImage(button){
 const blob=await imageFromReport();
 await window.openDulogixPreparedImage({
  blob,clipboardBlob:window.driverScorecardClipboardPng,filename:'Dulogix-scorecard-'+(button.dataset.id||'driver')+'.jpg',
  title:'Share scorecard image - '+button.dataset.driver,
  phone:button.dataset.phone,
  description:'Dulogix weekly scorecard for '+button.dataset.driver,
  asDocument:true
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
 if(!button){alert('Open a driver scorecard first.');return;}
 if(preparedPdf&&preparedId===button.dataset.id){
  try{
   // No asynchronous work before the browser's native share request.
   const result=sharePdf(button);
   await result;
  }catch(error){
   if(error.name==='AbortError')return;
   if(error.name==='NotAllowedError'){
    downloadPdf(preparedPdf);
    alert('Browser blocked direct sharing. The PDF was downloaded so you can attach it manually.');
   }else alert('Unable to share scorecard: '+error.message);
  }
  return;
 }
 busy=true;action.disabled=true;
 action.textContent='Preparing PDF...';
 try{
  const blob=await pdfFromReport();
  preparedId=button.dataset.id;
  preparedPdf=new File([blob],'Dulogix-scorecard-'+(button.dataset.id||'driver')+'.pdf',{type:'application/pdf'});
  action.textContent=preparedActionLabel();
 }catch(error){
  action.textContent='Share PDF';
  alert('Unable to prepare scorecard PDF: '+error.message);
 }finally{busy=false;action.disabled=false;}
});
})();
