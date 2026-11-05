(()=>{
'use strict';
async function copyPng(blob){
 if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard unavailable in this browser.');
 if(blob.type!=='image/png'){
  const bitmap=await createImageBitmap(blob);
  try{const canvas=document.createElement('canvas');canvas.width=bitmap.width;canvas.height=bitmap.height;canvas.getContext('2d').drawImage(bitmap,0,0);blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));}
  finally{bitmap.close();}
  if(!blob)throw Error('Could not prepare image for clipboard.');
 }
 await navigator.clipboard.write([new ClipboardItem({'image/png':blob})]);
}
function phone(value){
 let number=String(value||'').replace(/[\s().-]/g,'');
 if(number.startsWith('00'))number='+'+number.slice(2);
 return /^\+[1-9]\d{7,14}$/.test(number)?number.slice(1):null;
}
function openWhatsApp(number){
 const clean=phone(number.startsWith('+')?number:'+'+number);
 if(!clean)throw Error('Driver needs an international-format phone number.');
 window.location.href='whatsapp://send?phone='+encodeURIComponent(clean);
}
function downloadPng(blob,name){
 const url=URL.createObjectURL(blob);
 const a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();
 setTimeout(()=>URL.revokeObjectURL(url),30000);
}
window.DulogixImageShare={copyPng,phone,openWhatsApp,downloadPng};
})();