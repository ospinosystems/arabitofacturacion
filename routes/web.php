<?php

use App\Http\Controllers\InventariosNovedadesController;
use App\Http\Controllers\RetencionesController;
use App\Http\Controllers\TareaslocalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatcajasController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PedidosController;

use App\Http\Controllers\ItemsPedidosController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\MovimientosCajaController;

use App\Http\Controllers\PagoPedidosController;
use App\Http\Controllers\MovimientosController;
use App\Http\Controllers\ProveedoresController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\GarantiaController;


use App\Http\Controllers\MarcasController;
use App\Http\Controllers\DepositosController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ItemsFacturaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\tickera;
use App\Http\Controllers\sendCentral;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\PagosReferenciasController;
use App\Http\Controllers\tickeprecioController;
use App\Http\Controllers\CierresController;
use App\Http\Controllers\MovimientosInventarioController;
use App\Http\Controllers\MovimientosInventariounitarioController;
use App\Http\Controllers\sendCajerosReporteController;
use App\Http\Controllers\ResponsablesController;
use App\Http\Controllers\InventarioGarantiasController;
use App\Http\Controllers\MonedasController;

use App\Http\Controllers\CajasController;
Route::get('checkroutes', [PedidosController::class,"checkroutes"]);
Route::get('checkPed', [PedidosController::class,"checkPed"]);
Route::get('checkCt', [PedidosController::class, "checkCt"]);
Route::get('completePed', [PedidosController::class, "completePed"]);
Route::get('repareId', [PedidosController::class, "repareId"]);



Route::get('/backup', function () {
    \Illuminate\Support\Facades\Artisan::call('database:backup');
    return 'Respaldo Exitoso!';
});

Route::get('/backup-run', function () {

    \Illuminate\Support\Facades\Artisan::call('backup:run');

    return 'Copia de seguridad completada!';

});


Route::get('error', function (){
	return view("layouts.error");
})->name("error");


Route::get('showcajas', [PedidosController::class,"showcajas"]);

Route::get('verde', [InventarioController::class,"verde"]);
Route::get('inv', [sendCentral::class,"inv"]);
Route::get('i', [sendCentral::class,"sendAllLotes"]);
Route::get('sendAllTest', [sendCentral::class,"sendAllTest"]);
Route::get('sendAllMovs', [sendCentral::class,"sendAllMovs"]);


Route::get('', [HomeController::class,"index"]);

Route::get('senComoVamos', [sendCentral::class,"sendComovamos"]);

Route::get('closeAllSession', [HomeController::class,"closeAllSession"]);
Route::post('login', [HomeController::class,"login"]);
Route::post('forceUpdateDollar', [MonedasController::class,"updateDollarRate"]);
Route::get('logout', [HomeController::class,"logout"]);
Route::post('verificarLogin', [HomeController::class,"verificarLogin"]);

Route::get('sucursal', [SucursalController::class,"index"]);
Route::get('setSucursal', [SucursalController::class,"setSucursal"])->name("setSucursal");
Route::get('getSucursal', [SucursalController::class,"getSucursal"]);
Route::post('getMoneda', [MonedasController::class,"getMoneda"]);
Route::post('today', [PedidosController::class,"today"]);
Route::get('getUsuarios', [UsuariosController::class,"getUsuarios"]);


//Fuera de los middlewares debido a que es la ruta mas solicitadad de la app. Mejora el rendimiento al hacer menos calculos
Route::post('getinventario', [InventarioController::class,"index"]);
Route::get('getcatsCajas', [CatcajasController::class,"getcatsCajas"]);
Route::post('getNomina', [sendCentral::class,"getNomina"]);
Route::post('getAlquileres', [sendCentral::class,"getAlquileres"]);



Route::get('/importarcatcajas', [sendCentral::class,"getCatCajas"]);
Route::get('/importarusers', [sendCentral::class,"importarusers"]);
Route::get('/update03052024', [sendCentral::class,"update03052024"]);

Route::get('delitemduplicate', [ItemsPedidosController::class,"delitemduplicate"]);
Route::get('ajustarbalancecajas', [CajasController::class,"ajustarbalancecajas"]);
Route::post('setMoneda', [MonedasController::class,"setMoneda"]);




