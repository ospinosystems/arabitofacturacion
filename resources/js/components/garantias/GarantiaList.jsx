import React, { useState, useEffect } from 'react';

const GarantiaList = ({ garantias, onReload, sucursalConfig, db }) => {
    const [loading, setLoading] = useState(false);
    const [selectedGarantia, setSelectedGarantia] = useState(null);
    const [filter, setFilter] = useState('todas'); // 'todas', 'pendientes', 'aprobadas', 'rechazadas', 'finalizadas'
    const [expandedItems, setExpandedItems] = useState(new Set());
    const [activeTab, setActiveTab] = useState('garantias'); // 'garantias', 'inventario'

    // Funciones helper para parsear datos JSON
    const safeJsonParse = (jsonString, defaultValue = []) => {
        try {
            if (!jsonString || jsonString === '[]' || jsonString === '{}') {
                return defaultValue;
            }
            return JSON.parse(jsonString);
        } catch (error) {
            console.error('Error parsing JSON:', error, 'String:', jsonString);
            return defaultValue;
        }
    };

    const getGarantiaData = (garantia) => {
        const garantiaData = safeJsonParse(garantia.garantia_data, {});
        const productosData = safeJsonParse(garantia.productos_data, []);
        
        return {
            ...garantiaData,
            productos: garantia.productos_con_datos || productosData.map(p => ({
                ...p,
                descripcion: p.producto?.descripcion || 'Producto no encontrado'
            }))
        };
    };

    const calcularDiferenciaGarantia = (garantia) => {
        const metodosDevolucion = safeJsonParse(garantia.metodos_devolucion, []);
        const productosData = safeJsonParse(garantia.productos_data, []);
        
        // Calcular valor total de productos de entrada (que devuelve el cliente)
        let valorEntrada = 0;
        const productosEntrada = productosData.filter(p => p.tipo === 'entrada');
        productosEntrada.forEach(prod => {
            const productoDetalle = garantia.productos_con_datos?.find(p => p.id_producto === prod.id_producto);
            const precio = productoDetalle?.producto?.precio || 0;
            valorEntrada += precio * (prod.cantidad || 1);
        });
        
        // Calcular valor total de productos de salida (que recibe el cliente)
        let valorSalida = 0;
        const productosSalida = productosData.filter(p => p.tipo === 'salida');
        productosSalida.forEach(prod => {
            const productoDetalle = garantia.productos_con_datos?.find(p => p.id_producto === prod.id_producto);
            const precio = productoDetalle?.producto?.precio || 0;
            valorSalida += precio * (prod.cantidad || 1);
        });
        
        // Calcular diferencia
        const diferencia = valorSalida - valorEntrada;
        
        return {
            valorEntrada,
            valorSalida,
            diferencia,
            aFavorCliente: diferencia > 0,
            aFavorEmpresa: diferencia < 0,
            sinDiferencia: diferencia === 0
        };
    };

    const getCasoUsoDescription = (casoUso) => {
        const casos = {
            1: 'Producto por producto',
            2: 'Producto por dinero',
            3: 'Dinero por producto',
            4: 'Dinero por dinero',
            5: 'Transferencia entre sucursales'
        };
        return casos[casoUso] || `Caso ${casoUso}`;
    };

    const formatNombreCompleto = (persona) => {
        if (!persona) return 'N/A';
        return `${persona.nombre || ''} ${persona.apellido || ''}`.trim();
    };

    // Estados para inventario de garantías
    const [inventarioGarantias, setInventarioGarantias] = useState([]);
    const [tiposInventario, setTiposInventario] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [searchType, setSearchType] = useState('codigo_barras');
    const [tipoInventarioFilter, setTipoInventarioFilter] = useState('');
    const [sucursales, setSucursales] = useState([]);
    const [selectedProducto, setSelectedProducto] = useState(null);
    const [showTransferModal, setShowTransferModal] = useState(false);
    const [showVentaModal, setShowVentaModal] = useState(false);
    const [showPrintModal, setShowPrintModal] = useState(false);
    const [selectedGarantiaForPrint, setSelectedGarantiaForPrint] = useState(null);
    const [showExecuteModal, setShowExecuteModal] = useState(false);
    const [selectedGarantiaForExecute, setSelectedGarantiaForExecute] = useState(null);
    const [cajasDisponibles, setCajasDisponibles] = useState([]);
    const [cajasDisponiblesEjecutar, setCajasDisponiblesEjecutar] = useState([]);
    const [selectedCaja, setSelectedCaja] = useState(1);
    const [transferData, setTransferData] = useState({
        sucursal_destino: '',
        cantidad: 1,
        motivo: '',
        solicitado_por: ''
    });

    useEffect(() => {
        if (activeTab === 'inventario') {
            loadInventarioGarantias();
            loadTiposInventario();
            loadSucursales();
        }
    }, [activeTab, tipoInventarioFilter]);

    const loadInventarioGarantias = async () => {
        setLoading(true);
        try {
            const response = await db.getInventarioGarantiasCentral({
                tipo_inventario: tipoInventarioFilter,
                page: 1,
                per_page: 100
            });
            
            if (response.data.success) {
                setInventarioGarantias(response.data.data);
            } else {
                console.error('Error al cargar inventario:', response.data.message);
            }
        } catch (error) {
            console.error('Error al cargar inventario de garantías:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadTiposInventario = async () => {
        try {
            const response = await db.getTiposInventarioGarantia();
            
            if (response.data.success) {
                setTiposInventario(response.data.data);
            }
        } catch (error) {
            console.error('Error al cargar tipos de inventario:', error);
        }
    };

    const loadSucursales = async () => {
        try {
            const response = await db.getSucursales();
            
            if (response.data) {
                setSucursales(response.data);
            }
        } catch (error) {
            console.error('Error al cargar sucursales:', error);
        }
    };

    const handleSearchInventario = async () => {
        if (!searchTerm.trim()) {
            loadInventarioGarantias();
            return;
        }

        setLoading(true);
        try {
            const response = await db.searchInventarioGarantias({
                search_term: searchTerm,
                search_type: searchType,
                tipo_inventario: tipoInventarioFilter
            });
            
            if (response.data.success) {
                setInventarioGarantias(response.data.data);
            } else {
                console.error('Error en búsqueda:', response.data.message);
            }
        } catch (error) {
            console.error('Error al buscar productos:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleTransferirSucursal = async () => {
        if (!selectedProducto || !transferData.sucursal_destino) {
            alert('Seleccione un producto y sucursal destino');
            return;
        }

        setLoading(true);
        try {
            const response = await db.transferirProductoGarantiaSucursal({
                producto_id: selectedProducto.id,
                sucursal_destino: transferData.sucursal_destino,
                cantidad: transferData.cantidad,
                tipo_inventario: selectedProducto.tipo_inventario,
                motivo: transferData.motivo,
                solicitado_por: transferData.solicitado_por
            });
            
            if (response.data.success) {
                alert('Solicitud de transferencia enviada correctamente');
                setShowTransferModal(false);
                setSelectedProducto(null);
                setTransferData({
                    sucursal_destino: '',
                    cantidad: 1,
                    motivo: '',
                    solicitado_por: ''
                });
                loadInventarioGarantias();
            } else {
                alert('Error en transferencia: ' + response.data.message);
            }
        } catch (error) {
            console.error('Error al transferir:', error);
            alert('Error de conexión al transferir');
        } finally {
            setLoading(false);
        }
    };

    const handleTransferirVenta = async () => {
        if (!selectedProducto || !transferData.sucursal_destino) {
            alert('Seleccione un producto y sucursal destino');
            return;
        }

        setLoading(true);
        try {
            const response = await db.transferirGarantiaVentaNormal({
                producto_id: selectedProducto.id,
                sucursal_destino: transferData.sucursal_destino,
                cantidad: transferData.cantidad,
                tipo_inventario: selectedProducto.tipo_inventario,
                autorizado_por: transferData.solicitado_por,
                observaciones: transferData.motivo
            });
            
            if (response.data.success) {
                alert('Producto transferido a inventario de venta');
                setShowVentaModal(false);
                setSelectedProducto(null);
                setTransferData({
                    sucursal_destino: '',
                    cantidad: 1,
                    motivo: '',
                    solicitado_por: ''
                });
                loadInventarioGarantias();
            } else {
                alert('Error en transferencia: ' + response.data.message);
            }
        } catch (error) {
            console.error('Error al transferir a venta:', error);
            alert('Error de conexión al transferir a venta');
        } finally {
            setLoading(false);
        }
    };

    const getStatusColor = (estatus) => {
        switch (estatus) {
            case 'PENDIENTE':
                return 'bg-warning text-dark';
            case 'APROBADA':
                return 'bg-success text-white';
            case 'RECHAZADA':
                return 'bg-danger text-white';
            case 'FINALIZADA':
                return 'bg-secondary text-white';
            default:
                return 'bg-light text-dark';
        }
    };

    const getFilteredGarantias = () => {
        if (filter === 'todas') return garantias;
        return garantias.filter(g => {
            if (filter === 'pendientes') return g.estatus === 'PENDIENTE';
            if (filter === 'aprobadas') return g.estatus === 'APROBADA' && !g.ejecutada;
            if (filter === 'rechazadas') return g.estatus === 'RECHAZADA';
            if (filter === 'finalizadas') return g.ejecutada || g.estatus === 'FINALIZADA';
            return true;
        });
    };

    const handleEjecutarGarantia = async (garantia) => {
        // Cargar cajas disponibles y mostrar modal de selección
        await loadCajasDisponiblesEjecutar();
        setSelectedGarantiaForExecute(garantia);
        setShowExecuteModal(true);
    };

    const confirmarEjecucion = async () => {
        if (!selectedGarantiaForExecute) return;

        setLoading(true);
        try {
            // ACTUALIZADO: Usar la nueva función que maneja SolicitudGarantia correctamente
            // - Intenta ejecutar con el sistema moderno que edita inventario, crea movimientos y pedidos
            // - Usa solicitud_central_id si está disponible, sino usa el id local
            // - Incluye fallback al método legacy para compatibilidad
            const solicitudId = selectedGarantiaForExecute.solicitud_central_id || selectedGarantiaForExecute.id;
            const response = await db.ejecutarSolicitudGarantiaModerna(solicitudId, selectedCaja);
            
            if (response.status === 200 && response.data.success) {
                alert('Solicitud de garantía ejecutada exitosamente usando el sistema moderno');
                setShowExecuteModal(false);
                setSelectedGarantiaForExecute(null);
                onReload();
            } else {
                // Fallback al método legacy si falla
                console.warn('Método moderno falló, intentando método legacy...');
                try {
                    const legacyResponse = await db.ejecutarGarantia(selectedGarantiaForExecute.id, selectedCaja);
                    if (legacyResponse.status === 200 && legacyResponse.data.success) {
                        alert('Garantía ejecutada exitosamente (método legacy)');
                        setShowExecuteModal(false);
                        setSelectedGarantiaForExecute(null);
                        onReload();
                    } else {
                        alert('Error al ejecutar garantía: ' + (legacyResponse.data.message || 'Error desconocido'));
                    }
                } catch (legacyError) {
                    console.error('Error en método legacy:', legacyError);
                    alert('Error al ejecutar garantía: ' + (response.data.message || 'Error desconocido'));
                }
            }
        } catch (error) {
            console.error('Error al ejecutar garantía:', error);
            alert('Error de conexión al ejecutar garantía: ' + (error.response?.data?.message || error.message));
        } finally {
            setLoading(false);
        }
    };

    const handleSyncWithCentral = async () => {
        setLoading(true);
        try {
            const response = await db.syncGarantias();
            
            if (response.status === 200 && response.data.success) {
                onReload();
            } else {
                alert('Error en sincronización: ' + (response.data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error en sincronización:', error);
            alert('Error de conexión durante sincronización');
        } finally {
            setLoading(false);
        }
    };

    const loadCajasDisponiblesEjecutar = async () => {
        try {
            const response = await db.getUsuarios({ q: '' });

            if (response.status === 200 && response.data) {
                // Filtrar usuarios donde tipo_usuario sea 4
                const usuariosCaja = response.data.filter(usuario => usuario.tipo_usuario === 4);
                
                // Formatear los datos para que tengan la estructura esperada
                const cajasFormateadas = usuariosCaja.map(usuario => ({
                    id: usuario.id,
                    nombre: `${usuario.nombre}`.trim()
                }));

                setCajasDisponiblesEjecutar(cajasFormateadas);
                if (cajasFormateadas.length > 0) {
                    setSelectedCaja(cajasFormateadas[0].id);
                }
            } else {
                console.error('Error al cargar usuarios de caja');
                setCajasDisponiblesEjecutar([]);
            }
        } catch (error) {
            console.error('Error al cargar usuarios de caja:', error);
            setCajasDisponiblesEjecutar([]);
        }
    };

    const loadCajasDisponibles = async () => {
        try {
            const response = await fetch('/api/get-cajas-disponibles', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const result = await response.json();

            if (result.success) {
                setCajasDisponibles(result.cajas);
                if (result.cajas.length > 0) {
                    setSelectedCaja(result.cajas[0].id);
                }
            } else {
                console.error('Error al cargar cajas:', result.message);
            }
        } catch (error) {
            console.error('Error al cargar cajas disponibles:', error);
        }
    };

    const handleImprimirTicket = async (garantia) => {
        if (!garantia || (!garantia.ejecutada && garantia.estatus !== 'FINALIZADA')) {
            alert('Solo se pueden imprimir tickets de solicitudes finalizadas');
            return;
        }

        // Cargar cajas disponibles y mostrar modal
        await loadCajasDisponibles();
        setSelectedGarantiaForPrint(garantia);
        setShowPrintModal(true);
    };

    const confirmarImpresion = async () => {
        if (!selectedGarantiaForPrint) return;

        setLoading(true);
        try {
            const response = await fetch('/api/imprimir-ticket-garantia', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    solicitud_id: selectedGarantiaForPrint.id,
                    printer: selectedCaja
                })
            });

            const result = await response.json();

            if (result.success) {
                alert(`Ticket impreso exitosamente en Caja ${selectedCaja} (${result.tipo_impresion} #${result.numero_impresion})`);
                setShowPrintModal(false);
                setSelectedGarantiaForPrint(null);
            } else {
                alert('Error al imprimir ticket: ' + result.message);
            }
        } catch (error) {
            console.error('Error al imprimir ticket:', error);
            alert('Error al imprimir ticket: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const toggleExpanded = (garantiaId) => {
        const newExpanded = new Set(expandedItems);
        if (newExpanded.has(garantiaId)) {
            newExpanded.delete(garantiaId);
        } else {
            newExpanded.add(garantiaId);
        }
        setExpandedItems(newExpanded);
    };

    const filteredGarantias = getFilteredGarantias();

    return (
        <div className="garantia-list">

            {/* Tab content */}
            {activeTab === 'garantias' && (
                <div>
                    {/* Header con controles */}
                    <div className="row mb-4">
                        <div className="flex flex-col gap-3 w-full">
                            {/* Filtros Responsive */}
                            <div className="flex flex-col sm:flex-row gap-2">
                                {/* Desktop Layout - Filtros horizontales */}
                                <div className="hidden sm:flex flex-row items-center gap-1 text-sm">
                                <button
                                    type="button"
                                        className={`px-3 py-1.5 rounded ${filter === 'todas' ? 'bg-blue-600 text-white' : 'bg-white border border-blue-600 text-blue-600 hover:bg-blue-50'} transition`}
                                    onClick={() => setFilter('todas')}
                                >
                                    Todas ({garantias.length})
                                </button>
                                <button
                                    type="button"
                                        className={`px-3 py-1.5 rounded ${filter === 'pendientes' ? 'bg-sinapsis text-white' : 'bg-white border border-sinapsis text-sinapsis hover:bg-sinapsis/10'} transition`}
                                    onClick={() => setFilter('pendientes')}
                                >
                                    Pendientes ({garantias.filter(g => g.estatus === 'PENDIENTE').length})
                                </button>
                                <button
                                    type="button"
                                        className={`px-3 py-1.5 rounded ${filter === 'aprobadas' ? 'bg-green-600 text-white' : 'bg-white border border-green-600 text-green-600 hover:bg-green-50'} transition`}
                                    onClick={() => setFilter('aprobadas')}
                                >
                                    Por Ejecutar ({garantias.filter(g => g.estatus === 'APROBADA' && !g.ejecutada).length})
                                </button>
                                <button
                                    type="button"
                                        className={`px-3 py-1.5 rounded ${filter === 'finalizadas' ? 'bg-gray-600 text-white' : 'bg-white border border-gray-600 text-gray-600 hover:bg-gray-50'} transition`}
                                    onClick={() => setFilter('finalizadas')}
                                >
                                    Finalizadas ({garantias.filter(g => g.ejecutada || g.estatus === 'FINALIZADA').length})
                                </button>
                                <button
                                        className="px-3 py-1.5 rounded bg-sky-500 text-white hover:bg-sky-600 transition flex items-center gap-1"
                                    onClick={handleSyncWithCentral}
                                    disabled={loading}
                                >
                                        <i className="fa fa-sync"></i>
                                </button>
                            </div>
                            
                                {/* Mobile Layout - Filtros en grid */}
                                <div className="sm:hidden">
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            className={`px-3 py-2 rounded text-sm font-medium ${filter === 'todas' ? 'bg-blue-600 text-white' : 'bg-white border border-blue-600 text-blue-600 hover:bg-blue-50'} transition`}
                                            onClick={() => setFilter('todas')}
                                        >
                                            <div className="text-center">
                                                <div className="font-semibold">Todas</div>
                                                <div className="text-xs opacity-75">({garantias.length})</div>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            className={`px-3 py-2 rounded text-sm font-medium ${filter === 'pendientes' ? 'bg-sinapsis text-white' : 'bg-white border border-sinapsis text-sinapsis hover:bg-sinapsis/10'} transition`}
                                            onClick={() => setFilter('pendientes')}
                                        >
                                            <div className="text-center">
                                                <div className="font-semibold">Pendientes</div>
                                                <div className="text-xs opacity-75">({garantias.filter(g => g.estatus === 'PENDIENTE').length})</div>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            className={`px-3 py-2 rounded text-sm font-medium ${filter === 'aprobadas' ? 'bg-green-600 text-white' : 'bg-white border border-green-600 text-green-600 hover:bg-green-50'} transition`}
                                            onClick={() => setFilter('aprobadas')}
                                        >
                                            <div className="text-center">
                                                <div className="font-semibold">Por Ejecutar</div>
                                                <div className="text-xs opacity-75">({garantias.filter(g => g.estatus === 'APROBADA' && !g.ejecutada).length})</div>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            className={`px-3 py-2 rounded text-sm font-medium ${filter === 'finalizadas' ? 'bg-gray-600 text-white' : 'bg-white border border-gray-600 text-gray-600 hover:bg-gray-50'} transition`}
                                            onClick={() => setFilter('finalizadas')}
                                        >
                                            <div className="text-center">
                                                <div className="font-semibold">Finalizadas</div>
                                                <div className="text-xs opacity-75">({garantias.filter(g => g.ejecutada || g.estatus === 'FINALIZADA').length})</div>
                                            </div>
                                        </button>
                                    </div>
                                    
                                    {/* Botón de sincronización en móvil */}
                                    <div className="mt-2">
                                        <button
                                            className="w-full px-3 py-2 rounded bg-sky-500 text-white hover:bg-sky-600 transition flex items-center justify-center gap-2 text-sm font-medium"
                                            onClick={handleSyncWithCentral}
                                            disabled={loading}
                                        >
                                            <i className="fa fa-sync"></i>
                                            <span>Sincronizar con Central</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Lista de garantías */}
                    {loading && (
                        <div className="text-center py-3">
                            <div className="spinner-border text-primary" role="status">
                                <span className="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    )}

                    {!loading && filteredGarantias.length === 0 && (
                        <div className="alert alert-info text-center">
                            <i className="fa fa-info-circle me-2"></i>
                            No hay garantías en esta categoría
                        </div>
                    )}

                    {!loading && filteredGarantias.length > 0 && (
                        <div className="list-group">
                            {filteredGarantias.map((garantia) => (
                                <div
                                    key={garantia.id}
                                    className="list-group-item list-group-item-action"
                                >
                                    <div className="d-flex w-100 justify-content-between align-items-start">
                                        <div className="flex-grow-1">
                                            <div className="d-flex justify-content-between align-items-center mb-2">
                                                <h6 className="mb-0">
                                                    <i className="fa fa-shield-alt me-2 text-primary"></i>
                                                    Garantía #{garantia.id}
                                                    {garantia.solicitud_central_id && (
                                                        <small className="text-muted ms-2">
                                                            (Central: #{garantia.solicitud_central_id})
                                                        </small>
                                                    )}
                                                    {garantia.id_pedido_insucursal && (
                                                        <small className="text-info ms-2">
                                                            (Pedido: #{garantia.id_pedido_insucursal})
                                                        </small>
                                                    )}
                                                </h6>
                                                <div className="d-flex align-items-center gap-2">
                                                    <span className={`badge ${getStatusColor(garantia.estatus)}`}>
                                                        {garantia.estatus}
                                                    </span>
                                                    <button
                                                        className="btn btn-sm btn-outline-secondary"
                                                        onClick={() => toggleExpanded(garantia.id)}
                                                    >
                                                        <i className={`fa fa-chevron-${expandedItems.has(garantia.id) ? 'up' : 'down'}`}></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div className="row">
                                                <div className="col-md-4">
                                                    <small className="text-muted">Productos:</small>
                                                    <div>
                                                        {(() => {
                                                            const data = getGarantiaData(garantia);
                                                            if (data.productos && data.productos.length > 0) {
                                                                return data.productos.length > 1 
                                                                    ? `${data.productos.length} productos`
                                                                    : data.productos[0].producto?.descripcion || 'N/A';
                                                            }
                                                            return 'N/A';
                                                        })()}
                                                    </div>
                                                </div>
                                                <div className="col-md-4">
                                                    <small className="text-muted">Caso de uso:</small>
                                                    <div>{getCasoUsoDescription(garantia.caso_uso)}</div>
                                                </div>
                                                <div className="col-md-4">
                                                    <small className="text-muted">Fecha:</small>
                                                    <div>{new Date(garantia.created_at).toLocaleDateString()}</div>
                                                </div>
                                            </div>

                                            {expandedItems.has(garantia.id) && (
                                                <div className="mt-3 pt-3 border-top">
                                                    {(() => {
                                                        const data = getGarantiaData(garantia);
                                                        const metodosDevolucion = safeJsonParse(garantia.metodos_devolucion, []);
                                                        
                                                        return (
                                                            <>
                                                                <div className="row">
                                                                    <div className="col-md-6">
                                                                        <h6>Detalles</h6>
                                                                        <ul className="list-unstyled small">
                                                                            <li><strong>Motivo:</strong> {data.motivo || garantia.motivo_devolucion || 'N/A'}</li>
                                                                            <li><strong>Tipo:</strong> {garantia.tipo_solicitud}</li>
                                                                            <li><strong>Sucursal:</strong> {garantia.sucursal?.nombre || 'N/A'}</li>
                                                                            {garantia.id_pedido_insucursal && (
                                                                                <li><strong>Pedido ID:</strong> #{garantia.id_pedido_insucursal}</li>
                                                                            )}
                                                                            {garantia.monto_devolucion_dinero && (
                                                                                <li><strong>Monto devolución:</strong> ${garantia.monto_devolucion_dinero}</li>
                                                                            )}
                                                                            <li><strong>Días desde compra:</strong> {data.dias_desdecompra || garantia.dias_transcurridos_compra || 'N/A'}</li>
                                                                            <li><strong>Trajo factura:</strong> {garantia.trajo_factura ? 'Sí' : 'No'}</li>
                                                                        </ul>
                                                                    </div>
                                                                    <div className="col-md-6">
                                                                        <h6>Responsables</h6>
                                                                        <ul className="list-unstyled small">
                                                                            <li><strong>Cliente:</strong> {formatNombreCompleto(garantia.cliente)} - {garantia.cliente?.cedula || 'N/A'}</li>
                                                                            <li><strong>Cajero:</strong> {formatNombreCompleto(garantia.cajero)} - {garantia.cajero?.cedula || 'N/A'}</li>
                                                                            <li><strong>Supervisor:</strong> {formatNombreCompleto(garantia.supervisor)} - {garantia.supervisor?.cedula || 'N/A'}</li>
                                                                            {garantia.dici && (
                                                                                <li><strong>DICI:</strong> {formatNombreCompleto(garantia.dici)} - {garantia.dici?.cedula || 'N/A'}</li>
                                                                            )}
                                                                        </ul>
                                                                    </div>
                                                                </div>

                                                                {/* Productos */}
                                                                {data.productos && data.productos.length > 0 && (
                                                                    <div className="mt-3">
                                                                        <h6 className="fw-bold text-dark mb-3 d-flex align-items-center">
                                                                            <i className="fa fa-box me-2 text-primary"></i>
                                                                            Productos ({data.productos.length})
                                                                        </h6>
                                                                        
                                                                        {/* Desktop Table */}
                                                                        <div className="d-none d-md-block">
                                                                            <div className="table-responsive">
                                                                                <table className="table table-hover table-bordered shadow-sm">
                                                                                    <thead className="table-light">
                                                                                        <tr>
                                                                                            <th className="fw-semibold text-uppercase small">
                                                                                                <i className="fa fa-tag me-1"></i>Descripción
                                                                                            </th>
                                                                                            <th className="fw-semibold text-uppercase small text-center">
                                                                                                <i className="fa fa-heart me-1"></i>Estado
                                                                                            </th>
                                                                                            <th className="fw-semibold text-uppercase small text-center">
                                                                                                <i className="fa fa-exchange-alt me-1"></i>Tipo
                                                                                            </th>
                                                                                            <th className="fw-semibold text-uppercase small text-center">
                                                                                                <i className="fa fa-sort-numeric-up me-1"></i>Cantidad
                                                                                            </th>
                                                                                            <th className="fw-semibold text-uppercase small text-center">
                                                                                                <i className="fa fa-dollar-sign me-1"></i>Precio
                                                                                            </th>
                                                                                            <th className="fw-semibold text-uppercase small text-center">
                                                                                                <i className="fa fa-barcode me-1"></i>Código
                                                                                            </th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        {data.productos.map((prod, index) => {
                                                                                            // Extraer tipo desde productos_data - Buscar coincidencia exacta por id_producto, estado y tipo
                                                                                            const productosData = safeJsonParse(garantia.productos_data, []);
                                                                                            const prodData = productosData.find(p => 
                                                                                                p.id_producto === prod.id_producto && 
                                                                                                p.estado === prod.estado && 
                                                                                                p.tipo
                                                                                            );
                                                                                            const tipoProducto = prodData?.tipo || 'N/A';
                                                                                            
                                                                                            return (
                                                                                            <tr key={index} className="align-middle">
                                                                                                <td className="fw-medium" style={{ maxWidth: '250px' }}>
                                                                                                    <div className="text-truncate" title={prod.producto?.descripcion || 'N/A'}>
                                                                                                        {prod.producto?.descripcion || 'N/A'}
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td className="text-center">
                                                                                                    <span className={`badge rounded-pill ${
                                                                                                        prod.estado === 'DAÑADO' ? 'bg-danger' : 'bg-success'
                                                                                                    }`}>
                                                                                                        <i className={`fa ${prod.estado === 'DAÑADO' ? 'fa-times' : 'fa-check'} me-1`}></i>
                                                                                                        {prod.estado || 'BUENO'}
                                                                                                    </span>
                                                                                                </td>
                                                                                                <td className="text-center">
                                                                                                    {tipoProducto !== 'N/A' ? (
                                                                                                        <span className={`badge rounded-pill ${
                                                                                                            tipoProducto === 'entrada' ? 'bg-info' : 'bg-warning text-dark'
                                                                                                        }`}>
                                                                                                            <i className={`fa ${tipoProducto === 'entrada' ? 'fa-arrow-down' : 'fa-arrow-up'} me-1`}></i>
                                                                                                            {tipoProducto.toUpperCase()}
                                                                                                        </span>
                                                                                                    ) : (
                                                                                                        <span className="badge bg-secondary">N/A</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td className="text-center">
                                                                                                    <span className="badge bg-primary fs-6">{prod.cantidad || 1}</span>
                                                                                                </td>
                                                                                                <td className="text-center fw-bold text-success">
                                                                                                    ${prod.producto?.precio || 0}
                                                                                                </td>
                                                                                                <td className="text-center">
                                                                                                    <code className="small text-muted">{prod.producto?.codigo_barras || 'N/A'}</code>
                                                                                                </td>
                                                                                            </tr>
                                                                                            );
                                                                                        })}
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        {/* Mobile Cards */}
                                                                        <div className="d-md-none">
                                                                            {data.productos.map((prod, index) => {
                                                                                // Extraer tipo desde productos_data - Buscar coincidencia exacta por id_producto, estado y tipo
                                                                                const productosData = safeJsonParse(garantia.productos_data, []);
                                                                                const prodData = productosData.find(p => 
                                                                                    p.id_producto === prod.id_producto && 
                                                                                    p.estado === prod.estado && 
                                                                                    p.tipo
                                                                                );
                                                                                const tipoProducto = prodData?.tipo || 'N/A';

                                                                                return (
                                                                                <div key={index} className="card border border-primary mb-3 shadow-sm">
                                                                                    <div className="card-header bg-light py-2">
                                                                                        <div className="d-flex justify-content-between align-items-center">
                                                                                            <small className="text-muted fw-semibold">
                                                                                                <i className="fa fa-box me-1"></i>
                                                                                                Producto #{index + 1}
                                                                                            </small>
                                                                                            <div className="d-flex gap-1">
                                                                                                <span className={`badge rounded-pill ${
                                                                                                    prod.estado === 'DAÑADO' ? 'bg-danger' : 'bg-success'
                                                                                                }`}>
                                                                                                    <i className={`fa ${prod.estado === 'DAÑADO' ? 'fa-times' : 'fa-check'} me-1`}></i>
                                                                                                    {prod.estado || 'BUENO'}
                                                                                                </span>
                                                                                                {tipoProducto !== 'N/A' && (
                                                                                                    <span className={`badge rounded-pill ${
                                                                                                        tipoProducto === 'entrada' ? 'bg-info' : 'bg-warning text-dark'
                                                                                                    }`}>
                                                                                                        <i className={`fa ${tipoProducto === 'entrada' ? 'fa-arrow-down' : 'fa-arrow-up'} me-1`}></i>
                                                                                                        {tipoProducto.toUpperCase()}
                                                                                                    </span>
                                                                                                )}
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div className="card-body p-3">
                                                                                        {/* Descripción Principal */}
                                                                                        <h6 className="card-title text-primary mb-3" title={prod.producto?.descripcion || 'N/A'}>
                                                                                            <i className="fa fa-tag me-2"></i>
                                                                                            {prod.producto?.descripcion || 'N/A'}
                                                                                        </h6>
                                                                                        
                                                                                        {/* Grid de Información */}
                                                                                        <div className="row g-2 mb-3">
                                                                                            <div className="col-6">
                                                                                                <div className="bg-light p-2 rounded text-center">
                                                                                                    <div className="text-muted small mb-1">
                                                                                                        <i className="fa fa-sort-numeric-up me-1"></i>Cantidad
                                                                                                    </div>
                                                                                                    <div className="fw-bold text-primary fs-5">
                                                                                                        {prod.cantidad || 1}
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div className="col-6">
                                                                                                <div className="bg-light p-2 rounded text-center">
                                                                                                    <div className="text-muted small mb-1">
                                                                                                        <i className="fa fa-dollar-sign me-1"></i>Precio
                                                                                                    </div>
                                                                                                    <div className="fw-bold text-success fs-5">
                                                                                                        ${prod.producto?.precio || 0}
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        
                                                                                        {/* Código de Barras */}
                                                                                        <div className="border-top pt-2">
                                                                                            <div className="d-flex justify-content-between align-items-center">
                                                                                                <small className="text-muted">
                                                                                                    <i className="fa fa-barcode me-1"></i>
                                                                                                    Código:
                                                                                                </small>
                                                                                                <code className="small bg-light px-2 py-1 rounded">
                                                                                                    {prod.producto?.codigo_barras || 'N/A'}
                                                                                                </code>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                );
                                                                            })}
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {/* Métodos de devolución */}
                                                                {metodosDevolucion.length > 0 && (
                                                                    <div className="mt-3">
                                                                        <h6>Métodos de Devolución</h6>
                                                                        <div className="table-responsive">
                                                                            <table className="table table-sm table-striped">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Tipo</th>
                                                                                        <th>Monto</th>
                                                                                        <th>Moneda</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {metodosDevolucion.map((metodo, index) => (
                                                                                        <tr key={index}>
                                                                                            <td>{metodo.tipo || 'N/A'}</td>
                                                                                            <td>{metodo.monto_original || 0}</td>
                                                                                            <td>{metodo.moneda_original || 'N/A'}</td>
                                                                                        </tr>
                                                                                    ))}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {/* Card de Diferencia */}
                                                                {(() => {
                                                                    const diferenciaData = calcularDiferenciaGarantia(garantia);
                                                                    return (
                                                                        <div className="mt-3">
                                                                            <div className={`card border-0 shadow-sm ${
                                                                                diferenciaData.aFavorCliente ? 'border-start border-4 border-success' :
                                                                                diferenciaData.aFavorEmpresa ? 'border-start border-4 border-danger' :
                                                                                'border-start border-4 border-info'
                                                                            }`}>
                                                                                <div className="card-body p-3">
                                                                                    <div className="d-flex justify-content-between align-items-center">
                                                                                        <div>
                                                                                            <h6 className="card-title mb-1">
                                                                                                <i className={`fa ${
                                                                                                    diferenciaData.aFavorCliente ? 'fa-arrow-up text-success' :
                                                                                                    diferenciaData.aFavorEmpresa ? 'fa-arrow-down text-danger' :
                                                                                    'fa-equals text-info'
                                                                                                } me-2`}></i>
                                                                                                Diferencia en Movimiento
                                                                                            </h6>
                                                                                            <p className="card-text small text-muted mb-0">
                                                                                                {diferenciaData.aFavorCliente ? 'Cliente debe pagar diferencia' :
                                                                                                 diferenciaData.aFavorEmpresa ? 'Empresa debe devolver diferencia' :
                                                                                                 'Sin diferencia - Intercambio equitativo'}
                                                                                            </p>
                                                                                        </div>
                                                                                        <div className="text-end">
                                                                                            <div className={`h5 mb-0 ${
                                                                                                diferenciaData.aFavorCliente ? 'text-success' :
                                                                                                diferenciaData.aFavorEmpresa ? 'text-danger' :
                                                                                                'text-info'
                                                                                            }`}>
                                                                                                ${Math.abs(diferenciaData.diferencia).toFixed(2)}
                                                                                            </div>
                                                                                            <small className="text-muted">
                                                                                                {diferenciaData.aFavorCliente ? 'A favor del cliente' :
                                                                                                 diferenciaData.aFavorEmpresa ? 'A favor de la empresa' :
                                                                                                 'Sin diferencia'}
                                                                                            </small>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    {/* Detalles adicionales */}
                                                                                    <div className="row mt-2 pt-2 border-top">
                                                                                        <div className="col-6">
                                                                                            <small className="text-muted d-block">Valor Entrada:</small>
                                                                                            <span className="fw-bold">${diferenciaData.valorEntrada.toFixed(2)}</span>
                                                                                        </div>
                                                                                        <div className="col-6">
                                                                                            <small className="text-muted d-block">Valor Salida:</small>
                                                                                            <span className="fw-bold">${diferenciaData.valorSalida.toFixed(2)}</span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                })()}

                                                                {(data.detalles_adicionales || garantia.detalles_adicionales) && (
                                                                    <div className="mt-2">
                                                                        <strong>Detalles adicionales:</strong>
                                                                        <div className="text-muted small">{data.detalles_adicionales || garantia.detalles_adicionales}</div>
                                                                    </div>
                                                                )}

                                                                {/* Acciones */}
                                                                <div className="mt-3 pt-2 border-top">
                                                                    {garantia.estatus === 'APROBADA' && !garantia.ejecutada && (
                                                                        <button
                                                                            className="btn btn-success btn-sm me-2"
                                                                            onClick={() => handleEjecutarGarantia(garantia)}
                                                                            disabled={loading}
                                                                        >
                                                                            <i className="fa fa-play me-1"></i>
                                                                            Ejecutar Garantía
                                                                        </button>
                                                                    )}

                                                                    {(garantia.ejecutada || garantia.estatus === 'FINALIZADA') && (
                                                                        <button
                                                                            className="btn btn-warning btn-sm me-2"
                                                                            onClick={() => handleImprimirTicket(garantia)}
                                                                            disabled={loading}
                                                                        >
                                                                            <i className="fa fa-print me-1"></i>
                                                                            Imprimir Ticket
                                                                        </button>
                                                                    )}

                                                                    {garantia.solicitud_central_id && (
                                                                        <button
                                                                            className="btn btn-info btn-sm me-2"
                                                                            onClick={() => window.open(`/api/garantias/redirect-central-dashboard/${garantia.solicitud_central_id}`, '_blank')}
                                                                        >
                                                                            <i className="fa fa-external-link-alt me-1"></i>
                                                                            Ver en Central
                                                                        </button>
                                                                    )}

                                                                    <button
                                                                        className="btn btn-outline-secondary btn-sm"
                                                                        onClick={() => setSelectedGarantia(garantia)}
                                                                    >
                                                                        <i className="fa fa-eye me-1"></i>
                                                                        Ver Detalles
                                                                    </button>
                                                                </div>
                                                            </>
                                                        );
                                                    })()}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Inventario de Garantías */}
            {activeTab === 'inventario' && (
                <div>
                    {/* Controles de búsqueda */}
                    <div className="row mb-4">
                        <div className="col-md-3">
                            <select
                                className="form-select"
                                value={tipoInventarioFilter}
                                onChange={(e) => setTipoInventarioFilter(e.target.value)}
                            >
                                <option value="">Todos los tipos</option>
                                {tiposInventario.map(tipo => (
                                    <option key={tipo.id} value={tipo.nombre}>
                                        {tipo.descripcion}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3">
                            <select
                                className="form-select"
                                value={searchType}
                                onChange={(e) => setSearchType(e.target.value)}
                            >
                                <option value="codigo_barras">Código de Barras</option>
                                <option value="descripcion">Descripción</option>
                                <option value="codigo_proveedor">Código Proveedor</option>
                            </select>
                        </div>
                        <div className="col-md-4">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Buscar producto..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onKeyPress={(e) => e.key === 'Enter' && handleSearchInventario()}
                            />
                        </div>
                        <div className="col-md-2">
                            <button
                                className="btn btn-primary w-100"
                                onClick={handleSearchInventario}
                                disabled={loading}
                            >
                                <i className="fa fa-search me-1"></i>
                                Buscar
                            </button>
                        </div>
                    </div>

                    {loading && (
                        <div className="text-center py-3">
                            <div className="spinner-border text-primary" role="status">
                                <span className="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    )}

                    {!loading && inventarioGarantias.length === 0 && (
                        <div className="alert alert-info text-center">
                            <i className="fa fa-info-circle me-2"></i>
                            No se encontraron productos en inventario de garantías
                        </div>
                    )}

                    {!loading && inventarioGarantias.length > 0 && (
                        <div className="table-responsive">
                            <table className="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>
                                        <th>Disponible</th>
                                        <th>Reservado</th>
                                        <th>Recuperado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {inventarioGarantias.map(producto => (
                                        <tr key={producto.id}>
                                            <td>{producto.codigo_barras}</td>
                                            <td>{producto.descripcion}</td>
                                            <td>
                                                <span className={`badge ${
                                                    producto.tipo_inventario === 'disponible' ? 'bg-success' :
                                                    producto.tipo_inventario === 'reservado' ? 'bg-warning' :
                                                    'bg-info'
                                                }`}>
                                                    {producto.tipo_inventario}
                                                </span>
                                            </td>
                                            <td className="text-center">
                                                <span className="badge bg-success">
                                                    {producto.cantidad_disponible || 0}
                                                </span>
                                            </td>
                                            <td className="text-center">
                                                <span className="badge bg-warning">
                                                    {producto.cantidad_reservada || 0}
                                                </span>
                                            </td>
                                            <td className="text-center">
                                                <span className="badge bg-info">
                                                    {producto.cantidad_recuperada || 0}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="btn-group" role="group">
                                                    <button
                                                        className="btn btn-sm btn-outline-primary"
                                                        onClick={() => {
                                                            setSelectedProducto(producto);
                                                            setShowTransferModal(true);
                                                        }}
                                                    >
                                                        <i className="fa fa-exchange-alt me-1"></i>
                                                        Transferir
                                                    </button>
                                                    <button
                                                        className="btn btn-sm btn-outline-success"
                                                        onClick={() => {
                                                            setSelectedProducto(producto);
                                                            setShowVentaModal(true);
                                                        }}
                                                    >
                                                        <i className="fa fa-store me-1"></i>
                                                        A Venta
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {/* Modal para transferir entre sucursales */}
            {showTransferModal && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Transferir Producto</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowTransferModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <div className="mb-3">
                                    <label className="form-label">Producto:</label>
                                    <p className="form-control-plaintext">{selectedProducto?.descripcion}</p>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Sucursal Destino:</label>
                                    <select
                                        className="form-select"
                                        value={transferData.sucursal_destino}
                                        onChange={(e) => setTransferData({...transferData, sucursal_destino: e.target.value})}
                                    >
                                        <option value="">Seleccionar sucursal</option>
                                        {sucursales.map(sucursal => (
                                            <option key={sucursal.id} value={sucursal.codigo}>
                                                {sucursal.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Cantidad:</label>
                                    <input
                                        type="number"
                                        className="form-control"
                                        value={transferData.cantidad}
                                        onChange={(e) => setTransferData({...transferData, cantidad: parseInt(e.target.value)})}
                                        min="1"
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Motivo:</label>
                                    <textarea
                                        className="form-control"
                                        value={transferData.motivo}
                                        onChange={(e) => setTransferData({...transferData, motivo: e.target.value})}
                                        rows="3"
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Solicitado por:</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        value={transferData.solicitado_por}
                                        onChange={(e) => setTransferData({...transferData, solicitado_por: e.target.value})}
                                    />
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => setShowTransferModal(false)}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-primary"
                                    onClick={handleTransferirSucursal}
                                    disabled={loading}
                                >
                                    Transferir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal para transferir a venta */}
            {showVentaModal && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Transferir a Venta Normal</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowVentaModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <div className="mb-3">
                                    <label className="form-label">Producto:</label>
                                    <p className="form-control-plaintext">{selectedProducto?.descripcion}</p>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Sucursal Destino:</label>
                                    <select
                                        className="form-select"
                                        value={transferData.sucursal_destino}
                                        onChange={(e) => setTransferData({...transferData, sucursal_destino: e.target.value})}
                                    >
                                        <option value="">Seleccionar sucursal</option>
                                        {sucursales.map(sucursal => (
                                            <option key={sucursal.id} value={sucursal.codigo}>
                                                {sucursal.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Cantidad:</label>
                                    <input
                                        type="number"
                                        className="form-control"
                                        value={transferData.cantidad}
                                        onChange={(e) => setTransferData({...transferData, cantidad: parseInt(e.target.value)})}
                                        min="1"
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Autorizado por:</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        value={transferData.solicitado_por}
                                        onChange={(e) => setTransferData({...transferData, solicitado_por: e.target.value})}
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label">Observaciones:</label>
                                    <textarea
                                        className="form-control"
                                        value={transferData.motivo}
                                        onChange={(e) => setTransferData({...transferData, motivo: e.target.value})}
                                        rows="3"
                                    />
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => setShowVentaModal(false)}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-success"
                                    onClick={handleTransferirVenta}
                                    disabled={loading}
                                >
                                    Transferir a Venta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de detalles de garantía */}
            {selectedGarantia && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-fullscreen-lg-down" style={{ maxWidth: '95%' }}>
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">
                                    Detalles de Solicitud #{selectedGarantia.id}
                                </h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setSelectedGarantia(null)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                {(() => {
                                    const data = getGarantiaData(selectedGarantia);
                                    const metodosDevolucion = safeJsonParse(selectedGarantia.metodos_devolucion, []);
                                    
                                    return (
                                        <div className="row">
                                            <div className="col-md-8">
                                                <h6>Información General</h6>
                                                <table className="table table-sm">
                                                    <tbody>
                                                        <tr>
                                                            <td><strong>Estado:</strong></td>
                                                            <td>
                                                                <span className={`badge ${getStatusColor(selectedGarantia.estatus)}`}>
                                                                    {selectedGarantia.estatus}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Caso de uso:</strong></td>
                                                            <td>{getCasoUsoDescription(selectedGarantia.caso_uso)}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Tipo:</strong></td>
                                                            <td>{selectedGarantia.tipo_solicitud}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Sucursal:</strong></td>
                                                            <td>{selectedGarantia.sucursal?.nombre || 'N/A'} ({selectedGarantia.sucursal?.codigo || 'N/A'})</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Fecha creación:</strong></td>
                                                            <td>{new Date(selectedGarantia.created_at).toLocaleString()}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Motivo:</strong></td>
                                                            <td>{data.motivo || selectedGarantia.motivo_devolucion || 'N/A'}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Días desde compra:</strong></td>
                                                            <td>{data.dias_desdecompra || selectedGarantia.dias_transcurridos_compra || 'N/A'}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Trajo factura:</strong></td>
                                                            <td>{selectedGarantia.trajo_factura ? 'Sí' : 'No'}</td>
                                                        </tr>
                                                        {selectedGarantia.monto_devolucion_dinero && (
                                                            <tr>
                                                                <td><strong>Monto devolución:</strong></td>
                                                                <td>${selectedGarantia.monto_devolucion_dinero}</td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </table>

                                                {/* Responsables */}
                                                <div className="mt-3">
                                                    <h6>Responsables</h6>
                                                    <div className="row">
                                                        <div className="col-6">
                                                            <p><strong>Cliente:</strong> {formatNombreCompleto(selectedGarantia.cliente)} - {selectedGarantia.cliente?.cedula || 'N/A'}</p>
                                                            <p><strong>Cajero:</strong> {formatNombreCompleto(selectedGarantia.cajero)} - {selectedGarantia.cajero?.cedula || 'N/A'}</p>
                                                        </div>
                                                        <div className="col-6">
                                                            <p><strong>Supervisor:</strong> {formatNombreCompleto(selectedGarantia.supervisor)} - {selectedGarantia.supervisor?.cedula || 'N/A'}</p>
                                                            {selectedGarantia.dici && (
                                                                <p><strong>DICI:</strong> {formatNombreCompleto(selectedGarantia.dici)} - {selectedGarantia.dici?.cedula || 'N/A'}</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Productos */}
                                                {data.productos && data.productos.length > 0 && (
                                                    <div className="mt-3">
                                                        <h6>Productos</h6>
                                                        <div className="table-responsive">
                                                            <table className="table table-sm table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Código</th>
                                                                        <th>Descripción</th>
                                                                        <th>Estado</th>
                                                                        <th>Tipo</th>
                                                                        <th>Cantidad</th>
                                                                        <th>Precio</th>
                                                                        <th>Total</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {data.productos.map((prod, index) => {
                                                                        const precio = prod.producto?.precio || 0;
                                                                        const cantidad = prod.cantidad || 1;
                                                                        const total = precio * cantidad;
                                                                        
                                                                        // Extraer tipo desde productos_data
                                                                        const productosData = safeJsonParse(selectedGarantia.productos_data, []);
                                                                        const prodData = productosData.find(p => p.id_producto === prod.id_producto);
                                                                        const tipoProducto = prodData?.tipo || 'N/A';
                                                                        
                                                                        return (
                                                                        <tr key={index}>
                                                                                <td>
                                                                                    <span className="badge bg-light text-dark">
                                                                                        {prod.producto?.codigo_barras || prod.producto?.codigo_proveedor || prod.producto?.id || 'N/A'}
                                                                                    </span>
                                                                                </td>
                                                                                <td><strong>{prod.producto?.descripcion || 'N/A'}</strong></td>
                                                                                <td>
                                                                                    {prod.estado ? (
                                                                                        <span className={`badge ${prod.estado === 'MALO' || prod.estado === 'DAÑADO' ? 'bg-danger' : 'bg-success'}`}>
                                                                                            {prod.estado === 'MALO' ? 'DAÑADO' : prod.estado}
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="badge bg-secondary">N/A</span>
                                                                                    )}
                                                                                </td>
                                                                                <td>
                                                                                    {tipoProducto !== 'N/A' ? (
                                                                                        <span className={`badge ${tipoProducto === 'entrada' ? 'bg-success' : 'bg-danger'}`}>
                                                                                            {tipoProducto.toUpperCase()}
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="badge bg-secondary">N/A</span>
                                                                                    )}
                                                                                </td>
                                                                                <td>
                                                                                    <span className="badge bg-primary">{cantidad}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <span className="text-primary fw-bold">${precio.toFixed(2)}</span>
                                                                                </td>
                                                                                <td>
                                                                                    <strong className="text-success">${total.toFixed(2)}</strong>
                                                                                </td>
                                                                            </tr>
                                                                        );
                                                                    })}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Sistema de Carrito Nuevo - garantia_data */}
                                                {selectedGarantia.garantia_data && (selectedGarantia.garantia_data.entradas || selectedGarantia.garantia_data.salidas) && (
                                                    <div className="mt-3">
                                                        <h6>
                                                            <i className="fa fa-shopping-cart me-2"></i>
                                                            Carrito de {selectedGarantia.tipo_solicitud} (Sistema Nuevo)
                                                            {selectedGarantia.caso_uso && (
                                                                <span className="badge bg-info ms-2">
                                                                    {getCasoUsoDescription(selectedGarantia.caso_uso)}
                                                                </span>
                                                            )}
                                                        </h6>
                                                        
                                                        <div className="row">
                                                            {/* ENTRADAS */}
                                                            {selectedGarantia.garantia_data.entradas && selectedGarantia.garantia_data.entradas.length > 0 && (
                                                                <div className="col-md-6">
                                                                    <div className="card border-warning">
                                                                        <div className="card-header bg-warning text-dark">
                                                                            <h6 className="mb-0">
                                                                                <i className="fa fa-arrow-down me-2"></i>
                                                                                Entradas ({selectedGarantia.garantia_data.entradas.length})
                                                                            </h6>
                                                                            <small>Lo que recibe la tienda</small>
                                                                        </div>
                                                                        <div className="card-body p-2">
                                                                            <div className="table-responsive">
                                                                                <table className="table table-sm mb-0">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>Tipo</th>
                                                                                            <th>Descripción</th>
                                                                                            <th>Estado</th>
                                                                                            <th>Movimiento</th>
                                                                                            <th>Cantidad/Monto</th>
                                                                                            <th>Valor</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        {selectedGarantia.garantia_data.entradas.map((entrada, index) => (
                                                                                            <tr key={index}>
                                                                                                <td>
                                                                                                    <span className={`badge ${entrada.tipo === 'PRODUCTO' ? 'bg-warning' : 'bg-success'}`}>
                                                                                                        {entrada.tipo === 'PRODUCTO' ? 'PRODUCTO' : 'DINERO'}
                                                                                                    </span>
                                                                                                </td>
                                                                                                <td>
                                                                                                    {entrada.tipo === 'PRODUCTO' ? (
                                                                                                        <strong>{entrada.descripcion || `Producto #${entrada.producto_id}`}</strong>
                                                                                                    ) : (
                                                                                                        <div>
                                                                                                            <strong>Pago Cliente</strong>
                                                                                                            <div>
                                                                                                                <span className="badge bg-info badge-sm">
                                                                                                                    {entrada.metodo || 'EFECTIVO'}
                                                                                                                </span>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {entrada.tipo === 'PRODUCTO' ? (
                                                                                                        entrada.estado ? (
                                                                                                            <span className={`badge ${entrada.estado === 'MALO' || entrada.estado === 'DAÑADO' ? 'bg-danger' : 'bg-success'}`}>
                                                                                                                {entrada.estado === 'MALO' ? 'DAÑADO' : entrada.estado === 'DAÑADO' ? 'DAÑADO' : 'BUENO'}
                                                                                                            </span>
                                                                                                        ) : (
                                                                                                            <span className="badge bg-secondary">N/A</span>
                                                                                                        )
                                                                                                    ) : (
                                                                                                        <span className="badge bg-secondary">-</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {entrada.tipo === 'PRODUCTO' ? (
                                                                                                        <span className="badge bg-success">
                                                                                                            ↗️ ENTRADA
                                                                                                        </span>
                                                                                                    ) : (
                                                                                                        <span className="badge bg-secondary">-</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {entrada.tipo === 'PRODUCTO' ? (
                                                                                                        <span className="badge bg-primary">{entrada.cantidad}</span>
                                                                                                    ) : (
                                                                                                        <span className="text-success fw-bold">${entrada.monto_usd}</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    <strong className="text-warning">
                                                                                                        ${entrada.tipo === 'PRODUCTO' ? 
                                                                                                            ((entrada.cantidad || 0) * (entrada.precio_unitario || 0)).toFixed(2) : 
                                                                                                            (entrada.monto_usd || 0).toFixed(2)
                                                                                                        }
                                                                                                    </strong>
                                                                                                </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                                            </div>
                                                                        </div>
                                                        </div>
                                                                </div>
                                                            )}

                                                            {/* SALIDAS */}
                                                            {selectedGarantia.garantia_data.salidas && selectedGarantia.garantia_data.salidas.length > 0 && (
                                                                <div className="col-md-6">
                                                                    <div className="card border-success">
                                                                        <div className="card-header bg-success text-white">
                                                                            <h6 className="mb-0">
                                                                                <i className="fa fa-arrow-up me-2"></i>
                                                                                Salidas ({selectedGarantia.garantia_data.salidas.length})
                                                                            </h6>
                                                                            <small>Lo que entrega la tienda</small>
                                                                        </div>
                                                                        <div className="card-body p-2">
                                                                            <div className="table-responsive">
                                                                                <table className="table table-sm mb-0">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>Tipo</th>
                                                                                            <th>Descripción</th>
                                                                                            <th>Estado</th>
                                                                                            <th>Movimiento</th>
                                                                                            <th>Cantidad/Monto</th>
                                                                                            <th>Valor</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        {selectedGarantia.garantia_data.salidas.map((salida, index) => (
                                                                                            <tr key={index}>
                                                                                                <td>
                                                                                                    <span className={`badge ${salida.tipo === 'PRODUCTO' ? 'bg-primary' : 'bg-danger'}`}>
                                                                                                        {salida.tipo === 'PRODUCTO' ? 'PRODUCTO' : 'DINERO'}
                                                                                                    </span>
                                                                                                </td>
                                                                                                <td>
                                                                                                    {salida.tipo === 'PRODUCTO' ? (
                                                                                                        <strong>{salida.descripcion || `Producto #${salida.producto_id}`}</strong>
                                                                                                    ) : (
                                                                                                        <div>
                                                                                                            <strong>Devolución</strong>
                                                                                                            <div>
                                                                                                                <span className="badge bg-info badge-sm">
                                                                                                                    {salida.metodo || 'EFECTIVO'}
                                                                                                                </span>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {salida.tipo === 'PRODUCTO' ? (
                                                                                                        salida.estado ? (
                                                                                                            <span className="badge bg-success">
                                                                                                                {salida.estado}
                                                                                                            </span>
                                                                                                        ) : (
                                                                                                            <span className="badge bg-secondary">N/A</span>
                                                                                                        )
                                                                                                    ) : (
                                                                                                        <span className="badge bg-secondary">-</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {salida.tipo === 'PRODUCTO' ? (
                                                                                                        <span className="badge bg-danger">
                                                                                                            ↙️ SALIDA
                                                                                                        </span>
                                                                                                    ) : (
                                                                                                        <span className="badge bg-secondary">-</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    {salida.tipo === 'PRODUCTO' ? (
                                                                                                        <span className="badge bg-primary">{salida.cantidad}</span>
                                                                                                    ) : (
                                                                                                        <span className="text-danger fw-bold">${salida.monto_usd}</span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td>
                                                                                                    <strong className="text-success">
                                                                                                        ${salida.tipo === 'PRODUCTO' ? 
                                                                                                            ((salida.cantidad || 0) * (salida.precio_unitario || 0)).toFixed(2) : 
                                                                                                            (salida.monto_usd || 0).toFixed(2)
                                                                                                        }
                                                                                                    </strong>
                                                                                                </td>
                                                                                            </tr>
                                                                                        ))}
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Resumen del Carrito Nuevo */}
                                                        {selectedGarantia.garantia_data && (selectedGarantia.garantia_data.entradas || selectedGarantia.garantia_data.salidas) && (
                                                            <div className="mt-3">
                                                                <div className="card bg-light">
                                                                    <div className="card-header">
                                                                        <h6 className="mb-0">
                                                                            <i className="fa fa-calculator me-2"></i>
                                                                            Resumen del Carrito
                                                                        </h6>
                                                                    </div>
                                                                    <div className="card-body">
                                                                        <div className="row">
                                                                            <div className="col-md-4">
                                                                                <strong>Total Entradas:</strong>
                                                                                <div className="text-warning fs-5">
                                                                                    ${(() => {
                                                                                        const entradas = selectedGarantia.garantia_data.entradas || [];
                                                                                        return entradas.reduce((sum, entrada) => {
                                                                                            return sum + (entrada.tipo === 'PRODUCTO' ? 
                                                                                                (entrada.cantidad || 0) * (entrada.precio_unitario || 0) : 
                                                                                                (entrada.monto_usd || 0));
                                                                                        }, 0).toFixed(2);
                                                                                    })()}
                                                                                </div>
                                                                            </div>
                                                                            <div className="col-md-4">
                                                                                <strong>Total Salidas:</strong>
                                                                                <div className="text-success fs-5">
                                                                                    ${(() => {
                                                                                        const salidas = selectedGarantia.garantia_data.salidas || [];
                                                                                        return salidas.reduce((sum, salida) => {
                                                                                            return sum + (salida.tipo === 'PRODUCTO' ? 
                                                                                                (salida.cantidad || 0) * (salida.precio_unitario || 0) : 
                                                                                                (salida.monto_usd || 0));
                                                                                        }, 0).toFixed(2);
                                                                                    })()}
                                                                                </div>
                                                                            </div>
                                                                            <div className="col-md-4">
                                                                                <strong>Balance:</strong>
                                                                                <div className={`fs-5 ${(() => {
                                                                                    const entradas = selectedGarantia.garantia_data.entradas || [];
                                                                                    const salidas = selectedGarantia.garantia_data.salidas || [];
                                                                                    const totalEntradas = entradas.reduce((sum, entrada) => {
                                                                                        return sum + (entrada.tipo === 'PRODUCTO' ? 
                                                                                            (entrada.cantidad || 0) * (entrada.precio_unitario || 0) : 
                                                                                            (entrada.monto_usd || 0));
                                                                                    }, 0);
                                                                                    const totalSalidas = salidas.reduce((sum, salida) => {
                                                                                        return sum + (salida.tipo === 'PRODUCTO' ? 
                                                                                            (salida.cantidad || 0) * (salida.precio_unitario || 0) : 
                                                                                            (salida.monto_usd || 0));
                                                                                    }, 0);
                                                                                    const diferencia = totalEntradas - totalSalidas;
                                                                                    return Math.abs(diferencia) < 0.01 ? 'text-muted' : 
                                                                                           diferencia > 0 ? 'text-primary' : 'text-danger';
                                                                                })()}`}>
                                                                                    ${(() => {
                                                                                        const entradas = selectedGarantia.garantia_data.entradas || [];
                                                                                        const salidas = selectedGarantia.garantia_data.salidas || [];
                                                                                        const totalEntradas = entradas.reduce((sum, entrada) => {
                                                                                            return sum + (entrada.tipo === 'PRODUCTO' ? 
                                                                                                (entrada.cantidad || 0) * (entrada.precio_unitario || 0) : 
                                                                                                (entrada.monto_usd || 0));
                                                                                        }, 0);
                                                                                        const totalSalidas = salidas.reduce((sum, salida) => {
                                                                                            return sum + (salida.tipo === 'PRODUCTO' ? 
                                                                                                (salida.cantidad || 0) * (salida.precio_unitario || 0) : 
                                                                                                (salida.monto_usd || 0));
                                                                                        }, 0);
                                                                                        const diferencia = totalEntradas - totalSalidas;
                                                                                        return (diferencia).toFixed(2);
                                                                                    })()}
                                                                                </div>
                                                                                <small className="text-muted">
                                                                                    {(() => {
                                                                                        const entradas = selectedGarantia.garantia_data.entradas || [];
                                                                                        const salidas = selectedGarantia.garantia_data.salidas || [];
                                                                                        const totalEntradas = entradas.reduce((sum, entrada) => {
                                                                                            return sum + (entrada.tipo === 'PRODUCTO' ? 
                                                                                                (entrada.cantidad || 0) * (entrada.precio_unitario || 0) : 
                                                                                                (entrada.monto_usd || 0));
                                                                                        }, 0);
                                                                                        const totalSalidas = salidas.reduce((sum, salida) => {
                                                                                            return sum + (salida.tipo === 'PRODUCTO' ? 
                                                                                                (salida.cantidad || 0) * (salida.precio_unitario || 0) : 
                                                                                                (salida.monto_usd || 0));
                                                                                        }, 0);
                                                                                        const diferencia = totalEntradas - totalSalidas;
                                                                                        return Math.abs(diferencia) < 0.01 ? 'Equilibrado' : 
                                                                                               diferencia > 0 ? 'A favor del cliente' : 'A favor de la empresa';
                                                                                    })()}
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Métodos de devolución */}
                                                {metodosDevolucion.length > 0 && (
                                                    <div className="mt-3">
                                                        <h6>Métodos de Devolución</h6>
                                                        <div className="table-responsive">
                                                            <table className="table table-sm table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Tipo</th>
                                                                        <th>Monto</th>
                                                                        <th>Moneda</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {metodosDevolucion.map((metodo, index) => (
                                                                        <tr key={index}>
                                                                            <td>{metodo.tipo || 'N/A'}</td>
                                                                            <td>{metodo.monto_original || 0}</td>
                                                                            <td>{metodo.moneda_original || 'N/A'}</td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                )}

                                                {(data.detalles_adicionales || selectedGarantia.detalles_adicionales) && (
                                                    <div className="mt-3">
                                                        <h6>Detalles Adicionales</h6>
                                                        <div className="alert alert-info">
                                                            {data.detalles_adicionales || selectedGarantia.detalles_adicionales}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Información adicional de Garantía Data */}
                                                {Object.keys(data).length > 0 && (
                                                    <div className="mt-3">
                                                        <h6>
                                                            <i className="fa fa-info-circle me-2"></i>
                                                            Información de Garantía
                                                        </h6>
                                                        <div className="row">
                                                            {data.dias_desdecompra !== undefined && (
                                                                <div className="col-md-6">
                                                                    <strong>Días desde compra:</strong> 
                                                                    <span className="badge bg-primary ms-2">{data.dias_desdecompra} días</span>
                                                                </div>
                                                            )}
                                                            {data.cantidad_salida !== undefined && (
                                                                <div className="col-md-6">
                                                                    <strong>Cantidad salida:</strong> 
                                                                    <span className="badge bg-info ms-2">{data.cantidad_salida}</span>
                                                                </div>
                                                            )}
                                                            {data.motivo_salida && (
                                                                <div className="col-md-12 mt-2">
                                                                    <strong>Motivo salida:</strong> 
                                                                    <div className="alert alert-secondary mt-1 mb-0 py-2">
                                                                        {data.motivo_salida}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Estado de Ejecución */}
                                                {(selectedGarantia.puede_ejecutarse !== undefined || selectedGarantia.inventario_afectado) && (
                                                    <div className="mt-3">
                                                        <h6>
                                                            <i className="fa fa-cogs me-2"></i>
                                                            Estado de Ejecución
                                                        </h6>
                                                        <div className="row">
                                                            {selectedGarantia.puede_ejecutarse !== undefined && (
                                                                <div className="col-md-6">
                                                                    <strong>Puede Ejecutarse:</strong>
                                                                    <span className={`badge ms-2 ${selectedGarantia.puede_ejecutarse ? 'bg-success' : 'bg-danger'}`}>
                                                                        {selectedGarantia.puede_ejecutarse ? 'Sí' : 'No'}
                                                                    </span>
                                                                </div>
                                                            )}
                                                            {selectedGarantia.inventario_afectado && (
                                                                <div className="col-md-6">
                                                                    <strong>Inventario Afectado:</strong>
                                                                    <span className="badge bg-warning ms-2">Sí</span>
                                                                </div>
                                                            )}
                                                            {selectedGarantia.fecha_ejecucion && (
                                                                <div className="col-md-12 mt-2">
                                                                    <strong>Fecha de Ejecución:</strong> 
                                                                    <span className="text-success ms-2">
                                                                        {new Date(selectedGarantia.fecha_ejecucion).toLocaleString()}
                                                                    </span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Galería de Fotos */}
                                            <div className="col-md-4">
                                                <h6>
                                                    <i className="fa fa-images me-2"></i>
                                                    Galería de Fotos
                                                </h6>
                                                
                                                {/* Foto de Factura */}
                                                {selectedGarantia.foto_factura_url && (
                                                    <div className="mb-3">
                                                        <h6 className="text-info">
                                                            <i className="fa fa-receipt me-2"></i>
                                                            Foto de Factura
                                                        </h6>
                                                        <div className="card">
                                                        <div className="position-relative">
                                                            <img 
                                                                src={`/api/garantias/proxy-central-storage?path=${encodeURIComponent(selectedGarantia.foto_factura_url || '')}`} 
                                                                alt="Foto de Factura"
                                                                    className="card-img-top"
                                                                    style={{ height: '200px', objectFit: 'cover', cursor: 'pointer' }}
                                                                onClick={() => window.open(`/api/garantias/proxy-central-storage?path=${encodeURIComponent(selectedGarantia.foto_factura_url || '')}`, '_blank')}
                                                                onError={(e) => {
                                                                    e.target.style.display = 'none';
                                                                        e.target.nextElementSibling.style.display = 'flex';
                                                                }}
                                                            />
                                                            <div 
                                                                    className="d-none position-absolute top-0 start-0 w-100 h-100 bg-light d-flex align-items-center justify-content-center"
                                                                    style={{ height: '200px' }}
                                                                >
                                                                    <div className="text-center text-muted">
                                                                        <i className="fa fa-receipt fa-3x mb-2"></i>
                                                                        <p>Factura no disponible</p>
                                                                        <small>{selectedGarantia.foto_factura_url}</small>
                                                                </div>
                                                                </div>
                                                                <div className="position-absolute top-0 end-0 m-2">
                                                                    <span className="badge bg-info">Factura</span>
                                                                </div>
                                                            </div>
                                                            <div className="card-body p-2">
                                                                <small className="text-muted">
                                                                    <i className="fa fa-info-circle me-1"></i>
                                                                    Factura #{selectedGarantia.factura_venta_id || 'N/A'}
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Fotos de Productos */}
                                                {selectedGarantia.fotos && selectedGarantia.fotos.length > 0 && (
                                                    <div className="mb-3">
                                                        <h6 className="text-success">
                                                            <i className="fa fa-camera me-2"></i>
                                                            Fotos de Productos ({selectedGarantia.fotos.length})
                                                        </h6>
                                                        <div className="row">
                                                            {selectedGarantia.fotos.map((foto, index) => {
                                                                // Construir URL de la foto vía proxy (conexión con Central siempre por backend)
                                                                const fotoUrl = foto.url_completa || 
                                                                               foto.foto_url_completa || 
                                                                               `/api/garantias/proxy-central-storage?path=${encodeURIComponent(foto.foto_url || '')}`;
                                                                
                                                                return (
                                                                    <div key={index} className="col-12 mb-3">
                                                                    <div className="card">
                                                                        <div className="position-relative">
                                                                            <img 
                                                                                    src={fotoUrl} 
                                                                                    alt={`Foto Producto ${index + 1}`}
                                                                                className="card-img-top"
                                                                                    style={{ height: '200px', objectFit: 'cover', cursor: 'pointer' }}
                                                                                    onClick={() => window.open(fotoUrl, '_blank')}
                                                                                onError={(e) => {
                                                                                        console.error('Error cargando foto de producto:', fotoUrl);
                                                                                    e.target.style.display = 'none';
                                                                                    e.target.nextElementSibling.style.display = 'flex';
                                                                                }}
                                                                            />
                                                                            <div 
                                                                                className="d-none position-absolute top-0 start-0 w-100 h-100 bg-light d-flex align-items-center justify-content-center"
                                                                                    style={{ height: '200px' }}
                                                                            >
                                                                                <div className="text-center text-muted">
                                                                                        <i className="fa fa-image fa-3x mb-2"></i>
                                                                                    <p>Imagen no disponible</p>
                                                                                        <small>{foto.foto_url}</small>
                                                                                </div>
                                                                            </div>
                                                                            <div className="position-absolute top-0 end-0 m-2">
                                                                                <span className="badge bg-primary">#{index + 1}</span>
                                                                            </div>
                                                                        </div>
                                                                        {foto.descripcion && (
                                                                            <div className="card-body p-2">
                                                                                <small className="text-muted">
                                                                                    <i className="fa fa-comment me-1"></i>
                                                                                    {foto.descripcion}
                                                                                </small>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Mensaje cuando no hay fotos */}
                                                {!selectedGarantia.foto_factura_url && (!selectedGarantia.fotos || selectedGarantia.fotos.length === 0) && (
                                                    <div className="alert alert-info text-center">
                                                        <div>
                                                            <i className="fa fa-camera fa-2x mb-2 text-muted"></i>
                                                            <p className="mb-1">No hay fotos disponibles</p>
                                                            <small className="text-muted">
                                                                Esta solicitud no incluye fotos de productos ni factura
                                                            </small>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Contador de fotos */}
                                                {(selectedGarantia.foto_factura_url || (selectedGarantia.fotos && selectedGarantia.fotos.length > 0)) && (
                                                    <div className="mt-3">
                                                        <div className="alert alert-success text-center py-2">
                                                            <small className="text-success">
                                                                <i className="fa fa-check-circle me-1"></i>
                                                                {(() => {
                                                                    const tieneFactura = selectedGarantia.foto_factura_url ? 1 : 0;
                                                                    const cantidadProductos = selectedGarantia.fotos ? selectedGarantia.fotos.length : 0;
                                                                    const total = tieneFactura + cantidadProductos;
                                                                    
                                                                    let mensaje = `${total} foto${total !== 1 ? 's' : ''} disponible${total !== 1 ? 's' : ''}`;
                                                                    if (tieneFactura && cantidadProductos > 0) {
                                                                        mensaje += ` (1 factura + ${cantidadProductos} producto${cantidadProductos !== 1 ? 's' : ''})`;
                                                                    } else if (tieneFactura) {
                                                                        mensaje += ' (factura)';
                                                                    } else if (cantidadProductos > 0) {
                                                                        mensaje += ` (producto${cantidadProductos !== 1 ? 's' : ''})`;
                                                                    }
                                                                    
                                                                    return mensaje;
                                                                })()}
                                                            </small>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })()}
                            </div>
                            <div className="modal-footer">
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => setSelectedGarantia(null)}
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal para seleccionar caja de ejecución - Totalmente Responsivo */}
            {showExecuteModal && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm modal-md-lg modal-lg-xl modal-xl">
                        <div className="modal-content">
                            {/* Header Totalmente Responsivo */}
                            <div className="modal-header bg-success text-white p-2 p-md-3">
                                <div className="flex-grow-1">
                                    <h5 className="modal-title mb-0 fs-6 fs-md-5 fw-bold">
                                        <i className="fa fa-play me-1 me-md-2"></i>
                                        <span className="d-none d-md-inline">Seleccionar Caja para Ejecutar Garantía</span>
                                        <span className="d-inline d-md-none">Ejecutar Garantía</span>
                                    </h5>
                                </div>
                                <button
                                    type="button"
                                    className="btn-close btn-close-white"
                                    onClick={() => {
                                        setShowExecuteModal(false);
                                        setSelectedGarantiaForExecute(null);
                                    }}
                                ></button>
                            </div>
                            
                            {/* Body Totalmente Responsivo */}
                            <div className="modal-body p-2 p-sm-3 p-md-4">
                                {selectedGarantiaForExecute && (
                                    <div className="mb-4">
                                        <h6 className="fs-6 fs-md-5 fw-bold text-dark mb-2 mb-md-3 d-flex align-items-center">
                                            <i className="fa fa-info-circle me-1 me-md-2 text-success"></i>
                                            <span className="d-none d-sm-inline">Detalles de la Solicitud</span>
                                            <span className="d-inline d-sm-none">Detalles</span>
                                        </h6>
                                        <div className="card shadow-sm border-0">
                                            <div className="card-body p-2 p-sm-3 p-md-4">
                                                {/* Layout Móvil Mejorado */}
                                                <div className="d-block d-md-none">
                                                    <div className="text-center p-2 p-sm-3 bg-light rounded mb-3">
                                                        <div className="h5 mb-1 text-success">#{selectedGarantiaForExecute.id}</div>
                                                        <small className="text-muted">Solicitud de Garantía</small>
                                                    </div>
                                                    
                                                    <div className="row g-2">
                                                        <div className="col-12">
                                                            <div className="bg-light p-2 rounded">
                                                                <small className="text-muted d-block fw-bold">Cliente</small>
                                                                <div className="fw-semibold text-truncate" title="{selectedGarantiaForExecute.cliente?.nombre} {selectedGarantiaForExecute.cliente?.apellido}">
                                                                    {selectedGarantiaForExecute.cliente?.nombre} {selectedGarantiaForExecute.cliente?.apellido}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div className="col-12 col-sm-6">
                                                            <div className="bg-light p-2 rounded">
                                                                <small className="text-muted d-block fw-bold">Estatus</small>
                                                                <span className={`badge ${getStatusColor(selectedGarantiaForExecute.estatus)}`}>
                                                                    {selectedGarantiaForExecute.estatus}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div className="col-12 col-sm-6">
                                                            <div className="bg-light p-2 rounded">
                                                                <small className="text-muted d-block fw-bold">Caso de Uso</small>
                                                                <small className="fw-semibold text-truncate d-block" title="{getCasoUsoDescription(selectedGarantiaForExecute.caso_uso)}">{getCasoUsoDescription(selectedGarantiaForExecute.caso_uso)}</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {/* Layout Tablet y Desktop Mejorado */}
                                                <div className="d-none d-md-block">
                                                    <div className="row g-2 g-lg-3">
                                                        <div className="col-lg-6">
                                                            <div className="mb-2 mb-lg-3">
                                                                <strong className="text-primary">Solicitud #:</strong> 
                                                                <span className="text-success ms-1 fw-bold">#{selectedGarantiaForExecute.id}</span>
                                                            </div>
                                                            <div className="mb-2 mb-lg-3">
                                                                <strong className="text-primary">Cliente:</strong> 
                                                                <span className="ms-1 text-truncate d-inline-block" style={{maxWidth: '200px'}} title="{selectedGarantiaForExecute.cliente?.nombre} {selectedGarantiaForExecute.cliente?.apellido}">
                                                                    {selectedGarantiaForExecute.cliente?.nombre} {selectedGarantiaForExecute.cliente?.apellido}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div className="col-lg-6">
                                                            <div className="mb-2 mb-lg-3">
                                                                <strong className="text-primary">Estatus:</strong> 
                                                                <span className={`badge ms-2 ${getStatusColor(selectedGarantiaForExecute.estatus)}`}>
                                                                    {selectedGarantiaForExecute.estatus}
                                                                </span>
                                                            </div>
                                                            <div className="mb-2 mb-lg-3">
                                                                <strong className="text-primary">Caso de uso:</strong> 
                                                                <span className="ms-1 text-truncate d-inline-block" style={{maxWidth: '200px'}} title="{getCasoUsoDescription(selectedGarantiaForExecute.caso_uso)}">
                                                                    {getCasoUsoDescription(selectedGarantiaForExecute.caso_uso)}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Selector de Caja Totalmente Responsivo */}
                                <div className="mb-3 mb-md-4">
                                    <label htmlFor="cajaExecuteSelect" className="form-label fw-bold text-dark d-flex align-items-center flex-wrap">
                                        <i className="fa fa-user me-1 me-md-2 text-primary"></i>
                                        <span className="d-none d-md-inline">Seleccione el Usuario de Caja:</span>
                                        <span className="d-inline d-md-none">Usuario de Caja:</span>
                                    </label>
                                    <select
                                        id="cajaExecuteSelect"
                                        className="form-select form-select-lg shadow-sm"
                                        value={selectedCaja}
                                        onChange={(e) => setSelectedCaja(parseInt(e.target.value))}
                                    >
                                        {cajasDisponiblesEjecutar.map(usuario => (
                                            <option key={usuario.id} value={usuario.id}>
                                                {usuario.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    {cajasDisponiblesEjecutar.length === 0 && (
                                        <div className="alert alert-warning mt-2 mt-md-3 d-flex align-items-start flex-column flex-sm-row">
                                            <i className="fa fa-exclamation-triangle me-0 me-sm-2 mb-1 mb-sm-0"></i>
                                            <span className="small text-center text-sm-start">No hay usuarios de caja disponibles en el sistema</span>
                                        </div>
                                    )}
                                </div>

                                {/* Nota Informativa Totalmente Responsiva */}
                                <div className="alert alert-info border-0 shadow-sm">
                                    <div className="d-flex align-items-start">
                                        <i className="fa fa-info-circle me-2 mt-1 text-info flex-shrink-0"></i>
                                        <div className="flex-grow-1">
                                            <strong className="d-block d-md-inline">Nota Importante:</strong>
                                            <span className="small d-block d-md-inline"> Este usuario de caja será utilizado para facturar los pedidos generados por la ejecución de la garantía.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Footer Totalmente Responsivo */}
                            <div className="modal-footer bg-light p-2 p-sm-3">
                                {/* Layout Móvil - Botones Verticales */}
                                <div className="d-block d-md-none w-100">
                                    <div className="d-grid gap-2">
                                        <button
                                            type="button"
                                            className="btn btn-success btn-lg py-3 shadow-sm"
                                            onClick={confirmarEjecucion}
                                            disabled={loading || cajasDisponiblesEjecutar.length === 0}
                                        >
                                            {loading ? (
                                                <>
                                                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                    <span className="d-none d-sm-inline">Ejecutando Garantía...</span>
                                                    <span className="d-inline d-sm-none">Ejecutando...</span>
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fa fa-play me-2"></i>
                                                    <span className="d-none d-sm-inline">Ejecutar Garantía</span>
                                                    <span className="d-inline d-sm-none">Ejecutar</span>
                                                </>
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary btn-lg py-2 shadow-sm"
                                            onClick={() => {
                                                setShowExecuteModal(false);
                                                setSelectedGarantiaForExecute(null);
                                            }}
                                            disabled={loading}
                                        >
                                            <i className="fa fa-times me-2"></i>
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                                
                                {/* Layout Tablet y Desktop - Botones Horizontales */}
                                <div className="d-none d-md-flex justify-content-end gap-2 w-100">
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary px-3 py-2 shadow-sm"
                                        onClick={() => {
                                            setShowExecuteModal(false);
                                            setSelectedGarantiaForExecute(null);
                                        }}
                                        disabled={loading}
                                    >
                                        <i className="fa fa-times me-1 me-lg-2"></i>
                                        <span className="d-none d-lg-inline">Cancelar</span>
                                        <span className="d-inline d-lg-none">Cancelar</span>
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-success px-3 px-lg-4 py-2 shadow-sm"
                                        onClick={confirmarEjecucion}
                                        disabled={loading || cajasDisponiblesEjecutar.length === 0}
                                    >
                                        {loading ? (
                                            <>
                                                <span className="spinner-border spinner-border-sm me-1 me-lg-2" role="status" aria-hidden="true"></span>
                                                <span className="d-none d-lg-inline">Ejecutando Garantía...</span>
                                                <span className="d-inline d-lg-none">Ejecutando...</span>
                                            </>
                                        ) : (
                                            <>
                                                <i className="fa fa-play me-1 me-lg-2"></i>
                                                <span className="d-none d-lg-inline">Ejecutar Garantía</span>
                                                <span className="d-inline d-lg-none">Ejecutar</span>
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal para seleccionar caja de impresión */}
            {showPrintModal && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">
                                    <i className="fa fa-print me-2"></i>
                                    Seleccionar Caja para Imprimir
                                </h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => {
                                        setShowPrintModal(false);
                                        setSelectedGarantiaForPrint(null);
                                    }}
                                ></button>
                            </div>
                            <div className="modal-body">
                                {selectedGarantiaForPrint && (
                                    <div className="mb-3">
                                        <h6>Detalles de la Solicitud:</h6>
                                        <div className="card">
                                            <div className="card-body">
                                                <p><strong>Solicitud #:</strong> {selectedGarantiaForPrint.id}</p>
                                                <p><strong>Cliente:</strong> {selectedGarantiaForPrint.cliente?.nombre} {selectedGarantiaForPrint.cliente?.apellido}</p>
                                                <p><strong>Estatus:</strong> 
                                                    <span className={`badge ms-2 ${getStatusColor(selectedGarantiaForPrint.estatus)}`}>
                                                        {selectedGarantiaForPrint.estatus}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="mb-3">
                                    <label htmlFor="cajaSelect" className="form-label">
                                        <i className="fa fa-cash-register me-2"></i>
                                        Seleccione la Caja:
                                    </label>
                                    <select
                                        id="cajaSelect"
                                        className="form-select"
                                        value={selectedCaja}
                                        onChange={(e) => setSelectedCaja(parseInt(e.target.value))}
                                    >
                                        {cajasDisponibles.map(caja => (
                                            <option key={caja.id} value={caja.id}>
                                                {caja.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    {cajasDisponibles.length === 0 && (
                                        <div className="alert alert-warning mt-2">
                                            <i className="fa fa-exclamation-triangle me-2"></i>
                                            No hay cajas configuradas en el sistema
                                        </div>
                                    )}
                                </div>

                                <div className="alert alert-info">
                                    <i className="fa fa-info-circle me-2"></i>
                                    <strong>Nota:</strong> El ticket se imprimirá en la caja seleccionada. 
                                    Asegúrese de que la impresora esté conectada y funcionando.
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button
                                    type="button"
                                    className="btn"
                                    onClick={() => {
                                        setShowPrintModal(false);
                                        setSelectedGarantiaForPrint(null);
                                    }}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    className="btn bg-sinapsis"
                                    onClick={confirmarImpresion}
                                    disabled={loading || cajasDisponibles.length === 0}
                                >
                                    {loading ? (
                                        <>
                                            <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                            Imprimiendo...
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa fa-print me-2"></i>
                                            Imprimir Ticket
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GarantiaList; 