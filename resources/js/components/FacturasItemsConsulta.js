import React, { useState, useEffect, useMemo } from "react";

/**
 * Consulta de facturas (recibidas/aceptadas) con sus ítems: número, proveedor, monto,
 * fecha, estatus y el detalle de cada ítem (código, descripción, cantidad, precio base,
 * precio venta, subtotal). Busca/filtra en el backend (botón Buscar) y también en vivo
 * sobre lo ya cargado.
 */
function FacturasItemsConsulta({
  getFacturas,
  facturas,
  factqBuscar,
  setfactqBuscar,
  factqBuscarDate,
  setfactqBuscarDate,
}) {
  const [expandidoId, setexpandidoId] = useState(null);

  // El backend devuelve un array; si por algún motivo llega otra cosa (error, paginado),
  // lo normalizamos para no romper con "facturas.map is not a function".
  const lista = useMemo(() => {
    if (Array.isArray(facturas)) return facturas;
    if (facturas && Array.isArray(facturas.data)) return facturas.data;
    if (facturas && Array.isArray(facturas.facturas)) return facturas.facturas;
    return [];
  }, [facturas]);

  useEffect(() => {
    if (lista.length === 0) getFacturas(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleBuscar = () => getFacturas(false);
  const toggleExpand = (id) => setexpandidoId((prev) => (prev === id ? null : id));

  const formatNum = (n) =>
    n != null && !isNaN(n)
      ? Number(n).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      : "-";

  const fechaFact = (f) => (f.fechaemision || f.created_at || "").toString().substring(0, 10) || "-";
  const estatusFact = (f) =>
    f.estatus == 1 ? { txt: "Aceptada", cls: "bg-success" } : { txt: "Pendiente", cls: "bg-secondary" };

  // Filtro en vivo sobre lo cargado (número, descripción, proveedor, código/desc de ítems).
  const filtradas = useMemo(() => {
    const q = (factqBuscar || "").trim().toLowerCase();
    if (!q) return lista;
    return lista.filter((f) => {
      const enCabecera =
        (f.numfact || "").toString().toLowerCase().includes(q) ||
        (f.descripcion || "").toLowerCase().includes(q) ||
        (f.proveedor && (f.proveedor.descripcion || "").toLowerCase().includes(q));
      const enItems =
        Array.isArray(f.items) &&
        f.items.some(
          (it) =>
            it.producto &&
            (((it.producto.codigo_barras || "").toLowerCase().includes(q)) ||
              ((it.producto.descripcion || "").toLowerCase().includes(q)))
        );
      return enCabecera || enItems;
    });
  }, [lista, factqBuscar]);

  const totalMonto = filtradas.reduce((s, f) => s + (parseFloat(f.monto) || 0), 0);

  return (
    <div className="container-fluid py-2">
      <div className="card shadow-sm">
        <div className="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
          <h6 className="mb-0">
            <i className="fas fa-file-invoice me-1"></i>Facturas e ítems
          </h6>
          <span className="badge bg-light text-dark">{filtradas.length} factura(s)</span>
          <span className="badge bg-info text-dark">Total: {formatNum(totalMonto)}</span>
          <div className="d-flex flex-wrap align-items-center gap-2 ms-auto">
            <input
              type="text"
              className="form-control form-control-sm"
              style={{ maxWidth: "240px" }}
              placeholder="Nº, descripción, proveedor o producto…"
              value={factqBuscar || ""}
              onChange={(e) => setfactqBuscar(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleBuscar()}
            />
            {factqBuscarDate !== undefined && (
              <input
                type="date"
                className="form-control form-control-sm"
                style={{ maxWidth: "150px" }}
                value={factqBuscarDate || ""}
                onChange={(e) => setfactqBuscarDate(e.target.value)}
              />
            )}
            <button type="button" className="btn btn-sm btn-primary" onClick={handleBuscar}>
              <i className="fas fa-search me-1"></i> Buscar
            </button>
          </div>
        </div>

        <div className="card-body p-2 overflow-auto" style={{ maxHeight: "70vh" }}>
          {filtradas.length === 0 ? (
            <p className="mb-0 text-muted small">
              Sin facturas para mostrar. Usá <b>Buscar</b> para cargar o ajustá el filtro.
            </p>
          ) : (
            <div className="table-responsive">
              <table className="table table-sm table-hover align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th style={{ width: "32px" }}></th>
                    <th>Nº Factura</th>
                    <th>Proveedor</th>
                    <th>Descripción</th>
                    <th className="text-center">Ítems</th>
                    <th className="text-end">Monto</th>
                    <th>Fecha</th>
                    <th className="text-center">Estatus</th>
                  </tr>
                </thead>
                <tbody>
                  {filtradas.map((f) => {
                    const items = Array.isArray(f.items) ? f.items : [];
                    const est = estatusFact(f);
                    const abierto = expandidoId === f.id;
                    return (
                      <React.Fragment key={f.id}>
                        <tr
                          className={abierto ? "table-primary" : ""}
                          style={{ cursor: "pointer" }}
                          onClick={() => toggleExpand(f.id)}
                        >
                          <td>
                            <i className={`fas fa-chevron-${abierto ? "down" : "right"} small`} />
                          </td>
                          <td className="fw-semibold">{f.numfact || "-"}</td>
                          <td>{f.proveedor ? f.proveedor.descripcion : "-"}</td>
                          <td className="text-truncate" style={{ maxWidth: "240px" }} title={f.descripcion}>
                            {f.descripcion || "-"}
                          </td>
                          <td className="text-center">{items.length}</td>
                          <td className="text-end">{formatNum(f.monto)}</td>
                          <td>{fechaFact(f)}</td>
                          <td className="text-center">
                            <span className={`badge ${est.cls}`}>{est.txt}</span>
                          </td>
                        </tr>

                        {abierto && (
                          <tr>
                            <td colSpan={8} className="p-0 bg-light">
                              {items.length === 0 ? (
                                <div className="p-2 text-muted small">Sin ítems en esta factura.</div>
                              ) : (
                                <table className="table table-sm table-bordered mb-0 small">
                                  <thead className="table-secondary">
                                    <tr>
                                      <th>Código</th>
                                      <th>Descripción</th>
                                      <th className="text-end">Cant.</th>
                                      <th className="text-end">Precio base</th>
                                      <th className="text-end">Precio venta</th>
                                      <th className="text-end">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {items.map((it) => {
                                      const p = it.producto || {};
                                      const subtotal =
                                        it.subtotal_base_clean != null
                                          ? it.subtotal_base_clean
                                          : (parseFloat(p.precio_base) || 0) * (parseFloat(it.cantidad) || 0);
                                      return (
                                        <tr key={it.id}>
                                          <td className="font-monospace">{p.codigo_barras || "-"}</td>
                                          <td>{p.descripcion || "—"}</td>
                                          <td className="text-end">{it.cantidad ?? "-"}</td>
                                          <td className="text-end">{formatNum(p.precio_base)}</td>
                                          <td className="text-end">{formatNum(p.precio)}</td>
                                          <td className="text-end fw-semibold">{formatNum(subtotal)}</td>
                                        </tr>
                                      );
                                    })}
                                  </tbody>
                                </table>
                              )}
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default FacturasItemsConsulta;
