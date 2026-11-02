(()=>{
const dialog=document.getElementById('driver-scorecard-dialog'),content=document.getElementById('driver-scorecard-content');if(!dialog||!content)return;
const esc=s=>String(s??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=(x,suffix='')=>x===''||x==='—'?'Not available':esc(x)+suffix;
const line=(name,value,notes='')=>'<div class="sc-line"><span>'+name+'</span><strong>'+fmt(value)+'</strong>'+(notes?'<small>'+notes+'</small>':'')+'</div>';
const section=(title,body)=>'<section class="sc-section"><h3>'+title+'</h3>'+body+'</section>';
const css='<style>.sc-page{padding:24px 30px;font-family:Arial,sans-serif}.sc-title{text-align:center;font-size:26px;font-weight:900;color:#18354f;margin:8px 0 18px}.sc-brand{display:flex;justify-content:space-between;align-items:center}.sc-brand img{width:120px;max-height:55px;object-fit:contain}.sc-meta{text-align:right;font-size:13px}.sc-overall{background:#eaf3fb;border:1px solid #c7d9e8;border-radius:10px;padding:15px;display:flex;justify-content:space-between;align-items:center;gap:10px;margin:16px 0}.sc-overall strong{font-size:25px}.sc-status{background:#d9efdf;padding:5px 9px;border-radius:5px}.sc-section{margin:18px 0;border-top:4px solid #17456c;padding-top:8px}.sc-section h3{margin:0 0 8px;color:#17456c;font-size:18px}.sc-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 26px}.sc-line{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e0e7ee;padding:8px 2px;font-size:13px}.sc-line strong{white-space:nowrap;color:#245d82}.sc-line small{color:#687b90}.sc-focus{background:#f2f8ee;border:1px solid #c8e0b8;padding:12px 18px;border-radius:8px}.sc-focus li{margin:5px 0}@media(max-width:550px){.sc-page{padding:16px}.sc-grid{grid-template-columns:1fr}.sc-title{font-size:20px}}</style>';
function openReport(button){
const x=button.dataset;const week=document.querySelector('.top-five-week')?.textContent?.trim()||'Selected week';
const focus=[['Delivery completion rate',x.dcr],['DSC DPMO',x.dpmo],['Customer escalations',x.ce]].filter(([k,v])=>v!=='—'&&v!=='');
content.innerHTML=css+'<article class="sc-page"><div class="sc-brand"><img src="/dulogix-logo.png" alt="Dulogix"><div class="sc-meta"><b>'+esc(week)+'</b><div>Individual Driver Performance</div></div></div><h1 class="sc-title">DSP WEEKLY SCORECARD</h1><h2>'+esc(x.driver)+'</h2><div style="font-size:12px;color:#607991">'+esc(x.id)+'</div><div class="sc-overall"><div><b>Overall Score</b><div>Rank '+esc(x.rank)+'</div></div><div style="text-align:right"><strong>'+fmt(x.score)+' / 100</strong><div><span class="sc-status">'+esc(x.status)+'</span></div></div></div>'+
section('Comprehensive Safety', '<div class="sc-grid">'+line('Safe Driving Metric (FICO / Ementor)',x.fico)+line('Netradyne violation deductions',x.net)+'</div>')+
section('Delivery Quality & Standard Work Compliance','<div class="sc-grid">'+line('Delivery Completion Rate (DCR)',x.dcr,'%')+line('Delivery Workflow Compliance',x.dwc,'%')+line('In-app Delivery Compliance',x.iadc,'%')+line('Delivery Success Conditions (DSC DPMO)',x.dpmo)+line('Photo on Delivery (POD)',x.pod,'%')+line('Contact Compliance (CC)',x.cc,'%')+line('Customer Escalations (CE)',x.ce)+line('Negative Feedback (CDF DPMO)',x.cdf)+'</div>')+
section('Pickup Quality',line('PSB Collection Fails / 100 stops',x.psb))+
section('Recommended Focus Areas','<div class="sc-focus"><ol>'+focus.map(([k,v])=>'<li>'+esc(k)+' — '+esc(v)+'</li>').join('')+'</ol><small>First-draft focus areas from available metrics; thresholds and prioritisation to be confirmed.</small></div>')+
'<div style="font-size:12px;color:#667a8f;margin-top:15px">Current Week Tips — Coming Soon</div></article>';
dialog.showModal();
}
document.addEventListener('click',e=>{const b=e.target.closest('.driver-report-open');if(b){e.preventDefault();e.stopPropagation();openReport(b)}});
document.getElementById('driver-scorecard-close').addEventListener('click',()=>dialog.close());
document.getElementById('driver-scorecard-print').addEventListener('click',()=>window.print());
dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});
})();
