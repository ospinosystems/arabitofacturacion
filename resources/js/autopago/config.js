/**
 * Configuración del quiosco de autopago
 */

/** Tasa de cambio Bs por USD para mostrar totales */
export const TASA_DOLAR_BS = 38;

/** IVA (16%) */
export const IVA_RATE = 0.16;

/** Formato estándar digital: siempre 2 decimales */
const fmt = { minimumFractionDigits: 2, maximumFractionDigits: 2 };

/** Formatea montos en Bs con 2 decimales */
export const formatBs = (n) =>
  Number(n).toLocaleString('es-VE', fmt);

/** Formatea montos en USD con 2 decimales */
export const formatUsd = (n) =>
  Number(n).toLocaleString('en-US', fmt);

/** Estados de validación de pagos */
export const PAYMENT_STATUS = {
  PENDING: 'pending',
  VALIDATED: 'validated',
  REJECTED: 'rejected',
  LOADING: 'loading',
};

/** Color primario de la paleta */
export const PRIMARY_ORANGE = '#F26D0A';

/** Colores por método de pago (alto contraste - combinan con naranja #F26D0A) */
export const PAYMENT_METHOD_COLORS = {
  card: {
    bg: '#d97706',          // amber-600 - fondo oscuro
    bgLight: '#fef3c7',     // amber-100
    bgOverlay: 'rgba(251, 191, 36, 0.3)',
    border: 'rgba(180, 83, 9, 0.5)',
    hover: '#b45309',       // amber-700
    text: '#451a03',        // amber-950 - máximo contraste
    textOnBg: '#ffffff',    // texto blanco sobre header
  },
  transfer: {
    bg: '#ea580c',          // orange-600 - fondo oscuro
    bgLight: '#ffedd5',     // orange-100
    bgOverlay: 'rgba(251, 146, 60, 0.3)',
    border: 'rgba(194, 65, 12, 0.5)',
    hover: '#c2410c',       // orange-700
    text: '#431407',        // orange-950 - máximo contraste
    textOnBg: '#ffffff',    // texto blanco sobre header
  },
};

/** Instrucciones para pago con tarjeta (lector / terminal) */
export const POS_INSTRUCCIONES = [
  'Pulse el botón verde «Enviar al datáfono» para que el datáfono reciba el monto.',
  'Inserte o acerque su tarjeta al datáfono',
  'En el datáfono: confirme el monto e introduzca su clave',
];

/** Palabras clave a resaltar en cada instrucción (mismo orden que POS_INSTRUCCIONES) */
export const POS_INSTRUCCIONES_KEYWORDS = [
  ['botón verde', 'Enviar al datáfono', 'datáfono'],
  ['Inserte', 'acerque', 'tarjeta', 'datáfono'],
  ['confirme', 'monto', 'introduzca', 'clave'],
];

/** Datos de destino para transferencia / pago móvil */
export const TRANSFER_DESTINO = {
  banco: 'Banesco (0134)',
  telefono: '0424-3564848',
  cedula: 'V-21628222',
};

/** Métodos de pago disponibles por defecto */
export const PAYMENT_METHODS = [
  {
    type: 'card',
    description: 'Tarjeta débito',
    currency: 'bs',
    icon: 'fa-credit-card',
  },
  {
    type: 'transfer',
    description: 'Transferencia / Pago móvil',
    currency: 'bs',
    icon: 'fa-mobile-alt',
  },
];
