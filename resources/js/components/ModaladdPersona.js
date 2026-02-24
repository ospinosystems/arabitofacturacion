import { useHotkeys } from "react-hotkeys-hook";
import { useState } from "react";

export default function Modaladdproductocarrito({
  countListPersoInter,
  tbodypersoInterref,
  setToggleAddPersona,
  getPersona,
  personas,
  setPersonas,
  inputmodaladdpersonacarritoref,
  setPersonaFast,
  clienteInpidentificacion,
  setclienteInpidentificacion,
  clienteInpnombre,
  setclienteInpnombre,
  clienteInptelefono,
  setclienteInptelefono,
  clienteInpdireccion,
  setclienteInpdireccion,
  number
}) {
  const [searchValue, setSearchValue] = useState("");
  const clearSearch = () => {
    setSearchValue("");
    getPersona("");
    if (inputmodaladdpersonacarritoref?.current) {
      inputmodaladdpersonacarritoref.current.value = "";
    }
  };

  useHotkeys(
    "esc",
    (event) => {
        // Si estamos en el input de búsqueda y hay texto, limpiar primero
        if (
            event.target === inputmodaladdpersonacarritoref?.current &&
            searchValue
        ) {
            clearSearch();
            event.preventDefault();
            return;
        }
        // Si no hay texto en búsqueda o no estamos en el input, cerrar modal
        setToggleAddPersona(false);
    },
    {
      enableOnTags: ["INPUT", "SELECT"],
      filter: false,
    },
    [inputmodaladdpersonacarritoref, searchValue, clearSearch]
  );

  return (
    <>
      {/* Modal Backdrop */}
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
        {/* Modal Container */}
        <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col">
          {/* Modal Header */}
          <div className="flex items-center justify-between flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50">
            <h5 className="text-lg font-semibold text-gray-900">Agregar Cliente</h5>
            <button 
              type="button" 
              className="p-2 text-gray-400 transition-colors rounded-full hover:text-gray-600 hover:bg-gray-100"
              onClick={() => setToggleAddPersona(false)}
              aria-label="Close"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          {/* Modal Body */}
          <div className="flex-1 overflow-y-auto">
            <div className="p-4">
            {/* Search Container */}
            <div className="mb-4">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input 
                  type="text" 
                  className="w-full py-2 pl-10 pr-10 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                  ref={inputmodaladdpersonacarritoref} 
                  placeholder="Buscar cliente..." 
                  value={searchValue}
                  onChange={(val) => {
                    const newValue = val.target.value;
                    setSearchValue(newValue);
                    getPersona(newValue);
                  }}
                />
                {searchValue && (
                  <button
                    type="button"
                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                    onClick={clearSearch}
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                )}
              </div>
            </div>

            {/* Content Container */}
            <div className="bg-white border border-gray-200 rounded-lg">
              {(searchValue && searchValue.trim() !== '' && personas.length > 0) && (
                <div className="border-b border-gray-200 bg-gray-50">
                  <div className="grid grid-cols-2 gap-4 px-4 py-3">
                    <div className="text-xs font-medium tracking-wider text-gray-600 uppercase">
                      Cédula
                    </div>
                    <div className="text-xs font-medium tracking-wider text-gray-600 uppercase">
                      Nombre y Apellido
                    </div>
                  </div>
                </div>
              )}
              
              <div ref={tbodypersoInterref} className="divide-y divide-gray-200">
                  {searchValue && searchValue.trim() !== '' && personas.length > 0 ? personas.map((e, i) => (
                    <div 
                      tabIndex="-1" 
                      className={`grid grid-cols-2 gap-4 px-4 py-3 cursor-pointer transition-colors ${
                        countListPersoInter === i 
                          ? "bg-orange-50 border-l-4 border-orange-400" 
                          : "hover:bg-gray-50"
                      }`}
                      key={e.id} 
                      onClick={() => setPersonas(e)}
                      data-index={e.id}
                    >
                      <div className="font-mono text-sm text-gray-700">
                        {e.identificacion}
                      </div>
                      <div className="text-sm font-medium text-gray-900" data-index={i}>
                        {e.nombre}
                      </div>
                    </div>
                  )) : searchValue && searchValue.trim() !== '' ? (
                    // Mostrar mensaje de "no encontrado" solo si hay búsqueda activa
                    <div className="px-4 py-8 text-center text-gray-500">
                      <div className="flex flex-col items-center space-y-3">
                        <svg className="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <div className="text-sm">
                          No se encontraron clientes para: "<span className="font-medium">{searchValue}</span>"
                        </div>
                        <button 
                          onClick={clearSearch}
                          className="px-4 py-2 text-sm text-orange-600 transition-colors rounded-md hover:text-orange-700 hover:bg-orange-50"
                        >
                          Limpiar búsqueda
                        </button>
                      </div>
                    </div>
                  ) : (
                    // Mostrar formulario solo si no hay búsqueda activa
                    <div className="px-4 py-6">
                      <div className="max-w-2xl mx-auto">
                        <div className="mb-6 text-center">
                          <div className="text-sm text-gray-500">
                            Crear un nuevo cliente:
                          </div>
                        </div>
                        <form onSubmit={setPersonaFast} className="space-y-4">
                          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                              <label htmlFor="identificacion" className="block text-xs font-medium text-gray-700">
                                C.I./RIF
                              </label>
                              <input
                                type="text"
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                id="identificacion"
                                value={clienteInpidentificacion}
                                onChange={e => setclienteInpidentificacion(e.target.value)}
                                placeholder="Ingrese C.I. o RIF"
                              />
                            </div>
                            <div className="space-y-1">
                              <label htmlFor="nombre" className="block text-xs font-medium text-gray-700">
                                Nombres y Apellidos
                              </label>
                              <input
                                type="text"
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                id="nombre"
                                value={clienteInpnombre}
                                onChange={e => setclienteInpnombre(e.target.value)}
                                placeholder="Nombre completo"
                              />
                            </div>
                            <div className="space-y-1">
                              <label htmlFor="telefono" className="block text-xs font-medium text-gray-700">
                                Teléfono
                              </label>
                              <input
                                type="text"
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                id="telefono"
                                value={clienteInptelefono}
                                onChange={e => setclienteInptelefono(e.target.value)}
                                placeholder="Número de teléfono"
                              />
                            </div>
                            <div className="space-y-1">
                              <label htmlFor="direccion" className="block text-xs font-medium text-gray-700">
                                Dirección
                              </label>
                              <input
                                type="text"
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
                                id="direccion"
                                value={clienteInpdireccion}
                                onChange={e => setclienteInpdireccion(e.target.value)}
                                placeholder="Dirección completa"
                              />
                            </div>
                          </div>
                          <div className="flex justify-center pt-4">
                            <button 
                              className="flex items-center px-6 py-2 space-x-2 text-sm font-medium text-white transition-colors bg-orange-500 rounded-md hover:bg-orange-600 focus:ring-2 focus:ring-orange-400 focus:ring-offset-2" 
                              type="submit"
                            >
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
                              </svg>
                              <span>Guardar Cliente</span>
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  )}
              </div>
            </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
