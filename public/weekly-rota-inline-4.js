
(()=> {
 const bar=document.createElement('div');
 bar.setAttribute('aria-hidden','true');
 bar.className='rota-sticky-header-bar';
 const strip=document.createElement('div');
 strip.className='rota-sticky-header-strip';
 bar.appendChild(strip);document.body.appendChild(bar);
 bar.style.pointerEvents='auto';
 bar.addEventListener('click',event=>{const cell=event.target.closest('[data-rota-day]');if(cell&&cell.dataset.rotaDay!=='')document.querySelector('#drivers thead th[data-rota-day="'+cell.dataset.rotaDay+'"]')?.click();});
 function update(){
  const table=document.getElementById('drivers'),wrap=table?.parentElement,head=table?.querySelector('thead tr');
  if(!table||!wrap||!head)return;
  const rect=wrap.getBoundingClientRect(),headRect=head.getBoundingClientRect();
  const active=rect.top<0&&rect.bottom>headRect.height&&rect.width>0;
  bar.style.display=active?'block':'none';
  if(!active)return;
  bar.style.left=rect.left+'px';bar.style.width=rect.width+'px';
  bar.style.height=headRect.height+'px';
  const cells=[...head.cells],originals=[...strip.children];
  if(originals.length!==cells.length){strip.replaceChildren(...cells.map(()=>document.createElement('div')));}
  cells.forEach((cell,i)=>{
   const item=strip.children[i],width=cell.getBoundingClientRect().width;
   item.dataset.rotaDay=cell.dataset.rotaDay??'';
   item.title=cell.hasAttribute('data-rota-day')?'Filter drivers for this day':'';
   // Preserve the weekday/date and surplus values as separate elements.
   // textContent flattened them into a single incorrect-looking line.
   item.replaceChildren(...Array.from(cell.childNodes,node=>node.cloneNode(true)));
   item.className='rota-sticky-header-cell'; item.style.flexBasis=width+'px'; item.style.width=width+'px';
   item.style.cursor=cell.hasAttribute('data-rota-day')?'pointer':'default';
   const layout=item.querySelector('.rota-day-heading-layout');
   if(layout){
    layout.classList.add('rota-sticky-heading-layout');
    const date=layout.querySelector('.rota-day-heading-date');
    if(date)date.classList.add('rota-sticky-heading-date');
    const figures=layout.querySelector('.rota-day-surplus');
    if(figures){
     figures.classList.add('rota-sticky-surplus');
     figures.querySelectorAll('.rota-day-surplus-value').forEach((value,index)=>{
      const short=value.classList.contains('short');
      value.classList.add('rota-sticky-surplus-value'); value.classList.toggle('rota-sticky-short',short); value.classList.toggle('rota-sticky-second',!short&&index===1);
     });
    }
   }
  });
  strip.style.height=headRect.height+'px';
  strip.style.transform='translateX(-'+wrap.scrollLeft+'px)';
 }
 let pending=false;
 function schedule(){if(pending)return;pending=true;requestAnimationFrame(()=>{pending=false;update()})}
 window.addEventListener('scroll',schedule,{passive:true});
 window.addEventListener('resize',schedule,{passive:true});
 document.addEventListener('scroll',schedule,{passive:true,capture:true});
 document.addEventListener('DOMContentLoaded',schedule);
})();
