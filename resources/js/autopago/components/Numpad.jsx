import React from 'react';
import { Delete } from 'lucide-react';

const BRAND_COLOR = 'rgba(242, 109, 10, 0.95)';

export default function Numpad({ value, onChange, maxLength = 20 }) {
  const keys = [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    ['', '0', 'back'],
  ];

  const handleKey = (key) => {
    if (key === 'back') {
      onChange(value.slice(0, -1));
    } else if (key && value.length < maxLength) {
      onChange(value + key);
    }
  };

  return (
    <div className="w-full max-w-xs mx-auto">
      <div className="grid grid-cols-3 gap-2">
        {keys.flat().map((key, i) =>
          key ? (
            <button
              key={i}
              type="button"
              onClick={() => handleKey(key)}
              className="h-14 rounded-lg flex items-center justify-center text-2xl font-semibold text-white transition-all active:scale-95 shadow-sm"
              style={{ backgroundColor: BRAND_COLOR }}
            >
              {key === 'back' ? <Delete size={26} strokeWidth={2.5} /> : key}
            </button>
          ) : (
            <div key={i} />
          )
        )}
      </div>
    </div>
  );
}
