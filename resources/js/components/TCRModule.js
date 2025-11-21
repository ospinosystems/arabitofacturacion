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

                // Seleccionar el producto y esperar ubicación
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

    return (
        <div className="min-h-screen bg-gray-50 p-4 lg:p-6">
            {/* Header mejorado - Más compacto */}
            <div className="mb-4 bg-white rounded-lg shadow p-4 border-l-4 border-blue-400">
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl lg:text-3xl font-bold text-gray-700 flex items-center gap-2">
                            <span className="text-blue-600">
                                <i className="fas fa-warehouse"></i> TCR
                            </span>
                            <span className="text-gray-500">Recepción de Mercancía</span>
                        </h1>
                        <p className="text-gray-400 mt-1 text-xs lg:text-sm">Sistema inteligente de gestión y ubicación</p>
                    </div>
                    <div className="flex items-center gap-2 px-3 py-1.5 bg-blue-500 text-white rounded-lg shadow text-sm">
                        <i className="fas fa-barcode"></i>
                        <span className="font-semibold">Sistema de Pistoleo</span>
                    </div>
                </div>
            </div>
        
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                {/* Columna Izquierda: Filtros y Lista */}
                <div className="lg:col-span-4 xl:col-span-3">
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        {/* Filtros */}
                        <div className="bg-gray-50 p-4 border-b border-gray-200">
                            <h2 className="text-lg font-bold text-gray-700 flex items-center gap-2 mb-4">
                                <i className="fas fa-filter text-blue-500"></i>
                                Filtros de Búsqueda
                            </h2>
                            
                            <form onSubmit={event => { event.preventDefault(); getPedidosCentral() }} className="space-y-3">
                                {/* ID y Resultados */}
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="col-span-2">
                                        <input 
                                            type="text" 
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition" 
                                            placeholder='Nº Transferencia...' 
                                            value={qpedidoscentralq} 
                                            onChange={e => setqpedidoscentralq(e.target.value)} 
                                        />
                                    </div>
                                    <div>
                                        <select 
                                            className="w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
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
                                            className="w-full px-3 py-2 text-sm font-semibold text-green-700 bg-green-50 border border-green-300 rounded-lg cursor-not-allowed"
                                            value="4"
                                            disabled
                                        >
                                            <option value="4">✓ REVISADO</option>
                                        </select>
                                        <p className="text-xs text-gray-400 mt-1 text-center">Solo pedidos revisados</p>
                                    </div>
                                    <div>
                                        <button 
                                            type="button" 
                                            className="w-full h-full px-2 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-300 rounded-lg transition text-gray-500 hover:text-gray-700"
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
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
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
                                    className="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow hover:shadow-lg transition transform hover:scale-[1.01] active:scale-[0.99]"
                                >
                                    <i className="fas fa-search mr-2"></i>
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
                        <div className="space-y-6">
                            {/* Header del pedido */}
                            <div className="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                                <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-gray-400 mb-2">
                                            <i className="far fa-calendar mr-1"></i>
                                            {pedidosCentral[indexPedidoCentral].created_at}
                                        </p>
                                        <div className="flex items-center gap-3 flex-wrap">
                                            <span className="text-2xl font-bold text-gray-700">Pedido</span>
                                            <span className="px-4 py-2 bg-blue-500 text-white font-bold text-xl rounded-lg shadow">
                                                #{pedidosCentral[indexPedidoCentral].id}
                                            </span>
                                            <span className="px-3 py-1 bg-emerald-100 text-emerald-700 font-semibold text-sm rounded-full border border-emerald-300">
                                                <i className="fas fa-check-circle mr-1"></i>
                                                REVISADO
                                            </span>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="flex items-center justify-end gap-2 mb-2">
                                            <span className="text-sm text-gray-400">Base:</span>
                                            <span className="text-lg font-bold text-blue-600">
                                                {moneda(pedidosCentral[indexPedidoCentral].base)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-end gap-2 mb-2">
                                            <span className="text-sm text-gray-400">Venta:</span>
                                            <span className="text-2xl font-bold text-emerald-600">
                                                {moneda(pedidosCentral[indexPedidoCentral].venta)}
                                            </span>
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            <i className="fas fa-boxes mr-1"></i>
                                            <b>{pedidosCentral[indexPedidoCentral].items.length}</b> items
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Panel de Pistoleo Principal */}
                            <div className="bg-white rounded-lg shadow-xl overflow-hidden border border-gray-200">
                                <div className="bg-gradient-to-r from-blue-500 to-blue-600 p-4 border-b border-blue-700">
                                    <h2 className="text-xl font-bold text-white flex items-center gap-2">
                                        <i className="fas fa-barcode text-2xl"></i>
                                        Sistema de Pistoleo - Asignación de Ubicaciones
                                    </h2>
                                </div>
                                
                                {/* Input genérico de búsqueda de productos - STICKY - Solo si está REVISADO */}
                                {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                    <div className={`sticky top-0 z-50 shadow-lg border-b-4 -mx-6 px-6 py-4 mb-4 transition-all ${
                                        esperandoUbicacion 
                                            ? 'bg-gradient-to-r from-orange-100 to-orange-50 border-orange-500' 
                                            : 'bg-white border-blue-500'
                                    }`}>
                                        <div className={`rounded-lg p-4 border-2 shadow-md transition-all ${
                                            esperandoUbicacion
                                                ? 'bg-gradient-to-r from-orange-50 to-yellow-50 border-orange-400 animate-pulse'
                                                : 'bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-300'
                                        }`}>
                                            <div className="flex items-start gap-4">
                                                <div className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ${
                                                    esperandoUbicacion ? 'bg-orange-500' : 'bg-blue-500'
                                                }`}>
                                                    <i className={`fas ${esperandoUbicacion ? 'fa-map-marker-alt' : 'fa-barcode'} text-white text-xl`}></i>
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center mb-2 gap-2 font-bold text-sm">
                                                        <i className={`fas ${esperandoUbicacion ? 'fa-map-marker-alt text-orange-700' : 'fa-barcode text-blue-600'}`}></i>
                                                        <span className={esperandoUbicacion ? 'text-orange-700' : 'text-blue-700'}>
                                                            {esperandoUbicacion ? 'Escanea Ubicación' : 'Busca Producto'}
                                                        </span>
                                                        <span className={`ml-auto text-xs px-2 py-1 rounded ${
                                                            esperandoUbicacion ? 'bg-orange-500 text-white animate-bounce' : 'bg-blue-500 text-white'
                                                        }`}>
                                                            <i className="fas fa-thumbtack mr-1"></i>
                                                            {esperandoUbicacion ? 'PASO 2/2' : 'SIEMPRE'}
                                                        </span>
                                                    </div>
                                                    <input
                                                        ref={inputGenericoRef}
                                                        type="text"
                                                        placeholder={esperandoUbicacion 
                                                            ? "Escanea ubicación" 
                                                            : "Código de barras o proveedor"
                                                        }
                                                        onKeyPress={handleBuscarProducto}
                                                        className={`w-full px-3 py-2 font-mono rounded border focus:ring-1 transition
                                                            ${esperandoUbicacion ? 'border-orange-400 focus:ring-orange-400 text-orange-900' : 'border-blue-400 focus:ring-blue-400'}
                                                        `}
                                                        autoFocus
                                                    />
                                                    {busquedaMensaje && (
                                                        <div className={`
                                                            mt-2 px-2 py-1 rounded flex items-center gap-2 text-xs
                                                            ${
                                                                busquedaMensaje.startsWith('✓') || busquedaMensaje.startsWith('✅')
                                                                    ? 'bg-emerald-100 text-emerald-800' 
                                                                    : busquedaMensaje.startsWith('⚠️')
                                                                    ? 'bg-amber-100 text-amber-800'
                                                                    : busquedaMensaje.startsWith('🔍')
                                                                    ? 'bg-blue-100 text-blue-800'
                                                                    : busquedaMensaje.startsWith('❌')
                                                                    ? 'bg-red-100 text-red-700'
                                                                    : 'bg-red-100 text-red-800'
                                                            }
                                                        `}>
                                                            <i className={`fas ${
                                                                busquedaMensaje.startsWith('✓') || busquedaMensaje.startsWith('✅')
                                                                    ? 'fa-check-circle' 
                                                                    : busquedaMensaje.startsWith('⚠️')
                                                                    ? 'fa-exclamation-triangle'
                                                                    : busquedaMensaje.startsWith('🔍')
                                                                    ? 'fa-spinner fa-spin'
                                                                    : busquedaMensaje.startsWith('❌')
                                                                    ? 'fa-times-circle'
                                                                    : 'fa-exclamation-circle'
                                                            }`}></i>
                                                            <span>{busquedaMensaje}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="p-6 space-y-6">

                                    {/* Alerta si NO está en estado REVISADO */}
                                    {pedidosCentral[indexPedidoCentral].estado !== 4 && (
                                        <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-lg">
                                            <div className="flex items-center gap-3">
                                                <i className={`fas ${pedidosCentral[indexPedidoCentral].estado === 1 ? 'fa-tasks' : 'fa-lock'} text-amber-600 text-2xl`}></i>
                                                <div>
                                                    <p className="font-bold text-amber-800">
                                                        {pedidosCentral[indexPedidoCentral].estado === 1 
                                                            ? 'Modo Revisión de Pedido' 
                                                            : 'Sistema de Pistoleo Deshabilitado'}
                                                    </p>
                                                    <p className="text-sm text-amber-700">
                                                        {pedidosCentral[indexPedidoCentral].estado === 1 
                                                            ? 'Selecciona los productos para aprobarlos. El sistema de pistoleo estará disponible cuando el pedido esté en estado REVISADO.' 
                                                            : `El sistema de pistoleo solo está disponible para pedidos en estado REVISADO. Estado actual: ${
                                                                pedidosCentral[indexPedidoCentral].estado == 3 ? 'EN REVISIÓN' : 'DESCONOCIDO'
                                                            }`}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Botón para seleccionar/deseleccionar todos - Solo en estado PENDIENTE */}
                                    {pedidosCentral[indexPedidoCentral].estado === 1 && (
                                        <div className="bg-white rounded-lg p-4 shadow space-y-3">
                                            <button
                                                onClick={(e) => {
                                                    e.currentTarget.setAttribute('data-index', 'all');
                                                    e.currentTarget.setAttribute('data-tipo', 'selectall');
                                                    selectPedidosCentral(e);
                                                }}
                                                data-index="all"
                                                data-tipo="selectall"
                                                className="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow hover:shadow-lg transition transform hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2"
                                            >
                                                <i className="fas fa-check-double"></i>
                                                {pedidosCentral[indexPedidoCentral].items.every(item => item.aprobado === true) 
                                                    ? 'Deseleccionar Todos' 
                                                    : 'Seleccionar Todos'}
                                            </button>

                                            {/* Botón de guardar pedido - Solo si todos están aprobados */}
                                            {pedidosCentral[indexPedidoCentral].items.length > 0 && 
                                             pedidosCentral[indexPedidoCentral].items.every(item => item.aprobado === true) && (
                                                <div className="space-y-2">
                                                    <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                                                        <div className="flex items-center gap-2 text-emerald-700">
                                                            <i className="fas fa-check-circle text-xl"></i>
                                                            <span className="font-semibold">Todos los productos han sido revisados</span>
                                                        </div>
                                                    </div>
                                                    <button
                                                        onClick={checkPedidosCentral}
                                                        className="w-full px-4 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold text-lg rounded-lg shadow-lg hover:shadow-xl transition transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2"
                                                    >
                                                        <i className="fas fa-save text-xl"></i>
                                                        Guardar Pedido Revisado
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    

                                    {/* Lista de productos mejorada */}
                                    <div className="bg-white rounded-lg p-6 shadow">
                                        <h3 className="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                                            <i className="fas fa-clipboard-list text-blue-500"></i>
                                            Lista de Productos
                                            {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                                <span className="text-sm font-normal text-gray-500 ml-2">
                                                    (Escanea la ubicación directamente en cada producto)
                                                </span>
                                            )}
                                        </h3>
                                        
                                        <div className="space-y-3 max-h-[600px] overflow-y-auto pr-2">
                                            {pedidosCentral[indexPedidoCentral].items.map((e, i) => (
                                                <div 
                                                    key={e.id}
                                                    data-producto-card={i}
                                                    className={`rounded-lg border p-4 transition-all ${
                                                        pedidosCentral[indexPedidoCentral].estado === 4 
                                                            ? (e.warehouse_codigo 
                                                                ? 'border-emerald-300 bg-emerald-50 shadow' 
                                                                : productoSeleccionado === i
                                                                ? 'border-blue-500 bg-gradient-to-r from-blue-100 to-blue-50 shadow-2xl ring-4 ring-blue-300 scale-[1.02] transform'
                                                                : 'border-amber-300 bg-amber-50 hover:border-amber-400 hover:shadow-md')
                                                            : (pedidosCentral[indexPedidoCentral].estado === 1
                                                                ? (e.aprobado === true
                                                                    ? 'border-emerald-300 bg-emerald-50 shadow'
                                                                    : e.aprobado === false
                                                                    ? 'border-red-300 bg-red-50'
                                                                    : 'border-gray-300 bg-gray-50')
                                                                : 'border-gray-300 bg-gray-50')
                                                    }`}
                                                >
                                                    {/* Banner de producto seleccionado */}
                                                    {pedidosCentral[indexPedidoCentral].estado === 4 && productoSeleccionado === i && !e.warehouse_codigo && (
                                                        <div className="mb-3 -mt-2 -mx-2 px-4 py-2 bg-blue-600 text-white rounded-t-lg flex items-center gap-2 animate-pulse">
                                                            <i className="fas fa-arrow-down text-xl"></i>
                                                            <span className="font-bold text-sm uppercase">Producto Seleccionado - Escanea la ubicación abajo</span>
                                                            <i className="fas fa-arrow-down text-xl"></i>
                                                        </div>
                                                    )}

                                                    <div className="flex items-start gap-4">
                                                        {/* Checkbox para estado PENDIENTE */}
                                                        {pedidosCentral[indexPedidoCentral].estado === 1 && (
                                                            <div className="flex-shrink-0 pt-2">
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
                                                                    className="w-6 h-6 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer"
                                                                />
                                                            </div>
                                                        )}

                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-center justify-between gap-3 mb-3">
                                                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                                                    <span className={`flex-shrink-0 px-3 py-1 font-bold text-sm rounded-lg ${
                                                                        pedidosCentral[indexPedidoCentral].estado === 4
                                                                            ? (e.warehouse_codigo 
                                                                                ? 'bg-emerald-500 text-white' 
                                                                                : 'bg-amber-500 text-white')
                                                                            : (pedidosCentral[indexPedidoCentral].estado === 1
                                                                                ? (e.aprobado === true
                                                                                    ? 'bg-emerald-500 text-white'
                                                                                    : e.aprobado === false
                                                                                    ? 'bg-red-500 text-white'
                                                                                    : 'bg-gray-500 text-white')
                                                                                : 'bg-gray-500 text-white')
                                                                    }`}>
                                                                        #{i + 1}
                                                                    </span>
                                                                    <h4 className="font-bold text-gray-700 text-base flex-1 min-w-0">
                                                                        {e.producto.descripcion}
                                                                    </h4>
                                                                </div>
                                                                
                                                                {/* Botón de imprimir ticket - pequeño al lado */}
                                                                <button
                                                                    onClick={() => {
                                                                        // Crear formulario para enviar datos por POST
                                                                        const form = document.createElement('form');
                                                                        form.method = 'POST';
                                                                        form.action = '/warehouse-inventory/imprimir-ticket-producto';
                                                                        form.target = '_blank';
                                                                        
                                                                        // Agregar token CSRF
                                                                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                                                                        const csrfInput = document.createElement('input');
                                                                        csrfInput.type = 'hidden';
                                                                        csrfInput.name = '_token';
                                                                        csrfInput.value = csrfToken;
                                                                        form.appendChild(csrfInput);
                                                                        
                                                                        // Agregar datos del producto
                                                                        const campos = {
                                                                            'codigo_barras': e.producto.codigo_barras || '',
                                                                            'codigo_proveedor': e.producto.codigo_proveedor || '',
                                                                            'descripcion': e.producto.descripcion || '',
                                                                            'marca': e.producto.marca || ''
                                                                        };
                                                                        
                                                                        Object.keys(campos).forEach(key => {
                                                                            const input = document.createElement('input');
                                                                            input.type = 'hidden';
                                                                            input.name = key;
                                                                            input.value = campos[key];
                                                                            form.appendChild(input);
                                                                        });
                                                                        
                                                                        // Enviar formulario
                                                                        document.body.appendChild(form);
                                                                        form.submit();
                                                                        document.body.removeChild(form);
                                                                    }}
                                                                    className="flex-shrink-0 px-2 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white text-xs font-semibold rounded shadow hover:shadow-md transition transform hover:scale-105 active:scale-95 flex items-center justify-center gap-1"
                                                                    title="Imprimir Ticket"
                                                                >
                                                                    <i className="fas fa-print text-sm"></i>
                                                                    <span className="hidden sm:inline">Ticket</span>
                                                                </button>
                                                            </div>
                                                            
                                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600 mb-3">
                                                                <p className="flex items-center gap-2">
                                                                    <i className="fas fa-barcode text-blue-500"></i>
                                                                    <span className="font-semibold">Código Barras:</span>
                                                                    <span className="font-mono text-xs">{e.producto.codigo_barras || 'N/A'}</span>
                                                                </p>
                                                                <p className="flex items-center gap-2">
                                                                    <i className="fas fa-tag text-purple-500"></i>
                                                                    <span className="font-semibold">Código Proveedor:</span>
                                                                    <span className="font-mono text-xs">{e.producto.codigo_proveedor || 'N/A'}</span>
                                                                </p>
                                                                <p className="flex items-center gap-2">
                                                                    <i className="fas fa-cubes text-blue-500"></i>
                                                                    <span className="font-semibold">Cantidad:</span>
                                                                    <span className="font-bold text-blue-600">{e.cantidad}</span>
                                                                </p>
                                                            </div>
                                                            
                                                            {/* Mostrar ubicación si está en estado REVISADO y tiene warehouse */}
                                                            {pedidosCentral[indexPedidoCentral].estado === 4 && e.warehouse_codigo && (
                                                                <div className="mt-3 p-3 bg-emerald-100 rounded-lg border border-emerald-300">
                                                                    <p className="text-emerald-700 font-bold flex items-center gap-2">
                                                                        <i className="fas fa-map-marker-alt"></i>
                                                                        UBICACIÓN: <span className="font-mono text-lg">{e.warehouse_codigo}</span>
                                                                    </p>
                                                                </div>
                                                            )}
                                                        </div>
                                                        
                                                        <div className="flex-shrink-0 text-center">
                                                            {pedidosCentral[indexPedidoCentral].estado === 4 ? (
                                                                e.warehouse_codigo ? (
                                                                    <div>
                                                                        <i className="fas fa-check-circle text-4xl text-emerald-500 mb-2"></i>
                                                                        <div className="px-2 py-1 bg-emerald-500 text-white font-bold text-xs rounded-full">
                                                                            COMPLETADO
                                                                        </div>
                                                                    </div>
                                                                ) : (
                                                                    <div>
                                                                        <i className="fas fa-barcode text-4xl text-amber-500 mb-2 animate-pulse"></i>
                                                                        <div className="px-2 py-1 bg-amber-500 text-white font-bold text-xs rounded-full">
                                                                            PENDIENTE
                                                                        </div>
                                                                    </div>
                                                                )
                                                            ) : pedidosCentral[indexPedidoCentral].estado === 1 ? (
                                                                e.aprobado === true ? (
                                                                    <div>
                                                                        <i className="fas fa-check-circle text-4xl text-emerald-500 mb-2"></i>
                                                                        <div className="px-2 py-1 bg-emerald-500 text-white font-bold text-xs rounded-full">
                                                                            APROBADO
                                                                        </div>
                                                                    </div>
                                                                ) : e.aprobado === false ? (
                                                                    <div>
                                                                        <i className="fas fa-times-circle text-4xl text-red-500 mb-2"></i>
                                                                        <div className="px-2 py-1 bg-red-500 text-white font-bold text-xs rounded-full">
                                                                            RECHAZADO
                                                                        </div>
                                                                    </div>
                                                                ) : (
                                                                    <div>
                                                                        <i className="fas fa-circle text-4xl text-gray-400 mb-2"></i>
                                                                        <div className="px-2 py-1 bg-gray-500 text-white font-bold text-xs rounded-full">
                                                                            SIN REVISAR
                                                                        </div>
                                                                    </div>
                                                                )
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* NOTA: El botón de guardar ahora está en el resumen de progreso cuando todos tienen ubicación */}


                                    {/* Resumen de progreso mejorado - Solo si está REVISADO */}
                                    {pedidosCentral[indexPedidoCentral].estado === 4 && (
                                        <div className="bg-white rounded-lg p-6 shadow space-y-4">
                                            <h3 className="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                                                <i className="fas fa-chart-pie text-blue-500"></i>
                                                Progreso de Recepción
                                            </h3>
                                            
                                            <div className="grid grid-cols-3 gap-4 mb-6">
                                                <div className="bg-emerald-50 rounded-lg p-4 border border-emerald-200">
                                                    <div className="text-4xl font-bold text-emerald-600 mb-2">
                                                        {pedidosCentral[indexPedidoCentral].items.filter(e => e.warehouse_codigo).length}
                                                    </div>
                                                    <div className="text-xs font-semibold text-emerald-700 uppercase flex items-center gap-1">
                                                        <i className="fas fa-check-circle"></i>
                                                        Con Ubicación
                                                    </div>
                                                </div>
                                                
                                                <div className="bg-amber-50 rounded-lg p-4 border border-amber-200">
                                                    <div className="text-4xl font-bold text-amber-600 mb-2">
                                                        {pedidosCentral[indexPedidoCentral].items.filter(e => !e.warehouse_codigo).length}
                                                    </div>
                                                    <div className="text-xs font-semibold text-amber-700 uppercase flex items-center gap-1">
                                                        <i className="fas fa-clock"></i>
                                                        Pendientes
                                                    </div>
                                                </div>
                                                
                                                <div className="bg-blue-50 rounded-lg p-4 border border-blue-200">
                                                    <div className="text-4xl font-bold text-blue-600 mb-2">
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
                                                    style={{width: `${(pedidosCentral[indexPedidoCentral].items.filter(e => e.warehouse_codigo).length / pedidosCentral[indexPedidoCentral].items.length) * 100}%`}}
                                                >
                                                    <span className="text-white font-bold text-sm">
                                                        {Math.round((pedidosCentral[indexPedidoCentral].items.filter(e => e.warehouse_codigo).length / pedidosCentral[indexPedidoCentral].items.length) * 100)}%
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Botón de guardar cuando todos tienen ubicación */}
                                            {pedidosCentral[indexPedidoCentral].items.length > 0 && 
                                             pedidosCentral[indexPedidoCentral].items.every(item => item.warehouse_codigo) && (
                                                <div className="space-y-2 pt-4 border-t border-gray-200">
                                                    <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                                                        <div className="flex items-center gap-2 text-emerald-700">
                                                            <i className="fas fa-check-circle text-xl"></i>
                                                            <span className="font-semibold">Todos los productos tienen ubicación asignada</span>
                                                        </div>
                                                        <p className="text-sm text-emerald-600 mt-1 ml-7">
                                                            Los productos serán registrados en sus ubicaciones correspondientes
                                                        </p>
                                                    </div>
                                                    <button
                                                        onClick={checkPedidosCentral}
                                                        className="w-full px-4 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold text-lg rounded-lg shadow-lg hover:shadow-xl transition transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2"
                                                    >
                                                        <i className="fas fa-save text-xl"></i>
                                                        Guardar Recepción con Ubicaciones
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow p-12 text-center">
                            <div className="max-w-md mx-auto">
                                <i className="fas fa-hand-pointer text-gray-300 text-8xl mb-6"></i>
                                <h3 className="text-2xl font-bold text-gray-700 mb-3">Seleccione una transferencia</h3>
                                <p className="text-gray-500">Haz clic en una transferencia de la lista de la izquierda para comenzar la recepción con el sistema de pistoleo.</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
