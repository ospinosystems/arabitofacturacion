import { useEffect } from "react";

export default function EstadisticaInventario({
	fechaQEstaInve,
	setfechaQEstaInve,
	fechaFromEstaInve,
	setfechaFromEstaInve,
	fechaToEstaInve,
	setfechaToEstaInve,
	orderByEstaInv,
	setorderByEstaInv,
	orderByColumEstaInv,
	setorderByColumEstaInv,
	
	dataEstaInven,
	moneda,

	categoriaEstaInve,
	setcategoriaEstaInve,
	categorias,
	getEstaInventario,
}) {
	/* useEffect(() => {
		getEstaInventario();
	}, [
		fechaQEstaInve,
		fechaFromEstaInve,
		fechaToEstaInve,
		orderByEstaInv,
		categoriaEstaInve,
		orderByColumEstaInv,
	]); */


	let data = []

	try{
		data = Object.values(dataEstaInven)
	}catch(err){

	}


	return (
		<div className="container">
			<form className="input-group" onSubmit={e=>{e.preventDefault();getEstaInventario();}}>
				<select
					className={("form-control form-control-sm ")}
					value={categoriaEstaInve}
					onChange={e => setcategoriaEstaInve((e.target.value))}
				>
					<option value="">--Select--</option>
					{categorias.map(e => <option value={e.id} key={e.id}>{e.descripcion}</option>)}
					
				</select>
				<input type="text" className="form-control" placeholder="Buscar..." value={fechaQEstaInve} onChange={e=>setfechaQEstaInve(e.target.value)}/>
				<input type="date" className="form-control" value={fechaFromEstaInve} onChange={e=>setfechaFromEstaInve(e.target.value)}/>
				<input type="date" className="form-control" value={fechaToEstaInve} onChange={e=>setfechaToEstaInve(e.target.value)}/>
				
				<select className="form-control" value={orderByEstaInv} onChange={e=>setorderByEstaInv(e.target.value)}>
					<option value="asc">ASC</option>
					<option value="desc">DESC</option>
				</select>
				<button
					className="btn btn-sinapsis "
					type="submit"
					style={{ marginLeft: "8px" }}
				>
					Buscar
				</button>
			</form>
			<table className="table">
				<thead>
					<tr>
						
						<th className="pointer">Categoría</th>
						<th>ID</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("codigo")}>Código</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("descripcion")}>Descripción</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("cantidad")}>Stock</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("precio")}>Precio</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("cantidadtotal")}>Total Ventas Unitarias</th>
						<th className="pointer" onClick={()=>setorderByColumEstaInv("totalventa")}>Total Monto Venta</th>
					</tr>
				</thead>
				<tbody>
					{data?data.map(e=>
						<tr key={e.id}>
							<td>{e.categoria?e.categoria.descripcion:null}</td>
							<td>{e.id}</td>
							<td>{e.codigo_barras}</td>
							<td>{e.descripcion}</td>
							<td>{e.cantidad}</td>
							<td className="text-success">{moneda(e.precio)}</td>
							<td className="text-sinapsis">{e.cantidadtotal}</td>
							<td className="text-success">{moneda(e.totalventa)}</td>

						</tr>
						):null}
				</tbody>
			</table>
		</div>
	)
}