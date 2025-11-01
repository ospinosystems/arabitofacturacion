import React, { useState } from 'react';
import axios from 'axios';

const ConsultarInventario = () => {
    const [modoConsulta, setModoConsulta] = useState('producto'); // 'producto' o 'ubicacion'
    const [productos, setProductos] = useState([]);
    const [ubicaciones, setUbicaciones] = useState([]);
    const [resultado, setResultado] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [loading, setLoading] = useState(false);

    const buscarProductos = async () => {
        if (!buscar.trim()) return;
        try {
            const response = await axios.get('/inventario/buscar', {
                params: { buscar }
            });
            setProductos(response.data.productos || []);
        } catch (error) {
            console.error('Error buscando:', error);
        }
    };

    const buscarUbicaciones = async () => {
        if (!buscar.trim()) return;
        try {
            const response = await axios.get('/warehouses', {
                params: { buscar }
            });
            setUbicaciones(response.data.warehouses || []);
        } catch (error) {
            console.error('Error buscando:', error);
        }
    };

    const consultarProducto = async (inventarioId) => {
        try {
            setLoading(true);
            const response = await axios.get(`/warehouse-inventory/producto/${inventarioId}`);
            setResultado({ tipo: 'producto', data: response.data });
            setProductos([]);
        } catch (error) {
            console.error('Error:', error);
            alert('Error al consultar producto');
        } finally {
            setLoading(false);
        }
    };

    const consultarUbicacion = async (warehouseId) => {
        try {
            setLoading(true);
            const response = await axios.get(`/warehouse-inventory/ubicacion/${warehouseId}`);
            setResultado({ tipo: 'ubicacion', data: response.data });
            setUbicaciones([]);
        } catch (error) {
            console.error('Error:', error);
            alert('Error al consultar ubicación');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="consultar-inventario">
            <div className="card mb-3">
                <div className="card-header">
                    <h5 className="mb-0">Consultar Inventario</h5>
                </div>
                <div className="card-body">
                    <div className="btn-group mb-3" role="group">
                        <button 
                            type="button" 
                            className={`btn ${modoConsulta === 'producto' ? 'btn-primary' : 'btn-outline-primary'}`}
                            onClick={() => { setModoConsulta('producto'); setResultado(null); setBuscar(''); }}
                        >
                            Por Producto
                        </button>
                        <button 
                            type="button" 
                            className={`btn ${modoConsulta === 'ubicacion' ? 'btn-primary' : 'btn-outline-primary'}`}
                            onClick={() => { setModoConsulta('ubicacion'); setResultado(null); setBuscar(''); }}
                        >
                            Por Ubicación
                        </button>
                    </div>

                    <div className="input-group mb-3">
                        <input
                            type="text"
                            className="form-control"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            placeholder={modoConsulta === 'producto' ? 'Buscar producto...' : 'Buscar ubicación...'}
                            onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), modoConsulta === 'producto' ? buscarProductos() : buscarUbicaciones())}
                        />
                        <button 
                            className="btn btn-primary"
                            onClick={modoConsulta === 'producto' ? buscarProductos : buscarUbicaciones}
                        >
                            Buscar
                        </button>
                    </div>

                    {/* Resultados de búsqueda */}
                    {productos.length > 0 && (
                        <div className="list-group mb-3">
                            {productos.map(p => (
                                <button
                                    key={p.id}
                                    className="list-group-item list-group-item-action"
                                    onClick={() => consultarProducto(p.id)}
                                >
                                    <strong>{p.codigo_barras}</strong> - {p.descripcion}
                                </button>
                            ))}
                        </div>
                    )}

                    {ubicaciones.length > 0 && (
                        <div className="list-group mb-3">
                            {ubicaciones.map(w => (
                                <button
                                    key={w.id}
                                    className="list-group-item list-group-item-action"
                                    onClick={() => consultarUbicacion(w.id)}
                                >
                                    <strong>{w.codigo}</strong> - {w.nombre || w.tipo}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Resultados */}
            {loading && <div className="text-center p-5">Cargando...</div>}

            {resultado && resultado.tipo === 'producto' && (
                <div className="card">
                    <div className="card-header">
                        <h6 className="mb-0">Ubicaciones del Producto: {resultado.data.producto?.descripcion}</h6>
                        <small>Total en stock: {resultado.data.totalStock || 0} unidades</small>
                    </div>
                    <div className="card-body">
                        <div className="table-responsive">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Ubicación</th>
                                        <th>Cantidad</th>
                                        <th>Lote</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {resultado.data.ubicaciones?.map(u => (
                                        <tr key={u.id}>
                                            <td><strong>{u.warehouse?.codigo}</strong></td>
                                            <td>{u.cantidad}</td>
                                            <td>{u.lote || '-'}</td>
                                            <td>{u.fecha_vencimiento || '-'}</td>
                                            <td><span className="badge bg-success">{u.estado}</span></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {resultado && resultado.tipo === 'ubicacion' && (
                <div className="card">
                    <div className="card-header">
                        <h6 className="mb-0">Productos en: {resultado.data.warehouse?.codigo}</h6>
                        <small>{resultado.data.totalProductos} productos | {resultado.data.totalUnidades} unidades</small>
                    </div>
                    <div className="card-body">
                        <div className="table-responsive">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Lote</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {resultado.data.productos?.map(p => (
                                        <tr key={p.id}>
                                            <td>{p.inventario?.codigo_barras}</td>
                                            <td>{p.inventario?.descripcion}</td>
                                            <td>{p.cantidad}</td>
                                            <td>{p.lote || '-'}</td>
                                            <td>{p.fecha_vencimiento || '-'}</td>
                                            <td><span className="badge bg-success">{p.estado}</span></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ConsultarInventario;

