import React, { useState, useRef, useEffect, useCallback } from 'react';
import { usePprKeyboard } from './PprKeyboardContext';

const INPUT_ID = 'buscar-pedido-id';

export default function BuscarPedido({ onBuscar, loading, entregaParcialSolicitada, onEntregaParcialChange }) {
  const [idInput, setIdInput] = useState('');
  const scanInputRef = useRef(null);
  const bufferRef = useRef('');
  const { register, unregister, close, activeInput, updateValue } = usePprKeyboard();

  useEffect(() => {
    if (activeInput?.id === INPUT_ID) updateValue(idInput);
  }, [idInput, activeInput?.id, updateValue]);

  useEffect(() => {
    scanInputRef.current?.focus();
  }, []);

  const handleBuscar = useCallback(() => {
    const id = (bufferRef.current || idInput).toString().trim().replace(/\D/g, '').slice(0, 8);
    if (!id) return;
    bufferRef.current = '';
    setIdInput('');
    onBuscar(parseInt(id, 10));
  }, [idInput, onBuscar]);

  // Capturar escaneo desde cualquier parte de la vista (no requiere foco en el input)
  useEffect(() => {
    const onDocKeyDown = (e) => {
      if (e.key === 'Enter') {
        const buf = bufferRef.current.replace(/\D/g, '').slice(0, 8);
        if (buf) {
          e.preventDefault();
          e.stopPropagation();
          onBuscar(parseInt(buf, 10));
          bufferRef.current = '';
          setIdInput('');
        }
        return;
      }
      if (/^[0-9]$/.test(e.key)) {
        e.preventDefault();
        e.stopPropagation();
        bufferRef.current = (bufferRef.current + e.key).slice(0, 8);
        setIdInput(bufferRef.current);
      }
    };
    document.addEventListener('keydown', onDocKeyDown, true);
    return () => document.removeEventListener('keydown', onDocKeyDown, true);
  }, [onBuscar]);

  const handleFocus = () => {
    bufferRef.current = idInput;
    // No abrir teclado en pantalla al hacer foco; solo con el botón "Teclado"
  };

  const handleBlur = () => {
    unregister(INPUT_ID);
  };

  const abrirTeclado = () => {
    if (activeInput?.id === INPUT_ID) {
      close();
      return;
    }
    bufferRef.current = idInput;
    register(INPUT_ID, {
      type: 'numeric',
      value: idInput,
      onChange: (v) => {
        bufferRef.current = v;
        setIdInput(v);
      },
      maxLength: 8,
    });
  };

  return (
    <div className="flex flex-col h-full max-w-md mx-auto">
      <input
        ref={scanInputRef}
        type="text"
        inputMode="none"
        autoComplete="off"
        readOnly
        value={idInput}
        onFocus={handleFocus}
        onBlur={handleBlur}
        className="absolute opacity-0 h-0 w-0 -left-[9999px] focus:opacity-0"
        aria-label="Escanear código de barras o ingresar número de pedido"
      />
      {loading && (
        <div className="mx-4 mt-2 p-3 rounded-xl bg-sinapsis text-white text-center font-semibold shadow-lg flex items-center justify-center gap-2" role="status" aria-live="polite">
          <svg className="animate-spin h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          Buscando pedido…
        </div>
      )}
      <p className="px-4 pt-2 text-xs text-gray-500 text-center">
        Escanee el código de barras desde cualquier parte de la pantalla o use el teclado.
      </p>
      <div className="p-4">
        {onEntregaParcialChange && (
          <div className="mb-4 p-3 rounded-xl border-2 bg-white border-gray-200">
            <p className="text-sm font-medium text-gray-700 mb-2">Modo de entrega</p>
            <div className="flex gap-2 flex-wrap">
              <button
                type="button"
                onClick={() => onEntregaParcialChange(false)}
                className={`px-4 py-2 rounded-lg text-sm font-medium border-2 transition-colors ${!entregaParcialSolicitada ? 'bg-sinapsis text-white border-sinapsis' : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'}`}
              >
                Un escaneo → entregar todo
              </button>
              <button
                type="button"
                onClick={() => onEntregaParcialChange(true)}
                className={`px-4 py-2 rounded-lg text-sm font-medium border-2 transition-colors ${entregaParcialSolicitada ? 'bg-sinapsis text-white border-sinapsis' : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'}`}
              >
                Entrega parcial (por ítems)
              </button>
            </div>
            <p className="text-xs text-gray-500 mt-2">
              {entregaParcialSolicitada ? 'Al escanear irás a indicar cantidades por ítem.' : 'Al escanear se despachará todo el pedido.'}
            </p>
          </div>
        )}
        <label className="block text-sm font-medium text-gray-600 mb-2">Nº de pedido (escanear o teclear)</label>
        <div className="flex gap-2 items-stretch">
          <div
            role="button"
            tabIndex={0}
            onClick={() => scanInputRef.current?.focus()}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') scanInputRef.current?.focus(); }}
            className="flex-1 min-h-[48px] px-4 py-3 text-2xl font-mono bg-white border-2 border-gray-300 rounded-xl text-center cursor-pointer focus:outline-none focus:ring-2 focus:ring-sinapsis ring-offset-2"
            aria-readonly
          >
            {idInput || '—'}
          </div>
          <button
            type="button"
            onClick={abrirTeclado}
            className="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium text-sm border border-gray-300 focus:outline-none focus:ring-2 focus:ring-sinapsis ring-offset-2"
            title="Abrir teclado en pantalla"
          >
            Teclado
          </button>
        </div>
      </div>
      <div className="p-4 pt-2">
        <button
          type="button"
          onClick={handleBuscar}
          disabled={!idInput.trim() || loading}
          className="w-full py-4 rounded-xl btn-sinapsis text-lg font-semibold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
        >
          {loading ? 'Buscando…' : 'Buscar pedido'}
        </button>
      </div>
    </div>
  );
}
