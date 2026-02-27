import { useEffect, useRef, useState, useCallback } from "react";
import { useHotkeys } from "react-hotkeys-hook";
import { useApp } from '../contexts/AppContext';
import db from "../database/database";
import ModalScanCarnetAprobacion from "./modalScanCarnetAprobacion";

export default function ListProductosInterno({
  setLastDbRequest,
  lastDbRequest,
  openValidationTarea,

  num,
  setNum,
  auth,
  refaddfast,
  setinputqinterno,
  inputqinterno,
  tbodyproducInterref,
  productos,
  countListInter,
  moneda,
  setCountListInter,
  setView,
  permisoExecuteEnter,
  user,
  // Props adicionales para el input de cantidad
  inputCantidadCarritoref,
  setCantidad,
  cantidad,
  number,
  dolar,
  addCarritoRequestInterno,
  setproductoSelectinternouno,
  // Variables necesarias para addCarritoRequestInterno
  devolucionTipo,
  pedidoData,
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
  // Funciones necesarias
  notificar,
  getPedido,
  addNewPedido,
  addNewPedidoFront,
  // Props para navegación de pedidos
  pedidosFast,
  pedidosFrontPendientesList = [],
  onClickEditPedido,
  togglereferenciapago,

  cedula_referenciapago,
  setcedula_referenciapago,
  telefono_referenciapago,
  settelefono_referenciapago,
  // Props para ordenamiento
  orderColumn,
  setOrderColumn,
  orderBy,
  setOrderBy,
  getProductos,
  qProductosMain,
  setQProductosMain,
  // Props para modal de pedido original en devoluciones
  setShowModalPedidoOriginal,
  pedidoOriginalAsignado,
}) {


  useEffect(() => {
    getProductos();
  }, [
      num,
      qProductosMain,
      orderColumn,
      orderBy,
  ]);
  // Usar el context general de la aplicación
  const { setActiveProductCart, activeProductCart, searchCompleted } = useApp();
  
  // Estado local para el producto seleccionado
  const [selectedProduct, setSelectedProduct] = useState(null);
  
  // Estado para manejar qué fila tiene focus
  const [focusedRowIndex, setFocusedRowIndex] = useState(null);
  
  // Estado para controlar cuándo realmente cambió el input
  const [lastInputValue, setLastInputValue] = useState(null);
  
  // Ref para el debounce
  const debounceRef = useRef(null);
  
  // Ref para el debounce del input de búsqueda
  const searchDebounceRef = useRef(null);
  
  // Ref para prevenir repetición de TAB
  const isNavigatingPedido = useRef(false);
  
  // Estados para el modal de carnet de aprobación
  const [showModalCarnet, setShowModalCarnet] = useState(false);
  const [valinputsetclaveadmin, setvalinputsetclaveadmin] = useState("");
  const inputCarnetRef = useRef(null);
  
  // Función para verificar si un producto está en el carrito
  const isProductInCart = (productId) => {
    if (!pedidoData || !pedidoData.items) return false;
    return pedidoData.items.some(item => item.producto && item.producto.id == productId);
  };
  
  // Función para obtener la cantidad de un producto en el carrito
  const getCartQuantity = (productId) => {
    if (!pedidoData || !pedidoData.items) return 0;
    const cartItem = pedidoData.items.find(item => item.producto && item.producto.id == productId);
    return cartItem ? Number(parseFloat(cartItem.cantidad).toFixed(2)) : 0;
  };
  
  // Función para obtener la cantidad a mostrar (carrito o inventario)
  const getDisplayQuantity = (product) => {
    if (isProductInCart(product.id)) {
      return getCartQuantity(product.id);
    }
    return product.cantidad;
  };

  // Función para manejar el click en las columnas y cambiar el ordenamiento
  const handleColumnClick = (column) => {
    if (orderColumn === column) {
      // Si es la misma columna, cambiar dirección
      setOrderBy(orderBy === 'asc' ? 'desc' : 'asc');
    } else {
      // Si es nueva columna, establecer como ascendente por defecto
      setOrderColumn(column);
      setOrderBy('asc');
    }
  };

  // Función para obtener el ícono de ordenamiento
  const getSortIcon = (column) => {
    if (orderColumn !== column) {
      return <span className="text-gray-400">↕</span>;
    }
    return orderBy === 'asc' ? <span className="text-orange-600">↑</span> : <span className="text-orange-600">↓</span>;
  };
  //f1 - Crear nuevo pedido (solo front)
  useHotkeys(
    "f1",
    () => {
      if (typeof addNewPedidoFront === 'function') {
        addNewPedidoFront();
      } else if (typeof addNewPedido === 'function') {
        addNewPedido();
      }
    },
    {
      enableOnTags: ["INPUT", "SELECT"],
      filter: false,
    },
    []
  );

 

  // F3: Abrir vista de pedidos
 

  // Lista combinada: pedidos front (UUID) primero, luego pedidos backend (id numérico) para Tab/Shift+Tab
  const listaPedidosCombinada = [
    ...(Array.isArray(pedidosFrontPendientesList) ? pedidosFrontPendientesList.map((p) => ({ id: p.id, _frontOnly: true })) : []),
    ...(Array.isArray(pedidosFast) ? pedidosFast.map((p) => ({ id: p.id, _frontOnly: false })) : []),
  ];

  // TAB: Seleccionar primer pedido o siguiente (incluye pedidos front y backend)
  useHotkeys(
    "tab",
    (event) => {
      // No ejecutar si el modal de referencia está abierto
      if (togglereferenciapago) {
        return; // Permitir comportamiento por defecto del TAB
      }
      
      // Prevenir ejecución si ya se está navegando
      if (isNavigatingPedido.current) {
        event.preventDefault();
        return;
      }
      
      event.preventDefault();
      if (listaPedidosCombinada.length > 0) {
        if (pedidoData && pedidoData.id != null && pedidoData.id !== "") {
          const currentIndex = listaPedidosCombinada.findIndex((p) => p.id == pedidoData.id);
          
          if (currentIndex >= 0 && currentIndex < listaPedidosCombinada.length - 1) {
            const pedidoSiguiente = listaPedidosCombinada[currentIndex + 1];
            if (pedidoSiguiente && pedidoSiguiente.id != null) {
              isNavigatingPedido.current = true;
              onClickEditPedido(null, pedidoSiguiente.id);
              setTimeout(() => {
                isNavigatingPedido.current = false;
              }, 300);
            }
          }
        } else {
          const primerPedido = listaPedidosCombinada[0];
          if (primerPedido && primerPedido.id != null) {
            isNavigatingPedido.current = true;
            onClickEditPedido(null, primerPedido.id);
            setTimeout(() => {
              isNavigatingPedido.current = false;
            }, 300);
          }
        }
      }
    },
    {
      enableOnTags: ["INPUT", "SELECT"],
      filter: (event) => {
        return event.target !== inputCantidadCarritoref?.current;
      },
    },
    [pedidosFast, pedidosFrontPendientesList, pedidoData, onClickEditPedido, inputCantidadCarritoref, togglereferenciapago]
  );

  // SHIFT+TAB: Seleccionar pedido anterior (incluye pedidos front y backend)
  useHotkeys(
    "shift+tab",
    (event) => {
      if (togglereferenciapago) {
        return;
      }
      
      if (isNavigatingPedido.current) {
        event.preventDefault();
        return;
      }
      
      event.preventDefault();
      if (listaPedidosCombinada.length > 0) {
        if (pedidoData && pedidoData.id != null && pedidoData.id !== "") {
          const currentIndex = listaPedidosCombinada.findIndex((p) => p.id == pedidoData.id);
          
          if (currentIndex > 0) {
            const pedidoAnterior = listaPedidosCombinada[currentIndex - 1];
            if (pedidoAnterior && pedidoAnterior.id != null) {
              isNavigatingPedido.current = true;
              onClickEditPedido(null, pedidoAnterior.id);
              setTimeout(() => {
                isNavigatingPedido.current = false;
              }, 300);
            }
          }
        } else {
          const ultimoPedido = listaPedidosCombinada[listaPedidosCombinada.length - 1];
          if (ultimoPedido && ultimoPedido.id != null) {
            isNavigatingPedido.current = true;
            onClickEditPedido(null, ultimoPedido.id);
            setTimeout(() => {
              isNavigatingPedido.current = false;
            }, 300);
          }
        }
      }
    },
    {
      enableOnTags: ["INPUT", "SELECT"],
      filter: (event) => {
        return event.target !== inputCantidadCarritoref?.current;
      },
    },
    [pedidosFast, pedidosFrontPendientesList, pedidoData, onClickEditPedido, inputCantidadCarritoref, togglereferenciapago]
  );



  //down
  useHotkeys(
    "down",
    (event) => {
      // No ejecutar si el input de cantidad está abierto
      if (selectedProduct) return;

      
      // No ejecutar si el modal de referencia está abierto
      if (togglereferenciapago) {
        return; // Permitir comportamiento por defecto del DOWN
      }
      
      // Si estamos en el input de búsqueda, ir al primer elemento de la lista
      if (event.target === refaddfast?.current && productos.length > 0) {
        event.preventDefault();
        setCountListInter(0);
        if (tbodyproducInterref?.current?.rows[0]) {
          tbodyproducInterref.current.rows[0].focus();
        }
        return;
      }
      
      // Navegación normal en la lista
      let index = countListInter + 1;
      if (tbodyproducInterref) {
        if (tbodyproducInterref.current) {
          if (tbodyproducInterref.current.rows[index]) {
            setCountListInter(index);
            tbodyproducInterref.current.rows[index].focus();
          }
        }
      }
    },
    { enableOnTags: ["INPUT", "SELECT"] },
    [selectedProduct, productos, countListInter, refaddfast, tbodyproducInterref, setCountListInter, togglereferenciapago]
  );

  //up
  useHotkeys(
    "up",
    () => {
      // No ejecutar si el input de cantidad está abierto
      if (selectedProduct) return;
      
      // No ejecutar si el modal de referencia está abierto
      if (togglereferenciapago) {
        return; // Permitir comportamiento por defecto del UP
      }
      
      if (countListInter > 0) {
        let index = countListInter - 1;
        if (tbodyproducInterref) {
          if (tbodyproducInterref.current) {
            if (tbodyproducInterref.current.rows[index]) {
              tbodyproducInterref.current.rows[index].focus();
              setCountListInter(index);
            }
          }
        }
      }
    },
    { enableOnTags: ["INPUT", "SELECT"] },
    [selectedProduct, togglereferenciapago, countListInter, tbodyproducInterref, setCountListInter]
  );

  useEffect(() => {
    setCountListInter(0)
  }, [])

  // Event listener nativo para números
  useEffect(() => {
    const handleKeyDown = (event) => {
      const key = event.key;
      const number = parseInt(key);
      
      // Desactivar si estamos escribiendo en cualquier input o textarea
      if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
        return;
      }
      
      // Verificar si es un número válido (0-9) o punto decimal
      const isValidNumericInput = (key >= '0' && key <= '9') || key === '.' || key === '-';
      
      // Validar que haya un pedido seleccionado antes de permitir usar números
      if (!pedidoData || !pedidoData.id) {
        // Solo mostrar notificación si el usuario está intentando interactuar activamente
        if (isValidNumericInput && !selectedProduct && countListInter >= 0 && productos.length > 0) {
          notificar("No hay pedido seleccionado.", "warning");
        }
        return;
      }
      
      if (isValidNumericInput && !selectedProduct && countListInter >= 0 && productos.length > 0) {
        const product = productos[countListInter];
        if (product) {
          event.preventDefault();
          event.stopPropagation();
          handleProductSelection(product.id);
          // Establecer la cantidad después de un pequeño delay
          setTimeout(() => {
            // Si es un punto, establecer "0." como valor inicial
            if (key === '.') {
              setCantidad('0.');
              setLastInputValue('0.');
            } else {
              // Para números (incluyendo 0), usar el valor directamente
              setCantidad(key === '0' ? '0' : key === '-' ? '-' : number);
              setLastInputValue(key === '0' ? '0' : key === '-' ? '-' : number);
            }
          }, 100);
        }
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [selectedProduct, countListInter, productos, handleProductSelection, setCantidad, setLastInputValue, pedidoData]);

  // Función para manejar la selección de producto
  const handleProductSelection = (productId) => {
    // Validar que haya un pedido seleccionado
    if (!pedidoData || !pedidoData.id) {
      notificar("No hay pedido seleccionado. ", "error");
      return;
    }
    
    const product = productos.find(p => p.id == productId);
    if (product) {
      // Si ya está seleccionado, cerrar el input
      if (selectedProduct && selectedProduct.id == productId) {
        closeQuantityInput();
        return;
      }
      
      // Si hay un producto seleccionado anteriormente, primero cerrar completamente
      if (selectedProduct) {
        setSelectedProduct(null);
        setActiveProductCart(null);
        setLastInputValue(null);
        setproductoSelectinternouno(null);
      }
      
      // Pequeño delay para asegurar que se cierre antes de abrir el nuevo
      setTimeout(() => {
        setSelectedProduct(product);
        setActiveProductCart(productId);
        setCantidad(""); // Input vacío por defecto
        setLastInputValue(null); // Resetear el valor del último input
        
        // Establecer productoSelectinternouno para que funcione con addCarritoRequestInterno
        // Usar id_producto si existe (coincide con items_disponibles de la factura original); si no, id
        const productIdForApi = product.id_producto != null ? product.id_producto : productId;
        setproductoSelectinternouno({
          descripcion: product.descripcion,
          precio: product.precio,
          unidad: product.unidad,
          cantidad: product.cantidad,
          id: productIdForApi,
          id_producto: product.id_producto ?? productId,
          codigo_barras: product.codigo_barras ?? product.codigo_proveedor ?? "",
          codigo_proveedor: product.codigo_proveedor ?? "",
        });
        
        // Enfocar el input de cantidad después de un pequeño delay
        setTimeout(() => {
          if (inputCantidadCarritoref && inputCantidadCarritoref.current) {
            inputCantidadCarritoref.current.focus();
          }
        }, 50);
      }, 10);
    }
  };

  // Función para cerrar el input de cantidad
  const closeQuantityInput = () => {
    setSelectedProduct(null);
   };
  

  // Limpiar estado cuando cambie el pedido
  useEffect(() => {
    if (pedidoData && pedidoData.id) {
      // Si cambió el pedido, limpiar el estado del input
      closeQuantityInput();
    }
  }, [pedidoData?.id]);

  // Cleanup del debounce al desmontar el componente
  useEffect(() => {
    return () => {
      if (searchDebounceRef.current) {
        clearTimeout(searchDebounceRef.current);
      }
    };
  }, []);

  // Función para verificar si hay cantidades negativas en el pedido
  const hasNegativeQuantities = () => {
    if (!pedidoData || !pedidoData.items) return false;
    
    // Verificar si hay items con cantidad negativa en el pedido
    const hasNegativeItems = pedidoData.items.filter(item => parseFloat(item.cantidad) < 0).length ? true : false;
    
    // Verificar si la cantidad actual que se está ingresando es negativa
    const currentQuantityIsNegative = parseFloat(cantidad) < 0;
    console.log(hasNegativeItems, currentQuantityIsNegative);
    console.log(pedidoData.items);
    console.log(cantidad);
    
    return !hasNegativeItems && currentQuantityIsNegative;
  };

  // Función para manejar el éxito del escaneo del carnet
  const handleCarnetScanSuccess = (carnetCode) => {
    setvalinputsetclaveadmin(carnetCode);
    // Proceder con la adición al carrito
    proceedWithAddToCart(carnetCode);
  };

  // Función para proceder con la adición al carrito (con o sin carnet). cantidadOverride = valor actual del input (p. ej. al pulsar Enter).
  const proceedWithAddToCart = (carnetCode = null, cantidadOverride = null) => {
    if (selectedProduct) {
      const cantidadToUse = cantidadOverride !== undefined && cantidadOverride !== null && String(cantidadOverride).trim() !== "" ? cantidadOverride : cantidad;
      const qtyNum = parseFloat(cantidadToUse) || 0;

      // Obtener el producto actual de la lista para tener el stock más reciente
      const currentProduct = productos.find(p => p.id === selectedProduct.id);
      if (!currentProduct) {
        notificar("Producto no encontrado", "error");
        return;
      }
      
      // Validar que la cantidad no supere el stock disponible (solo para pedidos de backend)
      if (!pedidoData?._frontOnly && qtyNum > currentProduct.cantidad) {
        notificar(`No se puede agregar ${qtyNum} unidades. Stock disponible: ${currentProduct.cantidad}`, "error");
        return;
      }

      if (pedidoData?._frontOnly && qtyNum === 0) {
        notificar("Indique la cantidad", "error");
        return;
      }
      
      // Pedido solo en front: no llamar setCarrito; agregar directamente al pedido en state (vía addCarritoRequestInterno). No llamar setNum(10) para no disparar getProductos (inventario) innecesariamente.
      if (pedidoData?._frontOnly) {
        addCarritoRequestInterno(null, true, cantidadToUse);
        setproductoSelectinternouno(null);
        closeQuantityInput();
        if (refaddfast?.current) {
          if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
          }
          refaddfast.current.select();
          setFocusedRowIndex(null);
          const checkAndFocus = () => {
            if (searchCompleted && refaddfast?.current) {
              refaddfast.current.focus();
            } else {
              setTimeout(checkAndFocus, 50);
            }
          };
          setTimeout(checkAndFocus, 50);
        }
        return;
      }
      
      // Simular exactamente lo que hace addCarritoRequestInterno
      try {
        if (devolucionTipo == 1) {
          // Si es garantía, no hacer nada por ahora
          console.log('Es garantía, no implementado aún');
          return;
        }

        // Siempre usar "agregar" - el backend maneja las actualizaciones automáticamente
        let type = "agregar";
        let params = {
          id: selectedProduct.id,
          type,
          cantidad: qtyNum,
          numero_factura: pedidoData?.id || null,
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
          valinputsetclaveadmin: carnetCode // Agregar el código del carnet
        };
        if (qtyNum < 0 && pedidoData?.isdevolucionOriginalid) {
          params.id_pedido_original = pedidoData.isdevolucionOriginalid;
        }


        // Llamar directamente a db.setCarrito (solo para pedidos de backend)
        db.setCarrito(params).then((res) => {
          if (res.data.msj) {
            notificar(res.data.msj);
          }
          getPedido();
          setproductoSelectinternouno(null);
          closeQuantityInput();
          setNum(10);

          // Limpiar input de búsqueda y hacer foco después de que termine la búsqueda
          if (refaddfast?.current) {
            // Limpiar el debounce pendiente si existe
            if (searchDebounceRef.current) {
              clearTimeout(searchDebounceRef.current);
            }
            
            // Limpiar el input inmediatamente
            refaddfast.current.select();
            setFocusedRowIndex(null)
            
            // Ejecutar búsqueda vacía para limpiar resultados
            
            // Hacer foco después de que la búsqueda termine
            const checkAndFocus = () => {
              if (searchCompleted && refaddfast?.current) {
                refaddfast.current.focus();
              } else {
                // Si aún no terminó, esperar un poco más
                setTimeout(checkAndFocus, 50);
              }
            };
            
            // Iniciar la verificación
            setTimeout(checkAndFocus, 50);
          }

          if(res.data.estado===false) {
              setLastDbRequest({ dbFunction: db.setCarrito, params });
              openValidationTarea(res.data.id_tarea)
          }
        }).catch((error) => {
          console.error('Error en setCarrito:', error);
          notificar("Error al agregar producto al carrito");
        });
      } catch (error) {
        console.error('Error al agregar al carrito:', error);
      }
    }
  };

  // Función para verificar si el pedido necesita asignar el pedido original de devolución
  const necesitaPedidoOriginal = () => {
    if (!pedidoData) return false;
    // Si no tiene isdevolucionOriginalid y no tiene pedidoOriginalAsignado
    return !pedidoData.isdevolucionOriginalid && !pedidoOriginalAsignado;
  };

  // Función para agregar al carrito
  const handleAddToCart = (event = null) => {
    if (selectedProduct) {
      // Verificar si hay cantidades negativas en el pedido
      if (hasNegativeQuantities()) {
        // PRIMERO: Verificar si necesita asignar el pedido original
        if (necesitaPedidoOriginal() && setShowModalPedidoOriginal) {
          // Mostrar modal de pedido original ANTES del modal de carnet
          setShowModalPedidoOriginal(true);
          return;
        }
        
        // SEGUNDO: Si ya tiene pedido original, mostrar modal de carnet
        setShowModalCarnet(true);
        return;
      }
      
      // Pasar valor del input cuando viene del Enter para agregar y cerrar en una
      const cantidadFromInput = event?.target?.value;
      proceedWithAddToCart(null, cantidadFromInput);
    }
  };


  // Navegación con flechas - Solo prevenir cuando el input está abierto
  useHotkeys(
    "up",
    (event) => {
      // No ejecutar si el modal de referencia está abierto
      if (togglereferenciapago) {
        return; // Permitir comportamiento por defecto del UP
      }
      
      // Si el input de cantidad está abierto, no hacer nada (permitir navegación del cursor)
      if (selectedProduct && event.target === inputCantidadCarritoref?.current) {
        // No prevenir el evento, permitir que el cursor se mueva normalmente
        return;
      }
    },
    {
      enableOnTags: ["INPUT"],
      keydown: true,
      keyup: false,
    },
    [selectedProduct, togglereferenciapago, inputCantidadCarritoref]
  );

  useHotkeys(
    "down",
    (event) => {
      // No ejecutar si el modal de referencia está abierto
      if (togglereferenciapago) {
        return; // Permitir comportamiento por defecto del DOWN
      }
      
      // Si el input de cantidad está abierto, no hacer nada (permitir navegación del cursor)
      if (selectedProduct && event.target === inputCantidadCarritoref?.current) {
        // No prevenir el evento, permitir que el cursor se mueva normalmente
        return;
      }
    },
    {
      enableOnTags: ["INPUT"],
      keydown: true,
      keyup: false,
    },
    [selectedProduct, togglereferenciapago, inputCantidadCarritoref]
  );

 

  useHotkeys(
    "escape",
    (event) => {
      if (selectedProduct) {
        event.preventDefault();
        event.stopPropagation();
        closeQuantityInput();
        return; // Detener la ejecución aquí
      }
      
      // Hacer scroll top en todos los contenedores
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
      // Buscar y hacer scroll en cualquier contenedor padre con overflow
      let parent = refaddfast.current?.parentElement;
      while (parent) {
        const style = window.getComputedStyle(parent);
        if (style.overflowY === 'auto' || style.overflowY === 'scroll') {
          parent.scrollTo({ top: 0, behavior: 'smooth' });
        }
        parent = parent.parentElement;
      }
      
      setTimeout(() => {
        refaddfast.current.select();
      }, 100);
    },
    {
      enableOnTags: ["INPUT", "SELECT"],
    },
    []
);

  // Enter en input de cantidad: agregar al carrito con el valor actual del input (un solo Enter agrega y cierra)
  useHotkeys(
    "enter",
    (event) => {
      if (selectedProduct && event.target === inputCantidadCarritoref?.current) {
        event.preventDefault();
        event.stopPropagation();
        handleAddToCart(event);
      }
    },
    {
      enableOnTags: ["INPUT"],
      keydown: true,
      keyup: false,
    },
    [selectedProduct, handleAddToCart]
  );

  // Ref para controlar el enfoque después de Enter
  const pendingFocusRef = useRef(false);

  // useEffect para manejar el enfoque cuando la búsqueda termine
  useEffect(() => {
    if (pendingFocusRef.current && searchCompleted && productos.length > 0) {
      // Pequeño delay para asegurar que el DOM se haya actualizado
      setTimeout(() => {
        // Enfocar el primer elemento
        setCountListInter(0);
        if (tbodyproducInterref?.current?.rows[0]) {
          tbodyproducInterref.current.rows[0].focus();
        }
        // Resetear la bandera
        pendingFocusRef.current = false;
      }, 50);
    }
  }, [searchCompleted, productos, tbodyproducInterref, setCountListInter]);

  // useEffect para manejar Ctrl y quitar foco del input de búsqueda
  useEffect(() => {
    const handleKeyDown = (e) => {
      // Si se presiona Control (izquierdo o derecho) y el input está enfocado
      if ((e.key === 'Control' || e.ctrlKey) && document.activeElement === refaddfast?.current) {
        refaddfast.current.blur();
      }
    };

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [refaddfast]);

  // Enter en el input de búsqueda para enfocar el primer resultado
  useHotkeys(
    "enter",
    (event) => {
      if (event.target === refaddfast?.current) {
        event.preventDefault();
        event.stopPropagation();
        
        // Función para enfocar el primer elemento
        const focusFirstElement = () => {
          setCountListInter(0);
          if (tbodyproducInterref?.current?.rows[0]) {
            tbodyproducInterref.current.rows[0].focus();
          }
        };
        
        // Si hay productos y la búsqueda ya terminó, enfocar inmediatamente
        if (productos.length > 0 && searchCompleted) {
          focusFirstElement();
        } else {
          // Si no hay productos o la búsqueda está en progreso, esperar
          // Primero ejecutar la búsqueda inmediatamente (sin debounce)
          const currentValue = refaddfast.current.value;
          if (currentValue) {
            // Limpiar el debounce pendiente
            if (searchDebounceRef.current) {
              clearTimeout(searchDebounceRef.current);
            }
            
            // Ejecutar búsqueda inmediatamente
            setQProductosMain(currentValue);
            getProductos(currentValue);
            
            // Marcar que debe enfocar cuando termine
            pendingFocusRef.current = true;
          } else {
            // Si no hay valor, enfocar inmediatamente si hay productos
            if (productos.length > 0) {
              focusFirstElement();
            }
          }
        }
      }
    },
    {
      enableOnTags: ["INPUT"],
      keydown: true,
      keyup: false,
    },
    [productos, tbodyproducInterref, setCountListInter, searchCompleted]
  );
  return (
      <div className="flex flex-col h-100">
          {/* Barra de búsqueda responsive */}
          <div className="top-0 z-10 flex flex-col mt-0 mb-1 rounded gap-y-1 sm:flex-row sm:items-center">
              <input
                  type="text"
                  ref={refaddfast}
                  className="flex-1 px-3 py-2 text-xs border border-gray-300 rounded focus:!ring-2 focus:!ring-orange-400 focus:!border-orange-400"
                  placeholder="Agregar...(Esc)"
                  /* onChange={(e) => setinputqinterno(e.target.value)} */
                  onChange={(e) => {
                    const value = e.target.value;
                    
                    // Limpiar el timeout anterior si existe
                    if (searchDebounceRef.current) {
                      clearTimeout(searchDebounceRef.current);
                    }
                    
                    
                    // Configurar el debounce para la búsqueda
                    searchDebounceRef.current = setTimeout(() => {
                      setQProductosMain(value);
                    }, 100); // 300ms de delay
                  }}
              />
              <select
                  className="px-2 py-2 text-xs border border-gray-300 rounded sm:ml-2 sm:w-20 focus:ring-1 focus:ring-orange-400 focus:border-orange-400 sm:mt-0"
                  value={num}
                  onChange={e => setNum(Number(e.target.value))}
                  title="Cantidad de registros a mostrar"
              >
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={20}>20</option>
                  <option value={50}>50</option>
              </select>
          </div>

          {/* Tabla responsive - desktop: fija, móvil: scroll horizontal */}
          <div className="flex-1 overflow-auto border border-gray-200 rounded">
            <table className="w-full min-w-[700px] text-xs table-fixed border-gray-200">

              <colgroup>
                  <col className="w-[100px] min-w-[100px]" />
                  <col className="w-[200px] min-w-[200px]" />
                  <col className="w-[60px] min-w-[60px]" />
                  <col className="w-[25px] min-w-[25px]" />
                  <col className="w-[180px] min-w-[180px]" />
              </colgroup>
              <thead className="border-b bg-gray-50">
                  <tr>
                      <th 
                          className="px-1 py-1 text-xs font-medium text-left text-gray-600 transition-colors cursor-pointer"
                          onClick={() => handleColumnClick('codigo_barras')}
                          title="Ordenar por código"
                      >
                          <div className="flex items-center justify-between">
                              <span>Código</span>
                              {getSortIcon('codigo_barras')}
                          </div>
                      </th>
                      <th 
                          className="px-2 py-1 text-xs font-medium text-left text-gray-600 transition-colors cursor-pointer"
                          onClick={() => handleColumnClick('descripcion')}
                          title="Ordenar por descripción"
                      >
                          <div className="flex items-center justify-between">
                              <span>Descripción</span>
                              {getSortIcon('descripcion')}
                          </div>
                      </th>
                      <th 
                          className="px-1 py-1 text-xs font-medium text-center text-gray-600 transition-colors cursor-pointer hover:bg-gray-100"
                          onClick={() => handleColumnClick('cantidad')}
                          title="Ordenar por cantidad"
                      >
                          <div className="flex items-center justify-center space-x-1">
                              <span>Cant.</span>
                              {getSortIcon('cantidad')}
                          </div>
                      </th>
                      <th className="px-0.5 py-1 text-center text-xs font-medium text-gray-600">
                          Und.
                      </th>
                      <th 
                          className="px-1 py-1 text-xs font-medium text-center text-gray-600 transition-colors cursor-pointer hover:bg-gray-100"
                          onClick={() => handleColumnClick('precio')}
                          title="Ordenar por precio"
                      >
                          <div className="flex items-center justify-center space-x-1">
                              <span>Precios</span>
                              {getSortIcon('precio')}
                          </div>
                      </th>
                  </tr>
              </thead>
              <tbody
                  ref={tbodyproducInterref}
                  className="divide-y divide-gray-200"
              >
                  {!searchCompleted ? (
                      // Mostrar loader cuando la búsqueda está en progreso
                      <tr>
                          <td
                              colSpan="5"
                              className="px-4 py-8 text-xs text-center text-gray-500"
                          >
                              <div className="flex flex-col items-center space-y-3">
                                  <div className="relative">
                                      <div className="w-8 h-8 border-2 border-gray-200 rounded-full animate-spin">
                                          <div className="absolute inset-0 border-2 border-transparent rounded-full border-t-orange-400 animate-spin"></div>
                                      </div>
                                  </div>
                                  <div className="text-gray-600">
                                      <span className="font-medium">
                                          Buscando productos
                                      </span>
                                      <span className="animate-pulse">...</span>
                                  </div>
                              </div>
                          </td>
                      </tr>
                  ) : productos.length > 0 ? (
                      productos.map((e, i) => {
                          const isSelected = selectedProduct && selectedProduct.id == e.id;
                          const isHighlighted = isSelected || countListInter == i || focusedRowIndex === i;
                          return (
                              <tr
                                  tabIndex={-1}
                                  className={`bg-white cursor-pointer outline-none transition-colors focus:outline-none focus:ring-0 ${isHighlighted ? "bg-amber-50/80" : ""} focus-within:!bg-amber-100 hover:bg-gray-50/80`}
                                  key={e.id}
                                  onClick={() => handleProductSelection(e.id)}
                                  onFocus={() => setFocusedRowIndex(i)}
                                  onBlur={(ev) => {
                                      if (!ev.currentTarget.contains(ev.relatedTarget)) {
                                          setFocusedRowIndex(null);
                                      }
                                  }}
                                  data-index={e.id}
                              >
                                  <td className="px-1 py-1 font-mono text-xs text-gray-700">
                                      <div
                                          className="text-xs break-words"
                                          title={e.codigo_barras}
                                      >
                                          {e.codigo_barras}
                                      </div>
                                      <div
                                          className="text-xs text-gray-500 break-words"
                                          title={e.codigo_proveedor}
                                      >
                                          {e.codigo_proveedor}
                                      </div>
                                  </td>
                                  <td className="px-2 py-1 text-xs font-medium text-gray-900">
                                      <div className="flex items-center space-x-2">
                                          <div
                                              className="break-words"
                                              title={e.descripcion}
                                          >
                                              {e.descripcion}
                                          </div>
                                          {isProductInCart(e.id) && (
                                              <span className="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0">
                                                  {getCartQuantity(e.id)}
                                              </span>
                                          )}
                                      </div>
                                  </td>
                                  <td className="px-1 py-1 text-center align-middle">
                                      <div className="inline-block min-w-[3.5rem] w-14 text-center">
                                          {isSelected ? (
                                              <input
                                                  type="number"
                                                  ref={inputCantidadCarritoref}
                                                  className="w-full max-w-[3.5rem] min-w-[2.5rem] px-1 py-0.5 text-xs text-center border border-gray-300 rounded focus:ring-1 focus:ring-orange-400 focus:border-orange-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none box-border"
                                                  placeholder={`${e.cantidad}`}
                                                  min="1"
                                                  max={e.cantidad}
                                                  onKeyDown={(event) => {
                                                      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                                                          event.preventDefault();
                                                      }
                                                      if (event.key === 'Enter') {
                                                          event.preventDefault();
                                                          event.stopPropagation();
                                                          handleAddToCart(event);
                                                      }
                                                  }}
                                                  onChange={(event) => {
                                                      const value = event.target.value;
                                                      if (value === '' || /^[-\d]*\.?\d*$/.test(value)) {
                                                          const numericValue = value === '' ? "" : parseFloat(value);
                                                          if (numericValue === "" || numericValue <= e.cantidad) {
                                                              setCantidad(numericValue);
                                                          } else {
                                                              notificar(`Cantidad máxima disponible: ${e.cantidad}`, "warning");
                                                          }
                                                      }
                                                  }}
                                                  value={cantidad}
                                              />
                                          ) : (
                                              <span
                                                  className={
                                                      `inline-block px-1 py-0.5 border !border-orange-200 rounded text-xs formShowProductos cursor-pointer ` +
                                                      (e.cantidad == 0
                                                          ? "bg-red-100 text-red-700 border-red-300"
                                                          : "bg-orange-50 text-orange-900")
                                                  }
                                              >
                                                  {e.cantidad}
                                              </span>
                                          )}
                                      </div>
                                  </td>
                                  <td className="px-0.5 py-1 text-center text-xs text-gray-600 whitespace-nowrap">
                                      <div className="text-xs">
                                          {e.unidad}
                                      </div>
                                  </td>
                                  <td className="px-1 py-1">
                                      {isSelected ? (
                                          <div className="flex items-center justify-end space-x-2">
                                              <div className="text-xs text-gray-600">
                                                  <div className="text-green-800">${moneda(cantidad * e.precio)}</div>
                                                  <div>Bs.{moneda(cantidad * e.precio * dolar)}</div>
                                              </div>
                                              <div className="flex space-x-1">
                                                  <button
                                                      onClick={(event) => {
                                                          event.stopPropagation();
                                                          closeQuantityInput();
                                                      }}
                                                      className="px-2 py-1 text-xs text-white bg-gray-500 rounded hover:bg-gray-600 focus:ring-2 focus:ring-gray-400"
                                                      title="Cancelar"
                                                  >
                                                      ✕
                                                  </button>
                                              </div>
                                          </div>
                                      ) : (
                                          <div className="flex flex-col gap-1 sm:flex-row sm:gap-2 min-w-[180px]">
                                              <span className="flex-1 px-1 py-0.5 bg-orange-50 text-green-800 border !border-orange-200 text-lg font-medium rounded text-center whitespace-nowrap">
                                                  ${moneda(e.precio)}
                                              </span>
                                              <span className="flex-1 px-1 py-0.5 bg-orange-50 text-orange-900 border !border-orange-200 text-lg rounded text-center whitespace-nowrap">
                                                  Bs.{moneda(e.bs)}
                                              </span>

                                              {user.sucursal == "elorza" && (
                                                  <span className="flex-1 px-1 py-0.5 bg-orange-50 text-orange-900 border !border-orange-200 text-lg rounded text-center whitespace-nowrap">
                                                      P.{moneda(e.cop)}
                                                  </span>
                                              )}
                                          </div>
                                      )}
                                  </td>
                              </tr>
                          );
                      })
                  ) : productos && productos.length === 0 && qProductosMain ? (
                      // Solo mostrar "sin resultados" si hay búsqueda activa
                      <tr>
                          <td
                              colSpan="5"
                              className="px-4 py-4 text-xs text-center text-gray-500"
                          >
                              <div className="text-gray-600">
                                  No se encontraron productos 
                              </div>
                          </td>
                      </tr>
                  ) : (
                      // Estado inicial o carga
                      <tr>
                          <td
                              colSpan="5"
                              className="px-4 py-8 text-xs text-center text-gray-500"
                          >
                              <div className="flex flex-col items-center space-y-3">
                                  <div className="relative">
                                      <div className="w-8 h-8 border-2 border-gray-200 rounded-full animate-spin">
                                          <div className="absolute inset-0 border-2 border-transparent rounded-full border-t-orange-400 animate-spin"></div>
                                      </div>
                                  </div>
                                  <div className="text-gray-600">
                                      <span className="font-medium">
                                          Cargando productos
                                      </span>
                                      <span className="animate-pulse">...</span>
                                  </div>
                              </div>
                          </td>
                      </tr>
                  )}
              </tbody>
            </table>
          </div>
          
          {/* Modal para escanear carnet de aprobación */}
          <ModalScanCarnetAprobacion
            isOpen={showModalCarnet}
            onClose={() => {
              setShowModalCarnet(false);
              setvalinputsetclaveadmin("");
            }}
            onScanSuccess={handleCarnetScanSuccess}
            inputCarnetRef={inputCarnetRef}
            valinputsetclaveadmin={valinputsetclaveadmin}
            setvalinputsetclaveadmin={setvalinputsetclaveadmin}
          />
      </div>
  );
}