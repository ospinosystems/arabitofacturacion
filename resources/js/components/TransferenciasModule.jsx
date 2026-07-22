import React, { useState, useEffect, useRef, useCallback } from 'react';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import db from '../database/database';

// Imprime una lista de picking en HOJA CARTA (para buscar físicamente los productos en almacén).
// filas: [{ barras, codigo_proveedor, descripcion, cantidad }]
const imprimirListaPicking = ({ titulo, subtitulo, filas }) => {
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    const rows = (filas || []).map((f, i) => `
        <tr>
          <td class="c">${i + 1}</td>
          <td class="mono">${esc(f.barras || '—')}</td>
          <td class="mono">${esc(f.codigo_proveedor || '—')}</td>
          <td>${esc(f.descripcion || '—')}</td>
          <td class="c b">${esc(f.cantidad)}</td>
          <td class="ub mono">${esc(f.ubicacion || '')}</td>
          <td class="chk"><span class="box"></span></td>
        </tr>`).join('');
    const totalUni = (filas || []).reduce((a, f) => a + (parseFloat(f.cantidad) || 0), 0);
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${esc(titulo)}</title>
      <style>
        @page { size: letter portrait; margin: 12mm; }
        * { font-family: Arial, sans-serif; }
        body { color:#111; font-size:12px; }
        h1 { font-size:16px; margin:0 0 2px; }
        .sub { color:#555; margin-bottom:10px; font-size:11px; }
        table { width:100%; border-collapse:collapse; }
        th,td { border:1px solid #cbd5e1; padding:5px 6px; text-align:left; font-size:11px; vertical-align:top; }
        th { background:#1e3a8a; color:#fff; }
        td.c { text-align:center; } td.b { font-weight:bold; } .mono { font-family:monospace; }
        td.ub { width:90px; } td.chk { width:30px; text-align:center; }
        .box { display:inline-block; width:15px; height:15px; border:2px solid #334155; }
        .pie { margin-top:14px; font-size:10px; color:#444; }
      </style></head><body>
      <h1>${esc(titulo)}</h1>
      <div class="sub">${esc(subtitulo || '')}</div>
      <table><thead><tr>
        <th>#</th><th>Cód. Barras</th><th>Cód. Prov.</th><th>Descripción</th>
        <th>Cant.</th><th>Ubicación</th><th>✔</th>
      </tr></thead><tbody>${rows}</tbody></table>
      <div class="pie">Total líneas: ${(filas || []).length} · Total unidades: ${totalUni}</div>
      <script>window.onload=function(){setTimeout(function(){window.print();},300);}</script>
      </body></html>`;
    const w = window.open('', '_blank');
    if (!w) { alert('Habilitá las ventanas emergentes para poder imprimir la lista.'); return; }
    w.document.write(html);
    w.document.close();
};

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

const SelectedProductItem = ({ item, onRemove, onQuantityChange, isEditable, index, totalItems, mostrarRevisado = false, onToggleRevisado }) => {
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

    // En modo picking: fila verde si ya fue revisado, ámbar si falta revisar.
    const rowCls = mostrarRevisado
        ? (item.revisado ? 'bg-emerald-50' : 'bg-amber-50/50 hover:bg-amber-50')
        : 'hover:bg-gray-50';

    return (
        <tr className={`border-b border-gray-100 ${rowCls}`}>
            {mostrarRevisado && (
                <td className="px-2 py-1 text-center">
                    <input
                        type="checkbox"
                        checked={!!item.revisado}
                        onChange={() => onToggleRevisado && onToggleRevisado(item.id_producto_insucursal)}
                        className="w-5 h-5 text-emerald-600 rounded cursor-pointer align-middle"
                        title={item.revisado ? 'Revisado — click para desmarcar' : 'Marcar como revisado'}
                    />
                </td>
            )}
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

const TransferenciaForm = ({ onSave, onCancel, sucursalActualId, transferenciaToEdit = null, sucursales, cargarTransferencias, modoBorrador = false, onGuardarBorrador }) => {
    // Una premonta (orden de redistribución traída de central) se arma como transferencia NUEVA,
    // no como edición de una transferencia local existente.
    const esPremonta = !!transferenciaToEdit?.es_premontada;
    // Modo borrador (Plan B): la orden vive como "en preparación" local; guardar NO descuenta
    // inventario. La salida (descuento + espejo en central) se hace aparte con "Dar salida".
    const esBorrador = !!modoBorrador;
    const esEdicion = !!transferenciaToEdit && !esPremonta && !esBorrador;
    const idSucursalOrigen = sucursalActualId || ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER;

    const [idSucursalDestinoSeleccionada, setIdSucursalDestinoSeleccionada] = useState(transferenciaToEdit?.id_destino || '');
    const [itemsTransferencia, setItemsTransferencia] = useState([]);
    const [error, setError] = useState('');
    const [estaCargando, setEstaCargando] = useState(false);
    const [mensajeExito, setMensajeExito] = useState('');
    const [observaciones, setObservaciones] = useState(transferenciaToEdit?.observaciones || '');
    const [mostrarObservaciones, setMostrarObservaciones] = useState(false);

    // Destino: bloqueado por defecto cuando ya trae un destino (edición o borrador de
    // redistribución); en un borrador nuevo/manual sin destino queda el buscador abierto.
    const [destinoBloqueado, setDestinoBloqueado] = useState((esEdicion || esBorrador) && !!transferenciaToEdit?.id_destino);
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
                // El producto puede venir anidado (premonta/edición) o plano (borrador de tdGetOrdenes).
                const prod = itemAPI.producto || {
                    id: itemAPI.id_producto_insucursal || itemAPI.id_producto,
                    precio_base: itemAPI.base || 0,
                    precio: itemAPI.venta || 0,
                    codigo_barras: itemAPI.codigo_barras,
                    codigo_proveedor: itemAPI.codigo_proveedor,
                    descripcion: itemAPI.descripcion,
                };
                const precioVenta = parseFloat(prod.precio) || 0;

                // Crear un objeto con la estructura correcta para el formulario
                return {
                    id: itemAPI.id, // ID del item de transferencia
                    id_producto: prod.id, // ID del producto global
                    id_pedido: transferenciaToEdit.id, // ID de la transferencia
                    id_producto_insucursal: prod.id, // ID del producto en inventario
                    cantidad: String(itemAPI.cantidad), // Convertir a string para consistencia
                    base: String(prod.precio_base ?? 0), // Precio base del producto
                    venta: String(prod.precio ?? 0), // Precio venta del producto
                    descuento: String(itemAPI.descuento || "0.00"),
                    monto: String((parseFloat(itemAPI.cantidad) * precioVenta).toFixed(2)),
                    ct_real: parseFloat(itemAPI.cantidad),
                    barras_real: prod.codigo_barras,
                    alterno_real: prod.codigo_proveedor,
                    descripcion_real: prod.descripcion,
                    vinculo_real: itemAPI.id_producto_insucursal || prod.id,
                    created_at: itemAPI.created_at,
                    updated_at: itemAPI.updated_at,
                    cantidad_original_stock_inventario: itemAPI?.cantidad || 0,
                    revisado: !!itemAPI.revisado, // marca de picking (Plan B)
                    ubicacion: itemAPI.ubicacion || (itemAPI.producto && itemAPI.producto.ubicacion) || null, // ubicación de almacén
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
            revisado: true, // recién agregado a mano = ya revisado físicamente
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
                    // Editar la cantidad marca el producto como revisado (picking Plan B).
                    revisado: nuevaCantidadStr === '' ? item.revisado : true,
                };
            })
        );
    };

    // Alterna la marca de "revisado" de un producto (picking Plan B).
    const toggleRevisado = (idProductoInsucursal) => {
        setItemsTransferencia(prev => prev.map(item =>
            item.id_producto_insucursal === idProductoInsucursal ? { ...item, revisado: !item.revisado } : item
        ));
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
                revisado: !!item.revisado,
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
        if ((esPremonta || esBorrador) && transferenciaToEdit?.id_orden_distribucion) {
            datosTransferencia.id_orden_distribucion = transferenciaToEdit.id_orden_distribucion;
        }

        // ── Modo borrador (Plan B): guardar la orden "en preparación" SIN descontar inventario ──
        if (esBorrador) {
            if (transferenciaToEdit?.id) datosTransferencia.id = transferenciaToEdit.id;
            setEstaCargando(true);
            try {
                const res = await onGuardarBorrador(datosTransferencia);
                if (res?.estado) {
                    setMensajeExito('Borrador guardado (no se descontó inventario).');
                    onSave(res);
                    setTimeout(() => setMensajeExito(''), 4000);
                } else {
                    throw new Error(res?.msj || 'No se pudo guardar el borrador.');
                }
            } catch (err) {
                setError(err.message || 'Error al guardar el borrador.');
            } finally {
                setEstaCargando(false);
            }
            return;
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
                    {esBorrador
                        ? (transferenciaToEdit?.id ? `Orden en preparación #${transferenciaToEdit.id}` : 'Nueva orden en preparación')
                        : (esEdicion ? `Editando Transferencia #${transferenciaToEdit.id}` : 'Nueva Transferencia')}
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
            {esBorrador && (
                <div className="bg-blue-50 border-l-4 border-blue-500 text-blue-800 px-3 py-2 text-sm flex items-start gap-2">
                    <i className="fas fa-info-circle mt-0.5"></i>
                    <span>
                        Estás <b>preparando</b> la orden{transferenciaToEdit?.id_orden_distribucion ? <> de la redistribución <b>#{transferenciaToEdit.id_orden_distribucion}</b></> : ''}.
                        Ajustá las cantidades a lo que vayas consiguiendo y quitá lo que no. Guardar <b>no descuenta inventario</b>;
                        el inventario sale recién cuando le das <b>“Dar salida”</b> desde el listado.
                    </span>
                </div>
            )}

            

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
                        <div className="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <h3 className="text-sm font-semibold text-gray-700">
                                Productos ({itemsTransferencia.length})
                                {esBorrador && (() => {
                                    const rev = itemsTransferencia.filter(i => i.revisado).length;
                                    const total = itemsTransferencia.length;
                                    const done = rev === total;
                                    return (
                                        <span className={`ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold ${done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                            <i className={`fas ${done ? 'fa-check-circle' : 'fa-clipboard-check'}`}></i>
                                            {rev}/{total} revisados{!done ? ` · faltan ${total - rev}` : ''}
                                        </span>
                                    );
                                })()}
                            </h3>
                            <button
                                type="button"
                                onClick={() => imprimirListaPicking({
                                    titulo: 'Lista de picking' + (transferenciaToEdit?.id ? ' · Orden #' + transferenciaToEdit.id : ''),
                                    subtitulo: (transferenciaToEdit?.id_orden_distribucion ? 'Redistribución #' + transferenciaToEdit.id_orden_distribucion + ' · ' : '') + 'Destino ' + (codigoDestino || '—') + ' · ' + itemsTransferencia.length + ' producto(s)',
                                    filas: itemsTransferencia.map(i => ({ barras: i.barras_real, codigo_proveedor: i.alterno_real, descripcion: i.descripcion_real, ubicacion: i.ubicacion, cantidad: i.cantidad })),
                                })}
                                className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                                title="Imprimir la lista (hoja carta) para buscar los productos en almacén"
                            >
                                <i className="fas fa-print"></i> Imprimir lista
                            </button>
                        </div>
                        <div className="border rounded-md max-h-[calc(100vh-340px)] overflow-y-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                                    <tr>
                                        {esBorrador && <th className="px-2 py-1 text-center font-semibold w-10" title="Revisado">✔</th>}
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
                                            mostrarRevisado={esBorrador}
                                            onToggleRevisado={toggleRevisado}
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
                    {estaCargando ? 'Guardando...' : (esBorrador ? (transferenciaToEdit?.id ? 'Guardar cambios' : 'Guardar borrador') : (esEdicion ? 'Actualizar Transferencia' : 'Crear Transferencia'))}
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

// Paso de resolución de conflictos al crear una orden desde una redistribución de central.
// Cada producto de la redistribución se coteja contra el inventario local por código de barras:
//   • no existe localmente → se excluye (no se puede enviar lo que no tenés en sistema)
//   • existe pero la cantidad no alcanza → se avisa y el usuario ajusta la cantidad
// El usuario ajusta/excluye y recién ahí se crea la orden en preparación (sin descontar).
const ResolverConflictosPremonta = ({ prem, filas, destinoBadge, onConfirmar, onCancelar, procesando }) => {
    const [rows, setRows] = useState([]);
    useEffect(() => {
        setRows((filas || []).map(f => ({
            ...f,
            // sugerir de entrada lo factible: mín(solicitado, stock). El usuario lo ajusta.
            cantidad: f.existe ? String(Math.min(parseFloat(f.cantidadSolicitada) || 0, parseFloat(f.stockLocal) || 0)) : '0',
            incluir: !!f.existe,
        })));
    }, [filas]);

    const setCantidad = (key, val) => {
        if (val !== '' && !/^\d*\.?\d{0,2}$/.test(val)) return;
        setRows(prev => prev.map(r => (r.key === key ? { ...r, cantidad: val } : r)));
    };
    const usarSolicitado = (key) => setRows(prev => prev.map(r => (r.key === key ? { ...r, cantidad: String(r.cantidadSolicitada) } : r)));
    const usarStock = (key) => setRows(prev => prev.map(r => (r.key === key ? { ...r, cantidad: String(r.stockLocal || 0) } : r)));
    const toggleIncluir = (key) => setRows(prev => prev.map(r => (r.key === key ? { ...r, incluir: !r.incluir } : r)));

    const incluidos = rows.filter(r => r.incluir && r.existe && parseFloat(r.cantidad) > 0);
    const noExisten = rows.filter(r => !r.existe).length;
    const excedenStock = rows.filter(r => r.existe && r.incluir && parseFloat(r.cantidad) > (parseFloat(r.stockLocal) || 0)).length;

    const confirmar = () => {
        const items = incluidos.map(r => ({ id_producto_insucursal: r.local.id, cantidad: parseFloat(r.cantidad).toFixed(2) }));
        onConfirmar(items);
    };

    return (
        <div className="bg-white rounded-lg shadow p-3 md:p-4">
            <div className="flex flex-wrap items-center justify-between gap-2 pb-2 mb-3 border-b border-gray-200">
                <div>
                    <h2 className="text-sm font-bold text-gray-800 uppercase tracking-wide">
                        <i className="fas fa-triangle-exclamation text-amber-500 mr-1"></i>
                        Resolver conflictos · Redistribución #{prem?.id_orden_distribucion}
                    </h2>
                    <p className="text-xs text-gray-500 mt-0.5">Ajustá cantidades y excluí lo que no tengas antes de crear la orden. Todavía no se descuenta nada.</p>
                </div>
                <div className="flex items-center gap-2 text-xs">
                    <button
                        type="button"
                        onClick={() => imprimirListaPicking({
                            titulo: 'Lista de picking · Redistribución #' + (prem?.id_orden_distribucion ?? ''),
                            subtitulo: 'Búsqueda física en almacén — ' + (rows.length) + ' producto(s)',
                            filas: rows.map(r => ({ barras: r.barras, codigo_proveedor: r.codigo_proveedor, descripcion: r.descripcion, ubicacion: r.ubicacion, cantidad: parseFloat(r.cantidadSolicitada || 0).toFixed(0) })),
                        })}
                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold"
                        title="Imprimir la lista de productos (hoja carta) para buscarlos en almacén"
                    >
                        <i className="fas fa-print"></i> Imprimir lista
                    </button>
                    <span className="text-gray-500">Destino:</span> {destinoBadge}
                </div>
            </div>

            {/* Resumen de conflictos */}
            <div className="flex flex-wrap gap-2 mb-3 text-xs">
                <span className="px-2 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold"><i className="fas fa-check mr-1"></i>{incluidos.length} a enviar</span>
                {excedenStock > 0 && <span className="px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 font-semibold"><i className="fas fa-exclamation mr-1"></i>{excedenStock} supera(n) stock</span>}
                {noExisten > 0 && <span className="px-2 py-1 rounded bg-red-50 text-red-700 border border-red-200 font-semibold"><i className="fas fa-ban mr-1"></i>{noExisten} no existe(n)</span>}
            </div>

            <div className="border rounded-md overflow-x-auto max-h-[55vh] overflow-y-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                        <tr>
                            <th className="px-2 py-1.5 text-left font-semibold">Producto</th>
                            <th className="px-2 py-1.5 text-left font-semibold">Cód. Barras</th>
                            <th className="px-2 py-1.5 text-center font-semibold">Solicitado</th>
                            <th className="px-2 py-1.5 text-center font-semibold">Stock local</th>
                            <th className="px-2 py-1.5 text-center font-semibold">A enviar</th>
                            <th className="px-2 py-1.5 text-center font-semibold">Estado</th>
                            <th className="px-2 py-1.5 text-center font-semibold w-10"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map(r => {
                            const cant = parseFloat(r.cantidad) || 0;
                            const stock = parseFloat(r.stockLocal) || 0;
                            const excede = r.existe && cant > stock;
                            const rowCls = !r.existe ? 'bg-red-50/60' : (!r.incluir ? 'bg-gray-50 opacity-60' : (excede ? 'bg-amber-50/60' : ''));
                            return (
                                <tr key={r.key} className={rowCls}>
                                    <td className="px-2 py-1.5 text-gray-800">{r.descripcion || '—'}</td>
                                    <td className="px-2 py-1.5 font-mono text-xs text-gray-600 whitespace-nowrap">{r.barras || '—'}</td>
                                    <td className="px-2 py-1.5 text-center text-gray-600">{parseFloat(r.cantidadSolicitada || 0).toFixed(2)}</td>
                                    <td className="px-2 py-1.5 text-center">{r.existe ? <span className={stock > 0 ? 'text-gray-700' : 'text-red-600 font-semibold'}>{stock.toFixed(2)}</span> : <span className="text-red-500">—</span>}</td>
                                    <td className="px-2 py-1.5 text-center">
                                        {r.existe ? (
                                            <div className="flex items-center justify-center gap-1">
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    value={r.cantidad}
                                                    disabled={!r.incluir}
                                                    onChange={(e) => setCantidad(r.key, e.target.value)}
                                                    className={`w-20 p-1 text-center border rounded ${excede ? 'border-amber-400 text-amber-700' : 'border-gray-300'} ${!r.incluir ? 'bg-gray-100' : ''}`}
                                                />
                                                {r.incluir && (
                                                    <div className="flex flex-col leading-none">
                                                        <button type="button" onClick={() => usarSolicitado(r.key)} title="Usar lo solicitado" className="text-[10px] text-indigo-600 hover:underline">sol.</button>
                                                        <button type="button" onClick={() => usarStock(r.key)} title="Usar el stock disponible" className="text-[10px] text-indigo-600 hover:underline">stock</button>
                                                    </div>
                                                )}
                                            </div>
                                        ) : <span className="text-gray-400">—</span>}
                                    </td>
                                    <td className="px-2 py-1.5 text-center whitespace-nowrap">
                                        {!r.existe ? (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-bold"><i className="fas fa-ban"></i>No existe</span>
                                        ) : !r.incluir ? (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs font-bold">Excluido</span>
                                        ) : excede ? (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-bold" title={`Solo hay ${stock} en stock`}><i className="fas fa-exclamation"></i>Supera stock</span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold"><i className="fas fa-check"></i>OK</span>
                                        )}
                                    </td>
                                    <td className="px-2 py-1.5 text-center">
                                        {r.existe && (
                                            <button
                                                type="button"
                                                onClick={() => toggleIncluir(r.key)}
                                                className={`p-1 rounded ${r.incluir ? 'text-red-500 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'}`}
                                                title={r.incluir ? 'Excluir de la orden' : 'Incluir en la orden'}
                                            >
                                                <i className={`fas ${r.incluir ? 'fa-trash' : 'fa-plus'}`}></i>
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {noExisten > 0 && (
                <div className="mt-3 border border-red-200 bg-red-50 rounded-md p-3">
                    <p className="text-xs font-bold text-red-700 mb-1">
                        <i className="fas fa-ban mr-1"></i>{noExisten} producto(s) de la redistribución NO existen en tu inventario local (quedan excluidos):
                    </p>
                    <ul className="text-xs text-red-700 space-y-0.5 max-h-40 overflow-y-auto">
                        {rows.filter(r => !r.existe).map(r => (
                            <li key={r.key} className="flex gap-2">
                                <span className="font-mono font-semibold whitespace-nowrap">{r.barras || r.codigo_proveedor || '—'}</span>
                                <span className="text-red-600">·</span>
                                <span className="truncate">{r.descripcion || 'sin descripción'}</span>
                                <span className="text-red-400 whitespace-nowrap">(pedía {parseFloat(r.cantidadSolicitada || 0).toFixed(0)})</span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-[11px] text-red-500 mt-1.5">Si deberían existir, creá su ficha en el inventario y volvé a "Revisar y crear".</p>
                </div>
            )}

            <div className="flex flex-col sm:flex-row justify-end gap-2 pt-3 mt-2 border-t border-gray-200">
                <button type="button" onClick={onCancelar} disabled={!!procesando} className="px-4 py-2 border rounded-md text-sm bg-white hover:bg-gray-50">Cancelar</button>
                <button
                    type="button"
                    onClick={confirmar}
                    disabled={!!procesando || incluidos.length === 0}
                    className="inline-flex justify-center items-center px-4 py-2 rounded-md text-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                >
                    {procesando ? <><i className="fas fa-spinner fa-spin mr-1"></i>Creando...</> : <><i className="fas fa-file-circle-plus mr-1"></i>Crear orden ({incluidos.length})</>}
                </button>
            </div>
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
    const [qPremonta, setQPremonta] = useState('');
    // Borradores = órdenes de despacho locales "en preparación" (estado 0), aún sin descontar.
    const [borradores, setBorradores] = useState([]);
    const [borradorEnEdicion, setBorradorEnEdicion] = useState(null);
    const [procesando, setProcesando] = useState(null); // id ocupado (crear/salida/eliminar)
    // Resolución de conflictos de una redistribución antes de crear la orden: { prem, filas }.
    const [conflictosPremonta, setConflictosPremonta] = useState(null);

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

    // Cargar borradores (órdenes locales en preparación, estado 0).
    const cargarBorradores = useCallback(async () => {
        try {
            const res = await db.tdGetOrdenes({ estado: 0, limit: 100 });
            setBorradores(res.data?.ordenes || []);
        } catch (e) { setBorradores([]); }
    }, []);

    // Cargar premontas (redistribuciones aprobadas para esta sucursal origen) + borradores.
    useEffect(() => {
        db.getPremontadas({ limit: 50 })
            .then(res => setPremontas(res.data?.premontadas || []))
            .catch(() => setPremontas([]));
        cargarBorradores();
    }, [refreshListKey, cargarBorradores]);

    // Coteja los productos de la redistribución contra el inventario local en UNA sola petición
    // (antes se hacía 1 request por producto → lentísimo con cientos de ítems). Arma las filas de
    // conflicto: existe/no existe, stock local, cantidad solicitada.
    const construirFilasConflicto = async (prem) => {
        const items = prem.items || [];
        // Junta todos los códigos (barras y proveedor) para resolverlos de un tiro.
        const codigos = [];
        items.forEach(it => {
            const s = it.producto || {};
            if (s.codigo_barras) codigos.push(String(s.codigo_barras));
            if (s.codigo_proveedor) codigos.push(String(s.codigo_proveedor));
        });

        // Índice local por barras y por proveedor (una consulta).
        const porBarras = {};
        const porProveedor = {};
        try {
            const r = await db.resolverInventarioPorCodigos({ codigos });
            (r.data?.productos || []).forEach(p => {
                if (p.codigo_barras) porBarras[String(p.codigo_barras)] = p;
                if (p.codigo_proveedor) porProveedor[String(p.codigo_proveedor)] = p;
            });
        } catch (e) { /* si falla, todo queda como "no existe" y el usuario lo ve */ }

        return items.map((it, idx) => {
            const snap = it.producto || {};
            const barras = snap.codigo_barras;
            const prov = snap.codigo_proveedor;
            const local = (barras && porBarras[String(barras)]) || (prov && porProveedor[String(prov)]) || null;
            return {
                key: 'f' + idx + '-' + (barras || prov || it.id || idx),
                descripcion: snap.descripcion || (local && local.descripcion) || null,
                barras: barras || (local && local.codigo_barras) || null,
                codigo_proveedor: prov || (local && local.codigo_proveedor) || null,
                ubicacion: local ? (local.ubicacion || null) : null,
                cantidadSolicitada: it.cantidad,
                local,
                existe: !!local,
                stockLocal: local ? local.cantidad : null,
            };
        });
    };

    // Imprime la lista de picking de una premonta resolviendo la ubicación de almacén (1 petición).
    const imprimirPremonta = async (prem) => {
        setProcesando('print-' + prem.id_orden_distribucion);
        try {
            const items = prem.items || [];
            const codigos = [];
            items.forEach(it => {
                const s = it.producto || {};
                if (s.codigo_barras) codigos.push(String(s.codigo_barras));
                if (s.codigo_proveedor) codigos.push(String(s.codigo_proveedor));
            });
            const porBarras = {}, porProveedor = {};
            try {
                const r = await db.resolverInventarioPorCodigos({ codigos });
                (r.data?.productos || []).forEach(p => {
                    if (p.codigo_barras) porBarras[String(p.codigo_barras)] = p;
                    if (p.codigo_proveedor) porProveedor[String(p.codigo_proveedor)] = p;
                });
            } catch (e) { /* sin ubicación si falla */ }
            const filas = items.map(it => {
                const s = it.producto || {};
                const local = (s.codigo_barras && porBarras[String(s.codigo_barras)]) || (s.codigo_proveedor && porProveedor[String(s.codigo_proveedor)]) || null;
                return { barras: s.codigo_barras, codigo_proveedor: s.codigo_proveedor, descripcion: s.descripcion, ubicacion: local ? (local.ubicacion || null) : null, cantidad: it.cantidad };
            });
            imprimirListaPicking({
                titulo: 'Lista de picking · Redistribución #' + prem.id_orden_distribucion,
                subtitulo: 'Búsqueda física en almacén · ' + items.length + ' producto(s)',
                filas,
            });
        } finally {
            setProcesando(null);
        }
    };

    // "Crear orden" desde una redistribución: primero abre el paso de resolución de conflictos
    // (ajustar cantidades / excluir inexistentes). La creación real ocurre en confirmarOrdenConflictos.
    const abrirResolucionPremonta = async (prem) => {
        setProcesando('prem-' + prem.id_orden_distribucion);
        try {
            const filas = await construirFilasConflicto(prem);
            setConflictosPremonta({ prem, filas });
            setVistaActual('conflictos');
        } catch (e) {
            alert('Error al cotejar la redistribución: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    // Crea la orden en preparación (estado 0, SIN descontar) con los ítems ya resueltos por el
    // usuario, ligada a la redistribución. La redistribución original de central queda intacta.
    const confirmarOrdenConflictos = async (items) => {
        const prem = conflictosPremonta?.prem;
        if (!prem) return;
        if (!items.length) { alert('No hay productos válidos para crear la orden.'); return; }
        setProcesando('crear-conflictos');
        try {
            const res = await db.tdGuardarOrden({
                id_destino: prem.sucursal_destino?.id || '',
                id_orden_distribucion: prem.id_orden_distribucion,
                observaciones: 'Redistribución #' + prem.id_orden_distribucion,
                items,
            });
            if (res.data?.estado) {
                await cargarBorradores();
                setPremontas(prev => prev.filter(p => p.id_orden_distribucion !== prem.id_orden_distribucion));
                setConflictosPremonta(null);
                setVistaActual('list');
            } else {
                alert(res.data?.msj || 'No se pudo crear la orden.');
            }
        } catch (e) {
            alert('Error al crear la orden: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    // Editar un borrador: abre el formulario en modo borrador con sus ítems.
    const editarBorrador = (borrador) => {
        setBorradorEnEdicion(borrador);
        setTransferenciaSeleccionada({
            id: borrador.id,
            id_orden_distribucion: borrador.id_orden_distribucion,
            id_destino: borrador.id_destino,
            observaciones: borrador.observacion || '',
            items: borrador.items || [],
        });
        setVistaActual('form');
    };

    // Guarda el borrador (crear/actualizar) sin descontar. Devuelve {estado, msj, orden}.
    const guardarBorrador = async (datos) => {
        const res = await db.tdGuardarOrden(datos);
        await cargarBorradores();
        return res.data;
    };

    // Dar salida a un borrador: descuenta inventario + crea el espejo en central.
    const darSalida = async (borrador) => {
        const nItems = (borrador.items || []).filter(i => parseFloat(i.cantidad) > 0).length;
        if (!window.confirm(`¿Dar salida a la orden #${borrador.id}? Se descontarán ${nItems} producto(s) del inventario y se enviará a central. Esta acción sí mueve inventario.`)) return;
        setProcesando('salida-' + borrador.id);
        try {
            const res = await db.tdDarSalidaSimple({ id_transferencia: borrador.id });
            if (res.data?.estado) {
                await cargarBorradores();
                setRefreshListKey(k => k + 1); // refrescar histórico central
                alert(res.data.msj || 'Salida dada.');
            } else {
                alert(res.data?.msj || 'No se pudo dar salida.');
            }
        } catch (e) {
            alert('Error al dar salida: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    // Eliminar un borrador (no tocó inventario).
    const eliminarBorrador = async (borrador) => {
        if (!window.confirm(`¿Eliminar la orden en preparación #${borrador.id}? No descontó inventario, solo se borra el borrador.`)) return;
        setProcesando('del-' + borrador.id);
        try {
            const res = await db.tdEliminarOrden({ id: borrador.id });
            if (res.data?.estado) {
                await cargarBorradores();
                setRefreshListKey(k => k + 1); // por si vuelve a aparecer la premonta
            } else {
                alert(res.data?.msj || 'No se pudo eliminar.');
            }
        } catch (e) {
            alert('Error al eliminar: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    const handleSaveTransfer = (transferenciaGuardada) => {
        setVistaActual('list');
        setTransferenciaSeleccionada(null);
        setBorradorEnEdicion(null);
        setRefreshListKey(prevKey => prevKey + 1);
    };

    const handleCancelForm = () => {
        setVistaActual('list');
        setTransferenciaSeleccionada(null);
        setBorradorEnEdicion(null);
        setConflictosPremonta(null);
    };

    const handleGoToCreate = () => {
        // Una transferencia nueva se arma como BORRADOR (orden en preparación): no descuenta
        // inventario hasta "Dar salida". Sentinel {id:null} activa el modo borrador con form vacío.
        setTransferenciaSeleccionada(null);
        setBorradorEnEdicion({ id: null });
        setVistaActual('form');
    };

    const handleEditTransfer = (transferencia) => {
        setTransferenciaSeleccionada(transferencia);
        setBorradorEnEdicion(null);
        setVistaActual('form');
    };

    const handleViewDetails = (transferencia) => {
        setTransferenciaSeleccionada(transferencia);
        setVistaActual('detail');
    };

    const idOrigenReal = sucursalActualId || ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER;

    // Resolver etiqueta de sucursal destino (badge con color de central).
    const sucById = {};
    (sucursales || []).forEach(s => { sucById[s.id] = s; });
    const badgeDestinoPorId = (id) => {
        const s = sucById[id];
        const codigo = (s && s.codigo) || (id != null ? ('ID ' + id) : '—');
        return (
            <span className="inline-block px-2 py-0.5 rounded text-xs font-bold whitespace-nowrap" style={{ backgroundColor: (s && s.background) || '#e5e7eb', color: (s && s.color) || '#374151' }}>
                {codigo}
            </span>
        );
    };

    // Redistribuciones que ya tienen un borrador local en preparación (para no crear duplicados:
    // central solo las excluye cuando ya existe el pedido, que nace recién en la salida).
    const odsConBorrador = new Set(
        borradores.map(b => b.id_orden_distribucion).filter(Boolean).map(Number)
    );

    // Premontas: sin borrador aún + filtradas por buscador (id de redistribución o destino).
    const premontasFiltradas = premontas.filter(p => {
        if (odsConBorrador.has(Number(p.id_orden_distribucion))) return false;
        const q = qPremonta.trim().toLowerCase();
        if (!q) return true;
        const destino = (p.sucursal_destino?.codigo || p.sucursal_destino?.nombre || '').toLowerCase();
        return String(p.id_orden_distribucion).includes(q) || destino.includes(q);
    });

    return (
        <div className="mx-auto px-2 pt-1 pb-2 sm:px-4 md:px-6">
            <header className="mb-3 pb-2 border-b border-gray-200">
                <div className="flex flex-col sm:flex-row justify-between items-center">
                    <h3 className="text-lg sm:text-xl font-bold text-gray-800">Gestión de Transferencias</h3>
                    {vistaActual === 'list' && (<button onClick={handleGoToCreate} className="mt-3 sm:mt-0 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">+ Nueva Transferencia</button>)}
                    {(vistaActual === 'form' || vistaActual === 'detail' || vistaActual === 'conflictos') && (<button onClick={handleCancelForm} className="mt-3 sm:mt-0 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">&larr; Volver al Listado</button>)}
                </div>
            </header>
            <main>
                {vistaActual === 'list' && (
                    <>
                    {/* ── Redistribuciones por despachar (premontas de central) ── */}
                    {(premontasFiltradas.length > 0 || (qPremonta && premontas.length > 0)) && (
                        <div className="mb-3 border border-amber-300 rounded-lg overflow-hidden">
                            <div className="flex items-center justify-between gap-2 bg-amber-50 px-3 py-2 flex-wrap">
                                <h4 className="text-sm font-bold text-amber-800"><i className="fas fa-inbox mr-1"></i>Redistribuciones por despachar ({premontasFiltradas.length})</h4>
                                <input
                                    type="text"
                                    value={qPremonta}
                                    onChange={(e) => setQPremonta(e.target.value)}
                                    placeholder="Filtrar por # o destino..."
                                    className="w-48 px-2 py-1 text-sm border border-amber-300 rounded focus:outline-none focus:ring-1 focus:ring-amber-400"
                                />
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-amber-100">
                                    <thead className="bg-amber-50/60 text-xs uppercase tracking-wide text-amber-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Origen</th>
                                            <th className="px-3 py-2 text-left font-semibold">Redistribución</th>
                                            <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                            <th className="px-3 py-2 text-center font-semibold">Productos</th>
                                            <th className="px-3 py-2 text-center font-semibold">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-100">
                                        {premontasFiltradas.length === 0 ? (
                                            <tr><td colSpan={5} className="px-3 py-4 text-center text-gray-400">Sin coincidencias</td></tr>
                                        ) : premontasFiltradas.map(prem => (
                                            <tr key={prem.id_orden_distribucion} className="hover:bg-amber-50/40">
                                                <td className="px-3 py-2">
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800"><i className="fas fa-random"></i>REDISTRIBUCIÓN</span>
                                                </td>
                                                <td className="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">#{prem.id_orden_distribucion}</td>
                                                <td className="px-3 py-2">{badgeDestinoPorId(prem.sucursal_destino?.id)}</td>
                                                <td className="px-3 py-2 text-center text-gray-600">{(prem.items || []).length}</td>
                                                <td className="px-3 py-2 text-center whitespace-nowrap">
                                                    <button
                                                        onClick={() => imprimirPremonta(prem)}
                                                        disabled={procesando === 'print-' + prem.id_orden_distribucion}
                                                        className="mr-1 px-2 py-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 text-xs font-semibold rounded-md"
                                                        title="Imprimir lista (hoja carta) con ubicaciones para buscar en almacén"
                                                    >
                                                        {procesando === 'print-' + prem.id_orden_distribucion ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-print"></i>}
                                                    </button>
                                                    <button
                                                        onClick={() => abrirResolucionPremonta(prem)}
                                                        disabled={procesando === 'prem-' + prem.id_orden_distribucion}
                                                        className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-semibold rounded-md"
                                                        title="Cotejar contra tu inventario, resolver conflictos y crear la orden editable"
                                                    >
                                                        {procesando === 'prem-' + prem.id_orden_distribucion
                                                            ? <><i className="fas fa-spinner fa-spin mr-1"></i>Cotejando...</>
                                                            : <><i className="fas fa-magnifying-glass mr-1"></i>Revisar y crear</>}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* ── Órdenes en preparación (borradores, sin descontar todavía) ── */}
                    {borradores.length > 0 && (
                        <div className="mb-3 border border-blue-200 rounded-lg overflow-hidden">
                            <div className="bg-blue-50 px-3 py-2">
                                <h4 className="text-sm font-bold text-blue-800"><i className="fas fa-pen-to-square mr-1"></i>Órdenes en preparación ({borradores.length})</h4>
                                <p className="text-xs text-blue-600">Ajustá cantidades a lo que consigas. El inventario sale recién al “Dar salida”.</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-blue-100">
                                    <thead className="bg-blue-50/60 text-xs uppercase tracking-wide text-blue-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Orden</th>
                                            <th className="px-3 py-2 text-left font-semibold">Origen</th>
                                            <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                            <th className="px-3 py-2 text-center font-semibold">Productos</th>
                                            <th className="px-3 py-2 text-center font-semibold">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-100">
                                        {borradores.map(b => {
                                            const nItems = (b.items || []).filter(i => parseFloat(i.cantidad) > 0).length;
                                            const totalItems = (b.items || []).length;
                                            const revItems = (b.items || []).filter(i => i.revisado).length;
                                            const revDone = totalItems > 0 && revItems === totalItems;
                                            const ocupado = procesando === 'salida-' + b.id || procesando === 'del-' + b.id;
                                            return (
                                                <tr key={b.id} className="hover:bg-blue-50/40">
                                                    <td className="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">#{b.id}</td>
                                                    <td className="px-3 py-2">
                                                        {b.id_orden_distribucion
                                                            ? <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800" title={'Redistribución #' + b.id_orden_distribucion}><i className="fas fa-random"></i>REDIST. #{b.id_orden_distribucion}</span>
                                                            : <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-600"><i className="fas fa-user"></i>MANUAL</span>}
                                                    </td>
                                                    <td className="px-3 py-2">{badgeDestinoPorId(b.id_destino)}</td>
                                                    <td className="px-3 py-2 text-center text-gray-600">
                                                        {nItems}
                                                        {totalItems > 0 && (
                                                            <span className={`ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold ${revDone ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`} title={`${revItems}/${totalItems} revisados`}>
                                                                <i className={`fas ${revDone ? 'fa-check' : 'fa-clipboard-check'}`}></i>{revItems}/{totalItems}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-center whitespace-nowrap">
                                                        <button
                                                            onClick={() => imprimirListaPicking({
                                                                titulo: 'Lista de picking · Orden #' + b.id,
                                                                subtitulo: (b.id_orden_distribucion ? 'Redistribución #' + b.id_orden_distribucion + ' · ' : '') + totalItems + ' producto(s)',
                                                                filas: (b.items || []).map(i => ({ barras: i.codigo_barras, codigo_proveedor: i.codigo_proveedor, descripcion: i.descripcion, ubicacion: i.ubicacion, cantidad: i.cantidad })),
                                                            })}
                                                            className="mr-1 px-2 py-1 text-xs font-semibold text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
                                                            title="Imprimir lista (hoja carta) para almacén"
                                                        >
                                                            <i className="fas fa-print"></i>
                                                        </button>
                                                        <button onClick={() => editarBorrador(b)} disabled={ocupado} className="px-2 py-1 text-xs font-semibold text-blue-700 border border-blue-300 rounded hover:bg-blue-50 disabled:opacity-50" title="Editar cantidades / ítems">
                                                            <i className="fas fa-edit mr-1"></i>Editar
                                                        </button>
                                                        <button onClick={() => darSalida(b)} disabled={ocupado || nItems === 0} className="ml-1 px-2 py-1 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded disabled:opacity-50" title="Descontar inventario y enviar a central">
                                                            {procesando === 'salida-' + b.id ? <><i className="fas fa-spinner fa-spin mr-1"></i>Saliendo...</> : <><i className="fas fa-truck-arrow-right mr-1"></i>Dar salida</>}
                                                        </button>
                                                        <button onClick={() => eliminarBorrador(b)} disabled={ocupado} className="ml-1 px-2 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 disabled:opacity-50" title="Eliminar borrador">
                                                            {procesando === 'del-' + b.id ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-trash"></i>}
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
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
                        modoBorrador={!!borradorEnEdicion}
                        onGuardarBorrador={guardarBorrador}
                    />
                )}
                {vistaActual === 'conflictos' && conflictosPremonta && (
                    <ResolverConflictosPremonta
                        prem={conflictosPremonta.prem}
                        filas={conflictosPremonta.filas}
                        destinoBadge={badgeDestinoPorId(conflictosPremonta.prem?.sucursal_destino?.id)}
                        onConfirmar={confirmarOrdenConflictos}
                        onCancelar={handleCancelForm}
                        procesando={procesando === 'crear-conflictos'}
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

