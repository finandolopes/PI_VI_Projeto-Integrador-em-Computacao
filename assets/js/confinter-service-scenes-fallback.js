/* CONFINTER — service-specific 3D art direction. No backend side effects. */
(function () {
  'use strict';
  const hero=document.getElementById('inicio'), host=document.getElementById('scribble-three');
  if(!hero || !host) return;
  const toggle=document.getElementById('wireframe-toggle');
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  const names=['credito','protecao','atendimento'];
  const labels=['Crédito inteligente','Proteção financeira','Atendimento humano'];
  const R=window.THREE;
  if(!R || reduced.matches){if(toggle)toggle.hidden=true;return;}
  let renderer;
  try {renderer=new R.WebGLRenderer({alpha:true,antialias:true,powerPreference:'low-power'});}catch(e){if(toggle)toggle.hidden=true;return;}
  renderer.setPixelRatio(Math.min(devicePixelRatio||1,1.6));
  renderer.setClearColor(0,0);host.appendChild(renderer.domElement);
  const scene=new R.Scene();const camera=new R.PerspectiveCamera(40,1,.1,100);camera.position.z=8.8;
  scene.add(new R.AmbientLight(0xffffff,.9));
  const light=new R.DirectionalLight(0xc7eeff,1.5);light.position.set(2,5,6);scene.add(light);
  const gold=new R.PointLight(0xffce70,1.2,24);gold.position.set(-4,2,4);scene.add(gold);
  const material=(c,m=.2)=>new R.MeshStandardMaterial({color:c,metalness:m,roughness:.3,transparent:true,opacity:.94,side:R.DoubleSide});
  const blue=material(0x0b77b8,.45),navy=material(0x064270,.3),silver=material(0xb7e6ff,.55),yellow=material(0xf2bc48,.6),dark=material(0x103c64,.3);
  const wireMat=new R.LineBasicMaterial({color:0xc8f3ff,transparent:true,opacity:.27,depthWrite:false});
  const groups=[];
  const add=(g,geo,mat,pos=[0,0,0],rot=[0,0,0])=>{let m=new R.Mesh(geo,mat);m.position.set(...pos);m.rotation.set(...rot);g.add(m);return m};
  const makeWire=(g,m)=>{const geo=new R.WireframeGeometry(m.geometry);const line=new R.LineSegments(geo,wireMat.clone());line.position.copy(m.position);line.rotation.copy(m.rotation);line.scale.copy(m.scale);g.add(line);};
  const create=()=>{const g=new R.Group();scene.add(g);groups.push(g);return g};
  // Slide 1 — financial card with embossed chip and paired payment circles.
  {const g=create();const body=add(g,new R.BoxGeometry(4.15,2.5,.25,4,3,1),blue,[0,0,0],[.08,-.3,-.08]);makeWire(g,body);
    add(g,new R.BoxGeometry(.71,.48,.055),yellow,[-1.15,.12,.22]);
    for(let i=0;i<4;i++)add(g,new R.BoxGeometry(.02,.43,.025),navy,[-1.38+i*.15,.12,.27]);
    add(g,new R.BoxGeometry(2.8,.075,.04),silver,[.16,-.73,.21]);
    add(g,new R.TorusGeometry(.48,.07,12,56),yellow,[1.17,.68,.2]);
    add(g,new R.TorusGeometry(.48,.07,12,56),silver,[1.58,.68,.19]);}
  // Slide 2 — protective shield (custom extruded outline + security ring).
  {const g=create();const s=new R.Shape();s.moveTo(0,1.73);s.bezierCurveTo(.73,1.4,1.42,1.35,1.5,1.2);s.lineTo(1.35,-.33);s.bezierCurveTo(1.2,-1.05,.6,-1.57,0,-1.92);s.bezierCurveTo(-.6,-1.57,-1.2,-1.05,-1.35,-.33);s.lineTo(-1.5,1.2);s.bezierCurveTo(-1.42,1.35,-.73,1.4,0,1.73);
    const shield=add(g,new R.ExtrudeGeometry(s,{depth:.27,bevelEnabled:true,bevelThickness:.12,bevelSize:.10,bevelSegments:3,steps:1}),blue);shield.position.z=-.15;makeWire(g,shield);
    const ring=add(g,new R.TorusGeometry(2.13,.025,8,88),silver,[0,0,-.35],[.3,.3,.18]);makeWire(g,ring);
    const a=add(g,new R.BoxGeometry(.19,.96,.15),yellow,[-.35,-.06,.46],[0,0,-.64]);const b=add(g,new R.BoxGeometry(.19,1.67,.15),yellow,[.34,.17,.46],[0,0,.68]);}
  // Slide 3 — customer service headset: torus arch, pads and microphone boom.
  {const g=create();const arc=add(g,new R.TorusGeometry(1.35,.22,18,78,Math.PI),blue,[0,.03,0],[0,0,0]);makeWire(g,arc);
    const left=add(g,new R.BoxGeometry(.55,1.04,.52,2,3,2),yellow,[-1.37,-.58,.12],[0,0,-.07]);makeWire(g,left);
    const right=add(g,new R.BoxGeometry(.55,1.04,.52,2,3,2),yellow,[1.37,-.58,.12],[0,0,.07]);makeWire(g,right);
    const boom=add(g,new R.TorusGeometry(1.04,.067,10,50,Math.PI/2.35),silver,[.18,-.55,.18],[0,0,-1.45]);
    add(g,new R.SphereGeometry(.19,16,12),navy,[-.36,-1.32,.25]);
    add(g,new R.BoxGeometry(.9,.10,.09),silver,[0,1.26,-.10]);}
  let index=0, enabled=true, visible=true, last=0, pointerX=0,pointerY=0, phase=0;
  const caption=document.createElement('div');caption.className='service-object-name';caption.textContent=labels[0];hero.appendChild(caption);
  function choose(next){next=((Number(next)||0)%groups.length+groups.length)%groups.length;index=next;
    hero.dataset.service3d=names[next];caption.textContent=labels[next];
    groups.forEach((g,i)=>{g.visible=i===index;if(i===index){g.scale.setScalar(.75);g.rotation.y=-.75;g.position.y=-.3;}});
  }
  choose(0);
  function resize(){const w=Math.max(host.clientWidth,180),h=Math.max(host.clientHeight,180);renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix();const scale=w<450?.72:w<650?.84:1;groups.forEach(g=>g.userData.baseScale=scale)}
  resize();window.addEventListener('resize',resize);
  hero.addEventListener('pointermove',(e)=>{let r=hero.getBoundingClientRect();pointerX=((e.clientX-r.left)/r.width-.5)*.4;pointerY=((e.clientY-r.top)/r.height-.5)*.2;},{passive:true});
  hero.addEventListener('pointerleave',()=>{pointerX=0;pointerY=0});
  const observer=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting},{threshold:.03});observer.observe(hero);
  let offFrame=0;
  function draw(now){offFrame=requestAnimationFrame(draw);if(document.hidden||!visible||!enabled||now-last<30)return;last=now;
    phase+=.016;let active=groups[index],goal=active.userData.baseScale||1;
    active.scale.x+=(goal-active.scale.x)*.07;active.scale.y+=(goal-active.scale.y)*.07;active.scale.z+=(goal-active.scale.z)*.07;
    active.rotation.y+=(-.14+Math.sin(phase*.32)*.28+pointerX-active.rotation.y)*.035;
    active.rotation.x+=(pointerY-active.rotation.x)*.025;active.position.y+=(Math.sin(phase)*.13-active.position.y)*.025;
    renderer.render(scene,camera);
  }draw(0);
  if(toggle)toggle.addEventListener('click',()=>{enabled=!enabled;host.style.opacity=enabled?'1':'0';toggle.setAttribute('aria-pressed',String(enabled));toggle.textContent=enabled?'✦ Objetos 3D: ligados':'✦ Objetos 3D: desligados';});
  reduced.addEventListener('change',()=>{if(reduced.matches){cancelAnimationFrame(offFrame);renderer.dispose();host.replaceChildren();caption.remove();if(toggle)toggle.hidden=true;}});
  document.addEventListener('confinter:slidechange',e=>choose(e.detail.index));
  window.Confinter3D={show:choose};
})();
