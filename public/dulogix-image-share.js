(()=>{
'use strict';
async function copyPng(blob){
 if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard unavailable in this browser.');
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