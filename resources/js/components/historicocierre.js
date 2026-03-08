export default function Historicocierre({
	cierres,
	fechaGetCierre,
	setfechaGetCierre,
	getCierres,
	verCierreReq,
	fechaGetCierre2,
	setfechaGetCierre2,
	tipoUsuarioCierre,
	settipoUsuarioCierre,
}) {
	return (
		<div className="container-fluid p-0">
			<div className="input-group mb-3">
				<input type="date" className="form-control" value={fechaGetCierre} onChange={e => setfechaGetCierre(e.target.value)} />
				<input type="date" className="form-control" value={fechaGetCierre2} onChange={e=>setfechaGetCierre2(e.target.value)}/>
				<select className="form-control" value={tipoUsuarioCierre} onChange={e=>settipoUsuarioCierre(e.target.value)}>
					<option value="">Todos</option>
					<option value="1">Administrador</option>
					<option value="0">Cajero</option>
				</select>
				<div className="inputr-group-append">
					<button className="btn" onClick={getCierres}><i className="fa fa-search"></i></button>
				</div>
			</div>
			<table className="table table-sm">
				<thead>
					<tr>
						<th>Cajero</th>
						<th>Num. Ventas</th>
						<th>Débito</th>
						<th>Efectivo</th>
						<th>Transferencia</th>
						<th>Dejar $</th>
						<th>Dejar COP</th>
						<th>Dejar BS.</th>
						<th>Guardado $</th>
						<th>Guardado COP</th>
						<th>Guardado BS</th>

						<th>Actual $</th>
						<th>Actual COP</th>
						<th>Actual BS</th>
						<th>Actual Biopago BS</th>
						<th>Actual Punto BS</th>

						<th>Tasa</th>
						<th>Fecha</th>

					</tr>
					{cierres ? cierres.cierres ? cierres.cierres.length ? cierres.cierres.map(e=>
						<tr key={e.id}>
							<td>{e.usuario.usuario}</td>
							<td>{e.numventas}</td>
							<td>{e.debito}</td>
							<td>{e.efectivo}</td>
							<td>{e.transferencia}</td>
							<td>{e.dejar_dolar}</td>
							<td>{e.dejar_peso}</td>
							<td>{e.dejar_bss}</td>
							<td>{e.efectivo_guardado}</td>
							<td>{e.efectivo_guardado_cop}</td>
							<td>{e.efectivo_guardado_bs}</td>

							<td>{e.efectivo_actual}</td>
							<td>{e.efectivo_actual_cop}</td>
							<td>{e.efectivo_actual_bs}</td>
							<td>{e.caja_biopago}</td>
							<td>{e.puntodeventa_actual_bs}</td>
							
							
							
							

							<td>{e.tasa}</td>
							<th>{e.fecha}</th>
							<td>
								<button className="btn btn-outline-success" onClick={()=>verCierreReq(e.fecha,"ver",e.id_usuario)} type="button">Ver</button>
								<button className="btn btn-outline-success" onClick={()=>verCierreReq(e.fecha,"enviar",e.id_usuario)} type="button"><i className="fa fa-send"></i></button>
							</td>
						</tr>

						):null:null:null}
					<tr>
						<td></td>
						<th>{cierres.numventas}</th>
						<th>{cierres.debito}</th>
						<th>{cierres.efectivo}</th>
						<th>{cierres.transferencia}</th>
						<th>{cierres.dejar_dolar}</th>
						<th>{cierres.dejar_peso}</th>
						<th>{cierres.dejar_bss}</th>
						<th>{cierres.efectivo_guardado}</th>
						<th>{cierres.efectivo_guardado_cop}</th>
						<th>{cierres.efectivo_guardado_bs}</th>
						<th>{cierres.efectivo_actual}</th>
						<th>{cierres.efectivo_actual_cop}</th>
						<th>{cierres.efectivo_actual_bs}</th>
						<th>{cierres.caja_biopago}</th>
						<th>{cierres.puntodeventa_actual_bs}</th>
						
					</tr>
				</thead>
			</table>
			<h1>Total <b>{ fechaGetCierre}</b> - <b>{ fechaGetCierre2}</b></h1>
			{cierres?cierres.numventas?
				<table className="table">
					<thead>
						<tr>
							<th className="bg-success text-right">Inversión</th>
							<th className="bg-success text-right">Venta</th>
							<th className="bg-success text-right">Ganancia Estimada</th>
							<th className="bg-success text-right">Porcentaje promedio</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td className="fw-bold text-right">{cierres.precio_base}</td>
							<td className="fw-bold text-right">{cierres.precio}</td>
							<td className="fw-bold  text-success text-right">{cierres.ganancia}</td>
							<td className="fw-bold text-right">{cierres.porcentaje}%</td>
						</tr>
					</tbody>
				</table>
			:null:null}
		</div>
	)
}