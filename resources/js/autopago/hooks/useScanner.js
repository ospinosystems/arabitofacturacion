import { useEffect, useRef, useCallback } from 'react';
import { searchByBarcode } from '../services/productService';

const MIN_BARCODE_LENGTH = 8;
/** Tiempo sin teclas para resetear buffer (ms). Escáner: ~50ms. Teclado manual: ~200-500ms */
const BUFFER_RESET_MS = 2000;

/**
 * Hook que captura eventos de teclado para escáner de códigos de barras.
 * Los escáneres envían caracteres rápido y terminan en Enter.
 * Busca productos reales via API getinventario.
 * options.disabled: si true, no captura teclas (para que un input las reciba).
 */
export function useScanner(onScan, onNotFound, options = {}) {
  const bufferRef = useRef('');
  const lastKeyTimeRef = useRef(0);
  const onScanRef = useRef(onScan);
  const onNotFoundRef = useRef(onNotFound);
  const disabledRef = useRef(options.disabled);
  onScanRef.current = onScan;
  onNotFoundRef.current = onNotFound;
  disabledRef.current = options.disabled;

  const beep = useCallback(() => {
    try {
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();
      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);
      oscillator.frequency.value = 1200;
      oscillator.type = 'sine';
      gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
      oscillator.start(audioContext.currentTime);
      oscillator.stop(audioContext.currentTime + 0.1);
    } catch {
      console.log('BEEP');
    }
  }, []);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (disabledRef.current) return;

      const now = Date.now();
      const timeSinceLastKey = now - lastKeyTimeRef.current;

      if (e.key === 'Enter') {
        e.preventDefault();
        const barcode = bufferRef.current.trim();
        bufferRef.current = '';

        if (barcode.length >= MIN_BARCODE_LENGTH) {
          searchByBarcode(barcode)
            .then((product) => {
              if (product) {
                onScanRef.current?.(product);
                beep();
              } else {
                onNotFoundRef.current?.(barcode);
              }
            })
            .catch(() => {
              onNotFoundRef.current?.(barcode);
            });
        }
        lastKeyTimeRef.current = now;
        return;
      }

      if (timeSinceLastKey > BUFFER_RESET_MS) {
        bufferRef.current = '';
      }

      if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        bufferRef.current += e.key;
      }

      lastKeyTimeRef.current = now;
    };

    window.addEventListener('keydown', handleKeyDown, { capture: true });
    return () => window.removeEventListener('keydown', handleKeyDown, { capture: true });
  }, [beep]);
}
