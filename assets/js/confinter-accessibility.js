/* Menu acessível CONFINTER: independente de frameworks e do banco de dados. */
(function(){'use strict';
 if(window.__cfA11yInstalled)return;window.__cfA11yInstalled=true;
 const d=document,root=d.documentElement,storageKey='confinter-accessibility-v2';
 const initial={font:0,contrast:false,spacing:false,links:false,pause:false,focus:false};
 let settings={...initial};try{const saved=JSON.parse(localStorage.getItem(storageKey)||'{}');for(const k of Object.keys(initial)){if(typeof saved[k]===typeof initial[k])settings[k]=saved[k]}}catch(_e){}
 const escapeHtml=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function apply(){['cf-large-text','cf-xlarge-text','cf-high-contrast','cf-letter-space','cf-links-highlight','cf-pause-motion','cf-focus-visible'].forEach(c=>root.classList.remove(c));
 if(settings.font===1)root.classList.add('cf-large-text');if(settings.font===2)root.classList.add('cf-xlarge-text');
 if(settings.contrast)root.classList.add('cf-high-contrast');if(settings.spacing)root.classList.add('cf-letter-space');
 if(settings.links)root.classList.add('cf-links-highlight');if(settings.pause)root.classList.add('cf-pause-motion');
 if(settings.focus)root.classList.add('cf-focus-visible');
 d.querySelectorAll('[data-cf-a11y]').forEach(b=>{const key=b.dataset.cfA11y;b.setAttribute('aria-pressed',key==='font-up'?String(settings.font>0):key==='font-down'?String(settings.font===0):String(!!settings[key]))});
 const state=d.getElementById('cf-a11y-motion-state');if(state)state.textContent=settings.pause?'Animações pausadas':'Animações ativas';
 try{localStorage.setItem(storageKey,JSON.stringify(settings))}catch(_e){}
 d.dispatchEvent(new CustomEvent('confinter:motionchange',{detail:{paused:settings.pause}}));}
 function ready(){if(!d.body)return;
 const content=d.querySelector('main')||d.querySelector('#main')||d.querySelector('.hero')||d.querySelector('section')||d.body;
 if(!content.id)content.id='conteudo-principal';if(!content.hasAttribute('tabindex'))content.setAttribute('tabindex','-1');
 const skip=d.createElement('a');skip.className='skip-confinter';skip.href='#'+content.id;skip.textContent='Pular para o conteúdo principal';d.body.insertBefore(skip,d.body.firstChild);
 const el=d.createElement('div');el.id='cf-a11y-root';el.innerHTML=`<button id="cf-a11y-launcher" type="button" aria-controls="cf-a11y-panel" aria-haspopup="dialog" aria-expanded="false" aria-label="Abrir menu de acessibilidade"><span aria-hidden="true" style="font-size:23px">♿</span><span>Acessibilidade</span></button>
 <section id="cf-a11y-panel" role="dialog" aria-modal="false" aria-label="Opções de acessibilidade" hidden>
 <div class="cf-a11y-head"><h2>Acessibilidade</h2><button type="button" id="cf-a11y-close" aria-label="Fechar menu">✕</button></div>
 <p>Personalize a navegação e reduza efeitos 3D quando desejar.</p>
 <div class="cf-a11y-grid">
 <button type="button" data-cf-a11y="font-up">A+ Aumentar fonte</button>
 <button type="button" data-cf-a11y="font-down">A− Reduzir fonte</button>
 <button type="button" data-cf-a11y="contrast" aria-pressed="false">Alto contraste</button>
 <button type="button" data-cf-a11y="spacing" aria-pressed="false">Espaçar texto</button>
 <button type="button" data-cf-a11y="links" aria-pressed="false">Destacar links</button>
 <button type="button" data-cf-a11y="focus" aria-pressed="false">Destacar foco</button>
 <button type="button" data-cf-a11y="pause" aria-pressed="false">Pausar efeitos 3D</button>
 <button type="button" id="cf-a11y-reset">Restaurar padrão</button>
 </div><div class="cf-a11y-note"><p id="cf-a11y-motion-state" role="status">Animações ativas</p><p>Use Tab para navegar e Esc para fechar. O navegador também permite ampliar a página com Ctrl + +.</p><div id="cf-a11y-vlibras-note" aria-live="polite"></div></div>
 </section>`;
 d.body.appendChild(el);const launcher=d.getElementById('cf-a11y-launcher'),panel=d.getElementById('cf-a11y-panel');
 function toggle(open){panel.hidden=!open;launcher.setAttribute('aria-expanded',String(open));if(open)d.getElementById('cf-a11y-close').focus();else launcher.focus()}
 launcher.addEventListener('click',()=>toggle(panel.hidden));d.getElementById('cf-a11y-close').addEventListener('click',()=>toggle(false));
 d.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){e.preventDefault();toggle(false)}});
 d.addEventListener('pointerdown',e=>{if(!panel.hidden&&!el.contains(e.target)){panel.hidden=true;launcher.setAttribute('aria-expanded','false')}});
 el.querySelectorAll('[data-cf-a11y]').forEach(b=>b.addEventListener('click',()=>{const k=b.dataset.cfA11y;if(k==='font-up')settings.font=Math.min(2,settings.font+1);else if(k==='font-down')settings.font=Math.max(0,settings.font-1);else settings[k]=!settings[k];apply()}));
 d.getElementById('cf-a11y-reset').addEventListener('click',()=>{settings={...initial};apply()});
 apply();
 }
 if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',ready);else ready();
})();
