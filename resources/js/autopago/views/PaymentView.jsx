import React, { useState, useMemo } from 'react';
import { ArrowLeft, CreditCard, Smartphone } from 'lucide-react';
import { TASA_DOLAR_BS, IVA_RATE, formatBs, formatUsd } from '../config';

export default function PaymentView({ items, onSuccess, onBack }) {
  const [selectedMethod, setSelectedMethod] = useState(null);
  const [processing, setProcessing] = useState(false);

  const totals = useMemo(() => {
    const subtotalUSD = items.reduce((sum, i) => sum + i.price * i.quantity, 0);
    const ivaUSD = subtotalUSD * IVA_RATE;
    const totalUSD = subtotalUSD + ivaUSD;
    const totalBs = totalUSD * TASA_DOLAR_BS;
    return { totalBs, totalUSD };
  }, [items]);

  const handleSelectMethod = (method) => {
    setSelectedMethod(method);
    setProcessing(true);
    setTimeout(() => {
      setProcessing(false);
      onSuccess();
    }, 2500);
  };

  return (
    <div className="h-full flex flex-col px-8 py-8 animate-fade-in relative">
      {onBack && !processing && (
        <button
          type="button"
          onClick={onBack}
          className="absolute top-6 left-6 flex items-center gap-2 py-3 px-4 rounded-xl text-2xl font-bold text-gray-600 hover:bg-gray-100 transition-colors"
        >
          <ArrowLeft size={32} strokeWidth={2.5} />
          Volver
        </button>
      )}
      <h2 className="text-6xl font-black text-gray-900 text-center mb-6">
        Método de Pago
      </h2>

      <div className="flex justify-between items-baseline mb-10">
        <span className="text-4xl font-bold text-gray-700">TOTAL</span>
        <div className="text-right">
          <div className="text-9xl font-black text-green-600 tracking-tight">
            Bs. {formatBs(totals.totalBs)}
          </div>
          <div className="text-2xl text-gray-500 mt-1">
            Ref. USD {formatUsd(totals.totalUSD)}
          </div>
        </div>
      </div>

      {processing ? (
        <div className="flex-1 flex flex-col items-center justify-center">
          <div className="text-4xl font-bold text-gray-700 text-center mb-4">
            {selectedMethod === 'card'
              ? 'Procesando en terminal...'
              : 'Escanee el código QR en pantalla'}
          </div>
          <div className="w-48 h-48 border-4 border-orange-500 border-dashed rounded-2xl animate-pulse flex items-center justify-center mt-6">
            <span className="text-6xl">⌛</span>
          </div>
        </div>
      ) : (
        <div className="flex-1 flex flex-col gap-6">
          <button
            type="button"
            onClick={() => handleSelectMethod('card')}
            className="flex items-center gap-8 p-8 rounded-2xl border-4 border-gray-200 hover:border-orange-500 transition-all active:scale-[0.98] bg-white"
            style={{
              borderColor: selectedMethod === 'card' ? 'rgba(242, 109, 10, 0.8)' : undefined,
            }}
          >
            <div
              className="w-24 h-24 rounded-xl flex items-center justify-center"
              style={{ backgroundColor: 'rgba(242, 109, 10, 0.2)' }}
            >
              <CreditCard size={48} style={{ color: 'rgba(242, 109, 10, 0.95)' }} strokeWidth={2} />
            </div>
            <div className="text-left flex-1">
              <p className="text-5xl font-black text-gray-900">
                TARJETA DE DÉBITO
              </p>
              <p className="text-2xl text-gray-500 mt-1">
                Inserte o acerque su tarjeta
              </p>
            </div>
          </button>

          <button
            type="button"
            onClick={() => handleSelectMethod('transfer')}
            className="flex items-center gap-8 p-8 rounded-2xl border-4 border-gray-200 hover:border-orange-500 transition-all active:scale-[0.98] bg-white"
            style={{
              borderColor: selectedMethod === 'transfer' ? 'rgba(242, 109, 10, 0.8)' : undefined,
            }}
          >
            <div
              className="w-24 h-24 rounded-xl flex items-center justify-center"
              style={{ backgroundColor: 'rgba(242, 109, 10, 0.2)' }}
            >
              <Smartphone size={48} style={{ color: 'rgba(242, 109, 10, 0.95)' }} strokeWidth={2} />
            </div>
            <div className="text-left flex-1">
              <p className="text-5xl font-black text-gray-900">
                TRANSFERENCIA / PAGO MÓVIL
              </p>
              <p className="text-2xl text-gray-500 mt-1">
                Escanee el código QR con su banco
              </p>
            </div>
          </button>
        </div>
      )}
    </div>
  );
}
