import React, { useState, useEffect, useCallback, useRef } from 'react';
import { X, Search, PackagePlus, ArrowLeft } from 'lucide-react';
import QWERTYKeyboard from './QWERTYKeyboard';
import ModalClaveAdmin from './ModalClaveAdmin';
import { searchProducts } from '../services/productService';
import { api } from '../services/api';
import { formatBs } from '../config';

const DEBOUNCE_MS = 400;
const MIN_QUERY_LENGTH = 2;

export default function ModalBuscarProducto({ open, onClose, onSelectProduct, onAgregarPanelOpenChange, customerName }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [vista, setVista] = useState('buscar');
  const [idTarea, setIdTarea] = useState(null);
  const [showClaveModal, setShowClaveModal] = useState(false);
  const [codigoAgregar, setCodigoAgregar] = useState('');
  const [cantidadAgregar, setCantidadAgregar] = useState('');
  const [sendingAgregar, setSendingAgregar] = useState(false);
  const [msgAgregar, setMsgAgregar] = useState(null);
  const [campoActivoAgregar, setCampoActivoAgregar] = useState('codigo'); // 'codigo' | 'cantidad'
  const inputCodigoRef = useRef(null);
  const inputCantidadRef = useRef(null);
  const inputBuscarRef = useRef(null);

  const doSearch = useCallback(async (q) => {
    const trimmed = (q || '').trim();
    if (trimmed.length < MIN_QUERY_LENGTH) {
      setResults([]);
      return;
    }
    setLoading(true);
    try {
      const list = await searchProducts(trimmed, 15);
      setResults(list);
    } catch {
      setResults([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) return;
    const t = setTimeout(() => doSearch(query), DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [query, open, doSearch]);

  useEffect(() => {
    if (open) {
      setQuery('');
      setResults([]);
      setVista('buscar');
      setMsgAgregar(null);
      onAgregarPanelOpenChange?.(false);
    } else {
      onAgregarPanelOpenChange?.(false);
    }
  }, [open, onAgregarPanelOpenChange]);

  useEffect(() => {
    onAgregarPanelOpenChange?.(vista === 'agregar');
  }, [vista, onAgregarPanelOpenChange]);

  useEffect(() => {
    if (!open) return;
    const t = setTimeout(() => {
      if (vista === 'agregar') inputCodigoRef.current?.focus();
      else inputBuscarRef.current?.focus();
    }, 100);
    return () => clearTimeout(t);
  }, [open, vista]);

  const handleAbrirAgregarProducto = () => {
    setVista('agregar');
    setMsgAgregar(null);
  };

  const handleClaveSuccess = async () => {
    setShowClaveModal(false);
    const codigo = codigoAgregar.trim();
    const cantidad = parseInt(cantidadAgregar, 10);
    if (!codigo || !(cantidad > 0) || !idTarea) {
      setMsgAgregar({ tipo: 'error', text: 'Código y cantidad no válidos.' });
      return;
    }
    setSendingAgregar(true);
    setMsgAgregar(null);
    try {
      const { data } = await api.solicitarAgregarProducto({ id_tarea: idTarea, codigo, cantidad });
      if (data?.estado) {
        setMsgAgregar({ tipo: 'ok', text: data.msj || 'Solicitud enviada.' });
        setCodigoAgregar('');
        setCantidadAgregar('');
      } else {
        setMsgAgregar({ tipo: 'error', text: data?.msj || 'Error al enviar.' });
      }
    } catch (err) {
      setMsgAgregar({ tipo: 'error', text: err.response?.data?.msj || 'Error de conexión.' });
    } finally {
      setSendingAgregar(false);
    }
  };

  const handlePedirClaveYEnviar = async () => {
    const codigo = codigoAgregar.trim();
    const cantidad = parseInt(cantidadAgregar, 10);
    if (!codigo || !(cantidad > 0)) {
      setMsgAgregar({ tipo: 'error', text: 'Ingrese código y cantidad válida.' });
      return;
    }
    setMsgAgregar(null);
    try {
      const { data } = await api.crearTareaAgregarProducto();
      if (data?.estado && data?.id) {
        setIdTarea(data.id);
        setShowClaveModal(true);
      } else {
        setMsgAgregar({ tipo: 'error', text: 'No se pudo crear la tarea.' });
      }
    } catch {
      setMsgAgregar({ tipo: 'error', text: 'No se pudo crear la tarea. Intente de nuevo.' });
    }
  };

  const handleKeyPress = (key) => {
    setQuery((prev) => prev + key);
  };

  const handleBackspace = () => {
    setQuery((prev) => prev.slice(0, -1));
  };

  const handleSelect = (product) => {
    onSelectProduct?.(product);
    onClose?.();
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div
        className="bg-white rounded-2xl shadow-xl w-full max-w-2xl h-[calc(100vh-2rem)] flex flex-col overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div
          className="flex-shrink-0 flex flex-col gap-1 px-5 py-4 border-b border-gray-200"
          style={{ backgroundColor: 'rgba(242, 109, 10, 0.1)' }}
        >
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2 min-w-0">
              <Search className="text-gray-600 flex-shrink-0" size={24} strokeWidth={2} />
              <h3 className="text-xl font-bold text-gray-800 truncate">
                {vista === 'buscar' ? 'Buscar producto' : 'Solicitar agregar producto'}
              </h3>
            </div>
          <div className="flex items-center gap-2">
            {vista === 'agregar' ? (
              <button
                type="button"
                onClick={() => setVista('buscar')}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200"
              >
                <ArrowLeft size={18} />
                Volver
              </button>
            ) : (
              <button
                type="button"
                onClick={handleAbrirAgregarProducto}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-white"
                style={{ backgroundColor: 'rgba(242, 109, 10, 0.9)' }}
              >
                <PackagePlus size={18} />
                Agregar producto
              </button>
            )}
            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
              aria-label="Cerrar"
            >
              <X size={24} strokeWidth={2} />
            </button>
          </div>
          </div>
          {customerName?.trim() && (
            <p className="text-sm font-medium text-gray-700 truncate" title={customerName}>
              Cliente: {customerName}
            </p>
          )}
        </div>

        {vista === 'agregar' ? (
          <div className="flex-1 min-h-0 flex flex-col overflow-hidden p-4">
            <p className="text-sm text-gray-600 mb-3">Código de barras o código proveedor y cantidad a solicitar.</p>
            {/* Ambos inputs arriba */}
            <div className="flex-shrink-0 space-y-3 mb-4">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Código (teclado, escáner o pantalla)</label>
                <input
                  ref={inputCodigoRef}
                  type="text"
                  value={codigoAgregar}
                  onChange={(e) => setCodigoAgregar(e.target.value)}
                  onFocus={() => setCampoActivoAgregar('codigo')}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') e.preventDefault();
                  }}
                  className={`w-full rounded-xl border-2 bg-gray-50 px-4 py-3 text-lg font-medium text-gray-800 min-h-[48px] focus:outline-none ${campoActivoAgregar === 'codigo' ? 'border-orange-400 ring-2 ring-orange-200' : 'border-gray-200'}`}
                  placeholder="Escriba o escanee código..."
                  autoComplete="off"
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Cantidad</label>
                <input
                  ref={inputCantidadRef}
                  type="text"
                  inputMode="numeric"
                  value={cantidadAgregar}
                  onChange={(e) => setCantidadAgregar(e.target.value.replace(/\D/g, '').slice(0, 8))}
                  onFocus={() => setCampoActivoAgregar('cantidad')}
                  className={`w-full rounded-xl border-2 bg-gray-50 px-4 py-3 text-xl font-medium text-gray-800 min-h-[48px] tabular-nums focus:outline-none ${campoActivoAgregar === 'cantidad' ? 'border-orange-400 ring-2 ring-orange-200' : 'border-gray-200'}`}
                  placeholder="0"
                />
              </div>
            </div>
            {/* Teclado debajo de ambos inputs */}
            <div className="flex-shrink-0">
              <p className="text-sm font-semibold text-gray-700 mb-2">
                Teclado {campoActivoAgregar === 'codigo' ? '(código)' : '(cantidad)'}
              </p>
              <QWERTYKeyboard
                onKeyPress={(key) => {
                  if (campoActivoAgregar === 'codigo') {
                    setCodigoAgregar((prev) => prev + key);
                  } else {
                    if (/^\d$/.test(key)) {
                      setCantidadAgregar((prev) => (prev + key).replace(/\D/g, '').slice(0, 8));
                    }
                  }
                }}
                onBackspace={() => {
                  if (campoActivoAgregar === 'codigo') {
                    setCodigoAgregar((prev) => prev.slice(0, -1));
                  } else {
                    setCantidadAgregar((prev) => prev.slice(0, -1));
                  }
                }}
                withSymbols
              />
            </div>
            <div className="flex-1 min-h-2" />
            {msgAgregar && (
              <p className={`mt-3 text-sm font-medium ${msgAgregar.tipo === 'ok' ? 'text-green-700' : 'text-red-600'}`}>
                {msgAgregar.text}
              </p>
            )}
            <button
              type="button"
              disabled={sendingAgregar}
              onClick={handlePedirClaveYEnviar}
              className="mt-4 w-full py-4 rounded-xl font-bold text-lg text-white disabled:opacity-60"
              style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
            >
              {sendingAgregar ? 'Enviando…' : 'Enviar solicitud'}
            </button>
          </div>
        ) : (
          <>
        {msgAgregar && vista === 'buscar' && (
          <div className="flex-shrink-0 px-4 py-2 bg-red-50 border-b border-red-100">
            <p className="text-sm font-medium text-red-700">{msgAgregar.text}</p>
          </div>
        )}
        <div className="flex-shrink-0 p-4">
          <input
            ref={inputBuscarRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') e.preventDefault(); }}
            className="w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-xl font-medium text-gray-800 min-h-[52px] focus:border-orange-400 focus:outline-none"
            placeholder="Escriba nombre o código, o escanee..."
            autoComplete="off"
          />
        </div>

        <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
          <div className="h-full overflow-y-auto px-4 py-2">
          {loading && (
            <div className="flex justify-center py-6">
              <span className="animate-spin h-10 w-10 border-2 border-orange-500 border-t-transparent rounded-full" />
            </div>
          )}
          {!loading && query.trim().length >= MIN_QUERY_LENGTH && results.length === 0 && (
            <p className="text-center text-gray-500 py-6">No se encontraron productos.</p>
          )}
          {!loading && results.length > 0 && (
            <ul className="space-y-2">
              {results.map((p) => (
                <li key={p.id}>
                  <button
                    type="button"
                    onClick={() => handleSelect(p)}
                    className="w-full flex items-center gap-4 p-4 rounded-xl border-2 border-gray-100 bg-white text-left hover:border-orange-200 hover:bg-orange-50/50 transition-colors"
                  >
                    <div className="w-14 h-14 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                      <img
                        src={p.image || 'https://placehold.co/140x140/E5E7EB/6B7280?text=Producto'}
                        alt=""
                        className="w-full h-full object-cover"
                      />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-lg font-semibold text-gray-800 truncate">{p.name}</p>
                      <p className="font-medium text-gray-900">Bs. {formatBs(p.price)}</p>
                      <p className="text-xs text-gray-500 mt-0.5">
                        {p.ean && <span>Cód. barras: {p.ean}</span>}
                        {p.ean && p.codigo_proveedor && ' · '}
                        {p.codigo_proveedor && <span>Cód. proveedor: {p.codigo_proveedor}</span>}
                        {p.stock != null && (p.ean || p.codigo_proveedor) && ' · '}
                        {p.stock != null && <span>Stock: {p.stock}</span>}
                      </p>
                    </div>
                    <span className="text-sm font-semibold text-orange-600">Agregar</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
          </div>
        </div>

        <div className="flex-shrink-0 p-4 border-t border-gray-200 bg-gray-100">
          <p className="text-sm font-semibold text-gray-900 mb-3">Teclado</p>
          <QWERTYKeyboard
            onKeyPress={handleKeyPress}
            onBackspace={handleBackspace}
            withSymbols
          />
        </div>
          </>
        )}

        <ModalClaveAdmin
          open={showClaveModal}
          onClose={() => setShowClaveModal(false)}
          onSuccess={handleClaveSuccess}
          idTarea={idTarea}
          sendClavemodal={api.sendClavemodal}
        />
      </div>
    </div>
  );
}
