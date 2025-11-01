import { render } from 'react-dom';
import React from 'react';
import WarehouseModule from './components/warehouses/WarehouseModule';

const warehouseApp = document.getElementById('warehouse-app');

if (warehouseApp) {
    render(<WarehouseModule />, warehouseApp);
}

