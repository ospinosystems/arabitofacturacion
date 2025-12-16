import React, { useState, useEffect } from 'react';
import Clientes from '../components/clientes';
import db from '../database/database';


function Credito({
  limitdeudores,
  setlimitdeudores,

  orderbycolumdeudores,
  setorderbycolumdeudores,
  orderbyorderdeudores,
  setorderbyorderdeudores,

  onchangecaja,

  qDeudores,
  deudoresList,

  selectDeudor,
  setSelectDeudor,

  tipo_pago_deudor,
  monto_pago_deudor,
  setPagoCredito,
  onClickEditPedido,
  detallesDeudor,
  onlyVueltos,
  setOnlyVueltos,

  sumPedidos,
  sumPedidosArr,
  setsumPedidosArr,
  printCreditos,
  moneda,
  getDeudores,
  getDeudor,

}) {

  // State para controlar el bloqueo de la sección de pago
  const [creditsUpdated, setCreditsUpdated] = useState(false);
  const [updatingCredits, setUpdatingCredits] = useState(false);
  const [updateMessage, setUpdateMessage] = useState('');
  const [updatedClients, setUpdatedClients] = useState(new Set()); // Mantener registro de clientes actualizados

  // Función para actualizar los créditos del cliente

  useEffect(() => {
    if (selectDeudor == null) {
        getDeudores();
    } else {
        getDeudor();
    }
}, [selectDeudor]);
  const handleUpdateCredits = async () => {
    // Debug: Verificar valores
    
    // Validaciones específicas con mensajes detallados
    if (selectDeudor === null || selectDeudor === undefined) {
      alert('Debe seleccionar un cliente primero (no hay cliente seleccionado)');
      return;
    }
    
    if (!deudoresList || deudoresList.length === 0) {
      alert('No hay lista de clientes disponible. Por favor, busque clientes primero.');
      return;
    }
    
    if (!deudoresList[selectDeudor]) {
      alert(`Cliente en posición ${selectDeudor} no encontrado en la lista. Total clientes: ${deudoresList.length}`);
      return;
    }

    const cliente = deudoresList[selectDeudor];
    
    if (window.confirm(`¿Está seguro de que desea actualizar todos los pedidos de crédito del cliente ${cliente.nombre}?`)) {
      setUpdatingCredits(true);
      setUpdateMessage('Actualizando créditos...');
      
      try {
        const response = await db.updateCreditOrders({ id_cliente: cliente.id });
        
        if (response.data.estado) {
          setUpdateMessage(`✅ ${response.data.msj}`);
          setCreditsUpdated(true);
          
          // Agregar cliente al registro de actualizados
          setUpdatedClients(prev => new Set([...prev, cliente.id]));
          
          // Recargar los datos del deudor para mostrar los cambios
          if (typeof getDeudores === 'function') {
            getDeudores();
          }
          
          // Recargar los detalles del deudor actual
          if (typeof getDeudor === 'function') {
            getDeudor();
          }
        } else {
          setUpdateMessage(`❌ ${response.data.msj}`);
        }
      } catch (error) {
        console.error('Error updating credits:', error);
        setUpdateMessage('❌ Error al actualizar los créditos');
      } finally {
        setUpdatingCredits(false);
      }
    }
  };

  return (
    <div className="container"> 
    
      <div className="row align-items-center mb-3">
        <div className="col">
          <h3 className="mb-0">Cuentas por cobrar</h3>
          <small className="text-muted d-block">Gestión de créditos y abonos por cliente</small>
          <small className="text-muted d-block">Nota: los créditos están sujetos a cambios de acuerdo a variaciones de precio del producto.</small>
        </div>
        <div className="col text-right">
          <button className="btn btn-outline-secondary mr-2" title="Imprimir resumen de créditos" onClick={printCreditos}>
            <i className="fa fa-print"></i> Imprimir
          </button>
          <button className="btn btn-outline-success" onClick={()=>{
            let num = window.prompt("Cantidad de resultados a mostrar", limitdeudores)

            if (num) {
              setlimitdeudores(num)
            }
          }}>Resultados: {limitdeudores}</button>
        </div>
      </div> 

      {
        selectDeudor===null?
        <div>
          <form onSubmit={getDeudores} className="mb-3">
            <input
              type="text"
              className="form-control"
              placeholder='Buscar cliente por nombre o identificación...'
              value={qDeudores}
              name="qDeudores"
              onChange={onchangecaja}
            />
          </form>
          <table className="table table-hoverable">
            <thead>
              <tr>
                <th className="text-center">Cliente</th>
                <th
                  className="text-center pointer hover"
                  title="Ordenar por fecha de vencimiento"
                  onClick={()=>orderbycolumdeudores=="vence"?setorderbyorderdeudores(orderbyorderdeudores=="desc"?"asc":"desc"):setorderbycolumdeudores("vence")}
                >
                  Vence
                </th>
                <th
                  className="text-right pointer hover"
                  title="Ordenar por saldo pendiente"
                  onClick={()=>orderbycolumdeudores=="saldo"?setorderbyorderdeudores(orderbyorderdeudores=="desc"?"asc":"desc"):setorderbycolumdeudores("saldo")}
                >
                  Saldo
                </th>
              </tr>
            </thead>

            <tbody>
              {deudoresList.length?deudoresList.map((e,i)=>
                e?
                <tr key={e.id} className="text-center pointer" onClick={()=>{
                      setOnlyVueltos(0)
                      setSelectDeudor(i)
                      setsumPedidosArr([])
                      

                      // Verificar si este cliente ya fue actualizado
                      const clienteYaActualizado = updatedClients.has(e.id);
                      setCreditsUpdated(clienteYaActualizado);

                    }}>
                  <td>
                    <div className="font-weight-bold">{e.nombre}</div>
                    <small className="text-muted">{e.identificacion} · ID {e.id}</small>
                  </td>

                  <td>{e.vence} ({e.dias} días)</td>
                  <td  className={(e.saldo>0?"text-success":"text-danger")+(" h2 text-right")}>
                    <button className={("btn ")+(e.saldo<0?"btn-outline-danger":"btn-outline-success")}>{moneda(e.saldo)}</button>
                  </td>
                </tr>
                :null
              ):null}
            </tbody>

          </table>
            {!deudoresList.length ? <div className='h3 text-center text-dark mt-2'><i>¡Sin resultados!</i></div> : null}
        </div>:
        <div className="p-4">
          <div className="d-flex justify-content-between align-items-center mb-2">
            <button
              type="button"
              className="btn btn-link text-danger p-0"
              onClick={()=>{
                setSelectDeudor(null)
                setOnlyVueltos(0)
                setCreditsUpdated(false)
                setUpdateMessage('')
              }}
            >
              <i className="fa fa-arrow-left"></i> Volver a la lista de clientes
            </button>
            {deudoresList[selectDeudor] && (
              <div className="text-right">
                <div className="h5 mb-0">{deudoresList[selectDeudor].nombre}</div>
                <small className="text-muted">{deudoresList[selectDeudor].identificacion}</small>
              </div>
            )}
          </div>
          <hr/>

          {/* Sección de actualización de créditos */}
          {selectDeudor !== null && deudoresList && deudoresList[selectDeudor] && (
            <div className="mb-4 p-3 border rounded bg-light">
              <div className="row align-items-center">
                <div className="col-md-8">
                  <h5 className="mb-2">
                    <i className="fa fa-refresh text-primary"></i> 
                    Actualización de Créditos
                  </h5>
                  <p className="mb-1 text-muted">
                    Cliente: <strong>{deudoresList[selectDeudor]?.nombre}</strong>
                  </p>
                  {updateMessage && (
                    <div className={`alert ${updateMessage.includes('✅') ? 'alert-success' : updateMessage.includes('❌') ? 'alert-danger' : 'alert-info'} py-2`}>
                      {updateMessage}
                    </div>
                  )}
                </div>
                <div className="col-md-4 text-right">
                  <button 
                    className={`btn ${creditsUpdated ? 'btn-success' : 'btn-warning'} btn-lg`}
                    onClick={handleUpdateCredits}
                    disabled={updatingCredits}
                  >
                    {updatingCredits ? (
                      <>
                        <i className="fa fa-spinner fa-spin"></i> Actualizando...
                      </>
                    ) : creditsUpdated ? (
                      <>
                        <i className="fa fa-check"></i> Créditos Actualizados
                      </>
                    ) : (
                      <>
                        <i className="fa fa-refresh"></i> Actualizar Créditos
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>
          )}
          
          <div>
            <table className="table table-striped">
              <thead>
                  <tr className="">
                    {detallesDeudor["pedido_total"]?
                    <>
                      <th>
                        {deudoresList[selectDeudor]?
                        <div className="">
                          <span className="">{deudoresList[selectDeudor].identificacion}</span>
                          <h1 className="">{deudoresList[selectDeudor].nombre}</h1>
                        </div>:null}

                        <div className="mb-2 d-flex align-items-center">
                          <button
                            className="btn btn-sm btn-outline-primary mr-2"
                            onClick={() => {
                              if (detallesDeudor && detallesDeudor["pedido"]) {
                                const allIds = detallesDeudor["pedido"].map(p => p.id);
                                setsumPedidosArr(allIds);
                              }
                            }}
                          >
                            Seleccionar todos
                          </button>
                          <button
                            className="btn btn-sm btn-outline-secondary mr-3"
                            onClick={() => {
                              setsumPedidosArr([]);
                            }}
                          >
                            Limpiar selección
                          </button>

                          
                        </div>
                      </th>

                      <th>
                        <span className="badge badge-info">
                            {sumPedidosArr.length} pedido(s) seleccionado(s)
                        </span>
                        {sumPedidosArr.length ? (
                          <div className="mb-2 d-flex align-items-center flex-column align-items-start">
                            <div className="btn-group btn-group-sm mb-1">
                             {/*  <a
                                className="btn btn-success"
                                target="_blank"
                                href={"/sumpedidos?id="+sumPedidosArr}
                              >
                                Unificar
                              </a> */}
                              {deudoresList[selectDeudor] && creditsUpdated && (
                                <a
                                  className="btn btn-primary"
                                  target="_blank"
                                  href={"/reporteCreditosCliente?id_cliente="+deudoresList[selectDeudor].id+"&ids="+sumPedidosArr.join(",")}
                                >
                                  Créditos seleccionados
                                </a>
                              )}
                            </div>
                            {deudoresList[selectDeudor] && !creditsUpdated && (
                              <small className="text-sinapsis">
                                <i className="fa fa-lock"></i> Actualice los créditos para habilitar el reporte de facturas
                              </small>
                            )}
                          </div>
                        ) : null}
                      </th>


                      {!onlyVueltos?
                        <>
                            <td className="text-right">Balance: <h2 className={(detallesDeudor["pedido_total"]["diferencia"]>0?"text-success":"text-danger")}>{detallesDeudor["pedido_total"]["diferencia"]}</h2></td>
                            <td className="text-right h3 text-danger">{moneda(detallesDeudor["pedido_total"][1])}</td>
                            <td className="text-right h3 text-success">{moneda(detallesDeudor["pedido_total"][0])}</td>
                        </>
                      :null}
                    </>
                    :null}
                  </tr>
                <tr>
                  <th className="" style={{width: '10%'}}>Acciones</th>
                  <th className="text-right" style={{width: '15%'}}>Fecha vence</th>
                  <th style={{width: '25%'}}>Pedido</th>
                  <th style={{width: '25%'}}>Formas de pago</th>
                  <th className="text-right" style={{width: '15%'}}>Crédito</th>
                  <th className="text-right" style={{width: '10%'}}>Abono</th>
                </tr>
              </thead>
              <tbody>

              {/* Sección de pago - BLOQUEADA hasta que se actualicen los créditos */}
              {!onlyVueltos && (
                <tr className={!creditsUpdated ? 'table-secondary' : ''}>
                  <td colSpan="2"></td>
                  <td colSpan="3">
                    {!creditsUpdated ? (
                      <div className="alert alert-warning py-2 mb-0">
                        <i className="fa fa-lock"></i> 
                        Sección bloqueada hasta actualizar créditos
                      </div>
                    ) : (
                      <form onSubmit={setPagoCredito} className="">
                        <div className="form-group">
                          <label htmlFor="">Monto Abono (USD)</label>
                          <input type="number" step="0.01" name="monto_pago_deudor" value={monto_pago_deudor} onChange={onchangecaja} className="form-control" placeholder="0.00"/>
                          <small className="text-muted">El método de pago se selecciona al procesar el pedido</small>
                        </div>
                      </form>
                    )}
                  </td>
                </tr>
              )}

                {
                  detallesDeudor&&detallesDeudor["pedido"]?
                    detallesDeudor["pedido"].map(e=>
                      <tr key={e.id}>
                        <td>
                          {!sumPedidosArr.filter(id_save=>id_save==e.id).length?
                            <button className="btn btn-sm btn-outline-success" data-id={e.id} data-tipo="add" onClick={sumPedidos}>Seleccionar</button>
                            :
                            <button className="btn btn-sm btn-outline-danger" data-id={e.id} data-tipo="del" onClick={sumPedidos}>Quitar</button>
                          }
                        </td>
                        <td className="">
                            <div className="font-weight-bold">{e.fecha_vence}</div>
                            <small className="text-muted">
                              Creado: {e.created_at ? new Date(e.created_at).toLocaleDateString() : 'N/A'}
                            </small>
                        </td>
                        <td className="align-middle">
                          <div className="text-center">
                            <button className="btn btn-secondary btn-sm w-75 mb-1" data-id={e.id} onClick={onClickEditPedido}>
                              Ver pedido #{e.id} <i className="fa fa-eye"></i>
                            </button>

                            <div className="small text-muted">
                              Items: {e.items ? e.items.length : (typeof e.items_count !== 'undefined' ? e.items_count : 0)}
                            </div>
                          </div>
                        </td>
                        <td className="align-middle">
                          {e.pagos.map(ee=><div key={ee.id}>
                            {ee.tipo==1&&ee.monto!=0?<span className="w-50 btn-sm btn-info btn">Trans. {ee.monto}</span>:null}
                            {ee.tipo==2&&ee.monto!=0?<span className="w-50 btn-sm btn-secondary btn">Deb. {ee.monto}</span>:null}
                            {ee.tipo==3&&ee.monto!=0?<span className="w-50 btn-sm btn-success btn">Efec. {ee.monto}</span>:null}
                            {ee.tipo==5&&ee.monto!=0?<span className="w-50 btn-sm btn-info btn">Biopago {ee.monto}</span>:null}
                            {ee.tipo==6&&ee.monto!=0?<span className="w-50 btn-sm btn-danger btn" data-id={e.id} onClick={onClickEditPedido}>Vuel. {ee.monto}</span>:null}
                            {ee.tipo==4&&ee.monto!=0?<span className="w-50 btn-sm btn-warning btn">Cred. {ee.monto}</span>:null}
                          </div>)}
                          {e.entregado.map((ee,i)=><div key={ee.id}>
                            <span className="w-50 btn-sm btn-warning btn">{ee.descripcion} {ee.monto}</span>
                          </div>)}
                        </td>
                        {e.saldoDebe?
                          <>
                            <th className="text-danger h2 text-right align-middle">{moneda(e.saldoDebe)}</th>
                            <td></td>
                            
                          </>:
                          <>
                            <td></td>
                            <th className="text-success h2 text-right align-middle">{moneda(e.saldoAbono)}</th>
                          </>
                        }
                      </tr>
                    )
                  :null
                }
              </tbody>
            </table>
          </div>
        </div>
      }
      
    </div>
  )
}
export default Credito