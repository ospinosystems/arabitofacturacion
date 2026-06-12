import React, { useState, useMemo } from "react";
import {
	BarChart,
	Bar,
	CartesianGrid,
	XAxis,
	YAxis,
	Tooltip,
	ResponsiveContainer,
	PieChart,
	Pie,
	Cell,
	Legend,
} from "recharts";
import db from "../database/database";

const COLORS = ["#f97316", "#3b82f6", "#8b5cf6", "#10b981", "#eab308"];

function parseNum(val) {
	if (val == null || val === "") return 0;
	if (typeof val === "number" && !Number.isNaN(val)) return val;
	const s = String(val).replace(/,/g, "");
	const n = parseFloat(s);
	return Number.isNaN(n) ? 0 : n;
}

// HISTORIAL-MONEDA-ORIGINAL 2026-06-12 — tipo_pago en cierres_metodos_pago:
// 1=Transferencia, 2=Débito, 3=Efectivo, 5=Biopago. Cada fila trae moneda
// (BS/USD/COP) + monto_digital + monto_real EN SU MONEDA ORIGINAL.
const TIPO_PAGO = { TRANSFERENCIA: 1, DEBITO: 2, EFECTIVO: 3, BIOPAGO: 5 };
const ORDEN_MONEDA = ["BS", "USD", "COP"];

