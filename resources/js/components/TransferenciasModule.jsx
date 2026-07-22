import React, { useState, useEffect, useRef, useCallback } from 'react';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import db from '../database/database';

// Imprime una lista de picking en HOJA CARTA (para buscar físicamente los productos en almacén).
// Acepta `grupos` = [{titulo, filas}] para dividir en varias sublistas (una por sección, con salto
// de página), o `filas` sueltas (una sola lista). filas: [{ barras, codigo_proveedor, descripcion, ubicacion, cantidad }]
const imprimirListaPicking = ({ titulo, subtitulo, filas, grupos, destino }) => {
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    const secciones = (grupos && grupos.length) ? grupos : [{ titulo: null, filas: filas || [] }];
    const destinoHtml = destino ? `<div class="destino"><span class="lbl">DESTINO</span> ${esc(destino)}</div>` : '';
    const thead = `<thead><tr>
        <th>#</th><th>Cód. Barras</th><th>Cód. Prov.</th><th>Descripción</th>
        <th>Cant.</th><th>Ubicación</th><th>✔</th>
      </tr></thead>`;
    const bloques = secciones.map((g, gi) => {
        const rows = (g.filas || []).map((f, i) => `
        <tr>
          <td class="c">${i + 1}</td>
          <td class="mono">${esc(f.barras || '—')}</td>
          <td class="mono">${esc(f.codigo_proveedor || '—')}</td>
          <td>${esc(f.descripcion || '—')}</td>
          <td class="c b">${esc(f.cantidad)}</td>
          <td class="ub mono">${esc(f.ubicacion || '')}</td>
          <td class="chk"><span class="box"></span></td>
        </tr>`).join('');
        const uni = (g.filas || []).reduce((a, f) => a + (parseFloat(f.cantidad) || 0), 0);
        // La sucursal destino se repite grande en cada sublista (cada una va en su hoja).
        return `<section class="${gi > 0 ? 'brk' : ''}">
            <div class="hoja">Hoja ${gi + 1} de ${secciones.length}</div>
            ${destinoHtml}
            ${g.titulo ? `<h2>${esc(g.titulo)}</h2>` : ''}
            <table>${thead}<tbody>${rows}</tbody></table>
            <div class="pie">Líneas: ${(g.filas || []).length} · Unidades: ${uni}</div>
          </section>`;
    }).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${esc(titulo)}</title>
      <style>
        @page { size: letter portrait; margin: 12mm; }
        * { font-family: Arial, sans-serif; }
        body { color:#111; font-size:12px; }
        h1 { font-size:16px; margin:0 0 2px; }
        h2 { font-size:13px; margin:0 0 6px; padding:4px 8px; background:#eef2ff; border-left:4px solid #1e3a8a; }
        .sub { color:#555; margin-bottom:8px; font-size:11px; }
        .hoja { text-align:right; font-size:11px; font-weight:700; color:#64748b; margin-bottom:2px; }
        .destino { font-size:30px; font-weight:800; text-align:center; letter-spacing:1px; color:#1e3a8a; border:3px solid #1e3a8a; border-radius:8px; padding:8px 6px; margin:4px 0 10px; text-transform:uppercase; }
        .destino .lbl { display:block; font-size:11px; font-weight:600; letter-spacing:2px; color:#64748b; }
        section.brk { page-break-before: always; }
        table { width:100%; border-collapse:collapse; }
        th,td { border:1px solid #cbd5e1; padding:5px 6px; text-align:left; font-size:11px; vertical-align:top; }
        th { background:#1e3a8a; color:#fff; }
        td.c { text-align:center; } td.b { font-weight:bold; } .mono { font-family:monospace; }
        td.ub { width:90px; } td.chk { width:30px; text-align:center; }
        .box { display:inline-block; width:15px; height:15px; border:2px solid #334155; }
        .pie { margin:8px 0 14px; font-size:10px; color:#444; }
      </style></head><body>
      <h1>${esc(titulo)}</h1>
      <div class="sub">${esc(subtitulo || '')}</div>
      ${bloques}
      <script>window.onload=function(){setTimeout(function(){window.print();},300);}</script>
      </body></html>`;
    const w = window.open('', '_blank');
    if (!w) { alert('Habilitá las ventanas emergentes para poder imprimir la lista.'); return; }
    w.document.write(html);
    w.document.close();
};

// Ordena filas por un campo y dirección.
const ordenarFilas = (filas, campo, dir) => {
    const arr = [...(filas || [])];
    if (campo === 'original') return arr;
    const key = { descripcion: 'descripcion', barras: 'barras', proveedor: 'codigo_proveedor', ubicacion: 'ubicacion' }[campo] || 'descripcion';
    arr.sort((a, b) => {
        const va = String(a[key] || '').toLowerCase();
        const vb = String(b[key] || '').toLowerCase();
        return dir === 'desc' ? vb.localeCompare(va) : va.localeCompare(vb);
    });
    return arr;
};

// Divide filas en sublistas: 'ninguno' (una), 'partes' (N grupos ~iguales), 'porLista' (chunks de N).
const dividirFilas = (filas, modo, valor) => {
    const arr = filas || [];
    if (modo === 'partes' && valor >= 2) {
        const n = Math.min(valor, arr.length || 1);
        const base = Math.ceil(arr.length / n);
        const grupos = [];
        for (let i = 0; i < arr.length; i += base) grupos.push(arr.slice(i, i + base));
        return grupos.length ? grupos : [arr];
    }
    if (modo === 'porLista' && valor >= 1) {
        const grupos = [];
        for (let i = 0; i < arr.length; i += valor) grupos.push(arr.slice(i, i + valor));
        return grupos.length ? grupos : [arr];
    }
    return [arr];
};

// Resuelve los productos LOCALES de una premonta en UNA sola petición. El match confiable es por
// ID (producto_id_master = el `id` que reusa el inventario local); si no hay id, cae a código
// (barras/proveedor, tolerante a espacios). Devuelve índices porId/porBarras/porProveedor.
const resolverLocalesDePremonta = async (items) => {
    const codigos = [], ids = [];
    (items || []).forEach(it => {
        const s = it.producto || {};
        if (s.codigo_barras) codigos.push(String(s.codigo_barras).trim());
        if (s.codigo_proveedor) codigos.push(String(s.codigo_proveedor).trim());
        if (it.producto_id_master) ids.push(it.producto_id_master);
    });
    const porId = {}, porBarras = {}, porProveedor = {};
    let error = null, productosCount = 0, conUbicacion = 0, debug = null;
    try {
        const r = await db.resolverInventarioPorCodigos({ ids, codigos });
        const prods = r.data?.productos || [];
        productosCount = prods.length;
        debug = r.data?.debug_ubicaciones;
        prods.forEach(p => {
            porId[String(p.id)] = p;
            if (p.ubicacion) conUbicacion++;
            if (p.codigo_barras) porBarras[String(p.codigo_barras).trim()] = p;
            if (p.codigo_proveedor) porProveedor[String(p.codigo_proveedor).trim()] = p;
        });
        if (r.data?.estado === false) error = r.data?.msj || 'El backend devolvió estado=false.';
    } catch (e) {
        error = (e.response ? ('HTTP ' + e.response.status) : (e.message || 'error de red'));
        console.error('resolverLocalesDePremonta:', e);
    }
    return { porId, porBarras, porProveedor, error, productosCount, conUbicacion, debug };
};

// Busca el producto local para un ítem de premonta. Considera el match por id (producto_id_master)
// y por código (barras/proveedor). Prefiere el que TENGA ubicación, para que un id que apunte a un
// producto sin ubicación no tape al match por código que sí la tiene.
const matchLocal = (it, snap, porId, porBarras, porProveedor) => {
    const s = snap || it.producto || {};
    const porIdMatch = it.producto_id_master ? porId[String(it.producto_id_master)] : null;
    const porCodMatch = (s.codigo_barras && porBarras[String(s.codigo_barras).trim()])
        || (s.codigo_proveedor && porProveedor[String(s.codigo_proveedor).trim()])
        || null;
    if (porIdMatch && porIdMatch.ubicacion) return porIdMatch;
    if (porCodMatch && porCodMatch.ubicacion) return porCodMatch;
    return porIdMatch || porCodMatch || null;
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

// Combobox compacto de sucursal con buscador (reemplaza los <select> planos en los filtros).
const SucursalCombo = ({ value, onChange, sucursales = [], placeholder = 'Todas' }) => {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const ref = useRef(null);
    const sel = (sucursales || []).find(s => String(s.id) === String(value));
    const filtered = (sucursales || []).filter(s => {
        const t = q.trim().toLowerCase();
        if (!t) return true;
        return (s.codigo || '').toLowerCase().includes(t) || (s.nombre || '').toLowerCase().includes(t);
    });
    useEffect(() => {
        const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);
    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => { setOpen(o => !o); setQ(''); }}
                className="w-full flex items-center justify-between gap-1 px-2 py-1.5 text-sm border border-gray-300 rounded bg-white text-left focus:outline-none focus:ring-1 focus:ring-indigo-400"
            >
                <span className={`truncate ${sel ? 'text-gray-800' : 'text-gray-400'}`}>{sel ? (sel.codigo + (sel.nombre ? ' · ' + sel.nombre : '')) : placeholder}</span>
                <span className="flex items-center gap-1 shrink-0">
                    {sel && <i className="fas fa-times text-gray-400 hover:text-red-500" onClick={(e) => { e.stopPropagation(); onChange(''); }}></i>}
                    <i className="fas fa-chevron-down text-gray-400 text-[10px]"></i>
                </span>
            </button>
            {open && (
                <div className="absolute z-30 mt-1 w-full min-w-[12rem] bg-white border border-gray-300 rounded-md shadow-lg">
                    <input
                        autoFocus
                        value={q}
                        onChange={e => setQ(e.target.value)}
                        placeholder="Buscar código o nombre…"
                        className="w-full px-2 py-1.5 text-sm border-b border-gray-200 focus:outline-none"
                    />
                    <ul className="max-h-56 overflow-y-auto">
                        <li onClick={() => { onChange(''); setOpen(false); }} className="px-2 py-1.5 text-sm text-gray-500 hover:bg-indigo-50 cursor-pointer">{placeholder}</li>
                        {filtered.map(s => (
                            <li
                                key={s.id}
                                onClick={() => { onChange(String(s.id)); setOpen(false); }}
                                className={`px-2 py-1.5 text-sm hover:bg-indigo-50 cursor-pointer ${String(s.id) === String(value) ? 'bg-indigo-50 font-semibold' : ''}`}
                            >
                                <b>{s.codigo}</b>{s.nombre ? <span className="text-gray-500 ml-1">{s.nombre}</span> : null}
                            </li>
                        ))}
                        {!filtered.length && <li className="px-2 py-1.5 text-sm text-gray-400">Sin coincidencias</li>}
                    </ul>
                </div>
            )}
        </div>
    );
};

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
                    <button
                        type="button"
                        onClick={() => onToggleRevisado && onToggleRevisado(item.id_producto_insucursal)}
                        className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold transition ${item.revisado ? 'bg-emerald-500 text-white' : 'bg-white border-2 border-amber-400 text-amber-600 hover:bg-amber-50'}`}
                        title={item.revisado ? 'Revisado — click para desmarcar' : 'Marcar como revisado'}
                    >
                        <i className={`fas ${item.revisado ? 'fa-check' : 'fa-circle'} ${item.revisado ? '' : 'text-[8px]'}`}></i>
                        {item.revisado ? 'Revisado' : 'Falta'}
                    </button>
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
                    onFocus={(e) => e.target.select()}
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

const TransferenciaForm = ({ onSave, onCancel, sucursalActualId, transferenciaToEdit = null, sucursales, cargarTransferencias, modoBorrador = false, onGuardarBorrador, onImprimir }) => {
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
    // Marca si el usuario tocó algo (para confirmar el descarte al cancelar). Los cambios viven
    // en estado local: cancelar (sin guardar) siempre los descarta y la orden vuelve a como estaba.
    const [dirty, setDirty] = useState(false);
    const handleCancelClick = () => {
        if (dirty && !window.confirm('¿Descartar los cambios? La orden volverá a como estaba (no se guardó nada).')) return;
        onCancel();
    };

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
        setDirty(true);
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
        setDirty(true);
        setItemsTransferencia(prev => prev.filter(item => item.id_producto_insucursal !== idProductoInsucursal));
    };

    const handleQuantityChange = (idProductoInsucursal, nuevaCantidadStr) => {
        setDirty(true);
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
        setDirty(true);
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
                    <label className="block text-xs font-medium text-gray-700 mb-1">
                        <i className="fas fa-plus-circle text-indigo-500 mr-1"></i>
                        {esBorrador ? 'Agregar producto (fuera de la redistribución original)' : 'Buscar y Agregar Productos:'}
                    </label>
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
                                onClick={() => onImprimir && onImprimir({
                                    titulo: 'Lista de picking' + (transferenciaToEdit?.id ? ' · Orden #' + transferenciaToEdit.id : ''),
                                    subtitulo: (transferenciaToEdit?.id_orden_distribucion ? 'Redistribución #' + transferenciaToEdit.id_orden_distribucion + ' · ' : '') + itemsTransferencia.length + ' producto(s)',
                                    destino: codigoDestino || '—',
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
                                        {esBorrador && <th className="px-2 py-1 text-center font-semibold w-24">Revisado</th>}
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
                <button type="button" onClick={handleCancelClick} disabled={estaCargando} className="w-full sm:w-auto px-4 py-2 border rounded-md shadow-sm text-sm bg-white hover:bg-gray-50 transition">Cancelar</button>
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
            {/* Header + filtros compactos, siempre visibles */}
            <div className="px-3 py-2 border-b border-gray-200 sm:px-4">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="w-28">
                        <label className="block text-[11px] font-medium text-gray-500">ID</label>
                        <input
                            name="q"
                            placeholder="ID"
                            value={filtros.q}
                            onChange={handleFilterChange}
                            className="mt-0.5 block w-full px-2 py-1.5 text-sm border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-400 rounded"
                        />
                    </div>
                    <div className="w-36">
                        <label className="block text-[11px] font-medium text-gray-500">Estado</label>
                        <select
                            name="estatus_string"
                            value={filtros.estatus_string}
                            onChange={handleFilterChange}
                            className="mt-0.5 block w-full px-2 py-1.5 text-sm border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-400 rounded"
                        >
                            <option value="">Todos</option>
                            <option value="0">Pendiente</option>
                            <option value="1">Procesado</option>
                            <option value="2">Extraído</option>
                            <option value="3">En Revision</option>
                            <option value="4">Revisado</option>
                        </select>
                    </div>
                    <div className="w-44">
                        <label className="block text-[11px] font-medium text-gray-500">Origen</label>
                        <div className="mt-0.5">
                            <SucursalCombo value={filtros.id_origen || ''} onChange={(val) => setFiltros(prev => ({ ...prev, id_origen: val }))} sucursales={sucursales} />
                        </div>
                    </div>
                    <div className="w-44">
                        <label className="block text-[11px] font-medium text-gray-500">Destino</label>
                        <div className="mt-0.5">
                            <SucursalCombo value={filtros.id_destino || ''} onChange={(val) => setFiltros(prev => ({ ...prev, id_destino: val }))} sucursales={sucursales} />
                        </div>
                    </div>
                    <div className="w-20">
                        <label className="block text-[11px] font-medium text-gray-500">Result.</label>
                        <select
                            name="limit"
                            value={filtros.limit}
                            onChange={handleFilterChange}
                            className="mt-0.5 block w-full px-2 py-1.5 text-sm border border-gray-300 focus:outline-none focus:ring-1 focus:ring-indigo-400 rounded"
                        >
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <button
                        onClick={handleSearch}
                        className="px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                        title="Buscar"
                    >
                        <i className="fas fa-search"></i>
                    </button>
                </div>
            </div>

            {/* (filtros extra ocultos, sin uso) */}
            <div className="hidden">
                <div className="px-4 py-3 sm:px-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
const ResolverConflictosPremonta = ({ prem, filas, destinoBadge, onConfirmar, onCancelar, onImprimir, procesando }) => {
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
                        onClick={() => onImprimir({
                            titulo: 'Lista de picking · Redistribución #' + (prem?.id_orden_distribucion ?? ''),
                            subtitulo: 'Búsqueda física en almacén — ' + (rows.length) + ' producto(s)',
                            destino: (prem?.sucursal_destino?.codigo || prem?.sucursal_destino?.nombre || ('Destino ' + (prem?.sucursal_destino?.id ?? '—'))),
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
                            <th className="px-2 py-1.5 text-left font-semibold">Ubicación</th>
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
                                    <td className="px-2 py-1.5 font-mono text-[11px] text-emerald-700 max-w-[10rem] truncate" title={r.ubicacion || ''}>{r.ubicacion || '—'}</td>
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

// Modal de opciones para imprimir la lista de picking: ordenar y dividir en varias sublistas.
const PrintPickingModal = ({ payload, onClose }) => {
    const [campo, setCampo] = useState('descripcion');
    const [dir, setDir] = useState('asc');
    const [modo, setModo] = useState('ninguno'); // 'ninguno' | 'partes' | 'porLista'
    const [nPartes, setNPartes] = useState(2);
    const [porLista, setPorLista] = useState(50);

    if (!payload) return null;
    const total = (payload.filas || []).length;
    const valor = modo === 'partes' ? parseInt(nPartes) || 2 : (modo === 'porLista' ? parseInt(porLista) || 1 : 0);
    const previewGrupos = dividirFilas(payload.filas, modo, valor);
    const nListas = previewGrupos.length;

    const imprimir = () => {
        const ordenadas = ordenarFilas(payload.filas, campo, dir);
        const partes = dividirFilas(ordenadas, modo, valor);
        const grupos = partes.map((filas, i) => ({
            titulo: partes.length > 1 ? `Lista ${i + 1} de ${partes.length} · ${filas.length} producto(s)` : null,
            filas,
        }));
        imprimirListaPicking({ titulo: payload.titulo, subtitulo: payload.subtitulo, destino: payload.destino, grupos });
        onClose();
    };

    const totalUnidades = (payload.filas || []).reduce((a, f) => a + (parseFloat(f.cantidad) || 0), 0);

    return (
        <div className="fixed inset-0 bg-gray-900/50 z-[60] flex items-start justify-center p-4 overflow-y-auto">
            <div className="relative mt-16 w-full max-w-md bg-white rounded-lg shadow-xl p-4">
                <h3 className="text-base font-bold text-gray-800 mb-2"><i className="fas fa-print mr-1 text-indigo-600"></i>Imprimir lista de picking</h3>
                <div className="flex items-center gap-2 mb-3 flex-wrap">
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold">
                        <i className="fas fa-boxes-stacked"></i>{total} producto(s)
                    </span>
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                        {totalUnidades} unidad(es)
                    </span>
                    {payload.destino && (
                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-bold">
                            <i className="fas fa-location-dot"></i>{payload.destino}
                        </span>
                    )}
                </div>

                {/* Orden */}
                <div className="mb-3">
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Ordenar por</label>
                    <div className="flex gap-2">
                        <select value={campo} onChange={e => setCampo(e.target.value)} className="flex-1 px-2 py-1.5 text-sm border border-gray-300 rounded">
                            <option value="descripcion">Descripción (A→Z)</option>
                            <option value="barras">Cód. Barras</option>
                            <option value="proveedor">Cód. Proveedor</option>
                            <option value="ubicacion">Ubicación</option>
                            <option value="original">Sin ordenar (como viene)</option>
                        </select>
                        <select value={dir} onChange={e => setDir(e.target.value)} disabled={campo === 'original'} className="w-28 px-2 py-1.5 text-sm border border-gray-300 rounded disabled:bg-gray-100">
                            <option value="asc">Ascendente</option>
                            <option value="desc">Descendente</option>
                        </select>
                    </div>
                </div>

                {/* División */}
                <div className="mb-3">
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Dividir la lista</label>
                    <div className="space-y-1.5">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="radio" name="modo" checked={modo === 'ninguno'} onChange={() => setModo('ninguno')} />
                            Una sola lista
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="radio" name="modo" checked={modo === 'partes'} onChange={() => setModo('partes')} />
                            Dividir en
                            <input type="number" min="2" max="10" value={nPartes} onChange={e => setNPartes(e.target.value)} onFocus={() => setModo('partes')} className="w-16 px-2 py-1 text-sm border border-gray-300 rounded text-center" />
                            listas iguales
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="radio" name="modo" checked={modo === 'porLista'} onChange={() => setModo('porLista')} />
                            <input type="number" min="1" value={porLista} onChange={e => setPorLista(e.target.value)} onFocus={() => setModo('porLista')} className="w-16 px-2 py-1 text-sm border border-gray-300 rounded text-center" />
                            productos por lista
                        </label>
                    </div>
                    {modo !== 'ninguno' && (
                        <p className="text-xs text-indigo-600 mt-1.5"><i className="fas fa-layer-group mr-1"></i>Se generarán <b>{nListas}</b> lista(s), cada una en su hoja.</p>
                    )}
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-200">
                    <button onClick={onClose} className="px-4 py-2 border rounded-md text-sm bg-white hover:bg-gray-50">Cancelar</button>
                    <button onClick={imprimir} className="px-4 py-2 rounded-md text-sm text-white bg-indigo-600 hover:bg-indigo-700"><i className="fas fa-print mr-1"></i>Imprimir</button>
                </div>
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
    // Filtro común para las órdenes (premontas / borradores / despachadas).
    // Fecha local de hoy (YYYY-MM-DD), sin desfase de zona horaria.
    const hoyLocal = () => {
        const d = new Date();
        return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    };
    // Formatea una fecha (ISO o MySQL) a dd/mm/yyyy tomando solo el día.
    const fmtFecha = (s) => {
        const d = String(s || '').slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return '—';
        const [y, m, dd] = d.split('-');
        return `${dd}/${m}/${y}`;
    };
    // Por defecto el filtro arranca en HOY (evita mostrar órdenes viejas). Limpiar quita las fechas.
    const [filtroOrdenes, setFiltroOrdenes] = useState({ q: '', destino: '', desde: hoyLocal(), hasta: '' });
    const setFiltro = (campo, val) => setFiltroOrdenes(prev => ({ ...prev, [campo]: val }));
    const limpiarFiltros = () => setFiltroOrdenes({ q: '', destino: '', desde: '', hasta: '' });
    // Tab activo del listado: 'enviadas' | 'redistribuciones' | 'preparacion' | 'despachadas'.
    const [tabActiva, setTabActiva] = useState('redistribuciones');
    // Borradores = órdenes de despacho locales "en preparación" (estado 0), aún sin descontar.
    const [borradores, setBorradores] = useState([]);
    const [borradorEnEdicion, setBorradorEnEdicion] = useState(null);
    const [procesando, setProcesando] = useState(null); // id ocupado (crear/salida/eliminar)
    // Resolución de conflictos de una redistribución antes de crear la orden: { prem, filas }.
    const [conflictosPremonta, setConflictosPremonta] = useState(null);
    // Modal de opciones de impresión de la lista de picking: { titulo, subtitulo, filas } | null.
    const [printModal, setPrintModal] = useState(null);
    const abrirPrintModal = (payload) => setPrintModal(payload);
    // Órdenes ya despachadas (estado 1) — para imprimir Guía de Despacho / Bultos.
    const [despachadas, setDespachadas] = useState([]);
    // Loading al cargar las órdenes (premontas + borradores + despachadas).
    const [cargandoOrdenes, setCargandoOrdenes] = useState(true);
    // Modal de impresión de bultos (transferencia): la orden + nº + url del iframe.
    const [bultosOrden, setBultosOrden] = useState(null);
    const [numBultosInput, setNumBultosInput] = useState('');
    const [bultosIframeUrl, setBultosIframeUrl] = useState(null);
    const refIframeBultos = useRef(null);

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

    // Cargar despachadas (estado 1). Además verifica contra central el Nº de Guía REAL (el id local
    // guardado puede estar stale). Anota central_pedido_id / central_existe / central_estado.
    const cargarDespachadas = useCallback(async () => {
        try {
            const res = await db.tdGetOrdenes({ estado: 1, limit: 100 });
            let ordenes = res.data?.ordenes || [];
            const ids = ordenes.map(o => o.id);
            if (ids.length) {
                // Verificación contra central: distingue "verificado y presente" / "verificado y
                // ausente (stale)" / "no se pudo verificar". Si la verificación NO está disponible
                // (central sin desplegar → estado:false/404/error), se cae al id_transferencia_central
                // guardado, que hoy es confiable (se setea al id real de central al enviar).
                let verificado = false;
                let mapa = {};
                try {
                    const v = await db.tdVerificarEspejos({ ids });
                    verificado = !!(v.data && v.data.estado);
                    mapa = v.data?.espejos || {};
                } catch (e) { verificado = false; }
                ordenes = ordenes.map(o => {
                    const c = mapa[String(o.id)];
                    if (verificado) {
                        // Central respondió: su mapa es la verdad (presente ⇒ id real; ausente ⇒ stale).
                        return { ...o, central_pedido_id: c ? c.id : (o.id_transferencia_central || null), central_existe: !!c, central_estado: c ? c.estado : null, central_verificado: true };
                    }
                    // No se pudo verificar: usar el id guardado como fallback (sin confirmar).
                    return { ...o, central_pedido_id: o.id_transferencia_central || null, central_existe: !!o.id_transferencia_central, central_estado: null, central_verificado: false };
                });
            }
            setDespachadas(ordenes);
        } catch (e) { setDespachadas([]); }
    }, []);

    // Cargar premontas (redistribuciones aprobadas para esta sucursal origen) + borradores.
    useEffect(() => {
        let vivo = true;
        setCargandoOrdenes(true);
        const cargarPremontas = db.getPremontadas({ limit: 50 })
            .then(res => { if (vivo) setPremontas(res.data?.premontadas || []); })
            .catch(() => { if (vivo) setPremontas([]); });
        Promise.all([cargarPremontas, cargarBorradores(), cargarDespachadas()])
            .finally(() => { if (vivo) setCargandoOrdenes(false); });
        return () => { vivo = false; };
    }, [refreshListKey, cargarBorradores, cargarDespachadas]);

    // Coteja los productos de la redistribución contra el inventario local en UNA sola petición
    // (antes se hacía 1 request por producto → lentísimo con cientos de ítems). Arma las filas de
    // conflicto: existe/no existe, stock local, cantidad solicitada.
    const construirFilasConflicto = async (prem) => {
        const items = prem.items || [];
        const { porId, porBarras, porProveedor } = await resolverLocalesDePremonta(items);

        return items.map((it, idx) => {
            const snap = it.producto || {};
            const barras = snap.codigo_barras;
            const prov = snap.codigo_proveedor;
            const local = matchLocal(it, snap, porId, porBarras, porProveedor);
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
            const res = await resolverLocalesDePremonta(items);
            const { porId, porBarras, porProveedor } = res;
            const filas = items.map(it => {
                const s = it.producto || {};
                const local = matchLocal(it, s, porId, porBarras, porProveedor);
                return { barras: s.codigo_barras, codigo_proveedor: s.codigo_proveedor, descripcion: s.descripcion, ubicacion: local ? (local.ubicacion || null) : null, cantidad: it.cantidad };
            });
            // Diagnóstico: si NO se resolvió ninguna ubicación, avisar por qué (en vez de imprimir en blanco).
            const conUbic = filas.filter(f => f.ubicacion).length;
            if (conUbic === 0) {
                const detalle = res.error
                    ? ('El endpoint de inventario falló: ' + res.error + '.\n\nProbablemente el backend del galpón está cacheado (opcache) o la ruta no está actualizada. Corré en el galpón:\n  php artisan optimize:clear')
                    : ('El endpoint respondió pero sin ubicaciones.\nProductos devueltos: ' + res.productosCount + ' · con ubicación: ' + res.conUbicacion + ' · debug_ubicaciones: ' + res.debug + '.\n\nSi debug_ubicaciones NO aparece (undefined) → el backend es viejo (opcache): php artisan optimize:clear');
                alert('No se pudo resolver la ubicación de ningún producto.\n\n' + detalle);
            }
            const d = prem.sucursal_destino || {};
            abrirPrintModal({
                titulo: 'Lista de picking · Redistribución #' + prem.id_orden_distribucion,
                subtitulo: 'Búsqueda física en almacén · ' + items.length + ' producto(s)',
                destino: d.codigo || d.nombre || ('Destino ' + (d.id ?? '—')),
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

    // Reenviar a central una despachada cuyo envío no se completó (sin Nº de Guía). Reusa
    // darSalidaSimple: para estado=1 sin central, solo reintenta el envío (NO re-descuenta).
    const reenviarACentral = async (d) => {
        setProcesando('reenv-' + d.id);
        try {
            const res = await db.tdDarSalidaSimple({ id_transferencia: d.id });
            if (res.data?.estado) {
                await cargarBorradores();
                setRefreshListKey(k => k + 1);
                alert(res.data.msj || 'Enviada a central.');
            } else {
                alert(res.data?.msj || 'No se pudo enviar a central.');
            }
        } catch (e) {
            alert('Error al enviar a central: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    // Reversar una orden despachada: reintegra inventario, quita el espejo en central y la vuelve
    // a "en preparación" (estado 0) para corregir y volver a dar salida.
    const reversarSalida = async (d) => {
        if (!window.confirm(`¿Reversar la orden #${d.id}?\n\nSe REINTEGRA el inventario, se quita el envío de central y la orden vuelve a "en preparación" para corregir. No se puede si el destino ya la recibió.`)) return;
        setProcesando('rev-' + d.id);
        try {
            const res = await db.tdReversarSalida({ id_transferencia: d.id });
            if (res.data?.estado) {
                await cargarBorradores();
                setRefreshListKey(k => k + 1); // refresca despachadas + histórico
                alert(res.data.msj || 'Salida reversada.');
            } else {
                alert(res.data?.msj || 'No se pudo reversar.');
            }
        } catch (e) {
            alert('Error al reversar: ' + (e.message || e));
        } finally {
            setProcesando(null);
        }
    };

    // Imprime la GUÍA DE DESPACHO de una orden despachada, con el MISMO formato/letras que la F4
    // de pagarMain.js (mismo HTML, estilos y textos). Destino = sucursal receptora; Origen = esta.
    const imprimirGuiaDespacho = (orden) => {
        const sucByIdLocal = {};
        (sucursales || []).forEach(s => { sucByIdLocal[s.id] = s; });
        // Nº de Guía = id del pedido en central (verificado, o guardado si no se pudo verificar).
        // Solo se bloquea si central CONFIRMÓ que no existe (o nunca se envió).
        const centralId = orden.central_existe ? orden.central_pedido_id : null;
        if (!centralId) {
            alert('Esta orden no tiene pedido en central (el envío no se completó o fue reversado). Tocá "Enviar a central" antes de imprimir la guía.');
            return;
        }
        const id = String(centralId).padStart(8, '0');
        const destino = sucByIdLocal[orden.id_destino] || {};
        const origenSuc = sucByIdLocal[sucursalActualId || ID_SUCURSAL_ACTUAL_ORIGEN_PLACEHOLDER] || {};
        const clienteRazon = destino.nombre || destino.codigo || ('Sucursal ' + (orden.id_destino ?? '—'));
        const clienteRif = destino.rif || destino.identificacion || '—';
        const clienteDir = (destino.direccion && String(destino.direccion).trim()) ? destino.direccion : '—';
        const origenNombre = origenSuc.nombre || origenSuc.codigo || '—';
        const items = orden.items || [];
        const sub = items.reduce((a, it) => a + (parseFloat(it.cantidad) || 0) * (parseFloat(it.precio) || 0), 0);
        const exento = 0, gravable = sub, iva = 0;
        const fmtP = (n) => Number(n).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const ventana = window.open('', '_blank');
        if (!ventana) { alert('Habilitá las ventanas emergentes para poder imprimir la guía.'); return; }
        ventana.document.write(`
            <!DOCTYPE html><html><head><title>Guía de Despacho N° ${id}</title>
            <style>body{font-family:sans-serif;padding:1rem;} table{border-collapse:collapse;} th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;} th{background:#f3f4f6;} .header{margin-bottom:1rem;} .totales{margin-left:auto;margin-top:1rem;} .totales table{margin-left:auto;} .totales td:last-child{text-align:right;} .firmas{margin-top:2rem;display:flex;gap:2rem;justify-content:center;width:100%;} .titulo-guia{text-align:left;font-weight:bold;margin-bottom:1rem;}</style>
            </head><body>
            <div class="titulo-guia">Guía de Despacho N°: ${id}</div>
            <div class="header">
                <div><strong>Cliente</strong></div>
                <div>Razón Social: ${clienteRazon}</div>
                <div>RIF: ${clienteRif}</div>
                <div>Dirección: ${clienteDir}</div>
                <div style="margin-top:0.5rem;"><strong>Origen:</strong> ${origenNombre}</div>

            </div>
            <table style="width:100%;"><thead><tr><th>#</th><th>Código</th><th>Cód. proveedor</th><th>Descripción</th><th style="text-align:right">Cantidad</th><th style="text-align:right">Precio</th></tr></thead><tbody>
            ${items.map((e, i) => {
                const cod = (e.codigo_barras ?? '—').toString().trim() || '—';
                const codProv = (e.codigo_proveedor ?? '—').toString().trim() || '—';
                const desc = (e.descripcion ?? '—').toString();
                const cant = Number(e.cantidad);
                const prec = e.precio ?? 0;
                const precStr = Number(prec).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                return `<tr><td>${i + 1}</td><td>${cod}</td><td>${codProv}</td><td>${desc}</td><td style="text-align:right">${cant % 1 === 0 ? cant : cant.toFixed(2)}</td><td style="text-align:right">${precStr}</td></tr>`;
            }).join('')}
            </tbody></table>
            <div class="totales">
                <table>
                <tr><td style="padding-right:1rem;">Subtotal</td><td style="text-align:right;">${fmtP(sub)}</td></tr>
                <tr><td style="padding-right:1rem;">Monto Exento</td><td style="text-align:right;">${fmtP(exento)}</td></tr>
                <tr><td style="padding-right:1rem;">Monto Gravable</td><td style="text-align:right;">${fmtP(gravable)}</td></tr>
                <tr><td style="padding-right:1rem;">IVA</td><td style="text-align:right;">${fmtP(iva)}</td></tr>
                <tr><td style="padding-right:1rem;font-weight:bold;">Monto Total</td><td style="text-align:right;font-weight:bold;">${fmtP(sub)}</td></tr>
                </table>
            </div>
            <div class="firmas">
                <div><div style="border-top:1px solid #333;padding-top:4px;width:140px;text-align:center;">Firma del Despachador</div></div>
                <div><div style="border-top:1px solid #333;padding-top:4px;width:140px;text-align:center;">Firma del Receptor</div></div>
            </div>
            </body></html>`);
        ventana.document.close();
        ventana.focus();
        setTimeout(() => { ventana.print(); ventana.close(); }, 300);
    };

    // Abre el modal de impresión de bultos para una orden despachada.
    const abrirBultosModal = (orden) => {
        setBultosOrden(orden);
        setNumBultosInput('');
        setBultosIframeUrl(null);
    };
    const generarVistaBultos = () => {
        const n = parseInt(numBultosInput, 10);
        if (!(n >= 1)) { alert('Indique un número de bultos válido (≥ 1).'); return; }
        const sucByIdLocal = {};
        (sucursales || []).forEach(s => { sucByIdLocal[s.id] = s; });
        const destinoCod = (sucByIdLocal[bultosOrden?.id_destino] || {}).codigo || ('SUC ' + (bultosOrden?.id_destino ?? ''));
        const base = typeof window !== 'undefined' ? window.location.origin : '';
        setBultosIframeUrl(`${base}/transferencia-despacho/print-bultos?id=${bultosOrden.id}&bultos=${n}&destino=${encodeURIComponent(destinoCod)}`);
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

    // ── Filtro común (texto / destino / rango de fechas) ──
    const hayFiltros = !!(filtroOrdenes.q || filtroOrdenes.destino || filtroOrdenes.desde || filtroOrdenes.hasta);
    const enRangoFecha = (fechaStr) => {
        if (!filtroOrdenes.desde && !filtroOrdenes.hasta) return true;
        // Comparar por DÍA (YYYY-MM-DD) como texto: robusto tanto a ISO ("2026-07-22T10:30:00Z")
        // como al formato MySQL con espacio ("2026-07-22 10:30:00"), sin depender de new Date().
        const dia = String(fechaStr || '').slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(dia)) return false; // sin fecha válida → fuera del rango
        if (filtroOrdenes.desde && dia < filtroOrdenes.desde) return false;
        if (filtroOrdenes.hasta && dia > filtroOrdenes.hasta) return false;
        return true;
    };
    const matchTexto = (campos) => {
        const q = filtroOrdenes.q.trim().toLowerCase();
        if (!q) return true;
        return campos.filter(v => v != null && v !== '').some(c => String(c).toLowerCase().includes(q));
    };
    const matchDestino = (idDestino) => !filtroOrdenes.destino || String(idDestino) === String(filtroOrdenes.destino);
    const codDestino = (id) => { const s = sucById[id]; return s ? [s.codigo, s.nombre] : []; };

    // Premontas: sin borrador aún + filtro común (por # redistribución, destino, fecha).
    // Premontas (redistribuciones por despachar): se filtran por fecha de emisión + destino + texto.
    const premontasFiltradas = premontas.filter(p => {
        if (odsConBorrador.has(Number(p.id_orden_distribucion))) return false;
        const destino = p.sucursal_destino || {};
        return matchDestino(destino.id)
            && enRangoFecha(p.fecha_emision || p.created_at)
            && matchTexto([p.id_orden_distribucion, destino.codigo, destino.nombre]);
    });

    // Borradores (en preparación): filtro por fecha de creación + destino + texto.
    const borradoresFiltrados = borradores.filter(b =>
        matchDestino(b.id_destino)
        && enRangoFecha(b.created_at)
        && matchTexto([b.id, b.id_orden_distribucion, ...codDestino(b.id_destino)])
    );

    // Despachadas: lista histórica → fecha (default hoy) + texto/destino.
    const despachadasFiltradas = despachadas.filter(d =>
        matchDestino(d.id_destino)
        && enRangoFecha(d.updated_at || d.created_at)
        && matchTexto([d.id, d.id_transferencia_central, d.id_orden_distribucion, ...codDestino(d.id_destino)])
    );

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
                    {/* ── Filtro común de órdenes (buscar / destino / fechas) + recargar ── */}
                    <div className="mb-3 bg-white border border-gray-200 rounded-lg p-3">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
                            <div className="lg:col-span-2">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                                <input
                                    type="text"
                                    value={filtroOrdenes.q}
                                    onChange={e => setFiltro('q', e.target.value)}
                                    placeholder="# orden, # redistribución, guía, destino…"
                                    className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Destino</label>
                                <SucursalCombo value={filtroOrdenes.destino} onChange={(val) => setFiltro('destino', val)} sucursales={sucursales} />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1" title="La fecha filtra todos los tabs (por defecto, hoy). Tocá Limpiar para ver todo.">Desde <i className="fas fa-circle-info text-gray-400"></i></label>
                                <input type="date" value={filtroOrdenes.desde} onChange={e => setFiltro('desde', e.target.value)} className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                                <input type="date" value={filtroOrdenes.hasta} onChange={e => setFiltro('hasta', e.target.value)} className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded" />
                            </div>
                        </div>
                        <div className="flex items-center gap-2 mt-2 flex-wrap">
                            <button onClick={() => setRefreshListKey(k => k + 1)} className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded" title="Recargar órdenes">
                                <i className="fas fa-rotate"></i>Recargar
                            </button>
                            {hayFiltros && (
                                <button onClick={limpiarFiltros} className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded hover:bg-gray-50" title="Limpiar filtros">
                                    <i className="fas fa-eraser"></i>Limpiar
                                </button>
                            )}
                            <span className="text-xs text-gray-400 ml-auto">
                                {premontasFiltradas.length} redistribución(es) · {borradoresFiltrados.length} en preparación · {despachadasFiltradas.length} despachada(s)
                            </span>
                        </div>
                    </div>

                    {/* ── Tabs del listado ── */}
                    <div className="mb-3 border-b border-gray-200">
                        <div className="flex flex-wrap gap-1">
                            {[
                                { key: 'enviadas', label: 'Órdenes Enviadas', icon: 'fa-paper-plane', count: null, badge: 'bg-indigo-100 text-indigo-700' },
                                { key: 'redistribuciones', label: 'Redistribuciones', icon: 'fa-random', count: premontasFiltradas.length, badge: 'bg-amber-100 text-amber-700' },
                                { key: 'preparacion', label: 'En preparación', icon: 'fa-pen-to-square', count: borradoresFiltrados.length, badge: 'bg-blue-100 text-blue-700' },
                                { key: 'despachadas', label: 'Despachadas', icon: 'fa-truck-fast', count: despachadasFiltradas.length, badge: 'bg-emerald-100 text-emerald-700' },
                            ].map(t => (
                                <button
                                    key={t.key}
                                    type="button"
                                    onClick={() => setTabActiva(t.key)}
                                    className={`px-3 py-2 text-sm font-semibold border-b-2 transition ${tabActiva === t.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                                >
                                    <i className={`fas ${t.icon} mr-1`}></i>{t.label}
                                    {t.count > 0 && <span className={`ml-1 px-1.5 py-0.5 text-xs font-bold rounded-full ${t.badge}`}>{t.count}</span>}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Loading mientras se cargan las órdenes (premontas + borradores + despachadas) */}
                    {cargandoOrdenes && tabActiva !== 'enviadas' && (
                        <div className="text-center py-16 text-gray-400">
                            <i className="fas fa-circle-notch fa-spin text-4xl mb-3 text-indigo-400"></i>
                            <p className="text-sm font-medium">Cargando órdenes…</p>
                        </div>
                    )}

                    {/* ── Redistribuciones por despachar (premontas de central) ── */}
                    {!cargandoOrdenes && tabActiva === 'redistribuciones' && (
                        premontasFiltradas.length === 0 ? (
                            <div className="text-center py-12 text-gray-400 border border-dashed border-gray-200 rounded-lg">
                                <i className="fas fa-inbox text-4xl mb-2"></i>
                                <p>No hay redistribuciones por despachar{hayFiltros ? ' con estos filtros' : ''}
                                    {hayFiltros && <span className="block text-gray-300 text-xs mt-1">(cambiá la fecha o tocá Limpiar para ver más)</span>}
                                </p>
                            </div>
                        ) : (
                        <div className="mb-3 border border-amber-300 rounded-lg overflow-hidden">
                            <div className="flex items-center justify-between gap-2 bg-amber-50 px-3 py-2 flex-wrap">
                                <h4 className="text-sm font-bold text-amber-800"><i className="fas fa-inbox mr-1"></i>Redistribuciones por despachar ({premontasFiltradas.length})</h4>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-amber-100">
                                    <thead className="bg-amber-50/60 text-xs uppercase tracking-wide text-amber-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Origen</th>
                                            <th className="px-3 py-2 text-left font-semibold">Redistribución</th>
                                            <th className="px-3 py-2 text-left font-semibold">Fecha</th>
                                            <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                            <th className="px-3 py-2 text-center font-semibold">Productos</th>
                                            <th className="px-3 py-2 text-center font-semibold">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-100">
                                        {premontasFiltradas.length === 0 ? (
                                            <tr><td colSpan={6} className="px-3 py-4 text-center text-gray-400">Sin coincidencias</td></tr>
                                        ) : premontasFiltradas.map(prem => (
                                            <tr key={prem.id_orden_distribucion} className="hover:bg-amber-50/40">
                                                <td className="px-3 py-2">
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800"><i className="fas fa-random"></i>REDISTRIBUCIÓN</span>
                                                </td>
                                                <td className="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">#{prem.id_orden_distribucion}</td>
                                                <td className="px-3 py-2 text-gray-600 whitespace-nowrap">{fmtFecha(prem.fecha_emision || prem.created_at)}</td>
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
                        )
                    )}

                    {/* ── Órdenes en preparación (borradores, sin descontar todavía) ── */}
                    {!cargandoOrdenes && tabActiva === 'preparacion' && (
                        borradoresFiltrados.length === 0 ? (
                            <div className="text-center py-12 text-gray-400 border border-dashed border-gray-200 rounded-lg">
                                <i className="fas fa-pen-to-square text-4xl mb-2"></i>
                                <p>No hay órdenes en preparación</p>
                            </div>
                        ) : (
                        <div className="mb-3 border border-blue-200 rounded-lg overflow-hidden">
                            <div className="bg-blue-50 px-3 py-2">
                                <h4 className="text-sm font-bold text-blue-800"><i className="fas fa-pen-to-square mr-1"></i>Órdenes en preparación ({borradoresFiltrados.length})</h4>
                                <p className="text-xs text-blue-600">Ajustá cantidades a lo que consigas. El inventario sale recién al “Dar salida”.</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-blue-100">
                                    <thead className="bg-blue-50/60 text-xs uppercase tracking-wide text-blue-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Orden</th>
                                            <th className="px-3 py-2 text-left font-semibold">Fecha</th>
                                            <th className="px-3 py-2 text-left font-semibold">Origen</th>
                                            <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                            <th className="px-3 py-2 text-center font-semibold">Productos</th>
                                            <th className="px-3 py-2 text-center font-semibold">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-100">
                                        {borradoresFiltrados.map(b => {
                                            const nItems = (b.items || []).filter(i => parseFloat(i.cantidad) > 0).length;
                                            const totalItems = (b.items || []).length;
                                            const revItems = (b.items || []).filter(i => i.revisado).length;
                                            const revDone = totalItems > 0 && revItems === totalItems;
                                            const ocupado = procesando === 'salida-' + b.id || procesando === 'del-' + b.id;
                                            return (
                                                <tr key={b.id} className="hover:bg-blue-50/40">
                                                    <td className="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">#{b.id}</td>
                                                    <td className="px-3 py-2 text-gray-600 whitespace-nowrap">{fmtFecha(b.created_at)}</td>
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
                                                            onClick={() => abrirPrintModal({
                                                                titulo: 'Lista de picking · Orden #' + b.id,
                                                                subtitulo: (b.id_orden_distribucion ? 'Redistribución #' + b.id_orden_distribucion + ' · ' : '') + totalItems + ' producto(s)',
                                                                destino: (sucById[b.id_destino]?.codigo || sucById[b.id_destino]?.nombre || ('Destino ' + (b.id_destino ?? '—'))),
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
                        )
                    )}
                    {/* ── Despachadas (estado 1, inventario ya descontado) — imprimir Guía / Bultos ── */}
                    {!cargandoOrdenes && tabActiva === 'despachadas' && (
                        despachadasFiltradas.length === 0 ? (
                            <div className="text-center py-12 text-gray-400 border border-dashed border-gray-200 rounded-lg">
                                <i className="fas fa-truck-fast text-4xl mb-2"></i>
                                <p>No hay despachadas {hayFiltros ? 'con estos filtros' : 'hoy'} <span className="text-gray-300">(cambiá la fecha o tocá Limpiar para ver más)</span></p>
                            </div>
                        ) : (
                        <div className="mb-3 border border-emerald-200 rounded-lg overflow-hidden">
                            <div className="bg-emerald-50 px-3 py-2">
                                <h4 className="text-sm font-bold text-emerald-800"><i className="fas fa-truck-fast mr-1"></i>Despachadas — listas para imprimir ({despachadasFiltradas.length})</h4>
                                <p className="text-xs text-emerald-600">Inventario ya descontado. Imprimí la Guía de Despacho y las etiquetas de bultos.</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-emerald-100">
                                    <thead className="bg-emerald-50/60 text-xs uppercase tracking-wide text-emerald-700">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Orden</th>
                                            <th className="px-3 py-2 text-left font-semibold">Fecha</th>
                                            <th className="px-3 py-2 text-left font-semibold">Nº Guía</th>
                                            <th className="px-3 py-2 text-left font-semibold">Destino</th>
                                            <th className="px-3 py-2 text-center font-semibold">Productos</th>
                                            <th className="px-3 py-2 text-center font-semibold">Imprimir</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-100">
                                        {despachadasFiltradas.map(d => {
                                            const nItems = (d.items || []).length;
                                            // Nº de Guía = id REAL del pedido en central. Confirmado = verificado y presente.
                                            // Si no se pudo verificar (central sin desplegar), se muestra el id guardado
                                            // (confiable) marcado "sin verificar". Si central confirmó que NO existe, "sin nº".
                                            const tieneGuia = !!d.central_pedido_id;
                                            const confirmado = d.central_existe && d.central_verificado;
                                            const guia = tieneGuia ? String(d.central_pedido_id).padStart(8, '0') : '—';
                                            return (
                                                <tr key={d.id} className="hover:bg-emerald-50/40">
                                                    <td className="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">#{d.id}</td>
                                                    <td className="px-3 py-2 text-gray-600 whitespace-nowrap">{fmtFecha(d.updated_at || d.created_at)}</td>
                                                    <td className="px-3 py-2 font-mono whitespace-nowrap">
                                                        {tieneGuia
                                                            ? (confirmado
                                                                ? <span className="text-gray-700" title="Nº de pedido en central (verificado)">{guia}</span>
                                                                : <span className="text-gray-600" title="Nº de pedido en central (guardado; no se pudo verificar contra central)">{guia} <i className="fas fa-clock text-amber-400 ml-0.5" title="sin verificar"></i></span>)
                                                            : <span className="text-amber-500" title="No hay pedido en central para esta orden (reenviá a central)">— sin nº —</span>}
                                                    </td>
                                                    <td className="px-3 py-2">{badgeDestinoPorId(d.id_destino)}</td>
                                                    <td className="px-3 py-2 text-center text-gray-600">{nItems}</td>
                                                    <td className="px-3 py-2 text-center whitespace-nowrap">
                                                        {!d.central_existe && (
                                                            <button onClick={() => reenviarACentral(d)} disabled={procesando === 'reenv-' + d.id} className="mr-1 px-2 py-1 text-xs font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded disabled:opacity-50" title="El envío a central no se completó. Reintentar (no re-descuenta inventario).">
                                                                {procesando === 'reenv-' + d.id ? <><i className="fas fa-spinner fa-spin mr-1"></i>Enviando...</> : <><i className="fas fa-paper-plane mr-1"></i>Enviar a central</>}
                                                            </button>
                                                        )}
                                                        <button onClick={() => imprimirGuiaDespacho(d)} className="px-2 py-1 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded" title="Imprimir Guía de Despacho">
                                                            <i className="fas fa-file-invoice mr-1"></i>Guía de Despacho
                                                        </button>
                                                        <button onClick={() => abrirBultosModal(d)} className="ml-1 px-2 py-1 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded" title="Imprimir etiquetas de bultos">
                                                            <i className="fas fa-box mr-1"></i>Bultos
                                                        </button>
                                                        <button onClick={() => reversarSalida(d)} disabled={procesando === 'rev-' + d.id} className="ml-1 px-2 py-1 text-xs font-semibold text-red-700 border border-red-300 rounded hover:bg-red-50 disabled:opacity-50" title="Reintegrar inventario y volver a preparación para corregir">
                                                            {procesando === 'rev-' + d.id ? <><i className="fas fa-spinner fa-spin mr-1"></i>Reversando...</> : <><i className="fas fa-rotate-left mr-1"></i>Reversar</>}
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        )
                    )}
                    {tabActiva === 'enviadas' && (
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
                    )}
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
                        onImprimir={abrirPrintModal}
                    />
                )}
                {vistaActual === 'conflictos' && conflictosPremonta && (
                    <ResolverConflictosPremonta
                        prem={conflictosPremonta.prem}
                        filas={conflictosPremonta.filas}
                        destinoBadge={badgeDestinoPorId(conflictosPremonta.prem?.sucursal_destino?.id)}
                        onConfirmar={confirmarOrdenConflictos}
                        onCancelar={handleCancelForm}
                        onImprimir={abrirPrintModal}
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
                <PrintPickingModal payload={printModal} onClose={() => setPrintModal(null)} />

                {/* Modal pantalla completa: Imprimir Bultos (mismo flujo/formato que pagarMain) */}
                {bultosOrden && (
                    <div className="fixed inset-0 z-[100] flex flex-col bg-white">
                        <div className="flex-shrink-0 flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-amber-50 border-b border-amber-200">
                            <h2 className="text-lg font-bold text-amber-900">
                                <i className="fa fa-box mr-2"></i>
                                Imprimir Bultos — Orden #{bultosOrden.id}
                            </h2>
                            <div className="flex items-center gap-3 flex-wrap">
                                <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    Número de bultos:
                                    <input
                                        type="number"
                                        min="1"
                                        className="w-20 px-2 py-1.5 border border-gray-300 rounded-md text-center font-mono"
                                        value={numBultosInput}
                                        onChange={(e) => setNumBultosInput(e.target.value)}
                                        placeholder="Ej: 5"
                                    />
                                </label>
                                <button
                                    type="button"
                                    onClick={generarVistaBultos}
                                    className="px-4 py-2 text-white bg-amber-600 border border-amber-700 rounded-lg hover:bg-amber-700"
                                >
                                    <i className="fa fa-refresh mr-2"></i>
                                    Generar vista
                                </button>
                                {bultosIframeUrl && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            try {
                                                if (refIframeBultos.current?.contentWindow) {
                                                    refIframeBultos.current.contentWindow.print();
                                                }
                                            } catch (err) {
                                                alert('Error al imprimir: ' + err.message);
                                            }
                                        }}
                                        className="px-4 py-2 text-white bg-indigo-600 border border-indigo-700 rounded-lg hover:bg-indigo-700"
                                    >
                                        <i className="fa fa-print mr-2"></i>
                                        Imprimir
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => { setBultosOrden(null); setBultosIframeUrl(null); setNumBultosInput(''); }}
                                    className="px-4 py-2 text-gray-700 bg-gray-200 border border-gray-400 rounded-lg hover:bg-gray-300"
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 min-h-0 flex flex-col bg-gray-100 p-4">
                            {bultosIframeUrl ? (
                                <iframe
                                    ref={refIframeBultos}
                                    src={bultosIframeUrl}
                                    title="Bultos"
                                    className="flex-1 w-full bg-white border border-gray-300 rounded"
                                />
                            ) : (
                                <div className="flex-1 flex items-center justify-center text-gray-400 text-sm">
                                    Indicá el número de bultos y tocá <b className="mx-1">Generar vista</b>.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </main>
        </div>
    );
};

export default TransferenciasModule;

// ###################################################################################
// #                            FIN: COMPONENTES REACT                               #
// ###################################################################################

