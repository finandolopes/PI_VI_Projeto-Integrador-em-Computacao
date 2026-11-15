/**
 * Chat Widget - Floating Chat Bar
 * Fase 3: Communication & Automation
 * 
 * Widget de chat flutuante para leads
 * - Pre-qualificação em tempo real
 * - Interface mobile-responsive
 * - Histórico de mensagens
 * - Roteamento para agente humano
 * 
 * @author CONFINTER
 * @version 1.0
 */

class ChatWidget {
    constructor(options = {}) {
        this.apiUrl = options.apiUrl || '/api/chat-api.php';
        this.position = options.position || 'bottom-right';
        this.theme = options.theme || 'light';
        this.initialMessage = options.initialMessage || 'Olá! 👋 Como posso ajudar';
        this.leadId = null;
        this.sessionId = this.generateSessionId();
        this.messages = [];
        this.isOpen = false;
        this.isLoading = false;
        this.currentUser = null;
        
        this.init();
    }
    
    /**
     * Inicializa o widget
     */
    init() {
        this.injectStyles();
        this.createHTML();
        this.attachEventListeners();
        this.loadChatHistory();
    }
    
    /**
     * Injeta estilos CSS
     */
    injectStyles() {
        const styles = `
            .confinter-chat-widget {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                --primary: #007bff;
                --secondary: #6c757d;
                --success: #28a745;
                --danger: #dc3545;
                --light: #f8f9fa;
                --dark: #343a40;
            }
            
            .chat-widget-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--primary) 0%, #0056b3 100%);
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
                transition: all 0.3s ease;
                z-index: 9998;
            }
            
            .chat-widget-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 16px rgba(0, 123, 255, 0.4);
            }
            
            .chat-widget-button.open {
                opacity: 0;
                pointer-events: none;
            }
            
            .chat-widget-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 400px;
                max-height: 600px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
                display: flex;
                flex-direction: column;
                opacity: 0;
                transform: scale(0.95) translateY(20px);
                pointer-events: none;
                transition: all 0.3s ease;
                z-index: 9999;
            }
            
            .chat-widget-container.open {
                opacity: 1;
                transform: scale(1) translateY(0);
                pointer-events: all;
            }
            
            .chat-header {
                background: linear-gradient(135deg, var(--primary) 0%, #0056b3 100%);
                color: white;
                padding: 20px;
                border-radius: 12px 12px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .chat-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            }
            
            .chat-header-subtitle {
                font-size: 12px;
                opacity: 0.9;
            }
            
            .chat-close-btn {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 20px;
                padding: 0;
                transition: transform 0.2s;
            }
            
            .chat-close-btn:hover {
                transform: rotate(90deg);
            }
            
            .chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
                background: var(--light);
            }
            
            .chat-message {
                display: flex;
                margin-bottom: 12px;
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .chat-message.user {
                justify-content: flex-end;
            }
            
            .chat-message-content {
                max-width: 70%;
                padding: 12px 16px;
                border-radius: 12px;
                line-height: 1.4;
                word-wrap: break-word;
            }
            
            .chat-message.bot .chat-message-content {
                background: white;
                color: var(--dark);
                border: 1px solid #e0e0e0;
            }
            
            .chat-message.user .chat-message-content {
                background: var(--primary);
                color: white;
                border-radius: 12px 2px 12px 12px;
            }
            
            .chat-message-time {
                font-size: 11px;
                color: #999;
                margin-top: 4px;
                padding: 0 16px;
            }
            
            .chat-input-area {
                padding: 16px;
                background: white;
                border-top: 1px solid #e0e0e0;
                display: flex;
                gap: 8px;
            }
            
            .chat-input {
                flex: 1;
                padding: 10px 16px;
                border: 1px solid #ddd;
                border-radius: 20px;
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }
            
            .chat-input:focus {
                border-color: var(--primary);
            }
            
            .chat-send-btn {
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
                font-size: 18px;
            }
            
            .chat-send-btn:hover {
                background: #0056b3;
                transform: scale(1.05);
            }
            
            .chat-send-btn:active {
                transform: scale(0.95);
            }
            
            .chat-loading {
                display: flex;
                gap: 4px;
                padding: 12px 16px;
            }
            
            .chat-loading-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #999;
                animation: bounce 1.4s infinite;
            }
            
            .chat-loading-dot:nth-child(2) {
                animation-delay: 0.2s;
            }
            
            .chat-loading-dot:nth-child(3) {
                animation-delay: 0.4s;
            }
            
            @keyframes bounce {
                0%, 80%, 100% {
                    opacity: 0.5;
                    transform: translateY(0);
                }
                40% {
                    opacity: 1;
                    transform: translateY(-8px);
                }
            }
            
            .chat-qualifier {
                background: #f0f0f0;
                padding: 12px;
                border-radius: 8px;
                margin: 8px 0;
            }
            
            .chat-qualifier-question {
                font-size: 13px;
                font-weight: 500;
                margin-bottom: 8px;
            }
            
            .chat-qualifier-options {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .chat-option-btn {
                background: white;
                border: 1px solid #ddd;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s;
                text-align: left;
            }
            
            .chat-option-btn:hover {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }
            
            .chat-badge {
                display: inline-block;
                background: var(--success);
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 8px;
            }
            
            @media (max-width: 480px) {
                .chat-widget-container {
                    width: 100%;
                    max-height: 100vh;
                    right: 0;
                    bottom: 0;
                    border-radius: 0;
                }
                
                .chat-widget-button {
                    bottom: 16px;
                    right: 16px;
                }
            }
            
            .chat-typing-indicator {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 12px 16px;
            }
            
            .chat-dot {
                width: 8px;
                height: 8px;
                background: #999;
                border-radius: 50%;
                animation: pulse 1.4s infinite;
            }
            
            .chat-dot:nth-child(2) { animation-delay: 0.2s; }
            .chat-dot:nth-child(3) { animation-delay: 0.4s; }
            
            @keyframes pulse {
                0%, 60%, 100% { opacity: 0.3; }
                30% { opacity: 1; }
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = styles;
        document.head.appendChild(style);
    }
    
    /**
     * Cria HTML do widget
     */
    createHTML() {
        const container = document.createElement('div');
        container.className = 'confinter-chat-widget';
        
        container.innerHTML = `
            <button class="chat-widget-button" aria-label="Abrir chat">
                <span>💬</span>
            </button>
            
            <div class="chat-widget-container">
                <div class="chat-header">
                    <div>
                        <h3>CONFINTER Chat</h3>
                        <div class="chat-header-subtitle">Geralmente responde em minutos</div>
                    </div>
                    <button class="chat-close-btn" aria-label="Fechar chat">✕</button>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <div class="chat-message bot">
                        <div>
                            <div class="chat-message-content">${this.initialMessage}</div>
                            <div class="chat-message-time">${this.getCurrentTime()}</div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-area">
                    <input class="chat-input" id="chat-input" type="text" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button class="chat-send-btn" aria-label="Enviar mensagem">➤</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(container);
    }
    
