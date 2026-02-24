import React, { useState, useEffect } from 'react';

const ORIGEN_TRANSF = 'TRANSF.GARANTIA.DISPONIBLE_TIENDA';

function claseTipoOrigen(tipo) {
    if (!tipo) return 'bg-gray-200 text-gray-700';
    const t = String(tipo).toUpperCase();
    if (t === 'DAÑADO') return 'bg-red-200 text-red-800 font-semibold';
    if (t === 'RECUPERADO') return 'bg-green-200 text-green-800 font-semibold';
    if (t === 'TRANSFERIDO_AL_PROVEEDOR') return 'bg-amber-200 text-amber-800 font-semibold';
    if (t === 'DEVOLUCION') return 'bg-blue-200 text-blue-800 font-semibold';
    if (t === 'DISPONIBLE_EN_TIENDA') return 'bg-emerald-200 text-emerald-800 font-semibold';
    return 'bg-gray-200 text-gray-700 font-medium';
}

function getTipoLabel(tipo) {
    if (!tipo) return '—';
    const t = String(tipo).toUpperCase();
    if (t === 'DAÑADO') return 'Dañado';
    if (t === 'RECUPERADO') return 'Recuperado';
    if (t === 'TRANSFERIDO_AL_PROVEEDOR') return 'Transf. proveedor';
    if (t === 'DEVOLUCION') return 'Devolución';
    if (t === 'DISPONIBLE_EN_TIENDA') return 'Disp. tienda';
    return tipo;
}

