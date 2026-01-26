import { useHotkeys } from "react-hotkeys-hook";

export default function InventarioForzado({
    setporcenganancia,
    productosInventario,
    qBuscarInventario,
    setQBuscarInventario,
    type,

    changeInventario,
    printTickedPrecio,

    Invnum,
    setInvnum,
    InvorderColumn,
    setInvorderColumn,
    InvorderBy,
    setInvorderBy,
    inputBuscarInventario, 
    guardarNuevoProductoLote,

    proveedoresList,
    number,
    refsInpInvList,
    categorias,
    
    setSameGanancia,
    setSameCat,
    setSamePro,
    sameCatValue,
    sameProValue,
    busquedaAvanazadaInv,
    setbusquedaAvanazadaInv,

    busqAvanzInputsFun,
    busqAvanzInputs,
    buscarInvAvanz,

    setCtxBulto,
    setStockMin,
    setPrecioAlterno,
    reporteInventario,
    exportPendientes,

    openmodalhistoricoproducto,

    showmodalhistoricoproducto,
    setshowmodalhistoricoproducto,
    fecha1modalhistoricoproducto,
    setfecha1modalhistoricoproducto,
    fecha2modalhistoricoproducto,
    setfecha2modalhistoricoproducto,
    usuariomodalhistoricoproducto,
    setusuariomodalhistoricoproducto,
    usuariosData,

    datamodalhistoricoproducto,
    setdatamodalhistoricoproducto,
    getmovientoinventariounitario,
    user,

    selectRepleceProducto,
    replaceProducto,
    setreplaceProducto,
    saveReplaceProducto,
    getPorcentajeInventario,
    cleanInventario,

    openBarcodeScan,
    
}){
    useHotkeys(
        "esc",
        () => {
            inputBuscarInventario.current.value = "";
            inputBuscarInventario.current.focus();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        []
    );
    const getPorGanacia = (precio,base) => {
        return 0;
        try{
            let por = 0

            precio = parseFloat(precio)
            base = parseFloat(base)

            let dif = precio-base

            por = ((dif*100)/base).toFixed(2)
            if (por) {
                return (dif<0?"":"+")+por+"%"

            }else{
                return ""

            }
        }catch(err){
            return ""
        }
    } 
    return (
        <div className="inventario-forzado">
            {showmodalhistoricoproducto && (
                <div className="fixed inset-0 flex items-center justify-center overflow-x-hidden overflow-y-auto" style={{ zIndex: 1500 }}>
                    {/* Overlay/Backdrop */}
                    <div 
                        className="fixed inset-0 transition-opacity bg-black bg-opacity-50" 
                        onClick={() => setshowmodalhistoricoproducto(false)}
                        aria-hidden="true"
                    ></div>
                    
                    {/* Modal Content */}
                    <div className="relative w-full max-w-8xl mx-4 my-6 bg-white rounded-lg shadow-xl max-h-[90vh] flex flex-col transform transition-all">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between p-6 border-b border-gray-200 rounded-t-lg">
                            <h3 className="text-xl font-semibold text-gray-900">
                                Historial de Producto
                            </h3>
                            <button 
                                type="button" 
                                className="p-2 text-gray-400 transition-colors duration-200 rounded-lg hover:text-gray-600 hover:bg-gray-100" 
                                onClick={() => setshowmodalhistoricoproducto(false)}
                                aria-label="Cerrar modal"
                            >
                                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        
                        {/* Product Information */}
                        {(() => {
                            // Obtener el ID del producto del primer elemento del historial
                            const currentProductId = datamodalhistoricoproducto.length > 0 ? datamodalhistoricoproducto[0].id_producto : null;
                            
                            // Buscar la información completa del producto en productosInventario
                            const currentProduct = currentProductId ? productosInventario.find(p => p.id === currentProductId) : null;
                            
                            if (!currentProduct) return null;
                            
                            return (
                                <div className="px-6 py-4 border-b border-gray-200 bg-blue-50">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <span className="text-xs font-medium tracking-wide text-gray-500 uppercase">ID Producto</span>
                                            <p className="text-sm font-semibold text-gray-900">{currentProduct.id}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-medium tracking-wide text-gray-500 uppercase">Código de Barras</span>
                                            <p className="text-sm font-semibold text-gray-900">{currentProduct.codigo_barras || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-medium tracking-wide text-gray-500 uppercase">Código Proveedor</span>
                                            <p className="text-sm font-semibold text-gray-900">{currentProduct.codigo_proveedor || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-medium tracking-wide text-gray-500 uppercase">Descripción</span>
                                            <p className="text-sm font-semibold text-gray-900 truncate" title={currentProduct.descripcion}>
                                                {currentProduct.descripcion || 'N/A'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })()}
                        
                        {/* Modal Body */}
                        <div className="flex-1 p-6 overflow-y-auto">
                            {/* Search Filters */}
                            <div className="p-4 mb-6 rounded-lg bg-gray-50">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <label className="block text-sm font-medium text-gray-700">
                                            Usuario
                                        </label>
                                        <select
                                            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            value={usuariomodalhistoricoproducto}
                                            onChange={e => setusuariomodalhistoricoproducto(e.target.value)}
                                        >
                                            <option value="">Seleccione Usuario</option>
                                            {usuariosData?.length && usuariosData.map(e => (
                                                <option value={e.id} key={e.id}>{e.usuario}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="block text-sm font-medium text-gray-700">
                                            Fecha Inicio
                                        </label>
                                        <input 
                                            type="date" 
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            value={fecha1modalhistoricoproducto}
                                            onChange={e => setfecha1modalhistoricoproducto(e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="block text-sm font-medium text-gray-700">
                                            Fecha Fin
                                        </label>
                                        <input 
                                            type="date" 
                                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            value={fecha2modalhistoricoproducto}
                                            onChange={e => setfecha2modalhistoricoproducto(e.target.value)}
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end mt-4">
                                    <button 
                                        className="inline-flex items-center px-4 py-2 text-sm font-medium text-white transition-colors duration-200 bg-blue-600 rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        onClick={() => getmovientoinventariounitario()}
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Buscar
                                    </button>
                                </div>
                            </div>

                            {/* Content with Table and Summary */}
                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
                                {/* Table Section */}
                                <div className="lg:col-span-3">
                                    <div className="overflow-x-auto bg-white border border-gray-200 rounded-lg">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Usuario
                                                    </th>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Producto
                                                    </th>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Origen
                                                    </th>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Cantidad
                                                    </th>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Ct. Después
                                                    </th>
                                                    <th className="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                        Fecha
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {datamodalhistoricoproducto.map((e, index) => (
                                                    <tr 
                                                        key={e.id}
                                                        className={index % 2 === 0 ? 'bg-white' : 'bg-gray-50 hover:bg-gray-100'}
                                                    >
                                                        <td className="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                                            {e.usuario?.usuario || '-'}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                                            {e.id_producto}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                                            {e.origen}
                                                        </td>
                                                        <td className={`px-6 py-4 whitespace-nowrap text-sm font-medium ${
                                                            e.cantidad < 0 ? 'text-red-600' : 'text-green-600'
                                                        }`}>
                                                            {e.cantidad > 0 ? '+' : ''}{parseFloat(e.cantidad).toFixed(2)}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                                            {parseFloat(e.cantidadafter).toFixed(2)}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                                            {e.created_at}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Summary Section */}
                                <div className="lg:col-span-1">
                                    <div className="p-4 bg-white border border-gray-200 rounded-lg">
                                        <h4 className="flex items-center mb-4 text-lg font-medium text-gray-900">
                                            <svg className="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                            Resumen por Tipo
                                        </h4>
                                        
                                        <div className="space-y-3 overflow-y-auto">
                                            {(() => {
                                                // Función para extraer el tipo de movimiento sin el número
                                                const extractMovementType = (origen) => {
                                                    if (!origen) return 'SIN_TIPO';
                                                    // Remover todo lo que esté después de # seguido de números
                                                    return origen.replace(/#\d+/g, '').trim();
                                                };
                                                
                                                // Función para obtener la descripción del tipo de movimiento
                                                const getMovementDescription = (tipo) => {
                                                    const descriptions = {
                                                        'inItPd': 'VENTA',
                                                        'delItemPedido': 'ELI.VENTA',
                                                        'updItemPedido': 'ACT.VENTA'
                                                    };
                                                    return descriptions[tipo] ? ` (${descriptions[tipo]})` : '';
                                                };

                                                // Agrupar y sumar por tipo de movimiento
                                                const summary = datamodalhistoricoproducto.reduce((acc, item) => {
                                                    const tipo = extractMovementType(item.origen);
                                                    if (!acc[tipo]) {
                                                        acc[tipo] = {
                                                            total: 0,
                                                            positivos: 0,
                                                            negativos: 0,
                                                            count: 0
                                                        };
                                                    }
                                                    acc[tipo].total += parseFloat(item.cantidad || 0);
                                                    acc[tipo].count += 1;
                                                    
                                                    if (item.cantidad > 0) {
                                                        acc[tipo].positivos += parseFloat(item.cantidad);
                                                    } else {
                                                        acc[tipo].negativos += parseFloat(item.cantidad);
                                                    }
                                                    
                                                    return acc;
                                                }, {});

                                                // Convertir a array y ordenar por total absoluto
                                                return Object.entries(summary)
                                                    .sort(([,a], [,b]) => Math.abs(b.total) - Math.abs(a.total))
                                                    .map(([tipo, data]) => (
                                                        <div key={tipo} className="p-3 border border-gray-100 rounded-lg bg-gray-50">
                                                            <div className="flex items-center justify-between mb-2">
                                                                <h5 className="text-sm font-medium text-gray-800 truncate" title={tipo}>
                                                                    {tipo}{getMovementDescription(tipo)}
                                                                </h5>
                                                                <span className="px-2 py-1 text-xs text-gray-500 bg-gray-200 rounded-full">
                                                                    {data.count}
                                                                </span>
                                                            </div>
                                                            
                                                            <div className="space-y-1">
                                                                <div className={`text-sm font-semibold ${
                                                                    data.total > 0 ? 'text-green-600' : 
                                                                    data.total < 0 ? 'text-red-600' : 'text-gray-600'
                                                                }`}>
                                                                    Total: {data.total > 0 ? '+' : ''}{data.total}
                                                                </div>
                                                                
                                                                {data.positivos > 0 && (
                                                                    <div className="text-xs text-green-600">
                                                                        ↗ +{data.positivos}
                                                                    </div>
                                                                )}
                                                                
                                                                {data.negativos < 0 && (
                                                                    <div className="text-xs text-red-600">
                                                                        ↘ {data.negativos}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ));
                                            })()}
                                        </div>

                                        {datamodalhistoricoproducto.length === 0 && (
                                            <div className="py-8 text-center">
                                                <svg className="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                                <p className="mt-2 text-sm text-gray-500">
                                                    Sin datos para resumir
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Empty State */}
                            {datamodalhistoricoproducto.length === 0 && (
                                <div className="py-12 text-center">
                                    <svg className="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No hay datos</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        No se encontraron movimientos para los filtros seleccionados.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

           {/*  {replaceProducto?
                <div className="m-2">
                    {replaceProducto.este?
                        <>
                            <button className="mb-2 btn btn-outline-danger w-100 w-md-auto mb-md-0" onClick={()=>setreplaceProducto({poreste: null, este: null})}>{replaceProducto.este}</button>
                            <span className="fw-bold ms-1 me-1 d-none d-md-inline">{">"}</span>
                        </> 
                    :null}

                    {replaceProducto.poreste?
                        <>
                            <button className="mb-2 btn btn-outline-success w-100 w-md-auto mb-md-0" onClick={()=>setreplaceProducto({...replaceProducto, poreste: null})}> {replaceProducto.poreste}</button>
                            <button className="btn btn-outline-success btn-sm ms-2 w-100 w-md-auto" onClick={saveReplaceProducto}><i className="fa fa-paper-plane"></i></button>
                        </> 
                    :null}
                </div>
            :null} */}

            <div className="py-3 mb-4 bg-white shadow-sm toolbar sticky-top">
                <div className="row g-3">
                    <div className="col-12 col-md-6">
                        <div className="search-box">
                            {busquedaAvanazadaInv ? (
                                <div className="advanced-search">
                                    <div className="row g-2">
                                        <div className="col-12 col-md-4">
                                            <div className="input-group">
                                                <span className="input-group-text">
                                                    <i className="fas fa-barcode"></i>
                                                </span>
                                                <input
                                                    type="text"
                                                    className="form-control"
                                                    onChange={e => busqAvanzInputsFun(e, "codigo_barras")}
                                                    value={busqAvanzInputs["codigo_barras"]}
                                                    placeholder="Código de barras"
                                                />
                                            </div>
                                        </div>
                                        <div className="col-12 col-md-4">
                                            <div className="input-group">
                                                <span className="input-group-text">
                                                    <i className="fas fa-hashtag"></i>
                                                </span>
                                                <input
                                                    type="text"
                                                    className="form-control"
                                                    onChange={e => busqAvanzInputsFun(e, "codigo_proveedor")}
                                                    value={busqAvanzInputs["codigo_proveedor"]}
                                                    placeholder="Código proveedor"
                                                />
                                            </div>
                                        </div>
                                        <div className="col-12 col-md-4">
                                            <select
                                                className="form-select"
                                                onChange={e => busqAvanzInputsFun(e, "id_proveedor")}
                                                value={busqAvanzInputs["id_proveedor"]}
                                            >
                                                <option value="">Seleccione Proveedor</option>
                                                {proveedoresList.map(e => (
                                                    <option value={e.id} key={e.id}>{e.descripcion}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="input-group input-group-lg">
                                    <span className="input-group-text">
                                        <i className="fas fa-search"></i>
                                    </span>
                                    <input
                                        type="text"
                                        ref={inputBuscarInventario}
                                        className="form-control"
                                        placeholder="Buscar... (ESC para limpiar)"
                                        onChange={e => setQBuscarInventario(e.target.value)}
                                        value={qBuscarInventario}
                                    />
                                    <span className="input-group-text" onClick={() => openBarcodeScan("qBuscarInventario")}>
                                        <i className="fas fa-barcode"></i>
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="col-12 col-md-6">
                        <div className="gap-2 d-flex flex-column flex-md-row justify-content-end">
                            <div className="mb-2 btn-group w-100 w-md-auto mb-md-0">
                                <select
                                    className="form-select"
                                    value={Invnum}
                                    onChange={e => setInvnum(e.target.value)}
                                >
                                    <option value="25">25 registros</option>
                                    <option value="50">50 registros</option>
                                    <option value="100">100 registros</option>
                                    <option value="500">500 registros</option>
                                    <option value="2000">2000 registros</option>
                                    <option value="10000">10000 registros</option>
                                </select>
                                <select
                                    className="form-select"
                                    value={InvorderBy}
                                    onChange={e => setInvorderBy(e.target.value)}
                                >
                                    <option value="asc">Ascendente</option>
                                    <option value="desc">Descendente</option>
                                </select>
                            </div>
                            <div className="btn-group w-100 w-md-auto">
                                <button className="mb-2 btn btn-primary w-100 w-md-auto mb-md-0" onClick={getPorcentajeInventario}>
                                    <i className="fas fa-percentage me-2"></i>
                                    % Inventariado
                                </button>
                                <button className="mb-2 btn btn-info w-100 w-md-auto mb-md-0" onClick={reporteInventario}>
                                    <i className="fas fa-file-alt me-2"></i>
                                    Reporte
                                </button>
                                {user.iscentral && (
                                    <button 
                                        className="mb-2 btn btn-primary w-100 w-md-auto mb-md-0"
                                        onClick={() => changeInventario(null, null, null, "add")}
                                    >
                                        <i className="fas fa-plus me-2"></i>
                                        Nuevo (F2)
                                    </button>
                                )}
                                {busquedaAvanazadaInv && (
                                    <button 
                                        className="mb-2 btn btn-primary w-100 w-md-auto mb-md-0"
                                        onClick={buscarInvAvanz}
                                    >
                                        <i className="fas fa-search me-2"></i>
                                        Buscar
                                    </button>
                                )}
                                {user.iscentral && (
                                    <button 
                                        className="btn btn-success w-100 w-md-auto"
                                        onClick={guardarNuevoProductoLote}
                                    >
                                        <i className="fas fa-save me-2"></i>
                                        Guardar (F1)
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {/* <a href="#" onClick={() => setbusquedaAvanazadaInv(!busquedaAvanazadaInv)} className="mb-3 d-block">Búsqueda {busquedaAvanazadaInv ? "sencilla" :"avanazada"}</a>
             */}
            {/* Mobile Sort Controls */}
            <div className="mb-3">
                <div className="flex-wrap gap-2 d-flex">
                    <div className="d-flex align-items-center">
                        <span className="badge bg-sinapsis me-2">
                            <i className="fas fa-shield-alt"></i>
                        </span>
                        <small>Garantía</small>
                    </div>
                    <div className="d-flex align-items-center">
                        <span className="badge bg-info me-2">
                            <i className="fas fa-clock"></i>
                        </span>
                        <small>Pendiente por Retirar</small>
                    </div>
                    <div className="d-flex align-items-center">
                        <span className="badge bg-secondary me-2">
                            <i className="fas fa-paper-plane"></i>
                        </span>
                        <small>Pendiente por Enviar</small>
                    </div>
                </div>
            </div>
            <div className="mb-3 d-md-none">
                <div className="row g-2">
                    <div className="col-6">
                        <select
                            className="form-select"
                            value={InvorderColumn}
                            onChange={e => setInvorderColumn(e.target.value)}
                        >
                            <option value="id">ID</option>
                            <option value="codigo_proveedor">Código Alterno</option>
                            <option value="codigo_barras">Código Barras</option>
                            <option value="unidad">Unidad</option>
                            <option value="descripcion">Descripción</option>
                            <option value="cantidad">Cantidad</option>
                            <option value="precio_base">Precio Base</option>
                            <option value="precio">Precio Venta</option>
                        </select>
                    </div>
                    <div className="col-6">
                        <select
                            className="form-select"
                            value={InvorderBy}
                            onChange={e => setInvorderBy(e.target.value)}
                        >
                            <option value="asc">Ascendente</option>
                            <option value="desc">Descendente</option>
                        </select>
                    </div>
                </div>
            </div>

            <form ref={refsInpInvList} onSubmit={e=>e.preventDefault()}>
                {/* Desktop Table View */}
                <div className="table-responsive d-none d-md-block">
                    <table className="table table-hover">
                        <thead className="table-light">
                            <tr>
                                <th onClick={() => setInvorderColumn("id")}>
                                    ID
                                </th>
                                <th onClick={() => setInvorderColumn("codigo_proveedor")}>
                                    CÓDIGO ALTERNO
                                </th>
                                <th onClick={() => setInvorderColumn("codigo_barras")}>
                                    CÓDIGO BARRAS
                                </th>
                                <th onClick={() => setInvorderColumn("unidad")}>
                                    UNIDAD
                                </th>
                                <th onClick={() => setInvorderColumn("descripcion")}>
                                    DESCRIPCIÓN
                                </th>
                                <th>
                                    <div className="d-flex align-items-center">
                                        <span onClick={() => setInvorderColumn("cantidad")}>CANTIDAD</span>
                                        <span className="mx-2">/</span>
                                        <span onClick={() => setInvorderColumn("push")}>INVENTARIO</span>
                                    </div>
                                </th>
                                <th onClick={() => setInvorderColumn("precio_base")}>
                                    PRECIO BASE
                                </th>
                                <th>
                                    <div className="d-flex align-items-center justify-content-between">
                                        <span onClick={() => setInvorderColumn("precio")}>PRECIO VENTA</span>
                                        <button 
                                            className="btn btn-sm btn-outline-success"
                                            onClick={setSameGanancia}
                                        >
                                            <i className="fas fa-percentage me-1"></i>
                                            % General
                                        </button>
                                    </div>
                                </th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            {productosInventario.map((e, i) => (
                                <tr 
                                    key={i}
                                    className={`${e.push ? 'table-success' : 'table-danger'} align-middle`}
                                    onDoubleClick={() => {
                                        if (!e.push) {
                                            changeInventario(null, i, e.id, "update");
                                        }
                                    }}
                                >
                                    <td>
                                        <span 
                                            className="cursor-pointer text-primary"
                                            onClick={() => selectRepleceProducto(e.id)}
                                        >
                                            {e.id}
                                        </span>
                                    </td>
                                    {type(e.type) ? (
                                        <>
                                            <td>{e.codigo_proveedor}</td>
                                            <td>{e.codigo_barras}</td>
                                            <td>{e.unidad}</td>
                                            <td>{e.descripcion}</td>
                                            <td>
                                                <div className="gap-2 d-flex align-items-center">
                                                    <span>{e.cantidad}</span>
                                                    {/* <span className="badge bg-sinapsis ms-2" title="Garantía">
                                                        <i className="fas fa-shield-alt"></i> {e.garantia || 0}
                                                    </span>
                                                    <span className="badge bg-info ms-1" title="Pendiente por Retirar">
                                                        <i className="fas fa-clock"></i> {e.ppr || 0}
                                                    </span>
                                                    <span className="badge bg-secondary ms-1" title="Pendiente por Enviar">
                                                        <i className="fas fa-paper-plane"></i> {e.pendiente_enviar || 0}
                                                    </span> */}
                                                    <button 
                                                        className="btn btn-sm btn-outline-primary"
                                                        onClick={() => openmodalhistoricoproducto(e.id)}
                                                    >
                                                        <i className="fas fa-history"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>{e.precio_base}</td>
                                            <td>
                                                <div className="d-flex flex-column">
                                                    <span className="fw-bold">{e.precio}</span>
                                                    <small className="text-success">
                                                        {getPorGanacia(e.precio || 0, e.precio_base || 0)}
                                                    </small>
                                                </div>
                                            </td>
                                        </>
                                    ) : (
                                        <>
                                            <td>
                                                <input
                                                    type="text"
                                                    className="form-control form-control-sm"
                                                    value={e.codigo_proveedor || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "codigo_proveedor")}
                                                    placeholder="Código proveedor..."
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    required
                                                    className={`form-control form-control-sm ${!e.codigo_barras ? 'is-invalid' : ''}`}
                                                    value={e.codigo_barras || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "codigo_barras")}
                                                    placeholder="Código barras..."
                                                />
                                            </td>
                                            <td>
                                                <select
                                                    className={`form-control form-control-sm ${!e.unidad ? 'is-invalid' : ''}`}
                                                    value={e.unidad || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "unidad")}
                                                >
                                                    <option value="">Seleccione...</option>
                                                    <option value="UND">UND</option>
                                                    <option value="PAR">PAR</option>
                                                    <option value="JUEGO">JUEGO</option>
                                                    <option value="PQT">PQT</option>
                                                    <option value="MTR">MTR</option>
                                                    <option value="KG">KG</option>
                                                    <option value="GRS">GRS</option>
                                                    <option value="LTR">LTR</option>
                                                    <option value="ML">ML</option>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea
                                                    className={`form-control form-control-sm ${!e.descripcion ? 'is-invalid' : ''}`}
                                                    value={e.descripcion || ""}
                                                    onChange={e => changeInventario(e.target.value.replace("\n", ""), i, e.id, "changeInput", "descripcion")}
                                                    placeholder="Descripción..."
                                                    rows="2"
                                                />
                                            </td>
                                            <td>
                                                <div className="gap-2 d-flex flex-column">
                                                    <input
                                                        type="text"
                                                        required
                                                        className={`form-control form-control-sm ${!e.cantidad ? 'is-invalid' : ''}`}
                                                        value={e.cantidad || ""}
                                                        onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "cantidad")}
                                                        placeholder="Cantidad..."
                                                    />
                                                    <button
                                                        className={`btn btn-sm ${e.push ? 'btn-success' : 'btn-secondary'}`}
                                                        onClick={() => changeInventario(!e.push, i, e.id, "changeInput", "push")}
                                                    >
                                                        {e.push ? 'Inventariado' : 'No inventariado'}
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    required
                                                    className={`form-control form-control-sm ${!e.precio_base ? 'is-invalid' : ''}`}
                                                    value={e.precio_base || ""}
                                                    onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "precio_base")}
                                                    placeholder="Precio base..."
                                                />
                                            </td>
                                            <td>
                                                <div className="gap-2 d-flex flex-column">
                                                    <div className="input-group input-group-sm">
                                                        <input
                                                            type="text"
                                                            required
                                                            className={`form-control ${!e.precio ? 'is-invalid' : ''}`}
                                                            value={e.precio || ""}
                                                            onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "precio")}
                                                            placeholder="Precio venta..."
                                                        />
                                                        <button
                                                            className="btn btn-outline-secondary"
                                                            onClick={() => setporcenganancia("list", e.precio_base, (precio) => {
                                                                changeInventario(precio, i, e.id, "changeInput", "precio");
                                                            })}
                                                        >
                                                            <i className="fas fa-percentage"></i>
                                                        </button>
                                                    </div>
                                                    <small className="text-success">
                                                        {getPorGanacia(e.precio || 0, e.precio_base || 0)}
                                                    </small>
                                                </div>
                                            </td>
                                        </>
                                    )}
                                    <td>
                                        <div className="btn-group">
                                            <button
                                                className="btn btn-sm btn-outline-primary"
                                                onClick={() => changeInventario(null, i, e.id, "update")}
                                            >
                                                <i className="fas fa-edit"></i>
                                            </button>
                                            <button
                                                className="btn btn-sm btn-outline-danger"
                                                onClick={() => changeInventario(null, i, e.id, "delete")}
                                            >
                                                <i className="fas fa-trash"></i>
                                            </button>
                                            <span className="btn-sm btn btn-warning" onClick={() => printTickedPrecio(e.id)}><i className="fa fa-print"></i></span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Mobile Card View */}
                <div className="d-md-none">
                    {productosInventario.map((e, i) => (
                        <div 
                            key={i}
                            className={`card mb-3 ${e.push ? 'border-success' : 'border-danger'} ${!type(e.type) ? 'edit-mode' : ''}`}
                            onDoubleClick={() => {
                                if (!e.push) {
                                    changeInventario(null, i, e.id, "update");
                                }
                            }}
                        >
                            <div className={`card-header d-flex justify-content-between align-items-center ${!type(e.type) ? 'bg-primary bg-opacity-10' : ''}`}>
                                <span 
                                    className="cursor-pointer text-primary"
                                    onClick={() => selectRepleceProducto(e.id)}
                                >
                                    {e.id}
                                </span>
                                <div className="gap-1 d-flex">
                                    <span className="btn-sm btn btn-warning" onClick={() => printTickedPrecio(e.id)}>
                                        <i className="fa fa-print"></i>
                                    </span>
                                    <button 
                                        className="btn btn-sm btn-outline-primary"
                                        onClick={() => openmodalhistoricoproducto(e.id)}
                                    >
                                        <i className="fas fa-history"></i>
                                    </button>
                                    <button
                                        className="btn btn-sm btn-outline-danger"
                                        onClick={() => changeInventario(null, i, e.id, "delete")}
                                    >
                                        <i className="fas fa-trash"></i>
                                    </button>
                                    <button
                                        className="btn btn-sm btn-outline-primary"
                                        onClick={() => changeInventario(null, i, e.id, "update")}
                                    >
                                        <i className="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            <div className={`card-body ${!type(e.type) ? 'bg-light border-top border-primary border-opacity-25' : ''}`}>
                                {type(e.type) ? (
                                    <>
                                        <div className="mb-2 row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Código Alterno</small>
                                                <div>{e.codigo_proveedor}</div>
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Código Barras</small>
                                                <div>{e.codigo_barras}</div>
                                            </div>
                                        </div>
                                        <div className="mb-2 row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Unidad</small>
                                                <div>{e.unidad}</div>
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Cantidad</small>
                                                <div className="fw-bold fs-5 text-primary">
                                                    {e.cantidad}  
                                                </div>
                                                <div className="gap-2 d-flex">
                                                    <span className="badge bg-sinapsis badge-sm" title="Garantía">
                                                        <i className="fas fa-shield-alt fa-xs"></i> {e.garantia || 0}
                                                    </span>
                                                    <span className="badge bg-info badge-sm" title="Pendiente por Retirar">
                                                        <i className="fas fa-clock fa-xs"></i> {e.ppr || 0}
                                                    </span>
                                                    <span className="badge bg-secondary badge-sm" title="Pendiente por Enviar">
                                                        <i className="fas fa-paper-plane fa-xs"></i> {e.pendiente_enviar || 0}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mb-2">
                                            <small className="text-muted">Descripción</small>
                                            <div>{e.descripcion}</div>
                                        </div>
                                        <div className="row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Precio Base</small>
                                                <div>{e.precio_base}</div>
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Precio Venta</small>
                                                <div>
                                                    <span className="fw-bold">{e.precio}</span>
                                                    <small className="text-success d-block">
                                                        {getPorGanacia(e.precio || 0, e.precio_base || 0)}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div className="top-0 gap-2 mt-2 end-0 me-2 d-flex">
                                            <button
                                                className="btn btn-sm btn-primary"
                                                onClick={() => changeInventario(null, i, e.id, "update")}
                                            >
                                                <i className="fas fa-edit"></i>
                                            </button>

                                            <button
                                                className="btn btn-sm btn-sinapsis"
                                                onDoubleClick={() => {
                                                    let valor = prompt("Ingrese La cantidad Util");
                                                    if (valor !== null) {
                                                        let newvalor = parseFloat(e.cantidad) + parseFloat(valor)
                                                        changeInventario(newvalor, i, e.id, "changeInput", "cantidad")
                                                    }
                                                }}
                                            >
                                                <i className="fas fa-box-open"></i>
                                            </button>


                                        </div>
                                        <div className="mb-2 row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Código Alterno</small>
                                                <input
                                                    type="text"
                                                    className="form-control form-control-sm"
                                                    value={e.codigo_proveedor || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "codigo_proveedor")}
                                                    placeholder="Código proveedor..."
                                                />
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Código Barras</small>
                                                <input
                                                    type="text"
                                                    required
                                                    className={`form-control form-control-sm ${!e.codigo_barras ? 'is-invalid' : ''}`}
                                                    value={e.codigo_barras || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "codigo_barras")}
                                                    placeholder="Código barras..."
                                                />
                                            </div>
                                        </div>
                                        <div className="mb-2 row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Unidad</small>
                                                <select
                                                    className={`form-control form-control-sm ${!e.unidad ? 'is-invalid' : ''}`}
                                                    value={e.unidad || ""}
                                                    onChange={e => changeInventario(e.target.value, i, e.id, "changeInput", "unidad")}
                                                >
                                                    <option value="">Seleccione...</option>
                                                    <option value="UND">UND</option>
                                                    <option value="PAR">PAR</option>
                                                    <option value="JUEGO">JUEGO</option>
                                                    <option value="PQT">PQT</option>
                                                    <option value="MTR">MTR</option>
                                                    <option value="KG">KG</option>
                                                    <option value="GRS">GRS</option>
                                                    <option value="LTR">LTR</option>
                                                    <option value="ML">ML</option>
                                                </select>
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Cantidad</small>
                                                <input
                                                    type="text"
                                                    required
                                                    className={`form-control form-control-sm fw-bold fs-5 ${!e.cantidad ? 'is-invalid' : ''}`}
                                                    value={e.cantidad || ""}
                                                    onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "cantidad")}
                                                    placeholder="Cantidad..."
                                                />
                                            </div>
                                        </div>
                                        <div className="mb-2">
                                            <small className="text-muted">Descripción</small>
                                            <textarea
                                                className={`form-control form-control-sm ${!e.descripcion ? 'is-invalid' : ''}`}
                                                value={e.descripcion || ""}
                                                onChange={e => changeInventario(e.target.value.replace("\n", ""), i, e.id, "changeInput", "descripcion")}
                                                placeholder="Descripción..."
                                                rows="2"
                                            />
                                        </div>
                                        <div className="mb-2 row g-2">
                                            <div className="col-6">
                                                <small className="text-muted">Precio Base</small>
                                                <input
                                                    type="text"
                                                    required
                                                    className={`form-control form-control-sm ${!e.precio_base ? 'is-invalid' : ''}`}
                                                    value={e.precio_base || ""}
                                                    onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "precio_base")}
                                                    placeholder="Precio base..."
                                                />
                                            </div>
                                            <div className="col-6">
                                                <small className="text-muted">Precio Venta</small>
                                                <div className="input-group input-group-sm">
                                                    <input
                                                        type="text"
                                                        required
                                                        className={`form-control ${!e.precio ? 'is-invalid' : ''}`}
                                                        value={e.precio || ""}
                                                        onChange={e => changeInventario(number(e.target.value), i, e.id, "changeInput", "precio")}
                                                        placeholder="Precio venta..."
                                                    />
                                                    <button
                                                        className="btn btn-outline-secondary"
                                                        onClick={() => setporcenganancia("list", e.precio_base, (precio) => {
                                                            changeInventario(precio, i, e.id, "changeInput", "precio");
                                                        })}
                                                    >
                                                        <i className="fas fa-percentage"></i>
                                                    </button>
                                                </div>
                                                <small className="text-success">
                                                    {getPorGanacia(e.precio || 0, e.precio_base || 0)}
                                                </small>
                                            </div>
                                        </div>
                                        <div className="mt-2">
                                            <button
                                                className={`btn btn-sm w-100 ${e.push ? 'btn-success' : 'btn-secondary'}`}
                                                onClick={() => changeInventario(!e.push, i, e.id, "changeInput", "push")}
                                            >
                                                {e.push ? 'Inventariado' : 'No inventariado'}
                                            </button>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </form>
        </div>    
    )
}