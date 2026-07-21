import React, { useState, useEffect, useRef, useCallback } from 'react';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import db from '../database/database';

// ###################################################################################
// #                            INICIO: MOCK DATA Y SERVICIOS                        #
// ###################################################################################

const ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER = 1;
let nextTransferenciaId = 4;
let nextDetalleId = 200; // Incremented to avoid collision with new item IDs

// --- Estado Mapping ---
const ESTADO_NUMERICO_A_STRING = {
    1: 'PENDIENTE',
    2: 'PROCESADO',
    3: 'EN REVISION',
    4: 'REVISADO',

   
};



const ESTADO_STRING_A_NUMERICO = {
    'PENDIENTE': 1,
    'EN REVISION': 3,
    'REVISADO': 4,
    'PROCESADO': 2,
};
const OPCIONES_ESTATUS_STRING = Object.values(ESTADO_NUMERICO_A_STRING);


const mockSucursalesData = [
    { id: 1, nombre_sucursal: 'Sucursal Principal (Automática)', direccion_sucursal: 'Calle Falsa 123' },
    { id: 2, nombre_sucursal: 'Sucursal Norte', direccion_sucursal: 'Av. Norte 456' },
    { id: 3, nombre_sucursal: 'Sucursal Centro', direccion_sucursal: 'Plaza Central 789' },
    { id: 4, nombre_sucursal: 'Almacén General', direccion_sucursal: 'Bodega 001' },
];

// Inventario sigue igual, pero lo usaremos para buscar y popular los 'items' de transferencia
const mockInventarioData = [
    { id: 101, sucursal_id: 1, codigo_barras: '7501001', codigo_proveedor: 'PROV001', descripcion: 'Laptop Gamer Pro X', cantidad: 15, precio: 1200.00, precio_base: 1000.00 },
    { id: 102, sucursal_id: 1, codigo_barras: '7501002', codigo_proveedor: 'PROV002', descripcion: 'Monitor Curvo 32"', cantidad: 25, precio: 450.00, precio_base: 380.00 },
    { id: 103, sucursal_id: 1, codigo_barras: '7501003', codigo_proveedor: 'PROV001', descripcion: 'Teclado Mecánico RGB', cantidad: 50, precio: 80.00, precio_base: 60.00 },
    { id: 104, sucursal_id: 1, codigo_barras: '7501004', codigo_proveedor: 'PROV003', descripcion: 'Mouse Inalámbrico Ergo', cantidad: 30, precio: 40.00, precio_base: 30.00 },
    { id: 105, sucursal_id: 1, codigo_barras: '7501005', codigo_proveedor: 'PROV002', descripcion: 'Webcam HD 1080p', cantidad: 0, precio: 60.00, precio_base: 45.00 },
    { id: 106, sucursal_id: 1, codigo_barras: 'SCANTEST001', codigo_proveedor: 'SCAN01', descripcion: 'Producto Escáner Rápido', cantidad: 100, precio: 10.00, precio_base: 5.00 },
];

// Adaptado a la nueva estructura JSON
let mockTransferenciasData = [
    {
        id: 1,
        id_cxp: null,
        idinsucursal: 1001, // ID de la transferencia en la sucursal (simulado)
        estado: ESTADO_STRING_A_NUMERICO['PROCESADO'], // 3
        id_origen: 1,
        id_destino: 2,
        created_at: new Date(2024, 4, 10, 10, 30).toISOString(),
        updated_at: new Date(2024, 4, 10, 11, 0).toISOString(),
        base: 1060.00, // Suma de bases de items
        venta: 1280.00, // Suma de ventas de items
        items: [
            {
                id: 10, // ID del item de transferencia
                id_producto: 101, // ID del producto global (asumiendo)
                id_pedido: 1, // ID de la transferencia a la que pertenece
                cantidad: "2.00",
                basef: "1000.00", // Precio base formateado (string)
                base: "1000.00",   // Precio base (string)
                venta: "1200.00",  // Precio venta (string)
                descuento: "0.00",
                monto: "2400.00", // cantidad * venta
                ct_real: 2,
                barras_real: '7501001',
                alterno_real: 'PROV001',
                descripcion_real: 'Laptop Gamer Pro X',
                vinculo_real: 101, // ID del inventario_sucursal
                created_at: new Date(2024, 4, 10, 10, 30).toISOString(),
                updated_at: new Date(2024, 4, 10, 10, 30).toISOString(),
                id_producto_insucursal: 101, // ID del registro en inventario_sucursal
                // producto_insucursal: mockInventarioData.find(p => p.id === 101), // Podríamos popularlo
                // ...otros campos de item...
                modificable: false,
            },
            {
                id: 11,
                id_producto: 103,
                id_pedido: 1,
                cantidad: "5.00",
                basef: "60.00",
                base: "60.00",
                venta: "80.00",
                descuento: "0.00",
                monto: "400.00",
                ct_real: 5,
                barras_real: '7501003',
                alterno_real: 'PROV001',
                descripcion_real: 'Teclado Mecánico RGB',
                vinculo_real: 103,
                created_at: new Date(2024, 4, 10, 10, 30).toISOString(),
                updated_at: new Date(2024, 4, 10, 10, 30).toISOString(),
                id_producto_insucursal: 103,
                modificable: false,
            }
        ],
        origen: mockSucursalesData.find(s => s.id === 1),
        destino: mockSucursalesData.find(s => s.id === 2),
        // sucursal: mockSucursalesData.find(s => s.id === 1), // Sucursal que registra la transferencia
    },
    {
        id: 2,
        id_cxp: null,
        idinsucursal: 1002,
        estado: ESTADO_STRING_A_NUMERICO['EN REVISION'], // 1
        id_origen: 1,
        id_destino: 3,
        created_at: new Date(2024, 4, 15, 14, 0).toISOString(),
        updated_at: new Date(2024, 4, 15, 14, 5).toISOString(),
        base: 3800.00,
        venta: 4500.00,
        items: [
            {
                id: 12,
                id_producto: 102,
                id_pedido: 2,
                cantidad: "10.00",
                base: "380.00",
                venta: "450.00",
                descuento: "0.00",
                monto: "4500.00",
                ct_real: 10,
                barras_real: '7501002',
                alterno_real: 'PROV002',
                descripcion_real: 'Monitor Curvo 32"',
                vinculo_real: 102,
                created_at: new Date(2024, 4, 15, 14, 0).toISOString(),
                updated_at: new Date(2024, 4, 15, 14, 0).toISOString(),
                id_producto_insucursal: 102,
                modificable: false,
            }
        ],
        origen: mockSucursalesData.find(s => s.id === 1),
        destino: mockSucursalesData.find(s => s.id === 3),
    },
    {
        id: 3,
        id_cxp: null,
        idinsucursal: 1003,
        estado: ESTADO_STRING_A_NUMERICO['PENDIENTE'], // 0
        id_origen: 1,
        id_destino: 4,
        created_at: new Date(Date.now() - 3600000 * 5).toISOString(), // Hace 5 horas
        updated_at: new Date(Date.now() - 3600000 * 5).toISOString(),
        base: 300.00,
        venta: 400.00,
        items: [
            {
                id: 13,
                id_producto: 104,
                id_pedido: 3,
                cantidad: "10.00",
                base: "30.00",
                venta: "40.00",
                descuento: "0.00",
                monto: "400.00",
                ct_real: 10,
                barras_real: '7501004',
                alterno_real: 'PROV003',
                descripcion_real: 'Mouse Inalámbrico Ergo',
                vinculo_real: 104,
                created_at: new Date(Date.now() - 3600000 * 5).toISOString(),
                updated_at: new Date(Date.now() - 3600000 * 5).toISOString(),
                id_producto_insucursal: 104,
                modificable: true, // Pendiente es modificable
            }
        ],
        origen: mockSucursalesData.find(s => s.id === 1),
        destino: mockSucursalesData.find(s => s.id === 4),
    },
];

