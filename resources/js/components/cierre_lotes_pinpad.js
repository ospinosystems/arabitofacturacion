import { useState, useMemo } from 'react';

/**
 * Lotes Pinpad (POS débito) en el cierre.
 *
 * FIX 2026-05-26 — Cada transacción es un lote independiente (antes se agrupaban
 * por terminal). El banco viene autodetectado del campo `bank` que retornó el
 * dispositivo POS; central hace el mapeo final a banco_id. El cajero ya no elige
 * banco a mano.
 *
 * Por la cantidad de transacciones (cientos por día), la vista vive COLAPSADA por
 * defecto y solo muestra un resumen: total trans, monto total y desglose por banco
 * detectado. Click en "Ver detalle" expande la lista completa.
 */
function LotesPinpad({ lotesPinpad, totalizarcierre, bloqueado }) {
    const [expandido, setExpandido] = useState(false);

    if (!lotesPinpad || lotesPinpad.length === 0) {
        return null;
    }

    // Resumen agregado: totales + desglose por banco detectado del PINPAD.
    const resumen = useMemo(() => {
        const totalBs = lotesPinpad.reduce((s, l) => s + parseFloat(l.monto_bs || 0), 0);
        const totalUsd = lotesPinpad.reduce((s, l) => s + parseFloat(l.monto_usd || 0), 0);
        const porBanco = new Map();
        lotesPinpad.forEach(l => {
            const banco = (l.banco && String(l.banco).trim()) || 'sin banco';
            if (!porBanco.has(banco)) porBanco.set(banco, { cantidad: 0, monto_bs: 0 });
            const acc = porBanco.get(banco);
            acc.cantidad += 1;
            acc.monto_bs += parseFloat(l.monto_bs || 0);
        });
        const sinBanco = lotesPinpad.filter(l => !l.banco || !String(l.banco).trim()).length;
        return {
            cantidad: lotesPinpad.length,
            totalBs,
            totalUsd,
            porBanco: Array.from(porBanco.entries()).map(([banco, info]) => ({ banco, ...info })),
            sinBanco,
        };
    }, [lotesPinpad]);

    return (
        <>
            <div className="row mb-2">
                <div className="col-2 text-success text-right">
                    Lotes Pinpad
                </div>
                <div className="col align-middle">
                    <div className="card">
                        <div className="card-body p-2">
                            <div className="d-flex justify-content-between align-items-center flex-wrap">
                                <div className="d-flex gap-3 align-items-center flex-wrap">
                                    <span className="badge bg-primary" style={{ fontSize: '0.9rem' }}>
                                        {resumen.cantidad} {resumen.cantidad === 1 ? 'transacción' : 'transacciones'}
                                    </span>
                                    <span><b>Total Bs:</b> {resumen.totalBs.toFixed(2)}</span>
                                    <span><b>Total USD:</b> {resumen.totalUsd.toFixed(2)}</span>
                                    {resumen.porBanco.map(b => (
                                        <span key={b.banco} className={`badge ${b.banco === 'sin banco' ? 'bg-warning text-dark' : 'bg-info'}`} style={{ fontSize: '0.85rem' }}>
                                            {b.banco}: {b.cantidad} · Bs {b.monto_bs.toFixed(2)}
                                        </span>
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => setExpandido(v => !v)}
                                    title={expandido ? 'Colapsar' : 'Ver todas las transacciones'}
                                >
                                    <i className={`fa fa-chevron-${expandido ? 'up' : 'down'} mr-1`}></i>
                                    {expandido ? 'Colapsar' : 'Ver detalle'}
                                </button>
                            </div>

                            {resumen.sinBanco > 0 && (
                                <div className="alert alert-warning mt-2 mb-0 py-1 small" role="alert">
                                    <i className="fa fa-exclamation-triangle"></i>
                                    {' '}Hay {resumen.sinBanco} transacción(es) sin banco detectado por el POS. Central las recibirá igual pero pueden quedar sin mapear.
                                </div>
                            )}

                            {expandido && (
                                <div className="mt-3" style={{ maxHeight: '400px', overflowY: 'auto' }}>
                                    <table className="table table-sm table-hover mb-0">
                                        <thead className="sticky-top bg-light">
                                            <tr>
                                                <th>#Pago</th>
                                                <th>Terminal</th>
                                                <th>Pedido</th>
                                                <th>Referencia</th>
                                                <th className="text-right">Monto Bs</th>
                                                <th className="text-right">Monto USD</th>
                                                <th>Banco POS</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lotesPinpad.map((lote, i) => {
                                                const t = (lote.transacciones && lote.transacciones[0]) || {};
                                                return (
                                                    <tr key={i} className={!lote.banco ? 'table-warning' : ''}>
                                                        <td>{t.id || lote.pago_id || '—'}</td>
                                                        <td className="font-monospace">{lote.terminal}</td>
                                                        <td>{t.id_pedido || '—'}</td>
                                                        <td className="font-monospace">{t.referencia || '—'}</td>
                                                        <td className="text-right">{parseFloat(lote.monto_bs || 0).toFixed(2)}</td>
                                                        <td className="text-right">{parseFloat(lote.monto_usd || 0).toFixed(2)}</td>
                                                        <td>{lote.banco || <span className="text-muted">—</span>}</td>
                                                        <td>{t.estado || '—'}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

export default LotesPinpad;
