import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import BuscarPedido from './BuscarPedido';
import DespachoRapido from './DespachoRapido';
import DespacharPedido from './DespacharPedido';
import Historico from './Historico';
import { playExitoSound } from './soundAlert';
import { PprKeyboardProvider, usePprKeyboard } from './PprKeyboardContext';
import PprOnScreenKeyboard from './PprOnScreenKeyboard';

const TAB_DESPACHAR = 'despachar';
const TAB_HISTORICO = 'historico';

function AppContent() {
  const { activeInput } = usePprKeyboard();
  const [tab, setTab] = useState(TAB_DESPACHAR);
  const [step, setStep] = useState('buscar'); // 'buscar' | 'confirmar_rapido' | 'despachar' | 'despachado_ok'
  const [pedido, setPedido] = useState(null);
  const [lastDespachadoId, setLastDespachadoId] = useState(null);
  const [lastDespachadoItems, setLastDespachadoItems] = useState([]);

  const [clienteEsCF, setClienteEsCF] = useState(false);
  const [clienteAnclado, setClienteAnclado] = useState(false);
  const [yaDespachadoCompleto, setYaDespachadoCompleto] = useState(false);
  const [fechaUltimoDespacho, setFechaUltimoDespacho] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);
  const [entregaParcialSolicitada, setEntregaParcialSolicitada] = useState(false);
  const scanBufferOnSuccessRef = useRef('');

  const buscarPedido = (id) => {
    setError('');
    setSuccess('');
    setLoading(true);
    let delegadoAConfirmar = false;
    axios.get(`/ppr/pedido/${id}`)
      .then((res) => {
        const d = res.data;
        if (d.estado && d.pedido) {
          const p = d.pedido;
          setPedido(p);
          setClienteEsCF(!!d.cliente_es_cf);
          setClienteAnclado(!!d.cliente_anclado);
          setYaDespachadoCompleto(!!d.ya_despachado_completo);
          setFechaUltimoDespacho(d.fecha_ultimo_despacho || '');

          if (entregaParcialSolicitada) {
            setStep('despachar');
            return;
          }
          const items = (p.items || []).filter((i) => i.producto);
          const pendienteVal = (item) => (item.pendiente != null ? item.pendiente : parseFloat(item.cantidad, 10));
          const payload = items
            .filter((i) => pendienteVal(i) > 0)
            .map((i) => ({
              id_item_pedido: i.id,
              unidades_entregadas: pendienteVal(i),
            }));
          if (payload.length === 0) {
            setStep('confirmar_rapido');
            return;
          }
          delegadoAConfirmar = true;
          confirmarEntrega({
            id_pedido: p.id,
            items: payload,
            retiro_total: true,
          }, p);
        } else {
          setError(d.msj || 'Pedido no encontrado');
        }
      })
      .catch((err) => {
        const msg = err.response?.data?.msj || err.message || 'Error al buscar pedido';
        setError(msg);
      })
      .finally(() => {
        if (!delegadoAConfirmar) setLoading(false);
      });
  };

  const volverABuscar = () => {
    setStep('buscar');
    setPedido(null);
    setLastDespachadoId(null);
    setLastDespachadoItems([]);
    setClienteAnclado(false);
    setYaDespachadoCompleto(false);
    setFechaUltimoDespacho('');
    setEntregaParcialSolicitada(false);
    setError('');
    setSuccess('');
  };

  const confirmarEntrega = (body, pedidoRef) => {
    setError('');
    setSuccess('');
    setLoading(true);
    axios.post('/ppr/registrar-entrega', body)
      .then((res) => {
        const d = res.data;
        if (d.estado) {
          playExitoSound();
          setLastDespachadoId(body.id_pedido ?? null);
          const itemsPayload = body.items || [];
          const pedidoItems = (pedidoRef?.items || []).filter((i) => i.producto);
          const itemsToShow = itemsPayload.map((bi) => {
            const found = pedidoItems.find((i) => i.id === bi.id_item_pedido);
            return {
              descripcion: found?.producto?.descripcion || '—',
              codigo_barras: found?.producto?.codigo_barras || '—',
              unidades_entregadas: bi.unidades_entregadas,
            };
          });
          setLastDespachadoItems(itemsToShow);
          setSuccess(d.msj || 'Listo.');
          setStep('despachado_ok');
          setPedido(null);
        } else {
          setError(d.msj || 'Error al registrar');
        }
      })
      .catch((err) => {
        const msg = err.response?.data?.msj || err.message || 'Error al registrar entrega';
        setError(msg);
      })
      .finally(() => setLoading(false));
  };


  // Volver al home (buscar) tras 10 s sin actividad en pantalla "Factura despachada"
  useEffect(() => {
    if (step !== 'despachado_ok') return;
    const t = setTimeout(() => volverABuscar(), 10000);
    return () => clearTimeout(t);
  }, [step]);

  // En pantalla "Factura despachada": escuchar escáner (dígitos + Enter) para buscar otra factura sin tocar "Despachar otra"
  useEffect(() => {
    if (tab !== TAB_DESPACHAR || step !== 'despachado_ok') return;
    scanBufferOnSuccessRef.current = '';
    const onKeyDown = (e) => {
      if (e.key === 'Enter') {
        const buf = scanBufferOnSuccessRef.current.replace(/\D/g, '').slice(0, 8);
        if (buf) {
          e.preventDefault();
          e.stopPropagation();
          const id = parseInt(buf, 10);
          scanBufferOnSuccessRef.current = '';
          volverABuscar();
          buscarPedido(id);
        }
        return;
      }
      if (/^[0-9]$/.test(e.key)) {
        e.preventDefault();
        e.stopPropagation();
        scanBufferOnSuccessRef.current = (scanBufferOnSuccessRef.current + e.key).slice(0, 8);
      }
    };
    document.addEventListener('keydown', onKeyDown, true);
    return () => document.removeEventListener('keydown', onKeyDown, true);
  }, [tab, step]);

  const cerrarSesion = () => {
    setMenuOpen(false);
    // Limpiar sesión en cliente para que /login no redirija de nuevo a /ppr
    localStorage.removeItem('user_data');
    localStorage.removeItem('session_token');
    window.location.href = '/logout';
  };

  return (
    <div className="min-h-full flex flex-col flex-1 bg-gray-100">
      <header className="bg-sinapsis text-white py-1.5 px-3 shadow relative">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={() => setMenuOpen((o) => !o)}
              className="p-2 -m-2 rounded-lg hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
              aria-label="Menú"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <h1 className="text-sm font-semibold truncate">PPR</h1>
          </div>
          <nav className="flex gap-2 shrink-0">
            <button
              type="button"
              onClick={() => { setTab(TAB_DESPACHAR); setStep('buscar'); setPedido(null); setError(''); setMenuOpen(false); }}
              className={`px-3 py-1.5 rounded-md text-sm font-medium ${tab === TAB_DESPACHAR ? 'bg-white text-sinapsis' : 'bg-white/20 text-white'}`}
            >
              Despachar
            </button>
            <button
              type="button"
              onClick={() => { setTab(TAB_HISTORICO); setMenuOpen(false); }}
              className={`px-3 py-1.5 rounded-md text-sm font-medium ${tab === TAB_HISTORICO ? 'bg-white text-sinapsis' : 'bg-white/20 text-white'}`}
            >
              Histórico
            </button>
          </nav>
        </div>
        {menuOpen && (
          <>
            <div className="absolute left-0 right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 min-w-[180px]" style={{ left: '0.75rem', right: 'auto' }}>
              <button
                type="button"
                onClick={cerrarSesion}
                className="w-full flex items-center gap-2 px-4 py-3 text-left text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 font-medium"
              >
                <svg className="w-5 h-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Cerrar sesión
              </button>
            </div>
            <button
              type="button"
              aria-label="Cerrar menú"
              className="fixed inset-0 z-40"
              onClick={() => setMenuOpen(false)}
            />
          </>
        )}
      </header>

      <main className={`flex-1 min-h-0 overflow-auto py-4 ${(error || (success && step !== 'despachado_ok')) ? 'pb-24' : ''} ${activeInput ? 'pb-72' : ''}`}>
        {tab === TAB_HISTORICO && <Historico />}
        {tab === TAB_DESPACHAR && step === 'despachado_ok' && (
          <div className="flex flex-col flex-1 min-h-0 items-center px-6 py-8 text-center">
            <div className="flex flex-col items-center justify-center max-w-md w-full">
              <div className="w-24 h-24 rounded-full bg-green-500 flex items-center justify-center shadow-lg mb-6" aria-hidden="true">
                <svg className="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round">
                  <path d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h2 className="text-2xl font-bold text-gray-900 mb-2">
                Factura {lastDespachadoId != null ? lastDespachadoId : '—'} despachada
              </h2>
              {lastDespachadoItems.length > 0 && (
                <div className="w-full mb-6 text-left">
                  <p className="text-sm font-semibold text-gray-700 mb-2">Items despachados:</p>
                  <ul className="space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    {lastDespachadoItems.map((item, idx) => (
                      <li key={idx} className="flex justify-between items-start gap-2 text-sm text-gray-800">
                        <div className="flex-1 min-w-0">
                          <span className="block truncate font-medium">{item.descripcion}</span>
                          <span className="block text-xs font-mono text-gray-500 mt-0.5">Cód. barras: {item.codigo_barras}</span>
                        </div>
                        <span className="font-mono font-semibold text-green-700 shrink-0">{item.unidades_entregadas}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
              <p className="text-lg text-gray-600 mb-1">
                Puede despachar otra factura.
              </p>
              <p className="text-sm text-gray-500 mb-8">
                Escanee otra factura aquí o toque el botón.
              </p>
              <button
                type="button"
                onClick={volverABuscar}
                className="w-full max-w-xs mx-auto py-4 px-6 rounded-xl bg-sinapsis hover:bg-sinapsis-dark text-white text-xl font-semibold shadow-lg active:scale-[0.98] transition-colors"
              >
                Despachar otra
              </button>
            </div>
          </div>
        )}
        {tab === TAB_DESPACHAR && step === 'buscar' && (
          <BuscarPedido
            onBuscar={buscarPedido}
            loading={loading}
            entregaParcialSolicitada={entregaParcialSolicitada}
            onEntregaParcialChange={setEntregaParcialSolicitada}
          />
        )}
        {tab === TAB_DESPACHAR && step === 'confirmar_rapido' && pedido && (
          <DespachoRapido
            pedido={pedido}
            yaDespachadoCompleto={yaDespachadoCompleto}
            fechaUltimoDespacho={fechaUltimoDespacho}
            onEntregarTodo={(body) => confirmarEntrega(body, pedido)}
            onDespacharPorItems={() => setStep('despachar')}
            onVolver={volverABuscar}
            loading={loading}
          />
        )}
        {tab === TAB_DESPACHAR && step === 'despachar' && pedido && (
          <DespacharPedido
            pedido={pedido}
            clienteEsCF={clienteEsCF}
            clienteAnclado={clienteAnclado}
            yaDespachadoCompleto={yaDespachadoCompleto}
            fechaUltimoDespacho={fechaUltimoDespacho}
            onConfirmar={(body) => confirmarEntrega(body, pedido)}
            onVolver={volverABuscar}
            loading={loading}
          />
        )}
      </main>

      {(error || (success && step !== 'despachado_ok')) && (
        <div className="fixed bottom-0 left-0 right-0 safe-area-padding p-3 pb-4 bg-gray-100/95 backdrop-blur-sm border-t border-gray-200 shadow-lg z-50">
          {error && (
            <div className="mx-auto max-w-md p-3 rounded-xl bg-red-100 text-red-800 text-sm">
              {error}
            </div>
          )}
          {success && step !== 'despachado_ok' && (
            <div className="mx-auto max-w-md p-3 rounded-xl bg-green-100 text-green-800 text-sm">
              {success}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function App() {
  return (
    <PprKeyboardProvider>
      <AppContent />
      <PprOnScreenKeyboard />
    </PprKeyboardProvider>
  );
}
