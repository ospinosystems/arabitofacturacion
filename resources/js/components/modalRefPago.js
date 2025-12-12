import { useState, useEffect } from "react";
import { useHotkeys } from "react-hotkeys-hook";
import axios from "axios";

const MODULOS = [
    { id: 'Central', label: 'Central', icon: 'fa-building' },
    { id: 'AutoValidar', label: 'Auto-Validar', icon: 'fa-check-circle' },
];

export default function ModalRefPago({
    addRefPago,
    descripcion_referenciapago,
    setdescripcion_referenciapago,
    banco_referenciapago,
    cedula_referenciapago,
    setcedula_referenciapago,
    telefono_referenciapago,
    settelefono_referenciapago,
    setbanco_referenciapago,
    monto_referenciapago,
    setmonto_referenciapago,
    tipo_referenciapago,
    settipo_referenciapago,
    transferencia,
    dolar,
    number,
    bancos,
    montoTraido,
    tipoTraido,
    pedidoData,
}) {
    const [isrefbanbs, setisrefbanbs] = useState(true);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Estado para el módulo seleccionado
    const [moduloSeleccionado, setModuloSeleccionado] = useState('Central');

    // Obtener fecha de hoy en formato YYYY-MM-DD
    const getTodayDate = () => {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // Nuevos campos para los módulos
    const [fechaPago, setFechaPago] = useState(getTodayDate());
    const [telefonoPago, setTelefonoPago] = useState('');
    const [codigoBancoOrigen, setCodigoBancoOrigen] = useState('');
    const [montoReal, setMontoReal] = useState(''); // Monto real transferido (si es mayor al del pedido)

    // Estados para autovalidación
    const [autoValidando, setAutoValidando] = useState(false);
    const [resultadoValidacion, setResultadoValidacion] = useState(null);

    // Monto total del pedido en Bs
    const montoTotalPedido = parseFloat(pedidoData?.bs || 0);
    
    // Calcular suma de referencias ya cargadas
    const sumaReferencias = (pedidoData?.referencias || []).reduce((sum, ref) => {
        return sum + parseFloat(ref.monto || 0);
    }, 0);
    
    // Monto restante (total - referencias previas)
    const montoRestante = Math.max(0, montoTotalPedido - sumaReferencias);
    
    // Monto máximo permitido es el total del pedido en Bs
    const montoMaximoPedido = montoTotalPedido;

    // Validar formato de teléfono: 0 + código + 7 dígitos = 11 dígitos
    const validarTelefono = (telefono) => {
        // Códigos válidos: 0412, 0414, 0416, 0424, 0426, 0422
        const regex = /^0(412|414|416|424|426|422)\d{7}$/;
        return regex.test(telefono);
    };

    // Formatear teléfono de 04XX a 58XX antes de enviar
    const formatearTelefono = (telefono) => {
        if (!telefono) return "";
        let numeros = telefono.replace(/\D/g, '');
        if (numeros.startsWith('0')) {
            numeros = '58' + numeros.slice(1);
        }
        if (!numeros.startsWith('58')) {
            numeros = '58' + numeros;
        }
        return numeros;
    };

    // Validar formato de monto: xxxxx.xx (permite negativos)
    const validarFormatoMonto = (monto) => {
        const regex = /^-?\d+(\.\d{1,2})?$/;
        return regex.test(monto);
    };

    // Obtener configuración de campos según módulo
    const getModuloConfig = () => {
        switch (moduloSeleccionado) {
            case 'Central':
                return {
                    showAllFields: true,
                    showBanco: true,
                    showReferencia: true,
                    referenciaMinLength: null,
                    referenciaMaxLength: null,
                    referenciaPlaceholder: 'Referencia completa de la transacción...',
                    showFechaPago: false,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: false,
                };
            case 'AutoValidar':
                return {
                    showAllFields: false,
                    showBanco: false,
                    showReferencia: true,
                    referenciaMinLength: 12,
                    referenciaMaxLength: 12,
                    referenciaPlaceholder: 'Número de referencia (12 dígitos)...',
                    showFechaPago: true,
                    showTelefonoPago: true,
                    showCodigoBancoOrigen: true,
                };
            default:
                return {
                    showAllFields: true,
                    showBanco: true,
                    showReferencia: true,
                    referenciaMinLength: null,
                    referenciaMaxLength: null,
                    referenciaPlaceholder: 'Referencia completa de la transacción...',
                    showFechaPago: false,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: false,
                };
        }
    };

    const moduloConfig = getModuloConfig();

    // Validar formulario para AutoValidar
    const validateFormAutoValidar = () => {
        const newErrors = {};

        // Validar referencia (exactamente 12 dígitos, se autocompleta con ceros)
        if (!descripcion_referenciapago || descripcion_referenciapago.trim() === '') {
            newErrors.descripcion = 'La referencia es obligatoria';
        }

        // Validar teléfono
        if (!telefonoPago) {
            newErrors.telefonoPago = 'El teléfono es obligatorio';
        } else if (!validarTelefono(telefonoPago)) {
            newErrors.telefonoPago = 'Formato inválido. Debe ser: 0 + código (412,414,416,424,426,422) + 7 dígitos. Ej: 04141234567';
        }

        // Validar banco origen
        if (!codigoBancoOrigen) {
            newErrors.codigoBancoOrigen = 'El banco origen es obligatorio';
        }

        // Validar fecha de pago
        if (!fechaPago) {
            newErrors.fechaPago = 'La fecha de pago es obligatoria';
        }

        // Validar monto
        if (!monto_referenciapago || monto_referenciapago == 0) {
            newErrors.monto = 'El monto no puede ser 0';
        } else if (!validarFormatoMonto(monto_referenciapago)) {
            newErrors.monto = 'El monto debe tener formato válido (ej: 1234.56)';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Validar formulario
    const validateForm = () => {
        const newErrors = {};

        // Validación según módulo
        if (moduloSeleccionado === 'Central') {
            // Validar banco y referencia
            if (!descripcion_referenciapago || descripcion_referenciapago.trim() === '') {
                newErrors.descripcion = 'La referencia es obligatoria';
            }

            if (!banco_referenciapago || banco_referenciapago === '') {
                newErrors.banco = 'Debe seleccionar un banco';
            }
        } else if (moduloSeleccionado === 'AutoValidar') {
            return validateFormAutoValidar();
        }

        if (!monto_referenciapago || monto_referenciapago == 0) {
            newErrors.monto = 'El monto no puede ser 0';
        } else if (!validarFormatoMonto(monto_referenciapago)) {
            newErrors.monto = 'El monto debe tener formato válido (ej: 1234.56)';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Mapeo de módulo a categoría
    const getCategoriaFromModulo = () => {
        switch (moduloSeleccionado) {
            case 'Central': return 'central';
            case 'AutoValidar': return 'autovalidar';
            default: return 'central';
        }
    };

    // Función para autovalidar transferencia
    const handleAutoValidar = async () => {
        if (!validateFormAutoValidar()) {
            return;
        }

        setAutoValidando(true);
        setResultadoValidacion(null);

        try {
            // Autocompletar referencia con ceros a la izquierda hasta 12 dígitos
            const referenciaFormateada = descripcion_referenciapago.padStart(12, '0');
            
            const response = await axios.post('/autovalidar-transferencia', {
                referencia: referenciaFormateada,
                telefono: formatearTelefono(telefonoPago),
                banco_origen: codigoBancoOrigen,
                fecha_pago: fechaPago,
                monto: monto_referenciapago,
                id_pedido: pedidoData?.id,
                cedula: cedula_referenciapago,
            });

            if (response.data.estado === true) {
                setResultadoValidacion({
                    success: true,
                    message: response.data.msj || '¡Transferencia validada y aprobada exitosamente!',
                    data: response.data.data
                });
                
                // Recargar el pedido después de 1.5 segundos
                setTimeout(() => {
                    addRefPago("recargar");
                }, 1500);
            } else if (response.data.monto_diferente) {
                // Caso especial: referencia encontrada pero monto diferente
                setResultadoValidacion({
                    success: false,
                    montoDiferente: true,
                    message: response.data.msj,
                    data: {
                        modalidad: response.data.modalidad,
                        referencia_encontrada: response.data.referencia_encontrada,
                        monto_ingresado: response.data.monto_ingresado,
                        monto_banco: response.data.monto_banco,
                        response: response.data.response
                    }
                });
            } else {
                setResultadoValidacion({
                    success: false,
                    message: response.data.msj || 'No se pudo validar la transferencia',
                    data: response.data.data
                });
            }
        } catch (error) {
            console.error('Error al autovalidar:', error);
            setResultadoValidacion({
                success: false,
                message: error.response?.data?.msj || 'Error de conexión al validar la transferencia'
            });
        } finally {
            setAutoValidando(false);
        }
    };

    // Manejar envío del formulario
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);
        try {
            const categoria = getCategoriaFromModulo();
            const datosAdicionales = {};

            // Agregar datos adicionales para módulos automáticos
            if (moduloSeleccionado !== 'Central') {
                if (moduloConfig.showFechaPago) {
                    datosAdicionales.fecha_pago = fechaPago;
                }
                if (moduloConfig.showTelefonoPago) {
                    // Formatear teléfono de 04XX a 58XX antes de enviar
                    datosAdicionales.telefono_pago = formatearTelefono(telefonoPago);
                }
                if (moduloConfig.showCodigoBancoOrigen) {
                    datosAdicionales.codigo_banco_origen = codigoBancoOrigen;
                }
            }

            // Si hay monto real (cliente transfirió más), enviarlo
            if (montoReal && parseFloat(montoReal) > parseFloat(monto_referenciapago)) {
                datosAdicionales.monto_real = montoReal;
            }

            await addRefPago("enviar", "", "", categoria, datosAdicionales);
        } catch (error) {
            console.error('Error al guardar:', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    // Cerrar modal
    const handleClose = () => {
        addRefPago("toggle");
    };

    // Manejar tecla Enter
    const handleKeyPress = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            // Si está en modo AutoValidar, llamar a handleAutoValidar
            if (moduloSeleccionado === 'AutoValidar') {
                handleAutoValidar();
            } else {
                handleSubmit(e);
            }
        }
    };

    // Efecto para calcular monto según banco o módulo
    useEffect(() => {
        // Para módulos automáticos, siempre es en Bs
        if (moduloSeleccionado !== 'Central') {
            let monto = (transferencia * dolar).toFixed(2);
            setmonto_referenciapago(monto);
            setisrefbanbs(true);
        } else if (
            banco_referenciapago == "ZELLE" ||
            banco_referenciapago == "BINANCE" ||
            banco_referenciapago == "AirTM"
        ) {
            setisrefbanbs(false);
            setmonto_referenciapago(transferencia);
        } else {
            let monto = (transferencia * dolar).toFixed(2);
            setmonto_referenciapago(monto);
            setisrefbanbs(true);
        }
    }, [banco_referenciapago, transferencia, dolar, moduloSeleccionado]);

    // Limpiar errores cuando cambian los valores
    useEffect(() => {
        if (errors.descripcion && descripcion_referenciapago) {
            setErrors(prev => ({ ...prev, descripcion: null }));
        }
        if (errors.banco && banco_referenciapago) {
            setErrors(prev => ({ ...prev, banco: null }));
        }
        if (errors.monto && monto_referenciapago > 0) {
            setErrors(prev => ({ ...prev, monto: null }));
        }
        if (errors.fechaPago && fechaPago) {
            setErrors(prev => ({ ...prev, fechaPago: null }));
        }
        if (errors.telefonoPago && telefonoPago) {
            setErrors(prev => ({ ...prev, telefonoPago: null }));
        }
        if (errors.codigoBancoOrigen && codigoBancoOrigen) {
            setErrors(prev => ({ ...prev, codigoBancoOrigen: null }));
        }
    }, [descripcion_referenciapago, banco_referenciapago, monto_referenciapago, fechaPago, telefonoPago, codigoBancoOrigen]);

    // Limpiar campos cuando cambia el módulo (excepto fechaPago que mantiene la fecha actual)
    useEffect(() => {
        setdescripcion_referenciapago('');
        setTelefonoPago('');
        setCodigoBancoOrigen('');
        setErrors({});
        // Resetear fecha a hoy cuando cambia de módulo
        setFechaPago(getTodayDate());
    }, [moduloSeleccionado]);

    // Hotkey para cerrar con ESC
    useHotkeys('esc', handleClose, { enableOnTags: ['INPUT', 'SELECT'] });

    // Auto-focus en el primer input al abrir
    useEffect(() => {
        const firstInput = document.querySelector('#cedula-input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }, []);

    // Cargar monto cuando se abra el modal
    useEffect(() => {
        if (montoTraido && montoTraido > 0) {
            setmonto_referenciapago(montoTraido);
        }
    }, [montoTraido]);

    return (
        <>
            {/* Overlay sutil que no bloquea la vista */}
            <div
                className="fixed inset-0 z-40 transition-opacity bg-black opacity-60 backdrop-blur-[2px]"
                onClick={handleClose}
            ></div>

            {/* Modal flotante posicionado */}
            <div className="fixed z-50 w-full max-w-2xl overflow-y-auto max-h-[90vh] transform -translate-x-1/2 -translate-y-1/2 bg-white border border-gray-200 rounded-lg shadow-xl top-1/2 left-1/2">
                {/* Header compacto */}
                <div className="px-4 py-3 border-b border-orange-100 bg-orange-50">
                    <div className="flex items-center justify-between">
                        <h3 className="flex items-center text-sm font-semibold text-gray-800">
                            <i className="mr-2 text-xs text-orange-500 fa fa-credit-card"></i>
                            Referencia de Pago
                        </h3>
                        <button
                            onClick={handleClose}
                            className="p-1 text-gray-400 transition-colors rounded hover:text-gray-600 hover:bg-gray-100"
                            title="Cerrar (ESC)"
                        >
                            <i className="text-xs fa fa-times"></i>
                        </button>
                    </div>

                    {/* Selector de módulos */}
                    <div className="flex flex-wrap gap-1 mt-3">
                        {MODULOS.map((modulo) => (
                            <button
                                key={modulo.id}
                                type="button"
                                onClick={() => setModuloSeleccionado(modulo.id)}
                                className={`flex items-center px-2 py-1 text-xs font-medium rounded transition-colors ${
                                    moduloSeleccionado === modulo.id
                                        ? 'bg-orange-500 text-white'
                                        : 'bg-white text-gray-600 hover:bg-orange-100'
                                }`}
                            >
                                <i className={`mr-1 fa ${modulo.icon}`}></i>
                                {modulo.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Body compacto */}
                <form onSubmit={handleSubmit} className="p-4 space-y-3">
                    {/* Cédula - Solo para módulo Central */}
                    {moduloSeleccionado === 'Central' && (
                        <div>
                            <label className="block mb-1 text-xs font-medium text-gray-700">
                                Cédula <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="cedula-input"
                                type="text"
                                placeholder="Cédula"
                                value={cedula_referenciapago}
                                onChange={(e) =>
                                    setcedula_referenciapago(e.target.value)
                                }
                                onKeyPress={handleKeyPress}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                            />
                        </div>
                    )}

                    {/* Módulo Central - Campos de banco y referencia siempre visibles */}
                    {moduloSeleccionado === 'Central' && (
                        <>
                            {/* Banco */}
                            <div>
                                <label className="block mb-1 text-xs font-medium text-gray-700">
                                    Banco <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={banco_referenciapago}
                                    onChange={e => {
                                        setdescripcion_referenciapago("");
                                        setbanco_referenciapago(e.target.value);
                                    }}
                                    onKeyPress={handleKeyPress}
                                    className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                        errors.banco ? 'border-red-300' : 'border-gray-200'
                                    }`}
                                >
                                    {bancos
                                        .filter(e => e.value != "0134 BANESCO ARABITO PUNTOS 9935")
                                        .filter(e => e.value != "0134 BANESCO TITANIO")
                                        .map((e, i) => (
                                            <option key={i} value={e.value}>
                                                {e.text}
                                            </option>
                                        ))
                                    }
                                </select>
                                {errors.banco && (
                                    <p className="mt-1 text-xs text-red-600">{errors.banco}</p>
                                )}
                            </div>

                            {/* Referencia */}
                            <div>
                                <label className="block mb-1 text-xs font-medium text-gray-700">
                                    Referencia <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Referencia completa de la transacción..."
                                    value={descripcion_referenciapago}
                                    onChange={e => setdescripcion_referenciapago(
                                        ["ZELLE", "BINANCE", "AirTM"].includes(banco_referenciapago) ? e.target.value : number(e.target.value)
                                    )}
                                    onKeyPress={handleKeyPress}
                                    data-ref-input="true"
                                    className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                        errors.descripcion ? 'border-red-300' : 'border-gray-200'
                                    }`}
                                />
                                {errors.descripcion && (
                                    <p className="mt-1 text-xs text-red-600">{errors.descripcion}</p>
                                )}
                            </div>
                        </>
                    )}

                    {/* Campos para módulos automáticos */}
                    {moduloSeleccionado !== 'Central' && (
                        <>
                            {/* Referencia - siempre visible en módulos automáticos */}
                            <div>
                                <label className="block mb-1 text-xs font-medium text-gray-700">
                                    Referencia <span className="text-red-500">*</span>
                                    <span className="ml-1 text-gray-400">(12 dígitos)</span>
                                </label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        placeholder={moduloConfig.referenciaPlaceholder}
                                        value={descripcion_referenciapago}
                                        onChange={e => {
                                            let value = number(e.target.value);
                                            // Limitar a 12 dígitos máximo
                                            value = value.slice(0, 12);
                                            setdescripcion_referenciapago(value);
                                        }}
                                        onKeyPress={handleKeyPress}
                                        maxLength={12}
                                        data-ref-input="true"
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.descripcion ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    />
                                    {/* Indicador de dígitos restantes */}
                                    {descripcion_referenciapago && descripcion_referenciapago.length < 12 && (
                                        <span className="absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-orange-500 font-medium">
                                            Faltan {12 - descripcion_referenciapago.length} dígitos
                                        </span>
                                    )}
                                    {descripcion_referenciapago && descripcion_referenciapago.length === 12 && (
                                        <span className="absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-green-500 font-medium">
                                            <i className="fa fa-check mr-1"></i>Completo
                                        </span>
                                    )}
                                </div>
                                {/* Mostrar cómo quedará con ceros a la izquierda */}
                                {descripcion_referenciapago && descripcion_referenciapago.length > 0 && descripcion_referenciapago.length < 12 && (
                                    <p className="mt-1 text-xs text-blue-600">
                                        <i className="fa fa-info-circle mr-1"></i>
                                        Se enviará como: <strong>{descripcion_referenciapago.padStart(12, '0')}</strong>
                                    </p>
                                )}
                                {errors.descripcion && (
                                    <p className="mt-1 text-xs text-red-600">{errors.descripcion}</p>
                                )}
                            </div>

                            {/* Teléfono - Solo AutoPagoMovil */}
                            {moduloConfig.showTelefonoPago && (
                                <div>
                                    <label className="block mb-1 text-xs font-medium text-gray-700">
                                        Número de Teléfono <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ej: 04141234567"
                                        value={telefonoPago}
                                        onChange={e => {
                                            const value = e.target.value.replace(/\D/g, '').slice(0, 11);
                                            setTelefonoPago(value);
                                        }}
                                        onKeyPress={handleKeyPress}
                                        maxLength={11}
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.telefonoPago ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        <i className="mr-1 fa fa-info-circle"></i>
                                        Formato: 0414, 0424, 0412, 0416, 0426 + 7 dígitos
                                    </p>
                                    {errors.telefonoPago && (
                                        <p className="mt-1 text-xs text-red-600">{errors.telefonoPago}</p>
                                    )}
                                </div>
                            )}

                            {/* Banco Origen - AutoPagoMovil y AutoInterbancaria */}
                            {moduloConfig.showCodigoBancoOrigen && (
                                <div>
                                    <label className="block mb-1 text-xs font-medium text-gray-700">
                                        Banco Origen <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={codigoBancoOrigen}
                                        onChange={e => setCodigoBancoOrigen(e.target.value)}
                                        onKeyPress={handleKeyPress}
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.codigoBancoOrigen ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    >
                                        <option value="">Seleccionar banco...</option>
                                        <option value="0156">100% Banco - 0156</option> 
                                        <option value="0171">Activo - 0171</option> 
                                        <option value="0916">Agil - 0916</option> 
                                        <option value="0166">Agricola de Venezuela - 0166</option> 
                                        <option value="0172">Bancamiga - 0172</option> 
                                        <option value="0114">BanCaribe - 0114</option> 
                                        <option value="0168">BanCrecer - 0168</option> 
                                        <option value="0134">Banesco - 0134</option> 
                                        <option value="0174">Banplus - 0174</option> 
                                        <option value="0175">BDT - 0175</option> 
                                        <option value="0151">BFC - 0151</option> 
                                        <option value="0116">BOD - 0116</option> 
                                        <option value="0128">Caroni - 0128</option> 
                                        <option value="0163">del Tesoro - 0163</option> 
                                        <option value="0157">DelSur - 0157</option> 
                                        <option value="0176">Espirito Santo - 0176</option> 
                                        <option value="0115">Exterior - 0115</option> 
                                        <option value="0177">Fuerza Armada Nacional - 0177</option> 
                                        <option value="0146">Gente Emprendedora C.A - 0146</option> 
                                        <option value="0003">Industrial de Venezuela - 0003</option> 
                                        <option value="0105">Mercantil - 0105</option> 
                                        <option value="0169">R4 Banco - 0169</option> 
                                        <option value="0905">Mpandco - 0905</option> 
                                        <option value="0914">Mueve - 0914</option> 
                                        <option value="0903">MultiPagos - 0903</option> 
                                        <option value="0191">Nacional de Credito - 0191</option> 
                                        <option value="0138">Plaza - 0138</option> 
                                        <option value="0108">Provincial - 0108</option> 
                                        <option value="0149">Pueblo Soberano - 0149</option> 
                                        <option value="0917">Sodexo - 0917</option> 
                                        <option value="0137">Sofitasa - 0137</option> 
                                        <option value="0104">Venezolano de Credito - 0104</option> 
                                        <option value="0102">Venezuela - 0102</option> 
                                        <option value="0911">Venmo - 0911</option> 
                                    </select>
                                    {errors.codigoBancoOrigen && (
                                        <p className="mt-1 text-xs text-red-600">{errors.codigoBancoOrigen}</p>
                                    )}
                                </div>
                            )}

                            {/* Fecha de Pago - Al final */}
                            {moduloConfig.showFechaPago && (
                                <div>
                                    <label className="block mb-1 text-xs font-medium text-gray-700">
                                        Fecha de Pago <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={fechaPago}
                                        onChange={e => setFechaPago(e.target.value)}
                                        onKeyPress={handleKeyPress}
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.fechaPago ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    />
                                    {errors.fechaPago && (
                                        <p className="mt-1 text-xs text-red-600">{errors.fechaPago}</p>
                                    )}
                                </div>
                            )}
                        </>
                    )}

                    {/* Monto */}
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs font-medium text-gray-700">
                                Monto{" "}
                                {isrefbanbs ? (
                                    <span className="px-2 py-0.5 text-xs font-medium text-blue-700 bg-blue-100 rounded">
                                        Bs
                                    </span>
                                ) : (
                                    <span className="px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded">
                                        $
                                    </span>
                                )}
                                <span className="text-red-500">*</span>
                            </label>
                            {/* Botones para seleccionar monto */}
                            <div className="flex gap-1">
                                <button
                                    type="button"
                                    onClick={() => setmonto_referenciapago(montoTotalPedido.toFixed(2))}
                                    className="px-2 py-0.5 text-[10px] font-medium text-green-700 bg-green-100 rounded hover:bg-green-200 transition-colors"
                                    title="Usar monto total del pedido"
                                >
                                    Total: {montoTotalPedido.toFixed(2)}
                                </button>
                                {sumaReferencias > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setmonto_referenciapago(montoRestante.toFixed(2))}
                                        className="px-2 py-0.5 text-[10px] font-medium text-orange-700 bg-orange-100 rounded hover:bg-orange-200 transition-colors"
                                        title="Usar monto restante (descontando transferencias previas)"
                                    >
                                        Restante: {montoRestante.toFixed(2)}
                                    </button>
                                )}
                            </div>
                        </div>
                        <input
                            type="text"
                            value={monto_referenciapago}
                            onChange={(e) => {
                                // Permitir números negativos: solo dígitos, punto decimal y signo negativo al inicio
                                const value = e.target.value;
                                const filtered = value.replace(/[^0-9.-]/g, '').replace(/(?!^)-/g, '').replace(/(\..*)\./g, '$1');
                                setmonto_referenciapago(filtered);
                            }}
                            onKeyPress={handleKeyPress}
                            className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                errors.monto
                                    ? "border-red-300"
                                    : "border-gray-200"
                            }`}
                        />
                        {errors.monto && (
                            <p className="mt-1 text-xs text-red-600">
                                {errors.monto}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-gray-500">
                            <i className="mr-1 fa fa-info-circle"></i>
                            Máx: Bs {montoMaximoPedido.toFixed(2)} | Ya cargado: Bs {sumaReferencias.toFixed(2)}
                        </p>
                    </div>

                    {/* Resultado de validación */}
                    {moduloSeleccionado === 'AutoValidar' && resultadoValidacion && (
                        <div className={`p-3 rounded-lg border overflow-hidden ${
                            resultadoValidacion.success 
                                ? 'bg-green-50 border-green-200' 
                                : resultadoValidacion.montoDiferente
                                    ? 'bg-amber-50 border-amber-300'
                                    : 'bg-red-50 border-red-200'
                        }`}>
                            <div className="flex items-start">
                                <i className={`mt-0.5 mr-2 fa ${
                                    resultadoValidacion.success 
                                        ? 'fa-check-circle text-green-500' 
                                        : resultadoValidacion.montoDiferente
                                            ? 'fa-exclamation-triangle text-amber-500'
                                            : 'fa-times-circle text-red-500'
                                } text-base flex-shrink-0`}></i>
                                <div className="flex-1 min-w-0">
                                    <p className={`font-medium text-sm ${
                                        resultadoValidacion.success 
                                            ? 'text-green-800' 
                                            : resultadoValidacion.montoDiferente
                                                ? 'text-amber-800'
                                                : 'text-red-800'
                                    }`}>
                                        {resultadoValidacion.success 
                                            ? '¡Transferencia Validada!' 
                                            : resultadoValidacion.montoDiferente
                                                ? '¡Referencia Encontrada - Monto Diferente!'
                                                : 'Validación Fallida'}
                                    </p>
                                    <p className={`mt-1 text-xs break-words ${
                                        resultadoValidacion.success 
                                            ? 'text-green-700' 
                                            : resultadoValidacion.montoDiferente
                                                ? 'text-amber-700'
                                                : 'text-red-700'
                                    }`}>
                                        {resultadoValidacion.message}
                                    </p>
                                    {/* Detalles adicionales para monto diferente */}
                                    {resultadoValidacion.montoDiferente && resultadoValidacion.data && (
                                        <div className="mt-2 p-2 bg-amber-100 rounded text-xs text-amber-800">
                                            <p><strong>Referencia encontrada:</strong> {resultadoValidacion.data.referencia_encontrada}</p>
                                            <p><strong>Monto ingresado:</strong> Bs {parseFloat(resultadoValidacion.data.monto_ingresado).toFixed(2)}</p>
                                            <p><strong>Monto en banco:</strong> Bs {parseFloat(resultadoValidacion.data.monto_banco).toFixed(2)}</p>
                                            <p className="mt-1 font-medium text-amber-900">
                                                Diferencia: Bs {Math.abs(resultadoValidacion.data.monto_banco - resultadoValidacion.data.monto_ingresado).toFixed(2)}
                                            </p>
                                        </div>
                                    )}
                                    {resultadoValidacion.data && resultadoValidacion.data.modalidad && (
                                        <p className="mt-1 text-xs text-gray-600">
                                            <strong>Modalidad:</strong> {
                                                resultadoValidacion.data.modalidad === 'pagomovil' ? 'Pago Móvil' :
                                                resultadoValidacion.data.modalidad === 'interbancaria' ? 'Interbancaria' :
                                                resultadoValidacion.data.modalidad === 'banesco' ? 'Banesco-Banesco' :
                                                resultadoValidacion.data.modalidad
                                            }
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Footer compacto */}
                    <div className="pt-3 border-t border-gray-100">
                        {moduloSeleccionado === 'AutoValidar' ? (
                            /* Botón grande para AutoValidar */
                            <button
                                type="button"
                                onClick={handleAutoValidar}
                                disabled={autoValidando || resultadoValidacion?.success}
                                className={`w-full flex items-center justify-center px-6 py-4 text-base font-semibold text-white transition-all rounded-lg shadow-lg ${
                                    resultadoValidacion?.success
                                        ? 'bg-green-500 cursor-not-allowed'
                                        : autoValidando
                                            ? 'bg-blue-400 cursor-wait'
                                            : 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl'
                                }`}
                            >
                                {autoValidando ? (
                                    <>
                                        <i className="mr-3 text-xl fa fa-spinner fa-spin"></i>
                                        <span>Validando transferencia...</span>
                                    </>
                                ) : resultadoValidacion?.success ? (
                                    <>
                                        <i className="mr-3 text-xl fa fa-check-circle"></i>
                                        <span>¡Transferencia Aprobada!</span>
                                    </>
                                ) : (
                                    <>
                                        <i className="mr-3 text-xl fa fa-shield"></i>
                                        <span>Validar y Autovalidar Transferencia</span>
                                    </>
                                )}
                            </button>
                        ) : (
                            /* Botones normales para Central */
                            <div className="flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={handleClose}
                                    className="px-3 py-2 text-xs font-medium text-gray-600 transition-colors bg-gray-100 rounded hover:bg-gray-200"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="flex items-center px-3 py-2 space-x-1 text-xs font-medium text-white transition-colors bg-orange-500 rounded hover:bg-orange-600 disabled:opacity-50"
                                >
                                    {isSubmitting ? (
                                        <>
                                            <i className="fa fa-spinner fa-spin"></i>
                                            <span>Guardando...</span>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa fa-check"></i>
                                            <span>Guardar</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}