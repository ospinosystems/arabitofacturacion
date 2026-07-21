import React, { useState, useEffect, useCallback, useMemo, useRef } from "react";
import db from "../database/database";
import {
    ResponsiveContainer, LineChart, Line, BarChart, Bar, XAxis, YAxis,
    CartesianGrid, Tooltip, Legend,
} from "recharts";

/**
 * Salud de Inventario: qué vende la sucursal, qué se va a acabar y qué no rota, con
 * proyecciones (reposición), utilidad e histórico. Datos 100% locales; el nombre de
 * categoría se trae de central (sin duplicar). Venta = cobrado (estado=1), netas de devolución.
 */
const ORANGE = "#f97316";
const BLUE = "#3b82f6";
const GREEN = "#10b981";

const fmtFecha = (d) => {
    const z = (n) => (n < 10 ? "0" + n : "" + n);
    return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
};
const hoy = () => fmtFecha(new Date());
const hace = (dias) => { const d = new Date(); d.setDate(d.getDate() - dias); return fmtFecha(d); };
const inicioMes = () => { const d = new Date(); return fmtFecha(new Date(d.getFullYear(), d.getMonth(), 1)); };

export default function InventarioAnalyticsModule({ moneda }) {
    const fmt = moneda || ((n) => Number(n || 0).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    const fmtU = (n) => Number(n || 0).toLocaleString("es-VE", { maximumFractionDigits: 0 });

    const [desde, setDesde] = useState(hace(30));
    const [hasta, setHasta] = useState(hoy());
    const [cobertura, setCobertura] = useState(15);
    const [q, setQ] = useState("");
    const [idCategoria, setIdCategoria] = useState("");
    const [minPrecio, setMinPrecio] = useState(""); // rango de precio base (tamaño)
    const [maxPrecio, setMaxPrecio] = useState("");
    const [tamano, setTamano] = useState("todos"); // todos | grandes | medianos | pequenos
    const [vista, setVista] = useState("reposicion"); // reposicion (principal) | resumen | productos | muertos
    const [ordenCol, setOrdenCol] = useState("valor_reponer");
    const [ordenDir, setOrdenDir] = useState("desc");
    const [alerta, setAlerta] = useState("todas"); // todas | sin_stock | critico | alerta
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(50);
    const [total, setTotal] = useState(0);

    const [data, setData] = useState({ kpis: null, serie: [], productos: [] });
    const [categorias, setCategorias] = useState([]);
    const [loading, setLoading] = useState(false);
    const [inicial, setInicial] = useState(true); // true hasta la primera respuesta
    const debRef = useRef(null);

    // catálogo de categorías desde central (no bloquea si falla)
    useEffect(() => {
        db.getCategoriasAnalytics({}).then((r) => {
            const cats = r.data?.categorias || [];
            setCategorias(Array.isArray(cats) ? cats : []);
        }).catch(() => setCategorias([]));
    }, []);

    const vistaParam = vista === "muertos" ? "muertos" : vista === "reposicion" ? "reposicion" : "";

    const cargar = useCallback(() => {
        setLoading(true);
        db.getInventarioAnalytics({
            desde, hasta, cobertura_dias: cobertura, q, id_categoria: idCategoria,
            min_precio: minPrecio || 0, max_precio: maxPrecio || 0,
            alerta: alerta === "todas" ? "" : alerta,
            vista: vistaParam, orden_col: ordenCol, orden_dir: ordenDir, page, per_page: perPage,
        }).then((r) => {
            if (r.data?.estado) {
                setData({ kpis: r.data.kpis, serie: r.data.serie || [], productos: r.data.productos || [] });
                setTotal(r.data.total || 0);
            } else {
                setData({ kpis: null, serie: [], productos: [] });
                setTotal(0);
            }
        }).finally(() => { setLoading(false); setInicial(false); });
    }, [desde, hasta, cobertura, q, idCategoria, minPrecio, maxPrecio, alerta, vistaParam, ordenCol, ordenDir, page, perPage]);

    // recarga al cambiar filtros (q con debounce)
    useEffect(() => {
        if (debRef.current) clearTimeout(debRef.current);
        debRef.current = setTimeout(cargar, 250);
        return () => clearTimeout(debRef.current);
    }, [cargar]);

    // al cambiar cualquier filtro (no la página) volver a la página 1
    useEffect(() => {
        setPage(1);
    }, [desde, hasta, cobertura, q, idCategoria, minPrecio, maxPrecio, alerta, vistaParam, ordenCol, ordenDir, perPage]);

    const aplicarTamano = (t) => {
        setTamano(t);
        if (t === "grandes") { setMinPrecio("100"); setMaxPrecio(""); }
        else if (t === "medianos") { setMinPrecio("20"); setMaxPrecio("100"); }
        else if (t === "pequenos") { setMinPrecio("5"); setMaxPrecio("20"); }
        else if (t === "minimos") { setMinPrecio(""); setMaxPrecio("5"); }
        else { setMinPrecio(""); setMaxPrecio(""); }
    };
    const totalPaginas = Math.max(1, Math.ceil(total / perPage));

    const catNombre = useMemo(() => {
        const m = {};
        categorias.forEach((c) => { m[c.id] = c.descripcion || c.nombre || ("Cat " + c.id); });
        return m;
    }, [categorias]);

    const kpis = data.kpis;
    const productos = data.productos;

    const topUtilidad = useMemo(() =>
        [...productos].sort((a, b) => b.utilidad - a.utilidad).slice(0, 10)
            .map((p) => ({ nombre: (p.descripcion || "").substring(0, 18), utilidad: p.utilidad })), [productos]);

    // Loading inicial (hasta la primera respuesta)
    if (inicial) {
        return (
            <div className="flex flex-col items-center justify-center py-24 text-gray-500">
                <i className="fas fa-spinner fa-spin text-4xl text-orange-500 mb-4"></i>
                <p className="font-semibold text-gray-700">Cargando Fallas…</p>
                <p className="text-xs text-gray-400">Analizando ventas e inventario</p>
            </div>
        );
    }

    const setRango = (d, h) => { setDesde(d); setHasta(h); };
    const ordenar = (col) => {
        if (ordenCol === col) setOrdenDir(ordenDir === "desc" ? "asc" : "desc");
        else { setOrdenCol(col); setOrdenDir("desc"); }
    };
    const flecha = (col) => ordenCol === col ? (ordenDir === "desc" ? " ▾" : " ▴") : "";

    // Alerta de reposición por urgencia (cuán pronto se agota), independiente del valor.
    const severidad = (p) => {
        if (p.unidades > 0 && p.stock <= 0) return { txt: "SIN STOCK", cls: "bg-red-600 text-white" };
        const d = p.dias_inventario;
        if (d === null || d === undefined) return null;
        if (d <= cobertura / 3) return { txt: "CRÍTICO", cls: "bg-red-500 text-white" };
        if (d <= cobertura) return { txt: "ALERTA", cls: "bg-amber-500 text-white" };
        return { txt: "OK", cls: "bg-emerald-100 text-emerald-700" };
    };

    const Kpi = ({ label, value, sub, color }) => (
        <div className="bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
            <div className="text-[11px] uppercase tracking-wide text-gray-400">{label}</div>
            <div className={`text-lg font-bold ${color || "text-gray-800"}`}>{value}</div>
            {sub && <div className="text-[11px] text-gray-400">{sub}</div>}
        </div>
    );

    const btnRango = (txt, on) => (
        <button onClick={on} className="px-2 py-1 text-xs bg-gray-100 hover:bg-orange-100 rounded">{txt}</button>
    );
    const tab = (id, txt, icon) => (
        <button onClick={() => setVista(id)} className={`px-3 py-1.5 text-sm rounded-lg font-semibold ${vista === id ? "bg-orange-500 text-white" : "bg-white text-gray-600 border border-gray-300 hover:bg-orange-50"}`}>
            <i className={`fas ${icon} mr-1`}></i>{txt}
        </button>
    );

    return (
        <div className="p-2 lg:p-4 space-y-3 relative">
            {/* Overlay de carga en cada cambio de filtro */}
            {loading && (
                <div className="absolute inset-0 z-30 bg-white/60 backdrop-blur-[1px] flex items-start justify-center pt-28">
                    <div className="bg-white shadow-lg rounded-lg px-4 py-2 text-orange-600 text-sm font-semibold border border-orange-200">
                        <i className="fas fa-spinner fa-spin mr-2"></i>Actualizando…
                    </div>
                </div>
            )}
            <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-lg font-bold text-gray-800"><i className="fas fa-triangle-exclamation text-orange-500 mr-1"></i>Fallas</h2>
                {loading && <span className="text-xs text-orange-500"><i className="fas fa-spinner fa-spin mr-1"></i>cargando…</span>}
            </div>

            {/* Filtros */}
            <div className="bg-white border border-gray-200 rounded-lg p-3 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-[11px] text-gray-500">Buscar producto</label>
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Descripción o código…" className="px-2 py-1.5 border rounded text-sm w-56" />
                </div>
                <div>
                    <label className="block text-[11px] text-gray-500">Desde</label>
                    <input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="px-2 py-1.5 border rounded text-sm" />
                </div>
                <div>
                    <label className="block text-[11px] text-gray-500">Hasta</label>
                    <input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} className="px-2 py-1.5 border rounded text-sm" />
                </div>
                <div className="flex gap-1">
                    {btnRango("Hoy", () => setRango(hoy(), hoy()))}
                    {btnRango("7 días", () => setRango(hace(6), hoy()))}
                    {btnRango("30 días", () => setRango(hace(29), hoy()))}
                    {btnRango("Mes", () => setRango(inicioMes(), hoy()))}
                </div>
                {categorias.length > 0 && (
                    <div>
                        <label className="block text-[11px] text-gray-500">Categoría</label>
                        <select value={idCategoria} onChange={(e) => setIdCategoria(e.target.value)} className="px-2 py-1.5 border rounded text-sm">
                            <option value="">Todas</option>
                            {categorias.map((c) => <option key={c.id} value={c.id}>{c.descripcion || c.nombre}</option>)}
                        </select>
                    </div>
                )}
                <div>
                    <label className="block text-[11px] text-gray-500">Cubrir (días)</label>
                    <select value={cobertura} onChange={(e) => setCobertura(parseInt(e.target.value))} className="px-2 py-1.5 border rounded text-sm">
                        <option value={7}>7</option>
                        <option value={15}>15</option>
                        <option value={30}>30</option>
                        <option value={60}>60</option>
                        <option value={90}>90</option>
                    </select>
                </div>
                <div>
                    <label className="block text-[11px] text-gray-500">Tamaño (precio base)</label>
                    <div className="flex gap-1 items-center">
                        {[["todos", "Todos"], ["grandes", "Grandes >$100"], ["medianos", "Medianos $20–100"], ["pequenos", "Pequeños $5–20"], ["minimos", "Mínimos <$5"]].map(([k, txt]) => (
                            <button key={k} onClick={() => aplicarTamano(k)}
                                className={`px-1.5 py-1 text-[11px] rounded ${tamano === k ? "bg-orange-500 text-white" : "bg-gray-100 hover:bg-orange-100"}`}>
                                {txt}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* KPIs */}
            {kpis && (
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
                    <Kpi label="Venta" value={fmt(kpis.venta)} color="text-emerald-600" />
                    <Kpi label="Costo (base)" value={fmt(kpis.base)} />
                    <Kpi label="Utilidad" value={fmt(kpis.utilidad)} sub={`Margen ${kpis.margen}%`} color="text-orange-600" />
                    <Kpi label="Unidades" value={fmtU(kpis.unidades)} sub={`${kpis.dias_periodo} días`} />
                    <Kpi label="Productos vendidos" value={fmtU(kpis.productos_vendidos)} />
                    <Kpi label="Inventario muerto" value={fmtU(kpis.productos_muertos)} sub="sin ventas" color="text-red-600" />
                    <Kpi label="Valor inventario" value={fmt(kpis.valor_inventario_costo)} sub={`venta ${fmt(kpis.valor_inventario_venta)}`} color="text-blue-600" />
                </div>
            )}

            {/* Pestañas */}
            <div className="flex gap-2 flex-wrap">
                {tab("reposicion", "Reposición", "fa-truck-ramp-box")}
                {tab("resumen", "Resumen", "fa-chart-line")}
                {tab("productos", "Productos", "fa-list")}
                {tab("muertos", "Inventario muerto", "fa-ban")}
            </div>

            {/* Filtro por tipo de alerta (no aplica a Resumen ni a Inventario muerto) */}
            {vista !== "resumen" && vista !== "muertos" && (
                <div className="flex gap-1 flex-wrap items-center">
                    <span className="text-[11px] text-gray-500 mr-1">Tipo de alerta:</span>
                    {[
                        ["todas", "Todas", "bg-gray-700 text-white"],
                        ["sin_stock", "Sin stock", "bg-red-600 text-white"],
                        ["critico", "Crítico", "bg-red-500 text-white"],
                        ["alerta", "Alerta", "bg-amber-500 text-white"],
                    ].map(([k, txt, cls]) => (
                        <button key={k} onClick={() => setAlerta(k)}
                            className={`px-2 py-1 text-[11px] rounded font-semibold ${alerta === k ? cls : "bg-gray-100 text-gray-600 hover:bg-gray-200"}`}>
                            {txt}
                        </button>
                    ))}
                </div>
            )}

            {/* Resumen: gráficas */}
            {vista === "resumen" && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div className="bg-white border border-gray-200 rounded-lg p-3">
                        <h3 className="text-sm font-bold text-gray-700 mb-2">Venta y utilidad por día</h3>
                        <ResponsiveContainer width="100%" height={260}>
                            <LineChart data={data.serie}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#eee" />
                                <XAxis dataKey="fecha" fontSize={10} tickFormatter={(v) => (v || "").substring(5)} />
                                <YAxis fontSize={10} />
                                <Tooltip formatter={(v) => fmt(v)} />
                                <Legend />
                                <Line type="monotone" dataKey="venta" name="Venta" stroke={GREEN} strokeWidth={2} dot={false} />
                                <Line type="monotone" dataKey="utilidad" name="Utilidad" stroke={ORANGE} strokeWidth={2} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="bg-white border border-gray-200 rounded-lg p-3">
                        <h3 className="text-sm font-bold text-gray-700 mb-2">Top 10 por utilidad</h3>
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={topUtilidad} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#eee" />
                                <XAxis type="number" fontSize={10} />
                                <YAxis type="category" dataKey="nombre" width={120} fontSize={10} />
                                <Tooltip formatter={(v) => fmt(v)} />
                                <Bar dataKey="utilidad" name="Utilidad" fill={ORANGE} radius={[0, 4, 4, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            )}

            {/* Tabla de productos */}
            {vista !== "resumen" && (
                <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div className="px-3 py-2 text-xs text-gray-500 border-b bg-gray-50 flex items-center justify-between gap-2 flex-wrap">
                        <span>
                            {vista === "muertos" && "Productos con stock que NO se vendieron en el rango."}
                            {vista === "reposicion" && `Productos que faltan para cubrir ${cobertura} días de venta.`}
                            {vista === "productos" && "Todos los productos con su venta, utilidad y rotación."}
                            {" "}<b>{total}</b> resultado(s)
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="text-gray-400">Por página:</span>
                            <select value={perPage} onChange={(e) => setPerPage(parseInt(e.target.value))} className="px-1 py-0.5 border rounded text-xs">
                                {[25, 50, 100, 200, 500].map((n) => <option key={n} value={n}>{n}</option>)}
                            </select>
                        </span>
                    </div>
                    <div className="overflow-auto" style={{ maxHeight: "60vh" }}>
                        <table className="w-full text-xs">
                            <thead className="bg-gray-100 sticky top-0 text-gray-500 uppercase text-[10px]">
                                <tr>
                                    {vista !== "muertos" && <th className="px-2 py-1.5 text-center">Alerta</th>}
                                    <th className="px-2 py-1.5 text-left cursor-pointer" onClick={() => ordenar("descripcion")}>Producto{flecha("descripcion")}</th>
                                    <th className="px-2 py-1.5 text-left">Cód.</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("stock")}>Stock{flecha("stock")}</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("unidades")}>Vendidas{flecha("unidades")}</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("venta")}>Venta{flecha("venta")}</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("utilidad")}>Utilidad{flecha("utilidad")}</th>
                                    <th className="px-2 py-1.5 text-end">VDP</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("dias_inventario")}>Días inv.{flecha("dias_inventario")}</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("necesarias")}>Faltan ({cobertura}d){flecha("necesarias")}</th>
                                    <th className="px-2 py-1.5 text-end cursor-pointer" onClick={() => ordenar("valor_reponer")}>Valor reponer{flecha("valor_reponer")}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {productos.length === 0 ? (
                                    <tr><td colSpan={11} className="text-center text-gray-400 py-6">Sin datos para el filtro.</td></tr>
                                ) : productos.map((p) => {
                                    const sev = severidad(p);
                                    return (
                                    <tr key={p.id} className={`hover:bg-orange-50 ${p.muerto ? "bg-red-50/40" : (sev && (sev.txt === "SIN STOCK" || sev.txt === "CRÍTICO") ? "bg-red-50/30" : "")}`}>
                                        {vista !== "muertos" && (
                                            <td className="px-2 py-1.5 text-center">
                                                {sev ? <span className={`px-1.5 py-0.5 rounded text-[9px] font-bold ${sev.cls}`}>{sev.txt}</span> : <span className="text-gray-300">—</span>}
                                            </td>
                                        )}
                                        <td className="px-2 py-1.5">
                                            {p.descripcion}
                                            {p.muerto && <span className="ml-1 px-1 bg-red-200 text-red-700 rounded text-[9px] font-bold">MUERTO</span>}
                                            {p.id_categoria && catNombre[p.id_categoria] && <span className="ml-1 text-[9px] text-gray-400">· {catNombre[p.id_categoria]}</span>}
                                        </td>
                                        <td className="px-2 py-1.5 font-mono text-gray-500">{p.codigo_barras || p.codigo_proveedor || "-"}</td>
                                        <td className="px-2 py-1.5 text-end">{fmtU(p.stock)}</td>
                                        <td className="px-2 py-1.5 text-end font-semibold">{fmtU(p.unidades)}</td>
                                        <td className="px-2 py-1.5 text-end text-emerald-600">{fmt(p.venta)}</td>
                                        <td className="px-2 py-1.5 text-end text-orange-600 font-semibold">{fmt(p.utilidad)}</td>
                                        <td className="px-2 py-1.5 text-end">{p.vdp}</td>
                                        <td className="px-2 py-1.5 text-end">{p.dias_inventario === null ? "∞" : p.dias_inventario}</td>
                                        <td className={`px-2 py-1.5 text-end font-bold ${p.necesarias > 0 ? "text-red-600" : "text-gray-400"}`}>{p.necesarias > 0 ? fmtU(p.necesarias) : "—"}</td>
                                        <td className="px-2 py-1.5 text-end font-bold text-blue-700">{p.valor_reponer > 0 ? fmt(p.valor_reponer) : "—"}</td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-3 py-2 border-t bg-gray-50 flex items-center justify-between text-xs">
                        <span className="text-gray-500">Página {page} de {totalPaginas}</span>
                        <div className="flex gap-1">
                            <button disabled={page <= 1} onClick={() => setPage(1)} className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">«</button>
                            <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">‹ Ant.</button>
                            <button disabled={page >= totalPaginas} onClick={() => setPage(page + 1)} className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">Sig. ›</button>
                            <button disabled={page >= totalPaginas} onClick={() => setPage(totalPaginas)} className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">»</button>
                        </div>
                    </div>
                </div>
            )}
            <p className="text-[10px] text-gray-400">Venta = pedidos cobrados (netos de devolución). Costo y utilidad estimados con el precio base actual. VDP = venta diaria promedio del rango.</p>
        </div>
    );
}
