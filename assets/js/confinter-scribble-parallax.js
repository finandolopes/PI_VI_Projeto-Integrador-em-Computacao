/* Artistic wireframe + parallax. Additive; safe fallback when Three.js unavailable. */
(function(){'use strict';
 function init(){
   var host=document.getElementById('scribble-three'), hero=document.getElementById('inicio'), toggle=document.getElementById('wireframe-toggle');
   if(!host||!hero) return;
   var reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
   if(reduced.matches || !window.THREE){ if(toggle)toggle.hidden=true;return; }
   var T=window.THREE, renderer, scene, camera, object, frame=0, enabled=true, target={x:0,y:0}, parallax=0, visible=true;
   try{ renderer=new T.WebGLRenderer({alpha:true,antialias:true,powerPreference:'low-power'}); }
   catch(e){ if(toggle)toggle.hidden=true;return; }
   renderer.setClearColor(0x000000,0);renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,1.5));
   host.appendChild(renderer.domElement);scene=new T.Scene();camera=new T.PerspectiveCamera(40,1,.1,40);camera.position.set(0,0,8.7);
   object=new T.Group();scene.add(object);
   // Two braided organic forms. Deterministic jitter is applied ONCE rather than every frame.
   var geometries=[new T.TorusKnotGeometry(1.45,.47,280,15,2,5),new T.TorusKnotGeometry(1.24,.32,250,13,3,7)];
   geometries.forEach(function(g,i){
     var coords=g.attributes.position;
     for(var j=0;j<coords.count;j++){
       var k=j*3+i*71;
       var jitter=(Math.sin(k*12.9898)*43758.5453)%1;
       coords.setXYZ(j,coords.getX(j)+(jitter-.5)*.035,coords.getY(j)+(Math.sin(k*3.21)*.019),coords.getZ(j)+(Math.cos(k*8.13)*.021));
     }
     coords.needsUpdate=true;
     var w=new T.WireframeGeometry(g);var lines=new T.LineSegments(w,new T.LineBasicMaterial({color:i?0xf5c86e:0x92e4ff,transparent:true,opacity:i?.25:.35,depthWrite:false}));
     lines.rotation.set(.15+i*.18,0,i*.35);object.add(lines);g.dispose();
   });
   function resize(){var w=Math.max(host.clientWidth,200),h=Math.max(host.clientHeight,200);renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix();object.scale.setScalar(w<600?.84:1)}
   var obs=new IntersectionObserver(function(entries){visible=entries[0].isIntersecting},{threshold:.01});obs.observe(hero);
   function scroll(){ var r=hero.getBoundingClientRect();parallax=Math.max(-1,Math.min(1,-r.top/Math.max(r.height,1)));hero.style.setProperty('--hero-scroll',Math.round(parallax*160)+'px'); }
   function pointer(e){ var r=hero.getBoundingClientRect();target.x=((e.clientX-r.left)/r.width-.5)*.3;target.y=((e.clientY-r.top)/r.height-.5)*.2;}
   hero.addEventListener('pointermove',pointer,{passive:true});window.addEventListener('scroll',scroll,{passive:true});window.addEventListener('resize',resize);resize();scroll();
   if(toggle)toggle.addEventListener('click',function(){enabled=!enabled;document.body.classList.toggle('scribble-off',!enabled);toggle.setAttribute('aria-pressed',String(enabled));toggle.textContent=enabled?'✦ Traços 3D: ligados':'✦ Traços 3D: desligados';});
   var last=0;
   function animate(now){ frame=requestAnimationFrame(animate);if(!visible||!enabled||document.hidden)return;if(now-last<32)return;last=now;
     object.rotation.y+=.002;object.rotation.x+=(target.y-object.rotation.x)*.015;
     object.position.x+=(target.x-object.position.x)*.018;object.position.y=Math.sin(now*.00027)*.12-parallax*.36;
     renderer.render(scene,camera);
   }animate(0);
   reduced.addEventListener('change',function(){if(reduced.matches){cancelAnimationFrame(frame);renderer.dispose();host.textContent='';if(toggle)toggle.hidden=true;}});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
