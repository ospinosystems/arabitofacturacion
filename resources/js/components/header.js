import React, { useState } from "react";
import logo from "../../images/logo.png";
import carrito from "../../images/carrito1.png";
import BarraPedLateral from "./barraPedLateral";

function Header({
    updatetasasfromCentral,
    user,
    logout,
    getip,
    settoggleClientesBtn,
    toggleClientesBtn,
    getVentasClick,
    dolar,
    peso,
    view,
    setView,
    setMoneda,
    getPedidos,
    setViewCaja,
    viewCaja,
    setShowModalMovimientos,
    showModalMovimientos,
    auth,
    isCierre,
    abrirModuloCierre,
    setsubViewInventario,
    subViewInventario,
    setmodViewInventario,
    sincInventario,
    reportefiscal,
    // Props para pedidos
    pedidosFast,
    pedidosFrontPendientesList = [],
    onClickEditPedido,
    currentPedidoId,
    addNewPedido,
    addNewPedidoFront,
    pedidoData,
}) {
    const [updatingDollar, setUpdatingDollar] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    // Grupos colapsables del sidebar reorganizado por rol
    const [openDici, setOpenDici] = useState(false);
    const [openAdmin, setOpenAdmin] = useState(false);

    // Separación estricta por rol: el DICI (tipo 7) ve SOLO el Módulo DICI; el gerente/superadmin
    // (tipo 1/6) ve SOLO el Módulo Administración. Ninguno ve el módulo del otro.
    const verModuloDici = user.tipo_usuario == 7;
    const verModuloAdmin = user.tipo_usuario == 1 || user.tipo_usuario == 6;

    // Helpers de navegación del sidebar (centralizan setView + subvistas y cierran el panel).
    const irInventario = (sub = "inventario", mod = null) => {
        setView("inventario");
        if (typeof setsubViewInventario === "function") setsubViewInventario(sub);
        if (mod !== null && typeof setmodViewInventario === "function") setmodViewInventario(mod);
        setSidebarOpen(false);
    };
    const irVista = (v) => { setView(v); setSidebarOpen(false); };
    const itemCls = (activo) =>
        `w-full flex items-center px-3 py-1.5 rounded text-sm font-medium transition-colors text-left ${activo ? "bg-orange-50 text-orange-800" : "text-gray-600 hover:bg-gray-50"}`;

    const handleUpdateDollar = async (e) => {
        const tipo = e.currentTarget.attributes["data-type"].value;

        // First confirmation
        if (!window.confirm("¿Está seguro que desea actualizar la tasa?")) {
            return;
        }

        // Second confirmation
        if (
            !window.confirm(
                "Esta acción no se puede deshacer. ¿Desea continuar?"
            )
        ) {
            return;
        }

        if (tipo === "1") {
            setUpdatingDollar(true);
            try {
                await setMoneda(e);
            } finally {
                setUpdatingDollar(false);
            }
        } else {
            setMoneda(e);
        }
    };

    return (
        <>
            {/* Overlay para móvil */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black bg-opacity-50"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar limpio y elegante */}
            <div
                className={`fixed left-0 top-0 h-full w-full z-[2040] sm:w-72 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                    sidebarOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                {/* Header del Sidebar */}
                <div className="p-4 border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <img
                                src={logo}
                                alt="Sinapsis"
                                className="w-auto h-6"
                            />
                            <div>
                                <h1 className="text-sm font-bold text-gray-800">
                                    {user.sucursal}
                                </h1>
                                <p className="text-xs text-gray-600">
                                    {user.nombre}
                                </p>
                            </div>
                        </div>
                        <button
                            className="sm:p-1  !p-3 text-gray-600 rounded hover:bg-gray-100"
                            onClick={() => setSidebarOpen(false)}
                        >
                            <i className="text-base text-black sm:text-sm fa fa-times"></i>
                        </button>
                    </div>
                </div>

                {/* Contenido del Sidebar */}
                <div className="h-full p-3 overflow-y-auto">
                    <nav className="space-y-1">
                        {user.tipo_usuario == 8 ? (
                            /* Navegación para Pasillero (tipo 8) - Solo TCD y TCR Pasillero */
                            <>
                                <button
                                    className={`w-full flex items-center px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                        view === "tcd-pasillero"
                                            ? "bg-green-100 text-green-800 border-l-4 border-green-800"
                                            : "text-gray-700 hover:bg-gray-100"
                                    }`}
                                    onClick={() => {
                                        window.open("/warehouse-inventory/tcd/pasillero", "_blank");
                                    }}
                                >
                                    <i className="w-4 mr-2 fa fa-warehouse"></i>
                                    TCD Pasillero
                                </button>

                                <button
                                    className={`w-full flex items-center px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                        view === "tcr-pasillero"
                                            ? "bg-green-100 text-green-800 border-l-4 border-green-800"
                                            : "text-gray-700 hover:bg-gray-100"
                                    }`}
                                    onClick={() => {
                                        window.open("/warehouse-inventory/tcr/pasillero", "_blank");
                                    }}
                                >
                                    <i className="w-4 mr-2 fa fa-warehouse"></i>
                                    TCR Pasillero
                                </button>
                            </>
                        ) : (
                            /* Navegación reorganizada por rol (no pasillero) */
                            <>
                                {/* 1 · Módulo DICI */}
                                {verModuloDici && (
                                    <div className="space-y-1">
                                        <button
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded text-sm font-semibold transition-colors text-left ${openDici ? "bg-orange-100 text-orange-800" : "text-gray-700 hover:bg-gray-100"}`}
                                            onClick={() => setOpenDici(!openDici)}
                                        >
                                            <span className="flex items-center"><i className="w-4 mr-2 fa fa-boxes"></i>Módulo DICI</span>
                                            <i className={`fa fa-chevron-${openDici ? "up" : "down"} text-xs`}></i>
                                        </button>
                                        {openDici && (
                                            <div className="pl-2 ml-3 space-y-1 border-l border-gray-200">
                                                <div className="px-3 pt-1 text-[10px] font-semibold tracking-wide text-gray-400 uppercase">Gestión</div>
                                                <button className={itemCls(view === "inventario" && subViewInventario === "inventario")} onClick={() => irInventario("inventario", "list")}><i className="w-4 mr-2 fa fa-list"></i>Inventario</button>
                                                <button className={itemCls(view === "inventario-ciclico")} onClick={() => irVista("inventario-ciclico")}><i className="w-4 mr-2 fa fa-clipboard-list"></i>Inventario Cíclico</button>
                                                <button className={itemCls(false)} onClick={() => irInventario("inventario", "historico")}><i className="w-4 mr-2 fa fa-history"></i>Histórico</button>
                                                <button className={itemCls(false)} onClick={() => { sincInventario && sincInventario(); setSidebarOpen(false); }}><i className="w-4 mr-2 fa fa-sync"></i>Sincronizar</button>
                                                <button className={itemCls(view === "inventario" && subViewInventario === "facturasItems")} onClick={() => irInventario("facturasItems")}><i className="w-4 mr-2 fa fa-file-invoice"></i>Facturas e Items</button>
                                                <div className="my-1 border-t border-gray-100"></div>
                                                <button className={itemCls(view === "pedidosCentral")} onClick={() => irVista("pedidosCentral")}><i className="w-4 mr-2 fa fa-tower-broadcast"></i>Torre de transferencias (TCD, TCR)</button>
                                                <button className={itemCls(view === "garantias")} onClick={() => irVista("garantias")}><i className="w-4 mr-2 fa fa-shield-alt"></i>Garantías</button>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* 2 · Módulo Administración (Gerente) */}
                                {verModuloAdmin && (
                                    <div className="space-y-1">
                                        <button
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded text-sm font-semibold transition-colors text-left ${openAdmin ? "bg-orange-100 text-orange-800" : "text-gray-700 hover:bg-gray-100"}`}
                                            onClick={() => setOpenAdmin(!openAdmin)}
                                        >
                                            <span className="flex items-center"><i className="w-4 mr-2 fa fa-user-tie"></i>Administración (Gerente)</span>
                                            <i className={`fa fa-chevron-${openAdmin ? "up" : "down"} text-xs`}></i>
                                        </button>
                                        {openAdmin && (
                                            <div className="pl-2 ml-3 space-y-1 border-l border-gray-200">
                                                <button className={itemCls(view === "historialVentasCierre")} onClick={() => irVista("historialVentasCierre")}><i className="w-4 mr-2 fa fa-history"></i>Historial ventas por cierre</button>
                                                <button className={itemCls(view === "pedidosCentral")} onClick={() => irVista("pedidosCentral")}><i className="w-4 mr-2 fa fa-tower-broadcast"></i>Torre de transferencias (TCD, TCR) — solo ver</button>
                                                <button className={itemCls(view === "inventario" && subViewInventario === "efectivo")} onClick={() => irInventario("efectivo")}><i className="w-4 mr-2 fa fa-money-bill-wave"></i>Control de Efectivo</button>
                                                <button className={itemCls(view === "inventario" && subViewInventario === "estadisticas")} onClick={() => irInventario("estadisticas")}><i className="w-4 mr-2 fa fa-chart-bar"></i>Estadísticas</button>
                                                <button className={itemCls(view === "inventario" && subViewInventario === "suministros")} onClick={() => irInventario("suministros")}><i className="w-4 mr-2 fa fa-box-open"></i>Suministros</button>
                                                <button className={itemCls(false)} onClick={() => { reportefiscal && reportefiscal("x"); setSidebarOpen(false); }}><i className="w-4 mr-2 fa fa-file-invoice"></i>Reporte X</button>
                                                <button className={itemCls(false)} onClick={() => { reportefiscal && reportefiscal("z"); setSidebarOpen(false); }}><i className="w-4 mr-2 fa fa-file-invoice-dollar"></i>Reporte Z</button>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {(verModuloDici || verModuloAdmin) && <div className="my-2 border-t border-gray-200"></div>}

                                {!verModuloDici && auth(1) && (
                                    <button
                                        className={`w-full flex items-center px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                            view === "ventas"
                                                ? "bg-orange-100 text-orange-800 border-l-4 border-orange-800"
                                                : "text-gray-700 hover:bg-gray-100"
                                        }`}
                                        onClick={() => {
                                            setView("ventas");
                                            getVentasClick();
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-chart-line"></i>
                                        Ventas
                                    </button>
                                )}

                                {!verModuloDici && auth(3) && (
                                    <button
                                        className={`w-full flex items-center px-3  py-2 rounded text-sm font-medium transition-colors text-left ${
                                            view === "pedidos"
                                                ? "bg-orange-100 text-orange-800 border-l-4 border-orange-800"
                                                : "text-gray-700 hover:bg-gray-100"
                                        }`}
                                        onClick={() => {
                                            setView("pedidos");
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-calculator"></i>
                                        Pedidos
                                    </button>
                                )}

                                {/* Fallas — visible para DICI y gerente. Color distintivo (rojo) para que resalte. */}
                                {(verModuloDici || verModuloAdmin) && (
                                    <button
                                        className={`w-full flex items-center px-3 py-2 rounded text-sm font-bold transition-colors text-left border-l-4 ${
                                            view === "inventario-analytics"
                                                ? "bg-red-600 text-white border-red-800"
                                                : "bg-red-50 text-red-700 border-red-500 hover:bg-red-100"
                                        }`}
                                        onClick={() => {
                                            setView("inventario-analytics");
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-triangle-exclamation"></i>
                                        Fallas
                                    </button>
                                )}

                                {!verModuloDici && auth(3) && (
                                    <button
                                        className={`w-full flex items-center px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                            view === "presupuestos"
                                                ? "bg-blue-100 text-blue-800 border-l-4 border-blue-800"
                                                : "text-gray-700 hover:bg-gray-100"
                                        }`}
                                        onClick={() => {
                                            setView("presupuestos");
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-file-alt"></i>
                                        Presupuestos
                                    </button>
                                )}

                                {!verModuloDici && auth(2) && (
                                    <div className="space-y-1">
                                        <button
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                                toggleClientesBtn
                                                    ? "bg-orange-100 text-orange-800"
                                                    : "text-gray-700 hover:bg-gray-100"
                                            }`}
                                            onClick={() =>
                                                settoggleClientesBtn(
                                                    !toggleClientesBtn
                                                )
                                            }
                                        >
                                            <div className="flex items-center">
                                                <i className="w-4 mr-2 fa fa-users"></i>
                                                Clientes
                                            </div>
                                            <i
                                                className={`fa fa-chevron-${
                                                    toggleClientesBtn
                                                        ? "up"
                                                        : "down"
                                                } text-xs`}
                                            ></i>
                                        </button>

                                        {toggleClientesBtn && (
                                            <div className="ml-6 space-y-1">
                                                <button
                                                    className={`w-full flex items-center px-3 py-1.5 rounded text-sm font-medium transition-colors text-left ${
                                                        view === "credito"
                                                            ? "bg-orange-50 text-orange-800"
                                                            : "text-gray-600 hover:bg-gray-50"
                                                    }`}
                                                    onClick={() => {
                                                        setView("credito");
                                                        settoggleClientesBtn(
                                                            false
                                                        );
                                                        setSidebarOpen(false);
                                                    }}
                                                >
                                                    <i className="w-4 mr-2 fa fa-credit-card"></i>
                                                    Cuentas por cobrar
                                                </button>
                                                <button
                                                    className={`w-full flex items-center px-3 py-1.5 rounded text-sm font-medium transition-colors text-left ${
                                                        view === "clientes_crud"
                                                            ? "bg-orange-50 text-orange-800"
                                                            : "text-gray-600 hover:bg-gray-50"
                                                    }`}
                                                    onClick={() => {
                                                        setView(
                                                            "clientes_crud"
                                                        );
                                                        settoggleClientesBtn(
                                                            false
                                                        );
                                                        setSidebarOpen(false);
                                                    }}
                                                >
                                                    <i className="w-4 mr-2 fa fa-user-cog"></i>
                                                    Administrar Clientes
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {!verModuloDici && (
                                    <button
                                        className={`w-full flex items-center px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                                            view === "cierres"
                                                ? "bg-orange-100 text-orange-800 border-l-4 border-orange-800"
                                                : "text-gray-700 hover:bg-gray-100"
                                        }`}
                                        onClick={() => {
                                            abrirModuloCierre();
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-lock"></i>
                                        Cierre
                                    </button>
                                )}

                            </>
                        )}

                        {/* Separador */}
                        <div className="my-3 border-t border-gray-200"></div>

                        {/* Configuración y logout */}
                        <div className="space-y-1">
                            {user.tipo_usuario != 7 &&
                                user.tipo_usuario != 8 &&
                                (user.usuario == "admin" ||
                                    user.usuario == "ao") && (
                                    <button
                                        className="flex items-center w-full px-3 py-2 text-sm font-medium text-left text-gray-700 transition-colors rounded hover:bg-gray-100"
                                        onClick={() => {
                                            setView("configuracion");
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <i className="w-4 mr-2 fa fa-cogs"></i>
                                        Configuración
                                    </button>
                                )}

                            <button
                                className="flex items-center w-full px-3 py-2 text-sm font-medium text-left text-red-600 transition-colors rounded hover:bg-red-50"
                                onClick={() => {
                                    console.log("Botón logout clickeado");
                                    logout();
                                }}
                            >
                                <i className="w-4 mr-2 fa fa-sign-out-alt"></i>
                                Cerrar Sesión
                            </button>
                        </div>
                    </nav>
                </div>
            </div>

            {/* Header unificado */}
            <header className="fixed top-0 left-0 right-0 z-40 w-full bg-white border-b border-orange-200">
                <div className="px-3 py-2">
                    <div className="flex items-center gap-3">
                        {/* Botón hamburguesa y sucursal */}
                        <div className="flex items-center gap-2">
                            <button
                                className="sm:p-1.5 p-[10px] text-gray-600 hover:bg-gray-100 rounded"
                                onClick={() => setSidebarOpen(true)}
                            >
                                <i className="fa fa-bars"></i>
                            </button>
                            <div className="hidden sm:block">
                                <span className="text-sm font-bold text-gray-800">
                                    {user.sucursal}
                                </span>
                            </div>
                        </div>

                        {/* Gestión de pedidos - Más compacta en móvil */}
                        <div className="flex-1 min-w-0">
                            <BarraPedLateral
                                addNewPedido={addNewPedido}
                                addNewPedidoFront={addNewPedidoFront}
                                pedidosFast={pedidosFast}
                                pedidosFrontPendientesList={pedidosFrontPendientesList}
                                onClickEditPedido={onClickEditPedido}
                                pedidoData={pedidoData}
                            />
                        </div>

                        {/* Separador - Oculto en móvil */}
                        <div className="hidden w-px h-5 bg-gray-300 md:block"></div>

                        {/* Botones de acción y tasas - Ocultos en móvil porque están en sidebar */}
                        <div className="items-center hidden gap-2 md:flex">
                            {/* Botón Pedidos */}
                            <button
                                className={`px-2 py-1 !border-orange-200 rounded text-xs font-medium transition-colors ${
                                    view === "pedidos"
                                        ? "bg-orange-100 text-orange-800 border border-orange-800"
                                        : "bg-orange-50 hover:bg-orange-100 text-orange-700 border border-orange-200"
                                }`}
                                onClick={() => setView("pedidos")}
                                title="Ver pedidos"
                            >
                                <i className="mr-1 fa fa-calculator"></i>
                                <span className="hidden lg:inline">
                                    Pedidos
                                </span>
                            </button>

                            {/* Tasas */}
                            <button
                                className="px-2 py-1 text-xs !border-orange-200 font-medium text-orange-700 transition-colors border  rounded bg-orange-50 hover:bg-orange-100 disabled:opacity-50"
                                onClick={handleUpdateDollar}
                                data-type="1"
                                disabled={updatingDollar}
                            >
                                {updatingDollar ? (
                                    <>
                                        <span className="">Cargando...</span>
                                    </>
                                ) : (
                                    <>
                                        <i className="mr-1 fa fa-dollar-sign"></i>
                                        <span className="hidden lg:inline">
                                            {dolar}
                                        </span>
                                        <span className="lg:hidden">
                                            {parseFloat(dolar).toFixed(0)}
                                        </span>
                                    </>
                                )}
                            </button>
                            {user.sucursal == "elorza" && (
                                <button
                                    className="px-2 py-1 text-xs font-medium text-orange-700 transition-colors border !border-orange-200 rounded bg-orange-50 hover:bg-orange-100"
                                    onClick={handleUpdateDollar}
                                    data-type="2"
                                >
                                    <i className="mr-1 fa fa-coins"></i>
                                    <span className="hidden lg:inline">
                                        {parseFloat(peso).toFixed(2)}
                                    </span>
                                    <span className="lg:hidden">
                                        {parseFloat(peso).toFixed(0)}
                                    </span>
                                </button>
                            )}
                        </div>

                        {/* Separador - Oculto en móvil */}

                        {/* Información del usuario */}
                        {/*   <div className="flex items-center gap-2">
              <div className="text-right">
                <p className="text-xs font-semibold text-gray-800">{user.nombre}</p>
                <p className="text-xs text-gray-600">{user.usuario} ({user.role})</p>
              </div>
            </div> */}
                    </div>
                </div>
            </header>
        </>
    );
}

export default Header;
