import React, { createContext, useContext, useState, useCallback } from 'react';

const PprKeyboardContext = createContext(null);

export function PprKeyboardProvider({ children }) {
  const [activeInput, setActiveInput] = useState(null);

  const register = useCallback((id, options) => {
    setActiveInput({ id, ...options });
  }, []);

  const unregister = useCallback((id) => {
    setActiveInput((prev) => (prev && prev.id === id ? null : prev));
  }, []);

  const close = useCallback(() => {
    setActiveInput(null);
  }, []);

  const updateValue = useCallback((value) => {
    setActiveInput((prev) => (prev ? { ...prev, value } : null));
  }, []);

  return (
    <PprKeyboardContext.Provider value={{ activeInput, register, unregister, close, updateValue }}>
      {children}
    </PprKeyboardContext.Provider>
  );
}

export function usePprKeyboard() {
  const ctx = useContext(PprKeyboardContext);
  if (!ctx) throw new Error('usePprKeyboard must be used within PprKeyboardProvider');
  return ctx;
}
