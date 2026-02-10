import React, { useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { TASA_DOLAR_BS, formatBs, formatUsd } from '../config';

export default function CartItem({ item }) {
  const { tryAddItem, decrementItem, removeItem } = useCart();
  const [showConfirm, setShowConfirm] = useState(false);

  const lineTotalBs = item.price * item.quantity;
  const lineTotalUsd = item.precio != null ? item.precio * item.quantity : lineTotalBs / TASA_DOLAR_BS;

  const handleDecrement = () => {
    if (item.quantity === 1) {
      setShowConfirm(true);
    } else {
      decrementItem(item.id);
    }
  };

  const handleConfirmRemove = () => {
    removeItem(item.id);
    setShowConfirm(false);
  };

  const handleCancelRemove = () => {
    setShowConfirm(false);
  };

  return (
    <>
      <div
        className="flex items-center gap-4 rounded-xl bg-white p-4 border border-gray-100 shadow-sm hover:shadow-md transition-shadow animate-fade-in"
        style={{ animation: 'fadeIn 0.3s ease-out' }}
      >
        <div className="flex-shrink-0 w-[100px] h-[100px] rounded-lg overflow-hidden bg-gray-50 ring-1 ring-gray-100">
          <img
            src={item.image || 'https://placehold.co/140x140/E5E7EB/6B7280?text=Producto'}
            alt={item.name}
            className="w-full h-full object-cover"
            onError={(e) => {
              e.target.src = 'https://placehold.co/140x140/E5E7EB/6B7280?text=Producto';
            }}
          />
        </div>

        <div className="flex-1 min-w-0">
          <h3 className="text-2xl font-bold text-gray-800 line-clamp-2">
            {item.name}
          </h3>
          {item.ean && (
            <p className="text-sm text-gray-500 mt-0.5">Cód. barras: {item.ean}</p>
          )}
          <p className="text-lg text-gray-700 mt-0.5">
            Bs. {formatBs(item.price)}
            {item.precio != null && (
              <span> (Ref. USD {formatUsd(item.precio)})</span>
            )}
            {' '}× {item.quantity} {item.unit}
            {item.stock != null && (
              <span className="text-sm text-gray-500"> · Stock {item.stock}</span>
            )}
          </p>
          <p className="text-xl font-bold text-gray-900 mt-1">
            Bs. {formatBs(lineTotalBs)} <span className="text-lg font-medium text-gray-600">(Ref. USD {formatUsd(lineTotalUsd)})</span>
          </p>
        </div>

        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            type="button"
            onClick={handleDecrement}
            className="w-16 h-16 rounded-lg flex items-center justify-center transition-all active:scale-95"
            style={{ backgroundColor: 'rgba(242, 109, 10, 0.9)' }}
            aria-label="Restar cantidad"
          >
            <Minus className="text-white" size={24} strokeWidth={3} />
          </button>

          <span className="text-3xl font-bold text-gray-800 min-w-[48px] text-center tabular-nums">
            {item.quantity}
          </span>

          <button
            type="button"
            onClick={() => tryAddItem(item)}
            className="w-16 h-16 rounded-lg flex items-center justify-center transition-all active:scale-95"
            style={{ backgroundColor: 'rgba(242, 109, 10, 0.9)' }}
            aria-label="Sumar cantidad"
          >
            <Plus className="text-white" size={24} strokeWidth={3} />
          </button>
        </div>
      </div>

      {showConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-8">
          <div className="bg-white rounded-2xl p-10 max-w-xl shadow-xl text-center">
            <h3 className="text-4xl font-bold text-gray-800 mb-4">
              ¿Eliminar este producto?
            </h3>
            <p className="text-2xl text-gray-500 mb-6">{item.name}</p>
            <div className="flex gap-4 justify-center">
              <button
                type="button"
                onClick={handleCancelRemove}
                className="px-8 py-4 text-xl font-semibold rounded-xl bg-gray-100 text-gray-800 hover:bg-gray-200 transition-colors"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleConfirmRemove}
                className="px-8 py-4 text-xl font-semibold rounded-xl text-white transition-opacity"
                style={{ backgroundColor: 'rgba(242, 109, 10, 0.95)' }}
              >
                Sí, eliminar
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
