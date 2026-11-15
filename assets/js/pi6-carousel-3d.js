/* Progressive enhancement: existing Bootstrap carousel, images, labels and navigation are authoritative. */
(function(){
 'use strict';
 function init(){
  var hero=document.getElementById('heroCarousel');
  if(!hero||!hero.classList.contains('pi6-cinematic'))return;
  var items=Array.prototype.slice.call(hero.querySelectorAll('.carousel-item'));
  if(!items.length)return;
  var reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  var fine=window.matchMedia('(hover:hover) and (pointer:fine)');
  items.forEach(function(item){
   var bg=item.style.backgroundImage;
   if(bg&&bg!=='none')item.style.setProperty('--c3-bg',bg);
  });
  // Preserve the original inline images as graceful fallback if a slide has no background.
  if(!items.every(function(item){return !!item.style.getPropertyValue('--c3-bg')}))return;
  hero.classList.add('pi6-c-ready');
  var progress=document.createElement('div');progress.className='pi6-c-progress';progress.setAttribute('aria-hidden','true');
  var bar=document.createElement('span');progress.appendChild(bar);hero.appendChild(progress);
  var interval=parseInt(hero.getAttribute('data-bs-interval'),10);if(!Number.isFinite(interval)||interval<1000)interval=5000;
  hero.style.setProperty('--c3-duration',interval+'ms');
  var playing=!reduced.matches&&items.length>1;
  var isHover=false,isHidden=false;
  function updateState(){
   var paused=isHover||isHidden||reduced.matches;
   hero.classList.toggle('pi6-c-playing',playing&&!reduced.matches);
   hero.classList.toggle('pi6-c-paused',paused);
  }
  // Reset visual progress on Bootstrap's actual successful slide transitions.
  hero.addEventListener('slid.bs.carousel',function(){bar.style.animation='none';void bar.offsetWidth;bar.style.animation='';updateState()});
  hero.addEventListener('mouseenter',function(){isHover=true;updateState()});
  hero.addEventListener('mouseleave',function(){isHover=false;updateState()});
  document.addEventListener('visibilitychange',function(){isHidden=document.hidden;updateState()});
  if(reduced.addEventListener)reduced.addEventListener('change',function(){updateState();if(reduced.matches){hero.style.setProperty('--c3x','0deg');hero.style.setProperty('--c3y','0deg')}});
  // Pointer parallax changes only CSS variables (no interference with carousel's transforms).
  if(fine.matches&&!reduced.matches){
   var frame=0;
   hero.classList.add('pi6-c-interactive');
   hero.addEventListener('pointermove',function(ev){
    var r=hero.getBoundingClientRect();if(!r.width||!r.height)return;
    var x=(ev.clientX-r.left)/r.width-.5,y=(ev.clientY-r.top)/r.height-.5;
    if(frame)cancelAnimationFrame(frame);
    frame=requestAnimationFrame(function(){
     hero.style.setProperty('--c3x',(-y*2.8).toFixed(2)+'deg');hero.style.setProperty('--c3y',(x*3.4).toFixed(2)+'deg');
     hero.style.setProperty('--c3dx',(x*20).toFixed(1)+'px');hero.style.setProperty('--c3dy',(y*14).toFixed(1)+'px');frame=0;
    });
   },{passive:true});
   hero.addEventListener('pointerleave',function(){if(frame)cancelAnimationFrame(frame);['--c3x','--c3y','--c3dx','--c3dy'].forEach(function(k){hero.style.removeProperty(k)})});
  }
  updateState();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
