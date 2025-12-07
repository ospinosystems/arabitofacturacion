import { useState, useEffect } from "react";
import { useHotkeys } from "react-hotkeys-hook";

const MODULOS = [
    { id: 'Central', label: 'Central', icon: 'fa-building' },
    { id: 'AutoPagoMovil', label: 'Pago Móvil', icon: 'fa-mobile' },
    { id: 'AutoBanescoBanesco', label: 'Banesco-Banesco', icon: 'fa-university' },
    { id: 'AutoInterbancaria', label: 'Interbancaria', icon: 'fa-exchange' },
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

    // Validar formato de teléfono: 58 + código (sin 0) + 7 dígitos = 12 dígitos
    const validarTelefono = (telefono) => {
        // Códigos válidos: 412, 414, 416, 424, 426, 422
        const regex = /^58(412|414|416|424|426|422)\d{7}$/;
        return regex.test(telefono);
    };

    // Validar formato de monto: xxxxx.xx
    const validarFormatoMonto = (monto) => {
        const regex = /^\d+(\.\d{1,2})?$/;
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
                    referenciaMaxLength: null,
                    referenciaPlaceholder: 'Referencia completa de la transacción...',
                    showFechaPago: false,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: false,
                };
            case 'AutoPagoMovil':
                return {
                    showAllFields: false,
                    showBanco: false,
                    showReferencia: true,
                    referenciaMaxLength: 6,
                    referenciaPlaceholder: 'Últimos 6 dígitos numéricos...',
                    showFechaPago: true,
                    showTelefonoPago: true,
                    showCodigoBancoOrigen: true,
                };
            case 'AutoBanescoBanesco':
                return {
                    showAllFields: false,
                    showBanco: false,
                    showReferencia: true,
                    referenciaMaxLength: 12,
                    referenciaPlaceholder: 'Últimos 12 dígitos numéricos...',
                    showFechaPago: false,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: false,
                };
            case 'AutoInterbancaria':
                return {
                    showAllFields: false,
                    showBanco: false,
                    showReferencia: true,
                    referenciaMaxLength: 6,
                    referenciaPlaceholder: 'Últimos 6 dígitos numéricos...',
                    showFechaPago: true,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: true,
                };
            default:
                return {
                    showAllFields: true,
                    showBanco: true,
                    showReferencia: true,
                    referenciaMaxLength: null,
                    referenciaPlaceholder: 'Referencia completa de la transacción...',
                    showFechaPago: false,
                    showTelefonoPago: false,
                    showCodigoBancoOrigen: false,
                };
        }
    };

    const moduloConfig = getModuloConfig();

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
        } else {
            // Validaciones para módulos automáticos
            if (!descripcion_referenciapago || descripcion_referenciapago.trim() === '') {
                newErrors.descripcion = 'La referencia es obligatoria';
            } else if (moduloConfig.referenciaMaxLength && descripcion_referenciapago.length !== moduloConfig.referenciaMaxLength) {
                newErrors.descripcion = `La referencia debe tener ${moduloConfig.referenciaMaxLength} dígitos`;
            }

            if (moduloConfig.showFechaPago && !fechaPago) {
                newErrors.fechaPago = 'La fecha de pago es obligatoria';
            }

            if (moduloConfig.showTelefonoPago) {
                if (!telefonoPago) {
                    newErrors.telefonoPago = 'El teléfono es obligatorio';
                } else if (!validarTelefono(telefonoPago)) {
                    newErrors.telefonoPago = 'Formato inválido. Debe ser: 58 + código (416,426,414,424,412,422) + 7 dígitos. Ej: 584142440203';
                }
            }

            if (moduloConfig.showCodigoBancoOrigen && !codigoBancoOrigen) {
                newErrors.codigoBancoOrigen = 'El código de banco origen es obligatorio';
            }
        }

        if (!monto_referenciapago || monto_referenciapago == 0) {
            newErrors.monto = 'El monto debe ser mayor a 0';
        } else if (!validarFormatoMonto(monto_referenciapago)) {
            newErrors.monto = 'El monto debe tener formato válido (ej: 1234.56)';
        } else if (parseFloat(monto_referenciapago) > montoMaximoPedido) {
            newErrors.monto = `El monto no puede ser mayor a Bs ${montoMaximoPedido.toFixed(2)} (monto del pedido)`;
        }

        // Validar monto real si se proporciona
        if (montoReal) {
            if (!validarFormatoMonto(montoReal)) {
                newErrors.montoReal = 'El monto real debe tener formato válido (ej: 1234.56)';
            } else {
                const montoRealNum = parseFloat(montoReal);
                const montoNum = parseFloat(monto_referenciapago);
                const limiteMaximo = montoNum * 1.05; // 5% más del monto

                if (montoRealNum <= montoNum) {
                    newErrors.montoReal = 'El monto real debe ser mayor al monto del pedido';
                } else if (montoRealNum > limiteMaximo) {
                    newErrors.montoReal = `El monto real no puede exceder el 5% del monto (máx: Bs ${limiteMaximo.toFixed(2)})`;
                }
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Mapeo de módulo a categoría
    const getCategoriaFromModulo = () => {
        switch (moduloSeleccionado) {
            case 'Central': return 'central';
            case 'AutoPagoMovil': return 'pagomovil';
            case 'AutoBanescoBanesco': return 'banesco';
            case 'AutoInterbancaria': return 'interbancaria';
            default: return 'central';
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
                    datosAdicionales.telefono_pago = telefonoPago;
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
            handleSubmit(e);
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
                                        banco_referenciapago == "ZELLE" ? e.target.value : number(e.target.value)
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
                                    {moduloConfig.referenciaMaxLength && (
                                        <span className="ml-1 text-gray-400">({moduloConfig.referenciaMaxLength} dígitos)</span>
                                    )}
                                </label>
                                <input
                                    type="text"
                                    placeholder={moduloConfig.referenciaPlaceholder}
                                    value={descripcion_referenciapago}
                                    onChange={e => {
                                        let value = number(e.target.value);
                                        if (moduloConfig.referenciaMaxLength) {
                                            value = value.slice(0, moduloConfig.referenciaMaxLength);
                                        }
                                        setdescripcion_referenciapago(value);
                                    }}
                                    onKeyPress={handleKeyPress}
                                    maxLength={moduloConfig.referenciaMaxLength || undefined}
                                    data-ref-input="true"
                                    className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                        errors.descripcion ? 'border-red-300' : 'border-gray-200'
                                    }`}
                                />
                                {errors.descripcion && (
                                    <p className="mt-1 text-xs text-red-600">{errors.descripcion}</p>
                                )}
                            </div>

                            {/* Fecha de Pago - AutoPagoMovil y AutoInterbancaria */}
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

                            {/* Teléfono - Solo AutoPagoMovil */}
                            {moduloConfig.showTelefonoPago && (
                                <div>
                                    <label className="block mb-1 text-xs font-medium text-gray-700">
                                        Número de Teléfono <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ej: 584142440203"
                                        value={telefonoPago}
                                        onChange={e => setTelefonoPago(number(e.target.value))}
                                        onKeyPress={handleKeyPress}
                                        maxLength={12}
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.telefonoPago ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        <i className="mr-1 fa fa-info-circle"></i>
                                        Formato: 58 + código sin 0 (416,426,414,424,412,422) + 7 dígitos
                                    </p>
                                    {errors.telefonoPago && (
                                        <p className="mt-1 text-xs text-red-600">{errors.telefonoPago}</p>
                                    )}
                                </div>
                            )}

                            {/* Código Banco Origen - AutoPagoMovil y AutoInterbancaria */}
                            {moduloConfig.showCodigoBancoOrigen && (
                                <div>
                                    <label className="block mb-1 text-xs font-medium text-gray-700">
                                        Código Banco Origen <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ej: 0102"
                                        value={codigoBancoOrigen}
                                        onChange={e => setCodigoBancoOrigen(number(e.target.value))}
                                        onKeyPress={handleKeyPress}
                                        maxLength={4}
                                        className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                            errors.codigoBancoOrigen ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                    />
                                    {errors.codigoBancoOrigen && (
                                        <p className="mt-1 text-xs text-red-600">{errors.codigoBancoOrigen}</p>
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
                            onChange={(e) =>
                                setmonto_referenciapago(number(e.target.value))
                            }
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

                    {/* Monto Real - Solo si el cliente transfirió más */}
                    {moduloSeleccionado !== 'Central' && (
                        <div>
                            <label className="block mb-1 text-xs font-medium text-gray-700">
                                Monto Real Transferido{" "}
                                <span className="px-2 py-0.5 text-xs font-medium text-yellow-700 bg-yellow-100 rounded">
                                    Opcional
                                </span>
                            </label>
                            <input
                                type="text"
                                placeholder="Solo si el cliente transfirió más de lo debido"
                                value={montoReal}
                                onChange={(e) => setMontoReal(e.target.value.replace(/[^0-9.]/g, ''))}
                                onKeyPress={handleKeyPress}
                                className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                    errors.montoReal ? 'border-red-300' : 'border-gray-200'
                                }`}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                <i className="mr-1 fa fa-exclamation-triangle text-yellow-500"></i>
                                <strong>Si el cliente transfirió más, ingrese aquí el monto total que cayó al banco.</strong> El sistema registrará el monto correcto y el sobrante. Máx. permitido: 5% adicional (Bs {(parseFloat(monto_referenciapago || 0) * 1.05).toFixed(2)})
                            </p>
                            {errors.montoReal && (
                                <p className="mt-1 text-xs text-red-600">{errors.montoReal}</p>
                            )}
                        </div>
                    )}

                    {/* Footer compacto */}
                    <div className="flex items-center justify-end pt-3 space-x-2 border-t border-gray-100">
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
                </form>
            </div>
        </>
    );
}