    /**
     * Anexa event listeners
     */
    attachEventListeners() {
        const button = document.querySelector('.chat-widget-button');
        const closeBtn = document.querySelector('.chat-close-btn');
        const container = document.querySelector('.chat-widget-container');
        const input = document.getElementById('chat-input');
        const sendBtn = document.querySelector('.chat-send-btn');
        
        button.addEventListener('click', () => this.toggleChat());
        closeBtn.addEventListener('click', () => this.closeChat());
        
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        sendBtn.addEventListener('click', () => this.sendMessage());
        
        // Fechar ao clicar fora
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target) && !button.contains(e.target)) {
                if (this.isOpen) {
                    // Não fechar automaticamente
                }
            }
        });
    }
    
    /**
     * Toggle chat
     */
    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    /**
     * Abre chat
     */
    openChat() {
        const container = document.querySelector('.chat-widget-container');
        const button = document.querySelector('.chat-widget-button');
        
        container.classList.add('open');
        button.classList.add('open');
        this.isOpen = true;
        
        document.getElementById('chat-input').focus();
    }
    
    /**
     * Fecha chat
     */
    closeChat() {
        const container = document.querySelector('.chat-widget-container');
        const button = document.querySelector('.chat-widget-button');
        
        container.classList.remove('open');
        button.classList.remove('open');
        this.isOpen = false;
    }
    
    /**
     * Envia mensagem
     */
    sendMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Adiciona mensagem do usuário
        this.addMessage(message, 'user');
        input.value = '';
        input.focus();
        
        // Enviar para servidor
        this.isLoading = true;
        this.showLoadingIndicator();
        
        fetch(this.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'process_message',
                message: message,
                session_id: this.sessionId,
                lead_id: this.leadId
            })
        })
        .then(r => r.json())
        .then(data => {
            this.isLoading = false;
            this.removeLoadingIndicator();
            
            if (data.success) {
                // Resposta do bot
                this.addMessage(data.response, 'bot');
                
                // Atualizar lead ID se novo
                if (data.lead_id) {
                    this.leadId = data.lead_id;
                }
                
                // Mostrar qualificador se disponível
                if (data.qualifier) {
                    this.showQualifier(data.qualifier);
                }
            } else {
                this.addMessage('Desculpe, houve um erro. Tente novamente.', 'bot');
            }
        })
        .catch(err => {
            this.isLoading = false;
            this.removeLoadingIndicator();
            console.error('Chat error:', err);
            this.addMessage('Erro ao enviar mensagem. Tente novamente.', 'bot');
        });
    }
    
    /**
     * Adiciona mensagem ao chat
     */
    addMessage(text, sender) {
        const messagesDiv = document.getElementById('chat-messages');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${sender}`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'chat-message-content';
        contentDiv.textContent = text;
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'chat-message-time';
        timeDiv.textContent = this.getCurrentTime();
        
        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(timeDiv);
        
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        this.messages.push({ text, sender, time: new Date() });
    }
    
    /**
     * Mostra indicador de loading
     */
    showLoadingIndicator() {
        const messagesDiv = document.getElementById('chat-messages');
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chat-message bot';
        loadingDiv.id = 'chat-loading';
        
        loadingDiv.innerHTML = `
            <div class="chat-typing-indicator">
                <div class="chat-dot"></div>
                <div class="chat-dot"></div>
                <div class="chat-dot"></div>
            </div>
        `;
        
        messagesDiv.appendChild(loadingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    /**
     * Remove indicador de loading
     */
    removeLoadingIndicator() {
        const loading = document.getElementById('chat-loading');
        if (loading) {
            loading.remove();
        }
    }
    
    /**
     * Mostra qualificador
     */
    showQualifier(qualifier) {
        const messagesDiv = document.getElementById('chat-messages');
        
        const qualifierDiv = document.createElement('div');
        qualifierDiv.className = 'chat-message bot';
        
        const content = document.createElement('div');
        content.className = 'chat-qualifier';
        
        const question = document.createElement('div');
        question.className = 'chat-qualifier-question';
        question.textContent = qualifier.question;
        
        const options = document.createElement('div');
        options.className = 'chat-qualifier-options';
        
        qualifier.options.forEach(option => {
            const btn = document.createElement('button');
            btn.className = 'chat-option-btn';
            btn.textContent = option;
            btn.addEventListener('click', () => {
                this.selectQualifierOption(option);
            });
            options.appendChild(btn);
        });
        
        content.appendChild(question);
        content.appendChild(options);
        qualifierDiv.appendChild(content);
        
        messagesDiv.appendChild(qualifierDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    /**
     * Seleciona opção qualificadora
     */
    selectQualifierOption(option) {
        this.addMessage(option, 'user');
        this.sendMessage();
    }
    
    /**
     * Carrega histórico de chat
     */
    loadChatHistory() {
        // Recupera histórico do sessionStorage se existir
        const saved = sessionStorage.getItem(`chat_history_${this.sessionId}`);
        if (saved) {
            this.messages = JSON.parse(saved);
            const messagesDiv = document.getElementById('chat-messages');
            
            this.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = `chat-message ${msg.sender}`;
                div.innerHTML = `
                    <div class="chat-message-content">${msg.text}</div>
                    <div class="chat-message-time">${new Date(msg.time).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'})}</div>
                `;
                messagesDiv.appendChild(div);
            });
            
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }
    
    /**
     * Obtém hora atual formatada
     */
    getCurrentTime() {
        return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    
    /**
     * Gera session ID único
     */
    generateSessionId() {
        return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }
    
    /**
     * Salva histórico
     */
    saveHistory() {
        sessionStorage.setItem(`chat_history_${this.sessionId}`, JSON.stringify(this.messages));
    }
}

// Inicializar widget quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.confinterChat = new ChatWidget({
        initialMessage: 'Olá! 👋 Bem-vindo à CONFINTER.\n\nEm que posso ajudar'
    });
});

// Expor para uso externo
window.ChatWidget = ChatWidget;
