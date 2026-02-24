import React from 'react';
import NumericKeypad from './NumericKeypad';
import AlphaKeypad from './AlphaKeypad';
import { usePprKeyboard } from './PprKeyboardContext';

export default function PprOnScreenKeyboard() {
  const { activeInput, close } = usePprKeyboard();

  if (!activeInput) return null;

  const { type, value, onChange, maxLength = 20 } = activeInput;

  return (
    <div
      className="fixed bottom-0 left-0 right-0 z-40 bg-gray-100 border-t-2 border-gray-300 shadow-lg safe-area-padding pb-2"
      style={{ touchAction: 'manipulation' }}
    >
      <div className="flex justify-end px-2 py-1">
        <button
          type="button"
          onClick={() => {
            document.activeElement?.blur?.();
            close();
          }}
          className="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100"
        >
          Cerrar teclado
        </button>
      </div>
      <div className="px-2 max-w-md mx-auto">
        {type === 'numeric' && (
          <NumericKeypad value={value} onChange={onChange} maxLength={maxLength} />
        )}
        {type === 'alpha' && (
          <AlphaKeypad value={value} onChange={onChange} maxLength={maxLength} />
        )}
      </div>
    </div>
  );
}
