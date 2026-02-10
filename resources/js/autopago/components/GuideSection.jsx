import React from 'react';
import { ScanBarcode } from 'lucide-react';

export default function GuideSection({ isEmpty }) {
  return (
    <div
      className="flex flex-row items-center justify-center gap-3 rounded-xl px-4 py-3"
      style={{
        backgroundColor: 'rgba(242, 109, 10, 0.9)',
      }}
    >
      <ScanBarcode
        className="flex-shrink-0 text-white"
        size={28}
        strokeWidth={2}
      />
      {isEmpty ? (
        <div className="flex flex-col">
          <span className="text-2xl font-bold text-white">
            Escanea tus productos
          </span>
          <span className="text-base text-white/90">
            Apunta el escáner al código o ingresa con teclado y Enter
          </span>
        </div>
      ) : (
        <p className="text-xl font-semibold text-white">
          Sigue escaneando o elige un método de pago abajo · Usa + y - para cantidades
        </p>
      )}
    </div>
  );
}
