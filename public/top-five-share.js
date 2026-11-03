(()=>{
const dialog=document.getElementById('top-five-dialog'),open=document.getElementById('top-five-open'),close=document.getElementById('top-five-close');
if(!dialog||!open)return;
open.addEventListener('click',()=>dialog.showModal());
close.addEventListener('click',()=>dialog.close());
dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});
const feedback=dialog.querySelector('#top-five-feedback');
const entries=()=>[...dialog.querySelectorAll('.top-five-entry')].map((row,i)=>({rank:i+1,name:row.querySelector('.top-five-name').textContent.trim(),score:row.querySelector('.top-five-score').textContent.trim()}));
const week=()=>dialog.querySelector('.top-five-week').textContent.trim();
function message(){return '🏆 DULOGIX — TOP 5 DRIVERS\n'+week()+'\n\n'+entries().map(x=>'🏆 '+x.rank+'. '+x.name+' — '+x.score).join('\n')+'\n\nDulogix Driver Performance'}
const logo=new Image();logo.src='/dulogix-logo.png';
function rounded(c,x,y,w,h,r){c.beginPath();c.roundRect(x,y,w,h,r);c.fill()}
async function imageFile(){
const canvas=document.createElement('canvas');canvas.width=900;canvas.height=940;
const c=canvas.getContext('2d');
c.fillStyle='#f2f6fb';c.fillRect(0,0,900,940);
c.fillStyle='#17456c';rounded(c,28,28,844,884,22);
c.fillStyle='white';rounded(c,45,45,810,850,16);
c.fillStyle='#17456c';rounded(c,45,45,810,185,16);
c.fillStyle='white';c.font='bold 35px system-ui';c.fillText('TOP 5 DRIVERS',80,126);
c.font='24px system-ui';c.fillText(week(),80,180);
try{await logo.decode();const ratio=Math.min(210/logo.width,120/logo.height);c.drawImage(logo,620,65,logo.width*ratio,logo.height*ratio)}catch(e){c.font='bold 27px system-ui';c.fillText('DULOGIX',660,110)}
entries().forEach((x,i)=>{
const y=260+i*110;c.fillStyle=i%2===0?'#edf3fa':'#f8fafc';rounded(c,66,y,768,94,12);
c.font='35px system-ui';c.fillStyle='#b17a1c';c.fillText('🏆',86,y+58);
c.font='bold 28px system-ui';c.fillStyle='#18344f';c.fillText(String(x.rank)+'. '+x.name,153,y+40,510);
c.font='22px system-ui';c.fillStyle='#53677d';c.fillText('Rank '+x.rank,154,y+73);
c.textAlign='right';c.font='bold 28px system-ui';c.fillStyle='#087a39';c.fillText(x.score,811,y+57,190);c.textAlign='left';
});
c.fillStyle='#53677d';c.font='22px system-ui';c.fillText('Dulogix Driver Performance',80,860);
const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
if(!blob)throw Error('Unable to generate leaderboard image');
return new File([blob],'Dulogix-top-5-drivers.png',{type:'image/png'});
}
async function copyImage(){
if(!navigator.clipboard?.write||!window.ClipboardItem)throw Error('Image clipboard not supported. Use Chrome or Edge on desktop.');
const file=await imageFile();
await navigator.clipboard.write([new ClipboardItem({'image/png':file})]);
return file;
}
dialog.querySelector('#top-five-copy').addEventListener('click',async()=>{
try{await copyImage();feedback.textContent='Leaderboard IMAGE copied. Paste with Ctrl+V into any WhatsApp chat.'}
catch(e){feedback.textContent=e.message}
});
dialog.querySelector('#top-five-whatsapp').addEventListener('click',async()=>{
try{
const file=await copyImage();
feedback.textContent='Leaderboard IMAGE copied. Open any WhatsApp chat and press Ctrl+V to paste, then Send.';
if(/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)&&navigator.canShare?.({files:[file]})){await navigator.share({files:[file],title:'Dulogix Top 5 Drivers'});return}
window.location.href='whatsapp://send';
}catch(e){if(e.name!=='AbortError')feedback.textContent='Image not copied: '+e.message}
});
})();
