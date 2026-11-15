/**
 * ========================================
 * ADVANCED LOAN SIMULATOR v2.0
 * ========================================
 * Simulador profissional com dados do Banco Central
 * Data: 07/02/2026
 * Integrações: Disclaimer, Banco Central, Corretor, Analytics
 */

class AdvancedLoanSimulator {
    constructor(containerId = 'advanced-simulator', options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Container não encontrado: ' + containerId);
            return;
        }

        this.options = {
            minAmount: 1000,
            maxAmount: 100000,
            minMonths: 12,
            maxMonths: 120,
            whatsappNumber: options.whatsappNumber || '5511948016298',
            correctorEmail: options.correctorEmail || 'contato@confinter.com.br',
            analyticsEvent: options.analyticsEvent || 'loan_simulator',
            enableComparison: options.enableComparison !== false,
            enableAmortization: options.enableAmortization !== false,
            ...options
        };

        // Taxas do Banco Central (dados oficiais)
        this.rates = {
            'servidor_publico': { rate: 1.93, name: 'Servidor Público', min_rate: 1.93, source: 'BC 2026' },
            'inss': { rate: 1.72, name: 'Aposentado/Pensionista INSS', min_rate: 1.72, source: 'BC 2026' },
            'clt': { rate: 2.95, name: 'Funcionário CLT', min_rate: 2.95, source: 'Estimado' },
            'pj': { rate: 3.50, name: 'PJ/Autônomo', min_rate: 3.50, source: 'Estimado' },
            'aposentado': { rate: 1.72, name: 'Aposentado Servidor Público', min_rate: 1.72, source: 'BC 2026' },
            'fgts': { rate: 1.29, name: 'Saque Aniversário FGTS', min_rate: 1.29, source: 'BC 2026' }
        };

