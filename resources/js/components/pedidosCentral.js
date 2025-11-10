import React, { useState, useEffect } from 'react';

import Modalmovil from "./modalmovil";
import TransferenciasModule from "./TransferenciasModule";
import TCRModule from "./TCRModule";

export default function PedidosCentralComponent({
	socketUrl,
	setSocketUrl,
	setInventarioFromSucursal,
	getInventarioFromSucursal,
	getPedidosCentral,
	selectPedidosCentral,
	checkPedidosCentral,

	pedidosCentral,
	setIndexPedidoCentral,
	indexPedidoCentral,
	moneda,

	showaddpedidocentral,
	setshowaddpedidocentral,
	valheaderpedidocentral,
	setvalheaderpedidocentral,
	valbodypedidocentral,
	setvalbodypedidocentral,
	procesarImportPedidoCentral,

	pathcentral,
	setpathcentral,
	mastermachines,
	getmastermachine,

	setinventarioModifiedCentralImport,
	inventarioModifiedCentralImport,
	saveChangeInvInSucurFromCentral,

	getTareasCentral,
	settareasCentral,
	tareasCentral,
	runTareaCentral,

	modalmovilRef,
	modalmovilx,
	modalmovily,
	setmodalmovilshow,
	modalmovilshow,
	getProductos,
	productos,
	linkproductocentralsucursal,
	inputbuscarcentralforvincular,
	openVincularSucursalwithCentral,
	idselectproductoinsucursalforvicular,
	removeVinculoCentral,
	sucursalesCentral,

	qpedidoscentralq,
	setqpedidoscentralq,
	qpedidocentrallimit,
	setqpedidocentrallimit,
	qpedidocentralestado,
	setqpedidocentralestado,
	qpedidocentralemisor,
	setqpedidocentralemisor,
	getSucursales,
	
	openBarcodeScan,
	buscarDatosFact,
	setbuscarDatosFact
}){

	const [subviewcentral, setsubviewcentral] = useState("pedidos")
	const [showdetailsPEdido, setshowdetailsPEdido] = useState(false)
	const [showCorregirDatos, setshowCorregirDatos] = useState(null)
	const [ismovil, setismovil] = useState(window.innerWidth <= 768)
	
	useEffect(() => {
		const handleResize = () => {
			setismovil(window.innerWidth <= 768)
		}
		window.addEventListener('resize', handleResize)
		return () => window.removeEventListener('resize', handleResize)
	}, [])
	
	try {
		return (
			<>

			{modalmovilshow ? (
                <Modalmovil
					margin={100}
                    modalmovilRef={modalmovilRef}
                    x={modalmovilx}
                    y={modalmovily}
                    setmodalmovilshow={setmodalmovilshow}
                    modalmovilshow={modalmovilshow}
                    getProductos={getProductos}
                    productos={productos}
                    linkproductocentralsucursal={linkproductocentralsucursal}
                    inputbuscarcentralforvincular={inputbuscarcentralforvincular}
                />
            ) : null}

				<nav className="flex flex-wrap gap-2 mb-4">
					<button
						className={`px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm
							${subviewcentral === "pedidos"
								? "bg-sinapsis text-white shadow border border-sinapsis"
								: "bg-white text-gray-700 hover:bg-sinapsis border border-gray-300"
							}`}
						onClick={() => { getPedidosCentral(); setsubviewcentral("pedidos"); }}>
						Recibir Pedidos
					</button>
					<button
						className={`px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm
							${subviewcentral === "pedidos_send"
								? "bg-sinapsis text-white shadow border border-sinapsis"
								: "bg-white text-gray-700 hover:bg-sinapsis border border-gray-300"
							}`}
						onClick={() => { setsubviewcentral("pedidos_send"); }}>
						Enviar Pedidos
					</button>
					<button
						className={`px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm
							${subviewcentral === "tcr"
								? "bg-sinapsis text-white shadow border border-sinapsis"
								: "bg-white text-gray-700 hover:bg-sinapsis border border-gray-300"
							}`}
						onClick={() => { setsubviewcentral("tcr"); }}>
						TCR
					</button>
					<button
						className={`px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm
							${subviewcentral === "tareas"
								? "bg-sinapsis text-white shadow border border-sinapsis"
								: "bg-white text-gray-700 hover:bg-sinapsis border border-gray-300"
							}`}
						onClick={() => { setsubviewcentral("tareas"); }}>
						Tareas
					</button>
					{/* 
					<button
						className={`px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm
							${subviewcentral === "inventario"
								? "bg-sinapsis text-white shadow border border-sinapsis"
								: "bg-white text-gray-700 hover:bg-sinapsis hover:text-white border border-gray-300"
							}`}
						onClick={() => { getInventarioFromSucursal(); setsubviewcentral("inventario"); }}>
						Actualizar Inventario
					</button>
					*/}
				</nav>
				{subviewcentral == "tareas" ?
					<>
						<div className="flex items-center gap-4 mb-4">
							<h1 className="text-2xl font-bold flex items-center gap-2">
								Tareas
								<button
									className="inline-flex items-center px-2 py-1 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded transition"
									onClick={() => getTareasCentral([0])}
								>
									<i className="fa fa-search"></i>
								</button>
							</h1>
							<button
								className="px-4 py-2 bg-sinapsis text-white rounded hover:bg-sinapsis-dark transition text-sm font-semibold"
								onClick={() => runTareaCentral()}
							>
								RESOLVER TODO
							</button>
						</div>
						<div className="row">

							<div className="col">
								
								<div>
									{
										tareasCentral.length
											? tareasCentral.map((e,i) =>
												e ?
													<div
														key={e.id}
														className={("bg-light text-secondary") + " card mt-2 pointer"}>
														<div className="card-body flex-row justify-content-between">
															<div>
																<h4>Destino <button className="btn btn-secondary">{e.sucursal.nombre}</button> </h4>
																<small className="text-muted fst-italic">Acción</small> <br />
																<small className="text-muted fst-italic">
																	<b>
																		{e.tipo==1?"MODIFICAR":null}
																		{e.tipo==2?"ELIMINAR DUPLICADOS":null}
																	</b> 
																</small>
																<br />
																<small className="text-muted fst-italic">FECHA</small> <br />
																<small className="text-muted fst-italic"><b>{e.created_at}</b> </small>
																<br />
																{e.prodantesproducto?
																<>


																	<small className="text-success fst-italic">Hay respuesta por resolver</small> 
																	<table className="table">
																		<thead>
																			<tr>
																				<td>id</td>
																				<td>codigo_proveedor</td>
																				<td>codigo_barras</td>
																				<td>descripcion</td>
																				<td>cantidad</td>
																				<td>stockmax</td>
																				<td>stockmin</td>
																				<td>unidad</td>
																				<td>id_categoria</td>
																				<td>id_proveedor</td>
																				<td>precio</td>
																				<td>precio_base</td>
																				<td>iva</td>
																				<td>INV</td>
																			</tr>
																		</thead>
																		<tbody key={e.id}>	
																			{e["prodantesproducto"]?
																				<tr className='bg-danger-light'>
																					<td>{e["prodantesproducto"].id}</td>
																					
																					<td>{e["prodantesproducto"].codigo_proveedor}</td>
																					<td>{e["prodantesproducto"].codigo_barras}</td>
																					<td>{e["prodantesproducto"].descripcion}</td>
																					<td>{e["prodantesproducto"].cantidad}</td>
																					<td>{e["prodantesproducto"].stockmax}</td>
																					<td>{e["prodantesproducto"].stockmin}</td>
																					<td>{e["prodantesproducto"].unidad}</td>
																					<td>{e["prodantesproducto"].id_categoria}</td>
																					<td>{e["prodantesproducto"].id_proveedor}</td>
																					<td>{e["prodantesproducto"].precio}</td>
																					<td>{e["prodantesproducto"].precio_base}</td>
																					<td>{e["prodantesproducto"].iva}</td>
																					<td>{e["prodantesproducto"].push}</td>
																					
																				</tr>
																			:null}
																			{e["prodcambiarproducto"]?
																				<tr className='bg-success-light'>
																					<td>{e["prodcambiarproducto"].id}</td>
																				
																					<td>{e["prodcambiarproducto"].codigo_proveedor}</td>
																					<td>{e["prodcambiarproducto"].codigo_barras}</td>
																					<td>{e["prodcambiarproducto"].descripcion}</td>
																					<td>{e["prodcambiarproducto"].cantidad}</td>
																					<td>{e["prodcambiarproducto"].stockmax}</td>
																					<td>{e["prodcambiarproducto"].stockmin}</td>
																					<td>{e["prodcambiarproducto"].unidad}</td>
																					<td>{e["prodcambiarproducto"].id_categoria}</td>
																					<td>{e["prodcambiarproducto"].id_proveedor}</td>
																					<td>{e["prodcambiarproducto"].precio}</td>
																					<td>{e["prodcambiarproducto"].precio_base}</td>
																					<td>{e["prodcambiarproducto"].iva}</td>
																					<td>{e["prodcambiarproducto"].push}</td>
																					
																				</tr>
																			:null}
																		</tbody>
																	</table>


																</>
																:null}
															</div>
														</div>

													</div>
													: null
											)
											: <div className='h3 text-center text-dark mt-2'><i>¡Sin resultados!</i></div>

									}
								</div>
							</div>
						</div>
					</>
					: null}
				{subviewcentral == "pedidos" ?
					<div className="container-fluid mt-3"> {/* Añadido mt-3 para un poco de margen superior */}
						<h1 className="mb-4 text-center text-md-start">TRANSFERENCIAS</h1> {/* mb-4 y text-center en móvil */}
					
						<div className="row">
							{/* Columna Izquierda: Filtros y Lista de Transferencias */}
							<div className="col-lg-3 col-md-4 col-12 mb-3 mb-md-0">
								<div className="card shadow-sm">
									<div className="card-body">
										<form onSubmit={event => { event.preventDefault(); getPedidosCentral() }} className='mb-3'>
											{/* Primera fila: ID y Resultados */}
											<div className="row g-2 mb-2">
												<div className="col-8">
													<input 
														type="text" 
														className="form-control form-control-sm" 
														placeholder='Número de Transferencia...' 
														value={qpedidoscentralq} 
														onChange={e => setqpedidoscentralq(e.target.value)} 
													/>
												</div>
												<div className="col-4">
													<select 
														className="form-select form-select-sm" 
														value={qpedidocentrallimit} 
														onChange={e => setqpedidocentrallimit(e.target.value)}
													>
														<option value="">-RESULTADOS-</option>
														<option value="5">5 (SúperRápida)</option>
														<option value="10">10 (Rápida)</option>
														<option value="20">20 (Media)</option>
														<option value="50">50 (Lenta)</option>
														<option value="100">100 (SúperLenta)</option>
													</select>
												</div>
											</div>

											{/* Segunda fila: Estado, Emisor y Botón SUCS */}
											<div className="row g-2 mb-2">
												<div className="col-5">
													<select 
														className="form-select form-select-sm" 
														value={qpedidocentralestado} 
														onChange={e => setqpedidocentralestado(e.target.value)}
													>
														<option value="">-ESTADO-</option>
														<option value="1">PENDIENTE</option>
														<option value="3">EN REVISIÓN</option>
														<option value="4">REVISADO</option>
													</select>
												</div>
												<div className="col-5">
													<select 
														className="form-select form-select-sm" 
														value={qpedidocentralemisor} 
														onChange={e => setqpedidocentralemisor(e.target.value)}
													>
														<option value="">-EMISOR-</option>
														{sucursalesCentral.map(e =>
															<option value={e.id} key={e.id}>
																{e.nombre}
															</option>
														)}
													</select>
												</div>
												<div className="col-2">
													<button 
														type="button" 
														className="btn btn-outline-secondary btn-sm w-100" 
														onClick={getSucursales}
													>
														<i className="fa fa-sync-alt"></i>
													</button>
												</div>
											</div>

											{/* Botón de búsqueda */}
											<div className="d-grid">
												<button 
													type="submit" 
													className="btn btn-outline-success btn-sm"
												>
													<i className="fa fa-search"></i> BUSCAR TRANSFERENCIAS
												</button>
											</div>
										</form>
					
										<hr />
					
										<div className="d-flex justify-content-between align-items-center mb-2">
											<h6 className="mb-0">Lista de Transferencias</h6>
											<button 
												className="btn btn-sm btn-outline-secondary" 
												onClick={() => setshowdetailsPEdido(!showdetailsPEdido)}
											>
												<i className={`fas ${showdetailsPEdido ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>
											</button>
										</div>

										<div className={`transition-all duration-300 ${showdetailsPEdido ? 'max-h-[60vh]' : 'max-h-0'} overflow-hidden`}>
											<div className="overflow-y-auto" style={{ maxHeight: '60vh' }}>
												{pedidosCentral.length
													? pedidosCentral.map((e, i) =>
														e ? (
															<div onClick={() => setIndexPedidoCentral(i)} data-index={i} key={e.id} className={`card mb-2 pointer hover-shadow ${indexPedidoCentral === i ? 'border-primary' : ''}`}>
																<div className="card-body p-2">
																	<div className='row g-0 align-items-center'>
																		<div className={
																			(e.estado == 1 ? "bg-danger text-light" :
																				e.estado == 3 ? "bg-warning text-dark" :
																					e.estado == 4 ? "bg-info text-light" : "") + (" col-auto d-flex align-items-center justify-content-center p-3 rounded-start")
																		} style={{ minHeight: '100%', fontSize: '1.2rem' }}>
																			{/* Icono o texto de estado */}
																			{e.estado == 1 ? <i className="fas fa-exclamation-circle"></i> :
																			e.estado == 3 ? <i className="fas fa-search"></i> :
																			e.estado == 4 ? <i className="fas fa-check-circle"></i> : <i className="fas fa-question-circle"></i>}
																		</div>
																		<div className="col">
																			<div className="ps-2">
																				<div className={(indexPedidoCentral === i ? "fw-bold" : "text-secondary") + " d-flex flex-column flex-sm-row justify-content-between align-items-start w-100 mb-1"}>
																					<div>
																						<span className="badge bg-secondary me-2">#{e.id}</span>
																						<span className="badge" style={{ backgroundColor: e.origen.background, color: e.origen.color || '#fff' }}>{e.origen.codigo}</span>
																					</div>
																					<span className="text-muted fst-italic small mt-1 mt-sm-0">ITEMS: <b>{e.items.length}</b></span>
																				</div>
																				{e.cxp && (
																					<div className="small fw-bold mb-1">
																						<span className='text-sinapsis me-2'>{e.cxp?"FACT "+e.cxp.numfact:null}</span>
																						<span>{e.cxp?e.cxp.proveedor.descripcion.substr(0,20):null}{e.cxp && e.cxp.proveedor.descripcion.length > 20 ? "..." : ""}</span>
																					</div>
																				)}
																				<div className='text-center text-sm-start w-100'>
																					<small className="text-muted fst-italic">{e.created_at}</small>
																				</div>
																			</div>
																		</div>
																	</div>
																</div>
															</div>
														) : null
													)
													: <div className='h5 text-center text-muted mt-4 p-3 border rounded'><i>¡Sin resultados!</i></div>
												}
											</div>
										</div>
									</div>
								</div>
							</div>
					
							{/* Columna Derecha: Detalles de Transferencia o Formulario de Importación */}
							<div className="col-lg-9 col-md-8 col-12">
								{!showaddpedidocentral ? (
									<div className="card shadow-sm">
										<div className="card-body">
											{indexPedidoCentral !== null && pedidosCentral && pedidosCentral[indexPedidoCentral] ? (
												<>
													<div className="d-flex flex-column flex-sm-row justify-content-between align-items-start p-2 border rounded mb-3 bg-light">
														<div className="mb-2 mb-sm-0">
															<small className="text-muted fst-italic d-block">{pedidosCentral[indexPedidoCentral].created_at}</small>
															<div className="d-flex align-items-center">
																<span className="fs-4 fw-bold me-2">ID:</span>
																<span className="btn btn-secondary btn-sm" onClick={() => setshowdetailsPEdido(!showdetailsPEdido)}>
																	{pedidosCentral[indexPedidoCentral].id} <i className={`fas ${showdetailsPEdido ? 'fa-chevron-up' : 'fa-chevron-down'} ms-1`}></i>
																</span>
															</div>
														</div>
														<div className="text-sm-end">
															<div className="mb-1">
																<span className="h6 text-muted font-italic">Base: </span>
																<span className="h6 text-sinapsis">{moneda(pedidosCentral[indexPedidoCentral].base)}</span>
															</div>
															<div className="mb-1">
																<span className="h6 text-muted font-italic">Venta: </span>
																<span className="h5 text-success fw-bold">{moneda(pedidosCentral[indexPedidoCentral].venta)}</span>
															</div>
															<span className="h6 text-muted">Items: <b>{pedidosCentral[indexPedidoCentral].items.length}</b></span>
														</div>
													</div>
					
													{/* {showdetailsPEdido && (
														<div className="table-responsive mb-3">
															<table className="table table-sm table-bordered table-hover">
																<thead className="table-light">
																	<tr>
																		<th>Origen</th>
																		<th>Cant.</th>
																		<th>C.Barras</th>
																		<th>C.Prov.</th>
																		<th>Descripción</th>
																		<th className="text-end">P.Base</th>
																		<th className="text-end">P.Venta</th>
																		<th className="text-end">Monto</th>
																	</tr>
																</thead>
																<tbody>
																	{pedidosCentral[indexPedidoCentral].items.map((item, itemIdx) => (
																		<tr key={itemIdx}>
																			<td><small className='text-muted'>{pedidosCentral[indexPedidoCentral].origen.codigo}</small></td>
																			<th className="align-middle fs-5">{item.cantidad.toString().replace(/\.00/, "")}</th>
																			<td>{item.producto.codigo_barras || "-"}</td>
																			<td>{item.producto.codigo_proveedor || "-"}</td>
																			<td>{item.producto.descripcion}</td>
																			<td className="text-end text-sinapsis">{moneda(item.producto.precio_base)}</td>
																			<td className="text-end text-success">{moneda(item.producto.precio)}</td>
																			<td className="text-end fw-bold">{moneda(item.monto)}</td>
																		</tr>
																	))}
																</tbody>
															</table>
														</div>
													)} */}
					
													<div className="input-group mb-3">
														<span className="input-group-text"><i className="fas fa-search"></i></span>
														<input type="text" className="form-control fs-5" placeholder='Buscar producto en transferencia...' value={buscarDatosFact} onChange={event => setbuscarDatosFact(event.target.value)} />
														<span className="input-group-text pointer" onClick={() => openBarcodeScan("setbuscarDatosFact")}>
															<i className="fas fa-barcode"></i>
														</span>
													</div>
					
													{/* Vista de Items para Móvil (ismovil=true) */}
													{ismovil && pedidosCentral[indexPedidoCentral] && (
														<div>
															{pedidosCentral[indexPedidoCentral].items
																.filter(fil => {
																	if (!buscarDatosFact) return true;
																	const searchTerm = buscarDatosFact.toLowerCase();
																	return (fil.producto.codigo_barras && fil.producto.codigo_barras.toLowerCase().includes(searchTerm)) ||
																		(fil.producto.codigo_proveedor && fil.producto.codigo_proveedor.toLowerCase().includes(searchTerm)) ||
																		(fil.producto.descripcion && fil.producto.descripcion.toLowerCase().includes(searchTerm));
																})
																.map((e, i) => (
																<div key={e.id} className={`mb-3 border rounded p-3 ${e.aprobado ? "bg-success-light" : "bg-light"}`}>
																	{e.super !== 1 && e.vinculo_sugerido && (
																		<div className="mb-2 p-2 border rounded bg-light-warning">
																			<small className="text-muted d-block mb-1">Sugerido por Suc. Destino:</small>
																			{e.vinculo_real && (
																				<button className="btn btn-warning btn-sm fs-10px mb-1">
																					{e.vinculo_real} <i className="fa fa-link"></i>
																				</button>
																			)}
																			<div><small><strong>Desc:</strong> {e.vinculo_sugerido.descripcion}</small></div>
																			<div><small><strong>Barras:</strong> {e.vinculo_sugerido.codigo_barras}</small></div>
																			<div><small><strong>Proveedor:</strong> {e.vinculo_sugerido.codigo_proveedor}</small></div>
																		</div>
																	)}
					
																	<div className="d-flex justify-content-between align-items-center mb-2">
																		<div>
																			{typeof (e.aprobado) === "undefined" ? (
																				<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-outline-danger btn-sm me-2">
																					<i className="fa fa-times"></i> {i + 1}
																				</button>
																			) : e.aprobado ? (
																				<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-success btn-sm me-2"> {/* Cambiado a btn-success si aprobado */}
																					<i className="fa fa-check"></i> {i + 1}
																				</button>
																			) : ( // No aprobado explícitamente (e.aprobado === false), podría ser un estado intermedio o rechazado
																				<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-outline-secondary btn-sm me-2">
																					<i className="fa fa-ban"></i> {i + 1} {/* Ejemplo de ícono para no aprobado */}
																				</button>
																			)}
																		</div>
																		<i className="fa fa-question-circle text-warning fa-lg pointer" onClick={() => setshowCorregirDatos(showCorregirDatos !== i ? i : null)}></i>
																	</div>
					
																	<div className="mb-2"><strong>Barras:</strong> {e.producto.codigo_barras || <small className="text-muted">N/A</small>}</div>
																	{showCorregirDatos === i && (
																		<input className="form-control form-control-sm mb-2" type="text" value={e.barras_real || ""} data-index={i} data-tipo="changebarras_real" onChange={selectPedidosCentral} placeholder="Corregir Barras..." />
																	)}
																	<div className="mb-2"><strong>Proveedor:</strong> {e.producto.codigo_proveedor || <small className="text-muted">N/A</small>}</div>
																	{/* 
																	{showCorregirDatos === i && (
																		<input className="form-control form-control-sm mb-2" type="text" value={e.alterno_real || ""} data-index={i} data-tipo="changealterno_real" onChange={selectPedidosCentral} placeholder="Corregir Alterno..." />
																	)}
					
																	<div className="mb-2"><strong>Descripción:</strong> {e.producto.descripcion} <small className="text-muted ms-1">({pedidosCentral[indexPedidoCentral].origen.codigo})</small></div>
																	{showCorregirDatos === i && (
																		<input className="form-control form-control-sm mb-2" type="text" value={e.descripcion_real || ""} data-index={i} data-tipo="changedescripcion_real" onChange={selectPedidosCentral} placeholder="Corregir Descripción..." />
																	)}
																	
																	<div className="row">
																		<div className="col-6 mb-2"><strong>Cantidad:</strong> {e.cantidad}</div>
																		{showCorregirDatos === i && (
																			<div className="col-6 mb-2">
																				<input className="form-control form-control-sm" type="text" value={e.ct_real || ""} data-index={i} data-tipo="changect_real" onChange={selectPedidosCentral} placeholder="Corregir Ct..." />
																			</div>
																		)}
																	</div> */}
					
																	<div className="mb-2"><strong>Descripción:</strong> {e.producto.descripcion} <small className="text-muted ms-1">({pedidosCentral[indexPedidoCentral].origen.codigo})</small></div>
					
																	<div className="row">
																		<div className="col-6 mb-2 text-sinapsis"><strong>Base:</strong> {moneda(e.base)}</div>
																		<div className="col-6 mb-2 text-success"><strong>Venta:</strong> {moneda(e.venta)}</div>
																	</div>
																	<div className="mb-2 text-end fw-bold"><strong>Monto:</strong> {moneda(e.monto)}</div>
					
																	{e.super === 1 && (
																		<div className="text-center p-2 mt-2 rounded" style={{ background: "linear-gradient(45deg, rgb(255, 215, 0), rgb(255, 165, 0))", color: "#000", fontStyle: "italic" }}>
																			<i className="fa fa-globe"></i> SUPER GLOBAL
																		</div>
																	)}
																</div>
															))}
														</div>
													)}
					
													{/* Vista de Items para Desktop (ismovil=false) */}
													{!ismovil && pedidosCentral[indexPedidoCentral] && (
														<div className="table-responsive">
															<table className="table table-sm table-bordered table-hover">
																<thead className="table-light">
																	<tr>
																		<th><small>Verificar</small></th>
																		<th>ID</th>
																		<th>Barras </th>
																		<th>Alterno </th>
																		<th>Descripción </th>
																		<th className="text-center">Cant.</th>
																		<th className="text-end">Base</th>
																		<th className="text-end">Venta</th>
																		<th className="text-end">Subtotal</th>
																	</tr>
																</thead>
																<tbody>
																{pedidosCentral[indexPedidoCentral].items
																	.filter(fil => {
																		if (!buscarDatosFact) return true;
																		const searchTerm = buscarDatosFact.toLowerCase();
																		return (fil.producto.codigo_barras && fil.producto.codigo_barras.toLowerCase().includes(searchTerm)) ||
																			(fil.producto.codigo_proveedor && fil.producto.codigo_proveedor.toLowerCase().includes(searchTerm)) ||
																			(fil.producto.descripcion && fil.producto.descripcion.toLowerCase().includes(searchTerm));
																	})
																	.map((e, i) => (
																	<React.Fragment key={e.id}>
																		{/* {e.super !== 1 && e.vinculo_sugerido && (
																			<tr className="table-warning">
																				<td></td>
																				<td colSpan="8">
																					<small className="text-muted d-block mb-1">Sugerido por Suc. Destino:</small>
																					{e.vinculo_real && ( <button className="btn btn-warning btn-xs me-1">{e.vinculo_real} <i className="fa fa-link"></i></button> )}
																					<small><strong>Desc:</strong> {e.vinculo_sugerido.descripcion}</small>, <small><strong>Barras:</strong> {e.vinculo_sugerido.codigo_barras}</small>, <small><strong>Prov:</strong> {e.vinculo_sugerido.codigo_proveedor}</small>
																				</td>
																			</tr>
																		)} */}
																		<tr style={e.super === 1 ? {
																			background: "linear-gradient(45deg, #FFD700, #FFA500)",
																			color: "#000",
																			fontWeight: "bold",
																			boxShadow: "0 0 10px rgba(255, 215, 0, 0.5)"
																		} : {}} 
																		className={e.aprobado ? "bg-success-light" : ""}>
																			<td className='align-middle'>
																				<div className="btn-group">
																					{typeof (e.aprobado) === "undefined" ? (
																						<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-outline-danger btn-sm">
																							<i className="fa fa-times"></i>
																						</button>
																					) : e.aprobado ? (
																						<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-success btn-sm">
																							<i className="fa fa-check"></i>
																						</button>
																					) : (
																						<button onClick={selectPedidosCentral} data-index={i} data-tipo="select" className="btn btn-outline-secondary btn-sm">
																							<i className="fa fa-ban"></i>
																						</button>
																					)}
																					<button className="btn btn-outline-dark btn-sm" onClick={() => setshowCorregirDatos(showCorregirDatos !== i ? i : null)}>
																						<i className={`fa ${showCorregirDatos === i ? "fa-minus-circle" : "fa-question-circle"}`}></i>
																					</button>
																				</div>
																			</td>
																			<td className='align-middle'>{e.id}</td>
																			<td className='align-middle'>
																				{e.producto.codigo_barras || <small className="text-muted">N/A</small>}
																				{showCorregirDatos === i && <input type="text" className="form-control form-control-sm mt-1" value={e.barras_real || ""} data-index={i} data-tipo="changebarras_real" onChange={selectPedidosCentral} placeholder="Corregir Barras..." />} 
																			</td>
																			<td className='align-middle'>
																				{e.producto.codigo_proveedor || <small className="text-muted">N/A</small>}
																				{/* {showCorregirDatos === i && <input type="text" className="form-control form-control-sm mt-1" value={e.alterno_real || ""} data-index={i} data-tipo="changealterno_real" onChange={selectPedidosCentral} placeholder="Corregir Alterno..." />} */}
																			</td>
																			<td className='align-middle'>
																				{e.producto.descripcion} <small className='text-muted'>({pedidosCentral[indexPedidoCentral].origen.codigo})</small>
																				{/* {showCorregirDatos === i && <input type="text" className="form-control form-control-sm mt-1" value={e.descripcion_real || ""} data-index={i} data-tipo="changedescripcion_real" onChange={selectPedidosCentral} placeholder="Corregir Descripción..." />} */}
																			</td>
																			<td className='align-middle text-center'>
																				{e.cantidad.toString().replace(/\.00/, "")}
																				{/* {showCorregirDatos === i && <input type="text" className="form-control form-control-sm mt-1" value={e.ct_real || ""} data-index={i} data-tipo="changect_real" onChange={selectPedidosCentral} placeholder="Corregir Ct..." />} */}
																			</td>
																			<td className='align-middle text-end'>{moneda(e.base)}</td>
																			<td className='align-middle text-end text-success'>{moneda(e.venta)}</td>
																			<td className='align-middle text-end fw-bold'>{moneda(e.monto)}</td>
																		</tr>
																		{/* {e.super === 1 && (
																			<tr>
																				<td colSpan="9" className="text-center p-1" style={{ background: "linear-gradient(45deg, rgb(255, 215, 0), rgb(255, 165, 0))", color: "#000", fontStyle: "italic" }}>
																					<i className="fa fa-globe"></i> SUPER GLOBAL
																				</td>
																			</tr>
																		)} */}
																		</React.Fragment>
																	))}
																</tbody>
															</table>
														</div>
													)}
					
													{pedidosCentral[indexPedidoCentral] && !pedidosCentral[indexPedidoCentral].items.filter(e => (typeof (e.aprobado) === "undefined")).length && (
														<div className="d-grid mt-3">
															<button className="btn btn-success btn-lg" onClick={checkPedidosCentral}>
																<i className="fa fa-save me-2"></i>Guardar Pedido Verificado
															</button>
														</div>
													)}
												</>
											) : (
												<div className="text-center p-5 border rounded bg-light">
													<i className="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
													<h5>Seleccione una transferencia</h5>
													<p className="text-muted">Haz clic en una transferencia de la lista de la izquierda para ver sus detalles aquí.</p>
												</div>
											)}
										</div>
									</div>
								) : (
									<div className="card shadow-sm">
										<div className="card-header">
											<h3>Importar Pedido</h3>
										</div>
										<div className="card-body">
											<div className="form-group mb-3">
												<label htmlFor="headerPedido" className="form-label">Cabecera del pedido</label>
												<input id="headerPedido" type="text" className="form-control" value={valheaderpedidocentral} onChange={e => setvalheaderpedidocentral(e.target.value)} placeholder="Cabecera del pedido" />
											</div>
											<div className="form-group mb-3">
												<label htmlFor="bodyPedido" className="form-label">Cuerpo del pedido</label>
												<textarea id="bodyPedido" className="form-control" value={valbodypedidocentral} onChange={e => setvalbodypedidocentral(e.target.value)} placeholder="Cuerpo del pedido" rows="10"></textarea>
											</div>
											<div className="d-grid">
												<button className="btn btn-primary btn-lg" onClick={procesarImportPedidoCentral}><i className="fa fa-upload me-2"></i>Importar</button>
											</div>
										</div>
									</div>
								)}
							</div>
						</div>
					</div>
					: null}
				
				{subviewcentral == "pedidos_send" ? (<TransferenciasModule/>) : null}

				{subviewcentral == "tcr" ? (
					<TCRModule
						pedidosCentral={pedidosCentral}
						indexPedidoCentral={indexPedidoCentral}
						setIndexPedidoCentral={setIndexPedidoCentral}
						qpedidoscentralq={qpedidoscentralq}
						setqpedidoscentralq={setqpedidoscentralq}
						qpedidocentrallimit={qpedidocentrallimit}
						setqpedidocentrallimit={setqpedidocentrallimit}
						qpedidocentralemisor={qpedidocentralemisor}
						setqpedidocentralemisor={setqpedidocentralemisor}
						sucursalesCentral={sucursalesCentral}
						getPedidosCentral={getPedidosCentral}
						getSucursales={getSucursales}
						showdetailsPEdido={showdetailsPEdido}
						setshowdetailsPEdido={setshowdetailsPEdido}
						moneda={moneda}
						selectPedidosCentral={selectPedidosCentral}
						checkPedidosCentral={checkPedidosCentral}
					/>
				) : null}

			</>
		)	
	} catch (error) {
		alert("Error en PedidosCentral.js"+error)
		return ""	
	}
	

}