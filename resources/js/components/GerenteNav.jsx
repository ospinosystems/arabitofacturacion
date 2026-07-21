import React from "react";

/**
 * Barra de navegación del módulo de Administración (GERENTE). Análoga a DiciNav: se muestra
 * en todas las vistas de administración para moverse entre ellas con tamaño consistente.
 * Solo la ve el gerente/superadmin (tipo 1/6).
 */
export default function GerenteNav({
    view,
    setView,
    subViewInventario,
    setsubViewInventario,
    reportefiscal,
    numReporteZ,
    setnumReporteZ,
    user,
}) {
    // Separación estricta por rol: esta barra es del gerente. El DICI NO la ve.
    if (![1, 6].includes(Number(user?.tipo_usuario))) return null;

    const ir = (v) => { if (typeof setView === "function") setView(v); };
    const irInv = (sub) => {
        ir("inventario");
        if (typeof setsubViewInventario === "function") setsubViewInventario(sub);
    };

    const enHist = view === "historialVentasCierre";
    const enInv = view === "inventario";
    const sub = subViewInventario;

    return (
        <div className="px-3 py-2 mx-2 mt-2 mb-3 bg-white border rounded shadow-sm dici-nav">
            <div className="flex-wrap gap-2 d-flex align-items-center">
                <button
                    className={`btn ${enHist ? "btn-sinapsis" : "btn-outline-secondary"}`}
                    onClick={() => ir("historialVentasCierre")}
                >
                    <i className="fas fa-history me-1"></i>
                    Historial ventas por cierre
                </button>
                <button
                    className={`btn ${enInv && sub === "efectivo" ? "btn-sinapsis" : "btn-outline-secondary"}`}
                    onClick={() => irInv("efectivo")}
                >
                    <i className="fas fa-money-bill-wave me-1"></i>
                    Control de Efectivo
                </button>
                <button
                    className={`btn ${enInv && sub === "estadisticas" ? "btn-sinapsis" : "btn-outline-secondary"}`}
                    onClick={() => irInv("estadisticas")}
                >
                    <i className="fas fa-chart-bar me-1"></i>
                    Estadísticas
                </button>
                <button
                    className={`btn ${enInv && sub === "suministros" ? "btn-sinapsis" : "btn-outline-secondary"}`}
                    onClick={() => irInv("suministros")}
                >
                    <i className="fas fa-box-open me-1"></i>
                    Suministros
                </button>

                {/* Reportes fiscales */}
                <div className="gap-2 ms-auto d-flex flex-wrap align-items-center">
                    <button className="btn btn-outline-primary" onClick={() => reportefiscal && reportefiscal("x")}>
                        <i className="fas fa-file-invoice me-1"></i>
                        Reporte X
                    </button>
                    <button className="btn btn-outline-primary" onClick={() => reportefiscal && reportefiscal("z")}>
                        <i className="fas fa-file-invoice-dollar me-1"></i>
                        Reporte Z
                    </button>
                    <div className="input-group" style={{ maxWidth: "11rem" }}>
                        <span className="input-group-text"><i className="fas fa-hashtag"></i></span>
                        <input
                            className="form-control"
                            type="number"
                            value={numReporteZ || ""}
                            onChange={(e) => setnumReporteZ && setnumReporteZ(e.target.value)}
                            placeholder="Nº Reporte Z"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
