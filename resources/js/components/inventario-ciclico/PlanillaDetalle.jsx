import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import BuscarProductoModal from './BuscarProductoModal';

const STORAGE_KEY_PENDIENTES = (planillaId) => `inventario_ciclico_pendientes_${planillaId}`;

const PlanillaDetalle = ({ planilla, onBack, sucursalConfig }) => {
    const [planillaData, setPlanillaData] = useState(planilla);
    const [loading, setLoading] = useState(false);
    const [showBuscarModal, setShowBuscarModal] = useState(false);
    const [editingDetalle, setEditingDetalle] = useState(null);
    const [editForm, setEditForm] = useState({ cantidad_fisica: '', observaciones: '' });
    const [pendientes, setPendientes] = useState([]);
    const [enviando, setEnviando] = useState(false);
    const [editingPendienteIdx, setEditingPendienteIdx] = useState(null);
    const [editCantidadValue, setEditCantidadValue] = useState('');
    const [showReporteFullScreen, setShowReporteFullScreen] = useState(false);

    const loadPendientes = useCallback(() => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_PENDIENTES(planilla.id));
            setPendientes(raw ? JSON.parse(raw) : []);
        } catch {
            setPendientes([]);
        }
    }, [planilla.id]);

    useEffect(() => {
        loadPlanillaDetalle();
    }, [planilla.id]);

    useEffect(() => {
        loadPendientes();
    }, [loadPendientes]);

    const loadPlanillaDetalle = async () => {
        setLoading(true);
        try {
            const response = await axios.get(`/api/inventario-ciclico/planillas/${planilla.id}`);
            if (response.data.success) {
                setPlanillaData(response.data.data);
            }
        } catch (error) {
            console.error('Error al cargar detalle de planilla:', error);
            alert('Error al cargar los detalles de la planilla');
        } finally {
            setLoading(false);
        }
    };

    const handleProductoAdded = () => {
        loadPendientes();
    };

    const handleEnviarACentral = async () => {
        if (pendientes.length === 0) return;
        if (!confirm(`¿Enviar ${pendientes.length} producto(s) a Central? Esta acción enviará todas las tareas pendientes.`)) {
            return;
        }
        setEnviando(true);
        try {
            const response = await axios.post(`/api/inventario-ciclico/planillas/${planilla.id}/enviar-tareas`, {
                tareas: pendientes.map((p) => ({
                    id_producto: p.id_producto,
                    cantidad_fisica: p.cantidad_fisica,
                    observaciones: p.observaciones || '',
                })),
            });
            if (response.data.success) {
                localStorage.removeItem(STORAGE_KEY_PENDIENTES(planilla.id));
                loadPendientes();
                loadPlanillaDetalle();
                alert(response.data.message || 'Tareas enviadas a Central correctamente.');
            } else {
                alert(response.data.message || 'Error al enviar.');
            }
        } catch (error) {
            console.error('Error al enviar tareas:', error);
            alert(error.response?.data?.message || 'Error al enviar las tareas a Central.');
        } finally {
            setEnviando(false);
        }
    };

    const quitarPendiente = (index) => {
        const next = pendientes.filter((_, i) => i !== index);
        localStorage.setItem(STORAGE_KEY_PENDIENTES(planilla.id), JSON.stringify(next));
        setPendientes(next);
        if (editingPendienteIdx === index) {
            setEditingPendienteIdx(null);
        } else if (editingPendienteIdx != null && editingPendienteIdx > index) {
            setEditingPendienteIdx(editingPendienteIdx - 1);
        }
    };

    const iniciarEditarCantidad = (idx) => {
        setEditingPendienteIdx(idx);
        setEditCantidadValue(String(pendientes[idx].cantidad_fisica ?? ''));
    };

    const guardarCantidadPendiente = (idx) => {
        const num = parseInt(editCantidadValue, 10);
        if (isNaN(num) || num < 0) {
            setEditingPendienteIdx(null);
            setEditCantidadValue('');
            return;
        }
        const next = [...pendientes];
        next[idx] = { ...next[idx], cantidad_fisica: num };
        localStorage.setItem(STORAGE_KEY_PENDIENTES(planilla.id), JSON.stringify(next));
        setPendientes(next);
        setEditingPendienteIdx(null);
        setEditCantidadValue('');
    };

    const handleEditDetalle = (detalle) => {
        setEditingDetalle(detalle);
        setEditForm({
            cantidad_fisica: detalle.cantidad_fisica.toString(),
            observaciones: detalle.observaciones || ''
        });
    };

    const handleSaveEdit = async () => {
        try {
            const response = await axios.put(
                `/api/inventario-ciclico/planillas/${planilla.id}/productos/${editingDetalle.id}`,
                {
                    cantidad_fisica: parseInt(editForm.cantidad_fisica),
                    observaciones: editForm.observaciones
                }
            );

            if (response.data.success) {
                setEditingDetalle(null);
                setEditForm({ cantidad_fisica: '', observaciones: '' });
                loadPlanillaDetalle();
            }
        } catch (error) {
            console.error('Error al actualizar cantidad:', error);
            alert('Error al actualizar la cantidad');
        }
    };

    const handleCancelEdit = () => {
        setEditingDetalle(null);
        setEditForm({ cantidad_fisica: '', observaciones: '' });
    };

    const handleCerrarPlanilla = async () => {
        if (!confirm('¿Está seguro de que desea cerrar esta planilla? Esta acción no se puede deshacer.')) {
            return;
        }

        try {
            const response = await axios.post(`/api/inventario-ciclico/planillas/${planilla.id}/cerrar`);
            if (response.data.success) {
                alert('Planilla cerrada exitosamente');
                loadPlanillaDetalle();
            }
        } catch (error) {
            console.error('Error al cerrar planilla:', error);
            alert(error.response?.data?.message || 'Error al cerrar la planilla');
        }
    };

    const handleRefrescarTareas = async () => {
        try {
            const response = await axios.post(`/api/inventario-ciclico/planillas/${planilla.id}/refrescar-tareas`);
            if (response.data.success) {
                loadPlanillaDetalle();
            }
        } catch (error) {
            console.error('Error al refrescar tareas:', error);
        }
    };

    const handleGenerarReporte = (tipo) => {
        if (tipo === 'pdf') {
            setShowReporteFullScreen(true);
            return;
        }
        axios.get(`/api/inventario-ciclico/planillas/${planilla.id}/reporte-excel`, { responseType: 'blob' })
            .then((response) => {
                const blob = new Blob([response.data]);
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `Planilla_Inventario_${planilla.id}_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                document.body.removeChild(a);
            })
            .catch((error) => {
                console.error('Error:', error);
                alert('Error al generar el reporte Excel');
            });
    };

    const getEstatusTareaColor = (estatus) => {
        switch (estatus) {
            case 'Confirmado':
                return 'bg-green-100 text-green-800';
            case 'Pendiente Aprobación':
                return 'bg-yellow-100 text-yellow-800';
            case 'Aprobada':
                return 'bg-blue-100 text-blue-800';
            case 'Rechazada':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const formatNumber = (value, decimals = 2) => {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value || 0);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('es-VE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const canClose = planillaData?.estatus == 'Abierta' && 
        planillaData?.tareas?.every(d => d.estado == 1);

    return (
        <div className="">
            {/* Header */}
            <div className="flex justify-between items-start">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">
                        Planilla #{planillaData?.id}
                    </h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Creada el {formatDate(planillaData?.fecha_creacion)} por {planillaData?.usuarioCreador?.usuario}
                    </p>
                    {planillaData?.notas_generales && (
                        <p className="text-sm text-gray-600 mt-2">
                            <strong>Notas:</strong> {planillaData.notas_generales}
                        </p>
                    )}
                </div>
                
                <div className="flex space-x-3">
                    <button
                        onClick={handleRefrescarTareas}
                        className="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        <i className="fa fa-refresh mr-2"></i>
                        Refrescar
                    </button>
                    
                    {planillaData?.estatus == 'Abierta' && (
                        <>
                            <button
                                onClick={() => handleGenerarReporte('pdf')}
                                className="px-4 py-2 text-sm font-medium text-white bg-amber-600 border border-transparent rounded-md hover:bg-amber-700"
                                title="Reporte pendiente (no cerrada): cant. sistema y cant. entrantes"
                            >
                                <i className="fa fa-file-text-o mr-2"></i>
                                Reporte pendiente
                            </button>
                            <button
                                onClick={() => setShowBuscarModal(true)}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700"
                            >
                                <i className="fa fa-plus mr-2"></i>
                                Agregar Producto
                            </button>
                            {pendientes.length > 0 && (
                                <button
                                    onClick={handleEnviarACentral}
                                    disabled={enviando}
                                    className="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    {enviando ? (
                                        <>
                                            <i className="fa fa-spinner fa-spin mr-2"></i>
                                            Enviando...
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa fa-paper-plane mr-2"></i>
                                            Enviar a Central ({pendientes.length})
                                        </>
                                    )}
                                </button>
                            )}
                        </>
                    )}
                    
                    {canClose && (
                        <button
                            onClick={handleCerrarPlanilla}
                            className="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700"
                        >
                            <i className="fa fa-check mr-2"></i>
                            Cerrar Planilla
                        </button>
                    )}
                    
                    {planillaData?.estatus == 'Cerrada' && (
                        <div className="flex space-x-2">
                            <button
                                onClick={() => handleGenerarReporte('pdf')}
                                className="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                            >
                                <i className="fa fa-file-pdf-o mr-2"></i>
                                Reporte PDF
                            </button>
                            <button
                                onClick={() => handleGenerarReporte('excel')}
                                className="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700"
                            >
                                <i className="fa fa-file-excel-o mr-2"></i>
                                Reporte Excel
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* Pendientes de envío (localStorage) */}
            {pendientes.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 p-4 rounded-lg shadow-sm">
                    <h3 className="text-sm font-semibold text-amber-900 mb-2">
                        <i className="fa fa-clock-o mr-2"></i>
                        Pendientes de envío a Central ({pendientes.length})
                    </h3>
                    <p className="text-xs text-amber-800 mb-3">
                        Estos productos se guardaron localmente. Use &quot;Enviar a Central&quot; para enviarlos todos de una vez.
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full divide-y divide-amber-200 text-sm table-fixed" style={{ minWidth: '520px' }}>
                            <colgroup>
                                <col style={{ width: '32%' }} />
                                <col style={{ width: '10%' }} />
                                <col style={{ width: '12%' }} />
                                <col style={{ width: '12%' }} />
                                <col style={{ width: '24%' }} />
                                <col style={{ width: '10%' }} />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th className="text-left py-2 text-amber-900">Producto</th>
                                    <th className="text-right py-2 text-amber-900">Cant. sistema</th>
                                    <th className="text-right py-2 text-amber-900">Cant. física</th>
                                    <th className="text-right py-2 text-amber-900">Diferencia</th>
                                    <th className="text-left py-2 text-amber-900">Hora agregado</th>
                                    <th className="text-center py-2 text-amber-900 w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendientes.map((p, idx) => (
                                    <tr key={`${p.id_producto}-${idx}`} className="border-t border-amber-200">
                                        <td className="py-2 overflow-hidden">
                                            <div className="font-medium text-gray-900 truncate" title={p.producto?.descripcion}>{p.producto?.descripcion}</div>
                                            <div className="text-xs text-gray-500 truncate" title={`Barras: ${p.producto?.codigo_barras ?? '-'} | Proveedor: ${p.producto?.codigo_proveedor ?? '-'}`}>
                                                Barras: {p.producto?.codigo_barras ?? '-'}
                                                {p.producto?.codigo_proveedor != null && p.producto?.codigo_proveedor !== '' && (
                                                    <span className="ml-2">| Proveedor: {p.producto.codigo_proveedor}</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-right py-2 whitespace-nowrap">
                                            {p.cantidad_sistema ?? p.producto?.cantidad ?? '-'}
                                        </td>
                                        <td className="text-right py-2 whitespace-nowrap">
                                            {editingPendienteIdx === idx ? (
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={editCantidadValue}
                                                    onChange={(e) => setEditCantidadValue(e.target.value)}
                                                    onBlur={() => guardarCantidadPendiente(idx)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') guardarCantidadPendiente(idx);
                                                        if (e.key === 'Escape') {
                                                            setEditingPendienteIdx(null);
                                                            setEditCantidadValue('');
                                                        }
                                                    }}
                                                    className="w-20 px-2 py-1 border border-amber-400 rounded text-right text-sm"
                                                    autoFocus
                                                />
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => iniciarEditarCantidad(idx)}
                                                    className="text-right hover:bg-amber-100 px-2 py-1 rounded inline-flex items-center gap-1 min-w-[3rem]"
                                                    title="Editar cantidad física"
                                                >
                                                    {p.cantidad_fisica}
                                                    <i className="fa fa-pencil text-xs text-amber-700"></i>
                                                </button>
                                            )}
                                        </td>
                                        <td className="text-right py-2 whitespace-nowrap">
                                            {(p.cantidad_sistema ?? p.producto?.cantidad) != null
                                                ? (p.cantidad_fisica - (p.cantidad_sistema ?? p.producto.cantidad))
                                                : '-'}
                                        </td>
                                        <td className="py-2 text-gray-600 whitespace-nowrap overflow-hidden">
                                            {p.fecha_agregado
                                                ? new Date(p.fecha_agregado).toLocaleString('es-VE', {
                                                      dateStyle: 'short',
                                                      timeStyle: 'short',
                                                  })
                                                : '-'}
                                        </td>
                                        <td className="py-2 text-center">
                                            <button
                                                type="button"
                                                onClick={() => quitarPendiente(idx)}
                                                className="text-red-600 hover:text-red-800 text-xs inline-flex"
                                                title="Quitar de pendientes"
                                            >
                                                <i className="fa fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Estatus de la planilla */}
            <div className="bg-white p-4 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getEstatusTareaColor(planillaData?.estatus)}`}>
                            {planillaData?.estatus}
                        </span>
                        <span className="text-sm text-gray-600">
                            {planillaData?.tareas?.length || 0} productos
                        </span>
                    </div>
                    
                    {planillaData?.estatus == 'Cerrada' && (
                        <div className="text-sm text-gray-600">
                            Cerrada el {formatDate(planillaData?.fecha_cierre)}
                        </div>
                    )}
                </div>
            </div>

            {/* Tabla de productos */}
            <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
                {loading ? (
                    <div className="flex justify-center items-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>
                ) : planillaData?.tareas?.length == 0 ? (
                    <div className="text-center py-12">
                        <i className="fa fa-box text-4xl text-gray-400 mb-4"></i>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                            No hay productos en esta planilla
                        </h3>
                        <p className="text-gray-500">
                            Agrega productos para comenzar el conteo de inventario.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Producto / Códigos
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cant. Sistema
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cant. Física
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Diferencia
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Valor Diferencia
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID Tarea
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Envío
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Aprobación Central
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Aprobación
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {planillaData?.tareas?.map((detalle) => (
                                    <tr key={detalle.id}>
                                        <td className="px-6 py-4">
                                            <div>
                                                <div className="text-sm font-medium text-gray-900">
                                                    {detalle.producto?.descripcion}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    <span className="font-medium">Barras:</span> {detalle.producto?.codigo_barras || 'N/A'}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    <span className="font-medium">Proveedor:</span> {detalle.producto?.codigo_proveedor || 'N/A'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {detalle.cantidad_sistema}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {editingDetalle?.id == detalle.id ? (
                                                <input
                                                    type="number"
                                                    value={editForm.cantidad_fisica}
                                                    onChange={(e) => setEditForm(prev => ({ ...prev, cantidad_fisica: e.target.value }))}
                                                    className="w-20 px-2 py-1 border border-gray-300 rounded text-sm"
                                                    min="0"
                                                />
                                            ) : (
                                                <span className="text-sm text-gray-900">
                                                    {detalle.cantidad_fisica}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`text-sm font-medium ${
                                                detalle.diferencia_cantidad > 0 ? 'text-green-600' : 
                                                detalle.diferencia_cantidad < 0 ? 'text-red-600' : 'text-gray-600'
                                            }`}>
                                                {detalle.diferencia_cantidad > 0 ? '+' : ''}{detalle.diferencia_cantidad}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${formatNumber(detalle.diferencia_valor)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {detalle.id || '-'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">
                                            {detalle.created_at ? (
                                                <div>
                                                    <div>{new Date(detalle.created_at).toLocaleDateString('es-VE')}</div>
                                                    <div className="text-xs text-gray-400">
                                                        {new Date(detalle.created_at).toLocaleTimeString('es-VE', {hour: '2-digit', minute: '2-digit'})}
                                                    </div>
                                                </div>
                                            ) : '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                detalle.permiso == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                            }`}>
                                                {detalle.permiso == 1 ? 'Aprobado' : 'Pendiente'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">
                                            {detalle.fecha_aprobacion ? (
                                                <div>
                                                    <div>{new Date(detalle.fecha_aprobacion).toLocaleDateString('es-VE')}</div>
                                                    <div className="text-xs text-gray-400">
                                                        {new Date(detalle.fecha_aprobacion).toLocaleTimeString('es-VE', {hour: '2-digit', minute: '2-digit'})}
                                                    </div>
                                                </div>
                                            ) : '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                detalle.estado == 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                            }`}>
                                                {detalle.estado == 1 ? 'Ejecutado' : 'Pendiente'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {editingDetalle?.id == detalle.id ? (
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={handleSaveEdit}
                                                        className="text-green-600 hover:text-green-900"
                                                    >
                                                        <i className="fa fa-check"></i>
                                                    </button>
                                                    <button
                                                        onClick={handleCancelEdit}
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            ) : planillaData?.estatus == 'Abierta' ? (
                                                <button
                                                    onClick={() => handleEditDetalle(detalle)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    <i className="fa fa-edit"></i>
                                                </button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Reporte a pantalla completa (solo front, datos de planillaData + pendientes) */}
            {showReporteFullScreen && (
                <div
                    className="fixed inset-0 z-[9999] bg-white overflow-auto"
                    style={{ fontFamily: 'Arial, sans-serif' }}
                >
                    <style>{`
                        @media print {
                            .reporte-no-print { display: none !important; }
                            .reporte-print-container { box-shadow: none; border: none; max-width: 100%; }
                        }
                        .reporte-print-container { max-width: 215.9mm; margin: 0 auto; padding: 14px; }
                        .reporte-tabla { width: 100%; border-collapse: collapse; font-size: 10px; }
                        .reporte-tabla th, .reporte-tabla td { border: 1px solid #000; padding: 4px 6px; }
                        .reporte-tabla th { background: #e0e0e0; font-weight: bold; }
                    `}</style>
                    <div className="reporte-no-print flex gap-3 items-center p-4 bg-gray-100 border-b border-gray-300 sticky top-0 z-10">
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="px-4 py-2 bg-blue-600 text-white rounded font-medium text-sm hover:bg-blue-700"
                        >
                            <i className="fa fa-print mr-2"></i>
                            Imprimir
                        </button>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="px-4 py-2 bg-red-600 text-white rounded font-medium text-sm hover:bg-red-700"
                        >
                            <i className="fa fa-file-pdf-o mr-2"></i>
                            Guardar como PDF
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowReporteFullScreen(false)}
                            className="px-4 py-2 bg-gray-600 text-white rounded font-medium text-sm hover:bg-gray-700"
                        >
                            <i className="fa fa-times mr-2"></i>
                            Cerrar reporte
                        </button>
                    </div>
                    <div className="reporte-print-container p-4">
                        <div className="mb-4 pb-2 border-b border-gray-300">
                            <p className="text-base font-bold m-0">
                                Planilla {planillaData?.estatus === 'Cerrada' ? 'cerrada' : 'pendiente'} #{planillaData?.id ?? planilla.id}
                            </p>
                            <p className="text-xs text-gray-600 mt-1">
                                {planillaData?.sucursal?.nombre ?? ''} · {planillaData?.sucursal?.codigo ?? ''}
                                {planillaData?.fecha_creacion && ` · ${new Date(planillaData.fecha_creacion).toLocaleDateString('es-VE')}`}
                            </p>
                        </div>

                        <table className="reporte-tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th>Cód. barras</th>
                                    <th>Cód. proveedor</th>
                                    <th className="text-right">Cant. sistema</th>
                                    <th className="text-center">Cant. física</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(planillaData?.tareas ?? []).map((tarea, idx) => {
                                    const prod = tarea.producto ?? {};
                                    return (
                                        <tr key={tarea.id ?? idx}>
                                            <td>{tarea.id ?? idx + 1}</td>
                                            <td>{prod.descripcion ?? '—'}</td>
                                            <td>{prod.codigo_barras ?? '—'}</td>
                                            <td>{prod.codigo_proveedor ?? '—'}</td>
                                            <td className="text-right">{Number(tarea.cantidad_sistema ?? 0).toLocaleString()}</td>
                                            <td className="text-center">&nbsp;</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        {pendientes.length > 0 && (
                            <div className="mt-6">
                                <p className="text-sm font-bold mb-2">Solo en local (pendientes envío)</p>
                                <table className="reporte-tabla">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Producto</th>
                                            <th>Cód. barras</th>
                                            <th>Cód. proveedor</th>
                                            <th className="text-right">Cant. sistema</th>
                                            <th className="text-center">Cant. física</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendientes.map((p, idx) => {
                                            const prod = p.producto ?? {};
                                            const cantSistema = p.cantidad_sistema ?? prod.cantidad ?? '';
                                            return (
                                                <tr key={`p-${p.id_producto}-${idx}`}>
                                                    <td>{idx + 1}</td>
                                                    <td>{prod.descripcion ?? '—'}</td>
                                                    <td>{prod.codigo_barras ?? '—'}</td>
                                                    <td>{prod.codigo_proveedor ?? '—'}</td>
                                                    <td className="text-right">
                                                        {typeof cantSistema === 'number' ? cantSistema.toLocaleString() : cantSistema}
                                                    </td>
                                                    <td className="text-center">&nbsp;</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Modal para buscar productos */}
            {showBuscarModal && (
                <BuscarProductoModal
                    onClose={() => setShowBuscarModal(false)}
                    onProductoSelected={handleProductoAdded}
                    planillaId={planilla.id}
                    sucursalConfig={sucursalConfig}
                />
            )}
        </div>
    );
};

export default PlanillaDetalle; 