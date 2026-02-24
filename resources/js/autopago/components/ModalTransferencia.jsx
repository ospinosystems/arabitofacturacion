import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import TecladoNumerico from './TecladoNumerico';
import { PAYMENT_METHOD_COLORS } from '../config';

/** Validar teléfono: 0 + código + 7 dígitos = 11 dígitos */
const validarTelefono = (telefono) => {
  const regex = /^0(412|414|416|424|426|422)\d{7}$/;
  return regex.test(telefono);
};

/** Formatear teléfono de 04XX a 58XX */
const formatearTelefono = (telefono) => {
  if (!telefono) return '';
  let numeros = telefono.replace(/\D/g, '');
  if (numeros.startsWith('0')) numeros = '58' + numeros.slice(1);
  if (!numeros.startsWith('58')) numeros = '58' + numeros;
  return numeros;
};

const getTodayDate = () => {
  const today = new Date();
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
};

const number = (v) => String(v).replace(/\D/g, '');

/** Formatea monto para input (permite decimales) */
const formatMontoInput = (v) => {
  const s = String(v || '').replace(/[^0-9.,]/g, '').replace(',', '.');
  const parts = s.split('.');
  if (parts.length > 2) return parts[0] + '.' + parts.slice(1).join('');
  return s;
};

/** Redondea a 2 decimales para evitar errores de punto flotante al comparar montos */
const round2 = (x) => Math.round(parseFloat(x) * 100) / 100;

const BANCOS = [
  { value: '0156', label: '100% Banco - 0156' },
  { value: '0171', label: 'Activo - 0171' },
  { value: '0916', label: 'Agil - 0916' },
  { value: '0166', label: 'Agricola de Venezuela - 0166' },
  { value: '0172', label: 'Bancamiga - 0172' },
  { value: '0114', label: 'BanCaribe - 0114' },
  { value: '0168', label: 'BanCrecer - 0168' },
  { value: '0134', label: 'Banesco - 0134' },
  { value: '0174', label: 'Banplus - 0174' },
  { value: '0175', label: 'BDT - 0175' },
  { value: '0151', label: 'BFC - 0151' },
  { value: '0116', label: 'BOD - 0116' },
  { value: '0128', label: 'Caroni - 0128' },
  { value: '0163', label: 'del Tesoro - 0163' },
  { value: '0157', label: 'DelSur - 0157' },
  { value: '0176', label: 'Espirito Santo - 0176' },
  { value: '0115', label: 'Exterior - 0115' },
  { value: '0177', label: 'Fuerza Armada Nacional - 0177' },
  { value: '0146', label: 'Gente Emprendedora C.A - 0146' },
  { value: '0003', label: 'Industrial de Venezuela - 0003' },
  { value: '0105', label: 'Mercantil - 0105' },
  { value: '0169', label: 'R4 Banco - 0169' },
  { value: '0905', label: 'Mpandco - 0905' },
  { value: '0914', label: 'Mueve - 0914' },
  { value: '0903', label: 'MultiPagos - 0903' },
  { value: '0191', label: 'Nacional de Credito - 0191' },
  { value: '0138', label: 'Plaza - 0138' },
  { value: '0108', label: 'Provincial - 0108' },
  { value: '0149', label: 'Pueblo Soberano - 0149' },
  { value: '0917', label: 'Sodexo - 0917' },
  { value: '0137', label: 'Sofitasa - 0137' },
  { value: '0104', label: 'Venezolano de Credito - 0104' },
  { value: '0102', label: 'Venezuela - 0102' },
  { value: '0911', label: 'Venmo - 0911' },
];