        this.init();
    }

    init() {
        this.render();
        this.attachEventListeners();
        this.trackAnalytics('simulator_loaded');
    }

    render() {
        this.container.innerHTML = `
            <div class="advanced-simulator-wrapper">
                <!-- DISCLAIMER -->
                <div class="simulation-disclaimer" role="alert" aria-label="Aviso de simulação">
                    <div class="disclaimer-icon">⚠️</div>
                    <div class="disclaimer-content">
                        <h3>Simulação Apenas</h3>
                        <p>Esta é uma <strong>SIMULAÇÃO</strong> para fins ilustrativos. Os valores reais devem ser <strong>validados com nossos corretores</strong> antes da contratação. As taxas podem variar conforme políticas de crédito das instituições financeiras parceiras.</p>
                        <p><small>📚 Fonte: Banco Central do Brasil - Taxas máximas oficiais</small></p>
                    </div>
                    <button class="disclaimer-close" type="button" aria-label="Fechar aviso" onclick="this.parentElement.style.display='none';">×</button>
                </div>

                <!-- SIMULADOR -->
                <div class="simulator-content">
                    <h2>💰 Simulador de Empréstimo Consignado</h2>
                    <p class="subtitle">Informe seus dados para simular a melhor opção para você</p>

                    <form id="simulator-form" novalidate>
                        <!-- Valor do Empréstimo -->
                        <div class="form-group">
                            <label for="loan-amount">
                                💵 Valor desejado: 
                                <span class="amount-display">R$ 10.000</span>
                            </label>
                            <input 
                                type="range" 
                                id="loan-amount" 
                                min="${this.options.minAmount}" 
                                max="${this.options.maxAmount}" 
                                step="500" 
                                value="10000"
                                class="slider amount-slider"
                                aria-label="Valor do empréstimo"
                            >
                            <div class="slider-labels">
                                <span>R$ ${this.formatCurrency(this.options.minAmount)}</span>
                                <span>R$ ${this.formatCurrency(this.options.maxAmount)}</span>
                            </div>
                        </div>

                        <!-- Prazo -->
                        <div class="form-group">
                            <label for="loan-months">
                                📅 Prazo desejado: 
                                <span class="months-display">24 meses</span>
                            </label>
                            <input 
                                type="range" 
                                id="loan-months" 
                                min="${this.options.minMonths}" 
                                max="${this.options.maxMonths}" 
                                step="1" 
                                value="24"
                                class="slider months-slider"
                                aria-label="Prazo em meses"
                            >
                            <div class="slider-labels">
                                <span>${this.options.minMonths} meses</span>
                                <span>${this.options.maxMonths} meses</span>
                            </div>
                        </div>

                        <!-- Tipo de Renda -->
                        <div class="form-group">
                            <label for="income-type">👤 Tipo de renda:</label>
                            <select id="income-type" class="form-control" aria-label="Selecione seu tipo de renda">
                                ${Object.entries(this.rates).map(([key, data]) => `
                                    <option value="${key}">${data.name} - ${data.rate.toFixed(2)}% a.m.</option>
                                `).join('')}
                            </select>
                            <small class="rate-source">Fonte: <span id="rate-source">Banco Central</span></small>
                        </div>
                    </form>

                    <!-- RESULTADOS -->
                    <div id="results" class="results-container" role="region" aria-label="Resultado da simulação" aria-live="polite">
                        <div class="result-card">
                            <div class="result-header">
                                <h3>📊 Resultado da Simulação</h3>
                            </div>
                            <div class="results-grid">
                                <div class="result-item">
                                    <span class="label">Taxa Mensal:</span>
                                    <span class="value" id="result-rate">1,93%</span>
                                </div>
                                <div class="result-item">
                                    <span class="label">Valor da Parcela:</span>
                                    <span class="value highlight" id="result-installment">R$ 520,45</span>
                                </div>
                                <div class="result-item">
                                    <span class="label">Total de Juros:</span>
                                    <span class="value" id="result-total-interest">R$ 2.490,80</span>
                                </div>
                                <div class="result-item">
                                    <span class="label">Valor Total:</span>
                                    <span class="value big" id="result-total">R$ 12.490,80</span>
                                </div>
                            </div>
                        </div>

                        <!-- COMPARATIVO -->
                        <div class="comparison-container" role="region" aria-label="Comparativo com outras linhas de crédito">
                            <h3>📈 Por que Consignado</h3>
                            <p class="comparison-intro">Veja quanto você economiza com empréstimo consignado:</p>
                            <div class="comparison-items">
                                <div class="comparison-item">
                                    <div class="option-header">
                                        <span class="option-name">💳 Cartão de Crédito</span>
                                        <span class="option-rate">(15% a.m.)</span>
                                    </div>
                                    <div class="option-value" id="comp-card-value">R$ 43.200</div>
                                    <div class="savings" id="comp-card-savings">Você economiza R$ 32.709,20</div>
                                </div>
                                <div class="comparison-item">
                                    <div class="option-header">
                                        <span class="option-name">📊 Cheque Especial</span>
                                        <span class="option-rate">(10% a.m.)</span>
                                    </div>
                                    <div class="option-value" id="comp-check-value">R$ 28.000</div>
                                    <div class="savings" id="comp-check-savings">Você economiza R$ 15.509,20</div>
                                </div>
                                <div class="comparison-item comparison-highlight">
                                    <div class="option-header">
                                        <span class="option-name">✅ Consignado CONFINTER</span>
                                        <span class="option-rate">MELHOR OPÇÃO</span>
                                    </div>
                                    <div class="option-value" id="comp-consignado-value">R$ 12.490,80</div>
                                    <div class="savings">Parcela a partir de <strong id="comp-consignado-installment">R$ 520,45</strong></div>
                                </div>
                            </div>
                        </div>

                        <!-- AÇÕES -->
                        <div class="simulator-actions">
                            <button type="button" class="btn btn-primary btn-lg" id="btn-whatsapp" aria-label="Conversar com especialista via WhatsApp">
                                💬 FALE COM ESPECIALISTA
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btn-email" aria-label="Enviar simulação por e-mail">
                                ✉️ ENVIAR POR E-MAIL
                            </button>
                            ${this.options.enableAmortization ? `
                            <button type="button" class="btn btn-outline btn-lg" id="btn-amortization" aria-label="Ver tabela de amortização detalhada">
                                📋 VER TABELA DE AMORTIZAÇÃO
                            </button>
                            ` : ''}
                        </div>
                    </div>

                    <!-- MODAL AMORTIZAÇÃO -->
                    ${this.options.enableAmortization ? `
                    <div id="amortization-modal" class="modal-advanced" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="amort-title">
                        <div class="modal-overlay"></div>
                        <div class="modal-content-advanced">
                            <div class="modal-header">
                                <h3 id="amort-title">📋 Tabela de Amortização</h3>
                                <button type="button" class="close-btn" id="close-amortization" aria-label="Fechar tabela">×</button>
                            </div>
                            <div class="modal-body">
                                <div class="table-wrapper">
                                    <table id="amortization-table" role="table">
                                        <thead>
                                            <tr role="row">
                                                <th role="columnheader">Parcela</th>
                                                <th role="columnheader">Vencimento</th>
                                                <th role="columnheader">Principal</th>
                                                <th role="columnheader">Juros</th>
                                                <th role="columnheader">Total</th>
                                                <th role="columnheader">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody id="amortization-body">
                                            <!-- Gerado por JS -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-secondary" id="btn-print-table" aria-label="Imprimir tabela">
                                        🖨️ IMPRIMIR
                                    </button>
                                    <button type="button" class="btn btn-primary" id="btn-download-table" aria-label="Fazer download em PDF">
                                        📥 BAIXAR PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    attachEventListeners() {
        const amountInput = document.getElementById('loan-amount');
        const monthsInput = document.getElementById('loan-months');
        const incomeSelect = document.getElementById('income-type');
        const whatsappBtn = document.getElementById('btn-whatsapp');
        const emailBtn = document.getElementById('btn-email');
        const amortizationBtn = document.getElementById('btn-amortization');

        // Eventos principais
        amountInput.addEventListener('input', () => this.update());
        monthsInput.addEventListener('input', () => this.update());
        incomeSelect.addEventListener('change', () => this.update());

        // Botões
        whatsappBtn.addEventListener('click', () => this.openWhatsApp());
        emailBtn.addEventListener('click', () => this.sendEmail());
        
        if (amortizationBtn) {
            amortizationBtn.addEventListener('click', () => this.showAmortizationTable());
            document.getElementById('close-amortization').addEventListener('click', () => this.closeAmortization());
        }

        // Modal overlay click
        const modal = document.getElementById('amortization-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('modal-overlay')) {
                    this.closeAmortization();
                }
            });

            // Print e Download
            document.getElementById('btn-print-table').addEventListener('click', () => this.printTable());
            document.getElementById('btn-download-table').addEventListener('click', () => this.downloadPDF());
        }

        // Atualizar display inicial
        this.update();
    }

    update() {
        const amount = parseFloat(document.getElementById('loan-amount').value);
        const months = parseInt(document.getElementById('loan-months').value);
        const incomeType = document.getElementById('income-type').value;
        const rateData = this.rates[incomeType];
        const rate = rateData.rate;

        // Atualizar displays
        document.querySelector('.amount-display').textContent = 'R$ ' + this.formatCurrency(amount);
        document.querySelector('.months-display').textContent = months + ' meses';
        document.getElementById('rate-source').textContent = rateData.source;

        // Calcular
        const calc = this.calculateLoan(amount, months, rate);

        // Atualizar resultados
        document.getElementById('result-rate').textContent = rate.toFixed(2) + '%';
        document.getElementById('result-installment').textContent = 'R$ ' + this.formatCurrency(calc.installment);
        document.getElementById('result-total-interest').textContent = 'R$ ' + this.formatCurrency(calc.totalInterest);
        document.getElementById('result-total').textContent = 'R$ ' + this.formatCurrency(calc.totalAmount);

        // Atualizar comparativos
        this.updateComparisons(amount, months, calc);

        // Armazenar para WhatsApp/Email
        this.currentLoan = calc;
        this.currentAmount = amount;
        this.currentMonths = months;
        this.currentRate = rate;
        this.currentIncomeType = rateData.name;

        this.trackAnalytics('simulator_updated', {
            amount: amount,
            months: months,
            income_type: incomeType,
            monthly_installment: calc.installment
        });
    }

    calculateLoan(amount, months, ratePercentage) {
        const monthlyRate = ratePercentage / 100;
        
        if (monthlyRate == 0) {
            return {
                amount,
                months,
                rate: ratePercentage,
                installment: amount / months,
                totalAmount: amount,
                totalInterest: 0
            };
        }
        
        // Fórmula: P = V * [i(1+i)^n] / [(1+i)^n-1]
        const numerator = monthlyRate * Math.pow(1 + monthlyRate, months);
        const denominator = Math.pow(1 + monthlyRate, months) - 1;
        const installment = amount * (numerator / denominator);
        
        const totalAmount = installment * months;
        const totalInterest = totalAmount - amount;

        return {
            amount,
            months,
            rate: ratePercentage,
            installment,
            totalAmount,
            totalInterest
        };
    }

    updateComparisons(amount, months, calc) {
        // Cartão (15% a.m.)
        const creditCardRate = 0.15;
        const creditCardTotal = amount * Math.pow(1 + creditCardRate, months);
        const creditCardSavings = creditCardTotal - calc.totalAmount;

        // Cheque (10% a.m.)
        const checkRate = 0.10;
        const checkTotal = amount * Math.pow(1 + checkRate, months);
        const checkSavings = checkTotal - calc.totalAmount;

        // Atualizar na DOM
        document.getElementById('comp-card-value').textContent = 'R$ ' + this.formatCurrency(creditCardTotal);
        document.getElementById('comp-card-savings').innerHTML = 
            `<strong>💰 Você economiza R$ ${this.formatCurrency(creditCardSavings)}</strong>`;

        document.getElementById('comp-check-value').textContent = 'R$ ' + this.formatCurrency(checkTotal);
        document.getElementById('comp-check-savings').innerHTML = 
            `<strong>💰 Você economiza R$ ${this.formatCurrency(checkSavings)}</strong>`;

        document.getElementById('comp-consignado-installment').textContent = 'R$ ' + this.formatCurrency(calc.installment);
    }

    showAmortizationTable() {
        const modal = document.getElementById('amortization-modal');
        const tbody = document.getElementById('amortization-body');
        
        const amortization = this.generateAmortizationTable(
            this.currentAmount,
            this.currentMonths,
            this.currentRate
        );

        tbody.innerHTML = '';
        amortization.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.setAttribute('role', 'row');
            tr.innerHTML = `
                <td role="cell">${row.month}</td>
                <td role="cell">${row.dueDate}</td>
                <td role="cell">R$ ${this.formatCurrency(row.principal)}</td>
                <td role="cell">R$ ${this.formatCurrency(row.interest)}</td>
                <td role="cell"><strong>R$ ${this.formatCurrency(row.payment)}</strong></td>
                <td role="cell">R$ ${this.formatCurrency(row.balance)}</td>
            `;
            tbody.appendChild(tr);
        });

        modal.style.display = 'block';
        this.trackAnalytics('amortization_viewed');
    }

    closeAmortization() {
        const modal = document.getElementById('amortization-modal');
        if (modal) modal.style.display = 'none';
    }

    generateAmortizationTable(amount, months, ratePercentage) {
        const calc = this.calculateLoan(amount, months, ratePercentage);
        const table = [];
        let balance = amount;
        const monthlyRate = ratePercentage / 100;
        const startDate = new Date();

        for (let i = 1; i <= months; i++) {
            const interest = balance * monthlyRate;
            const principal = calc.installment - interest;
            balance -= principal;

            const dueDate = new Date(startDate);
            dueDate.setMonth(dueDate.getMonth() + i);

            table.push({
                month: i,
                dueDate: dueDate.toLocaleDateString('pt-BR'),
                principal: Math.max(0, principal),
                interest: interest,
                payment: calc.installment,
                balance: Math.max(0, balance)
            });
        }

        return table;
    }

    openWhatsApp() {
        const message = `Olá! 👋 Gostaria de mais informações sobre meu empréstimo simulado:

💵 Valor: R$ ${this.formatCurrency(this.currentAmount)}
📅 Prazo: ${this.currentMonths} meses
💰 Parcela: R$ ${this.formatCurrency(this.currentLoan.installment)} a.m.
📊 Taxa: ${this.currentRate.toFixed(2)}% a.m.
👤 Perfil: ${this.currentIncomeType}

Gostaria de validar esta simulação com um corretor.`;

        const encodedMessage = encodeURIComponent(message);
        const url = `https://wa.me/${this.options.whatsappNumber}?text=${encodedMessage}`;
        
        this.trackAnalytics('simulator_whatsapp_clicked', { amount: this.currentAmount });
        window.open(url, '_blank');
    }

    sendEmail() {
        const subject = encodeURIComponent(`Simulação de Empréstimo - R$ ${this.formatCurrency(this.currentAmount)}`);
        const body = encodeURIComponent(`
Olá,

Gostaria de validar a simulação de empréstimo abaixo:

Valor: R$ ${this.formatCurrency(this.currentAmount)}
Prazo: ${this.currentMonths} meses
Parcela: R$ ${this.formatCurrency(this.currentLoan.installment)} a.m.
Taxa: ${this.currentRate.toFixed(2)}% a.m.
Perfil: ${this.currentIncomeType}

Favor, entrar em contato para próximos passos.

Atenciosamente
        `);

        const url = `mailto:${this.options.correctorEmail}?subject=${subject}&body=${body}`;
        
        this.trackAnalytics('simulator_email_clicked', { amount: this.currentAmount });
        window.location.href = url;
    }

    printTable() {
        window.print();
        this.trackAnalytics('amortization_printed');
    }

    downloadPDF() {
        // Usar jsPDF para gerar PDF
        if (typeof jsPDF === 'undefined') {
            alert('Biblioteca PDF não disponível. Use print para salvar como PDF.');
            this.printTable();
            return;
        }

        this.trackAnalytics('amortization_downloaded');
        alert('Download PDF implementado em próxima versão');
    }

    formatCurrency(value) {
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    trackAnalytics(event, data = {}) {
        // Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', this.options.analyticsEvent + '_' + event, data);
        }
        
        // Console para debug
        console.log(`[Simulator Analytics] ${event}`, data);
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    const simulator = new AdvancedLoanSimulator('advanced-simulator', {
        whatsappNumber: '5511948016298',
        correctorEmail: 'contato@confinter.com.br'
    });

    // Exposição global para debug
    window.loanSimulator = simulator;
});
