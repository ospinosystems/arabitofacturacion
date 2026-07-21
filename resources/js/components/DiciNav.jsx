import React from "react";

/**
 * Barra de navegación del módulo DICI.
 * Se muestra en la parte superior de todas las áreas del DICI
 * (Gestión de inventario, Torre de Transferencia, Garantías) para
 * que sea fácil moverse entre ellas.
 */
export default function DiciNav({
    view,
    setView,
    modViewInventario,
    setmodViewInventario,
    subViewInventario,
    setsubViewInventario,
    sincInventario,
    user,
}) {
    // Separación estricta por rol: esta barra es del DICI. El gerente/superadmin NO la ve.
    if (user?.tipo_usuario != 7) return null;

    const modInv = modViewInventario || "list";
    const irA = (vista) => {
        if (typeof setView === "function") setView(vista);
    };
    const irAGestion = (modo) => {
        irA("inventario");
        if (typeof setsubViewInventario === "function") setsubViewInventario("inventario");
        if (typeof setmodViewInventario === "function") setmodViewInventario(modo);
    };
    const irAFacturas = () => {
        irA("inventario");
        if (typeof setsubViewInventario === "function") setsubViewInventario("facturasItems");
    };

    const enGestion = view === "inventario";
    const enTorre = view === "pedidosCentral";
    const enGarantias = view === "garantias";
    const enCiclico = view === "inventario-ciclico";
    const enFacturas = view === "inventario" && subViewInventario === "facturasItems";

    return (
        <div className="px-3 py-2 mx-2 mt-2 mb-3 bg-white border rounded shadow-sm dici-nav">
                {/* Secciones principales */}
                <div className="flex-wrap gap-2 d-flex align-items-center">
                    <button
                        className={`btn ${(enGestion || enCiclico) ? "btn-sinapsis" : "btn-outline-secondary"}`}
                        onClick={() => irAGestion("list")}
                    >
                        <i className="fas fa-boxes me-1"></i>
                        Gestión
                    </button>
                    {(user?.iscentral || user?.tipo_usuario == 7) && (
                        <button
                            className={`btn ${enTorre ? "btn-sinapsis" : "btn-outline-secondary"}`}
                            onClick={() => irA("pedidosCentral")}
                        >
                            <i className="fas fa-tower-broadcast me-1"></i>
                            Torre de Transferencia
                        </button>
                    )}
                    <button
                        className={`btn ${enGarantias ? "btn-sinapsis" : "btn-outline-secondary"}`}
                        onClick={() => irA("garantias")}
                    >
                        <i className="fas fa-shield-alt me-1"></i>
                        Garantías
                    </button>
                </div>

                {/* Sub-barra de Gestión: SIEMPRE visible, para que el nav tenga el mismo tamaño en todas las vistas DICI */}
                <div className="flex-wrap gap-2 pt-2 mt-2 d-flex align-items-center border-top">
                    <small className="text-muted me-1">Gestión:</small>
                    <button
                        className={`btn btn-sm ${enGestion && !enFacturas && modInv === "list" ? "btn-primary" : "btn-outline-primary"}`}
                        onClick={() => irAGestion("list")}
                    >
                        <i className="fas fa-list me-1"></i>
                        Inventario
                    </button>
                    <button
                        className={`btn btn-sm ${enCiclico ? "btn-primary" : "btn-outline-primary"}`}
                        onClick={() => irA("inventario-ciclico")}
                    >
                        <i className="fas fa-clipboard-list me-1"></i>
                        Inventario Cíclico
                    </button>
                    <button
                        className={`btn btn-sm ${enGestion && !enFacturas && modInv === "historico" ? "btn-primary" : "btn-outline-primary"}`}
                        onClick={() => irAGestion("historico")}
                    >
                        <i className="fas fa-history me-1"></i>
                        Histórico
                    </button>
                    <button
                        className={`btn btn-sm ${enFacturas ? "btn-primary" : "btn-outline-primary"}`}
                        onClick={irAFacturas}
                    >
                        <i className="fas fa-file-invoice me-1"></i>
                        Facturas e Items
                    </button>
                    <button
                        className="btn btn-sm btn-outline-success"
                        onClick={() => sincInventario && sincInventario()}
                    >
                        <i className="fas fa-sync me-1"></i>
                        Sincronizar
                    </button>
                </div>
        </div>
    );
}
