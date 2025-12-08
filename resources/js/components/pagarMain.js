import { useEffect, useState, useRef } from "react";
import { useHotkeys } from "react-hotkeys-hook";
import ListProductosInterno from "./listProductosInterno";

import db from "../database/database";

export default function PagarMain({
    qProductosMain,
    setQProductosMain,
    setLastDbRequest,
    lastDbRequest,
    openValidationTarea,

    num,
    setNum,
    ModaladdproductocarritoToggle,
    productoSelectinternouno,
    devolucionTipo,
    setdevolucionTipo,
    cantidad,
    addCarritoRequestInterno,
    setproductoSelectinternouno,
    setinputqinterno,
    inputqinterno,
    refaddfast,
    tbodyproducInterref,
    productos,
    countListInter,
    setProductoCarritoInterno,
    setCountListInter,
    toggleModalProductos,
    inputCantidadCarritoref,
    setCantidad,
    getProductos,
    permisoExecuteEnter,
    devolucionMotivo,
    setdevolucionMotivo,
    devolucion_cantidad_salida,
    setdevolucion_cantidad_salida,
    devolucion_motivo_salida,
    setdevolucion_motivo_salida,
    devolucion_ci_cajero,
    setdevolucion_ci_cajero,
    devolucion_ci_autorizo,
    setdevolucion_ci_autorizo,
    devolucion_dias_desdecompra,
    setdevolucion_dias_desdecompra,
    devolucion_ci_cliente,
    setdevolucion_ci_cliente,
    devolucion_telefono_cliente,
    setdevolucion_telefono_cliente,
    devolucion_nombre_cliente,
    setdevolucion_nombre_cliente,
    devolucion_nombre_cajero,
    setdevolucion_nombre_cajero,
    devolucion_nombre_autorizo,
    setdevolucion_nombre_autorizo,
    devolucion_trajo_factura,
    setdevolucion_trajo_factura,
    devolucion_motivonotrajofact,
    setdevolucion_motivonotrajofact,

    getPedidosFast,
    addNewPedido,
    addRetencionesPago,
    delRetencionPago,
    user,
    view,
    changeEntregado,
    setPagoPedido,
    viewconfigcredito,
    setPrecioAlternoCarrito,
    addRefPago,
    delRefPago,
    refPago,
    pedidosFast,
    pedidoData,
    getPedido,
    notificar,
    debito,
    setDebito,
    debitoRef,
    setDebitoRef,
    debitoRefError,
    setDebitoRefError,
    efectivo,
    setEfectivo,
    efectivo_bs,
    setEfectivo_bs,
    efectivo_dolar,
    setEfectivo_dolar,
    efectivo_peso,
    setEfectivo_peso,
    transferencia,
    setTransferencia,
    credito,
    setCredito,
    vuelto,
    setVuelto,
    number,
    delItemPedido,
    setDescuento,
    setDescuentoUnitario,
    setDescuentoTotal,
    setCantidadCarrito,
    toggleAddPersona,
    setToggleAddPersona,
    toggleImprimirTicket,
    sendReciboFiscal,
    sendNotaCredito,
    del_pedido,
    facturar_pedido,
    inputmodaladdpersonacarritoref,
    entregarVuelto,
    setclienteInpnombre,
    setclienteInptelefono,
    setclienteInpdireccion,
    viewReportPedido,
    autoCorrector,
    setautoCorrector,
    getDebito,
    getCredito,
    getTransferencia,
    getEfectivo,
    onClickEditPedido,
    setBiopago,
    biopago,
    getBio,
    facturar_e_imprimir,
    moneda,
    dolar,
    peso,
    auth,
    togglereferenciapago,
    setToggleAddPersonaFun,
    transferirpedidoa,
    settransferirpedidoa,
    sucursalesCentral,
    setexportpedido,
    getSucursales,
    setView,
    setselectprinter,
    setmonedaToPrint,
    selectprinter,
    monedaToPrint,
    setGastoOperativo,

    changeOnlyInputBulto,
    setchangeOnlyInputBultoFun,
    printBultos,
    showXBulto,
    setshowXBulto,

    cedula_referenciapago,
    setcedula_referenciapago,
    telefono_referenciapago,
    settelefono_referenciapago,
    // Props para ordenamiento
    orderColumn,
    setOrderColumn,
    orderBy,
    setOrderBy,
}) {
    const [recibido_dolar, setrecibido_dolar] = useState("");
    const [recibido_bs, setrecibido_bs] = useState("");
    const [recibido_cop, setrecibido_cop] = useState("");
    const [cambio_dolar, setcambio_dolar] = useState("");

    // Estado para mostrar/ocultar menú flotante
    const [showFloatingMenu, setShowFloatingMenu] = useState(true);
    const [lastScrollY, setLastScrollY] = useState(0);
    const [showVueltosSection, setShowVueltosSection] = useState(false);
    const [showTransferirSection, setShowTransferirSection] = useState(false);
    const [showHeaderAndMenu, setShowHeaderAndMenu] = useState(true);
    const [cambio_bs, setcambio_bs] = useState("");
    const [cambio_cop, setcambio_cop] = useState("");

    const [cambio_tot_result, setcambio_tot_result] = useState("");
    const [recibido_tot, setrecibido_tot] = useState("");

    // Refs para los inputs de pago
    const debitoInputRef = useRef(null);
    const efectivoInputRef = useRef(null);
    const transferenciaInputRef = useRef(null);
    const biopagoInputRef = useRef(null);
    const creditoInputRef = useRef(null);

    // Estados para modal de pedido original (devoluciones)
    const [showModalPedidoOriginal, setShowModalPedidoOriginal] = useState(false);
    const [pedidoOriginalInput, setPedidoOriginalInput] = useState("");
    const [itemsDisponiblesDevolucion, setItemsDisponiblesDevolucion] = useState([]);
    const [pedidoOriginalAsignado, setPedidoOriginalAsignado] = useState(null);
    const pedidoOriginalInputRef = useRef(null);
    
    // Estado para modal de info de factura original
    const [showModalInfoFacturaOriginal, setShowModalInfoFacturaOriginal] = useState(false);
    const [itemsFacturaOriginal, setItemsFacturaOriginal] = useState([]);
    const [loadingFacturaOriginal, setLoadingFacturaOriginal] = useState(false);

    // Estados para mostrar/ocultar efectivo dividido por moneda
    const [showEfectivoDetalle, setShowEfectivoDetalle] = useState(false);
    const [showEfectivoPeso, setShowEfectivoPeso] = useState(false);
    
    // Ref para input de referencia del débito
    const debitoRefInputRef = useRef(null);
    
    // Configuración de métodos de pago (cambiar aquí para mostrar/ocultar)
    const SHOW_BIOPAGO = false; // Cambiar a true para mostrar Biopago
    
    // Estado para mostrar/ocultar Crédito (toggle desde interfaz)
    const [showCredito, setShowCredito] = useState(false);

    // Estado para rastrear la última función de pago llamada (para doble clic)
    const [lastPaymentMethodCalled, setLastPaymentMethodCalled] = useState(null);

    // Estados para calculadora/vueltos
    const [showCalculadora, setShowCalculadora] = useState(false);
    const [calcInput, setCalcInput] = useState("");
    const [calcMoneda, setCalcMoneda] = useState("usd"); // usd, bs, cop
    // Sección 1: Lo que pagó el cliente (por método)
    const [calcPagoEfectivoUsd, setCalcPagoEfectivoUsd] = useState("");
    const [calcPagoEfectivoBs, setCalcPagoEfectivoBs] = useState("");
    const [calcPagoEfectivoCop, setCalcPagoEfectivoCop] = useState("");
    const [calcPagoDebito, setCalcPagoDebito] = useState("");
    const [calcPagoTransferencia, setCalcPagoTransferencia] = useState("");
    // Campo activo para agregar desde calculadora
    const [calcCampoActivo, setCalcCampoActivo] = useState(null);
    // Mostrar mini calculadora flotante
    const [showMiniCalc, setShowMiniCalc] = useState(false);
    // Vuelto combinado (cuánto dar en cada moneda)
    const [calcVueltoUsd, setCalcVueltoUsd] = useState("");
    const [calcVueltoBs, setCalcVueltoBs] = useState("");
    const [calcVueltoCop, setCalcVueltoCop] = useState("");
    const calculadoraRef = useRef(null);
    const miniCalcRef = useRef(null);
    const calculadoraBtnRef = useRef(null);

    // Handler para ejecutar setPagoPedido al presionar ENTER en inputs de pago
    const handlePaymentInputKeyDown = (e) => {
        if (e.key === 'Enter' && !e.ctrlKey && !e.shiftKey && !e.altKey) {
            e.preventDefault();
            e.stopPropagation();
            setPagoPedido();
        }
    };

    // Funciones de calculadora/vueltos
    const getTasaBsCalc = () => pedidoData?.items?.[0]?.tasa || dolar || 1;
    const getTasaCopCalc = () => pedidoData?.items?.[0]?.tasa_cop || peso || 1;
    
    const calcConvertir = (valor, desde) => {
        const v = parseFloat(valor) || 0;
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        if (desde === "usd") return { usd: v, bs: v * tasaBs, cop: v * tasaCop };
        if (desde === "bs") return { usd: v / tasaBs, bs: v, cop: (v / tasaBs) * tasaCop };
        if (desde === "cop") return { usd: v / tasaCop, bs: (v / tasaCop) * tasaBs, cop: v };
        return { usd: 0, bs: 0, cop: 0 };
    };

    // Calcular total pagado por el cliente (en USD)
    const calcTotalPagado = () => {
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        const efectivoUsd = parseFloat(calcPagoEfectivoUsd) || 0;
        const efectivoBs = parseFloat(calcPagoEfectivoBs) || 0;
        const efectivoCop = parseFloat(calcPagoEfectivoCop) || 0;
        const debitoBs = parseFloat(calcPagoDebito) || 0;
        const transferenciaUsd = parseFloat(calcPagoTransferencia) || 0;
        return efectivoUsd + (efectivoBs / tasaBs) + (efectivoCop / tasaCop) + (debitoBs / tasaBs) + transferenciaUsd;
    };

    // Calcular diferencia (positivo = vuelto a dar, negativo = falta por pagar)
    const calcDiferencia = () => {
        const totalPedido = parseFloat(pedidoData?.clean_total) || 0;
        return calcTotalPagado() - totalPedido;
    };

    // Calcular cuánto del vuelto ya está asignado (en USD)
    const calcVueltoAsignado = () => {
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        const vueltoUsd = parseFloat(calcVueltoUsd) || 0;
        const vueltoBs = parseFloat(calcVueltoBs) || 0;
        const vueltoCop = parseFloat(calcVueltoCop) || 0;
        return vueltoUsd + (vueltoBs / tasaBs) + (vueltoCop / tasaCop);
    };

    // Calcular cuánto falta por asignar del vuelto (en USD)
    const calcVueltoPendiente = () => {
        const diferencia = calcDiferencia();
        if (diferencia <= 0) return 0;
        return Math.max(0, diferencia - calcVueltoAsignado());
    };

    // Cuando cambia el total pagado, poner el vuelto por defecto en dólares
    useEffect(() => {
        const diferencia = calcTotalPagado() - (parseFloat(pedidoData?.clean_total) || 0);
        if (diferencia > 0) {
            setCalcVueltoUsd(diferencia.toFixed(2));
            setCalcVueltoBs("");
            setCalcVueltoCop("");
        } else {
            setCalcVueltoUsd("");
            setCalcVueltoBs("");
            setCalcVueltoCop("");
        }
    }, [calcPagoEfectivoUsd, calcPagoEfectivoBs, calcPagoEfectivoCop, calcPagoDebito, calcPagoTransferencia]);

    // Auto-calcular el resto en una moneda específica (pone todo en esa moneda)
    const calcAutoVuelto = (moneda) => {
        const diferencia = calcDiferencia();
        if (diferencia <= 0) return;
        
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        
        // Poner todo en la moneda seleccionada y limpiar las demás
        if (moneda === "usd") {
            setCalcVueltoUsd(diferencia.toFixed(2));
            setCalcVueltoBs("");
            setCalcVueltoCop("");
        } else if (moneda === "bs") {
            setCalcVueltoUsd("");
            setCalcVueltoBs((diferencia * tasaBs).toFixed(2));
            setCalcVueltoCop("");
        } else if (moneda === "cop") {
            setCalcVueltoUsd("");
            setCalcVueltoBs("");
            setCalcVueltoCop((diferencia * tasaCop).toFixed(0));
        }
    };

    // Manejar cambio en input de vuelto (resta de los otros campos)
    const handleVueltoChange = (moneda, valor) => {
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        const diferencia = calcDiferencia();
        if (diferencia <= 0) return;
        
        // Convertir el nuevo valor a USD
        let nuevoValorUsd = 0;
        if (moneda === "usd") {
            nuevoValorUsd = parseFloat(valor) || 0;
            setCalcVueltoUsd(valor);
        } else if (moneda === "bs") {
            nuevoValorUsd = (parseFloat(valor) || 0) / tasaBs;
            setCalcVueltoBs(valor);
        } else if (moneda === "cop") {
            nuevoValorUsd = (parseFloat(valor) || 0) / tasaCop;
            setCalcVueltoCop(valor);
        }
        
        // Calcular cuánto queda disponible después de este cambio
        const otrosUsd = moneda === "usd" ? 0 : (parseFloat(calcVueltoUsd) || 0);
        const otrosBsUsd = moneda === "bs" ? 0 : ((parseFloat(calcVueltoBs) || 0) / tasaBs);
        const otrosCopUsd = moneda === "cop" ? 0 : ((parseFloat(calcVueltoCop) || 0) / tasaCop);
        
        const totalOtros = otrosUsd + otrosBsUsd + otrosCopUsd;
        const disponible = diferencia - nuevoValorUsd;
        
        // Si el nuevo valor excede lo disponible, ajustar los otros campos
        if (nuevoValorUsd > diferencia) {
            // Limitar al máximo disponible
            if (moneda === "usd") {
                setCalcVueltoUsd(diferencia.toFixed(2));
            } else if (moneda === "bs") {
                setCalcVueltoBs((diferencia * tasaBs).toFixed(2));
            } else if (moneda === "cop") {
                setCalcVueltoCop((diferencia * tasaCop).toFixed(0));
            }
            // Limpiar los otros
            if (moneda !== "usd") setCalcVueltoUsd("");
            if (moneda !== "bs") setCalcVueltoBs("");
            if (moneda !== "cop") setCalcVueltoCop("");
        } else if (totalOtros > disponible && disponible >= 0) {
            // Hay que reducir los otros campos proporcionalmente
            const factor = disponible / totalOtros;
            if (moneda !== "usd" && otrosUsd > 0) {
                setCalcVueltoUsd((otrosUsd * factor).toFixed(2));
            }
            if (moneda !== "bs" && otrosBsUsd > 0) {
                setCalcVueltoBs((otrosBsUsd * factor * tasaBs).toFixed(2));
            }
            if (moneda !== "cop" && otrosCopUsd > 0) {
                setCalcVueltoCop((otrosCopUsd * factor * tasaCop).toFixed(0));
            }
        }
    };

    const calcEvaluar = () => {
        try {
            // Evaluar expresión matemática simple (solo +, -, *, /)
            const expr = calcInput.replace(/[^0-9+\-*/.]/g, '');
            if (!expr) return 0;
            // eslint-disable-next-line no-eval
            return eval(expr) || 0;
        } catch { return 0; }
    };

    const calcAgregarDigito = (d) => setCalcInput(prev => prev + d);
    const calcBorrar = () => setCalcInput(prev => prev.slice(0, -1));
    const calcLimpiarTodo = () => { 
        setCalcInput(""); 
        setCalcPagoEfectivoUsd(""); 
        setCalcPagoEfectivoBs(""); 
        setCalcPagoEfectivoCop(""); 
        setCalcPagoDebito(""); 
        setCalcPagoTransferencia(""); 
    };
    
    // Obtener valor del campo activo
    const getCalcCampoValor = () => {
        const valores = {
            efectivo_usd: calcPagoEfectivoUsd,
            efectivo_bs: calcPagoEfectivoBs,
            efectivo_cop: calcPagoEfectivoCop,
            debito: calcPagoDebito,
            transferencia: calcPagoTransferencia,
        };
        return parseFloat(valores[calcCampoActivo]) || 0;
    };

    // Obtener moneda del campo activo
    const getCalcCampoMoneda = () => {
        const monedas = {
            efectivo_usd: "usd",
            efectivo_bs: "bs",
            efectivo_cop: "cop",
            debito: "bs",
            transferencia: "usd",
        };
        return monedas[calcCampoActivo] || "usd";
    };

    // Agregar monto al campo activo
    const calcAplicarAlCampo = () => {
        const resultado = calcEvaluar();
        if (!resultado || !calcCampoActivo) return;
        
        const setters = {
            efectivo_usd: setCalcPagoEfectivoUsd,
            efectivo_bs: setCalcPagoEfectivoBs,
            efectivo_cop: setCalcPagoEfectivoCop,
            debito: setCalcPagoDebito,
            transferencia: setCalcPagoTransferencia,
        };
        
        const decimales = calcCampoActivo === "efectivo_cop" ? 0 : 2;
        const setter = setters[calcCampoActivo];
        if (setter) {
            setter(prev => ((parseFloat(prev) || 0) + resultado).toFixed(decimales));
        }
        setCalcInput("");
    };

    // Manejar foco en input de calculadora
    const handleCalcInputFocus = (campo) => {
        setCalcCampoActivo(campo);
        setShowMiniCalc(true);
        setCalcInput("");
    };

    // Manejar blur en input de calculadora
    const handleCalcInputBlur = (e) => {
        // No cerrar si el clic fue en la mini calculadora
        setTimeout(() => {
            if (miniCalcRef.current && !miniCalcRef.current.contains(document.activeElement)) {
                setShowMiniCalc(false);
            }
        }, 150);
    };

    // Función para importar todos los montos de la calculadora a los campos de pago del sistema
    const calcImportarTodoAPago = () => {
        const tasaBs = getTasaBsCalc();
        const tasaCop = getTasaCopCalc();
        const totalPedidoUsd = parseFloat(pedidoData?.clean_total) || 0;
        
        // Calcular lo que ya está cargado en pagos (en USD)
        const yaDebitoUsd = (parseFloat(debito || 0) / tasaBs);
        const yaEfectivoUsd = parseFloat(efectivo_dolar || 0);
        const yaEfectivoBsUsd = (parseFloat(efectivo_bs || 0) / tasaBs);
        const yaEfectivoCopUsd = (parseFloat(efectivo_peso || 0) / tasaCop);
        const yaTransferenciaUsd = parseFloat(transferencia || 0);
        const yaCreditoUsd = parseFloat(credito || 0);
        
        let totalYaCargado = yaDebitoUsd + yaEfectivoUsd + yaEfectivoBsUsd + yaEfectivoCopUsd + yaTransferenciaUsd + yaCreditoUsd;
        let disponibleUsd = Math.max(0, totalPedidoUsd - totalYaCargado);
        
        // Importar Efectivo USD
        const pagoEfectivoUsd = parseFloat(calcPagoEfectivoUsd) || 0;
        if (pagoEfectivoUsd > 0 && disponibleUsd > 0) {
            const montoAImportar = Math.min(pagoEfectivoUsd, disponibleUsd);
            setEfectivo_dolar(prev => ((parseFloat(prev) || 0) + montoAImportar).toFixed(2));
            setCalcPagoEfectivoUsd(prev => {
                const resto = (parseFloat(prev) || 0) - montoAImportar;
                return resto > 0 ? resto.toFixed(2) : "";
            });
            disponibleUsd -= montoAImportar;
        }
        
        // Importar Efectivo Bs
        const pagoEfectivoBs = parseFloat(calcPagoEfectivoBs) || 0;
        const pagoEfectivoBsUsd = pagoEfectivoBs / tasaBs;
        if (pagoEfectivoBs > 0 && disponibleUsd > 0) {
            const montoAImportarUsd = Math.min(pagoEfectivoBsUsd, disponibleUsd);
            const montoAImportarBs = montoAImportarUsd * tasaBs;
            setEfectivo_bs(prev => ((parseFloat(prev) || 0) + montoAImportarBs).toFixed(2));
            setCalcPagoEfectivoBs(prev => {
                const resto = (parseFloat(prev) || 0) - montoAImportarBs;
                return resto > 0 ? resto.toFixed(2) : "";
            });
            disponibleUsd -= montoAImportarUsd;
        }
        
        // Importar Débito (Bs)
        const pagoDebito = parseFloat(calcPagoDebito) || 0;
        const pagoDebitoUsd = pagoDebito / tasaBs;
        if (pagoDebito > 0 && disponibleUsd > 0) {
            const montoAImportarUsd = Math.min(pagoDebitoUsd, disponibleUsd);
            const montoAImportarBs = montoAImportarUsd * tasaBs;
            setDebito(prev => ((parseFloat(prev) || 0) + montoAImportarBs).toFixed(2));
            setCalcPagoDebito(prev => {
                const resto = (parseFloat(prev) || 0) - montoAImportarBs;
                return resto > 0 ? resto.toFixed(2) : "";
            });
            disponibleUsd -= montoAImportarUsd;
        }
        
        // Importar Transferencia (USD)
        const pagoTransferencia = parseFloat(calcPagoTransferencia) || 0;
        if (pagoTransferencia > 0 && disponibleUsd > 0) {
            const montoAImportar = Math.min(pagoTransferencia, disponibleUsd);
            setTransferencia(prev => ((parseFloat(prev) || 0) + montoAImportar).toFixed(2));
            setCalcPagoTransferencia(prev => {
                const resto = (parseFloat(prev) || 0) - montoAImportar;
                return resto > 0 ? resto.toFixed(2) : "";
            });
            disponibleUsd -= montoAImportar;
        }
        
        // Importar Efectivo COP
        const pagoEfectivoCop = parseFloat(calcPagoEfectivoCop) || 0;
        const pagoEfectivoCopUsd = pagoEfectivoCop / tasaCop;
        if (pagoEfectivoCop > 0 && disponibleUsd > 0) {
            const montoAImportarUsd = Math.min(pagoEfectivoCopUsd, disponibleUsd);
            const montoAImportarCop = montoAImportarUsd * tasaCop;
            setEfectivo_peso(prev => ((parseFloat(prev) || 0) + montoAImportarCop).toFixed(0));
            setCalcPagoEfectivoCop(prev => {
                const resto = (parseFloat(prev) || 0) - montoAImportarCop;
                return resto > 0 ? resto.toFixed(0) : "";
            });
        }
    };

    // Capturar teclado cuando calculadora está abierta
    useEffect(() => {
        if (!showCalculadora) return;
        
        const handleKeyDown = (e) => {
            // Prevenir que los eventos lleguen a pagarMain
            e.stopPropagation();
            
            // Escape cierra la calculadora o la mini calculadora
            if (e.key === 'Escape') {
                if (showMiniCalc) {
                    setShowMiniCalc(false);
                } else {
                    setShowCalculadora(false);
                }
                return;
            }
            
            // Si la mini calculadora está abierta y estamos en un input de la calculadora
            if (showMiniCalc && e.target.tagName === 'INPUT') {
                // Números y operadores van a calcInput
                if (/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                    calcAgregarDigito(e.key);
                } else if (['+', '-', '*', '/', '.'].includes(e.key)) {
                    e.preventDefault();
                    calcAgregarDigito(e.key);
                } else if (e.key === 'Backspace' && calcInput) {
                    e.preventDefault();
                    calcBorrar();
                } else if (e.key === 'Enter' && calcInput) {
                    e.preventDefault();
                    calcAplicarAlCampo();
                }
            }
        };
        
        // Capturar en fase de captura para interceptar antes que otros handlers
        document.addEventListener('keydown', handleKeyDown, true);
        return () => document.removeEventListener('keydown', handleKeyDown, true);
    }, [showCalculadora, showMiniCalc, calcInput, calcCampoActivo]);

    // Funciones para formato de moneda en inputs de pago (tiempo real)
    const formatMonedaLive = (value) => {
        if (!value || value === '') return '';
        let str = String(value);

        // Normalizar: siempre tratar la coma como punto decimal
        // Ej: "10,5" -> "10.5"
        str = str.replace(/,/g, '.');

        // Manejar puntos como miles o decimales
        // Puede ser formato con punto decimal: 1234.56
        // O puede tener puntos como miles: 1.234.567
        const puntos = (str.match(/\./g) || []).length;
        if (puntos > 1) {
            // Con varios puntos: todos menos el último son miles, el último es decimal
            // Ej: "70.000,36" -> "70.000.36" -> "70000.36"
            const lastDotIndex = str.lastIndexOf('.');
            const intPartRaw = str.substring(0, lastDotIndex);
            const decPart = str.substring(lastDotIndex + 1); // puede estar vacío mientras escribes
            const intClean = intPartRaw.replace(/\./g, '');
            str = intClean + '.' + decPart; // conservamos el punto decimal aunque no haya dígitos aún
        } else if (puntos === 1) {
            const lastDotIndex = str.lastIndexOf('.');
            const afterDot = str.substring(lastDotIndex + 1);

            // Un solo punto: si después hay más de 2 dígitos, asumimos que era miles, no decimal
            // Ej: "7.000" o "70.000" o "7.0000" -> quitar punto
            if (afterDot.length > 2) {
                str = str.replace(/\./g, '');
            }
        }
        
        // Limpiar todo excepto números, punto y signo negativo
        let clean = str.replace(/[^0-9.-]/g, '');
        
        // Solo permitir un punto decimal
        const parts = clean.split('.');
        if (parts.length > 2) {
            clean = parts[0] + '.' + parts.slice(1).join('');
        }
        // Limitar a 2 decimales
        if (parts.length === 2 && parts[1].length > 2) {
            clean = parts[0] + '.' + parts[1].substring(0, 2);
        }
        return clean;
    };

    const displayMonedaLive = (value) => {
        if (!value || value === '') return '';
        const num = parseFloat(value);
        if (isNaN(num)) return value;
        // Separar parte entera y decimal
        const parts = String(value).split('.');
        const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        // Si hay decimales, mostrarlos (máximo 2)
        if (parts.length > 1) {
            return intPart + ',' + parts[1].substring(0, 2);
        }
        return intPart;
    };

    const showTittlePrice = (pu, total, tasaItem = null) => {
        try {
            const tasaUsar = tasaItem || dolar;
            return (
                "P/U. Bs." +
                moneda(number(pu) * tasaUsar) +
                "\n" +
                "Total Bs." +
                moneda(number(total) * tasaUsar)
            );
        } catch (err) {
            return "";
        }
    };
    const changeRecibido = (val, type) => {
        switch (type) {
            case "recibido_dolar":
                setrecibido_dolar(number(val));

                break;
            case "recibido_bs":
                setrecibido_bs(number(val));
                break;
            case "recibido_cop":
                setrecibido_cop(number(val));
                break;
        }
    };
    const setPagoInBs = (callback) => {
        const input = window.prompt("Monto en Bs (se usará la tasa del pedido)");
        if (input === null || input === "") return;

        const bs = parseFloat(formatMonedaLive(input));
        if (isNaN(bs) || bs === 0) return;

        const tasaBsPedido = getTasaPromedioCarrito();
        if (!tasaBsPedido || tasaBsPedido <= 0) return;

        // Convertir Bs -> USD usando la tasa histórica del pedido
        const usd = bs / tasaBsPedido;
        callback(usd.toFixed(4));
    };
    const sumRecibido = () => {
        let vuel_dolar = parseFloat(recibido_dolar ? recibido_dolar : 0);
        let vuel_bs =
            parseFloat(recibido_bs ? recibido_bs : 0) / parseFloat(dolar);
        let vuel_cop =
            parseFloat(recibido_cop ? recibido_cop : 0) / parseFloat(peso);

        let t = vuel_dolar + vuel_bs + vuel_cop;
        let cambio_dolar =
            recibido_dolar || recibido_bs || recibido_cop
                ? t - pedidoData.clean_total
                : "";

        setrecibido_tot(t.toFixed(2));
        setcambio_dolar(cambio_dolar !== "" ? cambio_dolar.toFixed(2) : "");
        setcambio_bs("");
        setcambio_cop("");
        setcambio_tot_result(
            cambio_dolar !== "" ? cambio_dolar.toFixed(2) : ""
        );
    };
    const setVueltobs = () => {
        setcambio_bs((cambio_tot_result * dolar).toFixed(2));
        setcambio_dolar("");
        setcambio_cop("");
    };
    const setVueltodolar = () => {
        setcambio_bs("");
        setcambio_dolar(cambio_tot_result);
        setcambio_cop("");
    };
    const setVueltocop = () => {
        setcambio_bs("");
        setcambio_dolar("");
        setcambio_cop((cambio_tot_result * peso).toFixed(2));
    };
    const syncCambio = (val, type) => {
        val = number(val);
        let valC = 0;
        if (type == "Dolar") {
            setcambio_dolar(val);
            valC = val;
        } else if (type == "Bolivares") {
            setcambio_bs(val);
            valC = parseFloat(val ? val : 0) / parseFloat(dolar);
        } else if (type == "Pesos") {
            setcambio_cop(val);
            valC = parseFloat(val ? val : 0) / parseFloat(peso);
        }

        let divisor = 0;

        let inputs = [
            {
                key: "Dolar",
                val: cambio_dolar,
                set: (val) => setcambio_dolar(val),
            },
            {
                key: "Bolivares",
                val: cambio_bs,
                set: (val) => setcambio_bs(val),
            },
            { key: "Pesos", val: cambio_cop, set: (val) => setcambio_cop(val) },
        ];

        inputs.map((e) => {
            if (e.key != type) {
                if (e.val) {
                    divisor++;
                }
            }
        });
        let cambio_tot_resultvalC = 0;
        if (cambio_bs && cambio_dolar && type == "Pesos") {
            let bs = parseFloat(cambio_bs) / parseFloat(dolar);
            setcambio_dolar((cambio_tot_result - bs - valC).toFixed(2));
        } else {
            inputs.map((e) => {
                if (e.key != type) {
                    if (e.val) {
                        cambio_tot_resultvalC =
                            (cambio_tot_result - valC) / divisor;
                        if (e.key == "Dolar") {
                            e.set(cambio_tot_resultvalC.toFixed(2));
                        } else if (e.key == "Bolivares") {
                            e.set((cambio_tot_resultvalC * dolar).toFixed(2));
                        } else if (e.key == "Pesos") {
                            e.set((cambio_tot_resultvalC * peso).toFixed(2));
                        }
                    }
                }
            });
        }
    };
    const sumCambio = () => {
        let vuel_dolar = parseFloat(cambio_dolar ? cambio_dolar : 0);
        let vuel_bs = parseFloat(cambio_bs ? cambio_bs : 0) / parseFloat(dolar);
        let vuel_cop =
            parseFloat(cambio_cop ? cambio_cop : 0) / parseFloat(peso);
        return (vuel_dolar + vuel_bs + vuel_cop).toFixed(2);
    };
    // Calcula la tasa promedio ponderada de los items del carrito
    const getTasaPromedioCarrito = () => {
        try {
            const items = pedidoData?.items || [];
            if (items.length === 0) return dolar;

            let sumaTasaPonderada = 0;
            let sumaTotal = 0;

            items.forEach((item) => {
                // Usar number() para manejar strings formateados
                const totalItem = number(item.total) || 0;
                const tasaItem = number(item.tasa) || dolar;
                sumaTasaPonderada += Math.abs(totalItem) * tasaItem;
                sumaTotal += Math.abs(totalItem);
            });

            if (sumaTotal === 0) return dolar;
            return sumaTasaPonderada / sumaTotal;
        } catch (err) {
            return dolar;
        }
    };

    // Funciones locales para calcular pagos con tasas del pedido
    const getDebitoLocal = () => {
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        const otrosTotal =
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        const restanteUSD = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total)
            : Math.max(0, pedidoData.clean_total - otrosTotal);
        
        const montoDebitoBs = (restanteUSD * tasaBsPedido).toFixed(2);
        
        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'debito' && parseFloat(debito || 0).toFixed(2) === montoDebitoBs) {
            setDebito((parseFloat(pedidoData.clean_total) * tasaBsPedido).toFixed(2));
            setEfectivo_dolar("");
            setEfectivo_bs("");
            setEfectivo_peso("");
            setTransferencia("");
            setCredito("");
            setBiopago("");
        } else {
            setDebito(montoDebitoBs);
        }
        
        setLastPaymentMethodCalled('debito');
    };

    const getEfectivoLocal = () => {
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular total de otros pagos (excluyendo efectivo_dolar)
        const otrosTotal =
            (parseFloat(debito || 0) / tasaBsPedido) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        const restanteUSD = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total)
            : Math.max(0, pedidoData.clean_total - otrosTotal);
        
        const montoEfectivo = restanteUSD.toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'efectivo' && parseFloat(efectivo_dolar || 0).toFixed(2) === montoEfectivo) {
            setEfectivo_dolar(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo_bs("");
            setEfectivo_peso("");
            setDebito("");
            setTransferencia("");
            setCredito("");
            setBiopago("");
        } else {
            setEfectivo_dolar(montoEfectivo);
        }
        
        setLastPaymentMethodCalled('efectivo');
    };

    const getEfectivoBsLocal = () => {
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular total de otros pagos (excluyendo efectivo_bs)
        const otrosTotal =
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        const restanteUSD = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total)
            : Math.max(0, pedidoData.clean_total - otrosTotal);
        
        const montoEfectivoBs = (restanteUSD * tasaBsPedido).toFixed(2);
        
        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'efectivo_bs' && parseFloat(efectivo_bs || 0).toFixed(2) === montoEfectivoBs) {
            setEfectivo_bs((parseFloat(pedidoData.clean_total) * tasaBsPedido).toFixed(2));
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setDebito("");
            setTransferencia("");
            setCredito("");
            setBiopago("");
        } else {
            setEfectivo_bs(montoEfectivoBs);
        }
        
        setLastPaymentMethodCalled('efectivo_bs');
    };

    const getTransferenciaLocal = () => {
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        const otrosTotal =
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        const montoTransferencia = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total).toFixed(2)
            : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'transferencia' && parseFloat(transferencia || 0).toFixed(2) === montoTransferencia) {
            setTransferencia(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo_dolar("");
            setEfectivo_bs("");
            setEfectivo_peso("");
            setDebito("");
            setCredito("");
            setBiopago("");
        } else {
            setTransferencia(montoTransferencia);
        }
        
        setLastPaymentMethodCalled('transferencia');
    };

    const getBiopagoLocal = () => {
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        const otrosTotal =
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(credito || 0);

        const montoBiopago = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total).toFixed(2)
            : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'biopago' && parseFloat(biopago || 0).toFixed(2) === montoBiopago) {
            setBiopago(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo_dolar("");
            setEfectivo_bs("");
            setEfectivo_peso("");
            setTransferencia("");
            setDebito("");
            setCredito("");
        } else {
            setBiopago(montoBiopago);
        }
        
        setLastPaymentMethodCalled('biopago');
    };

    const debitoBs = (met) => {
        try {
            const tasaPromedio = getTasaPromedioCarrito();

            if (met == "debito") {
                if (debito == "") {
                    return "";
                }
                return "Bs." + moneda(tasaPromedio * debito);
            }

            if (met == "transferencia") {
                if (transferencia == "") {
                    return "";
                }
                return "Bs." + moneda(tasaPromedio * transferencia);
            }
            if (met == "biopago") {
                if (biopago == "") {
                    return "";
                }
                return "Bs." + moneda(tasaPromedio * biopago);
            }
            if (met == "efectivo") {
                if (efectivo == "") {
                    return "";
                }
                return "Bs." + moneda(tasaPromedio * efectivo);
            }
        } catch (err) {
            return "";
            console.log();
        }
    };
    const syncPago = (val, type) => {
        val = number(val);
        
        // Usar la tasa del pedido (de los items), no la tasa actual
        const tasaBsPedido = getTasaPromedioCarrito();
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Setear el valor del campo que se está editando
        if (type == "Debito") {
            setDebito(val);
        } else if (type == "Efectivo") {
            setEfectivo(val);
        } else if (type == "EfectivoUSD") {
            setEfectivo_dolar(val);
        } else if (type == "EfectivoBs") {
            setEfectivo_bs(val);
        } else if (type == "EfectivoCOP") {
            setEfectivo_peso(val);
        } else if (type == "Transferencia") {
            setTransferencia(val);
        } else if (type == "Credito") {
            setCredito(val);
        } else if (type == "Biopago") {
            setBiopago(val);
        }

        const totalPedido = pedidoData.clean_total;
        const esDevolucion = totalPedido < 0;

        // Todos los campos de pago con su valor actual en USD y su moneda
        // IMPORTANTE: Usar el nuevo valor (val) para el campo que se está editando
        // Débito ahora es en Bs
        const allInputs = [
            { key: "Debito", val: type === "Debito" ? val : debito, set: setDebito, moneda: "bs", esEfectivo: false },
            { key: "EfectivoUSD", val: type === "EfectivoUSD" ? val : efectivo_dolar, set: setEfectivo_dolar, moneda: "usd", esEfectivo: true },
            { key: "EfectivoBs", val: type === "EfectivoBs" ? val : efectivo_bs, set: setEfectivo_bs, moneda: "bs", esEfectivo: true },
            { key: "EfectivoCOP", val: type === "EfectivoCOP" ? val : efectivo_peso, set: setEfectivo_peso, moneda: "cop", esEfectivo: true },
            { key: "Transferencia", val: type === "Transferencia" ? val : transferencia, set: setTransferencia, moneda: "usd", esEfectivo: false },
            { key: "Credito", val: type === "Credito" ? val : credito, set: setCredito, moneda: "usd", esEfectivo: false },
            { key: "Biopago", val: type === "Biopago" ? val : biopago, set: setBiopago, moneda: "usd", esEfectivo: false },
        ];

        // Función para convertir valor a USD usando tasas del pedido
        const toUSD = (valor, moneda) => {
            const v = parseFloat(valor) || 0;
            if (moneda === "bs") return tasaBsPedido > 0 ? v / tasaBsPedido : 0;
            if (moneda === "cop") return tasaCopPedido > 0 ? v / tasaCopPedido : 0;
            return v;
        };

        // Calcular el valor en USD del campo que se está editando
        const monedaActual = allInputs.find(e => e.key === type)?.moneda || "usd";
        let valUSD = toUSD(val, monedaActual);

        // Calcular total de OTROS pagos (excluyendo el campo actual) - solo si tienen valor
        const totalOtrosPagosUSD = allInputs
            .filter(e => e.key !== type && parseFloat(e.val) > 0)
            .reduce((sum, e) => sum + toUSD(e.val, e.moneda), 0);

        // Calcular máximo permitido para este campo
        const maxPermitidoUSD = totalPedido - totalOtrosPagosUSD;

        // Si autoCorrector está DESACTIVADO, limitar el valor si excede
        if (!autoCorrector) {
            const excede = esDevolucion 
                ? valUSD < maxPermitidoUSD - 0.01
                : valUSD > maxPermitidoUSD + 0.01;

            if (excede && maxPermitidoUSD >= 0) {
                const valLimitadoUSD = esDevolucion ? maxPermitidoUSD : Math.max(0, maxPermitidoUSD);
                let valLimitado = valLimitadoUSD;
                if (monedaActual === "bs") valLimitado = valLimitadoUSD * tasaBsPedido;
                else if (monedaActual === "cop") valLimitado = valLimitadoUSD * tasaCopPedido;
                
                if (type === "Debito") setDebito(valLimitado.toFixed(2));
                else if (type === "EfectivoUSD") setEfectivo_dolar(valLimitado.toFixed(2));
                else if (type === "EfectivoBs") setEfectivo_bs(valLimitado.toFixed(2));
                else if (type === "EfectivoCOP") setEfectivo_peso(valLimitado.toFixed(2));
                else if (type === "Transferencia") setTransferencia(valLimitado.toFixed(2));
                else if (type === "Credito") setCredito(valLimitado.toFixed(2));
                else if (type === "Biopago") setBiopago(valLimitado.toFixed(2));
            }
            return;
        }

        // autoCorrector está ACTIVO - continuar con la redistribución

        // Función para verificar si un valor es válido para auto-resta
        // Debe ser un número diferente de cero (ni vacío, ni null, ni "0", ni "0.00")
        const esValorValido = (val) => {
            if (!val && val !== 0) return false; // null, undefined, ""
            const num = parseFloat(val);
            return !isNaN(num) && num !== 0;
        };

        // Si estamos editando un efectivo, verificar si hay OTROS efectivos con valor
        const esEfectivoActual = ["EfectivoUSD", "EfectivoBs", "EfectivoCOP"].includes(type);
        const otrosEfectivosConValor = allInputs
            .filter(e => e.esEfectivo && e.key !== type && esValorValido(e.val))
            .length;

        // Si es efectivo Y hay otros efectivos → solo redistribuir entre efectivos
        // Si es efectivo Y NO hay otros efectivos → redistribuir entre TODOS
        const inputs = (esEfectivoActual && otrosEfectivosConValor > 0)
            ? allInputs.filter(e => e.esEfectivo) 
            : allInputs;

        // Si estamos editando efectivo Y hay otros efectivos, calcular el total disponible para efectivos
        // (total del pedido menos los otros métodos de pago)
        let totalParaDistribuir = totalPedido;
        if (esEfectivoActual && otrosEfectivosConValor > 0) {
            const otrosPagos = allInputs
                .filter(e => !e.esEfectivo && esValorValido(e.val))
                .reduce((sum, e) => {
                    let valEnUSD = parseFloat(e.val) || 0;
                    if (e.moneda === "bs") valEnUSD = valEnUSD / tasaBsPedido;
                    else if (e.moneda === "cop") valEnUSD = valEnUSD / tasaCopPedido;
                    return sum + valEnUSD;
                }, 0);
            totalParaDistribuir = totalPedido - otrosPagos;
        }

        // Contar cuántos campos tienen valor válido (excluyendo el actual)
        let divisor = 0;
        inputs.forEach((e) => {
            if (e.key !== type && esValorValido(e.val)) {
                divisor++;
            }
        });

        if (divisor === 0) return;

        // Calcular restante y distribuir
        const restanteUSD = (totalParaDistribuir - valUSD) / divisor;

        // Verificar que el restante tenga el mismo signo que el total (o sea cero)
        // Para pedidos positivos: restante no debe ser negativo
        // Para devoluciones: restante no debe ser positivo
        if (esDevolucion) {
            if (restanteUSD > 0) return; // En devolución, restante debe ser negativo o cero
        } else {
            if (restanteUSD < 0) return; // En pedido normal, restante debe ser positivo o cero
        }

        inputs.forEach((e) => {
            if (e.key !== type && esValorValido(e.val)) {
                // Convertir el restante a la moneda del campo usando tasa del pedido
                let nuevoVal = restanteUSD;
                if (e.moneda === "bs") {
                    nuevoVal = restanteUSD * tasaBsPedido;
                } else if (e.moneda === "cop") {
                    nuevoVal = restanteUSD * tasaCopPedido;
                }
                e.set(nuevoVal.toFixed(e.moneda === "cop" ? 0 : 2));
            }
        });
    };

    const [validatingRef, setValidatingRef] = useState(null);

    // Estados para modal de código de aprobación
    const [showCodigoModal, setShowCodigoModal] = useState(false);
    const [codigoGenerado, setCodigoGenerado] = useState("");
    const [claveIngresada, setClaveIngresada] = useState("");
    const [currentRefId, setCurrentRefId] = useState(null);

    const sendRefToMerchant = (id_ref) => {
        console.log(id_ref, "id_ref BEFORE");
        setValidatingRef(id_ref);
        console.log(id_ref, "id_ref");
        db.sendRefToMerchant({
            id_ref: id_ref,
        })
            .then((res) => {
                // Verificar si se requiere procesamiento Megasoft desde el frontend
                if (res.data.estado === "megasoft_required") {
                    // Obtener datos para la petición Megasoft
                    const datosMegasoft = res.data.datos_megasoft;
                    
                    // Hacer la petición directamente a Megasoft desde el frontend
                    fetch('http://localhost:8085/vpos/metodo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            accion: datosMegasoft.accion,
                            montoTransaccion: datosMegasoft.montoTransaccion,
                            cedula: datosMegasoft.cedula,
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(megasoftResponse => {
                        // Enviar la respuesta de Megasoft al backend para que la procese
                        return db.procesarRespuestaMegasoft({
                            id_ref: datosMegasoft.id_ref,
                            response_megasoft: megasoftResponse
                        });
                    })
                    .then(backendResponse => {
                        // Mostrar la respuesta del backend
                        notificar(backendResponse.data.msj);
                        
                        // Refrescar los datos del pedido
                        setTimeout(() => {
                            getPedido(null, null, false);
                        }, 500);
                    })
                    .catch(error => {
                        console.error('Error en proceso Megasoft:', error);
                        notificar({
                            msj: "Error al procesar con Megasoft: " + error.message,
                            estado: false
                        });
                    })
                    .finally(() => {
                        setValidatingRef(null);
                    });
                    
                } else if (res.data.estado === "codigo_required") {
                    // Código de aprobación requerido
                    setCodigoGenerado(res.data.codigo_generado);
                    setCurrentRefId(id_ref);
                    setShowCodigoModal(true);
                    setClaveIngresada("");
                    setValidatingRef(null);
                } else {
                    // Mostrar la respuesta exacta del backend
                    notificar(res.data.msj);

                    // Refrescar los datos del pedido después de un pequeño delay
                    // para asegurar que la BD se haya actualizado completamente
                    setTimeout(() => {
                        getPedido(null, null, false);
                    }, 500);
                    setValidatingRef(null);
                }
            })
            .catch((error) => {
                notificar({
                    msj:
                        "Error al validar referencia: " +
                        (error.response?.data?.msj || error.message),
                    estado: false,
                });
                setValidatingRef(null);
            });
    };

    // Función para validar el código de aprobación
    const validarCodigo = () => {
        if (!claveIngresada.trim()) {
            notificar({
                msj: "Por favor ingrese la clave de aprobación",
                estado: false,
            });
            return;
        }

        db.validarCodigoAprobacion({
            codigo: claveIngresada,
            id_ref: currentRefId,
        })
            .then((res) => {
                if (res.data.estado) {
                    notificar(res.data.msj);
                    setShowCodigoModal(false);
                    setClaveIngresada("");
                    setCodigoGenerado("");
                    setCurrentRefId(null);

                    // Refrescar los datos del pedido
                    setTimeout(() => {
                        getPedido(null, null, false);
                    }, 500);
                } else {
                    notificar({
                        msj: res.data.msj,
                        estado: false,
                    });
                }
            })
            .catch((error) => {
                notificar({
                    msj:
                        "Error al validar código: " +
                        (error.response?.data?.msj || error.message),
                    estado: false,
                });
            });
    };

    // Función para cerrar el modal de código
    const cerrarModalCodigo = () => {
        setShowCodigoModal(false);
        setClaveIngresada("");
        setCodigoGenerado("");
        setCurrentRefId(null);
    };

    // Función para generar la clave desde el código mostrado
    
    // ============= FUNCIONES PARA DEVOLUCIONES =============
    
    // Función para asignar pedido original a devolución
    const asignarPedidoOriginal = () => {
        if (!pedidoOriginalInput.trim()) {
            notificar({
                msj: "Ingrese el número de pedido original",
                estado: false,
            });
            return;
        }

        db.asignarPedidoOriginalDevolucion({
            id_pedido_devolucion: id,
            id_pedido_original: pedidoOriginalInput,
        })
            .then((res) => {
                if (res.data.estado) {
                    notificar(res.data);
                    setItemsDisponiblesDevolucion(res.data.items_disponibles || []);
                    setPedidoOriginalAsignado(pedidoOriginalInput);
                    setShowModalPedidoOriginal(false);
                    setPedidoOriginalInput("");
                    // Refrescar pedido
                    getPedido(null, null, false);
                } else {
                    notificar(res.data);
                }
            })
            .catch((error) => {
                notificar({
                    msj: "Error: " + (error.response?.data?.msj || error.message),
                    estado: false,
                });
            });
    };

    // Función para cerrar modal de pedido original
    const cerrarModalPedidoOriginal = () => {
        setShowModalPedidoOriginal(false);
        setPedidoOriginalInput("");
    };

    // Verificar si se necesita mostrar modal de pedido original
    // Se muestra cuando se intenta agregar cantidad negativa por primera vez
    // y el pedido no tiene isdevolucionOriginalid asignado
    const verificarNecesitaPedidoOriginal = () => {
        // Si el pedido ya tiene items con cantidad negativa, ya fue validado
        const tieneItemsNegativos = items.some(item => item.cantidad < 0);
        // Si pedidoData tiene isdevolucionOriginalid, ya está asignado
        const tieneOriginalAsignado = pedidoData?.isdevolucionOriginalid;
        
        return !tieneItemsNegativos && !tieneOriginalAsignado;
    };

    // Función para ver información de la factura original
    const verFacturaOriginal = () => {
        const idOriginal = pedidoData?.isdevolucionOriginalid || pedidoOriginalAsignado;
        if (!idOriginal) return;

        setLoadingFacturaOriginal(true);
        setShowModalInfoFacturaOriginal(true);

        db.getItemsDisponiblesDevolucion({ id_pedido_original: idOriginal })
            .then((res) => {
                if (res.data.estado) {
                    setItemsFacturaOriginal(res.data.items_disponibles || []);
                } else {
                    notificar({ msj: "Error al cargar items", estado: false });
                }
            })
            .catch((error) => {
                notificar({ msj: "Error: " + error.message, estado: false });
            })
            .finally(() => {
                setLoadingFacturaOriginal(false);
            });
    };

    // Función para eliminar la asignación de factura original
    const eliminarAsignacionFacturaOriginal = () => {
        if (!confirm("¿Está seguro de eliminar la asignación de la factura original?")) {
            return;
        }

        db.eliminarPedidoOriginalDevolucion({ id_pedido: id })
            .then((res) => {
                notificar(res);
                if (res.data.estado) {
                    setShowModalInfoFacturaOriginal(false);
                    setPedidoOriginalAsignado(null);
                    setItemsFacturaOriginal([]);
                    getPedido(null, null, false);
                }
            })
            .catch((error) => {
                notificar({ msj: "Error: " + error.message, estado: false });
            });
    };

    useEffect(() => {
        sumRecibido();
    }, [recibido_bs, recibido_cop, recibido_dolar]);

    // Sincronizar efectivo total con la suma de efectivo_dolar + efectivo_bs + efectivo_peso
    useEffect(() => {
        const totalEfectivo = 
            parseFloat(efectivo_dolar || 0) + 
            (parseFloat(efectivo_bs || 0) / dolar) + 
            (parseFloat(efectivo_peso || 0) / peso);
        if (totalEfectivo > 0) {
            setEfectivo(totalEfectivo.toFixed(2));
        } else {
            setEfectivo("");
        }
    }, [efectivo_dolar, efectivo_bs, efectivo_peso, dolar, peso]);

    useEffect(() => {
        getPedidosFast();
    }, []);

    // Limpiar estados de factura original cuando cambie el pedido
    useEffect(() => {
        // Limpiar estados relacionados con devoluciones al cambiar de pedido
        setPedidoOriginalAsignado(null);
        setItemsFacturaOriginal([]);
        setItemsDisponiblesDevolucion([]);
        setShowModalInfoFacturaOriginal(false);
        setShowModalPedidoOriginal(false);
        setPedidoOriginalInput("");
        // Limpiar calculadora/vueltos
        setShowCalculadora(false);
        setShowMiniCalc(false);
        setCalcInput("");
        setCalcCampoActivo(null);
        setCalcPagoEfectivoUsd("");
        setCalcPagoEfectivoBs("");
        setCalcPagoEfectivoCop("");
        setCalcPagoDebito("");
        setCalcPagoTransferencia("");
        setCalcVueltoUsd("");
        setCalcVueltoBs("");
        setCalcVueltoCop("");
    }, [pedidoData?.id]);

    // useEffect para detectar scroll y mostrar/ocultar menú
    useEffect(() => {
        const handleScroll = () => {
            const currentScrollY = window.scrollY;

            if (currentScrollY < lastScrollY) {
                // Scroll hacia arriba - mostrar menú
                setShowFloatingMenu(true);
            } else if (currentScrollY > lastScrollY && currentScrollY > 50) {
                // Scroll hacia abajo - ocultar menú (solo después de 50px)
                setShowFloatingMenu(false);
            }

            setLastScrollY(currentScrollY);
        };

        // Agregar listener al scroll global de la ventana
        window.addEventListener("scroll", handleScroll, { passive: true });

        return () => {
            window.removeEventListener("scroll", handleScroll);
        };
    }, [lastScrollY]);

    //esc

    //c
    useHotkeys(
        "c",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            getCredito();
            // Hacer foco en el input de crédito
            setTimeout(() => {
                if (creditoInputRef.current) {
                    creditoInputRef.current.focus();
                    creditoInputRef.current.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast]
    );

    useHotkeys(
        "t",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            getTransferenciaLocal();
            // Hacer foco en el input de transferencia
            setTimeout(() => {
                if (transferenciaInputRef.current) {
                    transferenciaInputRef.current.focus();
                    transferenciaInputRef.current.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast]
    );
    //p - Biopago (Pago móvil)
    useHotkeys(
        "p",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            getBiopagoLocal();
            // Hacer foco en el input de biopago
            setTimeout(() => {
                if (biopagoInputRef.current) {
                    biopagoInputRef.current.focus();
                    biopagoInputRef.current.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast]
    );
    //b - Efectivo Bolívares
    useHotkeys(
        "b",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            // No ejecutar si estamos en el input de referencia de débito
            if (event.target?.getAttribute("data-ref-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            getEfectivoBsLocal();
            // Hacer foco en el input de efectivo Bs
            setTimeout(() => {
                const efectivoBsInput = document.querySelector('[data-efectivo="bs"]');
                if (efectivoBsInput) {
                    efectivoBsInput.focus();
                    efectivoBsInput.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast]
    );
    //e - Efectivo USD
    useHotkeys(
        "e",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            // Usar función local que usa tasas del pedido
            getEfectivoLocal();
            // Hacer foco en el input de efectivo USD
            setTimeout(() => {
                if (efectivoInputRef.current) {
                    efectivoInputRef.current.focus();
                    efectivoInputRef.current.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast]
    );
    //d
    useHotkeys(
        "d",
        (event) => {
            // No ejecutar si estamos en el input de búsqueda de productos
            if (event.target === refaddfast?.current) {
                return;
            }

            // No ejecutar si estamos en el modal de carnet
            if (event.target?.getAttribute("data-carnet-input") === "true") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            // Si ya estamos en el input de débito, pasar al input de referencia
            if (document.activeElement === debitoInputRef.current) {
                const refInput = document.querySelector('[data-ref-input="true"]');
                if (refInput) {
                    refInput.focus();
                    refInput.select();
                }
                return;
            }

            getDebitoLocal();
            // Hacer foco en el input de débito
            setTimeout(() => {
                if (debitoInputRef.current) {
                    debitoInputRef.current.focus();
                    debitoInputRef.current.select();
                }
            }, 50);
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        [refaddfast, debitoInputRef]
    );
    //f5
    useHotkeys(
        "f5",
        () => {
            del_pedido();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        []
    );
    //f4
    useHotkeys(
        "f4",
        () => {
            viewReportPedido();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        []
    );
    //f3
    useHotkeys(
        "f3",
        () => {
            toggleImprimirTicket();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        []
    );
    //f2
    useHotkeys(
        "f2",
        () => {
            setToggleAddPersonaFun(true, () => {
                setclienteInpnombre("");
                setclienteInptelefono("");
                setclienteInpdireccion("");

                if (inputmodaladdpersonacarritoref) {
                    if (inputmodaladdpersonacarritoref.current) {
                        inputmodaladdpersonacarritoref.current.focus();
                    }
                }
            });
        },
        { enableOnTags: ["INPUT", "SELECT"] },
        []
    );
    //ctrl+enter
    useHotkeys(
        "ctrl+enter",
        (event) => {
            if (!event.repeat) {
                facturar_e_imprimir();
            }
        },
        {
            keydown: true,
            keyup: false,
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        []
    );
    //f1 - Crear nuevo pedido
    useHotkeys(
        "f1",
        () => {
            // Validar que existe la función addNewPedido
            if (typeof addNewPedido === "function") {
                addNewPedido();
            } else {
                console.warn("addNewPedido function not available");
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        []
    );

    //f10 - Toggle header y menú flotante
    useHotkeys(
        "f10",
        (event) => {
            event.preventDefault(); // Prevenir comportamiento por defecto del navegador
            setShowHeaderAndMenu(!showHeaderAndMenu);
            // Scroll al top
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        [showHeaderAndMenu]
    );

    //tab - Comportamiento por defecto cuando el modal de referencia está abierto
    useHotkeys(
        "tab",
        (event) => {
            // Solo aplicar comportamiento por defecto si el modal de referencia está abierto
            if (togglereferenciapago) {
                // Permitir el comportamiento por defecto del TAB (navegación entre elementos)
                return; // No hacer preventDefault, dejar que el TAB funcione normalmente
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        [togglereferenciapago]
    );

    //r - Abrir modal de referencia de pago
    useHotkeys(
        "r",
        (event) => {
            // Solo ejecutar si NO estamos en ningún input o select
            const isInputOrSelect =
                event.target.tagName === "INPUT" ||
                event.target.tagName === "SELECT" ||
                event.target.tagName === "TEXTAREA";

            if (!isInputOrSelect) {
                event.preventDefault();
                event.stopPropagation();

                // Determinar qué tipo de pago está activo y su monto
                let montoActivo = transferencia;
                let tipoActivo = "1"; // Transferencia por defecto

                if (debito && debito > 0) {
                    montoActivo = debito;
                    tipoActivo = "2"; // Débito
                } else if (efectivo && efectivo > 0) {
                    montoActivo = efectivo;
                    tipoActivo = "3"; // Efectivo
                } else if (credito && credito > 0) {
                    montoActivo = credito;
                    tipoActivo = "4"; // Crédito
                } else if (biopago && biopago > 0) {
                    montoActivo = biopago;
                    tipoActivo = "5"; // Biopago
                }

                addRefPago("toggle", montoActivo, tipoActivo);
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
            filter: false,
        },
        [transferencia, debito, efectivo, credito, biopago]
    );

    const {
        id = null,
        created_at = "",
        cliente = "",
        items = [],
        retenciones = [],
        total_des = 0,
        subtotal = 0,
        total = 0,
        total_porciento = 0,
        cop = 0,
        bs = 0,
        editable = 0,
        vuelto_entregado = 0,
        estado = 0,
        exento = 0,
        gravable = 0,
        ivas = 0,
        monto_iva = 0,
    } = pedidoData;

    let ifnegative = items.filter((e) => e.cantidad < 0).length;
    return pedidoData ? (
        <div className="container-fluid h-100 ">
            <div className="row h-100">
                <div className="h-100 col-lg-7 pr-2 pl-2">
                    <ListProductosInterno
                        qProductosMain={qProductosMain}
                        setQProductosMain={setQProductosMain}
                        setLastDbRequest={setLastDbRequest}
                        lastDbRequest={lastDbRequest}
                        openValidationTarea={openValidationTarea}
                        num={num}
                        setNum={setNum}
                        auth={auth}
                        refaddfast={refaddfast}
                        cedula_referenciapago={cedula_referenciapago}
                        setcedula_referenciapago={setcedula_referenciapago}
                        telefono_referenciapago={telefono_referenciapago}
                        settelefono_referenciapago={settelefono_referenciapago}
                        setinputqinterno={setinputqinterno}
                        inputqinterno={inputqinterno}
                        tbodyproducInterref={tbodyproducInterref}
                        productos={productos}
                        countListInter={countListInter}
                        moneda={moneda}
                        setCountListInter={setCountListInter}
                        setView={setView}
                        permisoExecuteEnter={permisoExecuteEnter}
                        user={user}
                        inputCantidadCarritoref={inputCantidadCarritoref}
                        setCantidad={setCantidad}
                        cantidad={cantidad}
                        number={number}
                        dolar={dolar}
                        addCarritoRequestInterno={addCarritoRequestInterno}
                        setproductoSelectinternouno={
                            setproductoSelectinternouno
                        }
                        pedidoData={pedidoData}
                        notificar={notificar}
                        getPedido={getPedido}
                        pedidosFast={pedidosFast}
                        onClickEditPedido={onClickEditPedido}
                        togglereferenciapago={togglereferenciapago}
                        orderColumn={orderColumn}
                        setOrderColumn={setOrderColumn}
                        orderBy={orderBy}
                        setOrderBy={setOrderBy}
                        getProductos={getProductos}
                        devolucionTipo={devolucionTipo}
                        setShowModalPedidoOriginal={setShowModalPedidoOriginal}
                        pedidoOriginalAsignado={pedidoOriginalAsignado}
                    />
                </div>

                <div className="h-100 col-lg-5 pr-2 pl-0 d-flex flex-column">
                    {id ? (
                        <>
                            <div className="relative">
                                <div className="p-2 mb-1 bg-white border border-orange-400 rounded">
                                    {/* Precios arriba */}
                                    <div className="flex flex-col items-center justify-between pb-3 mb-3 border-b border-gray-200 gap-y-2 sm:flex-row">
                                        <div className="flex items-center gap-6">
                                            <div className="text-left">
                                                
                                                <div className="text-2xl font-bold text-green-500 md:text-4xl">
                                                    {moneda(total)} <span className="text-xs">Ref</span>
                                                </div>
                                            </div>
                                            <div className="h-12 border-l border-gray-300"></div>
                                            <div className="text-left">
                                                <div className="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-0.5">
                                                    {pedidoData.estado !== 0 && pedidoData.items?.[0]?.tasa && (
                                                        <span className="px-1.5 py-0.5 text-[10px] font-medium text-orange-600 bg-orange-100 rounded">
                                                            @{parseFloat(pedidoData.items[0].tasa).toFixed(4)}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <div className="text-2xl font-bold text-orange-500 md:text-4xl">
                                                        {moneda(pedidoData.bs)} <span className="text-xs">Bs</span>
                                                    </div>
                                                    {/* Botón Calculadora/Vueltos */}
                                                    <button
                                                        ref={calculadoraBtnRef}
                                                        onClick={() => setShowCalculadora(!showCalculadora)}
                                                        className={`p-1.5 text-xs border rounded transition-colors ${showCalculadora ? "bg-orange-500 text-white border-orange-600" : "text-gray-500 bg-gray-100 border-gray-300 hover:bg-gray-200 hover:text-gray-700"}`}
                                                        title="Calculadora y Vueltos (ESC para cerrar)"
                                                    >
                                                        <i className="fa fa-calculator"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            {user.sucursal == "elorza" && (
                                                <>
                                                    <div className="h-12 border-l border-gray-300"></div>
                                                    <div className="text-left">
                                                        <div className="text-xs font-semibold text-gray-700 mb-0.5">
                                                            Total Cops
                                                        </div>
                                                        <div className="text-2xl font-bold text-blue-500 md:text-4xl">
                                                            {moneda(
                                                                pedidoData.clean_total *
                                                                    peso
                                                            )}
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                        </div>

                                        {/* Descuento */}
                                        <button
                                            data-index={id}
                                            onClick={setDescuentoTotal}
                                            className="px-2 py-1 text-xs font-semibold text-gray-700 border !border-gray-300 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200"
                                        >
                                            <i className="mr-1.5 fa fa-percentage text-xs"></i>
                                            {total_porciento}%
                                        </button>
                                    </div>

                                    {/* Info del pedido abajo */}
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-semibold text-gray-700">#{id}</span>
                                            <span className="text-xs text-gray-400">•</span>
                                            <span className="text-xs text-gray-500">{created_at}</span>
                                            
                                            {/* Botón para ver factura original */}
                                            {(pedidoData?.isdevolucionOriginalid || pedidoOriginalAsignado) && (
                                                <>
                                                    <span className="text-xs text-gray-400">•</span>
                                                    <button
                                                        onClick={verFacturaOriginal}
                                                        className="px-2 py-0.5 text-xs font-medium text-orange-700 bg-orange-100 border border-orange-300 rounded hover:bg-orange-200 transition-colors"
                                                        title="Ver factura original"
                                                    >
                                                        <i className="mr-1 fa fa-file-invoice"></i>
                                                        #{pedidoData?.isdevolucionOriginalid || pedidoOriginalAsignado}
                                                    </button>
                                                </>
                                            )}
                                            
                                            {cliente && (
                                                <>
                                                    <span className="text-xs text-gray-400">•</span>
                                                    <span className="text-xs font-medium text-gray-700 truncate max-w-[150px]" title={cliente.nombre === "CF" ? "Sin cliente" : cliente.nombre}>
                                                        {cliente.nombre === "CF" ? "Sin cliente" : cliente.nombre}
                                                    </span>
                                                    {cliente.identificacion !== "CF" && (
                                                        <span className="text-xs text-gray-500">
                                                            ({cliente.identificacion})
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </div>

                                        {/* Badge de estado */}
                                        <span
                                            className={`${
                                                estado == 1
                                                    ? "bg-green-100 text-green-700 !border-green-200"
                                                    : estado == 2
                                                    ? "bg-red-100 text-red-700 !border-red-200"
                                                    : "bg-orange-100 text-orange-700 !border-orange-200"
                                            } inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold border rounded-full whitespace-nowrap`}
                                        >
                                            {estado == 1 ? (
                                                <>
                                                    <i className="fa fa-check-circle"></i>
                                                    Completado
                                                </>
                                            ) : estado == 2 ? (
                                                <>
                                                    <i className="fa fa-times-circle"></i>
                                                    Cancelado
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fa fa-clock"></i>
                                                    Pendiente
                                                </>
                                            )}
                                        </span>
                                    </div>
                                    {user.sucursal == "elorza" && (
                                        <div className="pt-3 mt-3 text-right border-t border-gray-200">
                                            <div className="text-xs text-gray-500">
                                                COP{" "}
                                                <span
                                                    data-type="cop"
                                                    className="font-bold text-gray-600 cursor-pointer"
                                                >
                                                    {cop}
                                                </span>
                                            </div>
                                        </div>
                                    )}
                                    {pedidoData.clean_total < 0 ? (
                                        <div className="p-2 mt-3 text-xs border border-yellow-200 rounded bg-yellow-50">
                                            <i className="mr-2 text-yellow-600 fa fa-exclamation-triangle"></i>
                                            <span className="text-yellow-800">
                                                Debemos pagarle diferencia al
                                                cliente
                                            </span>
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            <div className="flex-1 overflow-auto pr-2">
                                {items && items.length > 0 ? (
                                    <div className="mb-3 bg-white border border-gray-200 rounded ">
                                        <table className="w-full text-xs ">
                                            <colgroup>
                                                <col className="!w-[60%]" />
                                                <col className="!w-[10%]" />
                                                <col className="!w-[10%]" />
                                                <col className="!w-[10%]" />
                                                {editable && (
                                                    <col className="!w-[10%]" />
                                                )}
                                            </colgroup>
                                            <thead className="border-b border-gray-200 bg-gray-50">
                                                <tr>
                                                    <th className="px-2 py-1 text-xs font-medium tracking-wider text-left text-gray-600">
                                                        Producto{" "}
                                                        <span className="font-normal text-gray-500">
                                                            ({items.length})
                                                        </span>
                                                    </th>
                                                    <th className="px-2 py-1 text-xs font-medium tracking-wider text-center text-gray-600">
                                                        Cant.
                                                    </th>
                                                    <th className="px-2 py-1 text-xs font-medium tracking-wider text-right text-gray-600">
                                                        Precio
                                                    </th>
                                                    {/*  <th className="px-2 py-1 text-xs font-medium tracking-wider text-right text-gray-600">
                                                        Subtotal
                                                    </th> */}
                                                    <th className="px-2 py-1 text-xs font-medium tracking-wider text-right text-gray-600">
                                                        Total
                                                    </th>
                                                    {editable && (
                                                        <th className="px-2 py-1 text-xs font-medium tracking-wider text-center text-gray-600"></th>
                                                    )}
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {items.map((e, i) =>
                                                    e.abono && !e.producto ? (
                                                        <tr
                                                            key={e.id}
                                                            className="hover:bg-gray-50"
                                                        >
                                                            <td className="px-2 py-1 text-xs text-gray-600">
                                                                MOV
                                                            </td>
                                                            <td className="px-2 py-1 text-xs">
                                                                {e.abono}
                                                            </td>
                                                            <td className="px-2 py-1 text-xs text-center">
                                                                {e.cantidad}
                                                            </td>
                                                            <td className="px-2 py-1 text-xs text-right">
                                                                {e.monto}
                                                            </td>
                                                            <td
                                                                onClick={
                                                                    setDescuentoUnitario
                                                                }
                                                                data-index={
                                                                    e.id
                                                                }
                                                                className="px-2 py-1 text-xs text-right cursor-pointer hover:bg-orange-50"
                                                            >
                                                                {e.descuento}
                                                            </td>
                                                            <td className="px-2 py-1 text-xs text-right">
                                                                {moneda(e.total_des)}
                                                            </td>
                                                            <td className="px-2 py-1 text-xs font-bold text-right">
                                                                {moneda(e.total)}
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        <tr
                                                            key={e.id}
                                                            title={showTittlePrice(
                                                                e.producto
                                                                    .precio,
                                                                e.total,
                                                                e.tasa
                                                            )}
                                                            className="hover:bg-gray-50"
                                                        >
                                                            <td className="px-2 py-1">
                                                                <div className="flex items-center space-x-2">
                                                                    {ifnegative ? (
                                                                        <>
                                                                            {e.condicion ==
                                                                            1 ? (
                                                                                <span className="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">
                                                                                    Garantía
                                                                                </span>
                                                                            ) : null}
                                                                            {e.condicion ==
                                                                                2 ||
                                                                            e.condicion ==
                                                                                0 ? (
                                                                                <span className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs">
                                                                                    Cambio
                                                                                </span>
                                                                            ) : null}
                                                                        </>
                                                                    ) : null}
                                                                    <span
                                                                        className="text-xs cursor-pointer"
                                                                        data-id={
                                                                            e.id
                                                                        }
                                                                    >
                                                                        <div className="font-mono text-gray-600">
                                                                            {
                                                                                e
                                                                                    .producto
                                                                                    .codigo_barras
                                                                            }
                                                                        </div>
                                                                        <div
                                                                            className="font-medium text-gray-900 "
                                                                            title={
                                                                                e
                                                                                    .producto
                                                                                    .descripcion
                                                                            }
                                                                        >
                                                                            {
                                                                                e
                                                                                    .producto
                                                                                    .descripcion
                                                                            }
                                                                        </div>
                                                                    </span>
                                                                    {/*  {e.entregado ? (
                                                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-800 rounded text-xs">
                                                                            Entregado
                                                                        </span>
                                                                    ) : null} */}
                                                                </div>
                                                            </td>
                                                            <td
                                                                className="px-2 py-1 text-center cursor-pointer"
                                                                onClick={
                                                                    e.condicion ==
                                                                    1
                                                                        ? null
                                                                        : setCantidadCarrito
                                                                }
                                                                data-index={
                                                                    e.id
                                                                }
                                                            >
                                                                <div className="flex items-center justify-center space-x-1">
                                                                    {ifnegative ? (
                                                                        e.cantidad <
                                                                        0 ? (
                                                                            <span className="px-1 py-0.5 bg-green-100 text-green-800 rounded text-xs">
                                                                                <i className="fa fa-arrow-down"></i>
                                                                            </span>
                                                                        ) : (
                                                                            <span className="px-1 py-0.5 bg-red-100 text-red-800 rounded text-xs">
                                                                                <i className="fa fa-arrow-up"></i>
                                                                            </span>
                                                                        )
                                                                    ) : null}
                                                                    <span className="text-xs">
                                                                        {Number(
                                                                            e.cantidad
                                                                        ) %
                                                                            1 ===
                                                                        0
                                                                            ? Number(
                                                                                  e.cantidad
                                                                              )
                                                                            : Number(
                                                                                  e.cantidad
                                                                              ).toFixed(
                                                                                  2
                                                                              )}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            {e.producto
                                                                .precio1 ? (
                                                                <td
                                                                    className="px-2 py-1 text-xs text-right text-green-600 cursor-pointer"
                                                                    data-iditem={
                                                                        e.id
                                                                    }
                                                                    onClick={
                                                                        setPrecioAlternoCarrito
                                                                    }
                                                                >
                                                                    <div>
                                                                        {moneda(e.producto.precio, 4)}
                                                                    </div>
                                                                    {/* Mostrar tasa original en devoluciones */}
                                                                    {e.cantidad < 0 && e.tasa && (
                                                                        <div className="text-[10px] text-orange-600 font-normal">
                                                                            @{moneda(e.tasa, 4)}
                                                                        </div>
                                                                    )}
                                                                </td>
                                                            ) : (
                                                                <td className="px-2 py-1 text-xs text-right cursor-pointer">
                                                                    <div>
                                                                        {moneda(
                                                                            e
                                                                                .producto
                                                                                .precio,
                                                                            4
                                                                        )}
                                                                    </div>
                                                                    {/* Mostrar tasa original en devoluciones */}
                                                                    {e.cantidad < 0 && e.tasa && (
                                                                        <div className="text-[10px] text-orange-600">
                                                                            @{moneda(e.tasa, 4)}
                                                                        </div>
                                                                    )}
                                                                </td>
                                                            )}
                                                            {/*   <td
                                                                onClick={
                                                                    setDescuentoUnitario
                                                                }
                                                                data-index={e.id}
                                                                className="px-2 py-1 text-xs text-right cursor-pointer hover:bg-orange-50"
                                                            >
                                                                {e.subtotal}
                                                            </td> */}
                                                            <td className="px-2 py-1 text-xs font-bold text-right">
                                                                {moneda(e.total)}
                                                            </td>
                                                            {editable ? (
                                                                <td className="px-2 py-1 text-center">
                                                                    <i
                                                                        onClick={
                                                                            delItemPedido
                                                                        }
                                                                        data-index={
                                                                            e.id
                                                                        }
                                                                        className="text-red-500 cursor-pointer fa fa-times hover:text-red-700"
                                                                    ></i>
                                                                </td>
                                                            ) : null}
                                                        </tr>
                                                    )
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="mb-1 overflow-hidden bg-white border border-gray-200 rounded">
                                        <div className="flex flex-col items-center justify-center px-4 py-12">
                                            <div className="flex items-center justify-center w-16 h-16 mb-3 bg-gray-100 rounded-full">
                                                <i className="text-2xl text-gray-400 fa fa-box-open"></i>
                                            </div>
                                            <p className="mb-1 text-sm font-semibold text-gray-700">
                                                Sin productos aún
                                            </p>
                                            <p className="text-xs text-center text-gray-500">
                                                Comienza agregando productos al
                                                pedido
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* ══════════ VISTA DE PAGOS PROCESADOS ══════════ */}
                                {pedidoData?.estado === 1 && pedidoData?.pagos && pedidoData.pagos.length > 0 ? (
                                    <div className="mb-3 overflow-hidden bg-white border border-gray-200 rounded">
                                        <div className="px-3 py-2 border-b border-gray-200 bg-gray-50">
                                            <h6 className="flex items-center text-sm font-semibold text-gray-800">
                                                <i className="mr-2 text-green-500 fa fa-check-circle"></i>
                                                Pagos Procesados
                                            </h6>
                                        </div>
                                        <div className="p-3 space-y-2">
                                            {pedidoData.pagos.map((pago) => {
                                                if (parseFloat(pago.monto) === 0) return null;
                                                
                                                const getTipoPago = (tipo) => {
                                                    // Convertir a string para comparar con el enum de la BD
                                                    const tipoStr = String(tipo);
                                                    switch(tipoStr) {
                                                        case '1': return { nombre: "Transferencia", icon: "fa-exchange", color: "blue" };
                                                        case '2': return { nombre: "Débito", icon: "fa-credit-card", color: "orange" };
                                                        case '3': return { nombre: "Efectivo", icon: "fa-money", color: "green" };
                                                        case '4': return { nombre: "Crédito", icon: "fa-calendar", color: "yellow" };
                                                        case '5': return { nombre: "Biopago", icon: "fa-mobile", color: "purple" };
                                                        case '6': return { nombre: "Vuelto", icon: "fa-undo", color: "red" };
                                                        default: return { nombre: "Otro", icon: "fa-ban", color: "gray" };
                                                    }
                                                };

                                                const tipoPago = getTipoPago(pago.tipo);
                                                
                                                return (
                                                    <div key={pago.id} className={`flex items-center justify-between p-2 border rounded bg-${tipoPago.color}-50 border-${tipoPago.color}-200`}>
                                                        <div className="flex items-center gap-2">
                                                            <i className={`fa ${tipoPago.icon} text-${tipoPago.color}-600`}></i>
                                                            <div>
                                                                <div className={`text-sm font-semibold text-${tipoPago.color}-800`}>
                                                                    {tipoPago.nombre}
                                                                </div>
                                                                {pago.referencia && (
                                                                    <div className="text-xs text-gray-600">
                                                                        Ref: #{pago.referencia}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            {pago.monto_original && pago.moneda ? (
                                                                <>
                                                                    <div className={`text-sm font-bold text-${tipoPago.color}-700`}>
                                                                        {pago.moneda === 'bs' ? `Bs ${moneda(pago.monto_original)}` : 
                                                                         pago.moneda === 'cop' ? `COP ${parseFloat(pago.monto_original).toFixed(0)}` : 
                                                                         `$${moneda(pago.monto)}`}
                                                                    </div>
                                                                    {pago.moneda !== 'usd' && (
                                                                        <div className="text-[10px] text-gray-600">
                                                                            ≈${moneda(pago.monto)}
                                                                        </div>
                                                                    )}
                                                                </>
                                                            ) : (
                                                                <div className={`text-sm font-bold text-${tipoPago.color}-700`}>
                                                                    ${moneda(pago.monto)}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : pedidoData?.estado !== 1 ? (
                                <div className="mb-3 space-y-2">
                                    {/* ══════════ GRUPO DÉBITO ══════════ */}
                                    <div className="flex flex-col gap-1">
                                        <span className="text-[10px] font-medium text-orange-600">Débito (D)</span>
                                        <div className="flex flex-1 gap-2">
                                            {/* Monto Débito (en Bs) */}
                                            <div className={`flex-1 flex border rounded ${debito != "" ? "bg-white border-orange-300" : "bg-white border-gray-200"}`}>
                                                <label
                                                    className="flex items-center justify-center w-8 text-orange-500 border-r border-gray-200 rounded-l cursor-pointer bg-orange-100"
                                                    onClick={getDebitoLocal}
                                                    title="Débito - Calcular restante en Bs"
                                                >
                                                    <span className="text-[10px] font-bold">Bs</span>
                                                </label>
                                                <div className="flex-1 flex flex-col h-full">
                                                    <div className="flex items-center h-full">
                                                        <div className="relative flex items-center w-full h-full">
                                                            {debito && (
                                                                <span className="absolute left-2 text-xs font-semibold text-orange-500">
                                                                    ≈${moneda(parseFloat(debito || 0) / getTasaPromedioCarrito())}
                                                                </span>
                                                            )}
                                                            <input
                                                                ref={debitoInputRef}
                                                                type="text"
                                                                className="flex-1 min-w-0 h-full px-2 py-1 text-lg font-bold text-orange-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right pl-10"
                                                                value={displayMonedaLive(debito)}
                                                                onChange={(e) => syncPago(formatMonedaLive(e.target.value), "Debito")}
                                                                onKeyDown={handlePaymentInputKeyDown}
                                                                onBlur={(e) => {
                                                                    if (debito && parseFloat(debito) > 0) {
                                                                        setTimeout(() => {
                                                                            const activeEl = document.activeElement;
                                                                            const isPaymentInput = activeEl?.getAttribute("data-efectivo") || 
                                                                                          activeEl === debitoRefInputRef.current;
                                                                            if (!isPaymentInput && debitoRefInputRef.current) {
                                                                                debitoRefInputRef.current.focus();
                                                                            }
                                                                        }, 100);
                                                                    }
                                                                }}
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <span className="px-1 text-sm font-bold text-orange-700">Bs</span>
                                                    </div>
                                                </div>
                                            </div>
                                            {/* Referencia Débito (obligatoria, exactamente 4 dígitos) */}
                                            <div className="relative w-28">
                                                <div className={`h-full border rounded ${debitoRefError ? "bg-red-50 border-red-500 ring-2 ring-red-300" : debitoRef.length === 4 ? "bg-white border-orange-300" : debito && debitoRef.length > 0 && debitoRef.length < 4 ? "bg-yellow-50 border-yellow-400" : "bg-white border-red-200"}`}>
                                                    <input
                                                        ref={debitoRefInputRef}
                                                        type="text"
                                                        className={`w-full h-full px-2 text-lg font-bold text-center bg-transparent border-0 focus:ring-0 focus:outline-none ${debitoRefError ? "placeholder-red-400" : "placeholder-gray-400"}`}
                                                        value={debitoRef}
                                                        onChange={(e) => {
                                                            setDebitoRef(e.target.value.replace(/[^0-9]/g, ''));
                                                            if (debitoRefError) setDebitoRefError(false); // Limpiar error al escribir
                                                        }}
                                                        onKeyDown={handlePaymentInputKeyDown}
                                                        placeholder={debitoRefError ? "Falta Ref." : "4 díg.*"}
                                                        maxLength={4}
                                                        minLength={4}
                                                        data-ref-input="true"
                                                    />
                                                </div>
                                                {debito && debitoRef.length > 0 && debitoRef.length < 4 && (
                                                    <span className="absolute -bottom-3 left-0 right-0 text-[8px] text-yellow-600 text-center">
                                                        Faltan {4 - debitoRef.length} díg.
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* ══════════ GRUPO EFECTIVO ══════════ */}
                                    <div className="flex-1 flex flex-wrap gap-2">
                                        {/* Efectivo USD */}
                                        <div className="flex-1 min-w-[120px] flex flex-col gap-1">
                                            <span className="text-[10px] font-medium text-green-600">Efectivo USD (E)</span>
                                            <div className={`flex border rounded ${efectivo_dolar != "" ? "bg-white border-green-300" : "bg-white border-gray-200"}`}>
                                                <label
                                                    className="flex items-center justify-center w-8 text-green-600 border-r border-gray-200 rounded-l cursor-pointer bg-green-100"
                                                    onClick={getEfectivoLocal}
                                                    title="Efectivo USD - Calcular restante en $"
                                                >
                                                    <span className="text-xs font-bold">$</span>
                                                </label>
                                                <input
                                                    ref={efectivoInputRef}
                                                    data-efectivo="usd"
                                                    type="text"
                                                    className="flex-1 min-w-0 px-2 py-1.5 text-lg font-bold text-green-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                    value={displayMonedaLive(efectivo_dolar)}
                                                    onChange={(e) => syncPago(formatMonedaLive(e.target.value), "EfectivoUSD")}
                                                    onKeyDown={handlePaymentInputKeyDown}
                                                    placeholder="0"
                                                />
                                                <span className="flex items-center px-1 text-sm font-bold text-green-700">$</span>
                                            </div>
                                        </div>
                                        {/* Efectivo Bs */}
                                        <div className="flex-1 min-w-[120px] flex flex-col gap-1">
                                            <span className="text-[10px] font-medium text-yellow-600">Efectivo Bolívares (B)</span>
                                            <div className={`flex border rounded ${efectivo_bs != "" ? "bg-white border-yellow-300" : "bg-white border-gray-200"}`}>
                                                <label
                                                    className="flex items-center justify-center w-8 text-yellow-600 border-r border-gray-200 rounded-l cursor-pointer bg-yellow-100"
                                                    onClick={getEfectivoBsLocal}
                                                    title={efectivo_bs ? `Efectivo Bs ≈$${moneda(parseFloat(efectivo_bs || 0) / getTasaPromedioCarrito())}` : "Efectivo Bs - Calcular restante en Bs"}
                                                >
                                                    <span className="text-[10px] font-bold">Bs</span>
                                                </label>
                                                <input
                                                    data-efectivo="bs"
                                                    type="text"
                                                    className="flex-1 min-w-0 px-2 py-1.5 text-lg font-bold text-yellow-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                    value={displayMonedaLive(efectivo_bs)}
                                                    onChange={(e) => syncPago(formatMonedaLive(e.target.value), "EfectivoBs")}
                                                    onKeyDown={handlePaymentInputKeyDown}
                                                    placeholder="0"
                                                />
                                                <span className="flex items-center px-1 text-sm font-bold text-yellow-700">Bs</span>
                                            </div>
                                        </div>
                                            {/* Efectivo Pesos (oculto por defecto) */}
                                            {showEfectivoPeso && (
                                                <div className={`flex-1 min-w-[120px] flex border rounded ${efectivo_peso != "" ? "bg-white border-amber-300" : "bg-white border-gray-200"}`}>
                                                    <label
                                                        className="flex items-center justify-center w-8 text-amber-600 border-r border-gray-200 rounded-l cursor-pointer bg-amber-100"
                                                        onClick={() => {
                                                            const tasaBs = getTasaPromedioCarrito();
                                                            const tasaCop = pedidoData?.items?.[0]?.tasa_cop || peso;
                                                            const totalPagos = (parseFloat(debito || 0) / tasaBs) + parseFloat(efectivo_dolar || 0) + (parseFloat(efectivo_bs || 0) / tasaBs) + (parseFloat(efectivo_peso || 0) / tasaCop) + parseFloat(transferencia || 0);
                                                            const restanteUSD = parseFloat(pedidoData?.clean_total || 0) - totalPagos;
                                                            if (restanteUSD > 0) setEfectivo_peso((restanteUSD * tasaCop).toFixed(0));
                                                        }}
                                                        title="Efectivo Pesos COP"
                                                    >
                                                        <span className="text-[10px] font-bold">COP</span>
                                                    </label>
                                                    <div className="flex-1 flex flex-col">
                                                        <div className="flex items-center">
                                                            <input
                                                                data-efectivo="cop"
                                                                type="text"
                                                                className="flex-1 min-w-0 px-2 py-1 text-lg font-bold text-amber-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                                value={displayMonedaLive(efectivo_peso)}
                                                                onChange={(e) => syncPago(formatMonedaLive(e.target.value), "EfectivoCOP")}
                                                                placeholder="0"
                                                            />
                                                            <span className="px-1 text-[10px] font-bold text-amber-700">COP</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => { setShowEfectivoPeso(false); setEfectivo_peso(""); }}
                                                                className="px-1 text-gray-400 hover:text-red-500"
                                                                title="Quitar COP"
                                                            >
                                                                <i className="text-xs fa fa-times"></i>
                                                            </button>
                                                        </div>
                                                        {efectivo_peso && (
                                                            <div className="px-2 pb-0.5 text-[10px] text-amber-600 text-right">≈${moneda(parseFloat(efectivo_peso || 0) / (pedidoData?.items?.[0]?.tasa_cop || peso))}</div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                    </div>

                                    {/* ══════════ GRUPO TRANSFERENCIA ══════════ */}
                                    <div className="flex flex-col gap-1">
                                        <span className="text-[10px] font-medium text-blue-600">Transferencia (T)</span>
                                        <div className={`flex-1 border rounded ${transferencia != "" ? "bg-white border-blue-300" : "bg-white border-gray-200"}`}>
                                            <div className="flex items-stretch">
                                                <label
                                                    className="flex items-center justify-center w-8 text-blue-500 border-r border-gray-200 rounded-l cursor-pointer bg-blue-100"
                                                    onClick={getTransferenciaLocal}
                                                    title="Transferencia"
                                                >
                                                    <i className="text-xs fa fa-dollar"></i>
                                                </label>
                                                <input
                                                    ref={transferenciaInputRef}
                                                    type="text"
                                                    className="flex-1 min-w-0 px-2 py-1.5 text-lg font-bold text-blue-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                    value={displayMonedaLive(transferencia)}
                                                    onChange={(e) => syncPago(formatMonedaLive(e.target.value), "Transferencia")}
                                                    onKeyDown={handlePaymentInputKeyDown}
                                                    placeholder="0"
                                                />
                                                <span className="flex items-center px-1 text-lg font-bold text-blue-700">$</span>
                                                <span
                                                    className="flex items-center px-2 text-blue-500 cursor-pointer hover:text-blue-600"
                                                    onClick={() => addRefPago("toggle", transferencia, "1")}
                                                    title="Agregar referencia"
                                                >
                                                    <i className="text-xs fa fa-plus-circle"></i>
                                                </span>
                                                <span
                                                    className="flex items-center px-2 py-1.5 text-xs font-medium text-white bg-blue-500 rounded-r cursor-pointer hover:bg-blue-600"
                                                    onClick={() => setPagoInBs((val) => syncPago(val, "Transferencia"))}
                                                    title="Convertir de Bs a $"
                                                >
                                                    Bs
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* ══════════ BIOPAGO (CONTROLADO POR CÓDIGO) ══════════ */}
                                    {SHOW_BIOPAGO && (
                                        <div className="flex flex-col gap-1">
                                            <span className="text-[10px] font-medium text-gray-500">Biopago</span>
                                            <div className={`flex-1 border rounded ${biopago != "" ? "bg-white border-purple-300" : "bg-white border-gray-200"}`}>
                                                <div className="flex items-stretch">
                                                    <label
                                                        className="flex items-center justify-center w-8 text-purple-500 border-r border-gray-200 rounded-l cursor-pointer bg-purple-100"
                                                        onClick={getBiopagoLocal}
                                                        title="Biopago"
                                                    >
                                                        <i className="text-xs fa fa-dollar"></i>
                                                    </label>
                                                    <input
                                                        ref={biopagoInputRef}
                                                        type="text"
                                                        className="flex-1 min-w-0 px-2 py-1.5 text-lg font-bold text-purple-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                        value={displayMonedaLive(biopago)}
                                                        onChange={(e) => syncPago(formatMonedaLive(e.target.value), "Biopago")}
                                                        placeholder="0"
                                                    />
                                                    <span className="flex items-center px-1 text-lg font-bold text-purple-700">$</span>
                                                    <span
                                                        className="flex items-center px-2 text-purple-500 cursor-pointer hover:text-purple-600"
                                                        onClick={() => addRefPago("toggle", biopago, "5")}
                                                        title="Agregar referencia"
                                                    >
                                                        <i className="text-xs fa fa-plus-circle"></i>
                                                    </span>
                                                    <span
                                                        className="flex items-center px-2 py-1.5 text-xs font-medium text-white bg-purple-500 rounded-r cursor-pointer hover:bg-purple-600"
                                                        onClick={() => setPagoInBs((val) => syncPago(val, "Biopago"))}
                                                        title="Convertir de Bs a $"
                                                    >
                                                        Bs
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* ══════════ CRÉDITO (OCULTO POR DEFECTO) ══════════ */}
                                    {showCredito && (
                                        <div className="flex flex-col gap-1">
                                            <span className="text-[10px] font-medium text-gray-500">Crédito</span>
                                            <div className={`flex-1 border rounded ${credito != "" ? "bg-white border-yellow-300" : "bg-white border-gray-200"}`}>
                                                <div className="flex items-stretch">
                                                    <label
                                                        className="flex items-center justify-center w-8 text-yellow-500 border-r border-gray-200 rounded-l cursor-pointer bg-yellow-100"
                                                        onClick={getCredito}
                                                        title="Crédito"
                                                    >
                                                        <i className="text-xs fa fa-dollar"></i>
                                                    </label>
                                                    <input
                                                        ref={creditoInputRef}
                                                        type="text"
                                                        className="flex-1 min-w-0 px-2 py-1.5 text-lg font-bold text-yellow-700 bg-transparent border-0 focus:ring-0 focus:outline-none text-right"
                                                        value={displayMonedaLive(credito)}
                                                        onChange={(e) => syncPago(formatMonedaLive(e.target.value), "Credito")}
                                                        placeholder="0"
                                                    />
                                                    <span className="flex items-center px-2 text-lg font-bold text-yellow-700">$</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setShowCredito(false); setCredito(""); }}
                                                        className="flex items-center px-2 text-gray-400 hover:text-red-500"
                                                        title="Quitar crédito"
                                                    >
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Barra de opciones: Auto resta, + Crédito, Vueltos, Impresora */}
                                    <div className="flex items-center justify-between gap-2 px-2 py-1 bg-gray-50 rounded border border-gray-200">
                                        {/* Auto resta */}
                                        <div 
                                            className="flex items-center gap-1 cursor-pointer hover:opacity-80"
                                            onClick={() => setautoCorrector(!autoCorrector)}
                                            title="Auto resta de pagos"
                                        >
                                            <span className="text-[10px] text-gray-500">Auto</span>
                                            <span className={`text-[10px] px-1.5 py-0.5 rounded ${autoCorrector ? "bg-green-100 text-green-600" : "bg-gray-200 text-gray-400"}`}>
                                                {autoCorrector ? "ON" : "OFF"}
                                            </span>
                                        </div>

                                        {/* + Crédito */}
                                        {!showCredito && (
                                            <button
                                                type="button"
                                                onClick={() => setShowCredito(true)}
                                                className="flex items-center gap-1 text-[10px] text-yellow-600 hover:text-yellow-700"
                                            >
                                                <i className="fa fa-plus"></i> Crédito
                                            </button>
                                        )}

                                        {/* + COP */}
                                        {!showEfectivoPeso && (
                                            <button
                                                type="button"
                                                onClick={() => setShowEfectivoPeso(true)}
                                                className="flex items-center gap-1 text-[10px] text-amber-600 hover:text-amber-700"
                                            >
                                                <i className="fa fa-plus"></i> COP
                                            </button>
                                        )}

                                        {/* Vueltos */}
                                        {/* <button
                                            onClick={() => setShowVueltosSection(!showVueltosSection)}
                                            className="flex items-center gap-1 text-[10px] text-gray-500 hover:text-orange-600"
                                        >
                                            <i className={`fa fa-chevron-${showVueltosSection ? "down" : "right"}`}></i>
                                            Vueltos
                                        </button> */}

                                        {/* Impresora */}
                                        <div className="flex items-center gap-1">
                                            <i className="text-[10px] text-orange-500 fa fa-print"></i>
                                            <select
                                                className="text-[10px] text-gray-600 bg-transparent border-none focus:outline-none cursor-pointer"
                                                value={selectprinter}
                                                onChange={(e) => setselectprinter(e.target.value)}
                                            >
                                                {[...Array(10)].map((_, i) => (
                                                    <option key={i + 1} value={i + 1}>C{i + 1}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                ) : null}

                                {refPago && refPago.length > 0 && (
                                    <div className="mb-3 bg-white border border-gray-200 rounded-lg !overflow-visible">
                                        <div className="px-3 py-2 overflow-hidden border-b border-orange-100 rounded-t-lg bg-orange-50">
                                            <div className="flex items-center justify-between">
                                                <h6 className="flex items-center text-sm font-medium text-gray-800">
                                                    <i className="mr-2 text-xs text-orange-500 fa fa-credit-card"></i>
                                                    Referencias Bancarias
                                                </h6>
                                                <button
                                                    className="px-2 py-1 text-xs font-medium text-orange-600 transition-colors bg-white border border-orange-200 rounded hover:bg-orange-50"
                                                    onClick={addRetencionesPago}
                                                >
                                                    <i className="mr-1 fa fa-plus"></i>
                                                    Retención
                                                </button>
                                            </div>
                                        </div>
                                        <div className="p-2 space-y-2">
                                            {refPago.map((e) => (
                                                <div
                                                    key={e.id}
                                                    className={`relative bg-white border rounded-lg p-1.5 transition-all duration-200 ${
                                                        validatingRef === e.id
                                                            ? "border-blue-300 bg-blue-50 "
                                                            : "border-gray-200 hover:border-gray-300 hover:"
                                                    }`}
                                                >
                                                    {/* Loader overlay */}
                                                    {validatingRef === e.id && (
                                                        <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-blue-50/80 backdrop-blur-sm">
                                                            <div className="flex items-center space-x-2">
                                                                <div className="w-4 h-4 border-2 border-blue-600 rounded-full border-t-transparent animate-spin"></div>
                                                                <span className="text-xs font-medium text-blue-700">
                                                                    Validando...
                                                                </span>
                                                            </div>
                                                        </div>
                                                    )}

                                                    <div className="flex flex-col justify-between gap-3 sm:items-center sm:flex-row">
                                                        {/* Info de la referencia */}
                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-center justify-between">
                                                                {/* Detalles de la referencia */}
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="text-sm text-gray-900 truncate">
                                                                        {e.descripcion ? (
                                                                            <b>
                                                                                #
                                                                                {
                                                                                    e.descripcion
                                                                                }
                                                                            </b>
                                                                        ) : (
                                                                            <b>
                                                                                Sin
                                                                                referencia
                                                                            </b>
                                                                        )}{" "}
                                                                        {e.banco ? (
                                                                            <span>
                                                                                <b>
                                                                                    {
                                                                                        e.banco
                                                                                    }
                                                                                </b>
                                                                            </span>
                                                                        ) : null}
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        CI:{" "}
                                                                        {
                                                                            e.cedula
                                                                        }
                                                                    </p>
                                                                </div>

                                                                {/* Monto como badge a la derecha */}
                                                                {(e.tipo == 1 &&
                                                                    e.monto !=
                                                                        0) ||
                                                                (e.tipo == 2 &&
                                                                    e.monto !=
                                                                        0) ||
                                                                (e.tipo == 5 &&
                                                                    e.monto !=
                                                                        0) ? (
                                                                    <div className="ml-3">
                                                                        <span className="px-2 py-1 text-xs font-medium text-green-700 border !border-green-300 bg-green-100 rounded">
                                                                            {e.tipo ==
                                                                                1 &&
                                                                                "T. "}
                                                                            {e.tipo ==
                                                                                2 &&
                                                                                "D. "}
                                                                            {e.tipo ==
                                                                                5 &&
                                                                                "B. "}
                                                                            {moneda(
                                                                                e.monto
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ) : null}
                                                            </div>
                                                        </div>

                                                        {/* Estado y acciones */}
                                                        <div className="flex items-center gap-2">
                                                            {/* Estado */}
                                                            {e.estatus ===
                                                            "aprobada" ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-800 border !border-green-300 bg-green-100 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></div>
                                                                    Aprobada
                                                                </span>
                                                            ) : e.estatus ===
                                                              "rechazada" ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium text-red-800 border !border-red-300 bg-red-100 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></div>
                                                                    Rechazada
                                                                </span>
                                                            ) : e.estatus ===
                                                              "pendiente" ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium border !border-amber-300 bg-amber-100 text-amber-800 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1.5"></div>
                                                                    Pendiente
                                                                </span>
                                                            ) : e.estatus ===
                                                              "error" ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium text-red-800 border !border-red-300 bg-red-100 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></div>
                                                                    Error
                                                                </span>
                                                            ) : // Fallback para referencias sin estatus
                                                            !e.descripcion ||
                                                              e.descripcion.trim() ===
                                                                  "" ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-600 border !border-gray-300 bg-gray-100 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1.5"></div>
                                                                    Sin validar
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-800 border !border-green-300 bg-green-100 rounded">
                                                                    <div className="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></div>
                                                                    Validada
                                                                </span>
                                                            )}

                                                            {/* Botones de acción */}
                                                            <div className="flex items-center gap-1">
                                                                {(e.estatus ===
                                                                    "pendiente" ||
                                                                    e.estatus ===
                                                                        "error" ||
                                                                    !e.descripcion ||
                                                                    e.descripcion.trim() ===
                                                                        "") && (
                                                                    <button
                                                                        className="flex items-center justify-center w-6 h-6 text-blue-600 transition-colors bg-blue-100 rounded hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                                                        onClick={() =>
                                                                            sendRefToMerchant(
                                                                                e.id
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            validatingRef ===
                                                                            e.id
                                                                        }
                                                                        title={
                                                                            e.estatus ===
                                                                            "error"
                                                                                ? "Reintentar Validación"
                                                                                : "Validar Referencia"
                                                                        }
                                                                    >
                                                                        <i
                                                                            className={`text-xs fa ${
                                                                                e.estatus ===
                                                                                "error"
                                                                                    ? "fa-refresh"
                                                                                    : "fa-paper-plane"
                                                                            }`}
                                                                        ></i>
                                                                    </button>
                                                                )}
                                                                <button
                                                                    className="flex items-center justify-center w-6 h-6 text-red-600 transition-colors bg-red-100 rounded hover:bg-red-200"
                                                                    data-id={
                                                                        e.id
                                                                    }
                                                                    onClick={
                                                                        delRefPago
                                                                    }
                                                                    title="Eliminar"
                                                                >
                                                                    <i className="text-xs fa fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                            {retenciones &&
                                                retenciones.length > 0 &&
                                                retenciones.map((retencion) => (
                                                    <div
                                                        key={retencion.id}
                                                        className="flex items-center justify-between px-3 py-2 bg-orange-50 hover:bg-orange-100"
                                                    >
                                                        <div className="flex items-center space-x-2">
                                                            <span className="px-2 py-0.5 text-xs font-medium bg-orange-200 text-orange-800 rounded">
                                                                Retención
                                                            </span>
                                                            <span className="text-sm text-gray-700">
                                                                {
                                                                    retencion.descripcion
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center space-x-2">
                                                            <span className="px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 rounded">
                                                                -
                                                                {moneda(
                                                                    retencion.monto
                                                                )}
                                                            </span>
                                                            <button
                                                                className="p-1 text-red-500 transition-colors rounded hover:bg-red-50"
                                                                onClick={() =>
                                                                    delRetencionPago(
                                                                        retencion.id
                                                                    )
                                                                }
                                                                title="Eliminar"
                                                            >
                                                                <i className="text-xs fa fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}

                                {showVueltosSection && (
                                    <div className="mb-3 bg-white border border-gray-200 rounded-lg">
                                        <div className="flex items-center justify-between px-3 py-1.5 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                                            <span className="text-[10px] font-medium text-gray-600">Cálculo de Vueltos</span>
                                            <div className="flex space-x-1">
                                                <button className="px-1.5 py-0.5 text-[10px] text-gray-500 border border-gray-300 rounded hover:bg-gray-100" onClick={setVueltodolar}>$</button>
                                                <button className="px-1.5 py-0.5 text-[10px] text-gray-500 border border-gray-300 rounded hover:bg-gray-100" onClick={setVueltobs}>BS</button>
                                                <button className="px-1.5 py-0.5 text-[10px] text-gray-500 border border-gray-300 rounded hover:bg-gray-100" onClick={setVueltocop}>COP</button>
                                            </div>
                                        </div>
                                        <div className="p-3">
                                            <div className="space-y-2">
                                                <div className="grid grid-cols-3 gap-2">
                                                    <div className="flex">
                                                        <span className="px-2 py-1 text-xs text-gray-600 bg-gray-100 border border-r-0 border-gray-300 rounded-l">
                                                            $
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={
                                                                recibido_dolar
                                                            }
                                                            onChange={(e) =>
                                                                changeRecibido(
                                                                    e.target
                                                                        .value,
                                                                    "recibido_dolar"
                                                                )
                                                            }
                                                            placeholder="$"
                                                        />
                                                    </div>
                                                    <div className="flex">
                                                        <span className="px-2 py-1 text-xs text-gray-600 bg-gray-100 border border-r-0 border-gray-300 rounded-l">
                                                            BS
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={recibido_bs}
                                                            onChange={(e) =>
                                                                changeRecibido(
                                                                    e.target
                                                                        .value,
                                                                    "recibido_bs"
                                                                )
                                                            }
                                                            placeholder="BS"
                                                        />
                                                    </div>
                                                    <div className="flex">
                                                        <span className="px-2 py-1 text-xs text-gray-600 bg-gray-100 border border-r-0 border-gray-300 rounded-l">
                                                            COP
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={recibido_cop}
                                                            onChange={(e) =>
                                                                changeRecibido(
                                                                    e.target
                                                                        .value,
                                                                    "recibido_cop"
                                                                )
                                                            }
                                                            placeholder="COP"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="grid grid-cols-3 gap-2">
                                                    <div className="flex">
                                                        <span
                                                            className="px-2 py-1 text-xs text-orange-600 bg-orange-100 border border-r-0 border-orange-300 rounded-l cursor-pointer"
                                                            onClick={
                                                                setVueltodolar
                                                            }
                                                        >
                                                            $
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-orange-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={cambio_dolar}
                                                            onChange={(e) =>
                                                                syncCambio(
                                                                    e.target
                                                                        .value,
                                                                    "Dolar"
                                                                )
                                                            }
                                                            placeholder="$"
                                                        />
                                                    </div>
                                                    <div className="flex">
                                                        <span
                                                            className="px-2 py-1 text-xs text-orange-600 bg-orange-100 border border-r-0 border-orange-300 rounded-l cursor-pointer"
                                                            onClick={
                                                                setVueltobs
                                                            }
                                                        >
                                                            BS
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-orange-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={cambio_bs}
                                                            onChange={(e) =>
                                                                syncCambio(
                                                                    e.target
                                                                        .value,
                                                                    "Bolivares"
                                                                )
                                                            }
                                                            placeholder="BS"
                                                        />
                                                    </div>
                                                    <div className="flex">
                                                        <span
                                                            className="px-2 py-1 text-xs text-orange-600 bg-orange-100 border border-r-0 border-orange-300 rounded-l cursor-pointer"
                                                            onClick={
                                                                setVueltocop
                                                            }
                                                        >
                                                            COP
                                                        </span>
                                                        <input
                                                            type="text"
                                                            className="flex-1 px-2 py-1 text-xs border border-orange-300 rounded-r focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                                            value={cambio_cop}
                                                            onChange={(e) =>
                                                                syncCambio(
                                                                    e.target
                                                                        .value,
                                                                    "Pesos"
                                                                )
                                                            }
                                                            placeholder="COP"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="flex items-center justify-between pt-2 border-t border-gray-200">
                                                    <div className="flex items-center">
                                                        <small className="mr-2 text-xs text-gray-500">
                                                            Recibido:
                                                        </small>
                                                        <span className="text-xs font-bold text-green-600">
                                                            {recibido_tot}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center">
                                                        <small className="mr-2 text-xs text-gray-500">
                                                            Vuelto:
                                                        </small>
                                                        <span className="text-xs font-bold text-green-600">
                                                            {sumCambio()}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {auth(1) && (
                                    <div className="mb-3 bg-white ">
                                        <div className="px-3 py-2 bg-white border border-gray-200 rounded">
                                            <div className="flex items-center justify-between">
                                                <button
                                                    onClick={() =>
                                                        setShowTransferirSection(
                                                            !showTransferirSection
                                                        )
                                                    }
                                                    className="flex items-center text-xs font-medium text-gray-700 transition-colors hover:text-blue-600"
                                                >
                                                    <i
                                                        className={`mr-2 text-xs fa fa-chevron-${
                                                            showTransferirSection
                                                                ? "down"
                                                                : "right"
                                                        } transition-transform`}
                                                    ></i>
                                                    Transferir a Sucursal
                                                </button>
                                            </div>
                                        </div>
                                        {showTransferirSection && (
                                            <div className="flex items-center gap-2 p-2">
                                                <button
                                                    className="px-2 py-1.5 text-xs text-blue-600 transition-colors bg-blue-50 border border-blue-200 rounded hover:bg-blue-100"
                                                    onClick={getSucursales}
                                                    title="Buscar sucursales"
                                                >
                                                    <i className="fa fa-search"></i>
                                                </button>
                                                <select
                                                    className="flex-1 px-2 py-1.5 text-xs bg-white border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-400"
                                                    value={transferirpedidoa}
                                                    onChange={(e) =>
                                                        settransferirpedidoa(
                                                            e.target.value
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Seleccionar Sucursal
                                                    </option>
                                                    {sucursalesCentral.map(
                                                        (e) => (
                                                            <option
                                                                key={e.id}
                                                                value={e.id}
                                                            >
                                                                {e.nombre}
                                                            </option>
                                                        )
                                                    )}
                                                </select>
                                                <button
                                                    className="px-3 py-1.5 text-xs font-semibold text-white transition-colors bg-blue-500 rounded hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                                    onClick={setexportpedido}
                                                    disabled={
                                                        !transferirpedidoa
                                                    }
                                                >
                                                    <i className="mr-1.5 fa fa-paper-plane"></i>
                                                    Transferir
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="mb-3">
                                {showHeaderAndMenu && (
                                    <div className="flex flex-wrap gap-1.5 justify-center mt-2">
                                        {editable && (
                                            <>
                                                <button
                                                    className="flex items-center gap-1 px-2 py-1 text-xs text-green-700 transition-colors border !border-green-300 rounded bg-green-100 hover:bg-green-200"
                                                    onClick={facturar_pedido}
                                                    title="Facturar e Imprimir (Enter)"
                                                >
                                                    <i className="fa fa-paper-plane"></i>
                                                    <span>Fact.</span>
                                                </button>
                                                <button
                                                    className="flex items-center gap-1 px-2 py-1 text-xs text-blue-700 transition-colors border !border-blue-300 rounded bg-blue-100 hover:bg-blue-200"
                                                    onClick={
                                                        facturar_e_imprimir
                                                    }
                                                    title="Solo Facturar"
                                                >
                                                    <i className="fa fa-save"></i>
                                                    <span>Guard.</span>
                                                </button>
                                                <button
                                                    className="flex items-center gap-1 px-2 py-1 text-xs text-orange-700 transition-colors border !border-orange-300 rounded bg-orange-100 hover:bg-orange-200"
                                                    onClick={() =>
                                                        setToggleAddPersona(
                                                            true
                                                        )
                                                    }
                                                    title="Cliente (F2)"
                                                >
                                                    <i className="fa fa-user"></i>
                                                    <span>Client.</span>
                                                </button>
                                                <button
                                                    className="flex items-center gap-1 px-2 py-1 text-xs text-purple-700 transition-colors border !border-purple-300 rounded bg-purple-100 hover:bg-purple-200"
                                                    onClick={() =>
                                                        toggleImprimirTicket()
                                                    }
                                                    title="Imprimir (F3)"
                                                >
                                                    <i className="fa fa-print"></i>
                                                    <span>Impr.</span>
                                                </button>
                                            </>
                                        )}

                                        <button
                                            className="flex items-center gap-1 px-2 py-1 text-xs text-indigo-700 transition-colors border !border-indigo-300 rounded bg-indigo-100 hover:bg-indigo-200"
                                            onClick={() => viewReportPedido()}
                                            title="Ver Pedido (F4)"
                                        >
                                            <i className="fa fa-eye"></i>
                                            <span>Ver</span>
                                        </button>

                                        {editable && (
                                            <>
                                                <button
                                                    className="flex items-center gap-1 px-2 py-1 text-xs text-gray-700 transition-colors border !border-gray-300 rounded bg-gray-100 hover:bg-gray-200"
                                                    onClick={() =>
                                                        sendReciboFiscal()
                                                    }
                                                    title="Recibo Fiscal"
                                                >
                                                    <i className="fa fa-file-text"></i>
                                                    <span>Recib.</span>
                                                </button>
                                            </>
                                        )}
                                        <button
                                            className="flex items-center gap-1 px-2 py-1 text-xs text-amber-700 transition-colors border !border-amber-300 rounded bg-amber-100 hover:bg-amber-200"
                                            onClick={() => printBultos()}
                                            title="Imprimir Bultos"
                                        >
                                            <i className="fa fa-box"></i>
                                            <span>Bultos</span>
                                        </button>

                                        {pedidoData.fiscal == 1 && (
                                            <button
                                                className="flex items-center gap-1 px-2 py-1 text-xs text-red-700 transition-colors border !border-red-300 rounded bg-red-100 hover:bg-red-200"
                                                title="Nota de Crédito"
                                                onClick={() =>
                                                    sendNotaCredito()
                                                }
                                            >
                                                <i className="fa fa-undo"></i>
                                                <span>N.Créd.</span>
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        // Empty State
                        <div
                            className="p-5 text-center d-flex flex-column align-items-center justify-content-center"
                            style={{ height: "100%" }}
                        >
                            <div className="mb-4">
                                <div
                                    className="mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center"
                                    style={{ width: "120px", height: "120px" }}
                                >
                                    <i
                                        className="fa fa-shopping-cart text-muted"
                                        style={{ fontSize: "3.5rem" }}
                                    ></i>
                                </div>
                            </div>

                            <div className="mb-4">
                                <h3 className="mb-2 text-muted fw-normal">
                                    Ningún pedido seleccionado
                                </h3>
                                <p
                                    className="mb-0 text-muted"
                                    style={{
                                        maxWidth: "300px",
                                        lineHeight: "1.5",
                                    }}
                                >
                                    Selecciona un pedido de la lista lateral
                                    para ver los detalles de pago y procesar la
                                    facturación.
                                </p>
                            </div>

                            <div className="gap-2 d-flex flex-column">
                                <div className="d-flex align-items-center text-muted small mb-3">
                                    <i className="fa fa-lightbulb-o me-2 text-warning"></i>
                                    <span>
                                        Haz clic en cualquier pedido para
                                        comenzar
                                    </span>
                                </div>

                                {/* Atajos de teclado */}
                                <div
                                    className="mt-4 p-3 bg-light rounded"
                                    style={{ maxWidth: "500px" }}
                                >
                                    <h6 className="mb-3 text-center fw-bold text-dark">
                                        <i className="fa fa-keyboard-o me-2 text-info"></i>
                                        Atajos del Teclado
                                    </h6>
                                    <div className="row g-2 small">
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    F1
                                                </kbd>
                                                <span className="text-muted">
                                                    Iniciar nueva factura
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    ESC
                                                </kbd>
                                                <span className="text-muted">
                                                    Buscar producto
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    CTRL
                                                </kbd>
                                                <span className="text-muted">
                                                    Cancelar búsqueda
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    ↑ / ↓
                                                </kbd>
                                                <span className="text-muted">
                                                    Moverse entre productos
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    TAB
                                                </kbd>
                                                <span className="text-muted">
                                                    Navegar entre facturas
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    SHIFT+TAB
                                                </kbd>
                                                <span className="text-muted">
                                                    Navegar inversa
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    CTRL+↵
                                                </kbd>
                                                <span className="text-muted">
                                                    Guardar y facturar
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    F2
                                                </kbd>
                                                <span className="text-muted">
                                                    Agregar cliente
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    D
                                                </kbd>
                                                <span className="text-muted">
                                                    Débito
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    T
                                                </kbd>
                                                <span className="text-muted">
                                                    Transferencia
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    E
                                                </kbd>
                                                <span className="text-muted">
                                                    Efectivo
                                                </span>
                                            </div>
                                        </div>
                                        <div className="col-6">
                                            <div className="d-flex align-items-start mb-2">
                                                <kbd
                                                    className="me-2"
                                                    style={{
                                                        fontSize: "0.75rem",
                                                    }}
                                                >
                                                    B
                                                </kbd>
                                                <span className="text-muted">
                                                    Biopago
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal de Código de Aprobación */}
            {showCodigoModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Código de Aprobación
                                </h3>
                                <button
                                    onClick={cerrarModalCodigo}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <i className="fa fa-times"></i>
                                </button>
                            </div>

                            <div className="mb-6">
                                <div className="p-4 mb-4 text-center bg-gray-100 rounded-lg">
                                    <p className="mb-2 text-sm text-gray-600">
                                        Código generado:
                                    </p>
                                    <p className="font-mono text-2xl font-bold text-blue-600">
                                        {codigoGenerado}
                                    </p>
                                    <p className="mt-2 text-xs text-gray-500">
                                        Solo una persona autorizada puede
                                        descifrar este código
                                    </p>
                                </div>

                                <div className="mb-4">
                                    <label className="block mb-2 text-sm font-medium text-gray-700">
                                        Ingrese la clave de aprobación:
                                    </label>
                                    <input
                                        type="password"
                                        value={claveIngresada}
                                        onChange={(e) =>
                                            setClaveIngresada(e.target.value)
                                        }
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Ingrese la clave"
                                        onKeyPress={(e) => {
                                            if (e.key === "Enter") {
                                                validarCodigo();
                                            }
                                        }}
                                        autoFocus
                                    />
                                </div>

                                <div className="p-3 text-xs text-gray-500 border-l-4 border-yellow-400 rounded bg-yellow-50">
                                    <p className="mb-1 font-medium text-yellow-800">
                                        Instrucciones:
                                    </p>
                                    <p>
                                        La persona autorizada debe generar la
                                        clave correcta basándose en el código
                                        mostrado arriba.
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-3">
                                <button
                                    onClick={cerrarModalCodigo}
                                    className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={validarCodigo}
                                    className="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    disabled={!claveIngresada.trim()}
                                >
                                    Validar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Pedido Original para Devoluciones */}
            {showModalPedidoOriginal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    <i className="mr-2 text-orange-500 fa fa-exchange-alt"></i>
                                    Devolución - Factura Original
                                </h3>
                                <button
                                    onClick={cerrarModalPedidoOriginal}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <i className="fa fa-times"></i>
                                </button>
                            </div>

                            <div className="mb-6">
                                <div className="p-4 mb-4 text-sm border-l-4 border-orange-400 rounded bg-orange-50">
                                    <p className="font-medium text-orange-800">
                                        Para procesar una devolución, debe indicar el número de la factura original.
                                    </p>
                                    <p className="mt-1 text-orange-700">
                                        Solo podrá devolver productos que existan en esa factura y en las cantidades disponibles.
                                    </p>
                                </div>

                                <div className="mb-4">
                                    <label className="block mb-2 text-sm font-medium text-gray-700">
                                        Número de Pedido/Factura Original:
                                    </label>
                                    <input
                                        ref={pedidoOriginalInputRef}
                                        type="number"
                                        value={pedidoOriginalInput}
                                        onChange={(e) =>
                                            setPedidoOriginalInput(e.target.value)
                                        }
                                        className="w-full px-3 py-2 text-lg font-bold text-center border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        placeholder="Ej: 12345"
                                        onKeyPress={(e) => {
                                            if (e.key === "Enter") {
                                                asignarPedidoOriginal();
                                            }
                                        }}
                                        autoFocus
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end space-x-3">
                                <button
                                    onClick={cerrarModalPedidoOriginal}
                                    className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={asignarPedidoOriginal}
                                    className="px-4 py-2 text-white bg-orange-600 rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500"
                                    disabled={!pedidoOriginalInput.trim()}
                                >
                                    <i className="mr-2 fa fa-check"></i>
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Información de Factura Original */}
            {showModalInfoFacturaOriginal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="w-full max-w-5xl mx-4 bg-white rounded-lg shadow-xl max-h-[80vh] flex flex-col">
                        <div className="p-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    <i className="mr-2 text-orange-500 fa fa-file-invoice"></i>
                                    Factura Original #{pedidoData?.isdevolucionOriginalid || pedidoOriginalAsignado}
                                </h3>
                                <button
                                    onClick={() => setShowModalInfoFacturaOriginal(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <i className="fa fa-times"></i>
                                </button>
                            </div>
                            <p className="mt-1 text-xs text-gray-500">
                                Productos y precios al momento de la venta original
                            </p>
                        </div>

                        <div className="flex-1 p-4 overflow-y-auto">
                            {loadingFacturaOriginal ? (
                                <div className="flex items-center justify-center py-8">
                                    <i className="mr-2 text-2xl text-orange-500 fa fa-spinner fa-spin"></i>
                                    <span className="text-gray-600">Cargando...</span>
                                </div>
                            ) : itemsFacturaOriginal.length > 0 ? (
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-xs font-medium text-left text-gray-500 uppercase">Producto</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Cant. Original</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Devuelto</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Disponible</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Precio Unit.</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Desc.</th>
                                            <th className="px-3 py-2 text-xs font-medium text-right text-gray-500 uppercase">Tasa</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {itemsFacturaOriginal.map((item, index) => (
                                            <tr key={index} className={item.cantidad_disponible > 0 ? "" : "bg-gray-100 text-gray-400"}>
                                                <td className="px-3 py-2">
                                                    <div className="font-medium text-gray-900">{item.descripcion}</div>
                                                    <div className="text-xs text-gray-500">{item.codigo_barras}</div>
                                                </td>
                                                <td className="px-3 py-2 text-right font-medium">{item.cantidad_original}</td>
                                                <td className="px-3 py-2 text-right text-red-600">{item.cantidad_devuelta > 0 ? `-${item.cantidad_devuelta}` : "0"}</td>
                                                <td className="px-3 py-2 text-right font-bold text-green-600">{item.cantidad_disponible}</td>
                                                <td className="px-3 py-2 text-right font-medium">${moneda(item.precio_unitario)}</td>
                                                <td className="px-3 py-2 text-right text-purple-600">{item.descuento > 0 ? `${item.descuento}%` : "-"}</td>
                                                <td className="px-3 py-2 text-right text-gray-600">{item.tasa ? moneda(item.tasa) : "-"}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <div className="py-8 text-center text-gray-500">
                                    <i className="mb-2 text-3xl text-gray-300 fa fa-box-open"></i>
                                    <p>No hay items disponibles para devolución</p>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-between gap-3 p-4 border-t border-gray-200">
                            <button
                                onClick={eliminarAsignacionFacturaOriginal}
                                className="px-4 py-2 text-red-700 bg-red-100 border border-red-300 rounded-md hover:bg-red-200"
                            >
                                <i className="mr-2 fa fa-unlink"></i>
                                Eliminar asignación
                            </button>
                            <button
                                onClick={() => setShowModalInfoFacturaOriginal(false)}
                                className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Panel Lateral Calculadora/Vueltos */}
            {showCalculadora && (
                <>
                    {/* Overlay oscuro */}
                    <div 
                        className="fixed inset-0 bg-black/30 z-[60] transition-opacity duration-300"
                        onClick={() => setShowCalculadora(false)}
                    />
                    
                    {/* Panel lateral derecho - responsive */}
                    <div 
                        ref={calculadoraRef}
                        className="fixed top-0 right-0 h-full w-full sm:w-96 md:w-[420px] lg:w-[480px] max-w-full bg-white shadow-2xl z-[70] transform transition-transform duration-300 ease-out flex flex-col overflow-hidden"
                        style={{ animation: 'slideInRight 0.3s ease-out' }}
                    >
                        {/* Header con totales del pedido - fondo blanco */}
                        <div className="px-4 py-3 bg-white border-b-2 border-gray-200">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold text-gray-500">TOTAL PEDIDO</span>
                                <button 
                                    onClick={() => setShowCalculadora(false)} 
                                    className="w-8 h-8 flex items-center justify-center text-gray-500 bg-gray-100 rounded-full hover:bg-red-100 hover:text-red-500 transition-colors"
                                    title="Cerrar (ESC)"
                                >
                                    <i className="fa fa-times"></i>
                                </button>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="flex items-baseline gap-1">
                                    <span className="text-sm text-gray-500">REF</span>
                                    <span className="text-2xl font-bold text-green-600">${moneda(pedidoData?.clean_total)}</span>
                                </div>
                                <div className="flex items-baseline gap-1">
                                    <span className="text-sm text-gray-500">Bs</span>
                                    <span className="text-2xl font-bold text-orange-500">{moneda(pedidoData?.bs)}</span>
                                </div>
                                {user?.sucursal === "elorza" && (
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-xs text-gray-400">COP</span>
                                        <span className="text-lg font-bold text-blue-500">{moneda((pedidoData?.clean_total || 0) * getTasaCopCalc(), 0)}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                        
                        <div className="flex-1 flex flex-col p-3 gap-3 overflow-y-auto">
                            {/* Tasas de referencia */}
                            <div className="flex justify-between text-[10px] text-gray-500 bg-gray-100 rounded px-2 py-1">
                                <span>Tasa Bs: <b className="text-orange-600">{moneda(getTasaBsCalc(), 2)}</b></span>
                                <span>Tasa COP: <b className="text-blue-600">{moneda(getTasaCopCalc(), 0)}</b></span>
                            </div>

                            {/* ========== SECCIÓN 1: LO QUE PAGÓ EL CLIENTE ========== */}
                            <div className="bg-blue-50 rounded-lg p-3 border border-blue-200 relative">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-xs font-bold text-blue-700">
                                        <i className="fa fa-hand-holding-usd mr-1"></i>LO QUE PAGÓ EL CLIENTE
                                    </span>
                                    <button 
                                        onClick={calcLimpiarTodo}
                                        className="text-[9px] text-red-500 hover:text-red-700 font-medium px-1.5 py-0.5 bg-white rounded"
                                    >
                                        <i className="fa fa-trash mr-0.5"></i>Limpiar
                                    </button>
                                </div>
                                
                                {/* Campos de pago del cliente */}
                                <div className="space-y-2">
                                    {/* Fila 1: Efectivo USD y Efectivo Bs */}
                                    <div className="grid grid-cols-2 gap-2">
                                        {/* Efectivo USD */}
                                        <div>
                                            <label className="text-[9px] font-bold text-green-600 block mb-0.5">
                                                <i className="fa fa-dollar-sign mr-0.5"></i>EFECTIVO $
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-green-500 font-bold text-sm">$</span>
                                                <input
                                                    type="text"
                                                    value={calcPagoEfectivoUsd}
                                                    onChange={(e) => setCalcPagoEfectivoUsd(e.target.value.replace(/[^0-9.]/g, ''))}
                                                    className="w-full pl-6 pr-6 py-1.5 font-bold text-gray-900 border border-green-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-green-400"
                                                    placeholder="0.00"
                                                />
                                                {calcPagoEfectivoUsd && (
                                                    <button onClick={() => setCalcPagoEfectivoUsd("")} className="absolute right-1 top-1/2 -translate-y-1/2 text-green-400 hover:text-red-500 text-xs">
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                )}
                                            </div>
                                            {/* Referencia en Bs */}
                                            {parseFloat(calcPagoEfectivoUsd) > 0 && (
                                                <div className="text-sm font-bold text-orange-600 mt-1 text-right bg-orange-50 px-2 py-0.5 rounded">
                                                    = Bs {moneda((parseFloat(calcPagoEfectivoUsd) || 0) * getTasaBsCalc(), 2)}
                                                </div>
                                            )}
                                        </div>
                                        {/* Efectivo Bs */}
                                        <div>
                                            <label className="text-[9px] font-bold text-orange-600 block mb-0.5">
                                                <i className="fa fa-money-bill mr-0.5"></i>EFECTIVO Bs
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-orange-500 font-bold text-xs">Bs</span>
                                                <input
                                                    type="text"
                                                    value={calcPagoEfectivoBs}
                                                    onChange={(e) => setCalcPagoEfectivoBs(e.target.value.replace(/[^0-9.]/g, ''))}
                                                    className="w-full pl-7 pr-6 py-1.5 font-bold text-gray-900 border border-orange-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-orange-400"
                                                    placeholder="0.00"
                                                />
                                                {calcPagoEfectivoBs && (
                                                    <button onClick={() => setCalcPagoEfectivoBs("")} className="absolute right-1 top-1/2 -translate-y-1/2 text-orange-400 hover:text-red-500 text-xs">
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                )}
                                            </div>
                                            {/* Referencia en USD */}
                                            {parseFloat(calcPagoEfectivoBs) > 0 && (
                                                <div className="text-sm font-bold text-green-600 mt-1 text-right bg-green-50 px-2 py-0.5 rounded">
                                                    = ${moneda((parseFloat(calcPagoEfectivoBs) || 0) / getTasaBsCalc(), 2)}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Fila 2: Débito y Transferencia */}
                                    <div className="grid grid-cols-2 gap-2">
                                        {/* Débito (Bs) */}
                                        <div>
                                            <label className="text-[9px] font-bold text-purple-600 block mb-0.5">
                                                <i className="fa fa-credit-card mr-0.5"></i>DÉBITO (Bs)
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-purple-500 font-bold text-xs">Bs</span>
                                                <input
                                                    type="text"
                                                    value={calcPagoDebito}
                                                    onChange={(e) => setCalcPagoDebito(e.target.value.replace(/[^0-9.]/g, ''))}
                                                    className="w-full pl-7 pr-6 py-1.5 font-bold text-gray-900 border border-purple-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-purple-400"
                                                    placeholder="0.00"
                                                />
                                                {calcPagoDebito && (
                                                    <button onClick={() => setCalcPagoDebito("")} className="absolute right-1 top-1/2 -translate-y-1/2 text-purple-400 hover:text-red-500 text-xs">
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                )}
                                            </div>
                                            {/* Referencia en USD */}
                                            {parseFloat(calcPagoDebito) > 0 && (
                                                <div className="text-sm font-bold text-green-600 mt-1 text-right bg-green-50 px-2 py-0.5 rounded">
                                                    = ${moneda((parseFloat(calcPagoDebito) || 0) / getTasaBsCalc(), 2)}
                                                </div>
                                            )}
                                        </div>
                                        {/* Transferencia (USD) */}
                                        <div>
                                            <label className="text-[9px] font-bold text-cyan-600 block mb-0.5">
                                                <i className="fa fa-exchange-alt mr-0.5"></i>TRANSF. $
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-cyan-500 font-bold text-sm">$</span>
                                                <input
                                                    type="text"
                                                    value={calcPagoTransferencia}
                                                    onChange={(e) => setCalcPagoTransferencia(e.target.value.replace(/[^0-9.]/g, ''))}
                                                    className="w-full pl-6 pr-6 py-1.5 font-bold text-gray-900 border border-cyan-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-cyan-400"
                                                    placeholder="0.00"
                                                />
                                                {calcPagoTransferencia && (
                                                    <button onClick={() => setCalcPagoTransferencia("")} className="absolute right-1 top-1/2 -translate-y-1/2 text-cyan-400 hover:text-red-500 text-xs">
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                )}
                                            </div>
                                            {/* Referencia en Bs */}
                                            {parseFloat(calcPagoTransferencia) > 0 && (
                                                <div className="text-sm font-bold text-orange-600 mt-1 text-right bg-orange-50 px-2 py-0.5 rounded">
                                                    = Bs {moneda((parseFloat(calcPagoTransferencia) || 0) * getTasaBsCalc(), 2)}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Fila 3: Efectivo COP (solo Elorza) */}
                                    {user?.sucursal === "elorza" && (
                                        <div>
                                            <label className="text-[9px] font-bold text-blue-600 block mb-0.5">
                                                <i className="fa fa-coins mr-0.5"></i>EFECTIVO COP
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-blue-500 font-bold text-xs">COP</span>
                                                <input
                                                    type="text"
                                                    value={calcPagoEfectivoCop}
                                                    onChange={(e) => setCalcPagoEfectivoCop(e.target.value.replace(/[^0-9.]/g, ''))}
                                                    className="w-full pl-10 pr-6 py-1.5 font-bold text-gray-900 border border-blue-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-blue-400"
                                                    placeholder="0"
                                                />
                                                {calcPagoEfectivoCop && (
                                                    <button onClick={() => setCalcPagoEfectivoCop("")} className="absolute right-1 top-1/2 -translate-y-1/2 text-blue-400 hover:text-red-500 text-xs">
                                                        <i className="fa fa-times"></i>
                                                    </button>
                                                )}
                                            </div>
                                            {/* Referencia en USD */}
                                            {parseFloat(calcPagoEfectivoCop) > 0 && (
                                                <div className="text-sm font-bold text-green-600 mt-1 text-right bg-green-50 px-2 py-0.5 rounded">
                                                    = ${moneda((parseFloat(calcPagoEfectivoCop) || 0) / getTasaCopCalc(), 2)}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                                
                                {/* Total pagado */}
                                <div className="mt-2 pt-2 border-t border-blue-200 flex justify-between items-center">
                                    <span className="text-xs text-blue-600">Total pagado:</span>
                                    <span className="text-lg font-bold text-blue-700">${moneda(calcTotalPagado(), 2)}</span>
                                </div>

                            </div>

                            {/* Botón importar a pagos */}
                            <button 
                                onClick={calcImportarTodoAPago}
                                className="py-2 text-sm font-bold text-white bg-indigo-500 rounded-lg hover:bg-indigo-600 transition-colors"
                                title="Importar montos a los campos de pago del sistema"
                            >
                                <i className="fa fa-download mr-2"></i>IMPORTAR A PAGOS
                            </button>
                        </div>
                        
                        {/* ========== SECCIÓN 2: VUELTO / FALTA ========== */}
                        <div className={`px-3 py-3 border-t-2 ${calcDiferencia() >= 0 ? "bg-green-50 border-green-300" : "bg-red-50 border-red-300"}`}>
                            <div className="flex items-center justify-between mb-2">
                                <span className={`text-sm font-bold px-3 py-1 rounded-full ${calcDiferencia() >= 0 ? "bg-green-200 text-green-700" : "bg-red-200 text-red-700"}`}>
                                    <i className={`fa ${calcDiferencia() >= 0 ? "fa-hand-holding-usd" : "fa-exclamation-triangle"} mr-1`}></i>
                                    {calcDiferencia() >= 0 ? "VUELTO A DAR" : "FALTA POR COBRAR"}
                                </span>
                                <span className="text-lg font-bold text-gray-700">${moneda(Math.abs(calcDiferencia()), 2)}</span>
                            </div>
                            
                            {calcDiferencia() > 0 ? (
                                <>
                                    {/* Inputs con labels clickeables */}
                                    <div className="grid grid-cols-3 gap-2 mb-2">
                                        <div>
                                            <button 
                                                onClick={() => calcAutoVuelto("usd")}
                                                className="w-full text-sm font-bold text-green-700 bg-green-100 hover:bg-green-200 rounded-t px-2 py-1.5 transition-colors border-2 border-b-0 border-green-400"
                                            >
                                                <i className="fa fa-dollar-sign mr-1"></i>DÓLARES
                                            </button>
                                            <input
                                                type="text"
                                                value={calcVueltoUsd}
                                                onChange={(e) => handleVueltoChange("usd", e.target.value.replace(/[^0-9.]/g, ''))}
                                                className="w-full px-2 py-2 text-lg font-bold text-gray-900 border-2 border-green-400 rounded-b bg-white focus:outline-none focus:ring-2 focus:ring-green-500 text-center"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <div>
                                            <button 
                                                onClick={() => calcAutoVuelto("bs")}
                                                className="w-full text-sm font-bold text-orange-700 bg-orange-100 hover:bg-orange-200 rounded-t px-2 py-1.5 transition-colors border-2 border-b-0 border-orange-400"
                                            >
                                                <i className="fa fa-money-bill mr-1"></i>BOLÍVARES
                                            </button>
                                            <input
                                                type="text"
                                                value={calcVueltoBs}
                                                onChange={(e) => handleVueltoChange("bs", e.target.value.replace(/[^0-9.]/g, ''))}
                                                className="w-full px-2 py-2 text-lg font-bold text-gray-900 border-2 border-orange-400 rounded-b bg-white focus:outline-none focus:ring-2 focus:ring-orange-500 text-center"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <div>
                                            <button 
                                                onClick={() => calcAutoVuelto("cop")}
                                                className="w-full text-sm font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-t px-2 py-1.5 transition-colors border-2 border-b-0 border-blue-400"
                                            >
                                                <i className="fa fa-coins mr-1"></i>PESOS
                                            </button>
                                            <input
                                                type="text"
                                                value={calcVueltoCop}
                                                onChange={(e) => handleVueltoChange("cop", e.target.value.replace(/[^0-9.]/g, ''))}
                                                className="w-full px-2 py-2 text-lg font-bold text-gray-900 border-2 border-blue-400 rounded-b bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                                                placeholder="0"
                                            />
                                        </div>
                                    </div>
                                    
                                    {/* Indicador de estado */}
                                    <div className={`text-center text-xs font-bold py-1 rounded ${calcVueltoPendiente() > 0.01 ? "bg-red-100 text-red-600" : "bg-green-100 text-green-600"}`}>
                                        {calcVueltoPendiente() > 0.01 ? (
                                            <>Falta asignar: ${moneda(calcVueltoPendiente(), 2)}</>
                                        ) : (
                                            <><i className="fa fa-check mr-1"></i>Vuelto completo</>
                                        )}
                                    </div>
                                </>
                            ) : calcDiferencia() < 0 ? (
                                /* Falta por cobrar */
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="text-center p-2 rounded-lg bg-red-100">
                                        <div className="text-[9px] text-gray-500 font-medium">DÓLARES</div>
                                        <div className="text-xl font-bold text-red-600">${moneda(Math.abs(calcDiferencia()), 2)}</div>
                                    </div>
                                    <div className="text-center p-2 rounded-lg bg-red-100">
                                        <div className="text-[9px] text-gray-500 font-medium">BOLÍVARES</div>
                                        <div className="text-xl font-bold text-red-600">{moneda(Math.abs(calcDiferencia()) * getTasaBsCalc(), 2)}</div>
                                    </div>
                                    <div className="text-center p-2 rounded-lg bg-red-100">
                                        <div className="text-[9px] text-gray-500 font-medium">PESOS</div>
                                        <div className="text-lg font-bold text-red-600">{moneda(Math.abs(calcDiferencia()) * getTasaCopCalc(), 0)}</div>
                                    </div>
                                </div>
                            ) : (
                                /* Exacto */
                                <div className="text-center py-3">
                                    <i className="fa fa-check-circle text-3xl text-green-500 mb-1"></i>
                                    <div className="text-sm font-bold text-green-600">MONTO EXACTO</div>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}

            {/* Estilos para animación */}
            <style>{`
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
            `}</style>
        </div>
    ) : null;
}
