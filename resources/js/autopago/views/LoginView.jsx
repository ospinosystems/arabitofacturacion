import React, { useState } from 'react';
import { api } from '../services/api';

/** Login simple - usa los mismos endpoints del backend (login, verificarLogin) */
export default function LoginView({ onLoginSuccess }) {
  const [usuario, setUsuario] = useState('');
  const [clave, setClave] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showDollarUpdate, setShowDollarUpdate] = useState(false);
  const [updatingDollar, setUpdatingDollar] = useState(false);
  const [updateMessage, setUpdateMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await api.login({ usuario, clave });
      const data = response.data;

      if (data.estado) {
        onLoginSuccess(data.user, data.session_token);
      } else if (data.requires_update) {
        setShowDollarUpdate(true);
        setUpdateMessage('El valor del dólar debe actualizarse antes de continuar.');
      } else {
        setError(data.msj || 'Usuario o contraseña incorrectos');
      }
    } catch (err) {
      setError(err.response?.data?.msj || 'Error de conexión. Intente nuevamente.');
    } finally {
      setLoading(false);
    }
  };

  const forceUpdateDollar = async () => {
    setUpdatingDollar(true);
    try {
      const response = await api.forceUpdateDollar();
      if (response?.data?.estado) {
        setUpdateMessage(`✅ ${response.data.msj}`);
        setTimeout(() => {
          setShowDollarUpdate(false);
          setUpdateMessage('');
        }, 3000);
      } else {
        setUpdateMessage(response?.data?.msj || 'Error al actualizar');
      }
    } catch (err) {
      setUpdateMessage('Error de conexión con BCV. Use actualización manual.');
    } finally {
      setUpdatingDollar(false);
    }
  };

  return (
    <div className="h-full flex flex-col items-center justify-center px-8 bg-gray-50">
      <img
        src="/images/logo.png"
        alt="Logo"
        className="h-24 object-contain mb-6"
        onError={(e) => { e.target.style.display = 'none'; }}
      />
      <h2 className="text-3xl font-bold text-gray-800 mb-2">Autopago</h2>
      <p className="text-gray-600 mb-8">Inicie sesión para continuar</p>

      <form onSubmit={handleSubmit} className="w-full max-w-md space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
          <input
            type="text"
            value={usuario}
            onChange={(e) => setUsuario(e.target.value)}
            className="w-full px-4 py-3 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
            placeholder="Usuario"
            required
            autoComplete="username"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
          <input
            type="password"
            value={clave}
            onChange={(e) => setClave(e.target.value)}
            className="w-full px-4 py-3 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
            placeholder="Contraseña"
            required
            autoComplete="current-password"
          />
        </div>
        {error && (
          <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{error}</p>
        )}
        <button
          type="submit"
          disabled={loading}
          className="w-full py-4 text-xl font-bold text-white rounded-xl transition-all disabled:opacity-70"
          style={{
            backgroundColor: 'rgba(242, 109, 10, 0.95)',
            boxShadow: '0 4px 20px rgba(242, 109, 10, 0.35)',
          }}
        >
          {loading ? (
            <span className="flex items-center justify-center gap-2">
              <span className="animate-spin h-6 w-6 border-2 border-white border-t-transparent rounded-full" />
              Verificando...
            </span>
          ) : (
            'Iniciar sesión'
          )}
        </button>
      </form>

      {/* Modal actualización dólar */}
      {showDollarUpdate && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
            <h3 className="text-lg font-bold text-gray-800 mb-2">Actualizar valor del dólar</h3>
            <p className="text-gray-600 text-sm mb-4">{updateMessage}</p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={forceUpdateDollar}
                disabled={updatingDollar}
                className="flex-1 py-2 px-4 bg-green-600 text-white rounded-lg font-medium disabled:opacity-50"
              >
                {updatingDollar ? 'Actualizando...' : 'Actualizar (BCV)'}
              </button>
              <button
                type="button"
                onClick={() => setShowDollarUpdate(false)}
                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700"
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
