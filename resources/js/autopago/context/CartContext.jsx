import React, { createContext, useContext, useReducer, useState, useCallback, useEffect } from 'react';

/** Genera UUID v4 para id de orden */
const generateOrderId = () => {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
};

// Acciones del carrito
export const ADD_ITEM = 'ADD_ITEM';
export const DECREMENT_ITEM = 'DECREMENT_ITEM';
export const REMOVE_ITEM = 'REMOVE_ITEM';
export const CLEAR = 'CLEAR';

function getStock(productOrItem) {
  const s = productOrItem?.stock;
  return s != null && Number.isFinite(s) ? s : Infinity;
}

const cartReducer = (state, action) => {
  switch (action.type) {
    case ADD_ITEM: {
      const existing = state.items.find((i) => i.id === action.payload.id);
      const isEmpty = state.items.length === 0;
      const orderId = isEmpty ? generateOrderId() : state.orderId;

      if (existing) {
        const stock = getStock(existing);
        if (existing.quantity + 1 > stock) return state;
        return {
          ...state,
          orderId,
          items: state.items.map((i) =>
            i.id === action.payload.id
              ? { ...i, quantity: i.quantity + 1 }
              : i
          ),
        };
      }
      const stockNew = getStock(action.payload);
      if (1 > stockNew) return state;
      return {
        ...state,
        orderId,
        items: [...state.items, { ...action.payload, quantity: 1, stock: action.payload.stock ?? null }],
      };
    }
    case DECREMENT_ITEM: {
      const item = state.items.find((i) => i.id === action.payload);
      if (!item) return state;
      if (item.quantity === 1) {
        const newItems = state.items.filter((i) => i.id !== action.payload);
        return {
          ...state,
          items: newItems,
          orderId: newItems.length === 0 ? null : state.orderId,
        };
      }
      return {
        ...state,
        items: state.items.map((i) =>
          i.id === action.payload
            ? { ...i, quantity: i.quantity - 1 }
            : i
        ),
      };
    }
    case REMOVE_ITEM: {
      const newItems = state.items.filter((i) => i.id !== action.payload);
      return {
        ...state,
        items: newItems,
        orderId: newItems.length === 0 ? null : state.orderId,
      };
    }
    case CLEAR:
      return { ...state, items: [], orderId: null };
    default:
      return state;
  }
};

const CartContext = createContext(null);

export const CartProvider = ({ children }) => {
  const [state, dispatch] = useReducer(cartReducer, { items: [], orderId: null });
  const [stockExceededMessage, setStockExceededMessage] = useState(null);

  useEffect(() => {
    if (!stockExceededMessage) return;
    const t = setTimeout(() => setStockExceededMessage(null), 3500);
    return () => clearTimeout(t);
  }, [stockExceededMessage]);

  const addItem = (product) => dispatch({ type: ADD_ITEM, payload: product });

  const tryAddItem = useCallback((product) => {
    const existing = state.items.find((i) => i.id === product.id);
    const qty = existing?.quantity ?? 0;
    const stock = getStock(product) !== Infinity ? getStock(product) : getStock(existing);
    if (qty + 1 > stock) {
      setStockExceededMessage('No hay más cantidad disponible');
      return false;
    }
    dispatch({ type: ADD_ITEM, payload: product });
    return true;
  }, [state.items]);

  const decrementItem = (productId) =>
    dispatch({ type: DECREMENT_ITEM, payload: productId });
  const removeItem = (productId) =>
    dispatch({ type: REMOVE_ITEM, payload: productId });
  const clearCart = () => dispatch({ type: CLEAR });

  return (
    <CartContext.Provider
      value={{
        items: state.items,
        orderId: state.orderId,
        addItem,
        tryAddItem,
        stockExceededMessage,
        decrementItem,
        removeItem,
        clearCart,
      }}
    >
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used within CartProvider');
  return ctx;
};
