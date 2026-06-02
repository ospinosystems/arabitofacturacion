import { useHotkeys } from "react-hotkeys-hook";

import ModalAddCarrito from "../components/modaladdcarrito";
import SeleccionarMain from "./seleccionarMain";
import { useEffect } from "react";
export default function Seleccionar({
    openBarcodeScan,
    productos,
    selectItem,
    setPresupuesto,
    dolar,
    setSelectItem,
    cantidad,
    setCantidad,
    numero_factura,
    setNumero_factura,
    pedidoList,
    setFalla,
    number,
    moneda,
    inputCantidadCarritoref,
    addCarritoRequest,
    inputbusquedaProductosref,
    presupuestocarrito,
    getProductos,
    showOptionQMain,
    setshowOptionQMain,
    num,
    setNum,
    itemCero,
    setpresupuestocarrito,
    toggleImprimirTicket,
    delitempresupuestocarrito,
    sumsubtotalespresupuesto,
    auth,
    addCarrito,
    clickSetOrderColumn,
    orderColumn,
    orderBy,
    counterListProductos,
    setCounterListProductos,
    tbodyproductosref,
    focusCtMain,
    selectProductoFast,
    setpresupuestocarritotopedido,
    getPedido,
    getPedidos,
    setView,
    permisoExecuteEnter,

    getPedidosList,
    user,
    
}){
    
    //esc
   /*  useHotkeys(
        "esc",
        () => {
   
                inputbusquedaProductosref.current.value = "";
                inputbusquedaProductosref.current.focus();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        []
    ); */

    //f1
    useHotkeys(
        "f1",
        () => {
            if (selectItem === null) {
                getPedido("ultimo", () => {
                    setView("pagar");
                });
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        []
    ); 
    
    //f2
    useHotkeys(
        "f2",
        () => {
            setView("pedidos");
            getPedidos();
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
        },
        []
    );

    //space
    useHotkeys(
        "space",
        () => {
            if (selectItem !== null) {
                setNumero_factura("nuevo");
            }
        },
        {
            enableOnTags: ["INPUT", "SELECT"],
            filter: false,
        },
        [numero_factura]
    ); 

    //enter
    useHotkeys(
        "enter",
        () => {
            if (permisoExecuteEnter) {
                if (tbodyproductosref) {
                    if (tbodyproductosref.current) {
                        let tr = tbodyproductosref.current.rows[counterListProductos];
                        let index = tr.attributes["data-index"].value;
                        if (productos[index]) {
                            addCarrito(index);
                        }
                    }
                }
            }
        },
        {
            filterPreventDefault: false,
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        []
    );

    console.log(selectItem)
    return (
        <>
        
            {typeof selectItem == "number" ? (
                productos[selectItem] ? (
                    <ModalAddCarrito
                        selectItem={selectItem}
                        setPresupuesto={setPresupuesto}
                        dolar={dolar}
                        producto={productos[selectItem]}
                        setSelectItem={setSelectItem}
                        cantidad={cantidad}
                        setCantidad={setCantidad}
                        numero_factura={numero_factura}
                        setNumero_factura={setNumero_factura}
                        pedidoList={pedidoList}
                        setFalla={setFalla}
                        number={number}
                        moneda={moneda}
                        inputCantidadCarritoref={inputCantidadCarritoref}
                        addCarritoRequest={addCarritoRequest}
                        permisoExecuteEnter ={permisoExecuteEnter}
                        getPedidosList={getPedidosList}
                    />
                ) : null
            ) : 
                <SeleccionarMain
                    openBarcodeScan={openBarcodeScan}
                    user={user}
                    productos={productos}
                    selectItem={selectItem}
                    setPresupuesto={setPresupuesto}
                    dolar={dolar}
                    setSelectItem={setSelectItem}
                    cantidad={cantidad}
                    setCantidad={setCantidad}
                    numero_factura={numero_factura}
                    setNumero_factura={setNumero_factura}
                    pedidoList={pedidoList}
                    setFalla={setFalla}
                    number={number}
                    moneda={moneda}
                    inputCantidadCarritoref={inputCantidadCarritoref}
                    addCarritoRequest={addCarritoRequest}
                    inputbusquedaProductosref={inputbusquedaProductosref}
                    presupuestocarrito={presupuestocarrito}
                    getProductos={getProductos}
                    showOptionQMain={showOptionQMain}
                    setshowOptionQMain={setshowOptionQMain}
                    num={num}
                    setNum={setNum}
                    itemCero={itemCero}
                    setpresupuestocarrito={setpresupuestocarrito}
                    toggleImprimirTicket={toggleImprimirTicket}
                    delitempresupuestocarrito={delitempresupuestocarrito}
                    sumsubtotalespresupuesto={sumsubtotalespresupuesto}
                    auth={auth}
                    addCarrito={addCarrito}
                    clickSetOrderColumn={clickSetOrderColumn}
                    orderColumn={orderColumn}
                    orderBy={orderBy}
                    counterListProductos={counterListProductos}
                    setCounterListProductos={setCounterListProductos}
                    tbodyproductosref={tbodyproductosref}
                    focusCtMain={focusCtMain}
                    selectProductoFast={selectProductoFast}
                    setpresupuestocarritotopedido={setpresupuestocarritotopedido}
                />
            }
            
            
        </>
    )
}