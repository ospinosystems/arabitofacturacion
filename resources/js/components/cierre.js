import React, { useEffect, useState, useMemo } from 'react';
import Historicocierre from '../components/historicocierre';
import LotesPinpad from '../components/cierre_lotes_pinpad';
import TotalizarCierre from '../components/totalizar_cierre';
import { cloneDeep } from "lodash";
import db from "../database/database";

// Función para formatear números con separadores de miles
const formatNumber = (num) => {
	if (num === null || num === undefined) return '0.00';
	return parseFloat(num).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

function Cierre({
	dataPuntosAdicionales,
	setdataPuntosAdicionales,
	addTuplasPuntosAdicionales,

	tipoUsuarioCierre,
	settipoUsuarioCierre,
	moneda,
	caja_usd,
	caja_cop,
	caja_bs,
	caja_punto,
	setcaja_biopago,
	caja_biopago,

	notaCierre,

	dejar_usd,
	dejar_cop,
	dejar_bs,

	setDejar_usd,
	setDejar_cop,
	setDejar_bs,

	onchangecaja,
	cierre,
	cerrar_dia,
	total_caja_neto,
	total_punto,
	total_biopago,
	fechaCierre,
	setFechaCierre,

	viewCierre,
	setViewCierre,
	toggleDetallesCierre,
	setToggleDetallesCierre,
	guardar_cierre,
	sendCuentasporCobrar,
	total_dejar_caja_neto,

	guardar_usd,
	setguardar_usd,
	guardar_cop,
	setguardar_cop,
	guardar_bs,
	setguardar_bs,

	number,

	billete1,
	billete5,
	billete10,
	billete20,
	billete50,
	billete100,
	
	setbillete1,
	setbillete5,
	setbillete10,
	setbillete20,
	setbillete50,
	setbillete100,
	setCaja_usd,
	setCaja_cop,
	setCaja_bs,
	setCaja_punto,
	veryenviarcierrefun,

	dolar,
	peso,
	cierres,
	fechaGetCierre,
	setfechaGetCierre,
	fechaGetCierre2,
	setfechaGetCierre2,
	getCierres, 
	verCierreReq,

	auth,
	user,
	totalizarcierre,
	setTotalizarcierre,

	tipo_accionCierre,
	settipo_accionCierre,
	getTotalizarCierre,

	cierrenumreportez,
	setcierrenumreportez,
	cierreventaexcento,
	setcierreventaexcento,
	cierreventagravadas,
	setcierreventagravadas,
	cierreivaventa,
	setcierreivaventa,
	cierretotalventa,
	setcierretotalventa,
	cierreultimafactura,
	setcierreultimafactura,
	cierreefecadiccajafbs,
	setcierreefecadiccajafbs,
	cierreefecadiccajafcop,
	setcierreefecadiccajafcop,
	cierreefecadiccajafdolar,
	setcierreefecadiccajafdolar,
	cierreefecadiccajafeuro,
	setcierreefecadiccajafeuro,
	fun_setguardar,


	setCajaFuerteEntradaCierreDolar,
	CajaFuerteEntradaCierreDolar,
	setCajaFuerteEntradaCierreCop,
	CajaFuerteEntradaCierreCop,
	setCajaFuerteEntradaCierreBs,
	CajaFuerteEntradaCierreBs,
	setCajaChicaEntradaCierreDolar,
	CajaChicaEntradaCierreDolar,
	setCajaChicaEntradaCierreCop,
	CajaChicaEntradaCierreCop,
	setCajaChicaEntradaCierreBs,
	CajaChicaEntradaCierreBs,
	setcajaFuerteFun,

	serialbiopago,
	setserialbiopago,

	lotespuntototalizar,
	biopagostotalizar,
	lotesPinpad,
	setLotesPinpad,

	reversarCierre,
	bancos,

}) {

	useEffect(()=>{
		// Usar los valores de efectivo guardado que vienen del backend
		if (cierre && cierre.efectivo_guardado_usd !== undefined) {
			setguardar_usd(cierre.efectivo_guardado_usd);
		}
		if (cierre && cierre.efectivo_guardado_cop !== undefined) {
			setguardar_cop(cierre.efectivo_guardado_cop);
		}
		if (cierre && cierre.efectivo_guardado_bs !== undefined) {
			setguardar_bs(cierre.efectivo_guardado_bs);
		}
	},[cierre]);

	// Estado para modal de totalizar cierre (solo admin)
	const [mostrarTotalizar, setMostrarTotalizar] = useState(false);
	
	// Estados para controlar bloqueo de cálculo
	const [hayCierreGuardado, setHayCierreGuardado] = useState(false);
	const [hayCierreAdmin, setHayCierreAdmin] = useState(false);
	const [bloqueado, setBloqueado] = useState(false);
	const [mensajeBloqueo, setMensajeBloqueo] = useState('');
	
	// Detectar si es administrador (nivel 1)
	const esAdministrador = user && user.nivel === 1;
	
	// Auto-activar totalizar si es administrador
	useEffect(() => {
		if (esAdministrador) {
			setTotalizarcierre(true);
		}
	}, [user, esAdministrador]);
	
	// Auto-abrir modal totalizado cuando se ejecute el cierre siendo admin
	useEffect(() => {
		if (esAdministrador && cierre && cierre["fecha"]) {
			setMostrarTotalizar(true);
		}
	}, [cierre, cierre?.fecha, esAdministrador]);
	
	// Verificar estado del cierre al cargar/cambiar fecha
	useEffect(() => {
		verificarEstadoCierre();
	}, [fechaCierre, tipo_accionCierre, cierre?.fecha]);
	
	const verificarEstadoCierre = async () => {
		try {
			// Verificar si hay cierre guardado
			const response = await db.getStatusCierre();
			
			if (response.data && response.data.tipo_accionCierre) {
				const tipoAccion = response.data.tipo_accionCierre;
				
				// Si tipo_accion es "editar", significa que ya hay un cierre guardado
				if (tipoAccion === "editar") {
					setHayCierreGuardado(true);
					
					// Verificar si hay cierre de administrador
					try {
						const cierresResponse = await db.getCierres({ 
							fechaGetCierre: fechaCierre, 
							fechaGetCierre2: fechaCierre,
							tipoUsuarioCierre: 1 // Tipo admin
						});
						
						if (cierresResponse.data && cierresResponse.data.cierres && cierresResponse.data.cierres.length > 0) {
							setHayCierreAdmin(true);
							setBloqueado(true);
							setMensajeBloqueo('No se puede recalcular. Existe un cierre de administrador. Debe reversarlo primero.');
						} else {
							setHayCierreAdmin(false);
							setBloqueado(true);
							setMensajeBloqueo('Ya existe un cierre guardado. No puede recalcular sin eliminarlo primero.');
						}
					} catch (error) {
						console.error('Error verificando cierre admin:', error);
						setHayCierreAdmin(false);
						setBloqueado(true);
						setMensajeBloqueo('Ya existe un cierre guardado. No puede recalcular sin eliminarlo primero.');
					}
				} else {
					// No hay cierre guardado, permitir cálculo
					setHayCierreGuardado(false);
					setHayCierreAdmin(false);
					setBloqueado(false);
					setMensajeBloqueo('');
				}
			}
		} catch (error) {
			console.error('Error verificando estado del cierre:', error);
			// En caso de error, no bloquear
			setBloqueado(false);
		}
	};

	// Validar que lo que se deja en caja no sea mayor a lo disponible
	const validarDejar = (campo, valor) => {
		const valorNumerico = parseFloat(valor) || 0;
		
		if (campo === 'dejar_usd') {
			const disponible = parseFloat(caja_usd) || 0;
			if (valorNumerico > disponible) {
				alert(`No puedes dejar $${valorNumerico.toFixed(2)} USD en caja. Solo hay $${disponible.toFixed(2)} USD disponible.`);
				return disponible.toFixed(2);
			}
		} else if (campo === 'dejar_bs') {
			const disponible = parseFloat(caja_bs) || 0;
			if (valorNumerico > disponible) {
				alert(`No puedes dejar ${valorNumerico.toFixed(2)} Bs en caja. Solo hay ${disponible.toFixed(2)} Bs disponible.`);
				return disponible.toFixed(2);
			}
		} else if (campo === 'dejar_cop') {
			const disponible = parseFloat(caja_cop) || 0;
			if (valorNumerico > disponible) {
				alert(`No puedes dejar ${valorNumerico.toFixed(0)} COP en caja. Solo hay ${disponible.toFixed(0)} COP disponible.`);
				return disponible.toFixed(0);
			}
		}
		
		return valor;
	};

	// Wrapper para onchangecaja que valida los campos "dejar"
	const onchangecajaValidado = (event) => {
		const campo = event.target.name;
		let valor = event.target.value;
		
		// Si es un campo "dejar", validar
		if (campo === 'dejar_usd' || campo === 'dejar_bs' || campo === 'dejar_cop') {
			valor = validarDejar(campo, valor);
			
			// Crear evento sintético con el valor validado
			event.target.value = valor;
		}
		
		// Llamar a la función original
		onchangecaja(event);
	};


	// Calcular total de Punto de Venta (Lotes Otros Puntos de Venta + Lotes Pinpad)
	useEffect(() => {
		// Sumar lotes de otros puntos de venta
		const totalOtrosPuntos = dataPuntosAdicionales.reduce((sum, lote) => {
			return sum + (parseFloat(lote.monto) || 0);
		}, 0);

		// Sumar lotes Pinpad
		const totalLotesPinpad = (lotesPinpad || []).reduce((sum, lote) => {
			return sum + (parseFloat(lote.monto_bs) || 0);
		}, 0);

		const totalPuntoVenta = totalOtrosPuntos + totalLotesPinpad;
		setCaja_punto(totalPuntoVenta.toFixed(2));
	}, [dataPuntosAdicionales, lotesPinpad]);

	let totalCajaFuerte = (parseFloat(CajaFuerteEntradaCierreDolar) + parseFloat(CajaFuerteEntradaCierreCop/peso) + parseFloat(CajaFuerteEntradaCierreBs/dolar) ).toFixed(2)

	let sumCajapunto = 0
	const onchangetuplapuntosnew = (type,index,valor) => {
		let clone = cloneDeep(dataPuntosAdicionales)
		clone = clone.map((e,i)=>{
			if (i==index) {
				e[type] = valor

				if (type=="monto") {
					sumCajapunto += (valor == "NaN" || !valor ? "" : parseFloat(valor));
				}
			}

			if (i!=index && type=="monto") {
				sumCajapunto += parseFloat(e.monto)
			}
			return e	

		})
		if(type=="monto"){
			setCaja_punto(sumCajapunto)
		}
		setdataPuntosAdicionales(clone)
		
	}

	// Función para cambiar el banco de un lote Pinpad
	const onChangeBancoPinpad = (index, banco) => {
		const nuevosLotes = [...lotesPinpad];
		nuevosLotes[index] = {
			...nuevosLotes[index],
			banco: banco
		};
		setLotesPinpad(nuevosLotes);
	}

	// Estado para el modal de detalles de pagos
	const [modalPagos, setModalPagos] = useState({
		isOpen: false,
		tipo: null,
		moneda: null,
		titulo: ''
	});

	// Función para abrir modal de detalles de pagos
	const abrirModalPagos = (tipo, moneda, titulo) => {
		setModalPagos({
			isOpen: true,
			tipo: tipo,
			moneda: moneda,
			titulo: titulo
		});
	};

	const cerrarModalPagos = () => {
		setModalPagos({
			isOpen: false,
			tipo: null,
			moneda: null,
			titulo: ''
		});
	};
	
	// Función wrapper para validar antes de ejecutar cerrar_dia
	const ejecutarCerrarDia = (e) => {
		if (bloqueado) {
			if (e && e.preventDefault) e.preventDefault();
			alert(mensajeBloqueo);
			return false;
		}
		return cerrar_dia(e);
	};

	// Función para eliminar cierre individual del usuario
	const eliminarCierreIndividual = async () => {
		if (!confirm('¿Está seguro que desea eliminar su cierre? Deberá recalcularlo nuevamente.')) {
			return;
		}

		try {
			const response = await db.eliminarCierreUsuario({});
			
			if (response.data && response.data.estado) {
				alert(response.data.msj);
				// Recargar el estado del cierre
				await verificarEstadoCierre();
				// Limpiar el cierre actual para que se muestre "¡Cerremos el día!"
				// Esto depende de cómo esté estructurado el estado, ajustar según sea necesario
				window.location.reload(); // O mejor, actualizar solo el estado necesario
			} else {
				alert(response.data?.msj || 'Error al eliminar el cierre');
			}
		} catch (error) {
			console.error('Error eliminando cierre:', error);
			alert(error.response?.data?.msj || 'Error al eliminar el cierre');
		}
	};

	// Si es administrador, solo mostrar Totalizar Cierre
	if (esAdministrador) {
		return (
			<div className="container-fluid">
				<TotalizarCierre
					fechaCierre={fechaCierre}
					onClose={() => {}} // No necesita cerrar porque no es modal
					dolar={moneda?.bs || 0}
					peso={moneda?.cop || 0}
					esModal={false} // Renderizar como componente normal, no como modal
				/>
			</div>
		);
	}

	// Si es cajero, mostrar componente normal
	return (
		<div className="container-fluid">
			<div className="row">
				
				<div className="col">
					<div className="container-fluid">
						<div className="row">
							<div className="col">
								<div className="btn-group mb-1">
									<button className={(viewCierre=="cuadre"?"btn-":"btn-outline-")+("sinapsis btn")} onClick={()=>setViewCierre("cuadre")}>Cuadre</button>
									{auth(1)?
										<button className={(viewCierre=="historico"?"btn-":"btn-outline-")+("sinapsis btn")} onClick={()=>setViewCierre("historico")}>Histórico</button>
									:null}
								</div>
								<br />
							</div>
							
						</div>
						<br/>
						{viewCierre=="cuadre"?
						<>
							<div className="input-group">
								<button 
									className="btn btn-sinapsis" 
									onClick={ejecutarCerrarDia}
									disabled={bloqueado}
									title={bloqueado ? mensajeBloqueo : 'Calcular cierre'}
								>
									<i className="fa fa-cogs"></i>
								</button>

								<input type="date" disabled={true} value={fechaCierre} className="form-control" onChange={null}/>
							</div>
							
							{/* Mensaje de bloqueo y botón para eliminar */}
							{bloqueado && (
								<div className="alert alert-warning mt-2" role="alert">
									<strong><i className="fa fa-exclamation-triangle"></i> Atención:</strong> {mensajeBloqueo}
									{hayCierreGuardado && !hayCierreAdmin && (
										<div className="mt-2">
											<button 
												className="btn btn-sm btn-danger" 
												onClick={eliminarCierreIndividual}
											>
												<i className="fa fa-trash"></i> Eliminar Cierre
											</button>
										</div>
									)}
								</div>
							)}
							{cierre["fecha"]?
								<div className='d-flex justify-content-between'>

									<div className='btn-group mt-2 mb-2 w-30'>
		
										{/* Solo mostrar botón Guardar/Editar si NO está bloqueado */}
										{!bloqueado && (
											tipo_accionCierre=="guardar"?
											<button className="btn-sm btn btn-outline-success" onClick={guardar_cierre} type="button">Guardar</button>
											:
											<button className="btn-sm btn btn-sinapsis" onClick={guardar_cierre} type="button">Editar</button>
										)}
										
										<button className="btn-sm btn btn-sinapsis" onClick={veryenviarcierrefun} type="button" data-type="ver">Ver</button>

										
										<button className="btn btn-sm" onClick={()=>setToggleDetallesCierre(!toggleDetallesCierre)}>Ver detalles</button>
										
										{esAdministrador && cierre["fecha"] && (
											<button 
												className="btn btn-sm btn-primary ml-2" 
												onClick={() => setMostrarTotalizar(true)}
											>
												<i className="fa fa-users mr-1"></i> Ver Totalizado
											</button>
										)}
									</div>
									
									<div className='d-flex flex-column'>

										<div className='btn-group mt-2 mb-2'>

											{auth(1)?
											<button className={"btn "+(totalizarcierre?"btn-success":"")+" btn-lg"} onClick={()=>getTotalizarCierre()}>Totalizar</button>
											:null}

											


											{totalizarcierre?<button className="btn btn-warning" onClick={veryenviarcierrefun} type="button" data-type="enviar">Enviar y Sincronizar</button>:null}
											{/*<button className="btn btn-warning" onClick={sendCuentasporCobrar} type="button" data-type="enviar">Enviar Cuentas por Cobrar</button>*/}
										</div>
										<span>
											{totalizarcierre?"Totalizando ":"Cajero "}
										</span>		
									</div>
									
								</div>
							:null}
							{cierre["fecha"]?
								<>
									<form onSubmit={ejecutarCerrarDia}>
										<button hidden={true}></button>
										<div className="container-fluid p-0">
												{toggleDetallesCierre?
													<table className="table">
														<tbody>
															<tr>
																<td><span className="fw-bold">Entregado:</span> {cierre["entregado"]}</td>
																
																<td><span className="fw-bold">Pendiente:</span> {cierre["pendiente"]}</td>
																
																<td><span className="fw-bold">Caja inicial:</span> {cierre["caja_inicial"]}</td>
																
															</tr>
														</tbody>
													</table>
												:null}
												<div className="p-3 card shadow-card mb-2 mt-2">
													<div className="row mb-2 border-bottom">
														<div className="col h3 text-center" >
															¿Cuánto hay en caja?
														</div>
													</div>
												
													{/* Sección EFECTIVO */}
													<div className="row mb-3 mt-3">
														<div className="col">
															<h5 className="text-primary font-weight-bold border-bottom pb-2">EFECTIVO</h5>
														</div>
													</div>
													{/* <div className="row mb-2">
														<div className="col-2 text-success text-right">Billetes $</div>
														<div className="col font-weight-bold align-middle" >
															<div>
																<span className="h5">1</span>
																	<input type="text" value={billete1} name="billete1" onChange={e=>setbillete1(number(e.target.value))} className="input-50" placeholder="1 $" disabled={bloqueado}/>
																	<span className="h5">5</span>
																	<input type="text" value={billete5} name="billete5" onChange={e=>setbillete5(number(e.target.value))} className="input-50" placeholder="5 $" disabled={bloqueado}/>
																	<span className="h5">10</span>
																	<input type="text" value={billete10} name="billete10" onChange={e=>setbillete10(number(e.target.value))} className="input-50" placeholder="10 $" disabled={bloqueado}/>
																	<span className="h5">20</span>
																	<input type="text" value={billete20} name="billete20" onChange={e=>setbillete20(number(e.target.value))} className="input-50" placeholder="20 $" disabled={bloqueado}/>
																	<span className="h5">50</span>
																	<input type="text" value={billete50} name="billete50" onChange={e=>setbillete50(number(e.target.value))} className="input-50" placeholder="50 $" disabled={bloqueado}/>
																	<span className="h5">100</span>
																	<input type="text" value={billete100} name="billete100" onChange={e=>setbillete100(number(e.target.value))} className="input-50" placeholder="100 $" disabled={bloqueado}/>
														</div>
													</div> */}
													<div className="row mb-2">
														<div className="col-2"></div>
														<div className="col">
															<label className="font-weight-bold">Dólares ($)</label>
															<input type="text"  placeholder="$" name="caja_usd" value={caja_usd} onChange={onchangecaja} className="form-control" disabled={bloqueado}/>
														</div>

														<div className="col">
															<label className="font-weight-bold">Bolívares (Bs.)</label>
															<input type="text"  placeholder="Bs." name="caja_bs" value={caja_bs} onChange={onchangecaja} className="form-control" disabled={bloqueado}/>
														</div>
														<div className="col">
															<label className="font-weight-bold">Pesos (COP)</label>
															<input type="text"  placeholder="COP" name="caja_cop" value={caja_cop} onChange={onchangecaja} className="form-control" disabled={bloqueado}/>
														</div>

													</div>
													{/* <div className="row mb-2">
														<div className="col-2 text-success text-right">Total Caja Actual en $</div>
														<div className="col" >
															<input type="text" value={total_caja_neto} className="form-control" disabled={true}/>
														</div>
													</div> */}
													
													{/* Sección DÉBITO */}
													<div className="row mb-3 mt-4">
														<div className="col">
															<h5 className="text-primary font-weight-bold border-bottom pb-2">DÉBITO</h5>
														</div>
													</div>
													

													{/* 
														<div className="row mb-2">
															<div className="col-2 text-success text-right">LOTES</div>

															<div className="col align-middle text-center" >
																{lotespuntototalizar.map((e,i)=>
																	<div className="input-group" key={i}>
																		<div className="input-group-prepend w-50">
																			<input type="text" className="form-control" placeholder="MONTO LOTE 1" name="" defaultValue={e.monto} disabled />
																		</div>
																		<input type="text" className="form-control" placeholder="LOTE 1" name="" defaultValue={e.lote}  disabled/>
																		<input type="text" className="form-control" placeholder="BANCO" name="" defaultValue={e.banco}  disabled/>
																	</div>
																)}
															</div>
														</div>
													*/}
													
													{/* Sección de Lotes Pinpad */}
													<LotesPinpad 
														lotesPinpad={lotesPinpad} 
														bancos={bancos}
														onChangeBanco={onChangeBancoPinpad}
														totalizarcierre={totalizarcierre}
														bloqueado={bloqueado}
													/>

													<div className="row mb-2">
														<div className="col-2 text-success text-right">Lotes Otros Puntos de Venta</div>

														<div className="col align-middle text-center" >
															<table className="table table-bordered" style={{ tableLayout: 'fixed' }}>
																<thead>
																	<tr>
																		<th style={{ width: '20%' }}>CATEGORÍA</th>
																		<th style={{ width: '20%' }}>MONTO</th>
																		<th style={{ width: '25%' }}>LOTE</th>
																		<th style={{ width: '25%' }}>BANCO</th>
																		<th style={{ width: '10%' }}>
																			<button className="btn btn-success btn-sm" type="button" onClick={()=>addTuplasPuntosAdicionales("add",null)} disabled={totalizarcierre || bloqueado}>
																				<i className="fa fa-plus"></i>
																			</button>
																		</th>
																	</tr>
																</thead>
																<tbody>
																	{dataPuntosAdicionales.map((e,i)=>
																		<tr key={i}>
																			<td>
																				<select className='form-control form-control-sm' disabled={totalizarcierre || bloqueado} value={e.categoria} onChange={event=>onchangetuplapuntosnew("categoria",i,event.target.value)}>
																					<option value="">-</option>
																					<option value="DEBITO">DEBITO</option>
																					<option value="CREDITO">CREDITO</option>
																				</select>
																			</td>
																			<td>
																				<input type="text" className='form-control form-control-sm' disabled={totalizarcierre || bloqueado} value={e.monto} placeholder='monto' onChange={event=>onchangetuplapuntosnew("monto",i,number(event.target.value))} />
																			</td>
																			<td>
																				<input type="text" className='form-control form-control-sm' disabled={totalizarcierre || bloqueado} value={e.descripcion} placeholder='numlote' onChange={event=>onchangetuplapuntosnew("descripcion",i,event.target.value)} />
																			</td>
																			<td>
																				<select className='form-control form-control-sm' disabled={totalizarcierre || bloqueado} value={e.banco} onChange={event=>onchangetuplapuntosnew("banco",i,event.target.value)}>
																					{bancos.filter(e=>e.value!="0134").map((e,i)=>
																						<option key={i} value={e.value}>{e.text}</option>
																					)}
																				</select>
																			</td>
																			<td className="text-center">
																				{!(totalizarcierre || bloqueado) && (
																					<i className='fa fa-times text-danger' style={{ cursor: 'pointer' }} onClick={()=>addTuplasPuntosAdicionales("delete",i)}></i>
																				)}
																			</td>
																		</tr>	
																	)}
																</tbody>
															</table>
														</div>
													</div>

													<div className="row mb-2">
														<div className="col-2 text-success text-right">Total Punto de Venta en Bs</div>

														<div className="col align-middle text-center" >
															<input type="text" className="form-control" placeholder="Punto de venta Bs." name="caja_punto" value={caja_punto} onChange={onchangecaja} disabled/>
														</div>
													</div>
													
													{/* {totalizarcierre?
													<div className="row mb-2">
														<div className="col-2 text-success text-right">BIOPAGOS</div>

														<div className="col align-middle text-center" >
															{biopagostotalizar.map((e,i)=>
																<div className="input-group" key={i}>
																	<div className="input-group-prepend w-50">
																		<input type="text" className="form-control" placeholder="MONTO BIOPAGO 1" name="" defaultValue={e.monto} disabled />
																	</div>
																	<input type="text" className="form-control" placeholder="BIOPAGO SERIAL" name="" defaultValue={e.serial}  disabled/>
																</div>
															)}
														</div>
													</div>
													:
													<div className="row mb-2">
														<div className="col-2 text-success text-right">SERIAL Biopago</div>
														<div className="col align-middle text-center" >
															<input type="text" className="form-control" placeholder="SERIAL Biopago" name="serialbiopago" value={serialbiopago} onChange={e => setserialbiopago((e.target.value))} disabled={bloqueado}/>
														</div>
													</div>
													}
													<div className="row mb-2">
														<div className="col-2 text-success text-right">Total Biopago en Bs</div>
														<div className="col align-middle text-center" >
															<input type="text" className="form-control" placeholder="Biopago Bs." name="caja_biopago" value={caja_biopago} onChange={onchangecaja}/>
														</div>
													</div> */}
												</div>	

												<div className="p-3 card shadow-card mb-2">
												
													<div className="row p-2 border-bottom">
														<div className="col h3 text-center" >
															¿Cuánto dejarás en caja?
														</div>
													</div>
													<div className="row p-2">
														<div className="col-2"></div>
														<div className="col">
															<label className="font-weight-bold">Dólares ($)</label>
															<input type="text" placeholder="$" name="dejar_usd" value={dejar_usd} onChange={onchangecajaValidado} className="form-control" disabled={bloqueado}/>
														</div>
														<div className="col">
															<label className="font-weight-bold">Bolívares (Bs.)</label>
															<input type="text" placeholder="Bs." name="dejar_bs" value={dejar_bs} onChange={onchangecajaValidado} className="form-control" disabled={bloqueado}/>
														</div>
														<div className="col">
															<label className="font-weight-bold">Pesos (COP)</label>
															<input type="text" placeholder="COP" name="dejar_cop" value={dejar_cop} onChange={onchangecajaValidado} className="form-control" disabled={bloqueado}/>
														</div>
													</div>
						{/* 							<div className="row p-2">
														<div className="col-2 text-success text-right">Total Dejar Caja</div>

														<div className="col align-middle text-center" >
															<input type="text" value={total_dejar_caja_neto} className="form-control" disabled={true}/>
														</div>
													</div> */}
												</div>

												<div className="p-3 card shadow-card mb-2">
													<div className="row p-2 border-bottom">
														<div className="col h3 text-center">
															Cuadre Final
														</div>
													</div>
													<div className="row p-2 bg-light">
														<div className="col-2 text-left font-weight-bold">Tipo</div>
														<div className="col text-right font-weight-bold">Saldo Inicial</div>
														<div className="col text-right font-weight-bold">Real</div>
														<div className="col text-right font-weight-bold">Digital</div>
														<div className="col text-right font-weight-bold">Diferencia</div>
														<div className="col-2 text-center font-weight-bold">Estado</div>
													</div>
													
													{/* Renderizar cuadre detallado si existe */}
													{cierre.cuadre_detallado && (
															<>
																{/* ========== SECCIÓN 1: EFECTIVO ========== */}
																<div className="row p-2 mt-3">
																	<div className="col">
																		<h5 className="text-primary font-weight-bold">EFECTIVO</h5>
																	</div>
																</div>
																
																{/* Efectivo USD */}
																{cierre.cuadre_detallado.efectivo_usd && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className={cierre.cuadre_detallado.efectivo_usd.estado === 'cuadrado' ? 'text-success' : 'text-danger'}>
																				Dólares (USD)
																			</span>
																		</div>
																		<div className="col text-right">
																			${formatNumber(cierre.cuadre_detallado.efectivo_usd.inicial)}
																		</div>
																		<div className="col text-right">
																			${formatNumber(cierre.cuadre_detallado.efectivo_usd.real)}
																		</div>
																		<div className="col text-right position-relative">
																			${formatNumber(cierre.cuadre_detallado.efectivo_usd.digital)}
																			{cierre.cuadre_detallado.efectivo_usd.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(3, 'USD', 'Efectivo en Dólares')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className={cierre.cuadre_detallado.efectivo_usd.estado === 'cuadrado' ? 'text-success fw-bold' : (cierre.cuadre_detallado.efectivo_usd.estado === 'sobra' ? 'text-sinapsis fw-bold' : 'text-danger fw-bold')}>
																				${formatNumber(cierre.cuadre_detallado.efectivo_usd.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className={cierre.cuadre_detallado.efectivo_usd.estado === 'cuadrado' ? 'badge bg-success fs-5 px-3 py-2 fw-bold' : (cierre.cuadre_detallado.efectivo_usd.estado === 'sobra' ? 'badge bg-sinapsis fs-5 px-3 py-2 fw-bold' : 'badge bg-danger fs-5 px-3 py-2 fw-bold')}>
																				{cierre.cuadre_detallado.efectivo_usd.mensaje}
																			</span>
																		</div>
																	</div>
																)}
																
																{/* Efectivo BS */}
																{cierre.cuadre_detallado.efectivo_bs && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className={cierre.cuadre_detallado.efectivo_bs.estado === 'cuadrado' ? 'text-success' : 'text-danger'}>
																				Bolívares (Bs)
																			</span>
																		</div>
																		<div className="col text-right">
																			Bs {formatNumber(cierre.cuadre_detallado.efectivo_bs.inicial)}
																		</div>
																		<div className="col text-right">
																			Bs {formatNumber(cierre.cuadre_detallado.efectivo_bs.real)}
																		</div>
																		<div className="col text-right position-relative">
																			Bs {formatNumber(cierre.cuadre_detallado.efectivo_bs.digital)}
																			{cierre.cuadre_detallado.efectivo_bs.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(3, 'BS', 'Efectivo en Bolívares')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className={cierre.cuadre_detallado.efectivo_bs.estado === 'cuadrado' ? 'text-success fw-bold' : (cierre.cuadre_detallado.efectivo_bs.estado === 'sobra' ? 'text-sinapsis fw-bold' : 'text-danger fw-bold')}>
																				Bs {formatNumber(cierre.cuadre_detallado.efectivo_bs.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className={cierre.cuadre_detallado.efectivo_bs.estado === 'cuadrado' ? 'badge bg-success fs-5 px-3 py-2 fw-bold' : (cierre.cuadre_detallado.efectivo_bs.estado === 'sobra' ? 'badge bg-sinapsis fs-5 px-3 py-2 fw-bold' : 'badge bg-danger fs-5 px-3 py-2 fw-bold')}>
																				{cierre.cuadre_detallado.efectivo_bs.mensaje}
																			</span>
																		</div>
																	</div>
																)}
																
																{/* Efectivo COP */}
																{cierre.cuadre_detallado.efectivo_cop && (cierre.cuadre_detallado.efectivo_cop.real > 0 || cierre.cuadre_detallado.efectivo_cop.digital > 0) && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className={cierre.cuadre_detallado.efectivo_cop.estado === 'cuadrado' ? 'text-success' : 'text-danger'}>
																				Pesos (COP)
																			</span>
																		</div>
																		<div className="col text-right">
																			COP {formatNumber(cierre.cuadre_detallado.efectivo_cop.inicial)}
																		</div>
																		<div className="col text-right">
																			COP {formatNumber(cierre.cuadre_detallado.efectivo_cop.real)}
																		</div>
																		<div className="col text-right position-relative">
																			COP {formatNumber(cierre.cuadre_detallado.efectivo_cop.digital)}
																			{cierre.cuadre_detallado.efectivo_cop.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(3, 'COP', 'Efectivo en Pesos')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className={cierre.cuadre_detallado.efectivo_cop.estado === 'cuadrado' ? 'text-success fw-bold' : (cierre.cuadre_detallado.efectivo_cop.estado === 'sobra' ? 'text-sinapsis fw-bold' : 'text-danger fw-bold')}>
																				COP {formatNumber(cierre.cuadre_detallado.efectivo_cop.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className={cierre.cuadre_detallado.efectivo_cop.estado === 'cuadrado' ? 'badge bg-success fs-5 px-3 py-2 fw-bold' : (cierre.cuadre_detallado.efectivo_cop.estado === 'sobra' ? 'badge bg-sinapsis fs-5 px-3 py-2 fw-bold' : 'badge bg-danger fs-5 px-3 py-2 fw-bold')}>
																				{cierre.cuadre_detallado.efectivo_cop.mensaje}
																			</span>
																		</div>
																	</div>
																)}
																
																{/* ========== SECCIÓN 2: DÉBITO ========== */}
																<div className="row p-2 mt-3">
																	<div className="col">
																		<h5 className="text-primary font-weight-bold">DÉBITO</h5>
																	</div>
																</div>
																
																{/* Débito Pinpad */}
																{cierre.cuadre_detallado.debito_pinpad && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className={cierre.cuadre_detallado.debito_pinpad.estado === 'cuadrado' ? 'text-success' : 'text-danger'}>
																				Pinpad
																			</span>
																		</div>
																		<div className="col text-right">
																			-
																		</div>
																		<div className="col text-right">
																			Bs {formatNumber(cierre.cuadre_detallado.debito_pinpad.real)}
																		</div>
																		<div className="col text-right position-relative">
																			Bs {formatNumber(cierre.cuadre_detallado.debito_pinpad.digital)}
																			{cierre.cuadre_detallado.debito_pinpad.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(2, 'PINPAD', 'Débito Pinpad')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className={cierre.cuadre_detallado.debito_pinpad.estado === 'cuadrado' ? 'text-success fw-bold' : (cierre.cuadre_detallado.debito_pinpad.estado === 'sobra' ? 'text-sinapsis fw-bold' : 'text-danger fw-bold')}>
																				Bs {formatNumber(cierre.cuadre_detallado.debito_pinpad.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className={cierre.cuadre_detallado.debito_pinpad.estado === 'cuadrado' ? 'badge bg-success fs-5 px-3 py-2 fw-bold' : (cierre.cuadre_detallado.debito_pinpad.estado === 'sobra' ? 'badge bg-sinapsis fs-5 px-3 py-2 fw-bold' : 'badge bg-danger fs-5 px-3 py-2 fw-bold')}>
																				{cierre.cuadre_detallado.debito_pinpad.mensaje}
																			</span>
																		</div>
																	</div>
																)}
																
																{/* Débito Otros Puntos */}
																{cierre.cuadre_detallado.debito_otros && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className={cierre.cuadre_detallado.debito_otros.estado === 'cuadrado' ? 'text-success' : 'text-danger'}>
																				Otros Puntos
																			</span>
																		</div>
																		<div className="col text-right">
																			-
																		</div>
																		<div className="col text-right">
																			Bs {formatNumber(cierre.cuadre_detallado.debito_otros.real)}
																		</div>
																		<div className="col text-right position-relative">
																			Bs {formatNumber(cierre.cuadre_detallado.debito_otros.digital)}
																			{cierre.cuadre_detallado.debito_otros.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(2, 'OTROS', 'Débito Otros Puntos')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className={cierre.cuadre_detallado.debito_otros.estado === 'cuadrado' ? 'text-success fw-bold' : (cierre.cuadre_detallado.debito_otros.estado === 'sobra' ? 'text-sinapsis fw-bold' : 'text-danger fw-bold')}>
																				Bs {formatNumber(cierre.cuadre_detallado.debito_otros.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className={cierre.cuadre_detallado.debito_otros.estado === 'cuadrado' ? 'badge bg-success fs-5 px-3 py-2 fw-bold' : (cierre.cuadre_detallado.debito_otros.estado === 'sobra' ? 'badge bg-sinapsis fs-5 px-3 py-2 fw-bold' : 'badge bg-danger fs-5 px-3 py-2 fw-bold')}>
																				{cierre.cuadre_detallado.debito_otros.mensaje}
																			</span>
																		</div>
																	</div>
																)}
																
																{/* ========== SECCIÓN 3: TRANSFERENCIA ========== */}
																<div className="row p-2 mt-3">
																	<div className="col">
																		<h5 className="text-primary font-weight-bold">TRANSFERENCIA</h5>
																	</div>
																</div>
																
																{/* Transferencia */}
																{cierre.cuadre_detallado.transferencia && (
																	<div className="row p-2 border-bottom">
																		<div className="col-2 text-right h6">
																			<span className="text-success">
																				Transferencia
																			</span>
																		</div>
																		<div className="col text-right">
																			-
																		</div>
																		<div className="col text-right">
																			${formatNumber(cierre.cuadre_detallado.transferencia.real)}
																		</div>
																		<div className="col text-right position-relative">
																			${formatNumber(cierre.cuadre_detallado.transferencia.digital)}
																			{cierre.cuadre_detallado.transferencia.digital > 0 && (
																				<button 
																					type="button"
																					className="btn btn-sm btn-info position-absolute"
																					style={{ right: '-40px', top: '50%', transform: 'translateY(-50%)' }}
																					onClick={() => abrirModalPagos(1, null, 'Transferencia')}
																				>
																					<i className="fa fa-eye"></i>
																				</button>
																			)}
																		</div>
																		<div className="col text-right">
																			<span className="text-success fw-bold">
																				${formatNumber(cierre.cuadre_detallado.transferencia.diferencia)}
																			</span>
																		</div>
																		<div className="col-2 text-center">
																			<span className="badge bg-success fs-5 px-3 py-2 fw-bold">
																				{cierre.cuadre_detallado.transferencia.mensaje}
																			</span>
																		</div>
																	</div>
																)}
															</>
														)}
														
														{/* Biopago - Mantener al final */}
														{/* <div className="row p-2 border-bottom mt-3">
															<div className={(cierre["estado_biopago"]==1?"text-success":"text-danger")+(" col-2 text-right h6")}>Biopago</div>
															<div className="col text-center">-</div>
															<div className="col text-center">
																{total_biopago}
															</div>
															<div className="col text-center d-flex align-items-center justify-content-center">
																<span className="me-2">{cierre[5]?cierre[5].toFixed(2):"0.00"}</span>
																{cierre[5] > 0 && (
																	<button 
																		type="button"
																		className="btn btn-sm btn-info"
																		onClick={() => abrirModalPagos(5, null, 'Biopago')}
																	>
																		<i className="fa fa-eye"></i>
																	</button>
																)}
															</div>
															<div className="col text-center">
																<span className={(cierre["estado_biopago"]==1?"text-success":"text-danger")}>
																	{cierre["msj_biopago"]?cierre["msj_biopago"]:""}
																</span>
															</div>
															<div className="col text-center">
																<span className={(cierre["estado_biopago"]==1?"badge bg-success":"badge bg-danger")}>
																	{cierre["msj_biopago"]?cierre["msj_biopago"]:""}
																</span>
															</div>
														</div> */}
														
														{/* Nota */}
														<div className="row p-2 mt-3">
															<div className="col-2 text-success text-right h5">Nota</div>
															<div className="col">
																<textarea name="notaCierre" placeholder="Novedades..." value={notaCierre?notaCierre:""} onChange={onchangecaja} cols="40" rows="2" disabled={bloqueado}></textarea>
															</div>
														</div>
													</div>

													<div className="p-3 card shadow-card mb-2">
														<div className="row mb-3">
															<div className="col-12">
																<h5 className="text-success text-center font-weight-bold mb-0">
																	<i className="fa fa-money-bill-wave me-2"></i>
																	EFECTIVO GUARDADO EN CAJA FUERTE
																</h5>
															</div>
														</div>
													<div className="row g-3 text-center">
														<div className="col-md-4">
															<div className="card border-success shadow-sm h-100">
																<div className="card-body">
																	<h6 className="text-muted mb-2 fw-bold">USD (Dólares)</h6>
																	<h2 className="text-success fw-bold mb-0">
																		${parseFloat(cierre.efectivo_guardado_usd || 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
																	</h2>
																</div>
															</div>
														</div>
														<div className="col-md-4">
															<div className="card border-primary shadow-sm h-100">
																<div className="card-body">
																	<h6 className="text-muted mb-2 fw-bold">Bs (Bolívares)</h6>
																	<h2 className="text-primary fw-bold mb-0">
																		{parseFloat(cierre.efectivo_guardado_bs || 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
																	</h2>
																</div>
															</div>
														</div>
														<div className="col-md-4">
															<div className="card border-danger shadow-sm h-100">
																<div className="card-body">
																	<h6 className="text-muted mb-2 fw-bold">COP (Pesos)</h6>
																	<h2 className="text-danger fw-bold mb-0">
																		{parseFloat(cierre.efectivo_guardado_cop || 0).toLocaleString('es-VE', {minimumFractionDigits: 0, maximumFractionDigits: 0})}
																	</h2>
																</div>
															</div>
														</div>
													</div>
													</div>

													

												<div className="p-3 card shadow-card mb-2">
													<table className="table">
														<tbody>
															<tr>
																<th># de Reporte Z</th>
																<td>
																	<input type="text" className='form-control' value={cierrenumreportez} onChange={e=>setcierrenumreportez(e.target.value)} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<th>Venta Exento</th>
																<td>
																	<input type="text" className='form-control' value={cierreventaexcento} onChange={e=>setcierreventaexcento(number(e.target.value))} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<th>Ventas Gravadas (16%)</th>
																<td>
																	<input type="text" className='form-control' value={cierreventagravadas} onChange={e=>setcierreventagravadas(number(e.target.value))} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<th>IVA Ventas</th>
																<td>
																	<input type="text" className='form-control' value={cierreivaventa} onChange={e=>setcierreivaventa(number(e.target.value))} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<th>Total Ventas</th>
																<td>
																	<input type="text" className='form-control' value={cierretotalventa} onChange={e=>setcierretotalventa(number(e.target.value))} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<th>Última Factura</th>
																<td>
																	<input type="text" className='form-control' value={cierreultimafactura} onChange={e=>setcierreultimafactura(e.target.value)} disabled={bloqueado}/>
																</td>
															</tr>
															<tr>
																<td colSpan={2}>

																</td>
															</tr>
															
														</tbody>
													</table>
												</div>


										</div>


									</form>
									<div className="mt-2">
										{auth(1)?totalizarcierre?
											<button type='button' className={"btn-danger btn-lg"} onClick={()=>reversarCierre()}>Reversar Cierre</button>
										:null:null}
									</div>
								</>

								:<div className="d-flex flex-column justify-content-center align-items-center">
									{bloqueado ? (
										<div className="alert alert-warning text-center" role="alert" style={{maxWidth: '600px'}}>
											<h4><i className="fa fa-exclamation-triangle"></i> Cierre Bloqueado</h4>
											<p>{mensajeBloqueo}</p>
											{hayCierreGuardado && !hayCierreAdmin && (
												<button 
													className="btn btn-danger mt-2" 
													onClick={eliminarCierreIndividual}
												>
													<i className="fa fa-trash"></i> Eliminar Cierre
												</button>
											)}
										</div>
									) : (
										<button className="btn btn-xl btn-success" onClick={ejecutarCerrarDia}>¡Cerremos el día!</button>
									)}
								</div>}
						</>
						:null}	
						{viewCierre=="historico"?
							<Historicocierre 
							cierres={cierres}
							fechaGetCierre={fechaGetCierre}
							setfechaGetCierre={setfechaGetCierre}

							fechaGetCierre2={fechaGetCierre2}
							setfechaGetCierre2={setfechaGetCierre2}

							getCierres={getCierres}
							verCierreReq={verCierreReq}

							tipoUsuarioCierre={tipoUsuarioCierre}
							settipoUsuarioCierre={settipoUsuarioCierre}
							/>
						:null}
					</div>
				</div>
			</div>
			{/* Modal de detalles de pagos */}
			{modalPagos.isOpen && (
				<div className="modal fade show" style={{ display: 'block', background: 'rgba(0,0,0,0.5)' }}>
					<div className="modal-dialog modal-xl">
						<div className="modal-content">
							<div className="modal-header">
								<h5 className="modal-title">Detalles de Pagos - {modalPagos.titulo}</h5>
								<button type="button" className="btn-close" onClick={cerrarModalPagos}></button>
							</div>
							<div className="modal-body" style={{ maxHeight: '600px', overflowY: 'auto' }}>
								<ModalDetallesPagos 
									cierre={cierre}
									tipo={modalPagos.tipo}
									moneda={modalPagos.moneda}
									onRefrescarCierre={fechaCierre ? () => verCierreReq(fechaCierre, 'ver') : undefined}
								/>
							</div>
							<div className="modal-footer">
								<button type="button" className="btn" onClick={cerrarModalPagos}>Cerrar</button>
							</div>
						</div>
					</div>
				</div>
			)}
			
			{/* Modal de Totalizar Cierre (solo para administradores) */}
			{mostrarTotalizar && esAdministrador && (
				<TotalizarCierre
					fechaCierre={fechaCierre}
					onClose={() => setMostrarTotalizar(false)}
					dolar={dolar}
					peso={peso}
				/>
			)}
		</div>
	)
}

// Componente para mostrar detalles de pagos
function ModalDetallesPagos({ cierre, tipo, moneda, onRefrescarCierre }) {
	const [pedidoDetalle, setPedidoDetalle] = useState(null);
	const [pedidoData, setPedidoData] = useState(null);
	const [loadingPedido, setLoadingPedido] = useState(false);
	const [vistaAgrupada, setVistaAgrupada] = useState(tipo === 1); // Si es transferencia, mostrar agrupado por defecto
	const [acordeonAbierto, setAcordeonAbierto] = useState({}); // Controlar qué acordeones están abiertos
	const [pagosVerificados, setPagosVerificados] = useState({}); // Estado para trackear los pagos verificados
	const [verificandoId, setVerificandoId] = useState(null); // ID del pago que se está verificando con Instapago
	const [pagosActualizados, setPagosActualizados] = useState({}); // Pagos actualizados tras verificar Instapago
	
	// Función para togglear acordeón
	const toggleAcordeon = (key) => {
		setAcordeonAbierto(prev => ({
			...prev,
			[key]: !prev[key]
		}));
	};
	
	// Función para manejar el checkbox de verificación
	const toggleVerificacion = (idPago) => {
		setPagosVerificados(prev => ({
			...prev,
			[idPago]: !prev[idPago]
		}));
	};
	
	// Función para formatear moneda
	const formatCurrency = (value, decimals = 2) => {
		return parseFloat(value || 0).toLocaleString('es-VE', {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals
		});
	};
	
	// Filtrar pagos por tipo y moneda
	const pagosFiltrados = useMemo(() => {
		// CASOS ESPECIALES: Verificar primero PINPAD y OTROS antes de buscar en pagos_por_moneda
		// porque estos no están en pagos_por_moneda, están en lotes_pinpad
		if (tipo === 2 && moneda === 'PINPAD') {
			if (cierre.lotes_pinpad && Array.isArray(cierre.lotes_pinpad) && cierre.lotes_pinpad.length > 0) {
				const transacciones = cierre.lotes_pinpad.flatMap(lote => {
					return lote.transacciones || [];
				});
				// Ordenar por created_at (del más antiguo al más reciente)
				return transacciones.sort((a, b) => {
					const dateA = new Date(a.created_at || 0);
					const dateB = new Date(b.created_at || 0);
					return dateA - dateB;
				});
			} else {
				return [];
			}
		}
		
		if (!cierre.pagos_por_moneda) {
			return [];
		}
		
		const tipoPagos = cierre.pagos_por_moneda.find(p => p.tipo == tipo); // Usar == en lugar de ===
		
		// Para OTROS, filtrar pagos de débito que NO están en lotes_pinpad
		if (tipo === 2 && moneda === 'OTROS') {
			const idsEnPinpad = new Set();
			if (cierre.lotes_pinpad) {
				cierre.lotes_pinpad.forEach(lote => {
					(lote.transacciones || []).forEach(t => idsEnPinpad.add(t.id));
				});
			}
			// Devolver todos los pagos de débito que no están en PINPAD, ordenados por fecha
			if (tipoPagos && tipoPagos.monedas) {
				const pagosOtros = tipoPagos.monedas.flatMap(m => 
					(m.pagos || []).filter(p => !idsEnPinpad.has(p.id))
				);
				return pagosOtros.sort((a, b) => {
					const dateA = new Date(a.created_at || 0);
					const dateB = new Date(b.created_at || 0);
					return dateA - dateB;
				});
			}
			return [];
		}
		
		if (!tipoPagos) {
			return [];
		}
		
		if (moneda) {
			// Búsqueda case-insensitive
			const monedaPagos = tipoPagos.monedas.find(m => 
				m.moneda && moneda && m.moneda.toLowerCase() === moneda.toLowerCase()
			);
			
			if (monedaPagos) {
				// Ordenar por created_at (del más antiguo al más reciente)
				const pagos = monedaPagos.pagos || [];
				return pagos.sort((a, b) => {
					const dateA = new Date(a.created_at || 0);
					const dateB = new Date(b.created_at || 0);
					return dateA - dateB;
				});
			}
			
			return [];
		}
		
		// Si no hay moneda específica, devolver todos los pagos del tipo, ordenados por fecha
		const todosPagos = tipoPagos.monedas.flatMap(m => m.pagos || []);
		return todosPagos.sort((a, b) => {
			const dateA = new Date(a.created_at || 0);
			const dateB = new Date(b.created_at || 0);
			return dateA - dateB;
		});
	}, [cierre, tipo, moneda]);
	
	// Agrupar transferencias por categoría y banco (usar datos del backend si están disponibles)
	const transferenciasAgrupadas = useMemo(() => {
		if (tipo !== 1) {
			return null;
		}
		
		// Si el backend ya envió los datos agrupados, usarlos
		if (cierre.transferencias_agrupadas) {
			return cierre.transferencias_agrupadas;
		}
		
		// Fallback: agrupar en el frontend si no vienen del backend
		if (pagosFiltrados.length === 0) {
			return null;
		}
		
		const agrupacion = {};
		
		pagosFiltrados.forEach(pago => {
			const categoria = pago.categoria || 'Sin Categoría';
			const banco = pago.banco || 'Sin Banco';
			
			if (!agrupacion[categoria]) {
				agrupacion[categoria] = {};
			}
			
			if (!agrupacion[categoria][banco]) {
				agrupacion[categoria][banco] = {
					transacciones: [],
					monto_total: 0,
					cantidad: 0
				};
			}
			
			agrupacion[categoria][banco].transacciones.push(pago);
			agrupacion[categoria][banco].monto_total += parseFloat(pago.monto_referencia || pago.monto || 0);
			agrupacion[categoria][banco].cantidad++;
		});
		
		return agrupacion;
	}, [pagosFiltrados, tipo, cierre.transferencias_agrupadas]);
	
	const verPedido = async (idPedido) => {
		// Validar que idPedido sea válido
		if (!idPedido || idPedido === null || idPedido === undefined) {
			alert('Error: No se pudo obtener el ID del pedido');
			return;
		}
		
		setPedidoDetalle(idPedido);
		setLoadingPedido(true);
		
		try {
			const response = await db.getPedido({ id: idPedido });
			
			// getPedido puede retornar un array o un objeto directamente
			const pedidoData = Array.isArray(response?.data) ? response.data[0] : response?.data;
			
			if (pedidoData && (pedidoData.id || pedidoData.items)) {
				setPedidoData(pedidoData);
			}
		} catch (error) {
			console.error('Error al cargar pedido:', error);
		} finally {
			setLoadingPedido(false);
		}
	};
	
	const cerrarPedido = () => {
		setPedidoDetalle(null);
		setPedidoData(null);
	};

	const verificarInstapago = async (pago) => {
		if (!pago?.id) return;
		setVerificandoId(pago.id);
		try {
			const res = await db.queryTransaccionPosYActualizarPago({ id: pago.id });
			if (res.data?.success && res.data?.pago) {
				setPagosActualizados(prev => ({ ...prev, [pago.id]: res.data.pago }));
				onRefrescarCierre?.();
				alert('Transacción aprobada. Pago actualizado con datos de Instapago.');
			} else {
				alert(res.data?.message || 'No se encontró transacción aprobada en Instapago con esa referencia y monto.');
			}
		} catch (e) {
			const msg = e.response?.data?.message || e.message || 'Error al verificar con Instapago';
			alert(msg);
		} finally {
			setVerificandoId(null);
		}
	};
	
	if (pagosFiltrados.length === 0) {
		return <div className="alert alert-info">No hay pagos registrados para este tipo.</div>;
	}
	
	// Calcular total de movimientos y verificados
	const totalMovimientos = pagosFiltrados.length;
	const totalVerificados = pagosFiltrados.filter(pago => pagosVerificados[pago.id]).length;
	
	// Determinar qué columnas mostrar según la moneda
	const mostrarUSD = moneda === 'USD' || !moneda;
	const mostrarBS = moneda === 'BS';
	const mostrarCOP = moneda === 'COP';
	const mostrarDebito = moneda === 'PINPAD' || moneda === 'OTROS';
	
	// Si es transferencia, mostrar botón para cambiar vista
	const esTransferencia = tipo === 1;
	
	return (
		<div className="overflow-hidden rounded-lg shadow-sm">
			{/* Header con información de verificación */}
			<div className="p-3 bg-gradient-to-r from-blue-100 to-indigo-100 border-bottom d-flex justify-content-between align-items-center">
				<div className="d-flex align-items-center gap-3">
					<div className="badge bg-primary text-white px-3 py-2" style={{ fontSize: '14px' }}>
						<i className="fa fa-list-ol me-2"></i>
						Total: <strong>{totalMovimientos}</strong>
					</div>
					<div className={`badge ${totalVerificados === totalMovimientos && totalMovimientos > 0 ? 'bg-success' : 'bg-info'} text-white px-3 py-2`} style={{ fontSize: '14px' }}>
						<i className="fa fa-check-circle me-2"></i>
						Verificados: <strong>{totalVerificados}/{totalMovimientos}</strong>
					</div>
				</div>
				{totalMovimientos > 0 && (
					<div className="text-end">
						<small className="text-muted d-block">Progreso de verificación</small>
						<div className="progress" style={{ width: '200px', height: '20px' }}>
							<div 
								className={`progress-bar ${totalVerificados === totalMovimientos ? 'bg-success' : 'bg-primary'}`}
								role="progressbar" 
								style={{ width: `${(totalVerificados / totalMovimientos) * 100}%` }}
								aria-valuenow={totalVerificados} 
								aria-valuemin="0" 
								aria-valuemax={totalMovimientos}
							>
								{Math.round((totalVerificados / totalMovimientos) * 100)}%
							</div>
						</div>
					</div>
				)}
			</div>
			
			{/* Botón para cambiar vista (solo para transferencias) */}
			{esTransferencia && transferenciasAgrupadas && (
				<div className="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
					<div>
						<h6 className="mb-0">
							<i className="fa fa-filter text-primary me-2"></i>
							Vista: <span className="text-primary fw-bold">{vistaAgrupada ? 'Agrupada por Banco' : 'Detallada'}</span>
						</h6>
					</div>
					<button 
						className="btn btn-sm btn-outline-primary"
						onClick={() => setVistaAgrupada(!vistaAgrupada)}
					>
						<i className={`fa fa-${vistaAgrupada ? 'list' : 'layer-group'} me-2`}></i>
						{vistaAgrupada ? 'Ver Detalles' : 'Agrupar'}
					</button>
				</div>
			)}
			
			{/* Vista agrupada (solo transferencias) */}
			{esTransferencia && vistaAgrupada && transferenciasAgrupadas ? (
				<div className="p-4">
					{Object.entries(transferenciasAgrupadas).map(([categoria, bancos]) => (
						<div key={categoria} className="mb-4">
							<h5 className="text-white bg-primary fw-bold p-2 rounded mb-3">
								<i className="fa fa-folder-open me-2"></i>
								{categoria}
							</h5>
							
							<div className="row g-3">
								{Object.entries(bancos).map(([banco, datos]) => {
									const acordeonKey = `${categoria}-${banco}`;
									const estaAbierto = acordeonAbierto[acordeonKey];
									
									return (
										<div key={banco} className="col-md-6">
											<div className="card shadow-sm border-0 h-100">
												<div className="card-header bg-dark text-white">
													<h6 className="mb-0">
														<i className="fa fa-university me-2"></i>
														{banco}
													</h6>
												</div>
												<div className="card-body bg-light">
													<div className="d-flex justify-content-between align-items-center mb-3">
														<div>
															<p className="text-dark mb-1 small fw-bold">Cantidad de transacciones</p>
															<h4 className="mb-0 fw-bold text-dark">
																<i className="fa fa-receipt me-2"></i>
																{datos.cantidad}
															</h4>
														</div>
														<div className="text-end">
															<p className="text-dark mb-1 small fw-bold">Monto Total</p>
															<h4 className="mb-0 fw-bold text-success">
																{formatCurrency(datos.monto_total)}
															</h4>
														</div>
													</div>
													
													{/* Botón para expandir detalles */}
													<button 
														className="btn btn-sm btn-primary w-100 fw-bold"
														type="button"
														onClick={() => toggleAcordeon(acordeonKey)}
													>
														<i className={`fa fa-${estaAbierto ? 'eye-slash' : 'eye'} me-2`}></i>
														{estaAbierto ? 'Ocultar' : 'Ver'} Transacciones
													</button>
													
													{/* Detalles colapsables */}
													{estaAbierto && (
														<div className="mt-3">
															<div className="table-responsive" style={{ maxHeight: '300px', overflowY: 'auto' }}>
																<table className="table table-sm table-hover table-bordered mb-0">
																	<thead className="table-dark sticky-top">
																		<tr>
																			<th className="small text-center">#</th>
																			<th className="small text-center">Verificar</th>
																			<th className="small">ID</th>
																			<th className="small">Pedido</th>
																			<th className="small text-end">Monto</th>
																			<th className="small">Referencia</th>
																			<th className="small">Fecha</th>
																		</tr>
																	</thead>
																	<tbody className="bg-white">
																		{datos.transacciones
																			.sort((a, b) => new Date(a.created_at || a.fecha || 0) - new Date(b.created_at || b.fecha || 0))
																			.map((trans, idx) => (
																			<tr key={idx} className={pagosVerificados[trans.id] ? 'table-success' : ''}>
																				<td className="small text-center fw-bold">{idx + 1}</td>
																				<td className="small text-center">
																					<input 
																						type="checkbox" 
																						className="form-check-input"
																						style={{ width: '16px', height: '16px', cursor: 'pointer' }}
																						checked={pagosVerificados[trans.id] || false}
																						onChange={() => toggleVerificacion(trans.id)}
																						title="Marcar como verificado"
																					/>
																				</td>
																				<td className="small fw-bold">{trans.id}</td>
																				<td className="small">
																					<span className="badge bg-info">#{trans.id_pedido}</span>
																				</td>
																				<td className="small text-end fw-bold text-success">
																					{formatCurrency(trans.monto)}
																				</td>
																				<td className="small text-truncate" style={{ maxWidth: '150px' }} title={trans.descripcion}>
																					{trans.descripcion || 'N/A'}
																				</td>
																				<td className="small text-muted">
																					{trans.created_at ? new Date(trans.created_at).toLocaleString('es-VE', {dateStyle: 'short', timeStyle: 'short'}) : 'N/A'}
																				</td>
																			</tr>
																		))}
																	</tbody>
																	<tfoot className="table-light">
																		<tr>
																			<td colSpan="4" className="text-end fw-bold small">Subtotal:</td>
																			<td className="text-end fw-bold text-success small">
																				{formatCurrency(datos.monto_total)}
																			</td>
																			<td colSpan="2"></td>
																		</tr>
																	</tfoot>
																</table>
															</div>
														</div>
													)}
												</div>
											</div>
										</div>
									);
								})}
							</div>
						</div>
					))}
				</div>
			) : (
				/* Vista detallada (tabla) */
				<div className="overflow-x-auto">
				<table className="min-w-full divide-y divide-gray-200">
					<thead className="bg-gradient-to-r from-blue-50 to-indigo-50">
						<tr>
							<th className="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">#</th>
							<th className="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Verificar</th>
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Pedido</th>
							{(mostrarBS || mostrarDebito) && <th className="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Monto Bs</th>}
							{(mostrarBS || mostrarDebito) && <th className="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Ref. USD</th>}
							{mostrarCOP && <th className="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Monto COP</th>}
							{(mostrarUSD && !mostrarDebito) && <th className="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Monto USD</th>}
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Referencia</th>
							<th className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Fecha</th>
							<th className="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
						</tr>
					</thead>
					<tbody className="bg-white divide-y divide-gray-200">
						{pagosFiltrados.map((pago, idx) => (
							<tr key={idx} className={`hover:bg-blue-50 transition-colors duration-150 ${pagosVerificados[pago.id] ? 'bg-green-50' : ''}`}>
								<td className="px-4 py-3 whitespace-nowrap text-sm font-bold text-center text-gray-700">
									{idx + 1}
								</td>
								<td className="px-4 py-3 whitespace-nowrap text-center">
									<input 
										type="checkbox" 
										className="form-check-input"
										style={{ width: '18px', height: '18px', cursor: 'pointer' }}
										checked={pagosVerificados[pago.id] || false}
										onChange={() => toggleVerificacion(pago.id)}
										title="Marcar como verificado"
									/>
								</td>
								<td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
									<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
										{pago.id}
									</span>
								</td>
								<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
									<span className="font-semibold text-indigo-600">#{pago.id_pedido}</span>
								</td>
								{(mostrarBS || mostrarDebito) && (
									<>
										<td className="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
											<span className="text-green-600">Bs {pago.monto_original ? parseFloat(pago.monto_original).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00'}</span>
										</td>
										<td className="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-gray-700">
											<span className="text-blue-700">${pago.monto ? parseFloat(pago.monto).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00'}</span>
										</td>
									</>
								)}
								{mostrarCOP && (
									<td className="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
										<span className="text-orange-700">COP {pago.monto_original ? parseFloat(pago.monto_original).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00'}</span>
									</td>
								)}
								{(mostrarUSD && !mostrarDebito) && (
									<td className="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
										<span className="text-blue-700">${pago.monto ? parseFloat(pago.monto).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00'}</span>
									</td>
								)}
								<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
									{pago.referencia || <span className="text-gray-400 italic">N/A</span>}
								</td>
								<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
									{pago.created_at ? new Date(pago.created_at).toLocaleString('es-VE', {dateStyle: 'short', timeStyle: 'short'}) : <span className="text-gray-400 italic">N/A</span>}
								</td>
								<td className="px-4 py-3 whitespace-nowrap text-center text-sm">
									<div className="d-flex flex-wrap gap-1 justify-content-center align-items-center">
										{pago.id_pedido ? (
											<button 
												className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150"
												onClick={() => verPedido(pago.id_pedido)}
											>
												<i className="fa fa-file-text mr-1"></i> Ver
											</button>
										) : null}
										{moneda === 'OTROS' && (
											<button 
												className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors duration-150"
												onClick={() => verificarInstapago(pagosActualizados[pago.id] || pago)}
												disabled={verificandoId === pago.id}
												title="Verificar con Instapago si la transacción está aprobada y actualizar el pago"
											>
												{verificandoId === pago.id ? (
													<><i className="fa fa-spinner fa-spin mr-1"></i> Verificando...</>
												) : (
													<><i className="fa fa-check-circle mr-1"></i> Verificar Instapago</>
												)}
											</button>
										)}
										{!pago.id_pedido && moneda !== 'OTROS' && (
											<span className="text-gray-400 text-xs italic">N/A</span>
										)}
									</div>
								</td>
							</tr>
						))}
					</tbody>
					<tfoot className="bg-gradient-to-r from-indigo-50 to-blue-50 border-t-2 border-indigo-200">
						<tr>
							<td colSpan={4} className="px-4 py-4 text-right text-sm font-bold text-gray-800 uppercase">Total:</td>
							{(mostrarBS || mostrarDebito) && (
								<>
									<td className="px-4 py-4 text-right text-lg font-extrabold text-emerald-900">
										Bs {pagosFiltrados.reduce((sum, p) => sum + (parseFloat(p.monto_original) || 0), 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
									</td>
									<td className="px-4 py-4 text-right text-lg font-bold text-blue-900">
										${pagosFiltrados.reduce((sum, p) => sum + (parseFloat(p.monto) || 0), 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
									</td>
								</>
							)}
							{mostrarCOP && (
								<td className="px-4 py-4 text-right text-lg font-extrabold text-orange-900">
									COP {pagosFiltrados.reduce((sum, p) => sum + (parseFloat(p.monto_original) || 0), 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
								</td>
							)}
							{(mostrarUSD && !mostrarDebito) && (
								<td className="px-4 py-4 text-right text-lg font-extrabold text-blue-900">
									${pagosFiltrados.reduce((sum, p) => sum + (parseFloat(p.monto) || 0), 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
								</td>
							)}
							<td colSpan={3}></td>
						</tr>
					</tfoot>
				</table>
			</div>
			)}
			
			{/* Modal anidado para ver detalles del pedido */}
			{pedidoDetalle && (
				<div className="modal fade show" style={{ display: 'block', background: 'rgba(0,0,0,0.7)', zIndex: 1060 }}>
					<div className="modal-dialog modal-lg">
						<div className="modal-content">
							<div className="modal-header">
								<h5 className="modal-title">Detalles del Pedido #{pedidoDetalle}</h5>
								<button type="button" className="btn-close" onClick={cerrarPedido}></button>
							</div>
							<div className="modal-body" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
								{loadingPedido ? (
									<div className="text-center py-4">
										<div className="spinner-border text-primary" role="status">
											<span className="visually-hidden">Cargando...</span>
										</div>
										<p className="mt-2 text-muted">Cargando detalles del pedido...</p>
									</div>
								) : pedidoData && pedidoData.items ? (
									<>
										{/* Información del pedido */}
										<div className="mb-3 p-3 bg-light rounded">
											<div className="row">
												<div className="col-md-6">
													<p className="mb-1"><strong>Pedido:</strong> #{pedidoData.id}</p>
													<p className="mb-1"><strong>Fecha:</strong> {pedidoData.created_at}</p>
													{pedidoData.cliente && (
														<p className="mb-1"><strong>Cliente:</strong> {pedidoData.cliente.nombre}</p>
													)}
												</div>
												<div className="col-md-6 text-end">
													<p className="mb-1"><strong>Total Ref:</strong> <span className="text-success fs-5">${formatCurrency(pedidoData.total)}</span></p>
													<p className="mb-1"><strong>Total Bs:</strong> <span className="text-primary fs-5">Bs {formatCurrency(pedidoData.bs)}</span></p>
													{pedidoData.total_porciento > 0 && (
														<p className="mb-1"><strong>Descuento:</strong> <span className="text-warning">{pedidoData.total_porciento}%</span></p>
													)}
												</div>
											</div>
										</div>
										
										{/* Tabla de productos */}
										<div className="table-responsive">
											<table className="table table-sm table-hover">
												<thead className="table-light">
													<tr>
														<th className="text-start">Producto</th>
														<th className="text-center" style={{width: '80px'}}>Cant.</th>
														<th className="text-end" style={{width: '100px'}}>Precio</th>
														<th className="text-end" style={{width: '80px'}}>Desc.</th>
														<th className="text-end" style={{width: '120px'}}>Total</th>
													</tr>
												</thead>
												<tbody>
													{pedidoData.items.map((item, idx) => (
														<tr key={idx}>
															<td className="text-start">
																{item.producto ? (
																	<>
																		<div className="text-muted small font-monospace">{item.producto.codigo_barras}</div>
																		<div className="fw-medium">{item.producto.descripcion}</div>
																		{item.condicion === 1 && <span className="badge bg-warning text-dark">Garantía</span>}
																		{(item.condicion === 2 || item.condicion === 0) && item.cantidad < 0 && <span className="badge bg-info">Cambio</span>}
																	</>
																) : (
																	<span className="text-muted">MOV - {item.abono}</span>
																)}
															</td>
															<td className="text-center">
																{item.cantidad < 0 ? (
																	<span className="badge bg-success"><i className="fa fa-arrow-down"></i> {Math.abs(item.cantidad)}</span>
																) : (
																	<span>{Number(item.cantidad) % 1 === 0 ? Number(item.cantidad) : Number(item.cantidad).toFixed(2)}</span>
																)}
															</td>
															<td className="text-end">
																{item.producto ? (
																	<>
																		<div>${formatCurrency(item.producto.precio, 4)}</div>
																		{item.cantidad < 0 && item.tasa && (
																			<div className="text-warning small">@{formatCurrency(item.tasa, 4)}</div>
																		)}
																	</>
																) : (
																	<span>${formatCurrency(item.monto)}</span>
																)}
															</td>
															<td className="text-end">
																{item.descuento && parseFloat(item.descuento) > 0 ? (
																	<span className="badge bg-warning text-dark">
																		<i className="fa fa-tag"></i> {item.descuento}%
																	</span>
																) : (
																	<span className="text-muted">-</span>
																)}
															</td>
															<td className="text-end fw-bold">${formatCurrency(item.total)}</td>
														</tr>
													))}
												</tbody>
												<tfoot className="table-light">
													<tr>
														<td colSpan="4" className="text-end fw-bold">Total:</td>
														<td className="text-end fw-bold text-success fs-5">${formatCurrency(pedidoData.total)}</td>
													</tr>
												</tfoot>
											</table>
										</div>
										
										{/* Métodos de pago */}
										{pedidoData.pagos && pedidoData.pagos.length > 0 && (
											<div className="mt-4">
												<h6 className="mb-3 fw-bold text-primary">
													<i className="fa fa-credit-card me-2"></i>
													Métodos de Pago
												</h6>
												<div className="table-responsive">
													<table className="table table-sm table-bordered table-hover">
														<thead className="table-light">
															<tr>
																<th className="text-start">Método</th>
																<th className="text-center">Moneda</th>
																<th className="text-end">Monto Original</th>
																<th className="text-end">Monto (USD)</th>
																<th className="text-center">Referencia</th>
															</tr>
														</thead>
														<tbody>
															{pedidoData.pagos.map((pago) => {
																const getTipoPago = (tipo) => {
																	const tipoStr = String(tipo);
																	switch(tipoStr) {
																		case '1': return { nombre: "Transferencia", icon: "fa-exchange-alt", color: "primary" };
																		case '2': return { nombre: "Débito", icon: "fa-credit-card", color: "warning" };
																		case '3': return { nombre: "Efectivo", icon: "fa-money-bill", color: "success" };
																		case '4': return { nombre: "Crédito", icon: "fa-calendar-alt", color: "info" };
																		case '5': return { nombre: "Biopago", icon: "fa-mobile-alt", color: "purple" };
																		case '6': return { nombre: "Vuelto", icon: "fa-undo", color: "danger" };
																		default: return { nombre: "Otro", icon: "fa-question", color: "secondary" };
																	}
																};
																
																const tipoPago = getTipoPago(pago.tipo);
																const monedaLabel = pago.moneda === 'bs' ? 'Bs' : 
																					  pago.moneda === 'cop' ? 'COP' : 
																					  pago.moneda === 'usd' ? 'USD' : 
																					  pago.moneda || 'USD';
																
																return (
																	<tr key={pago.id}>
																		<td className="text-start">
																			<i className={`fa ${tipoPago.icon} text-${tipoPago.color} me-2`}></i>
																			<span className="fw-medium">{tipoPago.nombre}</span>
																		</td>
																		<td className="text-center">
																			<span className="badge bg-secondary">{monedaLabel}</span>
																		</td>
																		<td className="text-end">
																			{pago.monto_original ? (
																				<span className="fw-semibold">
																					{monedaLabel === 'Bs' ? `Bs ${formatNumber(pago.monto_original)}` : 
																					 monedaLabel === 'COP' ? `COP ${parseFloat(pago.monto_original).toLocaleString('es-VE', {minimumFractionDigits: 0, maximumFractionDigits: 0})}` : 
																					 `$${formatNumber(pago.monto_original)}`}
																				</span>
																			) : (
																				<span className="text-muted">-</span>
																			)}
																		</td>
																		<td className="text-end">
																			<span className="fw-bold text-primary">
																				${formatNumber(pago.monto || 0)}
																			</span>
																		</td>
																		<td className="text-center">
																			{pago.referencia ? (
																				<span className="badge bg-light text-dark font-monospace">
																					{pago.referencia}
																				</span>
																			) : (
																				<span className="text-muted">-</span>
																			)}
																		</td>
																	</tr>
																);
															})}
														</tbody>
														<tfoot className="table-light">
															<tr>
																<td colSpan="3" className="text-end fw-bold">Total Pagado:</td>
																<td className="text-end fw-bold text-success fs-6">
																	${formatNumber(pedidoData.pagos.reduce((sum, p) => sum + (parseFloat(p.monto) || 0), 0))}
																</td>
																<td></td>
															</tr>
														</tfoot>
													</table>
												</div>
											</div>
										)}
									</>
								) : (
									<div className="alert alert-warning">
										<i className="fa fa-exclamation-triangle"></i> No se pudieron cargar los detalles del pedido.
									</div>
								)}
							</div>
							<div className="modal-footer">
								<button type="button" className="btn btn-secondary" onClick={cerrarPedido}>Cerrar</button>
							</div>
						</div>
					</div>
				</div>
			)}
		</div>
	);
}

export default Cierre;