Route::group(['middleware' => ['auth.user:login']], function () {
	
	Route::group(['middleware' => ['auth.user:caja']], function () {
		Route::post('sendReciboFiscal', [tickera::class,"sendReciboFiscal"]);
		Route::post('sendNotaCredito', [tickera::class,"sendNotaCredito"]);
		
		
		Route::post('sendClavemodal', [HomeController::class,"sendClavemodal"]);

		Route::post('setGastoOperativo', [PagoPedidosController::class,"setGastoOperativo"]);


		Route::post('getStatusCierre', [CierresController::class,"getStatusCierre"]);

		
		Route::get('addNewPedido', [PedidosController::class,"addNewPedido"]);
		Route::get('setCarrito', [InventarioController::class,"setCarrito"]);

		Route::post('getPedidosList', [PedidosController::class,"getPedidosUser"]);
		
		Route::get('getReferenciasElec', [PagosReferenciasController::class,"getReferenciasElec"]);

		Route::post('getVentas', [PedidosController::class,"getVentas"]);
		Route::post('getPedido', [PedidosController::class,"getPedido"]);
		Route::post('getPedidosFast', [PedidosController::class,"getPedidosFast"]);
		Route::post('delItemPedido', [ItemsPedidosController::class,"delItemPedido"]);
		Route::post('changeEntregado', [ItemsPedidosController::class,"changeEntregado"]);
		
		Route::post('setCantidad', [ItemsPedidosController::class,"setCantidad"]);
		Route::post('setpersonacarrito', [PedidosController::class,"setpersonacarrito"]);
		
		Route::post('setPrecioAlternoCarrito', [ItemsPedidosController::class,"setPrecioAlternoCarrito"]);
		
		Route::post('getPedidos', [PedidosController::class,"getPedidos"]);
		
		Route::get('notaentregapedido', [PedidosController::class,"notaentregapedido"]);
		
		Route::post('setDescuentoUnitario', [ItemsPedidosController::class,"setDescuentoUnitario"]);
		Route::post('setDescuentoTotal', [ItemsPedidosController::class,"setDescuentoTotal"]);
	
		Route::post('getpersona', [ClientesController::class,"getpersona"]);
		
		Route::post('setPagoPedido', [PagoPedidosController::class,"setPagoPedido"]);
		Route::get('printBultos', [PedidosController::class,"printBultos"]);
		
		Route::post('setconfigcredito', [PagoPedidosController::class,"setconfigcredito"]);

		Route::post('addRefPago', [PagosReferenciasController::class,"addRefPago"]);
		Route::post('sendRefToMerchant', [PagosReferenciasController::class,"sendRefToMerchant"]);
		Route::post('procesarRespuestaMegasoft', [PagosReferenciasController::class,"procesarRespuestaMegasoft"]);
		Route::post('validarCodigoAprobacion', [PagosReferenciasController::class,"validarCodigoAprobacion"]);
		Route::post('delRefPago', [PagosReferenciasController::class,"delRefPago"]);
		

		Route::post('addRetencionesPago', [RetencionesController::class,"addRetencionesPago"]);
		Route::post('delRetencionPago', [RetencionesController::class,"delRetencionPago"]);

		

		
		
		Route::post('setPagoCredito', [PagoPedidosController::class,"setPagoCredito"]);
		Route::post('updateCreditOrders', [PagoPedidosController::class,"updateCreditOrders"]);
		
		Route::post('getDeudores', [PagoPedidosController::class,"getDeudores"]);
		Route::post('getDeudor', [PagoPedidosController::class,"getDeudor"]);
		Route::post('checkDeuda', [PagoPedidosController::class,"checkDeuda"]);
		
		
		Route::post('entregarVuelto', [PagoPedidosController::class,"entregarVuelto"]);
		
		Route::post('getMovimientosCaja', [MovimientosCajaController::class,"getMovimientosCaja"]);
		Route::post('setMovimientoCaja', [MovimientosCajaController::class,"setMovimientoCaja"]);
		
		Route::post('getMovimientos', [MovimientosController::class,"getMovimientos"]);
		Route::post('getBuscarDevolucion', [InventarioController::class,"index"]);
		
		Route::post('setClienteCrud', [ClientesController::class,"setClienteCrud"]);
		Route::post('getClienteCrud', [ClientesController::class,"getpersona"]);
		Route::post('delCliente', [ClientesController::class,"delCliente"]);
		Route::get('sumpedidos', [PedidosController::class,"sumpedidos"]);
		
		Route::post('imprimirTicked', [tickera::class,"imprimir"]);
		Route::get('resetPrintingState', [App\Http\Controllers\tickera::class, 'resetPrintingState']);

		Route::get('getProductosSerial', [InventarioController::class,"getProductosSerial"]);
		
		Route::post('guardarCierre', [PedidosController::class,"guardarCierre"]);
		Route::get('verCierre', [PedidosController::class,"verCierre"]);
		Route::post('cerrar', [PedidosController::class,"cerrar"]);
		Route::post('getPermisoCierre', [TareaslocalController::class,"getPermisoCierre"]);
		Route::get('sendCuentasporCobrar', [PedidosController::class,"sendCuentasporCobrar"]);
		

		Route::post('changepedidouser', [PedidosController::class,"changepedidouser"]);
		
		Route::post('delpedido', [PedidosController::class,"delpedido"]);
		
	});
	Route::group(['middleware' => ['auth.user:vendedor']], function () {
		// Route::post('getinventario', [InventarioController::class,"index"]);
		// Route::post('setCarrito', [InventarioController::class,"setCarrito"]);
	});
	
	// ================ RUTAS ESPECÍFICAS PARA DICI Y SUPERADMIN ================
	Route::group(['middleware' => ['auth.user:login']], function () {
		
		// ================ RUTAS PARA VALIDACIÓN DE FACTURAS ================
		
		// Validar si un número de factura existe en pedidos
		Route::post('validateFactura', [InventarioController::class,"validateFactura"]);

		// ================ RUTAS PARA BÚSQUEDA DE PRODUCTOS EN INVENTARIO ================
		
		// Buscar productos en inventario por múltiples campos
		Route::post('searchProductosInventario', [InventarioController::class,"searchProductosInventario"]);
		
		// Obtener producto específico por ID
		Route::get('producto/{id}', [InventarioController::class,"getProductoById"]);

		// ================ RUTAS PARA GESTIÓN DE RESPONSABLES ================
		
		// Buscar responsables existentes
		Route::post('searchResponsables', [ResponsablesController::class,"searchResponsables"]);
		
		// Guardar nuevo responsable
		Route::post('saveResponsable', [ResponsablesController::class,"saveResponsable"]);
		
		// Obtener responsable por ID
		Route::get('responsable/{id}', [ResponsablesController::class,"getResponsableById"]);
		
		// Obtener responsables por tipo
		Route::get('responsables/tipo/{tipo}', [ResponsablesController::class,"getResponsablesByTipo"]);

		// ================ RUTAS WEB PARA GARANTÍAS/DEVOLUCIONES ================
		// NOTA: Las rutas API de garantías están en routes/api.php
		
		// Crear garantía/devolución con pedido automático (casos 1-4) - FUNCIONALIDAD WEB
		Route::post('garantias/crear', [GarantiaController::class,"crearGarantiaCompleta"]);
		
		// Crear solo el pedido de garantía/devolución - FUNCIONALIDAD WEB
		Route::post('garantias/crear-pedido', [GarantiaController::class,"crearPedidoGarantia"]);
		
	});
	
	Route::group(['middleware' => ['auth.user:admin']], function () {

		Route::get('cambiarmodotransferencia', [PagosReferenciasController::class,"mostrarCambioModoTransferencia"]);
		Route::post('cambiarmodotransferencia', [PagosReferenciasController::class,"procesarCambioModoTransferencia"]);
		Route::post('generarCodigoSecreto', [PagosReferenciasController::class,"generarCodigoSecreto"]);

		// Ruta rápida de ventas - Solo para GERENTE
		Route::post('getVentasRapido', [PedidosController::class,"getVentasRapido"]);
		
		// Actualización de monedas - Solo para ADMIN

		Route::get('sincInventario', [sendCentral::class,"getAllInventarioFromCentral"]);
		Route::post('reportefiscal', [tickera::class,"reportefiscal"]);

		Route::get('showcsvInventario', [InventarioController::class,"showcsvInventario"]);
		// ================ RUTAS WEB PARA OPERACIONES DE GARANTÍAS ================
		
		// Obtener garantías locales para vista web
		Route::post('getGarantias', [GarantiaController::class,"getGarantias"]);
		
		// Registrar salida de productos por garantía
		Route::post('setSalidaGarantias', [GarantiaController::class,"setSalidaGarantias"]);

		Route::get('openTransferenciaPedido', [InventarioController::class,"openTransferenciaPedido"]);
		Route::post('setPagoPedidoTrans', [PagoPedidosController::class,"setPagoPedidoTrans"]);
		
		Route::post('getControlEfec', [CajasController::class,"getControlEfec"]);
		Route::post('verificarMovPenControlEfec', [sendCentral::class,"verificarMovPenControlEfec"]);
		Route::post('verificarMovPenControlEfecTRANFTRABAJADOR', [sendCentral::class,"verificarMovPenControlEfecTRANFTRABAJADOR"]);
		
		Route::post('aprobarRecepcionCaja', [sendCentral::class,"aprobarRecepcionCaja"]);
		
		
		Route::post('delCaja', [CajasController::class,"delCaja"]);
		
		Route::post('setControlEfec', [CajasController::class,"setControlEfec"]);
		Route::post('reversarMovPendientes', [CajasController::class,"reversarMovPendientes"]);
		
		
		Route::get('delpedidoforce', [PedidosController::class,"delpedidoForce"]);
		Route::get('reversarCierre', [CierresController::class,"reversarCierre"]);
		
		
		
		Route::get('getTareasLocal', [TareaslocalController::class,"getTareasLocal"]);
		Route::get('resolverTareaLocal', [TareaslocalController::class,"resolverTareaLocal"]);
		
		Route::get('getHistoricoInventario', [MovimientosInventarioController::class,"getHistoricoInventario"]);
		Route::get('getmovientoinventariounitario', [MovimientosInventariounitarioController::class,"getmovientoinventariounitario"]);
		Route::post('getSyncProductosCentralSucursal', [InventarioController::class,"getSyncProductosCentralSucursal"]);
		
		Route::post('saveReplaceProducto', [InventarioController::class,"saveReplaceProducto"]);
		Route::post('guardarDeSucursalEnCentral', [InventarioController::class,"guardarDeSucursalEnCentral"]);
		
		
		
		
		/* GastosController */
		
		Route::get('getCierres', [PedidosController::class,"getCierres"]);
		Route::post('setProveedor', [ProveedoresController::class,"setProveedor"]);
		Route::post('guardarNuevoProducto', [InventarioController::class,"guardarNuevoProducto"]);
		
		Route::post('getInventarioNovedades', [InventariosNovedadesController::class,"getInventarioNovedades"]);
		Route::post('resolveInventarioNovedades', [InventariosNovedadesController::class,"resolveInventarioNovedades"]);
		Route::post('sendInventarioNovedades', [InventariosNovedadesController::class,"sendInventarioNovedades"]);
		Route::post('delInventarioNovedades', [InventariosNovedadesController::class,"delInventarioNovedades"]);
		
		
		
		

		Route::post('guardarNuevoProductoLote', [InventarioController::class,"guardarNuevoProductoLote"]);
		Route::post('guardarNuevoProductoLoteFact', [InventarioController::class,"guardarNuevoProductoLoteFact"]);
		Route::post('getPorcentajeInventario', [InventarioController::class,"getPorcentajeInventario"]);
		Route::post('cleanInventario', [InventarioController::class,"cleanInventario"]);
		
		Route::post('addProductoFactInventario', [InventarioController::class,"addProductoFactInventario"]);
		

		Route::post('setCtxBulto', [InventarioController::class,"setCtxBulto"]);
		Route::post('setStockMin', [InventarioController::class,"setStockMin"]);
		
		Route::post('setPrecioAlterno', [InventarioController::class,"setPrecioAlterno"]);
		
		Route::post('getProveedores', [ProveedoresController::class,"getProveedores"]);
		Route::get('getCategorias', [CategoriasController::class,"getCategorias"]);
		Route::post('delCategoria', [CategoriasController::class,"delCategoria"]);
		Route::post('setCategorias', [CategoriasController::class,"setCategorias"]);


	
		Route::post('delProveedor', [ProveedoresController::class,"delProveedor"]);
		Route::post('delProducto', [InventarioController::class,"delProducto"]);
	
		Route::post('getDepositos', [DepositosController::class,"getDepositos"]);
		Route::post('getMarcas', [MarcasController::class,"getMarcas"]);
		
		Route::post('getFacturas', [FacturaController::class,"getFacturas"]);
		Route::post('setFactura', [FacturaController::class,"setFactura"]);
		Route::post('sendFacturaCentral', [sendCentral::class,"sendFacturaCentral"]);
		Route::post('getAllProveedores', [sendCentral::class,"getAllProveedores"]);
		
		
		Route::post('delFactura', [FacturaController::class,"delFactura"]);
	
		Route::post('delItemFact', [ItemsFacturaController::class,"delItemFact"]);
		
		Route::post('getFallas', [InventarioController::class,"getFallas"]);
		Route::post('setFalla', [InventarioController::class,"setFalla"]);
		Route::post('delFalla', [InventarioController::class,"delFalla"]);
		Route::get('reporteFalla', [InventarioController::class,"reporteFalla"]);
		
		
		Route::get('verFactura', [FacturaController::class,"verFactura"]);
		Route::get('verDetallesImagenFactura', [FacturaController::class,"verDetallesImagenFactura"]);
		
		Route::post('setUsuario', [UsuariosController::class,"setUsuario"]);
		Route::post('delUsuario', [UsuariosController::class,"delUsuario"]);
		Route::get('verCreditos', [PagoPedidosController::class,"verCreditos"]);
		Route::get('reporteInventario', [InventarioController::class,"reporteInventario"]);
		Route::post('getEstaInventario', [InventarioController::class,"getEstaInventario"]);

		Route::post('changeIdVinculacionCentral', [InventarioController::class,"changeIdVinculacionCentral"]);

		Route::post('saveMontoFactura', [FacturaController::class,"saveMontoFactura"]);
	
		
		Route::post('delMovCaja', [MovimientosCajaController::class,"delMovCaja"]);
		
		Route::get('printTickedPrecio', [tickeprecioController::class,"tickedPrecio"]);
		Route::post('getTotalizarCierre', [CierresController::class,"getTotalizarCierre"]);

		Route::post('printPrecios', [tickera::class,"precio"]);
		
		
		Route::post('delMov', [MovimientosController::class,"delMov"]);
		
	//Central
		Route::post('checkPedidosCentral', [InventarioController::class,"checkPedidosCentral"]);
		Route::post('removeVinculoCentral', [sendCentral::class,"removeVinculoCentral"]);
		
		Route::post('saveChangeInvInSucurFromCentral', [InventarioController::class,"saveChangeInvInSucurFromCentral"]);
		Route::get('getUniqueProductoById', [InventarioController::class,"getUniqueProductoById"]);

		
		
		Route::get('setVentas', [sendCentral::class,"setVentas"]);
		Route::get('setGastos', [sendCentral::class,"setGastos"]);
		Route::get('setCentralData', [sendCentral::class,"setCentralData"]);
		Route::get('central', [sendCentral::class,"index"]);
		Route::get('getMonedaCentral', [sendCentral::class,"getMonedaCentral"]);
		
		Route::get('setFacturasCentral', [sendCentral::class,"setFacturasCentral"]);
		
		Route::post('getmastermachine', [sendCentral::class,"getmastermachine"]);
		Route::post('setnewtasainsucursal', [sendCentral::class,"setnewtasainsucursal"]);
		Route::post('updatetasasfromCentral', [sendCentral::class,"updatetasasfromCentral"]);
		
		//req
		Route::get('setNuevaTareaCentral', [sendCentral::class,"setNuevaTareaCentral"]);
	
		Route::get('setSocketUrlDB', [sendCentral::class,"setSocketUrlDB"]);
		
		// ================ RUTAS PARA INVENTARIO DE GARANTÍAS ================
		
		// Obtener inventario de garantías desde central
		Route::post('getInventarioGarantiasCentral', [InventarioGarantiasController::class,"getInventarioGarantiasCentral"]);
		
		// Buscar productos en inventario de garantías
		Route::post('searchInventarioGarantias', [InventarioGarantiasController::class,"searchInventarioGarantias"]);
		
		// Transferir producto de garantía entre sucursales
		Route::post('transferirProductoGarantiaSucursal', [InventarioGarantiasController::class,"transferirProductoGarantiaSucursal"]);
		
		// Transferir producto de garantía a venta normal (solo desde central)
		Route::post('transferirGarantiaVentaNormal', [InventarioGarantiasController::class,"transferirGarantiaVentaNormal"]);
		
		// Obtener tipos de inventario de garantía disponibles
		Route::get('getTiposInventarioGarantia', [InventarioGarantiasController::class,"getTiposInventarioGarantia"]);
		
		// Obtener estadísticas de inventario de garantías
		Route::post('getEstadisticasInventarioGarantias', [InventarioGarantiasController::class,"getEstadisticasInventarioGarantias"]);
		
		// ================ FIN RUTAS INVENTARIO DE GARANTÍAS ================
		
		Route::post('reqpedidos', [sendCentral::class,"reqpedidos"]);
		Route::post('reqMipedidos', [sendCentral::class,"reqMipedidos"]);
		Route::post('settransferenciaDici', [sendCentral::class,"settransferenciaDici"]);
		
		Route::post('setInventarioFromSucursal', [sendCentral::class,"setInventarioFromSucursal"]);
		Route::post('getSucursales', [sendCentral::class,"getSucursales"]);
		Route::post('getInventarioSucursalFromCentral', [sendCentral::class,"getInventarioSucursalFromCentral"]);
		Route::post('setInventarioSucursalFromCentral', [sendCentral::class,"setInventarioSucursalFromCentral"]);
		
		Route::post('getInventarioFromSucursal', [sendCentral::class,"getInventarioFromSucursal"]);
		
		Route::post('setCambiosInventarioSucursal', [sendCentral::class,"setCambiosInventarioSucursal"]);
		Route::get('getTareasCentral', [sendCentral::class,"getTareasCentral"]);
		Route::post('runTareaCentral', [sendCentral::class,"runTareaCentral"]);
		
		//res
		Route::post('resinventario', [sendCentral::class,"resinventario"]);
		Route::post('respedidos', [sendCentral::class,"respedidos"]);
	
		Route::post('setexportpedido', [PedidosController::class,"setexportpedido"]);
		
		Route::get("/recibedSocketEvent",[sendCentral::class,"recibedSocketEvent"]);
		
		
		
		//Update App
		//Route::get('update', [sendCentral::class,"updateApp"]);
	});
	
		
});
	

