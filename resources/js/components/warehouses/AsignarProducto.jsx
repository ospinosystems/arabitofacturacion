import React, { useState, useEffect } from 'react';
import axios from 'axios';

const AsignarProducto = () => {
    const [warehouses, setWarehouses] = useState([]);
    const [productos, setProductos] = useState([]);
    const [formData, setFormData] = useState({
        warehouse_id: '',
        inventario_id: '',
        cantidad: '',
        lote: '',
        fecha_vencimiento: '',
        fecha_entrada: new Date().toISOString().split('T')[0],
        documento_referencia: '',
        observaciones: ''
    });
    const [loading, setLoading] = useState(false);
    const [buscarProducto, setBuscarProducto] = useState('');

    useEffect(() => {
        loadWarehouses();
    }, []);

    const loadWarehouses = async () => {
        try {
            const response = await axios.get('/warehouses/disponibles');
            setWarehouses(response.data.warehouses || []);
        } catch (error) {
            console.error('Error cargando ubicaciones:', error);
        }
    };

    const buscarProductos = async () => {
        if (!buscarProducto.trim()) return;

        try {
            const response = await axios.get('/inventario/buscar', {
                params: { buscar: buscarProducto }
            });
            setProductos(response.data.productos || []);
        } catch (error) {
            console.error('Error buscando productos:', error);
            alert('Error al buscar productos');
        }
    };

    const handleSelectProducto = (producto) => {
        setFormData(prev => ({
            ...prev,
            inventario_id: producto.id
        }));
        setBuscarProducto(producto.descripcion);
        setProductos([]);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.warehouse_id || !formData.inventario_id || !formData.cantidad) {
            alert('Por favor complete los campos obligatorios');
            return;
        }

        try {
            setLoading(true);

            const response = await axios.post('/warehouse-inventory/asignar', formData);

            if (response.data.estado) {
                alert(response.data.msj);
                // Limpiar formulario
                setFormData({
                    warehouse_id: '',
                    inventario_id: '',
                    cantidad: '',
                    lote: '',
                    fecha_vencimiento: '',
                    fecha_entrada: new Date().toISOString().split('T')[0],
                    documento_referencia: '',
                    observaciones: ''
                });
                setBuscarProducto('');
            } else {
                alert(response.data.msj);
            }
        } catch (error) {
            console.error('Error asignando producto:', error);
            alert('Error al asignar producto');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="asignar-producto">
            <div className="card">
                <div className="card-header">
                    <h5 className="mb-0">Asignar Producto a Ubicación</h5>
                </div>
                <div className="card-body">
                    <form onSubmit={handleSubmit}>
                        <div className="row mb-3">
                            <div className="col-md-6">
                                <label className="form-label">Ubicación *</label>
                                <select
                                    className="form-select"
                                    name="warehouse_id"
                                    value={formData.warehouse_id}
                                    onChange={handleChange}
                                    required
                                >
                                    <option value="">Seleccione una ubicación</option>
                                    {warehouses.map(w => (
                                        <option key={w.id} value={w.id}>
                                            {w.codigo} - {w.nombre || w.tipo}
                                            {w.capacidad_unidades && ` (Cap: ${w.capacidad_unidades})`}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="col-md-6">
                                <label className="form-label">Buscar Producto *</label>
                                <div className="input-group">
                                    <input
                                        type="text"
                                        className="form-control"
                                        value={buscarProducto}
                                        onChange={(e) => setBuscarProducto(e.target.value)}
                                        placeholder="Código o descripción..."
                                        onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), buscarProductos())}
                                    />
                                    <button 
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={buscarProductos}
                                    >
                                        Buscar
                                    </button>
                                </div>
                                {productos.length > 0 && (
                                    <div className="list-group mt-2" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                                        {productos.map(p => (
                                            <button
                                                key={p.id}
                                                type="button"
                                                className="list-group-item list-group-item-action"
                                                onClick={() => handleSelectProducto(p)}
                                            >
                                                <strong>{p.codigo_barras}</strong> - {p.descripcion}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="row mb-3">
                            <div className="col-md-3">
                                <label className="form-label">Cantidad *</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="cantidad"
                                    value={formData.cantidad}
                                    onChange={handleChange}
                                    step="0.01"
                                    min="0.01"
                                    required
                                />
                            </div>

                            <div className="col-md-3">
                                <label className="form-label">Lote</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="lote"
                                    value={formData.lote}
                                    onChange={handleChange}
                                    placeholder="Número de lote"
                                />
                            </div>

                            <div className="col-md-3">
                                <label className="form-label">Fecha Vencimiento</label>
                                <input
                                    type="date"
                                    className="form-control"
                                    name="fecha_vencimiento"
                                    value={formData.fecha_vencimiento}
                                    onChange={handleChange}
                                />
                            </div>

                            <div className="col-md-3">
                                <label className="form-label">Fecha Entrada *</label>
                                <input
                                    type="date"
                                    className="form-control"
                                    name="fecha_entrada"
                                    value={formData.fecha_entrada}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                        </div>

                        <div className="row mb-3">
                            <div className="col-md-6">
                                <label className="form-label">Documento Referencia</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="documento_referencia"
                                    value={formData.documento_referencia}
                                    onChange={handleChange}
                                    placeholder="Orden de compra, factura..."
                                />
                            </div>

                            <div className="col-md-6">
                                <label className="form-label">Observaciones</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="observaciones"
                                    value={formData.observaciones}
                                    onChange={handleChange}
                                    placeholder="Observaciones adicionales..."
                                />
                            </div>
                        </div>

                        <div className="d-flex justify-content-end">
                            <button 
                                type="submit" 
                                className="btn btn-primary"
                                disabled={loading}
                            >
                                {loading ? 'Guardando...' : 'Asignar Producto'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default AsignarProducto;

