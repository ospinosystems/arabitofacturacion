/**
 * Reproduce un sonido de alerta (beep) para avisar que el pedido ya fue despachado.
 * Usa Web Audio API para no depender de archivos externos.
 */
export function playDespachadoAlert() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 880;
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.15);
    setTimeout(() => {
      const osc2 = ctx.createOscillator();
      const gain2 = ctx.createGain();
      osc2.connect(gain2);
      gain2.connect(ctx.destination);
      osc2.frequency.value = 660;
      osc2.type = 'sine';
      gain2.gain.setValueAtTime(0.25, ctx.currentTime);
      gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
      osc2.start(ctx.currentTime);
      osc2.stop(ctx.currentTime + 0.2);
    }, 180);
  } catch (e) {
    // Silenciar si el navegador no soporta o requiere interacción previa
  }
}

/**
 * Reproduce un sonido de éxito (despacho registrado correctamente).
 * Tono ascendente breve y agradable.
 */
export function playExitoSound() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const playTone = (freq, startTime, duration) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = freq;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.25, startTime);
      gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
      osc.start(startTime);
      osc.stop(startTime + duration);
    };
    const t = ctx.currentTime;
    playTone(523.25, t, 0.12);       // C5
    playTone(659.25, t + 0.1, 0.12); // E5
    playTone(783.99, t + 0.2, 0.2);   // G5
  } catch (e) {
    // Silenciar si no hay soporte
  }
}

/**
 * Sonido de alerta: pedido con entregas parciales / pendiente por despachar.
 * Doble tono de alerta, distinto al éxito y al "ya despachado".
 */
export function playAlertaPendienteSound() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const beep = (freq, start, dur) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = freq;
      osc.type = 'square';
      gain.gain.setValueAtTime(0.2, start);
      gain.gain.exponentialRampToValueAtTime(0.01, start + dur);
      osc.start(start);
      osc.stop(start + dur);
    };
    const t = ctx.currentTime;
    beep(660, t, 0.12);
    beep(660, t + 0.2, 0.12);
  } catch (e) {
    // Silenciar si no hay soporte
  }
}
