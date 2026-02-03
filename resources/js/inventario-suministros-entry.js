import { render } from 'react-dom';
import React from 'react';
import InventarioSuministrosSucursal from './components/InventarioSuministrosSucursal';

function App() {
    const hasSession = typeof localStorage !== 'undefined' && localStorage.getItem('session_token');
    if (!hasSession) {
        window.location.href = '/';
        return null;
    }
    return (
        <InventarioSuministrosSucursal
            onBack={() => { window.location.href = '/'; }}
        />
    );
}

render(<App />, document.getElementById('app'));