function fmtMoneda(monto, moneda) {
	const n = Number(monto || 0);
	const txt = n.toLocaleString("es", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	const m = (moneda || "").toUpperCase();
	if (m === "USD") return `$${txt}`;
	if (m === "COP") return `${txt} COP`;
	return `${txt} Bs`; // BS por defecto
}

// Agrupa los metodosPago de un cierre por tipo → moneda → {dig, real}.
// Suma subtipos (ej débito pinpad + otros_puntos) que comparten tipo y moneda.
function agruparMetodos(metodos) {
	const acc = {}; // { tipo: { moneda: { dig, real } } }
	(metodos || []).forEach((mp) => {
		const tipo = parseInt(mp.tipo_pago);
		const moneda = (mp.moneda || "BS").toUpperCase();
		if (!acc[tipo]) acc[tipo] = {};
		if (!acc[tipo][moneda]) acc[tipo][moneda] = { dig: 0, real: 0 };
		acc[tipo][moneda].dig += parseNum(mp.monto_digital);
		acc[tipo][moneda].real += parseNum(mp.monto_real);
	});
	return acc;
}

// Devuelve líneas [{moneda, dig, real}] de un tipo, en orden BS→USD→COP, solo con valor.
function lineasDeTipo(agrupado, tipo) {
	const porMoneda = agrupado[tipo] || {};
	return ORDEN_MONEDA
		.filter((m) => porMoneda[m])
		.map((m) => ({ moneda: m, ...porMoneda[m] }))
		.filter((l) => Math.abs(l.dig) > 0.001 || Math.abs(l.real) > 0.001);
}

export default function HistorialVentasCierre({ onClose }) {
	const [fechaDesde, setFechaDesde] = useState("");
	const [fechaHasta, setFechaHasta] = useState("");
	const [tipoCierre, setTipoCierre] = useState(""); // "" = no elegido, "0" = cajero, "1" = admin
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState("");
	const [vistaResumen, setVistaResumen] = useState(true); // true = resumen + gráfico, false = detalle

	const buscar = () => {
		if (!tipoCierre || (tipoCierre !== "0" && tipoCierre !== "1")) {
			setError("Seleccione un tipo de cierre: Administrador o Cajero.");
			return;
		}
		if (!fechaDesde || !fechaHasta) {
			setError("Indique el rango de fechas (desde y hasta).");
			return;
		}
		if (fechaDesde > fechaHasta) {
			setError("La fecha desde no puede ser mayor que la fecha hasta.");
			return;
		}
		setError("");
		setLoading(true);
		db.getCierres({
			fechaGetCierre: fechaDesde,
			fechaGetCierre2: fechaHasta,
			tipoUsuarioCierre: tipoCierre,
		})
			.then((res) => {
				setData(res.data);
			})
			.catch((err) => {
				setError(err.response?.data?.msj || err.message || "Error al cargar cierres.");
				setData(null);
			})
			.finally(() => setLoading(false));
	};

	// Datos para gráfico por método de pago (montos)
	const chartData = useMemo(() => {
		if (!data || !data.cierres || !data.cierres.length) return [];
		let debito = 0,
			efectivo = 0,
			transferencia = 0,
			biopago = 0;
		data.cierres.forEach((c) => {
			debito += parseNum(c.debito);
			efectivo += parseNum(c.efectivo);
			transferencia += parseNum(c.transferencia);
			biopago += parseNum(c.caja_biopago);
		});
		return [
			{ nombre: "Efectivo", monto: efectivo, fill: COLORS[0] },
			{ nombre: "Débito", monto: debito, fill: COLORS[1] },
			{ nombre: "Transferencia", monto: transferencia, fill: COLORS[2] },
			{ nombre: "Biopago", monto: biopago, fill: COLORS[3] },
		].filter((d) => d.monto > 0);
	}, [data]);

	const totalVentas = data?.numventas != null ? data.numventas : 0;
	const totalPrecio = data?.precio ? parseNum(data.precio) : 0;

	// HISTORIAL-MONEDA-ORIGINAL 2026-06-12 — total agregado por (tipo, moneda) de
	// todos los cierres del rango, para el footer en moneda original.
	const totalMetodos = useMemo(() => {
		const acc = {};
		(data?.cierres || []).forEach((c) => {
			// Laravel serializa la relación metodosPago como metodos_pago (snake_case)
			(c.metodos_pago || c.metodosPago || []).forEach((mp) => {
				const tipo = parseInt(mp.tipo_pago);
				const moneda = (mp.moneda || "BS").toUpperCase();
				if (!acc[tipo]) acc[tipo] = {};
				if (!acc[tipo][moneda]) acc[tipo][moneda] = { dig: 0, real: 0 };
				acc[tipo][moneda].dig += parseNum(mp.monto_digital);
				acc[tipo][moneda].real += parseNum(mp.monto_real);
			});
		});
		return acc;
	}, [data]);

	const verReporteCierre = (cierre) => {
		// Cierre cajero (tipo_cierre 0): enviar id_usuario para ver esa caja. Cierre admin (1): no enviar usuario.
		const esCierreCajero = cierre.tipo_cierre === 0 || cierre.tipo_cierre === "0";
		db.openVerCierre({
			type: "ver",
			fechaCierre: cierre.fecha,
			usuario: esCierreCajero ? (cierre.id_usuario ?? "") : "",
		});
	};

	return (
		<div className="p-4 max-w-6xl mx-auto">
			<div className="flex items-center justify-between mb-4">
				<h2 className="text-xl font-semibold text-gray-800">
					Historial de ventas por cierre
				</h2>
				{onClose && (
					<button
						type="button"
						onClick={onClose}
						className="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50"
					>
						Cerrar
					</button>
				)}
			</div>

			{/* Filtros */}
			<div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm mb-4">
				<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
					<div>
						<label className="block text-xs font-medium text-gray-600 mb-1">
							Fecha desde
						</label>
						<input
							type="date"
							value={fechaDesde}
							onChange={(e) => setFechaDesde(e.target.value)}
							className="w-full border border-gray-300 rounded px-3 py-2 text-sm"
						/>
					</div>
					<div>
						<label className="block text-xs font-medium text-gray-600 mb-1">
							Fecha hasta
						</label>
						<input
							type="date"
							value={fechaHasta}
							onChange={(e) => setFechaHasta(e.target.value)}
							className="w-full border border-gray-300 rounded px-3 py-2 text-sm"
						/>
					</div>
					<div>
						<label className="block text-xs font-medium text-gray-600 mb-1">
							Tipo de cierre
						</label>
						<select
							value={tipoCierre}
							onChange={(e) => setTipoCierre(e.target.value)}
							className="w-full border border-gray-300 rounded px-3 py-2 text-sm"
						>
							<option value="">Seleccione...</option>
							<option value="1">Cierre administrador</option>
							<option value="0">Cierre cajero</option>
						</select>
					</div>
					<div>
						<button
							type="button"
							onClick={buscar}
							disabled={loading}
							className="w-full px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded hover:bg-orange-700 disabled:opacity-50 flex items-center justify-center gap-2"
						>
							{loading ? (
								<>
									<span className="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
									Buscando...
								</>
							) : (
								<>
									<i className="fa fa-search" />
									Buscar
								</>
							)}
						</button>
					</div>
				</div>
				{error && (
					<p className="mt-2 text-sm text-red-600" role="alert">
						{error}
					</p>
				)}
			</div>

			{/* Tabs Resumen / Detalle */}
			{data && data.cierres && data.cierres.length > 0 && (
				<div className="flex gap-2 mb-3">
					<button
						type="button"
						onClick={() => setVistaResumen(true)}
						className={`px-3 py-1.5 text-sm font-medium rounded ${
							vistaResumen
								? "bg-orange-100 text-orange-800 border border-orange-300"
								: "bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200"
						}`}
					>
						Resumen y gráfico
					</button>
					<button
						type="button"
						onClick={() => setVistaResumen(false)}
						className={`px-3 py-1.5 text-sm font-medium rounded ${
							!vistaResumen
								? "bg-orange-100 text-orange-800 border border-orange-300"
								: "bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200"
						}`}
					>
						Detallado
					</button>
				</div>
			)}

			{/* Resultados */}
			{data && data.cierres && data.cierres.length > 0 && vistaResumen && (
				<>
					{/* Resumen: cantidad de ventas y montos por método */}
					<div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
						<div className="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
							<p className="text-xs text-gray-500 uppercase">Cantidad de ventas</p>
							<p className="text-lg font-semibold text-gray-800">{totalVentas}</p>
						</div>
						<div className="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
							<p className="text-xs text-gray-500 uppercase">Total venta</p>
							<p className="text-lg font-semibold text-green-700">
								{typeof data.precio === "string" ? data.precio : totalPrecio.toFixed(2)}
							</p>
						</div>
						<div className="bg-white border border-orange-100 rounded-lg p-3 shadow-sm border-l-4 border-l-orange-500">
							<p className="text-xs text-gray-500 uppercase">Efectivo</p>
							<p className="text-lg font-semibold text-gray-800">
								{data.efectivo ?? "0.00"}
							</p>
						</div>
						<div className="bg-white border border-blue-100 rounded-lg p-3 shadow-sm border-l-4 border-l-blue-500">
							<p className="text-xs text-gray-500 uppercase">Débito</p>
							<p className="text-lg font-semibold text-gray-800">
								{data.debito ?? "0.00"}
							</p>
						</div>
						<div className="bg-white border border-purple-100 rounded-lg p-3 shadow-sm border-l-4 border-l-purple-500">
							<p className="text-xs text-gray-500 uppercase">Transferencia</p>
							<p className="text-lg font-semibold text-gray-800">
								{data.transferencia ?? "0.00"}
							</p>
						</div>
						<div className="bg-white border border-teal-100 rounded-lg p-3 shadow-sm border-l-4 border-l-teal-500">
							<p className="text-xs text-gray-500 uppercase">Biopago</p>
							<p className="text-lg font-semibold text-gray-800">
								{data.caja_biopago ?? "0.00"}
							</p>
						</div>
					</div>

					{/* Gráfico */}
					<div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm mb-4">
						<h3 className="text-sm font-semibold text-gray-700 mb-3">
							Montos por método de pago
						</h3>
						{chartData.length > 0 ? (
							<div className="h-80">
								<ResponsiveContainer width="100%" height="100%">
									<BarChart
										data={chartData}
										margin={{ top: 10, right: 20, left: 10, bottom: 20 }}
									>
										<CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
										<XAxis
											dataKey="nombre"
											stroke="#6b7280"
											fontSize={12}
										/>
										<YAxis
											stroke="#6b7280"
											fontSize={12}
											tickFormatter={(v) => `$${Number(v).toLocaleString("es", { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`}
										/>
										<Tooltip
											contentStyle={{
												backgroundColor: "#fff",
												border: "1px solid #e5e7eb",
												borderRadius: "8px",
												boxShadow: "0 4px 6px -1px rgba(0,0,0,0.1)",
											}}
											formatter={(value) => [
												`$${Number(value).toLocaleString("es", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
												"Monto",
											]}
										/>
										<Bar
											dataKey="monto"
											radius={[4, 4, 0, 0]}
											label={{ position: "top", fontSize: 11 }}
										>
											{chartData.map((entry, index) => (
												<Cell key={entry.nombre} fill={entry.fill} />
											))}
										</Bar>
									</BarChart>
								</ResponsiveContainer>
							</div>
						) : (
							<p className="text-sm text-gray-500 py-8 text-center">
								No hay montos por método de pago para mostrar.
							</p>
						)}
					</div>

					{/* Gráfico circular opcional */}
					{chartData.length > 0 && (
						<div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
							<h3 className="text-sm font-semibold text-gray-700 mb-3">
								Distribución por método
							</h3>
							<div className="h-72">
								<ResponsiveContainer width="100%" height="100%">
									<PieChart>
										<Pie
											data={chartData}
											dataKey="monto"
											nameKey="nombre"
											cx="50%"
											cy="50%"
											outerRadius={100}
											label={({ nombre, monto }) =>
												`${nombre}: $${Number(monto).toLocaleString("es", { maximumFractionDigits: 0 })}`
											}
										>
											{chartData.map((entry, index) => (
												<Cell key={entry.nombre} fill={entry.fill} />
											))}
										</Pie>
										<Tooltip
											formatter={(value) =>
												`$${Number(value).toLocaleString("es", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
											}
										/>
										<Legend />
									</PieChart>
								</ResponsiveContainer>
							</div>
						</div>
					)}
				</>
			)}

			{/* Vista detallada: tabla por cierre — HISTORIAL-DETALLADO 2026-06-12:
			    digital vs real por método, cuadre, y dejar en caja en las 3 monedas */}
			{data && data.cierres && data.cierres.length > 0 && !vistaResumen && (
				<div className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
					<div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between flex-wrap gap-2">
						<h3 className="text-lg font-semibold text-gray-800">
							Detalle por cierre ({data.cierres.length} registro(s))
						</h3>
						<span className="text-xs text-gray-500">
							<span className="font-semibold text-gray-600">Dig</span> = registrado por el sistema ·{" "}
							<span className="font-semibold text-gray-600">Real</span> = conciliado en el cierre
						</span>
					</div>
					<div className="overflow-x-auto">
						<table className="min-w-full divide-y divide-gray-200 text-xs">
							<thead className="bg-gray-50">
								{/* Fila de grupos */}
								<tr>
									<th className="px-2 py-1.5 text-left font-medium text-gray-500 uppercase border-r border-gray-200" rowSpan={2}>
										Usuario
									</th>
									<th className="px-2 py-1.5 text-left font-medium text-gray-500 uppercase border-r border-gray-200" rowSpan={2}>
										Fecha
									</th>
									<th className="px-2 py-1.5 text-right font-medium text-gray-500 uppercase border-r border-gray-200" rowSpan={2}>
										Nº
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-orange-700 uppercase border-r border-gray-200 bg-orange-50" colSpan={2}>
										Efectivo
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-blue-700 uppercase border-r border-gray-200 bg-blue-50" colSpan={2}>
										Débito
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-purple-700 uppercase border-r border-gray-200 bg-purple-50" colSpan={2}>
										Transferencia
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-teal-700 uppercase border-r border-gray-200 bg-teal-50" colSpan={2}>
										Biopago
									</th>
									<th className="px-2 py-1.5 text-right font-medium text-red-700 uppercase border-r border-gray-200 bg-red-50" rowSpan={2}>
										Cuadre
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-gray-600 uppercase border-r border-gray-200 bg-gray-100" colSpan={3}>
										Dejar en caja
									</th>
									<th className="px-2 py-1.5 text-right font-medium text-gray-500 uppercase border-r border-gray-200" rowSpan={2}>
										Total
									</th>
									<th className="px-2 py-1.5 text-center font-medium text-gray-500 uppercase" rowSpan={2}>
										Acción
									</th>
								</tr>
								{/* Fila de sub-columnas */}
								<tr>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-orange-50">Dig</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 border-r border-gray-200 bg-orange-50">Real</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-blue-50">Dig</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 border-r border-gray-200 bg-blue-50">Real</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-purple-50">Dig</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 border-r border-gray-200 bg-purple-50">Real</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-teal-50">Dig</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 border-r border-gray-200 bg-teal-50">Real</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-gray-100">USD</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 bg-gray-100">Bs</th>
									<th className="px-2 py-1 text-right font-normal text-gray-400 border-r border-gray-200 bg-gray-100">COP</th>
								</tr>
							</thead>
							<tbody className="bg-white divide-y divide-gray-200">
								{data.cierres.map((c) => {
									const descuadre = parseNum(c.descuadre);
									const cuadraOk = Math.abs(descuadre) < 0.01;
									const agr = agruparMetodos(c.metodos_pago || c.metodosPago);
									// Renderiza una celda Dig o Real de un tipo con sus monedas apiladas.
									const celda = (tipo, campo, extraClass = "") => {
										const lineas = lineasDeTipo(agr, tipo);
										return (
											<td className={`px-2 py-1.5 text-right whitespace-nowrap ${extraClass}`}>
												{lineas.length === 0 ? (
													<span className="text-gray-300">—</span>
												) : (
													lineas.map((l) => (
														<div key={l.moneda}>{fmtMoneda(l[campo], l.moneda)}</div>
													))
												)}
											</td>
										);
									};
									return (
										<tr key={c.id} className="hover:bg-gray-50">
											<td className="px-2 py-1.5 text-gray-800 border-r border-gray-100 align-top">
												{c.usuario?.usuario ?? c.id_usuario ?? "—"}
											</td>
											<td className="px-2 py-1.5 text-gray-600 border-r border-gray-100 whitespace-nowrap align-top">{c.fecha}</td>
											<td className="px-2 py-1.5 text-right text-gray-800 border-r border-gray-100 align-top">
												{c.numventas ?? 0}
											</td>
											{/* Efectivo (multimoneda) */}
											{celda(TIPO_PAGO.EFECTIVO, "dig", "text-gray-500 align-top")}
											{celda(TIPO_PAGO.EFECTIVO, "real", "text-gray-800 border-r border-gray-100 align-top")}
											{/* Débito (Bs, pinpad + otros) */}
											{celda(TIPO_PAGO.DEBITO, "dig", "text-gray-500 align-top")}
											{celda(TIPO_PAGO.DEBITO, "real", "text-gray-800 border-r border-gray-100 align-top")}
											{/* Transferencia */}
											{celda(TIPO_PAGO.TRANSFERENCIA, "dig", "text-gray-500 align-top")}
											{celda(TIPO_PAGO.TRANSFERENCIA, "real", "text-gray-800 border-r border-gray-100 align-top")}
											{/* Biopago */}
											{celda(TIPO_PAGO.BIOPAGO, "dig", "text-gray-500 align-top")}
											{celda(TIPO_PAGO.BIOPAGO, "real", "text-gray-800 border-r border-gray-100 align-top")}
											{/* Cuadre (USD, sigue siendo el descuadre consolidado) */}
											<td className={`px-2 py-1.5 text-right font-medium border-r border-gray-100 align-top ${cuadraOk ? "text-green-600" : "text-red-600"}`}>
												{c.descuadre ?? "0.00"}
											</td>
											{/* Dejar en caja 3 monedas */}
											<td className="px-2 py-1.5 text-right text-gray-700 align-top">{c.dejar_dolar ?? "0.00"}</td>
											<td className="px-2 py-1.5 text-right text-gray-700 align-top">{c.dejar_bss ?? "0.00"}</td>
											<td className="px-2 py-1.5 text-right text-gray-700 border-r border-gray-100 align-top">{c.dejar_peso ?? "0.00"}</td>
											{/* Total */}
											<td className="px-2 py-1.5 text-right font-medium text-gray-800 border-r border-gray-100 align-top">
												{c.precio ?? "0.00"}
											</td>
											{/* Acción */}
											<td className="px-2 py-1.5 text-center align-top">
												<button
													type="button"
													onClick={() => verReporteCierre(c)}
													className="px-2 py-1 font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded hover:bg-orange-100 whitespace-nowrap"
												>
													Ver
												</button>
											</td>
										</tr>
									);
								})}
							</tbody>
							<tfoot className="bg-gray-100 font-medium">
								<tr>
									{(() => {
										// Celda total de un tipo/campo en moneda original (apilada por moneda)
										const celdaTotal = (tipo, campo, extraClass = "") => {
											const lineas = lineasDeTipo(totalMetodos, tipo);
											return (
												<td className={`px-2 py-1.5 text-right whitespace-nowrap align-top ${extraClass}`}>
													{lineas.length === 0 ? (
														<span className="text-gray-300">—</span>
													) : (
														lineas.map((l) => (
															<div key={l.moneda}>{fmtMoneda(l[campo], l.moneda)}</div>
														))
													)}
												</td>
											);
										};
										return (
											<>
												<td className="px-2 py-1.5 border-r border-gray-200 align-top" colSpan={2}>
													Total
												</td>
												<td className="px-2 py-1.5 text-right border-r border-gray-200 align-top">{data.numventas}</td>
												{/* Efectivo */}
												{celdaTotal(TIPO_PAGO.EFECTIVO, "dig", "text-gray-500")}
												{celdaTotal(TIPO_PAGO.EFECTIVO, "real", "border-r border-gray-200")}
												{/* Débito */}
												{celdaTotal(TIPO_PAGO.DEBITO, "dig", "text-gray-500")}
												{celdaTotal(TIPO_PAGO.DEBITO, "real", "border-r border-gray-200")}
												{/* Transferencia */}
												{celdaTotal(TIPO_PAGO.TRANSFERENCIA, "dig", "text-gray-500")}
												{celdaTotal(TIPO_PAGO.TRANSFERENCIA, "real", "border-r border-gray-200")}
												{/* Biopago */}
												{celdaTotal(TIPO_PAGO.BIOPAGO, "dig", "text-gray-500")}
												{celdaTotal(TIPO_PAGO.BIOPAGO, "real", "border-r border-gray-200")}
												{/* Cuadre */}
												<td className="px-2 py-1.5 text-right border-r border-gray-200 align-top">{data.descuadre}</td>
												{/* Dejar 3 monedas */}
												<td className="px-2 py-1.5 text-right align-top">{data.dejar_dolar}</td>
												<td className="px-2 py-1.5 text-right align-top">{data.dejar_bss}</td>
												<td className="px-2 py-1.5 text-right border-r border-gray-200 align-top">{data.dejar_peso}</td>
												{/* Total */}
												<td className="px-2 py-1.5 text-right border-r border-gray-200 align-top">{data.precio}</td>
												<td className="px-2 py-1.5" />
											</>
										);
									})()}
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			)}

			{data && data.cierres && data.cierres.length === 0 && !loading && (
				<div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center text-amber-800">
					No se encontraron cierres con los filtros indicados.
				</div>
			)}
		</div>
	);
}
