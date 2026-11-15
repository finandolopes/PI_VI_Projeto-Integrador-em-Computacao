/**
 * ========================================
 * MULTI-STEP FORM - Formulários em Etapas
 * ========================================
 * Cria formulários com múltiplas etapas com progresso
 * Data: 07/02/2026
 */

class MultiStepForm {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error('Formulário não encontrado: ' + formId);
            return;
        }

        this.currentStep = 1;
        this.totalSteps = this.form.querySelectorAll('[data-step]').length;
        this.formData = new Map();
        this.options = {
            saveProgress: true,
            scrollBehavior: 'smooth',
            ...options
        };

        this.init();
    }

    init() {
        this.setupButtons();
        this.loadProgress();
        this.showStep(this.currentStep);
        this.updateProgressBar();
    }

    setupButtons() {
        // Botões Next
        document.querySelectorAll(`#${this.form.id} [data-action="next"]`).forEach(btn => {
            btn.addEventListener('click', () => this.nextStep());
        });

        // Botões Previous
        document.querySelectorAll(`#${this.form.id} [data-action="prev"]`).forEach(btn => {
            btn.addEventListener('click', () => this.prevStep());
        });

        // Submit
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    nextStep() {
        if (this.validateStep(this.currentStep)) {
            this.saveProgress();
            this.currentStep++;
            this.showStep(this.currentStep);
            this.scrollToForm();
        }
    }

    prevStep() {
        this.saveProgress();
        this.currentStep--;
        this.showStep(this.currentStep);
        this.scrollToForm();
    }

    validateStep(step) {
        const stepDiv = document.querySelector(`#${this.form.id} [data-step="${step}"]`);
        if (!stepDiv) return true;

        const inputs = stepDiv.querySelectorAll('input[required], textarea[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            this.showAlert('Por favor, preencha todos os campos obrigatórios', 'warning');
        }

        return isValid;
    }

    showStep(step) {
        // Esconder todos os steps
        document.querySelectorAll(`#${this.form.id} [data-step]`).forEach(el => {
            el.style.display = 'none';
            el.classList.remove('step-active');
        });

        // Mostrar step atual
        const currentStepDiv = document.querySelector(`#${this.form.id} [data-step="${step}"]`);
        if (currentStepDiv) {
            currentStepDiv.style.display = 'block';
            currentStepDiv.classList.add('step-active');
            
            // Trigger event para permitir custom handlers
            const event = new CustomEvent('stepChange', { detail: { step: step } });
            this.form.dispatchEvent(event);
        }

        this.updateProgressBar();
        this.updateButtons();
    }

    updateProgressBar() {
        const progressBar = document.querySelector(`#${this.form.id} .progress-bar`);
        if (progressBar) {
            const progress = (this.currentStep / this.totalSteps) * 100;
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
            progressBar.textContent = `Etapa ${this.currentStep} de ${this.totalSteps}`;
        }

        // Atualizar indicadores de step
        document.querySelectorAll(`#${this.form.id} .step-indicator`).forEach((indicator, index) => {
            indicator.classList.remove('active', 'completed');
            
            if (index + 1 === this.currentStep) {
                indicator.classList.add('active');
            } else if (index + 1 < this.currentStep) {
                indicator.classList.add('completed');
            }
        });
    }

    updateButtons() {
        const prevBtn = document.querySelector(`#${this.form.id} [data-action="prev"]`);
        const nextBtn = document.querySelector(`#${this.form.id} [data-action="next"]`);
        const submitBtn = document.querySelector(`#${this.form.id} [type="submit"]`);

        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
            prevBtn.disabled = this.currentStep === 1;
        }

        if (nextBtn) {
            nextBtn.style.display = this.currentStep < this.totalSteps ? 'block' : 'none';
            nextBtn.disabled = this.currentStep === this.totalSteps;
        }

        if (submitBtn) {
            submitBtn.style.display = this.currentStep === this.totalSteps ? 'block' : 'none';
        }
    }

    saveProgress() {
        const inputs = this.form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            let value;
            
            if (input.type === 'checkbox' || input.type === 'radio') {
                value = input.checked;
            } else {
                value = input.value;
            }
            
            this.formData.set(input.name, value);
        });

        if (this.options.saveProgress) {
            localStorage.setItem(`form_progress_${this.form.id}`, JSON.stringify(Array.from(this.formData)));
        }
    }

    loadProgress() {
        if (!this.options.saveProgress) return;

        const saved = localStorage.getItem(`form_progress_${this.form.id}`);
        if (!saved) return;

        try {
            const data = new Map(JSON.parse(saved));
            data.forEach((value, name) => {
                const input = this.form.elements[name];
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = value;
                    } else {
                        input.value = value;
                    }
                }
            });
        } catch (e) {
            console.error('Erro ao carregar progress do formulário:', e);
        }
    }

    getFormData() {
        const data = new FormData(this.form);
        return Object.fromEntries(data);
    }

    clearProgress() {
        localStorage.removeItem(`form_progress_${this.form.id}`);
        this.formData.clear();
        this.form.reset();
    }

    handleSubmit(e) {
        if (this.currentStep !== this.totalSteps) {
            e.preventDefault();
            return;
        }

        this.saveProgress();
        // Form será enviado normalmente
    }

    scrollToForm() {
        if (this.options.scrollBehavior) {
            this.form.scrollIntoView({ 
                behavior: this.options.scrollBehavior,
                block: 'start'
            });
        }
    }

    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        this.form.insertBefore(alertDiv, this.form.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Método para pular para um step específico (útil para validação)
    goToStep(step) {
        if (step > 0 && step <= this.totalSteps) {
            this.currentStep = step;
            this.showStep(step);
            this.scrollToForm();
        }
    }
}

// Auto-inicializar para formulários com classe 'multi-step-form'
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.multi-step-form').forEach(form => {
        new MultiStepForm(form.id);
    });
});
