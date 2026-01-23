import React, { useState, useEffect } from 'react';
import db from "../database/database";

const formatNumber = (num) => {
	if (num === null || num === undefined) return '0.00';
	return parseFloat(num).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

function TotalizarCierre({ 
	fechaCierre, 
	onClose,
	dolar, // tasa BS
	peso,  // tasa COP
	esModal = true // Por defecto es modal
}) {
	const [loading, setLoading] = useState(false);
	const [cierreConsolidado, setCierreConsolidado] = useState(null);
	const [datosConsolidados, setDatosConsolidados] = useState(null);
	const [cierresIndividuales, setCierresIndividuales] = useState([]);
	const [error, setError] = useState(null);
	const [numeroUsuarios, setNumeroUsuarios] = useState(0);
	const [cierreAdminGuardado, setCierreAdminGuardado] = useState(false);
	const [guardandoCierre, setGuardandoCierre] = useState(false);
	const [fechaCierreBackend, setFechaCierreBackend] = useState(null); // Fecha retornada por el backend

	// Cargar datos al montar el componente
	useEffect(() => {
		cargarCierreConsolidado();
	}, []); // No depende de fechaCierre, el backend usa automáticamente today()

	const cargarCierreConsolidado = async () => {
		setLoading(true);
		setError(null);
		
		try {
			// No pasar fechaCierre, el backend usará automáticamente today()
			const response = await db.getTotalizarCierre({ 
				totalizarcierre: true
			});
			
			if (response.data && response.data.estado && response.data.cierre_consolidado) {
				setCierreConsolidado(response.data.cierre_consolidado);
				setDatosConsolidados(response.data.datos_consolidados);
				setCierresIndividuales(response.data.cierres_individuales || []);
				setNumeroUsuarios(response.data.numero_usuarios || 0);
				// Actualizar estado de cierre admin guardado desde el backend
				setCierreAdminGuardado(response.data.cierre_admin_guardado || false);
				// Guardar la fecha retornada por el backend
				if (response.data.fecha) {
					setFechaCierreBackend(response.data.fecha);
				}
			} else {
				const errorMsg = response.data?.msj || 'El servidor no devolvió los datos esperados';
				setError(errorMsg);
			}
		} catch (err) {
			const errorMsg = 'Error al conectar con el servidor: ' + (err.message || err);
			setError(errorMsg);
		} finally {
			setLoading(false);
		}
	};

	// Función para mostrar mensaje detallado de faltante
	const mostrarMensajeFaltante = (data) => {
		const mensaje = data.msj || 'Error al guardar el cierre';
		
		// Crear un div para mostrar el mensaje formateado
		const mensajeDiv = document.createElement('div');
		mensajeDiv.style.cssText = `
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background: white;
			border: 3px solid #dc3545;
			border-radius: 8px;
			padding: 20px;
			max-width: 600px;
			max-height: 80vh;
			overflow-y: auto;
			z-index: 10000;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
			font-family: monospace;
			font-size: 13px;
			line-height: 1.6;
			white-space: pre-wrap;
		`;
		
		// Agregar título
		const titulo = document.createElement('div');
		titulo.style.cssText = 'font-weight: bold; font-size: 16px; color: #dc3545; margin-bottom: 15px; text-align: center;';
		titulo.textContent = '⚠️ FALTANTE EN CIERRE';
		mensajeDiv.appendChild(titulo);
		
		// Agregar mensaje
		const contenido = document.createElement('div');
		contenido.textContent = mensaje;
		mensajeDiv.appendChild(contenido);
		
		// Agregar botón de cerrar
		const botonCerrar = document.createElement('button');
		botonCerrar.textContent = 'Cerrar';
		botonCerrar.style.cssText = `
			margin-top: 15px;
			padding: 10px 20px;
			background: #dc3545;
			color: white;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			width: 100%;
			font-size: 14px;
			font-weight: bold;
		`;
		botonCerrar.onclick = () => {
			document.body.removeChild(mensajeDiv);
			document.body.removeChild(overlay);
		};
		mensajeDiv.appendChild(botonCerrar);
		
		// Crear overlay
		const overlay = document.createElement('div');
		overlay.style.cssText = `
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			z-index: 9999;
		`;
		overlay.onclick = () => {
			document.body.removeChild(mensajeDiv);
			document.body.removeChild(overlay);
		};
		
		document.body.appendChild(overlay);
		document.body.appendChild(mensajeDiv);
	};

	const guardarCierreAdmin = async () => {
		if (!cierreConsolidado || !datosConsolidados) {
			alert('No hay datos de cierre para guardar');
			return;
		}

		if (confirm('¿Está seguro que desea guardar el cierre consolidado de administrador?')) {
			setGuardandoCierre(true);
			try {
				// Preparar datos para guardar usando los valores consolidados
				// No pasar fechaCierre, el backend usará automáticamente today()
				const datosGuardar = {
					totalizarcierre: true, // Indica que es cierre de admin (tipo_cierre = 1)
					
					// Efectivo real contado por los cajeros
					caja_usd: datosConsolidados.efectivo_real.usd || 0,
					caja_cop: datosConsolidados.efectivo_real.cop || 0,
					caja_bs: datosConsolidados.efectivo_real.bs || 0,
					
					// Dejar en caja (consolidado)
					dejar_usd: datosConsolidados.dejar_caja.usd || 0,
					dejar_cop: datosConsolidados.dejar_caja.cop || 0,
					dejar_bs: datosConsolidados.dejar_caja.bs || 0,
					
					// Cuadre detallado (ya calculado por cerrarFun)
					cuadre_detallado: cierreConsolidado.cuadre_detallado,
					
					// Los lotes y puntos ya están incluidos en el cuadre_detallado
					// No necesitamos enviar dataPuntosAdicionales ni lotesPinpad
					// porque el backend los recalculará desde los cierres individuales
					dataPuntosAdicionales: [],
					lotesPinpad: []
				};

			const response = await db.guardarCierre(datosGuardar);
			
			if (response.data && response.data.estado) {
				alert('Cierre de administrador guardado exitosamente');
				setCierreAdminGuardado(true);
			} else {
				// Si hay un faltante, mostrar mensaje detallado
				// El backend retorna total_diferencia cuando hay un faltante
				if (response.data?.total_diferencia !== undefined || response.data?.descuadre_general !== undefined) {
					mostrarMensajeFaltante(response.data);
				} else {
					alert(response.data?.msj || 'Error al guardar el cierre');
				}
			}
			} catch (error) {
				alert('Error al guardar el cierre: ' + (error.message || error));
			} finally {
				setGuardandoCierre(false);
			}
		}
	};

	const verCierreAdmin = () => {
		// Usar la fecha retornada por el backend, o permitir seleccionar fecha para ver cierres pasados
		const fecha = fechaCierreBackend || "";
		
		if (!fecha) {
			// Si no hay fecha del backend, pedir al usuario que la seleccione (para ver cierres pasados)
			const fechaSeleccionada = prompt('Ingrese la fecha del cierre a ver (formato: YYYY-MM-DD):');
			if (!fechaSeleccionada) {
				return; // Usuario canceló
			}
			const url = `${db.getHost()}verCierre?type=ver&fecha=${fechaSeleccionada}`;
			window.open(url, '_blank');
		} else {
			// Abrir reporte en nueva pestaña (no petición asíncrona)
			const url = `${db.getHost()}verCierre?type=ver&fecha=${fecha}`;
			window.open(url, '_blank');
		}
	};

	const reversarCierreAdmin = async () => {
		// PRIMERA CONFIRMACIÓN
		if (!confirm('⚠️ ADVERTENCIA ⚠️\n\n¿Está seguro que desea REVERSAR el cierre de administrador?\n\nEsta acción eliminará TODOS los cierres del día (cajeros y administrador).\n\nPresione OK para continuar.')) {
			return;
		}

		// SEGUNDA CONFIRMACIÓN
		if (!confirm('⚠️ SEGUNDA CONFIRMACIÓN ⚠️\n\nEsta es una acción IRREVERSIBLE.\n\nSe eliminarán:\n- Cierre de administrador\n- Todos los cierres de cajeros\n- Registros de movimientos de caja del día\n\n¿Está COMPLETAMENTE SEGURO?')) {
			return;
		}

		// TERCERA CONFIRMACIÓN
		if (!confirm('⚠️ ÚLTIMA CONFIRMACIÓN ⚠️\n\nEsta es su última oportunidad para cancelar.\n\nAl presionar OK, se ejecutará el reverso de TODOS los cierres del día.\n\n¿CONFIRMA que desea proceder con el REVERSO?')) {
			return;
		}

		// Si llegó hasta aquí, ejecutar el reverso
		try {
			const response = await db.reversarCierre({});
			
			// El endpoint reversarCierre no devuelve JSON, solo ejecuta y no retorna nada
			alert('✅ Cierre reversado exitosamente.\n\nTodos los cierres del día han sido eliminados.');
			// Recargar los datos (incluye verificación de cierre admin)
			cargarCierreConsolidado();
		} catch (error) {
			alert('❌ Error al reversar el cierre: ' + (error.message || error));
		}
	};

	if (loading) {
		return (
			<div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
				<div className="bg-white rounded-lg p-8">
					<div className="flex items-center space-x-3">
						<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
						<span className="text-gray-700">Cargando cierre consolidado...</span>
					</div>
				</div>
			</div>
		);
	}

	if (error) {
		return (
			<div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
				<div className="bg-white rounded-lg p-8 max-w-md">
					<div className="text-red-600 mb-4">
						<i className="fa fa-exclamation-circle text-4xl"></i>
					</div>
					<h3 className="text-xl font-bold mb-2">Error</h3>
					<p className="text-gray-700 mb-4">{error}</p>
					<button 
						onClick={onClose}
						className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded"
					>
						Cerrar
					</button>
				</div>
			</div>
		);
	}

	if (!cierreConsolidado || !cierreConsolidado.cuadre_detallado) {
		return null;
	}

	const cuadre_detallado = cierreConsolidado.cuadre_detallado;

	const getEstadoClasses = (estado) => {
		if (estado === 'cuadrado') return { text: 'text-success', badge: 'badge bg-success fs-6 px-2 py-1 fw-bold' };
		if (estado === 'sobra') return { text: 'text-orange-700', badge: 'inline-block bg-orange-600 text-white text-sm px-2 py-1 rounded font-bold' };
		return { text: 'text-danger', badge: 'badge bg-danger fs-6 px-2 py-1 fw-bold' };
	};

	// Función reutilizable para renderizar un cuadre detallado
	const renderCuadreDetallado = (cuadre_detallado, titulo, mostrarResumen = true) => {
		if (!cuadre_detallado) return null;

		return (
			<div className="bg-white">
				<div className="mb-3">
					<h3 className="text-xl font-bold text-gray-800 border-b-2 border-gray-300 pb-2">
						<i className="fa fa-calculator text-blue-600 mr-2"></i>
						{titulo}
					</h3>
				</div>
				
				{/* Header de la tabla */}
				<div className="grid grid-cols-6 gap-2 p-2 bg-gray-100 font-bold text-sm border-b-2">
					<div className="text-left">Tipo</div>
					<div className="text-right">Saldo Inicial</div>
					<div className="text-right">Real</div>
					<div className="text-right">Digital</div>
					<div className="text-right">Diferencia</div>
					<div className="text-center">Estado</div>
				</div>

				{/* EFECTIVO */}
				<div className="mt-3 mb-2">
					<h6 className="text-blue-600 font-bold text-sm">EFECTIVO</h6>
				</div>

				{cuadre_detallado.efectivo_usd && (
					<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
						<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.efectivo_usd.estado).text}`}>
							Dólares (USD)
						</div>
						<div className="text-right">${formatNumber(cuadre_detallado.efectivo_usd.inicial)}</div>
						<div className="text-right">${formatNumber(cuadre_detallado.efectivo_usd.real)}</div>
						<div className="text-right">${formatNumber(cuadre_detallado.efectivo_usd.digital)}</div>
						<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.efectivo_usd.estado).text}`}>
							${formatNumber(cuadre_detallado.efectivo_usd.diferencia)}
						</div>
						<div className="text-center">
							<span className={getEstadoClasses(cuadre_detallado.efectivo_usd.estado).badge}>
								{cuadre_detallado.efectivo_usd.mensaje}
							</span>
						</div>
					</div>
				)}

				{cuadre_detallado.efectivo_bs && (
					<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
						<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.efectivo_bs.estado).text}`}>
							Bolívares (Bs)
						</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.efectivo_bs.inicial)}</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.efectivo_bs.real)}</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.efectivo_bs.digital)}</div>
						<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.efectivo_bs.estado).text}`}>
							Bs {formatNumber(cuadre_detallado.efectivo_bs.diferencia)}
						</div>
						<div className="text-center">
							<span className={getEstadoClasses(cuadre_detallado.efectivo_bs.estado).badge}>
								{cuadre_detallado.efectivo_bs.mensaje}
							</span>
						</div>
					</div>
				)}

				{cuadre_detallado.efectivo_cop && (cuadre_detallado.efectivo_cop.real > 0 || cuadre_detallado.efectivo_cop.digital > 0) && (
					<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
						<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.efectivo_cop.estado).text}`}>
							Pesos (COP)
						</div>
						<div className="text-right">COP {formatNumber(cuadre_detallado.efectivo_cop.inicial)}</div>
						<div className="text-right">COP {formatNumber(cuadre_detallado.efectivo_cop.real)}</div>
						<div className="text-right">COP {formatNumber(cuadre_detallado.efectivo_cop.digital)}</div>
						<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.efectivo_cop.estado).text}`}>
							COP {formatNumber(cuadre_detallado.efectivo_cop.diferencia)}
						</div>
						<div className="text-center">
							<span className={getEstadoClasses(cuadre_detallado.efectivo_cop.estado).badge}>
								{cuadre_detallado.efectivo_cop.mensaje}
							</span>
						</div>
					</div>
				)}

				{/* DÉBITO */}
				<div className="mt-3 mb-2">
					<h6 className="text-blue-600 font-bold text-sm">DÉBITO</h6>
				</div>

				{cuadre_detallado.debito_pinpad && (
					<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
						<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.debito_pinpad.estado).text}`}>
							Pinpad
						</div>
						<div className="text-right">-</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.debito_pinpad.real)}</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.debito_pinpad.digital)}</div>
						<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.debito_pinpad.estado).text}`}>
							Bs {formatNumber(cuadre_detallado.debito_pinpad.diferencia)}
						</div>
						<div className="text-center">
							<span className={getEstadoClasses(cuadre_detallado.debito_pinpad.estado).badge}>
								{cuadre_detallado.debito_pinpad.mensaje}
							</span>
						</div>
					</div>
				)}

				{cuadre_detallado.debito_otros && (
					<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
						<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.debito_otros.estado).text}`}>
							Otros Puntos
						</div>
						<div className="text-right">-</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.debito_otros.real)}</div>
						<div className="text-right">Bs {formatNumber(cuadre_detallado.debito_otros.digital)}</div>
						<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.debito_otros.estado).text}`}>
							Bs {formatNumber(cuadre_detallado.debito_otros.diferencia)}
						</div>
						<div className="text-center">
							<span className={getEstadoClasses(cuadre_detallado.debito_otros.estado).badge}>
								{cuadre_detallado.debito_otros.mensaje}
							</span>
						</div>
					</div>
				)}

				{/* TRANSFERENCIA */}
				{cuadre_detallado.transferencia && (cuadre_detallado.transferencia.real > 0 || cuadre_detallado.transferencia.digital > 0) && (
					<>
						<div className="mt-3 mb-2">
							<h6 className="text-blue-600 font-bold text-sm">TRANSFERENCIA</h6>
						</div>

						<div className="grid grid-cols-6 gap-2 p-2 border-b text-sm">
							<div className={`text-left font-semibold ${getEstadoClasses(cuadre_detallado.transferencia.estado).text}`}>
								Transferencia
							</div>
							<div className="text-right">-</div>
							<div className="text-right">${formatNumber(cuadre_detallado.transferencia.real)}</div>
							<div className="text-right">${formatNumber(cuadre_detallado.transferencia.digital)}</div>
							<div className={`text-right font-bold ${getEstadoClasses(cuadre_detallado.transferencia.estado).text}`}>
								${formatNumber(cuadre_detallado.transferencia.diferencia)}
							</div>
							<div className="text-center">
								<span className={getEstadoClasses(cuadre_detallado.transferencia.estado).badge}>
									{cuadre_detallado.transferencia.mensaje}
								</span>
							</div>
						</div>
					</>
				)}
			</div>
		);
	};

	// Contenido principal
	const contenido = (
		<div className="p-6 overflow-y-auto" style={esModal ? { maxHeight: 'calc(90vh - 160px)' } : {}}>
					{/* 1. CUADRE CONSOLIDADO DE TODAS LAS CAJAS */}
					<div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-lg p-4 mb-6 border-2 border-blue-300">
						<div className="mb-3">
							<h3 className="text-2xl font-bold text-blue-800 flex items-center">
								<i className="fa fa-chart-line text-blue-600 mr-2"></i>
								CUADRE CONSOLIDADO - TOTAL DE {numeroUsuarios} CAJA(S)
							</h3>
						</div>
						
						{renderCuadreDetallado(cierreConsolidado?.cuadre_detallado, "")}
						
						{/* Datos Introducidos por Usuarios - Consolidado */}
						{datosConsolidados && (
							<div className="mt-4 p-4 bg-white rounded-lg border-2 border-blue-400">
								<h5 className="text-blue-700 font-bold text-lg mb-3">
									<i className="fa fa-calculator text-blue-600 mr-2"></i>
									Datos Introducidos Total
								</h5>
								<div className="grid grid-cols-3 gap-4">
									{/* Efectivo Real Contado */}
									<div className="bg-blue-50 border border-blue-300 rounded-lg p-3">
										<h6 className="text-blue-700 font-bold text-sm mb-2">
											<i className="fa fa-money-bill-wave mr-1"></i>
											Efectivo Real Contado
										</h6>
										<div className="space-y-1 text-sm">
											<div className="flex justify-between">
												<span className="text-gray-600">USD:</span>
												<span className="font-bold text-gray-800">${formatNumber(datosConsolidados.efectivo_real.usd)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">Bs:</span>
												<span className="font-bold text-gray-800">Bs {formatNumber(datosConsolidados.efectivo_real.bs)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">COP:</span>
												<span className="font-bold text-gray-800">$ {formatNumber(datosConsolidados.efectivo_real.cop)}</span>
											</div>
										</div>
									</div>

								{/* Dejar en Caja */}
								<div className="bg-orange-50 border border-orange-300 rounded-lg p-3">
									<h6 className="text-orange-800 font-bold text-sm mb-2">
										<i className="fa fa-archive mr-1"></i>
										Dejar en Caja
									</h6>
										<div className="space-y-1 text-sm">
											<div className="flex justify-between">
												<span className="text-gray-600">USD:</span>
												<span className="font-bold text-gray-800">${formatNumber(datosConsolidados.dejar_caja.usd)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">Bs:</span>
												<span className="font-bold text-gray-800">Bs {formatNumber(datosConsolidados.dejar_caja.bs)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">COP:</span>
												<span className="font-bold text-gray-800">$ {formatNumber(datosConsolidados.dejar_caja.cop)}</span>
											</div>
										</div>
									</div>

									{/* Efectivo Guardado */}
									<div className="bg-green-50 border border-green-300 rounded-lg p-3">
										<h6 className="text-green-700 font-bold text-sm mb-2">
											<i className="fa fa-piggy-bank mr-1"></i>
											Efectivo Guardado
										</h6>
										<div className="space-y-1 text-sm">
											<div className="flex justify-between">
												<span className="text-gray-600">USD:</span>
												<span className="font-bold text-gray-800">${formatNumber(datosConsolidados.efectivo_guardado.usd)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">Bs:</span>
												<span className="font-bold text-gray-800">Bs {formatNumber(datosConsolidados.efectivo_guardado.bs)}</span>
											</div>
											<div className="flex justify-between">
												<span className="text-gray-600">COP:</span>
												<span className="font-bold text-gray-800">$ {formatNumber(datosConsolidados.efectivo_guardado.cop)}</span>
											</div>
										</div>
									</div>
								</div>
							</div>
						)}

						{/* Botones de Acción - Guardar/Ver/Reversar Cierre Admin */}
						<div className="mt-4 flex justify-end gap-3">
							{!cierreAdminGuardado ? (
								<button 
									onClick={guardarCierreAdmin}
									disabled={guardandoCierre}
									className="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
								>
									{guardandoCierre ? (
										<>
											<i className="fa fa-spinner fa-spin"></i>
											Guardando...
										</>
									) : (
										<>
											<i className="fa fa-save"></i>
											Guardar Cierre Administrador
										</>
									)}
								</button>
							) : (
								<>
									<button 
										onClick={reversarCierreAdmin}
										className="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center gap-2"
									>
										<i className="fa fa-undo"></i>
										Reversar Cierre
									</button>
									<button 
										onClick={verCierreAdmin}
										className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center gap-2"
									>
										<i className="fa fa-eye"></i>
										Ver Cierre
									</button>
								</>
							)}
						</div>
					</div>

					{/* 2. CIERRES INDIVIDUALES DE CADA CAJA */}
					<div className="space-y-4">
						<h3 className="text-xl font-bold text-gray-800 border-b-2 border-gray-300 pb-2 mb-4">
							<i className="fa fa-users text-gray-600 mr-2"></i>
							Detalle por Caja Individual
						</h3>

						{cierresIndividuales.map((cierreData, idx) => (
							<div key={idx} className="border border-gray-300 rounded-lg shadow-md overflow-hidden">
								{/* Header Usuario */}
								<div className="bg-gradient-to-r from-gray-700 to-gray-800 px-4 py-3">
									<div className="flex justify-between items-center">
										<div className="flex items-center space-x-3">
											<i className="fa fa-user-circle text-blue-400 text-2xl"></i>
											<div>
												<h4 className="text-lg font-bold text-white">{cierreData.nombre_usuario}</h4>
												<p className="text-sm text-gray-300">Usuario: {cierreData.usuario} | Caja #{cierreData.id_usuario}</p>
											</div>
										</div>
										<div className="text-right">
											<p className="text-xs text-gray-300">Efectivo a Guardar</p>
											<p className="text-lg font-bold text-green-400">
												${formatNumber(cierreData.datos_usuario?.efectivo_guardado.usd || 0)}
											</p>
										</div>
									</div>
								</div>

								{/* Cuadre Individual */}
								<div className="p-4 bg-gray-50">
									{renderCuadreDetallado(cierreData.cierre?.cuadre_detallado, "")}
									
									{/* Datos Introducidos por Usuario */}
									{cierreData.datos_usuario && (
										<div className="mt-3 p-3 bg-white rounded-lg border border-gray-300">
											<h6 className="text-gray-700 font-bold text-sm mb-2">
												<i className="fa fa-keyboard text-blue-600 mr-1"></i>
												Datos Introducidos
											</h6>
											<div className="grid grid-cols-3 gap-2 text-xs">
												{/* Efectivo Real */}
												<div className="bg-blue-50 border border-blue-200 rounded p-2">
													<p className="text-blue-700 font-semibold mb-1">Efectivo Real</p>
													<div className="space-y-0.5">
														<div className="flex justify-between">
															<span className="text-gray-600">USD:</span>
															<span className="font-bold">${formatNumber(cierreData.datos_usuario.efectivo_real.usd)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">Bs:</span>
															<span className="font-bold">Bs {formatNumber(cierreData.datos_usuario.efectivo_real.bs)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">COP:</span>
															<span className="font-bold">$ {formatNumber(cierreData.datos_usuario.efectivo_real.cop)}</span>
														</div>
													</div>
												</div>

											{/* Dejar en Caja */}
											<div className="bg-orange-50 border border-orange-200 rounded p-2">
												<p className="text-orange-800 font-semibold mb-1">Dejar en Caja</p>
													<div className="space-y-0.5">
														<div className="flex justify-between">
															<span className="text-gray-600">USD:</span>
															<span className="font-bold">${formatNumber(cierreData.datos_usuario.dejar_caja.usd)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">Bs:</span>
															<span className="font-bold">Bs {formatNumber(cierreData.datos_usuario.dejar_caja.bs)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">COP:</span>
															<span className="font-bold">$ {formatNumber(cierreData.datos_usuario.dejar_caja.cop)}</span>
														</div>
													</div>
												</div>

												{/* Efectivo Guardado */}
												<div className="bg-green-50 border border-green-200 rounded p-2">
													<p className="text-green-700 font-semibold mb-1">Efectivo Guardado</p>
													<div className="space-y-0.5">
														<div className="flex justify-between">
															<span className="text-gray-600">USD:</span>
															<span className="font-bold">${formatNumber(cierreData.datos_usuario.efectivo_guardado.usd)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">Bs:</span>
															<span className="font-bold">Bs {formatNumber(cierreData.datos_usuario.efectivo_guardado.bs)}</span>
														</div>
														<div className="flex justify-between">
															<span className="text-gray-600">COP:</span>
															<span className="font-bold">$ {formatNumber(cierreData.datos_usuario.efectivo_guardado.cop)}</span>
														</div>
													</div>
												</div>
											</div>
										</div>
									)}
								</div>
							</div>
						))}
					</div>
				</div>
	);

	// Si es modal, envolver con el backdrop y estructura de modal
	if (esModal) {
		return (
			<div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
				<div className="bg-white rounded-lg shadow-2xl w-full my-8" style={{ maxWidth: '95vw', maxHeight: '90vh' }}>
					{/* Header */}
					<div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
						<div>
							<h2 className="text-2xl font-bold">Totalizar Cierre de Día</h2>
							<p className="text-blue-100 text-sm">{numeroUsuarios} Caja(s)</p>
						</div>
						<div className="flex items-center gap-4">
							<div className="text-right">
								<p className="text-blue-100 text-sm font-semibold">Fecha de cálculo:</p>
								<p className="text-white text-base font-bold">{fechaCierreBackend || 'Cargando...'}</p>
							</div>
							<button 
								onClick={onClose}
								className="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition"
							>
								<i className="fa fa-times text-xl"></i>
							</button>
						</div>
					</div>

					{contenido}

					{/* Footer */}
					<div className="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-end gap-3 border-t">
						<button 
							onClick={onClose}
							className="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold transition"
						>
							<i className="fa fa-times mr-2"></i>
							Cerrar
						</button>
						<button 
							onClick={() => window.print()}
							className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition"
						>
							<i className="fa fa-print mr-2"></i>
							Imprimir
						</button>
					</div>
				</div>
			</div>
		);
	}

	// Si no es modal, retornar solo el contenido con un header simple
	return (
		<div className="w-full">
			{/* Header simple para vista no-modal */}
			<div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 rounded-lg mb-4 flex justify-between items-center">
				<div>
					<h2 className="text-2xl font-bold">Totalizar Cierre de Día</h2>
					<p className="text-blue-100 text-sm">{numeroUsuarios} Caja(s)</p>
				</div>
				<div className="text-right">
					<p className="text-blue-100 text-sm font-semibold">Fecha de cálculo:</p>
					<p className="text-white text-base font-bold">{fechaCierreBackend || 'Cargando...'}</p>
				</div>
			</div>
			
			{contenido}
			
			{/* Botón de imprimir para vista no-modal */}
			<div className="mt-4 flex justify-end">
				<button 
					onClick={() => window.print()}
					className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition"
				>
					<i className="fa fa-print mr-2"></i>
					Imprimir
				</button>
			</div>
		</div>
	);
}

export default TotalizarCierre;
