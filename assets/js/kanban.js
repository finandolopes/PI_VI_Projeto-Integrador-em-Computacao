/**
 * Kanban Board UI
 * Fase 2: Lead Management & CRM
 * 
 * Drag-and-drop lead management with real-time updates
 * @author CONFINTER
 * @version 1.0
 */

class KanbanBoard {
    
    constructor(containerId = '#kanbanContainer') {
        this.container = document.querySelector(containerId);
        this.board = [];
        this.draggingElement = null;
        this.dragSource = null;
        this.init();
    }
    
    /**
     * Inicializa o board
     */
    async init() {
        try {
            // Carregar board
            await this.loadBoard();
            
            // Renderizar
            this.render();
            
            // Configurar event listeners
            this.setupEventListeners();
            
            // Auto-refresh a cada 30 segundos
            setInterval(() => this.loadBoard(), 30000);
            
        } catch (error) {
            console.error('Erro ao inicializar kanban:', error);
            this.showError('Erro ao carregar kanban board');
        }
    }
    
    /**
     * Carrega dados do board via API
     */
    async loadBoard() {
        try {
            const response = await fetch('/api/kanban.phpaction=get_board', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) throw new Error('Erro ao carregar board');
            
            const data = await response.json();
            this.board = data.board || [];
            this.lastUpdate = data.generated_at;
            
        } catch (error) {
            console.error('Erro ao carregar board:', error);
            throw error;
        }
    }
    
    /**
     * Renderiza o board
     */
    render() {
        this.container.innerHTML = '';
        
        // Header
        const header = document.createElement('div');
        header.className = 'kanban-header';
        header.innerHTML = `
            <h2>📊 Kanban Board</h2>
            <div class="header-actions">
                <button class="btn btn-sm btn-primary" id="refreshBtn">
                    <i class="fas fa-sync"></i> Atualizar
                </button>
                <button class="btn btn-sm btn-secondary" id="settingsBtn">
                    <i class="fas fa-cog"></i> Config
                </button>
            </div>
        `;
        this.container.appendChild(header);
        
        // Board
        const boardContainer = document.createElement('div');
        boardContainer.className = 'kanban-board';
        
        // Render columns
        for (const column of this.board) {
            const columnEl = this.createColumn(column);
            boardContainer.appendChild(columnEl);
        }
        
        this.container.appendChild(boardContainer);
        
        // Footer com estatísticas
        const footer = document.createElement('div');
        footer.className = 'kanban-footer';
        footer.innerHTML = this.getStatsHTML();
        this.container.appendChild(footer);
    }
    
    /**
     * Cria elemento de coluna
     */
    createColumn(column) {
        const col = document.createElement('div');
        col.className = 'kanban-column';
        col.setAttribute('data-status', column.id);
        col.style.borderTopColor = column.color;
        
        // Header da coluna
        const header = document.createElement('div');
        header.className = 'column-header';
        header.innerHTML = `
            <h3>
                <span class="color-dot" style="background-color: ${column.color}"></span>
                ${column.title}
            </h3>
            <span class="count-badge">${column.count}</span>
        `;
        col.appendChild(header);
        
        // Lista de cards
        const cardsList = document.createElement('div');
        cardsList.className = 'cards-list';
        cardsList.setAttribute('data-status', column.id);
        
        for (const lead of column.leads) {
            const card = this.createCard(lead);
            cardsList.appendChild(card);
        }
        
        // Adicionar evento de drop
        cardsList.addEventListener('dragover', (e) => this.handleDragOver(e));
        cardsList.addEventListener('drop', (e) => this.handleDrop(e));
        
        col.appendChild(cardsList);
        
        return col;
    }
    
