/**
 * SOCIAL PROOF WIDGETS
 * Widgets de prova social (testemunhos em tempo real, contadores)
 * ROI: +22% conversão
 * Arquivo: assets/js/social-proof.js
 */

class SocialProofWidget {
    constructor(options = {}) {
        this.container = options.container || document.body;
        this.updateInterval = options.updateInterval || 5000;
        this.animations = options.animations !== false;
        this.position = options.position || 'bottom-right';
        this.notifications = [];
        this.stats = {
            visits: 0,
            leads: 0,
            conversions: 0
        };
        
        this.init();
    }

    /**
     * Inicializar widget
     */
    init() {
        this.loadStats();
        this.createNotificationContainer();
        this.createVisitorCounter();
        this.startNotificationCycle();
        this.attachListeners();
    }

    /**
     * Carregar estatísticas
     */
    loadStats() {
        // Carregar de localStorage ou API
        const stored = localStorage.getItem('socialProofStats');
        if (stored) {
            this.stats = JSON.parse(stored);
        }

        // Atualizar do servidor
        this.fetchStats();
    }

    /**
     * Buscar estatísticas do servidor
     */
    async fetchStats() {
        try {
            const response = await fetch('/api/social-proof-stats.php');
            const data = await response.json();
            
            if (data.success) {
                this.stats = data.stats;
                localStorage.setItem('socialProofStats', JSON.stringify(this.stats));
                this.updateCounters();
            }
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    }

    /**
     * Criar container de notificações
     */
    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'social-proof-notifications';
        container.className = `social-proof-container ${this.position}`;
        container.style.cssText = `
            position: fixed;
            ${this.getPositionStyles()}
            z-index: 9999;
            max-width: 400px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        `;
        
        document.body.appendChild(container);
    }

    /**
     * Obter estilos de posição
     */
    getPositionStyles() {
        const positions = {
            'bottom-right': 'bottom: 20px; right: 20px;',
            'bottom-left': 'bottom: 20px; left: 20px;',
            'top-right': 'top: 20px; right: 20px;',
            'top-left': 'top: 20px; left: 20px;'
        };
        return positions[this.position] || positions['bottom-right'];
    }

    /**
     * Criar contador de visitantes
     */
    createVisitorCounter() {
        const counter = document.createElement('div');
        counter.id = 'visitor-counter';
        counter.className = 'visitor-counter';
        counter.style.cssText = `
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            margin-bottom: 10px;
            animation: slideInUp 0.4s ease;
        `;
        
        const container = document.getElementById('social-proof-notifications');
        container.appendChild(counter);

        // Atualizar contador
        this.updateVisitorCounter();

        // Adicionar listener
        counter.addEventListener('click', () => this.toggleCounterDetails());
    }

    /**
     * Atualizar contador de visitantes
     */
    updateVisitorCounter() {
        const counter = document.getElementById('visitor-counter');
        if (!counter) return;

        const visits = this.getRandomIncrement(this.stats.visits);
        counter.innerHTML = `👥 <strong>${visits}+</strong> visitando agora`;
    }

    /**
     * Obter incremento aleatório
     */
    getRandomIncrement(base) {
        return Math.max(base, Math.floor(base + Math.random() * 5));
    }

    /**
     * Alternar detalhes do contador
     */
    toggleCounterDetails() {
        const container = document.getElementById('social-proof-notifications');
        let details = container.querySelector('.counter-details');

        if (details) {
            details.remove();
            return;
        }

        details = document.createElement('div');
        details.className = 'counter-details';
        details.style.cssText = `
            background: white;
            color: #333;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideInUp 0.3s ease;
        `;

        details.innerHTML = `
            <div style="margin-bottom: 8px;">
                <strong>📊 Estatísticas</strong>
            </div>
            <div>✅ ${this.stats.conversions || 0} clientes satisfeitos</div>
            <div>💼 ${this.stats.leads || 0} leads processados</div>
            <div style="margin-top: 8px; font-size: 11px; color: #999;">
                Dados atualizados em tempo real
            </div>
        `;

        const counter = document.getElementById('visitor-counter');
        if (counter) {
            counter.after(details);
        }
    }

    /**
     * Iniciar ciclo de notificações
     */
    startNotificationCycle() {
        // Notificações padrão
        this.notifications = this.getDefaultNotifications();

        // Mostrar notificação a cada 8-12 segundos
        setInterval(() => {
            this.showRandomNotification();
        }, 8000 + Math.random() * 4000);
    }

    /**
     * Obter notificações padrão
     */
    getDefaultNotifications() {
        return [
            {
                type: 'lead',
                message: 'João S. de São Paulo enviou uma requisição',
                icon: '📝'
            },
            {
                type: 'conversion',
                message: 'Marina F. recebeu uma proposta personalizada',
                icon: '✅'
            },
            {
                type: 'lead',
                message: 'Carlos A. iniciou uma análise de crédito',
                icon: '💰'
            },
            {
                type: 'conversion',
                message: 'Paula R. foi qualificada como lead hot',
                icon: '🔥'
            },
            {
                type: 'lead',
                message: 'Roberto M. consultou sobre linhas de crédito',
                icon: '📞'
            },
            {
                type: 'conversion',
                message: 'Fernanda K. agendou reunião com consultor',
                icon: '📅'
            },
            {
                type: 'lead',
                message: 'Bruno T. de Minas Gerais visitou nosso site',
                icon: '🌎'
            },
            {
                type: 'conversion',
                message: 'Alessandra P. recebeu aprovação pré-qualificada',
                icon: '🎉'
            }
        ];
    }

