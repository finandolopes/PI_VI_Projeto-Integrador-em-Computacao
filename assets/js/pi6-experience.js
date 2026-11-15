/* Animação opcional: nenhuma biblioteca externa, sem capturar cliques ou scroll */
(function () {
  'use strict';
  const scene = document.querySelector('[data-pi6-scene]');
  if (!scene || !window.matchMedia) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
  if (reduced.matches || !fine.matches) return;
  let raf = 0;
  function position(event) {
    const rect = scene.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    const x = Math.max(-1, Math.min(1, ((event.clientX - rect.left) / rect.width - .5) * 2));
    const y = Math.max(-1, Math.min(1, ((event.clientY - rect.top) / rect.height - .5) * 2));
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(function () {
      scene.style.setProperty('--pi-rotate-x', (-y * 10).toFixed(2) + 'deg');
      scene.style.setProperty('--pi-rotate-y', (x * 14).toFixed(2) + 'deg');
      scene.style.setProperty('--pi-move-x', (x * 7).toFixed(1) + 'px');
      scene.style.setProperty('--pi-move-y', (y * 7).toFixed(1) + 'px');
      raf = 0;
    });
  }
  scene.addEventListener('pointermove', position, {passive:true});
  scene.addEventListener('pointerleave', function () {
    if (raf) cancelAnimationFrame(raf);
    scene.style.setProperty('--pi-rotate-x','0deg');
    scene.style.setProperty('--pi-rotate-y','0deg');
    scene.style.setProperty('--pi-move-x','0px');
    scene.style.setProperty('--pi-move-y','0px');
  });
})();

/* Refinamento progressivo: não intercepta formulários, links ou navegação existente. */
(function(){
 'use strict';
 var root=document.querySelector('.pi6-experience');if(!root)return;
 document.documentElement.classList.add('pi6-enhanced');
 var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 if(!reduced&&window.IntersectionObserver){
   var observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('pi6-visible');observer.unobserve(entry.target)}})},{threshold:.12,rootMargin:'0px 0px -30px 0px'});
   root.querySelectorAll('.pi6-inner>div').forEach(function(el){el.classList.add('pi6-reveal');observer.observe(el)});
 }
 if(!reduced&&window.matchMedia('(hover: hover) and (pointer: fine)').matches){
   var pending=false,px=50,py=50;
   root.addEventListener('pointermove',function(e){var rect=root.getBoundingClientRect();px=(e.clientX-rect.left)/rect.width*100;py=(e.clientY-rect.top)/rect.height*100;if(!pending){pending=true;requestAnimationFrame(function(){root.style.setProperty('--pi-pointer-x',px.toFixed(2)+'%');root.style.setProperty('--pi-pointer-y',py.toFixed(2)+'%');pending=false})}},{passive:true});
 }
})();

/* Cartões 3D: interatividade opcional, sem escutar clique e sem interceptar navegação. */
(function(){
 'use strict';
 var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 var fine=window.matchMedia&&window.matchMedia('(hover: hover) and (pointer: fine)').matches;
 var cards=document.querySelectorAll('.pi6-flow-card');
 if(!reduced&&fine){cards.forEach(function(card){
  var frame=0;
  card.addEventListener('pointermove',function(ev){
   var r=card.getBoundingClientRect(),x=(ev.clientX-r.left)/r.width-.5,y=(ev.clientY-r.top)/r.height-.5;
   if(frame)cancelAnimationFrame(frame);
   frame=requestAnimationFrame(function(){
    card.style.transform='rotateX('+(-y*9).toFixed(2)+'deg) rotateY('+(x*10).toFixed(2)+'deg) translateY(-6px)';
    card.style.setProperty('--pi-card-x',((x+.5)*100).toFixed(1)+'%');card.style.setProperty('--pi-card-y',((y+.5)*100).toFixed(1)+'%');frame=0;
   });
  },{passive:true});
  card.addEventListener('pointerleave',function(){if(frame)cancelAnimationFrame(frame);card.style.transform='';card.style.removeProperty('--pi-card-x');card.style.removeProperty('--pi-card-y')});
 })}
 if(!reduced&&'IntersectionObserver' in window){
  var items=document.querySelectorAll('[data-pi6-appear]');
  var obs=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('pi6-in-view');obs.unobserve(e.target)}})},{threshold:.08});
  items.forEach(function(el){el.classList.add('pi6-pending');obs.observe(el)})
 }
})();
