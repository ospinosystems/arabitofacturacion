import React, { useState, useCallback, useMemo, useEffect, useRef } from 'react';
import { ArrowLeft, CreditCard, Smartphone, AlertCircle, Search } from 'lucide-react';
import BarcodeHandler from '../components/BarcodeHandler';
import GuideSection from '../components/GuideSection';
import EmptyCartState from '../components/EmptyCartState';
import CartItem from '../components/CartItem';
import ModalBuscarProducto from '../components/ModalBuscarProducto';
import ModalPosDebito from '../components/ModalPosDebito';
import ModalTransferencia from '../components/ModalTransferencia';
import { useCart } from '../context/CartContext';
import { api } from '../services/api';
import { TASA_DOLAR_BS, formatBs, formatUsd, PAYMENT_METHODS, PAYMENT_STATUS, PAYMENT_METHOD_COLORS } from '../config';

function FlashOverlay({ show }) {
  if (!show) return null;
  return (
    <div
      className="fixed inset-0 z-40 pointer-events-none"
      style={{
        background: 'linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(34,197,94,0.3) 100%)',
        animation: 'flashFade 100ms ease-out forwards',
      }}
    />
  );
}

function CartContent({ onPaymentSuccess, onBack, userData, sessionUser, onLogout, validatedPayments: validatedPaymentsProp, setValidatedPayments: setValidatedPaymentsProp }) {
  const { items, orderId, tryAddItem, stockExceededMessage } = useCart();
  const [flash, setFlash] = useState(false);
  const [barcodeNotFound, setBarcodeNotFound] = useState(null);
  const [validatedPaymentsFallback, setValidatedPaymentsFallback] = useState([]);
  const validatedPayments = validatedPaymentsProp !== undefined ? validatedPaymentsProp : validatedPaymentsFallback;
  const setValidatedPayments = setValidatedPaymentsProp || setValidatedPaymentsFallback;
  const [showModalPos, setShowModalPos] = useState(false);
  const [showModalTransfer, setShowModalTransfer] = useState(false);
  const [showModalBuscar, setShowModalBuscar] = useState(false);
  const [agregarPanelOpen, setAgregarPanelOpen] = useState(false);
  const [ordenError, setOrdenError] = useState(null);
  const [guardandoOrden, setGuardandoOrden] = useState(false);
  const [paymentMethodsExpanded, setPaymentMethodsExpanded] = useState(false);

  const totals = useMemo(() => {
    const totalBs = items.reduce((sum, i) => sum + i.price * i.quantity, 0);
    const totalUSD = totalBs / TASA_DOLAR_BS;
    return { totalBs, totalUSD };
  }, [items]);

  const totalPagado = useMemo(
    () => validatedPayments
      .filter((p) => p.status === PAYMENT_STATUS.VALIDATED)
      .reduce((s, p) => s + (p.amount || 0), 0),
    [validatedPayments]
  );

  const totalRestante = Math.max(0, Math.round((totals.totalBs - totalPagado) * 100) / 100);
  const isTotalCubierto = totalRestante < 0.01;

  const hasProcessedRef = useRef(false);

  useEffect(() => {
    if (items.length === 0 || !isTotalCubierto || validatedPayments.length === 0 || hasProcessedRef.current || !orderId) {
      return;
    }
    hasProcessedRef.current = true;
    setOrdenError(null);
    setGuardandoOrden(true);

    const payments = validatedPayments
      .filter((p) => p.status === PAYMENT_STATUS.VALIDATED)
      .map((p) => {
        const method = PAYMENT_METHODS.find((m) => m.type === p.type);
        const base = {
          type: p.type,
          amount: p.amount || 0,
          currency: p.currency || (method && method.currency) || 'bs',
        };
        if (p.type === 'card') {
          const ref4 = (p.refCorta || (p.referencia && String(p.referencia).replace(/\D/g, '').slice(-4)) || '').padStart(4, '0');
          base.referencia = ref4.slice(-4);
          if (p.posData && typeof p.posData === 'object') {
            base.posData = p.posData;
          }
        }
        if (p.type === 'transfer') {
          if (p.referencia) base.referencia = p.referencia;
          if (p.banco) base.banco = p.banco;
        }
        return base;
      });

    const payload = {
      uuid: orderId,
      id_cliente: userData?.clienteId || 1,
      items: items.map((i) => ({ id: i.id, quantity: i.quantity })),
      payments,
    };

    api
      .completarOrden(payload)
      .then((res) => {
        if (res.data && res.data.estado) {
          onPaymentSuccess();
        } else {
          hasProcessedRef.current = false;
          setOrdenError(res.data?.msj || 'Error al guardar la orden');
        }
      })
      .catch((err) => {
        hasProcessedRef.current = false;
        setOrdenError(err.response?.data?.msj || err.message || 'Error al guardar la orden');
      })
      .finally(() => {
        setGuardandoOrden(false);
      });
  }, [items.length, isTotalCubierto, validatedPayments.length, orderId, userData?.clienteId, onPaymentSuccess]);

  useEffect(() => {
    if (items.length === 0) setPaymentMethodsExpanded(false);
  }, [items.length]);

  const handleItemScanned = useCallback(() => {
    setBarcodeNotFound(null);
    setFlash(true);
    setTimeout(() => setFlash(false), 100);
  }, []);

  const handlePaymentSuccess = (payment) => {
    setValidatedPayments((prev) => [...prev, payment]);
    if (showModalPos) setShowModalPos(false);
    if (showModalTransfer) setShowModalTransfer(false);
  };

  const handleConfirmarPago = () => {
    if (isTotalCubierto) onPaymentSuccess();
  };

  return (
    <div className="h-full flex flex-col">
      <div className="flex-shrink-0 px-6 py-3 border-b border-gray-100 bg-white flex items-center justify-between gap-4">
        <button
          type="button"
          onClick={onBack}
          className="flex items-center gap-2 py-2 px-4 rounded-lg text-lg font-semibold text-gray-800 hover:bg-gray-50 transition-colors"
        >
          <ArrowLeft size={20} strokeWidth={2.5} />
          Volver
        </button>
        {userData?.nombre?.trim() && (
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-50 border border-orange-200 min-w-0 flex-1 justify-center max-w-md">
            <span className="text-sm font-medium text-orange-800 truncate" title={userData.nombre}>
              Cliente: {userData.nombre}
            </span>
          </div>
        )}
      </div>
      <BarcodeHandler
        disabled={showModalBuscar}
        onItemScanned={handleItemScanned}
        onNotFound={(barcode) => {
          setBarcodeNotFound(barcode);
          setTimeout(() => setBarcodeNotFound(null), 5000);
        }}
      />
      <FlashOverlay show={flash} />

      <section className="flex-shrink-0 px-6 py-2 space-y-2">
        <GuideSection isEmpty={items.length === 0} />
        <div className="flex justify-end">
          <button
            type="button"
            onClick={() => setShowModalBuscar(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors border border-orange-200"
          >
            <Search size={18} strokeWidth={2.5} />
            Buscar producto
          </button>
        </div>
      </section>

      <ModalBuscarProducto
        open={showModalBuscar}
        onClose={() => { setShowModalBuscar(false); setAgregarPanelOpen(false); }}
        onAgregarPanelOpenChange={setAgregarPanelOpen}
        customerName={userData?.nombre}
        onSelectProduct={(product) => {
          if (tryAddItem(product)) {
            handleItemScanned();
            setShowModalBuscar(false);
          }
        }}
      />

      <section className="flex-1 overflow-y-auto px-6 py-4 min-h-0 flex flex-col">
        {/* Mensajes arriba del listado de productos */}
        {barcodeNotFound && (
          <div
            className="flex-shrink-0 flex items-center gap-3 px-4 py-3 mb-4 rounded-xl bg-red-50 border-2 border-red-200 animate-fade-in"
            role="alert"
            aria-live="assertive"
          >
            <AlertCircle className="text-red-600 flex-shrink-0" size={28} strokeWidth={2} />
            <div>
              <p className="text-lg font-bold text-red-800">Producto no encontrado</p>
              <p className="text-sm text-red-700 font-mono">Código: {barcodeNotFound}</p>
            </div>
          </div>
        )}
        {stockExceededMessage && (
          <div
            className="flex-shrink-0 flex items-center gap-3 px-4 py-3 mb-4 rounded-xl text-white font-semibold shadow-md animate-fade-in"
            style={{ backgroundColor: 'rgba(220, 38, 38, 0.95)' }}
            role="alert"
          >
            <AlertCircle className="flex-shrink-0" size={28} strokeWidth={2} />
            <p className="text-lg">{stockExceededMessage}</p>
          </div>
        )}

        {items.length === 0 ? (
          <EmptyCartState />
        ) : (
          <div className="flex flex-col gap-4">
            {items.map((item) => (
              <CartItem key={item.id} item={item} />
            ))}
          </div>
        )}
      </section>

      <div className="flex-shrink-0 px-6 py-4 bg-white shadow-[0_-12px_40px_rgba(0,0,0,0.08)] border-t border-gray-100">
        {items.length === 0 ? (
          <div className="py-6 text-center">
            <p className="text-xl font-medium text-gray-700">
              Los totales y métodos de pago aparecerán aquí al agregar productos
            </p>
          </div>
        ) : (
          <>
            <div className="mb-4">
              <div
                className="rounded-xl p-4 flex items-center justify-between"
                style={{ backgroundColor: 'rgba(242, 109, 10, 0.08)' }}
              >
                <span className="text-3xl sm:text-4xl font-black text-gray-800">TOTAL A PAGAR</span>
                <div className="text-right">
                  <div className="text-7xl sm:text-8xl font-black text-green-600 tracking-tight">
                    Bs. {formatBs(totals.totalBs)}
                  </div>
                  <div className="text-sm font-medium text-gray-600 mt-0.5">
                    Ref. USD {formatUsd(totals.totalUSD)}
                  </div>
                </div>
              </div>
            </div>

            {/* Lista de pagos validados */}
            {validatedPayments.length > 0 && (
              <div className="mb-4 border border-gray-200 rounded-lg overflow-hidden">
                <div className="px-3 py-2 bg-gray-50 border-b border-gray-200">
                  <span className="text-sm font-medium text-gray-800">Pagos registrados</span>
                </div>
                <div className="max-h-28 overflow-y-auto divide-y divide-gray-100">
                  {validatedPayments.map((p, idx) => (
                    <div
                      key={idx}
                      className={`flex items-center justify-between px-3 py-2 ${
                        p.status === PAYMENT_STATUS.VALIDATED
                          ? 'bg-green-50'
                          : p.status === PAYMENT_STATUS.REJECTED
                            ? 'bg-red-50'
                            : 'bg-amber-50'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <i
                          className={`fa ${p.type === 'card' ? 'fa-credit-card' : p.type === 'manual' ? 'fa-hand-holding-usd' : 'fa-mobile-alt'}`}
                          style={{ color: (PAYMENT_METHOD_COLORS[p.type] || PAYMENT_METHOD_COLORS.card).text }}
                        />
                        <span className="text-sm font-medium text-gray-900">{p.description}</span>
                        {p.referencia && (
                          <span className="text-xs text-gray-500">Ref: {p.refCorta || p.referencia?.slice(-4)}</span>
                        )}
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-gray-800">
                          Bs. {formatBs(p.amount || 0)}
                        </span>
                        <span
                          className={`text-xs font-medium px-2 py-0.5 rounded ${
                            p.status === PAYMENT_STATUS.VALIDATED
                              ? 'bg-green-200 text-green-800'
                              : p.status === PAYMENT_STATUS.REJECTED
                                ? 'bg-red-200 text-red-800'
                                : 'bg-amber-200 text-amber-800'
                          }`}
                        >
                          {p.status === PAYMENT_STATUS.VALIDATED ? 'Aprobado' : p.status === PAYMENT_STATUS.REJECTED ? 'Rechazado' : 'Pendiente'}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="px-3 py-2 bg-gray-50 border-t border-gray-200 flex justify-between text-sm">
                  <span className="font-medium text-gray-700">Total pagado:</span>
                  <span className="font-bold text-green-700">Bs. {formatBs(totalPagado)}</span>
                </div>
              </div>
            )}

            {(() => {
              const needsToSelectPayment = items.length > 0 && !isTotalCubierto;
              const showGiantPagar = needsToSelectPayment && !paymentMethodsExpanded;
              return (
                <div
                  className={`rounded-2xl p-4 transition-all duration-300 ${needsToSelectPayment && paymentMethodsExpanded ? 'payment-method-attention bg-amber-50/80 border-2 border-amber-300' : 'bg-transparent border-2 border-transparent'}`}
                >
                  {showGiantPagar ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setPaymentMethodsExpanded(true)}
                        className="w-full rounded-2xl py-8 px-6 font-black text-4xl sm:text-5xl md:text-6xl text-white shadow-lg hover:shadow-xl active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-4 bg-green-600 hover:bg-green-700 border-4 border-green-700 focus:ring-4 focus:ring-green-300 focus:outline-none"
                      >
                        <i className="fa fa-credit-card text-4xl sm:text-5xl md:text-6xl" />
                        PAGAR
                      </button>
                      
                    </>
                  ) : (
                    <>
                      <div className="flex items-center justify-between gap-2 mb-3">
                        <p className="text-lg font-semibold text-gray-800">
                          Método de pago
                        </p>
                        {needsToSelectPayment && (
                          <span className="payment-method-label-pulse inline-flex items-center gap-1.5 rounded-full bg-amber-500 px-3 py-1 text-sm font-bold text-white shadow-md">
                            <span className="inline-block w-2 h-2 rounded-full bg-white animate-pulse" />
                            Selecciona uno
                          </span>
                        )}
                      </div>

                      <div className="grid grid-cols-2 gap-3 mb-4">
                        {PAYMENT_METHODS.map((method) => {
                          const colors = PAYMENT_METHOD_COLORS[method.type] || PAYMENT_METHOD_COLORS.card;
                          return (
                            <button
                              key={method.type}
                              type="button"
                              onClick={() => {
                                if (items.length === 0 || isTotalCubierto) return;
                                if (method.type === 'card') setShowModalPos(true);
                                else setShowModalTransfer(true);
                              }}
                              disabled={items.length === 0 || isTotalCubierto}
                              className="flex items-center gap-3 p-4 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all active:scale-[0.98] disabled:opacity-50"
                              style={{
                                borderColor: colors.bgLight,
                                backgroundColor: colors.bgOverlay,
                              }}
                              onMouseEnter={(e) => {
                                if (items.length > 0 && !isTotalCubierto) {
                                  e.currentTarget.style.borderColor = colors.bg;
                                  e.currentTarget.style.backgroundColor = colors.bgLight;
                                }
                              }}
                              onMouseLeave={(e) => {
                                e.currentTarget.style.borderColor = colors.bgLight;
                                e.currentTarget.style.backgroundColor = colors.bgOverlay;
                              }}
                            >
                              <div
                                className="w-14 h-14 rounded-lg flex items-center justify-center flex-shrink-0"
                                style={{ backgroundColor: colors.bgOverlay }}
                              >
                                <i className={`fa ${method.icon} text-2xl`} style={{ color: colors.text }} />
                              </div>
                              <div className="text-left min-w-0">
                                <p className="text-xl font-bold text-gray-900 leading-tight">
                                  {method.description}
                                </p>
                                <p className="text-sm text-gray-500">
                                  {method.type === 'card' ? 'Inserte o acerque' : 'Escanee el QR'}
                                </p>
                              </div>
                            </button>
                          );
                        })}
                      </div>

                      {isTotalCubierto ? (
                        <div className="w-full py-4 text-center">
                          <p className="text-xl font-bold text-green-700">Total cubierto</p>
                          <p className="text-sm text-gray-600 mt-1">
                            {guardandoOrden ? 'Guardando orden...' : ordenError ? 'Error al guardar' : 'Procesando...'}
                          </p>
                          {ordenError && (
                            <p className="text-sm text-red-600 mt-2 font-medium">{ordenError}</p>
                          )}
                        </div>
                      ) : (
                        null
                      )}
                    </>
                  )}
                </div>
              );
            })()}
          </>
        )}
      </div>

      <ModalPosDebito
        open={showModalPos}
        onClose={() => setShowModalPos(false)}
        montoTotal={totalRestante > 0 ? totalRestante : totals.totalBs}
        sessionUser={sessionUser}
        cedulaCliente={userData?.id}
        pedidoId={orderId}
        onSuccess={handlePaymentSuccess}
      />

      <ModalTransferencia
        open={showModalTransfer}
        onClose={() => setShowModalTransfer(false)}
        montoTotal={totalRestante > 0 ? totalRestante : totals.totalBs}
        pedidoId={orderId}
        cedula={userData?.id || ''}
        telefonoCliente={userData?.telefono || ''}
        onSuccess={handlePaymentSuccess}
      />
    </div>
  );
}

export default function MainCartView({ onPaymentSuccess, onBack, userData, sessionUser, onLogout, validatedPayments, setValidatedPayments }) {
  return (
    <CartContent
      onPaymentSuccess={onPaymentSuccess}
      onBack={onBack}
      userData={userData}
      sessionUser={sessionUser}
      onLogout={onLogout}
      validatedPayments={validatedPayments}
      setValidatedPayments={setValidatedPayments}
    />
  );
}
