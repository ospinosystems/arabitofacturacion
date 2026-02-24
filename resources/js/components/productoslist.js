import React from "react"

function ProductosList({
  auth,
  productos,
  addCarrito,
  clickSetOrderColumn,
  orderColumn,
  orderBy,
  counterListProductos,
  tbodyproductosref,
  selectProductoFast,
  moneda,
  user
}) {
  return (
    <>
      {/* Versión Desktop - compacta con columnas ancho fijo */}
      <div className="d-none d-lg-block overflow-auto" style={{ maxHeight: 'calc(100vh - 180px)' }}>
        <table className="table table-hover table-sm mb-0" style={{ tableLayout: 'fixed', width: '100%', minWidth: '600px' }}>
          <colgroup>
            <col style={{ width: '85px' }} />
            <col style={{ width: '85px' }} />
            <col style={{ width: '220px' }} />
            <col style={{ width: '50px' }} />
            <col style={{ width: '120px' }} />
          </colgroup>
          <thead className="bg-light border-bottom sticky-top">
            <tr>
              <th className="py-1 px-1 pointer small text-nowrap" data-valor="codigo_proveedor" onClick={clickSetOrderColumn} style={{ overflow: 'hidden', textOverflow: 'ellipsis' }} title="BARRAS">
                BARRAS
                {orderColumn=="codigo_proveedor" && (
                  <i className={`fa fa-arrow-${orderBy=="desc"?"up":"down"} ms-1`}></i>
                )}
              </th>
              <th className="py-1 px-1 pointer small text-nowrap" data-valor="codigo_proveedor" onClick={clickSetOrderColumn} style={{ overflow: 'hidden', textOverflow: 'ellipsis' }} title="ALTERNO">
                ALTERNO
                {orderColumn=="codigo_proveedor" && (
                  <i className={`fa fa-arrow-${orderBy=="desc"?"up":"down"} ms-1`}></i>
                )}
              </th>
              <th className="py-1 px-1 pointer small" data-valor="descripcion" onClick={clickSetOrderColumn} style={{ overflow: 'hidden', textOverflow: 'ellipsis' }} title="DESCRIPCIÓN">
                DESCRIPCIÓN
                {orderColumn=="descripcion" && (
                  <i className={`fa fa-arrow-${orderBy=="desc"?"up":"down"} ms-1`}></i>
                )}
              </th>
              <th className="py-1 px-1 pointer text-center small text-nowrap" data-valor="cantidad" onClick={clickSetOrderColumn} title="CANT">
                CANT
                {orderColumn=="cantidad" && (
                  <i className={`fa fa-arrow-${orderBy=="desc"?"up":"down"} ms-1`}></i>
                )}
              </th>
              <th className="py-1 px-1 pointer small text-nowrap" data-valor="precio" onClick={clickSetOrderColumn} style={{ overflow: 'hidden', textOverflow: 'ellipsis' }} title="PRECIO">
                PRECIO
                {orderColumn=="precio" && (
                  <i className={`fa fa-arrow-${orderBy=="desc"?"up":"down"} ms-1`}></i>
                )}
              </th>
            </tr>
          </thead>
          <tbody ref={tbodyproductosref} className="table-group-divider">
            {productos?.length ? productos.map((e,i) => (
              <tr 
                key={e.id}
                data-index={i} 
                tabIndex="-1" 
                className={`${counterListProductos == i ? "table-active" : ""} align-middle`}
                onClick={(event) => addCarrito(event)}
                style={{ cursor: 'pointer' }}
              >
                <td className="py-1 px-1 small" style={{ overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 0 }}>
                  <span className="fw-bold text-truncate d-block" title={e.codigo_barras}>{e.codigo_barras}</span>
                </td>
                <td className="py-1 px-1 small text-muted" style={{ overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 0 }}>
                  <span className="text-truncate d-block" title={e.codigo_proveedor}>{e.codigo_proveedor}</span>
                </td>
                <td className="py-1 px-1 small" style={{ overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 0 }}>
                  <span className="text-truncate d-block" title={e.descripcion}>{e.descripcion}</span>
                </td>
                <td className="py-1 px-1 text-center">
                  <span className={`badge ${parseFloat(e.cantidad) > 0 ? 'bg-success' : 'bg-danger'} small`}>
                    {e.cantidad.replace(".00", "")}
                  </span>
                </td>
                <td className="py-1 px-1">
                  <div className="d-flex flex-column gap-0">
                    <span className="small fw-bold">{moneda(e.precio)}</span>
                    <div className="d-flex gap-1 flex-wrap">
                      <small className="text-muted">Bs. {moneda(e.bs)}</small>
                      {user.sucursal=="elorza" && (
                        <small className="text-muted">Cop. {moneda(e.cop)}</small>
                      )}
                    </div>
                  </div>
                </td>
              </tr>
            )) : (
              <tr>
                <td colSpan="5" className="text-center py-4 small text-muted">
                  <i className="fas fa-box-open fa-2x mb-2 d-block"></i>
                  No hay productos disponibles
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Versión Móvil */}
      <div className="d-lg-none">
        {productos?.length ? (
          <div className="row g-3">
            {productos.map((e, i) => (
              <div 
                key={e.id}
                data-index={i}
                onClick={addCarrito}
                className="col-12"
              >
                <div className={`card h-100 shadow-sm ${counterListProductos == i ? 'border-dark' : ''}`}>
                  <div className="card-body">
                    <div className="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h6 className="card-subtitle mb-1 text-muted">
                          <small>{e.codigo_barras}</small>
                        </h6>
                        <h6 className="card-subtitle mb-2 text-muted">
                          <small>{e.codigo_proveedor}</small>
                        </h6>
                      </div>
                      <span className={`badge ${parseFloat(e.cantidad) > 0 ? 'bg-success' : 'bg-danger'} fs-6`}>
                        {e.cantidad.replace(".00", "")}
                      </span>
                    </div>
                    
                    <h5 className="card-title mb-3">{e.descripcion}</h5>
                    
                    <div className="d-flex flex-column gap-2">
                      <button className="btn btn-dark w-100">
                        <span className="fs-4 fw-bold">{moneda(e.precio)}</span>
                      </button>
                      <div className="d-flex gap-2">
                        <button className="btn btn-outline-dark flex-grow-1">
                          <small>Bs.</small> {moneda(e.bs)}
                        </button>
                        {user.sucursal=="elorza" && (
                          <button className="btn btn-outline-dark flex-grow-1">
                            <small>Cop.</small> {moneda(e.cop)}
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-5">
            <div className="text-muted">
              <i className="fas fa-box-open fa-3x mb-3"></i>
              <p className="h4">No hay productos disponibles</p>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default ProductosList