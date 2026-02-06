import { useState, useEffect } from 'react';
import db from '../database/database';

const TABS = { inventario: 'inventario', historial: 'historial', consumo: 'consumo' };

export default function InventarioSuministrosSucursal({ onBack }) {
    const [tab, setTab] = useState(TABS.inventario);
    const [inventario, setInventario] = useState([]);
    const [ordenes, setOrdenes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [loadingOrdenes, setLoadingOrdenes] = useState(false);
    const [recibiendoId, setRecibiendoId] = useState(null);
    const [consumiendoId, setConsumiendoId] = useState(null);
    const [productoParaConsumo, setProductoParaConsumo] = useState(null);
    const [cantidadConsumoModal, setCantidadConsumoModal] = useState('');
    const [ordenDetalleModal, setOrdenDetalleModal] = useState(null);
    const [loadingDetalleOrdenId, setLoadingDetalleOrdenId] = useState(null);
    const [historialConsumo, setHistorialConsumo] = useState([]);
    const [loadingConsumo, setLoadingConsumo] = useState(false);
    const hoy = new Date();
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const [fechaDesdeConsumo, setFechaDesdeConsumo] = useState(primerDiaMes.toISOString().slice(0, 10));
    const [fechaHastaConsumo, setFechaHastaConsumo] = useState(hoy.toISOString().slice(0, 10));
    const [msj, setMsj] = useState('');

    const cargarInventario = () => {
        setLoading(true);
        db.getInventarioInternoMiSucursal()
            .then(res => {
                if (res.data && res.data.estado && Array.isArray(res.data.data)) {
                    setInventario(res.data.data.filter(r => (r.cantidad || 0) > 0));
                } else {
                    setInventario([]);
                }
            })
            .catch(() => setInventario([]))
            .finally(() => setLoading(false));
    };

    const cargarOrdenes = () => {
        setLoadingOrdenes(true);
        db.getOrdenesInventarioInternoDestino()
            .then(res => {
                if (res.data && res.data.estado && Array.isArray(res.data.data)) {
                    setOrdenes(res.data.data);
                } else {
                    setOrdenes([]);
                }
            })
            .catch(() => setOrdenes([]))
            .finally(() => setLoadingOrdenes(false));
    };

    const cargarHistorialConsumo = () => {
        setLoadingConsumo(true);
        db.getHistorialConsumoInventarioInterno({
            fecha_desde: fechaDesdeConsumo,
            fecha_hasta: fechaHastaConsumo,
            agrupado: 1,
        })
            .then(res => {
                if (res.data && res.data.estado && Array.isArray(res.data.data)) {
                    setHistorialConsumo(res.data.data);
                } else {
                    setHistorialConsumo([]);
                }
            })
            .catch(() => setHistorialConsumo([]))
            .finally(() => setLoadingConsumo(false));
    };

    useEffect(() => {
        cargarInventario();
        cargarOrdenes();
    }, []);

    useEffect(() => {
        if (tab === TABS.consumo) cargarHistorialConsumo();
    }, [tab]);

    const recibirOrden = (idOrden) => {
        if (!idOrden) return;
        setRecibiendoId(idOrden);
        setMsj('');
        db.recibirOrdenInventarioInterno({ id_orden: String(idOrden) })
            .then(res => {
                if (res.data && res.data.estado) {
                    setMsj(res.data.msj || 'Orden marcada como recibida. Stock actualizado.');
                    setOrdenDetalleModal(null);
                    cargarInventario();
                    cargarOrdenes();
                } else {
                    setMsj(res.data?.msj || 'Error al recibir orden.');
                }
            })
            .catch(() => setMsj('Error de conexión con central.'))
            .finally(() => setRecibiendoId(null));
    };

    const abrirModalConsumo = (r) => {
        setProductoParaConsumo(r);
        setCantidadConsumoModal('');
    };

    const registrarConsumoDesdeModal = () => {
        if (!productoParaConsumo) return;
        const r = productoParaConsumo;
        const idInv = r.id_inventario_interno;
        const cant = parseFloat(cantidadConsumoModal || 0);
        if (!idInv || cant <= 0) {
            setMsj('Indique una cantidad mayor a 0 para registrar el consumo.');
            return;
        }
        if (cant > (r.cantidad || 0)) {
            setMsj('La cantidad a consumir no puede superar el stock disponible.');
            return;
        }
        setConsumiendoId(idInv);
        setMsj('');
        db.registrarConsumoInventarioInterno({ items: [{ id_inventario_interno: idInv, cantidad: cant }] })
            .then(res => {
                if (res.data && res.data.estado) {
                    setMsj(res.data.msj || 'Consumo registrado. Stock actualizado.');
                    setProductoParaConsumo(null);
                    setCantidadConsumoModal('');
                    cargarInventario();
                } else {
                    setMsj(res.data?.msj || 'Error al registrar consumo.');
                }
            })
            .catch(() => setMsj('Error de conexión con central.'))
            .finally(() => setConsumiendoId(null));
    };

    return (
        <div className="min-h-screen bg-gray-50 p-4">
            <div className="max-w-4xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-800">Inventario de Suministros (esta sucursal)</h1>
                    {onBack && (
                        <button onClick={onBack} className="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm">
                            Volver
                        </button>
                    )}
                </div>

                {msj && (
                    <div className="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">
                        {msj}
                    </div>
                )}

                <div className="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-2">
                    <button
                        type="button"
                        onClick={() => setTab(TABS.inventario)}
                        className={`px-4 py-2 rounded-lg text-sm font-medium ${tab === TABS.inventario ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'}`}
                    >
                        Inventario disponible
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab(TABS.historial)}
                        className={`px-4 py-2 rounded-lg text-sm font-medium ${tab === TABS.historial ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'}`}
                    >
                        Historial de órdenes
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab(TABS.consumo)}
                        className={`px-4 py-2 rounded-lg text-sm font-medium ${tab === TABS.consumo ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'}`}
                    >
                        Histórico de consumo
                    </button>
                </div>

                {tab === TABS.inventario && (
                    <div className="bg-white rounded-xl shadow p-4">
                        <div className="mb-4">
                            <button
                                onClick={cargarInventario}
                                disabled={loading}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm disabled:opacity-50"
                            >
                                {loading ? 'Cargando...' : 'Actualizar inventario'}
                            </button>
                        </div>
                        <p className="text-sm text-gray-600 mb-3">Seleccione un producto y pulse &quot;Registrar consumo&quot; para descontar cantidad del stock.</p>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="text-left p-2">SKU</th>
                                        <th className="text-left p-2">Descripción</th>
                                        <th className="text-right p-2">Cantidad</th>
                                        <th className="text-left p-2">Unidad</th>
                                        <th className="p-2 w-32"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {inventario.length === 0 && !loading && (
                                        <tr><td colSpan={5} className="p-4 text-center text-gray-500">Sin stock o no hay conexión con central.</td></tr>
                                    )}
                                    {inventario.map((r, i) => (
                                        <tr key={r.id_inventario_interno ?? i} className="border-b">
                                            <td className="p-2">{r.sku}</td>
                                            <td className="p-2">{r.descripcion}</td>
                                            <td className="p-2 text-right">{r.cantidad}</td>
                                            <td className="p-2">{r.unidad_medida || 'UND'}</td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() => abrirModalConsumo(r)}
                                                    className="px-3 py-1.5 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-700"
                                                >
                                                    Registrar consumo
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {tab === TABS.consumo && (
                    <div className="bg-white rounded-xl shadow p-4">
                        <p className="text-sm text-gray-600 mb-3">Consumo de suministros en esta sucursal, agrupado por ítem en el periodo seleccionado.</p>
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                <span className="text-gray-600">Desde:</span>
                                <input type="date" value={fechaDesdeConsumo} onChange={e => setFechaDesdeConsumo(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <span className="text-gray-600">Hasta:</span>
                                <input type="date" value={fechaHastaConsumo} onChange={e => setFechaHastaConsumo(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            </label>
                            <button type="button" onClick={cargarHistorialConsumo} disabled={loadingConsumo} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                                {loadingConsumo ? 'Cargando...' : 'Consultar'}
                            </button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="text-left p-2">SKU</th>
                                        <th className="text-left p-2">Descripción</th>
                                        <th className="text-right p-2">Cantidad consumida</th>
                                        <th className="text-left p-2">Unidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {historialConsumo.length === 0 && !loadingConsumo && (
                                        <tr><td colSpan={4} className="p-4 text-center text-gray-500">Sin consumo en el periodo o no hay conexión con central.</td></tr>
                                    )}
                                    {historialConsumo.map((row, idx) => (
                                        <tr key={row.id_inventario_interno || idx} className="border-b">
                                            <td className="p-2 font-mono text-xs">{row.sku || '—'}</td>
                                            <td className="p-2">{row.descripcion || '—'}</td>
                                            <td className="p-2 text-right font-medium">{row.cantidad_total ?? row.cantidad ?? 0}</td>
                                            <td className="p-2">{row.unidad_medida || 'UND'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {tab === TABS.historial && (
                    <div className="bg-white rounded-xl shadow p-4">
                        <p className="text-sm text-gray-600 mb-3">Órdenes enviadas por central a esta sucursal. Las que estén en estado &quot;Enviada&quot; puede marcarlas como recibidas para que el stock se sume a su inventario.</p>
                        <div className="mb-4">
                            <button type="button" onClick={cargarOrdenes} disabled={loadingOrdenes} className="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm disabled:opacity-50">
                                {loadingOrdenes ? 'Cargando...' : 'Actualizar listado'}
                            </button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="text-left p-2">Código</th>
                                        <th className="text-left p-2">Fecha</th>
                                        <th className="text-left p-2">Estado</th>
                                        <th className="text-left p-2">Items</th>
                                        <th className="p-2 w-28"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ordenes.length === 0 && !loadingOrdenes && (
                                        <tr><td colSpan={5} className="p-4 text-center text-gray-500">Sin órdenes o no hay conexión con central.</td></tr>
                                    )}
                                    {ordenes.map((o) => (
                                        <tr key={o.id} className="border-b hover:bg-gray-50">
                                            <td className="p-2 font-mono text-xs">{o.codigo}</td>
                                            <td className="p-2">{o.fecha || '—'}</td>
                                            <td className="p-2">
                                                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${o.estado === 'RECIBIDA' ? 'bg-green-100 text-green-800' : o.estado === 'PROCESADA' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700'}`}>
                                                    {o.estado === 'RECIBIDA' ? 'Recibida' : o.estado === 'PROCESADA' ? 'Enviada (pendiente recibir)' : o.estado || '—'}
                                                </span>
                                            </td>
                                            <td className="p-2 text-gray-600 max-w-xs truncate" title={o.items_resumen}>{o.items_resumen || '—'}</td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setOrdenDetalleModal({ id: o.id, codigo: o.codigo, fecha: o.fecha, estado: o.estado, items: null });
                                                        setLoadingDetalleOrdenId(o.id);
                                                        setMsj('');
                                                        db.getOrdenDetalleInventarioInternoDestino({ id_orden: o.id })
                                                            .then(res => {
                                                                if (res.data && res.data.estado && res.data.data) {
                                                                    setOrdenDetalleModal(res.data.data);
                                                                } else {
                                                                    setMsj(res.data?.msj || 'Error al cargar detalle.');
                                                                }
                                                            })
                                                            .catch(() => setMsj('Error de conexión al cargar detalle.'))
                                                            .finally(() => setLoadingDetalleOrdenId(null));
                                                    }}
                                                    disabled={loadingDetalleOrdenId !== null}
                                                    className="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
                                                >
                                                    {loadingDetalleOrdenId === o.id ? 'Cargando...' : 'Ver detalle'}
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Modal Detalle orden (ítems y cantidades a recibir) */}
                {ordenDetalleModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" onClick={() => { setOrdenDetalleModal(null); setLoadingDetalleOrdenId(null); }}>
                        <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
                            <div className="flex justify-between items-center border-b p-4">
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-800">Detalle de orden</h2>
                                    <p className="text-sm text-gray-600 mt-0.5">{ordenDetalleModal.codigo} — {ordenDetalleModal.fecha || '—'}</p>
                                    <span className={`inline-flex mt-1 px-2 py-0.5 rounded text-xs font-medium ${ordenDetalleModal.estado === 'RECIBIDA' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}>
                                        {ordenDetalleModal.estado === 'RECIBIDA' ? 'Recibida' : 'Enviada (pendiente recibir)'}
                                    </span>
                                </div>
                                <button type="button" onClick={() => { setOrdenDetalleModal(null); setLoadingDetalleOrdenId(null); }} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                            </div>
                            <div className="p-4 overflow-auto flex-1">
                                <p className="text-sm font-medium text-gray-700 mb-2">Ítems y cantidades a recibir:</p>
                                {loadingDetalleOrdenId === ordenDetalleModal.id ? (
                                    <p className="text-gray-500 py-4">Cargando detalle...</p>
                                ) : Array.isArray(ordenDetalleModal.items) && ordenDetalleModal.items.length > 0 ? (
                                    <table className="w-full text-sm border rounded-lg overflow-hidden">
                                        <thead>
                                            <tr className="bg-gray-100 border-b">
                                                <th className="text-left p-2">#</th>
                                                <th className="text-left p-2">SKU</th>
                                                <th className="text-left p-2">Descripción</th>
                                                <th className="text-right p-2">Cantidad</th>
                                                <th className="text-left p-2">Unidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {ordenDetalleModal.items.map((it, idx) => (
                                                <tr key={idx} className="border-b">
                                                    <td className="p-2 text-gray-500">{idx + 1}</td>
                                                    <td className="p-2 font-mono text-xs">{it.sku || '—'}</td>
                                                    <td className="p-2">{it.descripcion || '—'}</td>
                                                    <td className="p-2 text-right font-medium">{it.cantidad}</td>
                                                    <td className="p-2">{it.unidad_medida || 'UND'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-gray-500 py-4">No se pudieron cargar los ítems de esta orden.</p>
                                )}
                            </div>
                            <div className="border-t p-4 flex justify-end gap-2">
                                <button type="button" onClick={() => { setOrdenDetalleModal(null); setLoadingDetalleOrdenId(null); }} className="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Cerrar
                                </button>
                                {ordenDetalleModal.estado === 'PROCESADA' && (
                                    <button
                                        type="button"
                                        onClick={() => recibirOrden(ordenDetalleModal.id)}
                                        disabled={recibiendoId !== null}
                                        className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {recibiendoId === ordenDetalleModal.id ? 'Procesando...' : 'Recibir orden'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Modal Registrar consumo */}
                {productoParaConsumo && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" onClick={() => setProductoParaConsumo(null)}>
                        <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-5 space-y-4" onClick={e => e.stopPropagation()}>
                            <div className="flex justify-between items-center border-b pb-3">
                                <h2 className="text-lg font-semibold text-gray-800">Registrar consumo</h2>
                                <button type="button" onClick={() => setProductoParaConsumo(null)} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                            </div>
                            <p className="text-sm text-gray-700">
                                <strong>{productoParaConsumo.sku}</strong> — {productoParaConsumo.descripcion}
                            </p>
                            <p className="text-sm text-gray-600">Stock actual: <strong>{productoParaConsumo.cantidad}</strong> {productoParaConsumo.unidad_medida || 'UND'}</p>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Cantidad a descontar</label>
                                <input
                                    type="number"
                                    min="0.001"
                                    step="0.01"
                                    max={productoParaConsumo.cantidad ?? undefined}
                                    value={cantidadConsumoModal}
                                    onChange={e => setCantidadConsumoModal(e.target.value)}
                                    className="w-full min-w-[8rem] py-2 px-3 border border-gray-300 rounded-lg text-right"
                                    placeholder="0"
                                />
                                <p className="text-xs text-gray-500 mt-1">Máximo: {productoParaConsumo.cantidad}</p>
                            </div>
                            <div className="flex justify-end gap-2 pt-2 border-t">
                                <button type="button" onClick={() => setProductoParaConsumo(null)} className="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    onClick={registrarConsumoDesdeModal}
                                    disabled={consumiendoId !== null}
                                    className="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 disabled:opacity-50"
                                >
                                    {consumiendoId !== null ? 'Procesando...' : 'Descontar'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
