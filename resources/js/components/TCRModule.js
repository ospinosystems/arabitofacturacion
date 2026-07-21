import React, { useEffect, useRef, useState } from 'react';

export default function TCRModule({
    pedidosCentral,
    indexPedidoCentral,
    setIndexPedidoCentral,
    qpedidoscentralq,
    setqpedidoscentralq,
    qpedidocentrallimit,
    setqpedidocentrallimit,
    qpedidocentralemisor,
    setqpedidocentralemisor,
    qpedidocentralestado,
    setqpedidocentralestado,
    sucursalesCentral,
    getPedidosCentral,
    getSucursales,
    showdetailsPEdido,
    setshowdetailsPEdido,
    moneda,
    selectPedidosCentral,
    checkPedidosCentral,
}) {
    const [productoSeleccionado, setProductoSeleccionado] = useState(null);
    const [busquedaMensaje, setBusquedaMensaje] = useState('');
    const inputGenericoRef = useRef(null);
    const [esperandoUbicacion, setEsperandoUbicacion] = useState(false);
    // Ubicación opcional (por defecto ON): al escanear un producto solo se CHEQUEA, sin pedir
    // el segundo escaneo de ubicación. Se recuerda entre sesiones.
    const [ubicacionOpcional, setUbicacionOpcional] = useState(() => {
        const v = typeof window !== 'undefined' ? window.localStorage.getItem('tcr_ubicacion_opcional') : null;
        return v === null ? true : v === '1';
    });
    const toggleUbicacionOpcional = () => {
        setUbicacionOpcional(prev => {
            const next = !prev;
            try { window.localStorage.setItem('tcr_ubicacion_opcional', next ? '1' : '0'); } catch (e) { /* noop */ }
            // Al activar "opcional" se cancela cualquier espera de ubicación en curso.
            if (next) { setEsperandoUbicacion(false); setProductoSeleccionado(null); }
            return next;
        });
    };

    // Un ítem está "listo" si tiene ubicación asignada o fue chequeado (modo opcional).
    const itemListo = (item) => !!(item && (item.warehouse_codigo || item.chequeado));

    // Marca (o desmarca) un producto como chequeado sin ubicación, vía selectPedidosCentral.
    const marcarChequeado = (index, valor) => {
        selectPedidosCentral({
            currentTarget: {
                attributes: {
                    'data-index': { value: index },
                    'data-tipo': { value: 'setcheck' },
                },
                value: valor ? 'true' : 'false',
            },
        });
    };

    // Función para buscar producto por código de barras y seleccionarlo
    const handleBuscarProducto = (e) => {
        if (e.key === 'Enter' || e.type === 'blur') {
            const codigo = e.target.value.trim();
            
            if (!codigo) return;

            // Si estamos esperando ubicación, procesar como ubicación
            if (esperandoUbicacion && productoSeleccionado !== null) {
                handleAsignarUbicacion(codigo);
                e.target.value = '';
                return;
            }

            const pedidoActual = pedidosCentral[indexPedidoCentral];
            if (!pedidoActual) return;

            // Buscar el producto por código de barras o proveedor
            const productoIndex = pedidoActual.items.findIndex(item => {
                const codigoBarras = item.producto?.codigo_barras?.toString().trim();
                const codigoProveedor = item.producto?.codigo_proveedor?.toString().trim();
                return codigoBarras === codigo || codigoProveedor === codigo;
            });

            if (productoIndex !== -1) {
                const producto = pedidoActual.items[productoIndex];

                // Si ya tiene ubicación, mostrar mensaje
                if (producto.warehouse_codigo) {
                    setBusquedaMensaje(`⚠️ Producto ya tiene ubicación: ${producto.warehouse_codigo}`);
                    e.target.value = '';
                    setTimeout(() => setBusquedaMensaje(''), 3000);
                    return;
                }

                // ── Modo ubicación OPCIONAL: solo chequear el producto, sin segundo escaneo ──
                if (ubicacionOpcional) {
                    if (producto.chequeado) {
                        setBusquedaMensaje(`⚠️ Producto ya chequeado: ${producto.producto.descripcion.substring(0, 45)}...`);
                    } else {
                        marcarChequeado(productoIndex, true);
                        setBusquedaMensaje(`✅ Chequeado: ${producto.producto.descripcion.substring(0, 50)}...`);
                    }
                    e.target.value = '';
                    // Refocar para el siguiente escaneo y hacer scroll al producto
                    setTimeout(() => {
                        const productoElement = document.querySelector(`[data-producto-card="${productoIndex}"]`);
                        if (productoElement) productoElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (inputGenericoRef.current) inputGenericoRef.current.focus();
                    }, 100);
                    setTimeout(() => setBusquedaMensaje(''), 2500);
                    return;
                }

                // ── Modo con ubicación: seleccionar el producto y esperar el segundo escaneo ──
                setProductoSeleccionado(productoIndex);
                setEsperandoUbicacion(true);
                setBusquedaMensaje(`✓ Producto seleccionado: ${producto.producto.descripcion.substring(0, 50)}... | 🔵 ESCANEA LA UBICACIÓN`);
                e.target.value = '';

                // Hacer scroll al producto seleccionado
                setTimeout(() => {
                    const productoElement = document.querySelector(`[data-producto-card="${productoIndex}"]`);
                    if (productoElement) {
                        productoElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 100);
            } else {
                setBusquedaMensaje('✗ Producto no encontrado en este pedido');
                e.target.value = '';
                setTimeout(() => setBusquedaMensaje(''), 3000);
            }
        }
    };

    // Función para asignar ubicación al producto seleccionado
    const handleAsignarUbicacion = async (codigoUbicacion) => {
        if (!codigoUbicacion || productoSeleccionado === null) return;

        const producto = pedidosCentral[indexPedidoCentral].items[productoSeleccionado];
        
        // Mostrar mensaje de validación
        setBusquedaMensaje(`🔍 Validando ubicación ${codigoUbicacion}...`);

        try {
            // Validar la ubicación con el backend
            const response = await fetch(`/warehouses/buscar?buscar=${encodeURIComponent(codigoUbicacion)}`);
            const data = await response.json();

            if (!data.ubicaciones || data.ubicaciones.length === 0) {
                // Ubicación no válida
                setBusquedaMensaje(`❌ Ubicación "${codigoUbicacion}" no existe o no está activa`);
                setTimeout(() => {
                    setBusquedaMensaje(`⚠️ Producto seleccionado: ${producto.producto.descripcion.substring(0, 50)}... | 🔵 ESCANEA LA UBICACIÓN`);
                }, 3000);
                return;
            }

            // Buscar coincidencia exacta con el código escaneado
            const ubicacionExacta = data.ubicaciones.find(u => 
                u.codigo.toLowerCase() === codigoUbicacion.toLowerCase()
            );

            if (!ubicacionExacta) {
                // No se encontró coincidencia exacta
                setBusquedaMensaje(`❌ No se encontró ubicación exacta con código "${codigoUbicacion}"`);
                setTimeout(() => {
                    setBusquedaMensaje(`⚠️ Producto seleccionado: ${producto.producto.descripcion.substring(0, 50)}... | 🔵 ESCANEA LA UBICACIÓN`);
                }, 3000);
                return;
            }

            // Ubicación válida, proceder con la asignación
            const ubicacionValida = ubicacionExacta;
            
            // Crear evento sintético para actualizar el warehouse_codigo
            const syntheticEvent = {
                currentTarget: {
                    attributes: {
                        'data-index': { value: productoSeleccionado },
                        'data-tipo': { value: 'changewarehouse' }
                    },
                    value: ubicacionValida.codigo,
                    setAttribute: function(name, value) {
                        this.attributes[name] = { value };
                    }
                }
            };

            selectPedidosCentral(syntheticEvent);

            setBusquedaMensaje(`✅ Ubicación ${ubicacionValida.codigo} (${ubicacionValida.nombre || 'Sin nombre'}) asignada a: ${producto.producto.descripcion.substring(0, 40)}...`);
            
            // Resetear estados
            setProductoSeleccionado(null);
            setEsperandoUbicacion(false);
            
            // Limpiar mensaje después de 2 segundos y volver a enfocar el input genérico
            setTimeout(() => {
                setBusquedaMensaje('');
                if (inputGenericoRef.current) {
                    inputGenericoRef.current.focus();
                    inputGenericoRef.current.select();
                }
            }, 2000);

        } catch (error) {
            console.error('Error al validar ubicación:', error);
            setBusquedaMensaje(`❌ Error al validar ubicación: ${error.message}`);
            setTimeout(() => {
                setBusquedaMensaje(`⚠️ Producto seleccionado: ${producto.producto.descripcion.substring(0, 50)}... | 🔵 ESCANEA LA UBICACIÓN`);
            }, 3000);
        }
    };

    // Imprime el ticket del producto (POST a una pestaña nueva). Extraído para mantener
    // la fila de la tabla compacta.
    const imprimirTicketProducto = (prod) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/warehouse-inventory/imprimir-ticket-producto';
        form.target = '_blank';

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        const campos = {
            'codigo_barras': prod.codigo_barras || '',
            'codigo_proveedor': prod.codigo_proveedor || '',
            'descripcion': prod.descripcion || '',
            'marca': prod.marca || ''
        };
        Object.keys(campos).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = campos[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    return (
        <div className="min-h-screen bg-gray-50 p-1 lg:p-6">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                {/* Columna Izquierda: Filtros y Lista */}
                <div className="lg:col-span-4 xl:col-span-3">
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        {/* Filtros */}
                        <div className="bg-gray-50 p-2.5 border-b border-gray-200">
                            <h2 className="text-xs font-bold text-gray-600 flex items-center gap-1.5 mb-2 uppercase tracking-wide">
                                <i className="fas fa-filter text-blue-500"></i>
                                Filtros de Búsqueda
                            </h2>

                            <form onSubmit={event => { event.preventDefault(); getPedidosCentral() }} className="space-y-1.5">
                                {/* ID y Resultados */}
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="col-span-2">
                                        <input 
                                            type="text" 
                                            className="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition" 
                                            placeholder='Nº Transferencia...' 
                                            value={qpedidoscentralq} 
                                            onChange={e => setqpedidoscentralq(e.target.value)} 
                                        />
                                    </div>
                                    <div>
                                        <select 
                                            className="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                                            value={qpedidocentrallimit} 
                                            onChange={e => setqpedidocentrallimit(e.target.value)}
                                        >
                                            <option value="">Todo</option>
                                            <option value="5">5</option>
                                            <option value="10">10</option>
                                            <option value="20">20</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Estado y Emisor */}
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="col-span-2">
                                        <select
                                            className="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                                            value={qpedidocentralestado}
                                            onChange={e => setqpedidocentralestado(e.target.value)}
                                            title="Estado"
                                        >
                                            <option value="">Todos los estados</option>
                                            <option value="1">Pendiente</option>
                                            <option value="3">En revisión</option>
                                            <option value="4">Revisado</option>
                                        </select>
                                    </div>
                                    <div>
                                        <button 
                                            type="button" 
                                            className="w-full h-full px-2 py-1.5 bg-gray-50 hover:bg-gray-100 border border-gray-300 rounded-lg transition text-gray-500 hover:text-gray-700"
                                            onClick={getSucursales}
                                            title="Actualizar sucursales"
                                        >
                                            <i className="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </div>

                                {/* Emisor */}
                                <div>
                                    <select 
                                        className="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                                        value={qpedidocentralemisor} 
                                        onChange={e => setqpedidocentralemisor(e.target.value)}
                                    >
                                        <option value="">Todas las Sucursales</option>
                                        {sucursalesCentral.map(e =>
                                            <option value={e.id} key={e.id}>
                                                {e.nombre}
                                            </option>
                                        )}
                                    </select>
                                </div>

                                {/* Botón de búsqueda */}
                                <button
                                    type="submit"
                                    className="w-full px-3 py-1.5 text-sm bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow hover:shadow-lg transition"
                                >
                                    <i className="fas fa-search mr-1.5"></i>
                                    BUSCAR TRANSFERENCIAS
                                </button>
                            </form>
                        </div>
        
                        {/* Lista de Transferencias */}
                        <div className="p-4">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="font-bold text-gray-700 flex items-center gap-2">
                                    <i className="fas fa-list text-blue-500"></i>
                                    Transferencias
                                </h3>
                                <button 
                                    className="p-2 hover:bg-gray-50 rounded-lg transition"
                                    onClick={() => setshowdetailsPEdido(!showdetailsPEdido)}
                                >
                                    <i className={`fas ${showdetailsPEdido ? 'fa-chevron-up' : 'fa-chevron-down'} text-gray-500`}></i>
                                </button>
                            </div>

                            <div className={`transition-all duration-300 overflow-hidden ${showdetailsPEdido ? 'max-h-[70vh]' : 'max-h-0'}`}>
                                <div className="space-y-2 overflow-y-auto pr-1" style={{ maxHeight: '70vh' }}>
                                    {pedidosCentral.length ? (
                                        pedidosCentral.map((e, i) =>
                                            e ? (
                                                <div 
                                                    onClick={() => setIndexPedidoCentral(i)} 
                                                    data-index={i} 
                                                    key={e.id} 
                                                    className={`group cursor-pointer rounded-lg border transition-all ${
                                                        indexPedidoCentral === i 
                                                            ? 'border-blue-400 bg-blue-50 shadow' 
                                                            : 'border-gray-200 hover:border-blue-300 bg-white hover:shadow'
                                                    }`}
                                                >
                                                    <div className="flex items-stretch">
                                                        {/* Indicador de estado */}
                                                        <div className={`flex items-center justify-center w-12 rounded-l-lg ${
                                                            e.estado == 1 ? 'bg-red-500' :
                                                            e.estado == 2 ? 'bg-green-500' :
                                                            e.estado == 3 ? 'bg-yellow-500' :
                                                            e.estado == 4 ? 'bg-blue-500' : 'bg-gray-300'
                                                        }`}>
                                                            <i className={`fas ${
                                                                e.estado == 1 ? 'fa-exclamation-circle' :
                                                                e.estado == 2 ? 'fa-check-circle' :
                                                                e.estado == 3 ? 'fa-search' :
                                                                e.estado == 4 ? 'fa-check-double' : 'fa-question-circle'
                                                            } text-white text-lg`}></i>
                                                        </div>
                                                        
                                                        {/* Contenido */}
                                                        <div className="flex-1 p-3">
                                                            <div className="flex items-center justify-between mb-2">
                                                                <div className="flex items-center gap-2 flex-wrap">
                                                                    <span className="px-2 py-1 bg-gray-700 text-white text-xs font-bold rounded">
                                                                        #{e.id}
                                                                    </span>
                                                                    <span 
                                                                        className="px-2 py-1 text-xs font-bold rounded"
                                                                        style={{ 
                                                                            backgroundColor: e.origen.background, 
                                                                            color: e.origen.color || '#fff' 
                                                                        }}
                                                                    >
                                                                        {e.origen.codigo}
                                                                    </span>
                                                                    <span className={`px-2 py-1 text-[10px] font-bold rounded border ${
                                                                        e.estado == 1 ? 'bg-red-100 text-red-700 border-red-200' :
                                                                        e.estado == 3 ? 'bg-orange-100 text-orange-700 border-orange-200' :
                                                                        e.estado == 4 ? 'bg-blue-100 text-blue-700 border-blue-200' :
                                                                        'bg-gray-100 text-gray-600 border-gray-200'
                                                                    }`}>
                                                                        {e.estado == 1 ? 'PENDIENTE' :
                                                                         e.estado == 3 ? 'EN REVISIÓN' :
                                                                         e.estado == 4 ? 'REVISADO' : 'DESCONOCIDO'}
                                                                    </span>
                                                                </div>
                                                                <span className="text-xs text-gray-500 font-semibold">
                                                                    <i className="fas fa-boxes mr-1"></i>
                                                                    {e.items.length}
                                                                </span>
                                                            </div>
                                                            
                                                            {e.cxp && (
                                                                <div className="text-xs font-semibold text-gray-700 mb-1">
                                                                    <span className="text-blue-600">FACT {e.cxp.numfact}</span>
                                                                    <span className="text-gray-500 ml-2">
                                                                        {e.cxp.proveedor.descripcion.substr(0, 20)}
                                                                        {e.cxp.proveedor.descripcion.length > 20 ? "..." : ""}
                                                                    </span>
                                                                </div>
                                                            )}
                                                            
                                                            <div className="text-xs text-gray-400">
                                                                <i className="far fa-clock mr-1"></i>
                                                                {e.created_at}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ) : null
                                        )
                                    ) : (
                                        <div className="text-center py-12 text-gray-400">
                                            <i className="fas fa-inbox text-5xl mb-3"></i>
                                            <p className="font-medium">¡Sin resultados!</p>
                                            <p className="text-sm">Intenta ajustar los filtros</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        
                {/* Columna Derecha: Sistema de Pistoleo */}
                <div className="lg:col-span-8 xl:col-span-9">
                    {indexPedidoCentral !== null && pedidosCentral && pedidosCentral[indexPedidoCentral] ? (
                        <div className="space-y-3">
                            {/* Header del pedido */}
                            <div className="bg-white rounded-lg shadow p-3 border-l-4 border-blue-500">
                                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-bold text-gray-700">Pedido</span>
                                        <span className="px-2 py-0.5 bg-blue-500 text-white font-bold text-sm rounded">
                                            #{pedidosCentral[indexPedidoCentral].id}
                                        </span>
                                        <span className="px-2 py-0.5 bg-emerald-100 text-emerald-700 font-semibold text-xs rounded-full border border-emerald-300">
                                            REVISADO
                                        </span>
                                        <span className="text-xs text-gray-400">
                                            <i className="far fa-calendar mr-1"></i>
                                            {pedidosCentral[indexPedidoCentral].created_at}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-4 text-sm">
                                        <span className="text-gray-500">Base: <b className="text-blue-600">{moneda(pedidosCentral[indexPedidoCentral].base)}</b></span>
                                        <span className="text-gray-500">Venta: <b className="text-emerald-600">{moneda(pedidosCentral[indexPedidoCentral].venta)}</b></span>
                                        <span className="text-gray-500"><i className="fas fa-boxes mr-1"></i><b>{pedidosCentral[indexPedidoCentral].items.length}</b> items</span>
                                    </div>
                                </div>
                            </div>

                            {/* Panel de Pistoleo Principal */}
                            <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                                <div className="bg-blue-600 px-3 py-2 border-b border-blue-700 flex items-center justify-between gap-2 flex-wrap">
                                    <h2 className="text-sm font-bold text-white flex items-center gap-2">
                                        <i className="fas fa-barcode"></i>
                                        {ubicacionOpcional ? 'Pistoleo · Chequeo de Productos' : 'Pistoleo · Asignación de Ubicaciones'}
                                    </h2>
                                    {/* Toggle: ubicación opcional (por defecto ON = solo chequea) */}
                                    {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                        <button
                                            type="button"
                                            onClick={toggleUbicacionOpcional}
                                            className={`inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-bold transition border ${ubicacionOpcional ? 'bg-white text-blue-700 border-white' : 'bg-blue-500/30 text-white border-blue-300'}`}
                                            title={ubicacionOpcional ? 'Ubicación opcional activada: escanear solo chequea el producto. Click para exigir ubicación.' : 'Se exige escanear ubicación tras cada producto. Click para hacerla opcional.'}
                                        >
                                            <i className={`fas ${ubicacionOpcional ? 'fa-toggle-on' : 'fa-toggle-off'}`}></i>
                                            {ubicacionOpcional ? 'Ubicación opcional' : 'Ubicación requerida'}
                                        </button>
                                    )}
                                </div>

                                {/* Input de pistoleo - STICKY - Solo si está REVISADO */}
                                {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                    <div className={`sticky top-0 z-50 border-b px-3 py-2 bg-white ${esperandoUbicacion ? 'border-amber-500' : 'border-blue-500'}`}>
                                        <div className="flex items-center gap-2">
                                            <span className={`flex items-center gap-1 text-xs font-bold whitespace-nowrap ${esperandoUbicacion ? 'text-amber-700' : 'text-blue-700'}`}>
                                                <i className={`fas ${esperandoUbicacion ? 'fa-map-marker-alt' : 'fa-barcode'}`}></i>
                                                {esperandoUbicacion ? 'Escanea ubicación' : (ubicacionOpcional ? 'Escanea para chequear' : 'Busca producto')}
                                            </span>
                                            <input
                                                ref={inputGenericoRef}
                                                type="text"
                                                placeholder={esperandoUbicacion ? "Escanea ubicación" : "Código de barras o proveedor"}
                                                onKeyPress={handleBuscarProducto}
                                                className={`flex-1 px-2 py-1 text-sm font-mono rounded border focus:ring-1 transition ${esperandoUbicacion ? 'border-amber-500 focus:ring-amber-500' : 'border-blue-400 focus:ring-blue-400'}`}
                                                autoFocus
                                            />
                                        </div>
                                    </div>
                                )}

                                <div className="p-3 space-y-3">

                                    {/* Botón seleccionar/deseleccionar todos - Solo en estado PENDIENTE */}
                                    {pedidosCentral[indexPedidoCentral].estado === 1 && (
                                        <div className="space-y-2">
                                            <button
                                                onClick={(e) => {
                                                    e.currentTarget.setAttribute('data-index', 'all');
                                                    e.currentTarget.setAttribute('data-tipo', 'selectall');
                                                    selectPedidosCentral(e);
                                                }}
                                                data-index="all"
                                                data-tipo="selectall"
                                                className="w-full px-3 py-2 text-sm bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow transition flex items-center justify-center gap-2"
                                            >
                                                <i className="fas fa-check-double"></i>
                                                {pedidosCentral[indexPedidoCentral].items.every(item => item.aprobado === true)
                                                    ? 'Deseleccionar Todos'
                                                    : 'Seleccionar Todos'}
                                            </button>

                                            {pedidosCentral[indexPedidoCentral].items.length > 0 &&
                                             pedidosCentral[indexPedidoCentral].items.every(item => item.aprobado === true) && (
                                                <button
                                                    onClick={checkPedidosCentral}
                                                    className="w-full px-3 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow transition flex items-center justify-center gap-2"
                                                >
                                                    <i className="fas fa-save"></i>
                                                    Guardar Pedido Revisado
                                                </button>
                                            )}
                                        </div>
                                    )}

                                    

                                    {/* Lista de productos */}
                                    <div className="bg-white rounded-lg p-3 shadow">
                                        <h3 className="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                            <i className="fas fa-clipboard-list text-blue-500"></i>
                                            Lista de Productos
                                            {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                                <span className="text-sm font-normal text-gray-500 ml-2">
                                                    {ubicacionOpcional
                                                        ? '(Escanea cada producto para chequearlo — ubicación opcional)'
                                                        : '(Escanea el producto y luego su ubicación)'}
                                                </span>
                                            )}
                                        </h3>
                                        
                                        <div className="max-h-[600px] overflow-y-auto overflow-x-auto border border-gray-200 rounded-lg">
                                            <table className="w-full text-xs border-collapse">
                                                <thead className="sticky top-0 z-10 bg-gray-100">
                                                    <tr className="text-gray-500 uppercase text-[10px] tracking-wide">
                                                        {pedidosCentral[indexPedidoCentral].estado === 1 && (
                                                            <th className="px-2 py-1.5 text-center w-8"></th>
                                                        )}
                                                        <th className="px-2 py-1.5 text-center w-10">#</th>
                                                        <th className="px-2 py-1.5 text-left">Descripción</th>
                                                        <th className="px-2 py-1.5 text-left whitespace-nowrap">Cód. Barras</th>
                                                        <th className="px-2 py-1.5 text-left whitespace-nowrap">Cód. Prov.</th>
                                                        <th className="px-2 py-1.5 text-center w-14">Cant.</th>
                                                        {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                                            <th className="px-2 py-1.5 text-left whitespace-nowrap w-28">Ubicación</th>
                                                        )}
                                                        <th className="px-2 py-1.5 text-center w-24">Estado</th>
                                                        <th className="px-2 py-1.5 text-center w-10"></th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {pedidosCentral[indexPedidoCentral].items.map((e, i) => {
                                                        const estado = pedidosCentral[indexPedidoCentral].estado;
                                                        const seleccionado = estado === 4 && productoSeleccionado === i;
                                                        const listo = itemListo(e);
                                                        const rowBg = estado === 4
                                                            ? (listo
                                                                ? 'bg-emerald-50'
                                                                : seleccionado
                                                                ? 'bg-blue-100 ring-2 ring-inset ring-blue-400'
                                                                : 'bg-amber-50/60 hover:bg-amber-50')
                                                            : (estado === 1
                                                                ? (e.aprobado === true
                                                                    ? 'bg-emerald-50'
                                                                    : e.aprobado === false
                                                                    ? 'bg-red-50'
                                                                    : 'bg-white hover:bg-gray-50')
                                                                : 'bg-white');
                                                        const numBadge = estado === 4
                                                            ? (listo ? 'bg-emerald-500' : 'bg-amber-500')
                                                            : (estado === 1
                                                                ? (e.aprobado === true ? 'bg-emerald-500' : e.aprobado === false ? 'bg-red-500' : 'bg-gray-400')
                                                                : 'bg-gray-400');
                                                        return (
                                                            <tr
                                                                key={e.id}
                                                                data-producto-card={i}
                                                                className={`transition-colors ${rowBg}`}
                                                            >
                                                                {estado === 1 && (
                                                                    <td className="px-2 py-1.5 text-center align-middle">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={e.aprobado === true}
                                                                            onChange={() => {}}
                                                                            onClick={(event) => {
                                                                                event.currentTarget.setAttribute('data-index', i);
                                                                                event.currentTarget.setAttribute('data-tipo', 'select');
                                                                                selectPedidosCentral(event);
                                                                            }}
                                                                            data-index={i}
                                                                            data-tipo="select"
                                                                            className="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer align-middle"
                                                                        />
                                                                    </td>
                                                                )}
                                                                <td className="px-2 py-1.5 text-center align-middle">
                                                                    <span className={`inline-block px-1.5 py-0.5 font-bold text-white rounded ${numBadge}`}>
                                                                        {i + 1}
                                                                    </span>
                                                                </td>
                                                                <td className="px-2 py-1.5 align-middle">
                                                                    <span className="font-semibold text-gray-700 block truncate max-w-[22rem]" title={e.producto.descripcion}>
                                                                        {e.producto.descripcion}
                                                                    </span>
                                                                </td>
                                                                <td className="px-2 py-1.5 align-middle font-mono text-gray-600 whitespace-nowrap">
                                                                    {e.producto.codigo_barras || 'N/A'}
                                                                </td>
                                                                <td className="px-2 py-1.5 align-middle font-mono text-gray-600 whitespace-nowrap">
                                                                    {e.producto.codigo_proveedor || 'N/A'}
                                                                </td>
                                                                <td className="px-2 py-1.5 text-center align-middle">
                                                                    <span className="font-bold text-blue-600">{e.cantidad}</span>
                                                                </td>
                                                                {estado === 4 && (
                                                                    <td className="px-2 py-1.5 align-middle whitespace-nowrap">
                                                                        {e.warehouse_codigo ? (
                                                                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 font-mono font-bold rounded border border-emerald-300">
                                                                                <i className="fas fa-map-marker-alt"></i>
                                                                                {e.warehouse_codigo}
                                                                            </span>
                                                                        ) : e.chequeado ? (
                                                                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 font-bold rounded border border-emerald-300">
                                                                                <i className="fas fa-check"></i> Chequeado
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-amber-500 font-semibold">— sin ubicar</span>
                                                                        )}
                                                                    </td>
                                                                )}
                                                                <td className="px-2 py-1.5 text-center align-middle">
                                                                    {estado === 4 ? (
                                                                        listo ? (
                                                                            <div className="inline-flex items-center gap-1">
                                                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-500 text-white font-bold rounded-full">
                                                                                    <i className="fas fa-check-circle"></i> COMPLETADO
                                                                                </span>
                                                                                {/* Deshacer chequeo (solo si se chequeó sin ubicación) */}
                                                                                {!e.warehouse_codigo && e.chequeado && (
                                                                                    <button
                                                                                        onClick={() => marcarChequeado(i, false)}
                                                                                        className="text-gray-400 hover:text-red-500"
                                                                                        title="Deshacer chequeo"
                                                                                    >
                                                                                        <i className="fas fa-rotate-left"></i>
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                        ) : ubicacionOpcional ? (
                                                                            <button
                                                                                onClick={() => marcarChequeado(i, true)}
                                                                                className="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full transition"
                                                                                title="Chequear producto (sin ubicación)"
                                                                            >
                                                                                <i className="fas fa-check"></i> Chequear
                                                                            </button>
                                                                        ) : (
                                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-500 text-white font-bold rounded-full">
                                                                                <i className="fas fa-barcode"></i> PENDIENTE
                                                                            </span>
                                                                        )
                                                                    ) : estado === 1 ? (
                                                                        e.aprobado === true ? (
                                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-500 text-white font-bold rounded-full">
                                                                                <i className="fas fa-check-circle"></i> APROBADO
                                                                            </span>
                                                                        ) : e.aprobado === false ? (
                                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-red-500 text-white font-bold rounded-full">
                                                                                <i className="fas fa-times-circle"></i> RECHAZADO
                                                                            </span>
                                                                        ) : (
                                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-400 text-white font-bold rounded-full">
                                                                                <i className="fas fa-circle"></i> SIN REVISAR
                                                                            </span>
                                                                        )
                                                                    ) : null}
                                                                </td>
                                                                <td className="px-2 py-1.5 text-center align-middle">
                                                                    <button
                                                                        onClick={() => imprimirTicketProducto(e.producto)}
                                                                        className="px-1.5 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded transition"
                                                                        title="Imprimir Ticket"
                                                                    >
                                                                        <i className="fas fa-print"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {/* NOTA: El botón de guardar ahora está en el resumen de progreso cuando todos tienen ubicación */}


                                    {/* Resumen de progreso - Solo si está REVISADO */}
                                    {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                        <div className="bg-white rounded-lg p-4 shadow space-y-3">
                                            <h3 className="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                                <i className="fas fa-chart-pie text-blue-500"></i>
                                                Progreso de Recepción
                                            </h3>

                                            <div className="grid grid-cols-3 gap-3 mb-3">
                                                <div className="bg-emerald-50 rounded-lg p-4 border border-emerald-200">
                                                    <div className="text-2xl font-bold text-emerald-600 mb-2">
                                                        {pedidosCentral[indexPedidoCentral].items.filter(itemListo).length}
                                                    </div>
                                                    <div className="text-xs font-semibold text-emerald-700 uppercase flex items-center gap-1">
                                                        <i className="fas fa-check-circle"></i>
                                                        Chequeados
                                                    </div>
                                                </div>

                                                <div className="bg-amber-50 rounded-lg p-4 border border-amber-200">
                                                    <div className="text-2xl font-bold text-amber-600 mb-2">
                                                        {pedidosCentral[indexPedidoCentral].items.filter(e => !itemListo(e)).length}
                                                    </div>
                                                    <div className="text-xs font-semibold text-amber-700 uppercase flex items-center gap-1">
                                                        <i className="fas fa-clock"></i>
                                                        Pendientes
                                                    </div>
                                                </div>

                                                <div className="bg-blue-50 rounded-lg p-4 border border-blue-200">
                                                    <div className="text-2xl font-bold text-blue-600 mb-2">
                                                        {pedidosCentral[indexPedidoCentral].items.length}
                                                    </div>
                                                    <div className="text-xs font-semibold text-blue-700 uppercase flex items-center gap-1">
                                                        <i className="fas fa-boxes"></i>
                                                        Total
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Barra de progreso mejorada */}
                                            <div className="relative h-8 bg-gray-100 rounded-full overflow-hidden shadow-inner">
                                                <div
                                                    className="absolute inset-y-0 left-0 bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all duration-500 ease-out flex items-center justify-center"
                                                    style={{width: `${(pedidosCentral[indexPedidoCentral].items.filter(itemListo).length / pedidosCentral[indexPedidoCentral].items.length) * 100}%`}}
                                                >
                                                    <span className="text-white font-bold text-sm">
                                                        {Math.round((pedidosCentral[indexPedidoCentral].items.filter(itemListo).length / pedidosCentral[indexPedidoCentral].items.length) * 100)}%
                                                    </span>
                                                </div>
                                            </div>

                                            {/* En REVISADO los productos ya fueron verificados en PENDIENTE: el pistoleo de
                                                ubicación es OPCIONAL, así que la recepción se puede guardar siempre (no exige
                                                volver a escanear/chequear cada producto). */}
                                            {pedidosCentral[indexPedidoCentral].items.length > 0 && (() => {
                                                const items = pedidosCentral[indexPedidoCentral].items;
                                                const pendientes = items.filter(e => !itemListo(e)).length;
                                                const todosConUbic = items.every(item => item.warehouse_codigo);
                                                return (
                                                    <div className="pt-3 border-t border-gray-200">
                                                        {pendientes > 0 && (
                                                            <p className="text-xs text-gray-500 mb-2 text-center">
                                                                <i className="fas fa-circle-info mr-1"></i>
                                                                {pendientes} producto(s) sin ubicación. No es obligatorio: podés recibir igual.
                                                            </p>
                                                        )}
                                                        <button
                                                            onClick={checkPedidosCentral}
                                                            className="w-full px-3 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow transition flex items-center justify-center gap-2"
                                                        >
                                                            <i className="fas fa-save"></i>
                                                            {todosConUbic ? 'Guardar Recepción con Ubicaciones' : 'Guardar Recepción'}
                                                        </button>
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow p-6 text-center">
                            <div className="max-w-md mx-auto">
                                <i className="fas fa-hand-pointer text-gray-300 text-5xl mb-3"></i>
                                <h3 className="font-bold text-gray-700 mb-1">Seleccione una transferencia</h3>
                                <p className="text-sm text-gray-500">Haz clic en una transferencia de la lista para comenzar la recepción.</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
