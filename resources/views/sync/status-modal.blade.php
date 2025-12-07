{{-- Modal de Status de Sincronización para usar dentro de otras vistas --}}
<style>
    .sync-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    .sync-modal-overlay.active {
        display: flex;
    }
    .sync-modal {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .sync-modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        text-align: center;
    }
    .sync-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
    }
    .sync-modal-body {
        padding: 20px;
        max-height: 50vh;
        overflow-y: auto;
    }
    .sync-progress-container {
        margin-bottom: 20px;
    }
    .sync-progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #4a5568;
    }
    .sync-progress-bar {
        width: 100%;
        height: 12px;
        background: #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .sync-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 6px;
        transition: width 0.3s ease;
        width: 0%;
    }
    .sync-table-status {
        margin-top: 15px;
    }
    .sync-table-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
    }
    .sync-table-item:last-child {
        border-bottom: none;
    }
    .sync-table-name {
        font-weight: 500;
        color: #2d3748;
    }
    .sync-table-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .sync-badge-pending {
        background: #fed7aa;
        color: #c05621;
    }
    .sync-badge-syncing {
        background: #bee3f8;
        color: #2b6cb0;
    }
    .sync-badge-done {
        background: #c6f6d5;
        color: #276749;
    }
    .sync-badge-error {
        background: #fed7d7;
        color: #c53030;
    }
    .sync-time-estimate {
        text-align: center;
        margin-top: 15px;
        color: #718096;
        font-size: 0.9rem;
    }
    .sync-modal-footer {
        padding: 15px 20px;
        background: #f7fafc;
        text-align: center;
        border-top: 1px solid #e2e8f0;
    }
    .sync-btn-close {
        background: #e2e8f0;
        border: none;
        padding: 10px 30px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.2s;
    }
    .sync-btn-close:hover {
        background: #cbd5e0;
    }
    .sync-btn-close:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .sync-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: sync-spin 0.8s linear infinite;
        margin-right: 8px;
    }
    @keyframes sync-spin {
        to { transform: rotate(360deg); }
    }
    .sync-log {
        background: #1a202c;
        color: #68d391;
        font-family: monospace;
        font-size: 0.75rem;
        padding: 10px;
        border-radius: 6px;
        max-height: 120px;
        overflow-y: auto;
        margin-top: 15px;
    }
    .sync-log-entry {
        margin-bottom: 4px;
    }
    .sync-log-time {
        color: #a0aec0;
    }
</style>

<div id="syncModalOverlay" class="sync-modal-overlay">
    <div class="sync-modal">
        <div class="sync-modal-header">
            <h3 id="syncModalTitle">
                <span class="sync-spinner" id="syncSpinner"></span>
                Sincronizando con Central...
            </h3>
        </div>
        <div class="sync-modal-body">
            <div class="sync-progress-container">
                <div class="sync-progress-label">
                    <span id="syncProgressText">Iniciando...</span>
                    <span id="syncProgressPercent">0%</span>
                </div>
                <div class="sync-progress-bar">
                    <div class="sync-progress-fill" id="syncProgressFill"></div>
                </div>
            </div>
            
            <div class="sync-table-status" id="syncTableStatus">
                <!-- Las tablas se cargan dinámicamente -->
            </div>
            
            <div class="sync-time-estimate" id="syncTimeEstimate">
                Tiempo estimado: calculando...
            </div>
            
            <div class="sync-log" id="syncLog">
                <div class="sync-log-entry">
                    <span class="sync-log-time">[--:--:--]</span> Esperando inicio...
                </div>
            </div>
        </div>
        <div class="sync-modal-footer">
            <button class="sync-btn-close" id="syncBtnClose" disabled onclick="closeSyncModal()">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Funciones globales para el modal de sincronización
