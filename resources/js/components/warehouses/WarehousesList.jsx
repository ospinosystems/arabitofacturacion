import React, { useState, useEffect } from 'react';
import axios from 'axios';

const WarehousesList = ({ onNew, onEdit }) => {
    const [warehouses, setWarehouses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        buscar: '',
        estado: '',
        tipo: '',
        zona: ''
    });

    useEffect(() => {
        loadWarehouses();
    }, []);

    const loadWarehouses = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/warehouses', { params: filters });
            setWarehouses(response.data.warehouses || []);
        } catch (error) {
            console.error('Error cargando ubicaciones:', error);
            alert('Error al cargar ubicaciones');
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilters(prev => ({ ...prev, [name]: value }));
    };

    const handleSearch = () => {
        loadWarehouses();
    };

    const handleDelete = async (id, codigo) => {
        if (!confirm(`¿Está seguro de eliminar la ubicación ${codigo}?`)) {
            return;
        }

        try {
            const response = await axios.delete(`/warehouses/${id}`);
            if (response.data.estado) {
                alert(response.data.msj);
                loadWarehouses();
            } else {
                alert(response.data.msj);
            }
        } catch (error) {
            console.error('Error eliminando ubicación:', error);
            alert('Error al eliminar ubicación');
        }
    };

    const getEstadoBadge = (estado) => {
        const badges = {
            activa: 'badge bg-success',
            inactiva: 'badge bg-secondary',
            mantenimiento: 'badge bg-warning',
            bloqueada: 'badge bg-danger'
        };
        return <span className={badges[estado] || 'badge bg-secondary'}>{estado}</span>;
    };

    const getTipoBadge = (tipo) => {
        const badges = {
            almacenamiento: 'badge bg-primary',
            picking: 'badge bg-info',
            recepcion: 'badge bg-success',
            despacho: 'badge bg-warning'
        };
        return <span className={badges[tipo] || 'badge bg-secondary'}>{tipo}</span>;
    };

    if (loading) {
        return <div className="text-center p-5">Cargando...</div>;
    }

    return (
        <div className="warehouses-list">
            {/* Filtros */}
            <div className="card mb-3">
                <div className="card-body">
                    <div className="row g-3">
                        <div className="col-md-4">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Buscar por código, nombre o pasillo..."
                                name="buscar"
                                value={filters.buscar}
                                onChange={handleFilterChange}
                                onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                            />
                        </div>
                        <div className="col-md-2">
                            <select
                                className="form-select"
                                name="estado"
                                value={filters.estado}
                                onChange={handleFilterChange}
                            >
                                <option value="">Todos los estados</option>
                                <option value="activa">Activa</option>
                                <option value="inactiva">Inactiva</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="bloqueada">Bloqueada</option>
                            </select>
                        </div>
                        <div className="col-md-2">
                            <select
                                className="form-select"
                                name="tipo"
                                value={filters.tipo}
                                onChange={handleFilterChange}
                            >
                                <option value="">Todos los tipos</option>
                                <option value="almacenamiento">Almacenamiento</option>
                                <option value="picking">Picking</option>
                                <option value="recepcion">Recepción</option>
                                <option value="despacho">Despacho</option>
                            </select>
                        </div>
                        <div className="col-md-2">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Zona..."
                                name="zona"
                                value={filters.zona}
                                onChange={handleFilterChange}
                            />
                        </div>
                        <div className="col-md-2">
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

            {/* Botón Nueva Ubicación */}
            <div className="mb-3">
                <button className="btn btn-success" onClick={onNew}>
                    <i className="fas fa-plus"></i> Nueva Ubicación
                </button>
            </div>

            {/* Tabla */}
            <div className="card">
                <div className="card-body">
                    <div className="table-responsive">
                        <table className="table table-hover">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Zona</th>
                                    <th>Capacidad</th>
                                    <th>Ocupación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {warehouses.length === 0 ? (
                                    <tr>
                                        <td colSpan="8" className="text-center">
                                            No hay ubicaciones registradas
                                        </td>
                                    </tr>
                                ) : (
                                    warehouses.map(warehouse => (
                                        <tr key={warehouse.id}>
                                            <td><strong>{warehouse.codigo}</strong></td>
                                            <td>{warehouse.nombre || '-'}</td>
                                            <td>{getTipoBadge(warehouse.tipo)}</td>
                                            <td>{getEstadoBadge(warehouse.estado)}</td>
                                            <td>{warehouse.zona || '-'}</td>
                                            <td>{warehouse.capacidad_unidades || 'Sin límite'}</td>
                                            <td>
                                                {warehouse.ocupacion ? (
                                                    <div className="progress" style={{ height: '20px' }}>
                                                        <div 
                                                            className={`progress-bar ${warehouse.ocupacion > 80 ? 'bg-danger' : warehouse.ocupacion > 60 ? 'bg-warning' : 'bg-success'}`}
                                                            role="progressbar" 
                                                            style={{ width: `${warehouse.ocupacion}%` }}
                                                        >
                                                            {warehouse.ocupacion}%
                                                        </div>
                                                    </div>
                                                ) : '-'}
                                            </td>
                                            <td>
                                                <button 
                                                    className="btn btn-sm btn-info me-1"
                                                    onClick={() => window.location.href = `/warehouses/${warehouse.id}`}
                                                    title="Ver detalle"
                                                >
                                                    <i className="fas fa-eye"></i>
                                                </button>
                                                <button 
                                                    className="btn btn-sm btn-warning me-1"
                                                    onClick={() => onEdit(warehouse)}
                                                    title="Editar"
                                                >
                                                    <i className="fas fa-edit"></i>
                                                </button>
                                                <button 
                                                    className="btn btn-sm btn-danger"
                                                    onClick={() => handleDelete(warehouse.id, warehouse.codigo)}
                                                    title="Eliminar"
                                                >
                                                    <i className="fas fa-trash"></i>
                                                </button>
                                            </td>
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

export default WarehousesList;

