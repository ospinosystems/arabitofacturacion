import { render } from 'react-dom';
import { useState, StrictMode } from 'react';
import React from 'react';
import axios from 'axios';

// Configurar interceptor de axios para incluir token de sesión
axios.interceptors.request.use(
    config => {
        const sessionToken = localStorage.getItem('session_token');
        if (sessionToken) {
            config.headers['X-Session-Token'] = sessionToken;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

// Interceptor para manejar errores de sesión
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.data && 
            error.response.data.msj && 
            error.response.data.msj.includes('Sin sesión activa')) {
            // Limpiar datos de sesión y redirigir al login
            localStorage.removeItem('user_data');
            localStorage.removeItem('session_token');
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

import Facturar from "./facturar";
import Login from "./login";
import Notificacion from '../components/notificacion';
import { AppProvider } from '../contexts/AppContext';
import Cargando from '../components/cargando';

function Index() {
    const [loginActive, setLoginActive] = useState(false)
    const [user, setUser] = useState([])
    
    const [msj, setMsj] = useState("")
    const [loading, setLoading] = useState(false)
    const [showHeaderAndMenu, setShowHeaderAndMenu] = useState(true)

    // Verificar sesión al cargar la página (no redirigir a /ppr sin validar en servidor para evitar bucle)
    React.useEffect(() => {
        const savedUser = localStorage.getItem('user_data');
        const sessionToken = localStorage.getItem('session_token');
        
        if (savedUser && sessionToken) {
            try {
                const userData = JSON.parse(savedUser);
                const tipo = userData.tipo_usuario;
                
                // Verificar primero en el servidor si la sesión sigue válida
                axios.post('/verificarLogin')
                    .then(response => {
                        if (!response.data.estado) {
                            localStorage.removeItem('user_data');
                            localStorage.removeItem('session_token');
                            setLoginActive(false);
                            setUser([]);
                            return;
                        }
                        // Solo redirigir a /ppr si el servidor confirmó sesión y es portero (evita bucle cuando sesión expiró)
                        if (tipo === 10 || tipo === '10') {
                            window.location.href = '/ppr';
                            return;
                        }
                        setUser(userData);
                        setLoginActive(true);
                    })
                    .catch(() => {
                        localStorage.removeItem('user_data');
                        localStorage.removeItem('session_token');
                        setLoginActive(false);
                        setUser([]);
                    });
            } catch (error) {
                localStorage.removeItem('user_data');
                localStorage.removeItem('session_token');
                setLoginActive(false);
                setUser([]);
            }
        }
    }, []);

    const loginRes = res => {
        notificar(res)
        if (res.data) {
            if (res.data.user) {
                // Guardar datos de usuario y token en localStorage
                localStorage.setItem('user_data', JSON.stringify(res.data.user));
                if (res.data.session_token) {
                    localStorage.setItem('session_token', res.data.session_token);
                }
                
                // Verificar si debe redirigir a gestión de almacén (Galpón Valencia 1)
                if (res.data.redirect_to_warehouse) {
                    window.location.href = '/warehouse-inventory';
                    return;
                }
                // Redirigir por tipo de usuario (ej: portero -> /ppr)
                if (res.data.redirect_url && res.data.redirect_url !== '/' && res.data.redirect_url !== '/login') {
                    window.location.href = res.data.redirect_url;
                    return;
                }
                
                setUser(res.data.user)
                setLoginActive(res.data.estado)
            }
        }
        
    } 
    const notificar = (msj, fixed = true, simple=false) => {
        if (fixed) {
            setTimeout(() => {
                setMsj("")
            }, 3000)
        }else{
            setTimeout(() => {
                setMsj("")
            }, 100000)
        }
        if (msj == "") {
            setMsj("")
        } else {
            if (msj.data) {
                if (msj.data.msj) {
                    setMsj(msj.data.msj)

                } else {

                    setMsj(JSON.stringify(msj.data))
                }
            }else if(typeof msj === 'string' || msj instanceof String){
                setMsj(msj)
            }

        }
    }


    return(
        <StrictMode>
            <AppProvider>
                <Cargando active={loading} />

                {msj != "" ? <Notificacion msj={msj} notificar={notificar} /> : null}

                {loginActive&&user?<Facturar
                    setLoading={setLoading}
                    user={user}
                    notificar={notificar}
                    showHeaderAndMenu={showHeaderAndMenu}
                    setShowHeaderAndMenu={setShowHeaderAndMenu}
                />:<Login 
                    loginRes={loginRes} 
                />}
                
                {/* {loginActive && user && (
                    <footer className={` bg-white border-t py-1 border-gray-100 transition-all duration-300 ${
                        showHeaderAndMenu 
                            ? 'translate-y-0 opacity-100' 
                            : 'translate-y-full opacity-0 pointer-events-none'
                    }`}>
                        <div className="px-4 py-1">
                            <div className="flex items-center justify-between text-xs text-gray-500">
                                <div className="flex items-center space-x-4">
                                    <span>ESC: Menú • R: Referencia • F10: Toggle</span>
                                    <span>D: Débito • E: Efectivo • T: Transferencia • B: Biopago • C: Crédito</span>
                                </div>
                                <span className="text-gray-400">Arabito v1.2</span>
                            </div>
                        </div>
                    </footer>
                )} */}
            </AppProvider>
        </StrictMode>
    )
}

render(<Index />, document.getElementById('app'));
