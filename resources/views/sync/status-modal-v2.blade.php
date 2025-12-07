{{-- Modal de Status de Sincronización Mejorado --}}
<style>
    .sync-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
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
        border-radius: 16px;
        width: 95%;
        max-width: 600px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
    }
    .sync-modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 24px;
    }
    .sync-modal-header h3 {
        margin: 0 0 8px 0;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sync-header-stats {
        display: flex;
        gap: 20px;
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .sync-header-stat {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .sync-modal-body {
        padding: 20px 24px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    /* Resumen general */
    .sync-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .sync-summary-item {
        background: #f7fafc;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    .sync-summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
    }
    .sync-summary-label {
        font-size: 0.75rem;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Barra de progreso general */
    .sync-progress-main {
        margin-bottom: 20px;
    }
    .sync-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .sync-progress-title {
        font-weight: 600;
        color: #2d3748;
    }
    .sync-progress-percent {
        font-size: 1.1rem;
        font-weight: 700;
        color: #667eea;
    }
    .sync-progress-bar-main {
        width: 100%;
        height: 16px;
        background: #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }
    .sync-progress-fill-main {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 8px;
        transition: width 0.3s ease;
        width: 0%;
    }
    .sync-progress-info {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #718096;
    }
    
    /* Tablas con progreso individual */
    .sync-tables-container {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .sync-tables-header {
        background: #f7fafc;
        padding: 10px 15px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #4a5568;
        border-bottom: 1px solid #e2e8f0;
    }
    .sync-table-row {
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    .sync-table-row:last-child {
        border-bottom: none;
    }
    .sync-table-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }
    .sync-table-name {
        font-weight: 500;
        color: #2d3748;
        font-size: 0.9rem;
    }
    .sync-table-count {
        font-size: 0.8rem;
        color: #718096;
    }
    .sync-table-count strong {
        color: #2d3748;
    }
    .sync-table-progress {
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }
    .sync-table-progress-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s ease;
    }
    .sync-table-progress-fill.pending {
        background: #cbd5e0;
        width: 0%;
    }
    .sync-table-progress-fill.syncing {
        background: linear-gradient(90deg, #4299e1, #667eea);
        animation: progress-pulse 1.5s ease-in-out infinite;
    }
    .sync-table-progress-fill.done {
        background: #48bb78;
        width: 100%;
    }
    .sync-table-progress-fill.error {
        background: #fc8181;
    }
    @keyframes progress-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    .sync-table-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .sync-table-status.pending { color: #a0aec0; }
    .sync-table-status.syncing { color: #4299e1; }
    .sync-table-status.done { color: #48bb78; }
    .sync-table-status.error { color: #fc8181; }
    
    /* Log */
    .sync-log-container {
        margin-top: 16px;
    }
    .sync-log-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #1a202c;
        color: #a0aec0;
        border-radius: 8px 8px 0 0;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .sync-log-toggle:hover {
        background: #2d3748;
    }
    .sync-log {
        background: #1a202c;
        color: #68d391;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.75rem;
        padding: 12px;
        border-radius: 0 0 8px 8px;
        max-height: 150px;
        overflow-y: auto;
        display: none;
    }
    .sync-log.visible {
        display: block;
    }
    .sync-log-entry {
        margin-bottom: 4px;
        line-height: 1.4;
    }
    .sync-log-time {
        color: #718096;
    }
    .sync-log-success { color: #68d391; }
    .sync-log-error { color: #fc8181; }
    .sync-log-info { color: #63b3ed; }
    
    /* Footer */
    .sync-modal-footer {
        padding: 16px 24px;
        background: #f7fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .sync-footer-status {
        font-size: 0.85rem;
        color: #718096;
    }
    .sync-btn-close {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .sync-btn-close:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    .sync-btn-close:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Spinner */
    .sync-spinner {
        display: inline-block;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: sync-spin 0.8s linear infinite;
    }
    @keyframes sync-spin {
        to { transform: rotate(360deg); }
    }
</style>

<div id="syncModalOverlay" class="sync-modal-overlay">
    <div class="sync-modal">
        <div class="sync-modal-header" id="syncModalHeader">
            <h3 id="syncModalTitle">
                <span class="sync-spinner" id="syncSpinner"></span>
                <span id="syncTitleText">Sincronizando con Central...</span>
            </h3>
            <div class="sync-header-stats">
                <div class="sync-header-stat">
                    <span>📅</span>
                    <span id="syncFechaCorte">Desde: 2025-12-01</span>
                </div>
                <div class="sync-header-stat">
                    <span>⏱️</span>
                    <span id="syncTiempoTranscurrido">00:00</span>
                </div>
            </div>
        </div>
        
        <div class="sync-modal-body">
            <!-- Resumen -->
            <div class="sync-summary">
                <div class="sync-summary-item">
                    <div class="sync-summary-value" id="syncTotalPendientes">--</div>
                    <div class="sync-summary-label">Pendientes</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-value" id="syncTotalProcesados">0</div>
                    <div class="sync-summary-label">Procesados</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-value" id="syncTiempoEstimado">--</div>
                    <div class="sync-summary-label">Est. Restante</div>
                </div>
            </div>
            
            <!-- Progreso general -->
            <div class="sync-progress-main">
                <div class="sync-progress-header">
                    <span class="sync-progress-title">Progreso General</span>
                    <span class="sync-progress-percent" id="syncProgressPercent">0%</span>
                </div>
                <div class="sync-progress-bar-main">
                    <div class="sync-progress-fill-main" id="syncProgressFill"></div>
                </div>
                <div class="sync-progress-info">
                    <span id="syncProgressText">Preparando...</span>
                    <span id="syncProgressSpeed">-- reg/s</span>
                </div>
            </div>
            
            <!-- Tablas -->
            <div class="sync-tables-container">
                <div class="sync-tables-header">Detalle por Tabla</div>
                <div id="syncTablesBody">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
            
            <!-- Log -->
            <div class="sync-log-container">
                <div class="sync-log-toggle" onclick="SyncModal.toggleLog()">
                    <span>📋</span>
                    <span>Ver registro de actividad</span>
                    <span id="syncLogArrow">▼</span>
                </div>
                <div class="sync-log" id="syncLog"></div>
            </div>
        </div>
        
        <div class="sync-modal-footer">
            <div class="sync-footer-status" id="syncFooterStatus">
                Iniciando...
            </div>
            <button class="sync-btn-close" id="syncBtnClose" disabled onclick="closeSyncModal()">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Sistema de sincronización mejorado
window.SyncModal = {
    tables: [
        { key: 'inventarios', name: 'Inventarios', icon: '📦' },
        { key: 'pedidos', name: 'Pedidos', icon: '🛒' },
        { key: 'pago_pedidos', name: 'Pagos', icon: '💳' },
        { key: 'items_pedidos', name: 'Items', icon: '📊' },
        { key: 'cierres', name: 'Cierres', icon: '📋' },
        { key: 'cierres_puntos', name: 'Puntos', icon: '💰' },
        { key: 'pagos_referencias', name: 'Referencias', icon: '🔗' },
        { key: 'cajas', name: 'Cajas', icon: '🏦' },
        { key: 'movimientos', name: 'Movimientos', icon: '📈' }
    ],
    
    state: {
        isOpen: false,
        startTime: null,
        timerInterval: null,
        totalPendientes: 0,
        totalProcesados: 0,
        logVisible: false,
        tableData: {}
    },
    
    getEl: function(id) {
        return document.getElementById(id);
    },
    
    open: function(pendientesData) {
        const overlay = this.getEl('syncModalOverlay');
        if (overlay) overlay.classList.add('active');
        
        this.state.isOpen = true;
        this.state.startTime = Date.now();
        this.state.totalProcesados = 0;
        this.state.totalPendientes = 0;
        this.state.tableData = {};
        
        // Resetear UI
        this.resetUI();
        
        // Inicializar datos de pendientes si se proporcionan
        if (pendientesData) {
            this.setInitialData(pendientesData);
        }
        
        // Iniciar timer
        this.startTimer();
        
        // Renderizar tablas
        this.renderTables();
        
        this.addLog('Iniciando sincronización...', 'info');
    },
    
    resetUI: function() {
        const header = this.getEl('syncModalHeader');
        const spinner = this.getEl('syncSpinner');
        const titleText = this.getEl('syncTitleText');
        const btnClose = this.getEl('syncBtnClose');
        
        if (header) header.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        if (spinner) spinner.style.display = 'inline-block';
        if (titleText) titleText.textContent = 'Sincronizando con Central...';
        if (btnClose) btnClose.disabled = true;
        
        this.updateProgress(0, 'Preparando...');
        this.updateSummary(0, 0, '--');
        
        const log = this.getEl('syncLog');
        if (log) log.innerHTML = '';
    },
    
    setInitialData: function(data) {
        // data = { inventarios: { pendientes: X, total: Y }, ... }
        let totalPend = 0;
        
        this.tables.forEach(t => {
            if (data[t.key]) {
                this.state.tableData[t.key] = {
                    pendientes: data[t.key].pendientes || 0,
                    total: data[t.key].total || 0,
                    procesados: 0,
                    status: data[t.key].pendientes > 0 ? 'pending' : 'done'
                };
                totalPend += data[t.key].pendientes || 0;
            } else {
                this.state.tableData[t.key] = { pendientes: 0, total: 0, procesados: 0, status: 'pending' };
            }
        });
        
        this.state.totalPendientes = totalPend;
        this.updateSummary(totalPend, 0, '--');
    },
    
    close: function() {
        const overlay = this.getEl('syncModalOverlay');
        if (overlay) overlay.classList.remove('active');
        
        this.state.isOpen = false;
        this.stopTimer();
    },
    
    startTimer: function() {
        this.stopTimer();
        this.state.timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.state.startTime) / 1000);
            const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const secs = (elapsed % 60).toString().padStart(2, '0');
            const el = this.getEl('syncTiempoTranscurrido');
            if (el) el.textContent = `${mins}:${secs}`;
        }, 1000);
    },
    
    stopTimer: function() {
        if (this.state.timerInterval) {
            clearInterval(this.state.timerInterval);
            this.state.timerInterval = null;
        }
    },
    
    renderTables: function() {
        const container = this.getEl('syncTablesBody');
        if (!container) return;
        
        container.innerHTML = this.tables.map(t => {
            const data = this.state.tableData[t.key] || { pendientes: 0, total: 0, procesados: 0, status: 'pending' };
            const percent = data.total > 0 ? Math.round((data.procesados / data.total) * 100) : 0;
            
            return `
                <div class="sync-table-row" id="syncRow_${t.key}">
                    <div class="sync-table-info">
                        <span class="sync-table-name">${t.icon} ${t.name}</span>
                        <span class="sync-table-count">
                            <strong id="syncCount_${t.key}">${data.procesados}</strong> / ${data.pendientes} pendientes
                        </span>
                    </div>
                    <div class="sync-table-progress">
                        <div class="sync-table-progress-fill ${data.status}" 
                             id="syncProgress_${t.key}" 
                             style="width: ${data.status === 'done' ? 100 : percent}%"></div>
                    </div>
                    <div class="sync-table-status ${data.status}" id="syncStatus_${t.key}">
                        ${this.getStatusText(data.status, data.pendientes)}
                    </div>
                </div>
            `;
        }).join('');
    },
    
    getStatusText: function(status, count) {
        switch(status) {
            case 'done': return '✓ Completado';
            case 'syncing': return '⟳ Sincronizando...';
            case 'error': return '✗ Error';
            default: return count > 0 ? `⏳ ${count} pendientes` : '✓ Sin cambios';
        }
    },
    
    updateTable: function(key, procesados, status) {
        const data = this.state.tableData[key];
        if (!data) return;
        
        data.procesados = procesados;
        data.status = status;
        
        const countEl = this.getEl(`syncCount_${key}`);
        const progressEl = this.getEl(`syncProgress_${key}`);
        const statusEl = this.getEl(`syncStatus_${key}`);
        
        if (countEl) countEl.textContent = procesados;
        
        if (progressEl) {
            progressEl.className = `sync-table-progress-fill ${status}`;
            if (status === 'done') {
                progressEl.style.width = '100%';
            } else if (data.pendientes > 0) {
                progressEl.style.width = Math.round((procesados / data.pendientes) * 100) + '%';
            }
        }
        
        if (statusEl) {
            statusEl.className = `sync-table-status ${status}`;
            statusEl.innerHTML = this.getStatusText(status, data.pendientes - procesados);
        }
        
        // Actualizar totales
        this.recalcTotals();
    },
    
    recalcTotals: function() {
        let totalProc = 0;
        let allDone = true;
        
        this.tables.forEach(t => {
            const data = this.state.tableData[t.key];
            if (data) {
                totalProc += data.procesados || 0;
                if (data.status !== 'done' && data.pendientes > 0) allDone = false;
            }
        });
        
        this.state.totalProcesados = totalProc;
        
        // Calcular progreso y tiempo estimado
        const percent = this.state.totalPendientes > 0 
            ? Math.round((totalProc / this.state.totalPendientes) * 100) 
            : 0;
        
        const elapsed = (Date.now() - this.state.startTime) / 1000;
        const speed = elapsed > 0 ? totalProc / elapsed : 0;
        const remaining = this.state.totalPendientes - totalProc;
        const estSeconds = speed > 0 ? Math.round(remaining / speed) : 0;
        const estText = estSeconds > 60 
            ? `${Math.floor(estSeconds / 60)}m ${estSeconds % 60}s`
            : estSeconds > 0 ? `${estSeconds}s` : '--';
        
        this.updateProgress(percent, `Procesando registros...`);
        this.updateSummary(this.state.totalPendientes, totalProc, estText);
        
        const speedEl = this.getEl('syncProgressSpeed');
        if (speedEl) speedEl.textContent = `${Math.round(speed)} reg/s`;
    },
    
    updateProgress: function(percent, text) {
        const fill = this.getEl('syncProgressFill');
        const percentEl = this.getEl('syncProgressPercent');
        const textEl = this.getEl('syncProgressText');
        
        if (fill) fill.style.width = percent + '%';
        if (percentEl) percentEl.textContent = percent + '%';
        if (text && textEl) textEl.textContent = text;
    },
    
    updateSummary: function(pendientes, procesados, tiempoEst) {
        const pendEl = this.getEl('syncTotalPendientes');
        const procEl = this.getEl('syncTotalProcesados');
        const tiempoEl = this.getEl('syncTiempoEstimado');
        
        if (pendEl) pendEl.textContent = pendientes.toLocaleString();
        if (procEl) procEl.textContent = procesados.toLocaleString();
        if (tiempoEl) tiempoEl.textContent = tiempoEst;
    },
    
    toggleLog: function() {
        const log = this.getEl('syncLog');
        const arrow = this.getEl('syncLogArrow');
        
        this.state.logVisible = !this.state.logVisible;
        
        if (log) log.classList.toggle('visible', this.state.logVisible);
        if (arrow) arrow.textContent = this.state.logVisible ? '▲' : '▼';
    },
    
    addLog: function(message, type = 'info') {
        const log = this.getEl('syncLog');
        if (!log) return;
        
        const now = new Date();
        const time = now.toTimeString().substr(0, 8);
        const entry = document.createElement('div');
        entry.className = 'sync-log-entry';
        entry.innerHTML = `<span class="sync-log-time">[${time}]</span> <span class="sync-log-${type}">${message}</span>`;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    },
    
    complete: function(success, message, resultData) {
        this.stopTimer();
        
        const header = this.getEl('syncModalHeader');
        const spinner = this.getEl('syncSpinner');
        const titleText = this.getEl('syncTitleText');
        const btnClose = this.getEl('syncBtnClose');
        const footerStatus = this.getEl('syncFooterStatus');
        
        if (spinner) spinner.style.display = 'none';
        if (btnClose) btnClose.disabled = false;
        
        if (success) {
            if (header) header.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
            if (titleText) titleText.textContent = '✓ Sincronización Completada';
            
            // Actualizar todas las tablas como completadas
            if (resultData && resultData.tablas) {
                Object.keys(resultData.tablas).forEach(key => {
                    const tabla = resultData.tablas[key];
                    this.updateTable(key, tabla.sincronizados || tabla.enviados || 0, 'done');
                });
            } else {
                // Marcar todas como done
                this.tables.forEach(t => this.updateTable(t.key, this.state.tableData[t.key]?.pendientes || 0, 'done'));
            }
            
            this.updateProgress(100, 'Completado');
            this.addLog(message || 'Sincronización completada exitosamente', 'success');
        } else {
            if (header) header.style.background = 'linear-gradient(135deg, #fc8181 0%, #c53030 100%)';
            if (titleText) titleText.textContent = '✗ Error en Sincronización';
            this.addLog(message || 'Error durante la sincronización', 'error');
        }
        
        const elapsed = Math.floor((Date.now() - this.state.startTime) / 1000);
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        const tiempoTotal = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
        
        if (footerStatus) {
            footerStatus.textContent = success 
                ? `✓ Completado en ${tiempoTotal} - ${this.state.totalProcesados} registros`
                : `✗ Error después de ${tiempoTotal}`;
        }
        
        const tiempoEl = this.getEl('syncTiempoEstimado');
        if (tiempoEl) tiempoEl.textContent = tiempoTotal;
    }
};

function closeSyncModal() {
    window.SyncModal.close();
}
</script>
