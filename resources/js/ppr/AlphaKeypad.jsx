import React from 'react';

const ROW1 = 'ABCDEFGHI'.split('');
const ROW2 = 'JKLMNÑOPQ'.split('');
const ROW3 = 'RSTUVWXYZ'.split('');

export default function AlphaKeypad({ value = '', onChange, placeholder = '', maxLength = 80 }) {
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

  const keyClass = 'min-h-[44px] rounded-lg text-lg font-semibold select-none active:scale-95 transition bg-white border-2 border-gray-200 shadow hover:bg-sinapsis-select hover:border-sinapsis active:bg-sinapsis';

  return (
    <div className="grid gap-1.5 p-2" style={{ touchAction: 'manipulation' }}>
      <div className="grid grid-cols-9 gap-1">
        {ROW1.map((k) => (
          <button key={k} type="button" onClick={() => handleTap(k)} onTouchEnd={(e) => { e.preventDefault(); handleTap(k); }} className={keyClass}>{k}</button>
        ))}
      </div>
      <div className="grid grid-cols-9 gap-1">
        {ROW2.map((k) => (
          <button key={k} type="button" onClick={() => handleTap(k)} onTouchEnd={(e) => { e.preventDefault(); handleTap(k); }} className={keyClass}>{k}</button>
        ))}
      </div>
      <div className="grid grid-cols-9 gap-1">
        {ROW3.map((k) => (
          <button key={k} type="button" onClick={() => handleTap(k)} onTouchEnd={(e) => { e.preventDefault(); handleTap(k); }} className={keyClass}>{k}</button>
        ))}
      </div>
      <div className="grid grid-cols-2 gap-2 mt-1">
        <button type="button" onClick={() => handleTap(' ')} onTouchEnd={(e) => { e.preventDefault(); handleTap(' '); }} className={keyClass}>Espacio</button>
        <button type="button" onClick={() => handleTap('⌫')} onTouchEnd={(e) => { e.preventDefault(); handleTap('⌫'); }} className={keyClass}>⌫</button>
      </div>
    </div>
  );
}
