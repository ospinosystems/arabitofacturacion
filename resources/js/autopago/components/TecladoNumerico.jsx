import React from 'react';
import { Delete } from 'lucide-react';

/**
 * Teclado numérico táctil para kioskos.
 * @param {string} value - Valor actual
 * @param {function} onChange - Callback (nuevoValor) => void
 * @param {number} maxLength - Máximo de caracteres
 * @param {boolean} allowDecimal - Si permite punto decimal (para montos)
 * @param {string} accentColor - Color de acento (#hex)
 */
export default function TecladoNumerico({
  value = '',
  onChange,
  maxLength = 20,
  allowDecimal = false,
  accentColor = '#d97706',
}) {
  const handleKey = (key) => {
    if (key === 'back') {
      onChange(value.slice(0, -1));
    } else if (key === '.' && allowDecimal) {
      if (!value.includes('.') && value.length < maxLength) {
        onChange(value + '.');
      }
    } else if (key && key !== '.') {
      if (allowDecimal && value.includes('.')) {
        const [, dec] = value.split('.');
        if (dec.length < 2 && value.length < maxLength) {
          onChange(value + key);
        }
      } else if (value.length < maxLength) {
        onChange(value + key);
      }
    }
  };

  const keys = allowDecimal
    ? [
        ['1', '2', '3'],
        ['4', '5', '6'],
        ['7', '8', '9'],
        ['.', '0', 'back'],
      ]
    : [
        ['1', '2', '3'],
        ['4', '5', '6'],
        ['7', '8', '9'],
        ['', '0', 'back'],
      ];

  return (
    <div className="w-full">
      <div className="grid grid-cols-3 gap-2">
        {keys.flat().map((key, i) =>
          key ? (
            <button
              key={i}
              type="button"
              onClick={() => handleKey(key)}
              className="h-14 rounded-xl flex items-center justify-center text-2xl font-bold text-white transition-all active:scale-95 shadow-md hover:opacity-90"
              style={{ backgroundColor: key === 'back' ? '#6b7280' : accentColor }}
            >
              {key === 'back' ? (
                <Delete size={28} strokeWidth={2.5} />
              ) : (
                key
              )}
            </button>
          ) : (
            <div key={i} />
          )
        )}
      </div>
    </div>
  );
}
