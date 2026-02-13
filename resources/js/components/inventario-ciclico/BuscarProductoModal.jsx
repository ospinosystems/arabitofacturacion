import React, { useState, useEffect } from 'react';
import axios from 'axios';

const STORAGE_KEY_PENDIENTES = (planillaId) => `inventario_ciclico_pendientes_${planillaId}`;

const BuscarProductoModal = ({ onClose, onProductoSelected, planillaId, sucursalConfig }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [productos, setProductos] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedProducto, setSelectedProducto] = useState(null);
    const [formData, setFormData] = useState({
        cantidad_fisica: '',
        observaciones: ''
    });
    const [submitting, setSubmitting] = useState(false);
    const [mensajeAdicion, setMensajeAdicion] = useState(null);

    const getPendientes = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_PENDIENTES(planillaId));
            return raw ? JSON.parse(raw) : [];
        } catch {
            return [];
        }
    };

    const setPendientes = (arr) => {
        localStorage.setItem(STORAGE_KEY_PENDIENTES(planillaId), JSON.stringify(arr));
    };

    useEffect(() => {
        if (searchTerm.length >= 2) {
            searchProductos();
        } else {
            setProductos([]);
        }
    }, [searchTerm]);

    const searchProductos = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/api/inventario-ciclico/productos/buscar', {
                params: {
                    termino: searchTerm,
                    limit: 20
                }
            });
            
            if (response.data.success) {
                setProductos(response.data.data || []);
            } else {
                setProductos([]);
            }
        } catch (error) {
            console.error('Error al buscar productos:', error);
            setProductos([]);
        } finally {
            setLoading(false);
        }
    };

    const handleProductoSelect = (producto) => {
        setSelectedProducto(producto);
        setFormData({
            cantidad_fisica: producto.cantidad?.toString() || '0',
            observaciones: ''
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setMensajeAdicion(null);
        const cant = parseInt(formData.cantidad_fisica, 10) || 0;
        const pendientes = getPendientes();
        const existente = pendientes.find((p) => p.id_producto === selectedProducto.id);

        if (existente) {
            const cantidadAnterior = existente.cantidad_fisica;
            const nuevaCantidad = cantidadAnterior + cant;
            existente.cantidad_fisica = nuevaCantidad;
            existente.fecha_agregado = new Date().toISOString();
            if (formData.observaciones?.trim()) {
                existente.observaciones = [existente.observaciones, formData.observaciones].filter(Boolean).join(' | ');
            }
            setPendientes([...pendientes]);
            setMensajeAdicion(
                `Producto ya estaba en la planilla. Se sumaron ${cant} unidades. Total en planilla: ${nuevaCantidad} unidades.`
            );
            onProductoSelected(selectedProducto);
            onClose();
            return;
        }

        const nuevo = {
            id_producto: selectedProducto.id,
            cantidad_fisica: cant,
            cantidad_sistema: selectedProducto.cantidad ?? 0,
            fecha_agregado: new Date().toISOString(),
            observaciones: formData.observaciones || '',
            producto: {
                id: selectedProducto.id,
                descripcion: selectedProducto.descripcion,
                codigo_barras: selectedProducto.codigo_barras,
                codigo_proveedor: selectedProducto.codigo_proveedor,
                cantidad: selectedProducto.cantidad,
                precio: selectedProducto.precio,
                precio_base: selectedProducto.precio_base,
            },
        };
        setPendientes([...pendientes, nuevo]);
        onProductoSelected(selectedProducto);
        onClose();
    };

    const formatNumber = (value, decimals = 2) => {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value || 0);
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div className="mt-3">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h3 className="text-lg font-medium text-gray-900">
                            <i className="fa fa-search mr-2 text-blue-600"></i>
                            Buscar y Agregar Producto
                        </h3>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <i className="fa fa-times"></i>
                        </button>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Panel de búsqueda */}
                        <div>
                            <h4 className="text-md font-medium text-gray-900 mb-4">
                                Buscar Producto
                            </h4>
                            
                            {/* Campo de búsqueda */}
                            <div className="mb-4">
                                <input
                                    type="text"
                                    placeholder="Buscar por nombre, código de barras o código proveedor..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Lista de productos */}
                            <div className="border border-gray-200 rounded-md max-h-96 overflow-y-auto">
                                {loading ? (
                                    <div className="flex justify-center items-center py-8">
                                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                    </div>
                                ) : productos.length === 0 ? (
                                    <div className="text-center py-8 text-gray-500">
                                        {searchTerm.length >= 2 ? 'No se encontraron productos' : 'Ingresa al menos 2 caracteres para buscar'}
                                    </div>
                                ) : (
                                    <div className="divide-y divide-gray-200">
                                        {productos.map((producto) => (
                                            <div
                                                key={producto.id}
                                                className={`p-3 cursor-pointer hover:bg-gray-50 ${
                                                    selectedProducto?.id === producto.id ? 'bg-blue-50 border-l-4 border-blue-500' : ''
                                                }`}
                                                onClick={() => handleProductoSelect(producto)}
                                            >
                                                <div className="flex justify-between items-start">
                                                    <div className="flex-1">
                                                        <h5 className="text-sm font-medium text-gray-900">
                                                            {producto.descripcion}
                                                        </h5>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            Código: {producto.codigo_barras}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            Proveedor: {producto.codigo_proveedor}
                                                        </p>
                                                    </div>
                                                    <div className="text-right ml-4">
                                                        <p className="text-sm font-medium text-gray-900">
                                                            Cant: {producto.cantidad}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            ${formatNumber(producto.precio)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Panel de detalles del producto */}
                        <div>
                            <h4 className="text-md font-medium text-gray-900 mb-4">
                                Detalles del Producto
                            </h4>
                            
                            {selectedProducto ? (
                                <div className="space-y-4">
                                    {/* Información del producto */}
                                    <div className="bg-gray-50 p-4 rounded-md">
                                        <h5 className="font-medium text-gray-900 mb-2">
                                            {selectedProducto.descripcion}
                                        </h5>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span className="text-gray-600">Código de Barras:</span>
                                                <p className="font-medium">{selectedProducto.codigo_barras}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-600">Código Proveedor:</span>
                                                <p className="font-medium">{selectedProducto.codigo_proveedor}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-600">Cantidad en Sistema:</span>
                                                <p className="font-medium">{selectedProducto.cantidad}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-600">Precio:</span>
                                                <p className="font-medium">${formatNumber(selectedProducto.precio)}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Formulario para agregar */}
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Cantidad Física Encontrada *
                                            </label>
                                            <input
                                                type="number"
                                                value={formData.cantidad_fisica}
                                                onChange={(e) => setFormData(prev => ({ ...prev, cantidad_fisica: e.target.value }))}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                min="0"
                                                required
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Observaciones (Opcional)
                                            </label>
                                            <textarea
                                                value={formData.observaciones}
                                                onChange={(e) => setFormData(prev => ({ ...prev, observaciones: e.target.value }))}
                                                rows={3}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="Observaciones sobre este producto..."
                                            />
                                        </div>

                                        {/* Diferencia calculada */}
                                        <div className="bg-blue-50 p-3 rounded-md">
                                            <p className="text-sm text-gray-600">
                                                <strong>Diferencia:</strong> {parseInt(formData.cantidad_fisica || 0) - (selectedProducto.cantidad || 0)} unidades
                                            </p>
                                            <p className="text-sm text-gray-600">
                                                <strong>Valor Diferencia:</strong> ${formatNumber((parseInt(formData.cantidad_fisica || 0) - (selectedProducto.cantidad || 0)) * (selectedProducto.precio || 0))}
                                            </p>
                                        </div>

                                        {mensajeAdicion && (
                                            <div className="bg-amber-50 border border-amber-200 p-3 rounded-md">
                                                <p className="text-sm text-amber-800">
                                                    <i className="fa fa-info-circle mr-2"></i>
                                                    {mensajeAdicion}
                                                </p>
                                            </div>
                                        )}

                                        {/* Botones */}
                                        <div className="flex justify-end space-x-3 pt-4">
                                            <button
                                                type="button"
                                                onClick={onClose}
                                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                type="submit"
                                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50"
                                                disabled={!formData.cantidad_fisica}
                                            >
                                                <i className="fa fa-plus mr-2"></i>
                                                Agregar a Planilla (local)
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            ) : (
                                <div className="text-center py-12 text-gray-500">
                                    <i className="fa fa-box text-4xl mb-4"></i>
                                    <p>Selecciona un producto de la lista para ver sus detalles</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default BuscarProductoModal; 