window.SyncModal = {
    tables: [
        { key: 'inventarios', name: 'Inventarios' },
        { key: 'pedidos', name: 'Pedidos' },
        { key: 'pago_pedidos', name: 'Pagos' },
        { key: 'items_pedidos', name: 'Items' },
        { key: 'cierres', name: 'Cierres' },
        { key: 'cierres_puntos', name: 'Puntos' },
        { key: 'pagos_referencias', name: 'Referencias' },
        { key: 'cajas', name: 'Cajas' },
        { key: 'movimientos', name: 'Movimientos' }
    ],
    isOpen: false,
    pollInterval: null,
    
    // Helper para obtener elementos de forma segura
    getEl: function(id) {
        return document.getElementById(id);
    },
    
    open: function() {
        const overlay = this.getEl('syncModalOverlay');
        const btnClose = this.getEl('syncBtnClose');
        const spinner = this.getEl('syncSpinner');
        const title = this.getEl('syncModalTitle');
        
        if (overlay) overlay.classList.add('active');
        if (btnClose) btnClose.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
        if (title) {
            title.innerHTML = '<span class="sync-spinner"></span> Sincronizando con Central...';
            if (title.parentElement) {
                title.parentElement.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }
        
        this.isOpen = true;
        this.renderTables('pending');
        this.clearLog();
        this.addLog('Iniciando sincronización...');
    },
    
    close: function() {
        const overlay = this.getEl('syncModalOverlay');
        if (overlay) overlay.classList.remove('active');
        this.isOpen = false;
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },
    
    renderTables: function(defaultStatus) {
        const container = this.getEl('syncTableStatus');
        if (!container) return;
        
        container.innerHTML = this.tables.map(t => `
            <div class="sync-table-item" id="syncTable_${t.key}">
                <span class="sync-table-name">${t.name}</span>
                <span class="sync-table-badge sync-badge-${defaultStatus}" id="syncBadge_${t.key}">
                    ${defaultStatus === 'pending' ? 'Pendiente' : defaultStatus}
                </span>
            </div>
        `).join('');
    },
    
    updateTable: function(key, status, count) {
        const badge = this.getEl(`syncBadge_${key}`);
        if (badge) {
            badge.className = `sync-table-badge sync-badge-${status}`;
            if (status === 'done') {
                badge.textContent = count ? `✓ ${count}` : '✓ Listo';
            } else if (status === 'syncing') {
                badge.textContent = 'Sincronizando...';
            } else if (status === 'error') {
                badge.textContent = '✗ Error';
            } else {
                badge.textContent = 'Pendiente';
            }
        }
    },
    
    updateProgress: function(percent, text) {
        const fill = this.getEl('syncProgressFill');
        const percentEl = this.getEl('syncProgressPercent');
        const textEl = this.getEl('syncProgressText');
        
        if (fill) fill.style.width = percent + '%';
        if (percentEl) percentEl.textContent = Math.round(percent) + '%';
        if (text && textEl) textEl.textContent = text;
    },
    
    updateTimeEstimate: function(seconds) {
        const el = this.getEl('syncTimeEstimate');
        if (!el) return;
        
        if (seconds <= 0) {
            el.textContent = 'Finalizando...';
        } else {
            const mins = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            el.textContent = `Tiempo restante: ${mins > 0 ? mins + 'm ' : ''}${secs}s`;
        }
    },
    
    addLog: function(message) {
        const log = this.getEl('syncLog');
        if (!log) return;
        
        const now = new Date();
        const time = now.toTimeString().substr(0, 8);
        const entry = document.createElement('div');
        entry.className = 'sync-log-entry';
        entry.innerHTML = `<span class="sync-log-time">[${time}]</span> ${message}`;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    },
    
    clearLog: function() {
        const log = this.getEl('syncLog');
        if (log) log.innerHTML = '';
    },
    
    complete: function(success, message) {
        const btnClose = this.getEl('syncBtnClose');
        const spinner = this.getEl('syncSpinner');
        const title = this.getEl('syncModalTitle');
        const timeEstimate = this.getEl('syncTimeEstimate');
        
        if (btnClose) btnClose.disabled = false;
        if (spinner) spinner.style.display = 'none';
        
        if (title) {
            if (success) {
                title.innerHTML = '✓ Sincronización Completada';
                if (title.parentElement) {
                    title.parentElement.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
                }
                this.updateProgress(100, 'Completado');
            } else {
                title.innerHTML = '✗ Error en Sincronización';
                if (title.parentElement) {
                    title.parentElement.style.background = 'linear-gradient(135deg, #fc8181 0%, #c53030 100%)';
                }
            }
        }
        
        this.addLog(message || (success ? 'Sincronización completada exitosamente' : 'Error durante la sincronización'));
        if (timeEstimate) timeEstimate.textContent = '';
    }
};

function closeSyncModal() {
    window.SyncModal.close();
}
</script>