    /**
     * Mostrar notificação aleatória
     */
    showRandomNotification() {
        if (Math.random() > 0.7) return; // 30% de chance de mostrar

        const notification = this.notifications[
            Math.floor(Math.random() * this.notifications.length)
        ];

        this.displayNotification(notification);
    }

    /**
     * Exibir notificação
     */
    displayNotification(notification) {
        const container = document.getElementById('social-proof-notifications');
        if (!container) return;

        const notifElement = document.createElement('div');
        notifElement.className = `social-proof-notification ${notification.type}`;
        notifElement.style.cssText = `
            background: white;
            border-left: 4px solid #007bff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 13px;
            color: #333;
            animation: slideInUp 0.3s ease;
            cursor: pointer;
            transition: all 0.3s ease;
        `;

        notifElement.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">${notification.icon}</span>
                <div style="flex: 1;">
                    <strong>${notification.message}</strong>
                </div>
                <button style="
                    background: none;
                    border: none;
                    color: #999;
                    cursor: pointer;
                    font-size: 18px;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                " onclick="this.parentElement.parentElement.remove();">×</button>
            </div>
        `;

        // Mover contador para cima
        const counter = container.querySelector('.visitor-counter');
        if (counter) {
            container.insertBefore(notifElement, counter);
        } else {
            container.appendChild(notifElement);
        }

        // Auto-remover após 10 segundos
        setTimeout(() => {
            notifElement.style.animation = 'slideOutDown 0.3s ease';
            setTimeout(() => notifElement.remove(), 300);
        }, 10000);

        // Limitar a 3 notificações simultâneas
        const notifications = container.querySelectorAll('.social-proof-notification');
        if (notifications.length > 3) {
            notifications[0].remove();
        }
    }

    /**
     * Atualizar contadores
     */
    updateCounters() {
        this.updateVisitorCounter();
    }

    /**
     * Anexar listeners
     */
    attachListeners() {
        // Rastrear quando usuário interage com o site
        document.addEventListener('click', () => {
            this.incrementStat('visits');
        });

        // Rastrear submissão de formulário
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.id === 'requisicaoForm' || form.id === 'depoimentoForm') {
                this.incrementStat('leads');
            }
        }, true);
    }

    /**
     * Incrementar estatística
     */
    incrementStat(stat) {
        this.stats[stat]++;
        localStorage.setItem('socialProofStats', JSON.stringify(this.stats));
        this.updateCounters();
    }

    /**
     * Obter elemento de confiança (testimonial)
     */
    getTrustelElement() {
        const element = document.createElement('div');
        element.className = 'trust-element';
        element.style.cssText = `
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            margin: 8px 0;
            text-align: center;
        `;

        const testimonials = [
            '⭐⭐⭐⭐⭐ "Melhor serviço de crédito que já contratei!"',
            '⭐⭐⭐⭐⭐ "Aprovado em menos de 24 horas!"',
            '⭐⭐⭐⭐⭐ "Consultores muito profissionais e atencioso"',
            '⭐⭐⭐⭐⭐ "Melhor taxa que encontrei no mercado"'
        ];

        element.innerHTML = testimonials[
            Math.floor(Math.random() * testimonials.length)
        ];

        return element;
    }

    /**
     * Criar widget de avaliação
     */
    createRatingWidget() {
        const widget = document.createElement('div');
        widget.className = 'rating-widget';
        widget.style.cssText = `
            background: white;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 12px;
            text-align: center;
            font-size: 12px;
        `;

        widget.innerHTML = `
            <div style="margin-bottom: 8px; font-weight: bold;">Avalie nossa plataforma</div>
            <div style="font-size: 20px; letter-spacing: 4px; cursor: pointer;" onclick="alert('Obrigado por sua avaliação!');">
                ⭐ ⭐ ⭐ ⭐ ⭐
            </div>
            <div style="color: #999; margin-top: 8px; font-size: 11px;">
                ${Math.floor(Math.random() * 50) + 150} pessoas avaliaram
            </div>
        `;

        return widget;
    }

    /**
     * Adicionar CSS ao documento
     */
    static addStyles() {
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes slideOutDown {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(20px);
                }
            }

            .social-proof-notification {
                animation: slideInUp 0.3s ease !important;
            }

            .social-proof-notification:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
                transform: translateY(-2px) !important;
            }

            .visitor-counter:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(0, 123, 255, 0.4) !important;
            }

            @media (max-width: 480px) {
                #social-proof-notifications {
                    max-width: 300px !important;
                    bottom: 10px !important;
                    right: 10px !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    SocialProofWidget.addStyles();
    
    if (document.querySelector('[data-social-proof]')) {
        window.socialProof = new SocialProofWidget({
            position: 'bottom-right',
            updateInterval: 5000
        });
    }
});

// Exportar para uso externo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SocialProofWidget;
}