export default function TransferenciaGarantia({ sucursalConfig, db }) {
    const [ordenes, setOrdenes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [inventarioDisponible, setInventarioDisponible] = useState([]);
    const [tipos, setTipos] = useState([]);
    const [sucursales, setSucursales] = useState([]);
    const [modalNueva, setModalNueva] = useState(false);
    const [modalDetalle, setModalDetalle] = useState(null);
    /** Por cada item_id: tipo destino y cantidad a confirmar al confirmar devolución por proveedor (inline en modal detalle) */
    const [devolucionProveedorTiposPorItem, setDevolucionProveedorTiposPorItem] = useState({});
    const [devolucionProveedorCantidadPorItem, setDevolucionProveedorCantidadPorItem] = useState({});
    const [carrito, setCarrito] = useState([]);
    const [destinoId, setDestinoId] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');
    const [filtroFechaDesde, setFiltroFechaDesde] = useState('');
    const [filtroFechaHasta, setFiltroFechaHasta] = useState('');
    const [filtroSucursalDestino, setFiltroSucursalDestino] = useState('');
    const [filtroTipoDestino, setFiltroTipoDestino] = useState('');
    const [filtroTipoOrigen, setFiltroTipoOrigen] = useState('');
    const [filtroProveedor, setFiltroProveedor] = useState('');
    const [filtroItem, setFiltroItem] = useState('');
    const [proveedores, setProveedores] = useState([]);
    const [proveedorSeleccionado, setProveedorSeleccionado] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [itemSeleccionado, setItemSeleccionado] = useState(null);
    const [cantidadAgregar, setCantidadAgregar] = useState(1);
    const [tipoDestinoSeleccionado, setTipoDestinoSeleccionado] = useState('');
    const [miCodigoFromApi, setMiCodigoFromApi] = useState('');

    // En Facturación la sucursal se identifica por código (sucursalConfig o API connection-status).
    const sucursalCodigo = String(sucursalConfig?.codigo || miCodigoFromApi || '').trim().toLowerCase();
    const codigoNormalizado = (c) => String(c || '').trim().toLowerCase();
    const miSucursal = sucursales.find(s => codigoNormalizado(s.codigo) === sucursalCodigo);
    const miSucursalId = miSucursal?.id ? String(miSucursal.id) : '';
    const carritoTieneDisponibleEnTienda = carrito.some(c => String(c.tipo_inventario_destino || '').toUpperCase() === 'DISPONIBLE_EN_TIENDA');
    const carritoTieneTransferidoAlProveedor = carrito.some(c => String(c.tipo_inventario_destino || '').toUpperCase() === 'TRANSFERIDO_AL_PROVEEDOR');
    const destinoObligatorioMiTienda = carritoTieneDisponibleEnTienda || carritoTieneTransferidoAlProveedor;
    const tipoOrigenDestinoFijo = carrito.length > 0 ? { origen: carrito[0].tipo_inventario_origen, destino: carrito[0].tipo_inventario_destino } : null;

    useEffect(() => {
        loadOrdenes();
        loadTipos();
        loadSucursales();
        if (db?.getProveedoresGarantia) {
            db.getProveedoresGarantia().then(r => {
                const d = r?.data;
                if (d?.success && Array.isArray(d?.data)) setProveedores(d.data);
            }).catch(() => setProveedores([]));
        }
    }, []);

    // Si el carrito tiene "DISPONIBLE_EN_TIENDA" o "TRANSFERIDO_AL_PROVEEDOR", destino obligatorio es mi sucursal (no otra).
    useEffect(() => {
        if (destinoObligatorioMiTienda && miSucursalId && destinoId !== miSucursalId) {
            setDestinoId(miSucursalId);
        }
    }, [destinoObligatorioMiTienda, miSucursalId, destinoId]);

    // Inicializar tipo y cantidad por ítem cuando se abre el detalle de una orden que permite confirmar devolución por proveedor
    useEffect(() => {
        if (!modalDetalle?.id) return;
        const destino = modalDetalle?.sucursal_destino ?? modalDetalle?.sucursalDestino;
        const puede = modalDetalle.estado === 'ACEPTADA' && codigoNormalizado(destino?.codigo) === sucursalCodigo && !modalDetalle.devolucion_proveedor_confirmada_at;
        const items = (modalDetalle.items || []).filter(it => String(it.tipo_inventario_destino || '').toUpperCase() === 'TRANSFERIDO_AL_PROVEEDOR');
        if (puede && items.length > 0) {
            const tip = {};
            const cant = {};
            items.forEach(it => { tip[it.id] = 'DISPONIBLE_EN_TIENDA'; cant[it.id] = it.cantidad; });
            setDevolucionProveedorTiposPorItem(tip);
            setDevolucionProveedorCantidadPorItem(cant);
        } else {
            setDevolucionProveedorTiposPorItem({});
            setDevolucionProveedorCantidadPorItem({});
        }
    }, [modalDetalle?.id, modalDetalle?.estado, modalDetalle?.devolucion_proveedor_confirmada_at, modalDetalle?.items, sucursalCodigo]);

    // Obtener código de sucursal desde API (para detectar rol origen/destino y poder aceptar órdenes).
    useEffect(() => {
        if (!db?.checkGarantiaConnection) return;
        db.checkGarantiaConnection().then((res) => {
            const codigo = res?.data?.sucursal_codigo;
            if (codigo) setMiCodigoFromApi(String(codigo).trim());
        }).catch(() => {});
    }, [db]);

    const loadOrdenes = async (paramsOverride = null) => {
        if (!db?.getOrdenesTransferenciaGarantia) return;
        setLoading(true);
        try {
            let params;
            if (paramsOverride != null && typeof paramsOverride === 'object' && !Array.isArray(paramsOverride)) {
                params = {};
                if (paramsOverride.estado != null) params.estado = String(paramsOverride.estado);
                if (paramsOverride.fecha_desde != null) params.fecha_desde = String(paramsOverride.fecha_desde);
                if (paramsOverride.fecha_hasta != null) params.fecha_hasta = String(paramsOverride.fecha_hasta);
                if (paramsOverride.sucursal_destino_id != null) params.sucursal_destino_id = Number(paramsOverride.sucursal_destino_id);
                if (paramsOverride.tipo_inventario_destino != null) params.tipo_inventario_destino = String(paramsOverride.tipo_inventario_destino);
                if (paramsOverride.tipo_inventario_origen != null) params.tipo_inventario_origen = String(paramsOverride.tipo_inventario_origen);
                if (paramsOverride.proveedor_id != null) params.proveedor_id = Number(paramsOverride.proveedor_id);
                if (paramsOverride.producto_search != null) params.producto_search = String(paramsOverride.producto_search);
            } else {
                params = {};
                if (filtroEstado) params.estado = String(filtroEstado);
                if (filtroFechaDesde) params.fecha_desde = String(filtroFechaDesde);
                if (filtroFechaHasta) params.fecha_hasta = String(filtroFechaHasta);
                if (filtroSucursalDestino) params.sucursal_destino_id = Number(filtroSucursalDestino);
                if (filtroTipoDestino) params.tipo_inventario_destino = String(filtroTipoDestino);
                if (filtroTipoOrigen) params.tipo_inventario_origen = String(filtroTipoOrigen);
                if (filtroProveedor) params.proveedor_id = Number(filtroProveedor);
                if (filtroItem?.trim()) params.producto_search = String(filtroItem.trim());
            }
            const res = await db.getOrdenesTransferenciaGarantia(params);
            const payload = res?.data ?? {};
            if (payload.sucursal_codigo_actual != null && payload.sucursal_codigo_actual !== '') {
                setMiCodigoFromApi(String(payload.sucursal_codigo_actual).trim());
            }
            const data = payload?.data ?? payload;
            setOrdenes(Array.isArray(data) ? data : (data?.data ?? []));
        } catch (e) {
            console.error(e);
            setOrdenes([]);
        } finally {
            setLoading(false);
        }
    };

    const loadTipos = async () => {
        if (!db?.tiposInventarioTransferenciaGarantia) return;
        try {
            const res = await db.tiposInventarioTransferenciaGarantia();
            const d = res?.data?.data ?? res?.data;
            setTipos(Array.isArray(d) ? d : []);
        } catch (e) {
            setTipos([]);
        }
    };

    const loadSucursales = async () => {
        if (!db?.getSucursales) return;
        try {
            const res = await db.getSucursales({});
            const data = res?.data?.msj ?? res?.msj;
            setSucursales(Array.isArray(data) ? data : []);
        } catch (e) {
            setSucursales([]);
        }
    };

    const loadInventarioDisponible = async (params = {}) => {
        if (!db?.inventarioDisponibleTransferenciaGarantia) return;
        setLoading(true);
        try {
            const res = await db.inventarioDisponibleTransferenciaGarantia(params);
            const d = res?.data?.data ?? res?.data;
            const lista = Array.isArray(d) ? d : [];
            // No mostrar ni permitir seleccionar tipo DISPONIBLE_EN_TIENDA al armar la orden
            setInventarioDisponible(lista.filter(it => String(it.tipo_inventario || '').toUpperCase() !== 'DISPONIBLE_EN_TIENDA'));
        } catch (e) {
            setInventarioDisponible([]);
        } finally {
            setLoading(false);
        }
    };

    const buscarInventario = () => {
        const term = (searchTerm || '').trim();
        loadInventarioDisponible(term ? { producto_search: term } : {});
    };

    const abrirNueva = () => {
        setCarrito([]);
        setDestinoId('');
        setObservaciones('');
        setSearchTerm('');
        setItemSeleccionado(null);
        setInventarioDisponible([]);
        setModalNueva(true);
    };

    const agregarAlCarrito = (item, cantidad, tipoDestino) => {
        const cant = Math.min(parseInt(cantidad, 10) || 0, item.cantidad_disponible ?? 0);
        if (cant <= 0) return;
        const tipoOrigen = item.tipo_inventario;
        const tipo = tipoDestino || item.tipo_inventario;
        if (carrito.length > 0) {
            const primero = carrito[0];
            if (String(tipoOrigen || '').toUpperCase() !== String(primero.tipo_inventario_origen || '').toUpperCase()) {
                alert('Todos los ítems de la orden deben tener el mismo tipo de origen. Ya tiene ítems con origen «' + primero.tipo_inventario_origen + '».');
                return;
            }
            if (String(tipo || '').toUpperCase() !== String(primero.tipo_inventario_destino || '').toUpperCase()) {
                alert('Todos los ítems de la orden deben tener el mismo tipo de destino. Ya tiene ítems con destino «' + primero.tipo_inventario_destino + '».');
                return;
            }
        }
        setCarrito(prev => {
            const idx = prev.findIndex(p => p.producto_id === item.producto_id && p.tipo_inventario_origen === item.tipo_inventario);
            const nuevo = { producto_id: item.producto_id, tipo_inventario_origen: tipoOrigen, tipo_inventario_destino: tipo, cantidad: cant, producto: item.producto };
            if (idx >= 0) {
                const copy = [...prev];
                copy[idx] = nuevo;
                return copy;
            }
            return [...prev, nuevo];
        });
        setItemSeleccionado(null);
        setCantidadAgregar(1);
        setTipoDestinoSeleccionado('');
    };

    const quitarDelCarrito = (index) => {
        setCarrito(prev => prev.filter((_, i) => i !== index));
    };

    const enviarOrden = async () => {
        const destinoIdEnviar = destinoObligatorioMiTienda ? miSucursalId : destinoId;
        if (destinoObligatorioMiTienda && !miSucursalId) {
            alert('No se pudo identificar su sucursal. Con «Disponible en tienda» o «Transferido al proveedor» el destino debe ser su misma sucursal.');
            return;
        }
        if (!destinoIdEnviar || carrito.length === 0) {
            alert('Seleccione sucursal destino y al menos un ítem.');
            return;
        }
        setLoading(true);
        try {
            // Origen por código. Si hay DISPONIBLE_EN_TIENDA o TRANSFERIDO_AL_PROVEEDOR, destino = mi sucursal.
            const payload = {
                sucursal_destino_id: parseInt(destinoIdEnviar, 10),
                observaciones: observaciones,
                usuario_creacion_nombre: 'Usuario',
                items: carrito.map(c => ({ producto_id: c.producto_id, tipo_inventario_origen: c.tipo_inventario_origen, tipo_inventario_destino: c.tipo_inventario_destino, cantidad: c.cantidad })),
            };
            if (carritoTieneTransferidoAlProveedor && proveedorSeleccionado) payload.proveedor_id = parseInt(proveedorSeleccionado, 10);
            const res = await db.createOrdenTransferenciaGarantia(payload);
            const orderId = res?.data?.data?.id ?? res?.data?.id;
            alert('Orden creada. Pendiente de aprobación en central.');
            setModalNueva(false);
            setProveedorSeleccionado('');
            loadOrdenes();
            if (orderId) {
                const reportUrl = `${window.location.origin}/reportes/transferencia-garantia/${orderId}`;
                window.open(reportUrl, '_blank', 'noopener,noreferrer');
            }
        } catch (e) {
            alert('Error: ' + (e?.response?.data?.message || e.message));
        } finally {
            setLoading(false);
        }
    };

    const aceptarOrden = async (orden) => {
        if (!orden?.id) return;
        if (!confirm('¿Aceptar esta orden? Se descontará en origen y sumará en su sucursal (todos los ítems pendientes).')) return;
        setLoading(true);
        try {
            await db.aceptarOrdenTransferenciaGarantia(orden.id, {
                sucursal_destino_codigo: sucursalCodigo,
                usuario_aceptacion_nombre: 'Usuario',
            });
            alert('Orden aceptada correctamente.');
            setModalDetalle(null);
            loadOrdenes();
        } catch (e) {
            alert('Error: ' + (e?.response?.data?.message || e.message));
        } finally {
            setLoading(false);
        }
    };

    const confirmarRecepcionItem = async (orden, item) => {
        if (!orden?.id || !item?.id || !db?.recibirItemTransferenciaGarantia) return;
        const recibido = Number(item.cantidad_recibida ?? 0);
        const pendiente = item.cantidad - recibido;
        if (pendiente <= 0) return;
        setLoading(true);
        try {
            const res = await db.recibirItemTransferenciaGarantia(orden.id, {
                item_id: item.id,
                usuario_aceptacion_nombre: 'Usuario',
            });
            const data = res?.data;
            if (data?.data) setModalDetalle(data.data);
            if (data?.orden_completa) {
                alert('Recepción registrada. Orden totalmente recibida.');
                setModalDetalle(null);
            } else {
                alert('Recepción del ítem registrada.');
            }
            loadOrdenes();
        } catch (e) {
            alert('Error: ' + (e?.response?.data?.message || e?.message || e));
        } finally {
            setLoading(false);
        }
    };

    const verDetalle = async (id) => {
        try {
            const res = await db.getOrdenTransferenciaGarantia(id);
            const data = res?.data?.data ?? res?.data;
            setModalDetalle(data);
        } catch (e) {
            setModalDetalle(null);
        }
    };

    const confirmarDevolucionProveedor = async () => {
        const orden = modalDetalle;
        if (!orden?.id || !db?.confirmarDevolucionProveedorTransferenciaGarantia) return;
        const items = (orden.items || []).filter(it => String(it.tipo_inventario_destino || '').toUpperCase() === 'TRANSFERIDO_AL_PROVEEDOR');
        if (items.length === 0) {
            alert('No hay ítems con destino Transferido al proveedor.');
            return;
        }
        const payloadItems = items.map(it => {
            const nuevoTipo = devolucionProveedorTiposPorItem[it.id] ?? 'DISPONIBLE_EN_TIENDA';
            let cant = parseInt(devolucionProveedorCantidadPorItem[it.id], 10);
            if (isNaN(cant) || cant < 1) cant = it.cantidad;
            cant = Math.min(cant, it.cantidad);
            return { item_id: it.id, nuevo_tipo: opcionesDestinoDevolucionProveedor.includes(nuevoTipo) ? nuevoTipo : 'DISPONIBLE_EN_TIENDA', cantidad: cant };
        });
        setLoading(true);
        try {
            await db.confirmarDevolucionProveedorTransferenciaGarantia(orden.id, {
                items: payloadItems,
                usuario_nombre: 'Usuario',
            });
            alert('Devolución por proveedor confirmada.');
            setDevolucionProveedorTiposPorItem({});
            setDevolucionProveedorCantidadPorItem({});
            const res = await db.getOrdenTransferenciaGarantia(orden.id);
            const data = res?.data?.data ?? res?.data;
            if (data) setModalDetalle(data);
            if (data?.devolucion_proveedor_confirmada_at) setModalDetalle(null);
            loadOrdenes({});
        } catch (e) {
            alert('Error: ' + (e?.response?.data?.message || e?.message || e));
        } finally {
            setLoading(false);
        }
    };

    const opcionesDestinoDevolucionProveedor = ['DISPONIBLE_EN_TIENDA', 'RECUPERADO', 'DAÑADO', 'DEVOLUCION'];
    const getLabelDestinoDevolucion = (t) => {
        const labels = { DISPONIBLE_EN_TIENDA: 'Disponible en tienda', RECUPERADO: 'Recuperado', DAÑADO: 'Dañado', DEVOLUCION: 'Devolución' };
        return labels[t] || t;
    };

    const tiposDestinoDevolucion = (tipos || []).filter(t => String(t).toUpperCase() !== 'TRANSFERIDO_AL_PROVEEDOR');
    const tiposDestinoDevolucionList = tiposDestinoDevolucion.length > 0 ? tiposDestinoDevolucion : ['DISPONIBLE_EN_TIENDA', 'RECUPERADO', 'DAÑADO', 'DEVOLUCION'];
    // En nueva orden, tipo destino no incluye DEVOLUCION (no aplica aquí)
    const opcionesDestinoNuevaOrden = (tipos || []).filter(t => String(t).toUpperCase() !== 'DEVOLUCION');
    const tipoDestinoEfectivoNuevaOrden = tipoOrigenDestinoFijo ? tipoOrigenDestinoFijo.destino : (opcionesDestinoNuevaOrden.includes(tipoDestinoSeleccionado) ? tipoDestinoSeleccionado : (opcionesDestinoNuevaOrden[0] || ''));

    // La API (Central) puede devolver sucursal_origen/sucursal_destino en snake_case o camelCase
    const getDestino = (orden) => orden?.sucursal_destino ?? orden?.sucursalDestino ?? null;
    const getOrigen = (orden) => orden?.sucursal_origen ?? orden?.sucursalOrigen ?? null;
    const soyOrigen = (orden) => codigoNormalizado(getOrigen(orden)?.codigo) === sucursalCodigo;
    const soyDestino = (orden) => codigoNormalizado(getDestino(orden)?.codigo) === sucursalCodigo;
    const puedeAceptar = (orden) => orden?.estado === 'APROBADA' && soyDestino(orden);
    const puedeConfirmarDevolucionProveedor = (orden) => {
        if (!orden || orden.estado !== 'ACEPTADA' || !soyDestino(orden)) return false;
        if (orden.devolucion_proveedor_confirmada_at) return false;
        const items = orden.items || [];
        return items.length > 0 && items.every(it => String(it.tipo_inventario_destino || '').toUpperCase() === 'TRANSFERIDO_AL_PROVEEDOR');
    };
    const rolOrden = (o) => {
        if (soyOrigen(o)) return { label: 'Enviada por mí', badge: 'bg-blue-100 text-blue-800' };
        if (soyDestino(o)) return o.estado === 'ACEPTADA' ? { label: 'Recibida', badge: 'bg-green-100 text-green-800' } : { label: 'Para recibir', badge: 'bg-amber-100 text-amber-800' };
        return { label: '—', badge: 'bg-gray-100 text-gray-600' };
    };

    const limpiarFiltros = () => {
        setFiltroEstado('');
        setFiltroFechaDesde('');
        setFiltroFechaHasta('');
        setFiltroSucursalDestino('');
        setFiltroTipoDestino('');
        setFiltroTipoOrigen('');
        setFiltroProveedor('');
        setFiltroItem('');
        loadOrdenes({});
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2 justify-between">
                <h2 className="text-lg font-semibold">Transferencia de Garantía</h2>
                <button type="button" className="bg-green-600 text-white px-3 py-1.5 rounded text-sm" onClick={abrirNueva}>Nueva orden</button>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 p-4">
                <p className="text-sm font-medium text-gray-700 mb-3">Filtros</p>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Estado</label>
                        <select className="border rounded px-3 py-2 text-sm min-w-[160px]" value={filtroEstado} onChange={e => setFiltroEstado(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="PENDIENTE_APROBACION">Pendiente aprobación</option>
                            <option value="APROBADA">Aprobada</option>
                            <option value="ACEPTADA">Aceptada</option>
                            <option value="RECHAZADA">Rechazada</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Sucursal destino</label>
                        <select className="border rounded px-3 py-2 text-sm min-w-[160px]" value={filtroSucursalDestino} onChange={e => setFiltroSucursalDestino(e.target.value)}>
                            <option value="">Todas</option>
                            {sucursales.map(s => <option key={s.id} value={s.id}>{s.nombre || s.codigo}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Tipo destino</label>
                        <select className="border rounded px-3 py-2 text-sm min-w-[180px]" value={filtroTipoDestino} onChange={e => setFiltroTipoDestino(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="DISPONIBLE_EN_TIENDA">Disponible en tienda</option>
                            <option value="DAÑADO">Dañado</option>
                            <option value="RECUPERADO">Recuperado</option>
                            <option value="TRANSFERIDO_AL_PROVEEDOR">Transferido a proveedor</option>
                            <option value="DEVOLUCION">Devolución</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Tipo origen</label>
                        <select className="border rounded px-3 py-2 text-sm min-w-[180px]" value={filtroTipoOrigen} onChange={e => setFiltroTipoOrigen(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="DISPONIBLE_EN_TIENDA">Disponible en tienda</option>
                            <option value="DAÑADO">Dañado</option>
                            <option value="RECUPERADO">Recuperado</option>
                            <option value="TRANSFERIDO_AL_PROVEEDOR">Transferido a proveedor</option>
                            <option value="DEVOLUCION">Devolución</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Proveedor</label>
                        <select className="border rounded px-3 py-2 text-sm min-w-[180px]" value={filtroProveedor} onChange={e => setFiltroProveedor(e.target.value)}>
                            <option value="">Todos</option>
                            {proveedores.map(p => <option key={p.id} value={p.id}>{p.descripcion || p.nombre || p.id}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Fecha desde</label>
                        <input type="date" className="border rounded px-3 py-2 text-sm" value={filtroFechaDesde} onChange={e => setFiltroFechaDesde(e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Fecha hasta</label>
                        <input type="date" className="border rounded px-3 py-2 text-sm" value={filtroFechaHasta} onChange={e => setFiltroFechaHasta(e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Ítem (código o descripción)</label>
                        <input type="text" className="border rounded px-3 py-2 text-sm w-48" placeholder="Buscar por ítem..." value={filtroItem} onChange={e => setFiltroItem(e.target.value)} />
                    </div>
                    <button type="button" className="bg-blue-600 text-white px-4 py-2 rounded text-sm" onClick={loadOrdenes} disabled={loading}>{loading ? 'Cargando...' : 'Buscar'}</button>
                    <button type="button" className="px-4 py-2 border rounded text-sm" onClick={limpiarFiltros}>Limpiar</button>
                </div>
            </div>

            {!sucursalCodigo && ordenes.length > 0 && (
                <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-2">Cargando código de tu sucursal… Si ves órdenes aquí, en el servidor ya se reconoce tu sucursal; el botón &quot;Aceptar orden&quot; debería mostrarse en un momento. Si no aparece, pulsa &quot;Buscar&quot;.</p>
            )}
            {sucursalCodigo && (() => {
                const ordenesAprobadasParaAceptar = ordenes.filter(o => puedeAceptar(o));
                if (ordenesAprobadasParaAceptar.length === 0) return null;
                return (
                    <div className="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p className="text-sm font-medium text-green-800">
                            <i className="fa fa-check-circle mr-2"></i>
                            Tienes {ordenesAprobadasParaAceptar.length} orden(es) aprobada(s) por central pendiente(s) de aceptar. Como sucursal destino, acepta la orden para que los inventarios se recarguen.
                        </p>
                        <p className="text-xs text-green-700 mt-1">Usa el botón &quot;Aceptar orden&quot; en la fila o abre &quot;Ver&quot; y acepta desde el detalle.</p>
                    </div>
                );
            })()}
            {loading && <p className="text-sm text-gray-500">Cargando...</p>}
            <div className="overflow-x-auto border rounded">
                <table className="w-full border text-sm table-fixed" style={{ minWidth: '850px' }}>
                    <colgroup>
                        <col style={{ width: '50px' }} />
                        <col style={{ width: '90px' }} />
                        <col style={{ width: '130px' }} />
                        <col style={{ width: '130px' }} />
                        <col style={{ width: '95px' }} />
                        <col style={{ width: '95px' }} />
                        <col style={{ width: '100px' }} />
                        <col style={{ width: '100px' }} />
                        <col style={{ width: '90px' }} />
                        <col style={{ width: '120px' }} />
                        <col style={{ width: '120px' }} />
                    </colgroup>
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="text-left p-2">#</th>
                            <th className="text-left p-2">Mi rol</th>
                            <th className="text-left p-2">Origen</th>
                            <th className="text-left p-2">Destino</th>
                            <th className="text-left p-2">Tipo origen</th>
                            <th className="text-left p-2">Tipo destino</th>
                            <th className="text-left p-2">Proveedor</th>
                            <th className="text-left p-2">Estado</th>
                            <th className="text-left p-2">Fecha</th>
                            <th className="text-left p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {ordenes.map(o => {
                            const rol = rolOrden(o);
                            const tipoOrigen = o.items?.[0]?.tipo_inventario_origen;
                            const tipoDestino = o.items?.[0]?.tipo_inventario_destino;
                            return (
                            <tr key={o.id} className="border-t">
                                <td className="p-2">{o.id}</td>
                                <td className="p-2"><span className={`px-2 py-0.5 rounded text-xs font-medium ${rol.badge}`}>{rol.label}</span></td>
                                <td className="p-2 overflow-hidden"><span className="block truncate max-w-full" title={getOrigen(o)?.nombre ?? ''}>{getOrigen(o)?.nombre ?? o.sucursal_origen_id}</span></td>
                                <td className="p-2 overflow-hidden"><span className="block truncate max-w-full" title={getDestino(o)?.nombre ?? ''}>{getDestino(o)?.nombre ?? o.sucursal_destino_id}</span></td>
                                <td className="p-2 overflow-hidden"><span className={`inline-flex px-2 py-0.5 rounded text-xs ${claseTipoOrigen(tipoOrigen)}`} title={tipoOrigen}>{getTipoLabel(tipoOrigen)}</span></td>
                                <td className="p-2 overflow-hidden"><span className={`inline-flex px-2 py-0.5 rounded text-xs ${claseTipoOrigen(tipoDestino)}`} title={tipoDestino}>{getTipoLabel(tipoDestino)}</span></td>
                                <td className="p-2 overflow-hidden">{o.proveedor ? <span className="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 font-medium" title={o.proveedor.rif || ''}>{o.proveedor.descripcion || o.proveedor.id}</span> : <span className="text-gray-400">—</span>}</td>
                                <td className="p-2 overflow-hidden"><span className={`px-2 py-0.5 rounded text-xs truncate inline-block max-w-full ${o.estado === 'ACEPTADA' ? 'bg-green-200' : o.estado === 'APROBADA' ? 'bg-blue-200' : o.estado === 'RECHAZADA' ? 'bg-red-200' : 'bg-yellow-200'}`} title={o.estado}>{o.estado}</span></td>
                                <td className="p-2">{o.created_at ? new Date(o.created_at).toLocaleDateString() : ''}</td>
                                <td className="p-2 whitespace-nowrap">
                                    <button type="button" className="text-blue-600 mr-2" onClick={() => verDetalle(o.id)}>Ver</button>
                                    <a href={`/reportes/transferencia-garantia/${o.id}`} target="_blank" rel="noopener noreferrer" className="text-gray-600 hover:underline mr-2">Reporte</a>
                                    {puedeAceptar(o) && <button type="button" className="bg-green-600 text-white px-2 py-1 rounded text-xs font-medium" onClick={() => aceptarOrden(o)} title="Aceptar y recargar inventario">Aceptar orden</button>}
                                </td>
                            </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {ordenes.length === 0 && !loading && <p className="text-gray-500 text-sm">No hay órdenes.</p>}

            {/* Modal nueva orden */}
            {modalNueva && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-auto p-5">
                        <h3 className="font-semibold mb-3 text-lg">Nueva orden de transferencia</h3>
                        <p className="text-xs text-gray-500 mb-3">Origen: esta sucursal ({sucursalCodigo || 'configure código'})</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Sucursal destino *</label>
                                {destinoObligatorioMiTienda ? (
                                    <>
                                        <div className="w-full border rounded px-2 py-1.5 bg-gray-100 text-gray-700">
                                            Mi sucursal ({miSucursal?.nombre || miSucursal?.codigo || sucursalCodigo}) — obligatorio para «Disponible en tienda» o «Transferido al proveedor»
                                        </div>
                                        <input type="hidden" value={miSucursalId} readOnly />
                                    </>
                                ) : (
                                    <select className="w-full border rounded px-2 py-1.5" value={destinoId} onChange={e => setDestinoId(e.target.value)}>
                                        <option value="">Seleccione</option>
                                        {sucursales.filter(s => codigoNormalizado(s.codigo) !== sucursalCodigo).map(s => <option key={s.id} value={s.id}>{s.nombre || s.codigo}</option>)}
                                    </select>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Observaciones</label>
                                <input className="w-full border rounded px-2 py-1.5" value={observaciones} onChange={e => setObservaciones(e.target.value)} placeholder="Opcional" />
                            </div>
                            {carritoTieneTransferidoAlProveedor && (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium mb-1">Proveedor (transferido a proveedor)</label>
                                    <select className="w-full border rounded px-2 py-1.5 max-w-md" value={proveedorSeleccionado} onChange={e => setProveedorSeleccionado(e.target.value)}>
                                        <option value="">Seleccione proveedor (opcional)</option>
                                        {proveedores.map(p => <option key={p.id} value={p.id}>{p.descripcion || p.nombre || p.id}</option>)}
                                    </select>
                                </div>
                            )}
                        </div>
                        <div className="mb-4">
                            <label className="block text-sm font-medium mb-2">Inventario disponible (agregar ítems)</label>
                            {tipoOrigenDestinoFijo && (
                                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mb-2">
                                    Todos los ítems deben ser mismo origen y mismo destino. Origen: <strong>{tipoOrigenDestinoFijo.origen}</strong> → Destino: <strong>{tipoOrigenDestinoFijo.destino}</strong>
                                </p>
                            )}
                            <div className="flex gap-2 mb-2">
                                <input
                                    className="flex-1 border rounded px-3 py-2"
                                    placeholder="Buscar por código o descripción..."
                                    value={searchTerm}
                                    onChange={e => setSearchTerm(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && buscarInventario()}
                                />
                                <button type="button" className="px-4 py-2 bg-blue-600 text-white rounded" onClick={buscarInventario} disabled={loading}>
                                    {loading ? 'Buscando...' : 'Buscar'}
                                </button>
                            </div>
                            <div className="border rounded p-2 max-h-48 overflow-y-auto space-y-1 bg-gray-50">
                                {inventarioDisponible.length === 0 && !loading && <p className="text-gray-500 text-sm py-2">Escriba y pulse Buscar o Enter para buscar ítems.</p>}
                                {(tipoOrigenDestinoFijo
                                    ? inventarioDisponible.filter(it => String(it.tipo_inventario || '').toUpperCase() === String(tipoOrigenDestinoFijo.origen || '').toUpperCase())
                                    : inventarioDisponible
                                ).map((item, i) => (
                                    <div
                                        key={i}
                                        className={`flex flex-wrap items-center gap-2 text-sm py-2 px-2 rounded cursor-pointer hover:bg-blue-50 ${itemSeleccionado === item ? 'bg-blue-100 ring-1 ring-blue-300' : ''}`}
                                        onClick={() => { setItemSeleccionado(item); setCantidadAgregar(1); setTipoDestinoSeleccionado(tipoOrigenDestinoFijo ? tipoOrigenDestinoFijo.destino : (item.tipo_inventario || tipos[0] || '')); }}
                                    >
                                        <span className="flex-1 font-medium">{item.producto?.descripcion ?? item.producto_id}</span>
                                        <span className="text-gray-600 text-xs">Cód. proveedor: {item.producto?.codigo_proveedor || '—'}</span>
                                        {item.producto?.codigo_barras && <span className="text-gray-500 text-xs ml-1">| {item.producto.codigo_barras}</span>}
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs ${claseTipoOrigen(item.tipo_inventario)}`} title="Tipo origen">Origen: {item.tipo_inventario}</span>
                                        <span className="text-green-600">Disp: {item.cantidad_disponible ?? 0}</span>
                                        <button type="button" className="bg-gray-200 hover:bg-gray-300 px-2 py-0.5 rounded text-xs" onClick={e => { e.stopPropagation(); setItemSeleccionado(item); setCantidadAgregar(1); setTipoDestinoSeleccionado(tipoOrigenDestinoFijo ? tipoOrigenDestinoFijo.destino : (item.tipo_inventario || tipos[0] || '')); }}>Seleccionar</button>
                                    </div>
                                ))}
                            </div>
                            {itemSeleccionado && (
                                <div className="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Producto seleccionado</p>
                                    <p className="text-sm text-gray-800 mb-3">
                                        <span className="font-medium">{itemSeleccionado.producto?.descripcion ?? itemSeleccionado.producto_id}</span>
                                        {itemSeleccionado.producto?.codigo_proveedor && <span className="text-gray-600 ml-2">· Cód. proveedor: {itemSeleccionado.producto.codigo_proveedor}</span>}
                                        <span className="ml-2 inline-flex items-center"><span className={`px-2 py-0.5 rounded text-xs ${claseTipoOrigen(itemSeleccionado.tipo_inventario)}`}>Origen: {itemSeleccionado.tipo_inventario}</span><span className="text-gray-500 ml-2">· Disponible: {itemSeleccionado.cantidad_disponible ?? 0}</span></span>
                                    </p>
                                    <div className="flex flex-wrap items-end gap-4">
                                        <div className="min-w-[140px]">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Tipo destino</label>
                                            {tipoOrigenDestinoFijo ? (
                                                <div className="border border-gray-300 rounded-md px-3 py-2 text-sm bg-gray-100 text-gray-700">
                                                    {tipoOrigenDestinoFijo.destino}
                                                </div>
                                            ) : (
                                                <select className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" value={tipoDestinoEfectivoNuevaOrden} onChange={e => setTipoDestinoSeleccionado(e.target.value)}>
                                                    {opcionesDestinoNuevaOrden.map(t => <option key={t} value={t}>{t}</option>)}
                                                </select>
                                            )}
                                        </div>
                                        <div className="min-w-[120px]">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Cantidad <span className="text-gray-500 font-normal">(máx. {itemSeleccionado.cantidad_disponible ?? 0})</span></label>
                                            <input
                                                type="number"
                                                min={1}
                                                max={itemSeleccionado.cantidad_disponible ?? 1}
                                                className="w-full border border-gray-300 rounded-md px-3 py-2 font-medium"
                                                value={cantidadAgregar}
                                                onChange={e => setCantidadAgregar(Math.max(1, Math.min(itemSeleccionado.cantidad_disponible ?? 1, parseInt(e.target.value, 10) || 1)))}
                                            />
                                        </div>
                                        <div className="flex gap-2">
                                            <button type="button" className="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700" onClick={() => agregarAlCarrito(itemSeleccionado, cantidadAgregar, tipoDestinoEfectivoNuevaOrden)}>Agregar al carrito</button>
                                            <button type="button" className="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100" onClick={() => { setItemSeleccionado(null); setCantidadAgregar(1); setTipoDestinoSeleccionado(''); }}>Cancelar</button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                        <div className="mb-4">
                            <label className="block text-sm font-medium mb-2">Ítems de la orden</label>
                            <div className="border rounded overflow-hidden max-h-48 overflow-y-auto">
                                {carrito.length === 0 ? (
                                    <p className="text-sm text-gray-500 p-4 text-center">No hay ítems. Busque productos arriba y agregue al carrito.</p>
                                ) : (
                                    <table className="w-full text-sm">
                                        <thead className="bg-gray-100 sticky top-0">
                                            <tr>
                                                <th className="text-left p-2 font-medium text-gray-700">Códigos</th>
                                                <th className="text-left p-2 font-medium text-gray-700">Descripción</th>
                                                <th className="text-left p-2 font-medium text-gray-700">Origen</th>
                                                <th className="text-left p-2 font-medium text-gray-700">Destino</th>
                                                <th className="text-center p-2 font-medium text-gray-700 w-20">Cant.</th>
                                                <th className="text-right p-2 font-medium text-gray-700 w-20">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {carrito.map((c, i) => (
                                                <tr key={i} className="border-t border-gray-100 hover:bg-gray-50">
                                                    <td className="p-2 text-gray-600 whitespace-nowrap">
                                                        <span className="text-xs">{c.producto?.codigo_barras ?? '—'}</span>
                                                        {c.producto?.codigo_proveedor != null && (
                                                            <span className="block text-xs text-gray-500">{c.producto.codigo_proveedor}</span>
                                                        )}
                                                    </td>
                                                    <td className="p-2 font-medium max-w-[200px] truncate" title={c.producto?.descripcion}>{c.producto?.descripcion ?? c.producto_id}</td>
                                                    <td className="p-2">
                                                        <span className={`inline-flex px-2 py-0.5 rounded text-xs ${claseTipoOrigen(c.tipo_inventario_origen)}`}>{c.tipo_inventario_origen}</span>
                                                    </td>
                                                    <td className="p-2">
                                                        <span className={`inline-flex px-2 py-0.5 rounded text-xs ${claseTipoOrigen(c.tipo_inventario_destino)}`}>{c.tipo_inventario_destino}</span>
                                                    </td>
                                                    <td className="p-2 text-center font-medium">{c.cantidad}</td>
                                                    <td className="p-2 text-right">
                                                        <button type="button" className="text-red-600 hover:underline text-xs font-medium" onClick={() => quitarDelCarrito(i)}>Quitar</button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" className="px-4 py-2 border rounded" onClick={() => { setModalNueva(false); setProveedorSeleccionado(''); }}>Cancelar</button>
                            <button type="button" className="px-4 py-2 bg-green-600 text-white rounded" onClick={enviarOrden} disabled={carrito.length === 0 || !(destinoObligatorioMiTienda ? miSucursalId : destinoId) || loading}>Enviar orden</button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal detalle */}
            {modalDetalle && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-auto p-5">
                        <h3 className="font-semibold mb-2">Orden #{modalDetalle.id}</h3>
                        <p><strong>Origen:</strong> {getOrigen(modalDetalle)?.nombre ?? modalDetalle.sucursal_origen_id} | <strong>Destino:</strong> {getDestino(modalDetalle)?.nombre ?? modalDetalle.sucursal_destino_id}</p>
                        <p><strong>Estado:</strong> {modalDetalle.estado}</p>
                        <div className="my-3">
                            <strong>Ítems:</strong>
                            <table className="w-full text-sm border border-gray-200 rounded mt-2">
                                <thead className="bg-gray-100">
                                    <tr>
                                        <th className="text-left p-2">Producto</th>
                                        <th className="text-center p-2 w-24">Recibido</th>
                                        <th className="text-center p-2 w-28">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {modalDetalle.items?.map((it) => {
                                        const recibido = Number(it.cantidad_recibida ?? 0);
                                        const pendiente = it.cantidad - recibido;
                                        const yaRecibido = pendiente <= 0;
                                        return (
                                            <tr key={it.id} className="border-t border-gray-100">
                                                <td className="p-2">
                                                    <span className="font-medium">{it.producto?.descripcion ?? it.producto_id}</span>
                                                    <span className="text-gray-500 text-xs block">x{it.cantidad} ({it.tipo_inventario_origen} → {it.tipo_inventario_destino})</span>
                                                </td>
                                                <td className="p-2 text-center">
                                                    {yaRecibido ? <span className="text-green-700 font-medium">{recibido}/{it.cantidad}</span> : <span>{recibido}/{it.cantidad}</span>}
                                                </td>
                                                <td className="p-2 text-center">
                                                    {puedeAceptar(modalDetalle) && !yaRecibido && (
                                                        <button type="button" className="px-2 py-1 bg-green-600 text-white rounded text-xs" onClick={() => confirmarRecepcionItem(modalDetalle, it)} disabled={loading}>Confirmar recepción</button>
                                                    )}
                                                    {yaRecibido && <span className="text-green-600 text-xs">Recibido</span>}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        {puedeAceptar(modalDetalle) && (
                            <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p className="text-sm text-green-800 mb-2">Como sucursal destino, acepta la orden para que los inventarios se recarguen.</p>
                                <button type="button" className="px-3 py-1.5 bg-green-600 text-white rounded font-medium" onClick={() => aceptarOrden(modalDetalle)}>
                                    Aceptar orden y recargar inventario
                                </button>
                            </div>
                        )}
                        {modalDetalle?.devolucion_proveedor_confirmada_at && (
                            <p className="mt-2 text-sm text-green-700">Devolución por proveedor confirmada</p>
                        )}
                        {puedeConfirmarDevolucionProveedor(modalDetalle) && (() => {
                            const itemsTransf = (modalDetalle.items || []).filter(it => String(it.tipo_inventario_destino || '').toUpperCase() === 'TRANSFERIDO_AL_PROVEEDOR');
                            return (
                                <div className="mt-4 p-4 border border-amber-200 rounded-lg bg-amber-50">
                                    <h4 className="font-semibold text-amber-900 mb-2">Confirmar devolución por proveedor</h4>
                                    <p className="text-sm text-amber-800 mb-3">Indique la cantidad a confirmar y el destino final de cada ítem devuelto por el proveedor.</p>
                                    <table className="w-full text-sm border border-amber-200 rounded mb-3">
                                        <thead className="bg-amber-100">
                                            <tr>
                                                <th className="text-left p-2">Producto</th>
                                                <th className="text-center p-2 w-20">Cant. total</th>
                                                <th className="text-center p-2 w-28">Cant. a confirmar</th>
                                                <th className="text-left p-2 min-w-[160px]">Destino final</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {itemsTransf.map((it) => (
                                                <tr key={it.id} className="border-t border-amber-100">
                                                    <td className="p-2">{it.producto?.descripcion ?? it.producto_id}</td>
                                                    <td className="p-2 text-center font-medium">{it.cantidad}</td>
                                                    <td className="p-2">
                                                        <input
                                                            type="number"
                                                            min={1}
                                                            max={it.cantidad}
                                                            className="w-full border border-amber-300 rounded px-2 py-1 text-sm text-center"
                                                            value={devolucionProveedorCantidadPorItem[it.id] ?? it.cantidad}
                                                            onChange={e => setDevolucionProveedorCantidadPorItem(prev => ({ ...prev, [it.id]: e.target.value }))}
                                                        />
                                                    </td>
                                                    <td className="p-2">
                                                        <select
                                                            className="w-full border border-amber-300 rounded px-2 py-1.5 text-sm"
                                                            value={devolucionProveedorTiposPorItem[it.id] ?? 'DISPONIBLE_EN_TIENDA'}
                                                            onChange={e => setDevolucionProveedorTiposPorItem(prev => ({ ...prev, [it.id]: e.target.value }))}
                                                        >
                                                            {opcionesDestinoDevolucionProveedor.map(t => (
                                                                <option key={t} value={t}>{getLabelDestinoDevolucion(t)}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <button type="button" className="px-4 py-2 bg-amber-600 text-white rounded text-sm" onClick={confirmarDevolucionProveedor} disabled={loading}>{loading ? 'Procesando...' : 'Confirmar devolución'}</button>
                                </div>
                            );
                        })()}
                        <a href={modalDetalle ? `/reportes/transferencia-garantia/${modalDetalle.id}` : '#'} target="_blank" rel="noopener noreferrer" className="mt-2 ml-2 px-3 py-1.5 border rounded inline-block">Ver reporte</a>
                        <button type="button" className="mt-2 ml-2 px-3 py-1.5 border rounded" onClick={() => setModalDetalle(null)}>Cerrar</button>
                    </div>
                </div>
            )}

            </div>
    );
}
