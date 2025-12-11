import "../../css/modal.css";

import { useHotkeys } from "react-hotkeys-hook";
import { Html5QrcodeScanner } from "html5-qrcode";

import { useState, useEffect, useRef } from "react";
import { cloneDeep } from "lodash";
import db from "../database/database";
import { useApp } from "../contexts/AppContext";

import { moneda, number } from "./assets";

import Configuracion from "../components/configuracion";

import Pagar from "../components/pagar";
import ModalAddListProductosInterno from "../components/modalAddListProductosInterno";

import Header from "../components/header";
import Panelcentrodeacopio from "../components/panelcentrodeacopio";

import Pedidos from "../components/pedidos";

import Credito from "../components/credito";
import Vueltos from "../components/vueltos";
import Clientes from "../components/clientes";

import PedidosCentralComponent from "../components/pedidosCentral";

import Cierres from "../components/cierre";
import CierreV2 from "../components/CierreV2";
import Inventario from "../components/inventario";

import Seleccionar from "../components/seleccionar";
import Ventas from "../components/ventas";

import ViewPedidoVendedor from "../components/viewPedidoVendedor";

import ModaladdPersona from "./ModaladdPersona";
import Modalconfigcredito from "./Modalconfigcredito";
import PagarMain from "./pagarMain";
import ModalRefPago from "./modalRefPago";

import Submenuinventario from "./Submenuinventario";
import ModalSelectFactura from "./modalSelectFactura";
import ModalSelectProductoNewFact from "./ModalSelectProductoNewFact";

import Proveedores from "./proveedores";
import Modalsetclaveadmin from "./modalsetclaveadmin";
import ModalFormatoGarantia from "./modalFormatoGarantia";

import GarantiaModule from "./GarantiaModule";
import InventarioCiclicoModule from "./InventarioCiclicoModule";
import PresupuestoMain from "./presupuestoMain";

