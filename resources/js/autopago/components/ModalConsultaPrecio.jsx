import React, { useState, useEffect, useCallback, useRef } from 'react';
import { X, Search, Keyboard } from 'lucide-react';
import QWERTYKeyboard from './QWERTYKeyboard';
import { searchForConsultaPrecio } from '../services/productService';
import { formatBs, formatUsd } from '../config';

const INACTIVITY_MS = 15000;
const DEBOUNCE_MS = 350;

export default function ModalConsultaPrecio({ open, onClose }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showKeyboard, setShowKeyboard] = useState(false);
  const inputRef = useRef(null);
  const inactivityRef = useRef(null);
  const searchTimeoutRef = useRef(null);

  const resetModal = useCallback(() => {
    setQuery('');
    setResults([]);
    setLoading(false);
    setShowKeyboard(false);
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
      searchTimeoutRef.current = null;
    }
  }, []);

  const closeAndReset = useCallback(() => {
    resetModal();
    onClose?.();
  }, [onClose, resetModal]);

  const resetInactivityTimer = useCallback(() => {
    if (inactivityRef.current) clearTimeout(inactivityRef.current);
    if (!open) return;
    inactivityRef.current = setTimeout(() => {
      closeAndReset();
    }, INACTIVITY_MS);
  }, [open, closeAndReset]);

  useEffect(() => {
    if (!open) return;
    resetInactivityTimer();
    return () => {
      if (inactivityRef.current) clearTimeout(inactivityRef.current);
    };
  }, [open, resetInactivityTimer]);

  useEffect(() => {
    if (!open) return;
    const t = setTimeout(() => {
      inputRef.current?.focus();
    }, 150);
    return () => clearTimeout(t);
  }, [open]);

  const doSearch = useCallback(async (q) => {
    const trimmed = (q || '').trim();
    if (!trimmed) {
      setResults([]);
      return;
    }
    setLoading(true);
    try {
      const list = await searchForConsultaPrecio(trimmed, 25);
      setResults(list);
    } catch {
      setResults([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) return;
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      doSearch(query);
      searchTimeoutRef.current = null;
    }, DEBOUNCE_MS);
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [query, open, doSearch]);

  const handleKeyPress = (key) => {
    setQuery((prev) => prev + key);
    resetInactivityTimer();
  };

  const handleBackspace = () => {
    setQuery((prev) => prev.slice(0, -1));
    resetInactivityTimer();
  };

  const handleInputChange = (e) => {
    setQuery(e.target.value);
    resetInactivityTimer();
  };

  const handleCancel = () => {
    closeAndReset();
  };

  if (!open) return null;

  const single = results.length === 1 ? results[0] : null;
  const multiple = results.length > 1 ? results : [];

  return (
    <div
      className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      onClick={(e) => e.target === e.currentTarget && closeAndReset()}
      onTouchStart={resetInactivityTimer}
      onMouseMove={resetInactivityTimer}
      onKeyDown={resetInactivityTimer}
    >
      <div
        className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div
          className="flex-shrink-0 flex items-center justify-between gap-2 px-5 py-4 border-b border-gray-200"
          style={{ backgroundColor: 'rgba(242, 109, 10, 0.1)' }}
        >
          <div className="flex items-center gap-2 min-w-0">
            <Search className="text-gray-600 flex-shrink-0" size={26} strokeWidth={2} />
            <h3 className="text-xl font-bold text-gray-800 truncate">Consultar precio</h3>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setShowKeyboard((v) => !v)}
              className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold transition-colors ${showKeyboard ? 'text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'}`}
              style={showKeyboard ? { backgroundColor: 'rgba(242, 109, 10, 0.9)' } : {}}
            >
              <Keyboard size={18} />
              Teclado
            </button>
            <button
              type="button"
              onClick={handleCancel}
              className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
              aria-label="Cerrar"
            >
              <X size={24} strokeWidth={2} />
            </button>
          </div>
        </div>

        <div className="flex-shrink-0 p-4 border-b border-gray-100">
          <label className="block text-sm font-semibold text-gray-700 mb-2">Código de barras o código proveedor</label>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={handleInputChange}
            onFocus={resetInactivityTimer}
            onKeyDown={(e) => {
              if (e.key === 'Enter') e.preventDefault();
              resetInactivityTimer();
            }}
            className="w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-4 py-4 text-2xl font-medium text-gray-800 min-h-[56px] focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
            placeholder="Escriba o escanee código..."
            autoComplete="off"
          />
        </div>

        {showKeyboard && (
          <div className="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50">
            <p className="text-sm font-semibold text-gray-700 mb-2">Teclado en pantalla</p>
            <QWERTYKeyboard
              onKeyPress={handleKeyPress}
              onBackspace={handleBackspace}
              withSymbols
            />
          </div>
        )}

        <div className="flex-1 min-h-0 overflow-y-auto p-4">
          {loading && (
            <div className="flex justify-center py-8">
              <span className="animate-spin h-10 w-10 border-2 border-orange-500 border-t-transparent rounded-full" />
            </div>
          )}

          {!loading && query.trim() && results.length === 0 && (
            <p className="text-center text-gray-500 py-8">No se encontraron productos.</p>
          )}

          {!loading && single && (
            <div className="rounded-xl border-2 border-gray-200 bg-gray-50 p-5 space-y-3">
              <p className="text-lg font-semibold text-gray-900 break-words">{single.descripcion || 'Sin descripción'}</p>
              <div className="grid grid-cols-1 gap-2 text-base">
                <p className="text-gray-700">
                  <span className="font-medium text-gray-500">Código proveedor:</span>{' '}
                  {single.codigo_proveedor || '—'}
                </p>
                <p className="text-gray-700">
                  <span className="font-medium text-gray-500">Código de barras:</span>{' '}
                  {single.codigo_barras || '—'}
                </p>
                <div className="flex flex-wrap gap-4 pt-2 border-t border-gray-200">
                  <p className="text-xl font-bold text-gray-900">
                    Bs. {formatBs(parseFloat(single.bs) || 0)}
                  </p>
                  <p className="text-xl font-bold text-orange-600">
                    USD {formatUsd(parseFloat(single.precio) || 0)}
                  </p>
                </div>
              </div>
            </div>
          )}

          {!loading && multiple.length > 0 && (
            <div className="rounded-xl border-2 border-gray-200 overflow-hidden">
              <div className="overflow-x-auto max-h-[50vh] overflow-y-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-gray-100 sticky top-0">
                    <tr>
                      <th className="px-3 py-2 font-semibold text-gray-700">Descripción</th>
                      <th className="px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">Cód. proveedor</th>
                      <th className="px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">Cód. barras</th>
                      <th className="px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">Bs</th>
                      <th className="px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">USD</th>
                    </tr>
                  </thead>
                  <tbody>
                    {multiple.map((row) => (
                      <tr key={row.id} className="border-t border-gray-100 hover:bg-orange-50/50">
                        <td className="px-3 py-2 text-gray-800 max-w-[180px] truncate" title={row.descripcion}>
                          {row.descripcion || '—'}
                        </td>
                        <td className="px-3 py-2 text-gray-700 whitespace-nowrap">{row.codigo_proveedor || '—'}</td>
                        <td className="px-3 py-2 text-gray-700 whitespace-nowrap">{row.codigo_barras || '—'}</td>
                        <td className="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">{formatBs(parseFloat(row.bs) || 0)}</td>
                        <td className="px-3 py-2 font-medium text-orange-600 whitespace-nowrap">{formatUsd(parseFloat(row.precio) || 0)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>

        <div className="flex-shrink-0 p-4 border-t border-gray-200 bg-gray-50 flex justify-end">
          <button
            type="button"
            onClick={handleCancel}
            className="px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 transition-colors"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  );
}
