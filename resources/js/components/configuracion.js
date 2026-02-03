import Usuarios from '../components/usuarios';
import Categorias from '../components/categorias';


export default function Configuracion({
subViewConfig,
setsubViewConfig,

addNewUsuario,
usuarioNombre,
setusuarioNombre,
usuarioUsuario,
setusuarioUsuario,
usuarioRole,
setusuarioRole,
usuarioClave,
setusuarioClave,
usuarioIpPinpad,
setusuarioIpPinpad,
indexSelectUsuarios,
setIndexSelectUsuarios,
qBuscarUsuario,
setQBuscarUsuario,
delUsuario,
usuariosData,

addNewCategorias,
categoriasDescripcion,
setcategoriasDescripcion,
indexSelectCategorias,
setIndexSelectCategorias,
qBuscarCategorias,
setQBuscarCategorias,
delCategorias,
categorias,


}) {
	return (
		<>
			<div className="container">
        <div className="row">
	        <div className="col mb-2 d-flex justify-content-between">
	          <div className="btn-group">              
              <button className={("btn ")+(subViewConfig=="usuarios"?"btn-success":"btn-outline-success")} onClick={()=>setsubViewConfig("usuarios")}>Usuarios</button>
              
              <button className={("btn ")+(subViewConfig=="categorias"?"btn-success":"btn-outline-success")} onClick={()=>setsubViewConfig("categorias")}>Categorías</button>
              
	          </div>
	        </div>
          
        </div>
      </div>
      <hr/>
			{subViewConfig=="usuarios"?<Usuarios
	          
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
	    />:null}

	    {subViewConfig=="categorias"?<Categorias
        addNewCategorias={addNewCategorias}

        categoriasDescripcion={categoriasDescripcion}
        setcategoriasDescripcion={setcategoriasDescripcion}

        indexSelectCategorias={indexSelectCategorias}
        setIndexSelectCategorias={setIndexSelectCategorias}
        
        qBuscarCategorias={qBuscarCategorias}
        setQBuscarCategorias={setQBuscarCategorias}
        
        delCategorias={delCategorias}
        categorias={categorias}
	    />:null}
    </>
	)
}