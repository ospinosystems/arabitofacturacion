function Usuarios({

	usuarioNombre,
	setusuarioNombre,
	usuarioUsuario,
	setusuarioUsuario,
	usuarioRole,
	setusuarioRole,
	usuarioClave,
	setusuarioClave,
	usuarioIpPinpad,
	setusuarioIpPinpad,

	indexSelectUsuarios,
	setIndexSelectUsuarios,

	qBuscarUsuario,
	setQBuscarUsuario,

	delUsuario,

	usuariosData,

	addNewUsuario,




}) {



	const setIndexSelectUsuariosFun = e => {
		let index = e.currentTarget.attributes["data-index"].value

		if (index == indexSelectUsuarios) {
			setIndexSelectUsuarios(null)
		} else {
			setIndexSelectUsuarios(index)
		}
	}
	const setNuevoProveedor = () => {
		setusuarioNombre("")
		setusuarioUsuario("")
		setusuarioRole("")
		setusuarioClave("")
		setusuarioIpPinpad("")
		setIndexSelectUsuarios(null)
	}
	return (
		<>
			<div className="container">
				<div className="row">
					<div className="col">
						<h1>Usuarios <button className="btn btn-sm btn-success" onClick={setNuevoProveedor}>Nuevo</button></h1>

						<div className="">
							<div className="input-group ">
								<input type="text"
									className="form-control"
									placeholder="Buscar..."
									value={qBuscarUsuario}
									onChange={e => setQBuscarUsuario(e.target.value)} />
								<div className="input-group-prepend">
									<button className="btn btn-outline-secondary" type="button"><i className="fa fa-search"></i></button>
								</div>
							</div>
						</div>
						{
							usuariosData.length
								? usuariosData.map((e, i) =>
									<div
										onClick={setIndexSelectUsuariosFun}
										data-index={i}
										key={e.id}
										className={(indexSelectUsuarios == i ? "bg-sinapsis" : "bg-light text-secondary") + " card mt-2 pointer"}>
										<div className="card-header flex-row row justify-content-between">
											<div>
												<small>ID.{e.id}</small>
											</div>
											<div className="d-flex justify-content-between">
												<div><span>{e.nombre}</span></div>
											</div>
										</div>
										<div className="card-body">
											<div className="">
												<h5
													className="card-title"
												><b>{e.usuario}</b></h5>
											</div>
											<p className="card-text">
											</p>
										</div>
									</div>
								)
								: <div className='h3 text-center text-dark mt-2'><i>¡Sin resultados!</i></div>
						}

					</div>
					<div className="col">

						<form onSubmit={addNewUsuario}>
							<div className="form-group">
								<label htmlFor="">
									Nombres
								</label>
								<input type="text"
									value={usuarioNombre}
									onChange={e => setusuarioNombre(e.target.value)}
									className="form-control" />
							</div>
							<div className="form-group">
								<label htmlFor="">
									Usuario
								</label>
								<input type="text"
									value={usuarioUsuario}
									onChange={e => setusuarioUsuario(e.target.value)}
									className="form-control" />
							</div>
							<div className="form-group">
								<label htmlFor="">
									Role
								</label>
								<select
									value={usuarioRole}
									onChange={e => setusuarioRole(e.target.value)}
									className="form-control">
									<option value="">--Seleccione--</option>
									<option value="1">GERENTE</option>
									<option value="4">Cajero Vendedor</option>
									<option value="5">SUPERVISOR DE CAJA</option>
									<option value="6">SUPERADMIN</option>
									<option value="7">DICI</option>

								</select>
							</div>
							<div className="form-group">
								<label htmlFor="">
									Clave
								</label>
								<input type="password"
									value={usuarioClave}
									onChange={e => setusuarioClave(e.target.value)}
									className="form-control" />
							</div>
							<div className="form-group">
								<label htmlFor="">
									IP PINPAD
								</label>
								<input type="text"
									value={usuarioIpPinpad}
									onChange={e => setusuarioIpPinpad(e.target.value)}
									placeholder="Ej: 192.168.0.191:9001"
									className="form-control" />
							</div>
							<div className="form-group mt-1">
								{indexSelectUsuarios == null ?
									<button className="btn btn-outline-success btn-block" type="submit">Guardar</button>
									:
									<div className="btn-group">
										<button className="btn btn-sinapsis btn-block" type="submit">Editar</button>
										<button className="btn btn-outline-danger btn-block" onClick={delUsuario} type="button"><i className="fa fa-times"></i></button>

									</div>
								}
							</div>
						</form>
					</div>
				</div>
			</div>
		</>
	)
}
export default Usuarios