/** Modal Transferencia/Pago Móvil - idéntico a ModalRefPago (AutoValidar) */
export default function ModalTransferencia({
  open,
  onClose,
  montoTotal,
  pedidoId,
  cedula,
  telefonoCliente,
  onSuccess,
}) {
  const [referencia, setReferencia] = useState('');
  const [telefonoPago, setTelefonoPago] = useState('');
  const [codigoBancoOrigen, setCodigoBancoOrigen] = useState('');
  const [fechaPago, setFechaPago] = useState(getTodayDate());
  const [monto, setMonto] = useState('');
  const [errors, setErrors] = useState({});
  const [autoValidando, setAutoValidando] = useState(false);
  const [resultadoValidacion, setResultadoValidacion] = useState(null);
  const [campoActivo, setCampoActivo] = useState('monto'); // 'monto' | 'referencia' | 'telefono'
  const validandoRef = useRef(false);

  useEffect(() => {
    if (open && montoTotal > 0) {
      setMonto(montoTotal.toFixed(2));
      setReferencia('');
      setTelefonoPago((telefonoCliente || '').replace(/\D/g, '').slice(0, 11));
      setCodigoBancoOrigen('');
      setFechaPago(getTodayDate());
      setErrors({});
      setResultadoValidacion(null);
    }
  }, [open, montoTotal, telefonoCliente]);

  useEffect(() => {
    if (open) {
      const input = document.getElementById('autopago-ref-input');
      if (input) setTimeout(() => input.focus(), 100);
    }
  }, [open]);

  const validateForm = () => {
    const newErrors = {};
    if (!referencia || referencia.trim() === '') return false;
    if (!telefonoPago) {
      newErrors.telefono = 'El teléfono es obligatorio';
    } else if (!validarTelefono(telefonoPago)) {
      newErrors.telefono = 'Formato inválido. Ej: 04141234567';
    }
    if (!codigoBancoOrigen) newErrors.banco = 'El banco origen es obligatorio';
    if (!fechaPago) newErrors.fecha = 'La fecha de pago es obligatoria';
    const maxDisponible = parseFloat(montoTotal) || 0;
    if (!monto || parseFloat(monto) <= 0) newErrors.monto = 'El monto no puede ser 0';
    else if (round2(monto) > round2(maxDisponible)) newErrors.monto = 'El monto no puede ser mayor al disponible a pagar';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleAutoValidar = async () => {
    if (validandoRef.current || resultadoValidacion?.success) return;
    if (!validateForm()) return;

    validandoRef.current = true;
    setAutoValidando(true);
    setResultadoValidacion(null);

    const referenciaFormateada = referencia.padStart(12, '0');

    try {
      const response = await axios.post('/autovalidar-transferencia', {
        referencia: referenciaFormateada,
        telefono: formatearTelefono(telefonoPago),
        banco_origen: codigoBancoOrigen,
        fecha_pago: fechaPago,
        monto,
        id_pedido: pedidoId,
        cedula: cedula || '',
      });

      if (response.data.estado === true) {
        setResultadoValidacion({
          success: true,
          message: response.data.msj || '¡Transferencia validada y aprobada exitosamente!',
        });
        setTimeout(() => {
          onSuccess({
            type: 'transfer',
            description: 'Transferencia / Pago móvil',
            amount: parseFloat(monto),
            status: 'validated',
            referencia: response.data.data?.referencia_completa || referenciaFormateada,
            banco: response.data.data?.banco || '0134',
          });
          onClose();
        }, 1500);
      } else if (response.data.monto_diferente) {
        setResultadoValidacion({
          success: false,
          montoDiferente: true,
          message: response.data.msj,
          data: response.data,
        });
      } else {
        setResultadoValidacion({
          success: false,
          message: response.data.msj || 'No se pudo validar la transferencia',
        });
      }
    } catch (error) {
      setResultadoValidacion({
        success: false,
        message: error.response?.data?.msj || 'Error de conexión al validar la transferencia',
      });
    } finally {
      setAutoValidando(false);
      validandoRef.current = false;
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Escape' && !autoValidando) onClose();
    if (e.key === 'Enter' && !autoValidando && !resultadoValidacion?.success) {
      e.preventDefault();
      handleAutoValidar();
    }
  };

  if (!open) return null;

  return (
    <>
      <div
        className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        onKeyDown={handleKeyDown}
        onClick={onClose}
        tabIndex={-1}
      >
        <div
          className="bg-white rounded-xl shadow-xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-hidden flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          <div
            className="px-6 py-4 rounded-t-lg flex-shrink-0"
            style={{ backgroundColor: PAYMENT_METHOD_COLORS.transfer.bg, color: PAYMENT_METHOD_COLORS.transfer.textOnBg }}
          >
            <h3 className="text-lg font-bold flex items-center gap-2">
              <i className="fa fa-exchange" />
              Referencia de Pago
            </h3>
            
          </div>

          <div className="p-6 space-y-4 overflow-y-auto flex-1 bg-gray-50">
          <form onSubmit={(e) => { e.preventDefault(); handleAutoValidar(); }} className="space-y-4">
            {/* Monto a la izquierda (centrado) e imagen a la derecha */}
            <div
              className="rounded-xl overflow-hidden border-2 flex flex-col sm:flex-row bg-white min-h-[280px] sm:min-h-[360px]"
              style={{ borderColor: PAYMENT_METHOD_COLORS.transfer.border }}
            >
              <div className="flex-1 flex flex-col items-center justify-center p-6 bg-gray-50/80 border-b sm:border-b-0 sm:border-r border-gray-200">
                <p className="text-sm font-semibold text-gray-600 mb-1">Monto a pagar</p>
                <p className="text-4xl sm:text-5xl md:text-6xl font-black text-green-600 tracking-tight text-center">
                  Bs. {parseFloat(monto || 0).toFixed(2)}
                </p>
              </div>
              <div className="flex-1 flex justify-center items-center p-4 min-h-[240px] sm:min-h-0">
                <img
                  src="/images/pagomovil21628222_0134.jpeg"
                  alt="Datos para transferencia o pago móvil"
                  className="w-full h-auto max-h-[320px] sm:max-h-[420px] object-contain"
                />
              </div>
            </div>

            <div
              className="rounded-xl px-4 py-3 border-2 bg-amber-50 border-amber-300 text-center"
              role="alert"
            >
              <p className=" sm:text-lg font-bold text-gray-900">
                Debe colocar los datos de la transferencia una vez hecho el pago a los datos de arriba.
              </p>
              <p className="text-sm sm: font-semibold text-amber-800 mt-1">
                Al completar los datos, pulse <span className="font-black text-green-700">Validar</span>.
              </p>
            </div>

            {/* Inputs en una sola línea, más grandes */}
            <div
              className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 p-4 rounded-xl border-2"
              style={{
                backgroundColor: PAYMENT_METHOD_COLORS.transfer.bgLight,
                borderColor: PAYMENT_METHOD_COLORS.transfer.border,
              }}
            >
              <div>
                <label className="block text-sm font-bold text-gray-900 mb-1">Monto (Bs)</label>
                <input
                  type="text"
                  inputMode="none"
                  value={monto}
                onChange={(e) => {
                  const v = formatMontoInput(e.target.value);
                  if (v === '') { setMonto(''); return; }
                  if (!/^\d*\.?\d{0,2}$/.test(v)) return;
                  const max = parseFloat(montoTotal) || 0;
                  const num = parseFloat(v);
                  if (!v.endsWith('.') && max > 0 && round2(num) > round2(max)) {
                    setMonto(max.toFixed(2));
                  } else {
                    setMonto(v);
                  }
                }}
                  onFocus={() => setCampoActivo('monto')}
                onBlur={() => {
                  const num = parseFloat(monto) || 0;
                  const max = parseFloat(montoTotal) || 0;
                  if (round2(num) > round2(max) && max > 0) setMonto(max.toFixed(2));
                    else if (num > 0 && monto && !monto.endsWith('.')) setMonto(num.toFixed(2));
                  }}
                  placeholder="0.00"
                  className={`w-full px-3 py-3 border-2 rounded-xl font-semibold text-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 min-h-[52px] ${campoActivo === 'monto' ? 'border-orange-500 ring-2 ring-orange-200' : 'border-gray-300'}`}
                />
                <p className="text-xs font-medium text-gray-600 mt-0.5">Máx: Bs {parseFloat(montoTotal || 0).toFixed(2)}</p>
              </div>
              <div>
                <label className="block text-sm font-bold text-gray-900 mb-1">Referencia (12)</label>
                <input
                  id="autopago-ref-input"
                  type="text"
                  inputMode="none"
                  placeholder="Referencia..."
                  value={referencia}
                  onChange={(e) => setReferencia(number(e.target.value).slice(0, 12))}
                  onFocus={() => setCampoActivo('referencia')}
                  onKeyDown={handleKeyDown}
                  maxLength={12}
                  className={`w-full px-3 py-3 border-2 rounded-xl font-semibold text-lg min-h-[52px] focus:ring-2 focus:ring-orange-500 focus:border-orange-500 ${campoActivo === 'referencia' ? 'border-orange-500 ring-2 ring-orange-200' : 'border-gray-300'} ${errors.descripcion ? 'border-red-300' : ''}`}
                />
              </div>
              <div>
                <label className="block text-sm font-bold text-gray-900 mb-1">Banco origen</label>
                <select
                  value={codigoBancoOrigen}
                  onChange={(e) => setCodigoBancoOrigen(e.target.value)}
                  onKeyDown={handleKeyDown}
                  className={`w-full px-3 py-3 border-2 rounded-xl font-semibold  min-h-[52px] focus:ring-2 focus:ring-orange-500 focus:border-orange-500 border-gray-300 ${errors.banco ? 'border-red-300' : ''}`}
                >
                  <option value="">Seleccionar...</option>
                  {BANCOS.map((b) => (
                    <option key={b.value} value={b.value}>{b.label}</option>
                  ))}
                </select>
                {errors.banco && <p className="text-xs text-red-600 mt-0.5">{errors.banco}</p>}
              </div>
              <div>
                <label className="block text-sm font-bold text-gray-900 mb-1">Teléfono</label>
                <input
                  type="text"
                  inputMode="none"
                  placeholder="04141234567"
                  value={telefonoPago}
                  onChange={(e) => setTelefonoPago(e.target.value.replace(/\D/g, '').slice(0, 11))}
                  onFocus={() => setCampoActivo('telefono')}
                  onKeyDown={handleKeyDown}
                  maxLength={11}
                  className={`w-full px-3 py-3 border-2 rounded-xl font-semibold text-lg min-h-[52px] focus:ring-2 focus:ring-orange-500 focus:border-orange-500 ${campoActivo === 'telefono' ? 'border-orange-500 ring-2 ring-orange-200' : 'border-gray-300'} ${errors.telefono ? 'border-red-300' : ''}`}
                />
                {errors.telefono && <p className="text-xs text-red-600 mt-0.5">{errors.telefono}</p>}
              </div>
             
              <div>
                <label className="block text-sm font-bold text-gray-900 mb-1">Fecha pago</label>
                <input
                  type="date"
                  value={fechaPago}
                  onChange={(e) => setFechaPago(e.target.value)}
                  onKeyDown={handleKeyDown}
                  className={`w-full px-3 py-3 border-2 rounded-xl font-semibold  min-h-[52px] focus:ring-2 focus:ring-orange-500 focus:border-orange-500 border-gray-300 ${errors.fecha ? 'border-red-300' : ''}`}
                />
              </div>
            </div>

            {!autoValidando && !resultadoValidacion?.success && (
              <div className="pt-2">
                <TecladoNumerico
                  value={campoActivo === 'monto' ? monto : campoActivo === 'referencia' ? referencia : telefonoPago}
                  onChange={(v) => {
                    if (campoActivo === 'monto') {
                      const cleaned = v.replace(/[^0-9.]/g, '');
                      if (cleaned === '') { setMonto(''); return; }
                      if (!/^\d*\.?\d{0,2}$/.test(cleaned)) return;
                      const max = parseFloat(montoTotal) || 0;
                      const num = parseFloat(cleaned);
                      if (max > 0 && round2(num) > round2(max)) {
                        setMonto(max.toFixed(2));
                      } else {
                        setMonto(cleaned);
                      }
                    } else if (campoActivo === 'referencia') {
                      setReferencia(v.replace(/\D/g, '').slice(0, 12));
                    } else {
                      setTelefonoPago(v.replace(/\D/g, '').slice(0, 11));
                    }
                  }}
                  maxLength={campoActivo === 'monto' ? 15 : campoActivo === 'referencia' ? 12 : 11}
                  allowDecimal={campoActivo === 'monto'}
                  accentColor={PAYMENT_METHOD_COLORS.transfer.bg}
                />
              </div>
            )}

            {resultadoValidacion && (
              <div
                className={`p-4 rounded-lg text-center font-semibold ${
                  resultadoValidacion.success
                    ? 'bg-green-100 text-green-800 border border-green-300'
                    : resultadoValidacion.montoDiferente
                      ? 'bg-amber-100 text-amber-800 border border-amber-300'
                      : 'bg-red-100 text-red-800 border border-red-300'
                }`}
              >
                <p className="text-lg">
                  {resultadoValidacion.success ? '✓ Validada' : resultadoValidacion.montoDiferente ? '⚠ Monto diferente' : ''}
                </p>
                <p className="text-sm mt-1">{resultadoValidacion.message}</p>
              </div>
            )}
          </form>
          </div>

          <div className="flex flex-col gap-3 px-6 py-5 bg-gray-100 rounded-b-xl flex-shrink-0 border-t border-gray-200">
            <button
              type="button"
              onClick={handleAutoValidar}
              disabled={autoValidando || resultadoValidacion?.success}
              className="w-full px-6 py-4 rounded-xl transition-colors disabled:opacity-50 flex items-center justify-center gap-3 font-bold text-xl text-white min-h-[56px] bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 disabled:!bg-gray-400"
            >
              {autoValidando ? (
                <>
                  <svg className="animate-spin h-6 w-6" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  Procesando...
                </>
              ) : resultadoValidacion?.success ? (
                <>
                  <i className="fa fa-check-circle text-2xl" />
                  Aprobada
                </>
              ) : (
                <>
                  <i className="fa fa-shield text-2xl" />
                  Autovalidar
                </>
              )}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={autoValidando}
              className="w-full px-6 py-3 border-2 border-gray-400 rounded-xl font-semibold text-gray-900 hover:bg-gray-200 transition-colors disabled:opacity-50 text-lg"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
