import React, { useState, useEffect, useRef, useCallback } from 'react';
import db from '../database/database';

/**
 * Despacho con recolección y bultos (TCD). El checkeador (DICI) arma una orden en
 * preparación (sin descontar), reparte líneas a pasilleros, reconcuenta lo recolectado,
 * arma bultos, los cierra (etiqueta con código de barras) y despacha escaneando bulto
 * por bulto — cada bulto da salida a su mercancía. Lo no empacado queda excluido.
 */
export default function DespachoBultosModule({ sucursales = [] }) {
    const [vista, setVista] = useState('lista'); // 'lista' | 'nueva' | 'orden'
    const [ordenes, setOrdenes] = useState([]);
    const [orden, setOrden] = useState(null); // detalle de la orden abierta
    const [pasilleros, setPasilleros] = useState([]);
    const [excluidos, setExcluidos] = useState([]);
    const [msg, setMsg] = useState(null);
    const [cargando, setCargando] = useState(false);

    const flash = (estado, texto) => { setMsg({ estado, texto }); setTimeout(() => setMsg(null), 4000); };

    const cargarOrdenes = useCallback(async () => {
        setCargando(true);
        try {
            const r = await db.tdGetOrdenes({ estado: 0, limit: 50 });
            setOrdenes(r.data?.ordenes || []);
        } finally { setCargando(false); }
    }, []);

    useEffect(() => { cargarOrdenes(); db.tdGetPasilleros({}).then(r => setPasilleros(r.data?.pasilleros || [])); }, [cargarOrdenes]);

    const abrirOrden = async (id) => {
        const r = await db.tdGetAsignaciones({ id_transferencia: id });
        if (r.data?.estado) { setOrden(r.data.orden); cargarExcluidos(id); setVista('orden'); }
        else flash(false, r.data?.msj || 'No se pudo abrir');
    };
    const refrescar = async () => { if (orden) { const r = await db.tdGetAsignaciones({ id_transferencia: orden.id }); if (r.data?.estado) setOrden(r.data.orden); cargarExcluidos(orden.id); } };
    const cargarExcluidos = async (id) => { const r = await db.tdReporteExcluidos({ id_transferencia: id }); setExcluidos(r.data?.excluidos || []); };

    const sucNombre = (id) => { const s = sucursales.find(x => x.id == id); return s ? (s.nombre || s.codigo) : ('Destino ' + id); };

    // ───────────────────────── LISTA ─────────────────────────
    const renderLista = () => (
        <div>
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold text-gray-800">Órdenes en preparación</h2>
                <div className="flex gap-2">
                    <button onClick={cargarOrdenes} className="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg"><i className="fas fa-sync-alt mr-1"></i>Refrescar</button>
                    <button onClick={() => setVista('nueva')} className="px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold"><i className="fas fa-plus mr-1"></i>Nueva orden</button>
                </div>
            </div>
            {cargando ? <p className="text-gray-400 py-8 text-center">Cargando…</p> : ordenes.length === 0 ? (
                <div className="text-center py-12 text-gray-400"><i className="fas fa-inbox text-4xl mb-2"></i><p>Sin órdenes en preparación. Creá una nueva.</p></div>
            ) : (
                <div className="overflow-x-auto border border-gray-200 rounded-lg">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-100 text-gray-500 text-xs uppercase">
                            <tr><th className="px-3 py-2 text-left">Orden</th><th className="px-3 py-2 text-left">Destino</th><th className="px-3 py-2 text-center">Ítems</th><th className="px-3 py-2 text-center">Bultos</th><th className="px-3 py-2 text-left">Creada</th><th className="px-3 py-2"></th></tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {ordenes.map(o => (
                                <tr key={o.id} className="hover:bg-indigo-50">
                                    <td className="px-3 py-2 font-bold">#{o.id}</td>
                                    <td className="px-3 py-2">{sucNombre(o.id_destino)}</td>
                                    <td className="px-3 py-2 text-center">{o.items.length}</td>
                                    <td className="px-3 py-2 text-center">{o.bultos.length}</td>
                                    <td className="px-3 py-2 text-gray-500">{o.created_at}</td>
                                    <td className="px-3 py-2 text-right">
                                        <button onClick={() => abrirOrden(o.id)} className="px-3 py-1 bg-indigo-600 text-white rounded text-xs font-semibold">Abrir</button>
                                        <button onClick={() => eliminarOrden(o.id)} className="ml-2 px-2 py-1 bg-red-100 text-red-600 rounded text-xs"><i className="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );

    const eliminarOrden = async (id) => {
        if (!window.confirm('¿Eliminar la orden #' + id + '? (está en preparación, no descontó inventario)')) return;
        const r = await db.tdEliminarOrden({ id });
        flash(r.data?.estado, r.data?.msj); if (r.data?.estado) cargarOrdenes();
    };

    // ───────────────────────── NUEVA ORDEN ─────────────────────────
    const renderNueva = () => <NuevaOrden sucursales={sucursales} onGuardada={(o) => { flash(true, 'Orden guardada (sin descontar)'); cargarOrdenes(); setOrden(o); cargarExcluidos(o.id); setVista('orden'); }} onCancel={() => setVista('lista')} flash={flash} />;

    // ───────────────────────── ORDEN (workspace) ─────────────────────────
    const renderOrden = () => {
        if (!orden) return null;
        const todosBultosDespachados = orden.bultos.length > 0 && orden.bultos.every(b => b.estado === 'despachado');
        return (
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <button onClick={() => { setVista('lista'); setOrden(null); cargarOrdenes(); }} className="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm">&larr; Órdenes</button>
                        <h2 className="text-lg font-bold">Orden #{orden.id} <span className="text-sm font-normal text-gray-500">→ {sucNombre(orden.id_destino)}</span></h2>
                        <span className={`px-2 py-0.5 rounded text-xs font-bold ${orden.estado === 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>{orden.estado === 0 ? 'EN PREPARACIÓN' : 'DESPACHADA'}</span>
                    </div>
                    {orden.estado === 1 && (
                        <div className="flex gap-2">
                            <a target="_blank" rel="noreferrer" href={`transferencia-despacho/orden-despacho?id_transferencia=${orden.id}`} className="px-3 py-1.5 bg-slate-700 text-white rounded-lg text-sm"><i className="fas fa-print mr-1"></i>Orden de despacho</a>
                            <a target="_blank" rel="noreferrer" href={`transferencia-despacho/factura-bultos?id_transferencia=${orden.id}`} className="px-3 py-1.5 bg-emerald-700 text-white rounded-lg text-sm"><i className="fas fa-file-invoice mr-1"></i>Factura c/bultos</a>
                        </div>
                    )}
                </div>

                {orden.estado === 0 && <>
                    <SeccionAsignacion orden={orden} pasilleros={pasilleros} onChange={refrescar} flash={flash} />
                    <SeccionRecuento orden={orden} onChange={refrescar} flash={flash} />
                    <SeccionBultos orden={orden} onChange={refrescar} flash={flash} />
                    <SeccionDespacho orden={orden} todos={todosBultosDespachados} onChange={refrescar} flash={flash} />
                </>}

                {excluidos.length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                        <h3 className="text-sm font-bold text-red-700 mb-2"><i className="fas fa-triangle-exclamation mr-1"></i>Mercancía excluida (no empacada)</h3>
                        <table className="w-full text-xs">
                            <thead className="text-red-600"><tr><th className="text-left py-1">Producto</th><th className="text-center">Solicitado</th><th className="text-center">Empacado</th><th className="text-center">Excluido</th></tr></thead>
                            <tbody>{excluidos.map((e, i) => <tr key={i} className="border-t border-red-100"><td className="py-1">{e.descripcion}</td><td className="text-center">{e.solicitado}</td><td className="text-center">{e.empacado}</td><td className="text-center font-bold">{e.excluido}</td></tr>)}</tbody>
                        </table>
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="p-2 lg:p-4">
            {msg && <div className={`mb-3 px-3 py-2 rounded-lg text-sm ${msg.estado ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>{msg.texto}</div>}
            {vista === 'lista' && renderLista()}
            {vista === 'nueva' && renderNueva()}
            {vista === 'orden' && renderOrden()}
        </div>
    );
}

// ════════════════════════ Nueva Orden ════════════════════════
function NuevaOrden({ sucursales, onGuardada, onCancel, flash }) {
    const [termino, setTermino] = useState('');
    const [resultados, setResultados] = useState([]);
    const [items, setItems] = useState([]); // {id_producto, descripcion, codigo_barras, cantidad}
    const [destino, setDestino] = useState('');
    const [obs, setObs] = useState('');
    const debRef = useRef(null);

    useEffect(() => {
        if (debRef.current) clearTimeout(debRef.current);
        if (termino.trim()) debRef.current = setTimeout(async () => {
            const r = await db.getinventario({ vendedor: null, num: 25, itemCero: false, qProductosMain: termino, orderColumn: 'descripcion', orderBy: 'asc' });
            setResultados(r.data || []);
        }, 300);
        return () => clearTimeout(debRef.current);
    }, [termino]);

    const agregar = (p) => {
        if (items.find(i => i.id_producto === p.id)) { flash(false, 'Ya está en la orden'); return; }
        setItems([...items, { id_producto: p.id, descripcion: p.descripcion, codigo_barras: p.codigo_barras, cantidad: 1 }]);
        setTermino(''); setResultados([]);
    };
    const setCant = (id, v) => setItems(items.map(i => i.id_producto === id ? { ...i, cantidad: v } : i));
    const quitar = (id) => setItems(items.filter(i => i.id_producto !== id));

    const guardar = async () => {
        if (!destino) { flash(false, 'Elegí el destino'); return; }
        if (!items.length) { flash(false, 'Agregá al menos un producto'); return; }
        const r = await db.tdGuardarOrden({ id_destino: parseInt(destino), observaciones: obs, items: items.map(i => ({ id_producto_insucursal: i.id_producto, cantidad: parseFloat(i.cantidad) || 1 })) });
        if (r.data?.estado) onGuardada(r.data.orden); else flash(false, r.data?.msj || 'Error');
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold">Nueva orden de despacho</h2>
                <button onClick={onCancel} className="px-3 py-1.5 bg-gray-200 rounded-lg text-sm">Cancelar</button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label className="text-xs text-gray-500">Destino</label>
                    <select value={destino} onChange={e => setDestino(e.target.value)} className="w-full px-2 py-1.5 border rounded-lg text-sm">
                        <option value="">Elegí sucursal…</option>
                        {sucursales.map(s => <option key={s.id} value={s.id}>{s.nombre || s.codigo}</option>)}
                    </select>
                </div>
                <div>
                    <label className="text-xs text-gray-500">Observaciones</label>
                    <input value={obs} onChange={e => setObs(e.target.value)} className="w-full px-2 py-1.5 border rounded-lg text-sm" placeholder="Opcional" />
                </div>
            </div>
            <div className="relative mb-3">
                <input value={termino} onChange={e => setTermino(e.target.value)} placeholder="Buscar producto…" className="w-full px-3 py-2 border rounded-lg text-sm" />
                {resultados.length > 0 && (
                    <ul className="absolute z-20 bg-white border rounded-lg w-full max-h-64 overflow-y-auto shadow-lg mt-1">
                        {resultados.map(p => (
                            <li key={p.id} onClick={() => agregar(p)} className="px-3 py-2 hover:bg-indigo-50 cursor-pointer text-sm border-b">
                                <span className="font-semibold">{p.descripcion}</span> <span className="text-gray-400 font-mono text-xs">{p.codigo_barras}</span> <span className="text-emerald-600 text-xs">stock {p.cantidad}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <table className="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                <thead className="bg-gray-100 text-xs uppercase text-gray-500"><tr><th className="px-2 py-1.5 text-left">Producto</th><th className="px-2 py-1.5">Cód.</th><th className="px-2 py-1.5 w-24">Cantidad</th><th className="px-2 py-1.5"></th></tr></thead>
                <tbody className="divide-y divide-gray-100">
                    {items.length === 0 ? <tr><td colSpan="4" className="text-center text-gray-400 py-6">Sin productos</td></tr> :
                        items.map(i => (
                            <tr key={i.id_producto}>
                                <td className="px-2 py-1.5">{i.descripcion}</td>
                                <td className="px-2 py-1.5 font-mono text-xs text-gray-500">{i.codigo_barras}</td>
                                <td className="px-2 py-1.5"><input type="number" min="1" value={i.cantidad} onChange={e => setCant(i.id_producto, e.target.value)} className="w-20 px-2 py-1 border rounded text-center" /></td>
                                <td className="px-2 py-1.5 text-center"><button onClick={() => quitar(i.id_producto)} className="text-red-500"><i className="fas fa-trash"></i></button></td>
                            </tr>
                        ))}
                </tbody>
            </table>
            <div className="flex justify-end mt-4">
                <button onClick={guardar} className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold"><i className="fas fa-save mr-1"></i>Guardar orden (sin descontar)</button>
            </div>
        </div>
    );
}

// ════════════════════════ Asignación a pasilleros ════════════════════════
function SeccionAsignacion({ orden, pasilleros, onChange, flash }) {
    const [asig, setAsig] = useState(() => orden.items.map(it => ({ id_transferencia_item: it.id, pasillero_id: '', cantidad: it.cantidad })));
    const set = (idx, campo, v) => setAsig(asig.map((a, i) => i === idx ? { ...a, [campo]: v } : a));

    const guardar = async () => {
        const validas = asig.filter(a => a.pasillero_id && parseFloat(a.cantidad) > 0);
        if (!validas.length) { flash(false, 'Asigná al menos una línea a un pasillero'); return; }
        const r = await db.tdAsignarLineas({ id_transferencia: orden.id, asignaciones: validas.map(a => ({ ...a, cantidad: parseFloat(a.cantidad), pasillero_id: parseInt(a.pasillero_id) })) });
        flash(r.data?.estado, r.data?.msj); if (r.data?.estado) onChange();
    };
    const imprimir = (pid) => window.open(`transferencia-despacho/orden-recoleccion?id_transferencia=${orden.id}&pasillero_id=${pid}`, '_blank');
    const pasillerosAsignados = [...new Set(orden.asignaciones.map(a => a.pasillero_id))];

    return (
        <div className="bg-white border border-gray-200 rounded-lg p-3">
            <h3 className="text-sm font-bold text-gray-700 mb-2"><i className="fas fa-people-carry-box text-indigo-500 mr-1"></i>1 · Repartir líneas a pasilleros</h3>
            <table className="w-full text-xs">
                <thead className="text-gray-500 uppercase"><tr><th className="text-left py-1">Producto</th><th className="text-center">Solicitado</th><th className="text-left">Pasillero</th><th className="text-center w-24">Cantidad</th></tr></thead>
                <tbody>
                    {orden.items.map((it, idx) => (
                        <tr key={it.id} className="border-t">
                            <td className="py-1">{it.descripcion}</td>
                            <td className="text-center">{it.cantidad}</td>
                            <td><select value={asig[idx]?.pasillero_id || ''} onChange={e => set(idx, 'pasillero_id', e.target.value)} className="w-full px-1 py-1 border rounded"><option value="">—</option>{pasilleros.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}</select></td>
                            <td className="text-center"><input type="number" min="0" max={it.cantidad} value={asig[idx]?.cantidad ?? ''} onChange={e => set(idx, 'cantidad', e.target.value)} className="w-20 px-1 py-1 border rounded text-center" /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="flex items-center justify-between mt-2">
                <div className="flex gap-1 flex-wrap">
                    {pasillerosAsignados.map(pid => { const nom = orden.asignaciones.find(a => a.pasillero_id === pid)?.pasillero; return <button key={pid} onClick={() => imprimir(pid)} className="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-xs"><i className="fas fa-print mr-1"></i>Recolección · {nom}</button>; })}
                </div>
                <button onClick={guardar} className="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold">Asignar líneas</button>
            </div>
        </div>
    );
}

// ════════════════════════ Recuento ════════════════════════
function SeccionRecuento({ orden, onChange, flash }) {
    if (!orden.asignaciones.length) return null;
    const recontar = async (a, val) => {
        const r = await db.tdRecolectarLinea({ id_asignacion: a.id, cantidad_recolectada: parseFloat(val) || 0 });
        if (r.data?.estado) onChange(); else flash(false, r.data?.msj);
    };
    return (
        <div className="bg-white border border-gray-200 rounded-lg p-3">
            <h3 className="text-sm font-bold text-gray-700 mb-2"><i className="fas fa-clipboard-check text-indigo-500 mr-1"></i>2 · Recuento de lo recolectado</h3>
            <table className="w-full text-xs">
                <thead className="text-gray-500 uppercase"><tr><th className="text-left py-1">Producto</th><th className="text-left">Pasillero</th><th className="text-center">Asignado</th><th className="text-center w-28">Recolectado</th><th className="text-center">Estado</th></tr></thead>
                <tbody>
                    {orden.asignaciones.map(a => {
                        const it = orden.items.find(i => i.id === a.id_transferencia_item);
                        return (
                            <tr key={a.id} className="border-t">
                                <td className="py-1">{it?.descripcion}</td>
                                <td>{a.pasillero}</td>
                                <td className="text-center">{a.cantidad}</td>
                                <td className="text-center"><input type="number" min="0" max={a.cantidad} defaultValue={a.cantidad_recolectada} onBlur={e => recontar(a, e.target.value)} className="w-20 px-1 py-1 border rounded text-center" /></td>
                                <td className="text-center"><span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${a.estado === 'recolectada' ? 'bg-emerald-100 text-emerald-700' : a.estado === 'en_proceso' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'}`}>{a.estado}</span></td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

// ════════════════════════ Bultos + mini-panel ════════════════════════
function SeccionBultos({ orden, onChange, flash }) {
    const [sel, setSel] = useState({}); // por bulto: {id_transferencia_item, cantidad}
    const [consulta, setConsulta] = useState('');
    const [bultoConsultado, setBultoConsultado] = useState(null);

    const crear = async () => { const r = await db.tdCrearBulto({ id_transferencia: orden.id }); flash(r.data?.estado, r.data?.msj); if (r.data?.estado) onChange(); };
    const agregar = async (bultoId) => {
        const s = sel[bultoId]; if (!s || !s.id_transferencia_item || !(parseFloat(s.cantidad) > 0)) { flash(false, 'Elegí producto y cantidad'); return; }
        const r = await db.tdAgregarItemBulto({ id_bulto: bultoId, id_transferencia_item: parseInt(s.id_transferencia_item), cantidad: parseFloat(s.cantidad) });
        flash(r.data?.estado, r.data?.msj); if (r.data?.estado) { setSel({ ...sel, [bultoId]: { id_transferencia_item: '', cantidad: '' } }); onChange(); }
    };
    const quitar = async (biId) => { const r = await db.tdQuitarItemBulto({ id_bulto_item: biId }); flash(r.data?.estado, r.data?.msj); if (r.data?.estado) onChange(); };
    const cerrar = async (bultoId) => { const r = await db.tdCerrarBulto({ id_bulto: bultoId }); flash(r.data?.estado, r.data?.msj); if (r.data?.estado) onChange(); };
    const etiqueta = (bultoId) => window.open(`transferencia-despacho/etiqueta-bulto?id_bulto=${bultoId}`, '_blank');
    const consultar = async () => { if (!consulta.trim()) return; const r = await db.tdConsultarBulto({ codigo: consulta.trim() }); if (r.data?.estado) setBultoConsultado(r.data.bulto); else { setBultoConsultado(null); flash(false, r.data?.msj); } };
    const descProd = (id) => orden.items.find(i => i.id_producto === id)?.descripcion || ('#' + id);

    return (
        <div className="bg-white border border-gray-200 rounded-lg p-3">
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-sm font-bold text-gray-700"><i className="fas fa-box text-indigo-500 mr-1"></i>3 · Bultos</h3>
                <div className="flex gap-2 items-center">
                    <input value={consulta} onChange={e => setConsulta(e.target.value)} onKeyDown={e => e.key === 'Enter' && consultar()} placeholder="Escanear bulto (consultar)…" className="px-2 py-1 border rounded text-xs w-48" />
                    <button onClick={consultar} className="px-2 py-1 bg-slate-100 rounded text-xs">Consultar</button>
                    <button onClick={crear} className="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold"><i className="fas fa-plus mr-1"></i>Crear bulto</button>
                </div>
            </div>

            {bultoConsultado && (
                <div className="mb-2 p-2 bg-slate-50 border rounded text-xs">
                    <b>{bultoConsultado.codigo_barras}</b> (N° {bultoConsultado.numero}, {bultoConsultado.estado}) — {bultoConsultado.items.map((x, i) => <span key={i}>{x.descripcion} ×{x.cantidad}{i < bultoConsultado.items.length - 1 ? '; ' : ''}</span>)}
                    <button onClick={() => setBultoConsultado(null)} className="ml-2 text-gray-400">✕</button>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {orden.bultos.map(b => (
                    <div key={b.id} className={`border rounded-lg p-2 ${b.estado === 'abierto' ? 'border-amber-300 bg-amber-50/40' : b.estado === 'cerrado' ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-300 bg-slate-50'}`}>
                        <div className="flex items-center justify-between mb-1">
                            <span className="font-bold text-sm">Bulto N° {b.numero} <span className="font-mono text-xs text-gray-500">{b.codigo_barras}</span></span>
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${b.estado === 'abierto' ? 'bg-amber-200 text-amber-800' : b.estado === 'cerrado' ? 'bg-emerald-200 text-emerald-800' : 'bg-slate-300 text-slate-700'}`}>{b.estado.toUpperCase()}</span>
                        </div>
                        <ul className="text-xs mb-1">
                            {b.items.length === 0 ? <li className="text-gray-400">vacío</li> : b.items.map(bi => (
                                <li key={bi.id} className="flex justify-between py-0.5"><span>{descProd(bi.id_producto)} ×{bi.cantidad}</span>{b.estado === 'abierto' && <button onClick={() => quitar(bi.id)} className="text-red-400">✕</button>}</li>
                            ))}
                        </ul>
                        {b.estado === 'abierto' && (
                            <div className="flex gap-1 items-center mt-1">
                                <select value={sel[b.id]?.id_transferencia_item || ''} onChange={e => setSel({ ...sel, [b.id]: { ...sel[b.id], id_transferencia_item: e.target.value } })} className="flex-1 px-1 py-1 border rounded text-xs">
                                    <option value="">producto…</option>
                                    {orden.items.map(it => <option key={it.id} value={it.id}>{it.descripcion} (rec {it.recolectado}/emp {it.empacado})</option>)}
                                </select>
                                <input type="number" min="1" placeholder="cant" value={sel[b.id]?.cantidad || ''} onChange={e => setSel({ ...sel, [b.id]: { ...sel[b.id], cantidad: e.target.value } })} className="w-16 px-1 py-1 border rounded text-xs text-center" />
                                <button onClick={() => agregar(b.id)} className="px-2 py-1 bg-indigo-600 text-white rounded text-xs">+</button>
                                <button onClick={() => cerrar(b.id)} className="px-2 py-1 bg-emerald-600 text-white rounded text-xs">Cerrar</button>
                            </div>
                        )}
                        {b.estado !== 'abierto' && <button onClick={() => etiqueta(b.id)} className="mt-1 px-2 py-1 bg-slate-700 text-white rounded text-xs"><i className="fas fa-tag mr-1"></i>Etiqueta</button>}
                    </div>
                ))}
            </div>
        </div>
    );
}

// ════════════════════════ Despacho ════════════════════════
function SeccionDespacho({ orden, todos, onChange, flash }) {
    const [codigo, setCodigo] = useState('');
    const cerrados = orden.bultos.filter(b => b.estado === 'cerrado').length;
    const despachados = orden.bultos.filter(b => b.estado === 'despachado').length;

    const despachar = async () => {
        if (!codigo.trim()) return;
        const r = await db.tdDespacharBulto({ codigo: codigo.trim() });
        flash(r.data?.estado, r.data?.msj); setCodigo(''); if (r.data?.estado) onChange();
    };
    const finalizar = async () => {
        const r = await db.tdFinalizarDespacho({ id_transferencia: orden.id });
        flash(r.data?.estado, r.data?.msj); if (r.data?.estado) onChange();
    };
    if (orden.bultos.length === 0) return null;

    return (
        <div className="bg-white border border-gray-200 rounded-lg p-3">
            <h3 className="text-sm font-bold text-gray-700 mb-2"><i className="fas fa-truck-fast text-indigo-500 mr-1"></i>4 · Despacho (escaneá bulto por bulto — cada uno descuenta su mercancía)</h3>
            <div className="flex items-center gap-2 mb-2">
                <input value={codigo} onChange={e => setCodigo(e.target.value)} onKeyDown={e => e.key === 'Enter' && despachar()} placeholder="Escanear código de bulto…" className="flex-1 px-3 py-2 border rounded-lg text-sm font-mono" autoFocus />
                <button onClick={despachar} className="px-3 py-2 bg-amber-600 text-white rounded-lg text-sm font-semibold">Despachar bulto</button>
            </div>
            <div className="text-xs text-gray-500 mb-2">Despachados: <b>{despachados}</b> · Cerrados pendientes: <b>{cerrados}</b> · Total bultos: <b>{orden.bultos.length}</b></div>
            <button onClick={finalizar} disabled={!todos} className={`w-full px-3 py-2 rounded-lg text-sm font-bold ${todos ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'}`}>
                <i className="fas fa-flag-checkered mr-1"></i>Finalizar despacho y enviar a central
            </button>
        </div>
    );
}
