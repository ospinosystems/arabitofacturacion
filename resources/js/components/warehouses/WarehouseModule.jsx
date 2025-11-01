import React, { useState, useEffect } from 'react';
import WarehousesList from './WarehousesList';
import WarehouseForm from './WarehouseForm';
import WarehouseInventoryModule from './WarehouseInventoryModule';

const WarehouseModule = () => {
    const [activeTab, setActiveTab] = useState('ubicaciones');
    const [showForm, setShowForm] = useState(false);
    const [editingWarehouse, setEditingWarehouse] = useState(null);

    const handleNewWarehouse = () => {
        setEditingWarehouse(null);
        setShowForm(true);
    };

    const handleEditWarehouse = (warehouse) => {
        setEditingWarehouse(warehouse);
        setShowForm(true);
    };

    const handleCloseForm = () => {
        setShowForm(false);
        setEditingWarehouse(null);
    };

    return (
        <div className="warehouse-module p-4">
            <div className="row mb-4">
                <div className="col-12">
                    <h2>Gestión de Almacén</h2>
                </div>
            </div>

            {/* Pestañas */}
            <ul className="nav nav-tabs mb-4">
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeTab === 'ubicaciones' ? 'active' : ''}`}
                        onClick={() => setActiveTab('ubicaciones')}
                    >
                        Ubicaciones
                    </button>
                </li>
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeTab === 'inventario' ? 'active' : ''}`}
                        onClick={() => setActiveTab('inventario')}
                    >
                        Inventario
                    </button>
                </li>
            </ul>

            {/* Contenido según pestaña activa */}
            {activeTab === 'ubicaciones' && (
                <>
                    {!showForm ? (
                        <WarehousesList 
                            onNew={handleNewWarehouse}
                            onEdit={handleEditWarehouse}
                        />
                    ) : (
                        <WarehouseForm 
                            warehouse={editingWarehouse}
                            onClose={handleCloseForm}
                        />
                    )}
                </>
            )}

            {activeTab === 'inventario' && (
                <WarehouseInventoryModule />
            )}
        </div>
    );
};

export default WarehouseModule;

