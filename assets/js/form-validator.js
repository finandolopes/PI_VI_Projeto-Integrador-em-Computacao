/**
 * ========================================
 * FORM VALIDATOR - Validação em Tempo Real
 * ========================================
 * Valida formulários com feedback imediato
 * Data: 07/02/2026
 */

class FormValidator {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error('Formulário não encontrado: ' + formId);
            return;
        }

        this.options = {
            showFeedback: true,
            debounceDelay: 300,
            ...options
        };

        this.debounceTimers = {};
        this.setupValidation();
    }

    setupValidation() {
        // Event listeners para validação em tempo real
        this.form.addEventListener('input', (e) => this.handleInput(e));
        this.form.addEventListener('change', (e) => this.handleChange(e));
        this.form.addEventListener('blur', (e) => this.handleBlur(e), true);
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    handleInput(e) {
        const input = e.target;
        if (!input.name) return;

        // Debounce para não validar a cada caractere
        clearTimeout(this.debounceTimers[input.name]);
        this.debounceTimers[input.name] = setTimeout(() => {
            this.validateField(input);
        }, this.options.debounceDelay);
    }

    handleChange(e) {
        const input = e.target;
        if (input.type === 'select-one' || input.type === 'checkbox' || input.type === 'radio') {
            this.validateField(input);
        }
    }

    handleBlur(e) {
        const input = e.target;
        if (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA' || input.tagName === 'SELECT') {
            this.validateField(input);
        }
    }

    handleSubmit(e) {
        // Validar todos os campos
        const inputs = this.form.querySelectorAll('input, textarea, select');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            this.showAlert('Por favor, corrija os erros no formulário', 'danger');
        }
    }

    validateField(input) {
        const validators = this.getValidators(input);
        let isValid = true;

        for (let validator of validators) {
            if (!validator.test(input.value)) {
                isValid = false;
                this.setFeedback(input, false, validator.message);
                break;
            }
        }

        if (isValid && input.value.trim()) {
            this.setFeedback(input, true, 'Válido');
        } else if (isValid && !input.value.trim() && input.required) {
            this.setFeedback(input, false, 'Campo obrigatório');
            return false;
        } else if (isValid && !input.value.trim()) {
            this.clearFeedback(input);
        }

        return isValid;
    }

    getValidators(input) {
        const validators = [];

        // Validar obrigatoriedade
        if (input.required && !input.value.trim()) {
            return [{
                test: () => false,
                message: 'Campo obrigatório'
            }];
        }

        // Validação por tipo
        if (input.type === 'email') {
            validators.push({
                test: (value) => this.validateEmail(value),
                message: 'Email inválido'
            });
        }

        if (input.name === 'cpf') {
            validators.push({
                test: (value) => this.validateCPF(value),
                message: 'CPF inválido'
            });
        }

        if (input.name === 'cnpj') {
            validators.push({
                test: (value) => this.validateCNPJ(value),
                message: 'CNPJ inválido'
            });
        }

        if (input.name === 'telefone' || input.name === 'phone') {
            validators.push({
                test: (value) => this.validatePhone(value),
                message: 'Telefone inválido'
            });
        }

        if (input.type === 'password' && input.dataset.minLength) {
            const minLength = parseInt(input.dataset.minLength);
            validators.push({
                test: (value) => value.length >= minLength,
                message: `Mínimo ${minLength} caracteres`
            });
        }

        if (input.type === 'password' && input.dataset.requireNumbers) {
            validators.push({
                test: (value) => /\d/.test(value),
                message: 'Deve conter números'
            });
        }

        if (input.type === 'password' && input.dataset.requireSpecial) {
            validators.push({
                test: (value) => /[!@#$%^&*(),.":{}|<>]/.test(value),
                message: 'Deve conter caracteres especiais'
            });
        }

        if (input.type === 'url') {
            validators.push({
                test: (value) => this.validateURL(value),
                message: 'URL inválida'
            });
        }

        if (input.type === 'number' && input.min) {
            validators.push({
                test: (value) => parseFloat(value) >= parseFloat(input.min),
                message: `Mínimo: ${input.min}`
            });
        }

        if (input.type === 'number' && input.max) {
            validators.push({
                test: (value) => parseFloat(value) <= parseFloat(input.max),
                message: `Máximo: ${input.max}`
            });
        }

        // Se nenhum validador foi adicionado, passar
        if (validators.length === 0) {
            validators.push({
                test: (value) => true,
                message: ''
            });
        }

        return validators;
    }

    validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email) && email.length <= 254;
    }

    validateCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        
        if (cpf.length !== 11) return false;
        if (/^(\d)\1{10}$/.test(cpf)) return false;

        let soma = 0;
        let remainder;

        for (let i = 1; i <= 9; i++) {
            soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
        }

        remainder = (soma * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.substring(9, 10))) return false;

        soma = 0;
        for (let i = 1; i <= 10; i++) {
            soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
        }

        remainder = (soma * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.substring(10, 11))) return false;

        return true;
    }

    validateCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');

        if (cnpj.length !== 14) return false;
        if (/^(\d)\1{13}$/.test(cnpj)) return false;

        let size = cnpj.length - 2;
        let numbers = cnpj.substring(0, size);
        let digits = cnpj.substring(size);
        let sum = 0;
        let pos = size - 7;

        for (let i = size; i >= 1; i--) {
            sum += numbers.charAt(size - i) * pos--;
            if (pos < 2) pos = 9;
        }

        let result = sum % 11 < 2  0 : 11 - sum % 11;
        if (result !== parseInt(digits.charAt(0))) return false;

        size = size + 1;
        numbers = cnpj.substring(0, size);
        sum = 0;
        pos = size - 7;

        for (let i = size; i >= 1; i--) {
            sum += numbers.charAt(size - i) * pos--;
            if (pos < 2) pos = 9;
        }

        result = sum % 11 < 2  0 : 11 - sum % 11;
        if (result !== parseInt(digits.charAt(1))) return false;

        return true;
    }

    validatePhone(phone) {
        const digits = phone.replace(/\D/g, '');
        return digits.length >= 10 && digits.length <= 11;
    }

    validateURL(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    setFeedback(input, isValid, message) {
        if (!this.options.showFeedback) return;

        let feedback = input.nextElementSibling;
        
        if (!feedback || !feedback.classList.contains('form-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'form-feedback';
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }

        if (isValid) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            feedback.className = 'form-feedback text-success';
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.className = 'form-feedback text-danger';
        }

        feedback.textContent = message;
    }

    clearFeedback(input) {
        input.classList.remove('is-valid', 'is-invalid');
        
        let feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('form-feedback')) {
            feedback.textContent = '';
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

    isFormValid() {
        const inputs = this.form.querySelectorAll('input, textarea, select');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }
}

// Auto-inicializar para todos os formulários com classe 'form-validator'
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.form-validator').forEach(form => {
        new FormValidator(form.id);
    });
});
