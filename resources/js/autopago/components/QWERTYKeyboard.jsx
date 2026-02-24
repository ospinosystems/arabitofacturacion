import React from 'react';
import { Delete } from 'lucide-react';

const ROWS = [
  ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
  ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L'],
  ['Z', 'X', 'C', 'V', 'B', 'N', 'M'],
];

const SYMBOL_ROWS = [
  ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
  ['.', '-', '_', '/', ',', '@', '#', '*', '+', '(', ')'],
];

const BRAND_COLOR = 'rgba(242, 109, 10, 0.95)';
const HIGH_CONTRAST_BG = '#1a1a1a';

export default function QWERTYKeyboard({ onKeyPress, onBackspace, withSymbols = false, highContrast = false }) {
  const bgStyle = highContrast ? { backgroundColor: HIGH_CONTRAST_BG } : { backgroundColor: BRAND_COLOR };

  return (
    <div className="space-y-1">
      {withSymbols && SYMBOL_ROWS.map((row, rowIdx) => (
        <div key={`sym-${rowIdx}`} className="flex justify-center gap-1 flex-wrap">
          {row.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => onKeyPress(key)}
              className="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-semibold text-white transition-all active:scale-95"
              style={bgStyle}
            >
              {key}
            </button>
          ))}
        </div>
      ))}
      {ROWS.map((row, rowIdx) => (
        <div key={rowIdx} className="flex justify-center gap-1">
          {row.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => onKeyPress(key)}
              className="w-10 h-10 rounded-lg flex items-center justify-center text-base font-semibold text-white transition-all active:scale-95"
              style={bgStyle}
            >
              {key}
            </button>
          ))}
        </div>
      ))}
      <div className="flex justify-center gap-1.5 pt-1">
        <button
          type="button"
          onClick={onBackspace}
          className="px-5 h-10 rounded-lg flex items-center justify-center text-white font-semibold transition-all active:scale-95"
          style={bgStyle}
        >
          <Delete size={20} strokeWidth={2.5} />
        </button>
        <button
          type="button"
          onClick={() => onKeyPress(' ')}
          className="px-10 h-10 rounded-lg flex items-center justify-center text-base font-semibold text-white transition-all active:scale-95"
          style={bgStyle}
        >
          espacio
        </button>
      </div>
    </div>
  );
}
