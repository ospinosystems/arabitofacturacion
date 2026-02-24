import React, { useState, useMemo, useEffect, useRef, useCallback } from 'react';
import { playDespachadoAlert, playAlertaPendienteSound } from './soundAlert';
import { usePprKeyboard } from './PprKeyboardContext';

export default function DespacharPedido({ pedido, clienteEsCF, clienteAnclado, yaDespachadoCompleto, fechaUltimoDespacho, onConfirmar, onVolver, loading }) {
  const items = pedido?.items?.filter((i) => i.producto) || [];
  const [entregar, setEntregar] = useState(() => items.map(() => ''));
  const [cf, setCf] = useState({ nombreCompleto: '', cedula: '', telefono: '' });
  const [cfKeyboardTarget, setCfKeyboardTarget] = useState('despachar-cf-nombre');
  const alertPlayedRef = useRef(false);
  const inputRefs = useRef([]);
  const { register, unregister, close, activeInput, updateValue } = usePprKeyboard();

  const idsCF = ['despachar-cf-nombre', 'despachar-cf-cedula', 'despachar-cf-telefono'];
  const abrirTecladoCF = useCallback(() => {
    if (activeInput && idsCF.includes(activeInput.id)) {
      close();
      return;
    }
    if (cfKeyboardTarget === 'despachar-cf-nombre') {
      register('despachar-cf-nombre', {
        type: 'alpha',
        value: cf.nombreCompleto,
        onChange: (v) => setCf((s) => ({ ...s, nombreCompleto: v })),
        maxLength: 120,
      });
    } else if (cfKeyboardTarget === 'despachar-cf-cedula') {
      register('despachar-cf-cedula', {
        type: 'numeric',
        value: cf.cedula,
        onChange: (v) => setCf((s) => ({ ...s, cedula: v.replace(/\D/g, '').slice(0, 15) })),
        maxLength: 15,
      });
    } else {
      register('despachar-cf-telefono', {
        type: 'numeric',
        value: cf.telefono,
        onChange: (v) => setCf((s) => ({ ...s, telefono: v.replace(/\D/g, '').slice(0, 15) })),
        maxLength: 15,
      });
    }
  }, [cfKeyboardTarget, cf.nombreCompleto, cf.cedula, cf.telefono, register, activeInput?.id, close]);

  useEffect(() => {
    if (!activeInput) return;
    const id = activeInput.id;
    if (id.startsWith('despachar-cant-')) {
      const i = parseInt(id.replace('despachar-cant-', ''), 10);
      if (!Number.isNaN(i) && entregar[i] !== undefined) updateValue(entregar[i] ?? '');
    } else if (id === 'despachar-cf-nombre') updateValue(cf.nombreCompleto);
    else if (id === 'despachar-cf-cedula') updateValue(cf.cedula);
    else if (id === 'despachar-cf-telefono') updateValue(cf.telefono);
  }, [activeInput?.id, entregar, cf.nombreCompleto, cf.cedula, cf.telefono, updateValue]);

  const alertaPendienteRef = useRef(false);
  useEffect(() => {
    if (yaDespachadoCompleto && !alertPlayedRef.current) {
      alertPlayedRef.current = true;
      playDespachadoAlert();
    }
  }, [yaDespachadoCompleto]);
  const tieneParcialConPendiente = !yaDespachadoCompleto && items.some((i) => (i.entregado_hasta != null && i.entregado_hasta > 0)) && items.some((i) => (i.pendiente != null ? i.pendiente : parseFloat(i.cantidad, 10)) > 0);
  useEffect(() => {
    if (tieneParcialConPendiente && !alertaPendienteRef.current) {
      alertaPendienteRef.current = true;
      playAlertaPendienteSound();
    }
  }, [tieneParcialConPendiente]);

  const updateEntregar = (index, value) => {
    setEntregar((prev) => {
      const next = [...prev];
      const item = items[index];
      const pend = item?.pendiente != null ? item.pendiente : (item ? parseFloat(item.cantidad, 10) : Infinity);
      const num = parseFloat(value, 10);
      if (value === '' || value === null || value === undefined) {
        next[index] = value;
      } else if (!Number.isNaN(num) && pend !== Infinity && num > pend) {
        next[index] = pend === Math.floor(pend) ? String(Math.floor(pend)) : String(pend);
      } else {
        next[index] = value;
      }
      return next;
    });
  };

  const payload = useMemo(() => {
    const out = [];
    const pendienteVal = (item) => item.pendiente != null ? item.pendiente : parseFloat(item.cantidad, 10);
    items.forEach((item, i) => {
      const pend = pendienteVal(item);
      const u = parseFloat(entregar[i], 10);
      let aEntregar = 0;
      if (u > 0 && pend != null) {
        aEntregar = Math.min(u, pend);
      } else if (items.length === 1 && pend > 0 && (entregar[i] === '' || entregar[i] == null || Number.isNaN(u) || u === 0)) {
        aEntregar = pend;
      }
      if (aEntregar > 0) {
        out.push({ id_item_pedido: item.id, unidades_entregadas: aEntregar });
      }
    });
    return out;
  }, [items, entregar]);

  const retiroTotal = useMemo(() => {
    if (payload.length === 0) return false;
    if (items.length === 1 && payload.length === 1) {
      const pend = items[0].pendiente != null ? items[0].pendiente : parseFloat(items[0].cantidad, 10);
      return pend > 0 && (payload[0].unidades_entregadas >= pend || (parseFloat(entregar[0], 10) || 0) >= pend);
    }
    return items.every((item, i) => {
      const pend = item.pendiente != null ? item.pendiente : parseFloat(item.cantidad, 10);
      if (pend <= 0) return true;
      const u = parseFloat(entregar[i], 10) || 0;
      return u >= pend;
    });
  }, [items, entregar, payload]);

  const exigeDatosCF = clienteEsCF && payload.length > 0 && !retiroTotal && !clienteAnclado;
  const canConfirm = !yaDespachadoCompleto && payload.length > 0 && (!clienteEsCF || retiroTotal || clienteAnclado || (cf.nombreCompleto.trim() && cf.cedula.trim() && cf.telefono.trim()));
  const usuarioPedido = pedido?.vendedor?.nombre || pedido?.vendedor?.usuario || '—';
  const horaPedido = pedido?.created_at
    ? new Date(pedido.created_at).toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';

  const handleConfirmar = () => {
    if (!canConfirm) return;
    onConfirmar({
      id_pedido: pedido.id,
      items: payload,
      retiro_total: retiroTotal,
      ...(exigeDatosCF ? { nombre: cf.nombreCompleto, apellido: cf.nombreCompleto || '', cedula: cf.cedula, telefono: cf.telefono } : {}),
    });
  };

  return (
    <div className="flex flex-col h-full max-w-md mx-auto pb-4">
      <div className="flex items-center justify-between p-3 border-b bg-white sticky top-0 z-10">
        <button
          type="button"
          onClick={onVolver}
          className="px-3 py-2 text-gray-600 font-medium"
        >
          ← Volver
        </button>
        <span className="font-semibold text-gray-800">Pedido #{pedido.id}</span>
        <span className="w-12" />
      </div>
      <div className="px-3 py-2 bg-gray-50 border-b text-sm text-gray-600">
        <span>Usuario: <strong>{usuarioPedido}</strong></span>
        <span className="mx-2">·</span>
        <span>Hora pedido: <strong>{horaPedido}</strong></span>
      </div>
      {!yaDespachadoCompleto && items.some((i) => i.entregado_hasta != null && i.entregado_hasta > 0) && (
        <div className="mx-3 mt-2 p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium flex-shrink-0">
          Este pedido tiene entregas parciales. Abajo se muestra lo ya entregado y lo pendiente por ítem.
        </div>
      )}
      {/* Mensaje fijo arriba: ya despachado o instrucción de despacho */}
      {yaDespachadoCompleto ? (
        <div className="flex-shrink-0 p-3 border-b border-red-200 bg-red-50">
          <div className="flex flex-col items-center text-center">
            <div className="w-16 h-16 rounded-full bg-red-500 flex items-center justify-center shadow-lg mb-3 flex-shrink-0" aria-hidden="true">
              <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 6L6 18M6 6l12 12" />
              </svg>
            </div>
            <p className="text-lg font-bold text-red-900 leading-tight">Este pedido ya fue despachado completamente.</p>
            <p className="mt-2 text-sm font-medium text-red-700">Fecha del despacho: <strong className="text-red-900">{fechaUltimoDespacho || '—'}</strong></p>
            <button
              type="button"
              onClick={onVolver}
              className="w-full max-w-md mt-4 py-3 rounded-xl bg-sinapsis hover:bg-sinapsis-dark text-white font-bold shadow-lg active:scale-[0.98] border-0"
            >
              Volver al inicio — Empezar de nuevo
            </button>
          </div>
        </div>
      ) : items.length > 0 ? (
        <div className="flex-shrink-0 px-3 py-2 bg-slate-100 border-b border-slate-200 flex items-center justify-between gap-2">
          <p className="text-xs text-gray-600">
            Toque el campo &quot;Cantidad a entregar&quot; de un ítem para abrir el teclado y escribir.
          </p>
          <span className="flex-shrink-0 text-sm font-semibold text-gray-700 bg-white px-2 py-1 rounded border border-slate-300">
            {items.length} {items.length === 1 ? 'ítem' : 'ítems'}
          </span>
        </div>
      ) : null}
      {/* Lista de ítems con scroll propio (siempre debajo del mensaje) */}
      <div className="flex-1 min-h-0 flex flex-col p-3">
        {items.length > 0 ? (
          <div className="flex-1 min-h-0 overflow-y-auto space-y-2 -mx-1 px-1">
            {items.map((item, i) => {
          const cant = parseFloat(item.cantidad, 10);
          const pend = item.pendiente != null ? item.pendiente : cant;
          const aEntregar = parseFloat(entregar[i], 10) || 0;
          const quedaraPendiente = Math.max(0, pend - aEntregar);
          const esCompletoYa = pend === 0;
          const seCompletaEnEstaEntrega = pend > 0 && quedaraPendiente === 0 && aEntregar > 0;
          const esVerde = esCompletoYa || seCompletaEnEstaEntrega;
          const esParcialConPendiente = (item.entregado_hasta != null && item.entregado_hasta > 0) && pend > 0 && !esVerde;
          const esCantidadParcial = pend > 0 && aEntregar > 0 && aEntregar < pend;
          const cardClass = esVerde
            ? 'p-3 rounded-xl border-2 border-green-400 bg-green-100'
            : esCantidadParcial
              ? 'p-3 rounded-xl border-2 border-blue-400 bg-blue-50 ring-2 ring-blue-200 cursor-pointer'
              : esParcialConPendiente
                ? 'p-3 rounded-xl border-2 border-amber-400 bg-amber-50 ring-2 ring-amber-300 cursor-pointer'
                : 'p-3 rounded-xl border-2 border-gray-200 bg-white cursor-pointer';
          const textClass = esVerde ? 'text-green-900' : esCantidadParcial ? 'text-blue-900' : esParcialConPendiente ? 'text-amber-900' : 'text-gray-800';
          const subTextClass = esVerde ? 'text-green-700' : esCantidadParcial ? 'text-blue-700' : esParcialConPendiente ? 'text-amber-700' : 'text-gray-600';
          return (
            <div
              key={item.id}
              role="button"
              tabIndex={0}
              className={cardClass}
              onClick={() => { if (pend > 0) inputRefs.current[i]?.focus(); }}
              onKeyDown={(e) => { if (pend > 0 && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); inputRefs.current[i]?.focus(); } }}
            >
              <div className={`text-sm font-medium ${textClass}`}>
                {item.producto?.descripcion || '—'}
              </div>
              <div className={`flex flex-wrap gap-x-3 gap-y-0.5 text-xs font-mono mt-1 ${subTextClass}`}>
                <span><strong>Cód. barras:</strong> {item.producto?.codigo_barras || '—'}</span>
                <span><strong>Cód. proveedor:</strong> {item.producto?.codigo_proveedor || '—'}</span>
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <div className={`rounded-lg px-3 py-2 text-center border-2 ${esCantidadParcial ? 'bg-blue-200 border-blue-500 ring-2 ring-blue-300' : esParcialConPendiente ? 'bg-green-200 border-green-500 ring-2 ring-green-400' : 'bg-green-100 border-green-400'}`}>
                  <div className={`text-xs font-semibold uppercase tracking-wide ${esCantidadParcial ? 'text-blue-800' : 'text-green-800'}`}>A entregar</div>
                  <div className={`text-2xl font-bold font-mono mt-0.5 ${esCantidadParcial ? 'text-blue-900' : 'text-green-900'}`}>{aEntregar}</div>
                </div>
                <div className={`rounded-lg px-3 py-2 text-center border-2 ${esVerde ? 'bg-green-100 border-green-500' : 'bg-amber-100 border-amber-400'}`}>
                  <div className={`text-xs font-semibold uppercase tracking-wide ${esVerde ? 'text-green-800' : 'text-amber-800'}`}>Quedará pendiente</div>
                  <div className={`text-2xl font-bold font-mono mt-0.5 ${esVerde ? 'text-green-900' : 'text-amber-900'}`}>{quedaraPendiente}</div>
                </div>
              </div>
              {pend > 0 && (
                <div className="mt-2" onClick={(e) => e.stopPropagation()}>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Cantidad a entregar (toque el ítem o el campo)</label>
                  <input
                    ref={(el) => { inputRefs.current[i] = el; }}
                    type="text"
                    inputMode="none"
                    readOnly
                    value={entregar[i] ?? ''}
                    onFocus={() => register(`despachar-cant-${i}`, {
                      type: 'numeric',
                      value: entregar[i] ?? '',
                      onChange: (v) => updateEntregar(i, v),
                      maxLength: 10,
                    })}
                    onBlur={() => unregister(`despachar-cant-${i}`)}
                    placeholder="0"
                    className="w-full px-3 py-2 rounded-lg border-2 border-gray-300 text-gray-800 font-mono text-lg bg-white"
                  />
                </div>
              )}
              <div className={`mt-2 text-sm space-y-0.5 ${esVerde ? 'text-green-800' : esCantidadParcial ? 'text-blue-800' : esParcialConPendiente ? 'text-amber-800' : 'text-gray-600'}`}>
                <div>Pedido total: <strong>{cant}</strong></div>
                {(item.entregado_hasta != null && item.entregado_hasta > 0) && (
                  <div>Ya entregado: <strong>{item.entregado_hasta}</strong></div>
                )}
                <div>Pendiente actual: <strong>{pend}</strong></div>
                {esVerde && <div className="font-semibold">100% entregado</div>}
                {esCantidadParcial && <div className="font-semibold text-blue-800">Entrega parcial</div>}
              </div>
            </div>
          );
        })}

        {exigeDatosCF && (
          <div className="mt-4 p-3 rounded-xl border-2 border-amber-200 bg-amber-50">
            <div className="text-sm font-semibold text-amber-800 mb-2">Cliente CF — retiro parcial: complete datos</div>
            <p className="text-xs text-amber-700 mb-2">Seleccione el campo y pulse &quot;Teclado&quot; para escribir.</p>
            <label className="block text-xs text-amber-700 mb-1">Nombre y apellido</label>
            <input
              type="text"
              inputMode="none"
              readOnly
              value={cf.nombreCompleto}
              onFocus={() => setCfKeyboardTarget('despachar-cf-nombre')}
              onBlur={() => unregister('despachar-cf-nombre')}
              placeholder="Nombre y apellido"
              className="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white mb-2 text-gray-800"
            />
            <label className="block text-xs text-amber-700 mb-1">Cédula</label>
            <input
              type="text"
              inputMode="none"
              readOnly
              value={cf.cedula}
              onFocus={() => setCfKeyboardTarget('despachar-cf-cedula')}
              onBlur={() => unregister('despachar-cf-cedula')}
              placeholder="Cédula"
              className="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white mb-2 text-gray-800 font-mono"
            />
            <label className="block text-xs text-amber-700 mb-1">Teléfono</label>
            <input
              type="text"
              inputMode="none"
              readOnly
              value={cf.telefono}
              onFocus={() => setCfKeyboardTarget('despachar-cf-telefono')}
              onBlur={() => unregister('despachar-cf-telefono')}
              placeholder="Teléfono"
              className="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white mb-2 text-gray-800 font-mono"
            />
            <button
              type="button"
              onClick={abrirTecladoCF}
              className="w-full py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium text-sm border border-gray-300"
              title="Abrir teclado en pantalla"
            >
              Teclado
            </button>
          </div>
        )}
          </div>
        ) : null}
      </div>

      {!yaDespachadoCompleto && (
        <div className="flex-shrink-0 p-3 border-t bg-white">
          <button
            type="button"
            onClick={handleConfirmar}
            disabled={!canConfirm || loading}
            className="w-full py-4 mt-2 rounded-xl btn-sinapsis text-lg font-semibold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
          >
            {loading ? 'Enviando…' : 'Confirmar entrega e imprimir ticket'}
          </button>
        </div>
      )}
    </div>
  );
}
