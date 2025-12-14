import { useState, useEffect } from 'react';
import { useHotkeys } from "react-hotkeys-hook";

import ModalShowPedidoFast from '../components/ModalShowPedidoFast';
import RefsList from './refslist';

function Pedidos({
	setView,
	getReferenciasElec,
	refrenciasElecData,
	togleeReferenciasElec,
	settogleeReferenciasElec,

	addNewPedido,
	auth,
	pedidoData,
	getPedidoFast,
	showModalPedidoFast,
	setshowModalPedidoFast,

	orderbycolumpedidos,
	setorderbycolumpedidos,
	orderbyorderpedidos,
	setorderbyorderpedidos,

	moneda,
	setshowMisPedido,
	showMisPedido,
	tipobusquedapedido,
	setTipoBusqueda,

	busquedaPedido,

	fecha1pedido,

	fecha2pedido,

	onChangePedidos,

	pedidos,
	getPedidos,

	onClickEditPedido,
	onCLickDelPedido,

	tipoestadopedido,
	setTipoestadopedido,
	filterMetodoPago,
	filterMetodoPagoToggle,
	clickSetOrderColumnPedidos,
	toggleImprimirTicket,

	setmodalchangepedido,
	setseletIdChangePedidoUserHandle,
	modalchangepedido,
	modalchangepedidoy,
	modalchangepedidox,
	usuarioChangeUserPedido,
	usuariosData,
}) {



	useEffect(() => {
		getPedidos();
    }, [
        fecha1pedido,
        fecha2pedido,
        tipobusquedapedido,
        tipoestadopedido,
        filterMetodoPagoToggle,
        orderbycolumpedidos,
        orderbyorderpedidos,
		showMisPedido,
    ]);
	// Estado para manejar expansión de secciones
	const [expandedSections, setExpandedSections] = useState({});
	
	// Función para togglear sección específica
	const toggleSection = (productId, section) => {
		setExpandedSections(prev => ({
			...prev,
			[`${productId}-${section}`]: !prev[`${productId}-${section}`]
		}));
	};
	
	// Función para expandir/contraer todas las secciones de un producto
	const toggleAllSections = (productId) => {
		const ventasKey = `${productId}-ventas`;
		const devolucionesKey = `${productId}-devoluciones`;
		
		const ventasExpanded = expandedSections[ventasKey];
		const devolucionesExpanded = expandedSections[devolucionesKey];
		
		// Si alguna está expandida, contraer ambas, sino expandir ambas
		const shouldExpand = !ventasExpanded && !devolucionesExpanded;
		
		setExpandedSections(prev => ({
			...prev,
			[ventasKey]: shouldExpand,
			[devolucionesKey]: shouldExpand
		}));
	};

	//esc
	

	// F3: Volver a la vista de productos (pagar)
	useHotkeys(
		"escape",
		(event) => {
			event.preventDefault(); // Prevenir la búsqueda por defecto del navegador
			setView("pagar");
		},
		{
			enableOnTags: ["INPUT", "SELECT"],
			filter: false,
		},
		[setView]
	);

	return (
		<div className="p-3">
			{showModalPedidoFast && <ModalShowPedidoFast
				pedidoData={pedidoData}
				showModalPedidoFast={showModalPedidoFast}
				setshowModalPedidoFast={setshowModalPedidoFast}
				onClickEditPedido={onClickEditPedido}
			/>}

			{/* Filtros y Búsqueda */}
			<div className="grid grid-cols-1 gap-3 mb-3 lg:grid-cols-12">
				{/* Estado de Pedidos */}
				<div className="lg:col-span-4">
					<div className="bg-white border border-gray-200 rounded shadow-sm">
						<div className="p-2">
							<div className="grid grid-cols-4 gap-1">
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										tipoestadopedido === "todos" 
											? "bg-gray-800 text-white" 
											: "bg-gray-100 text-gray-700 hover:bg-gray-200"
									}`}
									onClick={() => setTipoestadopedido("todos")}
								>
									<i className="mr-1 fa fa-list"></i>
									TODOS
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										tipoestadopedido === 0 
											? "bg-orange-500 text-white" 
											: "bg-orange-100 text-orange-700 hover:bg-orange-200"
									}`}
									onClick={() => setTipoestadopedido(0)}
								>
									<i className="mr-1 fa fa-clock"></i>
									PEND
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										tipoestadopedido === 1 
											? "bg-green-500 text-white" 
											: "bg-green-100 text-green-700 hover:bg-green-200"
									}`}
									onClick={() => setTipoestadopedido(1)}
								>
									<i className="mr-1 fa fa-check"></i>
									PROC
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										tipoestadopedido === 2 
											? "bg-red-500 text-white" 
											: "bg-red-100 text-red-700 hover:bg-red-200"
									}`}
									onClick={() => setTipoestadopedido(2)}
								>
									<i className="mr-1 fa fa-times"></i>
									ANUL
								</button>
							</div>
						</div>
					</div>
				</div>

				{/* Búsqueda y Fechas */}
				<div className="lg:col-span-8">
					<div className="bg-white border border-gray-200 rounded shadow-sm">
						<div className="p-2">
							<div className="flex flex-col gap-2 lg:flex-row">
								{/* Tipo de Búsqueda */}
								<div className="flex rounded shadow-sm">
									<button 
										className={`px-2 py-1 text-xs font-medium rounded-l border transition-colors ${
											tipobusquedapedido === "fact" 
												? "bg-orange-500 text-white border-orange-500" 
												: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
										}`}
										onClick={() => setTipoBusqueda("fact")}
									>
										<i className="mr-1 fa fa-search"></i>
										FACT
									</button>
									<button 
										className={`px-2 py-1 text-xs font-medium border-t border-b transition-colors ${
											tipobusquedapedido === "prod" 
												? "bg-orange-500 text-white border-orange-500" 
												: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
										}`}
										onClick={() => setTipoBusqueda("prod")}
									>
										<i className="mr-1 fa fa-cube"></i>
										PROD
									</button>
									<button 
										className={`px-2 py-1 text-xs font-medium rounded-r border transition-colors ${
											tipobusquedapedido === "cliente" 
												? "bg-orange-500 text-white border-orange-500" 
												: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
										}`}
										onClick={() => setTipoBusqueda("cliente")}
									>
										<i className="mr-1 fa fa-user"></i>
										CLI
									</button>
								</div>

								{/* Búsqueda y Fechas */}
								<form onSubmit={getPedidos} className="flex flex-1 gap-2">
									<div className="relative flex-1">
										<div className="absolute inset-y-0 left-0 flex items-center pl-2 pointer-events-none">
											<i className="text-xs text-gray-400 fa fa-search"></i>
										</div>
										<input 
											className="w-full py-1 pr-2 text-xs border border-gray-300 rounded pl-7 focus:ring-1 focus:ring-orange-400 focus:border-orange-400" 
											placeholder="Buscar..." 
											value={busquedaPedido} 
											data-type="busquedaPedido" 
											onChange={onChangePedidos} 
											autoComplete="off" 
										/>
									</div>
									<div className="flex gap-1">
										<input 
											type="date" 
											value={fecha1pedido} 
											data-type="fecha1pedido" 
											onChange={onChangePedidos} 
											className="px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-orange-400 focus:border-orange-400" 
										/>
										<input 
											type="date" 
											value={fecha2pedido} 
											data-type="fecha2pedido" 
											onChange={onChangePedidos} 
											className="px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-orange-400 focus:border-orange-400" 
										/>
									</div>
									<button 
										type="button" 
										className="px-2 py-1 text-xs text-orange-600 transition-colors border border-orange-300 rounded hover:bg-orange-50" 
										onClick={() => getPedidos()}
									>
										<i className="fa fa-sync-alt"></i>
									</button>
									<a 
										href={`/exportarVentasCSV?fecha1=${fecha1pedido}&fecha2=${fecha2pedido}`}
										className="px-2 py-1 text-xs text-green-600 transition-colors border border-green-300 rounded hover:bg-green-50"
										title="Descargar CSV de ventas"
									>
										<i className="fa fa-download"></i>
									</a>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>

			{/* Controles y Métodos de Pago */}
			<div className="grid grid-cols-1 gap-3 mb-3 lg:grid-cols-2">
				{/* Controles de Pedidos */}
				<div className="bg-white border border-gray-200 rounded shadow-sm">
					<div className="p-2">
						<div className="flex items-center gap-2">
							<button 
								className="px-3 py-1 text-sm font-medium text-white transition-colors bg-orange-500 rounded shadow-sm hover:bg-orange-600" 
								data-valor="id" 
								onClick={clickSetOrderColumnPedidos}
							>
								<i className="mr-1 fa fa-shopping-cart"></i>
								{pedidos["fact"] ? pedidos["fact"].length : 0}
							</button>
							<div className="flex rounded shadow-sm">
								<button 
									onClick={() => setshowMisPedido(true)} 
									className={`px-2 py-1 text-xs font-medium rounded-l border transition-colors ${
										showMisPedido 
											? "bg-green-500 text-white border-green-500" 
											: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
									}`}
								>
									<i className="mr-1 fa fa-user"></i>
									Mis
								</button>
								{auth(1) && (
									<button 
										onClick={() => setshowMisPedido(false)} 
										className={`px-2 py-1 text-xs font-medium border-t border-b transition-colors ${
											!showMisPedido 
												? "bg-green-500 text-white border-green-500" 
												: "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
										}`}
									>
										<i className="mr-1 fa fa-users"></i>
										Todos
									</button>
								)}
								<button 
									className="px-2 py-1 text-xs text-white transition-colors bg-green-500 rounded-r hover:bg-green-600" 
									onClick={addNewPedido}
								>
									<i className="fa fa-plus"></i>
								</button>
							</div>
						</div>
					</div>
				</div>

				{/* Métodos de Pago */}
				<div className="bg-white border border-gray-200 rounded shadow-sm">
					<div className="p-2">
						<div className="flex justify-end">
							<div className="flex flex-wrap gap-1">
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === "todos" 
											? "bg-gray-800 text-white" 
											: "bg-gray-100 text-gray-700 hover:bg-gray-200"
									}`}
									data-type="todos" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-list"></i>
									Todos
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 1 
											? "bg-blue-500 text-white" 
											: "bg-blue-100 text-blue-700 hover:bg-blue-200"
									}`}
									data-type="1" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-exchange"></i>
									Trans
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 2 
											? "bg-gray-500 text-white" 
											: "bg-gray-100 text-gray-700 hover:bg-gray-200"
									}`}
									data-type="2" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-credit-card"></i>
									Deb
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 3 
											? "bg-green-500 text-white" 
											: "bg-green-100 text-green-700 hover:bg-green-200"
									}`}
									data-type="3" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-money"></i>
									Efec
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 4 
											? "bg-yellow-500 text-white" 
											: "bg-yellow-100 text-yellow-700 hover:bg-yellow-200"
									}`}
									data-type="4" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-calendar"></i>
									Cred
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 5 
											? "bg-cyan-500 text-white" 
											: "bg-cyan-100 text-cyan-700 hover:bg-cyan-200"
									}`}
									data-type="5" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-mobile"></i>
									Bio
								</button>
								<button 
									className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
										filterMetodoPagoToggle === 6 
											? "bg-red-500 text-white" 
											: "bg-red-100 text-red-700 hover:bg-red-200"
									}`}
									data-type="6" 
									onClick={filterMetodoPago}
								>
									<i className="mr-1 fa fa-ban"></i>
									Anul
								</button>

								<button 
									className="px-2 py-1 text-xs font-medium text-white transition-colors bg-green-500 rounded hover:bg-green-600" 
									onClick={() => settogleeReferenciasElec(true)}
								>
									REFs
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>

			{/* Lista de Pedidos */}
			<div className="mt-3">
				{tipobusquedapedido === "prod" ? (
					<div className="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-3">
						{pedidos["prod"]?.map(e => {
							if (!e) return null;
							
							// Separar cantidades positivas y negativas
							const ventasItems = e.items.filter(item => item.cantidad > 0);
							const devolucionesItems = e.items.filter(item => item.cantidad < 0);
							const ventasTotal = ventasItems.reduce((sum, item) => sum + parseInt(item.cantidad), 0);
							const devolucionesTotal = devolucionesItems.reduce((sum, item) => sum + Math.abs(parseInt(item.cantidad)), 0);
							
							return (
								<div className="h-full" key={e.id}>
									<div className="flex flex-col h-full bg-white border border-gray-200 rounded shadow-sm">
										{/* Header */}
										<div className="p-3 text-white bg-orange-500 rounded-t">
											<div className="flex items-start justify-between">
												<div className="flex-1 min-w-0">
													<h5 className="text-sm font-bold text-white truncate">{e.descripcion}</h5>
													<div className="text-xs text-orange-100">
														<i className="mr-1 fa fa-barcode"></i>
														{e.codigo_proveedor} | {e.codigo_barras}
													</div>
												</div>
												<div className="ml-2">
													<span className="px-2 py-1 text-xs font-bold text-orange-500 bg-white rounded-full">
														{e.cantidadtotal}
													</span>
												</div>
											</div>
										</div>
										
										{/* Body */}
										<div className="flex-1 p-3">
											{/* Precios */}
											<div className="grid grid-cols-2 gap-2 mb-3">
												<div className="p-2 text-xs text-center rounded bg-gray-50">
													<div className="mb-1 text-gray-500">Base</div>
													<div className="font-bold text-orange-500">${e.precio_base}</div>
												</div>
												<div className="p-2 text-xs text-center rounded bg-gray-50">
													<div className="mb-1 text-gray-500">Venta</div>
													<div className="font-bold text-green-500">${e.precio}</div>
												</div>
											</div>

											{/* Resumen de Cantidades */}
											<div className="grid grid-cols-2 gap-2 mb-3">
												{ventasTotal > 0 && (
													<div className="p-2 text-xs text-center border border-green-200 rounded bg-green-50">
														<i className="mr-1 text-green-500 fa fa-arrow-up"></i>
														<div className="font-bold text-green-700">{ventasTotal}</div>
														<div className="text-green-600">Ventas</div>
													</div>
												)}
												{devolucionesTotal > 0 && (
													<div className="p-2 text-xs text-center border border-red-200 rounded bg-red-50">
														<i className="mr-1 text-red-500 fa fa-arrow-down"></i>
														<div className="font-bold text-red-700">{devolucionesTotal}</div>
														<div className="text-red-600">Devol.</div>
													</div>
												)}
											</div>

											{/* Detalles expandibles */}
											<div className="space-y-2">
												{/* Ventas */}
												{ventasItems.length > 0 && (
													<div className="border border-green-200 rounded">
														<button 
															className="flex items-center justify-between w-full p-2 text-xs text-left transition-colors rounded-t bg-green-50 hover:bg-green-100"
															onClick={() => toggleSection(e.id, 'ventas')}
														>
															<div className="flex items-center space-x-2">
																<i className="text-green-600 fa fa-shopping-cart"></i>
																<span className="font-semibold text-green-700">
																	Ventas ({ventasItems.length})
																</span>
															</div>
															<i className={`fa transition-transform text-xs ${
																expandedSections[`${e.id}-ventas`] ? 'fa-chevron-up' : 'fa-chevron-down'
															} text-green-600`}></i>
														</button>
														<div className={`transition-all duration-300 ease-in-out ${
															expandedSections[`${e.id}-ventas`] ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
														} overflow-hidden`}>
															<div className="divide-y divide-gray-100">
																{ventasItems.map(item => (
																	<div key={item.id} className="flex items-center justify-between p-2 transition-colors hover:bg-gray-50">
																		<div className="flex items-center space-x-2">
																			<span className="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
																				+{item.cantidad}
																			</span>
																			<div>
																				<div className="text-xs text-gray-600">{item.created_at}</div>
																				<div className="flex items-center text-xs text-gray-500">
																					<i className="mr-1 fa fa-user"></i>
																					{item.pedido?.vendedor?.nombre}
																				</div>
																			</div>
																		</div>
																		<button 
																			className="px-2 py-1 text-xs text-blue-600 transition-colors rounded bg-blue-50 hover:bg-blue-100"
																			data-id={item.id_pedido}
																			onClick={onClickEditPedido}
																			title="Ver pedido"
																		>
																			<i className="mr-1 fa fa-eye"></i>
																			#{item.id_pedido}
																		</button>
																	</div>
																))}
															</div>
														</div>
													</div>
												)}

												{/* Devoluciones */}
												{devolucionesItems.length > 0 && (
													<div className="border border-red-200 rounded">
														<button 
															className="flex items-center justify-between w-full p-2 text-xs text-left transition-colors rounded-t bg-red-50 hover:bg-red-100"
															onClick={() => toggleSection(e.id, 'devoluciones')}
														>
															<div className="flex items-center space-x-2">
																<i className="text-red-600 fa fa-undo"></i>
																<span className="font-semibold text-red-700">
																	Devol. ({devolucionesItems.length})
																</span>
															</div>
															<i className={`fa transition-transform text-xs ${
																expandedSections[`${e.id}-devoluciones`] ? 'fa-chevron-up' : 'fa-chevron-down'
															} text-red-600`}></i>
														</button>
														<div className={`transition-all duration-300 ease-in-out ${
															expandedSections[`${e.id}-devoluciones`] ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
														} overflow-hidden`}>
															<div className="divide-y divide-gray-100">
																{devolucionesItems.map(item => (
																	<div key={item.id} className="flex items-center justify-between p-2 transition-colors hover:bg-gray-50">
																		<div className="flex items-center space-x-2">
																			<span className="px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded-full">
																				{item.cantidad}
																			</span>
																			<div>
																				<div className="text-xs text-gray-600">{item.created_at}</div>
																				<div className="flex items-center text-xs text-gray-500">
																					<i className="mr-1 fa fa-user"></i>
																					{item.pedido?.vendedor?.nombre}
																				</div>
																			</div>
																		</div>
																		<button 
																			className="px-2 py-1 text-xs text-red-600 transition-colors rounded bg-red-50 hover:bg-red-100"
																			data-id={item.id_pedido}
																			onClick={onClickEditPedido}
																			title="Ver pedido"
																		>
																			<i className="mr-1 fa fa-eye"></i>
																			#{item.id_pedido}
																		</button>
																	</div>
																))}
															</div>
														</div>
													</div>
												)}
											</div>
										</div>
										
										{/* Footer */}
										<div className="p-2 border-t border-gray-200 rounded-b bg-gray-50">
											<div className="flex items-center justify-between">
												<div className="text-xs text-gray-600">
													<i className="mr-1 fa fa-calculator"></i>
													{moneda(e.totalventa || 0)}
												</div>
												<div className="flex space-x-1">
													<button 
														className="px-2 py-1 text-xs text-blue-600 transition-colors rounded bg-blue-50 hover:bg-blue-100"
														onClick={() => toggleAllSections(e.id)}
														title="Expandir/Contraer todo"
													>
														<i className="fa fa-expand-arrows-alt"></i>
													</button>
													<button 
														className="px-2 py-1 text-xs text-green-600 transition-colors rounded bg-green-50 hover:bg-green-100"
														onClick={() => {
															// Acción para nuevo pedido con este producto
															console.log('Nuevo pedido con producto:', e.id);
														}}
														title="Nuevo pedido con este producto"
													>
														<i className="fa fa-plus"></i>
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							);
						})}
					</div>
				) : (
					<div className="overflow-hidden bg-white border border-gray-200 rounded shadow-sm">
						<div className="overflow-x-auto">
							<table className="w-full text-xs">
								<tbody className="divide-y divide-gray-200">
									{pedidos["fact"]?.map(e => e && (
										<tr 
											key={e.id}
											className={`hover:bg-gray-50 transition-colors ${
												e.estado === 1 ? "bg-green-50" : 
												e.estado === 2 ? "bg-red-50" : 
												"bg-yellow-50"
											}`}
										>
											{/* ID y Estado */}
											<td className="w-24 p-2">
												<div className="flex flex-col items-center space-y-1">
													<button 
														className="w-full px-2 py-1 text-xs font-medium text-white transition-colors bg-orange-500 rounded shadow-sm hover:bg-orange-600"
														data-id={e.id} 
														onClick={onClickEditPedido}
													>
														<i className="mr-1 fa fa-hashtag"></i>
														{e.id}
													</button>
													<span className={`w-full px-1 py-0.5 text-xs font-medium rounded text-center ${
														e.estado === 1 ? "bg-green-100 text-green-800" : 
														e.estado === 2 ? "bg-red-100 text-red-800" : 
														"bg-yellow-100 text-yellow-800"
													}`}>
														{e.estado === 1 ? "Proc" : e.estado === 2 ? "Anul" : "Pend"}
													</span>
												</div>
											</td>

											{/* Información del Vendedor y Fecha */}
											<td className="w-40 p-2">
												<div className="space-y-1">
													<div className="flex items-center">
														<i className="mr-1 text-orange-500 fa fa-user-circle"></i>
														<span className="text-xs font-medium text-gray-900 truncate">{e.vendedor?.nombre}</span>
													</div>
													<div className="flex items-center">
														<i className="mr-1 text-gray-400 fa fa-clock"></i>
														<span className="text-xs text-gray-600">{e.created_at}</span>
													</div>
												</div>
											</td>

											{/* Información del Cliente */}
											<td className="w-48 p-2">
												<div className="space-y-1">
													<div className="flex items-center">
														<i className="mr-1 text-orange-500 fa fa-user"></i>
														<span className="text-xs font-medium text-gray-900 truncate">{e.cliente ? e.cliente.nombre : "Cliente General"}</span>
													</div>
													{e.export && (
														<div className="flex items-center">
															<i className="mr-1 text-green-500 fa fa-check-circle"></i>
															<span className="text-xs font-medium text-green-600">Exportado</span>
														</div>
													)}
												</div>
											</td>

											{/* Métodos de Pago */}
											<td className="w-64 p-2">
												<div className="flex flex-wrap justify-center gap-1">
													{e.pagos?.map(ee => (
														ee.monto !== 0 && (
															<span 
																key={ee.id}
																className={`px-1 py-0.5 rounded text-xs font-medium ${
																	ee.tipo == 1 ? "bg-blue-100 text-blue-800" : 
																	ee.tipo == 2 ? "bg-gray-100 text-gray-800" : 
																	ee.tipo == 3 ? "bg-green-100 text-green-800" : 
																	ee.tipo == 4 ? "bg-yellow-100 text-yellow-800" : 
																	ee.tipo == 5 ? "bg-cyan-100 text-cyan-800" : 
																	"bg-red-100 text-red-800"
																}`}
															>
																<i className={`fa mr-1 ${
																	ee.tipo == 1 ? "fa-exchange" : 
																	ee.tipo == 2 ? "fa-credit-card" : 
																	ee.tipo == 3 ? "fa-money" : 
																	ee.tipo == 4 ? "fa-calendar" : 
																	ee.tipo == 5 ? "fa-mobile" : 
																	"fa-ban"
																}`}></i>
																{ee.tipo == 1 ? (
																	<>
																		T.{ee.monto}
																		{ee.bs !== undefined && (
																			<span className="ml-1 text-xs text-blue-800">/Bs {ee.bs}</span>
																		)}
																	</>
																) : ee.tipo == 2 ? (
																	<>
																		{ee.monto_original && ee.moneda === 'bs' ? (
																			<>
																				D.Bs {parseFloat(ee.monto_original).toFixed(2)}
																				<span className="ml-1 text-[10px] text-gray-600">(${parseFloat(ee.monto).toFixed(2)})</span>
																				{ee.referencia && (
																					<span className="ml-1 text-[10px] text-gray-500">#{ee.referencia}</span>
																				)}
																			</>
																		) : (
																			<>
																				D.${parseFloat(ee.monto).toFixed(2)}
																				{ee.referencia && (
																					<span className="ml-1 text-[10px] text-gray-500">#{ee.referencia}</span>
																				)}
																			</>
																		)}
																	</>
																) : ee.tipo == 3 ? (
																	<>
																		{ee.moneda === 'bs' ? (
																			`E.Bs ${parseFloat(ee.monto_original || ee.monto).toFixed(2)}`
																		) : ee.moneda === 'cop' ? (
																			`E.COP ${parseFloat(ee.monto_original || ee.monto).toFixed(0)}`
																		) : (
																			`E.$${parseFloat(ee.monto).toFixed(2)}`
																		)}
																	</>
																) : ee.tipo == 4 ? (
																	"C." + ee.monto
																) : ee.tipo == 5 ? (
																	"B." + ee.monto
																) : (
																	"V." + ee.monto
																)}
															</span>
														)
													))}
												</div>
											</td>

											{/* Total y Items */}
											<td className="w-32 p-2">
												<div className="flex flex-col items-center space-y-1">
													<span className="text-lg font-bold text-orange-500">{moneda(e.totales)}</span>
													<span className="px-2 py-0.5 bg-gray-800 text-white rounded-full text-xs font-medium">
														<i className="mr-1 fa fa-shopping-cart"></i>
														{e.items ? e.items.length : 0}
													</span>
												</div>
											</td>

											{/* Acciones */}
											<td className="p-2 w-36">
												<div className="flex justify-center gap-1">
													<button 
														className="px-2 py-1 text-xs font-medium text-white transition-colors bg-orange-500 rounded hover:bg-orange-600" 
														data-id={e.id} 
														onClick={onClickEditPedido}
														title="Editar pedido"
													>
														<i className="mr-1 fa fa-edit"></i>
														Edit
													</button>
													<button 
														className="px-1 py-1 text-xs text-orange-600 transition-colors border border-orange-300 rounded hover:bg-orange-50" 
														data-id={e.id} 
														onClick={getPedidoFast}
														title="Ver detalles"
													>
														<i className="fa fa-eye"></i>
													</button>
													<button 
														className="px-1 py-1 text-xs text-green-600 transition-colors border border-green-300 rounded hover:bg-green-50" 
														onClick={() => toggleImprimirTicket(e.id)}
														title="Imprimir ticket"
													>
														<i className="fa fa-print"></i>
													</button>
													{auth(1) && (
														<button 
															className="px-1 py-1 text-xs text-red-600 transition-colors border border-red-300 rounded hover:bg-red-50" 
															data-id={e.id} 
															data-type="getPedidos" 
															onClick={onCLickDelPedido}
															title="Eliminar pedido"
														>
															<i className="fa fa-times"></i>
														</button>
													)}
												</div>
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					</div>
				)}
			</div>

			{togleeReferenciasElec &&
				<RefsList
					settogleeReferenciasElec={settogleeReferenciasElec}
					togleeReferenciasElec={togleeReferenciasElec}
					moneda={moneda}
					refrenciasElecData={refrenciasElecData}
					onClickEditPedido={onClickEditPedido}
					getReferenciasElec={getReferenciasElec}
				/>
			}
		</div>
	);
}
export default Pedidos;