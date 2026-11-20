document.addEventListener('click',function(event){
 const trigger=event.target.closest('[data-perf-open-dialog],[data-perf-close-dialog],[data-perf-close-nearest-dialog]');
 if(!trigger)return;
 const open=trigger.getAttribute('data-perf-open-dialog');
 const close=trigger.getAttribute('data-perf-close-dialog');
 if(open){document.getElementById(open)?.showModal();return;}
 if(close){document.getElementById(close)?.close();return;}
 trigger.closest('dialog')?.close();
});
document.addEventListener('submit',function(event){
 if(event.target.querySelector('[data-perf-confirm-unmatched]') &&
   event.submitter?.matches('[data-perf-confirm-unmatched]') &&
   !window.confirm('Delete selected unmatched IDs? Driver results will not be affected.')){
   event.preventDefault();
 }
});