    /**
     * Cria elemento de card
     */
    createCard(lead) {
        const card = document.createElement('div');
        card.className = 'kanban-card';
        card.draggable = true;
        card.setAttribute('data-lead-id', lead.id);
        card.setAttribute('data-score', lead.score);
        
        // Determinar cor do score
        let scoreColor = '#3498db'; // Cool
        if (lead.score >= 80) scoreColor = '#27ae60'; // Hot
        else if (lead.score >= 60) scoreColor = '#f39c12'; // Warm
        
        // Avatar do vendedor
        const avatar = lead.assigned_to_avatar  
            `<img src="${lead.assigned_to_avatar}" alt="${lead.assigned_to_name}" class="avatar">` :
            `<div class="avatar-placeholder">${lead.assigned_to_name  lead.assigned_to_name.charAt(0).toUpperCase() : ''}</div>`;
        
        card.innerHTML = `
            <div class="card-header">
                <div class="score-badge" style="background-color: ${scoreColor}">
                    ${lead.score_label}
                </div>
                <div class="score-number">${lead.score}</div>
            </div>
            
            <div class="card-body">
                <h4 class="lead-name" title="${lead.name}">${this.truncate(lead.name, 25)}</h4>
                <p class="lead-company" title="${lead.company}">${this.truncate(lead.company, 20)}</p>
                <p class="lead-segment">${lead.segment}</p>
                <p class="lead-email" title="${lead.email}">${this.truncate(lead.email, 22)}</p>
            </div>
            
            <div class="card-footer">
                ${avatar}
                <span class="days-old">${this.getDaysOld(lead.created_at)}d</span>
            </div>
            
            <div class="card-actions">
                <button class="btn-action btn-view" data-lead-id="${lead.id}" title="Ver detalhes">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-action btn-edit" data-lead-id="${lead.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-action btn-delete" data-lead-id="${lead.id}" title="Deletar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        // Event listeners do card
        card.addEventListener('dragstart', (e) => this.handleDragStart(e));
        card.addEventListener('dragend', (e) => this.handleDragEnd(e));
        card.addEventListener('dragenter', (e) => this.handleDragEnter(e));
        card.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        
        return card;
    }
    
    /**
     * Handle drag start
     */
    handleDragStart(e) {
        this.draggingElement = e.target.closest('.kanban-card');
        this.dragSource = this.draggingElement.parentElement.getAttribute('data-status');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.draggingElement.innerHTML);
        this.draggingElement.classList.add('dragging');
    }
    
    /**
     * Handle drag end
     */
    handleDragEnd(e) {
        if (this.draggingElement) {
            this.draggingElement.classList.remove('dragging');
        }
    }
    
    /**
     * Handle drag over
     */
    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
    
    /**
     * Handle drag enter
     */
    handleDragEnter(e) {
        e.preventDefault();
    }
    
    /**
     * Handle drag leave
     */
    handleDragLeave(e) {
        e.preventDefault();
    }
    
    /**
     * Handle drop
     */
    handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!this.draggingElement) return;
        
        const toStatus = e.currentTarget.getAttribute('data-status');
        const leadId = parseInt(this.draggingElement.getAttribute('data-lead-id'));
        
        // Se é a mesma coluna, não faz nada
        if (this.dragSource === toStatus) {
            return;
        }
        
        // Mover card
        this.moveCard(leadId, this.dragSource, toStatus);
    }
    
    /**
     * Move lead para outro status
     */
    async moveCard(leadId, fromStatus, toStatus) {
        try {
            const response = await fetch('/api/kanban.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'move_card',
                    lead_id: leadId,
                    from_status: fromStatus,
                    to_status: toStatus
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`Lead movido para ${toStatus}`);
                await this.loadBoard();
                this.render();
            } else {
                this.showError(data.message || 'Erro ao mover lead');
                await this.loadBoard();
                this.render();
            }
            
        } catch (error) {
            console.error('Erro ao mover card:', error);
            this.showError('Erro ao mover card');
        }
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', async () => {
            await this.loadBoard();
            this.render();
            this.showSuccess('Board atualizado');
        });
        
        // Settings button
        document.getElementById('settingsBtn').addEventListener('click', () => {
            this.openSettings();
        });
        
        // Card actions
        this.container.addEventListener('click', (e) => {
            const viewBtn = e.target.closest('.btn-view');
            const editBtn = e.target.closest('.btn-edit');
            const deleteBtn = e.target.closest('.btn-delete');
            
            if (viewBtn) {
                const leadId = viewBtn.getAttribute('data-lead-id');
                this.viewLead(leadId);
            }
            
            if (editBtn) {
                const leadId = editBtn.getAttribute('data-lead-id');
                this.editLead(leadId);
            }
            
            if (deleteBtn) {
                const leadId = deleteBtn.getAttribute('data-lead-id');
                if (confirm('Tem certeza que deseja deletar este lead')) {
                    this.deleteLead(leadId);
                }
            }
        });
    }
    
    /**
     * Abre modal de visualização de lead
     */
    viewLead(leadId) {
        window.location.href = `/admin/lead-detail.phpid=${leadId}`;
    }
    
    /**
     * Abre modal de edição
     */
    editLead(leadId) {
        window.location.href = `/admin/edit-lead.phpid=${leadId}`;
    }
    
    /**
     * Deleta lead
     */
    async deleteLead(leadId) {
        try {
            const response = await fetch(`/api/leads.phpaction=delete&id=${leadId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            if (data.success) {
                this.showSuccess('Lead deletado');
                await this.loadBoard();
                this.render();
            }
        } catch (error) {
            console.error('Erro ao deletar lead:', error);
        }
    }
    
    /**
     * Abre settings
     */
    openSettings() {
        // Implementar modal de settings
        alert('Settings ainda não implementado');
    }
    
    /**
     * Obtém HTML com estatísticas
     */
    getStatsHTML() {
        const totals = this.board.reduce((acc, col) => {
            acc.total += col.count;
            return acc;
        }, { total: 0 });
        
        const conversions = this.board.find(col => col.id === 'won').count || 0;
        const rate = totals.total > 0  ((conversions / totals.total) * 100).toFixed(1) : '0';
        
        return `
            <div class="stats">
                <div class="stat-item">
                    <span class="stat-label">Total de Leads:</span>
                    <span class="stat-value">${totals.total}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Conversões:</span>
                    <span class="stat-value">${conversions}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Taxa de Conversão:</span>
                    <span class="stat-value">${rate}%</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Atualizado:</span>
                    <span class="stat-value">${this.lastUpdate || 'agora'}</span>
                </div>
            </div>
        `;
    }
    
    /**
     * Utilitários
     */
    truncate(str, length) {
        return str.length > length  str.substring(0, length) + '...' : str;
    }
    
    getDaysOld(createdAt) {
        const date = new Date(createdAt);
        const now = new Date();
        const days = Math.floor((now - date) / (1000 * 60 * 60 * 24));
        return days;
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success'  'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Inicializar quando documento estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    const kanban = new KanbanBoard('#kanbanContainer');
    window.kanban = kanban;
});