// }

// Rutas para sincronización con central
Route::get('/sincronizar-inventario', [App\Http\Controllers\sendCentral::class, 'getAllInventarioFromCentral'])->name('sincronizar.inventario');
Route::get('/sendCajerosEstadisticas', [App\Http\Controllers\sendCajerosReporteController::class, 'sendCajerosEstadisticas']);
	

/* Route::get('/reports', [App\Http\Controllers\sendCentral::class, 'getReports'])->name('reports.index');
Route::get('/reports/{filename}', [App\Http\Controllers\sendCentral::class, 'viewReport'])->name('reports.view');

Route::get('/reporte-global', [App\Http\Controllers\sendCentral::class, 'viewReport'])->name('reporte.global'); */

// Report routes
Route::get('/reports/{type}', [App\Http\Controllers\ReportController::class, 'viewReport'])->name('reports.view');

// NOTA: Las rutas API de garantías se movieron a routes/api.php para mejor organización

// ================ RUTAS PARA REVERSO DE GARANTÍAS ================
Route::prefix('garantia-reverso')->group(function () {
    Route::post('/buscar-solicitud', [App\Http\Controllers\GarantiaReversoController::class, 'buscarSolicitud']);
    Route::post('/solicitar-reverso', [App\Http\Controllers\GarantiaReversoController::class, 'solicitarReverso']);
    Route::post('/ejecutar-reverso', [App\Http\Controllers\GarantiaReversoController::class, 'ejecutarReverso']);
    
    // Nuevas rutas para el proceso de reverso completo
    Route::post('/listar-solicitudes-aprobadas', [App\Http\Controllers\GarantiaReversoController::class, 'listarSolicitudesReversoAprobadas']);
    Route::post('/ejecutar-reverso-completo', [App\Http\Controllers\GarantiaReversoController::class, 'ejecutarReversoCompletoDesdeInterfaz']);
});
// ================ FIN RUTAS REVERSO DE GARANTÍAS ================

