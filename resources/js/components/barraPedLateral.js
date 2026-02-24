export default function({
    pedidosFast,
    pedidosFrontPendientesList = [],
    onClickEditPedido,
    pedidoData,
    addNewPedido,
    addNewPedidoFront,
}){
    const { id=null } = pedidoData

    return (
        
        <>


            {/* Versión Desktop - Una sola barra: Nuevo + todos los tabs (front y normales) juntos */}
            <div className="items-center flex-1 hidden min-w-0 gap-2 md:flex">
                {/* Botón Nuevo Pedido (solo front, F1) - Desktop */}
                {addNewPedidoFront && (
                    <button
                        className="flex items-center flex-shrink-0 gap-1 px-2 py-1 text-xs font-medium text-blue-700 transition-colors border !border-blue-200 rounded bg-blue-50 hover:bg-blue-100"
                        onClick={() => addNewPedidoFront()}
                        title="Nuevo pedido (F1)"
                    >
                        <i className="fa fa-plus"></i>
                        <span>Nuevo</span>
                    </button>
                )}

                {/* Todos los tabs en una sola fila: primero pendientes front, luego pedidos normales */}
                {((pedidosFrontPendientesList?.length > 0) || (pedidosFast && pedidosFast.length > 0)) && (
                    <>
                        <span className="text-xs font-medium text-gray-600 whitespace-nowrap flex-shrink-0">Pedidos:</span>
                        <div className="flex flex-1 gap-1 overflow-x-auto min-w-0">
                            {pedidosFrontPendientesList?.map((pedido) =>
                                pedido ? (
                                    <button
                                        key={pedido.id}
                                        data-id={pedido.id}
                                        onClick={onClickEditPedido}
                                        title={`Pedido pendiente ${String(pedido.id).slice(0, 8)}...`}
                                        style={{ border: "1px solid" }}
                                        className={`px-2 py-1 rounded text-xs font-medium transition-colors flex-shrink-0 min-w-[2.5rem] border-t-4 border-t-blue-500 ${
                                            pedido.id == id
                                                ? "bg-blue-500 text-white !border-blue-200 shadow-sm"
                                                : "bg-blue-50 text-blue-700 !border-blue-200 hover:bg-blue-100"
                                        }`}
                                    >
                                        #{String(pedido.id).slice(0, 8)}
                                    </button>
                                ) : null
                            )}
                            {pedidosFast?.map((pedido) =>
                                pedido ? (
                                    <button
                                        key={pedido.id}
                                        data-id={pedido.id}
                                        onClick={onClickEditPedido}
                                        title={`Pedido #${pedido.id} - ${pedido.estado ? "Completado" : "Pendiente"}`}
                                        style={{ border: "1px solid" }}
                                        className={`px-2 py-1 rounded text-xs font-medium transition-colors flex-shrink-0 min-w-[2.5rem] ${
                                            pedido.id == id
                                                ? pedido.estado
                                                    ? "bg-green-500 text-white !border-green-200 shadow-sm"
                                                    : "bg-orange-500 text-white !border-orange-200 shadow-sm"
                                                : pedido.estado
                                                ? "bg-green-100 text-green-700 !border-green-300 hover:bg-green-200"
                                                : "bg-orange-100 text-orange-700 !border-orange-200 hover:bg-orange-200"
                                        }`}
                                    >
                                        #{pedido.id}
                                    </button>
                                ) : null
                            )}
                        </div>
                    </>
                )}
            </div>

            {/* Versión Mobile - Vertical */}
            <div className="flex items-center flex-1 min-w-0 gap-2 md:hidden">
                {/* Botón Nuevo Pedido (F1) - Mobile */}
                {addNewPedidoFront && (
                    <button
                        className="flex-shrink-0 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-500 p-1.5 rounded font-medium text-xs transition-colors"
                        onClick={() => addNewPedidoFront()}
                        title="Nuevo pedido (F1)"
                    >
                        <i className="fa fa-plus"></i>
                    </button>
                )}

                {/* Dropdown de Pedidos para Mobile */}
                {(pedidosFrontPendientesList?.length > 0 || (pedidosFast && pedidosFast.length > 0)) && (
                    <div className="relative flex-1">
                        <select
                            className="w-full px-2 py-1 pr-8 text-xs bg-white border border-gray-300 rounded appearance-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                            value={id || ""}
                            onChange={(e) => {
                                if (e.target.value && onClickEditPedido) {
                                    const event = {
                                        currentTarget: {
                                            attributes: {
                                                "data-id": {
                                                    value: e.target.value,
                                                },
                                            },
                                        },
                                    };
                                    onClickEditPedido(event);
                                }
                            }}
                        >
                            <option value="">Seleccionar pedido...</option>
                            {pedidosFrontPendientesList?.map((pedido) =>
                                pedido ? (
                                    <option key={pedido.id} value={pedido.id}>
                                        #{String(pedido.id).slice(0, 8)}... (pendiente)
                                    </option>
                                ) : null
                            )}
                            {pedidosFast?.map((pedido) =>
                                pedido ? (
                                    <option key={pedido.id} value={pedido.id}>
                                        #{pedido.id} -{" "}
                                        {pedido.estado
                                            ? "Completado"
                                            : "Pendiente"}
                                    </option>
                                ) : null
                            )}
                        </select>
                        <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <i className="text-xs text-gray-400 fa fa-chevron-down"></i>
                        </div>
                    </div>
                )}

                {/* Indicador Visual del Pedido Actual - Mobile */}
                {id && (
                    <div className="flex-shrink-0">
                        <span
                            className={`inline-block px-2 py-1 rounded text-xs font-medium ${
                                pedidosFrontPendientesList?.some((p) => p?.id == id)
                                    ? "bg-blue-100 text-blue-800 border border-blue-300"
                                    : pedidosFast?.find((p) => p?.id == id)?.estado
                                    ? "bg-green-100 text-green-800 border border-green-300"
                                    : "bg-orange-100 text-orange-800 border border-orange-300"
                            }`}
                        >
                            #{String(id).length > 10 ? String(id).slice(0, 8) + "…" : id}
                        </span>
                    </div>
                )}

            </div>


        </>
    );
}