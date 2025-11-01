import React, { useState } from 'react';
import AsignarProducto from './AsignarProducto';
import TransferirProducto from './TransferirProducto';
import ConsultarInventario from './ConsultarInventario';
import HistorialMovimientos from './HistorialMovimientos';

const WarehouseInventoryModule = () => {
    const [activeSubTab, setActiveSubTab] = useState('asignar');

    return (
        <div className="warehouse-inventory-module">
            {/* Sub-pestañas */}
            <ul className="nav nav-pills mb-4">
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeSubTab === 'asignar' ? 'active' : ''}`}
                        onClick={() => setActiveSubTab('asignar')}
                    >
                        Asignar Producto
                    </button>
                </li>
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeSubTab === 'transferir' ? 'active' : ''}`}
                        onClick={() => setActiveSubTab('transferir')}
                    >
                        Transferir
                    </button>
                </li>
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeSubTab === 'consultar' ? 'active' : ''}`}
                        onClick={() => setActiveSubTab('consultar')}
                    >
                        Consultar
                    </button>
                </li>
                <li className="nav-item">
                    <button 
                        className={`nav-link ${activeSubTab === 'historial' ? 'active' : ''}`}
                        onClick={() => setActiveSubTab('historial')}
                    >
                        Historial
                    </button>
                </li>
            </ul>

            {/* Contenido según sub-pestaña activa */}
            {activeSubTab === 'asignar' && <AsignarProducto />}
            {activeSubTab === 'transferir' && <TransferirProducto />}
            {activeSubTab === 'consultar' && <ConsultarInventario />}
            {activeSubTab === 'historial' && <HistorialMovimientos />}
        </div>
    );
};

export default WarehouseInventoryModule;

