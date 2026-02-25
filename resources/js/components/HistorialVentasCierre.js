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

			{/* Vista detallada: tabla por cierre */}
			{data && data.cierres && data.cierres.length > 0 && !vistaResumen && (
				<div className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
					<div className="px-4 py-3 border-b border-gray-200">
						<h3 className="text-lg font-semibold text-gray-800">
							Detalle por cierre ({data.cierres.length} registro(s))
						</h3>
					</div>
					<div className="overflow-x-auto">
						<table className="min-w-full divide-y divide-gray-200">
							<thead className="bg-gray-50">
								<tr>
									<th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
										Usuario
									</th>
									<th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
										Fecha
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Nº ventas
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Efectivo
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Débito
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Transferencia
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Biopago
									</th>
									<th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
										Total
									</th>
								</tr>
							</thead>
							<tbody className="bg-white divide-y divide-gray-200">
								{data.cierres.map((c) => (
									<tr key={c.id} className="hover:bg-gray-50">
										<td className="px-4 py-2 text-sm text-gray-800">
											{c.usuario?.usuario ?? c.id_usuario ?? "—"}
										</td>
										<td className="px-4 py-2 text-sm text-gray-600">{c.fecha}</td>
										<td className="px-4 py-2 text-sm text-right text-gray-800">
											{c.numventas ?? 0}
										</td>
										<td className="px-4 py-2 text-sm text-right text-gray-700">
											{c.efectivo ?? "0.00"}
										</td>
										<td className="px-4 py-2 text-sm text-right text-gray-700">
											{c.debito ?? "0.00"}
										</td>
										<td className="px-4 py-2 text-sm text-right text-gray-700">
											{c.transferencia ?? "0.00"}
										</td>
										<td className="px-4 py-2 text-sm text-right text-gray-700">
											{c.caja_biopago ?? "0.00"}
										</td>
										<td className="px-4 py-2 text-sm text-right font-medium text-gray-800">
											{c.precio ?? "0.00"}
										</td>
									</tr>
								))}
							</tbody>
							<tfoot className="bg-gray-100 font-medium">
								<tr>
									<td className="px-4 py-2 text-sm" colSpan={2}>
										Total
									</td>
									<td className="px-4 py-2 text-sm text-right">{data.numventas}</td>
									<td className="px-4 py-2 text-sm text-right">{data.efectivo}</td>
									<td className="px-4 py-2 text-sm text-right">{data.debito}</td>
									<td className="px-4 py-2 text-sm text-right">
										{data.transferencia}
									</td>
									<td className="px-4 py-2 text-sm text-right">
										{data.caja_biopago}
									</td>
									<td className="px-4 py-2 text-sm text-right">{data.precio}</td>
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