// ================ RUTAS PARA MONEDAS ================
Route::group(['middleware' => ['auth.user:admin']], function () {
    Route::get('getMonedas', [MonedasController::class, 'getMonedas']);
    Route::get('getDollarRate', [MonedasController::class, 'getDollarRate']);
    Route::post('updateDollarRate', [MonedasController::class, 'updateDollarRate']);
    Route::post('updateMoneda', [MonedasController::class, 'updateMoneda']);
});
// ================ FIN RUTAS MONEDAS ================

// ================ RUTAS PARA WAREHOUSES (GESTIÓN DE ALMACÉN) ================
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseInventoryController;

Route::group(['middleware' => ['auth.user:admin']], function () {
    // Rutas auxiliares para ubicaciones (deben ir primero para evitar conflictos)
	Route::get('warehouses/generar-ubicaciones', [WarehouseController::class, 'generarUbicaciones']);
    Route::get('warehouses/disponibles', [WarehouseController::class, 'getDisponibles']);
    Route::get('warehouses/buscar-codigo', [WarehouseController::class, 'buscarPorCodigo']);
    Route::post('warehouses/sugerir-ubicacion', [WarehouseController::class, 'sugerirUbicacion']);
    Route::get('warehouses/reporte-ocupacion', [WarehouseController::class, 'reporteOcupacion']);
    
    // Rutas CRUD de ubicaciones (warehouses)
    Route::get('warehouses/buscar', [WarehouseController::class, 'buscarUbicaciones'])->name('warehouses.buscar');
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('warehouses/{id}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::put('warehouses/{id}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    
    // Consultas de inventario (antes de las rutas con parámetros)
    Route::get('warehouse-inventory/buscar-productos', [WarehouseInventoryController::class, 'buscarProductos'])->name('warehouse-inventory.buscar-productos');
    Route::get('warehouse-inventory/producto/{inventarioId}', [WarehouseInventoryController::class, 'consultarUbicaciones'])->name('warehouse-inventory.producto');
    Route::get('warehouse-inventory/ubicacion/{warehouseId}', [WarehouseInventoryController::class, 'consultarPorUbicacion'])->name('warehouse-inventory.ubicacion');
    Route::get('warehouse-inventory/por-ubicacion', [WarehouseInventoryController::class, 'porUbicacion'])->name('warehouse-inventory.por-ubicacion');
    Route::get('warehouse-inventory/historial', [WarehouseInventoryController::class, 'historial'])->name('warehouse-inventory.historial');
    Route::get('warehouse-inventory/proximos-vencer', [WarehouseInventoryController::class, 'proximosVencer'])->name('warehouse-inventory.proximos-vencer');
    
    // Rutas de inventario en ubicaciones
    Route::get('warehouse-inventory', [WarehouseInventoryController::class, 'index'])->name('warehouse-inventory.index');
    Route::post('warehouse-inventory/asignar', [WarehouseInventoryController::class, 'asignar'])->name('warehouse-inventory.asignar');
    Route::post('warehouse-inventory/transferir', [WarehouseInventoryController::class, 'transferir'])->name('warehouse-inventory.transferir');
    Route::post('warehouse-inventory/retirar', [WarehouseInventoryController::class, 'retirar'])->name('warehouse-inventory.retirar');
    Route::put('warehouse-inventory/{id}/estado', [WarehouseInventoryController::class, 'actualizarEstado'])->name('warehouse-inventory.actualizar-estado');
});
// ================ FIN RUTAS WAREHOUSES ================


