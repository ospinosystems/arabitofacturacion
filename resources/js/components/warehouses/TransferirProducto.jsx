import React, { useState, useEffect } from 'react';
import axios from 'axios';

const TransferirProducto = () => {
    const [warehouses, setWarehouses] = useState([]);
    const [inventarioOrigen, setInventarioOrigen] = useState([]);
    const [formData, setFormData] = useState({
        warehouse_origen_id: '',
        warehouse_destino_id: '',
        inventario_id: '',
        cantidad: '',
        documento_referencia: '',
        observaciones: ''
    });
    const [loading, setLoading] = useState(false);

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

    const loadInventarioOrigen = async (warehouseId) => {
        if (!warehouseId) {
            setInventarioOrigen([]);
            return;
        }

        try {
            const response = await axios.get(`/warehouse-inventory/ubicacion/${warehouseId}`);
            setInventarioOrigen(response.data.productos || []);
        } catch (error) {
            console.error('Error cargando inventario:', error);
        }
    };

    const handleOrigenChange = (e) => {
        const warehouseId = e.target.value;
        setFormData(prev => ({
            ...prev,
            warehouse_origen_id: warehouseId,
            inventario_id: ''
        }));
        loadInventarioOrigen(warehouseId);
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

        if (!formData.warehouse_origen_id || !formData.warehouse_destino_id || !formData.inventario_id || !formData.cantidad) {
            alert('Por favor complete todos los campos obligatorios');
            return;
        }

        if (formData.warehouse_origen_id === formData.warehouse_destino_id) {
            alert('La ubicación origen y destino no pueden ser iguales');
            return;
        }

        try {
            setLoading(true);

            const response = await axios.post('/warehouse-inventory/transferir', formData);

            if (response.data.estado) {
                alert(response.data.msj);
                // Limpiar formulario
                setFormData({
                    warehouse_origen_id: '',
                    warehouse_destino_id: '',
                    inventario_id: '',
                    cantidad: '',
                    documento_referencia: '',
                    observaciones: ''
                });
                setInventarioOrigen([]);
            } else {
                alert(response.data.msj);
            }
        } catch (error) {
            console.error('Error transfiriendo producto:', error);
            alert('Error al transferir producto');
        } finally {
            setLoading(false);
        }
    };

    const productoSeleccionado = inventarioOrigen.find(p => p.inventario_id == formData.inventario_id);

    return (
        <div className="transferir-producto">
            <div className="card">
                <div className="card-header">
                    <h5 className="mb-0">Transferir Producto entre Ubicaciones</h5>
                </div>
                <div className="card-body">
                    <form onSubmit={handleSubmit}>
                        <div className="row mb-3">
                            <div className="col-md-6">
                                <label className="form-label">Ubicación Origen *</label>
                                <select
                                    className="form-select"
                                    name="warehouse_origen_id"
                                    value={formData.warehouse_origen_id}
                                    onChange={handleOrigenChange}
                                    required
                                >
                                    <option value="">Seleccione ubicación origen</option>
                                    {warehouses.map(w => (
                                        <option key={w.id} value={w.id}>
                                            {w.codigo} - {w.nombre || w.tipo}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="col-md-6">
                                <label className="form-label">Ubicación Destino *</label>
                                <select
                                    className="form-select"
                                    name="warehouse_destino_id"
                                    value={formData.warehouse_destino_id}
                                    onChange={handleChange}
                                    required
                                >
                                    <option value="">Seleccione ubicación destino</option>
                                    {warehouses.filter(w => w.id != formData.warehouse_origen_id).map(w => (
                                        <option key={w.id} value={w.id}>
                                            {w.codigo} - {w.nombre || w.tipo}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {formData.warehouse_origen_id && (
                            <div className="row mb-3">
                                <div className="col-md-12">
                                    <label className="form-label">Producto *</label>
                                    <select
                                        className="form-select"
                                        name="inventario_id"
                                        value={formData.inventario_id}
                                        onChange={handleChange}
                                        required
                                    >
                                        <option value="">Seleccione un producto</option>
                                        {inventarioOrigen.map(p => (
                                            <option key={p.id} value={p.inventario_id}>
                                                {p.inventario?.descripcion} - Stock: {p.cantidad} {p.lote && `(Lote: ${p.lote})`}
                                            </option>
                                        ))}
                                    </select>
                                    {inventarioOrigen.length === 0 && formData.warehouse_origen_id && (
                                        <small className="text-muted">No hay productos en esta ubicación</small>
                                    )}
                                </div>
                            </div>
                        )}

                        {productoSeleccionado && (
                            <div className="alert alert-info">
                                <strong>Stock disponible:</strong> {productoSeleccionado.cantidad} unidades
                                {productoSeleccionado.lote && <> | <strong>Lote:</strong> {productoSeleccionado.lote}</>}
                                {productoSeleccionado.fecha_vencimiento && <> | <strong>Vence:</strong> {productoSeleccionado.fecha_vencimiento}</>}
                            </div>
                        )}

                        <div className="row mb-3">
                            <div className="col-md-4">
                                <label className="form-label">Cantidad a Transferir *</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="cantidad"
                                    value={formData.cantidad}
                                    onChange={handleChange}
                                    step="0.01"
                                    min="0.01"
                                    max={productoSeleccionado?.cantidad || 999999}
                                    required
                                />
                            </div>

                            <div className="col-md-4">
                                <label className="form-label">Documento Referencia</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="documento_referencia"
                                    value={formData.documento_referencia}
                                    onChange={handleChange}
                                    placeholder="Orden, solicitud..."
                                />
                            </div>

                            <div className="col-md-4">
                                <label className="form-label">Observaciones</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="observaciones"
                                    value={formData.observaciones}
                                    onChange={handleChange}
                                    placeholder="Observaciones..."
                                />
                            </div>
                        </div>

                        <div className="d-flex justify-content-end">
                            <button 
                                type="submit" 
                                className="btn btn-primary"
                                disabled={loading}
                            >
                                {loading ? 'Transfiriendo...' : 'Transferir Producto'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default TransferirProducto;

