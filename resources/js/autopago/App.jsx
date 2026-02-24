import React, { useState, useReducer, useCallback, useEffect, useRef } from 'react';
import { CartProvider, useCart } from './context/CartContext';
import LoginView from './views/LoginView';
import WelcomeView from './views/WelcomeView';
import IdentificationView from './views/IdentificationView';
import RegisterView from './views/RegisterView';
import MainCartView from './views/MainCartView';
import SuccessView from './views/SuccessView';
import { api } from './services/api';
import { getSession, setSession, clearSession } from './services/persistence';

const STEPS = {
  LOGIN: 'login',
  WELCOME: 'welcome',
  IDENTIFICATION: 'identification',
  REGISTRATION: 'registration',
  SHOPPING: 'shopping',
  SUCCESS: 'success',
};

const STORAGE_USER = 'autopago_session_user';
const STORAGE_TOKEN = 'autopago_session_token';

function AppContent() {
  const [state, dispatch] = useReducer(reducer, {
    currentStep: STEPS.LOGIN,
    sessionUser: null,
    userData: { id: '', nombre: '', telefono: '' },
  });
  const { items, orderId, clearCart } = useCart();
  const [validatedPayments, setValidatedPayments] = useState([]);
  const inactivityTimerRef = useRef(null);
  const [checkingAuth, setCheckingAuth] = useState(true);
  const [idConfirmLoading, setIdConfirmLoading] = useState(false);
  const [registerLoading, setRegisterLoading] = useState(false);

  const goToStep = useCallback((step) => {
    dispatch({ type: 'SET_STEP', payload: step });
  }, []);

  const resetAll = useCallback(() => {
    clearCart();
    setValidatedPayments([]);
    clearSession();
    dispatch({ type: 'RESET' });
  }, [clearCart]);

  const handleLoginSuccess = useCallback((user, sessionToken) => {
    if (sessionToken) {
      try { localStorage.setItem(STORAGE_TOKEN, sessionToken); } catch (_) {}
    }
    try { localStorage.setItem(STORAGE_USER, JSON.stringify(user)); } catch (_) {}
    dispatch({ type: 'SET_SESSION_USER', payload: user });
    goToStep(STEPS.WELCOME);
  }, [goToStep]);

  const handleLogout = useCallback(() => {
    api.logout().catch(() => {});
    clearSession();
    try {
      localStorage.removeItem(STORAGE_USER);
      localStorage.removeItem(STORAGE_TOKEN);
    } catch (_) {}
    dispatch({ type: 'SET_SESSION_USER', payload: null });
    goToStep(STEPS.LOGIN);
  }, [goToStep]);

  useEffect(() => {
    const savedUser = localStorage.getItem(STORAGE_USER);
    const savedToken = localStorage.getItem(STORAGE_TOKEN);
    if (!savedUser || !savedToken) {
      setCheckingAuth(false);
      return;
    }
    api.verificarLogin()
      .then((res) => {
        if (res.data.estado) {
          try {
            const user = JSON.parse(savedUser);
            dispatch({ type: 'SET_SESSION_USER', payload: user });
            goToStep(STEPS.WELCOME);
            return getSession();
          } catch (_) {
            localStorage.removeItem(STORAGE_USER);
            localStorage.removeItem(STORAGE_TOKEN);
          }
        } else {
          localStorage.removeItem(STORAGE_USER);
          localStorage.removeItem(STORAGE_TOKEN);
        }
      })
      .then((session) => {
        if (session?.currentStep && session.currentStep !== STEPS.LOGIN) {
          dispatch({ type: 'SET_STEP', payload: session.currentStep });
          if (session.userData && typeof session.userData === 'object') {
            dispatch({ type: 'SET_USER_DATA', payload: session.userData });
          }
          if (Array.isArray(session.validatedPayments)) {
            setValidatedPayments(session.validatedPayments);
          }
        }
      })
      .catch(() => {
        localStorage.removeItem(STORAGE_USER);
        localStorage.removeItem(STORAGE_TOKEN);
      })
      .finally(() => setCheckingAuth(false));
  }, []);

  const handleStart = useCallback(() => {
    goToStep(STEPS.IDENTIFICATION);
  }, [goToStep]);

  const handleIdConfirm = useCallback(
    async (idValue) => {
      const cedula = (idValue || '').trim();
      if (!cedula) return;

      setIdConfirmLoading(true);
      try {
        const { data } = await api.getpersona({ q: cedula, num: 10 });
        const clientes = Array.isArray(data) ? data : [];
        const cedulaDigitos = cedula.replace(/\D/g, '');

        const clienteEncontrado = clientes.find((c) => {
          const idDigitos = String(c.identificacion || '').replace(/\D/g, '');
          return idDigitos === cedulaDigitos;
        });

        if (clienteEncontrado && clienteEncontrado.nombre?.trim() && clienteEncontrado.telefono?.trim()) {
          dispatch({
            type: 'SET_USER_DATA',
            payload: {
              id: clienteEncontrado.identificacion || cedula,
              nombre: clienteEncontrado.nombre,
              telefono: clienteEncontrado.telefono,
              clienteId: clienteEncontrado.id,
            },
          });
          goToStep(STEPS.SHOPPING);
        } else {
          dispatch({
            type: 'SET_USER_DATA',
            payload: {
              ...state.userData,
              id: cedula,
              nombre: clienteEncontrado?.nombre || '',
              telefono: clienteEncontrado?.telefono || '',
              clienteId: clienteEncontrado?.id,
            },
          });
          goToStep(STEPS.REGISTRATION);
        }
      } catch {
        dispatch({
          type: 'SET_USER_DATA',
          payload: { ...state.userData, id: cedula },
        });
        goToStep(STEPS.REGISTRATION);
      } finally {
        setIdConfirmLoading(false);
      }
    },
    [goToStep, state.userData]
  );

  const handleRegisterConfirm = useCallback(async () => {
    const { id, nombre, telefono, clienteId } = state.userData;
    if (!nombre?.trim() || !telefono?.trim()) return;

    setRegisterLoading(true);
    try {
      const res = await api.setClienteCrud({
        id: clienteId || null,
        clienteInpidentificacion: id || '',
        clienteInpnombre: nombre.trim(),
        clienteInpcorreo: '',
        clienteInpdireccion: '',
        clienteInptelefono: telefono.trim(),
        clienteInpestado: '',
        clienteInpciudad: '',
      });
      const newClienteId = res.data?.id;
      if (newClienteId) {
        dispatch({ type: 'SET_USER_DATA', payload: { ...state.userData, clienteId: newClienteId } });
      }
      goToStep(STEPS.SHOPPING);
    } catch {
      goToStep(STEPS.SHOPPING);
    } finally {
      setRegisterLoading(false);
    }
  }, [goToStep, state.userData]);

  const handlePaymentSuccess = useCallback(() => {
    clearSession();
    goToStep(STEPS.SUCCESS);
  }, [goToStep]);

  const handleBackFromIdentification = useCallback(() => {
    goToStep(STEPS.WELCOME);
  }, [goToStep]);

  const handleBackFromRegistration = useCallback(() => {
    goToStep(STEPS.IDENTIFICATION);
  }, [goToStep]);

  const handleBackFromShopping = useCallback(() => {
    if (state.userData.nombre?.trim()) {
      goToStep(STEPS.REGISTRATION);
    } else {
      goToStep(STEPS.IDENTIFICATION);
    }
  }, [goToStep, state.userData.nombre]);

  const startInactivityTimer = useCallback(() => {
    if (inactivityTimerRef.current) clearTimeout(inactivityTimerRef.current);
    inactivityTimerRef.current = setTimeout(() => {
      resetAll();
    }, 10000);
  }, [resetAll]);

  useEffect(() => {
    if (state.currentStep === STEPS.SUCCESS) {
      startInactivityTimer();
      const handleActivity = () => startInactivityTimer();
      window.addEventListener('click', handleActivity);
      window.addEventListener('touchstart', handleActivity);
      return () => {
        window.removeEventListener('click', handleActivity);
        window.removeEventListener('touchstart', handleActivity);
        if (inactivityTimerRef.current) clearTimeout(inactivityTimerRef.current);
      };
    }
  }, [state.currentStep, startInactivityTimer]);

  const saveSessionRef = useRef(null);
  useEffect(() => {
    if (state.currentStep === STEPS.LOGIN || state.currentStep === STEPS.SUCCESS || !state.sessionUser) return;
    if (saveSessionRef.current) clearTimeout(saveSessionRef.current);
    saveSessionRef.current = setTimeout(() => {
      setSession({
        cart: { items, orderId },
        userData: state.userData,
        currentStep: state.currentStep,
        validatedPayments,
      });
      saveSessionRef.current = null;
    }, 400);
    return () => {
      if (saveSessionRef.current) clearTimeout(saveSessionRef.current);
    };
  }, [state.currentStep, state.sessionUser, state.userData, items, orderId, validatedPayments]);

  const renderStep = () => {
    if (checkingAuth) {
      return (
        <div className="h-full flex items-center justify-center bg-gray-50">
          <div className="flex flex-col items-center gap-4">
            <span className="animate-spin h-12 w-12 border-4 border-orange-500 border-t-transparent rounded-full" />
            <p className="text-gray-600 font-medium">Verificando sesión...</p>
          </div>
        </div>
      );
    }
    if (state.currentStep === STEPS.LOGIN || !state.sessionUser) {
      return <LoginView onLoginSuccess={handleLoginSuccess} />;
    }
    switch (state.currentStep) {
      case STEPS.WELCOME:
        return <WelcomeView onStart={handleStart} />;
      case STEPS.IDENTIFICATION:
        return (
          <IdentificationView
            idValue={state.userData.id}
            onIdChange={(v) =>
              dispatch({ type: 'SET_USER_DATA', payload: { ...state.userData, id: v } })
            }
            onConfirm={() => handleIdConfirm(state.userData.id)}
            onBack={handleBackFromIdentification}
            loading={idConfirmLoading}
          />
        );
      case STEPS.REGISTRATION:
        return (
          <RegisterView
            userData={state.userData}
            onUserDataChange={(d) => dispatch({ type: 'SET_USER_DATA', payload: d })}
            onConfirm={handleRegisterConfirm}
            onBack={handleBackFromRegistration}
            loading={registerLoading}
          />
        );
      case STEPS.SHOPPING:
        return (
          <MainCartView
            onPaymentSuccess={handlePaymentSuccess}
            onBack={handleBackFromShopping}
            userData={state.userData}
            sessionUser={state.sessionUser}
            onLogout={handleLogout}
            validatedPayments={validatedPayments}
            setValidatedPayments={setValidatedPayments}
          />
        );
      case STEPS.SUCCESS:
        return <SuccessView onFinish={resetAll} />;
      default:
        return <LoginView onLoginSuccess={handleLoginSuccess} />;
    }
  };

  return (
    <div
      className="h-screen w-screen overflow-hidden flex flex-col bg-gray-50"
      style={{
        maxWidth: 1080,
        maxHeight: 1920,
        margin: '0 auto',
      }}
    >
      <main className="flex-1 overflow-hidden transition-opacity duration-300">
        {renderStep()}
      </main>
    </div>
  );
}

function reducer(state, action) {
  switch (action.type) {
    case 'SET_STEP':
      return { ...state, currentStep: action.payload };
    case 'SET_SESSION_USER':
      return { ...state, sessionUser: action.payload };
    case 'SET_USER_DATA':
      return { ...state, userData: action.payload };
    case 'RESET':
      return {
        currentStep: STEPS.WELCOME,
        sessionUser: state.sessionUser,
        userData: { id: '', nombre: '', telefono: '' },
      };
    default:
      return state;
  }
}

export default function App() {
  return (
    <CartProvider>
      <AppContent />
    </CartProvider>
  );
}
