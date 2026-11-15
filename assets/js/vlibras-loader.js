/**
 * VLibras Loader - Acessibilidade em Libras Brasileira
 * Carrega o plugin VLibras de forma assíncrona
 */

(function loadVLibras() {
    'use strict';
    
    const script = document.createElement('script');
    script.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
    script.defer = true;
    script.async = true;
    
    script.onload = function () {
        if (typeof VLibras !== 'undefined') {
            new VLibras.Widget();
        }
    };
    
    script.onerror = function () {
        console.warn('VLibras plugin failed to load. Accessibility features may be limited.');
    };
    
    document.body.appendChild(script);
})();