export default function Facturar({
    user,
    notificar,
    setLoading,
    showHeaderAndMenu,
    setShowHeaderAndMenu,
}) {
    // Usar el context para acceder a searchCompleted
    const { searchCompleted, setSearchCompleted } = useApp();

    const [inputqinterno, setinputqinterno] = useState("");

    const [buscarDevolucionhistorico, setbuscarDevolucionhistorico] =
        useState("");
    const [
        productosDevolucionSelecthistorico,
        setproductosDevolucionSelecthistorico,
    ] = useState([]);

    const [presupuestocarrito, setpresupuestocarrito] = useState([]);
    const [selectprinter, setselectprinter] = useState(1);
    const [monedaToPrint, setmonedaToPrint] = useState("bs");

    const [dropprintprice, setdropprintprice] = useState(false);

    const [num, setNum] = useState(15);
    const [showOptionQMain, setshowOptionQMain] = useState(false);
    const [itemCero, setItemCero] = useState(true);
    const [qProductosMain, setQProductosMain] = useState("");

    const [orderColumn, setOrderColumn] = useState("cantidad");
    const [orderBy, setOrderBy] = useState("desc");

    const [inputaddCarritoFast, setinputaddCarritoFast] = useState("");

    const [view, setView] = useState("pagar");
    const [selectItem, setSelectItem] = useState(null);
    const [pedidoSelect, setPedidoSelect] = useState(null);
    const [pedidoData, setPedidoData] = useState({});

    const [dolar, setDolar] = useState("");
    const [peso, setPeso] = useState("");

    const [cantidad, setCantidad] = useState("");
    const [numero_factura, setNumero_factura] = useState("nuevo");

    const [onlyVueltos, setOnlyVueltos] = useState(0);

    const [showinputaddCarritoFast, setshowinputaddCarritoFast] =
        useState(false);

    const [productos, setProductos] = useState([]);

    const [productosInventario, setProductosInventario] = useState([]);

    const [qBuscarInventario, setQBuscarInventario] = useState("");
    const [indexSelectInventario, setIndexSelectInventario] = useState(null);

    const [inpInvbarras, setinpInvbarras] = useState("");
    const [inpInvcantidad, setinpInvcantidad] = useState("");
    const [inpInvalterno, setinpInvalterno] = useState("");
    const [inpInvunidad, setinpInvunidad] = useState("UND");
    const [inpInvcategoria, setinpInvcategoria] = useState("24");
    const [inpInvdescripcion, setinpInvdescripcion] = useState("");
    const [inpInvbase, setinpInvbase] = useState("");
    const [inpInvventa, setinpInvventa] = useState("");
    const [inpInviva, setinpInviva] = useState("0");
    const [inpInvporcentaje_ganancia, setinpInvporcentaje_ganancia] =
        useState("0");

    const [inpInvLotes, setinpInvLotes] = useState([]);

    const [inpInvid_proveedor, setinpInvid_proveedor] = useState("");
    const [inpInvid_marca, setinpInvid_marca] = useState("");
    const [inpInvid_deposito, setinpInvid_deposito] = useState("");

    const [depositosList, setdepositosList] = useState([]);
    const [marcasList, setmarcasList] = useState([]);

    const [Invnum, setInvnum] = useState(25);
    const [InvorderColumn, setInvorderColumn] = useState("id");
    const [InvorderBy, setInvorderBy] = useState("desc");

    const [proveedordescripcion, setproveedordescripcion] = useState("");
    const [proveedorrif, setproveedorrif] = useState("");
    const [proveedordireccion, setproveedordireccion] = useState("");
    const [proveedortelefono, setproveedortelefono] = useState("");

    const [subViewInventario, setsubViewInventario] =
        useState("Submenuinventario");

    const [indexSelectProveedores, setIndexSelectProveedores] = useState(null);

    const [qBuscarProveedor, setQBuscarProveedor] = useState("");

    const [proveedoresList, setProveedoresList] = useState([]);

    const [pedidoList, setPedidoList] = useState([]);
    const [showMisPedido, setshowMisPedido] = useState(true);

    const [orderbycolumpedidos, setorderbycolumpedidos] = useState("id");
    const [orderbyorderpedidos, setorderbyorderpedidos] = useState("desc");

    const [debito, setDebito] = useState("");
    const [debitoRef, setDebitoRef] = useState(""); // Referencia obligatoria del débito
    const [debitoRefError, setDebitoRefError] = useState(false); // Error de referencia faltante
    
    // Estados para modal POS débito físico
    const [showModalPosDebito, setShowModalPosDebito] = useState(false);
    const [posCedulaTitular, setPosCedulaTitular] = useState("");
    const [posTipoCuenta, setPosTipoCuenta] = useState("CORRIENTE");
    const [posMontoDebito, setPosMontoDebito] = useState("");
    const [posLoading, setPosLoading] = useState(false);
    const [posPendingCallback, setPosPendingCallback] = useState(null);
    const [forzarReferenciaManual, setForzarReferenciaManual] = useState(false); // Para bypassear PINPAD
    const [posRespuesta, setPosRespuesta] = useState(null); // {mensaje, exito} para mostrar en modal
    const [posTransaccionesAprobadas, setPosTransaccionesAprobadas] = useState([]); // Array de transacciones aprobadas del pedido actual [{monto, cedula, referencia, tipoCuenta}]
    const [posMontoTotalOriginal, setPosMontoTotalOriginal] = useState(""); // Monto total original del débito
    // Mapa global de transacciones POS por pedido: { pedidoId: [{monto, cedula, referencia, ...}] }
    const [posTransaccionesPorPedido, setPosTransaccionesPorPedido] = useState({});
    
    const [efectivo, setEfectivo] = useState("");
    const [transferencia, setTransferencia] = useState("");
    const [credito, setCredito] = useState("");
    const [vuelto, setVuelto] = useState("");
    const [biopago, setBiopago] = useState("");
    const [lastPaymentMethodCalled, setLastPaymentMethodCalled] = useState(null);
    
    // Estados para efectivo dividido por moneda
    const [efectivo_bs, setEfectivo_bs] = useState("");
    const [efectivo_dolar, setEfectivo_dolar] = useState("");
    const [efectivo_peso, setEfectivo_peso] = useState("");
    const [tipo_referenciapago, settipo_referenciapago] = useState("1");
    const [descripcion_referenciapago, setdescripcion_referenciapago] =
        useState("");
    const [monto_referenciapago, setmonto_referenciapago] = useState("");

    const [cedula_referenciapago, setcedula_referenciapago] = useState("");
    const [telefono_referenciapago, settelefono_referenciapago] = useState("");

    const [banco_referenciapago, setbanco_referenciapago] = useState("0108");
    const [togglereferenciapago, settogglereferenciapago] = useState("");

    const [refrenciasElecData, setrefrenciasElecData] = useState([]);
    const [togleeReferenciasElec, settogleeReferenciasElec] = useState(false);

    const [viewconfigcredito, setviewconfigcredito] = useState(false);
    const [fechainiciocredito, setfechainiciocredito] = useState("");
    const [fechavencecredito, setfechavencecredito] = useState("");
    const [formatopagocredito, setformatopagocredito] = useState(1);
    const [datadeudacredito, setdatadeudacredito] = useState({});

    const [descuento, setDescuento] = useState(0);

    const [ModaladdproductocarritoToggle, setModaladdproductocarritoToggle] =
        useState(false);

    const [toggleAddPersona, setToggleAddPersona] = useState(false);
    const [personas, setPersona] = useState([]);

    const [pedidos, setPedidos] = useState([]);

    const [movimientosCaja, setMovimientosCaja] = useState([]);
    const [movimientos, setMovimientos] = useState([]);

    const [tipobusquedapedido, setTipoBusqueda] = useState("fact");

    const [tipoestadopedido, setTipoestadopedido] = useState("todos");

    const [busquedaPedido, setBusquedaPedido] = useState("");
    const [fecha1pedido, setFecha1pedido] = useState("");
    const [fecha2pedido, setFecha2pedido] = useState("");

    const [caja_usd, setCaja_usd] = useState("");
    const [caja_cop, setCaja_cop] = useState("");
    const [caja_bs, setCaja_bs] = useState("");
    const [caja_punto, setCaja_punto] = useState("");
    const [caja_biopago, setcaja_biopago] = useState("");

    const [dejar_usd, setDejar_usd] = useState("");
    const [dejar_cop, setDejar_cop] = useState("");
    const [dejar_bs, setDejar_bs] = useState("");

    const [lotespuntototalizar, setlotespuntototalizar] = useState([]);
    const [biopagostotalizar, setbiopagostotalizar] = useState([]);

    const [cierre, setCierre] = useState({});
    const [totalizarcierre, setTotalizarcierre] = useState(false);

    const [today, setToday] = useState("");

    const [fechaCierre, setFechaCierre] = useState("");

    const [viewCierre, setViewCierre] = useState("cuadre");
    const [toggleDetallesCierre, setToggleDetallesCierre] = useState(0);

    const [filterMetodoPagoToggle, setFilterMetodoPagoToggle] =
        useState("todos");

    const [notaCierre, setNotaCierre] = useState("");

    const [qDeudores, setQDeudores] = useState("");
    const [orderbycolumdeudores, setorderbycolumdeudores] = useState("saldo");
    const [orderbyorderdeudores, setorderbyorderdeudores] = useState("asc");
    const [limitdeudores, setlimitdeudores] = useState(25);

    const [deudoresList, setDeudoresList] = useState([]);
    const [cierres, setCierres] = useState({});

    const [selectDeudor, setSelectDeudor] = useState(null);

    const [tipo_pago_deudor, setTipo_pago_deudor] = useState("3");
    const [monto_pago_deudor, setMonto_pago_deudor] = useState("");

    const [detallesDeudor, setDetallesDeudor] = useState([]);

    const [counterListProductos, setCounterListProductos] = useState(0);

    const [countListInter, setCountListInter] = useState(0);
    const [countListPersoInter, setCountListPersoInter] = useState(0);

    const [viewCaja, setViewCaja] = useState(false);

    const [movCajadescripcion, setMovCajadescripcion] = useState("");
    const [movCajatipo, setMovCajatipo] = useState(1);
    const [movCajacategoria, setMovCajacategoria] = useState(5);
    const [movCajamonto, setMovCajamonto] = useState("");
    const [movCajaFecha, setMovCajaFecha] = useState("");

    const refaddfast = useRef(null);

    const tbodyproductosref = useRef(null);
    const inputBuscarInventario = useRef(null);

    const tbodyproducInterref = useRef(null);
    const tbodypersoInterref = useRef(null);

    const inputCantidadCarritoref = useRef(null);
    const inputbusquedaProductosref = useRef(null);
    const inputmodaladdpersonacarritoref = useRef(null);
    const inputaddcarritointernoref = useRef(null);

    const refinputaddcarritofast = useRef(null);

    const [typingTimeout, setTypingTimeout] = useState(0);

    const [fechaMovimientos, setFechaMovimientos] = useState("");

    const [showModalMovimientos, setShowModalMovimientos] = useState(false);
    const [productosDevolucionSelect, setProductosDevolucionSelect] = useState(
        []
    );

    const [idMovSelect, setIdMovSelect] = useState("nuevo");

    const [showModalFacturas, setshowModalFacturas] = useState(false);

    const [facturas, setfacturas] = useState([]);

    const [factqBuscar, setfactqBuscar] = useState("");
    const [factqBuscarDate, setfactqBuscarDate] = useState("");
    const [factOrderBy, setfactOrderBy] = useState("id");
    const [factOrderDescAsc, setfactOrderDescAsc] = useState("desc");
    const [factsubView, setfactsubView] = useState("buscar");
    const [factSelectIndex, setfactSelectIndex] = useState(null);
    const [factInpid_proveedor, setfactInpid_proveedor] = useState("");
    const [factInpnumfact, setfactInpnumfact] = useState("");
    const [factInpdescripcion, setfactInpdescripcion] = useState("");
    const [factInpmonto, setfactInpmonto] = useState("");
    const [factInpfechavencimiento, setfactInpfechavencimiento] = useState("");
    const [factInpImagen, setfactInpImagen] = useState("");

    const [factInpnumnota, setfactInpnumnota] = useState("");
    const [factInpsubtotal, setfactInpsubtotal] = useState("");
    const [factInpdescuento, setfactInpdescuento] = useState("");
    const [factInpmonto_gravable, setfactInpmonto_gravable] = useState("");
    const [factInpmonto_exento, setfactInpmonto_exento] = useState("");
    const [factInpiva, setfactInpiva] = useState("");
    const [factInpfechaemision, setfactInpfechaemision] = useState("");
    const [factInpfecharecepcion, setfactInpfecharecepcion] = useState("");
    const [factInpnota, setfactInpnota] = useState("");

    const [factInpestatus, setfactInpestatus] = useState(0);

    const [qBuscarCliente, setqBuscarCliente] = useState("");
    const [numclientesCrud, setnumclientesCrud] = useState(25);

    const [clientesCrud, setclientesCrud] = useState([]);
    const [indexSelectCliente, setindexSelectCliente] = useState(null);

    const [clienteInpidentificacion, setclienteInpidentificacion] =
        useState("");
    const [clienteInpnombre, setclienteInpnombre] = useState("");
    const [clienteInpcorreo, setclienteInpcorreo] = useState("");
    const [clienteInpdireccion, setclienteInpdireccion] = useState("");
    const [clienteInptelefono, setclienteInptelefono] = useState("");
    const [clienteInpestado, setclienteInpestado] = useState("");
    const [clienteInpciudad, setclienteInpciudad] = useState("");

    const [sumPedidosArr, setsumPedidosArr] = useState([]);

    const [controlefecQ, setcontrolefecQ] = useState("");
    const [controlefecQDesde, setcontrolefecQDesde] = useState("");
    const [controlefecQHasta, setcontrolefecQHasta] = useState("");
    const [controlefecQCategoria, setcontrolefecQCategoria] = useState("");

    const [controlefecResponsable, setcontrolefecResponsable] = useState("30");
    const [controlefecAsignar, setcontrolefecAsignar] = useState("43");
    const [openModalNuevoEfectivo, setopenModalNuevoEfectivo] = useState(false);

    const [controlefecData, setcontrolefecData] = useState([]);
    const [controlefecSelectGeneral, setcontrolefecSelectGeneral] = useState(1);
    const [controlefecSelectUnitario, setcontrolefecSelectUnitario] =
        useState(null);
    const [controlefecNewConcepto, setcontrolefecNewConcepto] = useState("");
    const [controlefecNewMonto, setcontrolefecNewMonto] = useState("");
    const [controlefecNewMontoMoneda, setcontrolefecNewMontoMoneda] =
        useState("");

    const [controlefecid_persona, setcontrolefecid_persona] = useState("");
    const [controlefecid_alquiler, setcontrolefecid_alquiler] = useState("");
    const [controlefecid_proveedor, setcontrolefecid_proveedor] = useState("");

    const [controlefecNewCategoria, setcontrolefecNewCategoria] = useState("");
    const [controlefecNewDepartamento, setcontrolefecNewDepartamento] =
        useState("");

    const [qFallas, setqFallas] = useState("");
    const [orderCatFallas, setorderCatFallas] = useState("proveedor");
    const [orderSubCatFallas, setorderSubCatFallas] = useState("todos");
    const [ascdescFallas, setascdescFallas] = useState("");
    const [fallas, setfallas] = useState([]);

    const [autoCorrector, setautoCorrector] = useState(true);

    const [pedidosCentral, setpedidoCentral] = useState([]);
    const [indexPedidoCentral, setIndexPedidoCentral] = useState(null);

    const [showaddpedidocentral, setshowaddpedidocentral] = useState(false);
    const [permisoExecuteEnter, setpermisoExecuteEnter] = useState(true);

    const [guardar_usd, setguardar_usd] = useState("");
    const [guardar_cop, setguardar_cop] = useState("");
    const [guardar_bs, setguardar_bs] = useState("");

    const [tipo_accionCierre, settipo_accionCierre] = useState("");

    const [ventasData, setventasData] = useState([]);

    const [fechaventas, setfechaventas] = useState("");

    const [pedidosFast, setpedidosFast] = useState([]);

    const [sucursaldata, setSucursaldata] = useState("");

    const [billete1, setbillete1] = useState("");
    const [billete5, setbillete5] = useState("");
    const [billete10, setbillete10] = useState("");
    const [billete20, setbillete20] = useState("");
    const [billete50, setbillete50] = useState("");
    const [billete100, setbillete100] = useState("");

    const [pathcentral, setpathcentral] = useState("");
    const [mastermachines, setmastermachines] = useState([]);

    const [usuariosData, setusuariosData] = useState([]);
    const [usuarioNombre, setusuarioNombre] = useState("");
    const [usuarioUsuario, setusuarioUsuario] = useState("");
    const [usuarioRole, setusuarioRole] = useState("");
    const [usuarioClave, setusuarioClave] = useState("");
    const [usuarioIpPinpad, setusuarioIpPinpad] = useState("");

    const [qBuscarUsuario, setQBuscarUsuario] = useState("");
    const [indexSelectUsuarios, setIndexSelectUsuarios] = useState(null);

    const [toggleClientesBtn, settoggleClientesBtn] = useState(false);

    const [modViewInventario, setmodViewInventario] = useState("list");

    const [loteIdCarrito, setLoteIdCarrito] = useState(null);
    const refsInpInvList = useRef(null);

    const [valheaderpedidocentral, setvalheaderpedidocentral] =
        useState("12340005ARAMCAL");
    const [valbodypedidocentral, setvalbodypedidocentral] = useState(
        "12341238123456123456123451234123712345612345612345123412361234561234561234512341235123456123456123451234123412345612345612345"
    );

    const [fechaGetCierre, setfechaGetCierre] = useState("");
    const [fechaGetCierre2, setfechaGetCierre2] = useState("");

    const [tipoUsuarioCierre, settipoUsuarioCierre] = useState("");

    const [CajaFuerteEntradaCierreDolar, setCajaFuerteEntradaCierreDolar] =
        useState("0");
    const [CajaFuerteEntradaCierreCop, setCajaFuerteEntradaCierreCop] =
        useState("0");
    const [CajaFuerteEntradaCierreBs, setCajaFuerteEntradaCierreBs] =
        useState("0");
    const [CajaChicaEntradaCierreDolar, setCajaChicaEntradaCierreDolar] =
        useState("0");
    const [CajaChicaEntradaCierreCop, setCajaChicaEntradaCierreCop] =
        useState("0");
    const [CajaChicaEntradaCierreBs, setCajaChicaEntradaCierreBs] =
        useState("0");

    const [lote1punto, setlote1punto] = useState("");
    const [montolote1punto, setmontolote1punto] = useState("");
    const [lote2punto, setlote2punto] = useState("");
    const [montolote2punto, setmontolote2punto] = useState("");
    const [serialbiopago, setserialbiopago] = useState("");

    const [puntolote1banco, setpuntolote1banco] = useState("");
    const [puntolote2banco, setpuntolote2banco] = useState("");

    const [modFact, setmodFact] = useState("factura");

    // 1234123812345612345612345
    // 1234123712345612345612345
    // 1234123612345612345612345
    // 1234123512345612345612345
    // 1234123412345612345612345
    // 12341234ARAMCAL

    const [socketUrl, setSocketUrl] = useState("");
    const [tareasCentral, settareasCentral] = useState([]);

    const [categoriaEstaInve, setcategoriaEstaInve] = useState("");
    const [fechaQEstaInve, setfechaQEstaInve] = useState("");
    const [fechaFromEstaInve, setfechaFromEstaInve] = useState("");
    const [fechaToEstaInve, setfechaToEstaInve] = useState("");
    const [orderByEstaInv, setorderByEstaInv] = useState("desc");
    const [orderByColumEstaInv, setorderByColumEstaInv] =
        useState("cantidadtotal");
    const [dataEstaInven, setdataEstaInven] = useState([]);

    const [tipopagoproveedor, settipopagoproveedor] = useState("");
    const [montopagoproveedor, setmontopagoproveedor] = useState("");
    const [pagosproveedor, setpagosproveedor] = useState([]);

    const [busquedaAvanazadaInv, setbusquedaAvanazadaInv] = useState(false);

    const [selectSucursalCentral, setselectSucursalCentral] = useState(null);
    const [sucursalesCentral, setsucursalesCentral] = useState([]);
    const [
        inventarioModifiedCentralImport,
        setinventarioModifiedCentralImport,
    ] = useState([]);

    const [
        parametrosConsultaFromsucursalToCentral,
        setparametrosConsultaFromsucursalToCentral,
    ] = useState({ numinventario: 25, novinculados: "novinculados" });
    const [subviewpanelcentroacopio, setsubviewpanelcentroacopio] =
        useState("");
    const [
        inventarioSucursalFromCentral,
        setdatainventarioSucursalFromCentral,
    ] = useState([]);
    const [
        estadisticasinventarioSucursalFromCentral,
        setestadisticasinventarioSucursalFromCentral,
    ] = useState({});

    const [fallaspanelcentroacopio, setfallaspanelcentroacopio] = useState([]);
    const [estadisticaspanelcentroacopio, setestadisticaspanelcentroacopio] =
        useState([]);
    const [gastospanelcentroacopio, setgastospanelcentroacopio] = useState([]);
    const [cierrespanelcentroacopio, setcierrespanelcentroacopio] = useState(
        []
    );
    const [diadeventapanelcentroacopio, setdiadeventapanelcentroacopio] =
        useState([]);
    const [tasaventapanelcentroacopio, settasaventapanelcentroacopio] =
        useState([]);

    const [busqAvanzInputs, setbusqAvanzInputs] = useState({
        codigo_barras: "",
        codigo_proveedor: "",
        id_proveedor: "",
        id_categoria: "",
        unidad: "",
        descripcion: "",
        iva: "",
        precio_base: "",
        precio: "",
        cantidad: "",
    });

    const [replaceProducto, setreplaceProducto] = useState({
        este: null,
        poreste: null,
    });
    const [transferirpedidoa, settransferirpedidoa] = useState("");

    const bancos = [
        { value: "", text: "--Seleccione Banco--" },

        // Para hacer selección múltiple de cursores (multi-cursor) en la mayoría de los editores de código:
        // - En VSCode: Mantén presionada la tecla "Alt" (Windows/Linux) o "Option" (Mac) y haz clic donde quieras agregar un cursor adicional.
        //   También puedes usar "Ctrl + Alt + Flecha abajo/arriba" (Windows/Linux) o "Option + Command + Flecha abajo/arriba" (Mac) para agregar cursores en líneas consecutivas.
        // - En Sublime Text: Mantén presionada "Ctrl" (Windows/Linux) o "Command" (Mac) y haz clic para agregar cursores.
        // - En JetBrains (WebStorm, PyCharm, etc): Mantén "Alt" (Windows/Linux) o "Option" (Mac) y haz clic.

        
        {value: "0134", text: "0134 BANESCO (TRANSFERENCIAS)" },
        {value: "0134 BANESCO TITANIO", text: "0134 BANESCO TITANIO" },
        {value: "0134 BANESCO ARABITO PUNTOS 9935",text: "0134 BANESCO ARABITO PUNTOS 9935",},
        {value: "0134 BANESCO TITANIO EL HENAOUI 2765",text: "0134 BANESCO TITANIO EL HENAOUI 2765",},
        
        {value: "0108", text: "0108 PROVINCIAL" },
        {value: "0108 PROVINCIAL AMER 9483",text: "0108 PROVINCIAL AMER 9483",},
        
        {value: "0191", text: "0191 BANCO NACIONAL DE CRÉDITO BNC" },
        {value: "0191 BNC EL HENAOUI TITANIO 7402",text: "0191 BNC EL HENAOUI TITANIO 7402",},
        {value: "0191 BNC AMER PERSONAL 9783",text: "0191 BNC AMER PERSONAL 9783 (TRANSFERENCIAS EL HENAOUI TITANIO)",},
        
        {value:"0171 ACTIVO ARABITO 0194", text:"0171 ACTIVO ARABITO 0194"},
        {value:"0171 ACTIVO EL HENAOUI 0176", text:"0171 ACTIVO EL HENAOUI 0176"},
        {value:"0171 ACTIVO TITANIO 0229", text:"0171 ACTIVO TITANIO 0229"},
        
        {value: "0105", text: "0105 MERCANTIL" },
        {value: "0105 MERCANTIL TITANIO 6669", text: "0105 MERCANTIL TITANIO 6669" },
        {value: "0105 MERCANTIL EL HENAOUI TITANIO 7894", text: "0105 MERCANTIL EL HENAOUI TITANIO 7894" },
        
        {value: "0114", text: "0114 BANCO DEL CARIBE" },
        {value: "0114 BANCARIBE TITANIO FP 6042",text: "0114 BANCARIBE TITANIO FP 6042",},
        
        {value: "0102", text: "0102 BANCO DE VENEZUELA" },
        {value: "0151", text: "0151 BANCO FONDO COMÚN BFC" },
        {value: "0175", text: "0175 BICENTENARIO" },
        {value: "0115", text: "0115 EXTERIOR" },

        {value: "ZELLE", text: "ZELLE" },
        {value: "BINANCE", text: "BINANCE" },
        {value: "AirTM", text: "AirTM" },
    ];

    const getControlEfec = () => {
        db.getControlEfec({
            controlefecQ,
            controlefecQDesde,
            controlefecQHasta,
            controlefecQCategoria,
            controlefecSelectGeneral,
        }).then((res) => {
            setcontrolefecData(res.data);
        });
    };
    const delCaja = (id) => {
        db.delCaja({
            id,
        }).then((res) => {
            notificar(res);
            getControlEfec();
        });
    };
    const verificarMovPenControlEfecTRANFTRABAJADOR = () => {
        if (confirm("Confirme")) {
            db.verificarMovPenControlEfecTRANFTRABAJADOR({}).then((res) => {
                getControlEfec();
                notificar(res.data);
            });
        }
    };

    const verificarMovPenControlEfec = () => {
        if (confirm("Confirme")) {
            db.verificarMovPenControlEfec({}).then((res) => {
                getControlEfec();
                notificar(res);
            });
        }
    };
    const aprobarRecepcionCaja = (id, type) => {
        if (confirm("¿Está seguro de " + type + " el movimiento?")) {
            db.aprobarRecepcionCaja({ id, type }).then((res) => {
                getControlEfec();
                notificar(res);
            });
        }
    };

    const reversarMovPendientes = () => {
        if (confirm("¿Realmente desea eliminar los movimientos pendientes?")) {
            if (confirm("¿Seguro/a, Seguro/a?")) {
                if (confirm("No hay marcha atrás!")) {
                    db.reversarMovPendientes({}).then((res) => {
                        getControlEfec();
                        notificar(res.data);
                    });
                }
            }
        }
    };

    const setControlEfec = (sendCentralData = false) => {
        if (confirm("¿Realmente desea cargar el movimiento?")) {
            if (
                !controlefecNewConcepto ||
                !controlefecNewCategoria ||
                !controlefecNewMonto ||
                !controlefecNewDepartamento ||
                !controlefecNewMontoMoneda
            ) {
                alert("Error: Campos Vacíos!");
            } else {
                setopenModalNuevoEfectivo(false);

                db.setControlEfec({
                    concepto: controlefecNewConcepto,
                    categoria: controlefecNewCategoria,
                    id_departamento: controlefecNewDepartamento,
                    monto: controlefecNewMonto,
                    controlefecSelectGeneral,
                    controlefecSelectUnitario,
                    controlefecNewMontoMoneda,
                    sendCentralData,
                    transferirpedidoa,
                    id_persona: controlefecid_persona,
                    id_alquiler: controlefecid_alquiler,
                    id_proveedor: controlefecid_proveedor,
                }).then((res) => {
                    setcontrolefecNewConcepto("");
                    setcontrolefecNewMonto("");
                    setcontrolefecNewMontoMoneda("");
                    setcontrolefecNewCategoria("");
                    setcontrolefecNewDepartamento("");
                    setcontrolefecid_persona("");
                    setcontrolefecid_alquiler("");
                    setcontrolefecid_proveedor("");

                    getControlEfec();
                    notificar(res.data.msj);
                });
            }
        }
    };

    const selectRepleceProducto = (id) => {
        if (replaceProducto.este) {
            setreplaceProducto({ ...replaceProducto, poreste: id });
        } else {
            setreplaceProducto({ ...replaceProducto, este: id });
        }
    };
    const saveReplaceProducto = () => {
        db.saveReplaceProducto({
            replaceProducto,
        }).then(({ data }) => {
            if (data.estado) {
                buscarInventario();
                setreplaceProducto({ este: null, poreste: null });
            }
            notificar(data.msj);
        });
    };

    ///Configuracion Component
    const [subViewConfig, setsubViewConfig] = useState("usuarios");

    ///End Configuracion Component

    const getSucursales = () => {
        db.getSucursales({}).then((res) => {
            if (res.data.estado) {
                setsucursalesCentral(res.data.msj);
                setLoading(false);
            } else {
                notificar(res.data.msj, false);
            }
        });
    };
    const onchangeparametrosConsultaFromsucursalToCentral = (e) => {
        const key = e.target.getAttribute("name");
        let value = e.target.value;
        setparametrosConsultaFromsucursalToCentral({
            ...parametrosConsultaFromsucursalToCentral,
            [key]: value,
        });
    };
    const [modalmovilx, setmodalmovilx] = useState(0);
    const [modalmovily, setmodalmovily] = useState(0);
    const [modalmovilshow, setmodalmovilshow] = useState(false);
    const [
        idselectproductoinsucursalforvicular,
        setidselectproductoinsucursalforvicular,
    ] = useState({ index: null, id: null });
    const inputbuscarcentralforvincular = useRef(null);
    const [tareasenprocesocentral, settareasenprocesocentral] = useState({});

    const [tareasinputfecha, settareasinputfecha] = useState("");
    const [tareasAdminLocalData, settareasAdminLocalData] = useState([]);
    const getTareasLocal = () => {
        setLoading(true);
        db.getTareasLocal({
            fecha: tareasinputfecha,
        }).then((res) => {
            setLoading(false);
            settareasAdminLocalData(res.data);
        });
    };
    const resolverTareaLocal = (id, tipo = "aprobar") => {
        setLoading(true);
        db.resolverTareaLocal({
            id,
            tipo,
        }).then((res) => {
            setLoading(false);
            getTareasLocal();
        });
    };
    const [
        datainventarioSucursalFromCentralcopy,
        setdatainventarioSucursalFromCentralcopy,
    ] = useState([]);
    const autovincularSucursalCentral = () => {
        let obj = cloneDeep(inventarioSucursalFromCentral);

        db.getSyncProductosCentralSucursal({ obj }).then((res) => {
            setdatainventarioSucursalFromCentral(res.data);
        });
    };
    const modalmovilRef = useRef(null);
    const openVincularSucursalwithCentral = (e, idinsucursal) => {
        console.log(idinsucursal, "idinsucursal");
        console.log(e, "idinsucursal e");
        if (
            idinsucursal.index == idselectproductoinsucursalforvicular.index &&
            modalmovilshow
        ) {
            setmodalmovilshow(false);
        } else {
            setmodalmovilshow(true);
            if (modalmovilRef) {
                if (modalmovilRef.current) {
                    modalmovilRef.current?.scrollIntoView({
                        block: "nearest",
                        behavior: "smooth",
                    });
                }
            }
        }

        let p = e.currentTarget.getBoundingClientRect();
        let y = p.top + window.scrollY;
        let x = p.left;
        setmodalmovily(y);
        setmodalmovilx(x);

        setidselectproductoinsucursalforvicular({
            index: idinsucursal.index,
            id: idinsucursal.id,
        });
    };
    const linkproductocentralsucursal = (idinsucursal) => {
        /*  if (!inventarioSucursalFromCentral.filter(e => e.id_vinculacion == idincentral).length) { */
        /*  let val = idselectproductoinsucursalforvicular.id */
        /* 
            changeInventarioFromSucursalCentral(
            idincentral,
            idselectproductoinsucursalforvicular.index,
            idselectproductoinsucursalforvicular.id,
            "changeInput",
            "vinculo_real"
        );
        */
        /*  let pedidosCentral_copy = cloneDeep(pedidosCentral);
        pedidosCentral_copy[indexPedidoCentral].items[index].vinculo_real = idincentral;
        setpedidoCentral(pedidosCentral_copy);

        setmodalmovilshow(false); */
        /* } else {
            alert("¡Error: Éste ID ya se ha vinculado!")
        } */

        let index = idselectproductoinsucursalforvicular.index;
        let pedidosCentral_copy = cloneDeep(pedidosCentral);
        pedidosCentral_copy[indexPedidoCentral].items[index].vinculo_real =
            idinsucursal;
        setpedidoCentral(pedidosCentral_copy);

        setmodalmovilshow(false);
    };

    /*const linkproductocentralsucursal = (idinsucursal) => {

        //Id in central ID VINCULACION
         let pedidosCentralcopy = cloneDeep(pedidosCentral)
        db.changeIdVinculacionCentral({
            pedioscentral: pedidosCentralcopy[indexPedidoCentral],
            idinsucursal,
            idincentral: idselectproductoinsucursalforvicular.id,  //id vinculacion
        }).then(({ data }) => {

            if (data.estado) {
                pedidosCentralcopy[indexPedidoCentral] = data.pedido
                setpedidoCentral(pedidosCentralcopy)
                setmodalmovilshow(false);
            } else {
                notificar(data.msj)
            }

        }) 


       
    };*/

    let puedoconsultarproductosinsucursalfromcentral = () => {
        //si todos los productos son consultados(0) o Procesados(3), puedo buscar mas productos.
        if (inventarioSucursalFromCentral) {
            return !inventarioSucursalFromCentral.filter(
                (e) => e.estatus == 1 /* || e.estatus == 2 */
            ).length;
        }
    };
    const getInventarioSucursalFromCentral = (type_force = null) => {
        setLoading(true);
        switch (type_force) {
            case "cierrespanelcentroacopio":
                break;
            case "inventarioSucursalFromCentralmodify":
                //Si no, no puego buscar mas productos. En vez de eso, voy a editar/guardar nuevos productos
                let enedicionoinsercion = inventarioSucursalFromCentral.filter(
                    (e) => e.estatus == 1
                );
                db.getInventarioSucursalFromCentral({
                    type: "inventarioSucursalFromCentralmodify",
                    id_tarea:
                        tareasenprocesocentral.inventarioSucursalFromCentral,
                    productos: enedicionoinsercion,
                    codigo_destino: selectSucursalCentral,
                }).then((res) => {
                    if (res.data.estado) {
                        setdatainventarioSucursalFromCentral([]);
                    }
                    notificar(res.data.msj, true);
                    setLoading(false);
                });
                break;
            case "inventarioSucursalFromCentral":
                //si todos los productos son consultados(0) o Procesados(3), puedo buscar mas productos.
                if (puedoconsultarproductosinsucursalfromcentral()) {
                    let pedidonum = "";
                    if (
                        parametrosConsultaFromsucursalToCentral.novinculados ===
                        "pedido"
                    ) {
                        pedidonum = window.prompt("ID PEDIDO");
                    }
                    db.getInventarioSucursalFromCentral({
                        pedidonum,
                        codigo_destino: selectSucursalCentral,
                        type: type_force
                            ? type_force
                            : subviewpanelcentroacopio,
                        parametros: parametrosConsultaFromsucursalToCentral,
                    }).then((res) => {
                        notificar(res.data.msj, true);
                        setLoading(false);
                    });
                }
                break;
        }
    };
    const guardarDeSucursalEnCentral = (index, id) => {
        db.guardarDeSucursalEnCentral({
            producto: inventarioSucursalFromCentral[index],
        }).then((res) => {
            let d = res.data;

            if (d.estado) {
                changeInventarioFromSucursalCentral(
                    d.id,
                    index,
                    id,
                    "changeInput",
                    "id_vinculacion"
                );
                notificar(d.msj, false);
            } else {
                if (d.id) {
                    changeInventarioFromSucursalCentral(
                        d.id,
                        index,
                        id,
                        "changeInput",
                        "id_vinculacion"
                    );
                }
                notificar(d.msj, false);
            }
        });
    };

    const setInventarioSucursalFromCentral = (type_force = null) => {
        setLoading(true);
        db.setInventarioSucursalFromCentral({
            codigo_destino: selectSucursalCentral,
            type: type_force ? type_force : subviewpanelcentroacopio,
        }).then((res) => {
            setLoading(false);
            let data = res.data;
            if (data.estado) {
                switch (subviewpanelcentroacopio) {
                    case "inventarioSucursalFromCentral":
                        settareasenprocesocentral({
                            ...tareasenprocesocentral,
                            inventarioSucursalFromCentral: data.msj.id,
                        }); //Guardar Id de tarea recibida

                        if (data.msj.respuesta) {
                            let respuesta = JSON.parse(data.msj.respuesta);
                            setdatainventarioSucursalFromCentral(
                                respuesta.respuesta
                                    ? respuesta.respuesta
                                    : respuesta
                            );
                            setdatainventarioSucursalFromCentralcopy(
                                respuesta.respuesta
                                    ? respuesta.respuesta
                                    : respuesta
                            );

                            setestadisticasinventarioSucursalFromCentral(
                                respuesta.estadisticas
                            );
                        }
                        break;
                    case "fallaspanelcentroacopio":
                        setfallaspanelcentroacopio(res.data);
                        break;
                    case "estadisticaspanelcentroacopio":
                        setestadisticaspanelcentroacopio(res.data);
                        break;
                    case "gastospanelcentroacopio":
                        setgastospanelcentroacopio(res.data);
                        break;
                    case "cierrespanelcentroacopio":
                        setcierrespanelcentroacopio(res.data);
                        break;
                    case "diadeventapanelcentroacopio":
                        setdiadeventapanelcentroacopio(res.data);
                        break;
                    case "tasaventapanelcentroacopio":
                        settasaventapanelcentroacopio(res.data);
                        break;
                }
            } else {
                notificar(res.data.msj, true);
            }
        });
    };
    const [uniqueproductofastshowbyid, setuniqueproductofastshowbyid] =
        useState({});
    const [showdatafastproductobyid, setshowdatafastproductobyid] =
        useState(false);

    const getUniqueProductoById = (e, id) => {
        let p = e.currentTarget.getBoundingClientRect();
        let y = p.top + window.scrollY;
        let x = p.left;
        setmodalmovily(y);
        setmodalmovilx(x);
        setshowdatafastproductobyid(true);

        setuniqueproductofastshowbyid({});
        db.getUniqueProductoById({ id }).then((res) => {
            setuniqueproductofastshowbyid(res.data);
        });
    };
    const getTareasCentral = (estado = [0]) => {
        setLoading(true);
        settareasCentral([]);
        db.getTareasCentral({ estado }).then((res) => {
            settareasCentral(res.data);
            setLoading(false);
        });
    };
    const runTareaCentral = (i) => {
        setLoading(true);
        db.runTareaCentral({
            tarea: tareasCentral[i],
        }).then((res) => {
            let data = res.data;
            notificar(data.msj, true);
            if (data.estado) {
                getTareasCentral();
            }
            setLoading(false);
        });
    };

    const updatetasasfromCentral = () => {
        setLoading(true);
        db.updatetasasfromCentral({}).then((res) => {
            getMoneda();
            setLoading(false);
        });
    };
    const setchangetasasucursal = (e) => {
        let tipo = e.currentTarget.attributes["data-type"].value;
        let valor = window.prompt("Nueva tasa");
        if (valor) {
            if (number(valor)) {
                setLoading(true);
                db.setnewtasainsucursal({
                    tipo,
                    valor,
                    id_sucursal: selectSucursalCentral,
                }).then((res) => {
                    notificar(res);
                    getInventarioSucursalFromCentral(
                        "tasaventapanelcentroacopio"
                    );
                    setLoading(false);
                });
            }
        }
    };

    const saveChangeInvInSucurFromCentral = () => {
        setLoading(true);
        db.saveChangeInvInSucurFromCentral({
            inventarioModifiedCentralImport:
                inventarioModifiedCentralImport.filter(
                    (e) => e.type === "replace"
                ),
        }).then((res) => {
            notificar(res);
            getInventarioFromSucursal();
            setLoading(false);
        });
    };
    const getInventarioFromSucursal = () => {
        setLoading(true);
        db.getInventarioFromSucursal({}).then((res) => {
            if (res.data) {
                if (res.data.length) {
                    if (typeof res.data[Symbol.iterator] === "function") {
                        if (!res.data.estado === false) {
                            setinventarioModifiedCentralImport([]);
                        } else {
                            setinventarioModifiedCentralImport(res.data);
                        }
                    }
                } else {
                    setinventarioModifiedCentralImport([]);
                }
            }

            if (res.data.estado === false) {
                notificar(res);
            }
            setLoading(false);
        });
    };
    const setCambiosInventarioSucursal = () => {
        let checkempty = inventarioSucursalFromCentral.filter(
            (e) =>
                e.codigo_barras == "" ||
                e.descripcion == "" ||
                e.id_categoria == "" ||
                e.unidad == "" ||
                e.id_proveedor == "" ||
                e.cantidad == "" ||
                e.precio == ""
        );

        if (inventarioSucursalFromCentral.length && !checkempty.length) {
            setLoading(true);
            db.setCambiosInventarioSucursal({
                productos: inventarioSucursalFromCentral,
                sucursal: sucursaldata,
            }).then((res) => {
                notificar(res);
                setLoading(false);
                try {
                    if (res.data.estado) {
                        getInventarioSucursalFromCentral(
                            subviewpanelcentroacopio
                        );
                    }
                } catch (err) {}
            });
        } else {
            alert(
                "¡Error con los campos! Algunos pueden estar vacíos " +
                    JSON.stringify(checkempty)
            );
        }
    };

    //down
    useHotkeys(
        "down",
        (event) => {
            if (
                view == "inventario" &&
                subViewInventario == "inventario" &&
                modViewInventario == "list"
            ) {
                // focusInputSibli(event.target, 1)
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        [
            view,
            counterListProductos,
            countListInter,
            countListPersoInter,
            subViewInventario,
            modViewInventario,
        ]
    );

    //up
    useHotkeys(
        "up",
        (event) => {
            if (
                view == "inventario" &&
                subViewInventario == "inventario" &&
                modViewInventario == "list"
            ) {
                // focusInputSibli(event.target, -1)
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        [
            view,
            counterListProductos,
            countListInter,
            countListPersoInter,
            subViewInventario,
            modViewInventario,
        ]
    );

    const [refPago, setrefPago] = useState([]);

    const addNewPedido = () => {
        db.addNewPedido({}).then((res) => {
            onClickEditPedido(null, res.data);
        });
    };
    const [categoriasCajas, setcategoriasCajas] = useState([]);
    const [departamentosCajas, setdepartamentosCajas] = useState([]);

    const getcatsCajas = () => {
        db.getcatsCajas({}).then((res) => {
            if (res.data.categorias.length) {
                setcategoriasCajas(res.data.categorias);
                setdepartamentosCajas(res.data.departamentos);
            }
        });
    };
    const addRetencionesPago = () => {
        let descripcion = prompt("Descripción");
        let monto = prompt("Monto");
        let num = null;
        if (pedidoData.id && descripcion && monto) {
            db.addRetencionesPago({
                monto: parseFloat(monto),
                id_pedido: pedidoData.id,
                descripcion,
                num,
            }).then((res) => {
                getPedido(null, null, false);
                notificar(res);
            });
        }
    };
    const delRetencionPago = (id) => {
        if (confirm("Confirme eliminación de Retencion")) {
            db.delRetencionPago({ id }).then((res) => {
                getPedido();
                notificar(res);
            });
        }
    };

    const addRefPago = (tipo, montoTraido = "", tipoTraido = "", categoria = "central", datosAdicionales = {}) => {
        if (tipo == "toggle") {
            settogglereferenciapago(!togglereferenciapago);

            settipo_referenciapago(tipoTraido);
            setmonto_referenciapago(montoTraido * dolar);
        }
        if (tipo == "enviar") {
            if (pedidoData.id && monto_referenciapago) {
                let ref = descripcion_referenciapago;

                db.addRefPago({
                    tipo: tipo_referenciapago,
                    descripcion: ref,
                    monto: monto_referenciapago,
                    banco: banco_referenciapago,
                    cedula: cedula_referenciapago,
                    telefono: telefono_referenciapago,
                    id_pedido: pedidoData.id,
                    categoria: categoria,
                    ...datosAdicionales,
                }).then((res) => {
                    getPedido(null, null, false);
                    notificar(res);
                    settogglereferenciapago(false);

                    settipo_referenciapago("");
                    setdescripcion_referenciapago("");
                    setmonto_referenciapago("");
                    setbanco_referenciapago("");
                    setcedula_referenciapago("");
                    settelefono_referenciapago("");

                    // Para módulos automáticos, enviar a merchant inmediatamente
                    if (res.data && res.data.estado && res.data.id_referencia && 
                        (categoria === "pagomovil" || categoria === "banesco" || categoria === "interbancaria")) {
                        db.sendRefToMerchant({
                            id_ref: res.data.id_referencia,
                        }).then((resMerchant) => {
                            console.log("Referencia enviada a merchant:", resMerchant);
                            notificar(resMerchant);
                        }).catch((err) => {
                            console.error("Error al enviar a merchant:", err);
                        });
                    }
                });
            }
        }
    };
    const changeEntregado = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        if (confirm("Confirme Entrega de producto")) {
            db.changeEntregado({ id }).then((res) => {
                getPedido();
                notificar(res);
            });
        }
    };
    const delRefPago = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        if (confirm("Confirme eliminación de referencia")) {
            db.delRefPago({ id }).then((res) => {
                getPedido();
                notificar(res);
            });
        }
    };
    //Gastos component

    const [qgastosfecha1, setqgastosfecha1] = useState("");
    const [qgastosfecha2, setqgastosfecha2] = useState("");
    const [qgastos, setqgastos] = useState("");
    const [qcatgastos, setqcatgastos] = useState("");
    const [gastosdescripcion, setgastosdescripcion] = useState("");
    const [gastoscategoria, setgastoscategoria] = useState("3");
    const [gastosmonto, setgastosmonto] = useState("");
    const [gastosData, setgastosData] = useState({});

    const delGastos = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        if (id && confirm("Confirme eliminación de gasto")) {
            db.delGastos({ id }).then((res) => {
                notificar(res);
                getGastos();
            });
        }
    };
    const getGastos = () => {
        db.getGastos({
            qgastosfecha1,
            qgastosfecha2,
            qgastos,
            qcatgastos,
        }).then((res) => {
            if (res.data) {
                if (res.data.gastos) {
                    setgastosData(res.data);
                } else {
                    setgastosData({});
                }
            }
        });
    };
    const setGasto = (e) => {
        e.preventDefault();

        db.setGasto({
            gastosdescripcion,
            gastoscategoria,
            gastosmonto,
        }).then((res) => {
            notificar(res);
            getGastos();
        });
    };
    //End Gastos Component

    ////Historico producto

    const [showmodalhistoricoproducto, setshowmodalhistoricoproducto] =
        useState(false);

    const [fecha1modalhistoricoproducto, setfecha1modalhistoricoproducto] =
        useState("");
    const [fecha2modalhistoricoproducto, setfecha2modalhistoricoproducto] =
        useState("");
    const [usuariomodalhistoricoproducto, setusuariomodalhistoricoproducto] =
        useState("");
    const [datamodalhistoricoproducto, setdatamodalhistoricoproducto] =
        useState([]);
    const [
        selectproductohistoricoproducto,
        setselectproductohistoricoproducto,
    ] = useState(null);

    const openmodalhistoricoproducto = (id) => {
        getmovientoinventariounitario(id);
        setselectproductohistoricoproducto(id);
        setshowmodalhistoricoproducto(true);
    };

    const getmovientoinventariounitario = (id) => {
        db.getmovientoinventariounitario({
            id: id ? id : selectproductohistoricoproducto,
            fecha1modalhistoricoproducto,
            fecha2modalhistoricoproducto,
            usuariomodalhistoricoproducto,
        }).then((res) => {
            if (Array.isArray(res.data)) {
                setdatamodalhistoricoproducto(res.data);
            } else {
                notificar(res.data);
            }
        });
    };

    /////End Historio producto

    //////Historico inventario
    const [qhistoinven, setqhistoinven] = useState("");
    const [fecha1histoinven, setfecha1histoinven] = useState("");
    const [fecha2histoinven, setfecha2histoinven] = useState("");
    const [orderByHistoInven, setorderByHistoInven] = useState("desc");
    const [usuarioHistoInven, setusuarioHistoInven] = useState("");
    const [historicoInventario, sethistoricoInventario] = useState([]);
    const getHistoricoInventario = () => {
        setLoading(true);

        db.getHistoricoInventario({
            qhistoinven,
            fecha1histoinven,
            fecha2histoinven,
            orderByHistoInven,
            usuarioHistoInven,
        }).then((res) => {
            sethistoricoInventario(res.data);
            setLoading(false);
        });
    };

    //////

    const [qBuscarCategorias, setQBuscarCategorias] = useState("");
    const [categorias, setcategorias] = useState([]);

    const [categoriasDescripcion, setcategoriasDescripcion] = useState("");
    const [indexSelectCategorias, setIndexSelectCategorias] = useState(null);

    const delCategorias = () => {
        setLoading(true);
        let id = null;
        if (indexSelectCategorias) {
            if (categorias[indexSelectCategorias]) {
                id = categorias[indexSelectCategorias].id;
            }
        }

        db.delCategoria({ id }).then((res) => {
            setLoading(false);
            getCategorias();
            notificar(res);
            setIndexSelectCategorias(null);
        });
    };

    const addNewCategorias = (e) => {
        e.preventDefault();

        let id = null;
        if (indexSelectCategorias) {
            if (categorias[indexSelectCategorias]) {
                id = categorias[indexSelectCategorias].id;
            }
        }

        if (categoriasDescripcion) {
            setLoading(true);
            db.setCategorias({ id, categoriasDescripcion }).then((res) => {
                notificar(res);
                setLoading(false);
                getCategorias();
            });
        }
    };
    const getCategorias = () => {
        db.getCategorias({
            q: qBuscarCategorias,
        }).then((res) => {
            if (res.data) {
                if (res.data.length) {
                    setcategorias(res.data);
                } else {
                    setcategorias([]);
                }
            }
        });
    };
    const setInputsCats = () => {
        if (indexSelectCategorias) {
            let obj = categorias[indexSelectCategorias];
            if (obj) {
                setcategoriasDescripcion(obj.descripcion);
            }
        }
    };

    useEffect(() => {
        if (user.usuario) {
            let lastchar = user.usuario.slice(-1);
            if (
                lastchar == 1 ||
                lastchar == 2 ||
                lastchar == 3 ||
                lastchar == 4 ||
                lastchar == 5 ||
                lastchar == 6 ||
                lastchar == 7 ||
                lastchar == 8 ||
                lastchar == 9 ||
                lastchar == 10
            ) {
                setselectprinter(lastchar);
            }
        }
    }, []);

    useEffect(() => {
        setInputsCats();
    }, [indexSelectCategorias]);
   

    useEffect(() => {
        getUsuarios();
    }, [qBuscarUsuario]);

    useEffect(() => {
        setInputsUsuarios();
    }, [indexSelectUsuarios]);

    useEffect(() => {
        // let isMounted = true;
        getMoneda(); //
        //getPedidosList();
        getToday();
        getSucursalFun();

        // return () => { isMounted = false }
    }, []);

    

    useEffect(() => {
        getClienteCrud();
    }, [qBuscarCliente]);
    useEffect(() => {
        focusCtMain();
    }, [selectItem]);

    

    
    
   

    

    useEffect(() => {
        if (view == "devoluciones") {
            getBuscarDevolucionhistorico();
        }
    }, [buscarDevolucionhistorico, view, fechaMovimientos]);

    
    useEffect(() => {
        if (view == "inventario") {
            if (subViewInventario == "inventario") {
                getProductos();
            } else if (subViewInventario == "proveedores") {
                getProveedores();
            }
        } 
        
    }, [view, subViewInventario]);

    useEffect(() => {
        if (view == "credito" || view == "vueltos") {
            getDeudores();
            getDeudor();
        }
    }, [view, orderbycolumdeudores, orderbyorderdeudores]);

    useEffect(() => {
        setInputsInventario();
    }, [indexSelectInventario]);

    useEffect(() => {
        if (subViewInventario == "proveedores") {
            setInputsProveedores();
        } else if (subViewInventario == "facturas") {
            getPagoProveedor();
        }
    }, [subViewInventario, indexSelectProveedores]);

    useEffect(() => {
        setBilletes();
    }, [billete1, billete5, billete10, billete20, billete50, billete100]);

   

    /* useEffect(() => {
    getInventarioSucursalFromCentral(subviewpanelcentroacopio)
  }, [subviewpanelcentroacopio]) */

    let total_caja_calc = (
        parseFloat(caja_usd ? caja_usd : 0) +
        parseFloat(caja_cop ? caja_cop : 0) / parseFloat(peso) +
        parseFloat(caja_bs ? caja_bs : 0) / parseFloat(dolar)
    ).toFixed(2);
    let total_caja_neto =
        !total_caja_calc || total_caja_calc == "NaN" ? 0 : total_caja_calc;

    let total_dejar_caja_calc = (
        parseFloat(dejar_usd ? dejar_usd : 0) +
        parseFloat(dejar_cop ? dejar_cop : 0) / parseFloat(peso) +
        parseFloat(dejar_bs ? dejar_bs : 0) / parseFloat(dolar)
    ).toFixed(2);
    let total_dejar_caja_neto =
        !total_dejar_caja_calc || total_dejar_caja_calc == "NaN"
            ? 0
            : total_dejar_caja_calc;

    let total_punto = dolar && caja_punto ? (caja_punto / dolar).toFixed(2) : 0;
    let total_biopago =
        dolar && caja_biopago ? (caja_biopago / dolar).toFixed(2) : 0;

    const runSockets = () => {
        /* const channel = Echo.channel("private.eventocentral."+user.sucursal)

        channel.subscribed(()=>{
            console.log("Subscrito a Central!")
        })
        .listen(".eventocentral",event=>{
            db.recibedSocketEvent({event}).then(({data})=>{
                console.log(data,"recibedSocketEvent Response")
            })
        }) */
    };

    const getSucursalFun = () => {
        db.getSucursal({}).then((res) => {
            if (res.data.codigo) {
                setSucursaldata(res.data);
            }
        });
    };
    const openReporteFalla = (id) => {
        if (id) {
            db.openReporteFalla(id);
        }
    };
    const getEstaInventario = () => {
        if (time != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            setLoading(true);
            db.getEstaInventario({
                fechaQEstaInve,
                fechaFromEstaInve,
                fechaToEstaInve,
                orderByEstaInv,
                categoriaEstaInve,
                orderByColumEstaInv,
            }).then((e) => {
                setdataEstaInven(e.data);
                setLoading(false);
            });
        }, 150);
        setTypingTimeout(time);
    };
    const setporcenganancia = (tipo, base = 0, fun = null) => {
        let insert = window.prompt("Porcentaje");
        if (insert) {
            if (number(insert)) {
                if (tipo == "unique") {
                    let re = (
                        parseFloat(inpInvbase) +
                        parseFloat(inpInvbase) * (parseFloat(insert) / 100)
                    ).toFixed(2);
                    if (re) {
                        setinpInvventa(re);
                    }
                } else if ("list") {
                    let re = (
                        parseFloat(base) +
                        parseFloat(base) * (parseFloat(insert) / 100)
                    ).toFixed(2);
                    if (re) {
                        fun(re);
                    }
                }
            }
        }
    };

    const focusInputSibli = (tar, mov) => {
        let inputs = [].slice.call(refsInpInvList.current.elements);
        let index;
        if (tar.tagName == "INPUT") {
            if (mov == "down") {
                mov = 11;
            } else if (mov == "up") {
                mov = -11;
            }
        }
        for (let i in inputs) {
            if (tar == inputs[i]) {
                index = parseInt(i) + mov;
                if (refsInpInvList.current[index]) {
                    refsInpInvList.current[index].focus();
                }
                break;
            }
        }
        if (typeof index === "undefined") {
            if (refsInpInvList.current[0]) {
                refsInpInvList.current[0].focus();
            }
        }
    };
    const sendCuentasporCobrar = () => {
        db.sendCuentasporCobrar({}).then((res) => {
            notificar(res);
        });
    };
    const setBackup = () => {
        db.backup({});
    };
    const getCierres = () => {
        db.getCierres({
            fechaGetCierre,
            fechaGetCierre2,
            tipoUsuarioCierre,
        }).then((res) => {
            if (res.data) {
                if (res.data.cierres) {
                    setCierres(res.data);
                } else {
                    setCierres({});
                }
            }
        });
    };
    const setcajaFuerteFun = (type, val, notchica = true) => {
        let val_chica = 0;
        switch (type) {
            case "setCajaFuerteEntradaCierreDolar":
                setCajaFuerteEntradaCierreDolar(val);

                val_chica = parseFloat(guardar_usd - val).toFixed(2);
                if (notchica) {
                    setCajaChicaEntradaCierreDolar(val_chica);
                }
                break;
            case "setCajaFuerteEntradaCierreCop":
                setCajaFuerteEntradaCierreCop(val);

                val_chica = parseFloat(guardar_cop - val).toFixed(2);
                if (notchica) {
                    setCajaChicaEntradaCierreCop(val_chica);
                }
                break;
            case "setCajaFuerteEntradaCierreBs":
                setCajaFuerteEntradaCierreBs(val);

                val_chica = parseFloat(guardar_bs - val).toFixed(2);
                if (notchica) {
                    setCajaChicaEntradaCierreBs(val_chica);
                }
                break;
        }
    };

    const [dataPuntosAdicionales, setdataPuntosAdicionales] = useState([]);

    const addTuplasPuntosAdicionales = (type, index) => {
        let newTupla = {
            banco: "",
            monto: "",
            descripcion: "",
            categoria: "",
        };
        switch (type) {
            case "delete":
                setdataPuntosAdicionales(
                    dataPuntosAdicionales.filter((e, i) => i !== index)
                );

                break;
            case "add":
                /* if (dataPuntosAdicionales.length==0) { */
                setdataPuntosAdicionales(
                    dataPuntosAdicionales.concat(newTupla)
                );
                /*  } */
                break;
        }
    };

    const fun_setguardar = (type, val, cierreForce) => {
        let total = number(cierreForce["efectivo_guardado"]);
        if (type == "setguardar_cop") {
            setguardar_cop(val);
            setcajaFuerteFun("setCajaFuerteEntradaCierreCop", val, false);

            let p = number((val / peso).toFixed(1));
            let u = total - p;

            setguardar_bs("");
            setcajaFuerteFun("setCajaFuerteEntradaCierreBs", "", false);

            setguardar_usd(u);
            setcajaFuerteFun("setCajaFuerteEntradaCierreDolar", u, false);
        }

        if (type == "setguardar_bs") {
            setguardar_bs(val);
            setcajaFuerteFun("setCajaFuerteEntradaCierreBs", val, false);

            if (!val) {
                let p = number((guardar_cop / peso).toFixed(1));
                let u = total - p;

                setguardar_usd(u);
                setcajaFuerteFun("setCajaFuerteEntradaCierreDolar", u, false);
            } else {
                let u = (
                    total -
                    number((guardar_cop / peso).toFixed(1)) -
                    number((val / dolar).toFixed(1))
                ).toFixed(1);
                setguardar_usd(u);
                setcajaFuerteFun("setCajaFuerteEntradaCierreDolar", u, false);
            }
        }
    };
    const cerrar_dia = (e = null) => {
        if (e) {
            e.preventDefault();
        }
        setLoading(true);
        db.cerrar({
            total_caja_neto,
            total_punto,
            dejar_usd,
            dejar_cop,
            dejar_bs,
            totalizarcierre,
            total_biopago,
        }).then((res) => {
            let cierreData = res.data;
            if (res.data) {
                setguardar_usd(cierreData["efectivo_guardado"]);

                fun_setguardar("setguardar_cop", guardar_cop, cierreData);
                fun_setguardar("setguardar_bs", guardar_bs, cierreData);

                settipo_accionCierre(cierreData["tipo_accion"]);
                setFechaCierre(cierreData["fecha"]);

                if (cierreData["puntosAdicional"]) {
                    if (cierreData["puntosAdicional"].length) {
                        setdataPuntosAdicionales(cierreData["puntosAdicional"]);
                    }
                }

                /* setCajaFuerteEntradaCierreBs()
                setCajaFuerteEntradaCierreCop() */
            }
            setCierre(cierreData);
            if (res.data.estado == false) {
                notificar(res);
            }
            setLoading(false);
        });
    };
    const focusCtMain = () => {
        if (inputCantidadCarritoref.current) {
            inputCantidadCarritoref.current.focus();
        }
    };
  
    const setToggleAddPersonaFun = (prop, callback = null) => {
        setToggleAddPersona(prop);
        if (callback) {
            callback();
        }
    };
    const getMovimientos = (val = "") => {
        setLoading(true);
        db.getMovimientos({ val, fechaMovimientos }).then((res) => {
            setMovimientos(res.data);

            // if (!res.data.length) {
            // setIdMovSelect("nuevo")
            // }else{
            //   if (res.data[0]) {
            //     setIdMovSelect(res.data[0].id)
            //   }
            // }
            setLoading(false);
        });
    };
    const getDeudor = () => {
        try {
            if (deudoresList[selectDeudor]) {
                setLoading(true);
                db.getDeudor({
                    onlyVueltos,
                    id: deudoresList[selectDeudor].id,
                }).then((res) => {
                    // detallesDeudor
                    setDetallesDeudor(res.data);
                    setLoading(false);
                });
            }
        } catch (err) {}
    };
    const entregarVuelto = () => {
        
    };
    const getDebito = () => {
        // Usar tasas del pedido
        const tasaBsPedido = pedidoData?.items?.[0]?.tasa || dolar;
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular la suma de los otros métodos de pago en USD
        const otrosTotal =
            parseFloat(efectivo || 0) +
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        // Calcular restante en USD y convertir a Bs
        const restanteUSD = otrosTotal === 0
            ? parseFloat(pedidoData.clean_total)
            : Math.max(0, pedidoData.clean_total - otrosTotal);
        
        // Convertir a Bs usando tasa del pedido
        const montoDebitoBs = (restanteUSD * tasaBsPedido).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'debito' && parseFloat(debito || 0).toFixed(2) === montoDebitoBs) {
            setDebito((parseFloat(pedidoData.clean_total) * tasaBsPedido).toFixed(2));
            setEfectivo("");
            setEfectivo_bs("");
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setTransferencia("");
            setCredito("");
            setBiopago("");
        } else {
            setDebito(montoDebitoBs);
        }
        
        setLastPaymentMethodCalled('debito');
    };
    const getCredito = () => {
        // Usar tasas del pedido
        const tasaBsPedido = pedidoData?.items?.[0]?.tasa || dolar;
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular la suma de los otros métodos de pago en USD
        const otrosTotal =
            parseFloat(efectivo || 0) +
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(biopago || 0);

        // Si los demás están en blanco, setear el total completo
        // Si hay otros montos, setear la diferencia
        const montoCredito =
            otrosTotal === 0
                ? parseFloat(pedidoData.clean_total).toFixed(2)
                : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'credito' && parseFloat(credito || 0).toFixed(2) === montoCredito) {
            setCredito(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo("");
            setEfectivo_bs("");
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setTransferencia("");
            setDebito("");
            setBiopago("");
        } else {
            setCredito(montoCredito);
        }
        
        setLastPaymentMethodCalled('credito');
    };
    const getTransferencia = () => {
        // Usar tasas del pedido
        const tasaBsPedido = pedidoData?.items?.[0]?.tasa || dolar;
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular la suma de los otros métodos de pago en USD
        const otrosTotal =
            parseFloat(efectivo || 0) +
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        // Si los demás están en blanco, setear el total completo
        // Si hay otros montos, setear la diferencia
        const montoTransferencia =
            otrosTotal === 0
                ? parseFloat(pedidoData.clean_total).toFixed(2)
                : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'transferencia' && parseFloat(transferencia || 0).toFixed(2) === montoTransferencia) {
            setTransferencia(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo("");
            setEfectivo_bs("");
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setDebito("");
            setCredito("");
            setBiopago("");
        } else {
            setTransferencia(montoTransferencia);
        }
        
        setLastPaymentMethodCalled('transferencia');
    };
    const getBio = () => {
        // Usar tasas del pedido
        const tasaBsPedido = pedidoData?.items?.[0]?.tasa || dolar;
        const tasaCopPedido = pedidoData?.items?.[0]?.tasa_cop || peso;
        
        // Calcular la suma de los otros métodos de pago en USD
        const otrosTotal =
            parseFloat(efectivo || 0) +
            parseFloat(efectivo_dolar || 0) +
            (parseFloat(efectivo_bs || 0) / tasaBsPedido) +
            (parseFloat(efectivo_peso || 0) / tasaCopPedido) +
            parseFloat(transferencia || 0) +
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(credito || 0);

        // Si los demás están en blanco, setear el total completo
        // Si hay otros montos, setear la diferencia
        const montoBiopago =
            otrosTotal === 0
                ? parseFloat(pedidoData.clean_total).toFixed(2)
                : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'biopago' && parseFloat(biopago || 0).toFixed(2) === montoBiopago) {
            setBiopago(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo("");
            setEfectivo_bs("");
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setTransferencia("");
            setDebito("");
            setCredito("");
        } else {
            setBiopago(montoBiopago);
        }
        
        setLastPaymentMethodCalled('biopago');
    };
    const getEfectivo = () => {
        // Usar tasas del pedido
        const tasaBsPedido = pedidoData?.items?.[0]?.tasa || dolar;
        
        // Calcular la suma de los otros métodos de pago en USD
        const otrosTotal =
            (parseFloat(debito || 0) / tasaBsPedido) +
            parseFloat(transferencia || 0) +
            parseFloat(credito || 0) +
            parseFloat(biopago || 0);

        // Si los demás están en blanco, setear el total completo
        // Si hay otros montos, setear la diferencia
        const montoEfectivo =
            otrosTotal === 0
                ? parseFloat(pedidoData.clean_total).toFixed(2)
                : Math.max(0, pedidoData.clean_total - otrosTotal).toFixed(2);

        // Si es la segunda invocación seguida y el resultado es el mismo, setear total completo y limpiar los demás
        if (lastPaymentMethodCalled === 'efectivo' && parseFloat(efectivo || 0).toFixed(2) === montoEfectivo) {
            setEfectivo(parseFloat(pedidoData.clean_total).toFixed(2));
            setEfectivo_bs("");
            setEfectivo_dolar("");
            setEfectivo_peso("");
            setDebito("");
            setTransferencia("");
            setCredito("");
            setBiopago("");
        } else {
            setEfectivo(montoEfectivo);
        }
        
        setLastPaymentMethodCalled('efectivo');
    };
    const getToday = () => {
        db.today({}).then((res) => {
            let today = res.data;
            setToday(today);

            setFecha1pedido(today);
            setFecha2pedido(today);
            setFechaMovimientos(today);
            setMovCajaFecha(today);
            setfechaventas(today);
            setqgastosfecha1(today);
            setqgastosfecha2(today);
            setfechainiciocredito(today);
            setcontrolefecQDesde(today);
            setcontrolefecQHasta(today);

            setcontrolefecQDesde(today);
            setcontrolefecQHasta(today);
            setfactqBuscarDate(today);
        });
    };

    const filterMetodoPago = (e) => {
        let type = e.currentTarget.attributes["data-type"].value;

        setFilterMetodoPagoToggle(type);
    };
    const onchangecaja = (e) => {
        let name = e.currentTarget.attributes["name"].value;
        let val;
        if (
            name == "notaCierre" ||
            name == "tipo_pago_deudor" ||
            name == "qDeudores"
        ) {
            val = e.currentTarget.value;
        } else {
            val = number(e.currentTarget.value);
            val = val == "NaN" || !val ? "" : val;
        }

        switch (name) {
            case "caja_usd":
                setCaja_usd(val);
                break;

            case "caja_cop":
                setCaja_cop(val);
                setguardar_cop(val);
                break;

            case "caja_bs":
                setCaja_bs(val);
                setguardar_bs(val);
                break;

            case "dejar_usd":
                setDejar_usd(val);
                break;

            case "dejar_cop":
                setDejar_cop(val);
                setguardar_cop(caja_cop - val);
                break;

            case "dejar_bs":
                setDejar_bs(val);
                setguardar_bs(caja_bs - val);

                break;

            case "caja_punto":
                setCaja_punto(val);
                break;

            case "notaCierre":
                setNotaCierre(val);
                break;

            case "tipo_pago_deudor":
                setTipo_pago_deudor(val);
                break;
            case "monto_pago_deudor":
                setMonto_pago_deudor(val);
                break;
            case "qDeudores":
                setQDeudores(val);
                break;
            case "caja_biopago":
                setcaja_biopago(val);
                break;
        }
    };

    const setMoneda = async (e) => {
        const tipo = e.currentTarget.attributes["data-type"].value;
        const tipoNombre = tipo === "1" ? "Dólar" : "Peso Colombiano";
        let valor = window.prompt(`Nuevo valor del ${tipoNombre}`);
        if (valor) {
            try {
                const res = await db.setMoneda({ tipo, valor });
                if (res.data.estado) {
                    notificar(
                        `✅ ${tipoNombre} actualizado: ${valor}. Recargando sistema...`
                    );
                    
                    // Esperar 2 segundos para que el usuario vea la notificación
                    setTimeout(async () => {
                        try {
                            // Cerrar sesión
                            await axios.get('/logout');
                        } catch (error) {
                            console.log('Logout error:', error);
                        } finally {
                            // Recargar la página para forzar nuevo login
                            window.location.reload(true);
                        }
                    }, 2000);
                } else {
                    notificar(`❌ Error: ${res.data.msj}`);
                }
            } catch (error) {
                notificar(`❌ Error al actualizar ${tipoNombre}`);
                console.error('Error updating currency:', error);
            }
        }
    };

    const updateDollarRate = async (e) => {
        const tipo = e.currentTarget.attributes["data-type"].value;

        // Solo actualizar automáticamente el dólar (tipo 1)
        if (tipo === "1") {
            try {
                const response = await db.updateDollarRate();
                if (response.data.estado) {
                    notificar(
                        `✅ Dólar actualizado: $${response.data.valor} Bs. Recargando sistema...`
                    );
                    
                    // Esperar 2 segundos para que el usuario vea la notificación
                    setTimeout(async () => {
                        try {
                            // Cerrar sesión
                            await axios.get('/logout');
                        } catch (error) {
                            console.log('Logout error:', error);
                        } finally {
                            // Recargar la página para forzar nuevo login
                            window.location.reload(true);
                        }
                    }, 2000);
                } else {
                    notificar(`❌ Error: ${response.data.msj}`);

                    // Si falla la actualización automática, ofrecer actualización manual
                    if (
                        window.confirm(
                            "¿Desea actualizar manualmente el valor del dólar?"
                        )
                    ) {
                        const newValue = window.prompt(
                            "💱 Ingrese el nuevo valor del dólar en Bolívares (Bs):\n\nEjemplo: 109.50"
                        );
                        if (
                            newValue &&
                            !isNaN(newValue) &&
                            parseFloat(newValue) > 0
                        ) {
                            try {
                                const manualResponse = await db.setMoneda({
                                    tipo: 1,
                                    valor: parseFloat(newValue),
                                });
                                if (manualResponse.data.estado) {
                                    notificar(
                                        `✅ Dólar actualizado manualmente: $${newValue} Bs. Recargando sistema...`
                                    );
                                    
                                    // Esperar 2 segundos para que el usuario vea la notificación
                                    setTimeout(async () => {
                                        try {
                                            // Cerrar sesión
                                            await axios.get('/logout');
                                        } catch (error) {
                                            console.log('Logout error:', error);
                                        } finally {
                                            // Recargar la página para forzar nuevo login
                                            window.location.reload(true);
                                        }
                                    }, 2000);
                                } else {
                                    notificar(
                                        `❌ Error al actualizar manualmente: ${manualResponse.data.msj}`
                                    );
                                }
                            } catch (manualError) {
                                notificar(
                                    "❌ Error al actualizar el dólar manualmente"
                                );
                                console.error(
                                    "Error updating dollar manually:",
                                    manualError
                                );
                            }
                        } else if (newValue !== null) {
                            notificar(
                                "❌ Por favor ingrese un valor válido mayor a 0"
                            );
                        }
                    }
                }
            } catch (error) {
                notificar("❌ Error al actualizar el dólar automáticamente");
                console.error("Error updating dollar:", error);

                // Si hay error en la conexión, también ofrecer actualización manual
                if (
                    window.confirm(
                        "¿Desea actualizar manualmente el valor del dólar?"
                    )
                ) {
                    const newValue = window.prompt(
                        "💱 Ingrese el nuevo valor del dólar en Bolívares (Bs):\n\nEjemplo: 109.50"
                    );
                    if (
                        newValue &&
                        !isNaN(newValue) &&
                        parseFloat(newValue) > 0
                    ) {
                        try {
                            const manualResponse = await db.setMoneda({
                                tipo: 1,
                                valor: parseFloat(newValue),
                            });
                            if (manualResponse.data.estado) {
                                notificar(
                                    `✅ Dólar actualizado manualmente: $${newValue} Bs`
                                );
                                getMoneda(); // Actualizar valores en pantalla
                            } else {
                                notificar(
                                    `❌ Error al actualizar manualmente: ${manualResponse.data.msj}`
                                );
                            }
                        } catch (manualError) {
                            notificar(
                                "❌ Error al actualizar el dólar manualmente"
                            );
                            console.error(
                                "Error updating dollar manually:",
                                manualError
                            );
                        }
                    } else if (newValue !== null) {
                        notificar(
                            "❌ Por favor ingrese un valor válido mayor a 0"
                        );
                    }
                }
            }
        } else {
            // Para otras monedas, usar el método manual
            setMoneda(e);
        }
    };
    const getMoneda = () => {
        setLoading(true);
        db.getMoneda().then((res) => {
            if (res.data.peso) {
                setPeso(res.data.peso);
            }

            if (res.data.dolar) {
                setDolar(res.data.dolar);
            }
            setLoading(false);
        });
    };
    const toggleModalProductos = (prop, callback = null) => {
        setproductoSelectinternouno(prop);

        if (callback) {
            callback();
        }
    };

    const [isPrinting, setIsPrinting] = useState(false);
    const [printError, setPrintError] = useState(null);

    const resetPrintingState = async (id) => {
        try {
            const res = await db.resetPrintingState({ id });
            if (res.data.estado) {
                notificar("Estado de impresión reseteado");
                setIsPrinting(false);
                setPrintError(null);
            } else {
                notificar("Error al resetear estado: " + res.data.msj);
            }
        } catch (err) {
            notificar("Error al resetear estado: " + err.message);
        }
    };

    const toggleImprimirTicket = (id_fake = null) => {
        if (isPrinting) {
            // Si está imprimiendo y hay error, ofrecer resetear
            if (printError) {
                if (
                    window.confirm(
                        "Hubo un error en la impresión anterior. ¿Desea resetear el estado de impresión?"
                    )
                ) {
                    resetPrintingState(id_fake || pedidoData.id);
                }
            }
            return;
        }

        if (pedidoData) {
            setIsPrinting(true);
            setPrintError(null);

            if (id_fake == "presupuesto") {
                let nombres = window.prompt(
                    "(Nombre y Apellido) o (Razón Social)"
                );
                let identificacion = window.prompt("CI o RIF");

                const params = {
                    id: id_fake ? id_fake : pedidoData.id,
                    moneda: monedaToPrint,
                    printer: selectprinter,
                    presupuestocarrito,
                    nombres,
                    identificacion,
                };

                db.imprimirTicked(params)
                    .then((res) => {
                        if (res.data.estado === false) {
                            setPrintError(res.data.msj);
                            if (res.data.msj.includes("está siendo impreso")) {
                                if (
                                    window.confirm(
                                        "El ticket parece estar atascado. ¿Desea resetear el estado de impresión?"
                                    )
                                ) {
                                    resetPrintingState(params.id);
                                }
                            } else {
                                setLastDbRequest({
                                    dbFunction: db.imprimirTicked,
                                    params,
                                });
                                openValidationTarea(res.data.id_tarea);
                            }
                        } else {
                            notificar(res.data.msj);
                        }
                        setIsPrinting(false);
                    })
                    .catch((err) => {
                        setPrintError(err.message);
                        setIsPrinting(false);
                        notificar("Error al imprimir: " + err.message);
                    });
            } else {
                const params = {
                    id: id_fake ? id_fake : pedidoData.id,
                    moneda: monedaToPrint,
                    printer: selectprinter,
                };

                db.imprimirTicked(params)
                    .then((res) => {
                        if (res.data.estado === false) {
                            setPrintError(res.data.msj);
                            if (res.data.msj.includes("está siendo impreso")) {
                                if (
                                    window.confirm(
                                        "El ticket parece estar atascado. ¿Desea resetear el estado de impresión?"
                                    )
                                ) {
                                    resetPrintingState(params.id);
                                }
                            } else {
                                setLastDbRequest({
                                    dbFunction: db.imprimirTicked,
                                    params,
                                });
                                openValidationTarea(res.data.id_tarea);
                            }
                        } else {
                            notificar(res.data.msj);
                        }
                        setIsPrinting(false);
                    })
                    .catch((err) => {
                        setPrintError(err.message);
                        setIsPrinting(false);
                        notificar("Error al imprimir: " + err.message);
                    });
            }
        } else {
            console.log("NO pedidoData", toggleImprimirTicket);
            setIsPrinting(false);
        }
    };
    const onChangePedidos = (e) => {
        const type = e.currentTarget.attributes["data-type"].value;
        const value = e.currentTarget.value;
        switch (type) {
            case "busquedaPedido":
                setBusquedaPedido(value);
                break;
            case "fecha1pedido":
                setFecha1pedido(value);
                break;
            case "fecha2pedido":
                setFecha2pedido(value);
                break;
        }
    };
    const getPedidos = (e) => {
        if (e) {
            e.preventDefault();
        }
        setLoading(true);
        setPedidos([]);

        if (time != 0) {
            clearTimeout(typingTimeout);
        }
        let time = window.setTimeout(() => {
            db.getPedidos({
                vendedor: showMisPedido ? [user.id_usuario] : [],
                busquedaPedido,
                fecha1pedido,
                fecha2pedido,
                tipobusquedapedido,
                tipoestadopedido,
                filterMetodoPagoToggle,
                orderbycolumpedidos,
                orderbyorderpedidos,
            }).then((res) => {
                if (res.data) {
                    setPedidos(res.data);
                } else {
                    setPedidos([]);
                }
                setLoading(false);
            });
        }, 150);
        setTypingTimeout(time);
    };
    const getProductos = (valmain = null, itemCeroForce = null) => {
        // Marcar que la búsqueda está en progreso
        setSearchCompleted(false);

        // Limpiar el timeout anterior si existe
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }

        // Crear un nuevo timeout para el debounce
        const newTimeout = setTimeout(() => {
            db.getinventario({
                vendedor: showMisPedido ? [user.id_usuario] : [],
                num,
                itemCero: itemCeroForce ? itemCeroForce : itemCero,
                qProductosMain: valmain ? valmain : qProductosMain,
                orderColumn,
                orderBy,
            })
                .then((res) => {
                    if (res.data) {
                        if (res.data.estado === false) {
                            notificar(res.data.msj, false);
                        }
                        let len = res.data.length;
                        if (len) {
                            setProductos(res.data);
                        }
                        if (!len) {
                            setProductos([]);
                        }
                        if (!res.data[counterListProductos]) {
                            setCounterListProductos(0);
                            setCountListInter(0);
                        }

                        if (showinputaddCarritoFast) {
                            if (len == 1) {
                                setQProductosMain("");
                                let id_pedido_fact = null;
                                if (
                                    ModaladdproductocarritoToggle &&
                                    pedidoData.id
                                ) {
                                    id_pedido_fact = pedidoData.id;
                                }
                                addCarritoRequest(
                                    "agregar",
                                    res.data[0].id,
                                    id_pedido_fact
                                );
                            }
                        }
                    }
                    // Marcar que la búsqueda terminó después de un pequeño delay para asegurar que setProductos se complete
                    setTimeout(() => {
                        setSearchCompleted(true);
                    }, 100);
                    //setLoading(false);
                })
                .catch((error) => {
                    // En caso de error, también marcar como terminada
                    setTimeout(() => {
                        setSearchCompleted(true);
                    }, 100);
                    console.error("Error en getProductos:", error);
                });
        }, 100);

        setTypingTimeout(newTimeout);
    };
    const getPersona = (q) => {
        setLoading(true);
        if (time != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            db.getpersona({ q }).then((res) => {
                if (res.data) {
                    if (res.statusText == "OK") {
                        if (res.data.length) {
                            setPersona(res.data);
                        } else {
                            setPersona([]);
                        }
                    }
                    if (!res.data.length) {
                        setclienteInpidentificacion(q);
                    }
                    setLoading(false);
                }
            });
        }, 100);
        setTypingTimeout(time);
    };

    const exportPendientes = () => {
        db.showcsvInventario({});
    };

    const [garantiasData, setgarantiasData] = useState([]);
    const [qgarantia, setqgarantia] = useState("");
    const [garantiaorderCampo, setgarantiaorderCampo] =
        useState("sumpendiente");
    const [garantiaorder, setgarantiaorder] = useState("desc");

    const [garantiaEstado, setgarantiaEstado] = useState("pendiente");

    const getGarantias = () => {
        db.getGarantias({
            qgarantia,
            garantiaorderCampo,
            garantiaorder,
            garantiaEstado,
        }).then((res) => {
            setgarantiasData(res.data);
        });
    };

    const setSalidaGarantias = (id) => {
        if (confirm("Confirme")) {
            let cantidad = window.prompt("Cantidad de SALIDA");
            let motivo = window.prompt("DESCRIPCION DE SALIDA");

            if (cantidad && motivo) {
                db.setSalidaGarantias({
                    id,
                    cantidad: number(cantidad),
                    motivo,
                }).then((res) => {
                    getGarantias();
                    notificar(res);
                });
            } else {
                alert("Error: Datos incorrectos");
            }
        }
    };

    const [devolucionselect, setdevolucionselect] = useState(null);
    const [menuselectdevoluciones, setmenuselectdevoluciones] =
        useState("cliente");

    const [clienteselectdevolucion, setclienteselectdevolucion] =
        useState(null);
    const [productosselectdevolucion, setproductosselectdevolucion] = useState(
        []
    );
    const [prodTempoDevolucion, setprodTempoDevolucion] = useState({});

    const [devolucionSalidaEntrada, setdevolucionSalidaEntrada] =
        useState(null);
    const [devolucionTipo, setdevolucionTipo] = useState(null);
    const [devolucionCt, setdevolucionCt] = useState("");

    const [devolucionMotivo, setdevolucionMotivo] = useState("");
    const [devolucion_cantidad_salida, setdevolucion_cantidad_salida] =
        useState("");
    const [devolucion_motivo_salida, setdevolucion_motivo_salida] =
        useState("");
    const [devolucion_ci_cajero, setdevolucion_ci_cajero] = useState("");
    const [devolucion_ci_autorizo, setdevolucion_ci_autorizo] = useState("");
    const [devolucion_dias_desdecompra, setdevolucion_dias_desdecompra] =
        useState("");
    const [devolucion_ci_cliente, setdevolucion_ci_cliente] = useState("");
    const [devolucion_telefono_cliente, setdevolucion_telefono_cliente] =
        useState("");
    const [devolucion_nombre_cliente, setdevolucion_nombre_cliente] =
        useState("");
    const [devolucion_nombre_cajero, setdevolucion_nombre_cajero] =
        useState("");
    const [devolucion_nombre_autorizo, setdevolucion_nombre_autorizo] =
        useState("");
    const [devolucion_trajo_factura, setdevolucion_trajo_factura] =
        useState("");
    const [devolucion_motivonotrajofact, setdevolucion_motivonotrajofact] =
        useState("");
    const [devolucion_numfactoriginal, setdevolucion_numfactoriginal] =
        useState("");

    const [viewGarantiaFormato, setviewGarantiaFormato] = useState(false);

    const [pagosselectdevolucion, setpagosselectdevolucion] = useState([]);
    const [pagosselectdevoluciontipo, setpagosselectdevoluciontipo] =
        useState("");
    const [pagosselectdevolucionmonto, setpagosselectdevolucionmonto] =
        useState("");

    const getSum = (total, num) => {
        return total + number(num.cantidad) * number(num.precio);
    };

    let devolucionsumentrada = () => {
        let val = productosselectdevolucion
            .filter((e) => e.tipo == 1)
            .reduce(getSum, 0);
        return val;
    };
    let devolucionsumsalida = () => {
        let val = productosselectdevolucion
            .filter((e) => e.tipo == 0)
            .reduce(getSum, 0);
        return val;
    };
    let devolucionsumdiferencia = () => {
        let val = devolucionsumsalida() - devolucionsumentrada();
        return {
            dolar: val,
            bs: val * dolar,
        };
    };

    const sethandlepagosselectdevolucion = () => {
        if (
            !pagosselectdevolucion.filter(
                (e) => e.tipo == pagosselectdevoluciontipo
            ).length &&
            (pagosselectdevoluciontipo || pagosselectdevolucionmonto)
        ) {
            setpagosselectdevolucion(
                pagosselectdevolucion.concat({
                    tipo: pagosselectdevoluciontipo,
                    monto: pagosselectdevolucionmonto,
                })
            );
        }
    };
    const delpagodevolucion = (id) => {
        setpagosselectdevolucion(
            pagosselectdevolucion.filter((e) => e.tipo != id)
        );
    };
    const delproductodevolucion = (id) => {
        setproductosselectdevolucion(
            productosselectdevolucion.filter((e) => e.idproducto != id)
        );
    };

    const getBuscarDevolucionhistorico = () => {
        setLoading(true);

        db.getBuscarDevolucionhistorico({
            q: buscarDevolucionhistorico,
            num: 10,
            orderColumn: "id",
            orderBy: "desc",
        }).then((res) => {
            setproductosDevolucionSelecthistorico(res.data);
            setLoading(false);
        });
    };
    const agregarProductoDevolucionTemporal = () => {
        setprodTempoDevolucion({});
        let id = prodTempoDevolucion.id;
        let precio = prodTempoDevolucion.precio;
        let descripcion = prodTempoDevolucion.descripcion;
        let codigo = prodTempoDevolucion.codigo_barras;
        if (
            !productosselectdevolucion.filter(
                (e) => e.idproducto == id && e.tipo == devolucionSalidaEntrada
            ).length
        ) {
            setproductosselectdevolucion(
                productosselectdevolucion.concat({
                    idproducto: id,
                    tipo: devolucionSalidaEntrada,
                    categoria: devolucionTipo,
                    cantidad: devolucionCt,
                    motivo: devolucionMotivo,
                    precio: precio,
                    descripcion: descripcion,
                    codigo: codigo,
                })
            );
        }

        console.log(productosselectdevolucion);
    };
   

    const setPersonaFastDevolucion = (e) => {
        e.preventDefault();
        db.setClienteCrud({
            id: null,
            clienteInpidentificacion,
            clienteInpnombre,
            clienteInpdireccion,
            clienteInptelefono,
        }).then((res) => {
            notificar(res);
            if (res.data) {
                if (res.data.estado) {
                    if (res.data.id) {
                        setclienteselectdevolucion(res.data.id);
                        setmenuselectdevoluciones("inventario");
                    }
                }
            }
            setLoading(false);
        });
    };

    const createDevolucion = (id) => {
        setLoading(true);

        if (devolucionselect) {
            alert("Error: Devolución pendiente");
        } else {
            db.createDevolucion({
                idcliente: id,
            }).then((res) => {
                notificar(res);
                if (res.data) {
                    let data = res.data;
                    setdevolucionselect(data.id_devolucion);
                }
                setLoading(false);
            });
        }
    };

    const setDevolucion = () => {
        if (
            confirm(
                "¿Está seguro de guardar la información? Ésta acción no se puede deshacer"
            )
        ) {
            let tipo;
            if (devolucionsumdiferencia().dolar > 0) {
                tipo = 1;
            }
            if (devolucionsumdiferencia().dolar < 0) {
                tipo = -1;
            }

            let procesado = false;

            let ref, banco;

            pagosselectdevolucion.map((e) => {
                if (e.tipo == "1") {
                    ref = window.prompt("Referencia");
                    banco = window.prompt("Banco");
                }
                if (e.tipo == "1" && (!ref || !banco)) {
                    alert(
                        "Error: Debe cargar referencia de transferencia electrónica."
                    );
                } else {
                    db.setPagoCredito({
                        id_cliente: clienteselectdevolucion,
                        tipo_pago_deudor: e.tipo,
                        monto_pago_deudor: e.monto * tipo,
                    }).then((res) => {
                        notificar(res);
                        if (res.data.estado) {
                            setpagosselectdevolucion([]);
                        }
                        if (ref && banco) {
                            db.addRefPago({
                                check: false,
                                tipo: 1,
                                descripcion: ref,
                                banco: banco,
                                monto: e.monto * tipo,
                                id_pedido: res.data.id_pedido,
                            }).then((res) => {
                                notificar(res);
                            });
                        }
                    });
                }
            });

            db.setDevolucion({
                productosselectdevolucion,
                id_cliente: clienteselectdevolucion,
            }).then((res) => {
                procesado = false;
                notificar(res);
                if (res.data.estado) {
                    setproductosselectdevolucion([]);
                    setclienteselectdevolucion(null);
                }
            });
        }
    };

    const setpagoDevolucion = (id_producto) => {
        db.setpagoDevolucion({
            devolucionselect,
            id_producto,
        }).then((res) => {
            notificar(res);
            if (res.data) {
                let data = res.data;
            }
            setLoading(false);
        });
    };

    const setPersonaFast = (e) => {
        e.preventDefault();
        db.setClienteCrud({
            id: null,
            clienteInpidentificacion,
            clienteInpnombre,
            clienteInpdireccion,
            clienteInptelefono,
        }).then((res) => {
            notificar(res);
            if (res.data) {
                if (res.data.estado) {
                    if (res.data.id) {
                        setPersonas(res.data.id);
                    }
                }
            }
            setLoading(false);
        });
    };
    const printCreditos = () => {
        db.openPrintCreditos(
            "qDeudores=" +
                qDeudores +
                "&orderbycolumdeudores=" +
                orderbycolumdeudores +
                "&orderbyorderdeudores=" +
                orderbyorderdeudores +
                ""
        );
    };
    const getPedidosList = (callback = null) => {
        db.getPedidosList({
            vendedor: user.id_usuario ? user.id_usuario : 1,
        }).then((res) => {
            setNumero_factura("nuevo");

            if (res.data.length) {
                setNumero_factura("ultimo");
            }
            if (callback) {
                callback("ultimo");
            }
        });
    };
    function allReplace(str, obj) {
        for (const x in obj) {
            str = str.replace(new RegExp(x, "g"), obj[x]);
        }
        return str;
    }
    const showAjustesPuntuales = () => {
        /*let code = Date.now().toString()
        let desbloqueo = window.prompt("CLAVE DE ACCESO: NÚMERO DE MOVIMIENTO: "+code) 

        let clave = code.split("").reverse().join("").substr(0,1)
        let clave2 = code.split("").reverse().join("").substr(1,1)
        let clave3 = code.split("").reverse().join("").substr(3,3)

        const d = new Date();
        let hour = d.getHours();

        let llave = allReplace(clave+hour+clave2+clave3, 
        {"0":"X","1":"L","2":"R","3":"E","4":"A","5":"S","6":"G","7":"F","8":"B","9":"P"})

         if (desbloqueo==llave) {
        }else{
            alert("¡CLAVE INCORRECTA!")
        } */
        setsubViewInventario("inventario");
        setView("inventario");
    };
    const [showModalPedidoFast, setshowModalPedidoFast] = useState(false);
    const getPedidoFast = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        setshowModalPedidoFast(true);
        getPedido(id);
    };

    const getReferenciasElec = () => {
        setrefrenciasElecData([]);
        db.getReferenciasElec({
            fecha1pedido,
            fecha2pedido,
        }).then((res) => {
            setrefrenciasElecData(res.data);
        });
    };
    const setGastoOperativo = () => {
        
    };
    const getPedido = (id, callback = null, clearPagosPedido = true) => {
        setLoading(true);
        if (!id) {
            id = pedidoSelect;
        } else {
            setPedidoSelect(id);
        }
        db.getPedido({ id }).then((res) => {
            setLoading(false);
            if (res.data) {
                if (res.data.estado === false) {
                    notificar(res.data.msj, false);
                }
                setPedidoData(res.data);
                setdatadeudacredito({});
                setviewconfigcredito(false);

                if (clearPagosPedido) {
                    setTransferencia("");
                    setDebito("");
                    setDebitoRef(""); // Limpiar referencia de débito
                    setForzarReferenciaManual(false); // Resetear modo manual
                    setEfectivo("");
                    setEfectivo_bs("");
                    setEfectivo_dolar("");
                    setEfectivo_peso("");
                    setCredito("");
                    setVuelto("");
                    setBiopago("");
                }
                setrefPago([]);

                getPedidosFast();

                if (res.data.referencias) {
                    if (res.data.referencias.length) {
                        setrefPago(res.data.referencias);
                    }
                } else {
                    setrefPago([]);
                }

                if (res.data.pagos) {
                    let d = res.data.pagos;
                    
                    // Transferencia (tipo 1)
                    const pagoTransf = d.find((e) => e.tipo == 1);
                    if (pagoTransf && pagoTransf.monto != "0.00") {
                        setTransferencia(pagoTransf.monto);
                    }
                    
                    // Débito (tipo 2) - Usar monto_original (Bs) si existe
                    const pagoDebito = d.find((e) => e.tipo == 2);
                    if (pagoDebito && pagoDebito.monto != "0.00") {
                        // monto_original tiene el valor en Bs que escribió la cajera
                        const valorDebito = pagoDebito.monto_original || pagoDebito.monto;
                        setDebito(valorDebito);
                        // También setear la referencia si existe
                        if (pagoDebito.referencia) {
                            setDebitoRef(pagoDebito.referencia);
                        }
                    }
                    
                    // Efectivo (tipo 3) - Procesar según moneda
                    const pagosEfectivo = d.filter((e) => e.tipo == 3);
                    pagosEfectivo.forEach((pago) => {
                        if (pago.monto == "0.00") return;
                        
                        // Usar monto_original si existe (valor en moneda original)
                        const valor = pago.monto_original || pago.monto;
                        
                        if (pago.moneda === 'dolar' || !pago.moneda) {
                            // Efectivo USD
                            setEfectivo_dolar(valor);
                        } else if (pago.moneda === 'bs') {
                            // Efectivo Bs
                            setEfectivo_bs(valor);
                        } else if (pago.moneda === 'peso') {
                            // Efectivo Pesos
                            setEfectivo_peso(valor);
                            setShowEfectivoPeso(true); // Mostrar el input de pesos
                        }
                    });
                    
                    // Crédito (tipo 4)
                    const pagoCredito = d.find((e) => e.tipo == 4);
                    if (pagoCredito && pagoCredito.monto != "0.00") {
                        setCredito(pagoCredito.monto);
                        setShowCredito(true); // Mostrar el input de crédito
                    }

                    // Biopago (tipo 5)
                    const pagoBio = d.find((e) => e.tipo == 5);
                    if (pagoBio && pagoBio.monto != "0.00") {
                        setBiopago(pagoBio.monto);
                    }
                    
                    // Vuelto (tipo 6)
                    const pagoVuelto = d.find((e) => e.tipo == 6);
                    if (pagoVuelto && pagoVuelto.monto != "0.00") {
                        setVuelto(pagoVuelto.monto);
                    }
                }
                if (callback) {
                    callback();
                }
            }
        });
    };
    
    const addCarrito = (e, callback = null) => {
        let index, loteid;
        if (e.currentTarget) {
            let attr = e.currentTarget.attributes;
            index = attr["data-index"].value;

            if (attr["data-loteid"]) {
                loteid = attr["data-loteid"].value;
            }
        } else {
            index = e;
        }
        setLoteIdCarrito(loteid);

        if (index != counterListProductos && 0) {
            setCounterListProductos(index);
        } else {
            setSelectItem(parseInt(index));
            if (callback) {
                callback();
            }
        }
    };
    const addCarritoRequest = (
        e,
        id_direct = null,
        id_pedido_direct = null,
        cantidad_direct = null
    ) => {
        try {
            setLoading(true);
            let type;
            if (e.currentTarget) {
                type = e.currentTarget.attributes["data-type"].value;
                e.preventDefault();
            } else {
                type = e;
            }
            let id = null;
            if (productos[selectItem]) {
                id = productos[selectItem].id;
            }
            if (id_direct) {
                id = id_direct;
            }

            db.setCarrito({
                id,
                type,
                cantidad: cantidad_direct ? cantidad_direct : cantidad,
                numero_factura: id_pedido_direct
                    ? id_pedido_direct
                    : numero_factura,
                loteIdCarrito,
            }).then((res) => {
                if (res.data.msj) {
                    notificar(res.data.msj);
                }
                if (numero_factura == "nuevo") {
                    //getPedidosList();
                }
                switch (res.data.type) {
                    case "agregar":
                        setSelectItem(null);
                        notificar(res);

                        if (
                            showinputaddCarritoFast &&
                            ModaladdproductocarritoToggle
                        ) {
                            getPedido(res.data.num_pedido);
                        }

                        break;
                    case "agregar_procesar":
                        getPedido(res.data.num_pedido, () => {
                            setView("pagar");
                            setSelectItem(null);
                        });
                        break;
                }
                setCantidad("");
                if (inputbusquedaProductosref) {
                    if (inputbusquedaProductosref.current) {
                        inputbusquedaProductosref.current.value = "";
                        inputbusquedaProductosref.current.focus();
                    }
                }

                setLoading(false);
            });
        } catch (err) {
            alert(err);
        }
    };
    const onClickEditPedido = (e, id_force = null) => {
        let id;
        if (!e && id_force) {
            id = id_force;
        } else {
            id = e.currentTarget.attributes["data-id"].value;
        }
        getPedido(id, () => {
            setView("pagar");
        });
    };
    const setexportpedido = () => {
        let sucursal = sucursalesCentral.filter(
            (e) => e.id == transferirpedidoa
        );

        if (sucursal.length) {
            if (
                confirm(
                    "¿Realmente desea exportar los productos a " +
                        sucursal[0].nombre +
                        "?"
                )
            ) {
                let params = { transferirpedidoa, id: pedidoData.id };
                db.setexportpedido(params).then((res) => {
                    notificar(res);
                    if (res.data.estado) {
                        setView("pagar");
                        getProductos();
                        setSelectItem(null);
                        db.openTransferenciaPedido(pedidoData.id);
                    }
                    if (res.data.estado === false) {
                        setLastDbRequest({
                            dbFunction: db.setexportpedido,
                            params,
                        });
                        openValidationTarea(res.data.id_tarea);
                    }
                });
            }
        }
    };

  
    const onCLickDelPedido = (e) => {
        if (confirm("¿Seguro de eliminar?")) {
            const current = e.currentTarget.attributes;
            const id = current["data-id"].value;
            let motivo = window.prompt("¿Cuál es el Motivo de eliminación?");
            if (motivo) {
                let params = { id, motivo };
                db.delpedido(params).then((res) => {
                    notificar(res);

                    switch (current["data-type"].value) {
                        case "getDeudor":
                            getDeudor();

                            break;

                        case "getPedidos":
                            getPedidos();
                            //getPedidosList();

                            break;
                    }
                    if (res.data.estado === false) {
                        setLastDbRequest({ dbFunction: db.delpedido, params });
                        openValidationTarea(res.data.id_tarea);
                    }
                });
            }
        }
    };
    const delItemPedido = (e) => {
        setLoading(true);
        const index = e.currentTarget.attributes["data-index"].value;
        let params = { index };
        db.delItemPedido(params).then((res) => {
            getPedido();
            setLoading(false);
            notificar(res);
            if (res.data.estado === false) {
                setLastDbRequest({ dbFunction: db.delItemPedido, params });
                openValidationTarea(res.data.id_tarea);
            }
        });
    };
    const setPrecioAlternoCarrito = (e) => {
        let iditem = e.currentTarget.attributes["data-iditem"].value;
        let p = window.prompt("p1 | p2", "p1");
        if (p == "p1" || p == "p2") {
            db.setPrecioAlternoCarrito({ iditem, p }).then((res) => {
                notificar(res);
                getPedido();
            });
        }
    };

    const setCtxBultoCarrito = (e) => {
        let iditem = e.currentTarget.attributes["data-iditem"].value;
        let ct = window.prompt("Cantidad por bulto");
        if (ct) {
            db.setCtxBultoCarrito({ iditem, ct }).then((res) => {
                notificar(res);
                getPedido();
            });
        }
    };

    const setDescuentoTotal = (e) => {
        // setLoading(true)

        let descuento = window.prompt("Descuento Total *0 para eliminar*");
        let index = e.currentTarget.attributes["data-index"].value;

        if (descuento) {
            if (descuento == "0") {
                let params = { index, descuento: 0 };
                db.setDescuentoTotal(params).then((res) => {
                    getPedido();
                    setLoading(false);
                    notificar(res);
                    if (res.data.estado === false) {
                        setLastDbRequest({
                            dbFunction: db.setDescuentoTotal,
                            params,
                        });
                        openValidationTarea(res.data.id_tarea);
                    }
                });
            } else {
                if (
                    typeof parseFloat(descuento) == "number" &&
                    pedidoData.clean_subtotal
                ) {
                    let total = parseFloat(pedidoData.clean_subtotal);

                    descuento =
                        100 -
                        ((parseFloat(descuento) * 100) / total).toFixed(3);

                    let params = { index, descuento };
                    db.setDescuentoTotal(params).then((res) => {
                        getPedido();
                        setLoading(false);
                        notificar(res);
                        if (res.data.estado === false) {
                            setLastDbRequest({
                                dbFunction: db.setDescuentoTotal,
                                params,
                            });
                            openValidationTarea(res.data.id_tarea);
                        }
                    });
                }
            }
        }
    };
    const setDescuentoUnitario = (e) => {
        const index = e.currentTarget.attributes["data-index"].value;
        
        // Buscar el item en el pedido para obtener su subtotal
        const item = pedidoData.items?.find(i => i.id == index);
        if (!item) {
            notificar({ data: { msj: "Item no encontrado", estado: false } });
            return;
        }
        
        // Calcular el subtotal del item (precio * cantidad)
        const subtotalItem = parseFloat(item.producto?.precio || 0) * Math.abs(parseFloat(item.cantidad || 0));
        
        if (subtotalItem <= 0) {
            notificar({ data: { msj: "El item no tiene subtotal válido", estado: false } });
            return;
        }
        
        const montoFinal = window.prompt(
            `Monto total final del ítem (Subtotal actual: $${subtotalItem.toFixed(2)})\n*Ingrese 0 para eliminar descuento*`
        );
        
        if (montoFinal === null || montoFinal === "") return;
        
        setLoading(true);
        
        if (montoFinal == "0") {
            // Eliminar descuento
            let params = { index, descuento: 0 };
            db.setDescuentoUnitario(params).then((res) => {
                getPedido();
                setLoading(false);
                notificar(res);
                if (res.data.estado === false) {
                    setLastDbRequest({
                        dbFunction: db.setDescuentoUnitario,
                        params,
                    });
                    openValidationTarea(res.data.id_tarea);
                }
            });
        } else {
            const montoFinalNum = parseFloat(montoFinal);
            if (isNaN(montoFinalNum) || montoFinalNum < 0) {
                notificar({ data: { msj: "Monto inválido", estado: false } });
                setLoading(false);
                return;
            }
            
            if (montoFinalNum >= subtotalItem) {
                notificar({ data: { msj: "El monto final debe ser menor al subtotal", estado: false } });
                setLoading(false);
                return;
            }
            
            // Calcular el porcentaje de descuento: descuento = 100 - ((montoFinal * 100) / subtotal)
            const descuento = (100 - ((montoFinalNum * 100) / subtotalItem)).toFixed(3);
            
            let params = { index, descuento };
            db.setDescuentoUnitario(params).then((res) => {
                getPedido();
                setLoading(false);
                notificar(res);
                if (res.data.estado === false) {
                    setLastDbRequest({
                        dbFunction: db.setDescuentoUnitario,
                        params,
                    });
                    openValidationTarea(res.data.id_tarea);
                }
            });
        }
    };
    const setCantidadCarrito = (e) => {
        const cantidad = window.prompt("Cantidad");
        if (cantidad) {
            const index = e.currentTarget.attributes["data-index"].value;
            setLoading(true);
            let params = { index, cantidad };
            db.setCantidad(params).then((res) => {
                getPedido();
                setLoading(false);
                notificar(res);
                if (res.data.estado === false) {
                    setLastDbRequest({ dbFunction: db.setCantidad, params });
                    openValidationTarea(res.data.id_tarea);
                }
            });
        }
    };
    const [productoSelectinternouno, setproductoSelectinternouno] =
        useState(null);
    const setProductoCarritoInterno = (id) => {
        let prod = productos.filter((e) => e.id == id)[0];
        setproductoSelectinternouno({
            descripcion: prod.descripcion,
            precio: prod.precio,
            unidad: prod.unidad,
            cantidad: prod.cantidad,
            id,
        });
        setdevolucionTipo(null);
        setModaladdproductocarritoToggle(true);
    };
    const addCarritoRequestInterno = (e = null, isnotformatogan = true) => {
        if (e) {
            e.preventDefault();
        }

        if (devolucionTipo == 1 && isnotformatogan) {
            setviewGarantiaFormato(true);
        } else {
            let type = "agregar";
            let params = {
                id: productoSelectinternouno.id,
                type,
                cantidad,
                numero_factura: pedidoData.id,
                devolucionTipo: devolucionTipo,

                devolucionMotivo,
                devolucion_cantidad_salida,
                devolucion_motivo_salida,
                devolucion_ci_cajero,
                devolucion_ci_autorizo,
                devolucion_dias_desdecompra,
                devolucion_ci_cliente,
                devolucion_telefono_cliente,
                devolucion_nombre_cliente,
                devolucion_nombre_cajero,
                devolucion_nombre_autorizo,
                devolucion_trajo_factura,
                devolucion_motivonotrajofact,
                devolucion_numfactoriginal,
            };
            db.setCarrito(params).then((res) => {
                if (res.data.msj) {
                    notificar(res.data.msj);
                }
                getPedido();
                setLoading(false);
                setinputqinterno("");
                setproductoSelectinternouno(null);
                setView("pagar");

                setviewGarantiaFormato(false);
                setdevolucionMotivo("");
                setdevolucion_cantidad_salida("");
                setdevolucion_motivo_salida("");
                setdevolucion_ci_cajero("");
                setdevolucion_ci_autorizo("");
                setdevolucion_dias_desdecompra("");
                setdevolucion_ci_cliente("");
                setdevolucion_telefono_cliente("");
                setdevolucion_nombre_cliente("");
                setdevolucion_nombre_cajero("");
                setdevolucion_nombre_autorizo("");
                setdevolucion_trajo_factura("");
                setdevolucion_motivonotrajofact("");
                setdevolucion_numfactoriginal("");

                if (res.data.estado === false) {
                    setLastDbRequest({ dbFunction: db.setCarrito, params });
                    openValidationTarea(res.data.id_tarea);
                }
            });

            setdevolucionTipo(null);
            setCantidad("");
        }
    };
    const setPersonas = (e) => {
        setLoading(true);
        let id_cliente;

        if (e.currentTarget) {
            id_cliente = e.currentTarget.attributes["data-index"].value;
        } else {
            id_cliente = e;
        }
        if (pedidoData.id) {
            db.setpersonacarrito({
                numero_factura: pedidoData.id,
                id_cliente,
            }).then((res) => {
                getPedido();
                setToggleAddPersona(false);
                setLoading(false);
                notificar(res);
            });
        }
    };

    const sendNotaCredito = () => {
        let numfact = prompt(
            "Indique el número de Factura Fiscal que desea Afectar"
        );
        let serial = prompt(
            "Indique el Serial de la Factura. Ejemplo: ZPA2000343"
        );
        let conf = confirm(
            "Confirme los Datos introducidos. Si hay un error, cancele esta ventana. #Factura: " +
                numfact +
                " SerialDeFactura: " +
                serial
        );
        if (numfact && serial && conf) {
            db.sendNotaCredito({ pedido: pedidoData.id, numfact, serial }).then(
                (res) => {
                    notificar(res);
                }
            );
        }
    };
    const sendReciboFiscal = () => {
        if (confirm("CONFIRME RECIBO FISCAL")) {
            db.sendReciboFiscal({ pedido: pedidoData.id }).then((res) => {
                notificar(res);
            });
        }
    };
    const [numReporteZ, setnumReporteZ] = useState("");
    const reportefiscal = (type) => {
        if (confirm("CONFIRME REPORTE " + type)) {
            let caja = prompt("Indique la Caja");
            if (caja) {
                db.reportefiscal({
                    type,
                    numReporteZ,
                    caja,
                }).then(({ data }) => {
                    notificar(data);
                });
            }
        }
    };
    const sincInventario = () => {
        if (
            confirm(
                "¿Seguro de sincronizar inventario, puede tardar varios minutos?"
            )
        ) {
            db.sincInventario({});
        }
    };
    const [buscarDatosFact, setbuscarDatosFact] = useState("");
    const [counterScan, setcounterScan] = useState(0);
    const openBarcodeScan = (callback) => {
        // Open barcode scanner library
        let scanner = new Html5QrcodeScanner("reader", {
            fps: 10,
            qrbox: { width: 300, height: 150 },
        });

        scanner.render(success, error);

        function success(result) {
            if (typeof callback === "function") {
                callback(result);
                if (callback === "qBuscarInventario") {
                    inputBuscarInventario.current.value = result;
                    inputBuscarInventario.current.focus();
                    scanner.clear();
                }
                if (callback === "setbuscarDatosFact") {
                    setbuscarDatosFact(result);
                    scanner.clear();
                }
                if (callback === "inputbusquedaProductosref") {
                    inputbusquedaProductosref.current.value = result;
                    inputbusquedaProductosref.current.focus();
                    scanner.clear();
                }
            }
        }

        function error(err) {
            //console.error(err);
        }
        if (counterScan === 1) {
            scanner.clear();
        }
        setcounterScan(counterScan === 0 ? 1 : 0);
    };

    const [showXBulto, setshowXBulto] = useState(false);
    const [changeOnlyInputBulto, setchangeOnlyInputBulto] = useState({});

    const setchangeOnlyInputBultoFun = (value, id_item) => {
        let clone_changeOnlyInputBulto = cloneDeep(changeOnlyInputBulto);
        clone_changeOnlyInputBulto[id_item] = number(value, 3);

        setchangeOnlyInputBulto(clone_changeOnlyInputBulto);
    };
    const printBultos = () => {
        // let bultos = JSON.stringify(changeOnlyInputBulto)
        let num_bulto = window.prompt("Número de Bultos");
        if (num_bulto) {
            db.printBultos(pedidoData.id, num_bulto);
        }
    };

    const setModRetencion = () => {
        db.setModRetencion({
            id: pedidoData.id,
        }).then((res) => {
            getPedido(null, null, false);
        });
    };

    const facturar_e_imprimir = () => {
        facturar_pedido(() => {
            toggleImprimirTicket();
        });
    };
    const [puedeFacturarTransfe, setpuedeFacturarTransfe] = useState(true);
    const [puedeFacturarTransfeTime, setpuedeFacturarTransfeTime] =
        useState(null);
    const setPagoPedido = (callback = null) => {
        // Si hay débito positivo y la sucursal tiene PINPAD activo (y NO está forzando manual), abrir modal del POS físico
        // No se requiere validar debitoRef porque el POS lo setea automáticamente
        if (debito && parseFloat(debito) > 0 && sucursaldata?.pinpad && !forzarReferenciaManual) {
            abrirModalPosDebito(callback);
            return;
        }
        
        // Validar referencia de débito obligatoria (si NO hay PINPAD o si está forzando manual)
        if (debito && parseFloat(debito) > 0 && !debitoRef) {
            // Mostrar error visual y hacer foco en el campo de referencia
            setDebitoRefError(true);
            setTimeout(() => {
                const debitoRefInput = document.querySelector('[data-ref-input="true"]');
                if (debitoRefInput) {
                    debitoRefInput.focus();
                    debitoRefInput.select();
                }
            }, 100);
            return;
        }
        // Validar que la referencia de débito tenga exactamente 4 dígitos (solo si NO hay PINPAD)
        if (debito && parseFloat(debito) > 0 && debitoRef && debitoRef.length !== 4) {
            alert("Error: La referencia de débito debe tener exactamente 4 dígitos.");
            setDebitoRefError(true);
            setTimeout(() => {
                const debitoRefInput = document.querySelector('[data-ref-input="true"]');
                if (debitoRefInput) {
                    debitoRefInput.focus();
                    debitoRefInput.select();
                }
            }, 100);
            return;
        }
        
        if (
            confirm(
                "¿Realmente desea guardar e imprimir pedido (" +
                    pedidoData.id +
                    ")?"
            )
        ) {
            // Limpiar error si la referencia está cargada
            setDebitoRefError(false);
            if (transferencia && !refPago.filter((e) => e.tipo == 1).length) {
                alert(
                    "Error: Debe cargar referencia de transferencia electrónica."
                );
            } else {
                procesarPagoInterno(callback);
            }
        }
    };
    
    // Función interna para procesar el pago (llamada después de POS exitoso o sin débito)
    // refOverride: permite pasar la referencia directamente (para cuando viene del POS)
    const procesarPagoInterno = (callback = null, refOverride = null) => {
        setLoading(true);
        // Construir pagos adicionales de efectivo (Bs y COP)
        let pagosAdicionales = [];
        if (efectivo_bs && parseFloat(efectivo_bs) != 0) {
            pagosAdicionales.push({ 
                moneda: 'bs', 
                monto_original: parseFloat(efectivo_bs)
            });
        }
        if (efectivo_peso && parseFloat(efectivo_peso) != 0) {
            pagosAdicionales.push({ 
                moneda: 'peso', 
                monto_original: parseFloat(efectivo_peso)
            });
        }
        
        const debitoBs = parseFloat(debito) || 0;
        const efectivoDolarVal = parseFloat(efectivo_dolar) || 0;
        
        // Usar refOverride si viene del POS, sino usar el estado debitoRef
        const refFinal = refOverride !== null ? refOverride : debitoRef;
        
        let params = {
            id: pedidoData.id,
            debito: debitoBs != 0 ? debitoBs : null,
            debitoRef: refFinal,
            efectivo: efectivoDolarVal != 0 ? efectivoDolarVal : null,
            transferencia,
            biopago,
            credito,
            vuelto,
            pagosAdicionales: pagosAdicionales.length > 0 ? pagosAdicionales : null,
        };
        
        db.setPagoPedido(params).then((res) => {
            notificar(res);
            setLoading(false);
            if (res.data.estado) {
                getPedidosFast();
                setPedidoData({});
                setSelectItem(null);
                setviewconfigcredito(false);
                if (callback) {
                    callback();
                }
            }
            if (res.data.estado === false) {
                setLastDbRequest({
                    dbFunction: db.setPagoPedido,
                    params,
                });
                openValidationTarea(res.data.id_tarea);
            }
        });
    };
    
    // Función para enviar solicitud al POS físico de débito
    const enviarSolicitudPosDebito = async () => {
        if (!posCedulaTitular) {
            alert("Debe ingresar la cédula del titular");
            return;
        }
        
        const montoActual = parseFloat(posMontoDebito) || 0;
        if (montoActual <= 0) {
            alert("Debe ingresar un monto válido");
            return;
        }
        
        setPosLoading(true);
        
        // Convertir monto: 12.36 -> 1236 (sin decimales, multiplicado por 100)
        const montoEntero = Math.round(montoActual * 100);
        
        const payload = {
            operacion: "COMPRA",
            monto: montoEntero,
            cedula: posCedulaTitular,
            numeroOrden: pedidoData.id.toString(),
            mensaje: "PowerBy:Ospino",
            tipoCuenta: posTipoCuenta
        };

        console.log("Payload POS:", payload);
        
        try {
            // Enviar a través del backend para evitar CORS
            const response = await db.enviarTransaccionPOS(payload);
            console.log("Respuesta POS:", response.data);
            
            const posData = response.data.data || {};
            
            if (response.data.success && posData.success) {
                // Éxito: agregar transacción al array
                const posReference = posData.reference || posData.approval || "";
                const refUltimos4 = posReference.slice(-4).padStart(4, '0');
                
                const nuevaTransaccion = {
                    monto: montoActual,
                    cedula: posCedulaTitular,
                    referencia: posReference,
                    refCorta: refUltimos4,
                    tipoCuenta: posTipoCuenta
                };
                
                const nuevasTransacciones = [...posTransaccionesAprobadas, nuevaTransaccion];
                setPosTransaccionesAprobadas(nuevasTransacciones);
                
                // Guardar en el mapa por pedido
                if (pedidoData?.id) {
                    guardarTransaccionPorPedido(pedidoData.id, nuevasTransacciones);
                }
                
                // Calcular monto restante
                const totalAprobado = nuevasTransacciones.reduce((sum, t) => sum + t.monto, 0);
                const montoOriginal = parseFloat(posMontoTotalOriginal) || 0;
                const restante = montoOriginal - totalAprobado;
                
                setPosRespuesta({ mensaje: `✓ APROBADO - Ref: ${posReference}`, exito: true });
                notificar({ data: { msj: `POS APROBADO - Ref: ${posReference} - Bs ${montoActual.toFixed(2)}`, estado: true } });
                
                // Limpiar campos para siguiente transacción
                setPosCedulaTitular("");
                setPosTipoCuenta("CORRIENTE");
                
                if (restante > 0) {
                    // Aún falta monto, preparar para siguiente transacción
                    setPosMontoDebito(restante.toFixed(2));
                    // Hacer foco en el input de cédula para la siguiente transacción
                    setTimeout(() => {
                        const inputCedula = document.getElementById('pos-cedula-input');
                        if (inputCedula) inputCedula.focus();
                    }, 100);
                } else {
                    // Ya se cubrió el total, finalizar automáticamente de inmediato
                    // Usar la referencia de la última transacción como referencia principal
                    const ultimaTrans = nuevasTransacciones[nuevasTransacciones.length - 1];
                    const refFinal = ultimaTrans.refCorta;
                    const totalAprobadoFinal = nuevasTransacciones.reduce((sum, t) => sum + t.monto, 0);
                    const numTransacciones = nuevasTransacciones.length;
                    
                    // Limpiar transacciones del mapa para este pedido
                    if (pedidoData?.id) {
                        limpiarTransaccionesPedido(pedidoData.id);
                    }
                    
                    // Cerrar modal y limpiar estados
                    setShowModalPosDebito(false);
                    setPosCedulaTitular("");
                    setPosTipoCuenta("CORRIENTE");
                    setPosMontoDebito("");
                    setPosRespuesta(null);
                    setPosTransaccionesAprobadas([]);
                    setPosMontoTotalOriginal("");
                    setDebitoRef(refFinal);
                    
                    // Actualizar el monto de débito con el total aprobado
                    setDebito(totalAprobadoFinal.toFixed(2));
                    
                    notificar({ data: { msj: `✓ POS Completado - ${numTransacciones} transacción(es) - Total: Bs ${totalAprobadoFinal.toFixed(2)}`, estado: true } });
                    procesarPagoInterno(posPendingCallback, refFinal);
                    setPosPendingCallback(null);
                }
            } else {
                // Error del POS - mostrar mensaje en el modal (no cerrar)
                const errorMsg = posData.message || response.data.error || "Error en transacción POS";
                setPosRespuesta({ mensaje: `✗ ${errorMsg}`, exito: false });
            }
        } catch (error) {
            console.error("Error POS:", error);
            const errorMsg = error.response?.data?.error || error.message || "Error de conexión";
            setPosRespuesta({ mensaje: `✗ ${errorMsg}`, exito: false });
        } finally {
            setPosLoading(false);
        }
    };
    
    // Función para finalizar múltiples transacciones POS
    // Si el total no está cubierto, solo aplica el monto al campo débito sin procesar el pago
    const finalizarTransaccionesPOS = (forzarSinProcesar = false) => {
        if (posTransaccionesAprobadas.length === 0) {
            alert("No hay transacciones aprobadas");
            return;
        }
        
        // Usar la referencia de la última transacción como referencia principal
        const ultimaTransaccion = posTransaccionesAprobadas[posTransaccionesAprobadas.length - 1];
        const refFinal = ultimaTransaccion.refCorta;
        
        // Calcular total aprobado y verificar si cubre el total
        const totalAprobado = posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0);
        const montoOriginal = parseFloat(posMontoTotalOriginal) || 0;
        const numTransacciones = posTransaccionesAprobadas.length;
        const totalCubierto = totalAprobado >= montoOriginal;
        
        // Limpiar transacciones del mapa para este pedido (ya se procesó)
        if (pedidoData?.id) {
            limpiarTransaccionesPedido(pedidoData.id);
        }
        
        // Cerrar modal y limpiar estados
        setShowModalPosDebito(false);
        setPosCedulaTitular("");
        setPosTipoCuenta("CORRIENTE");
        setPosMontoDebito("");
        setPosRespuesta(null);
        setPosTransaccionesAprobadas([]);
        setPosMontoTotalOriginal("");
        setDebitoRef(refFinal);
        
        // Actualizar el monto de débito con el total aprobado
        setDebito(totalAprobado.toFixed(2));
        
        if (totalCubierto && !forzarSinProcesar) {
            // Total cubierto: procesar pago y facturar
            notificar({ data: { msj: `✓ POS Completado - ${numTransacciones} transacción(es) - Total: Bs ${totalAprobado.toFixed(2)}`, estado: true } });
            procesarPagoInterno(posPendingCallback, refFinal);
        } else {
            // Total NO cubierto: solo aplicar monto al campo débito, el usuario completará con otros métodos
            const restante = montoOriginal - totalAprobado;
            notificar({ data: { msj: `POS Parcial - Bs ${totalAprobado.toFixed(2)} aplicado. Faltan Bs ${restante.toFixed(2)} por otros métodos.`, estado: true } });
        }
        setPosPendingCallback(null);
    };
    
    // Función para eliminar una transacción aprobada
    const eliminarTransaccionPOS = (index) => {
        if (posLoading) return;
        
        const nuevasTransacciones = posTransaccionesAprobadas.filter((_, i) => i !== index);
        setPosTransaccionesAprobadas(nuevasTransacciones);
        
        // Actualizar en el mapa por pedido
        if (pedidoData?.id) {
            guardarTransaccionPorPedido(pedidoData.id, nuevasTransacciones);
        }
        
        // Recalcular monto restante
        const totalAprobado = nuevasTransacciones.reduce((sum, t) => sum + t.monto, 0);
        const montoOriginal = parseFloat(posMontoTotalOriginal) || 0;
        const restante = montoOriginal - totalAprobado;
        setPosMontoDebito(restante > 0 ? restante.toFixed(2) : "0");
        setPosRespuesta(null);
    };
    
    // Función para abrir modal de POS débito
    const abrirModalPosDebito = (callback = null) => {
        // Precarga el monto del débito en Bs
        const montoInicial = debito || "0";
        setPosMontoTotalOriginal(montoInicial); // Guardar monto total original
        
        // Cargar transacciones existentes del pedido actual
        const transaccionesExistentes = pedidoData?.id ? (posTransaccionesPorPedido[pedidoData.id] || []) : [];
        setPosTransaccionesAprobadas(transaccionesExistentes);
        
        // Calcular monto restante considerando transacciones ya aprobadas
        const totalAprobado = transaccionesExistentes.reduce((sum, t) => sum + t.monto, 0);
        const montoRestante = Math.max(0, parseFloat(montoInicial) - totalAprobado);
        setPosMontoDebito(montoRestante.toFixed(2));
        
        setPosPendingCallback(() => callback);
        setPosRespuesta(null); // Limpiar respuesta anterior
        setPosCedulaTitular(""); // Limpiar cédula anterior
        setShowModalPosDebito(true);
    };
    
    // useEffect para hacer foco en el input de cédula cuando se abre el modal POS
    useEffect(() => {
        if (showModalPosDebito) {
            // Usar requestAnimationFrame para asegurar que el DOM esté listo
            requestAnimationFrame(() => {
                setTimeout(() => {
                    const inputCedula = document.getElementById('pos-cedula-input');
                    if (inputCedula) {
                        inputCedula.focus();
                    }
                }, 50);
            });
        }
    }, [showModalPosDebito]);
    
    // useEffect para cargar transacciones POS cuando cambia el pedido
    // Solo se ejecuta cuando cambia el ID del pedido (no cuando cambia el mapa)
    useEffect(() => {
        if (pedidoData?.id) {
            // Cargar transacciones guardadas para este pedido desde el mapa
            // Usamos una función para acceder al estado actual del mapa
            setPosTransaccionesAprobadas(prev => {
                const transaccionesDelPedido = posTransaccionesPorPedido[pedidoData.id] || [];
                return transaccionesDelPedido;
            });
        } else {
            // No hay pedido seleccionado, limpiar transacciones actuales
            setPosTransaccionesAprobadas([]);
        }
    }, [pedidoData?.id, posTransaccionesPorPedido]);
    
    // Función para guardar transacciones en el mapa por pedido
    const guardarTransaccionPorPedido = (pedidoId, transacciones) => {
        setPosTransaccionesPorPedido(prev => ({
            ...prev,
            [pedidoId]: transacciones
        }));
    };
    
    // Función para limpiar transacciones de un pedido específico
    const limpiarTransaccionesPedido = (pedidoId) => {
        setPosTransaccionesPorPedido(prev => {
            const nuevo = { ...prev };
            delete nuevo[pedidoId];
            return nuevo;
        });
    };
    
    const [inventariadoEstadistica, setinventariadoEstadistica] = useState([]);
    const getPorcentajeInventario = () => {
        db.getPorcentajeInventario({}).then((res) => {
            let data = res.data;
            alert("INVENTARIADO: " + data["porcentaje"]);
        });
    };
    const cleanInventario = () => {
        if (
            confirm(
                "LIMPIAR PRODUCTOS QUE NUNCA SE HAN VENDIDO Y SU CANTIDAD ESTÁ EN CERO"
            )
        ) {
            db.cleanInventario({}).then((res) => {
                notificar(res.data);
            });
        }
    };
    const setconfigcredito = (e) => {
        e.preventDefault();

        if (pedidoData.id) {
            setLoading(true);
            db.setconfigcredito({
                fechainiciocredito,
                fechavencecredito,
                formatopagocredito,
                id_pedido: pedidoData.id,
            }).then((res) => {
                notificar(res);
                setLoading(false);
            });
        }
    };
    const facturar_pedido = (callback = null) => {
        if (pedidoData.id) {
            if (credito) {
                db.checkDeuda({ id_cliente: pedidoData.id_cliente }).then(
                    (res) => {
                        if (res.data) {
                            let p = res.data.pedido_total;
                            setdatadeudacredito(p);
                            setviewconfigcredito(true);
                        }
                    }
                );
            } else {
                setPagoPedido(callback);
            }
        }
    };
    const del_pedido = () => {
        if (confirm("¿Seguro de eliminar?")) {
            if (pedidoData.id) {
                let motivo = window.prompt(
                    "¿Cuál es el Motivo de eliminación?"
                );
                if (motivo) {
                    let params = { id: pedidoData.id, motivo };
                    db.delpedido(params).then((res) => {
                        notificar(res);
                        getPedidosFast();
                        if (res.data.estado === false) {
                            setLastDbRequest({
                                dbFunction: db.delpedido,
                                params,
                            });
                            openValidationTarea(res.data.id_tarea);
                        }
                    });
                }
            } else {
                alert("No hay pedido seleccionado");
            }
        }
    };
    const [cierrenumreportez, setcierrenumreportez] = useState("");
    const [cierreventaexcento, setcierreventaexcento] = useState("");
    const [cierreventagravadas, setcierreventagravadas] = useState("");
    const [cierreivaventa, setcierreivaventa] = useState("");
    const [cierretotalventa, setcierretotalventa] = useState("");
    const [cierreultimafactura, setcierreultimafactura] = useState("");
    const [cierreefecadiccajafbs, setcierreefecadiccajafbs] = useState("");
    const [cierreefecadiccajafcop, setcierreefecadiccajafcop] = useState("");
    const [cierreefecadiccajafdolar, setcierreefecadiccajafdolar] =
        useState("");
    const [cierreefecadiccajafeuro, setcierreefecadiccajafeuro] = useState("");

    const reversarCierre = () => {
        if (confirm("Por favor, confirme reverso")) {
            db.reversarCierre({}).then((res) => {
                location.reload();
            });
        }
    };
    const guardar_cierre = (e, callback = null) => {
        if (caja_biopago && !totalizarcierre) {
            if (!serialbiopago) {
                alert("Falta serial de Biopago");
                return;
            }
        }

        let valCajaFuerteEntradaCierreDolar = CajaFuerteEntradaCierreDolar
            ? CajaFuerteEntradaCierreDolar
            : 0;
        let valCajaChicaEntradaCierreDolar = CajaChicaEntradaCierreDolar
            ? CajaChicaEntradaCierreDolar
            : 0;
        let valCajaFuerteEntradaCierreCop = CajaFuerteEntradaCierreCop
            ? CajaFuerteEntradaCierreCop
            : 0;
        let valCajaChicaEntradaCierreCop = CajaChicaEntradaCierreCop
            ? CajaChicaEntradaCierreCop
            : 0;
        let valCajaFuerteEntradaCierreBs = CajaFuerteEntradaCierreBs
            ? CajaFuerteEntradaCierreBs
            : 0;
        let valCajaChicaEntradaCierreBs = CajaChicaEntradaCierreBs
            ? CajaChicaEntradaCierreBs
            : 0;

        let sumcajaDolar =
            parseFloat(valCajaFuerteEntradaCierreDolar) +
            parseFloat(valCajaChicaEntradaCierreDolar);
        if (Math.trunc(parseFloat(guardar_usd)) != Math.trunc(sumcajaDolar)) {
            alert("Error en suma de cajas (FUERTE)(CHICA): <sumcajaDolar> ");
            return;
        }

        let sumcajaCop =
            parseFloat(valCajaFuerteEntradaCierreCop) +
            parseFloat(valCajaChicaEntradaCierreCop);
        if (Math.trunc(parseFloat(guardar_cop)) != Math.trunc(sumcajaCop)) {
            alert("Error en suma de cajas (FUERTE)(CHICA): <sumcajaCop> ");
            return;
        }

        let sumcajaBs =
            parseFloat(valCajaFuerteEntradaCierreBs) +
            parseFloat(valCajaChicaEntradaCierreBs);
        if (Math.trunc(parseFloat(guardar_bs)) != Math.trunc(sumcajaBs)) {
            alert("Error en suma de cajas (FUERTE)(CHICA): <sumcajaBs> ");
            return;
        }
        if (window.confirm("¿Realmente desea Guardar/Editar?")) {
            setLoading(true);
            console.log(tipo_accionCierre);
            db.guardarCierre({
                fechaCierre,

                total_caja_neto,

                dejar_usd,
                dejar_cop,
                dejar_bs,

                total_dejar_caja_neto,
                total_punto,
                total_biopago,

                guardar_usd,
                guardar_cop,
                guardar_bs,

                caja_usd,
                caja_cop,
                caja_bs,
                caja_punto,
                caja_biopago,

                efectivo: cierre["total_caja"],
                transferencia: cierre[1],
                entregadomenospend: cierre["entregadomenospend"],
                caja_inicial: cierre["caja_inicial"],

                precio: cierre["precio"],
                precio_base: cierre["precio_base"],
                ganancia: cierre["ganancia"],
                porcentaje: cierre["porcentaje"],
                desc_total: cierre["desc_total"],
                numventas: cierre["numventas"],

                debito_digital: cierre["debito_digital"],
                efectivo_digital: cierre["efectivo_digital"],
                transferencia_digital: cierre["transferencia_digital"],
                biopago_digital: cierre["biopago_digital"],
                descuadre: cierre["descuadre"],

                notaCierre,
                totalizarcierre,

                numreportez: cierrenumreportez,
                ventaexcento: cierreventaexcento,
                ventagravadas: cierreventagravadas,
                ivaventa: cierreivaventa,
                totalventa: cierretotalventa,
                ultimafactura: cierreultimafactura,
                efecadiccajafbs: cierreefecadiccajafbs,
                efecadiccajafcop: cierreefecadiccajafcop,
                efecadiccajafdolar: cierreefecadiccajafdolar,
                efecadiccajafeuro: cierreefecadiccajafeuro,

                inventariobase: cierre["total_inventario_base"],
                inventarioventa: cierre["total_inventario"],
                creditoporcobrartotal: cierre["cred_total"],
                credito: cierre["4"],
                vueltostotales: cierre["vueltos_totales"],
                abonosdeldia: cierre["abonosdeldia"],

                CajaFuerteEntradaCierreDolar,
                CajaFuerteEntradaCierreCop,
                CajaFuerteEntradaCierreBs,
                CajaChicaEntradaCierreDolar,
                CajaChicaEntradaCierreCop,
                CajaChicaEntradaCierreBs,

                montolote1punto,
                montolote2punto,
                lote1punto,
                lote2punto,
                serialbiopago,
                puntolote1banco,
                puntolote2banco,
                tipo_accionCierre,
                dataPuntosAdicionales,
            }).then((res) => {
                setLoading(false);
                notificar(res, false);
                if (res.data.estado) {
                    db.getStatusCierre({ fechaCierre }).then((res) => {
                        settipo_accionCierre(res.data.tipo_accionCierre);
                    });
                }
            });
        }
    };

    const [puedeSendCierre, setpuedeSendCierre] = useState(true);
    const [puedeSendCierreTime, setpuedeSendCierreTime] = useState(null);

    const veryenviarcierrefun = (e, callback = null) => {
        if (puedeSendCierre) {
            // Bloquear inmediatamente para evitar clics duplicados
            setpuedeSendCierre(false);
            clearTimeout(puedeSendCierreTime);
            let time = window.setTimeout(() => {
                setpuedeSendCierre(true);
            }, 20000);
            setpuedeSendCierreTime(time);
            
            let type = e.currentTarget.attributes["data-type"].value;

            if (type == "ver") {
                verCierreReq(fechaCierre, type);
            } else if (type == "enviar" || type == "sync") {
                // Primero obtener estado de pendientes, luego abrir modal y sincronizar
                setLoading(true);
                
                // Obtener estado actual de sincronización
                axios.get(db.getHost() + "sync/status")
                    .then((statusRes) => {
                        // Preparar datos de pendientes para el modal
                        const pendientesData = {};
                        if (statusRes.data) {
                            Object.keys(statusRes.data).forEach(key => {
                                const tabla = statusRes.data[key];
                                pendientesData[key] = {
                                    pendientes: tabla.pendientes || 0,
                                    total: tabla.total || 0,
                                    sincronizados: tabla.sincronizados || 0
                                };
                            });
                        }
                        
                        // Abrir modal con datos de pendientes
                        if (window.SyncModal) {
                            window.SyncModal.open(pendientesData);
                            window.SyncModal.addLog('Obtenido estado de sincronización');
                            window.SyncModal.addLog('Iniciando envío de cierre...');
                        }
                        
                        // Ejecutar sincronización
                        return db.sendCierre({
                            type: "enviar",
                            fecha: fechaCierre,
                            totalizarcierre,
                        });
                    })
                    .then((res) => {
                        setLoading(false);
                        
                        // Procesar respuesta del nuevo sistema de sincronización
                        if (res.data && res.data.estado) {
                            const registros = res.data.registros_totales || 0;
                            const tiempo = res.data.tiempo_sync || '0s';
                            const tablas = res.data.tablas || {};
                            
                            // Actualizar modal con resultados
                            if (window.SyncModal) {
                                window.SyncModal.addLog(`Registros procesados: ${registros}`);
                                window.SyncModal.addLog(`Tiempo total: ${tiempo}`);
                                window.SyncModal.complete(true, `Sincronización completada: ${registros} registros en ${tiempo}`, res.data);
                            }
                            
                            notificar(`✓ Sincronización completada: ${registros} registros`, false);
                            
                            // Ejecutar post-procesamiento (correo, backup) en segundo plano
                            // No bloquea al usuario, se ejecuta silenciosamente
                            db.ejecutarPostSync({ fecha: fechaCierre }).catch(err => {
                                console.log('Post-sync en background:', err?.message || 'completado');
                            });
                        } else {
                            // Manejar otros formatos de respuesta
                            let mensaje = "Proceso completado";
                            
                            if (typeof res.data === "string") {
                                mensaje = res.data;
                            } else if (res.data && res.data.mensaje) {
                                mensaje = res.data.mensaje;
                            } else if (Array.isArray(res.data)) {
                                mensaje = res.data.join("\n");
                            }
                            
                            if (window.SyncModal) {
                                if (res.data && res.data.error) {
                                    window.SyncModal.complete(false, mensaje);
                                } else {
                                    window.SyncModal.complete(true, mensaje, res.data);
                                }
                            }
                            
                            notificar(mensaje, false);
                        }
                    })
                    .catch((error) => {
                        setLoading(false);
                        const errorMsg = "Error: " + (error.message || "Error desconocido");
                        
                        // Si el modal no se abrió aún (error en obtener status), abrirlo ahora
                        if (window.SyncModal && !window.SyncModal.state.isOpen) {
                            window.SyncModal.open();
                        }
                        
                        if (window.SyncModal) {
                            window.SyncModal.addLog(errorMsg, 'error');
                            window.SyncModal.complete(false, errorMsg);
                        }
                        
                        notificar(errorMsg, false);
                    });
            }
        } else {
            alert("Debe esperar 20 SEGUNDOS PARA VOLVER A ENVIAR!");
        }
    };
    const verCierreReq = (fechaCierre, type = "ver", usuario = "") => {
        // console.log(fecha)
        // if (window.confirm("Confirme envio")) {
        db.openVerCierre({ fechaCierre, type, totalizarcierre, usuario });
        // }
    };
    const setPagoCredito = (e) => {
        e.preventDefault();
        if (deudoresList[selectDeudor]) {
            let ref, banco;
            if (tipo_pago_deudor == "1") {
                ref = window.prompt("Referencia");
                banco = window.prompt("Banco");
            }

            if (tipo_pago_deudor == "1" && (!ref || !banco)) {
                alert(
                    "Error: Debe cargar referencia de transferencia electrónica."
                );
            } else {
                let id_cliente = deudoresList[selectDeudor].id;
                setLoading(true);
                db.setPagoCredito({
                    id_cliente,
                    tipo_pago_deudor,
                    monto_pago_deudor,
                }).then((res) => {
                    notificar(res);
                    setLoading(false);
                    getDeudor(id_cliente);

                    if (ref && banco) {
                        db.addRefPago({
                            tipo: 1,
                            descripcion: ref,
                            monto: monto_pago_deudor,
                            banco: banco,
                            id_pedido: res.data.id_pedido,
                        }).then((res) => {
                            notificar(res);
                        });
                    }
                });
            }
        }
    };
    const getDeudores = (e) => {
        if (e) {
            e.preventDefault();
        }
        setLoading(true);

        if (time != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            db.getDeudores({
                qDeudores,
                view,
                orderbycolumdeudores,
                orderbyorderdeudores,
                limitdeudores,
            }).then((res) => {
                if (res.data) {
                    if (res.data.length) {
                        setDeudoresList(res.data);
                    } else {
                        setDeudoresList([]);
                    }
                }
                setLoading(false);
            });
        }, 150);
        setTypingTimeout(time);
    };
    const clickSetOrderColumn = (e) => {
        let valor = e.currentTarget.attributes["data-valor"].value;

        if (valor == orderColumn) {
            if (orderBy == "desc") {
                setOrderBy("asc");
            } else {
                setOrderBy("desc");
            }
        } else {
            setOrderColumn(valor);
        }
    };

    const clickSetOrderColumnPedidos = (e) => {
        let valor = e.currentTarget.attributes["data-valor"].value;

        if (valor == orderbycolumpedidos) {
            if (orderbyorderpedidos == "desc") {
                setorderbyorderpedidos("asc");
            } else {
                setorderbyorderpedidos("desc");
            }
        } else {
            setorderbycolumpedidos(valor);
        }
    };

    const delMov = (e) => {
        if (confirm("¿Seguro de eliminar?")) {
            setLoading(true);
            const id = e.currentTarget.attributes["data-id"].value;

            db.delMov({ id }).then((res) => {
                setLoading(false);
                notificar(res);
                getMovimientos();
            });
        }
    };

    const getTotalizarCierre = () => {
        if (!totalizarcierre) {
            db.getTotalizarCierre({}).then((res) => {
                if (res.data) {
                    let d = res.data;

                    if (!d.caja_usd) {
                        notificar(res.data);
                        return;
                    }
                    setCaja_usd(d.caja_usd);
                    setCaja_cop(d.caja_cop);
                    setCaja_bs(d.caja_bs);
                    setCaja_punto(d.caja_punto);
                    setcaja_biopago(d.caja_biopago);

                    setDejar_usd(d.dejar_dolar);
                    setDejar_cop(d.dejar_peso);
                    setDejar_bs(d.dejar_bss);

                    setlotespuntototalizar(d.lotes);
                    setbiopagostotalizar(d.biopagos);
                }
            });
        }
        setTotalizarcierre(!totalizarcierre);
    };
    const addProductoFactInventario = (id_producto) => {
        let id_factura = null;

        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id_factura = facturas[factSelectIndex].id;
                db.addProductoFactInventario({
                    id_producto,
                    id_factura,
                }).then((res) => {
                    if (res.data.estado) {
                        notificar(res.data.msj);
                        setView("SelectFacturasInventario");
                        getFacturas(false);
                    }
                });
            }
        }
    };
    const buscarInventario = (e) => {
        let id_factura = null;
        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id_factura = facturas[factSelectIndex].id;
            }
        }
        let checkempty = productosInventario
            .filter((e) => e.type)
            .filter(
                (e) =>
                    e.codigo_barras == "" ||
                    e.descripcion == "" ||
                    e.unidad == ""
            );

        if (!checkempty.length) {
            setLoading(true);

            if (time != 0) {
                clearTimeout(typingTimeout);
            }

            let time = window.setTimeout(() => {
                db.getinventario({
                    num: Invnum,
                    itemCero: true,
                    qProductosMain: qBuscarInventario,
                    orderColumn: InvorderColumn,
                    orderBy: InvorderBy,
                    busquedaAvanazadaInv,
                    busqAvanzInputs,
                    view,
                    id_factura,
                }).then((res) => {
                    if (res.data) {
                        if (res.data.length) {
                            setProductosInventario(res.data);
                        } else {
                            setProductosInventario([]);
                        }
                        setIndexSelectInventario(null);
                        if (res.data.length === 1) {
                            setIndexSelectInventario(0);
                        } else if (res.data.length == 0) {
                            setinpInvbarras(qBuscarInventario);
                        }
                    }
                    setLoading(false);
                });
            }, 120);
            setTypingTimeout(time);
        } else {
            alert("Hay productos pendientes en carga de Inventario List!");
        }
    };
    const getProveedores = (e) => {
        if (time != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            setLoading(true);
            db.getProveedores({
                q: qBuscarProveedor,
            }).then((res) => {
                if (res.data.length) {
                    setProveedoresList(res.data);
                } else {
                    setProveedoresList([]);
                }
                setLoading(false);
                if (res.data.length === 1) {
                    setIndexSelectProveedores(0);
                }
            });
        }, 150);
        setTypingTimeout(time);

        if (!categorias.length) {
            getCategorias();
        }
        if (!depositosList.length) {
            db.getDepositos({
                q: qBuscarProveedor,
            }).then((res) => {
                setdepositosList(res.data);
            });
        }
    };
    const setInputsInventario = () => {
        if (productosInventario[indexSelectInventario]) {
            let obj = productosInventario[indexSelectInventario];
            setinpInvbarras(obj.codigo_barras ? obj.codigo_barras : "");
            setinpInvcantidad(obj.cantidad ? obj.cantidad : "");
            setinpInvalterno(obj.codigo_proveedor ? obj.codigo_proveedor : "");
            setinpInvunidad(obj.unidad ? obj.unidad : "");
            setinpInvdescripcion(obj.descripcion ? obj.descripcion : "");
            setinpInvbase(obj.precio_base ? obj.precio_base : "");
            setinpInvventa(obj.precio ? obj.precio : "");
            setinpInviva(obj.iva ? obj.iva : "");

            setinpInvcategoria(obj.id_categoria ? obj.id_categoria : "");
            setinpInvid_proveedor(obj.id_proveedor ? obj.id_proveedor : "");
            setinpInvid_marca(obj.id_marca ? obj.id_marca : "");
            setinpInvid_deposito(obj.id_deposito ? obj.id_deposito : "");

            setinpInvLotes(obj.lotes ? obj.lotes : []);
        }
    };
    const setNewProducto = () => {
        setIndexSelectInventario(null);
        setinpInvbarras("");
        setinpInvcantidad("");
        setinpInvalterno("");
        setinpInvunidad("UND");
        setinpInvdescripcion("");
        setinpInvbase("");
        setinpInvventa("");
        setinpInviva("0");

        setinpInvLotes([]);

        if (facturas[factSelectIndex]) {
            setinpInvid_proveedor(facturas[factSelectIndex].proveedor.id);
        }

        setinpInvid_marca("GENÉRICO");
        setinpInvid_deposito(1);
    };
    const setInputsProveedores = () => {
        if (proveedoresList[indexSelectProveedores]) {
            let obj = proveedoresList[indexSelectProveedores];

            setproveedordescripcion(obj.descripcion);
            setproveedorrif(obj.rif);
            setproveedordireccion(obj.direccion);
            setproveedortelefono(obj.telefono);
        }
    };

    const [inventarioNovedadesData, setinventarioNovedadesData] = useState([]);

    const getInventarioNovedades = () => {
        db.getInventarioNovedades({}).then((res) => {
            setinventarioNovedadesData(res.data);
        });
    };
    const resolveInventarioNovedades = (id) => {
        db.resolveInventarioNovedades({ id }).then((res) => {
            getInventarioNovedades();
            notificar(res.data);
        });
    };
    const sendInventarioNovedades = (id) => {
        db.sendInventarioNovedades({ id }).then((res) => {
            notificar(res.data);
        });
    };
    const delInventarioNovedades = (id) => {
        if (confirm("Confirme")) {
            db.delInventarioNovedades({ id }).then((res) => {
                getInventarioNovedades();
            });
        }
    };

    const guardarNuevoProducto = () => {
        setLoading(true);

        db.guardarNuevoProducto({
            inpInvbarras,
            inpInvcantidad,
            inpInvalterno,
            inpInvunidad,
            inpInvcategoria,
            inpInvdescripcion,
            inpInvbase,
            inpInvventa,
            inpInviva,
            inpInvid_proveedor,
            inpInvid_marca,
            inpInvid_deposito,
            inpInvporcentaje_ganancia,
            inpInvLotes,
        }).then((res) => {
            notificar(res);
            setLoading(false);
            if (res.data.estado) {
                setinpInvbarras("");
                setinpInvcantidad("");
                setinpInvalterno("");
                setinpInvunidad("UND");
                setinpInvcategoria("24");
                setinpInvdescripcion("");
                setinpInvbase("");
                setinpInvventa("");
                setinpInviva("0");
                setinpInvid_marca("");
            }
        });
    };
    const getPedidosFast = () => {
        db.getPedidosFast({
            vendedor: showMisPedido ? [user.id_usuario] : [],
            fecha1pedido,
        }).then((res) => {
            setpedidosFast(res.data);
        });
    };
    const [modalchangepedido, setmodalchangepedido] = useState(false);

    const [usuarioChangeUserPedido, setusuarioChangeUserPedido] = useState("");
    const [seletIdChangePedidoUser, setseletIdChangePedidoUser] =
        useState(null);

    const [modalchangepedidoy, setmodalchangepedidoy] = useState(0);
    const [modalchangepedidox, setmodalchangepedidox] = useState(0);

    
    const setseletIdChangePedidoUserHandle = (event, id) => {
        setseletIdChangePedidoUser(id);
        setmodalchangepedido(true);

        let p = event.currentTarget.getBoundingClientRect();
        let y = p.top + window.scrollY;
        let x = p.left;
        setmodalchangepedidoy(y);
        setmodalchangepedidox(x);
    };

    const setSameGanancia = () => {
        let insert = window.prompt("Porcentaje");
        if (insert) {
            let obj = cloneDeep(productosInventario);
            obj.map((e) => {
                if (e.type) {
                    let re = (
                        parseFloat(e.precio_base) +
                        parseFloat(e.precio_base) * (parseFloat(insert) / 100)
                    ).toFixed(2);
                    if (re) {
                        e.precio = re;
                    }
                }
                return e;
            });
            setProductosInventario(obj);
        }
    };
    const [sameCatValue, setsameCatValue] = useState("");
    const [sameProValue, setsameProValue] = useState("");

    const setSameCat = (val) => {
        if (confirm("¿Confirma Generalizar categoría?")) {
            let obj = cloneDeep(productosInventario);
            obj.map((e) => {
                if (e.type) {
                    e.id_categoria = val;
                }
                return e;
            });
            setProductosInventario(obj);
            setsameCatValue(val);
        }
    };
    const setSamePro = (val) => {
        if (confirm("¿Confirma Generalizar proveeedor?")) {
            let obj = cloneDeep(productosInventario);
            obj.map((e) => {
                if (e.type) {
                    e.id_proveedor = val;
                }
                return e;
            });
            setProductosInventario(obj);
            setsameProValue(val);
        }
    };

    const busqAvanzInputsFun = (e, key) => {
        let obj = cloneDeep(busqAvanzInputs);
        obj[key] = e.target.value;
        setbusqAvanzInputs(obj);
    };
    const buscarInvAvanz = () => {
        buscarInventario(null);
    };

    const setProveedor = (e) => {
        setLoading(true);
        e.preventDefault();

        let id = null;

        if (indexSelectProveedores != null) {
            if (proveedoresList[indexSelectProveedores]) {
                id = proveedoresList[indexSelectProveedores].id;
            }
        }
        db.setProveedor({
            proveedordescripcion,
            proveedorrif,
            proveedordireccion,
            proveedortelefono,
            id,
        }).then((res) => {
            notificar(res);
            getProveedores();
            setLoading(false);
        });
    };
    const delProveedor = (e) => {
        let id;
        if (indexSelectProveedores != null) {
            if (proveedoresList[indexSelectProveedores]) {
                id = proveedoresList[indexSelectProveedores].id;
            }
        }
        if (confirm("¿Desea Eliminar?")) {
            setLoading(true);
            db.delProveedor({ id }).then((res) => {
                setLoading(false);
                getProveedores();
                notificar(res);

                if (res.data.estado) {
                    setIndexSelectProveedores(null);
                }
            });
        }
    };
    const delProducto = (e) => {
        let id;
        if (indexSelectInventario != null) {
            if (productosInventario[indexSelectInventario]) {
                id = productosInventario[indexSelectInventario].id;
            }
        }
        if (confirm("¿Desea Eliminar?")) {
            setLoading(true);
            db.delProducto({ id }).then((res) => {
                setLoading(false);
                buscarInventario();
                notificar(res);
                if (res.data.estado) {
                    setIndexSelectInventario(null);
                }
            });
        }
    };
    const getFacturas = (clean = true) => {
        if (time != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            setLoading(true);
            db.getFacturas({
                factqBuscar,
                factqBuscarDate,
                factOrderBy,
                factOrderDescAsc,
            }).then((res) => {
                setLoading(false);
                setfacturas(res.data);

                if (res.data.length === 1) {
                    setfactSelectIndex(0);
                }

                if (clean) {
                    setfactSelectIndex(null);
                }
            });
        }, 100);
        setTypingTimeout(time);
    };

    const setFactura = (e) => {
        e.preventDefault();
        setLoading(true);

        let id = null;

        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id = facturas[factSelectIndex].id;
            }
        }
        let matchPro = allProveedoresCentral.filter(
            (pro) => pro.id == factInpid_proveedor
        );
        if (matchPro.length) {
            const formData = new FormData();
            formData.append("id", id);
            formData.append("factInpid_proveedor", factInpid_proveedor);
            formData.append("factInpnumfact", factInpnumfact);
            formData.append("factInpdescripcion", factInpdescripcion);
            formData.append("factInpmonto", factInpmonto);
            formData.append("factInpfechavencimiento", factInpfechavencimiento);
            formData.append("factInpestatus", factInpestatus);
            formData.append("factInpnumnota", factInpnumnota);
            formData.append("factInpsubtotal", factInpsubtotal);
            formData.append("factInpdescuento", factInpdescuento);
            formData.append("factInpmonto_gravable", factInpmonto_gravable);
            formData.append("factInpmonto_exento", factInpmonto_exento);
            formData.append("factInpiva", factInpiva);
            formData.append("factInpfechaemision", factInpfechaemision);
            formData.append("factInpfecharecepcion", factInpfecharecepcion);
            formData.append("factInpnota", factInpnota);

            formData.append("proveedorCentraid", matchPro[0].id);
            formData.append("proveedorCentrarif", matchPro[0].rif);
            formData.append(
                "proveedorCentradescripcion",
                matchPro[0].descripcion
            );
            formData.append("proveedorCentradireccion", matchPro[0].direccion);
            formData.append("proveedorCentratelefono", matchPro[0].telefono);

            formData.append("factInpImagen", factInpImagen);

            db.setFactura(formData).then((res) => {
                notificar(res);
                getFacturas();
                setLoading(false);
                if (res.data.estado) {
                    setfactsubView("buscar");
                    setfactSelectIndex(null);
                }
            });
        }
    };
    const sendFacturaCentral = (id, i) => {
        if (confirm("Confirme envio y selección de factura")) {
            db.sendFacturaCentral({ id }).then((res) => {
                if (res.data) {
                    if (res.data.estatus) {
                        if (facturas[i]) {
                            setfactSelectIndex(i);
                            getFacturas();
                            notificar(res.data.msj);
                        }
                    }
                }
            });
        }
    };

    const [allProveedoresCentral, setallProveedoresCentral] = useState([]);
    const getAllProveedores = () => {
        db.getAllProveedores({}).then((res) => {
            if (res.data) {
                if (res.data.length) {
                    setallProveedoresCentral(res.data);
                } else {
                    notificar(res.data);
                }
            }
        });
    };
    const delFactura = (e) => {
        let id = null;

        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id = facturas[factSelectIndex].id;
            }
        }
        if (confirm("¿Desea Eliminar?")) {
            setLoading(true);
            db.delFactura({ id }).then((res) => {
                setLoading(false);
                getFacturas();
                notificar(res);
                if (res.data.estado) {
                    setfactsubView("buscar");
                    setfactSelectIndex(null);
                }
            });
        }
    };
    const saveFactura = () => {
        if (facturas[factSelectIndex]) {
            let id = facturas[factSelectIndex].id;
            let monto = facturas[factSelectIndex].summonto_base_clean;
            db.saveMontoFactura({ id, monto }).then((e) => {
                getFacturas(false);
            });
        }
    };
    const delItemFact = (id_producto) => {
        let id_factura = null;
        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id_factura = facturas[factSelectIndex].id;
                if (confirm("¿Desea Eliminar?")) {
                    setLoading(true);
                    db.delItemFact({ id_producto, id_factura }).then((res) => {
                        setLoading(false);
                        notificar(res);
                        if (res.data.estado) {
                            getFacturas(false);
                        }
                    });
                }
            }
        }
    };
    const setClienteCrud = (e) => {
        e.preventDefault();
        setLoading(true);
        let id = null;

        if (indexSelectCliente != null) {
            if (clientesCrud[indexSelectCliente]) {
                id = clientesCrud[indexSelectCliente].id;
            }
        }

        db.setClienteCrud({
            id,
            clienteInpidentificacion,
            clienteInpnombre,
            clienteInpcorreo,
            clienteInpdireccion,
            clienteInptelefono,
            clienteInpestado,
            clienteInpciudad,
        }).then((res) => {
            notificar(res);
            getClienteCrud();
            setLoading(false);
        });
    };
    const getClienteCrud = () => {
        setLoading(true);
        db.getClienteCrud({ q: qBuscarCliente, num: numclientesCrud }).then(
            (res) => {
                setLoading(false);
                setclientesCrud(res.data);
                setindexSelectCliente(null);
            }
        );
    };
    const delCliente = () => {
        let id = null;

        if (indexSelectCliente != null) {
            if (clientesCrud[indexSelectCliente]) {
                id = clientesCrud[indexSelectCliente].id;
            }
        }
        if (confirm("¿Desea Eliminar?")) {
            setLoading(true);
            db.delCliente({ id }).then((res) => {
                setLoading(false);
                getClienteCrud();
                notificar(res);
                if (res.data.estado) {
                    setindexSelectCliente(null);
                }
            });
        }
    };
    const sumPedidos = (e) => {
        let tipo = e.currentTarget.attributes["data-tipo"].value;
        let id = e.currentTarget.attributes["data-id"].value;
        if (tipo == "add") {
            if (sumPedidosArr.indexOf(id) < 0) {
                setsumPedidosArr(sumPedidosArr.concat(id));
            }
        } else {
            setsumPedidosArr(sumPedidosArr.filter((e) => e != id));
        }
    };

    const getFallas = () => {
        setLoading(true);
        db.getFallas({
            qFallas,
            orderCatFallas,
            orderSubCatFallas,
            ascdescFallas,
        }).then((res) => {
            setfallas(res.data);
            setLoading(false);
        });
    };
    const setFalla = (e) => {
        let id_producto = e.currentTarget.attributes["data-id"].value;
        db.setFalla({ id: null, id_producto }).then((res) => {
            notificar(res);
            setSelectItem(null);
        });
    };

    const delitempresupuestocarrito = (index) => {
        setpresupuestocarrito(presupuestocarrito.filter((e, i) => i != index));
    };

    const editCantidadPresupuestoCarrito = (index, nuevaCantidad) => {
        if (!nuevaCantidad || nuevaCantidad <= 0) {
            notificar("La cantidad debe ser mayor a 0");
            return;
        }

        const nuevoPresupuesto = presupuestocarrito.map((item, i) => {
            if (i === index) {
                const cantidad = parseFloat(nuevaCantidad);
                const nuevoSubtotal = cantidad * parseFloat(item.precio);
                return {
                    ...item,
                    cantidad: cantidad,
                    subtotal: nuevoSubtotal.toFixed(2),
                };
            }
            return item;
        });

        setpresupuestocarrito(nuevoPresupuesto);
        notificar("Cantidad actualizada correctamente");
    };

    const addCarritoPresupuesto = (e) => {
        let index;
        if (e.currentTarget) {
            let attr = e.currentTarget.attributes;
            index = attr["data-index"].value;
        } else {
            index = e;
        }

        // Seleccionar el producto
        setSelectItem(parseInt(index));

        // Obtener el producto seleccionado
        const producto = productos[index];
        if (!producto) return;

        // Verificar si el producto ya está en el presupuesto
        const existeEnPresupuesto = presupuestocarrito.find(
            (item) => item.id === producto.id
        );

        if (existeEnPresupuesto) {
            // Si ya existe, incrementar la cantidad
            const nuevoPsupuesto = presupuestocarrito.map((item) => {
                if (item.id === producto.id) {
                    const nuevaCantidad = parseInt(item.cantidad) + 1;
                    const nuevoSubtotal =
                        nuevaCantidad * parseFloat(item.precio);
                    return {
                        ...item,
                        cantidad: nuevaCantidad,
                        subtotal: nuevoSubtotal.toFixed(2),
                    };
                }
                return item;
            });
            setpresupuestocarrito(nuevoPsupuesto);
        } else {
            // Si no existe, agregarlo como nuevo item
            const nuevoItem = {
                id: producto.id,
                codigo_barras: producto.codigo_barras,
                descripcion: producto.descripcion,
                cantidad: 1,
                precio: parseFloat(producto.precio).toFixed(2),
                subtotal: parseFloat(producto.precio).toFixed(2),
            };

            setpresupuestocarrito([...presupuestocarrito, nuevoItem]);
        }

        // Notificar que se agregó el producto
        notificar(`${producto.descripcion} agregado al presupuesto`);
    };
    const setpresupuestocarritotopedido = () => {
        if (presupuestocarrito.length === 1) {
            presupuestocarrito.map((e) => {
                addCarritoRequest("agregar", e.id, "nuevo", e.cantidad);
            });
        } else if (presupuestocarrito.length > 1) {
            let clone = cloneDeep(presupuestocarrito);
            let first = clone[0];

            db.setCarrito({
                id: first.id,
                cantidad: first.cantidad,
                type: "agregar",
                numero_factura: "nuevo",
                loteIdCarrito,
            }).then((res) => {
                delete clone[0];
                getPedidosList((lastpedido) => {
                    clone.map((e) => {
                        db.setCarrito({
                            id: e.id,
                            cantidad: e.cantidad,
                            type: "agregar",
                            numero_factura: lastpedido,
                            loteIdCarrito,
                        }).then((res) => {
                            notificar(res);
                        });
                    });
                });
            });
        }
    };
    const setPresupuesto = (e) => {
        let id_producto = e.currentTarget.attributes["data-id"].value;
        let findpr = productos.filter((e) => e.id == id_producto);
        if (findpr.length) {
            let pro = findpr[0];

            let copypresupuestocarrito = cloneDeep(presupuestocarrito);
            if (
                copypresupuestocarrito.filter((e) => e.id == id_producto).length
            ) {
                copypresupuestocarrito = copypresupuestocarrito.filter(
                    (e) => e.id != id_producto
                );
            }
            let ct = cantidad ? cantidad : 1;
            let subtotalpresu = parseFloat(pro.precio) * ct;
            copypresupuestocarrito = copypresupuestocarrito.concat({
                id: pro.id,
                precio: pro.precio,
                cantidad: ct,
                descripcion: pro.descripcion,
                subtotal: subtotalpresu,
            });

            setpresupuestocarrito(copypresupuestocarrito);
            setSelectItem(null);
        }
    };
    const delFalla = (e) => {
        if (confirm("¿Desea Eliminar?")) {
            let id = e.currentTarget.attributes["data-id"].value;
            db.delFalla({ id }).then((res) => {
                notificar(res);
                getFallas();
            });
        }
    };
    const viewReportPedido = () => {
        db.openNotaentregapedido({ id: pedidoData.id });
    };

    const setInventarioFromSucursal = () => {
        setLoading(true);
        db.setInventarioFromSucursal({ path: pathcentral }).then((res) => {
            console.log(res.data);
            notificar(res);
            setLoading(false);
        });
    };
    const getip = () => {
        db.getip({}).then((res) => alert(res.data));
    };
    const getmastermachine = () => {
       
    };
  
    const [qpedidoscentralq, setqpedidoscentralq] = useState("");
    const [qpedidocentrallimit, setqpedidocentrallimit] = useState("5");
    const [qpedidocentralestado, setqpedidocentralestado] = useState("");
    const [qpedidocentralemisor, setqpedidocentralemisor] = useState("");
    const getPedidosCentral = () => {
        setLoading(true);
        db.reqpedidos({
            path: pathcentral,
            qpedidoscentralq,
            qpedidocentrallimit,
            qpedidocentralestado,
            qpedidocentralemisor,
        }).then((res) => {
            setLoading(false);
            setpedidoCentral([]);

            if (res.data) {
                if (res.data.length) {
                    if (res.data[0]) {
                        if (res.data[0].id) {
                            setpedidoCentral(res.data);
                        }
                    }
                } else {
                    setpedidoCentral([]);
                }

                if (res.data.msj) {
                    notificar(res);
                }
            } else {
                setpedidoCentral([]);
            }
        });
    };
    const procesarImportPedidoCentral = () => {
        // console.log(valbodypedidocentral)
        // Id pedido 4
        // Count items pedido 4
        // sucursal code *

        // console.log(valheaderpedidocentral)
        //id_pedido 4 (0)
        //id_producto 4 (0)
        //base 6 (2)
        //venta 6 (2)
        //cantidad 5 (1)

        try {
            // Header...
            let id_pedido_header = valheaderpedidocentral
                .substring(0, 4)
                .replace(/\b0*/g, "");
            let count = valheaderpedidocentral
                .substring(4, 8)
                .replace(/\b0*/g, "");
            let sucursal_code = valheaderpedidocentral.substring(8);

            let import_pedido = {};

            if (id_pedido_header && count && sucursal_code) {
                db.getSucursal({}).then((res) => {
                    try {
                        if (res.data) {
                            if (res.data.codigo) {
                                if (res.data.codigo != sucursal_code) {
                                    throw "Error: Pedido no pertenece a esta sucursal!";
                                } else {
                                    import_pedido.created_at = today;
                                    import_pedido.sucursal = sucursal_code;
                                    import_pedido.id = id_pedido_header;
                                    import_pedido.base = 0;
                                    import_pedido.venta = 0;
                                    import_pedido.items = [];

                                    let body = valbodypedidocentral
                                        .toString()
                                        .replace(/[^0-9]/g, "");
                                    if (!body) {
                                        throw "Error: Cuerpo incorrecto!";
                                    } else {
                                        let ids_productos = body
                                            .match(/.{1,25}/g)
                                            .map((e, i) => {
                                                if (e.length != 25) {
                                                    throw "Error: Líneas no tienen la longitud!";
                                                }
                                                let id_pedido = e
                                                    .substring(0, 4)
                                                    .replace(/\b0*/g, "");
                                                let id_producto = e
                                                    .substring(4, 8)
                                                    .replace(/\b0*/g, "");

                                                let base =
                                                    e
                                                        .substring(8, 12)
                                                        .replace(/\b0*/g, "") +
                                                    "." +
                                                    e.substring(12, 14);
                                                let venta =
                                                    e
                                                        .substring(14, 18)
                                                        .replace(/\b0*/g, "") +
                                                    "." +
                                                    e.substring(18, 20);

                                                let cantidad =
                                                    e
                                                        .substring(20, 24)
                                                        .replace(/\b0*/g, "") +
                                                    "." +
                                                    e.substring(24, 25);

                                                // if (id_pedido_header!=id_pedido) {
                                                //
                                                //   throw("Error: Producto #"+(i+1)+" no pertenece a este pedido!")
                                                // }

                                                return {
                                                    id_producto,
                                                    id_pedido,
                                                    base,
                                                    venta,
                                                    cantidad,
                                                };
                                            });
                                        db.getProductosSerial({
                                            count,
                                            ids_productos: ids_productos.map(
                                                (e) => e.id_producto
                                            ),
                                        }).then((res) => {
                                            try {
                                                let obj = res.data;

                                                if (obj.estado) {
                                                    if (obj.msj) {
                                                        let pro = obj.msj.map(
                                                            (e, i) => {
                                                                let filter =
                                                                    ids_productos.filter(
                                                                        (ee) =>
                                                                            ee.id_producto ==
                                                                            e.id
                                                                    )[0];

                                                                let cantidad =
                                                                    filter.cantidad;
                                                                let base =
                                                                    filter.base;
                                                                let venta =
                                                                    filter.venta;
                                                                let monto =
                                                                    cantidad *
                                                                    venta;

                                                                import_pedido.items.push(
                                                                    {
                                                                        cantidad:
                                                                            cantidad,
                                                                        producto:
                                                                            {
                                                                                precio_base:
                                                                                    base,
                                                                                precio: venta,
                                                                                codigo_barras:
                                                                                    e.codigo_barras,
                                                                                codigo_proveedor:
                                                                                    e.codigo_proveedor,
                                                                                descripcion:
                                                                                    e.descripcion,
                                                                                id: e.id,
                                                                            },
                                                                        id: i,
                                                                        monto,
                                                                    }
                                                                );

                                                                import_pedido.base +=
                                                                    parseFloat(
                                                                        cantidad *
                                                                            base
                                                                    );
                                                                import_pedido.venta +=
                                                                    parseFloat(
                                                                        monto
                                                                    );
                                                            }
                                                        );
                                                        // console.log("import_pedido",import_pedido)
                                                        setpedidoCentral(
                                                            pedidosCentral.concat(
                                                                import_pedido
                                                            )
                                                        );
                                                        setshowaddpedidocentral(
                                                            false
                                                        );
                                                    }
                                                } else {
                                                    alert(obj.msj);
                                                }
                                            } catch (err) {
                                                alert(err);
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    } catch (err) {
                        alert(err);
                    }
                });
            } else {
                throw "Error: Cabezera incorrecta!";
            }
        } catch (err) {
            alert(err);
        }
    };
    const selectPedidosCentral = (e) => {
        try {
            let index = e.currentTarget.attributes["data-index"].value;
            let tipo = e.currentTarget.attributes["data-tipo"].value;

            let pedidosCentral_copy = cloneDeep(pedidosCentral);

            if (tipo == "select") {
                if (
                    pedidosCentral_copy[indexPedidoCentral].items[index]
                        .aprobado === true
                ) {
                    delete pedidosCentral_copy[indexPedidoCentral].items[index]
                        .aprobado;
                } else if (
                    pedidosCentral_copy[indexPedidoCentral].items[index]
                        .aprobado === false
                ) {
                    pedidosCentral_copy[indexPedidoCentral].items[
                        index
                    ].aprobado = true;
                } else if (
                    typeof pedidosCentral_copy[indexPedidoCentral].items[index]
                        .aprobado === "undefined"
                ) {
                    pedidosCentral_copy[indexPedidoCentral].items[
                        index
                    ].aprobado = true;
                }
            } else if (tipo == "selectall") {
                // Verificar si todos están aprobados
                const todosAprobados = pedidosCentral_copy[indexPedidoCentral].items.every(
                    item => item.aprobado === true
                );
                
                // Si todos están aprobados, deseleccionar todos
                // Si no, aprobar todos
                pedidosCentral_copy[indexPedidoCentral].items.forEach((item, idx) => {
                    if (todosAprobados) {
                        delete pedidosCentral_copy[indexPedidoCentral].items[idx].aprobado;
                    } else {
                        pedidosCentral_copy[indexPedidoCentral].items[idx].aprobado = true;
                    }
                });
            } else if (tipo == "changect_real") {
                pedidosCentral_copy[indexPedidoCentral].items[index].ct_real =
                    number(e.currentTarget.value, 6);
            } else if (tipo == "changebarras_real") {
                pedidosCentral_copy[indexPedidoCentral].items[
                    index
                ].barras_real = e.currentTarget.value;
            } else if (tipo == "changealterno_real") {
                pedidosCentral_copy[indexPedidoCentral].items[
                    index
                ].alterno_real = e.currentTarget.value;
            } else if (tipo == "changedescripcion_real") {
                pedidosCentral_copy[indexPedidoCentral].items[
                    index
                ].descripcion_real = e.currentTarget.value;
            } else if (tipo == "changevinculo_real") {
                pedidosCentral_copy[indexPedidoCentral].items[
                    index
                ].vinculo_real = e.currentTarget.value;
            } else if (tipo == "changewarehouse") {
                pedidosCentral_copy[indexPedidoCentral].items[
                    index
                ].warehouse_codigo = e.currentTarget.value;
            }

            setpedidoCentral(pedidosCentral_copy);

            // console.log(pedidosCentral_copy)
        } catch (err) {
            console.log(err);
        }
    };
    const removeVinculoCentral = (id) => {
        db.removeVinculoCentral({
            id,
        }).then((res) => {
            if (res.data) {
                if (res.data.estado === true) {
                    let id_pedido = res.data.id_pedido;
                    let id_item = res.data.id_item;

                    let clone = cloneDeep(pedidosCentral);
                    clone = clone.map((e) => {
                        if (e.id == id_pedido) {
                            e.items = e.items.map((eitem) => {
                                if (eitem.id == id_item) {
                                    eitem.idinsucursal_vinculo = null;
                                    eitem.idinsucursal_producto = null;
                                    eitem.match = null;
                                }
                                return eitem;
                            });
                        }
                        return e;
                    });

                    setpedidoCentral(clone);
                } else {
                    console.log(
                        "ESTADO NOT TRUE removeVinculoCentral",
                        res.data
                    );
                }
            }
        });
    };
    const checkPedidosCentral = () => {
        if (indexPedidoCentral !== null && pedidosCentral) {
            if (pedidosCentral[indexPedidoCentral]) {
                setLoading(true);
                db.checkPedidosCentral({
                    pathcentral,
                    pedido: pedidosCentral[indexPedidoCentral],
                }).then((res) => {
                    setLoading(false);

                    notificar(res, false);
                    if (res.data.estado) {
                        getPedidosCentral();
                    }
                    if (res.data.proceso === "enrevision") {
                        getPedidosCentral();
                    }

                    if (res.data) {
                        if (res.data.estado === false) {
                            if (res.data.id_item) {
                                removeVinculoCentral(res.data.id_item);
                            }
                        }
                    }
                });
            }
        }
    };
    const verDetallesFactura = (e = null) => {
        let id = facturas[factSelectIndex];
        if (e) {
            id = e;
        }
        if (id) {
            db.openVerFactura({ id: facturas[factSelectIndex].id });
        }
    };
    const verDetallesImagenFactura = () => {
        db.openverDetallesImagenFactura({
            id: facturas[factSelectIndex].id,
        }).then((res) => {
            window.open(res.data, "targed=blank");
            console.log(res.data);
        });
    };
    const getVentas = () => {
        setLoading(true);
        // Usar la función rápida para reportes
        db.getVentasRapido({ fechaventas })
            .then((res) => {
                setventasData(res.data);
                setLoading(false);
            })
            .catch((error) => {
                console.error("Error obteniendo ventas:", error);
                setLoading(false);
            });
    };
    const getVentasClick = () => {
        getVentas();
    };
    const setBilletes = () => {
        let total = 0;
        total =
            parseInt(!billete1 ? 0 : billete1) * 1 +
            parseInt(!billete5 ? 0 : billete5) * 5 +
            parseInt(!billete10 ? 0 : billete10) * 10 +
            parseInt(!billete20 ? 0 : billete20) * 20 +
            parseInt(!billete50 ? 0 : billete50) * 50 +
            parseInt(!billete100 ? 0 : billete100) * 100;
        setCaja_usd(total);
    };
    const addNewUsuario = (e) => {
        e.preventDefault();

        let id = null;
        if (indexSelectUsuarios) {
            id = usuariosData[indexSelectUsuarios].id;
        }

        if (usuarioRole && usuarioNombre && usuarioUsuario) {
            setLoading(true);
            db.setUsuario({
                id,
                role: usuarioRole,
                nombres: usuarioNombre,
                usuario: usuarioUsuario,
                clave: usuarioClave,
                ip_pinpad: usuarioIpPinpad,
            }).then((res) => {
                notificar(res);
                setLoading(false);
                getUsuarios();
            });
        } else {
            console.log(
                "Err: addNewUsuario" +
                    usuarioRole +
                    " " +
                    usuarioNombre +
                    " " +
                    usuarioUsuario
            );
        }
    };
    const setInputsUsuarios = () => {
        if (indexSelectUsuarios) {
            let obj = usuariosData[indexSelectUsuarios];
            if (obj) {
                setusuarioNombre(obj.nombre);
                setusuarioUsuario(obj.usuario);
                setusuarioRole(obj.tipo_usuario);
                setusuarioClave(obj.clave);
                setusuarioIpPinpad(obj.ip_pinpad || "");
            }
        }
    };
    const getUsuarios = () => {
        setLoading(true);
        db.getUsuarios({ q: qBuscarUsuario }).then((res) => {
            setLoading(false);
            setusuariosData(res.data);
        });
    };
    const delUsuario = () => {
        setLoading(true);
        let id = null;
        if (indexSelectUsuarios) {
            id = usuariosData[indexSelectUsuarios].id;
        }
        db.delUsuario({ id }).then((res) => {
            setLoading(false);
            getUsuarios();
            notificar(res);
        });
    };
    const selectProductoFast = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        let val = e.currentTarget.attributes["data-val"].value;

        setQBuscarInventario(val);
        setfactSelectIndex("ninguna");
        setView("inventario");
        setsubViewInventario("inventario");
    };
    const addNewLote = (e) => {
        let addObj = {
            lote: "",
            creacion: "",
            vence: "",
            cantidad: "",
            type: "new",
            id: null,
        };
        setinpInvLotes(inpInvLotes.concat(addObj));
    };
    const changeModLote = (val, i, id, type, name = null) => {
        let lote = cloneDeep(inpInvLotes);

        switch (type) {
            case "update":
                if (lote[i].type != "new") {
                    lote[i].type = "update";
                }
                break;
            case "delModeUpdateDelete":
                delete lote[i].type;
                break;
            case "delNew":
                lote = lote.filter((e, ii) => ii !== i);
                break;
            case "changeInput":
                lote[i][name] = val;
                break;

            case "delMode":
                lote[i].type = "delete";
                let id_replace = 0;
                lote[i].id_replace = id_replace;
                break;
        }
        setinpInvLotes(lote);
    };
    const reporteInventario = () => {
        db.openReporteInventario();
    };
    const [personalNomina, setpersonalNomina] = useState([]);
    const [alquileresData, setalquileresData] = useState([]);

    const getNomina = () => {
        db.getNomina({}).then((res) => {
            if (res.data.length) {
                if (res.data[0].nominacargo) {
                    setpersonalNomina(res.data);
                }
            }
        });
    };
    const getAlquileres = () => {
        db.getAlquileres({}).then((res) => {
            if (res.data.length) {
                if (res.data[0].descripcion) {
                    setalquileresData(res.data);
                }
            }
        });
    };

    const guardarNuevoProductoLote = (e) => {
        if (!user.iscentral) {
            alert("No tiene permisos para gestionar Inventario");
            return;
        }
        // e.preventDefault()
        let id_factura = null;

        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id_factura = facturas[factSelectIndex].id;
            }
        }
        let lotesFil = productosInventario.filter((e) => e.type);

        let checkempty = lotesFil.filter(
            (e) =>
                e.codigo_barras == "" || e.descripcion == "" || e.unidad == ""
        );

        if (lotesFil.length && !checkempty.length) {
            setLoading(true);
            let motivo = null;
            db.guardarNuevoProductoLote({
                lotes: lotesFil,
                id_factura,
                motivo,
            }).then((res) => {
                let data = res.data;
                if (data.msj) {
                    notificar(data.msj);
                } else {
                    notificar(data);
                }
                if (data.estado) {
                    getFacturas(null);
                    buscarInventario();
                }
                setLoading(false);
            });
        } else {
            alert(
                "¡Error con los campos! Algunos pueden estar vacíos " +
                    JSON.stringify(checkempty)
            );
        }
    };
    const guardarNuevoProductoLoteFact = (e) => {
        if (!user.iscentral) {
            alert("No tiene permisos para gestionar Inventario");
            return;
        }
        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                let id_factura = facturas[factSelectIndex].id;
                let items = facturas[factSelectIndex].items;
                let itemsFilter = items.filter((e) => e.producto.type);
                let sumTotal = 0;
                items.map((item) => {
                    sumTotal +=
                        parseFloat(item.producto.precio3) *
                        parseFloat(item.cantidad);
                });

                if (sumTotal < parseFloat(facturas[factSelectIndex].monto)) {
                    if (itemsFilter.length) {
                        setLoading(true);
                        db.guardarNuevoProductoLoteFact({
                            items: itemsFilter,
                            id_factura,
                        }).then((res) => {
                            let data = res.data;
                            notificar(data.msj);
                            if (data.estado) {
                                getFacturas(null);
                            }
                            setLoading(false);
                        });
                    }
                } else {
                    alert("BASE FACT o CANTIDADES incorrectas");
                }
                console.log(sumTotal, "sumTotal");
                console.log(
                    facturas[factSelectIndex].monto,
                    "facturas[factSelectIndex].monto"
                );
            }
        }
    };

    const delPagoProveedor = (e) => {
        let id = e.target.attributes["data-id"].value;
        if (confirm("¿Seguro de eliminar?")) {
            db.delPagoProveedor({ id }).then((res) => {
                getPagoProveedor();
                notificar(res);
            });
        }
    };
    const getPagoProveedor = () => {
        if (proveedoresList[indexSelectProveedores]) {
            setLoading(true);
            db.getPagoProveedor({
                id_proveedor: proveedoresList[indexSelectProveedores].id,
            }).then((res) => {
                setLoading(false);
                setpagosproveedor(res.data);
            });
        }
    };
    const setPagoProveedor = (e) => {
        e.preventDefault();
        if (tipopagoproveedor && montopagoproveedor) {
            if (proveedoresList[indexSelectProveedores]) {
                db.setPagoProveedor({
                    tipo: tipopagoproveedor,
                    monto: montopagoproveedor,
                    id_proveedor: proveedoresList[indexSelectProveedores].id,
                }).then((res) => {
                    getPagoProveedor();
                    notificar(res);
                });
            }
        }
    };
    const setCtxBulto = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        let bulto = window.prompt("Cantidad por bulto");
        if (bulto) {
            db.setCtxBulto({ id, bulto }).then((res) => {
                buscarInventario();
                notificar(res);
            });
        }
    };
    const setStockMin = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        let min = window.prompt("Cantidad Mínima");
        if (min) {
            db.setStockMin({ id, min }).then((res) => {
                buscarInventario();
                notificar(res);
            });
        }
    };

    const setPrecioAlterno = (e) => {
        let id = e.currentTarget.attributes["data-id"].value;
        let type = e.currentTarget.attributes["data-type"].value;
        let precio = window.prompt("PRECIO " + type);
        if (precio) {
            db.setPrecioAlterno({ id, type, precio }).then((res) => {
                buscarInventario();
                notificar(res);
            });
        }
    };
    const changeInventario = (val, i, id, type, name = null) => {
        let obj = cloneDeep(productosInventario);

        switch (type) {
            case "update":
                let isupd = false;
                if (obj[i].type == "update") {
                    delete obj[i].type;
                    isupd = true;
                }

                if (obj.filter((e) => e.type).length != 0) {
                    return;
                }

                if (obj[i].type != "new") {
                    if (!isupd) {
                        obj[i].type = "update";
                    }
                }
                break;
            case "delModeUpdateDelete":
                delete obj[i].type;
                break;
            case "delNew":
                obj = obj.filter((e, ii) => ii !== i);
                break;
            case "changeInput":
                obj[i][name] = val;
                break;
            case "add":
                let pro = "";

                if (facturas[factSelectIndex]) {
                    pro = facturas[factSelectIndex].proveedor.id;
                } else {
                    pro = sameProValue;
                }

                let newObj = [
                    {
                        id: null,
                        codigo_proveedor: "",
                        codigo_barras: "",
                        descripcion: "",
                        id_categoria: sameCatValue,
                        id_marca: "",
                        unidad: "UND",
                        id_proveedor: pro,
                        cantidad: "",
                        precio_base: "",
                        precio3: "",
                        precio: "",
                        iva: "0",
                        type: "new",
                    },
                ];

                obj = newObj.concat(obj);
                break;

            case "delMode":
                obj[i].type = "delete";
                let id_replace = 0;
                obj[i].id_replace = id_replace;
                break;
        }
        setProductosInventario(obj);
    };

    const changeInventarioNewFact = (val, i, id, type, name = null) => {
        let id_factura = null;

        if (factSelectIndex != null) {
            if (facturas[factSelectIndex]) {
                id_factura = facturas[factSelectIndex].id;
                let obj = cloneDeep(facturas);

                switch (type) {
                    case "update":
                        if (
                            obj[factSelectIndex].items[i].producto.type != "new"
                        ) {
                            obj[factSelectIndex].items[i].producto.type =
                                "update";
                        }
                        break;
                    case "delModeUpdateDelete":
                        delete obj[factSelectIndex].items[i].producto.type;
                        break;
                    case "changeInput":
                        obj[factSelectIndex].items[i].producto[name] = val;
                        break;
                }
                setfacturas(obj);
            }
        }
    };

    const changeInventarioFromSucursalCentral = (
        val,
        i,
        id,
        type,
        name = null
    ) => {
        let obj = cloneDeep(inventarioSucursalFromCentral);

        switch (type) {
            case "update":
                if (obj[i].type != "new") {
                    obj[i].type = "update";
                    obj[i]["estatus"] = 1;
                }
                break;
            case "delModeUpdateDelete":
                obj[i]["id_vinculacion"] = null;
                obj[i]["estatus"] = 0;
                delete obj[i].type;
                break;
            case "delNew":
                obj = obj.filter((e, ii) => ii !== i);
                break;
            case "changeInput":
                if (id === null) {
                    let copyproducto = productos.filter((e) => e.id == val)[0];
                    obj[i] = {
                        bulto: copyproducto.bulto,
                        cantidad: "0.00",
                        codigo_barras: copyproducto.codigo_barras,
                        codigo_proveedor: copyproducto.codigo_proveedor,
                        descripcion: copyproducto.descripcion,
                        id_categoria: copyproducto.id_categoria,
                        id_deposito: copyproducto.id_deposito,
                        id_marca: copyproducto.id_marca,
                        id_proveedor: copyproducto.id_proveedor,
                        iva: copyproducto.iva,
                        porcentaje_ganancia: copyproducto.porcentaje_ganancia,
                        precio: copyproducto.precio,
                        precio1: copyproducto.precio1,
                        precio2: copyproducto.precio2,
                        precio3: copyproducto.precio3,
                        precio_base: copyproducto.precio_base,
                        stockmax: copyproducto.stockmax,
                        stockmin: copyproducto.stockmin,
                        unidad: copyproducto.unidad,
                        id_vinculacion: val,
                        type: "new",
                        estatus: 1,
                    };
                } else if (name == "id_vinculacion") {
                    if (obj[i].type != "new") {
                        obj[i].type = "update";
                        obj[i]["estatus"] = 1;

                        let productoclone = productos.filter(
                            (e) => e.id == val
                        );

                        if (productoclone.length) {
                            obj[i]["codigo_barras"] =
                                productoclone[0].codigo_barras;
                            obj[i]["codigo_proveedor"] =
                                productoclone[0].codigo_proveedor;
                            obj[i]["id_proveedor"] =
                                productoclone[0].id_proveedor;
                            obj[i]["id_categoria"] =
                                productoclone[0].id_categoria;
                            obj[i]["id_marca"] = productoclone[0].id_marca;
                            obj[i]["unidad"] = productoclone[0].unidad;
                            obj[i]["id_deposito"] =
                                productoclone[0].id_deposito;
                            obj[i]["descripcion"] =
                                productoclone[0].descripcion;
                            obj[i]["iva"] = productoclone[0].iva;
                            obj[i]["porcentaje_ganancia"] =
                                productoclone[0].porcentaje_ganancia;
                            obj[i]["precio_base"] =
                                productoclone[0].precio_base;
                            obj[i]["precio"] = productoclone[0].precio;
                            obj[i]["precio1"] = productoclone[0].precio1;
                            obj[i]["precio2"] = productoclone[0].precio2;
                            obj[i]["precio3"] = productoclone[0].precio3;
                            obj[i]["bulto"] = productoclone[0].bulto;
                            obj[i]["stockmin"] = productoclone[0].stockmin;
                            obj[i]["stockmax"] = productoclone[0].stockmax;
                        }
                    }
                }
                obj[i][name] = val;
                break;
            case "add":
                /*  let pro = ""

      if (facturas[factSelectIndex]) {
        pro = facturas[factSelectIndex].proveedor.id
      } else {
        pro = sameProValue
      }

      */
                let newObj = [
                    {
                        id: null,
                        id_vinculacion: null,
                        codigo_proveedor: "",
                        codigo_barras: "",
                        descripcion: "",
                        id_categoria: 1,
                        id_marca: "",
                        unidad: "UND",
                        id_proveedor: 1,
                        cantidad: "",
                        precio_base: "",
                        precio: "",
                        iva: "0",
                        type: "new",
                        estatus: 1,
                        precio1: 0,
                        precio2: 0,
                        precio3: 0,
                        stockmin: 0,
                        stockmax: 0,
                        id_vinculacion: null,
                    },
                ];

                obj = newObj.concat(obj);
                break;

            case "delMode":
                obj[i].type = "delete";
                obj[i]["estatus"] = 1;
                break;
        }
        setdatainventarioSucursalFromCentralcopy(obj);
        setdatainventarioSucursalFromCentral(obj);
    };

    const printPrecios = (type) => {
        if (productosInventario.length) {
            db.printPrecios({
                type,
                ids: productosInventario.map((e) => e.id),
            }).then((res) => {
                setdropprintprice(false);
            });
        }
    };
    const logout = () => {
        console.log("Función logout ejecutada");

        // Limpiar datos de sesión del localStorage
        localStorage.removeItem("user_data");
        localStorage.removeItem("session_token");

        console.log("localStorage limpiado");

        // Llamar al logout del backend
        db.logout()
            .then((e) => {
                console.log("Logout exitoso, redirigiendo...");
                window.location.href = "/";
            })
            .catch((error) => {
                console.log("Error en logout:", error);
                // Si hay error, redirigir de todas formas
                window.location.href = "/";
            });
    };
    const auth = (permiso) => {
        if (permiso == "super") {
            if (user.usuario == "admin") {
                return true;
            }
            return false;
        }
        let nivel = user.nivel;
        if (permiso == 1) {
            if (nivel == 1) {
                return true;
            }
        }
        if (permiso == 2) {
            //if (nivel == 1 || nivel == 2) {
            return true;
            //}
        }
        if (permiso == 3) {
            //if (nivel == 1 || nivel == 3) {
            return true;
            //}
        }

        if (permiso == 7) {
            return true;
        }
        return false;
    };
    const printTickedPrecio = (id) => {
        db.printTickedPrecio({ id });
    };
    let sumsubtotalespresupuesto = () => {
        let sum = 0;
        presupuestocarrito.map((e) => {
            sum += parseFloat(e.subtotal);
        });
        return moneda(sum);
    };

    const [isCierre, setisCierre] = useState(false);
    const getPermisoCierre = () => {
        if (!isCierre) {
            let params = {};
            db.getPermisoCierre(params).then((res) => {
                notificar(res);
                if (res.data.estado === true) {
                    setisCierre(true);
                    setView("cierres");
                } else if (res.data.estado === false) {
                    //setLastDbRequest({ dbFunction: db.getPermisoCierre, params });
                    openValidationTarea(res.data.id_tarea);
                }
            });
        } else {
            setView("cierres");
        }
    };

    const inputsetclaveadminref = useRef(null);
    const [showclaveadmin, setshowclaveadmin] = useState(false);
    const [valinputsetclaveadmin, setvalinputsetclaveadmin] = useState("");
    const [idtareatemp, setidtareatemp] = useState(null);

    const openValidationTarea = (id_tarea) => {
        if (id_tarea) {
            setshowclaveadmin(true);
            setidtareatemp(id_tarea);
        }
    };
    const closemodalsetclave = () => {
        setshowclaveadmin(false);
    };
    const sendClavemodal = () => {
        if (idtareatemp) {
            db.sendClavemodal({
                valinputsetclaveadmin,
                idtareatemp,
            }).then((res) => {
                if (res.data.estado === true) {
                    setshowclaveadmin(false);
                    setvalinputsetclaveadmin("");
                    if (lastDbRequest) {
                        lastDbRequest
                            .dbFunction(lastDbRequest.params)
                            .then((res) => {
                                notificar(res.data.msj);
                                getPedido();
                                setLastDbRequest(null);
                            });
                    }
                }
                notificar(res);
            });
        } else {
            alert("idtareatemp no es Válido " + idtareatemp);
        }
    };

    // Agregar al inicio del componente, después de los useState
    const [lastDbRequest, setLastDbRequest] = useState(null);

    return (
        <div
            className={`${"h-[calc(100vh-56px)] mt-[56px] overflow-auto"}`}
        >
            {showHeaderAndMenu && (
                <Header
                    addNewPedido={addNewPedido}
                    pedidosFast={pedidosFast}
                    onClickEditPedido={onClickEditPedido}
                    pedidoData={pedidoData}
                    updatetasasfromCentral={updatetasasfromCentral}
                    getip={getip}
                    auth={auth}
                    logout={logout}
                    user={user}
                    dolar={dolar}
                    peso={peso}
                    setMoneda={updateDollarRate}
                    view={view}
                    getPedidos={getPedidos}
                    setViewCaja={setViewCaja}
                    viewCaja={viewCaja}
                    setShowModalMovimientos={setShowModalMovimientos}
                    showModalMovimientos={showModalMovimientos}
                    getVentasClick={getVentasClick}
                    toggleClientesBtn={toggleClientesBtn}
                    settoggleClientesBtn={settoggleClientesBtn}
                    setView={setView}
                    isCierre={isCierre}
                    getPermisoCierre={getPermisoCierre}
                    setsubViewInventario={setsubViewInventario}
                    subViewInventario={subViewInventario}
                    showHeaderAndMenu={showHeaderAndMenu}
                    setShowHeaderAndMenu={setShowHeaderAndMenu}
                />
            )}
            {/* <div id="reader"></div> */}
            {showclaveadmin ? (
                <Modalsetclaveadmin
                    inputsetclaveadminref={inputsetclaveadminref}
                    valinputsetclaveadmin={valinputsetclaveadmin}
                    setvalinputsetclaveadmin={setvalinputsetclaveadmin}
                    closemodalsetclave={closemodalsetclave}
                    sendClavemodal={sendClavemodal}
                    typingTimeout={typingTimeout}
                    setTypingTimeout={setTypingTimeout}
                />
            ) : (
                <>
                    {view == "tareas" ? (
                        <div className="px-2 container-fluid">
                            <div className="mb-3 row">
                                <div className="col-12">
                                    <h1 className="mb-2 h4">
                                        Tareas
                                        <button
                                            className="btn btn-outline-success btn-sm ms-2"
                                            onClick={getTareasLocal}
                                        >
                                            <i className="fa fa-search"></i>
                                        </button>
                                    </h1>
                                    <input
                                        type="date"
                                        className="mb-3 form-control"
                                        value={tareasinputfecha}
                                        onChange={(e) =>
                                            settareasinputfecha(e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            {/* Vista móvil - Cards */}
                            <div className="d-md-none">
                                {tareasAdminLocalData.length ? (
                                    tareasAdminLocalData.map((e) => (
                                        <div
                                            key={e.id}
                                            className="mb-3 shadow-sm card"
                                        >
                                            <div className="p-3 card-body">
                                                <div className="row">
                                                    <div className="mb-2 col-12">
                                                        <div className="d-flex justify-content-between align-items-center">
                                                            <h6 className="mb-0 card-title text-primary">
                                                                #{e.id_pedido}
                                                            </h6>
                                                            <span
                                                                className={`badge ${
                                                                    e.estado
                                                                        ? "bg-success"
                                                                        : "bg-warning"
                                                                }`}
                                                            >
                                                                {e.estado
                                                                    ? "Resuelta"
                                                                    : "Pendiente"}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div className="mb-2 col-6">
                                                        <small className="text-muted">
                                                            Usuario:
                                                        </small>
                                                        <div className="fw-bold">
                                                            {e.usuario
                                                                ? e.usuario
                                                                      .usuario
                                                                : "N/A"}
                                                        </div>
                                                    </div>

                                                    <div className="mb-2 col-6">
                                                        <small className="text-muted">
                                                            Tipo:
                                                        </small>
                                                        <div className="fw-bold">
                                                            {e.tipo}
                                                        </div>
                                                    </div>

                                                    <div className="mb-2 col-12">
                                                        <small className="text-muted">
                                                            Descripción:
                                                        </small>
                                                        <div className="text-break">
                                                            {e.descripcion}
                                                        </div>
                                                    </div>

                                                    <div className="mb-3 col-12">
                                                        <small className="text-muted">
                                                            Fecha:
                                                        </small>
                                                        <div className="small">
                                                            {e.created_at}
                                                        </div>
                                                    </div>

                                                    <div className="col-12">
                                                        <div className="gap-2 d-flex justify-content-end">
                                                            <button
                                                                className="btn btn-danger btn-sm"
                                                                onClick={() =>
                                                                    resolverTareaLocal(
                                                                        e.id,
                                                                        "rechazar"
                                                                    )
                                                                }
                                                            >
                                                                <i className="fa fa-times me-1"></i>
                                                                Rechazar
                                                            </button>
                                                            {!e.estado && (
                                                                <button
                                                                    className="btn btn-warning btn-sm"
                                                                    onClick={() =>
                                                                        resolverTareaLocal(
                                                                            e.id
                                                                        )
                                                                    }
                                                                >
                                                                    <i className="fa fa-check me-1"></i>
                                                                    Resolver
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-4 text-center">
                                        <div className="text-muted">
                                            <i className="mb-3 fa fa-tasks fa-3x"></i>
                                            <p>No hay tareas disponibles</p>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Vista desktop - Tabla */}
                            <div className="d-none d-md-block">
                                <div className="table-responsive">
                                    <table className="table table-striped table-hover">
                                        <thead className="table-dark">
                                            <tr>
                                                <th width="100">Acción</th>
                                                <th>ID Pedido</th>
                                                <th>Usuario</th>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Hora</th>
                                                <th width="100">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tareasAdminLocalData.length ? (
                                                tareasAdminLocalData.map(
                                                    (e) => (
                                                        <tr key={e.id}>
                                                            <td>
                                                                <button
                                                                    className="btn btn-danger btn-sm"
                                                                    onClick={() =>
                                                                        resolverTareaLocal(
                                                                            e.id,
                                                                            "rechazar"
                                                                        )
                                                                    }
                                                                >
                                                                    <i className="fa fa-times"></i>
                                                                </button>
                                                            </td>
                                                            <td className="fw-bold text-primary">
                                                                #{e.id_pedido}
                                                            </td>
                                                            <td>
                                                                {e.usuario
                                                                    ? e.usuario
                                                                          .usuario
                                                                    : "N/A"}
                                                            </td>
                                                            <td>{e.tipo}</td>
                                                            <td className="text-break">
                                                                {e.descripcion}
                                                            </td>
                                                            <td className="small">
                                                                {e.created_at}
                                                            </td>
                                                            <td>
                                                                {e.estado ? (
                                                                    <span className="badge bg-success">
                                                                        Resuelta
                                                                    </span>
                                                                ) : (
                                                                    <button
                                                                        className="btn btn-warning btn-sm"
                                                                        onClick={() =>
                                                                            resolverTareaLocal(
                                                                                e.id
                                                                            )
                                                                        }
                                                                    >
                                                                        <i className="fa fa-check"></i>
                                                                    </button>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    )
                                                )
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan="7"
                                                        className="py-4 text-center"
                                                    >
                                                        <div className="text-muted">
                                                            <i className="mb-2 fa fa-tasks fa-2x"></i>
                                                            <p className="mb-0">
                                                                No hay tareas
                                                                disponibles
                                                            </p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {viewGarantiaFormato && (
                        <ModalFormatoGarantia
                            addCarritoRequestInterno={addCarritoRequestInterno}
                            setviewGarantiaFormato={setviewGarantiaFormato}
                            devolucionMotivo={devolucionMotivo}
                            setdevolucionMotivo={setdevolucionMotivo}
                            devolucion_cantidad_salida={
                                devolucion_cantidad_salida
                            }
                            setdevolucion_cantidad_salida={
                                setdevolucion_cantidad_salida
                            }
                            devolucion_motivo_salida={devolucion_motivo_salida}
                            setdevolucion_motivo_salida={
                                setdevolucion_motivo_salida
                            }
                            devolucion_ci_cajero={devolucion_ci_cajero}
                            setdevolucion_ci_cajero={setdevolucion_ci_cajero}
                            devolucion_ci_autorizo={devolucion_ci_autorizo}
                            setdevolucion_ci_autorizo={
                                setdevolucion_ci_autorizo
                            }
                            devolucion_dias_desdecompra={
                                devolucion_dias_desdecompra
                            }
                            setdevolucion_dias_desdecompra={
                                setdevolucion_dias_desdecompra
                            }
                            devolucion_ci_cliente={devolucion_ci_cliente}
                            setdevolucion_ci_cliente={setdevolucion_ci_cliente}
                            devolucion_telefono_cliente={
                                devolucion_telefono_cliente
                            }
                            setdevolucion_telefono_cliente={
                                setdevolucion_telefono_cliente
                            }
                            devolucion_nombre_cliente={
                                devolucion_nombre_cliente
                            }
                            setdevolucion_nombre_cliente={
                                setdevolucion_nombre_cliente
                            }
                            devolucion_nombre_cajero={devolucion_nombre_cajero}
                            setdevolucion_nombre_cajero={
                                setdevolucion_nombre_cajero
                            }
                            devolucion_nombre_autorizo={
                                devolucion_nombre_autorizo
                            }
                            setdevolucion_nombre_autorizo={
                                setdevolucion_nombre_autorizo
                            }
                            devolucion_trajo_factura={devolucion_trajo_factura}
                            setdevolucion_trajo_factura={
                                setdevolucion_trajo_factura
                            }
                            devolucion_motivonotrajofact={
                                devolucion_motivonotrajofact
                            }
                            setdevolucion_motivonotrajofact={
                                setdevolucion_motivonotrajofact
                            }
                            devolucion_numfactoriginal={
                                devolucion_numfactoriginal
                            }
                            setdevolucion_numfactoriginal={
                                setdevolucion_numfactoriginal
                            }
                            number={number}
                        />
                    )}

                    {view == "pedidosCentral" ? (
                        <PedidosCentralComponent
                            openBarcodeScan={openBarcodeScan}
                            buscarDatosFact={buscarDatosFact}
                            setbuscarDatosFact={setbuscarDatosFact}
                            getSucursales={getSucursales}
                            qpedidoscentralq={qpedidoscentralq}
                            setqpedidoscentralq={setqpedidoscentralq}
                            qpedidocentrallimit={qpedidocentrallimit}
                            setqpedidocentrallimit={setqpedidocentrallimit}
                            qpedidocentralestado={qpedidocentralestado}
                            setqpedidocentralestado={setqpedidocentralestado}
                            qpedidocentralemisor={qpedidocentralemisor}
                            setqpedidocentralemisor={setqpedidocentralemisor}
                            sucursalesCentral={sucursalesCentral}
                            saveChangeInvInSucurFromCentral={
                                saveChangeInvInSucurFromCentral
                            }
                            socketUrl={socketUrl}
                            setSocketUrl={setSocketUrl}
                            mastermachines={mastermachines}
                            getmastermachine={getmastermachine}
                            setInventarioFromSucursal={
                                setInventarioFromSucursal
                            }
                            getInventarioFromSucursal={
                                getInventarioFromSucursal
                            }
                            pathcentral={pathcentral}
                            setpathcentral={setpathcentral}
                            getPedidosCentral={getPedidosCentral}
                            selectPedidosCentral={selectPedidosCentral}
                            checkPedidosCentral={checkPedidosCentral}
                            removeVinculoCentral={removeVinculoCentral}
                            setinventarioModifiedCentralImport={
                                setinventarioModifiedCentralImport
                            }
                            inventarioModifiedCentralImport={
                                inventarioModifiedCentralImport
                            }
                            pedidosCentral={pedidosCentral}
                            setIndexPedidoCentral={setIndexPedidoCentral}
                            indexPedidoCentral={indexPedidoCentral}
                            moneda={moneda}
                            showaddpedidocentral={showaddpedidocentral}
                            setshowaddpedidocentral={setshowaddpedidocentral}
                            valheaderpedidocentral={valheaderpedidocentral}
                            setvalheaderpedidocentral={
                                setvalheaderpedidocentral
                            }
                            valbodypedidocentral={valbodypedidocentral}
                            setvalbodypedidocentral={setvalbodypedidocentral}
                            procesarImportPedidoCentral={
                                procesarImportPedidoCentral
                            }
                            getTareasCentral={getTareasCentral}
                            settareasCentral={settareasCentral}
                            tareasCentral={tareasCentral}
                            runTareaCentral={runTareaCentral}
                            modalmovilRef={modalmovilRef}
                            modalmovilx={modalmovilx}
                            modalmovily={modalmovily}
                            setmodalmovilshow={setmodalmovilshow}
                            modalmovilshow={modalmovilshow}
                            getProductos={getProductos}
                            productos={productos}
                            linkproductocentralsucursal={
                                linkproductocentralsucursal
                            }
                            inputbuscarcentralforvincular={
                                inputbuscarcentralforvincular
                            }
                            openVincularSucursalwithCentral={
                                openVincularSucursalwithCentral
                            }
                            idselectproductoinsucursalforvicular={
                                idselectproductoinsucursalforvicular
                            }
                        />
                    ) : null}
                    {view == "ventas" ? (
                        <Ventas
                            ventasData={ventasData}
                            getVentasClick={getVentasClick}
                            setfechaventas={setfechaventas}
                            fechaventas={fechaventas}
                            moneda={moneda}
                            onClickEditPedido={onClickEditPedido}
                            getVentas={getVentas}
                        />
                    ) : null}

                    {view == "vueltos" ? (
                        <Vueltos
                            onchangecaja={onchangecaja}
                            qDeudores={qDeudores}
                            deudoresList={deudoresList}
                            selectDeudor={selectDeudor}
                            setSelectDeudor={setSelectDeudor}
                            tipo_pago_deudor={tipo_pago_deudor}
                            monto_pago_deudor={monto_pago_deudor}
                            setPagoCredito={setPagoCredito}
                            onClickEditPedido={onClickEditPedido}
                            onCLickDelPedido={onCLickDelPedido}
                            detallesDeudor={detallesDeudor}
                            onlyVueltos={onlyVueltos}
                            setOnlyVueltos={setOnlyVueltos}
                            qBuscarCliente={qBuscarCliente}
                            setqBuscarCliente={setqBuscarCliente}
                            clientesCrud={clientesCrud}
                            setindexSelectCliente={setindexSelectCliente}
                            indexSelectCliente={indexSelectCliente}
                            setClienteCrud={setClienteCrud}
                            delCliente={delCliente}
                            clienteInpidentificacion={clienteInpidentificacion}
                            setclienteInpidentificacion={
                                setclienteInpidentificacion
                            }
                            clienteInpnombre={clienteInpnombre}
                            setclienteInpnombre={setclienteInpnombre}
                            clienteInpcorreo={clienteInpcorreo}
                            setclienteInpcorreo={setclienteInpcorreo}
                            clienteInpdireccion={clienteInpdireccion}
                            setclienteInpdireccion={setclienteInpdireccion}
                            clienteInptelefono={clienteInptelefono}
                            setclienteInptelefono={setclienteInptelefono}
                            clienteInpestado={clienteInpestado}
                            setclienteInpestado={setclienteInpestado}
                            clienteInpciudad={clienteInpciudad}
                            setclienteInpciudad={setclienteInpciudad}
                            sumPedidos={sumPedidos}
                            sumPedidosArr={sumPedidosArr}
                            setsumPedidosArr={setsumPedidosArr}
                        />
                    ) : null}

                    {view == "clientes_crud" ? (
                        <Clientes
                            qBuscarCliente={qBuscarCliente}
                            setqBuscarCliente={setqBuscarCliente}
                            clientesCrud={clientesCrud}
                            setindexSelectCliente={setindexSelectCliente}
                            indexSelectCliente={indexSelectCliente}
                            setClienteCrud={setClienteCrud}
                            delCliente={delCliente}
                            clienteInpidentificacion={clienteInpidentificacion}
                            setclienteInpidentificacion={
                                setclienteInpidentificacion
                            }
                            clienteInpnombre={clienteInpnombre}
                            setclienteInpnombre={setclienteInpnombre}
                            clienteInpcorreo={clienteInpcorreo}
                            setclienteInpcorreo={setclienteInpcorreo}
                            clienteInpdireccion={clienteInpdireccion}
                            setclienteInpdireccion={setclienteInpdireccion}
                            clienteInptelefono={clienteInptelefono}
                            setclienteInptelefono={setclienteInptelefono}
                            clienteInpestado={clienteInpestado}
                            setclienteInpestado={setclienteInpestado}
                            clienteInpciudad={clienteInpciudad}
                            setclienteInpciudad={setclienteInpciudad}
                        />
                    ) : null}

                           {/*  {view == "cierres" ? (
                                <CierreV2 totalizarcierre={totalizarcierre} onClose={() => setView("ventas")} bancos={bancos} />
                            ) : null} */}
                    {view == "cierres" ? (
                        <Cierres
                            bancos={bancos}
                            dataPuntosAdicionales={dataPuntosAdicionales}
                            setdataPuntosAdicionales={setdataPuntosAdicionales}
                            addTuplasPuntosAdicionales={
                                addTuplasPuntosAdicionales
                            }
                            reversarCierre={reversarCierre}
                            puntolote1banco={puntolote1banco}
                            puntolote2banco={puntolote2banco}
                            setpuntolote1banco={setpuntolote1banco}
                            setpuntolote2banco={setpuntolote2banco}
                            lote1punto={lote1punto}
                            setlote1punto={setlote1punto}
                            montolote1punto={montolote1punto}
                            setmontolote1punto={setmontolote1punto}
                            lote2punto={lote2punto}
                            setlote2punto={setlote2punto}
                            montolote2punto={montolote2punto}
                            setmontolote2punto={setmontolote2punto}
                            serialbiopago={serialbiopago}
                            setserialbiopago={setserialbiopago}
                            setCajaFuerteEntradaCierreDolar={
                                setCajaFuerteEntradaCierreDolar
                            }
                            CajaFuerteEntradaCierreDolar={
                                CajaFuerteEntradaCierreDolar
                            }
                            setCajaFuerteEntradaCierreCop={
                                setCajaFuerteEntradaCierreCop
                            }
                            CajaFuerteEntradaCierreCop={
                                CajaFuerteEntradaCierreCop
                            }
                            setCajaFuerteEntradaCierreBs={
                                setCajaFuerteEntradaCierreBs
                            }
                            CajaFuerteEntradaCierreBs={
                                CajaFuerteEntradaCierreBs
                            }
                            setCajaChicaEntradaCierreDolar={
                                setCajaChicaEntradaCierreDolar
                            }
                            CajaChicaEntradaCierreDolar={
                                CajaChicaEntradaCierreDolar
                            }
                            setCajaChicaEntradaCierreCop={
                                setCajaChicaEntradaCierreCop
                            }
                            CajaChicaEntradaCierreCop={
                                CajaChicaEntradaCierreCop
                            }
                            setCajaChicaEntradaCierreBs={
                                setCajaChicaEntradaCierreBs
                            }
                            CajaChicaEntradaCierreBs={CajaChicaEntradaCierreBs}
                            tipoUsuarioCierre={tipoUsuarioCierre}
                            settipoUsuarioCierre={settipoUsuarioCierre}
                            cierrenumreportez={cierrenumreportez}
                            setcierrenumreportez={setcierrenumreportez}
                            cierreventaexcento={cierreventaexcento}
                            setcierreventaexcento={setcierreventaexcento}
                            cierreventagravadas={cierreventagravadas}
                            setcierreventagravadas={setcierreventagravadas}
                            cierreivaventa={cierreivaventa}
                            setcierreivaventa={setcierreivaventa}
                            cierretotalventa={cierretotalventa}
                            setcierretotalventa={setcierretotalventa}
                            cierreultimafactura={cierreultimafactura}
                            setcierreultimafactura={setcierreultimafactura}
                            cierreefecadiccajafbs={cierreefecadiccajafbs}
                            setcierreefecadiccajafbs={setcierreefecadiccajafbs}
                            cierreefecadiccajafcop={cierreefecadiccajafcop}
                            setcierreefecadiccajafcop={
                                setcierreefecadiccajafcop
                            }
                            cierreefecadiccajafdolar={cierreefecadiccajafdolar}
                            setcierreefecadiccajafdolar={
                                setcierreefecadiccajafdolar
                            }
                            cierreefecadiccajafeuro={cierreefecadiccajafeuro}
                            setcierreefecadiccajafeuro={
                                setcierreefecadiccajafeuro
                            }
                            getTotalizarCierre={getTotalizarCierre}
                            totalizarcierre={totalizarcierre}
                            setTotalizarcierre={setTotalizarcierre}
                            moneda={moneda}
                            auth={auth}
                            sendCuentasporCobrar={sendCuentasporCobrar}
                            fechaGetCierre2={fechaGetCierre2}
                            setfechaGetCierre2={setfechaGetCierre2}
                            verCierreReq={verCierreReq}
                            fechaGetCierre={fechaGetCierre}
                            setfechaGetCierre={setfechaGetCierre}
                            getCierres={getCierres}
                            cierres={cierres}
                            number={number}
                            guardar_usd={guardar_usd}
                            setguardar_usd={setguardar_usd}
                            guardar_cop={guardar_cop}
                            setguardar_cop={setguardar_cop}
                            guardar_bs={guardar_bs}
                            setguardar_bs={setguardar_bs}
                            settipo_accionCierre={settipo_accionCierre}
                            tipo_accionCierre={tipo_accionCierre}
                            caja_usd={caja_usd}
                            setcaja_usd={setCaja_usd}
                            caja_cop={caja_cop}
                            setcaja_cop={setCaja_cop}
                            caja_bs={caja_bs}
                            setcaja_bs={setCaja_bs}
                            caja_punto={caja_punto}
                            setCaja_punto={setCaja_punto}
                            setcaja_biopago={setcaja_biopago}
                            caja_biopago={caja_biopago}
                            dejar_usd={dejar_usd}
                            dejar_cop={dejar_cop}
                            dejar_bs={dejar_bs}
                            setDejar_usd={setDejar_usd}
                            setDejar_cop={setDejar_cop}
                            setDejar_bs={setDejar_bs}
                            lotespuntototalizar={lotespuntototalizar}
                            biopagostotalizar={biopagostotalizar}
                            cierre={cierre}
                            cerrar_dia={cerrar_dia}
                            fun_setguardar={fun_setguardar}
                            setcajaFuerteFun={setcajaFuerteFun}
                            total_caja_neto={total_caja_neto}
                            total_punto={total_punto}
                            total_biopago={total_biopago}
                            total_dejar_caja_neto={total_dejar_caja_neto}
                            viewCierre={viewCierre}
                            setViewCierre={setViewCierre}
                            toggleDetallesCierre={toggleDetallesCierre}
                            setToggleDetallesCierre={setToggleDetallesCierre}
                            onchangecaja={onchangecaja}
                            fechaCierre={fechaCierre}
                            setFechaCierre={setFechaCierre}
                            guardar_cierre={guardar_cierre}
                            veryenviarcierrefun={veryenviarcierrefun}
                            notaCierre={notaCierre}
                            billete1={billete1}
                            setbillete1={setbillete1}
                            billete5={billete5}
                            setbillete5={setbillete5}
                            billete10={billete10}
                            setbillete10={setbillete10}
                            billete20={billete20}
                            setbillete20={setbillete20}
                            billete50={billete50}
                            setbillete50={setbillete50}
                            billete100={billete100}
                            setbillete100={setbillete100}
                            dolar={dolar}
                            peso={peso}
                        />
                    ) : null}
                    {view == "pedidos" ? (
                        <Pedidos
                            setView={setView}
                            getReferenciasElec={getReferenciasElec}
                            refrenciasElecData={refrenciasElecData}
                            togleeReferenciasElec={togleeReferenciasElec}
                            settogleeReferenciasElec={settogleeReferenciasElec}
                            addNewPedido={addNewPedido}
                            setmodalchangepedido={setmodalchangepedido}
                            setseletIdChangePedidoUserHandle={
                                setseletIdChangePedidoUserHandle
                            }
                            modalchangepedido={modalchangepedido}
                            modalchangepedidoy={modalchangepedidoy}
                            modalchangepedidox={modalchangepedidox}
                            usuarioChangeUserPedido={usuarioChangeUserPedido}
                           
                            usuariosData={usuariosData}
                            auth={auth}
                            toggleImprimirTicket={toggleImprimirTicket}
                            pedidoData={pedidoData}
                            showModalPedidoFast={showModalPedidoFast}
                            setshowModalPedidoFast={setshowModalPedidoFast}
                            getPedidoFast={getPedidoFast}
                            clickSetOrderColumnPedidos={
                                clickSetOrderColumnPedidos
                            }
                            orderbycolumpedidos={orderbycolumpedidos}
                            setorderbycolumpedidos={setorderbycolumpedidos}
                            orderbyorderpedidos={orderbyorderpedidos}
                            setorderbyorderpedidos={setorderbyorderpedidos}
                            moneda={moneda}
                            setshowMisPedido={setshowMisPedido}
                            showMisPedido={showMisPedido}
                            tipobusquedapedido={tipobusquedapedido}
                            setTipoBusqueda={setTipoBusqueda}
                            busquedaPedido={busquedaPedido}
                            fecha1pedido={fecha1pedido}
                            fecha2pedido={fecha2pedido}
                            onChangePedidos={onChangePedidos}
                            onClickEditPedido={onClickEditPedido}
                            onCLickDelPedido={onCLickDelPedido}
                            pedidos={pedidos}
                            getPedidos={getPedidos}
                            filterMetodoPago={filterMetodoPago}
                            filterMetodoPagoToggle={filterMetodoPagoToggle}
                            tipoestadopedido={tipoestadopedido}
                            setTipoestadopedido={setTipoestadopedido}
                        />
                    ) : null}

                    {view == "inventario" ? (
                        <Inventario
                            buscarInventario={buscarInventario}
                            getFacturas={getFacturas}
                            openBarcodeScan={openBarcodeScan}
                            sincInventario={sincInventario}
                            numReporteZ={numReporteZ}
                            setnumReporteZ={setnumReporteZ}
                            reportefiscal={reportefiscal}
                            exportPendientes={exportPendientes}
                            setSalidaGarantias={setSalidaGarantias}
                            garantiaEstado={garantiaEstado}
                            setgarantiaEstado={setgarantiaEstado}
                            garantiasData={garantiasData}
                            getGarantias={getGarantias}
                            setqgarantia={setqgarantia}
                            qgarantia={qgarantia}
                            garantiaorderCampo={garantiaorderCampo}
                            setgarantiaorderCampo={setgarantiaorderCampo}
                            garantiaorder={garantiaorder}
                            setgarantiaorder={setgarantiaorder}
                            dolar={dolar}
                            peso={peso}
                            inventarioNovedadesData={inventarioNovedadesData}
                            setinventarioNovedadesData={
                                setinventarioNovedadesData
                            }
                            getInventarioNovedades={getInventarioNovedades}
                            resolveInventarioNovedades={
                                resolveInventarioNovedades
                            }
                            sendInventarioNovedades={sendInventarioNovedades}
                            delInventarioNovedades={delInventarioNovedades}
                            aprobarRecepcionCaja={aprobarRecepcionCaja}
                            reversarMovPendientes={reversarMovPendientes}
                            getSucursales={getSucursales}
                            transferirpedidoa={transferirpedidoa}
                            settransferirpedidoa={settransferirpedidoa}
                            sucursalesCentral={sucursalesCentral}
                            getAlquileres={getAlquileres}
                            alquileresData={alquileresData}
                            getPorcentajeInventario={getPorcentajeInventario}
                            cleanInventario={cleanInventario}
                            allProveedoresCentral={allProveedoresCentral}
                            getAllProveedores={getAllProveedores}
                            setView={setView}
                            verificarMovPenControlEfec={
                                verificarMovPenControlEfec
                            }
                            verificarMovPenControlEfecTRANFTRABAJADOR={
                                verificarMovPenControlEfecTRANFTRABAJADOR
                            }
                            openModalNuevoEfectivo={openModalNuevoEfectivo}
                            setopenModalNuevoEfectivo={
                                setopenModalNuevoEfectivo
                            }
                            controlefecResponsable={controlefecResponsable}
                            setcontrolefecResponsable={
                                setcontrolefecResponsable
                            }
                            controlefecAsignar={controlefecAsignar}
                            setcontrolefecAsignar={setcontrolefecAsignar}
                            personalNomina={personalNomina}
                            setpersonalNomina={setpersonalNomina}
                            getNomina={getNomina}
                            categoriasCajas={categoriasCajas}
                            departamentosCajas={departamentosCajas}
                            setcategoriasCajas={setcategoriasCajas}
                            getcatsCajas={getcatsCajas}
                            getEstaInventario={getEstaInventario}
                            controlefecQ={controlefecQ}
                            setcontrolefecQ={setcontrolefecQ}
                            controlefecQDesde={controlefecQDesde}
                            setcontrolefecQDesde={setcontrolefecQDesde}
                            controlefecQHasta={controlefecQHasta}
                            setcontrolefecQHasta={setcontrolefecQHasta}
                            controlefecData={controlefecData}
                            setcontrolefecData={setcontrolefecData}
                            controlefecSelectGeneral={controlefecSelectGeneral}
                            setcontrolefecSelectGeneral={
                                setcontrolefecSelectGeneral
                            }
                            controlefecSelectUnitario={
                                controlefecSelectUnitario
                            }
                            setcontrolefecSelectUnitario={
                                setcontrolefecSelectUnitario
                            }
                            controlefecNewConcepto={controlefecNewConcepto}
                            setcontrolefecNewConcepto={
                                setcontrolefecNewConcepto
                            }
                            controlefecNewCategoria={controlefecNewCategoria}
                            setcontrolefecNewCategoria={
                                setcontrolefecNewCategoria
                            }
                            controlefecNewDepartamento={
                                controlefecNewDepartamento
                            }
                            setcontrolefecNewDepartamento={
                                setcontrolefecNewDepartamento
                            }
                            controlefecNewMonto={controlefecNewMonto}
                            setcontrolefecNewMonto={setcontrolefecNewMonto}
                            controlefecNewMontoMoneda={
                                controlefecNewMontoMoneda
                            }
                            setcontrolefecNewMontoMoneda={
                                setcontrolefecNewMontoMoneda
                            }
                            setcontrolefecid_persona={setcontrolefecid_persona}
                            controlefecid_persona={controlefecid_persona}
                            setcontrolefecid_alquiler={
                                setcontrolefecid_alquiler
                            }
                            controlefecid_alquiler={controlefecid_alquiler}
                            controlefecid_proveedor={controlefecid_proveedor}
                            setcontrolefecid_proveedor={
                                setcontrolefecid_proveedor
                            }
                            controlefecQCategoria={controlefecQCategoria}
                            setcontrolefecQCategoria={setcontrolefecQCategoria}
                            getControlEfec={getControlEfec}
                            setControlEfec={setControlEfec}
                            delCaja={delCaja}
                            saveReplaceProducto={saveReplaceProducto}
                            selectRepleceProducto={selectRepleceProducto}
                            replaceProducto={replaceProducto}
                            setreplaceProducto={setreplaceProducto}
                            user={user}
                            setStockMin={setStockMin}
                            datamodalhistoricoproducto={
                                datamodalhistoricoproducto
                            }
                            setdatamodalhistoricoproducto={
                                setdatamodalhistoricoproducto
                            }
                            getmovientoinventariounitario={
                                getmovientoinventariounitario
                            }
                            openmodalhistoricoproducto={
                                openmodalhistoricoproducto
                            }
                            showmodalhistoricoproducto={
                                showmodalhistoricoproducto
                            }
                            setshowmodalhistoricoproducto={
                                setshowmodalhistoricoproducto
                            }
                            fecha1modalhistoricoproducto={
                                fecha1modalhistoricoproducto
                            }
                            setfecha1modalhistoricoproducto={
                                setfecha1modalhistoricoproducto
                            }
                            fecha2modalhistoricoproducto={
                                fecha2modalhistoricoproducto
                            }
                            setfecha2modalhistoricoproducto={
                                setfecha2modalhistoricoproducto
                            }
                            usuariomodalhistoricoproducto={
                                usuariomodalhistoricoproducto
                            }
                            setusuariomodalhistoricoproducto={
                                setusuariomodalhistoricoproducto
                            }
                            usuariosData={usuariosData}
                            getUsuarios={getUsuarios}
                            qhistoinven={qhistoinven}
                            setqhistoinven={setqhistoinven}
                            fecha1histoinven={fecha1histoinven}
                            setfecha1histoinven={setfecha1histoinven}
                            fecha2histoinven={fecha2histoinven}
                            setfecha2histoinven={setfecha2histoinven}
                            orderByHistoInven={orderByHistoInven}
                            setorderByHistoInven={setorderByHistoInven}
                            historicoInventario={historicoInventario}
                            usuarioHistoInven={usuarioHistoInven}
                            setusuarioHistoInven={setusuarioHistoInven}
                            getHistoricoInventario={getHistoricoInventario}
                            categoriaEstaInve={categoriaEstaInve}
                            setcategoriaEstaInve={setcategoriaEstaInve}
                            printTickedPrecio={printTickedPrecio}
                            sameCatValue={sameCatValue}
                            sameProValue={sameProValue}
                            setdropprintprice={setdropprintprice}
                            dropprintprice={dropprintprice}
                            printPrecios={printPrecios}
                            setCtxBulto={setCtxBulto}
                            setPrecioAlterno={setPrecioAlterno}
                            qgastosfecha1={qgastosfecha1}
                            setqgastosfecha1={setqgastosfecha1}
                            qgastosfecha2={qgastosfecha2}
                            setqgastosfecha2={setqgastosfecha2}
                            qgastos={qgastos}
                            setqgastos={setqgastos}
                            qcatgastos={qcatgastos}
                            setqcatgastos={setqcatgastos}
                            gastosdescripcion={gastosdescripcion}
                            setgastosdescripcion={setgastosdescripcion}
                            gastoscategoria={gastoscategoria}
                            setgastoscategoria={setgastoscategoria}
                            gastosmonto={gastosmonto}
                            setgastosmonto={setgastosmonto}
                            gastosData={gastosData}
                            delGastos={delGastos}
                            getGastos={getGastos}
                            setGasto={setGasto}
                            delPagoProveedor={delPagoProveedor}
                            busqAvanzInputsFun={busqAvanzInputsFun}
                            busqAvanzInputs={busqAvanzInputs}
                            buscarInvAvanz={buscarInvAvanz}
                            busquedaAvanazadaInv={busquedaAvanazadaInv}
                            setbusquedaAvanazadaInv={setbusquedaAvanazadaInv}
                            setSameGanancia={setSameGanancia}
                            setSameCat={setSameCat}
                            setSamePro={setSamePro}
                            openReporteFalla={openReporteFalla}
                            getPagoProveedor={getPagoProveedor}
                            setPagoProveedor={setPagoProveedor}
                            pagosproveedor={pagosproveedor}
                            tipopagoproveedor={tipopagoproveedor}
                            settipopagoproveedor={settipopagoproveedor}
                            montopagoproveedor={montopagoproveedor}
                            setmontopagoproveedor={setmontopagoproveedor}
                            setmodFact={setmodFact}
                            modFact={modFact}
                            saveFactura={saveFactura}
                            categorias={categorias}
                            setporcenganancia={setporcenganancia}
                            refsInpInvList={refsInpInvList}
                            guardarNuevoProductoLote={guardarNuevoProductoLote}
                            changeInventario={changeInventario}
                            reporteInventario={reporteInventario}
                            addNewLote={addNewLote}
                            changeModLote={changeModLote}
                            modViewInventario={modViewInventario}
                            setmodViewInventario={setmodViewInventario}
                            setNewProducto={setNewProducto}
                            verDetallesFactura={verDetallesFactura}
                            showaddpedidocentral={showaddpedidocentral}
                            setshowaddpedidocentral={setshowaddpedidocentral}
                            valheaderpedidocentral={valheaderpedidocentral}
                            setvalheaderpedidocentral={
                                setvalheaderpedidocentral
                            }
                            valbodypedidocentral={valbodypedidocentral}
                            setvalbodypedidocentral={setvalbodypedidocentral}
                            procesarImportPedidoCentral={
                                procesarImportPedidoCentral
                            }
                            moneda={moneda}
                            productosInventario={productosInventario}
                            qBuscarInventario={qBuscarInventario}
                            setQBuscarInventario={setQBuscarInventario}
                            setIndexSelectInventario={setIndexSelectInventario}
                            indexSelectInventario={indexSelectInventario}
                            inputBuscarInventario={inputBuscarInventario}
                            inpInvbarras={inpInvbarras}
                            setinpInvbarras={setinpInvbarras}
                            inpInvcantidad={inpInvcantidad}
                            setinpInvcantidad={setinpInvcantidad}
                            inpInvalterno={inpInvalterno}
                            setinpInvalterno={setinpInvalterno}
                            inpInvunidad={inpInvunidad}
                            setinpInvunidad={setinpInvunidad}
                            inpInvcategoria={inpInvcategoria}
                            setinpInvcategoria={setinpInvcategoria}
                            inpInvdescripcion={inpInvdescripcion}
                            setinpInvdescripcion={setinpInvdescripcion}
                            inpInvbase={inpInvbase}
                            setinpInvbase={setinpInvbase}
                            inpInvventa={inpInvventa}
                            setinpInvventa={setinpInvventa}
                            inpInviva={inpInviva}
                            setinpInviva={setinpInviva}
                            inpInvLotes={inpInvLotes}
                            number={number}
                            guardarNuevoProducto={guardarNuevoProducto}
                            setProveedor={setProveedor}
                            proveedordescripcion={proveedordescripcion}
                            setproveedordescripcion={setproveedordescripcion}
                            proveedorrif={proveedorrif}
                            setproveedorrif={setproveedorrif}
                            proveedordireccion={proveedordireccion}
                            setproveedordireccion={setproveedordireccion}
                            proveedortelefono={proveedortelefono}
                            setproveedortelefono={setproveedortelefono}
                            subViewInventario={subViewInventario}
                            setsubViewInventario={setsubViewInventario}
                            setIndexSelectProveedores={
                                setIndexSelectProveedores
                            }
                            indexSelectProveedores={indexSelectProveedores}
                            qBuscarProveedor={qBuscarProveedor}
                            setQBuscarProveedor={setQBuscarProveedor}
                            proveedoresList={proveedoresList}
                            delProveedor={delProveedor}
                            delProducto={delProducto}
                            inpInvid_proveedor={inpInvid_proveedor}
                            setinpInvid_proveedor={setinpInvid_proveedor}
                            inpInvid_marca={inpInvid_marca}
                            setinpInvid_marca={setinpInvid_marca}
                            inpInvid_deposito={inpInvid_deposito}
                            setinpInvid_deposito={setinpInvid_deposito}
                            depositosList={depositosList}
                            marcasList={marcasList}
                            setshowModalFacturas={setshowModalFacturas}
                            showModalFacturas={showModalFacturas}
                            facturas={facturas}
                            factqBuscar={factqBuscar}
                            setfactqBuscar={setfactqBuscar}
                            factqBuscarDate={factqBuscarDate}
                            setfactqBuscarDate={setfactqBuscarDate}
                            factsubView={factsubView}
                            setfactsubView={setfactsubView}
                            factSelectIndex={factSelectIndex}
                            setfactSelectIndex={setfactSelectIndex}
                            factOrderBy={factOrderBy}
                            setfactOrderBy={setfactOrderBy}
                            factOrderDescAsc={factOrderDescAsc}
                            setfactOrderDescAsc={setfactOrderDescAsc}
                            factInpid_proveedor={factInpid_proveedor}
                            setfactInpid_proveedor={setfactInpid_proveedor}
                            factInpnumfact={factInpnumfact}
                            setfactInpnumfact={setfactInpnumfact}
                            factInpdescripcion={factInpdescripcion}
                            setfactInpdescripcion={setfactInpdescripcion}
                            factInpmonto={factInpmonto}
                            setfactInpmonto={setfactInpmonto}
                            factInpfechavencimiento={factInpfechavencimiento}
                            setfactInpfechavencimiento={
                                setfactInpfechavencimiento
                            }
                            factInpImagen={factInpImagen}
                            factInpestatus={factInpestatus}
                            setfactInpestatus={setfactInpestatus}
                            setFactura={setFactura}
                            delFactura={delFactura}
                            Invnum={Invnum}
                            setInvnum={setInvnum}
                            InvorderColumn={InvorderColumn}
                            setInvorderColumn={setInvorderColumn}
                            InvorderBy={InvorderBy}
                            setInvorderBy={setInvorderBy}
                            delItemFact={delItemFact}
                            qFallas={qFallas}
                            setqFallas={setqFallas}
                            orderCatFallas={orderCatFallas}
                            setorderCatFallas={setorderCatFallas}
                            orderSubCatFallas={orderSubCatFallas}
                            setorderSubCatFallas={setorderSubCatFallas}
                            ascdescFallas={ascdescFallas}
                            setascdescFallas={setascdescFallas}
                            fallas={fallas}
                            delFalla={delFalla}
                            getPedidosCentral={getPedidosCentral}
                            selectPedidosCentral={selectPedidosCentral}
                            checkPedidosCentral={checkPedidosCentral}
                            pedidosCentral={pedidosCentral}
                            setIndexPedidoCentral={setIndexPedidoCentral}
                            indexPedidoCentral={indexPedidoCentral}
                            fechaQEstaInve={fechaQEstaInve}
                            setfechaQEstaInve={setfechaQEstaInve}
                            fechaFromEstaInve={fechaFromEstaInve}
                            setfechaFromEstaInve={setfechaFromEstaInve}
                            fechaToEstaInve={fechaToEstaInve}
                            setfechaToEstaInve={setfechaToEstaInve}
                            orderByEstaInv={orderByEstaInv}
                            setorderByEstaInv={setorderByEstaInv}
                            orderByColumEstaInv={orderByColumEstaInv}
                            setorderByColumEstaInv={setorderByColumEstaInv}
                            dataEstaInven={dataEstaInven}
                        />
                    ) : null}
                    {view == "SelectFacturasInventario" ? (
                        <ModalSelectFactura
                            setfactInpImagen={setfactInpImagen}
                            allProveedoresCentral={allProveedoresCentral}
                            getAllProveedores={getAllProveedores}
                            setView={setView}
                            subViewInventario={subViewInventario}
                            delPagoProveedor={delPagoProveedor}
                            pagosproveedor={pagosproveedor}
                            getPagoProveedor={getPagoProveedor}
                            setPagoProveedor={setPagoProveedor}
                            tipopagoproveedor={tipopagoproveedor}
                            settipopagoproveedor={settipopagoproveedor}
                            montopagoproveedor={montopagoproveedor}
                            setmontopagoproveedor={setmontopagoproveedor}
                            setmodFact={setmodFact}
                            modFact={modFact}
                            qBuscarProveedor={qBuscarProveedor}
                            setQBuscarProveedor={setQBuscarProveedor}
                            setIndexSelectProveedores={
                                setIndexSelectProveedores
                            }
                            indexSelectProveedores={indexSelectProveedores}
                            moneda={moneda}
                            saveFactura={saveFactura}
                            setsubViewInventario={setsubViewInventario}
                            setshowModalFacturas={setshowModalFacturas}
                            facturas={facturas}
                            verDetallesFactura={verDetallesFactura}
                            verDetallesImagenFactura={verDetallesImagenFactura}
                            factqBuscar={factqBuscar}
                            setfactqBuscar={setfactqBuscar}
                            factqBuscarDate={factqBuscarDate}
                            setfactqBuscarDate={setfactqBuscarDate}
                            factsubView={factsubView}
                            setfactsubView={setfactsubView}
                            factSelectIndex={factSelectIndex}
                            setfactSelectIndex={setfactSelectIndex}
                            factOrderBy={factOrderBy}
                            setfactOrderBy={setfactOrderBy}
                            factOrderDescAsc={factOrderDescAsc}
                            setfactOrderDescAsc={setfactOrderDescAsc}
                            factInpid_proveedor={factInpid_proveedor}
                            setfactInpid_proveedor={setfactInpid_proveedor}
                            factInpnumfact={factInpnumfact}
                            setfactInpnumfact={setfactInpnumfact}
                            factInpdescripcion={factInpdescripcion}
                            setfactInpdescripcion={setfactInpdescripcion}
                            factInpmonto={factInpmonto}
                            setfactInpmonto={setfactInpmonto}
                            factInpfechavencimiento={factInpfechavencimiento}
                            setfactInpfechavencimiento={
                                setfactInpfechavencimiento
                            }
                            setFactura={setFactura}
                            proveedoresList={proveedoresList}
                            number={number}
                            factInpestatus={factInpestatus}
                            setfactInpestatus={setfactInpestatus}
                            delFactura={delFactura}
                            delItemFact={delItemFact}
                            factInpnumnota={factInpnumnota}
                            setfactInpnumnota={setfactInpnumnota}
                            factInpsubtotal={factInpsubtotal}
                            setfactInpsubtotal={setfactInpsubtotal}
                            factInpdescuento={factInpdescuento}
                            setfactInpdescuento={setfactInpdescuento}
                            factInpmonto_gravable={factInpmonto_gravable}
                            setfactInpmonto_gravable={setfactInpmonto_gravable}
                            factInpmonto_exento={factInpmonto_exento}
                            setfactInpmonto_exento={setfactInpmonto_exento}
                            factInpiva={factInpiva}
                            setfactInpiva={setfactInpiva}
                            factInpfechaemision={factInpfechaemision}
                            setfactInpfechaemision={setfactInpfechaemision}
                            factInpfecharecepcion={factInpfecharecepcion}
                            setfactInpfecharecepcion={setfactInpfecharecepcion}
                            factInpnota={factInpnota}
                            setfactInpnota={setfactInpnota}
                            sendFacturaCentral={sendFacturaCentral}
                            productosInventario={productosInventario}
                            changeInventario={changeInventario}
                            buscarInventario={buscarInventario}
                            guardarNuevoProductoLote={guardarNuevoProductoLote}
                            inputBuscarInventario={inputBuscarInventario}
                            setQBuscarInventario={setQBuscarInventario}
                            qBuscarInventario={qBuscarInventario}
                            Invnum={Invnum}
                            setInvnum={setInvnum}
                            InvorderBy={InvorderBy}
                            setInvorderBy={setInvorderBy}
                            changeInventarioNewFact={changeInventarioNewFact}
                            guardarNuevoProductoLoteFact={
                                guardarNuevoProductoLoteFact
                            }
                        ></ModalSelectFactura>
                    ) : null}
                    {view == "ModalSelectProductoNewFact" ? (
                        <ModalSelectProductoNewFact
                            setView={setView}
                            Invnum={Invnum}
                            setInvnum={setInvnum}
                            InvorderColumn={InvorderColumn}
                            setInvorderColumn={setInvorderColumn}
                            InvorderBy={InvorderBy}
                            setInvorderBy={setInvorderBy}
                            qBuscarInventario={qBuscarInventario}
                            setQBuscarInventario={setQBuscarInventario}
                            productosInventario={productosInventario}
                            inputBuscarInventario={inputBuscarInventario}
                            changeInventario={changeInventario}
                            categorias={categorias}
                            proveedoresList={proveedoresList}
                            guardarNuevoProductoLote={guardarNuevoProductoLote}
                            addProductoFactInventario={
                                addProductoFactInventario
                            }
                        />
                    ) : null}

                    {view == "proveedores" ? (
                        <Proveedores
                            setView={setView}
                            setProveedor={setProveedor}
                            proveedordescripcion={proveedordescripcion}
                            setproveedordescripcion={setproveedordescripcion}
                            proveedorrif={proveedorrif}
                            setproveedorrif={setproveedorrif}
                            proveedordireccion={proveedordireccion}
                            setproveedordireccion={setproveedordireccion}
                            proveedortelefono={proveedortelefono}
                            setproveedortelefono={setproveedortelefono}
                            subViewInventario={subViewInventario}
                            setsubViewInventario={setsubViewInventario}
                            setIndexSelectProveedores={
                                setIndexSelectProveedores
                            }
                            indexSelectProveedores={indexSelectProveedores}
                            qBuscarProveedor={qBuscarProveedor}
                            setQBuscarProveedor={setQBuscarProveedor}
                            proveedoresList={proveedoresList}
                            delProveedor={delProveedor}
                            delProducto={delProducto}
                            inpInvid_proveedor={inpInvid_proveedor}
                            setinpInvid_proveedor={setinpInvid_proveedor}
                            inpInvid_marca={inpInvid_marca}
                            setinpInvid_marca={setinpInvid_marca}
                            inpInvid_deposito={inpInvid_deposito}
                            setinpInvid_deposito={setinpInvid_deposito}
                        />
                    ) : null}
                    {view == "Submenuinventario" ? (
                        <Submenuinventario
                            view={view}
                            setView={setView}
                            setsubViewInventario={setsubViewInventario}
                            showAjustesPuntuales={showAjustesPuntuales}
                        />
                    ) : null}

                    {view == "ViewPedidoVendedor" ? (
                        <ViewPedidoVendedor />
                    ) : null}

                    {view == "pagar" ? (
                        <Pagar>
                            {toggleAddPersona ? (
                                <ModaladdPersona
                                    number={number}
                                    setToggleAddPersona={setToggleAddPersona}
                                    getPersona={getPersona}
                                    personas={personas}
                                    setPersonas={setPersonas}
                                    inputmodaladdpersonacarritoref={
                                        inputmodaladdpersonacarritoref
                                    }
                                    tbodypersoInterref={tbodypersoInterref}
                                    countListPersoInter={countListPersoInter}
                                    setPersonaFast={setPersonaFast}
                                    clienteInpidentificacion={
                                        clienteInpidentificacion
                                    }
                                    setclienteInpidentificacion={
                                        setclienteInpidentificacion
                                    }
                                    clienteInpnombre={clienteInpnombre}
                                    setclienteInpnombre={setclienteInpnombre}
                                    clienteInptelefono={clienteInptelefono}
                                    setclienteInptelefono={
                                        setclienteInptelefono
                                    }
                                    clienteInpdireccion={clienteInpdireccion}
                                    setclienteInpdireccion={
                                        setclienteInpdireccion
                                    }
                                />
                            ) : viewconfigcredito ? (
                                <Modalconfigcredito
                                    pedidoData={pedidoData}
                                    setPagoPedido={setPagoPedido}
                                    viewconfigcredito={viewconfigcredito}
                                    setviewconfigcredito={setviewconfigcredito}
                                    fechainiciocredito={fechainiciocredito}
                                    setfechainiciocredito={
                                        setfechainiciocredito
                                    }
                                    fechavencecredito={fechavencecredito}
                                    setfechavencecredito={setfechavencecredito}
                                    formatopagocredito={formatopagocredito}
                                    setformatopagocredito={
                                        setformatopagocredito
                                    }
                                    datadeudacredito={datadeudacredito}
                                    setdatadeudacredito={setdatadeudacredito}
                                    setconfigcredito={setconfigcredito}
                                />
                            ) : (
                                <PagarMain
                                    qProductosMain={qProductosMain}
                                    setLastDbRequest={setLastDbRequest}
                                    lastDbRequest={lastDbRequest}
                                    openValidationTarea={openValidationTarea}
                                    num={num}
                                    setNum={setNum}
                                    tbodyproducInterref={tbodyproducInterref}
                                    productos={productos}
                                    countListInter={countListInter}
                                    setProductoCarritoInterno={
                                        setProductoCarritoInterno
                                    }
                                    setQProductosMain={setQProductosMain}
                                    setCountListInter={setCountListInter}
                                    toggleModalProductos={toggleModalProductos}
                                    inputCantidadCarritoref={
                                        inputCantidadCarritoref
                                    }
                                    setCantidad={setCantidad}
                                    getProductos={getProductos}
                                    permisoExecuteEnter={permisoExecuteEnter}
                                    setproductoSelectinternouno={
                                        setproductoSelectinternouno
                                    }
                                    devolucionMotivo={devolucionMotivo}
                                    setdevolucionMotivo={setdevolucionMotivo}
                                    devolucion_cantidad_salida={
                                        devolucion_cantidad_salida
                                    }
                                    setdevolucion_cantidad_salida={
                                        setdevolucion_cantidad_salida
                                    }
                                    devolucion_motivo_salida={
                                        devolucion_motivo_salida
                                    }
                                    setdevolucion_motivo_salida={
                                        setdevolucion_motivo_salida
                                    }
                                    devolucion_ci_cajero={devolucion_ci_cajero}
                                    setdevolucion_ci_cajero={
                                        setdevolucion_ci_cajero
                                    }
                                    devolucion_ci_autorizo={
                                        devolucion_ci_autorizo
                                    }
                                    setdevolucion_ci_autorizo={
                                        setdevolucion_ci_autorizo
                                    }
                                    devolucion_dias_desdecompra={
                                        devolucion_dias_desdecompra
                                    }
                                    setdevolucion_dias_desdecompra={
                                        setdevolucion_dias_desdecompra
                                    }
                                    devolucion_ci_cliente={
                                        devolucion_ci_cliente
                                    }
                                    setdevolucion_ci_cliente={
                                        setdevolucion_ci_cliente
                                    }
                                    devolucion_telefono_cliente={
                                        devolucion_telefono_cliente
                                    }
                                    setdevolucion_telefono_cliente={
                                        setdevolucion_telefono_cliente
                                    }
                                    devolucion_nombre_cliente={
                                        devolucion_nombre_cliente
                                    }
                                    setdevolucion_nombre_cliente={
                                        setdevolucion_nombre_cliente
                                    }
                                    devolucion_nombre_cajero={
                                        devolucion_nombre_cajero
                                    }
                                    setdevolucion_nombre_cajero={
                                        setdevolucion_nombre_cajero
                                    }
                                    devolucion_nombre_autorizo={
                                        devolucion_nombre_autorizo
                                    }
                                    setdevolucion_nombre_autorizo={
                                        setdevolucion_nombre_autorizo
                                    }
                                    devolucion_trajo_factura={
                                        devolucion_trajo_factura
                                    }
                                    setdevolucion_trajo_factura={
                                        setdevolucion_trajo_factura
                                    }
                                    devolucion_motivonotrajofact={
                                        devolucion_motivonotrajofact
                                    }
                                    setdevolucion_motivonotrajofact={
                                        setdevolucion_motivonotrajofact
                                    }
                                    notificar={notificar}
                                    getPedidosFast={getPedidosFast}
                                    addNewPedido={addNewPedido}
                                    setModRetencion={setModRetencion}
                                    sendNotaCredito={sendNotaCredito}
                                    sendReciboFiscal={sendReciboFiscal}
                                    changeOnlyInputBulto={changeOnlyInputBulto}
                                    setchangeOnlyInputBultoFun={
                                        setchangeOnlyInputBultoFun
                                    }
                                    setshowXBulto={setshowXBulto}
                                    showXBulto={showXBulto}
                                    printBultos={printBultos}
                                    delRetencionPago={delRetencionPago}
                                    addRetencionesPago={addRetencionesPago}
                                    setGastoOperativo={setGastoOperativo}
                                    user={user}
                                    setselectprinter={setselectprinter}
                                    setmonedaToPrint={setmonedaToPrint}
                                    selectprinter={selectprinter}
                                    monedaToPrint={monedaToPrint}
                                    view={view}
                                    changeEntregado={changeEntregado}
                                    setPagoPedido={setPagoPedido}
                                    viewconfigcredito={viewconfigcredito}
                                    setviewconfigcredito={setviewconfigcredito}
                                    fechainiciocredito={fechainiciocredito}
                                    setfechainiciocredito={
                                        setfechainiciocredito
                                    }
                                    fechavencecredito={fechavencecredito}
                                    setfechavencecredito={setfechavencecredito}
                                    formatopagocredito={formatopagocredito}
                                    setformatopagocredito={
                                        setformatopagocredito
                                    }
                                    datadeudacredito={datadeudacredito}
                                    setdatadeudacredito={setdatadeudacredito}
                                    setconfigcredito={setconfigcredito}
                                    setPrecioAlternoCarrito={
                                        setPrecioAlternoCarrito
                                    }
                                    setCtxBultoCarrito={setCtxBultoCarrito}
                                    addRefPago={addRefPago}
                                    delRefPago={delRefPago}
                                    refPago={refPago}
                                    setrefPago={setrefPago}
                                    pedidosFast={pedidosFast}
                                    pedidoData={pedidoData}
                                    getPedido={getPedido}
                                    debito={debito}
                                    setDebito={setDebito}
                                    debitoRef={debitoRef}
                                    setDebitoRef={setDebitoRef}
                                    debitoRefError={debitoRefError}
                                    setDebitoRefError={setDebitoRefError}
                                    efectivo={efectivo}
                                    setEfectivo={setEfectivo}
                                    efectivo_bs={efectivo_bs}
                                    setEfectivo_bs={setEfectivo_bs}
                                    efectivo_dolar={efectivo_dolar}
                                    setEfectivo_dolar={setEfectivo_dolar}
                                    efectivo_peso={efectivo_peso}
                                    setEfectivo_peso={setEfectivo_peso}
                                    transferencia={transferencia}
                                    setTransferencia={setTransferencia}
                                    credito={credito}
                                    setCredito={setCredito}
                                    vuelto={vuelto}
                                    setVuelto={setVuelto}
                                    number={number}
                                    delItemPedido={delItemPedido}
                                    setDescuento={setDescuento}
                                    setDescuentoUnitario={setDescuentoUnitario}
                                    setDescuentoTotal={setDescuentoTotal}
                                    setCantidadCarrito={setCantidadCarrito}
                                    toggleAddPersona={toggleAddPersona}
                                    setToggleAddPersona={setToggleAddPersona}
                                    getPersona={getPersona}
                                    personas={personas}
                                    setPersonas={setPersonas}
                                    ModaladdproductocarritoToggle={
                                        ModaladdproductocarritoToggle
                                    }
                                    toggleImprimirTicket={toggleImprimirTicket}
                                    del_pedido={del_pedido}
                                    facturar_pedido={facturar_pedido}
                                    inputmodaladdpersonacarritoref={
                                        inputmodaladdpersonacarritoref
                                    }
                                    tbodypersoInterref={tbodypersoInterref}
                                    countListPersoInter={countListPersoInter}
                                    entregarVuelto={entregarVuelto}
                                    setPersonaFast={setPersonaFast}
                                    clienteInpidentificacion={
                                        clienteInpidentificacion
                                    }
                                    setclienteInpidentificacion={
                                        setclienteInpidentificacion
                                    }
                                    clienteInpnombre={clienteInpnombre}
                                    setclienteInpnombre={setclienteInpnombre}
                                    clienteInptelefono={clienteInptelefono}
                                    setclienteInptelefono={
                                        setclienteInptelefono
                                    }
                                    clienteInpdireccion={clienteInpdireccion}
                                    setclienteInpdireccion={
                                        setclienteInpdireccion
                                    }
                                    viewReportPedido={viewReportPedido}
                                    autoCorrector={autoCorrector}
                                    setautoCorrector={setautoCorrector}
                                    getDebito={getDebito}
                                    getCredito={getCredito}
                                    getTransferencia={getTransferencia}
                                    getEfectivo={getEfectivo}
                                    onClickEditPedido={onClickEditPedido}
                                    setBiopago={setBiopago}
                                    biopago={biopago}
                                    getBio={getBio}
                                    facturar_e_imprimir={facturar_e_imprimir}
                                    moneda={moneda}
                                    dolar={dolar}
                                    peso={peso}
                                    auth={auth}
                                    togglereferenciapago={togglereferenciapago}
                                    tipo_referenciapago={tipo_referenciapago}
                                    settipo_referenciapago={
                                        settipo_referenciapago
                                    }
                                    descripcion_referenciapago={
                                        descripcion_referenciapago
                                    }
                                    setdescripcion_referenciapago={
                                        setdescripcion_referenciapago
                                    }
                                    monto_referenciapago={monto_referenciapago}
                                    setmonto_referenciapago={
                                        setmonto_referenciapago
                                    }
                                    cedula_referenciapago={
                                        cedula_referenciapago
                                    }
                                    setcedula_referenciapago={
                                        setcedula_referenciapago
                                    }
                                    telefono_referenciapago={
                                        telefono_referenciapago
                                    }
                                    settelefono_referenciapago={
                                        settelefono_referenciapago
                                    }
                                    banco_referenciapago={banco_referenciapago}
                                    setbanco_referenciapago={
                                        setbanco_referenciapago
                                    }
                                    refaddfast={refaddfast}
                                    inputqinterno={inputqinterno}
                                    setinputqinterno={setinputqinterno}
                                    productoSelectinternouno={
                                        productoSelectinternouno
                                    }
                                    addCarritoRequestInterno={
                                        addCarritoRequestInterno
                                    }
                                    cantidad={cantidad}
                                    devolucionTipo={devolucionTipo}
                                    setdevolucionTipo={setdevolucionTipo}
                                    setToggleAddPersonaFun={
                                        setToggleAddPersonaFun
                                    }
                                    transferirpedidoa={transferirpedidoa}
                                    settransferirpedidoa={settransferirpedidoa}
                                    sucursalesCentral={sucursalesCentral}
                                    setexportpedido={setexportpedido}
                                    getSucursales={getSucursales}
                                    setView={setView}
                                    orderColumn={orderColumn}
                                    setOrderColumn={setOrderColumn}
                                    orderBy={orderBy}
                                    setOrderBy={setOrderBy}
                                    posTransaccionesPorPedido={posTransaccionesPorPedido}
                                    showModalPosDebito={showModalPosDebito}
                                />
                            )}
                        </Pagar>
                    ) : null}
                    {view == "panelcentrodeacopio" ? (
                        <Panelcentrodeacopio
                            guardarDeSucursalEnCentral={
                                guardarDeSucursalEnCentral
                            }
                            autovincularSucursalCentral={
                                autovincularSucursalCentral
                            }
                            datainventarioSucursalFromCentralcopy={
                                datainventarioSucursalFromCentralcopy
                            }
                            setdatainventarioSucursalFromCentralcopy={
                                setdatainventarioSucursalFromCentralcopy
                            }
                            estadisticasinventarioSucursalFromCentral={
                                estadisticasinventarioSucursalFromCentral
                            }
                            uniqueproductofastshowbyid={
                                uniqueproductofastshowbyid
                            }
                            getUniqueProductoById={getUniqueProductoById}
                            showdatafastproductobyid={showdatafastproductobyid}
                            setshowdatafastproductobyid={
                                setshowdatafastproductobyid
                            }
                            puedoconsultarproductosinsucursalfromcentral={
                                puedoconsultarproductosinsucursalfromcentral
                            }
                            getProductos={getProductos}
                            productos={productos}
                            idselectproductoinsucursalforvicular={
                                idselectproductoinsucursalforvicular
                            }
                            linkproductocentralsucursal={
                                linkproductocentralsucursal
                            }
                            openVincularSucursalwithCentral={
                                openVincularSucursalwithCentral
                            }
                            modalmovilRef={modalmovilRef}
                            inputbuscarcentralforvincular={
                                inputbuscarcentralforvincular
                            }
                            modalmovilx={modalmovilx}
                            modalmovily={modalmovily}
                            modalmovilshow={modalmovilshow}
                            setmodalmovilshow={setmodalmovilshow}
                            getTareasCentral={getTareasCentral}
                            tareasCentral={tareasCentral}
                            getSucursales={getSucursales}
                            sucursalesCentral={sucursalesCentral}
                            setselectSucursalCentral={setselectSucursalCentral}
                            selectSucursalCentral={selectSucursalCentral}
                            getInventarioSucursalFromCentral={
                                getInventarioSucursalFromCentral
                            }
                            setInventarioSucursalFromCentral={
                                setInventarioSucursalFromCentral
                            }
                            subviewpanelcentroacopio={subviewpanelcentroacopio}
                            setsubviewpanelcentroacopio={
                                setsubviewpanelcentroacopio
                            }
                            parametrosConsultaFromsucursalToCentral={
                                parametrosConsultaFromsucursalToCentral
                            }
                            setparametrosConsultaFromsucursalToCentral={
                                setparametrosConsultaFromsucursalToCentral
                            }
                            onchangeparametrosConsultaFromsucursalToCentral={
                                onchangeparametrosConsultaFromsucursalToCentral
                            }
                            fallaspanelcentroacopio={fallaspanelcentroacopio}
                            estadisticaspanelcentroacopio={
                                estadisticaspanelcentroacopio
                            }
                            gastospanelcentroacopio={gastospanelcentroacopio}
                            cierrespanelcentroacopio={cierrespanelcentroacopio}
                            diadeventapanelcentroacopio={
                                diadeventapanelcentroacopio
                            }
                            tasaventapanelcentroacopio={
                                tasaventapanelcentroacopio
                            }
                            setchangetasasucursal={setchangetasasucursal}
                            inventarioSucursalFromCentral={
                                inventarioSucursalFromCentral
                            }
                            categorias={categorias}
                            proveedoresList={proveedoresList}
                            changeInventarioFromSucursalCentral={
                                changeInventarioFromSucursalCentral
                            }
                            setCambiosInventarioSucursal={
                                setCambiosInventarioSucursal
                            }
                            number={number}
                        />
                    ) : null}
                    {view == "credito" ? (
                        <Credito
                            getDeudores={getDeudores}
                            getDeudor={getDeudor}
                            limitdeudores={limitdeudores}
                            setlimitdeudores={setlimitdeudores}
                            moneda={moneda}
                            orderbycolumdeudores={orderbycolumdeudores}
                            setorderbycolumdeudores={setorderbycolumdeudores}
                            orderbyorderdeudores={orderbyorderdeudores}
                            setorderbyorderdeudores={setorderbyorderdeudores}
                            printCreditos={printCreditos}
                            onchangecaja={onchangecaja}
                            qDeudores={qDeudores}
                            deudoresList={deudoresList}
                            tipo_pago_deudor={tipo_pago_deudor}
                            monto_pago_deudor={monto_pago_deudor}
                            selectDeudor={selectDeudor}
                            setSelectDeudor={setSelectDeudor}
                            setPagoCredito={setPagoCredito}
                            detallesDeudor={detallesDeudor}
                            onClickEditPedido={onClickEditPedido}
                            onCLickDelPedido={onCLickDelPedido}
                            onlyVueltos={onlyVueltos}
                            setOnlyVueltos={setOnlyVueltos}
                            qBuscarCliente={qBuscarCliente}
                            setqBuscarCliente={setqBuscarCliente}
                            clientesCrud={clientesCrud}
                            setindexSelectCliente={setindexSelectCliente}
                            indexSelectCliente={indexSelectCliente}
                            setClienteCrud={setClienteCrud}
                            delCliente={delCliente}
                            clienteInpidentificacion={clienteInpidentificacion}
                            setclienteInpidentificacion={
                                setclienteInpidentificacion
                            }
                            clienteInpnombre={clienteInpnombre}
                            setclienteInpnombre={setclienteInpnombre}
                            clienteInpcorreo={clienteInpcorreo}
                            setclienteInpcorreo={setclienteInpcorreo}
                            clienteInpdireccion={clienteInpdireccion}
                            setclienteInpdireccion={setclienteInpdireccion}
                            clienteInptelefono={clienteInptelefono}
                            setclienteInptelefono={setclienteInptelefono}
                            clienteInpestado={clienteInpestado}
                            setclienteInpestado={setclienteInpestado}
                            clienteInpciudad={clienteInpciudad}
                            setclienteInpciudad={setclienteInpciudad}
                            sumPedidos={sumPedidos}
                            sumPedidosArr={sumPedidosArr}
                            setsumPedidosArr={setsumPedidosArr}
                        />
                    ) : null}

                    {view == "configuracion" ? (
                        <Configuracion
                            subViewConfig={subViewConfig}
                            setsubViewConfig={setsubViewConfig}
                            categorias={categorias}
                            addNewCategorias={addNewCategorias}
                            categoriasDescripcion={categoriasDescripcion}
                            setcategoriasDescripcion={setcategoriasDescripcion}
                            indexSelectCategorias={indexSelectCategorias}
                            setIndexSelectCategorias={setIndexSelectCategorias}
                            qBuscarCategorias={qBuscarCategorias}
                            setQBuscarCategorias={setQBuscarCategorias}
                            delCategorias={delCategorias}
                            addNewUsuario={addNewUsuario}
                            usuarioNombre={usuarioNombre}
                            setusuarioNombre={setusuarioNombre}
                            usuarioUsuario={usuarioUsuario}
                            setusuarioUsuario={setusuarioUsuario}
                            usuarioRole={usuarioRole}
                            setusuarioRole={setusuarioRole}
                            usuarioClave={usuarioClave}
                            setusuarioClave={setusuarioClave}
                            usuarioIpPinpad={usuarioIpPinpad}
                            setusuarioIpPinpad={setusuarioIpPinpad}
                            indexSelectUsuarios={indexSelectUsuarios}
                            setIndexSelectUsuarios={setIndexSelectUsuarios}
                            qBuscarUsuario={qBuscarUsuario}
                            setQBuscarUsuario={setQBuscarUsuario}
                            delUsuario={delUsuario}
                            usuariosData={usuariosData}
                        />
                    ) : null}

                    {view == "garantias" ? (
                        <GarantiaModule
                            user={user}
                            notificar={notificar}
                            setLoading={setLoading}
                        />
                    ) : null}
                    {view == "inventario-ciclico" ? (
                        <InventarioCiclicoModule
                            user={user}
                            notificar={notificar}
                            setLoading={setLoading}
                        />
                    ) : null}

                    {view == "presupuestos" ? (
                        <PresupuestoMain
                            user={user}
                            productos={productos}
                            moneda={moneda}
                            inputbusquedaProductosref={
                                inputbusquedaProductosref
                            }
                            presupuestocarrito={presupuestocarrito}
                            getProductos={getProductos}
                            showOptionQMain={showOptionQMain}
                            setshowOptionQMain={setshowOptionQMain}
                            num={num}
                            setNum={setNum}
                            itemCero={itemCero}
                            setpresupuestocarrito={setpresupuestocarrito}
                            toggleImprimirTicket={toggleImprimirTicket}
                            delitempresupuestocarrito={
                                delitempresupuestocarrito
                            }
                            editCantidadPresupuestoCarrito={
                                editCantidadPresupuestoCarrito
                            }
                            sumsubtotalespresupuesto={sumsubtotalespresupuesto}
                            auth={auth}
                            addCarrito={addCarritoPresupuesto}
                            clickSetOrderColumn={clickSetOrderColumn}
                            orderColumn={orderColumn}
                            orderBy={orderBy}
                            counterListProductos={counterListProductos}
                            setCounterListProductos={setCounterListProductos}
                            tbodyproductosref={tbodyproductosref}
                            focusCtMain={focusCtMain}
                            selectProductoFast={selectProductoFast}
                            setpresupuestocarritotopedido={
                                setpresupuestocarritotopedido
                            }
                            openBarcodeScan={openBarcodeScan}
                            number={number}
                            dolar={dolar}
                        />
                    ) : null}

                    {/* Modal de Referencia de Pago - Overlay */}
                    {togglereferenciapago && (
                        <ModalRefPago
                            cedula_referenciapago={cedula_referenciapago}
                            setcedula_referenciapago={setcedula_referenciapago}
                            telefono_referenciapago={telefono_referenciapago}
                            settelefono_referenciapago={
                                settelefono_referenciapago
                            }
                            bancos={bancos}
                            addRefPago={addRefPago}
                            descripcion_referenciapago={
                                descripcion_referenciapago
                            }
                            setdescripcion_referenciapago={
                                setdescripcion_referenciapago
                            }
                            banco_referenciapago={banco_referenciapago}
                            setbanco_referenciapago={setbanco_referenciapago}
                            monto_referenciapago={monto_referenciapago}
                            setmonto_referenciapago={setmonto_referenciapago}
                            tipo_referenciapago={tipo_referenciapago}
                            settipo_referenciapago={settipo_referenciapago}
                            transferencia={transferencia}
                            dolar={dolar}
                            number={number}
                            montoTraido={monto_referenciapago}
                            tipoTraido={tipo_referenciapago}
                            pedidoData={pedidoData}
                        />
                    )}
                    
                    {/* Modal POS Débito Físico - Múltiples Transacciones */}
                    {showModalPosDebito && (
                        <div 
                            className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                            onKeyDown={(e) => {
                                // Bloquear todos los atajos de teclado excepto Escape
                                e.stopPropagation();
                                
                                if (e.key === 'Escape' && !posLoading) {
                                    e.preventDefault();
                                    setShowModalPosDebito(false);
                                    setPosCedulaTitular("");
                                    setPosTipoCuenta("CORRIENTE");
                                    setPosPendingCallback(null);
                                    setPosRespuesta(null);
                                }
                                
                                // Bloquear Ctrl+Enter, F keys, etc. mientras el modal está abierto
                                if (e.ctrlKey || e.altKey || e.key.startsWith('F')) {
                                    e.preventDefault();
                                }
                            }}
                            onKeyUp={(e) => e.stopPropagation()}
                            onKeyPress={(e) => e.stopPropagation()}
                            tabIndex={-1}
                        >
                            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
                                <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex-shrink-0">
                                    <h3 className="text-lg font-semibold flex items-center gap-2">
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                        Punto de Venta - Débito
                                    </h3>
                                    <p className="text-sm text-blue-100 mt-1">Pedido #{pedidoData.id} - Total: Bs {parseFloat(posMontoTotalOriginal || 0).toFixed(2)}</p>
                                </div>
                                <div className="p-6 space-y-4 overflow-y-auto flex-1">
                                    {/* Resumen de progreso */}
                                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-blue-700">Total a pagar:</span>
                                            <span className="font-semibold text-blue-900">Bs {parseFloat(posMontoTotalOriginal || 0).toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm mt-1">
                                            <span className="text-green-700">Aprobado:</span>
                                            <span className="font-semibold text-green-900">Bs {posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0).toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm mt-1 pt-1 border-t border-blue-200">
                                            <span className="text-gray-700">Restante:</span>
                                            <span className={`font-bold ${parseFloat(posMontoDebito || 0) > 0 ? 'text-orange-600' : 'text-green-600'}`}>
                                                Bs {parseFloat(posMontoDebito || 0).toFixed(2)}
                                            </span>
                                        </div>
                                        {/* Barra de progreso */}
                                        <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div 
                                                className="h-full bg-green-500 transition-all duration-300"
                                                style={{ 
                                                    width: `${Math.min(100, (posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0) / (parseFloat(posMontoTotalOriginal) || 1)) * 100)}%` 
                                                }}
                                            />
                                        </div>
                                    </div>

                                    {/* Lista de transacciones aprobadas */}
                                    {posTransaccionesAprobadas.length > 0 && (
                                        <div className="border border-green-200 rounded-lg overflow-hidden">
                                            <div className="bg-green-50 px-3 py-2 border-b border-green-200">
                                                <span className="text-sm font-medium text-green-800">
                                                    Transacciones Aprobadas ({posTransaccionesAprobadas.length})
                                                </span>
                                            </div>
                                            <div className="divide-y divide-green-100 max-h-32 overflow-y-auto">
                                                {posTransaccionesAprobadas.map((trans, index) => (
                                                    <div key={index} className="flex items-center justify-between px-3 py-2 bg-white hover:bg-green-50">
                                                        <div className="flex-1">
                                                            <span className="text-sm font-medium text-gray-900">Bs {trans.monto.toFixed(2)}</span>
                                                            <span className="text-xs text-gray-500 ml-2">CI: {trans.cedula}</span>
                                                            <span className="text-xs text-green-600 ml-2">Ref: {trans.refCorta}</span>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => eliminarTransaccionPOS(index)}
                                                            disabled={posLoading}
                                                            className="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded disabled:opacity-50"
                                                            title="Eliminar transacción"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Formulario para nueva transacción (siempre visible si hay monto original y no se ha cubierto el total) */}
                                    {(parseFloat(posMontoTotalOriginal || 0) > 0 && posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0) < parseFloat(posMontoTotalOriginal || 0)) && (
                                        <>
                                            <div className="border-t pt-4">
                                                <p className="text-sm font-medium text-gray-700 mb-3">
                                                    {posTransaccionesAprobadas.length > 0 ? 'Agregar otra tarjeta:' : 'Datos de la tarjeta:'}
                                                </p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Cédula del Titular
                                                </label>
                                                <input
                                                    id="pos-cedula-input"
                                                    type="text"
                                                    value={posCedulaTitular}
                                                    onChange={(e) => setPosCedulaTitular(e.target.value.replace(/\D/g, ''))}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                            const montoActual = parseFloat(posMontoDebito) || 0;
                                                            if (posCedulaTitular && !posLoading && montoActual > 0) {
                                                                enviarSolicitudPosDebito();
                                                            }
                                                        }
                                                    }}
                                                    placeholder="Ej: 12345678"
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                    disabled={posLoading}
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Tipo de Cuenta
                                                    </label>
                                                    <select
                                                        value={posTipoCuenta}
                                                        onChange={(e) => setPosTipoCuenta(e.target.value)}
                                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        disabled={posLoading}
                                                    >
                                                        <option value="CORRIENTE">CORRIENTE</option>
                                                        <option value="AHORROS">AHORROS</option>
                                                        <option value="CREDITO">CREDITO</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Monto (Bs)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={posMontoDebito}
                                                        onChange={(e) => setPosMontoDebito(e.target.value.replace(/[^0-9.,]/g, ''))}
                                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 font-semibold text-lg"
                                                        disabled={posLoading}
                                                    />
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    {/* Mensaje cuando el total está cubierto */}
                                    {posTransaccionesAprobadas.length > 0 && posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0) >= parseFloat(posMontoTotalOriginal || 0) && (
                                        <div className="bg-green-100 border border-green-300 rounded-lg p-4 text-center">
                                            <svg className="w-12 h-12 text-green-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p className="text-green-800 font-semibold">Total cubierto</p>
                                            <p className="text-green-600 text-sm">El pago se procesará automáticamente</p>
                                        </div>
                                    )}

                                    {/* Área de respuesta del POS */}
                                    {posRespuesta && (
                                        <div className={`p-4 rounded-lg text-center font-semibold ${posRespuesta.exito ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300'}`}>
                                            <p className="text-lg">{posRespuesta.mensaje}</p>
                                            {!posRespuesta.exito && (
                                                <p className="text-sm mt-2 font-normal">Puede reintentar o usar referencia manual</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-3 px-6 py-4 bg-gray-50 rounded-b-lg flex-shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowModalPosDebito(false);
                                            setPosCedulaTitular("");
                                            setPosTipoCuenta("CORRIENTE");
                                            setPosPendingCallback(null);
                                            setPosRespuesta(null);
                                            setPosTransaccionesAprobadas([]);
                                            setPosMontoTotalOriginal("");
                                        }}
                                        disabled={posLoading}
                                        className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors disabled:opacity-50"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowModalPosDebito(false);
                                            setPosCedulaTitular("");
                                            setPosTipoCuenta("CORRIENTE");
                                            setPosPendingCallback(null);
                                            setPosRespuesta(null);
                                            setPosTransaccionesAprobadas([]);
                                            setPosMontoTotalOriginal("");
                                            setForzarReferenciaManual(true);
                                            // Hacer foco en el campo de referencia después de cerrar el modal
                                            setTimeout(() => {
                                                const debitoRefInput = document.querySelector('[data-ref-input="true"]');
                                                if (debitoRefInput) {
                                                    debitoRefInput.focus();
                                                }
                                            }, 100);
                                        }}
                                        disabled={posLoading}
                                        className="px-4 py-2 border border-orange-400 rounded-lg text-orange-600 hover:bg-orange-50 transition-colors disabled:opacity-50"
                                    >
                                        Ref. Manual
                                    </button>
                                    {/* Botón Enviar al POS (solo si no se ha cubierto el total) */}
                                    {posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0) < parseFloat(posMontoTotalOriginal || 0) && (
                                        <button
                                            type="button"
                                            onClick={enviarSolicitudPosDebito}
                                            disabled={posLoading || !posCedulaTitular}
                                            className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                        >
                                            {posLoading ? (
                                                <>
                                                    <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                    </svg>
                                                    Procesando...
                                                </>
                                            ) : (
                                                <>
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    {posTransaccionesAprobadas.length > 0 ? 'Agregar Tarjeta' : 'Enviar al POS'}
                                                </>
                                            )}
                                        </button>
                                    )}
                                    {/* Botón Finalizar (solo si hay transacciones aprobadas y NO se cubrió el total) */}
                                    {posTransaccionesAprobadas.length > 0 && posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0) < parseFloat(posMontoTotalOriginal || 0) && (
                                        <button
                                            type="button"
                                            onClick={() => finalizarTransaccionesPOS(true)}
                                            disabled={posLoading}
                                            className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                            title="Aplicar monto parcial al débito. Deberá completar con otros métodos de pago."
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                            Aplicar Parcial (Bs {posTransaccionesAprobadas.reduce((sum, t) => sum + t.monto, 0).toFixed(2)})
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
