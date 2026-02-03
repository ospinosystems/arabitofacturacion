import { useState, useEffect } from 'react';
import db from '../database/database';

export default function InventarioSuministrosSucursal({ onBack }) {
    const [inventario, setInventario] = useState([]);
    const [loading, setLoading] = useState(false);
    const [idOrdenRecibir, setIdOrdenRecibir] = useState('');
    const [recibiendo, setRecibiendo] = useState(false);
    const [msj, setMsj] = useState('');

    const cargarInventario = () => {
        setLoading(true);
        db.getInventarioInternoMiSucursal()
            .then(res => {
                if (res.data && res.data.estado && Array.isArray(res.data.data)) {
                    setInventario(res.data.data.filter(r => (r.cantidad || 0) > 0));
                } else {
                    setInventario([]);
                }
            })
            .catch(() => setInventario([]))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        cargarInventario();
    }, []);

    const recibirOrden = () => {
        if (!idOrdenRecibir.trim()) {
            setMsj('Indique el ID de la orden a recibir.');
            return;
        }
        setRecibiendo(true);
        db.recibirOrdenInventarioInterno({ id_orden: idOrdenRecibir.trim() })
            .then(res => {
                if (res.data && res.data.estado) {
                    setMsj(res.data.msj || 'Orden marcada como recibida.');
                    setIdOrdenRecibir('');
                    cargarInventario();
                } else {
                    setMsj(res.data?.msj || 'Error al recibir orden.');
                }
            })
            .catch(() => setMsj('Error de conexión.'))
            .finally(() => setRecibiendo(false));
    };

    return (
        <div className="min-h-screen bg-gray-50 p-4">
            <div className="max-w-4xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-800">Inventario de Suministros (esta sucursal)</h1>
                    {onBack && (
                        <button onClick={onBack} className="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm">
                            Volver
                        </button>
                    )}
                </div>

                {msj && (
                    <div className="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">
                        {msj}
                    </div>
                )}

                <div className="mb-6">
                    <button
                        onClick={cargarInventario}
                        disabled={loading}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        {loading ? 'Cargando...' : 'Actualizar inventario'}
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow p-4 mb-6">
                    <h2 className="text-lg font-semibold text-gray-800 mb-3">Inventario disponible</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-gray-50">
                                    <th className="text-left p-2">SKU</th>
                                    <th className="text-left p-2">Descripción</th>
                                    <th className="text-right p-2">Cantidad</th>
                                    <th className="text-left p-2">Unidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                {inventario.length === 0 && !loading && (
                                    <tr><td colSpan={4} className="p-4 text-center text-gray-500">Sin stock o no hay conexión con central.</td></tr>
                                )}
                                {inventario.map((r, i) => (
                                    <tr key={i} className="border-b">
                                        <td className="p-2">{r.sku}</td>
                                        <td className="p-2">{r.descripcion}</td>
                                        <td className="p-2 text-right">{r.cantidad}</td>
                                        <td className="p-2">{r.unidad_medida || 'UND'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow p-4">
                    <h2 className="text-lg font-semibold text-gray-800 mb-3">Recibir / Aceptar ingreso</h2>
                    <p className="text-sm text-gray-600 mb-3">Indique el ID de la orden de entrada que desea marcar como recibida en esta sucursal.</p>
                    <div className="flex gap-2 items-center">
                        <input
                            type="text"
                            value={idOrdenRecibir}
                            onChange={e => setIdOrdenRecibir(e.target.value)}
                            placeholder="ID orden"
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32"
                        />
                        <button
                            onClick={recibirOrden}
                            disabled={recibiendo}
                            className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm disabled:opacity-50"
                        >
                            {recibiendo ? 'Procesando...' : 'Marcar como recibida'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
