/* Stable progressive enhancements: no dependency on the CGI/backend. */
(() => {
 const ready=fn=>document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn,{once:true}):fn();
 ready(()=>{
   const mobile=matchMedia('(max-width:650px)');
   const reduced=matchMedia('(prefers-reduced-motion: reduce)');
   const nav=document.getElementById('nav');const hamb=document.getElementById('hamb');
   if(nav&&hamb){
     nav.addEventListener('click',e=>{if(e.target.closest('a')&&mobile.matches){nav.classList.remove('open');hamb.setAttribute('aria-expanded','false')}});
     document.addEventListener('keydown',e=>{if(e.key==='Escape'&&mobile.matches){nav.classList.remove('open');hamb.setAttribute('aria-expanded','false');hamb.focus()}});
   }
   document.querySelectorAll('a[href^="#"]').forEach(a=>{
     const dest=a.getAttribute('href');if(dest==='#'||!dest)return;
     a.addEventListener('click',()=>{const t=document.getElementById(dest.substring(1));if(t){t.setAttribute('tabindex','-1');setTimeout(()=>t.focus({preventScroll:true}),80)}});
   });
   // Ensure service 3D remains an enhancement, not the source of content.
   if(!window.WebGLRenderingContext||reduced.matches){document.documentElement.classList.add('cf-static-effects')}
   document.querySelectorAll('img:not([alt])').forEach(img=>img.setAttribute('alt',''));
   document.querySelectorAll('a[target="_blank"]').forEach(a=>{if(!a.rel.includes('noopener'))a.rel=(a.rel+' noopener noreferrer').trim()});
 });
})();
