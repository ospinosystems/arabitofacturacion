import React from 'react';
import { ChevronDown, ScanBarcode } from 'lucide-react';

/**
 * Empty state cuando no hay productos. Flecha recta hacia abajo
 * con animación de barrido de arriba a abajo.
 */
export default function EmptyCartState() {
  return (
    <div className="flex-1 flex flex-col items-center justify-start px-8 pt-8 pb-24 min-h-[480px] relative">
      <div className="w-36 h-36 rounded-2xl flex items-center justify-center mb-10" style={{ backgroundColor: 'rgba(242, 109, 10, 0.12)' }}>
        <ScanBarcode size={72} strokeWidth={2} style={{ color: 'rgba(242, 109, 10, 0.9)' }} />
      </div>

      <div className="text-center mb-12">
        <h2 className="text-6xl font-extrabold text-gray-800 mb-5 tracking-tight">
          Agrega productos a tu compra
        </h2>
        <p className="text-3xl font-medium text-gray-600 max-w-xl mx-auto">
          Escanea el código de barras o ingrésalo con el teclado
        </p>
      </div>

      {/* Mensaje + flecha en esquina inferior izquierda */}
      <div className="absolute bottom-0 left-0 pl-12 pb-2 flex flex-col items-start">
        <div className="mb-4 pl-6 pr-6 py-4 rounded-xl border-l-4 bg-gray-50/90 max-w-[340px]" style={{ borderLeftColor: 'rgba(242, 109, 10, 0.85)' }}>
          <p className="text-3xl font-bold text-gray-800 leading-snug tracking-tight">
            Apunta el código de barras aquí para escanearlo
          </p>
        </div>
        <div className="flex flex-col items-center -space-y-3 empty-state-arrow-container">
          {[0, 1, 2, 3].map((i) => {
            const size = 130 - i * 15; /* 130 → 115 → 100 → 85 */
            return (
              <div key={i} className="flex justify-center w-full">
                <ChevronDown
                  size={size}
                  strokeWidth={4}
                  className="empty-state-sweep-chevron shrink-0"
                  style={{
                    color: 'rgba(242, 109, 10, 1)',
                    animationDelay: `${i * 0.12}s`,
                  }}
                />
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
