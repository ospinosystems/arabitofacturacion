import { useScanner } from '../hooks/useScanner';
import { useCart } from '../context/CartContext';

/**
 * Componente invisible que maneja el listener de teclado para el escáner.
 * disabled: cuando true (ej. panel Agregar producto abierto), no captura teclas.
 */
export default function BarcodeHandler({ onItemScanned, onNotFound, disabled }) {
  const { tryAddItem } = useCart();

  useScanner(
    (product) => {
      if (tryAddItem(product)) onItemScanned?.(product);
    },
    onNotFound,
    { disabled: !!disabled }
  );

  return null;
}
