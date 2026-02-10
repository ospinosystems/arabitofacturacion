import React, { useState, useEffect, useRef } from 'react';
import { Lock, X } from 'lucide-react';

const AUTO_CLEAR_MS = 1000;

export default function ModalClaveAdmin({ open, onClose, onSuccess, idTarea, sendClavemodal }) {
  const [clave, setClave] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const clearTimerRef = useRef(null);

  useEffect(() => {
    if (!open) {
      setClave('');
      if (clearTimerRef.current) clearTimeout(clearTimerRef.current);
      return;
    }
    return () => {
      if (clearTimerRef.current) clearTimeout(clearTimerRef.current);
    };
  }, [open]);

  useEffect(() => {
    if (!open || !clave) return;
    if (clearTimerRef.current) clearTimeout(clearTimerRef.current);
    clearTimerRef.current = setTimeout(() => {
      setClave('');
      clearTimerRef.current = null;
    }, AUTO_CLEAR_MS);
    return () => {
      if (clearTimerRef.current) clearTimeout(clearTimerRef.current);
    };
  }, [open, clave]);

  if (!open) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (!clave.trim()) {
      setError('Ingrese la clave');
      return;
    }
    setLoading(true);
    try {
      const { data } = await sendClavemodal({
        valinputsetclaveadmin: clave,
        idtareatemp: idTarea,
      });
      if (data && data.estado) {
        setClave('');
        onSuccess?.();
        onClose?.();
      } else {
        setError(data?.msj || 'Clave no autorizada');
      }
    } catch (err) {
      setError(err.response?.data?.msj || 'Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
      <div
        className="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div
          className="flex items-center justify-between px-5 py-4 border-b border-gray-200"
          style={{ backgroundColor: 'rgba(242, 109, 10, 0.1)' }}
        >
          <div className="flex items-center gap-2">
            <Lock className="text-gray-600" size={22} strokeWidth={2} />
            <h3 className="text-lg font-bold text-gray-800">Clave de administrador</h3>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-gray-500 hover:bg-gray-100"
            aria-label="Cerrar"
          >
            <X size={20} strokeWidth={2} />
          </button>
        </div>
        <form onSubmit={handleSubmit} className="p-5">
          <p className="text-gray-600 mb-3">Ingrese la clave DICI para abrir el panel de solicitud de producto.</p>
          <input
            type="password"
            value={clave}
            onChange={(e) => { setClave(e.target.value); setError(''); }}
            className="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-lg focus:border-orange-400 focus:outline-none"
            placeholder="Clave (se borra al segundo)"
            autoComplete="off"
            data-lpignore
            data-form-type="other"
          />
          {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
          <div className="flex gap-3 mt-4">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 py-3 rounded-xl font-semibold text-white"
              style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
            >
              {loading ? 'Verificando…' : 'Acceder'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
