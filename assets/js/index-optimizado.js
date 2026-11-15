/**
 * CONFINTER - JavaScript Otimizado
 * Funcionalidades com melhorias de acessibilidade e performance
 */

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // INICIALIZAR PLUGINS
    // ============================================
    
    initializeForm();
    initializeMobileNav();
    initializeAccessibility();
    registerPageView();
    checkErrorMessage();
    
    // ============================================
    // VERIFICAR MENSAGEM DE ERRO DO FORMULÁRIO
    // ============================================
    
    function checkErrorMessage() {
        // Verificar se a URL contém o parâmetro de erro
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('erro')) {
            // Rolar a página até a mensagem de erro
            const erroMensagem = document.getElementById('erro-mensagem');
            if (erroMensagem) {
                erroMensagem.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }
    
    // ============================================
    // FORMULÁRIO DE REQUISIÇÃO
    // ============================================
    
    function initializeForm() {
        const form = document.getElementById('form-requisicao');
        const dataNascimentoInput = document.getElementById('data_nascimento');
        const telefoneInput = document.getElementById('telefone');
        const outrosCheck = document.getElementById('outros_check');
        const outrosDiv = document.getElementById('outros_info_div');
        
        if (!form) return;
        
        // Aplicar máscara de data
        if (dataNascimentoInput && jQuery) {
            jQuery(dataNascimentoInput).mask('00/00/0000');
        }
        
        // Aplicar máscara de telefone
        if (telefoneInput && jQuery) {
            jQuery(telefoneInput).mask('(00) 00000-0000');
        }
        
        // Toggle campo "Outros"
        if (outrosCheck) {
            outrosCheck.addEventListener('change', function() {
                if (this.checked) {
                    outrosDiv.style.display = 'block';
                    outrosDiv.setAttribute('aria-hidden', 'false');
                } else {
                    outrosDiv.style.display = 'none';
                    outrosDiv.setAttribute('aria-hidden', 'true');
                }
            });
        }
        
        // Envio do formulário
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Validações
            const isValid = validateRequiredFields();
            
            if (isValid) {
                // Formatar data antes de enviar
                if (dataNascimentoInput.value) {
                    const dataNascimento = dataNascimentoInput.value;
                    const dataFormatada = dataNascimento.replace(/(\d{2})[-\/](\d{2})[-\/](\d{4})/, '$3-$2-$1');
                    dataNascimentoInput.value = dataFormatada;
                }
                
                // SweetAlert2 - Confirmação
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Requisição Enviada',
                        text: 'Sua requisição foi enviada com sucesso! Entraremos em contato em breve.',
                        timer: 3000,
                        showConfirmButton: false,
                        didClose: () => form.submit()
                    });
                } else {
                    form.submit();
                }
            }
        });
    }
    
    function validateRequiredFields() {
        const errors = [];
        const nome = document.getElementById('nome');
        const telefone = document.getElementById('telefone');
        const email = document.getElementById('email');
        const horario = document.getElementById('horario_contato');
        const categorias = document.querySelectorAll('input[name="categoria[]"]:checked');
        
        // Validar Nome
        if (!nome || !nome.value.trim()) {
            errors.push('Nome');
            nome.setAttribute('aria-invalid', 'true');
        } else {
            nome.setAttribute('aria-invalid', 'false');
        }
        
        // Validar Telefone
        if (!telefone || !telefone.value.trim()) {
            errors.push('Telefone');
            telefone.setAttribute('aria-invalid', 'true');
        } else {
            telefone.setAttribute('aria-invalid', 'false');
        }
        
        // Validar Email
        if (!email || !email.value.trim() || !isValidEmail(email.value)) {
            errors.push('Email válido');
            email.setAttribute('aria-invalid', 'true');
        } else {
            email.setAttribute('aria-invalid', 'false');
        }
        
        // Validar Horário
        if (!horario || !horario.value.trim()) {
            errors.push('Horário de Contato');
            horario.setAttribute('aria-invalid', 'true');
        } else {
            horario.setAttribute('aria-invalid', 'false');
        }
        
        // Validar Categorias
        if (categorias.length === 0) {
            errors.push('Selecione uma categoria');
        }
        
        if (errors.length > 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro na Validação',
                    html: `Os seguintes campos estão incompletos:<br><br><strong>${errors.join('<br>')}</strong>`,
                    confirmButtonText: 'OK'
                });
            } else {
                alert(`Campos incompletos:\n\n${errors.join('\n')}`);
            }
            return false;
        }
        
        return true;
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // ============================================
    // FORMULÁRIO DE DEPOIMENTO
    // ============================================
    
    const formDepoimento = document.getElementById('form-depoimento');
    if (formDepoimento) {
        formDepoimento.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const nome = document.querySelector('#form-depoimento input[name="nome"]');
            const mensagem = document.querySelector('#form-depoimento textarea[name="mensagem"]');
            
            // Validar campos
            if (!mensagem || !mensagem.value.trim()) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Campo obrigatório',
                        text: 'Por favor, escreva um depoimento.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Por favor, escreva um depoimento.');
                }
                return;
            }
            
            // Sucesso
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Depoimento Enviado',
                    text: 'Obrigado pelo seu depoimento! Será exibido após aprovação.',
                    timer: 3000,
                    showConfirmButton: false,
                    didClose: () => formDepoimento.submit()
                });
            } else {
                formDepoimento.submit();
            }
        });
    }
    
    // ============================================
    // NAVEGAÇÃO MÓVEL
    // ============================================
    
    function initializeMobileNav() {
        const mobileToggle = document.querySelector('.mobile-nav-toggle');
        const navbar = document.querySelector('.navbar');
        
        if (!mobileToggle || !navbar) return;
        
        // Toggle de menu
        mobileToggle.addEventListener('click', function() {
            navbar.classList.toggle('active');
            this.setAttribute('aria-expanded', navbar.classList.contains('active')  'true' : 'false');
        });
        
        // Fechar menu ao clicar em um link
        const navLinks = document.querySelectorAll('.navbar a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navbar.classList.remove('active');
                mobileToggle.setAttribute('aria-expanded', 'false');
            });
        });
        
        // Fechar menu ao clicar fora
        document.addEventListener('click', function(event) {
            if (!navbar.contains(event.target) && !mobileToggle.contains(event.target)) {
                navbar.classList.remove('active');
                mobileToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    // ============================================
    // ACESSIBILIDADE
    // ============================================
    
    function initializeAccessibility() {
        // Detectar preferência de movimento reduzido
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (prefersReducedMotion.matches) {
            document.body.classList.add('reduce-motion');
        }
        
        // Atalhos de teclado
        setupKeyboardShortcuts();
        
        // Skip to main content
        addSkipLink();
    }
    
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Tecla: s = scroll para formulário
            if (e.key === 's' && e.ctrlKey) {
                e.preventDefault();
                const form = document.getElementById('form-requisicao');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    form.focus();
                }
            }
            
            // Tecla: l = scroll para login
            if (e.key === 'l' && e.ctrlKey) {
                e.preventDefault();
                const loginBtn = document.querySelector('[data-bs-target="#loginModal"]');
                if (loginBtn) {
                    loginBtn.click();
                }
            }
        });
    }
    
    function addSkipLink() {
        if (document.querySelector('.skip-to-main')) return;
        
        const skipLink = document.createElement('a');
        skipLink.className = 'skip-to-main';
        skipLink.href = '#main';
        skipLink.textContent = 'Pular para conteúdo principal';
        skipLink.setAttribute('aria-label', 'Pular para conteúdo principal');
        document.body.prepend(skipLink);
    }
    
    // ============================================
    // REGISTRO DE VISITAS
    // ============================================
    
    function registerPageView() {
        fetch('admin/contador.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => {
            if (response.ok) {
                console.log('Visita registrada com sucesso');
            }
        })
        .catch(error => {
            console.error('Erro ao registrar visita:', error);
        });
    }
    
    // ============================================
    // SCROLL TO TOP BUTTON
    // ============================================
    
    const backToTopBtn = document.querySelector('.back-to-top');
    if (backToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 100) {
                backToTopBtn.style.display = 'flex';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });
        
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // ============================================
    // HEADER SCROLL EFFECT
    // ============================================
    
    const header = document.getElementById('header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 100) {
                header.classList.add('header-scrolled');
            } else {
                header.classList.remove('header-scrolled');
            }
        });
    }
    
    // ============================================
    // SMOOTH SCROLL PARA LINKS INTERNOS
    // ============================================
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Focar no elemento para acessibilidade
                    target.focus();
                }
            }
        });
    });
    
    // ============================================
    // LAZY LOADING PARA IMAGENS
    // ============================================
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// ============================================
// MENU FLUTUANTE - INTERAÇÃO
// ============================================

document.querySelectorAll('.floating-buttons a').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.1)';
    });
    
    btn.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
});

// ============================================
// MODO ESCURO (Opcional - com localStorage)
// ============================================

function initializeDarkMode() {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    const savedTheme = localStorage.getItem('theme');
    
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        }
    }
    
    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (prefersDark.matches) {
        applyTheme('dark');
    }
    
    prefersDark.addListener((e) => {
        applyTheme(e.matches  'dark' : 'light');
    });
}

// Chamar após DOM estar pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDarkMode);
} else {
    initializeDarkMode();
}
