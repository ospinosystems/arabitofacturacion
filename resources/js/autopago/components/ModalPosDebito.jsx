import React, { useState, useEffect, useRef } from 'react';
import { api } from '../services/api';
import TecladoNumerico from './TecladoNumerico';
import { PAYMENT_METHOD_COLORS, POS_INSTRUCCIONES, POS_INSTRUCCIONES_KEYWORDS } from '../config';

/** Envuelve las palabras clave del texto en <strong> para resaltarlas */
function resaltarClaves(texto, palabrasClave) {
  if (!palabrasClave?.length) return texto;
  const escapadas = palabrasClave.map((p) => p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
  const re = new RegExp(`(${escapadas.join('|')})`, 'gi');
  const partes = texto.split(re);
  return partes.map((parte, i) =>
    parte && palabrasClave.some((kw) => kw.toLowerCase() === parte.toLowerCase()) ? (
      <strong key={i} className="font-extrabold text-gray-900">
        {parte}
      </strong>
    ) : (
      parte
    )
  );
}

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
        <div className="bg-white rounded-xl shadow-xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
          <div
            className="px-4 py-2 rounded-t-lg flex-shrink-0 flex items-center justify-between gap-3"
            style={{ backgroundColor: PAYMENT_METHOD_COLORS.card.bg, color: PAYMENT_METHOD_COLORS.card.textOnBg }}
          >
            <h3 className=" font-bold flex items-center gap-2">
              <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
              Punto de Venta - Débito
            </h3>
            <span className="text-sm font-semibold opacity-95 whitespace-nowrap">
              Total: Bs {parseFloat(posMontoDebito || 0).toFixed(2)}
            </span>
          </div>

          <div className="p-6 space-y-4 overflow-y-auto flex-1 bg-gray-50">
            {/* Instrucciones por pasos: resaltan según lo que va completando el cliente */}
            {(() => {
              const requestSent = posLoading || transaccionesEstaSesion.length > 0;
              const allDone = transaccionesEstaSesion.length > 0 && !posLoading;
              const stepStatus = [
                requestSent ? 'completed' : 'current',   // 1: deja de resaltar tras enviar
                posLoading ? 'current' : (allDone ? 'completed' : 'pending'),
                allDone ? 'completed' : 'pending',
              ];
              return (
                <div
                  className="rounded-xl p-3 border-2 border-amber-200 bg-amber-50"
                >
                  <p className="text-3xl font-extrabold mb-2 text-gray-900 tracking-tight">
                    Siga estos pasos:
                  </p>
                  <ul className="space-y-2">
                    {POS_INSTRUCCIONES.map((inst, i) => {
                      const status = stepStatus[i];
                      const isCurrent = status === 'current';
                      const isCompleted = status === 'completed';
                      const esEnviarLector = i === 0;
                      return (
                        <li
                          key={i}
                          className={`flex items-start gap-3 rounded-lg py-2.5 px-3 transition-all duration-200 ${
                            esEnviarLector && isCurrent
                              ? 'bg-amber-300 border-2 border-amber-600 shadow-lg ring-2 ring-amber-400'
                              : isCurrent
                                ? 'bg-amber-200 border-2 border-amber-500 shadow-md'
                                : isCompleted
                                  ? 'bg-green-50 border border-green-200'
                                  : 'bg-white/80 border border-gray-200'
                          }`}
                        >
                          <span
                            className={`flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-xl font-bold ${
                              isCompleted
                                ? 'bg-green-600 text-white'
                                : isCurrent
                                  ? 'bg-amber-600 text-white'
                                  : 'bg-gray-300 text-gray-600'
                            }`}
                          >
                            {isCompleted ? '✓' : i + 1}
                          </span>
                          <span
                            className={`pt-0.5 text-2xl font-semibold leading-snug ${
                              esEnviarLector && isCurrent
                                ? 'text-amber-950'
                                : isCurrent
                                  ? 'text-amber-900'
                                  : isCompleted
                                    ? 'text-green-800'
                                    : 'text-gray-600'
                            }`}
                          >
                            {resaltarClaves(inst, POS_INSTRUCCIONES_KEYWORDS[i])}
                          </span>
                        </li>
                      );
                    })}
                  </ul>
                </div>
              );
            })()}

            <div
              className="border rounded-lg p-3"
              style={{
                backgroundColor: PAYMENT_METHOD_COLORS.card.bgLight,
                borderColor: PAYMENT_METHOD_COLORS.card.border,
              }}
            >
              <div className="flex justify-between items-center text-sm font-semibold text-gray-900">
                <span>Pendiente:</span>
                <span>Bs {parseFloat(montoTotal || 0).toFixed(2)}</span>
              </div>
              {transaccionesEstaSesion.length > 0 && (
                <div className="flex justify-between items-center text-sm mt-1 pt-1 border-t font-semibold" style={{ borderColor: PAYMENT_METHOD_COLORS.card.border }}>
                  <span className="text-green-900 font-semibold">Aprobado en esta sesión:</span>
                  <span className="text-green-900 font-semibold">Bs {transaccionesEstaSesion.reduce((s, t) => s + (t.amount || 0), 0).toFixed(2)}</span>
                </div>
              )}
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="bg-white rounded-xl border-2 border-amber-200 shadow-md p-3 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 transition-all">
                <label className="block  font-bold text-gray-900 mb-2">Cédula del Titular</label>
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
                  className={`w-full px-4 py-3 text-xl font-semibold text-gray-900 border-2 rounded-lg focus:outline-none ${campoActivo === 'cedula' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-300' : 'border-gray-300'}`}
                  disabled={posLoading}
                />
              </div>
              <div className="bg-white rounded-xl border-2 border-amber-200 shadow-md p-3 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 transition-all">
                <label className="block  font-bold text-amber-900 mb-2">Tipo de Cuenta</label>
                <select
                  value={posTipoCuenta}
                  onChange={(e) => setPosTipoCuenta(e.target.value)}
                  className="w-full px-4 py-3 text-xl font-semibold text-gray-900 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 bg-white"
                  disabled={posLoading}
                >
                  <option value="CORRIENTE">CORRIENTE</option>
                  <option value="AHORROS">AHORROS</option>
                  <option value="CREDITO">CREDITO</option>
                </select>
              </div>
              <div className="bg-white rounded-xl border-2 border-amber-200 shadow-md p-3 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 transition-all">
                <label className="block  font-bold text-gray-900 mb-2">Monto a pagar (Bs)</label>
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
                  className={`w-full px-4 py-3 text-xl font-bold text-gray-900 rounded-lg focus:outline-none ${campoActivo === 'monto' ? 'border-2 border-amber-500 bg-amber-50 ring-2 ring-amber-300' : 'border-2 border-gray-300'}`}
                  disabled={posLoading}
                />
                <p className="text-sm font-semibold text-gray-900 mt-1">Máx: Bs {parseFloat(montoTotal || 0).toFixed(2)}</p>
              </div>
            </div>

            {!posLoading && (
              <div className="pt-2">
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
                  accentColor={PAYMENT_METHOD_COLORS.card.bg}
                />
              </div>
            )}

            {transaccionesEstaSesion.length > 0 && (
              <div className="border border-green-200 rounded-lg overflow-hidden">
                <div className="bg-green-50 px-3 py-2 border-b border-green-200">
                  <span className="text-sm font-semibold text-green-900">
                    Tarjetas aprobadas ({transaccionesEstaSesion.length})
                  </span>
                </div>
                <div className="divide-y divide-green-100 max-h-24 overflow-y-auto">
                  {transaccionesEstaSesion.map((t, i) => (
                    <div key={i} className="flex items-center justify-between px-3 py-2 bg-white text-sm">
                      <span className="font-medium text-gray-900">Bs {parseFloat(t.amount || 0).toFixed(2)}</span>
                      <span className="text-gray-800 font-medium">CI: {t.cedula}</span>
                      <span className="text-green-600 font-medium">Ref: {t.refCorta}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {posRespuesta && (
              <div
                className={`p-4 rounded-lg text-center font-semibold ${
                  posRespuesta.exito
                    ? 'bg-green-100 text-green-900 border border-green-300'
                    : 'bg-red-100 text-red-900 border border-red-300'
                }`}
              >
                <p className="text-lg font-semibold">{posRespuesta.mensaje}</p>
                {!posRespuesta.exito && (
                  <p className="text-sm mt-2 font-medium text-gray-900">Puede reintentar</p>
                )}
              </div>
            )}
          </div>

          <div className="flex flex-col gap-3 px-6 py-4 bg-gray-100 rounded-b-lg flex-shrink-0 border-t border-gray-200">
            <button
              type="button"
              onClick={enviarSolicitudPosDebito}
              disabled={posLoading || !posCedulaTitular}
              className="w-full py-5 px-6 rounded-xl transition-all duration-200 disabled:opacity-50 flex items-center justify-center gap-3 font-bold text-xl shadow-lg"
              style={{
                backgroundColor: posLoading || !posCedulaTitular ? '#9ca3af' : '#059669',
                color: '#fff',
              }}
              onMouseEnter={(e) => {
                if (!posLoading && posCedulaTitular) {
                  e.currentTarget.style.backgroundColor = '#047857';
                  e.currentTarget.style.transform = 'scale(1.01)';
                }
              }}
              onMouseLeave={(e) => {
                if (!posLoading && posCedulaTitular) {
                  e.currentTarget.style.backgroundColor = '#059669';
                  e.currentTarget.style.transform = 'scale(1)';
                }
              }}
            >
              {posLoading ? (
                <>
                  <svg className="animate-spin h-8 w-8 flex-shrink-0" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  Procesando...
                </>
              ) : (
                <>
                  <svg className="w-10 h-10 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                  </svg>
                  <span className="text-3xl font-bold">Enviar al datáfono</span>
                </>
              )}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={posLoading}
              className="w-full px-4 py-3 border-2 border-gray-400 rounded-xl font-semibold text-gray-900 hover:bg-gray-200 transition-colors disabled:opacity-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
