import axios from 'axios';

const host = '';

/** API para autopago - mismos endpoints que facturación */
export const api = {
  login: (data) => axios.post(host + 'login', data),
  verificarLogin: () => axios.post(host + 'verificarLogin'),
  logout: () => axios.get(host + 'logout'),
  forceUpdateDollar: () => axios.post(host + 'forceUpdateDollar'),
  getInventario: (data) => axios.post(host + 'getinventario', data),
  getpersona: (data) => axios.post(host + 'getpersona', data),
  setClienteCrud: (data) => axios.post(host + 'setClienteCrud', data),
  enviarTransaccionPOS: (data) => axios.post(host + 'enviarTransaccionPOS', data),
  autovalidarTransferencia: (data) => axios.post(host + 'autovalidar-transferencia', data),
  completarOrden: (data) => axios.post(host + 'autopago-completar-orden', data),
  sendClavemodal: (data) => axios.post(host + 'sendClavemodal', data),
  crearTareaAgregarProducto: () => axios.post(host + 'autopago-crear-tarea-agregar-producto'),
  solicitarAgregarProducto: (data) => axios.post(host + 'autopago-solicitar-agregar-producto', data),
};

export default api;
