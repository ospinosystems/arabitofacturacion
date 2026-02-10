import React from 'react';
import { ArrowLeft } from 'lucide-react';
import Numpad from '../components/Numpad';

export default function IdentificationView({ idValue, onIdChange, onConfirm, onBack, loading }) {
  return (
    <div className="h-full flex flex-col px-10 py-8 animate-fade-in overflow-hidden relative bg-gray-50">
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
      <div className="flex items-center justify-center mb-8">
        <span className="px-4 py-2 rounded-lg text-sm font-medium text-gray-500 bg-white/80 border border-gray-200">
          Paso 1 de 3
        </span>
      </div>

      <div className="text-center mb-8">
        <h2 className="text-4xl sm:text-5xl font-bold text-gray-900 tracking-tight mb-3">
          Ingrese su cédula
        </h2>
        <p className="text-xl sm:text-2xl text-gray-600 font-medium max-w-lg mx-auto">
          Escriba su número con el teclado y pulse Continuar
        </p>
      </div>

      <input
        type="text"
        value={idValue}
        readOnly
        className="w-full max-w-md py-5 px-6 text-3xl font-semibold text-center border-2 border-gray-200 rounded-xl mb-8 bg-white mx-auto placeholder-gray-300 focus:outline-none shadow-sm"
        placeholder="Número de cédula"
      />
      <div className="flex-1 flex flex-col justify-center min-h-0">
        <Numpad value={idValue} onChange={onIdChange} maxLength={10} />
      </div>
      <button
        type="button"
        onClick={onConfirm}
        disabled={!idValue.trim() || loading}
        className="w-full max-w-2xl py-8 rounded-2xl text-4xl font-bold text-white tracking-tight transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed mb-16 mx-auto kiosk-btn-primary flex items-center justify-center gap-3 shadow-lg"
        style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
      >
        {loading ? (
          <>
            <span className="animate-spin h-8 w-8 border-2 border-white border-t-transparent rounded-full" />
            Buscando...
          </>
        ) : (
          'Continuar'
        )}
      </button>
    </div>
  );
}
