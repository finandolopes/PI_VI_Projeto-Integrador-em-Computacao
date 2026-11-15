/**
 * ========================================
 * DASHBOARD REALTIME - Atualizações em Tempo Real
 * ========================================
 * Conecta ao servidor via SSE para atualizar métricas
 * Data: 07/02/2026
 */

class DashboardRealtime {
    constructor(options = {}) {
        this.options = {
            sseEndpoint: 'sse-metrics.php',
            reconnectInterval: 5000,
            updateInterval: 30000,
            ...options
        };

        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.init();
    }

    init() {
        this.setupSSE();
        this.setupMetricsUpdates();
    }

    setupSSE() {
        console.log('Conectando ao SSE...');
        
        this.eventSource = new EventSource(this.options.sseEndpoint);

        this.eventSource.onopen = () => {
            console.log('SSE conectado');
            this.reconnectAttempts = 0;
            this.showConnectionStatus('online');
        };

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.updateDashboard(data);
            } catch (e) {
                console.error('Erro ao parsear dados SSE:', e);
            }
        };

        this.eventSource.onerror = () => {
            console.error('Erro no SSE');
            this.showConnectionStatus('offline');
            this.eventSource.close();
            this.attemptReconnect();
        };
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Tentando reconectar... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
            
            setTimeout(() => {
                this.setupSSE();
            }, this.options.reconnectInterval * this.reconnectAttempts);
        } else {
            console.error('Falha ao reconectar ao SSE após múltiplas tentativas');
            this.showAlert('Erro ao conectar ao servidor', 'danger');
        }
    }

    updateDashboard(data) {
        // Atualizar métricas com animação
        this.animateValue('card-visitas', data.visitas);
        this.animateValue('card-depoimentos', data.depoimentos);
        this.animateValue('card-requisicoes', data.requisicoes);
        this.animateValue('card-taxa', data.taxa, true);
        
        // Atualizar usuários online
        const onlineElement = document.getElementById('usuarios-online');
        if (onlineElement) {
            this.animateValue('usuarios-online', data.usuarios_online);
        }

        // Atualizar timestamp
        this.updateTimestamp(data.timestamp);

        // Atualizar gráficos se existirem
        if (typeof updateCharts === 'function') {
            updateCharts(data);
        }

        // Trigger custom event
        const event = new CustomEvent('dashboardUpdated', { detail: data });
        document.dispatchEvent(event);
    }

    animateValue(elementId, newValue, isPercentage = false) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const oldValue = parseInt(element.textContent) || 0;
        
        // Se o valor não mudou, não animar
        if (oldValue === newValue) return;

        const duration = 500; // ms
        const steps = 20;
        const stepValue = (newValue - oldValue) / steps;
        let currentStep = 0;

        const animate = () => {
            currentStep++;
            const current = oldValue + (stepValue * currentStep);
            
            if (isPercentage) {
                element.textContent = Math.round(current) + '%';
            } else {
                element.textContent = Math.round(current);
            }

            if (currentStep < steps) {
                setTimeout(animate, duration / steps);
            } else {
                element.textContent = isPercentage  newValue + '%' : newValue;
            }
        };

        animate();
    }

    updateTimestamp(timestamp) {
        const element = document.getElementById('last-update-time');
        if (element) {
            element.textContent = `Atualizado em ${timestamp}`;
            element.classList.add('pulse');
            
            setTimeout(() => {
                element.classList.remove('pulse');
            }, 1000);
        }
    }

    setupMetricsUpdates() {
        // Atualizar manualmente a cada intervalo como fallback
        setInterval(() => {
            this.refreshMetrics();
        }, this.options.updateInterval);
    }

    refreshMetrics() {
        // Fazer fetch dos dados como fallback se SSE não estiver funcionando
        if (!this.eventSource || this.eventSource.readyState !== EventSource.OPEN) {
            fetch(this.options.sseEndpoint)
                .then(response => response.json())
                .then(data => {
                    this.updateDashboard(data);
                })
                .catch(error => {
                    console.error('Erro ao atualizar métricas:', error);
                });
        }
    }

    showConnectionStatus(status) {
        const statusElement = document.getElementById('connection-status');
        if (!statusElement) return;

        if (status === 'online') {
            statusElement.className = 'badge bg-success';
            statusElement.textContent = '● Online';
        } else {
            statusElement.className = 'badge bg-danger';
            statusElement.textContent = '● Offline';
        }
    }

    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}

// Auto-inicializar quando o dashboard estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    const dashboardContainer = document.querySelector('[data-dashboard="realtime"]');
    if (dashboardContainer) {
        window.dashboardRealtime = new DashboardRealtime();
    }
});

// Desconectar ao sair da página
window.addEventListener('beforeunload', () => {
    if (window.dashboardRealtime) {
        window.dashboardRealtime.disconnect();
    }
});