// --- Mock API Service Functions --- (Obtener sucursales y buscar productos no cambian mucho)
const obtenerSucursalesMock = (excluirId = null) => {
    return new Promise((resolve) => {
        setTimeout(() => {
            const data = excluirId
                ? mockSucursalesData.filter(s => s.id !== excluirId)
                : [...mockSucursalesData];
            resolve({ data });
        }, 100);
    });
};

const buscarProductosInventarioMock = (termino, sucursalIdOrigen = null) => {
    return new Promise((resolve) => {
        setTimeout(() => {
            if (!termino || termino.trim() === '') { resolve({ data: [] }); return; }
            const terminoLower = termino.toLowerCase();
            const resultados = mockInventarioData.filter(p =>
                (sucursalIdOrigen ? p.sucursal_id === sucursalIdOrigen : true) &&
                p.cantidad > 0 &&
                (p.descripcion.toLowerCase().includes(terminoLower) ||
                 p.codigo_barras.toLowerCase().includes(terminoLower) ||
                 (p.codigo_proveedor && p.codigo_proveedor.toLowerCase().includes(terminoLower)))
            ).slice(0, 15);
            resolve({ data: resultados });
        }, 200);
    });
};

const obtenerTransferenciasMock = (filtros = {}) => {
    return new Promise((resolve) => {
        setTimeout(() => {
            let transferenciasFiltradas = mockTransferenciasData.map(t => ({
                ...t,
                // Asegurarse que origen y destino estén poblados si no lo están ya
                origen: t.origen || mockSucursalesData.find(s => s.id === t.id_origen),
                destino: t.destino || mockSucursalesData.find(s => s.id === t.id_destino),
            }));


            if (filtros.estatus_string) { // Filtrar por string de estado
                const estadoNum = ESTADO_STRING_A_NUMERICO[filtros.estatus_string];
                if (estadoNum !== undefined) {
                    transferenciasFiltradas = transferenciasFiltradas.filter(t => t.estado === estadoNum);
                }
            }
            if (filtros.id_destino) { // Cambiado de sucursal_destino_id
                transferenciasFiltradas = transferenciasFiltradas.filter(t => t.id_destino === parseInt(filtros.id_destino));
            }
            if (filtros.id_origen) { // Nuevo filtro
                transferenciasFiltradas = transferenciasFiltradas.filter(t => t.id_origen === parseInt(filtros.id_origen));
            }
            if (filtros.fecha_desde) {
                transferenciasFiltradas = transferenciasFiltradas.filter(t => new Date(t.created_at) >= new Date(filtros.fecha_desde));
            }
            if (filtros.fecha_hasta) {
                const fechaHasta = new Date(filtros.fecha_hasta);
                fechaHasta.setHours(23, 59, 59, 999);
                transferenciasFiltradas = transferenciasFiltradas.filter(t => new Date(t.created_at) <= fechaHasta);
            }

            transferenciasFiltradas.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            const porPagina = filtros.por_pagina || 10;
            const pagina = filtros.page || 1;
            const total = transferenciasFiltradas.length;
            const inicio = (pagina - 1) * porPagina;
            const fin = inicio + porPagina;
            const dataPaginada = transferenciasFiltradas.slice(inicio, fin);

            resolve({
                data: {
                    data: dataPaginada,
                    current_page: pagina,
                    last_page: Math.ceil(total / porPagina),
                    total: total,
                    per_page: porPagina,
                    from: total > 0 ? inicio + 1 : 0,
                    to: fin > total ? total : fin,
                }
            });
        }, 300);
    });
};

const crearOActualizarTransferenciaMock = (datosTransferencia, esEdicion = false) => {
   
};

// El cambio de estado ya no se hace desde UI, así que esta función mock no se usará externamente.
// const actualizarEstadoTransferenciaMock = (id, nuevoEstadoNum) => { ... }


// ###################################################################################
// #                            FIN: MOCK DATA Y SERVICIOS                           #
// ###################################################################################


// ###################################################################################
// #                            INICIO: COMPONENTES REACT                            #
// ###################################################################################

const StatusBadge = ({ estadoNum }) => {
    const estatusString = ESTADO_NUMERICO_A_STRING[estadoNum] || 'DESCONOCIDO';
    const statusColors = {
        'PENDIENTE': 'bg-red-500 text-white',
        'EN REVISION': 'bg-yellow-400 text-black',
        'REVISADO': 'bg-sky-500 text-white',
        'PROCESADO': 'bg-green-500 text-white',
        'DESCONOCIDO': 'bg-gray-400 text-black',
    };
    return (
        <span className={`px-3 py-1 text-xs font-semibold rounded-full leading-tight ${statusColors[estatusString]}`}>
            {estatusString}
        </span>
    );
};

