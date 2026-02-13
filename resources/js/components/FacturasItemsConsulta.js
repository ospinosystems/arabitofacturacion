import React, { useState, useEffect } from "react";

/**
 * Módulo compacto para buscar y ver facturas con sus ítems:
 * número factura, código, descripción, cantidades, precios.
 * Usa el modelo facturas y items (items_factura + producto).
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

  useEffect(() => {
    if (facturas && facturas.length === 0) {
      getFacturas(false);
    }
  }, []);

  const handleBuscar = () => {
    getFacturas(false);
  };

  const toggleExpand = (id) => {
    setexpandidoId((prev) => (prev === id ? null : id));
  };

  const formatNum = (n) =>
    n != null && !isNaN(n) ? Number(n).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "-";

  return (
    <div className="container-fluid py-2">
      <div className="card shadow-sm">
        <div className="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
          <h6 className="mb-0">Facturas e ítems</h6>
          <div className="d-flex flex-wrap align-items-center gap-2 ms-auto">
            <input
              type="text"
              className="form-control form-control-sm"
              style={{ maxWidth: "200px" }}
              placeholder="Buscar por número o descripción"
              value={factqBuscar || ""}
              onChange={(e) => setfactqBuscar(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleBuscar()}
            />
            {factqBuscarDate !== undefined && (
              <input
                type="date"
                className="form-control form-control-sm"
                style={{ maxWidth: "140px" }}
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
          {!facturas || facturas.length === 0 ? (
            <p className="text-muted small mb-0">Sin facturas. Use Buscar para cargar.</p>
          ) : (
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th style={{ width: "40px" }}></th>
                    <th>Nº Factura</th>
                    <th>Descripción</th>
                    <th>Proveedor</th>
                    <th className="text-end">Monto</th>
                    <th>Fecha emisión</th>
                  </tr>
                </thead>
                <tbody>
                  {facturas.map((f) => (
                    <React.Fragment key={f.id}>
                      <tr
                        className={expandidoId === f.id ? "table-primary" : ""}
                        style={{ cursor: "pointer" }}
                        onClick={() => toggleExpand(f.id)}
                      >
                        <td>
                          <i
                            className={`fas fa-chevron-${expandidoId === f.id ? "down" : "right"} small`}
                          />
                        </td>
                        <td>{f.numfact || "-"}</td>
                        <td>{f.descripcion || "-"}</td>
                        <td>{f.proveedor ? f.proveedor.descripcion : "-"}</td>
                        <td className="text-end">{formatNum(f.monto)}</td>
                        <td>{f.fechaemision || "-"}</td>
                      </tr>
                      {expandidoId === f.id && f.items && f.items.length > 0 && (
                        <tr>
                          <td colSpan={6} className="p-0 bg-light">
                            <table className="table table-sm table-bordered mb-0 small">
                              <thead>
                                <tr>
                                  <th>Código</th>
                                  <th>Descripción</th>
                                  <th className="text-end">Cant.</th>
                                  <th className="text-end">Precio unit.</th>
                                  <th className="text-end">Subtotal</th>
                                </tr>
                              </thead>
                              <tbody>
                                {f.items.map((it) => (
                                  <tr key={it.id}>
                                    <td>{it.producto ? it.producto.barras : "-"}</td>
                                    <td>{it.producto ? it.producto.descripcion : "-"}</td>
                                    <td className="text-end">{it.cantidad ?? "-"}</td>
                                    <td className="text-end">
                                      {formatNum(it.producto ? it.producto.precio3 : null)}
                                    </td>
                                    <td className="text-end">
                                      {formatNum(
                                        it.basefact != null
                                          ? it.basefact
                                          : it.producto && it.cantidad
                                          ? it.producto.precio3 * it.cantidad
                                          : null
                                      )}
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </td>
                        </tr>
                      )}
                      {expandidoId === f.id && (!f.items || f.items.length === 0) && (
                        <tr>
                          <td colSpan={6} className="text-muted small">
                            Sin ítems
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))}
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
