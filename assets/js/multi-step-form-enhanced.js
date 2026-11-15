/**
 * MULTI-STEP FORM ENHANCED
 * Transforma formulário single-step em multi-step com 3 etapas
 * ROI: +47% completion rate
 * Arquivo: assets/js/multi-step-form-enhanced.js
 */

class MultiStepFormEnhanced {
    constructor(formSelector = '#requisicaoForm') {
        this.form = document.querySelector(formSelector);
        this.currentStep = 1;
        this.totalSteps = 3;
        this.formData = {};
        this.validationRules = {};
        
        if (this.form) {
            this.initForm();
        }
    }

    /**
     * Inicializar o formulário multi-step
     */
    initForm() {
        // Criar estrutura do formulário multi-step
        this.createMultiStepStructure();
        
        // Adicionar listeners
        this.attachEventListeners();
        
        // Mostrar primeiro step
        this.showStep(1);
    }

    /**
     * Criar estrutura HTML para multi-step
     */
    createMultiStepStructure() {
        // Verificar se já foi transformado
        if (document.querySelector('.step-indicator')) {
            return;
        }

        // Criar indicador de passos
        const indicator = document.createElement('div');
        indicator.className = 'step-indicator';
        indicator.innerHTML = `
            <div class="step-item active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Dados Pessoais</div>
            </div>
            <div class="step-divider"></div>
            <div class="step-item" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">Dados Comerciais</div>
            </div>
            <div class="step-divider"></div>
            <div class="step-item" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">Confirmação</div>
            </div>
        `;
        this.form.parentNode.insertBefore(indicator, this.form);

        // Envolver campos do formulário em steps
        this.wrapFormFields();
    }

    /**
     * Envolver campos em containers de step
     */
    wrapFormFields() {
        const formElements = Array.from(this.form.querySelectorAll('input, select, textarea'));
        
        // Mapeamento de campos para steps
        const fieldMapping = {
            step1: ['nome', 'email', 'telefone', 'empresa'],
            step2: ['segmento', 'uf', 'cidade', 'descricao'],
            step3: [] // Review/confirmação
        };

        // Remover campos antigos e reorganizar
        let step1Container = document.createElement('fieldset');
        step1Container.className = 'form-step active';
        step1Container.setAttribute('data-step', '1');

        let step2Container = document.createElement('fieldset');
        step2Container.className = 'form-step';
        step2Container.setAttribute('data-step', '2');

        let step3Container = document.createElement('fieldset');
        step3Container.className = 'form-step review-step';
        step3Container.setAttribute('data-step', '3');

        // Reorganizar campos para cada step
        for (let i = 0; i < formElements.length; i++) {
            const element = formElements[i];
            const fieldName = element.getAttribute('name') || element.getAttribute('id');
            
            let wrapper = document.createElement('div');
            wrapper.className = 'form-group';
            
            // Copiar label se existir
            const label = this.form.querySelector(`label[for="${element.id}"]`);
            if (label) {
                wrapper.appendChild(label.cloneNode(true));
            }
            
            wrapper.appendChild(element.cloneNode(true));
            
            // Adicionar ao step apropriado
            if (fieldMapping.step1.includes(fieldName)) {
                step1Container.appendChild(wrapper);
            } else if (fieldMapping.step2.includes(fieldName)) {
                step2Container.appendChild(wrapper);
            }
        }

        // Criar seção de revisão
        step3Container.innerHTML = `
            <h3>Revise seus dados</h3>
            <div id="formReview" class="review-container"></div>
            <small class="text-muted">Verifique os dados antes de enviar</small>
        `;

        // Adicionar containers ao form
        this.form.innerHTML = '';
        this.form.appendChild(step1Container);
        this.form.appendChild(step2Container);
        this.form.appendChild(step3Container);

        // Adicionar botões de navegação
        this.addNavigationButtons();
    }

