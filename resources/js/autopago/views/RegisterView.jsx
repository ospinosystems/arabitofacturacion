import React, { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import QWERTYKeyboard from '../components/QWERTYKeyboard';
import Numpad from '../components/Numpad';

const TELEFONO_PREFIXES = ['0414', '0424', '0416', '0426', '0412', '0422'];

function parseTelefono(value) {
  const digits = (value || '').replace(/\D/g, '');
  const prefix = digits.slice(0, 4);
  const suffix = digits.slice(4, 11);
  const validPrefix = TELEFONO_PREFIXES.includes(prefix) ? prefix : TELEFONO_PREFIXES[0];
  return { prefix: validPrefix, suffix };
}

function buildTelefono(prefix, suffix) {
  return prefix + (suffix || '');
}

function isValidTelefono(value) {
  const digits = (value || '').replace(/\D/g, '');
  if (digits.length !== 11) return false;
  const prefix = digits.slice(0, 4);
  return TELEFONO_PREFIXES.includes(prefix);
}

export default function RegisterView({ userData, onUserDataChange, onConfirm, onBack, loading }) {
  const [activeField, setActiveField] = useState('nombre');

  const handleKeyPress = (key) => {
    const current = userData[activeField] || '';
    onUserDataChange({ ...userData, [activeField]: current + key });
  };

  const handleBackspace = () => {
    const current = userData[activeField] || '';
    onUserDataChange({ ...userData, [activeField]: current.slice(0, -1) });
  };

  const handleNumpadChange = (val) => {
    if (activeField === 'telefono') {
      const { prefix, suffix } = parseTelefono(userData.telefono);
      const full = buildTelefono(prefix, val);
      onUserDataChange({ ...userData, telefono: full });
    } else {
      onUserDataChange({ ...userData, [activeField]: val });
    }
  };

  const handlePrefixChange = (prefix) => {
    const { suffix } = parseTelefono(userData.telefono);
    onUserDataChange({ ...userData, telefono: buildTelefono(prefix, suffix) });
  };

  const canConfirm =
    userData.nombre?.trim() &&
    isValidTelefono(userData.telefono || '');

  return (
    <div className="h-full flex flex-col px-8 py-8 animate-fade-in overflow-hidden relative bg-gray-50">
      {onBack && (
        <button
          type="button"
          onClick={onBack}
          className="absolute top-6 left-6 flex items-center gap-2 py-3 px-5 rounded-xl text-xl font-semibold text-gray-500 hover:bg-white hover:text-gray-700 transition-colors"
        >
          <ArrowLeft size={28} strokeWidth={2.5} />
          Volver
        </button>
      )}
      <div className="flex items-center justify-center mb-6">
        <span className="px-5 py-2.5 rounded-xl text-sm font-semibold bg-white text-gray-600 shadow-sm border border-gray-100 kiosk-step-badge">
          Paso 2 de 3
        </span>
      </div>
      <h2 className="text-5xl font-extrabold text-gray-800 text-center mb-3 tracking-tight">
        Registro
      </h2>
      <p className="text-xl text-gray-500 text-center mb-6 font-medium">
        Toca cada campo para completarlo
      </p>

      <div className="space-y-3 mb-4">
        <div>
          <label className="block text-sm font-semibold text-gray-600 mb-1">
            Nombre completo
          </label>
          <button
            type="button"
            onClick={() => setActiveField('nombre')}
            className={`w-full py-3 px-4 text-xl text-left rounded-lg transition-all ${
              activeField === 'nombre' ? 'border-2 bg-white shadow-sm' : 'border-2 border-gray-200 bg-white'
            }`}
            style={activeField === 'nombre' ? { borderColor: 'rgba(242, 109, 10, 0.6)' } : {}}
          >
            <span className={!userData.nombre ? 'text-gray-400' : 'text-gray-800'}>
              {userData.nombre || 'Ingrese su nombre y apellido'}
            </span>
          </button>
        </div>

        <div>
          <label className="block text-sm font-semibold text-gray-600 mb-1">
            Teléfono
          </label>
          <div
            className={`flex items-stretch rounded-lg overflow-hidden border-2 ${
              activeField === 'telefono' ? 'border-orange-400' : 'border-gray-200'
            }`}
          >
            <select
              value={parseTelefono(userData.telefono).prefix}
              onChange={(e) => handlePrefixChange(e.target.value)}
              className="flex-shrink-0 w-24 py-3 px-3 text-lg font-semibold text-gray-800 bg-gray-50 border-r-2 border-gray-200 focus:outline-none appearance-none cursor-pointer"
              style={{
                backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23737475'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E")`,
                backgroundRepeat: 'no-repeat',
                backgroundPosition: 'right 8px center',
                backgroundSize: '24px',
                paddingRight: '40px',
              }}
            >
              {TELEFONO_PREFIXES.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => setActiveField('telefono')}
              className="flex-1 py-3 px-4 text-xl text-left bg-white min-w-0"
            >
              <span className={parseTelefono(userData.telefono).suffix ? 'text-gray-800' : 'text-gray-400'}>
                {parseTelefono(userData.telefono).suffix || '7 dígitos'}
              </span>
            </button>
          </div>
        </div>
      </div>

      <div className="flex-1 min-h-0 overflow-auto mb-4 flex flex-col items-center">
        <p className="text-sm font-medium text-gray-500 mb-2">
          {activeField === 'telefono' ? 'Teclado numérico para teléfono' : 'Teclado para nombre'}
        </p>
        <div className="w-full max-w-md mx-auto">
          {activeField === 'telefono' ? (
            <>
              <Numpad
                value={parseTelefono(userData.telefono).suffix}
                onChange={(val) => handleNumpadChange(val)}
                maxLength={7}
              />
              {userData.telefono && !isValidTelefono(userData.telefono) && (
                <p className="mt-2 text-sm font-medium text-red-600">
                  Ingresa los 7 dígitos restantes
                </p>
              )}
            </>
          ) : (
            <QWERTYKeyboard onKeyPress={handleKeyPress} onBackspace={handleBackspace} />
          )}
        </div>
      </div>

      <button
        type="button"
        onClick={onConfirm}
        disabled={!canConfirm || loading}
        className="w-full max-w-md py-5 rounded-xl text-2xl font-bold text-white tracking-tight transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed mb-10 mx-auto kiosk-btn-primary flex items-center justify-center gap-2 shadow-lg"
        style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
      >
        {loading ? (
          <>
            <span className="animate-spin h-8 w-8 border-2 border-white border-t-transparent rounded-full" />
            Guardando...
          </>
        ) : (
          'Confirmar y comprar'
        )}
      </button>
    </div>
  );
}
