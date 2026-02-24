import React from 'react';

const DIGITS = ['7', '8', '9', '4', '5', '6', '1', '2', '3', '', '0', '⌫'];

export default function NumericKeypad({ value = '', onChange, placeholder = '0', maxLength = 10 }) {
  const handleTap = (key) => {
    if (key === '⌫') {
      onChange(value.slice(0, -1));
      return;
    }
    if (key === '') return;
    const next = value + key;
    if (maxLength && next.length > maxLength) return;
    onChange(next);
  };

  return (
    <div className="grid grid-cols-3 gap-2 p-2" style={{ touchAction: 'manipulation' }}>
      {DIGITS.map((key, i) => (
        <button
          key={i}
          type="button"
          onClick={() => handleTap(key)}
          onTouchEnd={(e) => { e.preventDefault(); handleTap(key); }}
          className={`min-h-[52px] rounded-xl text-xl font-semibold select-none active:scale-95 transition
            ${key === '' ? 'invisible' : 'bg-white border-2 border-gray-200 shadow hover:bg-sinapsis-select hover:border-sinapsis active:bg-sinapsis'}`}
        >
          {key || ''}
        </button>
      ))}
    </div>
  );
}