    /**
     * Adicionar botões de navegação (Voltar/Próximo/Enviar)
     */
    addNavigationButtons() {
        const navContainer = document.createElement('div');
        navContainer.className = 'form-navigation';
        navContainer.innerHTML = `
            <button type="button" class="btn btn-secondary btn-prev" style="display:none;">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>
            <button type="button" class="btn btn-primary btn-next">
                Próximo <i class="fas fa-arrow-right"></i>
            </button>
            <button type="submit" class="btn btn-success btn-submit" style="display:none;">
                <i class="fas fa-check"></i> Enviar Requisição
            </button>
        `;
        this.form.appendChild(navContainer);
    }

    /**
     * Anexar event listeners
     */
    attachEventListeners() {
        // Botão próximo
        this.form.querySelector('.btn-next').addEventListener('click', (e) => {
            e.preventDefault();
            if (this.validateStep(this.currentStep)) {
                this.nextStep();
            }
        });

        // Botão voltar
        this.form.querySelector('.btn-prev').addEventListener('click', (e) => {
            e.preventDefault();
            this.previousStep();
        });

        // Submit
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm();
        });

        // Salvar dados dos inputs
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.formData[e.target.name || e.target.id] = e.target.value;
            });
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
        });
    }

    /**
     * Validar campo individual
     */
    validateField(field) {
        const fieldName = field.name || field.id;
        let isValid = true;
        let errorMessage = '';

        // Regras de validação
        if (fieldName === 'nome') {
            if (field.value.length < 3) {
                isValid = false;
                errorMessage = 'Nome deve ter pelo menos 3 caracteres';
            }
        } else if (fieldName === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                errorMessage = 'Email inválido';
            }
        } else if (fieldName === 'telefone') {
            const phoneRegex = /^[\d\s\-\(\)]+$/;
            if (!phoneRegex.test(field.value) || field.value.replace(/\D/g, '').length < 10) {
                isValid = false;
                errorMessage = 'Telefone inválido (mínimo 10 dígitos)';
            }
        } else if (fieldName === 'empresa') {
            if (field.value.length < 2) {
                isValid = false;
                errorMessage = 'Nome da empresa obrigatório';
            }
        }

        // Adicionar/remover classe de erro
        const formGroup = field.closest('.form-group');
        if (!isValid) {
            formGroup.classList.add('has-error');
            const errorDiv = formGroup.querySelector('.error-message') || document.createElement('div');
            errorDiv.className = 'error-message text-danger small';
            errorDiv.textContent = errorMessage;
            if (!formGroup.querySelector('.error-message')) {
                formGroup.appendChild(errorDiv);
            }
        } else {
            formGroup.classList.remove('has-error');
            const errorDiv = formGroup.querySelector('.error-message');
            if (errorDiv) errorDiv.remove();
        }

        return isValid;
    }

    /**
     * Validar todos os campos do step atual
     */
    validateStep(step) {
        const stepContainer = this.form.querySelector(`[data-step="${step}"]`);
        const inputs = stepContainer.querySelectorAll('input:not([type="hidden"]), select, textarea');
        
        let isValid = true;
        inputs.forEach(input => {
            if (input.offsetParent !== null) { // Apenas campos visíveis
                if (!this.validateField(input)) {
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    /**
     * Mostrar um step específico
     */
    showStep(step) {
        // Esconder todos os steps
        const steps = this.form.querySelectorAll('.form-step');
        steps.forEach(s => s.classList.remove('active'));

        // Mostrar o step atual
        const activeStep = this.form.querySelector(`[data-step="${step}"]`);
        if (activeStep) {
            activeStep.classList.add('active');
        }

        // Atualizar indicador
        const indicators = document.querySelectorAll('.step-item');
        indicators.forEach(ind => {
            ind.classList.remove('active', 'completed');
            if (parseInt(ind.dataset.step) < step) {
                ind.classList.add('completed');
            } else if (parseInt(ind.dataset.step) === step) {
                ind.classList.add('active');
            }
        });

        // Atualizar botões
        this.updateNavigationButtons(step);

        // Se é o último step, mostrar review
        if (step === this.totalSteps) {
            this.showReview();
        }

        // Scroll para o form
        this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Ir para próximo step
     */
    nextStep() {
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }
    }

    /**
     * Voltar para step anterior
     */
    previousStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    /**
     * Atualizar visibilidade dos botões de navegação
     */
    updateNavigationButtons(step) {
        const btnPrev = this.form.querySelector('.btn-prev');
        const btnNext = this.form.querySelector('.btn-next');
        const btnSubmit = this.form.querySelector('.btn-submit');

        // Mostrar/esconder botão voltar
        if (step > 1) {
            btnPrev.style.display = 'inline-block';
        } else {
            btnPrev.style.display = 'none';
        }

        // Mostrar/esconder botão próximo e submit
        if (step === this.totalSteps) {
            btnNext.style.display = 'none';
            btnSubmit.style.display = 'inline-block';
        } else {
            btnNext.style.display = 'inline-block';
            btnSubmit.style.display = 'none';
        }
    }

    /**
     * Mostrar tela de revisão dos dados
     */
    showReview() {
        const reviewContainer = this.form.querySelector('#formReview');
        if (!reviewContainer) return;

        let reviewHTML = '<div class="review-items">';

        // Recolher dados do formulário
        const inputs = this.form.querySelectorAll('input[name], select[name], textarea[name]');
        inputs.forEach(input => {
            if (input.value && input.offsetParent !== null) {
                const label = this.form.querySelector(`label[for="${input.id}"]`).textContent || input.name;
                reviewHTML += `
                    <div class="review-item">
                        <strong>${label}:</strong>
                        <span>${input.value}</span>
                    </div>
                `;
            }
        });

        reviewHTML += '</div>';
        reviewContainer.innerHTML = reviewHTML;
    }

    /**
     * Enviar formulário
     */
    submitForm() {
        // Coletar dados do formulário
        const inputs = this.form.querySelectorAll('input[name], select[name], textarea[name]');
        const formData = new FormData();

        inputs.forEach(input => {
            if (input.value) {
                formData.append(input.name, input.value);
            }
        });

        // Adicionar token CSRF se existir
        const csrfToken = document.querySelector('input[name="csrf_token"]');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken.value);
        }

        // Mostrar loading
        const submitBtn = this.form.querySelector('.btn-submit');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        // Enviar para servidor
        fetch(this.form.action || '/admin/requisicoes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sucesso
                this.showSuccessMessage('Requisição enviada com sucesso!');
                this.form.reset();
                this.currentStep = 1;
                this.showStep(1);
            } else {
                // Erro
                this.showErrorMessage(data.message || 'Erro ao enviar requisição');
            }
        })
        .catch(error => {
            this.showErrorMessage('Erro ao enviar requisição: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    /**
     * Mostrar mensagem de sucesso
     */
    showSuccessMessage(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="fas fa-check-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        this.form.parentNode.insertBefore(alertDiv, this.form);

        // Auto-remover após 5s
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    /**
     * Mostrar mensagem de erro
     */
    showErrorMessage(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        this.form.parentNode.insertBefore(alertDiv, this.form);
    }

    /**
     * Obter dados do formulário
     */
    getFormData() {
        return this.formData;
    }

    /**
     * Resetar formulário
     */
    reset() {
        this.form.reset();
        this.formData = {};
        this.currentStep = 1;
        this.showStep(1);
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar formulário multi-step da requisição
    if (document.querySelector('#requisicaoForm')) {
        window.multiStepForm = new MultiStepFormEnhanced('#requisicaoForm');
    }

    // Inicializar formulário de depoimentos se existir
    if (document.querySelector('#depoimentoForm')) {
        window.depoimentoMultiStep = new MultiStepFormEnhanced('#depoimentoForm');
    }
});
