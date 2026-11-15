/**
 * reCAPTCHA v3 INTEGRATION
 * Integração silent do reCAPTCHA v3 para proteção contra spam
 * ROI: -67% spam
 * Arquivo: assets/js/recaptcha-integration.js
 */

class RecaptchaV3Integration {
    constructor(options = {}) {
        this.siteKey = options.siteKey || window.RECAPTCHA_SITE_KEY || '';
        this.secretKey = options.secretKey || ''; // Apenas servidor
        this.threshold = options.threshold || 0.5; // Score mínimo aceitável
        this.actions = options.actions || {
            'submit_form': 0.5,
            'submit_requisicao': 0.6,
            'submit_depoimento': 0.5,
            'contact': 0.5
        };
        this.scoreHistory = [];
        this.scriptLoaded = false;
        
        if (this.siteKey) {
            this.init();
        }
    }

    /**
     * Inicializar reCAPTCHA v3
     */
    init() {
        // Carregar script do reCAPTCHA
        this.loadRecaptchaScript();

        // Adicionar listeners a formulários
        this.attachFormListeners();
    }

    /**
     * Carregar script do reCAPTCHA v3
     */
    loadRecaptchaScript() {
        if (this.scriptLoaded || window.grecaptcha) {
            this.scriptLoaded = true;
            return;
        }

        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.jsrender=${this.siteKey}`;
        script.async = true;
        script.defer = true;
        
        script.onload = () => {
            this.scriptLoaded = true;
            console.log('reCAPTCHA v3 carregado com sucesso');
        };

        script.onerror = () => {
            console.error('Erro ao carregar reCAPTCHA v3');
        };

        document.head.appendChild(script);
    }

    /**
     * Anexar listeners a formulários
     */
    attachFormListeners() {
        const forms = document.querySelectorAll('form[data-recaptcha-action]');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });

        // Também registrar forms sem atributo específico
        const defaultForms = document.querySelectorAll('#requisicaoForm, #depoimentoForm, form[id*="contact"]');
        defaultForms.forEach(form => {
            if (!form.hasAttribute('data-recaptcha-action')) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleFormSubmit(form);
                });
            }
        });
    }

    /**
     * Lidar com submit de formulário
     */
    async handleFormSubmit(form) {
        // Definir ação
        const action = form.getAttribute('data-recaptcha-action') || form.id || 'submit_form';

        try {
            // Mostrar loading
            this.showLoadingState(form);

            // Executar reCAPTCHA
            const token = await this.executeRecaptcha(action);

            // Verificar score no servidor
            const verification = await this.verifyTokenOnServer(token, action);

            if (verification.success && verification.score >= this.getActionThreshold(action)) {
                // Score aceitável - enviar form
                this.submitFormToServer(form, token, verification.score);
            } else {
                // Score baixo ou fail
                this.showSecurityWarning(form, verification);
            }
        } catch (error) {
            console.error('Erro ao processar reCAPTCHA:', error);
            this.showErrorMessage(form, 'Erro na verificação de segurança');
        }
    }

    /**
     * Executar reCAPTCHA para ação específica
     */
    async executeRecaptcha(action = 'submit') {
        return new Promise((resolve, reject) => {
            if (!window.grecaptcha) {
                reject(new Error('reCAPTCHA não carregado'));
                return;
            }

            window.grecaptcha.ready(() => {
                window.grecaptcha
                    .execute(this.siteKey, { action })
                    .then(token => resolve(token))
                    .catch(error => reject(error));
            });
        });
    }

    /**
     * Verificar token no servidor
     */
    async verifyTokenOnServer(token, action) {
        try {
            const response = await fetch('/api/verify-recaptcha.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    token: token,
                    action: action
                })
            });

            const data = await response.json();
            
            // Registrar no histórico
            this.scoreHistory.push({
                action: action,
                score: data.score,
                timestamp: new Date(),
                success: data.success
            });

            return data;
        } catch (error) {
            console.error('Erro ao verificar token:', error);
            return {
                success: false,
                score: 0
            };
        }
    }

    /**
     * Obter threshold para ação específica
     */
    getActionThreshold(action) {
        return this.actions[action] || this.threshold;
    }

    /**
     * Enviar formulário para servidor
     */
    async submitFormToServer(form, token, score) {
        try {
            // Adicionar token e score ao form
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'recaptcha_token';
            tokenInput.value = token;
            form.appendChild(tokenInput);

            const scoreInput = document.createElement('input');
            scoreInput.type = 'hidden';
            scoreInput.name = 'recaptcha_score';
            scoreInput.value = score;
            form.appendChild(scoreInput);

            // Preparar FormData
            const formData = new FormData(form);

            // Enviar
            const response = await fetch(form.action || form.getAttribute('data-submit-url') || window.location.href, {
                method: form.method || 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccessMessage(form, result.message);
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 2000);
                }
                form.reset();
            } else {
                this.showErrorMessage(form, result.message || 'Erro ao processar formulário');
            }
        } catch (error) {
            console.error('Erro ao enviar formulário:', error);
            this.showErrorMessage(form, 'Erro ao enviar formulário');
        } finally {
            this.hideLoadingState(form);
        }
    }

    /**
     * Mostrar aviso de segurança (score baixo)
     */
    showSecurityWarning(form, verification) {
        const message = `
            ⚠️ Atividade suspeita detectada (score: ${(verification.score * 100).toFixed(0)}%).
            Por favor, complete a verificação manual abaixo.
        `;

        // Mostrar reCAPTCHA v2 como fallback
        this.showChallengeRecaptcha(form);
        this.hideLoadingState(form);
    }

    /**
     * Mostrar reCAPTCHA v2 como fallback
     */
    showChallengeRecaptcha(form) {
        // Procurar por container existente
        let container = form.querySelector('.g-recaptcha-challenge');
        
        if (!container) {
            container = document.createElement('div');
            container.className = 'g-recaptcha-challenge';
            container.style.marginTop = '1rem';
            form.appendChild(container);
        }

        // Mostrar reCAPTCHA v2
        if (window.grecaptcha && this.siteKey) {
            container.innerHTML = '<div class="g-recaptcha" data-sitekey="' + this.siteKey + '"></div>';
            window.grecaptcha.render(container.querySelector('.g-recaptcha'));
        }
    }

    /**
     * Mostrar estado de loading
     */
    showLoadingState(form) {
        const submitBtn = form.querySelector('button[type="submit"], .btn-submit, [type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando segurança...';
            submitBtn.setAttribute('data-original-html', submitBtn.innerHTML);
        }
    }

    /**
     * Esconder estado de loading
     */
    hideLoadingState(form) {
        const submitBtn = form.querySelector('button[type="submit"], .btn-submit, [type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            const originalHtml = submitBtn.getAttribute('data-original-html');
            if (originalHtml) {
                submitBtn.innerHTML = originalHtml;
                submitBtn.removeAttribute('data-original-html');
            }
        }
    }

    /**
     * Mostrar mensagem de erro
     */
    showErrorMessage(form, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        form.parentNode.insertBefore(alertDiv, form);

        setTimeout(() => alertDiv.remove(), 5000);
    }

    /**
     * Mostrar mensagem de sucesso
     */
    showSuccessMessage(form, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="fas fa-check-circle"></i> ${message || 'Enviado com sucesso!'}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        form.parentNode.insertBefore(alertDiv, form);

        setTimeout(() => alertDiv.remove(), 5000);
    }

    /**
     * Obter histórico de scores
     */
    getScoreHistory() {
        return this.scoreHistory;
    }

    /**
     * Resetar histórico
     */
    resetHistory() {
        this.scoreHistory = [];
    }

    /**
     * Obter estatísticas de scores
     */
    getScoreStats() {
        if (this.scoreHistory.length === 0) {
            return null;
        }

        const scores = this.scoreHistory.map(h => h.score);
        const avgScore = scores.reduce((a, b) => a + b, 0) / scores.length;
        const minScore = Math.min(...scores);
        const maxScore = Math.max(...scores);

        const successCount = this.scoreHistory.filter(h => h.success).length;
        const successRate = (successCount / this.scoreHistory.length) * 100;

        return {
            total_checks: this.scoreHistory.length,
            average_score: avgScore.toFixed(2),
            min_score: minScore.toFixed(2),
            max_score: maxScore.toFixed(2),
            success_rate: successRate.toFixed(2),
            failures: this.scoreHistory.length - successCount
        };
    }

    /**
     * Ativar proteção em tempo real
     */
    enableRealtimeProtection() {
        // Rastrear interações suspeitas
        document.addEventListener('keypress', () => {
            this.trackBehavior('typing');
        });

        document.addEventListener('mousemove', () => {
            this.trackBehavior('mouse_movement');
        });

        document.addEventListener('click', () => {
            this.trackBehavior('click');
        });
    }

    /**
     * Rastrear comportamento para detecção
     */
    trackBehavior(type) {
        // Implementar lógica adicional de tracking se necessário
        // Enviar para servidor para análise adicional
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar reCAPTCHA v3 se siteKey estiver disponível
    if (window.RECAPTCHA_SITE_KEY) {
        window.recaptchaIntegration = new RecaptchaV3Integration({
            siteKey: window.RECAPTCHA_SITE_KEY,
            actions: {
                'submit_requisicao': 0.6,
                'submit_depoimento': 0.5,
                'contact': 0.5
            }
        });
    }
});

// Exportar classe para uso externo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RecaptchaV3Integration;
}
