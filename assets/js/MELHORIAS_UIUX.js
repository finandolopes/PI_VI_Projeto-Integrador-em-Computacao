/**
 * ========================================
 * MELHORIAS DE UX - JAVASCRIPT
 * Interatividade e validação em tempo real
 * ========================================
 */

(function() {
    'use strict';

    // ========================================
    // 1. VALIDAÇÃO DE FORMULÁRIO EM TEMPO REAL
    // ========================================

    class ValidadorFormulario {
        constructor() {
            this.campos = {};
            this.erros = {};
        }

        /**
         * Registrar campo para validação
         */
        registrarCampo(id, regras) {
            const campo = document.getElementById(id);
            if (campo) {
                this.campos[id] = { elemento: campo, regras };
                this.adicionarListeners(id);
            }
        }

        /**
         * Adicionar event listeners
         */
        adicionarListeners(id) {
            const campo = this.campos[id].elemento;

            // Validar ao sair do campo
            campo.addEventListener('blur', () => this.validarCampo(id));

            // Validação em tempo real para feedback imediato
            campo.addEventListener('input', () => {
                clearTimeout(this.campos[id].timeout);
                this.campos[id].timeout = setTimeout(() => {
                    this.validarCampo(id);
                }, 300);
            });
        }

        /**
         * Validar campo específico
         */
        validarCampo(id) {
            const { elemento, regras } = this.campos[id];
            const valor = elemento.value.trim();
            let valido = true;
            let mensagem = '';

            for (const regra of regras) {
                const resultado = this.executarRegra(regra, valor, elemento);

                if (!resultado.valido) {
                    valido = false;
                    mensagem = resultado.mensagem;
                    break;
                }
            }

            this.mostrarErro(id, valido, mensagem);
            return valido;
        }

        /**
         * Executar regra de validação
         */
        executarRegra(regra, valor, elemento) {
            const { tipo, mensagem, opcoes = {} } = regra;

            switch (tipo) {
                case 'obrigatorio':
                    return {
                        valido: valor.length > 0,
                        mensagem: mensagem || 'Campo obrigatório'
                    };

                case 'email':
                    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return {
                        valido: regexEmail.test(valor),
                        mensagem: mensagem || 'E-mail inválido'
                    };

                case 'telefone':
                    const regexTelefone = /^\((\d{2})\)[\s\-](\d{4,5})[\s\-](\d{4})$/;
                    return {
                        valido: regexTelefone.test(valor) || valor.length === 0,
                        mensagem: mensagem || 'Telefone inválido'
                    };

                case 'cpf':
                    return {
                        valido: this.validarCPF(valor),
                        mensagem: mensagem || 'CPF inválido'
                    };

                case 'minimo':
                    return {
                        valido: valor.length >= opcoes.tamanho,
                        mensagem: mensagem || `Mínimo ${opcoes.tamanho} caracteres`
                    };

                case 'maximo':
                    return {
                        valido: valor.length <= opcoes.tamanho,
                        mensagem: mensagem || `Máximo ${opcoes.tamanho} caracteres`
                    };

                case 'numero':
                    return {
                        valido: !isNaN(valor) && valor !== '',
                        mensagem: mensagem || 'Apenas números'
                    };

                case 'regex':
                    return {
                        valido: opcoes.padrao.test(valor) || valor.length === 0,
                        mensagem: mensagem || 'Formato inválido'
                    };

                default:
                    return { valido: true, mensagem: '' };
            }
        }

        /**
         * Validar CPF (algoritmo)
         */
        validarCPF(cpf) {
            cpf = cpf.replace(/\D/g, '');

            if (cpf.length !== 11) return false;
            if (/^(\d)\1+$/.test(cpf)) return false;

            // Primeiro dígito verificador
            let soma = 0;
            for (let i = 0; i < 9; i++) {
                soma += parseInt(cpf[i]) * (10 - i);
            }
            let digito1 = 11 - (soma % 11);
            digito1 = digito1 > 9  0 : digito1;

            if (parseInt(cpf[9]) !== digito1) return false;

            // Segundo dígito verificador
            soma = 0;
            for (let i = 0; i < 10; i++) {
                soma += parseInt(cpf[i]) * (11 - i);
            }
            let digito2 = 11 - (soma % 11);
            digito2 = digito2 > 9  0 : digito2;

            return parseInt(cpf[10]) === digito2;
        }

        /**
         * Mostrar ou ocultar erro
         */
        mostrarErro(id, valido, mensagem) {
            const campo = this.campos[id].elemento;
            const feedback = document.getElementById(`${id}-feedback`);

            campo.classList.toggle('is-invalid', !valido);
            campo.classList.toggle('is-valid', valido && campo.value.trim() !== '');

            if (feedback) {
                feedback.textContent = mensagem;
                feedback.style.display = valido  'none' : 'block';
            }

            this.erros[id] = !valido;
        }

        /**
         * Validar todo o formulário
         */
        validarTudo() {
            let todoValido = true;

            for (const id in this.campos) {
                if (!this.validarCampo(id)) {
                    todoValido = false;
                }
            }

            return todoValido;
        }
    }

    // ========================================
    // 2. LOGIN COM FEEDBACK VISUAL
    // ========================================

    class GerenciadorLogin {
        constructor() {
            this.formularioLogin = document.querySelector('[action*="processa_login"]');
            this.botaoEntrar = this.formularioLogin.querySelector('button[type="submit"]');
            this.campoUsuario = this.formularioLogin.querySelector('[name="usuario"]');
            this.campoSenha = this.formularioLogin.querySelector('[name="senha"]');
            this.containerErro = document.getElementById('loginError');
            this.msgErro = document.getElementById('errorMessage');

            if (this.formularioLogin) {
                this.inicializar();
            }
        }

        inicializar() {
            this.formularioLogin.addEventListener('submit', (e) => this.handleSubmit(e));
            this.campoUsuario.addEventListener('focus', () => this.limparErro());
            this.campoSenha.addEventListener('focus', () => this.limparErro());
        }

        handleSubmit(e) {
            // Validação básica
            if (!this.campoUsuario.value.trim() || !this.campoSenha.value.trim()) {
                e.preventDefault();
                this.mostrarErro('Preencha todos os campos');
                return;
            }

            // Mostrar loading
            this.botaoEntrar.classList.add('loading');
            this.botaoEntrar.disabled = true;
            this.botaoEntrar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Autenticando...';
        }

        mostrarErro(mensagem) {
            if (this.containerErro) {
                this.msgErro.textContent = mensagem;
                this.containerErro.classList.remove('d-none');
                this.containerErro.style.animation = 'slideInUp 0.3s ease-out';
            }
        }

        limparErro() {
            if (this.containerErro) {
                this.containerErro.classList.add('d-none');
            }
        }
    }

    // ========================================
    // 3. FORMATADORES DE ENTRADA
    // ========================================

    class Formatadores {
        /**
         * Formatar telefone (11) 98765-4321
         */
        static formatarTelefone(valor) {
            valor = valor.replace(/\D/g, '');
            if (valor.length <= 10) {
                valor = valor.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            } else {
                valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            return valor;
        }

        /**
         * Formatar CPF 123.456.789-00
         */
        static formatarCPF(valor) {
            valor = valor.replace(/\D/g, '');
            valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            return valor;
        }

        /**
         * Formatar moeda R$ 1.234,56
         */
        static formatarMoeda(valor) {
            valor = parseFloat(valor.replace(/\D/g, '')) / 100;
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        /**
         * Remover formatação
         */
        static removerFormatacao(valor) {
            return valor.replace(/\D/g, '');
        }
    }

    // ========================================
    // 4. APLICAR FORMATADORES
    // ========================================

    class AplicadorFormatadores {
        static inicializar() {
            // Telefone
            document.querySelectorAll('[data-format="telefone"]').forEach(campo => {
                campo.addEventListener('input', (e) => {
                    e.target.value = Formatadores.formatarTelefone(e.target.value);
                });
            });

            // CPF
            document.querySelectorAll('[data-format="cpf"]').forEach(campo => {
                campo.addEventListener('input', (e) => {
                    e.target.value = Formatadores.formatarCPF(e.target.value);
                });
            });

            // Moeda
            document.querySelectorAll('[data-format="moeda"]').forEach(campo => {
                campo.addEventListener('input', (e) => {
                    const valor = Formatadores.formatarMoeda(e.target.value);
                    e.target.value = valor;
                });
            });
        }
    }

    // ========================================
    // 5. INICIALIZAÇÃO
    // ========================================

    document.addEventListener('DOMContentLoaded', () => {
        // Inicializar validador de login
        new GerenciadorLogin();

        // Aplicar formatadores
        AplicadorFormatadores.inicializar();

        // Inicializar validação de formulário se houver campos
        const validador = new ValidadorFormulario();

        // Exemplo de campos a validar
        if (document.getElementById('resetEmail')) {
            validador.registrarCampo('resetEmail', [
                { tipo: 'obrigatorio', mensagem: 'E-mail obrigatório' },
                { tipo: 'email', mensagem: 'E-mail inválido' }
            ]);
        }

        // Tornar validador global se necessário
        window.validadorFormulario = validador;
        window.formatadores = Formatadores;
    });

    // ========================================
    // 6. UTILIDADES
    // ========================================

    window.UX = {
        /**
         * Mostrar toast notification
         */
        toast: function(mensagem, tipo = 'info', duracao = 3000) {
            const toastContainer = document.getElementById('toastContainer') || 
                                  this.criarToastContainer();

            const toast = document.createElement('div');
            toast.className = `alert alert-${tipo} alert-dismissible fade show`;
            toast.innerHTML = `
                ${mensagem}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            toastContainer.appendChild(toast);

            if (duracao > 0) {
                setTimeout(() => {
                    toast.remove();
                }, duracao);
            }
        },

        criarToastContainer: function() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        },

        /**
         * Mostrar loading
         */
        mostrarLoading: function(elemento) {
            elemento.classList.add('loading');
            elemento.disabled = true;
        },

        /**
         * Ocultar loading
         */
        ocultarLoading: function(elemento) {
            elemento.classList.remove('loading');
            elemento.disabled = false;
        }
    };

})();

/* FIM DO JAVASCRIPT */
