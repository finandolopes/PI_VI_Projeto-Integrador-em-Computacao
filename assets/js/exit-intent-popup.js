/**
 * EXIT-INTENT POPUP
 * Popup para capturar leads que estão deixando a página
 * ROI: +15% leads
 * Arquivo: assets/js/exit-intent-popup.js
 */

class ExitIntentPopup {
    constructor(options = {}) {
        this.enabled = options.enabled !== false;
        this.triggers = options.triggers || {};
        this.messages = options.messages || this.getDefaultMessages();
        this.displayDelay = options.displayDelay || 0;
        this.cooldown = options.cooldown || 86400000; // 24h
        this.targetUrl = options.targetUrl || window.location.href;
        this.shown = false;
        this.localStorageKey = 'exitIntentShown';
        
        if (this.enabled) {
            this.init();
        }
    }

    /**
     * Inicializar exit-intent
     */
    init() {
        this.addStyles();
        this.attachListeners();
    }

    /**
     * Anexar listeners
     */
    attachListeners() {
        // Detectar quando mouse sai do documento (mobile: touch end)
        document.addEventListener('mouseleave', (e) => {
            if (e.clientY <= 0) {
                this.handleExitIntent();
            }
        });

        // Detectar tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.shown) {
                setTimeout(() => {
                    if (!this.shown) {
                        this.handleExitIntent();
                    }
                }, 5000);
            }
        });

        // Mobile: detectar swipe para cima
        this.detectMobileExit();
    }

    /**
     * Lidar com exit intent
     */
    handleExitIntent() {
        // Verificar cooldown
        const lastShown = localStorage.getItem(this.localStorageKey);
        if (lastShown && (Date.now() - parseInt(lastShown) < this.cooldown)) {
            return;
        }

        // Delay para evitar múltiplos popups
        setTimeout(() => {
            if (!this.shown) {
                this.showPopup();
            }
        }, this.displayDelay);
    }

    /**
     * Mostrar popup
     */
    showPopup() {
        this.shown = true;
        localStorage.setItem(this.localStorageKey, Date.now().toString());

        const popup = this.createPopup();
        document.body.appendChild(popup);

        // Animação de entrada
        setTimeout(() => {
            popup.classList.add('active');
        }, 10);
    }

    /**
     * Criar popup
     */
    createPopup() {
        const message = this.getRandomMessage();

        const overlay = document.createElement('div');
        overlay.className = 'exit-intent-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;

        const popup = document.createElement('div');
        popup.className = 'exit-intent-popup';
        popup.style.cssText = `
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 10001;
        `;

        popup.innerHTML = `
            <div style="padding: 0;">
                <!-- Close button -->
                <button class="exit-popup-close" style="
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: color 0.2s ease;
                " onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'">×</button>

                <!-- Header -->
                <div style="
                    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                    color: white;
                    padding: 32px;
                    text-align: center;
                    border-radius: 8px 8px 0 0;
                ">
                    <div style="font-size: 48px; margin-bottom: 12px;">
                        ${message.icon}
                    </div>
                    <h2 style="margin: 0; font-size: 24px; margin-bottom: 8px;">
                        ${message.title}
                    </h2>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">
                        ${message.subtitle}
                    </p>
                </div>

                <!-- Content -->
                <div style="padding: 32px;">
                    <p style="color: #666; margin-bottom: 24px; line-height: 1.6;">
                        ${message.content}
                    </p>

                    <!-- Incentive -->
                    ${message.incentive  `
                        <div style="
                            background: #f8f9fa;
                            border-left: 4px solid #28a745;
                            padding: 12px;
                            margin-bottom: 24px;
                            border-radius: 4px;
                            font-size: 13px;
                            color: #333;
                        ">
                            <strong>✨ ${message.incentive}</strong>
                        </div>
                    ` : ''}

                    <!-- CTA Buttons -->
                    <div style="display: flex; gap: 12px;">
                        <button class="exit-popup-stay" style="
                            flex: 1;
                            padding: 12px;
                            background: #e9ecef;
                            color: #333;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: 600;
                            transition: all 0.2s ease;
                        " onmouseover="this.style.background='#dee2e6'" onmouseout="this.style.background='#e9ecef'">
                            Não, obrigado
                        </button>
                        <button class="exit-popup-cta" style="
                            flex: 1;
                            padding: 12px;
                            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: 600;
                            transition: all 0.2s ease;
                        " onmouseover="this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.3)'" onmouseout="this.style.boxShadow='none'">
                            ${message.cta_button}
                        </button>
                    </div>
                </div>
            </div>
        `;

        overlay.appendChild(popup);

        // Event listeners
        overlay.querySelector('.exit-popup-close').addEventListener('click', () => {
            this.closePopup(overlay);
        });

        overlay.querySelector('.exit-popup-stay').addEventListener('click', () => {
            this.closePopup(overlay);
        });

        overlay.querySelector('.exit-popup-cta').addEventListener('click', () => {
            this.handleCTA(message);
            this.closePopup(overlay);
        });

        // Fechar ao clicar no overlay
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.closePopup(overlay);
            }
        });

        // Ativar overlay
        overlay.style.opacity = '1';

        return overlay;
    }

    /**
     * Fechar popup
     */
    closePopup(popup) {
        popup.style.opacity = '0';
        setTimeout(() => {
            popup.remove();
            this.shown = false;
        }, 300);
    }

    /**
     * Lidar com CTA
     */
    handleCTA(message) {
        if (message.cta_action === 'scroll_to_form') {
            const form = document.querySelector(message.cta_target || '#requisicaoForm');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else if (message.cta_action === 'open_modal') {
            // Triggar modal do bootstrap se disponível
            const modal = document.querySelector(message.cta_target);
            if (modal && window.bootstrap) {
                new window.bootstrap.Modal(modal).show();
            }
        } else if (message.cta_action === 'navigate') {
            window.location.href = message.cta_target || '/';
        } else if (message.cta_action === 'play_video') {
            this.playVideo(message.cta_target);
        }
    }

    /**
     * Reproduzir vídeo
     */
    playVideo(videoId) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10002;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;

        modal.innerHTML = `
            <div style="position: relative; width: 100%; max-width: 800px;">
                <button onclick="this.parentElement.parentElement.remove()" style="
                    position: absolute;
                    top: -40px;
                    right: 0;
                    background: none;
                    border: none;
                    color: white;
                    font-size: 32px;
                    cursor: pointer;
                ">×</button>
                <iframe width="100%" height="450" src="https://www.youtube.com/embed/${videoId}" 
                    frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen style="border-radius: 8px;"></iframe>
            </div>
        `;

        document.body.appendChild(modal);
    }

    /**
     * Detectar exit no mobile
     */
    detectMobileExit() {
        let touchStartY = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        });

        document.addEventListener('touchend', (e) => {
            const touchEndY = e.changedTouches[0].clientY;
            if (touchStartY - touchEndY > 50) { // Swipe up
                this.handleExitIntent();
            }
        });
    }

    /**
     * Obter mensagem padrão
     */
    getDefaultMessages() {
        return [
            {
                icon: '⏸️',
                title: 'Espera um pouco!',
                subtitle: 'Antes você sair...',
                content: 'Vamos oferecer uma análise de crédito gratuita e sem compromisso para sua empresa.',
                incentive: '✅ Análise em até 24 horas | 💰 Sem taxa de consulta',
                cta_button: '✓ Quero Minha Análise',
                cta_action: 'scroll_to_form',
                cta_target: '#requisicaoForm'
            },
            {
                icon: '💡',
                title: 'Descubra as Melhores Linhas de Crédito',
                subtitle: 'Comparamos mais de 50 opções para você',
                content: 'Receba uma proposta personalizada de crédito com as melhores taxas do mercado.',
                incentive: '🎯 Taxa até 2% a.m. | ⚡ Análise automática',
                cta_button: '🚀 Solicitar Análise',
                cta_action: 'scroll_to_form',
                cta_target: '#requisicaoForm'
            },
            {
                icon: '🎁',
                title: 'Oferta Especial para Hoje',
                subtitle: 'Válido apenas para quem sair desta página',
                content: 'Ganhe um mês de consultoria financeira GRÁTIS ao enviar sua requisição agora.',
                incentive: '⭐ Oferta limitada | 🔒 100% confidencial',
                cta_button: '✨ Aproveitar Oferta',
                cta_action: 'scroll_to_form',
                cta_target: '#requisicaoForm'
            },
            {
                icon: '📹',
                title: 'Veja Como Nossos Clientes Conseguiram',
                subtitle: 'Assista a um caso de sucesso (3 minutos)',
                content: 'Empresas como a sua conseguiram até R$ 500 mil em crédito em 48 horas. Quer saber como',
                incentive: '📊 Estratégia comprovada | ✅ Sem jargão técnico',
                cta_button: '▶️ Assistir Vídeo',
                cta_action: 'play_video',
                cta_target: 'dQw4w9WgXcQ' // Substitua por ID real
            }
        ];
    }

    /**
     * Obter mensagem aleatória
     */
    getRandomMessage() {
        return this.messages[Math.floor(Math.random() * this.messages.length)];
    }

    /**
     * Adicionar CSS
     */
    addStyles() {
        const style = document.createElement('style');
        style.innerHTML = `
            .exit-intent-overlay {
                animation: fadeIn 0.3s ease;
            }

            .exit-intent-popup {
                animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            .exit-intent-overlay.active .exit-intent-popup {
                transform: scale(1) translateY(0) !important;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes popIn {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            @media (max-width: 480px) {
                .exit-intent-popup {
                    max-width: 100% !important;
                    width: 95% !important;
                }

                .exit-intent-popup > div {
                    max-width: 100% !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Resetar cooldown
     */
    resetCooldown() {
        localStorage.removeItem(this.localStorageKey);
    }

    /**
     * Forçar mostrar popup (para testes)
     */
    forceShow() {
        this.shown = false;
        this.showPopup();
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('[data-exit-intent]')) {
        window.exitIntentPopup = new ExitIntentPopup({
            enabled: true,
            cooldown: 86400000, // 24 horas
            displayDelay: 1000
        });
    }
});

// Exportar para uso externo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ExitIntentPopup;
}
