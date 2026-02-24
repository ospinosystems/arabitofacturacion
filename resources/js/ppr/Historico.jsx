import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { usePprKeyboard } from './PprKeyboardContext';

function formatFecha(str) {
  if (!str) return '—';
  try {
    const d = new Date(str);
    return d.toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch {
    return str;
  }
}

function dateDigitsToIso(digits) {
  const d = (digits || '').replace(/\D/g, '').slice(0, 8);
  if (!d.length) return '';
  const y = d.slice(0, 4) || '2020';
  const m = (d.length >= 6 ? d.slice(4, 6) : '01').padStart(2, '0');
  const day = (d.length >= 8 ? d.slice(6, 8) : '01').padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function isoToDateDigits(iso) {
  return (iso || '').replace(/-/g, '');
}

export default function Historico() {
  const [data, setData] = useState({ por_pedido: [] });
  const [loading, setLoading] = useState(true);
  const [expandidoId, setExpandidoId] = useState(null);
  const [fechaDesde, setFechaDesde] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - 7);
    return d.toISOString().slice(0, 10);
  });
  const [fechaHasta, setFechaHasta] = useState(() => new Date().toISOString().slice(0, 10));
  const [idPedidoFilter, setIdPedidoFilter] = useState('');
  const { register, unregister, activeInput, updateValue } = usePprKeyboard();

  useEffect(() => {
    if (!activeInput) return;
    if (activeInput.id === 'historico-fecha-desde') updateValue(isoToDateDigits(fechaDesde));
    else if (activeInput.id === 'historico-fecha-hasta') updateValue(isoToDateDigits(fechaHasta));
    else if (activeInput.id === 'historico-id-pedido') updateValue(idPedidoFilter);
  }, [activeInput?.id, fechaDesde, fechaHasta, idPedidoFilter, updateValue]);

  const fetchHistorico = () => {
    setLoading(true);
    setExpandidoId(null);
    const params = new URLSearchParams({ fecha_desde: fechaDesde, fecha_hasta: fechaHasta });
    if (idPedidoFilter.trim()) params.set('id_pedido', idPedidoFilter.trim());
    axios.get(`/ppr/historico?${params}`)
      .then((res) => {
        if (res.data && res.data.estado) {
          setData({ por_pedido: res.data.por_pedido || [] });
        }
      })
      .catch(() => setData({ por_pedido: [] }))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchHistorico();
  }, []);

  const togglePedido = (id) => {
    setExpandidoId((prev) => (prev === id ? null : id));
  };

  return (
    <div className="flex flex-col max-w-md mx-auto p-4 pb-8">
      <div className="flex flex-wrap gap-2 mb-4">
        <input
          type="text"
          inputMode="none"
          readOnly
          value={fechaDesde}
          onFocus={() => register('historico-fecha-desde', {
            type: 'numeric',
            value: isoToDateDigits(fechaDesde),
            onChange: (v) => setFechaDesde(dateDigitsToIso(v)),
            maxLength: 8,
          })}
          onBlur={() => unregister('historico-fecha-desde')}
          placeholder="YYYY-MM-DD"
          className="px-3 py-2 rounded-lg border w-32"
        />
        <input
          type="text"
          inputMode="none"
          readOnly
          value={fechaHasta}
          onFocus={() => register('historico-fecha-hasta', {
            type: 'numeric',
            value: isoToDateDigits(fechaHasta),
            onChange: (v) => setFechaHasta(dateDigitsToIso(v)),
            maxLength: 8,
          })}
          onBlur={() => unregister('historico-fecha-hasta')}
          placeholder="YYYY-MM-DD"
          className="px-3 py-2 rounded-lg border w-32"
        />
        <input
          type="text"
          inputMode="none"
          readOnly
          placeholder="Nº pedido"
          value={idPedidoFilter}
          onFocus={() => register('historico-id-pedido', {
            type: 'numeric',
            value: idPedidoFilter,
            onChange: setIdPedidoFilter,
            maxLength: 10,
          })}
          onBlur={() => unregister('historico-id-pedido')}
          className="px-3 py-2 rounded-lg border w-24"
        />
        <button
          type="button"
          onClick={fetchHistorico}
          disabled={loading}
          className="px-4 py-2 rounded-lg btn-sinapsis font-medium"
        >
          Buscar
        </button>
      </div>

      {loading ? (
        <p className="text-gray-500">Cargando…</p>
      ) : (
        <div className="space-y-2">
          {data.por_pedido.length === 0 ? (
            <p className="text-gray-500">Sin registros en el período.</p>
          ) : (
            data.por_pedido.map((p) => (
              <div key={p.id_pedido} className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <button
                  type="button"
                  onClick={() => togglePedido(p.id_pedido)}
                  className="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 active:bg-gray-100"
                >
                  <div>
                    <span className="font-bold text-gray-900">Pedido #{p.id_pedido}</span>
                    <span className="ml-2 text-sm text-gray-500">{formatFecha(p.fecha)}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                      {p.entregas.length} {p.entregas.length === 1 ? 'entrega' : 'entregas'}
                    </span>
                    <span className={`text-gray-400 transition-transform ${expandidoId === p.id_pedido ? 'rotate-180' : ''}`}>▼</span>
                  </div>
                </button>

                {expandidoId === p.id_pedido && (
                  <div className="border-t border-gray-100 bg-gray-50 p-3 space-y-3">
                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Ítems despachados</p>
                    {p.entregas.map((e, i) => (
                      <div
                        key={`${e.id_item_pedido}-${e.fecha}-${i}`}
                        className="rounded-xl border-2 border-white bg-white p-4 shadow-sm"
                      >
                        <div className="font-medium text-gray-900 mb-2">
                          {e.producto_desc || '—'}
                        </div>
                        <div className="grid grid-cols-1 gap-1.5 text-sm">
                          <div className="font-mono text-gray-700">
                            <span className="text-gray-500 font-normal">Cód. barras:</span> {e.codigo_barras || '—'}
                          </div>
                          <div className="font-mono text-gray-700">
                            <span className="text-gray-500 font-normal">Cód. proveedor:</span> {e.codigo_proveedor || '—'}
                          </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-100 text-sm">
                          <span className="font-semibold text-gray-800">{e.unidades_entregadas} und.</span>
                          <span className="text-gray-500">{formatFecha(e.fecha)}</span>
                          {e.portero && <span className="text-gray-600">({e.portero})</span>}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))
          )}
        </div>
      )}
    </div>
  );
}
