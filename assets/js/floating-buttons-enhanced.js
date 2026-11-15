/**
 * ========================================
 * FLOATING BUTTONS ENHANCED v2.0
 * ========================================
 * Menu flutuante otimizado com rastreamento
 * Data: 07/02/2026
 */

class FloatingButtonsEnhanced {
    constructor(config = {}) {
        this.config = {
            whatsappNumber: config.whatsappNumber || '5511948016298',
            instagramUrl: config.instagramUrl || 'https://www.instagram.com/confintersp',
            emailAddress: config.emailAddress || 'contato@confinter.com.br',
            position: config.position || 'bottom-left', // bottom-left, bottom-right, bottom-center
            showLabel: config.showLabel !== false,
            enableAnalytics: config.enableAnalytics !== false,
            animationStyle: config.animationStyle || 'pulse', // pulse, bounce, none
            ...config
        };

        this.init();
        this.trackUserBehavior();
    }

    init() {
        this.createHTML();
        this.attachEventListeners();
        this.updateContextualMessage();
    }

    createHTML() {
        const buttonsHTML = `
            <div class="floating-buttons-container floating-${this.config.position}" id="floating-buttons-container">
                <!-- WhatsApp Button -->
                <a href="https://wa.me/${this.config.whatsappNumber}?text=${encodeURIComponent(this.getWhatsAppMessage())}" 
                   class="floating-btn floating-btn-whatsapp" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="whatsapp"
                   data-analytics="floating_cta_whatsapp"
                   aria-label="Conversar com especialista via WhatsApp"
                   title="Fale com um especialista">
                    <span class="floating-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421-7.403h-.004a9.87 9.87 0 00-4.255.949c-1.238.5-2.368 1.215-3.285 2.132-1.817 1.817-2.839 4.233-2.839 6.79 0 2.557 1.022 4.973 2.839 6.79 1.817 1.817 4.233 2.839 6.79 2.839 2.557 0 4.973-1.022 6.79-2.839 1.817-1.817 2.839-4.233 2.839-6.79 0-2.557-1.022-4.973-2.839-6.79-1.817-1.817-4.233-2.839-6.79-2.839z"/>
                        </svg>
                    </span>
                    ${this.config.showLabel ? '<span class="floating-label">WhatsApp</span>' : ''}
                </a>

                <!-- Instagram Button -->
                <a href="${this.config.instagramUrl}" 
                   class="floating-btn floating-btn-instagram" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="instagram"
                   data-analytics="floating_cta_instagram"
                   aria-label="Seguir no Instagram"
                   title="Visite nosso Instagram">
                    <span class="floating-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2Zm0 1.8A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95h-8.5Zm4.25 2.95a5.25 5.25 0 1 1 0 10.5 5.25 5.25 0 0 1 0-10.5Zm0 1.8a3.45 3.45 0 1 0 0 6.9 3.45 3.45 0 0 0 0-6.9Zm5.37-2.22a1.23 1.23 0 1 1 0 2.46 1.23 1.23 0 0 1 0-2.46Z"/>
                        </svg>
                    </span>
                    ${this.config.showLabel ? '<span class="floating-label">Instagram</span>' : ''}
                </a>

                <!-- Email Button -->
                <a href="mailto:${this.config.emailAddress}?subject=Contato%20CONFINTER&body=Olá,%0AGostaria%20de%20mais%20informações%20sobre%20empréstimos%20consignados." 
                   class="floating-btn floating-btn-email" 
                   data-platform="email"
                   data-analytics="floating_cta_email"
                   aria-label="Enviar e-mail"
                   title="Enviar e-mail">
                    <span class="floating-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm0 2v.2l9 5.8 9-5.8V7l-9 5.8L3 7.2V7Z"/>
                        </svg>
                    </span>
                    ${this.config.showLabel ? '<span class="floating-label">E-mail</span>' : ''}
                </a>

                <!-- Accessibility: Skip to main content -->
                <a href="#main" 
                   class="floating-btn floating-btn-skip" 
                   aria-label="Ir para conteúdo principal"
                   title="Ir para conteúdo principal">
                    <span class="floating-icon">↓</span>
                </a>
            </div>
        `;

        document.body.insertAdjacentHTML('afterbegin', buttonsHTML);

        // Injetar estilos
        this.injectStyles();
    }

