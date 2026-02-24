import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

const CarritoDinamico = ({ 
    onCarritoChange, 
    onValidacionChange, 
    factura = null, 
    sucursalConfig = null,
    initialData = null,
    db = null,
    metodosPagoConfigurados = [],
    tasasCambio = { bs_to_usd: 37, cop_to_usd: 1 },
    modoValidacion = true, // NOTA: Solo afecta búsqueda - la validación de entradas SIEMPRE está activa
    productosFacturados = [], // Lista de productos de la factura original - REQUERIDOS para entradas
    modoTrasladoInterno = false // Nuevo prop para modo traslado interno
}) => {
    const [entradas, setEntradas] = useState([]);
    const [salidas, setSalidas] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [tipoBusqueda, setTipoBusqueda] = useState('descripcion');
    const [productosEncontrados, setProductosEncontrados] = useState([]);
    const [cargandoBusqueda, setCargandoBusqueda] = useState(false);
    const [procesandoCarrito, setProcesandoCarrito] = useState(false);
    const [resumen, setResumen] = useState({
        caso_uso: null,
        descripcion_caso: '',
        diferencia_pago: 0,
        balance_tipo: 'equilibrio',
        total_entradas: 0,
        total_salidas: 0,
        productos_entrada: 0,
        productos_salida: 0,
        errores: []
    });

    // Cargar datos iniciales si se proporcionan
    useEffect(() => {
        if (initialData) {
            if (initialData.entradas) setEntradas(initialData.entradas);
            if (initialData.salidas) setSalidas(initialData.salidas);
        }
    }, [initialData]);

    // Procesar carrito cuando cambie
    useEffect(() => {
        if (entradas.length > 0 || salidas.length > 0) {
            procesarCarrito();
        } else {
            resetearResumen();
        }
    }, [entradas, salidas]);

    // Notificar cambios al componente padre - EVITAR BUCLE con useCallback
    const notificarCambios = useCallback(() => {
        const carritoData = {
            entradas,
            salidas,
            resumen,
            total_productos: entradas.length + salidas.length
        };
        
        // Validación simplificada - sin restricciones de métodos de pago
        const tieneProductos = entradas.length > 0 || salidas.length > 0;
        
        // Validación mejorada
        const erroresValidacion = [...resumen.errores];
        
        if (onCarritoChange) {
            onCarritoChange(carritoData);
        }
        
        if (onValidacionChange) {
            onValidacionChange({
                esValido: erroresValidacion.length === 0 && tieneProductos,
                errores: erroresValidacion,
                requiereMetodosPago: false,
                diferencia_pago: resumen.diferencia_pago
            });
        }
    }, [entradas, salidas, resumen.caso_uso, resumen.diferencia_pago, resumen.errores, resumen]);

    // Ejecutar notificación cuando cambien las dependencias clave
    useEffect(() => {
        if (entradas.length > 0 || salidas.length > 0) {
            notificarCambios();
        }
    }, [notificarCambios]);

    const resetearResumen = () => {
        setResumen({
            caso_uso: null,
            descripcion_caso: '',
            diferencia_pago: 0,
            balance_tipo: 'equilibrio',
            total_entradas: 0,
            total_salidas: 0,
            productos_entrada: 0,
            productos_salida: 0,
            errores: []
        });
    };

    const buscarProductos = async (termino, tipo = 'descripcion') => {
        if (!termino || termino.length < 2) {
            setProductosEncontrados([]);
            return;
        }

        if (!db || !db.searchProductosInventario) {
            console.error('Base de datos no disponible o función searchProductosInventario no encontrada');
            setProductosEncontrados([]);
            return;
        }

        setCargandoBusqueda(true);
        try {
            // Usar la misma lógica que ProductoStep
            const searchParams = {
                term: termino,
                field: tipo,
                sucursal_id: sucursalConfig?.id || null
            };

            const response = await db.searchProductosInventario(searchParams);
            
            // Axios devuelve la respuesta en response.data
            const apiResponse = response.data;
            
            if (apiResponse.status === 200 && apiResponse.data) {
                // Mostrar todos los productos encontrados sin filtrar
                setProductosEncontrados(apiResponse.data);
            } else {
                setProductosEncontrados([]);
            }
        } catch (error) {
            console.error('Error al buscar productos:', error);
            setProductosEncontrados([]);
        } finally {
            setCargandoBusqueda(false);
        }
    };

    const handleSearchChange = (e) => {
        const value = e.target.value;
        setBusqueda(value);
        buscarProductos(value, tipoBusqueda);
    };

    const handleSearchTypeChange = (e) => {
        const tipo = e.target.value;
        setTipoBusqueda(tipo);
        if (busqueda.length >= 2) {
            buscarProductos(busqueda, tipo);
        }
    };

    const procesarCarrito = async () => {
        if (entradas.length === 0 && salidas.length === 0) return;

        setProcesandoCarrito(true);
        try {
            // Calcular totales
            const total_entradas = entradas.reduce((sum, item) => sum + (parseFloat(item.precio) * parseInt(item.cantidad)), 0);
            const total_salidas = salidas.reduce((sum, item) => sum + (parseFloat(item.precio) * parseInt(item.cantidad)), 0);
            
            // Calcular diferencia de pago (entradas - salidas)
            const diferencia_pago = total_entradas - total_salidas;
            
            // Determinar tipo de balance
            let balance_tipo = 'equilibrio';
            if (diferencia_pago > 0.01) {
                balance_tipo = 'favor_cliente'; // Entra más de lo que sale - cliente debe recibir dinero
            } else if (diferencia_pago < -0.01) {
                balance_tipo = 'favor_empresa'; // Sale más de lo que entra - empresa recibe más valor
            }
            
            // Detectar caso de uso automáticamente según lógica de negocio corregida
            let caso_uso = 1;
            let descripcion_caso = 'Garantía Simple';
            
            const tieneProductosDanados = entradas.some(item => item.estado === 'DAÑADO');
            const tieneProductosBuenos = entradas.some(item => item.estado === 'BUENO');
            const tieneSalidasProductos = salidas.length > 0;
            const tieneDiferenciaPago = Math.abs(diferencia_pago) > 0.01;
            
            // Lógica corregida de detección de casos
            if (tieneProductosDanados && tieneProductosBuenos) {
                // Caso mixto - productos dañados Y buenos de entrada
                caso_uso = 5;
                descripcion_caso = 'Caso Mixto - Garantías y devoluciones combinadas';
            } else if (tieneProductosDanados && !tieneProductosBuenos) {
                // Solo productos dañados de entrada = GARANTÍA
                if (tieneSalidasProductos && !tieneDiferenciaPago) {
                    // Entra producto dañado, sale producto, sin diferencia = Garantía Simple
                    caso_uso = 1;
                    descripcion_caso = 'Garantía Simple - Intercambio directo';
                } else if (tieneSalidasProductos && tieneDiferenciaPago) {
                    // Entra producto dañado, sale producto, CON diferencia = Garantía con diferencia
                    caso_uso = 2;
                    descripcion_caso = 'Garantía con Diferencia de Pago';
                } else if (!tieneSalidasProductos && tieneDiferenciaPago) {
                    // Entra producto dañado, NO sale producto, hay diferencia = Devolución de dinero por garantía
                    caso_uso = 4;
                    descripcion_caso = 'Garantía - Devolución de Dinero';
                } else {
                    // Caso por defecto para garantía
                    caso_uso = 1;
                    descripcion_caso = 'Garantía - Caso no definido';
                }
            } else if (tieneProductosBuenos && !tieneProductosDanados) {
                // Solo productos buenos de entrada = DEVOLUCIÓN
                if (tieneSalidasProductos && !tieneDiferenciaPago) {
                    // Entra producto bueno, sale producto, sin diferencia = Devolución por producto
                    caso_uso = 3;
                    descripcion_caso = 'Devolución - Producto por Producto';
                } else if (tieneSalidasProductos && tieneDiferenciaPago) {
                    // Entra producto bueno, sale producto, CON diferencia = Devolución con diferencia
                    caso_uso = 3;
                    descripcion_caso = 'Devolución - Con Diferencia de Pago';
                } else if (!tieneSalidasProductos && tieneDiferenciaPago) {
                    // Entra producto bueno, NO sale producto, hay diferencia = Devolución de dinero
                    caso_uso = 4;
                    descripcion_caso = 'Devolución - Reembolso de Dinero';
                } else {
                    // Caso por defecto para devolución
                    caso_uso = 3;
                    descripcion_caso = 'Devolución - Caso no definido';
                }
            } else {
                // No hay entradas - caso por defecto
                caso_uso = 1;
                descripcion_caso = 'Sin productos de entrada';
            }
            
            // Validaciones adicionales
            const errores = [];
            
            // Validación especial para traslados internos
            if (modoTrasladoInterno) {
                // En traslados internos, el producto que entra debe ser el mismo que sale
                if (entradas.length > 0 && salidas.length > 0) {
                    const productosEntrada = entradas.map(item => item.id).sort();
                    const productosSalida = salidas.map(item => item.id).sort();
                    
                    // Verificar que sean los mismos productos
                    if (JSON.stringify(productosEntrada) !== JSON.stringify(productosSalida)) {
                        errores.push('En traslados internos, el producto que entra debe ser el mismo que sale');
                    }
                    
                    // Verificar que las cantidades sean iguales
                    const totalEntrada = entradas.reduce((sum, item) => sum + item.cantidad, 0);
                    const totalSalida = salidas.reduce((sum, item) => sum + item.cantidad, 0);
                    
                    if (totalEntrada !== totalSalida) {
                        errores.push('En traslados internos, la cantidad que entra debe ser igual a la que sale');
                    }
                }
            }
            
            // Validar que haya al menos un producto de entrada
            if (entradas.length === 0) {
                errores.push('Debe agregar al menos un producto de entrada (garantía o devolución)');
            }
            
            // Validar casos específicos según la nueva lógica
            if (caso_uso === 1) {
                // Garantía Simple - debe tener producto dañado de entrada y producto de salida sin diferencia
                if (!tieneProductosDanados) {
                    errores.push('Garantía Simple requiere productos dañados de entrada');
                }
                if (!tieneSalidasProductos) {
                    errores.push('Garantía Simple requiere productos de salida (intercambio)');
                }
                if (tieneDiferenciaPago) {
                    errores.push('Garantía Simple no debe tener diferencia de pago significativa');
                }
            }
            
            if (caso_uso === 2) {
                // Garantía con diferencia - debe tener producto dañado de entrada, producto de salida Y diferencia
                if (!tieneProductosDanados) {
                    errores.push('Garantía con Diferencia requiere productos dañados de entrada');
                }
                if (!tieneSalidasProductos) {
                    errores.push('Garantía con Diferencia requiere productos de salida');
                }
                if (!tieneDiferenciaPago) {
                    errores.push('Garantía con Diferencia requiere diferencia de pago');
                }
            }
            
            if (caso_uso === 3) {
                // Devolución - debe tener productos buenos de entrada
                if (!tieneProductosBuenos) {
                    errores.push('Devolución requiere productos en buen estado de entrada');
                }
                if (tieneProductosDanados) {
                    errores.push('Devolución no debe incluir productos dañados (use caso mixto)');
                }
            }
            
            if (caso_uso === 4) {
                // Devolución de dinero - debe tener entrada y diferencia, sin salida de productos
                if (entradas.length === 0) {
                    errores.push('Devolución de dinero requiere productos de entrada');
                }
                if (tieneSalidasProductos) {
                    errores.push('Devolución de dinero no debe incluir productos de salida');
                }
                if (!tieneDiferenciaPago) {
                    errores.push('Devolución de dinero requiere diferencia de pago');
                }
            }
            
            if (caso_uso === 5) {
                // Caso mixto - debe tener productos dañados Y buenos
                if (!tieneProductosDanados || !tieneProductosBuenos) {
                    errores.push('Caso Mixto requiere productos dañados Y productos buenos de entrada');
                }
            }

            const nuevoResumen = {
                caso_uso,
                descripcion_caso,
                diferencia_pago,
                balance_tipo,
                total_entradas,
                total_salidas,
                productos_entrada: entradas.length,
                productos_salida: salidas.length,
                errores: errores // Usar errores validados
            };

            setResumen(nuevoResumen);
        } catch (error) {
            console.error('Error procesando carrito:', error);
            setResumen(prev => ({
                ...prev,
                errores: ['Error procesando carrito: ' + error.message]
            }));
        } finally {
            setProcesandoCarrito(false);
        }
    };

    const agregarProducto = (producto, tipo, estado = 'BUENO') => {
        // Validar productos de entrada - SIEMPRE deben estar en la factura original
        if (tipo === 'entrada') {
            // En modo traslado interno, no se requiere validación de factura
            if (!modoTrasladoInterno) {
            // Si no hay productos facturados, no se puede agregar nada como entrada
            if (!productosFacturados || productosFacturados.length === 0) {
                alert(`⚠️ No se pueden procesar entradas\n\nNo hay información de productos facturados disponible.\n\nPor favor valide la factura primero.`);
                return;
            }

            const productoFacturado = productosFacturados.find(p => p.id === producto.id);
            
            // El producto DEBE estar en la factura original (validación estricta)
            if (!productoFacturado) {
                alert(`⚠️ Producto no válido para garantía/devolución\n\nEl producto "${producto.descripcion}" no se encuentra en la factura original.\n\n✅ Solo se pueden procesar productos que fueron facturados originalmente.`);
                return;
            }
            
            // Validar cantidad máxima disponible de la factura
            const cantidadFacturada = productoFacturado.cantidad;
            const cantidadYaAgregada = entradas.filter(item => item.id === producto.id).reduce((sum, item) => sum + item.cantidad, 0);
            
            if (cantidadYaAgregada >= cantidadFacturada) {
                alert(`⚠️ Cantidad máxima alcanzada\n\nYa se ha agregado la cantidad máxima disponible de este producto.\n\nCantidad facturada: ${cantidadFacturada}\nCantidad ya agregada: ${cantidadYaAgregada}`);
                    return;
                }
            }
        }

        // Validación especial para traslados internos - salidas
        if (tipo === 'salida' && modoTrasladoInterno) {
            // Verificar que el producto ya esté en entradas como dañado
            const productoEnEntradas = entradas.find(e => e.id === producto.id && e.estado === 'DAÑADO');
            if (!productoEnEntradas) {
                alert(`⚠️ Producto no disponible para salida\n\nEn traslados internos, primero debe agregar "${producto.descripcion}" como producto dañado en la entrada antes de poder agregarlo como salida.`);
                return;
            }
        }

        // Entrada: usar precio de la factura original. Salida: usar precio actual del producto.
        const productoFacturadoForPrecio = !modoTrasladoInterno ? productosFacturados.find(p => p.id === producto.id) : null;
        const precioActualProducto = parseFloat(producto.precio) || 0;
        const precioFactura = productoFacturadoForPrecio ? (parseFloat(productoFacturadoForPrecio.precio_unitario) || 0) : precioActualProducto;
        const precioParaItem = tipo === 'entrada' && productoFacturadoForPrecio ? precioFactura : precioActualProducto;

        const nuevoItem = {
            id: producto.id,
            descripcion: producto.descripcion,
            codigo_barras: producto.codigo_barras || '',
            codigo_proveedor: producto.codigo_proveedor || '',
            precio: precioParaItem,
            precio_original: precioParaItem,
            precio_factura: tipo === 'entrada' ? precioFactura : null,
            precio_actual: tipo === 'entrada' ? precioActualProducto : precioActualProducto,
            stock_disponible: parseInt(producto.stock) || 0,
            cantidad: 1,
            estado: estado, // 'BUENO', 'DAÑADO', 'DEFECTUOSO'
            subtotal: precioParaItem,
            tipo: tipo, // 'entrada' o 'salida'
            categoria: producto.categoria || '',
            proveedor: producto.proveedor || '',
            cantidad_facturada: tipo === 'entrada' ? productosFacturados.find(p => p.id === producto.id)?.cantidad || 0 : null
        };

        if (tipo === 'entrada') {
            setEntradas(prev => [...prev, nuevoItem]);
        } else {
            setSalidas(prev => [...prev, nuevoItem]);
        }

        setBusqueda('');
        setProductosEncontrados([]);
    };

    const actualizarCantidad = (id, tipo, nuevaCantidad) => {
        const cantidad = Math.max(0.01, parseFloat(nuevaCantidad) || 0.01);
        
        // Validar cantidad máxima para productos de entrada - SIEMPRE validar
        if (tipo === 'entrada') {
            const productoFacturado = productosFacturados.find(p => p.id === id);
            if (productoFacturado) {
                const cantidadFacturada = productoFacturado.cantidad;
                const cantidadOtrosItems = entradas.filter(item => item.id === id && item !== entradas.find(e => e.id === id)).reduce((sum, item) => sum + item.cantidad, 0);
                
                if ((cantidad + cantidadOtrosItems) > cantidadFacturada) {
                    alert(`⚠️ Cantidad excede la facturada\n\nNo puede agregar más de ${cantidadFacturada} unidades de este producto.\n\nCantidad facturada: ${cantidadFacturada}`);
                    return;
                }
            }
        }
        
        if (tipo === 'entrada') {
            setEntradas(prev => prev.map(item => 
                item.id === id 
                    ? { ...item, cantidad, subtotal: item.precio * cantidad }
                    : item
            ));
        } else {
            setSalidas(prev => prev.map(item => 
                item.id === id 
                    ? { ...item, cantidad, subtotal: item.precio * cantidad }
                    : item
            ));
        }
    };

    const eliminarProducto = (id, tipo) => {
        if (tipo === 'entrada') {
            setEntradas(prev => prev.filter(item => item.id !== id));
        } else {
            setSalidas(prev => prev.filter(item => item.id !== id));
        }
    };

    const renderProductosEncontrados = () => {
        if (cargandoBusqueda) {
            return (
                <div className="p-4 text-center">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                    <p className="text-sm text-gray-600 mt-2">Buscando productos...</p>
                </div>
            );
        }

        if (productosEncontrados.length === 0) {
            return busqueda.length >= 2 ? (
                <div className="p-4 text-center text-gray-500">
                    <p>No se encontraron productos</p>
                </div>
            ) : null;
        }

        return (
            <div className="border rounded-lg shadow-lg bg-white max-h-80 overflow-y-auto">
                {productosEncontrados.map((producto) => {
                    // Verificar si el producto está en la factura original (solo si no es modo traslado interno)
                    const productoFacturado = modoTrasladoInterno ? null : productosFacturados.find(p => p.id === producto.id);
                    const estaEnFactura = modoTrasladoInterno ? true : !!productoFacturado;
                    const cantidadFacturada = productoFacturado?.cantidad || 0;
                    
                    // En modo traslado interno, verificar si este producto ya está en entradas como dañado
                    const productoYaEnEntradas = modoTrasladoInterno ? entradas.find(e => e.id === producto.id && e.estado === 'DAÑADO') : null;
                    const puedeAgregarComoSalida = modoTrasladoInterno ? !!productoYaEnEntradas : true;
                    
                    return (
                        <div key={producto.id} className={`p-3 border-b hover:bg-gray-50 ${!estaEnFactura ? 'bg-gray-50' : ''}`}>
                            {/* Desktop Layout */}
                            <div className="hidden md:flex justify-between items-start">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="font-medium text-gray-900 text-sm">{producto.descripcion}</p>
                                        {modoTrasladoInterno ? (
                                            <span className="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full whitespace-nowrap">
                                                🔄 Traslado interno
                                            </span>
                                        ) : estaEnFactura ? (
                                            <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full whitespace-nowrap">
                                                ✅ En factura
                                            </span>
                                        ) : (
                                            <span className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full whitespace-nowrap">
                                                ❌ No facturado
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-sm text-gray-600 mt-1">
                                        {producto.codigo_barras && <span>CB: {producto.codigo_barras} | </span>}
                                        {producto.codigo_proveedor && <span>CP: {producto.codigo_proveedor} | </span>}
                                        Categoría: {producto.categoria || 'Sin categoría'}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        {!modoTrasladoInterno && estaEnFactura && productoFacturado ? (
                                            <>
                                                <span className="text-green-700 font-medium">Precio factura: ${(parseFloat(productoFacturado.precio_unitario) || 0).toFixed(2)}</span>
                                                <span className="mx-1">|</span>
                                                <span>Precio actual: ${(parseFloat(producto.precio) || 0).toFixed(2)}</span>
                                                {(parseFloat(productoFacturado.precio_unitario) || 0).toFixed(2) !== (parseFloat(producto.precio) || 0).toFixed(2) && (
                                                    <span className="ml-1 text-amber-600">(diferente)</span>
                                                )}
                                            </>
                                        ) : (
                                            <>Precio: ${(parseFloat(producto.precio) || 0).toFixed(2)}</>
                                        )}
                                        {' | '}Stock: {producto.stock || 'N/A'}
                                        {!modoTrasladoInterno && estaEnFactura && (
                                            <span className="ml-2 text-green-600 font-medium">
                                                | Facturado: {cantidadFacturada} uds
                                            </span>
                                        )}
                                        {modoTrasladoInterno && productoYaEnEntradas && (
                                            <span className="ml-2 text-orange-600 font-medium">
                                                | Ya agregado como dañado
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="flex space-x-2 ml-4 flex-shrink-0">
                                    <button
                                        onClick={() => agregarProducto(producto, 'entrada', 'DAÑADO')}
                                        disabled={!estaEnFactura}
                                        className={`px-3 py-1 rounded text-sm transition-colors whitespace-nowrap ${
                                            estaEnFactura 
                                                ? 'bg-yellow-500 text-white hover:bg-yellow-600' 
                                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        }`}
                                        title={
                                            modoTrasladoInterno 
                                                ? "Agregar como producto dañado (traslado interno)" 
                                                : estaEnFactura 
                                                    ? "Agregar como producto dañado (garantía)" 
                                                    : "Producto no disponible - No está en la factura"
                                        }
                                    >
                                        Producto dañado
                                    </button>
                                  {/*   <button
                                        onClick={() => agregarProducto(producto, 'entrada', 'BUENO')}
                                        disabled={!estaEnFactura}
                                        className={`px-3 py-1 rounded text-sm transition-colors whitespace-nowrap ${
                                            estaEnFactura 
                                                ? 'bg-blue-500 text-white hover:bg-blue-600' 
                                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        }`}
                                        title={
                                            modoTrasladoInterno 
                                                ? "Agregar como producto bueno (traslado interno)" 
                                                : estaEnFactura 
                                                    ? "Agregar como producto bueno (devolución)" 
                                                    : "Producto no disponible - No está en la factura"
                                        }
                                    >
                                        Producto bueno
                                    </button> */}
                                    <button
                                        onClick={() => agregarProducto(producto, 'salida', 'BUENO')}
                                        disabled={modoTrasladoInterno && !puedeAgregarComoSalida}
                                        className={`px-3 py-1 rounded text-sm transition-colors whitespace-nowrap ${
                                            modoTrasladoInterno && !puedeAgregarComoSalida
                                                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                : 'bg-green-500 text-white hover:bg-green-600'
                                        }`}
                                        title={
                                            modoTrasladoInterno && !puedeAgregarComoSalida
                                                ? "Primero debe agregar este producto como dañado en la entrada"
                                                : "Agregar como producto a entregar"
                                        }
                                    >
                                        Producto a entregar
                                    </button>
                                </div>
                            </div>

                            {/* Mobile Layout */}
                            <div className="md:hidden">
                                {/* Header con título y badges */}
                                <div className="flex items-start justify-between mb-2">
                                    <div className="flex-1 min-w-0">
                                        <p className="font-medium text-gray-900 text-sm leading-tight">{producto.descripcion}</p>
                                    </div>
                                    <div className="ml-2 flex-shrink-0">
                                        {modoTrasladoInterno ? (
                                            <span className="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full whitespace-nowrap">
                                                🔄 Traslado
                                            </span>
                                        ) : estaEnFactura ? (
                                            <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full whitespace-nowrap">
                                                ✅ Factura
                                            </span>
                                        ) : (
                                            <span className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full whitespace-nowrap">
                                                ❌ No facturado
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Información del producto */}
                                <div className="space-y-1 mb-3">
                                    <div className="text-xs text-gray-600">
                                        {producto.codigo_barras && <span>CB: {producto.codigo_barras}</span>}
                                        {producto.codigo_proveedor && <span className="ml-2">CP: {producto.codigo_proveedor}</span>}
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        Categoría: {producto.categoria || 'Sin categoría'}
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {!modoTrasladoInterno && estaEnFactura && productoFacturado ? (
                                            <>
                                                <span className="text-green-700 font-medium">Precio factura: ${(parseFloat(productoFacturado.precio_unitario) || 0).toFixed(2)}</span>
                                                <span className="mx-1">|</span>
                                                <span>Precio actual: ${(parseFloat(producto.precio) || 0).toFixed(2)}</span>
                                                {(parseFloat(productoFacturado.precio_unitario) || 0).toFixed(2) !== (parseFloat(producto.precio) || 0).toFixed(2) && (
                                                    <span className="ml-1 text-amber-600">(diferente)</span>
                                                )}
                                            </>
                                        ) : (
                                            <>Precio: ${(parseFloat(producto.precio) || 0).toFixed(2)}</>
                                        )}
                                        {' | '}Stock: {producto.stock || 'N/A'}
                                        {!modoTrasladoInterno && estaEnFactura && (
                                            <span className="ml-2 text-green-600 font-medium">
                                                | Facturado: {cantidadFacturada} uds
                                            </span>
                                        )}
                                        {modoTrasladoInterno && productoYaEnEntradas && (
                                            <span className="ml-2 text-orange-600 font-medium">
                                                | Ya agregado como dañado
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Botones en móvil - Stack vertical */}
                                <div className="space-y-2">
                                    <button
                                        onClick={() => agregarProducto(producto, 'entrada', 'DAÑADO')}
                                        disabled={!estaEnFactura}
                                        className={`w-full px-3 py-2 rounded text-sm transition-colors font-medium ${
                                            estaEnFactura 
                                                ? 'bg-yellow-500 text-white hover:bg-yellow-600 active:bg-yellow-700' 
                                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        }`}
                                        title={
                                            modoTrasladoInterno 
                                                ? "Agregar como producto dañado (traslado interno)" 
                                                : estaEnFactura 
                                                    ? "Agregar como producto dañado (garantía)" 
                                                    : "Producto no disponible - No está en la factura"
                                        }
                                    >
                                        🟡 Producto dañado
                                    </button>
                                    <button
                                        onClick={() => agregarProducto(producto, 'entrada', 'BUENO')}
                                        disabled={!estaEnFactura}
                                        className={`w-full px-3 py-2 rounded text-sm transition-colors font-medium ${
                                            estaEnFactura 
                                                ? 'bg-blue-500 text-white hover:bg-blue-600 active:bg-blue-700' 
                                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        }`}
                                        title={
                                            modoTrasladoInterno 
                                                ? "Agregar como producto bueno (traslado interno)" 
                                                : estaEnFactura 
                                                    ? "Agregar como producto bueno (devolución)" 
                                                    : "Producto no disponible - No está en la factura"
                                        }
                                    >
                                        🔵 Producto bueno
                                    </button>
                                    <button
                                        onClick={() => agregarProducto(producto, 'salida', 'BUENO')}
                                        disabled={modoTrasladoInterno && !puedeAgregarComoSalida}
                                        className={`w-full px-3 py-2 rounded text-sm transition-colors font-medium ${
                                            modoTrasladoInterno && !puedeAgregarComoSalida
                                                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                : 'bg-green-500 text-white hover:bg-green-600 active:bg-green-700'
                                        }`}
                                        title={
                                            modoTrasladoInterno && !puedeAgregarComoSalida
                                                ? "Primero debe agregar este producto como dañado en la entrada"
                                                : "Agregar como producto a entregar"
                                        }
                                    >
                                        🟢 Producto a entregar
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    };

    const renderSeccionEntradas = () => (
        <div className="bg-yellow-50 rounded-lg p-4">
            <h3 className="font-semibold text-yellow-800 mb-4">
                📥 ENTRADAS - Lo que recibe la empresa
            </h3>
            {entradas.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                    <p>No hay productos en ENTRADAS</p>
                    <p className="text-sm">Use los botones "Producto dañado" o "Producto bueno" para agregar productos</p>
                </div>
            ) : (
                <>
                    {/* Desktop Table */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full border-collapse bg-white rounded-lg overflow-hidden shadow-sm">
                            <thead className="bg-yellow-100">
                                <tr>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Producto
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Códigos
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Estado
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Cantidad
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Precio Unit.
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Subtotal
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entradas.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-50">
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div>
                                                <p className="font-medium text-gray-900">{item.descripcion}</p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Stock: {item.stock_disponible || 'N/A'}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div className="text-sm text-gray-600">
                                                {item.codigo_barras && (
                                                    <div>CB: {item.codigo_barras}</div>
                                                )}
                                                {item.codigo_proveedor && (
                                                    <div>CP: {item.codigo_proveedor}</div>
                                                )}
                                                {!item.codigo_barras && !item.codigo_proveedor && (
                                                    <div className="text-gray-400">Sin códigos</div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div className="text-sm">
                                                <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${
                                                    item.estado === 'DAÑADO' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                                                }`}>
                                                    {item.estado}
                                                </span>
                                                <div className="text-xs text-gray-500 mt-1">
                                                    {item.estado === 'DAÑADO' ? 'Garantía' : 'Devolución'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center">
                                            <input
                                                type="number"
                                                value={item.cantidad}
                                                onClick={(e) => {
                                                    let cantidad = window.prompt('Ingrese la cantidad');
                                                    if (cantidad !== null && cantidad !== '' && parseFloat(cantidad)) {
                                                        actualizarCantidad(item.id, 'entrada', parseFloat(cantidad));
                                                    }
                                                }}
                                                onChange={(e) => actualizarCantidad(item.id, 'entrada', e.target.value)}
                                                className="w-16 px-2 py-1 border rounded text-center text-sm"
                                                min="0.01"
                                                step="0.01"
                                            />
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center text-sm font-medium">
                                            <div>${(parseFloat(item.precio) || 0).toFixed(2)}</div>
                                            {item.precio_factura != null && item.precio_actual != null && Math.abs(parseFloat(item.precio_factura) - parseFloat(item.precio_actual)) > 0.001 && (
                                                <div className="text-xs text-gray-500 mt-0.5">Factura: ${(parseFloat(item.precio_factura) || 0).toFixed(2)} | Actual: ${(parseFloat(item.precio_actual) || 0).toFixed(2)}</div>
                                            )}
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold">
                                            ${(parseFloat(item.subtotal) || 0).toFixed(2)}
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center">
                                            <button
                                                onClick={() => eliminarProducto(item.id, 'entrada')}
                                                className="text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded"
                                                title="Eliminar producto"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Cards */}
                    <div className="md:hidden space-y-3">
                        {entradas.map((item) => (
                            <div key={item.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                                {/* Header con título y estado */}
                                <div className="flex justify-between items-start mb-3">
                                    <div className="flex-1 min-w-0">
                                        <h4 className="font-medium text-gray-900 text-sm leading-tight">{item.descripcion}</h4>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Stock: {item.stock_disponible || 'N/A'}
                                        </p>
                                    </div>
                                    <div className="ml-3 flex-shrink-0">
                                        <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${
                                            item.estado === 'DAÑADO' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                                        }`}>
                                            {item.estado}
                                        </span>
                                    </div>
                                </div>

                                {/* Información del producto */}
                                <div className="space-y-2 mb-3">
                                    {/* Códigos */}
                                    <div className="text-xs text-gray-600">
                                        {item.codigo_barras && <span>CB: {item.codigo_barras}</span>}
                                        {item.codigo_proveedor && <span className="ml-2">CP: {item.codigo_proveedor}</span>}
                                        {!item.codigo_barras && !item.codigo_proveedor && (
                                            <span className="text-gray-400">Sin códigos</span>
                                        )}
                                    </div>
                                    
                                    {/* Tipo de operación */}
                                    <div className="text-xs text-gray-500">
                                        {item.estado === 'DAÑADO' ? '🟡 Garantía' : '🟢 Devolución'}
                                    </div>
                                </div>

                                {/* Controles de cantidad y precio */}
                                <div className="grid grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Cantidad</label>
                                        <input
                                            type="number"
                                            value={item.cantidad}
                                            onClick={(e) => {
                                                let cantidad = window.prompt('Ingrese la cantidad');
                                                if (cantidad !== null && cantidad !== '' && parseFloat(cantidad)) {
                                                    actualizarCantidad(item.id, 'entrada', parseFloat(cantidad));
                                                }
                                            }}
                                            onChange={(e) => actualizarCantidad(item.id, 'entrada', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded text-center text-sm"
                                            min="0.01"
                                            step="0.01"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Precio Unit. (factura)</label>
                                        <div className="px-3 py-2 bg-gray-50 border border-gray-300 rounded text-center text-sm font-medium">
                                            ${(parseFloat(item.precio) || 0).toFixed(2)}
                                        </div>
                                        {item.precio_factura != null && item.precio_actual != null && Math.abs(parseFloat(item.precio_factura) - parseFloat(item.precio_actual)) > 0.001 && (
                                            <div className="text-xs text-gray-500 mt-1">Actual: ${(parseFloat(item.precio_actual) || 0).toFixed(2)}</div>
                                        )}
                                    </div>
                                </div>

                                {/* Subtotal y acciones */}
                                <div className="flex justify-between items-center pt-3 border-t border-gray-200">
                                    <div>
                                        <span className="text-xs text-gray-600">Subtotal:</span>
                                        <span className="ml-2 font-semibold text-sm">${(parseFloat(item.subtotal) || 0).toFixed(2)}</span>
                                    </div>
                                    <button
                                        onClick={() => eliminarProducto(item.id, 'entrada')}
                                        className="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors"
                                        title="Eliminar producto"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );

    const renderSeccionSalidas = () => (
        <div className="bg-green-50 rounded-lg p-4">
            <h3 className="font-semibold text-green-800 mb-4">
                📤 SALIDAS - Lo que entrega la empresa
            </h3>
            {salidas.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                    <p>No hay productos en SALIDAS</p>
                    <p className="text-sm">Use el botón "Entregar Producto" para agregar productos</p>
                </div>
            ) : (
                <>
                    {/* Desktop Table */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full border-collapse bg-white rounded-lg overflow-hidden shadow-sm">
                            <thead className="bg-green-100">
                                <tr>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Producto
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Códigos
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-800">
                                        Estado
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Cantidad
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Precio Unit.
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Subtotal
                                    </th>
                                    <th className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-800">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {salidas.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-50">
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div>
                                                <p className="font-medium text-gray-900">{item.descripcion}</p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Stock: {item.stock_disponible || 'N/A'}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div className="text-sm text-gray-600">
                                                {item.codigo_barras && (
                                                    <div>CB: {item.codigo_barras}</div>
                                                )}
                                                {item.codigo_proveedor && (
                                                    <div>CP: {item.codigo_proveedor}</div>
                                                )}
                                                {!item.codigo_barras && !item.codigo_proveedor && (
                                                    <div className="text-gray-400">Sin códigos</div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3">
                                            <div className="text-sm">
                                                <span className="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    {item.estado}
                                                </span>
                                                <div className="text-xs text-gray-500 mt-1">
                                                    A entregar
                                                </div>
                                            </div>
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center">
                                            <input
                                                type="number"
                                                value={item.cantidad}
                                                onClick={(e) => {
                                                    let cantidad = window.prompt('Ingrese la cantidad');
                                                    if (cantidad !== null && cantidad !== '' && parseFloat(cantidad)) {
                                                        actualizarCantidad(item.id, 'salida', parseFloat(cantidad));
                                                    }
                                                }}
                                                onChange={(e) => actualizarCantidad(item.id, 'salida', e.target.value)}
                                                className="w-16 px-2 py-1 border rounded text-center text-sm"
                                                min="0.01"
                                                step="0.01"
                                            />
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center text-sm font-medium">
                                            ${(parseFloat(item.precio) || 0).toFixed(2)}
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center text-sm font-semibold">
                                            ${(parseFloat(item.subtotal) || 0).toFixed(2)}
                                        </td>
                                        <td className="border border-gray-200 px-4 py-3 text-center">
                                            <button
                                                onClick={() => eliminarProducto(item.id, 'salida')}
                                                className="text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded"
                                                title="Eliminar producto"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Cards */}
                    <div className="md:hidden space-y-3">
                        {salidas.map((item) => (
                            <div key={item.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                                {/* Header con título y estado */}
                                <div className="flex justify-between items-start mb-3">
                                    <div className="flex-1 min-w-0">
                                        <h4 className="font-medium text-gray-900 text-sm leading-tight">{item.descripcion}</h4>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Stock: {item.stock_disponible || 'N/A'}
                                        </p>
                                    </div>
                                    <div className="ml-3 flex-shrink-0">
                                        <span className="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {item.estado}
                                        </span>
                                    </div>
                                </div>

                                {/* Información del producto */}
                                <div className="space-y-2 mb-3">
                                    {/* Códigos */}
                                    <div className="text-xs text-gray-600">
                                        {item.codigo_barras && <span>CB: {item.codigo_barras}</span>}
                                        {item.codigo_proveedor && <span className="ml-2">CP: {item.codigo_proveedor}</span>}
                                        {!item.codigo_barras && !item.codigo_proveedor && (
                                            <span className="text-gray-400">Sin códigos</span>
                                        )}
                                    </div>
                                    
                                    {/* Tipo de operación */}
                                    <div className="text-xs text-gray-500">
                                        🟢 A entregar
                                    </div>
                                </div>

                                {/* Controles de cantidad y precio */}
                                <div className="grid grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Cantidad</label>
                                        <input
                                            type="number"
                                            value={item.cantidad}
                                            onClick={(e) => {
                                                let cantidad = window.prompt('Ingrese la cantidad');
                                                if (cantidad !== null && cantidad !== '' && parseFloat(cantidad)) {
                                                    actualizarCantidad(item.id, 'salida', parseFloat(cantidad));
                                                }
                                            }}
                                            onChange={(e) => actualizarCantidad(item.id, 'salida', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded text-center text-sm"
                                            min="0.01"
                                            step="0.01"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Precio Unit.</label>
                                        <div className="px-3 py-2 bg-gray-50 border border-gray-300 rounded text-center text-sm font-medium">
                                            ${(parseFloat(item.precio) || 0).toFixed(2)}
                                        </div>
                                    </div>
                                </div>

                                {/* Subtotal y acciones */}
                                <div className="flex justify-between items-center pt-3 border-t border-gray-200">
                                    <div>
                                        <span className="text-xs text-gray-600">Subtotal:</span>
                                        <span className="ml-2 font-semibold text-sm">${(parseFloat(item.subtotal) || 0).toFixed(2)}</span>
                                    </div>
                                    <button
                                        onClick={() => eliminarProducto(item.id, 'salida')}
                                        className="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors"
                                        title="Eliminar producto"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );

    const renderResumen = () => (
        <div className="bg-white border rounded-lg p-4">
            <h3 className="font-semibold text-gray-800 mb-3">📊 Resumen del Carrito</h3>
            
            {procesandoCarrito && (
                <div className="text-center py-4">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                    <p className="text-sm text-gray-600 mt-2">Procesando carrito...</p>
                </div>
            )}

            {!procesandoCarrito && (
                <div className="space-y-3">
                    {/* Caso de uso detectado */}
                    {resumen.caso_uso && (
                        <div className="p-3 bg-blue-50 border-l-4 border-blue-400 rounded">
                            <p className="text-sm font-medium text-blue-700">
                                🎯 Caso {resumen.caso_uso}: {resumen.descripcion_caso}
                            </p>
                        </div>
                    )}

                    {/* Totales */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-sm text-gray-600">Total Entradas</p>
                            <p className="font-semibold">${(parseFloat(resumen.total_entradas) || 0).toFixed(2)}</p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Total Salidas</p>
                            <p className="font-semibold">${(parseFloat(resumen.total_salidas) || 0).toFixed(2)}</p>
                        </div>
                    </div>

                    {/* Balance simplificado - sin métodos de pago */}
                    <div className={`p-3 rounded ${
                        resumen.balance_tipo === 'equilibrio' ? 'bg-green-50' :
                        resumen.balance_tipo === 'favor_cliente' ? 'bg-blue-50' :
                        'bg-yellow-50'
                    }`}>
                        <div className="flex justify-between items-center">
                            <div>
                                <p className="text-sm font-medium">
                                    {resumen.balance_tipo === 'equilibrio' && '⚖️ Balance equilibrado'}
                                    {resumen.balance_tipo === 'favor_cliente' && '💰 A favor del cliente'}
                                    {resumen.balance_tipo === 'favor_empresa' && '📈 A favor de la empresa'}
                                </p>
                                {resumen.diferencia_pago !== 0 && (
                                    <p className="text-sm text-gray-600">
                                        Diferencia: ${(parseFloat(Math.abs(resumen.diferencia_pago)) || 0).toFixed(2)}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Errores */}
                    {resumen.errores.length > 0 && (
                        <div className="bg-red-50 p-3 rounded">
                            <p className="text-sm font-medium text-red-700 mb-2">⚠️ Errores:</p>
                            <ul className="text-sm text-red-600 space-y-1">
                                {resumen.errores.map((error, index) => (
                                    <li key={index}>• {error}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );

    return (
        <div className="carrito-dinamico space-y-6">
            {/* Buscador */}
            <div className="bg-white border border-gray-300 rounded-lg p-4">
                <h3 className="font-semibold text-gray-800 mb-3">🔍 Buscar y Agregar Productos</h3>
                
                {/* Estado de validación unificado */}
                {modoTrasladoInterno ? (
                    <div className="bg-orange-50 border border-orange-200 rounded-md p-3 mb-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-orange-700 mb-2">
                                    <strong>🔄 Modo Traslado Interno Activado:</strong> No se requiere validación de factura.
                                </p>
                                <div className="text-xs text-orange-600 space-y-1">
                                    <p><strong>Instrucciones:</strong></p>
                                    <ul className="list-disc list-inside space-y-1 ml-2">
                                        <li>1. Busque y agregue el producto <strong>dañado</strong> en la entrada (botón amarillo)</li>
                                        <li>2. Luego agregue el mismo producto <strong>bueno</strong> en la salida (botón verde)</li>
                                        <li>3. El sistema validará que sea el mismo producto y cantidades equilibradas</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (!productosFacturados || productosFacturados.length === 0) ? (
                    <div className="bg-orange-50 border border-orange-200 rounded-md p-3 mb-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-orange-700">
                                    <strong>⚠️ Debe validar una factura primero</strong> para procesar garantías/devoluciones.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : !db ? (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-yellow-700">
                                    <strong>Base de datos no disponible.</strong> Verifique la conexión.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="bg-green-50 border border-green-200 rounded-md p-3 mb-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-green-700">
                                    <strong>✅ Factura validada:</strong> {productosFacturados.length} producto{productosFacturados.length !== 1 ? 's' : ''} disponible{productosFacturados.length !== 1 ? 's' : ''} para procesar.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
                
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                   {/*  <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Buscar por:</label>
                        <select 
                            value={tipoBusqueda}
                            onChange={handleSearchTypeChange}
                            className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 ${
                                !db 
                                    ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed' 
                                    : 'border-gray-300 focus:ring-blue-500'
                            }`}
                            disabled={!db}
                        >
                            <option value="descripcion">Descripción</option>
                            <option value="codigo_barras">Código de Barras</option>
                            <option value="codigo_proveedor">Código Proveedor</option>
                        </select>
                    </div> */}
                    <div className="md:col-span-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Buscar Producto <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={busqueda}
                            onChange={handleSearchChange}
                            placeholder={!db ? 'Base de datos no disponible' : 'Escriba el nombre del producto...'}
                            className={`w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 ${
                                !db 
                                    ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed' 
                                    : 'border-gray-300 focus:ring-blue-500'
                            }`}
                            disabled={!db}
                        />
                    </div>
                </div>
                
                {/* Resultados de búsqueda */}
                <div className="relative">
                    {cargandoBusqueda && (
                        <div className="text-center py-4">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                            <p className="text-sm text-gray-600 mt-2">Buscando productos...</p>
                        </div>
                    )}
                    
                    {!cargandoBusqueda && busqueda.length >= 2 && productosEncontrados.length === 0 && (
                        <div className="text-center py-4">
                            <div className="text-gray-500">
                                <p>No se encontraron productos que coincidan con "{busqueda}"</p>
                            </div>
                        </div>
                    )}
                    
                    {productosEncontrados.length > 0 && (
                        <div className="mt-3">
                            <h4 className="text-sm font-medium text-gray-700 mb-2">
                                Resultados de búsqueda ({productosEncontrados.length}):
                            </h4>
                            {renderProductosEncontrados()}
                        </div>
                    )}
                </div>
            </div>

            {/* Secciones del carrito */}
            <div className="space-y-6">
                {renderSeccionEntradas()}
                {renderSeccionSalidas()}
            </div>

            {/* Resumen */}
            {renderResumen()}
        </div>
    );
};

export default CarritoDinamico; 