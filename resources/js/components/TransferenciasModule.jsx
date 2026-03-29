import React, { useState, useEffect, useRef, useCallback, useMemo, Fragment } from 'react';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import db from '../database/database';

const ESTADO_CONFIG = {
    0: { label: 'PREMONTADO EN ORIGEN', color: 'bg-gray-500 text-white' },
    1: { label: 'ENVIADO A DESTINO', color: 'bg-red-500 text-white' },
    2: { label: 'PROCESADO', color: 'bg-green-500 text-white' },
    3: { label: 'EN REVISIÓN', color: 'bg-yellow-400 text-black' },
    4: { label: 'REVISADO', color: 'bg-sky-500 text-white' },
    5: { label: 'RECIBIDO EN DESTINO', color: 'bg-purple-500 text-white' },
};

const StatusBadge = ({ estadoNum }) => {
    const cfg = ESTADO_CONFIG[estadoNum] || { label: 'DESCONOCIDO', color: 'bg-gray-400 text-black' };
    return (
        <span className={`px-3 py-1 text-xs font-semibold rounded-full leading-tight ${cfg.color}`}>
            {cfg.label}
        </span>
    );
};

/** Misma convención que printBultos (cliente): prefijo SUC para el texto de etiqueta. */
function etiquetaDestinoTransferenciaBultos(transferencia) {
    const d = transferencia?.destino;
    if (!d) return '';
    const nombre = String(d.nombre_sucursal || d.nombre || '').trim();
    const codigo = String(d.codigo || '').trim();
    if (nombre) {
        const u = nombre.toUpperCase();
        return u.startsWith('SUC ') ? nombre : `SUC ${nombre}`;
    }
    if (codigo) return `SUC ${codigo}`;
    return '';
}

