import { useState } from 'react';

function LotesPinpad({ lotesPinpad, bancos, onChangeBanco, totalizarcierre, bloqueado }) {
    const [modalDetalle, setModalDetalle] = useState(null);

    const abrirDetalle = (lote) => {
        setModalDetalle(lote);
    };

    const cerrarDetalle = () => {
        setModalDetalle(null);
    };

    if (!lotesPinpad || lotesPinpad.length === 0) {
        return null;
    }

    return (
        <>
            <div className="row mb-2">
                <div className="col-2 text-success text-right">Lotes Pinpad</div>
                <div className="col align-middle text-center">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>TERMINAL</th>
                                <th>MONTO BS</th>
                                <th>MONTO USD</th>
                                <th>CANT.</th>
                                <th>BANCO</th>
                                <th>DETALLE</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lotesPinpad.map((lote, i) => (
                                <tr key={i} className={!lote.banco ? "table-warning" : ""}>
                                    <td>
                                        <input 
                                            type="text" 
                                            className="form-control" 
                                            value={lote.terminal} 
                                            disabled 
                                        />
                                    </td>
                                    <td>
                                        <input 
                                            type="text" 
                                            className="form-control" 
                                            value={lote.monto_bs.toFixed(2)} 
                                            disabled 
                                        />
                                    </td>
                                    <td>
                                        <input 
                                            type="text" 
                                            className="form-control" 
                                            value={lote.monto_usd.toFixed(2)} 
                                            disabled 
                                        />
                                    </td>
                                    <td>
                                        <input 
                                            type="text" 
                                            className="form-control" 
                                            value={lote.cantidad_transacciones} 
                                            disabled 
                                        />
                                    </td>
                                    <td>
                                        <select 
                                            className={`form-control ${!lote.banco ? 'border-danger' : ''}`}
                                            value={lote.banco || ''}
                                            onChange={(e) => onChangeBanco(i, e.target.value)}
                                            disabled={totalizarcierre || bloqueado}
                                        >
                                            {bancos && bancos.filter(b => b.value !== "0134").map((banco, idx) => (
                                                <option key={idx} value={banco.value}>{banco.text}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td>
                                        <button 
                                            type="button"
                                            className="btn btn-sm btn-info"
                                            onClick={() => abrirDetalle(lote)}
                                        >
                                            <i className="fa fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {lotesPinpad.some(lote => !lote.banco) && (
                        <div className="alert alert-warning mt-2" role="alert">
                            <i className="fa fa-exclamation-triangle"></i> Debe seleccionar un banco para todos los lotes Pinpad antes de guardar el cierre.
                        </div>
                    )}
                </div>
            </div>

            {/* Modal de detalle */}
            {modalDetalle && (
                <div className="modal fade show" style={{ display: 'block', background: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Detalle de Transacciones - Terminal {modalDetalle.terminal}</h5>
                                <button type="button" className="btn-close" onClick={cerrarDetalle}></button>
                            </div>
                            <div className="modal-body" style={{ maxHeight: '500px', overflowY: 'auto' }}>
                                {modalDetalle.transacciones.map((t, idx) => (
                                    <div key={idx} className="mb-2 p-2 border-bottom">
                                        <strong>Transacción {idx + 1}</strong><br/>
                                        <small>Pedido: #{t.id_pedido}</small><br/>
                                        Monto Bs: <strong>{t.monto_original.toFixed(2)}</strong><br/>
                                        Monto USD: <strong>${t.monto.toFixed(2)}</strong><br/>
                                        Referencia: <strong>{t.referencia}</strong><br/>
                                        Estado: <span className="badge bg-success">{t.estado}</span><br/>
                                        Lote POS: {t.lote || 'N/A'}
                                    </div>
                                ))}
                            </div>
                            <div className="modal-footer">
                                <button type="button" className="btn btn-secondary" onClick={cerrarDetalle}>Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export default LotesPinpad;
