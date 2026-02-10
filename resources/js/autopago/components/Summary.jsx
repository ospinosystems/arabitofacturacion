import React from 'react';
import { IVA_RATE, formatUsd } from '../config';

export default function Summary({ items, clearCart }) {
  const subtotal = items.reduce(
    (sum, i) => sum + i.price * i.quantity,
    0
  );
  const iva = subtotal * IVA_RATE;
  const total = subtotal + iva;

  const handlePayNow = () => {
    alert('Pago simulado. Gracias por su compra.');
    clearCart?.();
  };

  return (
    <div className="bg-white pt-8 pb-16 px-8 shadow-[0_-8px_32px_rgba(0,0,0,0.15)]">
      <div className="mb-6">
        <div className="flex justify-between text-2xl text-gray-500 mb-2">
          <span>Subtotal:</span>
          <span>${formatUsd(subtotal)}</span>
        </div>
        <div className="flex justify-between text-2xl text-gray-500 mb-2">
          <span>IVA (16%):</span>
          <span>${formatUsd(iva)}</span>
        </div>
      </div>

      <div className="flex justify-between items-baseline mb-8">
        <span className="text-4xl font-bold text-gray-700">TOTAL</span>
        <span className="text-9xl font-black text-black">
          ${formatUsd(total)}
        </span>
      </div>

      <button
        type="button"
        onClick={handlePayNow}
        disabled={items.length === 0}
        className="w-full h-[150px] rounded-2xl flex items-center justify-center text-6xl font-black text-white uppercase tracking-tight transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 mb-16"
        style={{
          background: `linear-gradient(135deg, rgba(242, 109, 10, 0.95) 0%, rgba(224, 92, 0, 0.95) 100%)`,
          boxShadow: '0 8px 24px rgba(242, 109, 10, 0.4)',
        }}
      >
        PAGAR AHORA
      </button>
    </div>
  );
}