/** Modal igual a pagarmain: iframe → reportes.bultos (vía printBultosTransferenciaPremontado). */
function BultosPremontadoModal({ open, onClose, transferencia }) {
    const [numBultosInput, setNumBultosInput] = useState('');
    const [bultosIframeUrl, setBultosIframeUrl] = useState(null);
    const refIframeBultos = useRef(null);

    useEffect(() => {
        if (!open) {
            setNumBultosInput('');
            setBultosIframeUrl(null);
        }
    }, [open]);

    if (!open || !transferencia?.id) return null;

    const generarVista = () => {
        const etiqueta = etiquetaDestinoTransferenciaBultos(transferencia);
        if (!etiqueta) {
            alert('No hay sucursal destino en el pedido para armar la etiqueta de bultos.');
            return;
        }
        const n = parseInt(numBultosInput, 10);
        if (!(n >= 1)) {
            alert('Indique un número de bultos válido (≥ 1).');
            return;
        }
        const base = typeof window !== 'undefined' ? window.location.origin : '';
        const params = new URLSearchParams();
        params.set('bultos', String(n));
        params.set('etiqueta_destino', etiqueta);
        params.set('id_central', String(transferencia.id));
        const codDest = String(transferencia.destino?.codigo || '').trim();
        if (codDest) params.set('codigo_destino', codDest);
        const fecha = transferencia.premontado_at || transferencia.created_at;
        if (fecha) params.set('fecha', fecha);
        setBultosIframeUrl(`${base}/printBultosTransferenciaPremontado?${params.toString()}`);
    };

    const etiquetaRaw = etiquetaDestinoTransferenciaBultos(transferencia);
    const cli = etiquetaRaw || '';
    const sucursalLabel = String(cli).includes('SUC ')
        ? (String(cli).split('SUC ')[1]?.trim() || cli)
        : cli;

    return (
        <div className="fixed inset-0 z-[100] flex flex-col bg-white">
            <div className="flex-shrink-0 flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-amber-50 border-b border-amber-200">
                <h2 className="text-lg font-bold text-amber-900 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span className="inline-flex items-center">
                        <i className="fa fa-box mr-2"></i>
                        Imprimir Bultos
                    </span>
                    <span className="text-base font-semibold text-amber-800/90">—</span>
                    <span className="inline-flex items-baseline gap-1">
                        <span className="text-sm font-medium text-amber-800">Pedido Central</span>
                        <span className="text-2xl font-extrabold text-amber-950 font-mono tracking-tight">#{transferencia.id}</span>
                    </span>
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
                        onClick={generarVista}
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
                                    refIframeBultos.current?.contentWindow?.print();
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
                        onClick={onClose}
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
                        title="Vista de bultos para imprimir"
                        className="w-full flex-1 min-h-0 border border-gray-300 rounded-lg bg-white"
                        onLoad={() => {
                            const doc = refIframeBultos.current?.contentDocument;
                            if (!doc || !sucursalLabel) return;
                            const label = String(sucursalLabel).toUpperCase();
                            try {
                                doc.querySelectorAll('.sucursal').forEach((el) => {
                                    const b = el.querySelector('b');
                                    if (b) b.textContent = label;
                                    else el.textContent = label;
                                });
                            } catch (e) {
                                console.warn('Bulto iframe: no se pudo actualizar sucursal', e);
                            }
                        }}
                    />
                ) : (
                    <div className="flex-1 flex items-center justify-center text-gray-500">
                        <div className="text-center">
                            <i className="fa fa-box text-4xl mb-3 opacity-50"></i>
                            <p className="font-medium">Indique el número de bultos y pulse «Generar vista»</p>
                            <p className="text-sm mt-1">Luego use «Imprimir» para imprimir como en pagarmain.</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

/** Payload para tickera: orden de transferencia (térmica). `printer`: índice 1-based según campo `tickera` de sucursal (API get-cajas-disponibles). */
function buildPayloadTicketOrdenTransferencia(transferencia, printerIndex = null) {
    const sucursalOrigen = transferencia.origen?.nombre_sucursal || transferencia.origen?.codigo
        || (transferencia.id_origen ? `ID: ${transferencia.id_origen}` : '');
    const sucursalDestino = transferencia.destino?.nombre_sucursal || transferencia.destino?.codigo
        || (transferencia.id_destino ? `ID: ${transferencia.id_destino}` : '');
    const items = (transferencia.items || []).map((item) => ({
        descripcion: item.producto?.descripcion || item.descripcion_real || '—',
        cantidad: item.cantidad,
        codigo_barras: item.producto?.codigo_barras || item.barras_real || '',
    }));
    const payload = {
        id_pedido: transferencia.id,
        sucursal_origen: sucursalOrigen,
        sucursal_destino: sucursalDestino,
        observaciones: transferencia.observaciones || '',
        items,
    };
    const n = Number(printerIndex);
    if (Number.isFinite(n) && n >= 1) {
        payload.printer = n;
    }
    return payload;
}

/**
 * Impresoras térmicas configuradas en la sucursal local (`sucursal.tickera`, valores separados por `;`).
 * Expuesto en backend como GET /api/get-cajas-disponibles.
 */
function useTickerasSucursalLocal() {
    const [cajas, setCajas] = useState([]);
    const [loading, setLoading] = useState(true);
    const [fetchError, setFetchError] = useState('');
    const [selectedId, setSelectedId] = useState(1);

    useEffect(() => {
        let cancelled = false;
        (async () => {
            setLoading(true);
            setFetchError('');
            try {
                const res = await fetch('/api/get-cajas-disponibles', { headers: { Accept: 'application/json' } });
                const data = await res.json().catch(() => ({}));
                if (cancelled) return;
                if (!res.ok || !data.success || !Array.isArray(data.cajas) || data.cajas.length === 0) {
                    setCajas([]);
                    setFetchError(data.message || 'No hay tiqueras configuradas en la sucursal (campo tickera).');
                    return;
                }
                setCajas(data.cajas);
                setSelectedId(data.cajas[0].id);
            } catch (e) {
                if (!cancelled) {
                    setCajas([]);
                    setFetchError('No se pudo obtener la lista de tiqueras.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();
        return () => { cancelled = true; };
    }, []);

    return { cajas, loading, fetchError, selectedId, setSelectedId };
}

/** Select de tiquera + botón imprimir ticket (orden transferencia), junto a «Imprimir bultos». */
function TicketOrdenTransferenciaToolbar({ transferencia }) {
    const { cajas, loading, fetchError, selectedId, setSelectedId } = useTickerasSucursalLocal();
    const [imprimiendoTicket, setImprimiendoTicket] = useState(false);

    const handleImprimirTicketOrden = async () => {
        if (!transferencia?.id || !cajas.length) return;
        setImprimiendoTicket(true);
        try {
            await imprimirTicketOrdenTransferenciaThermal(transferencia, selectedId);
        } catch (err) {
            alert(err?.message || 'Error al imprimir ticket');
        } finally {
            setImprimiendoTicket(false);
        }
    };

    const ticketDisabled = imprimiendoTicket || loading || cajas.length === 0;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                <span className="whitespace-nowrap">Tiquera</span>
                <select
                    className="min-w-[12rem] max-w-[20rem] px-2 py-1.5 text-sm border border-amber-400 rounded-md bg-white shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    value={loading || !cajas.length ? '' : String(selectedId)}
                    onChange={(e) => setSelectedId(Number(e.target.value))}
                    disabled={loading || cajas.length === 0}
                    title="Impresoras del campo tickera de la sucursal"
                >
                    {loading ? <option value="">Cargando…</option> : null}
                    {!loading && cajas.length === 0 ? (
                        <option value="">Sin tiqueras</option>
                    ) : null}
                    {!loading && cajas.map((c) => (
                        <option key={c.id} value={String(c.id)}>
                            {c.nombre}: {c.impresora}
                        </option>
                    ))}
                </select>
            </label>
            {fetchError ? (
                <span className="text-xs text-red-600 max-w-[14rem] leading-tight" title={fetchError}>
                    {fetchError}
                </span>
            ) : null}
            <button
                type="button"
                onClick={handleImprimirTicketOrden}
                disabled={ticketDisabled}
                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-950 bg-amber-50 border border-amber-400 rounded-md hover:bg-amber-100 transition disabled:opacity-50"
                title="Ticket térmico: orden de transferencia (origen/destino y productos)"
            >
                <i className="fa fa-lightbulb-o text-warning"></i>
                {imprimiendoTicket ? 'Imprimiendo…' : 'Ticket orden'}
            </button>
            <button
                type="button"
                onClick={() => imprimirGuiaDespachoTransferencia(transferencia)}
                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-800 bg-white border border-slate-400 rounded-md hover:bg-slate-50 transition"
                title="Guía de despacho: mismo formato que Imprimir en el modal F4 (Exportar lista) de cobro"
            >
                <i className="fa fa-file-text-o text-slate-600"></i>
                Guía despacho
            </button>
        </div>
    );
}

async function imprimirTicketOrdenTransferenciaThermal(transferencia, printerIndex = null) {
    const body = buildPayloadTicketOrdenTransferencia(transferencia, printerIndex);
    if (!body.items.length) {
        alert('No hay productos para imprimir en el ticket.');
        return;
    }
    const csrf = typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        : null;
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    const res = await fetch('/api/imprimir-ticket-orden-transferencia', {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
    });
    const result = await res.json().catch(() => ({}));
    if (!res.ok || !result.success) {
        alert(result.message || `Error al imprimir (${res.status})`);
        return;
    }
    alert(result.message || 'Orden de transferencia enviada a la impresora');
}

/** Evita romper el HTML al imprimir textos con & < > " */
function escapeHtmlGuiaDespacho(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Mismo documento que al pulsar «Imprimir» en el modal F4 de pagarMain: título GUIA DE DESPACHO,
 * bloque Cliente / Origen, tabla de ítems, totales y firmas.
 */
function imprimirGuiaDespachoTransferencia(transferencia) {
    if (!transferencia?.id) return;
    const items = transferencia.items || [];
    if (!items.length) {
        alert('No hay productos para la guía de despacho.');
        return;
    }
    const dest = transferencia.destino || {};
    const orig = transferencia.origen || {};
    const clienteRazon = [dest.nombre_sucursal, dest.nombre, dest.codigo].filter(Boolean).join(' · ') || '—';
    const clienteRif = dest.identificacion && String(dest.identificacion).trim() && dest.identificacion !== 'CF'
        ? String(dest.identificacion)
        : '—';
    const clienteDir = dest.direccion && String(dest.direccion).trim() ? String(dest.direccion) : '—';
    const origenNombre = orig.nombre_sucursal || orig.nombre || orig.codigo || '—';
    const id = transferencia.id;
    const created_at = transferencia.created_at || '';
    const sumMontos = items.reduce((s, e) => s + parseFloat(e.monto || 0), 0);
    const subFromVenta = parseFloat(transferencia.venta);
    const sub = (Number.isFinite(subFromVenta) && subFromVenta > 0) ? subFromVenta : sumMontos;
    let exento = parseFloat(transferencia.exento);
    if (!Number.isFinite(exento)) exento = 0;
    let gravable = parseFloat(transferencia.gravable);
    if (!Number.isFinite(gravable)) gravable = Math.max(0, sub - exento);
    let iva = parseFloat(transferencia.monto_iva ?? transferencia.ivas);
    if (!Number.isFinite(iva)) iva = 0;
    const fmtP = (n) => Number(n).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const rowsHtml = items.map((e, i) => {
        const cod = (e.producto?.codigo_barras ?? e.barras_real ?? '—').toString().trim() || '—';
        const codProv = (e.producto?.codigo_proveedor ?? e.alterno_real ?? '—').toString().trim() || '—';
        const desc = (e.producto?.descripcion ?? e.descripcion_real ?? '—').toString();
        const cant = Number(e.cantidad);
        const prec = e.producto?.precio ?? e.venta ?? e.precio_unitario ?? e.precio ?? 0;
        const precStr = Number(prec).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        const cantStr = cant % 1 === 0 ? String(cant) : cant.toFixed(2);
        return `<tr><td>${i + 1}</td><td>${escapeHtmlGuiaDespacho(cod)}</td><td>${escapeHtmlGuiaDespacho(codProv)}</td><td>${escapeHtmlGuiaDespacho(desc)}</td><td style="text-align:right">${cantStr}</td><td style="text-align:right">${precStr}</td></tr>`;
    }).join('');

    const ventana = window.open('', '_blank');
    if (!ventana) {
        alert('Permita ventanas emergentes para imprimir la guía de despacho.');
        return;
    }
    ventana.document.write(`
<!DOCTYPE html><html><head><title>Guía de despacho - Pedido Central ${id}</title>
<style>body{font-family:sans-serif;padding:1rem;} table{border-collapse:collapse;} th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;} th{background:#f3f4f6;} .header{margin-bottom:1rem;} .totales{margin-left:auto;margin-top:1rem;} .totales table{margin-left:auto;} .totales td:last-child{text-align:right;} .firmas{margin-top:2rem;display:flex;gap:2rem;justify-content:center;width:100%;} .titulo-guia{text-align:center;font-weight:bold;margin-bottom:1rem;}</style>
</head><body>
<div class="titulo-guia">GUIA DE DESPACHO</div>
<div class="header">
<div><strong>Cliente</strong></div>
<div>Razón Social: ${escapeHtmlGuiaDespacho(clienteRazon)}</div>
<div>RIF: ${escapeHtmlGuiaDespacho(clienteRif)}</div>
<div>Dirección: ${escapeHtmlGuiaDespacho(clienteDir)}</div>
<div style="margin-top:0.5rem;"><strong>Origen:</strong> ${escapeHtmlGuiaDespacho(origenNombre)}</div>
<div style="margin-top:0.25rem;font-size:12px;color:#666;">Pedido #${id}${created_at ? ' · ' + escapeHtmlGuiaDespacho(created_at) : ''}</div>
</div>
<table style="width:100%;"><thead><tr><th>#</th><th>Código</th><th>Cód. proveedor</th><th>Descripción</th><th style="text-align:right">Cantidad</th><th style="text-align:right">Precio</th></tr></thead><tbody>
${rowsHtml}
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
    setTimeout(() => {
        ventana.print();
        ventana.close();
    }, 300);
}

/** Ítem de pedido Central → payload settransferenciaDici (actualización) */
function mapItemParaActualizarCentral(item) {
    const p = item.producto || {};
    return {
        id: item.id,
        id_producto_insucursal: p.id ?? item.id_producto,
        cantidad: String(item.cantidad ?? ''),
        base: String(p.precio_base ?? item.base ?? ''),
        venta: String(p.precio ?? item.venta ?? ''),
        descripcion_real: p.descripcion || item.descripcion_real,
        barras_real: p.codigo_barras || item.barras_real,
    };
}

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
                num: 25,
                itemCero: false,
                qProductosMain: termino,
                orderColumn: "descripcion",
                orderBy: "asc",
            });
            setResultados(response.data || []);
            setMostrarResultados(true);
        } catch (error) { console.error("Error buscando productos:", error); setResultados([]); }
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
        const cantidadFinal = cantidadSeleccionada.trim() === '' ? '1.00' : cantidadSeleccionada;

        onProductSelect({
            ...productoSeleccionado,
            cantidadInicial: cantidadFinal
        });

        setTerminoBusqueda('');
        setProductoSeleccionado(null);
        setCantidadSeleccionada('');
        setMostrarModalCantidad(false);
        // El foco al buscador lo aplica useEffect al cerrar el modal (tras el commit), para no competir con el overlay
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
        if (mostrarModalCantidad) {
            const t = setTimeout(() => {
                cantidadInputRef.current?.focus();
                if (typeof cantidadInputRef.current?.select === 'function') {
                    cantidadInputRef.current.select();
                }
            }, 100);
            return () => clearTimeout(t);
        }
        const t = setTimeout(() => {
            inputRef.current?.focus({ preventScroll: true });
            if (typeof inputRef.current?.select === 'function') {
                inputRef.current.select();
            }
        }, 0);
        return () => clearTimeout(t);
    }, [mostrarModalCantidad]);

    return (
        <div className="relative w-full">
            <input
                ref={inputRef}
                type="text"
                className="form-input w-full p-2 border border-gray-300 rounded-md shadow-sm"
                placeholder={placeholder}
                value={terminoBusqueda}
                onChange={(e) => setTerminoBusqueda(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const q = terminoBusqueda.trim();
                    if (!q || resultados.length === 0) return;
                    const qLower = q.toLowerCase();
                    const exact = resultados.find((p) =>
                        String(p.codigo_barras || '').trim().toLowerCase() === qLower ||
                        String(p.codigo_proveedor || '').trim().toLowerCase() === qLower
                    );
                    if (exact) {
                        handleSelectProduct(exact);
                        return;
                    }
                    if (resultados.length === 1) {
                        handleSelectProduct(resultados[0]);
                    }
                }}
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

            {mostrarModalCantidad && productoSeleccionado && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-2 border shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <h3 className="text-lg font-medium leading-6 text-gray-900 mb-4">Seleccionar Cantidad</h3>
                            <div className="mt-2 px-7 py-3">
                                <div className="mb-4">
                                    <p className="text-sm text-gray-500 mb-1">Producto:</p>
                                    <p className="font-medium">{productoSeleccionado.descripcion}</p>
                                    <p className="text-sm text-gray-500">Stock disponible: {productoSeleccionado.cantidad}</p>
                                </div>
                                <div className="mb-4">
                                    <label htmlFor="cantidad" className="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                    <p className="text-xs text-gray-500 mb-1">Enter o Ctrl+Enter para agregar al premontado</p>
                                    <input
                                        ref={cantidadInputRef}
                                        type="number"
                                        id="cantidad"
                                        step="0.01"
                                        min="0.01"
                                        max={productoSeleccionado.cantidad}
                                        value={cantidadSeleccionada}
                                        onChange={handleCantidadChange}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && e.ctrlKey) {
                                                e.preventDefault();
                                                handleConfirmarCantidad();
                                                return;
                                            }
                                            handleKeyDown(e);
                                        }}
                                        className="form-input w-full p-4 text-2xl text-center border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder=""
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end space-x-3 px-4 py-3">
                                <button onClick={() => { setMostrarModalCantidad(false); setProductoSeleccionado(null); setCantidadSeleccionada(''); }}
                                    className="px-4 py-2 bg-gray-200 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-300">
                                    Cancelar
                                </button>
                                <button onClick={handleConfirmarCantidad}
                                    className="px-4 py-2 bg-indigo-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-indigo-700">
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
        if (valor === '') { onQuantityChange(item.id_producto_insucursal, ''); return; }
        if (/^\d*\.?\d{0,2}$/.test(valor)) { onQuantityChange(item.id_producto_insucursal, valor); }
    };

    return (
        <li className="flex flex-col sm:flex-row justify-between items-start sm:items-center p-3 border-b border-gray-200 space-y-2 sm:space-y-0 relative">
            <div className="absolute top-2 right-2 bg-gray-100 text-gray-600 text-xs font-medium px-2 py-1 rounded">
                {index + 1}/{totalItems}
            </div>
            <div className="flex-grow">
                <p className="font-semibold text-gray-800">{item.descripcion_real}</p>
                <p className="text-sm text-gray-500"><b>{item.barras_real}</b> | {item.alterno_real}</p>
            </div>
            <div className="flex items-center space-x-2">
                <input type="text" inputMode="decimal" value={item.cantidad} onChange={handleCantidadChange}
                    onBlur={(e) => {
                        const valor = e.target.value;
                        if (valor === '' || isNaN(parseFloat(valor)) || parseFloat(valor) <= 0) {
                            onQuantityChange(item.id_producto_insucursal, '1.00');
                        } else {
                            onQuantityChange(item.id_producto_insucursal, parseFloat(valor).toFixed(2));
                        }
                    }}
                    readOnly={!isEditable}
                    className={`form-input w-24 p-2 border border-gray-300 rounded-md text-center ${!isEditable ? 'bg-gray-100' : 'focus:ring-indigo-500 focus:border-indigo-500'}`}
                />
                {isEditable && (
                    <button onClick={() => onRemove(item.id_producto_insucursal)}
                        className="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded-md text-sm transition">
                        Eliminar
                    </button>
                )}
            </div>
        </li>
    );
};

// ==================== FORMULARIO DE CREACIÓN ====================
const TransferenciaForm = ({
    onSave,
    onCancel,
    sucursalActualId,
    transferenciaToEdit = null,
    sucursales,
    cargarTransferencias,
    onActualizarSucursales,
    cargandoSucursales = false,
}) => {
    const esEdicion = !!transferenciaToEdit;
    const [idSucursalDestinoSeleccionada, setIdSucursalDestinoSeleccionada] = useState(transferenciaToEdit?.id_destino || '');
    const [itemsTransferencia, setItemsTransferencia] = useState([]);
    const [error, setError] = useState('');
    const [estaCargando, setEstaCargando] = useState(false);
    const [mensajeExito, setMensajeExito] = useState('');
    const [observaciones, setObservaciones] = useState(transferenciaToEdit?.observaciones || '');
    const [mostrarObservaciones, setMostrarObservaciones] = useState(false);

    let nextDetalleId = useRef(200);

    useEffect(() => {
        if (transferenciaToEdit && transferenciaToEdit.items) {
            const itemsMapeados = transferenciaToEdit.items.map(itemAPI => ({
                id: itemAPI.id,
                id_producto: itemAPI.producto?.id || itemAPI.id_producto,
                id_pedido: transferenciaToEdit.id,
                id_producto_insucursal: itemAPI.producto?.id || itemAPI.id_producto,
                cantidad: String(itemAPI.cantidad),
                base: String(itemAPI.producto?.precio_base || itemAPI.base),
                venta: String(itemAPI.producto?.precio || itemAPI.venta),
                descuento: String(itemAPI.descuento || "0.00"),
                monto: String(itemAPI.monto),
                ct_real: parseFloat(itemAPI.cantidad),
                barras_real: itemAPI.producto?.codigo_barras || itemAPI.barras_real,
                alterno_real: itemAPI.producto?.codigo_proveedor || itemAPI.alterno_real,
                descripcion_real: itemAPI.producto?.descripcion || itemAPI.descripcion_real,
                vinculo_real: itemAPI.vinculo_real,
                cantidad_original_stock_inventario: itemAPI?.cantidad || 0,
                modificable: true,
            }));
            setItemsTransferencia(itemsMapeados);
            setObservaciones(transferenciaToEdit.observaciones || '');
        }
    }, [transferenciaToEdit]);

    const handleAddProduct = (productoDeInventario) => {
        if (itemsTransferencia.find(item => item.id_producto_insucursal === productoDeInventario.id)) {
            alert("Este producto ya ha sido agregado."); return;
        }
        const nuevoItem = {
            id: nextDetalleId.current++,
            id_producto: productoDeInventario.id,
            id_pedido: transferenciaToEdit?.id || null,
            cantidad: productoDeInventario.cantidadInicial || "1.00",
            base: String(productoDeInventario.precio_base),
            venta: String(productoDeInventario.precio),
            descuento: "0.00",
            monto: String((parseFloat(productoDeInventario.cantidadInicial || "1.00") * parseFloat(productoDeInventario.precio)).toFixed(2)),
            ct_real: parseFloat(productoDeInventario.cantidadInicial || "1.00"),
            barras_real: productoDeInventario.codigo_barras,
            alterno_real: productoDeInventario.codigo_proveedor,
            descripcion_real: productoDeInventario.descripcion,
            vinculo_real: productoDeInventario.id,
            id_producto_insucursal: productoDeInventario.id,
            cantidad_original_stock_inventario: productoDeInventario.cantidad,
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
                if (item.id_producto_insucursal === idProductoInsucursal) {
                    if (nuevaCantidadStr === '') return item;
                    const nuevaCantidadNum = parseFloat(nuevaCantidadStr);
                    if (isNaN(nuevaCantidadNum) || nuevaCantidadNum <= 0) return item;
                    const ventaNum = parseFloat(item.venta);
                    return {
                        ...item,
                        cantidad: nuevaCantidadStr,
                        monto: String((nuevaCantidadNum * ventaNum).toFixed(2)),
                        ct_real: nuevaCantidadNum
                    };
                }
                return item;
            })
        );
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(''); setMensajeExito('');

        if (!idSucursalDestinoSeleccionada) { setError('Debe seleccionar una sucursal de destino.'); return; }
        if (itemsTransferencia.length === 0) { setError('Debe agregar al menos un producto.'); return; }

        const datosTransferencia = {
            id_origen: sucursalActualId,
            id_destino: parseInt(idSucursalDestinoSeleccionada),
            observaciones: observaciones.trim(),
            actualizando: esEdicion,
            id: esEdicion ? transferenciaToEdit.id : undefined,
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

        setEstaCargando(true);
        try {
            const res = await db.settransferenciaDici(datosTransferencia);

            if (res.data.estado) {
                setMensajeExito(`Transferencia ${esEdicion ? 'actualizada' : 'creada'} exitosamente.`);
                if (!esEdicion) {
                    setIdSucursalDestinoSeleccionada('');
                    setItemsTransferencia([]);
                    setObservaciones('');
                }
                onSave(res.data);
                setTimeout(() => setMensajeExito(''), 5000);
            } else {
                throw new Error(res.data.msj || 'Error al procesar la transferencia');
            }
        } catch (err) {
            setError(err.response?.data?.msj || err.message || `Error al ${esEdicion ? 'actualizar' : 'crear'} transferencia.`);
        } finally {
            setEstaCargando(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6 p-1 md:p-6 rounded-lg">
            <h2 className="text-2xl font-semibold text-gray-800 mb-6">{esEdicion ? `Editando Transferencia #${transferenciaToEdit.id}` : 'Crear Nueva Transferencia'}</h2>
            {error && <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><p>{error}</p></div>}
            {mensajeExito && <div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4"><p>{mensajeExito}</p></div>}

            <div className="max-w-2xl">
                <label htmlFor="id_destino" className="block text-sm font-medium text-gray-700">Sucursal destino</label>
                <div className="mt-1 flex gap-2 items-stretch">
                    <select
                        id="id_destino"
                        value={idSucursalDestinoSeleccionada}
                        onChange={(e) => setIdSucursalDestinoSeleccionada(e.target.value)}
                        required
                        className="flex-1 min-w-0 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="">-- Seleccione destino --</option>
                        {sucursales.map(s => <option key={s.id} value={s.id}>{s.codigo}</option>)}
                    </select>
                    {typeof onActualizarSucursales === 'function' && (
                        <button
                            type="button"
                            onClick={() => onActualizarSucursales()}
                            disabled={cargandoSucursales}
                            title="Actualizar listado de sucursales desde Central"
                            className="shrink-0 px-3 py-2 rounded-md border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100 disabled:opacity-50 flex items-center justify-center"
                        >
                            <i className={`fas fa-sync-alt ${cargandoSucursales ? 'fa-spin' : ''}`} aria-hidden="true"></i>
                            <span className="sr-only">Actualizar sucursales</span>
                        </button>
                    )}
                </div>
            </div>

            <div className="relative">
                <div className="sticky top-0 z-10 bg-white pb-4 border-b border-gray-200">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Buscar y Agregar Productos:</label>
                    <ProductSearchInput onProductSelect={handleAddProduct} sucursalIdOrigen={sucursalActualId} />
                </div>

                {itemsTransferencia.length > 0 && (
                    <div className="mt-6">
                        <h3 className="text-lg font-medium text-gray-800 mb-2">
                            Productos en Transferencia ({itemsTransferencia.length} items):
                        </h3>
                        <ul className="border rounded-md divide-y max-h-[calc(100vh-400px)] overflow-y-auto">
                            {itemsTransferencia.map((item, index) => (
                                <SelectedProductItem key={item.id_producto_insucursal} item={item}
                                    onRemove={handleRemoveProduct} onQuantityChange={handleQuantityChange}
                                    isEditable={true} index={index} totalItems={itemsTransferencia.length} />
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            <div>
                <button type="button" onClick={() => setMostrarObservaciones(!mostrarObservaciones)}
                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i className={`fas fa-comment-alt mr-2 ${mostrarObservaciones ? 'text-indigo-600' : 'text-gray-400'}`}></i>
                    {mostrarObservaciones ? 'Ocultar Observaciones' : 'Agregar Observaciones'}
                </button>
            </div>
            {mostrarObservaciones && (
                <div className="mt-4">
                    <label htmlFor="observaciones" className="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea id="observaciones" value={observaciones} onChange={(e) => setObservaciones(e.target.value)}
                        rows="3" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Ingrese observaciones adicionales..." />
                </div>
            )}

            <div className="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-4">
                <button type="button" onClick={onCancel} disabled={estaCargando} className="w-full sm:w-auto px-6 py-2.5 border rounded-md shadow-sm text-sm bg-white hover:bg-gray-50 transition">Cancelar</button>
                <button type="submit" disabled={estaCargando || itemsTransferencia.length === 0} className="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 border-transparent rounded-md shadow-sm text-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition">
                    {estaCargando ? 'Guardando...' : (esEdicion ? 'Actualizar Transferencia' : 'Crear Transferencia')}
                </button>
            </div>
        </form>
    );
};

// ==================== VISTA DE CONFIRMACIÓN DE ENVÍO (ESCANEO) ====================
const codigoCoincideItem = (item, raw) => {
    const t = String(raw || '').trim().toLowerCase();
    if (!t) return false;
    const barras = String(item.producto?.codigo_barras ?? item.barras_real ?? '').trim().toLowerCase();
    const prov = String(item.producto?.codigo_proveedor ?? item.alterno_real ?? '').trim().toLowerCase();
    return (barras && barras === t) || (prov && prov === t);
};

const ConfirmarEnvioView = ({ transferencia, onBack, onConfirmed, recargarTransferenciaPorId }) => {
    const [itemsConfirmados, setItemsConfirmados] = useState([]);
    const [codigoBarras, setCodigoBarras] = useState('');
    const [estaCargando, setEstaCargando] = useState(false);
    const [eliminandoItem, setEliminandoItem] = useState(false);
    const [error, setError] = useState('');
    const [mensajeExito, setMensajeExito] = useState('');
    const [showModalBultos, setShowModalBultos] = useState(false);
    const scanInputRef = useRef(null);
    const cantidadRefs = useRef({});

    const setCantidadRef = useCallback((index, el) => {
        if (el) cantidadRefs.current[index] = el;
        else delete cantidadRefs.current[index];
    }, []);

    useEffect(() => {
        if (transferencia?.items) {
            setItemsConfirmados(transferencia.items.map(item => ({
                ...item,
                cantidad_confirmada: String(item.cantidad),
                escaneado: false,
                id_producto_insucursal: item.producto?.id || item.id_producto,
            })));
        }
        cantidadRefs.current = {};
        setTimeout(() => scanInputRef.current?.focus(), 200);
    }, [transferencia]);

    const enfocarCantidad = useCallback((index) => {
        setTimeout(() => {
            const el = cantidadRefs.current[index];
            if (el) {
                el.focus();
                if (typeof el.select === 'function') el.select();
            }
        }, 0);
    }, []);

    const handleScan = (e) => {
        e.preventDefault();
        if (!codigoBarras.trim()) return;

        const idx = itemsConfirmados.findIndex(item =>
            codigoCoincideItem(item, codigoBarras) && !item.escaneado
        );

        if (idx !== -1) {
            setItemsConfirmados(prev => prev.map((item, i) =>
                i === idx ? { ...item, escaneado: true } : item
            ));
            setError('');
            setCodigoBarras('');
            enfocarCantidad(idx);
        } else {
            const yaEscaneado = itemsConfirmados.find(item =>
                codigoCoincideItem(item, codigoBarras) && item.escaneado
            );
            if (yaEscaneado) {
                setError('Producto ya escaneado: ' + codigoBarras);
            } else {
                setError('Producto no encontrado en esta transferencia: ' + codigoBarras);
            }
            setCodigoBarras('');
            scanInputRef.current?.focus();
        }
    };

    const handleCantidadChange = (index, valor) => {
        setItemsConfirmados(prev => prev.map((item, i) =>
            i === index ? { ...item, cantidad_confirmada: valor } : item
        ));
    };

    const quitarItemPremontado = async (itemLinea) => {
        if (!transferencia?.id || transferencia.estado !== 0) return;
        const restantes = transferencia.items.filter((it) => it.id !== itemLinea.id);
        if (restantes.length === 0) {
            alert('Debe quedar al menos un producto en el premontado.');
            return;
        }
        if (!confirm('¿Quitar este producto del premontado? Se actualizará en Central.')) return;
        setEliminandoItem(true);
        setError('');
        try {
            const res = await db.settransferenciaDici({
                actualizando: true,
                id: transferencia.id,
                id_destino: transferencia.id_destino,
                observaciones: transferencia.observaciones || '',
                items: restantes.map(mapItemParaActualizarCentral),
            });
            if (!res.data.estado) {
                setError(res.data.msj || 'No se pudo quitar el ítem');
                return;
            }
            if (recargarTransferenciaPorId) {
                await recargarTransferenciaPorId(transferencia.id);
            }
        } catch (err) {
            setError(err.response?.data?.msj || err.message || 'Error al quitar ítem');
        } finally {
            setEliminandoItem(false);
        }
    };

    const todosEscaneados = itemsConfirmados.length > 0 && itemsConfirmados.every(item => item.escaneado);

    const handleConfirmarEnvio = async () => {
        setEstaCargando(true);
        setError('');
        try {
            const dest = transferencia.destino || {};
            const res = await db.confirmarEnvioTransferencia({
                id_pedido: transferencia.id,
                id_destino: transferencia.id_destino,
                nombre_destino: dest.nombre_sucursal || dest.nombre || '',
                codigo_destino: dest.codigo || '',
                items: itemsConfirmados.map(item => ({
                    id: item.id,
                    id_producto: item.id_producto,
                    id_producto_insucursal: item.id_producto_insucursal,
                    cantidad_confirmada: item.cantidad_confirmada,
                    cantidad: item.cantidad,
                })),
            });

            if (res.data.estado) {
                const hacia = [dest.nombre_sucursal || dest.nombre, dest.codigo].filter(Boolean).join(' · ');
                setMensajeExito(
                    hacia
                        ? `Envío confirmado hacia ${hacia}. El stock ya se descontó al premontar.`
                        : 'Envío confirmado. El stock ya se descontó al premontar.'
                );
                onConfirmed(res.data);
            } else {
                setError(res.data.msj || 'Error al confirmar envío');
            }
        } catch (err) {
            setError(err.response?.data?.msj || err.message || 'Error al confirmar envío');
        } finally {
            setEstaCargando(false);
        }
    };

    const escaneados = itemsConfirmados.filter(i => i.escaneado).length;
    const pctBarra = itemsConfirmados.length ? (escaneados / itemsConfirmados.length) * 100 : 0;

    return (
        <div className="p-4 md:p-6 bg-white rounded-lg shadow-xl">
            <div className="flex flex-wrap justify-between items-center gap-3 mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Confirmar Envío - Transferencia #{transferencia.id}</h2>
                <div className="flex flex-wrap gap-2">
                    {transferencia.estado === 0 && (
                        <button
                            type="button"
                            onClick={() => setShowModalBultos(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-900 bg-amber-100 border border-amber-300 rounded-md hover:bg-amber-200 transition"
                            title="Mismo formato que en pagarmain"
                        >
                            <i className="fa fa-box"></i>
                            Imprimir bultos
                        </button>
                    )}
                    {transferencia.estado === 0 && (
                        <TicketOrdenTransferenciaToolbar transferencia={transferencia} />
                    )}
                    <button onClick={onBack} className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md transition">&larr; Volver</button>
                </div>
            </div>

            <BultosPremontadoModal
                open={showModalBultos}
                onClose={() => setShowModalBultos(false)}
                transferencia={transferencia}
            />

            {error && <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><p>{error}</p></div>}
            {mensajeExito && <div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4"><p>{mensajeExito}</p></div>}

            <div className="mb-4 p-4 bg-gray-50 rounded-md">
                <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Destino: <b>{transferencia.destino?.nombre_sucursal || transferencia.destino?.codigo || `ID: ${transferencia.id_destino}`}</b></span>
                    <span className="text-sm font-medium">{escaneados}/{itemsConfirmados.length} escaneados</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-indigo-600 h-2.5 rounded-full transition-all" style={{ width: `${pctBarra}%` }}></div>
                </div>
            </div>

            <form onSubmit={handleScan} className="mb-6">
                <p className="text-xs text-gray-500 mb-2">Tras escanear, el foco pasa a <b>cantidad</b> (edita; <b>Ctrl+Enter</b> vuelve al lector).</p>
                <div className="flex space-x-2">
                    <input ref={scanInputRef} type="text" value={codigoBarras} onChange={(e) => setCodigoBarras(e.target.value)}
                        className="flex-1 p-3 text-lg border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500"
                        placeholder="Código de barras o código proveedor..." autoFocus />
                    <button type="submit" className="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        <i className="fas fa-barcode"></i>
                    </button>
                </div>
            </form>

            <div className="space-y-2 max-h-[50vh] overflow-y-auto">
                {itemsConfirmados.map((item, index) => (
                    <div key={item.id} className={`p-3 rounded-md border ${item.escaneado ? 'border-green-400 bg-green-50' : 'border-gray-200 bg-white'}`}>
                        <div className="flex flex-wrap justify-between items-center gap-2">
                            <div className="flex-grow min-w-0">
                                <div className="flex items-center space-x-2">
                                    {item.escaneado ? (
                                        <i className="fas fa-check-circle text-green-500"></i>
                                    ) : (
                                        <i className="fas fa-circle text-gray-300"></i>
                                    )}
                                    <span className="font-medium">{item.producto?.descripcion || item.descripcion_real}</span>
                                </div>
                                <p className="text-sm text-gray-500 ml-6">
                                    <b>{item.producto?.codigo_barras || item.barras_real}</b> | {item.producto?.codigo_proveedor || item.alterno_real}
                                </p>
                            </div>
                            <div className="flex items-center space-x-3 flex-shrink-0">
                                <span className="text-sm text-gray-500 whitespace-nowrap">Orig. {item.cantidad}</span>
                                <input
                                    ref={(el) => setCantidadRef(index, el)}
                                    type="number"
                                    step="0.01"
                                    value={item.cantidad_confirmada}
                                    onChange={(e) => handleCantidadChange(index, e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && e.ctrlKey) {
                                            e.preventDefault();
                                            scanInputRef.current?.focus();
                                            if (scanInputRef.current && typeof scanInputRef.current.select === 'function') {
                                                scanInputRef.current.select();
                                            }
                                        }
                                    }}
                                    className="w-28 p-2 text-center border-2 border-gray-300 rounded-md focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                />
                                {transferencia.estado === 0 && (
                                    <button
                                        type="button"
                                        disabled={eliminandoItem}
                                        onClick={() => quitarItemPremontado(item)}
                                        className="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded hover:bg-red-100 disabled:opacity-50"
                                        title="Quitar del premontado"
                                    >
                                        Quitar
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-6 flex justify-end space-x-4">
                <button onClick={onBack} className="px-6 py-3 border rounded-md text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onClick={handleConfirmarEnvio} disabled={!todosEscaneados || estaCargando}
                    className="px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 transition font-semibold">
                    {estaCargando ? 'Procesando...' : 'Confirmar envío a destino'}
                </button>
            </div>
        </div>
    );
};

// ==================== VISTA DE DETALLE ====================
const TransferenciaDetailView = ({ transferencia, onBack, sucursales, recargarTransferenciaPorId }) => {
    const [eliminandoItem, setEliminandoItem] = useState(false);
    const [showModalBultos, setShowModalBultos] = useState(false);

    const quitarLineaPremontado = async (itemLinea) => {
        if (!transferencia?.id || transferencia.estado !== 0 || !recargarTransferenciaPorId) return;
        const restantes = transferencia.items.filter((it) => it.id !== itemLinea.id);
        if (restantes.length === 0) {
            alert('Debe quedar al menos un producto en el premontado.');
            return;
        }
        if (!confirm('¿Quitar este producto del premontado en Central?')) return;
        setEliminandoItem(true);
        try {
            const res = await db.settransferenciaDici({
                actualizando: true,
                id: transferencia.id,
                id_destino: transferencia.id_destino,
                observaciones: transferencia.observaciones || '',
                items: restantes.map(mapItemParaActualizarCentral),
            });
            if (!res.data.estado) {
                alert(res.data.msj || 'No se pudo quitar el ítem');
                return;
            }
            await recargarTransferenciaPorId(transferencia.id);
        } catch (err) {
            alert(err.response?.data?.msj || err.message || 'Error al quitar ítem');
        } finally {
            setEliminandoItem(false);
        }
    };

    if (!transferencia) return <p>Cargando detalles...</p>;

    return (
        <div className="p-4 md:p-6 bg-white rounded-lg shadow-xl">
            <div className="flex flex-wrap justify-between items-center gap-3 mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Detalle de Transferencia #{transferencia.id}</h2>
                <div className="flex flex-wrap gap-2">
                    {transferencia.estado === 0 && (
                        <button
                            type="button"
                            onClick={() => setShowModalBultos(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-900 bg-amber-100 border border-amber-300 rounded-md hover:bg-amber-200 transition"
                            title="Mismo formato que en pagarmain"
                        >
                            <i className="fa fa-box"></i>
                            Imprimir bultos
                        </button>
                    )}
                    {transferencia.estado === 0 && (
                        <TicketOrdenTransferenciaToolbar transferencia={transferencia} />
                    )}
                    <button onClick={onBack} className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md transition">&larr; Volver</button>
                </div>
            </div>

            <BultosPremontadoModal
                open={showModalBultos}
                onClose={() => setShowModalBultos(false)}
                transferencia={transferencia}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 p-4 border rounded-md bg-gray-50">
                <div><p className="text-sm text-gray-500">ID Transferencia:</p><p className="font-medium">{transferencia.idinsucursal || transferencia.id}</p></div>
                <div><p className="text-sm text-gray-500">Estado:</p><StatusBadge estadoNum={transferencia.estado} /></div>
                <div><p className="text-sm text-gray-500">Fecha Creación:</p><p className="font-medium">{transferencia.created_at}</p></div>
                <div><p className="text-sm text-gray-500">Última Actualización:</p><p className="font-medium">{transferencia.updated_at}</p></div>
                <div><p className="text-sm text-gray-500">Sucursal Origen:</p><p className="font-medium">{transferencia.origen?.nombre_sucursal || transferencia.origen?.codigo || `ID: ${transferencia.id_origen}`}</p></div>
                <div><p className="text-sm text-gray-500">Sucursal Destino:</p><p className="font-medium">{transferencia.destino?.nombre_sucursal || transferencia.destino?.codigo || `ID: ${transferencia.id_destino}`}</p></div>
                {transferencia.observaciones && (
                    <div className="md:col-span-2"><p className="text-sm text-gray-500">Observaciones:</p><p className="font-medium whitespace-pre-wrap">{transferencia.observaciones}</p></div>
                )}
            </div>

            {/* Timestamps */}
            {(transferencia.premontado_at || transferencia.enviado_at || transferencia.recibido_at) && (
                <div className="mb-6 p-4 border rounded-md bg-blue-50">
                    <h3 className="text-sm font-semibold mb-2">Historial de estados</h3>
                    <div className="space-y-1 text-sm">
                        {transferencia.premontado_at && <div><span className="text-gray-500">Premontado:</span> {transferencia.premontado_at}</div>}
                        {transferencia.enviado_at && <div><span className="text-gray-500">Enviado:</span> {transferencia.enviado_at}</div>}
                        {transferencia.recibido_at && <div><span className="text-gray-500">Recibido:</span> {transferencia.recibido_at}</div>}
                        {transferencia.en_revision_at && <div><span className="text-gray-500">En revisión:</span> {transferencia.en_revision_at}</div>}
                        {transferencia.revisado_at && <div><span className="text-gray-500">Revisado:</span> {transferencia.revisado_at}</div>}
                        {transferencia.procesado_at && <div><span className="text-gray-500">Procesado:</span> {transferencia.procesado_at}</div>}
                    </div>
                </div>
            )}

            <h3 className="text-xl font-semibold text-gray-700 mb-3">
                Items {transferencia.estado === 0 ? <span className="text-sm font-normal text-gray-500">(premontado: puedes quitar líneas que no saldrán)</span> : null}
            </h3>
            {transferencia.items && transferencia.items.length > 0 ? (
                <ul className="border rounded-md divide-y max-h-[50vh] overflow-y-auto">
                    {transferencia.items.map((item, index) => (
                        <li key={item.id || index} className="flex flex-col sm:flex-row justify-between items-start sm:items-center p-3 gap-2">
                            <div className="flex-grow min-w-0">
                                <p className="font-semibold text-gray-800">{item.producto?.descripcion || item.descripcion_real}</p>
                                <p className="text-sm text-gray-500">
                                    <b>{item.producto?.codigo_barras || item.barras_real}</b> | {item.producto?.codigo_proveedor || item.alterno_real}
                                </p>
                                <p className="text-sm text-gray-600 mt-1">Cantidad: <b>{item.cantidad}</b></p>
                            </div>
                            {transferencia.estado === 0 && (
                                <button
                                    type="button"
                                    disabled={eliminandoItem}
                                    onClick={() => quitarLineaPremontado(item)}
                                    className="shrink-0 px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50"
                                >
                                    Quitar del premontado
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
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

// ==================== LISTA DE TRANSFERENCIAS ====================
const TransferenciaList = ({
    sucursalActualId, onEdit, onViewDetails, onConfirmSend, sucursales, cargarTransferencias,
    transferencias, estaCargando, error, filtros, setFiltros, filtrosActivos, setFiltrosActivos,
    paginacion, mostrarFiltros, setMostrarFiltros
}) => {
    const handleFilterChange = (e) => {
        setFiltros(prev => ({ ...prev, [e.target.name]: e.target.value }));
    };

    const handleSearch = () => {
        setFiltrosActivos({ ...filtros });
    };

    if (estaCargando && transferencias.length === 0) return <div className="text-center p-10">Cargando...</div>;
    if (error) return <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 my-4"><p>{error}</p></div>;

    return (
        <div className="rounded-lg">
            <div className="px-4 py-3 border-b border-gray-200 sm:px-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium text-gray-900">Transferencias</h2>
                    <div className="flex space-x-2">
                        <button onClick={() => setMostrarFiltros(!mostrarFiltros)}
                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i className={`fas fa-filter mr-2 ${mostrarFiltros ? 'text-indigo-600' : 'text-gray-400'}`}></i>Filtros
                        </button>
                        <button onClick={handleSearch}
                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i className="fas fa-search mr-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            {mostrarFiltros && (
                <div className="border-b border-gray-200 px-4 py-3 sm:px-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700">ID</label>
                            <input name="q" placeholder="Buscar por ID" value={filtros.q} onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700">Estado</label>
                            <select name="estatus_string" value={filtros.estatus_string} onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 rounded-md">
                                <option value="">Todos</option>
                                <option value="0">Premontado</option>
                                <option value="1">Enviado a Destino</option>
                                <option value="5">Recibido</option>
                                <option value="3">En Revisión</option>
                                <option value="4">Revisado</option>
                                <option value="2">Procesado</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700">Destino</label>
                            <select name="id_destino" value={filtros.id_destino} onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 rounded-md">
                                <option value="">Todas</option>
                                {sucursales.map(s => <option key={s.id} value={s.id}>{s.codigo}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700">Resultados</label>
                            <select name="limit" value={filtros.limit} onChange={handleFilterChange}
                                className="mt-1 block w-full pl-3 pr-8 py-1.5 text-sm border-gray-300 rounded-md">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
            )}

            <div className="p-0">
                {transferencias.length === 0 ? (
                    <div className="text-center py-12">
                        <i className="fas fa-box-open text-gray-400 text-4xl mb-3"></i>
                        <p className="text-gray-500">No se encontraron transferencias</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-b-lg border-t border-gray-200">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">ID</th>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Estado</th>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Creado</th>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Origen</th>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Destino</th>
                                    <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Items</th>
                                    <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Base</th>
                                    <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Venta</th>
                                    <th scope="col" className="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 w-40">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {transferencias.map(t => (
                                    <tr key={t.id} className="hover:bg-gray-50/80">
                                        <td className="px-3 py-2 font-mono font-semibold text-gray-900 whitespace-nowrap">#{t.id}</td>
                                        <td className="px-3 py-2 whitespace-nowrap"><StatusBadge estadoNum={t.estado} /></td>
                                        <td className="px-3 py-2 text-gray-600 whitespace-nowrap text-xs">{t.created_at || '—'}</td>
                                        <td className="px-3 py-2 text-gray-700 max-w-[10rem] truncate" title={t.origen?.nombre_sucursal || t.origen?.codigo || ''}>
                                            {t.origen?.nombre_sucursal || t.origen?.codigo || `ID: ${t.id_origen}`}
                                        </td>
                                        <td className="px-3 py-2 text-gray-700 max-w-[10rem] truncate" title={t.destino?.nombre_sucursal || t.destino?.codigo || ''}>
                                            {t.destino?.nombre_sucursal || t.destino?.codigo || `ID: ${t.id_destino}`}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{t.items?.length ?? 0}</td>
                                        <td className="px-3 py-2 text-right tabular-nums text-gray-800">${parseFloat(t.base || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums font-medium text-gray-900">${parseFloat(t.venta || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2">
                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                <button type="button" onClick={() => onViewDetails(t)} className="inline-flex items-center justify-center w-8 h-8 rounded text-indigo-600 hover:bg-indigo-50" title="Ver detalle">
                                                    <i className="fas fa-eye"></i>
                                                </button>
                                                {t.estado === 0 && (
                                                    <>
                                                        <button type="button" onClick={() => onEdit(t)} className="inline-flex items-center justify-center w-8 h-8 rounded text-blue-600 hover:bg-blue-50" title="Editar premontado">
                                                            <i className="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" onClick={() => onConfirmSend(t)} className="inline-flex items-center justify-center w-8 h-8 rounded text-red-600 hover:bg-red-50" title="Confirmar envío / salida">
                                                            <i className="fas fa-truck"></i>
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};

// ==================== HISTÓRICO DE TRANSFERENCIAS ====================
const TIMELINE_STEPS = [
    { estado: 0, key: 'premontado_at', label: 'Premontado', icon: 'fa-box', color: 'gray' },
    { estado: 1, key: 'enviado_at', label: 'Enviado', icon: 'fa-truck', color: 'red' },
    { estado: 5, key: 'recibido_at', label: 'Recibido', icon: 'fa-inbox', color: 'purple' },
    { estado: 3, key: 'en_revision_at', label: 'En Revisión', icon: 'fa-search', color: 'yellow' },
    { estado: 4, key: 'revisado_at', label: 'Revisado', icon: 'fa-check-circle', color: 'sky' },
    { estado: 2, key: 'procesado_at', label: 'Procesado', icon: 'fa-check-double', color: 'green' },
];

const StatusTimeline = ({ transferencia }) => {
    return (
        <div className="flex items-center space-x-1 overflow-x-auto py-2">
            {TIMELINE_STEPS.map((step, idx) => {
                const timestamp = transferencia[step.key];
                const isActive = !!timestamp;
                const isCurrent = transferencia.estado === step.estado;
                return (
                    <React.Fragment key={step.key}>
                        {idx > 0 && (
                            <div className={`flex-shrink-0 w-6 h-0.5 ${isActive ? 'bg-green-400' : 'bg-gray-200'}`}></div>
                        )}
                        <div className={`flex-shrink-0 flex flex-col items-center ${isActive ? '' : 'opacity-40'}`}>
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs
                                ${isCurrent ? 'ring-2 ring-offset-1 ring-indigo-500' : ''}
                                ${isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'}`}>
                                <i className={`fas ${step.icon}`}></i>
                            </div>
                            <span className="text-[10px] mt-1 text-center leading-tight whitespace-nowrap">{step.label}</span>
                            {timestamp && (
                                <span className="text-[9px] text-gray-400 whitespace-nowrap">{timestamp}</span>
                            )}
                        </div>
                    </React.Fragment>
                );
            })}
        </div>
    );
};

const HistoricoTransferencias = ({ sucursalActualId, sucursales, onBack }) => {
    const [transferencias, setTransferencias] = useState([]);
    const [estaCargando, setEstaCargando] = useState(false);
    const [error, setError] = useState('');
    const [expandedId, setExpandedId] = useState(null);
    const [resolviendoNovedadId, setResolviendoNovedadId] = useState(null);
    const [filtros, setFiltros] = useState({
        q: '', estatus_string: '', id_destino: '', limit: 25,
        fecha_desde: '', fecha_hasta: ''
    });

    const cargar = useCallback(async () => {
        setEstaCargando(true);
        setError('');
        try {
            const response = await db.reqMipedidos(filtros);
            setTransferencias(response.data.data || response.data.pedido || []);
        } catch (err) {
            setError('Error al cargar el histórico');
        } finally {
            setEstaCargando(false);
        }
    }, [filtros]);

    useEffect(() => { cargar(); }, []);

    const handleSearch = (e) => {
        e?.preventDefault();
        cargar();
    };

    const toggleExpand = (id) => {
        setExpandedId(expandedId === id ? null : id);
    };

    const totalItems = (t) => t.items?.length || 0;
    const totalCantidad = (t) => t.items?.reduce((sum, i) => sum + parseFloat(i.cantidad || 0), 0) || 0;

    const esSucursalOrigen = (t) =>
        sucursalActualId != null && t?.id_origen != null &&
        String(sucursalActualId) === String(t.id_origen);

    const resolverNovedadOrigen = async (idNovedad, accion) => {
        const observacion = window.prompt(
            accion === 'aceptar'
                ? 'Observación en origen (opcional):'
                : 'Motivo de rechazo (opcional):'
        );
        if (observacion === null) return;
        const ok = window.confirm(
            accion === 'aceptar'
                ? '¿Aceptar la novedad y aplicar el ajuste de inventario en esta sucursal (origen)?'
                : '¿Rechazar esta novedad en origen?'
        );
        if (!ok) return;
        setResolviendoNovedadId(idNovedad);
        try {
            const res = await db.resolverNovedadOrigen({
                id_novedad: idNovedad,
                accion,
                observacion,
            });
            if (res.data?.estado) {
                window.alert(res.data.msj || 'Listo.');
                await cargar();
            } else {
                window.alert(res.data?.msj || 'No se pudo resolver la novedad.');
            }
        } catch (e) {
            console.error(e);
            window.alert('Error al contactar el servidor.');
        } finally {
            setResolviendoNovedadId(null);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 className="text-2xl font-bold text-gray-800">Histórico de Transferencias</h2>
                    <p className="text-sm text-gray-500">Registro completo de transferencias enviadas con línea de tiempo de estados</p>
                </div>
                <button onClick={onBack}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md transition">
                    &larr; Volver
                </button>
            </div>

            {/* Filtros */}
            <form onSubmit={handleSearch} className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">ID</label>
                        <input type="text" value={filtros.q} onChange={e => setFiltros(p => ({ ...p, q: e.target.value }))}
                            placeholder="#" className="w-full p-2 text-sm border border-gray-300 rounded-md" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                        <select value={filtros.estatus_string} onChange={e => setFiltros(p => ({ ...p, estatus_string: e.target.value }))}
                            className="w-full p-2 text-sm border border-gray-300 rounded-md">
                            <option value="">Todos</option>
                            <option value="0">Premontado</option>
                            <option value="1">Enviado</option>
                            <option value="5">Recibido</option>
                            <option value="3">En Revisión</option>
                            <option value="4">Revisado</option>
                            <option value="2">Procesado</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Destino</label>
                        <select value={filtros.id_destino} onChange={e => setFiltros(p => ({ ...p, id_destino: e.target.value }))}
                            className="w-full p-2 text-sm border border-gray-300 rounded-md">
                            <option value="">Todos</option>
                            {sucursales.map(s => <option key={s.id} value={s.id}>{s.codigo}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Resultados</label>
                        <select value={filtros.limit} onChange={e => setFiltros(p => ({ ...p, limit: e.target.value }))}
                            className="w-full p-2 text-sm border border-gray-300 rounded-md">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div className="flex items-end">
                        <button type="submit" disabled={estaCargando}
                            className="w-full p-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 transition">
                            {estaCargando ? <i className="fas fa-spinner fa-spin"></i> : <><i className="fas fa-search mr-1"></i> Buscar</>}
                        </button>
                    </div>
                </div>
            </form>

            {error && <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded"><p>{error}</p></div>}

            {/* Resumen */}
            {transferencias.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="bg-white border rounded-lg p-3 text-center">
                        <div className="text-2xl font-bold text-indigo-600">{transferencias.length}</div>
                        <div className="text-xs text-gray-500">Transferencias</div>
                    </div>
                    <div className="bg-white border rounded-lg p-3 text-center">
                        <div className="text-2xl font-bold text-green-600">
                            {transferencias.filter(t => t.estado === 2).length}
                        </div>
                        <div className="text-xs text-gray-500">Procesadas</div>
                    </div>
                    <div className="bg-white border rounded-lg p-3 text-center">
                        <div className="text-2xl font-bold text-yellow-600">
                            {transferencias.filter(t => [0, 1, 3, 4, 5].includes(t.estado)).length}
                        </div>
                        <div className="text-xs text-gray-500">En proceso</div>
                    </div>
                    <div className="bg-white border rounded-lg p-3 text-center">
                        <div className="text-2xl font-bold text-gray-700">
                            {transferencias.reduce((s, t) => s + totalItems(t), 0)}
                        </div>
                        <div className="text-xs text-gray-500">Total items</div>
                    </div>
                </div>
            )}

            {/* Lista */}
            {estaCargando && transferencias.length === 0 ? (
                <div className="text-center py-12 text-gray-400">
                    <i className="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>Cargando histórico...</p>
                </div>
            ) : transferencias.length === 0 ? (
                <div className="text-center py-12 text-gray-400">
                    <i className="fas fa-history fa-3x mb-3"></i>
                    <p>No se encontraron transferencias</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="w-10 px-2 py-2.5" aria-label="Expandir" />
                                <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">ID</th>
                                <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Estado</th>
                                <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Creado</th>
                                <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Origen</th>
                                <th scope="col" className="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Destino</th>
                                <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Items</th>
                                <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Base</th>
                                <th scope="col" className="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Venta</th>
                                <th scope="col" className="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-600">Novedades</th>
                                <th scope="col" className="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 w-28">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {transferencias.map(t => (
                                <Fragment key={t.id}>
                                    <tr className={`hover:bg-gray-50/80 ${expandedId === t.id ? 'bg-indigo-50/40' : ''}`}>
                                        <td className="px-2 py-2 align-top">
                                            <button
                                                type="button"
                                                className="w-9 h-9 flex items-center justify-center rounded text-gray-500 hover:bg-gray-100"
                                                onClick={() => toggleExpand(t.id)}
                                                aria-expanded={expandedId === t.id}
                                                title={expandedId === t.id ? 'Ocultar detalle' : 'Ver detalle'}
                                            >
                                                <i className={`fas fa-chevron-${expandedId === t.id ? 'down' : 'right'}`}></i>
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 font-mono font-semibold text-gray-900 whitespace-nowrap align-top">#{t.id}</td>
                                        <td className="px-3 py-2 whitespace-nowrap align-top max-w-[14rem]">
                                            <StatusBadge estadoNum={t.estado} />
                                        </td>
                                        <td className="px-3 py-2 text-gray-600 whitespace-nowrap text-xs align-top">{t.created_at || '—'}</td>
                                        <td className="px-3 py-2 text-gray-700 max-w-[9rem] truncate align-top" title={t.origen?.nombre_sucursal || t.origen?.codigo || ''}>
                                            {t.origen?.nombre_sucursal || t.origen?.codigo || `ID: ${t.id_origen}`}
                                        </td>
                                        <td className="px-3 py-2 text-gray-700 max-w-[9rem] truncate align-top" title={t.destino?.nombre_sucursal || t.destino?.codigo || ''}>
                                            {t.destino?.nombre_sucursal || t.destino?.codigo || `ID: ${t.id_destino}`}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums align-top">{totalItems(t)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums text-gray-800 align-top">${parseFloat(t.base || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums font-medium text-gray-900 align-top">${parseFloat(t.venta || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-center align-top">
                                            {t.novedades?.length > 0 ? (
                                                <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-700">
                                                    {t.novedades.length}
                                                </span>
                                            ) : (
                                                <span className="text-gray-300">—</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div className="flex justify-center">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center justify-center w-8 h-8 rounded text-indigo-600 hover:bg-indigo-50"
                                                    onClick={() => toggleExpand(t.id)}
                                                    title={expandedId === t.id ? 'Ocultar detalle' : 'Ver detalle'}
                                                >
                                                    <i className="fas fa-list-ul"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    {expandedId === t.id && (
                                        <tr className="bg-gray-50">
                                            <td colSpan={11} className="p-0 border-t border-gray-200">
                                                <div className="px-4 py-3 border-b border-gray-200 bg-white">
                                                    <StatusTimeline transferencia={t} />
                                                </div>
                                                <div className="border-t border-gray-200">
                                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-gray-50 text-sm">
                                                        <div>
                                                            <span className="text-gray-500 block">Creado</span>
                                                            <span className="font-medium">{t.created_at || '-'}</span>
                                                        </div>
                                                        <div>
                                                            <span className="text-gray-500 block">Última actualización</span>
                                                            <span className="font-medium">{t.updated_at || '-'}</span>
                                                        </div>
                                                        <div>
                                                            <span className="text-gray-500 block">Origen</span>
                                                            <span className="font-medium">{t.origen?.nombre_sucursal || t.origen?.codigo || `ID: ${t.id_origen}`}</span>
                                                        </div>
                                                        <div>
                                                            <span className="text-gray-500 block">Destino</span>
                                                            <span className="font-medium">{t.destino?.nombre_sucursal || t.destino?.codigo || `ID: ${t.id_destino}`}</span>
                                                        </div>
                                                    </div>

                                                    <div className="px-4 py-3 border-t border-gray-100 bg-white">
                                                        <h4 className="text-sm font-semibold text-gray-600 mb-2">
                                                            <i className="fas fa-clock mr-1"></i> Línea de tiempo detallada
                                                        </h4>
                                                        <div className="relative">
                                                            <div className="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                                                            {TIMELINE_STEPS.map(step => {
                                                                const timestamp = t[step.key];
                                                                if (!timestamp) return null;
                                                                return (
                                                                    <div key={step.key} className="relative flex items-start space-x-3 pb-3 pl-1">
                                                                        <div className="relative z-10 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                                                            <i className={`fas ${step.icon} text-white text-xs`}></i>
                                                                        </div>
                                                                        <div className="flex-grow">
                                                                            <div className="flex justify-between items-center">
                                                                                <span className="font-medium text-sm text-gray-800">{step.label}</span>
                                                                                <span className="text-xs text-gray-400">{timestamp}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>

                                                    {t.observaciones && (
                                                        <div className="px-4 py-2 border-t border-gray-100 bg-yellow-50">
                                                            <span className="text-xs text-gray-500">Observaciones:</span>
                                                            <p className="text-sm">{t.observaciones}</p>
                                                        </div>
                                                    )}

                                                    <div className="px-4 py-3 border-t border-gray-100 bg-white">
                                                        <h4 className="text-sm font-semibold text-gray-600 mb-2">
                                                            <i className="fas fa-list mr-1"></i> Items ({totalItems(t)}) &mdash; Total cant: {totalCantidad(t).toFixed(2)}
                                                        </h4>
                                                        <div className="overflow-x-auto">
                                                            <table className="w-full text-sm">
                                                                <thead>
                                                                    <tr className="border-b border-gray-200 text-left text-xs text-gray-500 uppercase">
                                                                        <th className="pb-2 pr-3">#</th>
                                                                        <th className="pb-2 pr-3">Código Barras</th>
                                                                        <th className="pb-2 pr-3">Código Prov.</th>
                                                                        <th className="pb-2 pr-3">Descripción</th>
                                                                        <th className="pb-2 pr-3 text-right">Cantidad</th>
                                                                        <th className="pb-2 pr-3 text-right">Ct. Recibida</th>
                                                                        <th className="pb-2 text-right">Monto</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {t.items?.map((item, idx) => {
                                                                        const hasDiff = item.cantidad_recibida !== null &&
                                                                            item.cantidad_recibida !== undefined &&
                                                                            parseFloat(item.cantidad_recibida) !== parseFloat(item.cantidad);
                                                                        return (
                                                                            <tr key={item.id || idx}
                                                                                className={`border-b border-gray-100 ${hasDiff ? 'bg-red-50' : ''}`}>
                                                                                <td className="py-2 pr-3 text-gray-400">{idx + 1}</td>
                                                                                <td className="py-2 pr-3 font-mono text-xs">
                                                                                    {item.producto?.codigo_barras || item.barras_real || '-'}
                                                                                </td>
                                                                                <td className="py-2 pr-3 text-xs">
                                                                                    {item.producto?.codigo_proveedor || item.alterno_real || '-'}
                                                                                </td>
                                                                                <td className="py-2 pr-3">
                                                                                    {item.producto?.descripcion || item.descripcion_real || '-'}
                                                                                </td>
                                                                                <td className="py-2 pr-3 text-right font-medium">
                                                                                    {parseFloat(item.cantidad || 0).toFixed(2)}
                                                                                </td>
                                                                                <td className={`py-2 pr-3 text-right font-medium ${hasDiff ? 'text-red-600' : ''}`}>
                                                                                    {item.cantidad_recibida !== null && item.cantidad_recibida !== undefined
                                                                                        ? parseFloat(item.cantidad_recibida).toFixed(2)
                                                                                        : <span className="text-gray-300">-</span>}
                                                                                </td>
                                                                                <td className="py-2 text-right">
                                                                                    {parseFloat(item.monto || 0).toFixed(2)}
                                                                                </td>
                                                                            </tr>
                                                                        );
                                                                    })}
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr className="border-t-2 border-gray-300 font-semibold">
                                                                        <td colSpan="4" className="py-2 text-right text-gray-500">Totales:</td>
                                                                        <td className="py-2 pr-3 text-right">{totalCantidad(t).toFixed(2)}</td>
                                                                        <td className="py-2 pr-3 text-right">
                                                                            {t.items?.some(i => i.cantidad_recibida != null)
                                                                                ? t.items.reduce((s, i) => s + parseFloat(i.cantidad_recibida || 0), 0).toFixed(2)
                                                                                : '-'}
                                                                        </td>
                                                                        <td className="py-2 text-right">
                                                                            {t.items?.reduce((s, i) => s + parseFloat(i.monto || 0), 0).toFixed(2)}
                                                                        </td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>

                                                    {t.novedades?.length > 0 && (
                                                        <div className="px-4 py-3 border-t border-gray-100 bg-orange-50">
                                                            <h4 className="text-sm font-semibold text-orange-700 mb-2">
                                                                <i className="fas fa-exclamation-triangle mr-1"></i> Novedades ({t.novedades.length})
                                                            </h4>
                                                            {t.novedades.map(nov => (
                                                                <div key={nov.id} className="bg-white rounded border border-orange-200 p-3 mb-2">
                                                                    <div className="flex justify-between items-center mb-1">
                                                                        <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${
                                                                            nov.estado === 0 ? 'bg-yellow-100 text-yellow-800' :
                                                                            nov.estado === 1 ? 'bg-blue-100 text-blue-800' :
                                                                            nov.estado === 2 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                                        }`}>
                                                                            {nov.estado === 0 ? 'Reportada' :
                                                                             nov.estado === 1 ? 'Aprobada Central' :
                                                                             nov.estado === 2 ? 'Aceptada Origen' : 'Rechazada Origen'}
                                                                        </span>
                                                                        <span className="text-xs text-gray-400">{nov.reportada_at}</span>
                                                                    </div>
                                                                    {nov.observacion_destino && <p className="text-xs text-gray-600"><b>Destino:</b> {nov.observacion_destino}</p>}
                                                                    {nov.observacion_central && <p className="text-xs text-gray-600"><b>Central:</b> {nov.observacion_central}</p>}
                                                                    {nov.observacion_origen && <p className="text-xs text-gray-600"><b>Origen:</b> {nov.observacion_origen}</p>}
                                                                    {nov.items?.map(ni => (
                                                                        <div key={ni.id} className="flex justify-between text-xs mt-1 pt-1 border-t border-orange-100">
                                                                            <span>{ni.producto?.descripcion || `Prod #${ni.id_producto}`}</span>
                                                                            <span>
                                                                                Env: {ni.cantidad_enviada} | Rec: {ni.cantidad_recibida} |
                                                                                Dif: <b className={ni.diferencia < 0 ? 'text-red-600' : 'text-green-600'}>{ni.diferencia}</b>
                                                                            </span>
                                                                        </div>
                                                                    ))}
                                                                    {nov.estado === 1 && esSucursalOrigen(t) && (
                                                                        <div className="flex flex-wrap gap-2 mt-2 pt-2 border-t border-orange-100">
                                                                            <button
                                                                                type="button"
                                                                                disabled={resolviendoNovedadId === nov.id}
                                                                                className="px-2 py-1 text-xs font-medium rounded bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
                                                                                onClick={() => resolverNovedadOrigen(nov.id, 'aceptar')}
                                                                            >
                                                                                Aceptar (origen)
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                disabled={resolviendoNovedadId === nov.id}
                                                                                className="px-2 py-1 text-xs font-medium rounded bg-gray-600 text-white hover:bg-gray-700 disabled:opacity-50"
                                                                                onClick={() => resolverNovedadOrigen(nov.id, 'rechazar')}
                                                                            >
                                                                                Rechazar (origen)
                                                                            </button>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

function normalizeSucursalesMsj(msj) {
    if (Array.isArray(msj)) return msj;
    if (msj && typeof msj === 'object' && Array.isArray(msj.data)) return msj.data;
    return [];
}

// ==================== MÓDULO PRINCIPAL ====================
const TransferenciasModule = ({
    sucursalActualId,
    sucursalesCentral: sucursalesCentralProp,
    getSucursales: getSucursalesParent,
}) => {
    const [vistaActual, setVistaActual] = useState('list');
    const [transferenciaSeleccionada, setTransferenciaSeleccionada] = useState(null);
    const [refreshListKey, setRefreshListKey] = useState(0);
    const [sucursales, setSucursales] = useState([]);

    const sucursalesLista = useMemo(() => {
        if (Array.isArray(sucursalesCentralProp) && sucursalesCentralProp.length > 0) {
            return sucursalesCentralProp;
        }
        return normalizeSucursalesMsj(sucursales);
    }, [sucursalesCentralProp, sucursales]);

    const [transferencias, setTransferencias] = useState([]);
    const [estaCargando, setEstaCargando] = useState(true);
    const [error, setError] = useState('');
    const [filtros, setFiltros] = useState({ q: '', estatus_string: '', id_destino: '', limit: 10 });
    const [filtrosActivos, setFiltrosActivos] = useState({ q: '', estatus_string: '', id_destino: '', limit: 10 });
    const [paginacion, setPaginacion] = useState({});
    const [mostrarFiltros, setMostrarFiltros] = useState(false);
    const [cargandoSucursales, setCargandoSucursales] = useState(false);

    const getSucursalesParentRef = useRef(getSucursalesParent);
    getSucursalesParentRef.current = getSucursalesParent;

    const handleActualizarSucursales = useCallback(async () => {
        const fn = getSucursalesParentRef.current;
        if (fn) {
            fn();
            return;
        }
        setCargandoSucursales(true);
        try {
            const res = await db.getSucursales({});
            if (res.data.estado) {
                setSucursales(normalizeSucursalesMsj(res.data.msj));
            }
        } catch (error) {
            console.error("Error cargando sucursales:", error);
        } finally {
            setCargandoSucursales(false);
        }
    }, []);

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

    useEffect(() => {
        const fetchData = async () => {
            setEstaCargando(true);
            setError('');
            try {
                const { transferencias: nuevas, paginacion: nuevaPag } = await cargarTransferencias(filtrosActivos);
                setTransferencias(nuevas);
                setPaginacion(nuevaPag);
            } catch (err) {
                setError('No se pudieron cargar las transferencias.');
                setTransferencias([]);
            } finally {
                setEstaCargando(false);
            }
        };
        fetchData();
    }, [cargarTransferencias, filtrosActivos, refreshListKey]);

    const handleSaveTransfer = () => {
        setVistaActual('list');
        setTransferenciaSeleccionada(null);
        setRefreshListKey(prev => prev + 1);
    };

    const handleCancelForm = () => { setVistaActual('list'); setTransferenciaSeleccionada(null); };
    const handleGoToCreate = () => { setTransferenciaSeleccionada(null); setVistaActual('form'); };
    const handleEditTransfer = (t) => { setTransferenciaSeleccionada(t); setVistaActual('form'); };
    const handleViewDetails = (t) => { setTransferenciaSeleccionada(t); setVistaActual('detail'); };
    const handleConfirmSend = (t) => { setTransferenciaSeleccionada(t); setVistaActual('confirm_send'); };
    const handleEnvioConfirmed = () => { setVistaActual('list'); setTransferenciaSeleccionada(null); setRefreshListKey(prev => prev + 1); };
    const handleGoToHistorico = () => { setVistaActual('historico'); setTransferenciaSeleccionada(null); };

    const recargarTransferenciaPorId = useCallback(async (idPedido) => {
        try {
            const res = await db.reqMipedidos({
                q: String(idPedido),
                limit: 50,
                estatus_string: '',
                id_destino: '',
            });
            const lista = res.data.data || [];
            const found = lista.find((p) => String(p.id) === String(idPedido));
            if (found) {
                setTransferenciaSeleccionada(found);
            }
        } catch (e) {
            console.error('recargarTransferenciaPorId', e);
        }
    }, []);

    return (
        <div className="mx-auto px-2 py-4 sm:px-4 md:px-6">
            <header className="mb-6 pb-4 border-b border-gray-200">
                <div className="flex flex-col sm:flex-row justify-between items-center">
                    <h3 className="text-2xl sm:text-3xl font-bold text-gray-800">Gestión de Transferencias</h3>
                    <div className="flex flex-wrap gap-2 mt-3 sm:mt-0 items-center justify-center sm:justify-end">
                        {vistaActual === 'list' && (
                            <>
                                <button onClick={handleGoToHistorico}
                                    className="bg-gray-700 hover:bg-gray-800 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">
                                    <i className="fas fa-history mr-2"></i>Histórico
                                </button>
                                <button onClick={handleGoToCreate}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">
                                    + Nueva Transferencia
                                </button>
                            </>
                        )}
                        {(vistaActual !== 'list' && vistaActual !== 'historico') && (
                            <button onClick={handleCancelForm}
                                className="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition">
                                &larr; Volver al Listado
                            </button>
                        )}
                    </div>
                </div>
            </header>
            <main>
                {vistaActual === 'list' && (
                    <TransferenciaList
                        sucursalActualId={sucursalActualId}
                        onEdit={handleEditTransfer}
                        onViewDetails={handleViewDetails}
                        onConfirmSend={handleConfirmSend}
                        sucursales={sucursalesLista}
                        cargarTransferencias={cargarTransferencias}
                        transferencias={transferencias}
                        estaCargando={estaCargando}
                        error={error}
                        filtros={filtros}
                        setFiltros={setFiltros}
                        filtrosActivos={filtrosActivos}
                        setFiltrosActivos={setFiltrosActivos}
                        paginacion={paginacion}
                        mostrarFiltros={mostrarFiltros}
                        setMostrarFiltros={setMostrarFiltros}
                    />
                )}
                {vistaActual === 'form' && (
                    <TransferenciaForm
                        onSave={handleSaveTransfer}
                        onCancel={handleCancelForm}
                        sucursalActualId={sucursalActualId}
                        transferenciaToEdit={transferenciaSeleccionada}
                        sucursales={sucursalesLista}
                        cargarTransferencias={cargarTransferencias}
                        onActualizarSucursales={handleActualizarSucursales}
                        cargandoSucursales={cargandoSucursales}
                    />
                )}
                {vistaActual === 'detail' && (
                    <TransferenciaDetailView
                        transferencia={transferenciaSeleccionada}
                        onBack={handleCancelForm}
                        sucursales={sucursalesLista}
                        recargarTransferenciaPorId={recargarTransferenciaPorId}
                    />
                )}
                {vistaActual === 'confirm_send' && transferenciaSeleccionada && (
                    <ConfirmarEnvioView
                        transferencia={transferenciaSeleccionada}
                        onBack={handleCancelForm}
                        onConfirmed={handleEnvioConfirmed}
                        recargarTransferenciaPorId={recargarTransferenciaPorId}
                    />
                )}
                {vistaActual === 'historico' && (
                    <HistoricoTransferencias
                        sucursalActualId={sucursalActualId}
                        sucursales={sucursalesLista}
                        onBack={handleCancelForm}
                    />
                )}
            </main>
        </div>
    );
};

export default TransferenciasModule;
