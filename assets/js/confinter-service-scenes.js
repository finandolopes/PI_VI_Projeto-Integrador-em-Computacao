/* CONFINTER — author-made GLB models and studio environment, integrated with existing carousel */
(function(){
'use strict';
const host=document.getElementById('scribble-three'),hero=document.getElementById('inicio');
if(!host||!hero)return;
const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
const names=['credito','protecao','atendimento'];const labels=['Crédito inteligente','Proteção financeira','Atendimento humano'];
const toggle=document.getElementById('wireframe-toggle');
function fallback(reason){console.warn('CONFINTER 3D: fallback ativado',reason);const s=document.createElement('script');s.src='assets/js/confinter-service-scenes-fallback.js';document.body.appendChild(s);}
if(!window.THREE||!THREE.GLTFLoader||reduced.matches){if(reduced.matches){if(toggle)toggle.hidden=true;return;}fallback('WebGL/GLTFLoader indisponível');return;}
let renderer;try{renderer=new THREE.WebGLRenderer({alpha:true,antialias:true,powerPreference:'high-performance'});}catch(e){fallback(e.message);return;}
renderer.setPixelRatio(Math.min(devicePixelRatio||1,1.65));renderer.setClearColor(0,0);
renderer.outputEncoding=THREE.sRGBEncoding;renderer.toneMapping=THREE.ACESFilmicToneMapping;renderer.toneMappingExposure=1.3;
host.replaceChildren(renderer.domElement);
const scene=new THREE.Scene();const camera=new THREE.PerspectiveCamera(37,1,.1,100);camera.position.set(0,0,8.9);
scene.add(new THREE.HemisphereLight(0xbfeaff,0x1c3f62,1.1));
const key=new THREE.DirectionalLight(0xffffff,2.5);key.position.set(-2,5,8);scene.add(key);
const rim=new THREE.DirectionalLight(0x68d3ff,1.5);rim.position.set(5,-2,-4);scene.add(rim);
const warm=new THREE.PointLight(0xf5c55b,1.1,20);warm.position.set(-3,1,4);scene.add(warm);
const pmrem=new THREE.PMREMGenerator(renderer);pmrem.compileEquirectangularShader();
new THREE.TextureLoader().load('assets/3d/studio-environment.jpg',t=>{t.mapping=THREE.EquirectangularReflectionMapping;scene.environment=pmrem.fromEquirectangular(t).texture;t.dispose();});
const loader=new THREE.GLTFLoader(), groups=new Array(3);let loaded=0,failed=false,idx=0, enabled=true,playing=true,phase=0,last=0;
// Artistic transitions reuse the actual vertices of each GLB; no ornamental stock mesh.
const MAX_PARTICLES=innerWidth<600?320:innerWidth<1000?650:1200;
const sampler=new Array(3), transition={from:0,to:0,start:0,duration:1100,active:false};
const positions=new Float32Array(MAX_PARTICLES*3), colors=new Float32Array(MAX_PARTICLES*3), sizes=new Float32Array(MAX_PARTICLES);
const particleGeometry=new THREE.BufferGeometry();
particleGeometry.setAttribute('position',new THREE.BufferAttribute(positions,3).setUsage(THREE.DynamicDrawUsage));
particleGeometry.setAttribute('color',new THREE.BufferAttribute(colors,3));
const particles=new THREE.Points(particleGeometry,new THREE.PointsMaterial({size:.027,vertexColors:true,transparent:true,opacity:.82,blending:THREE.AdditiveBlending,depthWrite:false,sizeAttenuation:true}));
particles.visible=false;scene.add(particles);
let scribble=null,scrollDepth=0,scrollTarget=0;const reducedMotion=()=>reduced.matches || document.documentElement.classList.contains('reduce-motion') || document.body.classList.contains('reduce-motion');
function sampleModel(group){
 const verts=[];
 group.updateMatrixWorld(true);
 group.traverse(m=>{if(!m.isMesh||!m.geometry?.attributes?.position)return;
   const a=m.geometry.attributes.position, step=Math.max(1,Math.floor(a.count/420));
   for(let i=0;i<a.count;i+=step){const v=new THREE.Vector3().fromBufferAttribute(a,i).applyMatrix4(m.matrixWorld);verts.push(v.x,v.y,v.z)};
 });
 if(!verts.length)return new Float32Array([0,0,0]);return Float32Array.from(verts);
}
function buildScribble(g){
 let mesh=null;g.traverse(obj=>{if(!mesh&&obj.isMesh&&obj.geometry?.attributes?.position)mesh=obj;});
 if(!mesh)return null;
 // Geometry is copied so original GLB cannot be corrupted by artistic jitter.
 let geo=new THREE.WireframeGeometry(mesh.geometry);let arr=geo.attributes.position;
 for(let i=0;i<arr.count;i++){const seed=Math.sin(i*47.121+6.12)*43758.5453;const jitter=((seed-Math.floor(seed))-.5)*.014;arr.setXYZ(i,arr.getX(i)+jitter,arr.getY(i)+jitter*.8,arr.getZ(i)+jitter*.6)}
 geo.computeBoundingSphere();
 const lines=new THREE.LineSegments(geo,new THREE.LineBasicMaterial({color:0xa4e5ff,transparent:true,opacity:.19,depthWrite:false}));
 lines.rotation.copy(mesh.rotation);lines.position.copy(mesh.position);lines.scale.copy(mesh.scale);
 return lines;
}
function particleStart(from,to){
 const a=sampler[from]||new Float32Array([0,0,0]),b=sampler[to]||a;
 for(let i=0;i<MAX_PARTICLES;i++){
  const ai=(i*37%(a.length/3|0))*3,bi=(i*53%(b.length/3|0))*3;
  positions[i*3]=a[ai];positions[i*3+1]=a[ai+1];positions[i*3+2]=a[ai+2];
  const hue=i%5===0?[1,.75,.30]:[.43,.83,1];colors.set(hue,i*3);
 }
 particleGeometry.attributes.position.needsUpdate=true;
 particles.visible=true;transition.from=from;transition.to=to;transition.start=performance.now();transition.active=true;
}
function particleTick(now){
 if(!transition.active)return;
 const t=Math.min(1,(now-transition.start)/transition.duration),fade=Math.sin(Math.PI*t);
 const dst=sampler[transition.to]||sampler[transition.from];if(!dst)return;
 const k=t*t*(3-2*t);
 for(let i=0;i<MAX_PARTICLES;i++){
  const v=(i*53%(dst.length/3|0))*3,j=i*3;
  // deterministic swirl allows a smooth reversible transition, not random flashing
  const a=i*2.399963+phase*.22,r=fade*(.18+((i*13)%19)/65);
  positions[j]+=(dst[v]+Math.cos(a)*r-positions[j])*(.045+.05*k);
  positions[j+1]+=(dst[v+1]+Math.sin(a)*r-positions[j+1])*(.045+.05*k);
  positions[j+2]+=(dst[v+2]+Math.sin(a*.5)*r-positions[j+2])*(.045+.05*k);
 }
 particleGeometry.attributes.position.needsUpdate=true;
 particles.material.opacity=.8*fade;
 if(t>=1){transition.active=false;particles.visible=false;}
}

const captions=document.createElement('span');captions.className='service-object-name';captions.textContent=labels[0];hero.appendChild(captions);
const metal=(color,metalness,roughness,clearcoat)=>new THREE.MeshPhysicalMaterial({color,metalness,roughness,clearcoat,clearcoatRoughness:.18,envMapIntensity:1.5});
const palette=[metal(0x0577bc,.62,.24,.85),metal(0xf2bc48,.85,.19,.95),metal(0x9bdaf2,.63,.24,.7),metal(0x063d70,.5,.26,.8)];
function shadeObject(g){g.traverse(child=>{if(!child.isMesh)return;const n=child.name.toLowerCase();let mi=/chip|check|payment_ring_0|earcup/.test(n)?1:/rim|boom|contact|line|payment_ring_1/.test(n)?2:/inset|pad|capsule/.test(n)?3:0;child.material=palette[mi];child.castShadow=false;child.receiveShadow=false;});}
function applyScene(i){
 const previous=idx;idx=i;hero.dataset.service3d=names[i];captions.textContent=labels[i];
 if(previous!==i && sampler[previous] && sampler[i] && !reducedMotion())particleStart(previous,i);
 groups.forEach((g,j)=>{if(g){g.visible=j===i;
  if(j===i){g.scale.setScalar(reducedMotion()?1:.72);g.position.y=reducedMotion()?0:-.20;g.rotation.y=-.65;}
 }});
 if(scribble){scene.remove(scribble);scribble.geometry.dispose();scribble.material.dispose();scribble=null;}
 if(groups[i]&&!reducedMotion()){scribble=buildScribble(groups[i]);if(scribble){groups[i].add(scribble);scribble.scale.setScalar(1.004);}}
}

function animate(now){requestAnimationFrame(animate);if(!playing||document.hidden||!enabled||now-last<28)return;last=now;phase+=.014;scrollDepth+=(scrollTarget-scrollDepth)*.04;camera.position.y=scrollDepth*.25;camera.lookAt(0,0,0);particleTick(now);
 const a=groups[idx];if(a){let base=(host.clientWidth<430?.75:host.clientWidth<700?.86:1);const sc=a.scale.x+(base-a.scale.x)*.065;a.scale.setScalar(sc);a.rotation.y+=(-.25+Math.sin(phase*.4)*.25-a.rotation.y)*.045;a.rotation.x=Math.sin(phase*.6)*.08;a.position.y+=((Math.sin(phase)*.11+scrollDepth*.12)-a.position.y)*.03;if(scribble)scribble.material.opacity=.13+.08*Math.sin(phase*.65);}renderer.render(scene,camera);
}
function resize(){let w=Math.max(200,host.clientWidth),h=Math.max(180,host.clientHeight);renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix();}resize();window.addEventListener('resize',resize);
new IntersectionObserver(entries=>{playing=entries[0].isIntersecting},{threshold:.01}).observe(hero);
let scrollBusy=false;
window.addEventListener('scroll',()=>{if(scrollBusy)return;scrollBusy=true;requestAnimationFrame(()=>{const r=hero.getBoundingClientRect();scrollTarget=Math.max(-1,Math.min(1,-r.top/Math.max(1,r.height)));scrollBusy=false;});},{passive:true});

document.addEventListener('confinter:slidechange',e=>applyScene(((Number(e.detail.index)||0)%3+3)%3));
if(toggle)toggle.addEventListener('click',()=>{enabled=!enabled;host.style.opacity=enabled?'1':'0';toggle.textContent=enabled?'✦ Objetos 3D: ligados':'✦ Objetos 3D: desligados';toggle.setAttribute('aria-pressed',String(enabled));});
reduced.addEventListener('change',()=>{if(reduced.matches){enabled=false;host.style.display='none';if(toggle)toggle.hidden=true;}else{host.style.display='';enabled=true;if(toggle)toggle.hidden=false;}});
names.forEach((name,i)=>loader.load('assets/3d/'+name+'.glb',gltf=>{const g=gltf.scene;shadeObject(g);scene.add(g);groups[i]=g;g.visible=false;sampler[i]=sampleModel(g);loaded++;if(loaded===3){document.body.classList.add('cf-v7-glb-ready');applyScene(idx);requestAnimationFrame(animate);}},undefined,e=>{if(!failed){failed=true;renderer.dispose();host.replaceChildren();captions.remove();fallback(e?.message||name);}}));
window.Confinter3D={show:i=>applyScene(i)};
})();
