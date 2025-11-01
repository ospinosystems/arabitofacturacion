import React, { useState, useEffect } from 'react';
import axios from 'axios';

const WarehouseForm = ({ warehouse, onClose }) => {
    const [formData, setFormData] = useState({
        pasillo: '',
        cara: 1,
        rack: 1,
        nivel: 1,
        nombre: '',
        descripcion: '',
        tipo: 'almacenamiento',
        estado: 'activa',
        zona: '',
        capacidad_peso: '',
        capacidad_volumen: '',
        capacidad_unidades: ''
    });
    const [saving, setSaving] = useState(false);
    const [codigo, setCodigo] = useState('');

    useEffect(() => {
        if (warehouse) {
            setFormData({
                pasillo: warehouse.pasillo || '',
                cara: warehouse.cara || 1,
                rack: warehouse.rack || 1,
                nivel: warehouse.nivel || 1,
                nombre: warehouse.nombre || '',
                descripcion: warehouse.descripcion || '',
                tipo: warehouse.tipo || 'almacenamiento',
                estado: warehouse.estado || 'activa',
                zona: warehouse.zona || '',
                capacidad_peso: warehouse.capacidad_peso || '',
                capacidad_volumen: warehouse.capacidad_volumen || '',
                capacidad_unidades: warehouse.capacidad_unidades || ''
            });
        }
    }, [warehouse]);

    useEffect(() => {
        // Generar código automáticamente
        if (formData.pasillo && formData.cara && formData.rack && formData.nivel) {
            const nuevoCodigo = `${formData.pasillo.toUpperCase()}${formData.cara}-${formData.rack}-${formData.nivel}`;
            setCodigo(nuevoCodigo);
        }
    }, [formData.pasillo, formData.cara, formData.rack, formData.nivel]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.pasillo || !formData.cara || !formData.rack || !formData.nivel) {
            alert('Por favor complete las coordenadas de la ubicación');
            return;
        }

        try {
            setSaving(true);

            const url = warehouse ? `/warehouses/${warehouse.id}` : '/warehouses';
            const method = warehouse ? 'put' : 'post';

            const response = await axios[method](url, formData);

            if (response.data.estado) {
                alert(response.data.msj);
                onClose();
                window.location.reload();
            } else {
                alert(response.data.msj);
            }
        } catch (error) {
            console.error('Error guardando ubicación:', error);
            alert('Error al guardar la ubicación');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="warehouse-form">
            <div className="card">
                <div className="card-header d-flex justify-content-between align-items-center">
                    <h5 className="mb-0">
                        {warehouse ? 'Editar Ubicación' : 'Nueva Ubicación'}
                    </h5>
                    <button className="btn btn-sm btn-secondary" onClick={onClose}>
                        Volver
                    </button>
                </div>
                <div className="card-body">
                    <form onSubmit={handleSubmit}>
                        {/* Código generado */}
                        <div className="alert alert-info">
                            <strong>Código de ubicación:</strong> {codigo || 'Complete las coordenadas'}
                        </div>

                        {/* Coordenadas */}
                        <div className="row mb-3">
                            <div className="col-md-12">
                                <h6>Coordenadas de Ubicación</h6>
                                <small className="text-muted">
                                    Formato: [LETRA:PASILLO][NUMERO:CARA]-[NUMERO:RACK]-[NUMERO:NIVEL]
                                </small>
                            </div>
                        </div>

                        <div className="row mb-3">
                            <div className="col-md-3">
                                <label className="form-label">Pasillo *</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="pasillo"
                                    value={formData.pasillo}
                                    onChange={handleChange}
                                    placeholder="A, B, C..."
                                    maxLength="10"
                                    required
                                />
                                <small className="text-muted">Letra o código del pasillo</small>
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">Cara *</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="cara"
                                    value={formData.cara}
                                    onChange={handleChange}
                                    min="1"
                                    required
                                />
                                <small className="text-muted">Lado del pasillo (1, 2...)</small>
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">Rack *</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="rack"
                                    value={formData.rack}
                                    onChange={handleChange}
                                    min="1"
                                    required
                                />
                                <small className="text-muted">Número de rack</small>
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">Nivel *</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="nivel"
                                    value={formData.nivel}
                                    onChange={handleChange}
                                    min="1"
                                    required
                                />
                                <small className="text-muted">Nivel del rack</small>
                            </div>
                        </div>

                        <hr />

                        {/* Información adicional */}
                        <div className="row mb-3">
                            <div className="col-md-6">
                                <label className="form-label">Nombre</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="nombre"
                                    value={formData.nombre}
                                    onChange={handleChange}
                                    placeholder="Nombre descriptivo (opcional)"
                                />
                            </div>
                            <div className="col-md-6">
                                <label className="form-label">Zona</label>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="zona"
                                    value={formData.zona}
                                    onChange={handleChange}
                                    placeholder="Zona del almacén (opcional)"
                                />
                            </div>
                        </div>

                        <div className="row mb-3">
                            <div className="col-md-6">
                                <label className="form-label">Tipo *</label>
                                <select
                                    className="form-select"
                                    name="tipo"
                                    value={formData.tipo}
                                    onChange={handleChange}
                                    required
                                >
                                    <option value="almacenamiento">Almacenamiento</option>
                                    <option value="picking">Picking</option>
                                    <option value="recepcion">Recepción</option>
                                    <option value="despacho">Despacho</option>
                                </select>
                            </div>
                            <div className="col-md-6">
                                <label className="form-label">Estado *</label>
                                <select
                                    className="form-select"
                                    name="estado"
                                    value={formData.estado}
                                    onChange={handleChange}
                                    required
                                >
                                    <option value="activa">Activa</option>
                                    <option value="inactiva">Inactiva</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                    <option value="bloqueada">Bloqueada</option>
                                </select>
                            </div>
                        </div>

                        <div className="mb-3">
                            <label className="form-label">Descripción</label>
                            <textarea
                                className="form-control"
                                name="descripcion"
                                value={formData.descripcion}
                                onChange={handleChange}
                                rows="2"
                                placeholder="Descripción adicional (opcional)"
                            />
                        </div>

                        <hr />

                        {/* Capacidades */}
                        <div className="row mb-3">
                            <div className="col-md-12">
                                <h6>Capacidades (Opcional)</h6>
                            </div>
                        </div>

                        <div className="row mb-3">
                            <div className="col-md-4">
                                <label className="form-label">Capacidad Peso (kg)</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="capacidad_peso"
                                    value={formData.capacidad_peso}
                                    onChange={handleChange}
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">Capacidad Volumen (m³)</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="capacidad_volumen"
                                    value={formData.capacidad_volumen}
                                    onChange={handleChange}
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">Capacidad Unidades</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    name="capacidad_unidades"
                                    value={formData.capacidad_unidades}
                                    onChange={handleChange}
                                    min="0"
                                    placeholder="Sin límite"
                                />
                            </div>
                        </div>

                        {/* Botones */}
                        <div className="d-flex justify-content-end gap-2">
                            <button 
                                type="button" 
                                className="btn btn-secondary"
                                onClick={onClose}
                                disabled={saving}
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                className="btn btn-primary"
                                disabled={saving}
                            >
                                {saving ? 'Guardando...' : (warehouse ? 'Actualizar' : 'Crear')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default WarehouseForm;

