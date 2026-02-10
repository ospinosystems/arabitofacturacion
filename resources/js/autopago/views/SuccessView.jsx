import React from 'react';
import { CheckCircle, FileText } from 'lucide-react';

export default function SuccessView({ onFinish }) {
  return (
    <div className="h-full flex flex-col items-center justify-center px-10 animate-fade-in bg-gray-50">
      <div className="w-28 h-28 rounded-full bg-green-100 flex items-center justify-center mb-6">
        <CheckCircle size={64} className="text-green-600" strokeWidth={2} />
      </div>
      <h2 className="text-5xl font-extrabold text-gray-800 text-center mb-4 tracking-tight">
        ¡Compra exitosa!
      </h2>
      <div className="flex items-center gap-4 mb-6 p-5 rounded-xl bg-white border border-gray-100 shadow-sm max-w-md">
        <FileText size={40} className="text-gray-500 flex-shrink-0" />
        <div>
          <p className="text-2xl font-semibold text-gray-800">
            Retira tu factura fiscal
          </p>
          <p className="text-lg text-gray-500 mt-0.5">
            En la impresora del quiosco
          </p>
        </div>
      </div>
      <p className="text-xl text-gray-500 text-center mb-8 font-medium">
        Gracias por tu compra
      </p>
      <button
        type="button"
        onClick={onFinish}
        className="w-full max-w-lg py-10 rounded-xl text-4xl font-bold text-white tracking-tight transition-all active:scale-[0.98] mb-12 kiosk-btn-primary"
        style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
      >
        Finalizar
      </button>
      <p className="text-lg text-gray-400 text-center max-w-md mb-6">
        Se reiniciará en 10 segundos si no tocas nada
      </p>
      <img
        src="/images/logo.png"
        alt="Logo"
        className="h-16 w-auto object-contain opacity-90"
      />
    </div>
  );
}
