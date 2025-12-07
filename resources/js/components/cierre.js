import { useEffect } from 'react';
import Historicocierre from '../components/historicocierre';
import { cloneDeep } from "lodash";


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

	lote1punto,
	setlote1punto,
	montolote1punto,
	setmontolote1punto,
	lote2punto,
	setlote2punto,
	montolote2punto,
	setmontolote2punto,
	serialbiopago,
	setserialbiopago,

	lotespuntototalizar,
	biopagostotalizar,

	puntolote1banco,	
	puntolote2banco,	
	setpuntolote1banco,
	setpuntolote2banco,
	reversarCierre,
	bancos,

}) {

	useEffect(()=>{
		let montolote1puntoval = parseFloat(montolote1punto)
		let montolote2puntoval = parseFloat(montolote2punto)

        montolote1puntoval = montolote1puntoval == "NaN" || !montolote1puntoval ? "" : montolote1puntoval;
        montolote2puntoval = montolote2puntoval == "NaN" || !montolote2puntoval ? "" : montolote2puntoval;

		if (montolote1puntoval) {
			setCaja_punto(montolote1puntoval+montolote2puntoval)
		}

	},[montolote1punto,montolote2punto])

	useEffect(()=>{
		setguardar_cop(caja_cop-dejar_cop)
		setguardar_bs(caja_bs-dejar_bs)

	},[guardar_usd, dejar_bs,dejar_cop])

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
								<button className="btn btn-sinapsis" onClick={cerrar_dia}><i className="fa fa-cogs"></i></button>

								<input type="date" disabled={true} value={fechaCierre} className="form-control" onChange={null}/>
							</div>
							{cierre["fecha"]?
								<div className='d-flex justify-content-between'>

									<div className='btn-group mt-2 mb-2 w-30'>
		
										{tipo_accionCierre=="guardar"?
										<button className="btn-sm btn btn-outline-success" onClick={guardar_cierre} type="button">Guardar</button>
										:
											<button className="btn-sm btn btn-warning" onClick={guardar_cierre} type="button">Editar</button>
										}
										<button className="btn-sm btn btn-sinapsis" onClick={veryenviarcierrefun} type="button" data-type="ver">Ver</button>

										
										<button className="btn btn-sm" onClick={()=>setToggleDetallesCierre(!toggleDetallesCierre)}>Ver detalles</button>
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
											{totalizarcierre?"Totalizando ":"Cajero "} {cierre["fecha"]?cierre["usuariosget"].map(e=>
												<span key={e.id}> {e.usuario} </span>
											):null}
										</span>		
									</div>
									
								</div>
							:null}
							{cierre["fecha"]?
								<>
									<form onSubmit={cerrar_dia}>
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
													<div className="row mb-2">
														<div className="col-2 text-success text-right">Billetes $</div>
														<div className="col font-weight-bold align-middle" >
															<div>
																<span className="h5">1</span>
																	<input type="text" value={billete1} name="billete1" onChange={e=>setbillete1(number(e.target.value))} className="input-50" placeholder="1 $" />
																	<span className="h5">5</span>
																	<input type="text" value={billete5} name="billete5" onChange={e=>setbillete5(number(e.target.value))} className="input-50" placeholder="5 $" />
																	<span className="h5">10</span>
																	<input type="text" value={billete10} name="billete10" onChange={e=>setbillete10(number(e.target.value))} className="input-50" placeholder="10 $" />
																	<span className="h5">20</span>
																	<input type="text" value={billete20} name="billete20" onChange={e=>setbillete20(number(e.target.value))} className="input-50" placeholder="20 $" />
																	<span className="h5">50</span>
																	<input type="text" value={billete50} name="billete50" onChange={e=>setbillete50(number(e.target.value))} className="input-50" placeholder="50 $" />
																	<span className="h5">100</span>
																	<input type="text" value={billete100} name="billete100" onChange={e=>setbillete100(number(e.target.value))} className="input-50" placeholder="100 $" />
															</div>
														</div>
													</div>
													<div className="row mb-2">
														<div className="col-2"></div>
														<div className="col">
															$ <input type="text"  placeholder="$" name="caja_usd" value={caja_usd} onChange={onchangecaja}/>
														</div>

														<div className="col">
															P <input type="text"  placeholder="COP" name="caja_cop" value={caja_cop} onChange={onchangecaja}/>
														</div>

														<div className="col">
															Bs. <input type="text"  placeholder="Bs." name="caja_bs" value={caja_bs} onChange={onchangecaja}/>
														</div>
													</div>
													<div className="row mb-2">
														<div className="col-2 text-success text-right">Total Caja Actual en $</div>
														<div className="col" >
															<input type="text" value={total_caja_neto} className="form-control" disabled={true}/>
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
													<div className="row mb-2">
														<div className="col-2 text-success text-right">LOTES</div>

														<div className="col align-middle text-center" >
														<table className="table">
															<thead>
																<tr>
																	<th>CATEGORÍA</th>
																	<th>MONTO</th>
																	<th>LOTE</th>
																	<th>BANCO</th>
																	<th><button className="btn btn-success" type="button" onClick={()=>addTuplasPuntosAdicionales("add",null)}><i className="fa fa-plus"></i></button></th>
																</tr>
															</thead>
															<tbody>
																{dataPuntosAdicionales.map((e,i)=>
																	<tr key={i}>
																		<td>
																			<select className='form-control' disabled={totalizarcierre} value={e.categoria} onChange={event=>onchangetuplapuntosnew("categoria",i,event.target.value)}>
																				<option value="">-</option>
																				<option value="DEBITO">DEBITO</option>
																				<option value="CREDITO">CREDITO</option>
																			</select>
																		</td>
																		<td>
																			<input type="text" className='form-control' disabled={totalizarcierre} value={e.monto} placeholder='monto' onChange={event=>onchangetuplapuntosnew("monto",i,number(event.target.value))} />
																		</td>
																		<td>
																			<input type="text" className='form-control' disabled={totalizarcierre} value={e.descripcion} placeholder='numlote' onChange={event=>onchangetuplapuntosnew("descripcion",i,event.target.value)} />
																		</td>
																		<td>
																			<select className='form-control' disabled={totalizarcierre} value={e.banco} onChange={event=>onchangetuplapuntosnew("banco",i,event.target.value)}>
																				{bancos.filter(e=>e.value!="0134").map((e,i)=>
																					<option key={i} value={e.value}>{e.text}</option>
																				)}
																			</select>
																		</td>
																		<td> <i className='fa fa-times text-danger' onClick={()=>addTuplasPuntosAdicionales("delete",i)}></i> </td>
																	</tr>	
																)}
															</tbody>
														</table>
														
														
															{/* <div className="input-group">
																<div className="input-group-prepend w-50">
																	<input type="text" className="form-control" placeholder="MONTO LOTE 1" name="montolote1punto" value={montolote1punto} onChange={e => setmontolote1punto(number(e.target.value))} />
																</div>
																<input type="text" className="form-control" placeholder="LOTE 1" name="lote1punto" value={lote1punto} onChange={e => setlote1punto((e.target.value))} />
																<select name="puntolote1banco" className="form-control" value={puntolote1banco} onChange={(e)=>setpuntolote1banco(e.target.value)}>
																	{bancos.map((e,i)=>
																		<option key={i} value={e.value}>{e.text}</option>
																	)}
																</select>
															</div>

															<div className="input-group">
																<div className="input-group-prepend w-50">
																	<input type="text" className="form-control" placeholder="MONTO LOTE 2 (OPCIONAL)" name="montolote2punto" value={montolote2punto} onChange={e => setmontolote2punto(number(e.target.value))} />
																</div>
																<input type="text" className="form-control" placeholder="LOTE 2 (OPCIONAL)" name="lote2punto" value={lote2punto} onChange={e => setlote2punto((e.target.value))} />
																<select name="puntolote2banco" className="form-control" value={puntolote2banco} onChange={(e)=>setpuntolote2banco(e.target.value)}>
																	{bancos.map((e,i)=>
																		<option key={i} value={e.value}>{e.text}</option>
																	)}
																</select>
															</div> */}

															
														</div>
													</div>
													



													<div className="row mb-2">
														<div className="col-2 text-success text-right">Total Punto de Venta en Bs</div>

														<div className="col align-middle text-center" >
															<input type="text" className="form-control" placeholder="Punto de venta Bs." name="caja_punto" value={caja_punto} onChange={onchangecaja} disabled/>
														</div>
													</div>
													
													{totalizarcierre?
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
															<input type="text" className="form-control" placeholder="SERIAL Biopago" name="serialbiopago" value={serialbiopago} onChange={e => setserialbiopago((e.target.value))}/>
														</div>
													</div>
													}
													<div className="row mb-2">
														<div className="col-2 text-success text-right">Total Biopago en Bs</div>
														<div className="col align-middle text-center" >
															<input type="text" className="form-control" placeholder="Biopago Bs." name="caja_biopago" value={caja_biopago} onChange={onchangecaja}/>
														</div>
													</div>
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
															$ <input type="text" placeholder="$" name="dejar_usd" value={dejar_usd} onChange={onchangecaja}/>
														</div>
														<div className="col">
															P <input type="text" placeholder="COP" name="dejar_cop" value={dejar_cop} onChange={onchangecaja}/>
														</div>
														<div className="col">
															Bs. <input type="text" placeholder="Bs." name="dejar_bs" value={dejar_bs} onChange={onchangecaja}/>
															
														</div>
													</div>
													<div className="row p-2">
														<div className="col-2 text-success text-right">Total Dejar Caja</div>

														<div className="col align-middle text-center" >
															<input type="text" value={total_dejar_caja_neto} className="form-control" disabled={true}/>
														</div>
													</div>
												</div>


												<div className="p-3 card shadow-card mb-2">
													<div className="row p-2 border-bottom">
														<div className="col h3 text-center">
															Cuadre Final
														</div>
													</div>
													<div className="row p-2">
														<div className="col-2 text-succes text-right font-weight-bold"></div>
														<div className="col text-success">Facturado Real</div>
														<div className="col text-success">Facturado Digital</div>
														<div className="col"></div>
													</div>
													<div className="row p-2 border-bottom">
														<div className={(cierre["estado_efec"]==1?"text-success":"text-danger")+ (" col-2 text-right h5")}>Efectivo</div>
														<div className="col">
															{cierre["total_caja"]?cierre["total_caja"]:0}
															
														</div>
														<div className="col align-middle">
															{cierre[3]?cierre[3].toFixed(2):null}
														</div>

														<div className="col text-right">
															<div className={(cierre["estado_efec"]==1?"text-success":"text-danger")}>
																<span className="fst-italic fs-2">
																	{cierre["msj_efec"]?cierre["msj_efec"]:null}
																</span>
															</div>
														</div>
													</div>
													<div className="row p-2 border-bottom">
														<div className={(cierre["estado_punto"]==1?"text-success":"text-danger")+(" col-2 text-right h5")}>Débito</div>
														<div className="col">
															{total_punto}
															
														</div>
														<div className="col align-middle">
															{cierre[2]?cierre[2].toFixed(2):null}
														</div>
														<div className="col text-right">
															<div className={(cierre["estado_punto"]==1?"text-success":"text-danger")}>
																<span className="fst-italic fs-2">
																	{cierre["msj_punto"]?cierre["msj_punto"]:null}
																</span>
															</div>
														</div>
													</div>
													<div className="row p-2 border-bottom">
														<div className={(cierre["estado_biopago"]==1?"text-success":"text-danger")+(" col-2 text-right h5")}>Biopago</div>
														<div className="col">
															{total_biopago}
															
														</div>
														<div className="col align-middle">
															{cierre[5]?cierre[5].toFixed(2):null}
														</div>
														<div className="col text-right">
															<div className={(cierre["estado_biopago"]==1?"text-success":"text-danger")}>
																<span className="fst-italic fs-2">
																	{cierre["msj_biopago"]?cierre["msj_biopago"]:null}
																</span>
															</div>
														</div>
													</div>
													
													<div className="row p-2 border-bottom">
														<div className="col-2 text-success text-right h5">Transferencia</div>
														<div className="col">
															{cierre[1]?cierre[1].toFixed(2):null}
														</div>
														<div className="col">
															{cierre[1]?cierre[1].toFixed(2):null}
														</div>
														<div className="col"></div>
													</div>
													<div className="row p-2">
														<div className="col-2 text-success text-right h5">Nota</div>
														<div className="col">
															<textarea name="notaCierre" placeholder="Novedades..." value={notaCierre?notaCierre:""} onChange={onchangecaja} cols="40" rows="2"></textarea>
														</div>
														<div className="col">
															
														</div>
													</div>
												</div>


												<div className="p-3 card shadow-card mb-2">

													<div className="row">
														<div className="col-2 text-success text-right font-weight-bold h5">Control de Efectivo</div>
														<div className="col">
															<span className="text-success fs-3 fw-bold">{cierre["efectivo_guardado"]?cierre["efectivo_guardado"]:null}</span>
														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="$" name="guardar_usd" value={guardar_usd} onChange={e=>setguardar_usd(number(e.target.value))} disabled={true}/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">USD($)</span>
																</div>
															</div>
															
														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="COP" name="guardar_cop" value={guardar_cop} disabled={true} onChange={e=>fun_setguardar("setguardar_cop",number(e.target.value), cierre)}/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">COP.</span>
																</div>
															</div>
															
														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="Bs." name="guardar_bs" value={guardar_bs} disabled={true} onChange={e=>fun_setguardar("setguardar_bs",number(e.target.value), cierre)}/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">Bs.</span>
																</div>
															</div>
														</div>
													</div>
													<hr />
													<div className="row">
														<div className="col-2 text-primary text-right font-weight-bold h5 align-items-center d-flex justify-content-end">Caja Fuerte</div>
														<div className="col">
															<span className="text-primary fs-3 fw-bold">{totalCajaFuerte}</span>
														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="$" name="CajaFuerteEntradaCierreDolar" value={CajaFuerteEntradaCierreDolar} onChange={e => setcajaFuerteFun("setCajaFuerteEntradaCierreDolar",number(e.target.value))} disabled/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">USD($)</span>
																</div>
															</div>

														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="COP" name="CajaFuerteEntradaCierreCop" value={CajaFuerteEntradaCierreCop} onChange={e => setcajaFuerteFun("setCajaFuerteEntradaCierreCop",number(e.target.value))} disabled/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">COP.</span>
																</div>
															</div>

														</div>
														<div className="col">
															<div className="input-group mb-3">
																<input type="text" className="form-control" placeholder="Bs." name="CajaFuerteEntradaCierreBs" value={CajaFuerteEntradaCierreBs} onChange={e => setcajaFuerteFun("setCajaFuerteEntradaCierreBs",number(e.target.value))} disabled/>
																<div className="input-group-prepend w-50">
																	<span className="input-group-text">Bs.</span>
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
																	<input type="text" className='form-control' value={cierrenumreportez} onChange={e=>setcierrenumreportez(e.target.value)} />
																</td>
															</tr>
															<tr>
																<th>Venta Exento</th>
																<td>
																	<input type="text" className='form-control' value={cierreventaexcento} onChange={e=>setcierreventaexcento(number(e.target.value))} />
																</td>
															</tr>
															<tr>
																<th>Ventas Gravadas (16%)</th>
																<td>
																	<input type="text" className='form-control' value={cierreventagravadas} onChange={e=>setcierreventagravadas(number(e.target.value))} />
																</td>
															</tr>
															<tr>
																<th>IVA Ventas</th>
																<td>
																	<input type="text" className='form-control' value={cierreivaventa} onChange={e=>setcierreivaventa(number(e.target.value))} />
																</td>
															</tr>
															<tr>
																<th>Total Ventas</th>
																<td>
																	<input type="text" className='form-control' value={cierretotalventa} onChange={e=>setcierretotalventa(number(e.target.value))} />
																</td>
															</tr>
															<tr>
																<th>Última Factura</th>
																<td>
																	<input type="text" className='form-control' value={cierreultimafactura} onChange={e=>setcierreultimafactura(e.target.value)} />
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

								:<div className="d-flex justify-content-center align-items-center">
									<button className="btn btn-xl btn-success" onClick={cerrar_dia}>¡Cerremos el día!</button>
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
			
		</div>
	)
}

export default Cierre;