    getWhatsAppMessage() {
        // Mensagem contextual baseada na página
        const messages = {
            'simulator': 'Olá! Gostaria de validar minha simulação de empréstimo. Qual é o próximo passo',
            'requisição': 'Olá! Tenho dúvidas sobre minha requisição de análise de crédito.',
            'dúvidas': 'Olá! Tenho uma dúvida sobre empréstimo consignado. Podem me ajudar',
            'default': 'Olá CONFINTER! Gostaria de informações sobre empréstimo consignado com os menores juros do mercado.'
        };

        // Detectar contexto
        const hash = window.location.hash || '';
        if (hash.includes('analise-credito')) return messages.simulator;
        if (hash.includes('requi')) return messages.requisição;
        if (hash.includes('faq')) return messages.dúvidas;
        return messages.default;
    }

    attachEventListeners() {
        const buttons = document.querySelectorAll('.floating-btn');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const platform = btn.getAttribute('data-platform');
                const analytics = btn.getAttribute('data-analytics');
                
                if (analytics) {
                    this.trackEvent(analytics, { platform });
                }
            });

            // Acessibilidade: Keyboard support
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    btn.click();
                }
            });
        });

        // Habilitar animação ao hover
        document.addEventListener('mousemove', () => this.enableAnimations());
    }

    updateContextualMessage() {
        const whatsappBtn = document.querySelector('.floating-btn-whatsapp');
        if (whatsappBtn) {
            const newMessage = encodeURIComponent(this.getWhatsAppMessage());
            const currentHref = whatsappBtn.getAttribute('href');
            const baseUrl = `https://wa.me/${this.config.whatsappNumber}?text=`;
            whatsappBtn.setAttribute('href', baseUrl + newMessage);
        }
    }

    enableAnimations() {
        const container = document.getElementById('floating-buttons-container');
        if (!container) return;

        if (!container.hasAttribute('data-animated')) {
            container.setAttribute('data-animated', 'true');
            container.classList.add(`animation-${this.config.animationStyle}`);
        }
    }

    trackEvent(eventName, data = {}) {
        if (!this.config.enableAnalytics) return;

        // Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                'event_category': 'floating_buttons',
                'event_label': eventName,
                ...data
            });
        }

        // Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Lead', {
                content_name: eventName,
                ...data
            });
        }

        // Console log para debug
        console.log(`[Floating Buttons Analytics] ${eventName}`, data);
    }

    trackUserBehavior() {
        // Rastrear tempo até primeira interação com botão flutuante
        let firstInteractionTime = null;

        document.addEventListener('click', (e) => {
            if (e.target.closest('.floating-btn')) {
                if (!firstInteractionTime) {
                    firstInteractionTime = Date.now();
                    this.trackEvent('first_floating_button_interaction', {
                        'time_to_first_interaction_ms': firstInteractionTime
                    });
                }
            }
        });

        // Rastrear qual botão é mais clicado
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.floating-btn');
            if (btn) {
                const platform = btn.getAttribute('data-platform');
                this.trackEvent('floating_button_click', { platform });
            }
        });
    }

    injectStyles() {
        const styles = `
<style>
/* ========== FLOATING BUTTONS CONTAINER ========== */

.floating-buttons-container {
    position: fixed;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
}

.floating-bottom-right {
    bottom: 2rem;
    right: 1.5rem;
}

.floating-bottom-left {
    bottom: 2rem;
    left: 1.5rem;
}

.floating-bottom-center {
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
}

/* ========== FLOATING BUTTON STYLES ========== */

.floating-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    position: relative;
    padding: 0;
}

.floating-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.floating-btn:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.7);
    outline-offset: 2px;
}

.floating-btn:active {
    transform: scale(0.95);
}

/* WhatsApp */
.floating-btn-whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #20ba58 100%);
}

.floating-btn-whatsapp:hover {
    background: linear-gradient(135deg, #20ba58 0%, #1da853 100%);
}

/* Instagram */
.floating-btn-instagram {
    background: linear-gradient(135deg, #833AB4 0%, #FD1D1D 50%, #FCAF45 100%);
}

.floating-btn-instagram:hover {
    background: linear-gradient(135deg, #7238a7 0%, #e61116 50%, #f0a93d 100%);
}

/* Email */
.floating-btn-email {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.floating-btn-email:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3d96 100%);
}

/* Skip */
.floating-btn-skip {
    background: rgba(100, 100, 100, 0.8);
    width: 44px;
    height: 44px;
    font-size: 1.2rem;
}

.floating-btn-skip:hover {
    background: rgba(80, 80, 80, 0.9);
}

/* ========== ICONS E LABELS ========== */

.floating-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    color: white;
}

.floating-icon svg {
    width: 100%;
    height: 100%;
}

.floating-label {
    display: none;
    font-size: 0.75rem;
    white-space: nowrap;
    background-color: rgba(0, 0, 0, 0.8);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-left: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.floating-btn:hover .floating-label {
    opacity: 1;
}

/* ========== ANIMAÇÕES ========== */

@keyframes pulse-animation {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes bounce-animation {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.floating-buttons-container[data-animated='true'].animation-pulse .floating-btn-whatsapp {
    animation: pulse-animation 2s infinite;
}

.floating-buttons-container[data-animated='true'].animation-bounce .floating-btn-whatsapp {
    animation: bounce-animation 2s infinite;
}

/* ========== RESPONSIVO ========== */

@media (max-width: 768px) {
    .floating-buttons-container {
        gap: 0.5rem;
        padding: 0.75rem;
    }

    .floating-bottom-right {
        bottom: 1rem;
        right: 1rem;
    }

    .floating-bottom-left {
        bottom: 1rem;
        left: 1rem;
    }

    .floating-btn {
        width: 48px;
        height: 48px;
    }

    .floating-label {
        display: inline;
        margin-left: 0;
        position: absolute;
        bottom: -30px;
        left: 50%;
        transform: translateX(-50%);
        width: max-content;
    }

    .floating-btn:hover .floating-label {
        opacity: 1;
    }
}

@media (max-width: 480px) {
    .floating-buttons-container {
        gap: 0.4rem;
        padding: 0.5rem;
    }

    .floating-btn {
        width: 44px;
        height: 44px;
        font-size: 0.75rem;
    }

    .floating-icon {
        width: 20px;
        height: 20px;
    }
}

/* ========== DARK MODE ========== */

@media (prefers-color-scheme: dark) {
    .floating-btn {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
    }

    .floating-label {
        background-color: rgba(255, 255, 255, 0.9);
        color: #333;
    }
}

/* ========== ACESSIBILIDADE ========== */

@media (prefers-reduced-motion: reduce) {
    .floating-btn {
        transition: none;
    }

    .floating-btn:hover {
        transform: none;
    }

    .floating-buttons-container[data-animated='true'] .floating-btn-whatsapp {
        animation: none;
    }
}

@media (prefers-contrast: more) {
    .floating-btn {
        border: 2px solid rgba(255, 255, 255, 0.5);
    }
}
</style>
        `;

        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

// Inicializar automaticamente
document.addEventListener('DOMContentLoaded', () => {
    const floatingButtons = new FloatingButtonsEnhanced({
        whatsappNumber: '5511948016298',
        instagramUrl: 'https://www.instagram.com/confintersp',
        emailAddress: 'contato@confinter.com.br',
        position: 'bottom-left',
        showLabel: false,
        enableAnalytics: true,
        animationStyle: 'pulse'
    });

    // Exposição global para customização
    window.floatingButtons = floatingButtons;
});
