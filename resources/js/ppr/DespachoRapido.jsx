import React, { useState, useRef, useEffect } from 'react';
import { playDespachadoAlert, playAlertaPendienteSound } from './soundAlert';
import { usePprKeyboard } from './PprKeyboardContext';

const SCAN_INPUT_ID = 'despacho-rapido-scan';

export default function DespachoRapido({ pedido, yaDespachadoCompleto, fechaUltimoDespacho, onEntregarTodo, onDespacharPorItems, onVolver, loading }) {
  const scanInputRef = useRef(null);
  const [scanValue, setScanValue] = useState('');
  const alertPlayedRef = useRef(false);
  const { register, unregister, close, activeInput, updateValue } = usePprKeyboard();

  useEffect(() => {
    if (activeInput?.id === SCAN_INPUT_ID) updateValue(scanValue);
  }, [scanValue, activeInput?.id, updateValue]);

  const alertaPendienteRef = useRef(false);
  useEffect(() => {
    if (yaDespachadoCompleto && !alertPlayedRef.current) {
      alertPlayedRef.current = true;
      playDespachadoAlert();
    }
  }, [yaDespachadoCompleto]);
  const items = pedido?.items?.filter((i) => i.producto) || [];
  const tieneParcialConPendiente = !yaDespachadoCompleto && items.some((i) => (i.entregado_hasta != null && i.entregado_hasta > 0)) && items.some((i) => (i.pendiente != null ? i.pendiente : parseFloat(i.cantidad, 10)) > 0);
  useEffect(() => {
    if (tieneParcialConPendiente && !alertaPendienteRef.current) {
      alertaPendienteRef.current = true;
      playAlertaPendienteSound();
    }
  }, [tieneParcialConPendiente]);
  const tienePendiente = items.some((i) => (i.pendiente != null ? i.pendiente : parseFloat(i.cantidad, 10)) > 0);
  const puedeEntregar = tienePendiente && !yaDespachadoCompleto;
  const usuarioPedido = pedido?.vendedor?.nombre || pedido?.vendedor?.usuario || '—';
  const horaPedido = pedido?.created_at
    ? new Date(pedido.created_at).toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';

  useEffect(() => {
    scanInputRef.current?.focus();
  }, []);

  const handleScanKeyDown = (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const id = String(scanValue).trim().replace(/\D/g, '');
    if (id && parseInt(id, 10) === pedido.id && puedeEntregar) {
      setScanValue('');
      handleEntregarTodo();
    }
  };

  const onScanFocus = () => {
    // No abrir teclado solo al hacer foco; solo con el botón "Teclado"
  };

  const onScanBlur = () => {
    unregister(SCAN_INPUT_ID);
  };

  const abrirTeclado = () => {
    if (activeInput?.id === SCAN_INPUT_ID) {
      close();
      return;
    }
    register(SCAN_INPUT_ID, {
      type: 'numeric',
      value: scanValue,
      onChange: setScanValue,
      maxLength: 8,
    });
  };

  const handleEntregarTodo = () => {
    if (!puedeEntregar || loading) return;
    const payload = items
      .filter((i) => {
        const pend = i.pendiente != null ? i.pendiente : parseFloat(i.cantidad, 10);
        return pend > 0;
      })
      .map((i) => ({
        id_item_pedido: i.id,
        unidades_entregadas: i.pendiente != null ? i.pendiente : parseFloat(i.cantidad, 10),
      }));
    if (payload.length === 0) return;
    onEntregarTodo({
      id_pedido: pedido.id,
      items: payload,
      retiro_total: true,
    });
  };

  return (
    <div className="flex flex-col h-full max-w-md mx-auto pb-4">
      <input
        ref={scanInputRef}
        type="text"
        inputMode="none"
        autoComplete="off"
        readOnly
        value={scanValue}
        onFocus={onScanFocus}
        onBlur={onScanBlur}
        onKeyDown={handleScanKeyDown}
        className="absolute opacity-0 h-0 w-0 -left-[9999px]"
        aria-label="Segundo escaneo para despacho rápido"
      />
      <div className="flex items-center justify-between p-3 border-b bg-white sticky top-0 z-10">
        <button type="button" onClick={onVolver} className="px-3 py-2 text-gray-600 font-medium">
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
        <div className="mx-3 mt-2 p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium">
          Este pedido tiene entregas parciales. Cantidades pendientes por ítem abajo.
        </div>
      )}
      <div className="flex-1 overflow-y-auto p-3">
        <p className="text-sm font-semibold text-gray-700 mb-2">Productos del pedido (entregados y pendientes)</p>
        <div className="space-y-2 mb-4">
          {items.map((item) => {
            const cant = parseFloat(item.cantidad, 10);
            const pend = item.pendiente != null ? item.pendiente : cant;
            const esCompleto = pend === 0;
            const esParcialConPendiente = (item.entregado_hasta != null && item.entregado_hasta > 0) && pend > 0;
            const cardClass = esCompleto
              ? 'p-3 rounded-xl border-2 border-green-400 bg-green-100 flex flex-col gap-1 text-green-900'
              : esParcialConPendiente
                ? 'p-3 rounded-xl border-2 border-amber-400 bg-amber-50 flex flex-col gap-1 ring-2 ring-amber-300'
                : 'p-3 rounded-xl border-2 border-gray-200 bg-white flex flex-col gap-1';
            return (
              <div key={item.id} className={cardClass}>
                <div className="flex justify-between items-start">
                  <div className={`text-sm font-medium flex-1 min-w-0 mr-2 ${esCompleto ? 'text-green-900' : esParcialConPendiente ? 'text-amber-900' : 'text-gray-800'}`}>
                    {item.producto?.descripcion || '—'}
                  </div>
                  <div className={`text-sm shrink-0 font-mono font-semibold ${esCompleto ? 'text-green-800' : esParcialConPendiente ? 'text-amber-800' : 'text-gray-600'}`}>
                    {pend > 0 ? pend : cant}
                    {pend !== cant && pend > 0 && <span className="opacity-75"> / {cant}</span>}
                  </div>
                </div>
                <div className={`flex flex-wrap gap-x-3 gap-y-0.5 text-xs font-mono mt-1 ${esCompleto ? 'text-green-700' : esParcialConPendiente ? 'text-amber-700' : 'text-gray-600'}`}>
                  <span><strong>Cód. barras:</strong> {item.producto?.codigo_barras || '—'}</span>
                  <span><strong>Cód. proveedor:</strong> {item.producto?.codigo_proveedor || '—'}</span>
                </div>
                <div className={`mt-2 text-xs space-y-0.5 ${esCompleto ? 'text-green-800' : esParcialConPendiente ? 'text-amber-800' : 'text-gray-600'}`}>
                  <span>Pedido total: <strong>{cant}</strong></span>
                  {(item.entregado_hasta != null && item.entregado_hasta > 0) && (
                    <span className="ml-2">· Ya entregado: <strong>{item.entregado_hasta}</strong></span>
                  )}
                  <span className="ml-2">· Pendiente: <strong>{pend}</strong></span>
                  {esCompleto && <span className="ml-2 font-semibold">· 100% entregado</span>}
                </div>
              </div>
            );
          })}
        </div>

        {yaDespachadoCompleto && (
          <div className="w-full mb-4 space-y-4">
            <div className="p-4 rounded-xl bg-red-50 border-4 border-red-500 text-red-700 text-sm shadow-lg ring-2 ring-red-300">
              <p className="font-bold text-red-800">Este pedido ya fue despachado completamente.</p>
              <p className="mt-1 font-medium text-red-700">Fecha del despacho: <strong className="text-red-900">{fechaUltimoDespacho || '—'}</strong></p>
            </div>
            <button
              type="button"
              onClick={onVolver}
              className="w-full py-6 rounded-2xl bg-sinapsis hover:bg-sinapsis-dark text-white text-xl font-bold shadow-xl active:scale-[0.98] border-0"
            >
              Volver al inicio — Empezar de nuevo
            </button>
          </div>
        )}
        {!yaDespachadoCompleto && (
          <>
            <div className="rounded-2xl bg-green-50 border-2 border-green-200 p-6 w-full max-w-sm mx-auto text-center">
              <p className="text-lg font-semibold text-green-800 mb-1">Despacho rápido</p>
              <p className="text-gray-700 mb-4">Pedido #{pedido.id} listo. Escanee el código de nuevo o pulse el botón para entregar todo.</p>
              <button
                type="button"
                onClick={abrirTeclado}
                className="w-full mb-3 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium text-sm border border-gray-300"
                title="Abrir teclado en pantalla"
              >
                Teclado
              </button>
              <button
                type="button"
                onClick={handleEntregarTodo}
                disabled={!puedeEntregar || loading}
                className="w-full py-5 rounded-xl btn-sinapsis text-xl font-bold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
              >
                {loading ? 'Enviando…' : 'Entregar todo'}
              </button>
            </div>
            <button
              type="button"
              onClick={onDespacharPorItems}
              className="w-full mt-4 py-2 text-sm text-gray-500 underline hover:text-gray-700"
            >
              Despachar por ítems (cantidades)
            </button>
          </>
        )}
      </div>
    </div>
  );
}
