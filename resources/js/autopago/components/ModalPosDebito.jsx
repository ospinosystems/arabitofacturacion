import React, { useState, useEffect, useRef } from 'react';
import { api } from '../services/api';
import TecladoNumerico from './TecladoNumerico';

/** Extrae solo dígitos de la cédula para el POS */
const cedulaSoloDigitos = (v) => String(v || '').replace(/\D/g, '');

/** Formatea monto para input (permite decimales) */
const formatMontoInput = (v) => {
  const s = String(v || '').replace(/[^0-9.,]/g, '').replace(',', '.');
  const parts = s.split('.');
  if (parts.length > 2) return parts[0] + '.' + parts.slice(1).join('');
  return s;
};

/** Redondea a 2 decimales para evitar errores de punto flotante al comparar montos */
const round2 = (x) => Math.round(parseFloat(x) * 100) / 100;

/** Modal POS Débito - múltiples tarjetas, monto editable para pago parcial */
export default function ModalPosDebito({
  open,
  onClose,
  montoTotal,
  sessionUser,
  cedulaCliente,
  pedidoId,
  onSuccess,
}) {
  const [posCedulaTitular, setPosCedulaTitular] = useState('');
  const [posTipoCuenta, setPosTipoCuenta] = useState('CORRIENTE');
  const [posMontoDebito, setPosMontoDebito] = useState('');
  const [posLoading, setPosLoading] = useState(false);
  const [posRespuesta, setPosRespuesta] = useState(null);
  const [transaccionesEstaSesion, setTransaccionesEstaSesion] = useState([]);
  const [campoActivo, setCampoActivo] = useState('cedula'); // 'cedula' | 'monto'
  const enviandoRef = useRef(false); // evita doble petición (Enter en input + overlay)

  useEffect(() => {
    if (open) {
      setPosMontoDebito(montoTotal > 0 ? montoTotal.toFixed(2) : '');
      setPosCedulaTitular(cedulaSoloDigitos(cedulaCliente));
      setPosTipoCuenta('CORRIENTE');
      setPosRespuesta(null);
      setTransaccionesEstaSesion([]);
      enviandoRef.current = false;
    }
  }, [open, cedulaCliente]);

  useEffect(() => {
    if (open && montoTotal > 0) {
      setPosMontoDebito(montoTotal.toFixed(2));
    }
  }, [open, montoTotal]);

  useEffect(() => {
    if (open) {
      const inputCedula = document.getElementById('autopago-pos-cedula-input');
      if (inputCedula) {
        setTimeout(() => inputCedula.focus(), 100);
      }
    }
  }, [open]);

  const enviarSolicitudPosDebito = async () => {
    if (posLoading || enviandoRef.current) return;
    if (!posCedulaTitular) {
      setPosRespuesta({ mensaje: 'Debe ingresar la cédula del titular', exito: false });
      return;
    }

    const montoActual = parseFloat(posMontoDebito) || 0;
    const maxDisponible = parseFloat(montoTotal) || 0;
    if (montoActual <= 0) {
      setPosRespuesta({ mensaje: 'Debe ingresar un monto válido', exito: false });
      return;
    }
    if (round2(montoActual) > round2(maxDisponible)) {
      setPosRespuesta({ mensaje: `El monto no puede ser mayor al disponible a pagar (Bs ${maxDisponible.toFixed(2)})`, exito: false });
      return;
    }

    enviandoRef.current = true;
    setPosLoading(true);
    setPosRespuesta(null);

    const montoEntero = Math.round(montoActual * 100);
    const codigoSucursal = sessionUser?.sucursal || 'AUTOPAGO';
    const nombreUsuario = sessionUser?.usuario || 'kiosk';
    const indiceDebito = transaccionesEstaSesion.length;
    const orderId = pedidoId || `autopago-${Date.now()}`;
    const numeroOrden = `${codigoSucursal}-${nombreUsuario}-${orderId}-${montoEntero}-${indiceDebito}`;

    const payload = {
      operacion: 'COMPRA',
      monto: montoEntero,
      cedula: posCedulaTitular,
      numeroOrden,
      mensaje: 'PowerBy:Ospino',
      tipoCuenta: posTipoCuenta,
    };

    try {
      const response = await api.enviarTransaccionPOS(payload);
      const posData = response.data.data || {};

      if (response.data.success && posData && Object.keys(posData).length > 0) {
        const posReference = String(posData.reference || posData.approval || '').trim();
        const refUltimos4 = posReference.replace(/\D/g, '').slice(-4).padStart(4, '0');

        const pago = {
          type: 'card',
          description: 'Tarjeta débito',
          amount: montoActual,
          status: 'validated',
          referencia: posReference,
          refCorta: refUltimos4,
          cedula: posCedulaTitular,
          posData: posData,
        };

        setPosRespuesta({
          mensaje: `✓ APROBADO - Ref: ${posReference}`,
          exito: true,
        });

        onSuccess(pago);
        setTransaccionesEstaSesion((prev) => [...prev, { ...pago, monto: montoActual }]);
        setPosCedulaTitular('');
        setTimeout(() => setPosRespuesta(null), 2000);
      } else {
        const errorMsg = posData.message || response.data.error || 'Error en transacción POS';
        setPosRespuesta({ mensaje: `✗ ${errorMsg}`, exito: false });
      }
    } catch (error) {
      const errorMsg = error.response?.data?.error || error.message || 'Error de conexión';
      setPosRespuesta({ mensaje: `✗ ${errorMsg}`, exito: false });
    } finally {
      setPosLoading(false);
      enviandoRef.current = false;
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Escape' && !posLoading) {
      onClose();
    }
    if (e.key === 'Enter' && !posLoading && posCedulaTitular) {
      e.preventDefault();
      enviarSolicitudPosDebito();
    }
  };

  if (!open) return null;

  return (
    <>
      <div
        className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        onKeyDown={handleKeyDown}
        tabIndex={-1}
      >
        <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
          <div className="px-6 py-4 flex-shrink-0 border-b border-gray-200 bg-gray-50">
            <h3 className="text-xl font-semibold text-gray-800 flex items-center gap-2">
              <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
              Pago con tarjeta
            </h3>
            <p className="text-sm text-gray-500 mt-1">
              Monto: Bs {parseFloat(posMontoDebito || 0).toFixed(2)} · Máx. Bs {parseFloat(montoTotal || 0).toFixed(2)}
            </p>
          </div>

          <div className="p-6 space-y-5 overflow-y-auto flex-1">
            {/* Instrucción simple y clara */}
            <div className="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <p className="text-slate-700 leading-relaxed">
                {posLoading ? (
                  <>Enviando al datáfono… confirme el monto e introduzca su clave en el lector.</>
                ) : (
                  <>Ingrese su cédula y el monto, luego pulse <strong className="text-slate-800">Enviar al datáfono</strong>. En el lector confirme el monto e introduzca su clave.</>
                )}
              </p>
            </div>

            {/* Campos en bloque limpio */}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-600 mb-1">Cédula del titular</label>
                <input
                  id="autopago-pos-cedula-input"
                  type="text"
                  inputMode="none"
                  value={posCedulaTitular}
                  onChange={(e) => setPosCedulaTitular(e.target.value.replace(/\D/g, ''))}
                  onFocus={() => setCampoActivo('cedula')}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      e.stopPropagation();
                      enviarSolicitudPosDebito();
                    }
                  }}
                  placeholder="Ej: 12345678"
                  className={`w-full px-4 py-3 text-lg text-gray-900 border rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 ${campoActivo === 'cedula' ? 'border-slate-400 ring-2 ring-slate-200 bg-white' : 'border-gray-300 bg-white'}`}
                  disabled={posLoading}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Tipo de cuenta</label>
                  <select
                    value={posTipoCuenta}
                    onChange={(e) => setPosTipoCuenta(e.target.value)}
                    className="w-full px-4 py-3 text-gray-900 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white"
                    disabled={posLoading}
                  >
                    <option value="CORRIENTE">Corriente</option>
                    <option value="AHORROS">Ahorros</option>
                    <option value="CREDITO">Crédito</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Monto (Bs)</label>
                  <input
                    type="text"
                    inputMode="none"
                    value={posMontoDebito}
                    onChange={(e) => {
                      const v = formatMontoInput(e.target.value);
                      if (v === '') { setPosMontoDebito(''); return; }
                      if (!/^\d*\.?\d{0,2}$/.test(v)) return;
                      const max = parseFloat(montoTotal) || 0;
                      const num = parseFloat(v);
                      if (!v.endsWith('.') && max > 0 && round2(num) > round2(max)) {
                        setPosMontoDebito(max.toFixed(2));
                      } else {
                        setPosMontoDebito(v);
                      }
                    }}
                    onFocus={() => setCampoActivo('monto')}
                    onBlur={() => {
                      const num = parseFloat(posMontoDebito) || 0;
                      const max = parseFloat(montoTotal) || 0;
                      if (round2(num) > round2(max)) setPosMontoDebito(max.toFixed(2));
                      else if (num > 0 && posMontoDebito && !posMontoDebito.endsWith('.')) setPosMontoDebito(num.toFixed(2));
                    }}
                    placeholder="0.00"
                    className={`w-full px-4 py-3 text-lg text-gray-900 border rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 ${campoActivo === 'monto' ? 'border-slate-400 ring-2 ring-slate-200 bg-white' : 'border-gray-300 bg-white'}`}
                    disabled={posLoading}
                  />
                </div>
              </div>
            </div>

            {!posLoading && (
              <div>
                <p className="text-sm text-gray-500 mb-2">Teclado</p>
                <TecladoNumerico
                  value={campoActivo === 'cedula' ? posCedulaTitular : posMontoDebito}
                  onChange={(v) => {
                    if (campoActivo === 'cedula') {
                      setPosCedulaTitular(v.replace(/\D/g, '').slice(0, 8));
                    } else {
                      const cleaned = v.replace(/[^0-9.]/g, '');
                      if (cleaned === '') { setPosMontoDebito(''); return; }
                      if (!/^\d*\.?\d{0,2}$/.test(cleaned)) return;
                      const max = parseFloat(montoTotal) || 0;
                      const num = parseFloat(cleaned);
                      if (max > 0 && round2(num) > round2(max)) {
                        setPosMontoDebito(max.toFixed(2));
                      } else {
                        setPosMontoDebito(cleaned);
                      }
                    }
                  }}
                  maxLength={campoActivo === 'cedula' ? 8 : 15}
                  allowDecimal={campoActivo === 'monto'}
                  accentColor="#f26d0a"
                />
              </div>
            )}

            {transaccionesEstaSesion.length > 0 && (
              <div className="rounded-xl border border-slate-200 bg-slate-50 overflow-hidden">
                <div className="px-4 py-2 border-b border-slate-200">
                  <span className="text-sm font-medium text-slate-700">
                    Aprobado en esta sesión: Bs {transaccionesEstaSesion.reduce((s, t) => s + (t.amount || 0), 0).toFixed(2)}
                  </span>
                </div>
                <div className="divide-y divide-slate-200 max-h-20 overflow-y-auto">
                  {transaccionesEstaSesion.map((t, i) => (
                    <div key={i} className="flex items-center justify-between px-4 py-2 text-sm text-slate-700">
                      <span>Bs {parseFloat(t.amount || 0).toFixed(2)}</span>
                      <span>Ref: {t.refCorta}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {posRespuesta && (
              <div
                className={`p-4 rounded-xl text-center text-sm ${
                  posRespuesta.exito
                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                    : 'bg-red-50 text-red-800 border border-red-200'
                }`}
              >
                <p className="font-medium">{posRespuesta.mensaje}</p>
                {!posRespuesta.exito && (
                  <p className="mt-1 text-gray-600">Puede reintentar</p>
                )}
              </div>
            )}
          </div>

          <div className="flex flex-col gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button
              type="button"
              onClick={enviarSolicitudPosDebito}
              disabled={posLoading || !posCedulaTitular}
              className="w-full py-4 px-6 rounded-xl disabled:opacity-50 flex items-center justify-center gap-2 font-semibold text-lg text-white hover:opacity-90 focus:ring-2 focus:ring-orange-400 focus:outline-none transition-colors"
              style={{ backgroundColor: '#f26d0a' }}
            >
              {posLoading ? (
                <>
                  <svg className="animate-spin h-6 w-6" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  Procesando...
                </>
              ) : (
                <>Enviar al datáfono</>
              )}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={posLoading}
              className="w-full py-3 text-gray-600 hover:text-gray-800 font-medium disabled:opacity-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
