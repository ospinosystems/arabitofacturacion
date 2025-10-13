import { useState, useEffect } from "react";
import { useHotkeys } from "react-hotkeys-hook";

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
}) {
    const [isrefbanbs, setisrefbanbs] = useState(true);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showBancoReferencia, setShowBancoReferencia] = useState(false);

    // Validar formulario
    const validateForm = () => {
        const newErrors = {};

        // Validar banco y referencia solo si están visibles
        if (showBancoReferencia) {
            if (!descripcion_referenciapago || descripcion_referenciapago.trim() === '') {
                newErrors.descripcion = 'La referencia es obligatoria';
            }

            if (!banco_referenciapago || banco_referenciapago === '') {
                newErrors.banco = 'Debe seleccionar un banco';
            }
        }

        if (!monto_referenciapago || monto_referenciapago == 0) {
            newErrors.monto = 'El monto debe ser mayor a 0';
        }

        if (!tipo_referenciapago || tipo_referenciapago === '') {
            newErrors.tipo = 'Debe seleccionar el tipo de pago';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Manejar envío del formulario
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);
        try {
            await addRefPago("enviar");
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

    // Efecto para calcular monto según banco
    useEffect(() => {
        if (
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
    }, [banco_referenciapago, transferencia, dolar]);

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
        if (errors.tipo && tipo_referenciapago) {
            setErrors(prev => ({ ...prev, tipo: null }));
        }
    }, [descripcion_referenciapago, banco_referenciapago, monto_referenciapago, tipo_referenciapago]);

    // Hotkey para cerrar con ESC
    useHotkeys('esc', handleClose, { enableOnTags: ['INPUT', 'SELECT'] });

    // Auto-focus en el primer input al abrir
    useEffect(() => {
        const firstInput = document.querySelector('#cedula-input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }, []);

    // Cargar monto y tipo cuando se abra el modal
    useEffect(() => {
        if (montoTraido && montoTraido > 0) {
            setmonto_referenciapago(montoTraido);
        }
        if (tipoTraido) {
            settipo_referenciapago(tipoTraido);
        }
    }, [montoTraido, tipoTraido]);

    // Limpiar campos de banco y referencia cuando se ocultan
    useEffect(() => {
        if (!showBancoReferencia) {
            setdescripcion_referenciapago("");
            setbanco_referenciapago("");
            // Limpiar errores relacionados
            setErrors(prev => ({
                ...prev,
                descripcion: null,
                banco: null
            }));
        }
    }, [showBancoReferencia]);

    return (
        <>
            {/* Overlay sutil que no bloquea la vista */}
            <div
                className="fixed inset-0 z-40 transition-opacity bg-black opacity-60 backdrop-blur-[2px]"
                onClick={handleClose}
            ></div>

            {/* Modal flotante posicionado */}
            <div className="fixed z-50 w-full max-w-sm overflow-hidden transform -translate-x-1/2 -translate-y-1/2 bg-white border border-gray-200 rounded-lg shadow-xl top-1/2 left-1/2">
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

                    {/* Botón para mostrar/ocultar campos de banco y referencia */}
                    <div className="flex justify-center">
                        <button
                            type="button"
                            onClick={() => setShowBancoReferencia(!showBancoReferencia)}
                            className="flex items-center px-3 py-1 text-xs font-medium text-gray-600 transition-colors bg-gray-100 rounded hover:bg-gray-200"
                        >
                            <i className={`mr-1 fa ${showBancoReferencia ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                            {showBancoReferencia ? 'Ocultar' : 'Mostrar'} Banco y Referencia
                        </button>
                    </div>

                    {/* Campos de Banco y Referencia - Ocultos por defecto */}
                    {showBancoReferencia && (
                        <>
                            {/* Banco */}
                            <div>
                                <label className="block mb-1 text-xs font-medium text-gray-700">
                                    Banco
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
                                    Referencia
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

                    {/* Referencia */}
                    {/*   <div>
                            <label className="block mb-1 text-sm font-medium text-gray-700">
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
                                className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 ${
                                    errors.descripcion ? 'border-red-300' : 'border-gray-300'
                                }`}
                            />
                            {errors.descripcion && (
                                <p className="mt-1 text-sm text-red-600">{errors.descripcion}</p>
                            )}
                        </div> */}

                    {/* Banco */}
                    {/* <div>
                            <label className="block mb-1 text-sm font-medium text-gray-700">
                                Banco <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={banco_referenciapago}
                                onChange={e => {
                                    setdescripcion_referenciapago("");
                                    setbanco_referenciapago(e.target.value);
                                }}
                                onKeyPress={handleKeyPress}
                                className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 ${
                                    errors.banco ? 'border-red-300' : 'border-gray-300'
                                }`}
                            >
                                <option value="">-- Seleccione un banco --</option>
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
                                <p className="mt-1 text-sm text-red-600">{errors.banco}</p>
                            )}
                    </div> */}

                    {/* Monto */}
                    <div>
                        <label className="block mb-1 text-xs font-medium text-gray-700">
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
                    </div>

                    {/* Tipo de Pago */}
                    <div>
                        <label className="block mb-1 text-xs font-medium text-gray-700">
                            Tipo de Pago <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={tipo_referenciapago}
                            onChange={(e) =>
                                settipo_referenciapago(e.target.value)
                            }
                            onKeyPress={handleKeyPress}
                            className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400 ${
                                errors.tipo
                                    ? "border-red-300"
                                    : "border-gray-200"
                            }`}
                        >
                            <option value="">Seleccionar tipo</option>
                            <option value="1">Transferencia</option>
                            {/* <option value="2">Débito</option> */}
                            {/* <option value="5">BioPago</option> */}
                        </select>
                        {errors.tipo && (
                            <p className="mt-1 text-xs text-red-600">
                                {errors.tipo}
                            </p>
                        )}
                    </div>

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