const ProductSearchInput = ({ onProductSelect, sucursalIdOrigen, placeholder = "Buscar producto..." }) => {
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const [resultados, setResultados] = useState([]);
    const [estaCargando, setEstaCargando] = useState(false);
    const [mostrarResultados, setMostrarResultados] = useState(false);
    const [productoSeleccionado, setProductoSeleccionado] = useState(null);
    const [mostrarModalCantidad, setMostrarModalCantidad] = useState(false);
    const [cantidadSeleccionada, setCantidadSeleccionada] = useState('');
    const inputRef = useRef(null);
    const cantidadInputRef = useRef(null);
    const debounceTimeoutRef = useRef(null);

    const realizarBusqueda = useCallback(async (termino) => {
        if (!termino || termino.trim() === '') { setResultados([]); setMostrarResultados(false); return; }
        setEstaCargando(true);
        try {
            const response = await db.getinventario({
                vendedor: null,
                num:25,
                itemCero: false,
                qProductosMain: termino,
                orderColumn:"descripcion",
                orderBy:"asc",
            });
            console.log(response.data)
            setResultados(response.data || []);
            setMostrarResultados(true);
        } catch (error) { console.error("Error buscando productos (mock):", error); setResultados([]); }
        finally { setEstaCargando(false); }
    }, [sucursalIdOrigen]);

    useEffect(() => {
        if (debounceTimeoutRef.current) clearTimeout(debounceTimeoutRef.current);
        if (terminoBusqueda.trim() !== '') {
            debounceTimeoutRef.current = setTimeout(() => realizarBusqueda(terminoBusqueda), 300);
        } else { setResultados([]); setMostrarResultados(false); }
        return () => clearTimeout(debounceTimeoutRef.current);
    }, [terminoBusqueda, realizarBusqueda]);

    const handleSelectProduct = (producto) => {
        setProductoSeleccionado(producto);
        setCantidadSeleccionada('');
        setMostrarModalCantidad(true);
        setMostrarResultados(false);
    };

    const handleConfirmarCantidad = () => {
        // Si está vacío, establecer a 1
        const cantidadFinal = cantidadSeleccionada.trim() === '' ? '1.00' : cantidadSeleccionada;
        const cantidadNum = parseFloat(cantidadFinal);
        const stockOriginalNum = parseFloat(productoSeleccionado.cantidad);

        if (cantidadNum > stockOriginalNum) {
            alert(`La cantidad seleccionada (${cantidadFinal}) excede el stock disponible (${stockOriginalNum}).`);
            return;
        }

        onProductSelect({
            ...productoSeleccionado,
            cantidadInicial: cantidadFinal
        });
        
        setTerminoBusqueda('');
        setProductoSeleccionado(null);
        setCantidadSeleccionada('');
        setMostrarModalCantidad(false);
        inputRef.current?.focus();
    };

    const handleCantidadChange = (e) => {
        const valor = e.target.value;
        if (valor === '' || /^\d*\.?\d{0,2}$/.test(valor)) {
            setCantidadSeleccionada(valor);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleConfirmarCantidad();
        }
    };

    useEffect(() => { 
        inputRef.current?.focus();
        if (mostrarModalCantidad) {
            // Pequeño delay para asegurar que el modal esté renderizado
            setTimeout(() => {
                cantidadInputRef.current?.focus();
            }, 100);
        }
    }, [mostrarModalCantidad]);

    return (
        <div className="relative w-full">
            <input 
                ref={inputRef} 
                type="text" 
                className="w-full p-2 border border-gray-300 rounded-md shadow-sm" 
                placeholder={placeholder} 
                value={terminoBusqueda} 
                onChange={(e) => setTerminoBusqueda(e.target.value)} 
                onFocus={() => terminoBusqueda && resultados.length > 0 && setMostrarResultados(true)} 
                onBlur={() => setTimeout(() => setMostrarResultados(false), 150)} 
            />
            
            {estaCargando && <div className="absolute mt-1 w-full p-2 text-sm text-gray-500 bg-white border rounded shadow-lg">Buscando...</div>}
            
            {mostrarResultados && (
                <ul className="absolute z-20 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-72 overflow-y-auto">
                    {resultados.length > 0 ? (
                        resultados.map(producto => (
                            <li key={producto.id} className="px-3 py-2 hover:bg-indigo-50 cursor-pointer" onMouseDown={() => handleSelectProduct(producto)}>
                                <div className="flex justify-between items-center">
                                    <div>
                                        <div className="font-medium">{producto.descripcion}</div>
                                        <div className="text-sm text-gray-600"><b>{producto.codigo_barras}</b> | {producto.codigo_proveedor}</div>
                                        <div className="text-sm text-gray-600">Stock: {producto.cantidad}</div>
                                    </div>
                                </div>
                            </li>
                        ))
                    ) : (!estaCargando && terminoBusqueda && <li className="px-3 py-2 text-gray-500">No se encontraron productos.</li>)}
                </ul>
            )}

            {/* Modal de Cantidad */}
            {mostrarModalCantidad && productoSeleccionado && (
                <div className="fixed inset-0 bg-gray-900/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
                    <div className="relative mt-24 w-full max-w-xs p-4 border shadow-xl rounded-lg bg-white">
                        <div>
                            <h3 className="text-base font-semibold leading-6 text-gray-900 mb-3">
                                Seleccionar Cantidad
                            </h3>
                            <div className="mt-1">
                                <div className="mb-4">
                                    <p className="text-sm text-gray-500 mb-1">Producto:</p>
                                    <p className="font-medium">{productoSeleccionado.descripcion}</p>
                                    <p className="text-sm text-gray-500">Stock disponible: {productoSeleccionado.cantidad}</p>
                                </div>
                                <div className="mb-4">
                                    <label htmlFor="cantidad" className="block text-sm font-medium text-gray-700 mb-1">
                                        Cantidad
                                    </label>
                                    <input
                                        ref={cantidadInputRef}
                                        type="number"
                                        id="cantidad"
                                        step="0.01"
                                        min="0.01"
                                        max={productoSeleccionado.cantidad}
                                        value={cantidadSeleccionada}
                                        onChange={handleCantidadChange}
                                        onKeyDown={handleKeyDown}
                                        className="w-full p-2 text-lg text-center border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder=""
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end space-x-2 pt-3">
                                <button
                                    onClick={() => {
                                        setMostrarModalCantidad(false);
                                        setProductoSeleccionado(null);
                                        setCantidadSeleccionada('');
                                    }}
                                    className="px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-md shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={handleConfirmarCantidad}
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    Agregar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const SelectedProductItem = ({ item, onRemove, onQuantityChange, isEditable, index, totalItems }) => {
    const handleCantidadChange = (e) => {
        const valor = e.target.value;
        // Permitir valores vacíos temporalmente para mejor UX
        if (valor === '') {
            onQuantityChange(item.id_producto_insucursal, '');
            return;
        }
        
        // Validar que sea un número válido con hasta 2 decimales
        if (/^\d*\.?\d{0,2}$/.test(valor)) {
            onQuantityChange(item.id_producto_insucursal, valor);
        }
    };

    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50">
            <td className="px-2 py-1 text-center text-xs text-gray-400 whitespace-nowrap">{index + 1}</td>
            <td className="px-2 py-1 text-sm text-gray-800">{item.descripcion_real || item.producto?.descripcion || '—'}</td>
            <td className="px-2 py-1 text-xs font-mono text-gray-600 whitespace-nowrap">{item.barras_real || item.producto?.codigo_barras || '—'}</td>
            <td className="px-2 py-1 text-xs font-mono text-gray-600 whitespace-nowrap">{item.alterno_real || item.producto?.codigo_proveedor || '—'}</td>
            <td className="px-2 py-1 text-center">
                <input
                    type="text"
                    inputMode="decimal"
                    value={item.cantidad}
                    onChange={handleCantidadChange}
                    onBlur={(e) => {
                        const valor = e.target.value;
                        if (valor === '' || isNaN(parseFloat(valor)) || parseFloat(valor) <= 0) {
                            onQuantityChange(item.id_producto_insucursal, '1.00');
                        } else {
                            onQuantityChange(item.id_producto_insucursal, parseFloat(valor).toFixed(2));
                        }
                    }}
                    readOnly={!isEditable}
                    className={`w-20 p-1 text-sm border border-gray-300 rounded text-center ${!isEditable ? 'bg-gray-100' : 'focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500'}`}
                    aria-label={`Cantidad para ${item.descripcion_real}`}
                />
            </td>
            <td className="px-2 py-1 text-center">
                {isEditable && (
                    <button
                        type="button"
                        onClick={() => onRemove(item.id_producto_insucursal)}
                        className="text-red-600 hover:text-red-800 p-1"
                        title="Eliminar"
                    >
                        <i className="fas fa-trash"></i>
                    </button>
                )}
            </td>
        </tr>
    );
};

const TransferenciaForm = ({ onSave, onCancel, sucursalActualId, transferenciaToEdit = null, sucursales, cargarTransferencias }) => {
    // Una premonta (orden de redistribución traída de central) se arma como transferencia NUEVA,
    // no como edición de una transferencia local existente.
    const esPremonta = !!transferenciaToEdit?.es_premontada;
    const esEdicion = !!transferenciaToEdit && !esPremonta;
    const idSucursalOrigen = sucursalActualId || ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER;

    const [idSucursalDestinoSeleccionada, setIdSucursalDestinoSeleccionada] = useState(transferenciaToEdit?.id_destino || '');
    const [itemsTransferencia, setItemsTransferencia] = useState([]);
    const [error, setError] = useState('');
    const [estaCargando, setEstaCargando] = useState(false);
    const [mensajeExito, setMensajeExito] = useState('');
    const [observaciones, setObservaciones] = useState(transferenciaToEdit?.observaciones || '');
    const [mostrarObservaciones, setMostrarObservaciones] = useState(false);

    // Destino: bloqueado por defecto al editar (evita cambios sin querer); buscador al desbloquear.
    const [destinoBloqueado, setDestinoBloqueado] = useState(esEdicion);
    const [busquedaDestino, setBusquedaDestino] = useState('');
    const [mostrarListaDestino, setMostrarListaDestino] = useState(false);
    const codigoDestino = (sucursales.find(s => String(s.id) === String(idSucursalDestinoSeleccionada)) || {}).codigo || '';
    const sucursalesFiltradas = sucursales.filter(s => {
        const q = busquedaDestino.trim().toLowerCase();
        if (!q) return true;
        return (s.codigo || '').toLowerCase().includes(q) || (s.nombre || '').toLowerCase().includes(q);
    });

    useEffect(() => {
        if (transferenciaToEdit && transferenciaToEdit.items) {
            // Mapear items de la transferencia a editar, obteniendo stock original del inventario
            const itemsMapeados = transferenciaToEdit.items.map(itemAPI => {
                // Buscar el producto en el inventario usando el id_producto_insucursal
                const productoInventario = mockInventarioData.find(pInv => pInv.id === itemAPI.id_producto_insucursal);
                
                // Crear un objeto con la estructura correcta para el formulario
                return {
                    id: itemAPI.id, // ID del item de transferencia
                    id_producto: itemAPI.producto.id, // ID del producto global
                    id_pedido: transferenciaToEdit.id, // ID de la transferencia
                    id_producto_insucursal: itemAPI.producto.id, // ID del producto en inventario
                    cantidad: String(itemAPI.cantidad), // Convertir a string para consistencia
                    base: String(itemAPI.producto.precio_base), // Precio base del producto
                    venta: String(itemAPI.producto.precio), // Precio venta del producto
                    descuento: String(itemAPI.descuento || "0.00"),
                    monto: String((parseFloat(itemAPI.cantidad) * parseFloat(itemAPI.producto.precio)).toFixed(2)),
                    ct_real: parseFloat(itemAPI.cantidad),
                    barras_real: itemAPI.producto.codigo_barras,
                    alterno_real: itemAPI.producto.codigo_proveedor,
                    descripcion_real: itemAPI.producto.descripcion,
                    vinculo_real: itemAPI.id_producto_insucursal,
                    created_at: itemAPI.created_at,
                    updated_at: itemAPI.updated_at,
                    cantidad_original_stock_inventario: itemAPI?.cantidad || 0,
                    modificable: true, // Permitir edición en el formulario,
                    
                };
            });
            
            console.log('Items mapeados para edición:', itemsMapeados);
            setItemsTransferencia(itemsMapeados);
            setObservaciones(transferenciaToEdit.observaciones || '');
        }
    }, [transferenciaToEdit]);

    const handleAddProduct = (productoDeInventario) => {
        // productoDeInventario es un objeto de mockInventarioData con cantidadInicial
        if (itemsTransferencia.find(item => item.id_producto_insucursal === productoDeInventario.id)) {
            alert("Este producto ya ha sido agregado."); return;
        }
        // Crear un nuevo item con la estructura del JSON de la API
        const nuevoItem = {
            id: nextDetalleId++, // ID del item de transferencia (temporal para el mock)
            id_producto: productoDeInventario.id, // ID del producto "global"
            id_pedido: transferenciaToEdit?.id || null, // ID de la transferencia
            cantidad: productoDeInventario.cantidadInicial || "1.00", // Usar la cantidad seleccionada
            base: String(productoDeInventario.precio_base),
            venta: String(productoDeInventario.precio),
            descuento: "0.00",
            monto: String((parseFloat(productoDeInventario.cantidadInicial || "1.00") * parseFloat(productoDeInventario.precio)).toFixed(2)), // Calcular monto con la cantidad seleccionada
            ct_real: parseFloat(productoDeInventario.cantidadInicial || "1.00"),
            barras_real: productoDeInventario.codigo_barras,
            alterno_real: productoDeInventario.codigo_proveedor,
            descripcion_real: productoDeInventario.descripcion,
            vinculo_real: productoDeInventario.id, // ID del inventario_sucursal
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            id_producto_insucursal: productoDeInventario.id, // Clave para identificar el producto del inventario
            cantidad_original_stock_inventario: productoDeInventario.cantidad, // Stock del inventario al momento de agregar
            modificable: true,
        };
        setItemsTransferencia(prev => [...prev, nuevoItem]);
    };

    const handleRemoveProduct = (idProductoInsucursal) => {
        setItemsTransferencia(prev => prev.filter(item => item.id_producto_insucursal !== idProductoInsucursal));
    };

    const handleQuantityChange = (idProductoInsucursal, nuevaCantidadStr) => {
        setItemsTransferencia(prevItems =>
            prevItems.map(item => {
                if (item.id_producto_insucursal !== idProductoInsucursal) return item;

                // Guardar SIEMPRE el string crudo (incluido vacío o intermedio como "1." o "0")
                // para que el input controlado se pueda borrar y reescribir. La normalización
                // (vacío/0 → 1.00, redondeo a 2 decimales) la hace el onBlur del input.
                const nuevaCantidadNum = parseFloat(nuevaCantidadStr);
                const valido = !isNaN(nuevaCantidadNum) && nuevaCantidadNum > 0;
                const ventaNum = parseFloat(item.venta);
                const nuevoMonto = valido ? (nuevaCantidadNum * ventaNum).toFixed(2) : item.monto;

                return {
                    ...item,
                    cantidad: nuevaCantidadStr,
                    monto: String(nuevoMonto),
                    ct_real: valido ? nuevaCantidadNum : item.ct_real,
                };
            })
        );
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(''); 
        setMensajeExito('');
        
        if (!idSucursalDestinoSeleccionada) { 
            setError('Debe seleccionar una sucursal de destino.'); 
            return; 
        }
        if (itemsTransferencia.length === 0) { 
            setError('Debe agregar al menos un producto.'); 
            return; 
        }

        let validSubmission = true;
        itemsTransferencia.forEach(item => {
            const cant = parseFloat(item.cantidad);
            const stockOrig = parseFloat(item.cantidad_original_stock_inventario);
            if (isNaN(cant) || cant <= 0) {
                setError(`Cantidad inválida para ${item.descripcion_real}.`);
                validSubmission = false;
            }
           /*  if (cant > stockOrig) {
                setError(`Cantidad para ${item.descripcion_real} (${cant}) excede el stock original del inventario (${stockOrig}).`);
                validSubmission = false;
            } */
        });
        if (!validSubmission) return;

        const datosTransferencia = {
            id_origen: idSucursalOrigen,
            id_destino: parseInt(idSucursalDestinoSeleccionada),
            observaciones: observaciones.trim(),
            items: itemsTransferencia.map(item => ({
                id: esEdicion ? item.id : undefined,
                id_producto_insucursal: item.id_producto_insucursal,
                cantidad: item.cantidad,
                base: item.base,
                venta: item.venta,
                descripcion_real: item.descripcion_real,
                barras_real: item.barras_real,
            })),
        };

        // Agregar el ID si es edición
        if (esEdicion) {
            datosTransferencia.id = transferenciaToEdit.id;
            datosTransferencia.actualizando = true; // Indicador de que es una actualización
        } else {
            datosTransferencia.actualizando = false; // Indicador de que es una creación
        }

        // Si nace de una premonta (orden de redistribución aprobada), reenviamos su id para que
        // central marque la orden "En Tránsito" y ancle el pedido.
        if (esPremonta && transferenciaToEdit?.id_orden_distribucion) {
            datosTransferencia.id_orden_distribucion = transferenciaToEdit.id_orden_distribucion;
        }

        setEstaCargando(true);
        try {
            const res = await db.settransferenciaDici(datosTransferencia);
            
            if (res.data.estado) {
                setMensajeExito(`Transferencia ${esEdicion ? 'actualizada' : 'creada'} exitosamente.`);
                await cargarTransferencias(); // Recargar la lista
                
                if (!esEdicion) {
                    // Resetear el formulario solo si es creación
                    setIdSucursalDestinoSeleccionada('');
                    setItemsTransferencia([]);
                    setObservaciones('');
                }
                
                // Notificar al componente padre
                onSave(res.data);
                
                // Limpiar mensaje de éxito después de 5 segundos
                setTimeout(() => setMensajeExito(''), 5000);
            } else {
                throw new Error(res.data.mensaje || 'Error al procesar la transferencia');
            }
        } catch (err) {
            setError(err.message || `Error al ${esEdicion ? 'actualizar' : 'crear'} transferencia.`);
        } finally {
            setEstaCargando(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-3 p-1 md:p-3 rounded-lg">
            {/* Encabezado compacto + destino, arriba */}
            <div className="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-gray-200">
                <h2 className="text-sm font-semibold text-gray-800 uppercase tracking-wide">
                    {esEdicion ? `Editando Transferencia #${transferenciaToEdit.id}` : 'Nueva Transferencia'}
                </h2>
                <div className="flex items-center gap-2">
                    <span className="text-xs font-medium text-gray-500">Destino:</span>
                    {destinoBloqueado ? (
                        <>
                            <span className="px-2 py-1 bg-gray-100 text-gray-800 text-sm font-semibold rounded border border-gray-200">
                                {codigoDestino || '— sin destino —'}
                            </span>
                            <button
                                type="button"
                                onClick={() => { setDestinoBloqueado(false); setMostrarListaDestino(true); }}
                                className="inline-flex items-center text-xs px-2 py-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50"
                                title="Desbloquear para cambiar destino"
                            >
                                <i className="fas fa-lock mr-1"></i>Cambiar
                            </button>
                        </>
                    ) : (
                        <div className="relative">
                            <input
                                type="text"
                                value={busquedaDestino}
                                onChange={(e) => { setBusquedaDestino(e.target.value); setMostrarListaDestino(true); }}
                                onFocus={() => setMostrarListaDestino(true)}
                                onBlur={() => setTimeout(() => setMostrarListaDestino(false), 150)}
                                placeholder="Buscar sucursal..."
                                className="w-44 px-2 py-1 text-sm border border-indigo-400 rounded focus:outline-none focus:ring-1 focus:ring-indigo-400"
                            />
                            {mostrarListaDestino && (
                                <ul className="absolute right-0 z-20 mt-1 w-56 max-h-56 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg">
                                    {sucursalesFiltradas.length ? sucursalesFiltradas.map(s => (
                                        <li
                                            key={s.id}
                                            onMouseDown={() => {
                                                setIdSucursalDestinoSeleccionada(String(s.id));
                                                setBusquedaDestino('');
                                                setMostrarListaDestino(false);
                                                setDestinoBloqueado(true);
                                            }}
                                            className="px-3 py-1.5 text-sm hover:bg-indigo-50 cursor-pointer"
                                        >
                                            <b>{s.codigo}</b> <span className="text-gray-500">{s.nombre}</span>
                                        </li>
                                    )) : <li className="px-3 py-1.5 text-sm text-gray-400">Sin coincidencias</li>}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            </div>
            {error && <div className="bg-red-100 border-l-4 border-red-500 text-red-700 px-3 py-2 text-sm"><p>{error}</p></div>}
            {mensajeExito && <div className="bg-green-100 border-l-4 border-green-500 text-green-700 px-3 py-2 text-sm"><p>{mensajeExito}</p></div>}

            

            {/* Contenedor con posición relativa para el área de búsqueda y lista */}
            <div className="relative">
                {/* Área de búsqueda fija */}
                <div className="sticky top-0 z-10 bg-white pb-2 border-b border-gray-200">
                    <label className="block text-xs font-medium text-gray-700 mb-1">Buscar y Agregar Productos:</label>
                    <ProductSearchInput onProductSelect={handleAddProduct} sucursalIdOrigen={idSucursalOrigen} />
                </div>

                {/* Lista de productos en tabla */}
                {itemsTransferencia.length > 0 && (
                    <div className="mt-3">
                        <h3 className="text-sm font-semibold text-gray-700 mb-1">
                            Productos ({itemsTransferencia.length})
                        </h3>
                        <div className="border rounded-md max-h-[calc(100vh-340px)] overflow-y-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                                    <tr>
                                        <th className="px-2 py-1 text-center font-semibold">#</th>
                                        <th className="px-2 py-1 text-left font-semibold">Descripción</th>
                                        <th className="px-2 py-1 text-left font-semibold">Cód. Barras</th>
                                        <th className="px-2 py-1 text-left font-semibold">Cód. Proveedor</th>
                                        <th className="px-2 py-1 text-center font-semibold">Cantidad</th>
                                        <th className="px-2 py-1 text-center font-semibold w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {itemsTransferencia.map((item, index) => (
                                        <SelectedProductItem
                                            key={item.id_producto_insucursal}
                                            item={item}
                                            onRemove={handleRemoveProduct}
                                            onQuantityChange={handleQuantityChange}
                                            isEditable={true}
                                            index={index}
                                            totalItems={itemsTransferencia.length}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>


            <div>
                <button
                    type="button"
                    onClick={() => setMostrarObservaciones(!mostrarObservaciones)}
                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <i className={`fas fa-comment-alt mr-2 ${mostrarObservaciones ? 'text-indigo-600' : 'text-gray-400'}`}></i>
                    {mostrarObservaciones ? 'Ocultar Observaciones' : 'Agregar Observaciones'}
                </button>
            </div>
            {/* Textarea de Observaciones Colapsable */}
            {mostrarObservaciones && (
                <div className="mt-4 transition-all duration-200 ease-in-out">
                    <label htmlFor="observaciones" className="block text-sm font-medium text-gray-700 mb-1">
                        Observaciones
                    </label>
                    <textarea
                        id="observaciones"
                        value={observaciones}
                        onChange={(e) => setObservaciones(e.target.value)}
                        rows="3"
                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Ingrese observaciones adicionales sobre la transferencia..."
                    />
                </div>
            )}

            <div className="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-2 border-t border-gray-200">
                <button type="button" onClick={onCancel} disabled={estaCargando} className="w-full sm:w-auto px-4 py-2 border rounded-md shadow-sm text-sm bg-white hover:bg-gray-50 transition">Cancelar</button>
                <button type="submit" disabled={estaCargando || itemsTransferencia.length === 0} className="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 border-transparent rounded-md shadow-sm text-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition">
                    {estaCargando ? 'Guardando...' : (esEdicion ? 'Actualizar Transferencia' : 'Crear Transferencia')}
                </button>
            </div>
        </form>
    );
};

const TransferenciaDetailView = ({ transferencia, onBack, sucursales }) => {
    if (!transferencia) return <p>Cargando detalles...</p>;

    // Resolver la sucursal desde el objeto embebido que manda central (origen/destino con
    // codigo/nombre/colores) o, si no viene, desde la lista `sucursales` por id. Mismo criterio
    // que datosSuc en la tabla. Antes se leía `nombre_sucursal` (campo inexistente) → "ID: X".
    const sucById = {};
    (sucursales || []).forEach(s => { sucById[s.id] = s; });
    const resolverSuc = (obj, id) => (obj && (obj.codigo || obj.nombre)) ? obj : (sucById[id] || null);
    const labelSuc = (s, id) => (s && (s.nombre || s.codigo)) || (id != null ? `ID: ${id}` : '—');
    const origen = resolverSuc(transferencia.origen, transferencia.id_origen);
    const destino = resolverSuc(transferencia.destino, transferencia.id_destino);

    return (
        <div className="p-4 md:p-6 bg-white rounded-lg shadow-xl">
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Detalle de Transferencia #{transferencia.id}</h2>
                <button onClick={onBack} className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md transition">&larr; Volver al Listado</button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 p-4 border rounded-md bg-gray-50">
                <div><p className="text-sm text-gray-500">ID Transferencia:</p> <p className="font-medium">{transferencia.idinsucursal || transferencia.id}</p></div>
                <div><p className="text-sm text-gray-500">Estado:</p> <StatusBadge estadoNum={transferencia.estado} /></div>
                <div><p className="text-sm text-gray-500">Fecha Creación:</p> <p className="font-medium">{format(new Date(transferencia.created_at), 'dd/MM/yyyy HH:mm', { locale: es })}</p></div>
                <div><p className="text-sm text-gray-500">Última Actualización:</p> <p className="font-medium">{format(new Date(transferencia.updated_at), 'dd/MM/yyyy HH:mm', { locale: es })}</p></div>
                <div>
                    <p className="text-sm text-gray-500">Sucursal Origen:</p>
                    <p className="font-medium flex items-center gap-2">
                        {origen?.codigo && (
                            <span className="inline-block px-2 py-0.5 rounded text-xs font-bold" style={{ backgroundColor: origen.background || '#e5e7eb', color: origen.color || '#374151' }}>{origen.codigo}</span>
                        )}
                        {labelSuc(origen, transferencia.id_origen)}
                    </p>
                </div>
                <div>
                    <p className="text-sm text-gray-500">Sucursal Destino:</p>
                    <p className="font-medium flex items-center gap-2">
                        {destino?.codigo && (
                            <span className="inline-block px-2 py-0.5 rounded text-xs font-bold" style={{ backgroundColor: destino.background || '#e5e7eb', color: destino.color || '#374151' }}>{destino.codigo}</span>
                        )}
                        {labelSuc(destino, transferencia.id_destino)}
                    </p>
                </div>
                {transferencia.observaciones && (
                    <div className="md:col-span-2">
                        <p className="text-sm text-gray-500">Observaciones:</p>
                        <p className="font-medium whitespace-pre-wrap">{transferencia.observaciones}</p>
                    </div>
                )}
            </div>

            <h3 className="text-xl font-semibold text-gray-700 mb-3">Items Transferidos:</h3>
            {transferencia.items && transferencia.items.length > 0 ? (
                <div className="border rounded-md overflow-x-auto max-h-[50vh] overflow-y-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 text-left text-xs uppercase sticky top-0">
                            <tr>
                                <th className="px-3 py-2 w-10">#</th>
                                <th className="px-3 py-2">Descripción</th>
                                <th className="px-3 py-2">Cód. Barras</th>
                                <th className="px-3 py-2">Cód. Proveedor</th>
                                <th className="px-3 py-2 text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {transferencia.items.map((item, index) => {
                                const p = item.producto || {};
                                const desc = item.descripcion_real || p.descripcion || '—';
                                const barras = item.barras_real || p.codigo_barras || '—';
                                const alterno = item.alterno_real || p.codigo_proveedor || '—';
                                const cant = parseFloat(item.cantidad);
                                return (
                                    <tr key={item.id || index} className="hover:bg-gray-50">
                                        <td className="px-3 py-2 text-gray-400">{index + 1}</td>
                                        <td className="px-3 py-2 text-gray-800">{desc}</td>
                                        <td className="px-3 py-2 font-mono text-xs text-gray-600 whitespace-nowrap">{barras}</td>
                                        <td className="px-3 py-2 font-mono text-xs text-gray-600 whitespace-nowrap">{alterno}</td>
                                        <td className="px-3 py-2 text-right font-semibold">{isNaN(cant) ? '—' : cant.toFixed(2)}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="text-gray-500">No hay items en esta transferencia.</p>
            )}

            <div className="mt-6 pt-4 border-t text-right">
                <p className="text-sm text-gray-500">Total Base: <span className="font-medium">${parseFloat(transferencia.base || 0).toFixed(2)}</span></p>
                <p className="text-sm text-gray-500">Total Venta: <span className="font-medium">${parseFloat(transferencia.venta || 0).toFixed(2)}</span></p>
            </div>
        </div>
    );
};


const TransferenciaList = ({ 
    sucursalActualId, 
    onRequireRefresh, 
    onEdit, 
    onViewDetails, 
    sucursales, 
    cargarTransferencias,
    // Props de estado recibidas
    transferencias,
    setTransferencias,
    estaCargando,
    setEstaCargando,
    error,
    setError,
    filtros,
    setFiltros,
    filtrosActivos,
    setFiltrosActivos,
    paginacion,
    setPaginacion,
    mostrarFiltros,
    setMostrarFiltros
}) => {
    // Eliminamos el useEffect de carga de datos ya que ahora está en el componente padre

    const handleFilterChange = (e) => {
        setFiltros(prev => ({ ...prev, [e.target.name]: e.target.value }));
    };

    const handleSearch = () => {
        setFiltrosActivos({...filtros});
        cargarTransferencias();
    };

    const handleDelete = async (t) => {
        if (!window.confirm(`¿Eliminar/anular la transferencia #${t.id}? Se reintegrará el stock al inventario y se quitará el espejo en central.`)) return;
        try {
            const res = await db.delTransferenciaDici({ id: t.id });
            if (res.data && res.data.estado) {
                setFiltrosActivos({ ...filtrosActivos }); // recargar la lista
            } else {
                window.alert((res.data && res.data.msj) || 'No se pudo eliminar la transferencia.');
            }
        } catch (e) {
            window.alert('Error al eliminar la transferencia.');
        }
    };

    if (estaCargando && transferencias.length === 0) return <div className="text-center p-10">Cargando...</div>;
    if (error) return <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 my-4"><p>{error}</p></div>;

    // Mapa id_sucursal -> sucursal completa (código + colores del label, vienen de sucursals/central).
    const sucById = {};
    (sucursales || []).forEach(s => { sucById[s.id] = s; });
    const datosSuc = (obj, id) => {
        const s = (obj && obj.codigo) ? obj : (sucById[id] || null);
        return {
            codigo: (s && s.codigo) || (id != null ? `ID: ${id}` : '—'),
            background: (s && s.background) || '#e5e7eb',
            color: (s && s.color) || '#374151',
        };
    };
    const badgeSuc = (obj, id) => {
        const d = datosSuc(obj, id);
        return (
            <span className="inline-block px-2 py-0.5 rounded text-xs font-bold whitespace-nowrap" style={{ backgroundColor: d.background, color: d.color }}>
                {d.codigo}
            </span>
        );
    };

    // Filtro cliente por origen/destino (además de lo que devuelve el backend),
    // para que el filtro siempre aplique sobre lo cargado.
    const transferenciasMostradas = (transferencias || []).filter(t => {
        if (filtrosActivos.id_origen && String(t.id_origen) !== String(filtrosActivos.id_origen)) return false;
        if (filtrosActivos.id_destino && String(t.id_destino) !== String(filtrosActivos.id_destino)) return false;
        return true;
    });

    return (
        <div className="rounded-lg">
            {/* Header con botón de filtros */}
            <div className="px-4 py-3 border-b border-gray-200 sm:px-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium text-gray-900">Transferencias</h2>
                    <div className="flex space-x-2">
                        <button
                            onClick={() => setMostrarFiltros(!mostrarFiltros)}
                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <i className={`fas fa-filter mr-2 ${mostrarFiltros ? 'text-indigo-600' : 'text-gray-400'}`}></i>
                            Filtros
                        </button>
                        <button
                            onClick={handleSearch}
                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <i className="fas fa-search mr-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            {/* Filtros colapsables */}
            <div className={`border-b border-gray-200 transition-all duration-200 ${mostrarFiltros ? 'block' : 'hidden'}`}>
                <div className="px-4 py-3 sm:px-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div>
                            <label htmlFor="estatus_string_filter" className="block text-xs font-medium text-gray-700">ID</label>
                            <input
                                name="q"
                                id="q_filter"
                                placeholder="Buscar por ID"
                                value={filtros.q}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            />
                        </div>
                        <div>
                            <label htmlFor="estatus_string_filter" className="block text-xs font-medium text-gray-700">Estado</label>
                            <select
                                name="estatus_string"
                                id="estatus_string_filter"
                                value={filtros.estatus_string}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            >
                                <option value="">Todos</option>
                                <option value="0">Pendiente</option>
                                <option value="1">Procesado</option>
                                <option value="2">Extraído</option>
                                <option value="3">En Revision</option>
                                <option value="4">Revisado</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="id_origen_filter" className="block text-xs font-medium text-gray-700">Origen</label>
                            <select
                                name="id_origen"
                                id="id_origen_filter"
                                value={filtros.id_origen || ''}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            >
                                <option value="">Todas</option>
                                {sucursales.map(s => <option key={s.id} value={s.id}>{s.codigo}</option>)}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="id_destino_filter" className="block text-xs font-medium text-gray-700">Destino</label>
                            <select
                                name="id_destino"
                                id="id_destino_filter"
                                value={filtros.id_destino}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            >
                                <option value="">Todas</option>
                                {sucursales.map(s => <option key={s.id} value={s.id}>{s.codigo}</option>)}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="limit_filter" className="block text-xs font-medium text-gray-700">Resultados</label>
                            <select
                                name="limit"
                                id="limit_filter"
                                value={filtros.limit}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            >
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        {/* <div>
                            <label htmlFor="fecha_desde_filter" className="block text-xs font-medium text-gray-700">Desde</label>
                            <input
                                type="date"
                                name="fecha_desde"
                                id="fecha_desde_filter"
                                value={filtros.fecha_desde}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            />
                        </div>
                        <div>
                            <label htmlFor="fecha_hasta_filter" className="block text-xs font-medium text-gray-700">Hasta</label>
                            <input
                                type="date"
                                name="fecha_hasta"
                                id="fecha_hasta_filter"
                                value={filtros.fecha_hasta}
                                onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                            />
                        </div> */}
                    </div>
                </div>
            </div>

            {/* Lista de Transferencias en Tarjetas */}
            <div className="p-0">
                {estaCargando && transferencias.length === 0 ? (
                    <div className="flex items-center justify-center p-8">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                        <span className="ml-3 text-gray-600">Cargando transferencias...</span>
                    </div>
                ) : error ? (
                    <div className="bg-red-50 border-l-4 border-red-500 p-4 m-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <i className="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-red-700">{error}</p>
                            </div>
                        </div>
                    </div>
                ) : transferenciasMostradas.length === 0 ? (
                    <div className="text-center py-12">
                        <i className="fas fa-box-open text-gray-400 text-4xl mb-3"></i>
                        <p className="text-gray-500">No se encontraron transferencias</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto border border-gray-200 rounded-lg">
                        <table className="min-w-full text-sm divide-y divide-gray-200">
                            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold">#</th>
                                    <th className="px-3 py-2 text-left font-semibold">Estado</th>
                                    <th className="px-3 py-2 text-left font-semibold">Fecha</th>
                                    <th className="px-3 py-2 text-left font-semibold">Origen</th>
                                    <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                    <th className="px-3 py-2 text-center font-semibold">Prod.</th>
                                    <th className="px-3 py-2 text-right font-semibold">Base</th>
                                    <th className="px-3 py-2 text-right font-semibold">Venta</th>
                                    <th className="px-3 py-2 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                                {transferenciasMostradas.map(t => (
                                    <tr key={t.id} className="hover:bg-gray-50">
                                        <td className="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">#{t.id}</td>
                                        <td className="px-3 py-2 whitespace-nowrap"><StatusBadge estadoNum={t.estado} /></td>
                                        <td className="px-3 py-2 text-gray-500 whitespace-nowrap">{format(new Date(t.created_at), 'dd/MM/yy HH:mm', { locale: es })}</td>
                                        <td className="px-3 py-2">{badgeSuc(t.origen, t.id_origen)}</td>
                                        <td className="px-3 py-2">{badgeSuc(t.destino, t.id_destino)}</td>
                                        <td className="px-3 py-2 text-center text-gray-600">{t.items?.length || 0}</td>
                                        <td className="px-3 py-2 text-right text-gray-900">${parseFloat(t.base || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-right text-gray-900">${parseFloat(t.venta || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 whitespace-nowrap text-center">
                                            <button
                                                onClick={() => onViewDetails(t)}
                                                className="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50"
                                                title="Ver detalles"
                                            >
                                                <i className="fas fa-eye"></i>
                                            </button>
                                            {t.estado === ESTADO_STRING_A_NUMERICO['PENDIENTE'] && (
                                                <button
                                                    onClick={() => onEdit(t)}
                                                    className="ml-1 text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                    title="Editar"
                                                >
                                                    <i className="fas fa-edit"></i>
                                                </button>
                                            )}
                                            {t.estado !== ESTADO_STRING_A_NUMERICO['PROCESADO'] && (
                                                <button
                                                    onClick={() => handleDelete(t)}
                                                    className="ml-1 text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50"
                                                    title="Eliminar / anular"
                                                >
                                                    <i className="fas fa-trash"></i>
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Paginación */}
            {paginacion.last_page > 1 && (
                <div className="px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div className="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                        <div className="text-sm text-gray-700">
                            Mostrando <span className="font-medium">{paginacion.from || 0}</span> a <span className="font-medium">{paginacion.to || 0}</span> de <span className="font-medium">{paginacion.total || 0}</span> resultados
                        </div>
                        <div className="flex space-x-1">
                            <button
                                onClick={() => handlePageChange(1)}
                                disabled={paginacion.current_page === 1 || estaCargando}
                                className="relative inline-flex items-center px-2 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                            >
                                <i className="fas fa-angle-double-left"></i>
                            </button>
                            <button
                                onClick={() => handlePageChange(paginacion.current_page - 1)}
                                disabled={paginacion.current_page === 1 || estaCargando}
                                className="relative inline-flex items-center px-2 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                            >
                                <i className="fas fa-angle-left"></i>
                            </button>
                            <span className="relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white">
                                Página {paginacion.current_page} de {paginacion.last_page}
                            </span>
                            <button
                                onClick={() => handlePageChange(paginacion.current_page + 1)}
                                disabled={paginacion.current_page === paginacion.last_page || estaCargando}
                                className="relative inline-flex items-center px-2 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                            >
                                <i className="fas fa-angle-right"></i>
                            </button>
                            <button
                                onClick={() => handlePageChange(paginacion.last_page)}
                                disabled={paginacion.current_page === paginacion.last_page || estaCargando}
                                className="relative inline-flex items-center px-2 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                            >
                                <i className="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const TransferenciasModule = ({ sucursalActualId }) => {
    const [vistaActual, setVistaActual] = useState('list'); // 'list', 'form', 'detail'
    const [transferenciaSeleccionada, setTransferenciaSeleccionada] = useState(null);
    const [refreshListKey, setRefreshListKey] = useState(0);
    const [sucursales, setSucursales] = useState([]);
    
    // Estados movidos desde TransferenciaList
    const [transferencias, setTransferencias] = useState([]);
    const [estaCargando, setEstaCargando] = useState(true);
    const [error, setError] = useState('');
    const [filtros, setFiltros] = useState({ q: '', estatus_string: '', id_origen: '', id_destino: '', limit: 10 });
    const [filtrosActivos, setFiltrosActivos] = useState({ q: '', estatus_string: '', id_origen: '', id_destino: '', limit: 10 });
    const [paginacion, setPaginacion] = useState({});
    const [mostrarFiltros, setMostrarFiltros] = useState(false);
    // Premontas = órdenes de redistribución 'Aprobada' de central donde ESTA sucursal es el origen.
    const [premontas, setPremontas] = useState([]);

    const cargarTransferencias = useCallback(async (filtros) => {
        try {
            const response = await db.reqMipedidos(filtros);
            return {
                transferencias: response.data.data || [],
                paginacion: response.data
            };
        } catch (err) {
            throw new Error('Error al cargar transferencias');
        }
    }, []);

    // useEffect movido desde TransferenciaList
    useEffect(() => { 
        const fetchData = async () => {
            setEstaCargando(true);
            setError('');
            try {
                const { transferencias: nuevasTransferencias, paginacion: nuevaPaginacion } = await cargarTransferencias(filtrosActivos);
                setTransferencias(nuevasTransferencias);
                setPaginacion(nuevaPaginacion);
            } catch (err) {
                setError('No se pudieron cargar las transferencias.');
                setTransferencias([]);
            } finally {
                setEstaCargando(false);
            }
        };
        fetchData();
    }, [cargarTransferencias, filtrosActivos, refreshListKey]);

    useEffect(() => {
        const cargarSucursales = async () => {
            try { 
                const res = await db.getSucursales();
                if (res.data.msj) setSucursales(res.data.msj);
            }
            catch (error) { console.error("Error cargando sucursales:", error); }
        };
        cargarSucursales();
    }, []);

    // Cargar premontas (órdenes de redistribución aprobadas para esta sucursal origen).
    useEffect(() => {
        db.getPremontadas({ limit: 50 })
            .then(res => setPremontas(res.data?.premontadas || []))
            .catch(() => setPremontas([]));
    }, [refreshListKey]);

    // Abre una premonta en el formulario como transferencia nueva, mapeando cada producto
    // al inventario local por código de barras, y llevando el id de la orden de redistribución.
    const abrirPremonta = async (prem) => {
        setEstaCargando(true);
        try {
            const items = [];
            const noEncontrados = [];
            for (const it of (prem.items || [])) {
                const barras = it.producto?.codigo_barras;
                let local = null;
                if (barras) {
                    try {
                        const r = await db.getinventario({ vendedor: null, num: 5, itemCero: true, qProductosMain: barras, orderColumn: 'descripcion', orderBy: 'asc' });
                        const arr = r.data || [];
                        local = arr.find(p => String(p.codigo_barras) === String(barras)) || null;
                    } catch (e) { /* ignore */ }
                }
                if (local) {
                    items.push({
                        id: it.id,
                        id_producto_insucursal: local.id,
                        cantidad: it.cantidad,
                        descuento: 0,
                        producto: {
                            id: local.id, precio_base: local.precio_base, precio: local.precio,
                            codigo_barras: local.codigo_barras, codigo_proveedor: local.codigo_proveedor, descripcion: local.descripcion,
                        },
                        created_at: null, updated_at: null,
                    });
                } else {
                    noEncontrados.push(it.producto?.descripcion || barras || ('#' + (it.producto_id_master || it.id)));
                }
            }
            if (noEncontrados.length) {
                alert('Estos productos de la orden no están en tu inventario local y no se cargaron:\n- ' + noEncontrados.join('\n- '));
            }
            if (!items.length) { alert('Ningún producto de la orden se pudo mapear a tu inventario local.'); return; }
            setTransferenciaSeleccionada({
                id: null,
                es_premontada: true,
                id_orden_distribucion: prem.id_orden_distribucion,
                id_destino: prem.sucursal_destino?.id || '',
                observaciones: '',
                items,
            });
            setVistaActual('form');
        } finally {
            setEstaCargando(false);
        }
    };

    const handleSaveTransfer = (transferenciaGuardada) => {
        console.log("Transferencia guardada:", transferenciaGuardada);
        setVistaActual('list');
        setTransferenciaSeleccionada(null);
        setRefreshListKey(prevKey => prevKey + 1);
    };

    const handleCancelForm = () => {
        setVistaActual('list');
        setTransferenciaSeleccionada(null);
    };

    const handleGoToCreate = () => {
        setTransferenciaSeleccionada(null);
        setVistaActual('form');
    };

    const handleEditTransfer = (transferencia) => {
        setTransferenciaSeleccionada(transferencia);
        setVistaActual('form');
    };

    const handleViewDetails = (transferencia) => {
        setTransferenciaSeleccionada(transferencia);
        setVistaActual('detail');
    };

    const idOrigenReal = sucursalActualId || ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER;

    return (
        <div className="mx-auto px-2 pt-1 pb-2 sm:px-4 md:px-6">
            <header className="mb-3 pb-2 border-b border-gray-200">
                <div className="flex flex-col sm:flex-row justify-between items-center">
                    <h3 className="text-lg sm:text-xl font-bold text-gray-800">Gestión de Transferencias</h3>
                    {vistaActual === 'list' && (<button onClick={handleGoToCreate} className="mt-3 sm:mt-0 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">+ Nueva Transferencia</button>)}
                    {(vistaActual === 'form' || vistaActual === 'detail') && (<button onClick={handleCancelForm} className="mt-3 sm:mt-0 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">&larr; Volver al Listado</button>)}
                </div>
            </header>
            <main>
                {vistaActual === 'list' && (
                    <>
                    {premontas.length > 0 && (
                        <div className="mb-3 border border-amber-300 bg-amber-50 rounded-lg p-3">
                            <h4 className="text-sm font-bold text-amber-800 mb-2"><i className="fas fa-inbox mr-1"></i>Órdenes de redistribución para despachar ({premontas.length})</h4>
                            <div className="space-y-2">
                                {premontas.map(prem => (
                                    <div key={prem.id_orden_distribucion} className="flex items-center justify-between gap-3 bg-white border border-amber-200 rounded-md px-3 py-2 flex-wrap">
                                        <div className="text-sm">
                                            <span className="font-bold text-amber-800">Redistribución #{prem.id_orden_distribucion}</span>
                                            <span className="text-gray-500 ml-2">&rarr; {prem.sucursal_destino?.nombre || prem.sucursal_destino?.codigo || ('Destino ' + (prem.sucursal_destino?.id ?? '—'))}</span>
                                            <span className="text-gray-400 ml-2">· {(prem.items || []).length} producto(s)</span>
                                        </div>
                                        <button onClick={() => abrirPremonta(prem)} className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-md">
                                            <i className="fas fa-truck-arrow-right mr-1"></i>Despachar
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    <TransferenciaList
                        sucursalActualId={idOrigenReal}
                        onRequireRefresh={refreshListKey} 
                        onEdit={handleEditTransfer} 
                        onViewDetails={handleViewDetails}
                        sucursales={sucursales}
                        cargarTransferencias={cargarTransferencias}
                        // Props de estado movidas
                        transferencias={transferencias}
                        setTransferencias={setTransferencias}
                        estaCargando={estaCargando}
                        setEstaCargando={setEstaCargando}
                        error={error}
                        setError={setError}
                        filtros={filtros}
                        setFiltros={setFiltros}
                        filtrosActivos={filtrosActivos}
                        setFiltrosActivos={setFiltrosActivos}
                        paginacion={paginacion}
                        setPaginacion={setPaginacion}
                        mostrarFiltros={mostrarFiltros}
                        setMostrarFiltros={setMostrarFiltros}
                    />
                    </>
                )}
                {vistaActual === 'form' && (
                    <TransferenciaForm 
                        onSave={handleSaveTransfer} 
                        onCancel={handleCancelForm} 
                        sucursalActualId={idOrigenReal} 
                        transferenciaToEdit={transferenciaSeleccionada}
                        sucursales={sucursales}
                        cargarTransferencias={cargarTransferencias}
                    />
                )}
                {vistaActual === 'detail' && (
                    <TransferenciaDetailView 
                        transferencia={transferenciaSeleccionada} 
                        onBack={handleCancelForm}
                        sucursales={sucursales}
                    />
                )}
            </main>
        </div>
    );
};

export default TransferenciasModule;

// ###################################################################################
// #                            FIN: COMPONENTES REACT                               #
// ###################################################################################

