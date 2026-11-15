(function(){
  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, {once:true});
    else fn();
  }

  function initThreeHero(){
    const host = document.getElementById('hero-webgl');
    if(!host || !window.THREE || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.body.classList.add('webgl-ready');

    const scene = new THREE.Scene();
    const renderer = new THREE.WebGLRenderer({antialias:true, alpha:true});
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.8));
    renderer.setSize(host.clientWidth, host.clientHeight, false);
    host.appendChild(renderer.domElement);

    const camera = new THREE.PerspectiveCamera(35, host.clientWidth / Math.max(host.clientHeight, 1), 0.1, 100);
    camera.position.set(0.2, 0.1, 8.6);

    const ambient = new THREE.AmbientLight(0xffffff, 1.25);
    scene.add(ambient);

    const key = new THREE.DirectionalLight(0xbde8ff, 1.9);
    key.position.set(4, 5, 5);
    scene.add(key);

    const fill = new THREE.PointLight(0xf5c14d, 1.3, 30);
    fill.position.set(-4, 2, 6);
    scene.add(fill);

    const group = new THREE.Group();
    scene.add(group);

    const core = new THREE.Group();
    group.add(core);

    const cardGeo = new THREE.BoxGeometry(4.9, 2.9, 0.22, 2, 2, 2);
    const cardMat = new THREE.MeshPhysicalMaterial({
      color: 0x0c6ea9,
      metalness: 0.2,
      roughness: 0.35,
      clearcoat: 1,
      clearcoatRoughness: 0.18,
      transmission: 0.06,
      thickness: 0.4,
      sheen: 0.6,
      sheenColor: new THREE.Color(0xffffff),
      emissive: new THREE.Color(0x05233f),
      emissiveIntensity: 0.18
    });
    const card = new THREE.Mesh(cardGeo, cardMat);
    card.rotation.set(-0.25, -0.45, 0.08);
    core.add(card);

    const stripe = new THREE.Mesh(
      new THREE.BoxGeometry(3.8, 0.36, 0.03),
      new THREE.MeshStandardMaterial({color:0x94d9ff, metalness:0.1, roughness:0.2, transparent:true, opacity:0.75})
    );
    stripe.position.set(0.1, 0.68, 0.13);
    stripe.rotation.copy(card.rotation);
    core.add(stripe);

    const chip = new THREE.Mesh(
      new THREE.BoxGeometry(0.78, 0.58, 0.04),
      new THREE.MeshStandardMaterial({color:0xf3c560, metalness:0.75, roughness:0.3})
    );
    chip.position.set(-1.25, 0.15, 0.14);
    chip.rotation.copy(card.rotation);
    core.add(chip);

    const logoPlate = new THREE.Mesh(
      new THREE.PlaneGeometry(1.9, 0.34),
      new THREE.MeshBasicMaterial({color:0xdff7ff, transparent:true, opacity:0.95})
    );
    logoPlate.position.set(-0.5, -0.78, 0.14);
    logoPlate.rotation.copy(card.rotation);
    core.add(logoPlate);

    const coinGeo = new THREE.CylinderGeometry(0.72, 0.72, 0.18, 42);
    const coinMat = new THREE.MeshStandardMaterial({color:0xf1b73f, metalness:0.78, roughness:0.25, emissive:0x7a4c0a, emissiveIntensity:0.18});
    const coin = new THREE.Mesh(coinGeo, coinMat);
    coin.position.set(2.45, 1.35, 0.7);
    coin.rotation.set(0.25, 0.35, 1.55);
    group.add(coin);

    const ringMatA = new THREE.MeshBasicMaterial({color:0x73ddff, transparent:true, opacity:0.26, side:THREE.DoubleSide});
    const ringA = new THREE.Mesh(new THREE.TorusGeometry(3.2, 0.03, 18, 120), ringMatA);
    ringA.rotation.set(1.25, 0.2, 0.25);
    group.add(ringA);

    const ringMatB = new THREE.MeshBasicMaterial({color:0xf3c560, transparent:true, opacity:0.22, side:THREE.DoubleSide});
    const ringB = new THREE.Mesh(new THREE.TorusGeometry(2.55, 0.025, 18, 120), ringMatB);
    ringB.rotation.set(0.42, 0.9, -0.35);
    group.add(ringB);

    const ringC = new THREE.Mesh(new THREE.TorusGeometry(3.7, 0.025, 18, 140), new THREE.MeshBasicMaterial({color:0xa7e9ff, transparent:true, opacity:0.12, side:THREE.DoubleSide}));
    ringC.rotation.set(0.82, -0.65, 0.5);
    group.add(ringC);

    const particles = new THREE.Group();
    group.add(particles);
    const particleGeo = new THREE.SphereGeometry(0.04, 10, 10);
    for(let i = 0; i < 40; i++){
      const m = new THREE.Mesh(particleGeo, new THREE.MeshBasicMaterial({color: i % 3 === 0 ? 0xf5c14d : 0xbde8ff, transparent:true, opacity:0.7}));
      const radius = 2.1 + Math.random() * 2.6;
      const angle = Math.random() * Math.PI * 2;
      const rise = (Math.random() - 0.5) * 2.3;
      m.position.set(Math.cos(angle) * radius, rise, Math.sin(angle) * radius * 0.28);
      particles.add(m);
    }

    const target = {x:0, y:0};
    const current = {x:0, y:0};
    const hero = document.getElementById('inicio') || host;
    hero.addEventListener('pointermove', (ev) => {
      const rect = hero.getBoundingClientRect();
      target.x = ((ev.clientX - rect.left) / rect.width - 0.5) * 0.8;
      target.y = ((ev.clientY - rect.top) / rect.height - 0.5) * 0.7;
    });
    hero.addEventListener('pointerleave', () => { target.x = 0; target.y = 0; });

    function resize(){
      const w = host.clientWidth || host.offsetWidth || 600;
      const h = host.clientHeight || host.offsetHeight || 400;
      renderer.setSize(w, h, false);
      camera.aspect = w / Math.max(h, 1);
      camera.updateProjectionMatrix();
    }
    window.addEventListener('resize', resize);
    resize();

    if(window.gsap){
      gsap.fromTo(group.position, {y:0.45, x:0.55}, {y:-0.1, x:0, duration:1.2, ease:'power3.out'});
      gsap.fromTo(group.scale, {x:0.8, y:0.8, z:0.8}, {x:1, y:1, z:1, duration:1.1, ease:'power2.out'});
    }

    let stopped = false;
    document.addEventListener('visibilitychange', () => { stopped = document.hidden; });
    const clock = new THREE.Clock();
    function tick(){
      if(stopped) { requestAnimationFrame(tick); return; }
      const t = clock.getElapsedTime();
      current.x += (target.x - current.x) * 0.05;
      current.y += (target.y - current.y) * 0.05;

      group.rotation.y = current.x * 0.55 + Math.sin(t * 0.22) * 0.08;
      group.rotation.x = -current.y * 0.35 + Math.cos(t * 0.28) * 0.04;
      group.position.y = Math.sin(t * 1.1) * 0.12;

      ringA.rotation.z += 0.0048;
      ringB.rotation.z -= 0.0038;
      ringC.rotation.z += 0.0028;
      coin.rotation.y += 0.02;
      coin.position.y = 1.35 + Math.sin(t * 1.8) * 0.16;
      card.position.y = Math.sin(t * 1.2) * 0.08;
      particles.children.forEach((p, idx) => {
        const speed = 0.25 + (idx % 7) * 0.03;
        p.position.y += Math.sin(t * speed + idx) * 0.0025;
      });

      renderer.render(scene, camera);
      requestAnimationFrame(tick);
    }
    tick();
  }

  function initGsapInteractions(){
    if(!window.gsap) return;
    if(window.ScrollTrigger){ gsap.registerPlugin(ScrollTrigger); }

    gsap.utils.toArray('.section').forEach((section, index) => {
      if(!window.ScrollTrigger) return;
      gsap.fromTo(section, {backgroundPosition:'50% 0%'}, {
        backgroundPosition:'50% 100%',
        ease:'none',
        scrollTrigger:{trigger:section, scrub:true, start:'top bottom', end:'bottom top'}
      });
      if(index > 0){
        gsap.from(section.querySelectorAll('h2, .lead, .tile, .form-panel, .map-card, .faq-item, .stage-flow button'), {
          y: 40,
          opacity: 0,
          duration: 0.9,
          stagger: 0.08,
          ease: 'power3.out',
          scrollTrigger: {trigger: section, start: 'top 70%'}
        });
      }
    });

    const heroCopy = document.querySelector('.hero-copy');
    const scene = document.getElementById('scene');
    if(heroCopy && scene){
      gsap.from(heroCopy, {x:-40, opacity:0, duration:0.9, ease:'power3.out'});
      gsap.from(scene, {x:55, opacity:0, duration:1.1, ease:'power3.out', delay:0.08});
      if(window.ScrollTrigger){
        gsap.to(scene, {
          yPercent: 12,
          rotateZ: -1.2,
          ease: 'none',
          scrollTrigger: {trigger: '.hero', scrub: true, start: 'top top', end: 'bottom top'}
        });
      }
    }

    gsap.utils.toArray('.nav a, .hero-actions .pill, .services .tile, .values .tile, .stage-flow button').forEach((el) => {
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        gsap.to(el, {rotationY:x * 10, rotationX:-y * 8, z:16, duration:0.35, ease:'power2.out'});
      });
      el.addEventListener('pointerleave', () => {
        gsap.to(el, {rotationY:0, rotationX:0, z:0, duration:0.45, ease:'power3.out'});
      });
    });
  }

  ready(function(){
    initThreeHero();
    initGsapInteractions();
  });
})();
