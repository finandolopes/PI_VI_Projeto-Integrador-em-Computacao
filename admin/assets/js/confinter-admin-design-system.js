/* Accessible progressive enhancements only — no business logic rewritten. */
(function(){'use strict';document.addEventListener('DOMContentLoaded',function(){
 document.body.classList.add('cf-admin-design');
 if(!document.querySelector('.cf-skip')){var skip=document.createElement('a');skip.href='#cf-main';skip.className='cf-skip';skip.textContent='Ir para o conteúdo principal';document.body.insertBefore(skip,document.body.firstChild);}
 var main=document.querySelector('.page-wrapper main,main,.page-wrapper,.content');if(main&&!main.id)main.id='cf-main';else if(!main){var skip=document.querySelector('.cf-skip');if(skip)skip.remove();}
 document.querySelectorAll('table').forEach(function(t){if(t.closest('.table-responsive,.sd-table-scroll,[role="region"]'))return;var p=t.parentElement;if(!p||p.tagName.toLowerCase()==='form')return;var w=document.createElement('div');w.className='table-responsive';w.setAttribute('role','region');w.setAttribute('aria-label','Tabela com rolagem horizontal');w.tabIndex=0;p.insertBefore(w,t);w.appendChild(t);});
 document.querySelectorAll('img:not([alt])').forEach(function(im){if(im.getAttribute('role')==='presentation')im.alt='';});
 document.querySelectorAll('button:not([type])').forEach(function(b){if(!b.closest('form'))b.type='button';});
 var m=document.querySelector('.page-wrapper main, .page-wrapper .content');if(m&&!m.querySelector('.cf-page-tools')&&!m.closest('.modal')){var tools=document.createElement('div');tools.className='cf-page-tools';var title=document.querySelector('h1,.page-title');var label=title?title.textContent.trim():document.title;var span=document.createElement('span');span.className='cf-context';span.textContent='CONFINTER / '+label.slice(0,100);tools.appendChild(span);var btn=document.createElement('button');btn.type='button';btn.className='cf-print';btn.textContent='Imprimir página';btn.addEventListener('click',function(){window.print();});tools.appendChild(btn);m.insertBefore(tools,m.firstChild);}
});})();
