import React, { useState } from 'react';
import { ShoppingCart, Fingerprint, CreditCard, DollarSign } from 'lucide-react';
import ModalConsultaPrecio from '../components/ModalConsultaPrecio';

const STEPS = [
  { icon: Fingerprint, label: 'Identificarse' },
  { icon: ShoppingCart, label: 'Escanear productos' },
  { icon: CreditCard, label: 'Pagar' },
];

export default function WelcomeView({ onStart }) {
  const [showConsultaPrecio, setShowConsultaPrecio] = useState(false);

  return (
    <div className="h-full flex flex-col items-center justify-center px-12 animate-fade-in bg-gray-50 relative">
      <img
        src="/images/logo.png"
        alt="Logo"
        className="h-40 object-contain mb-6"
        onError={(e) => {
          e.target.style.display = 'none';
        }}
      />
      <h1 className="text-[5rem] sm:text-[7rem] md:text-[8rem] text-center mb-4 autopago-hero-word welcome-autopago">
        Autopago
      </h1>
      <p className="text-2xl text-gray-600 text-center mb-14 max-w-lg font-medium">
        Compre de forma rápida y sencilla
      </p>
      <div className="flex items-center justify-center gap-4 mb-14">
        {STEPS.map(({ icon: Icon, label }, i) => (
          <React.Fragment key={i}>
            <div className="flex flex-col items-center min-w-[140px] flex-shrink-0">
              <div
                className="h-20 rounded-full flex items-center justify-center flex-shrink-0 aspect-square overflow-hidden"
                style={{ backgroundColor: 'rgba(242, 109, 10, 0.12)' }}
              >
                <Icon size={32} strokeWidth={2} style={{ color: 'rgba(242, 109, 10, 0.95)' }} />
              </div>
              <span className="text-lg font-medium text-gray-600 mt-2 text-center w-full">
                {label}
              </span>
            </div>
            {i < STEPS.length - 1 && (
              <div
                className="w-8 h-0.5 flex-shrink-0 self-center"
                style={{ backgroundColor: 'rgba(242, 109, 10, 0.3)' }}
              />
            )}
          </React.Fragment>
        ))}
      </div>
      <button
        type="button"
        onClick={onStart}
        className="w-full max-w-2xl py-12 rounded-2xl text-5xl font-bold text-white tracking-tight animate-pulse-slow transition-all active:scale-[0.98] kiosk-btn-primary"
        style={{
          backgroundColor: 'rgba(242, 109, 10, 0.95)',
          boxShadow: '0 4px 20px rgba(242, 109, 10, 0.35)',
        }}
      >
        Toca para empezar
      </button>
      <div className="absolute bottom-6 left-0 right-0 flex justify-center px-12">
        <button
          type="button"
          onClick={() => setShowConsultaPrecio(true)}
          className="flex items-center gap-2 px-5 py-3 rounded-xl text-base font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 hover:text-gray-800 transition-colors"
        >
          <DollarSign size={20} strokeWidth={2} />
          Consultar precio
        </button>
      </div>
      <ModalConsultaPrecio open={showConsultaPrecio} onClose={() => setShowConsultaPrecio(false)} />
    </div>
  );
}
