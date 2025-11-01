import React, { useState, useEffect } from 'react';
import axios from 'axios';

const HistorialMovimientos = () => {
    const [movimientos, setMovimientos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        tipo: '',
        fecha_inicio: '',
        fecha_fin: '',
        warehouse_id: ''
    });
    const [warehouses, setWarehouses] = useState([]);

    useEffect(() => {
        loadWarehouses();
        loadMovimientos();
    }, []);

    const loadWarehouses = async () => {
        try {
            const response = await axios.get('/warehouses');
            setWarehouses(response.data.warehouses || []);
        } catch (error) {
            console.error('Error:', error);
        }
    };

    const loadMovimientos = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/warehouse-inventory/historial', { params: filters });
            setMovimientos(response.data.movimientos || []);
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cargar historial');
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilters(prev => ({ ...prev, [name]: value }));
    };

    const handleSearch = () => {
        loadMovimientos();
    };

    const getTipoBadge = (tipo) => {
        const badges = {
            entrada: 'badge bg-success',
            salida: 'badge bg-danger',
            transferencia: 'badge bg-info',
            ajuste: 'badge bg-warning',
            recepcion: 'badge bg-primary'
        };
        return <span className={badges[tipo] || 'badge bg-secondary'}>{tipo}</span>;
    };

    if (loading) {
        return <div className="text-center p-5">Cargando...</div>;
    }

    return (
        <div className="historial-movimientos">
            {/* Filtros */}
            <div className="card mb-3">
                <div className="card-body">
                    <div className="row g-3">
                        <div className="col-md-3">
                            <label className="form-label">Tipo de Movimiento</label>
                            <select
                                className="form-select"
                                name="tipo"
                                value={filters.tipo}
                                onChange={handleFilterChange}
                            >
                                <option value="">Todos</option>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="ajuste">Ajuste</option>
                                <option value="recepcion">Recepción</option>
                            </select>
                        </div>

                        <div className="col-md-3">
                            <label className="form-label">Ubicación</label>
                            <select
                                className="form-select"
                                name="warehouse_id"
                                value={filters.warehouse_id}
                                onChange={handleFilterChange}
                            >
                                <option value="">Todas</option>
                                {warehouses.map(w => (
                                    <option key={w.id} value={w.id}>{w.codigo}</option>
                                ))}
                            </select>
                        </div>

                        <div className="col-md-2">
                            <label className="form-label">Fecha Inicio</label>
                            <input
                                type="date"
                                className="form-control"
                                name="fecha_inicio"
                                value={filters.fecha_inicio}
                                onChange={handleFilterChange}
                            />
                        </div>

                        <div className="col-md-2">
                            <label className="form-label">Fecha Fin</label>
                            <input
                                type="date"
                                className="form-control"
                                name="fecha_fin"
                                value={filters.fecha_fin}
                                onChange={handleFilterChange}
                            />
                        </div>

                        <div className="col-md-2 d-flex align-items-end">
                            <button 
                                className="btn btn-primary w-100"
                                onClick={handleSearch}
                            >
                                Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Tabla */}
            <div className="card">
                <div className="card-header">
                    <h5 className="mb-0">Historial de Movimientos</h5>
                </div>
                <div className="card-body">
                    <div className="table-responsive">
                        <table className="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Producto</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Cantidad</th>
                                    <th>Usuario</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                {movimientos.length === 0 ? (
                                    <tr>
                                        <td colSpan="8" className="text-center">
                                            No hay movimientos registrados
                                        </td>
                                    </tr>
                                ) : (
                                    movimientos.map(mov => (
                                        <tr key={mov.id}>
                                            <td>{new Date(mov.fecha_movimiento).toLocaleString()}</td>
                                            <td>{getTipoBadge(mov.tipo)}</td>
                                            <td>
                                                <small>{mov.inventario?.codigo_barras}</small><br />
                                                {mov.inventario?.descripcion}
                                            </td>
                                            <td>
                                                {mov.warehouseOrigen?.codigo || '-'}
                                            </td>
                                            <td>
                                                {mov.warehouseDestino?.codigo || '-'}
                                            </td>
                                            <td>{mov.cantidad}</td>
                                            <td>{mov.usuario?.name || '-'}</td>
                                            <td>{mov.documento_referencia || '-'}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default HistorialMovimientos;

