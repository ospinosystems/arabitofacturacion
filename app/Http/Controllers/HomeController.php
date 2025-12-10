<?php

namespace App\Http\Controllers;

use App\Models\home;
use App\Models\usuarios;
use App\Models\sucursal;
use App\Models\tareaslocal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Response;
use Session;

class HomeController extends Controller
{
    function sendClavemodal(Request $req) {
        $valinputsetclaveadmin = preg_replace( '/[^a-z0-9 ]/i', '', strtolower($req->valinputsetclaveadmin));
        $idtareatemp = $req->idtareatemp;
        $u = usuarios::all();
        
        foreach ($u as $i => $usuario) {
            //1 GERENTE
            //5 SUPERVISOR DE CAJA
            //6 SUPERADMIN
            //7 DICI

	        

            if ($usuario->tipo_usuario=="7") {
                //DICI
                if (Hash::check($valinputsetclaveadmin, $usuario->clave)) {
                    
                    $obj = tareaslocal::find($idtareatemp);
                    if( $obj->tipo=="exportarPedido" || $obj->tipo=="aprobarPedido" || $obj->tipo=="devolucion" || $obj->tipo=="eliminarPedido" || $obj->tipo=="modped" || $obj->tipo=="devolucionPago") {

                        $obj->estado = 1;
                        $obj->save();
                        return Response::json(["msj"=>"EXITO","estado"=>true]);
                    }
                }
            }


            if ($usuario->tipo_usuario=="1" || $usuario->tipo_usuario=="6") {
                //GERENTE
                //SUPERADMIN
                if (Hash::check($valinputsetclaveadmin, $usuario->clave)) {
                    
                    $obj = tareaslocal::find($idtareatemp);
                    if($obj->tipo=="tickera" || $obj->tipo=="cierre" || $obj->tipo=="credito" || $obj->tipo=="transferirPedido" || $obj->tipo=="descuentoTotal" || $obj->tipo=="descuentoUnitario") {

                        $obj->estado = 1;
                        $obj->save();
                        return Response::json(["msj"=>"EXITO","estado"=>true]);
                    }
                }
            }


            if ($usuario->tipo_usuario=="5") {
                //SUPERVISOR DE CAJA
                if (Hash::check($valinputsetclaveadmin, $usuario->clave)) {
                    
                    $obj = tareaslocal::find($idtareatemp);
                    if ($obj->tipo=="tickera") {
                        $obj->estado = 1;
                        $obj->save();
                        return Response::json(["msj"=>"EXITO","estado"=>true]);
                    }
                }
            }
        }
        return Response::json(["msj"=>"NEGADA","estado"=>false]);
        


    }
    public function index(Request $request)
    {
        $su = sucursal::all()->first();
        if ($su) {
            // Redirigir a Warehouse Inventory si es Galpón Valencia 1
            if ($su->codigo === 'galponvalencia1') {
                // Verificar si hay sesión activa
                $sessionId = $request->header('X-Session-Token') ?? 
                            $request->cookie('session_token');
                
                $sessionData = null;
                if ($sessionId) {
                    $sessionData = app(\App\Services\SessionManager::class)->validateSession($sessionId);
                }
                
                // Fallback a sesión legacy
                if (!$sessionData && session('tipo_usuario')) {
                    $sessionData = ['legacy_data' => ['tipo_usuario' => session('tipo_usuario')]];
                }
                
                // Si hay sesión activa, redirigir a warehouse-inventory
                if ($sessionData) {
                    return redirect('/warehouse-inventory');
                }
                
                // Si no hay sesión, redirigir al login (nunca mostrar vista index)
                return redirect('/login');
            }
            return view("facturar.index");
        }else{

            return view("sucursal.crear",["sucursal"=>$su]);
        }

    }

    
    public function selectRedirect()
    {
      $selectRedirect = "/";
        switch(session("tipo_usuario")){
            case 1:
                $selectRedirect = '/admin';
                break;
            case 2:
                $selectRedirect = '/cajero';
                break;
            default:
                $selectRedirect = '/login';
        }
      return $selectRedirect;
         
        // return $next($request);
    } 
    public function verificarLogin(Request $req)
    {
        // Verificar sesión por token primero
        $sessionId = $req->header('X-Session-Token') ?? 
                    $req->cookie('session_token');
        
        if ($sessionId) {
            $sessionData = app(\App\Services\SessionManager::class)->validateSession($sessionId);
            if ($sessionData) {
                return Response::json(["estado" => true]);
            }
        }
        
        // Fallback a sesión legacy
        if (session()->has("id_usuario")) {
            return Response::json(["estado" => true]);
        }
        
        return Response::json(["estado" => false]);
    }
    public function logout(Request $request)
    {
        // Invalidar sesión por token si existe
        $sessionId = $request->header('X-Session-Token') ?? 
                    $request->cookie('session_token');
        
        if ($sessionId) {
            $sessionManager = app(\App\Services\SessionManager::class);
            $sessionManager->invalidateSession($sessionId);
        }
        
        // Limpiar sesión legacy
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        // Si es petición AJAX, retornar JSON
        if ($request->expectsJson() || $request->ajax()) {
            return Response::json([
                "estado" => true,
                "msj" => "Sesión cerrada exitosamente"
            ]);
        }
        
        // Si es petición normal del navegador, redirigir al login
        return redirect('/')->with('message', 'Sesión cerrada exitosamente');
    }
    public function role($tipo)
    {
        switch ($tipo) {
            case '1':
                return "Administrador";
                break;
            case '2':
                return "Cajero";
                break;
            case '3':
                return "Vendedor";
                break;
            case '4':
                return "Cajero Vendedor";
                break;
            
            default:
                # code...
                break;
        }
    }
    public function nivel($tipo)
    {
        if ($tipo == 1) {
            return 1;
            //Admin
        }
        
        if ($tipo == 1 || 
        $tipo == 2 ||
        $tipo == 4) {
            //Caja
            return 2;
        }
        
        if ($tipo == 1 || 
        $tipo == 3 ||
        $tipo == 4) {
            //Vendedor
            return 3;
        }
    }
    public function login(Request $req)
    {   
        try {
            $d = usuarios::where(function($query) use ($req){
                $query->orWhere('usuario', $req->usuario);
            })
            ->first();

            $sucursal = sucursal::all()->first();
            
            if ($d && Hash::check(preg_replace( '/[^a-z0-9 ]/i', '', strtolower($req->clave)) , $d->clave)) {
                
                // Verificar que el dólar esté actualizado hoy
                $dollarUpdate = $this->checkDollarUpdateToday();
                
                if (!$dollarUpdate['updated']) {
                    return Response::json([
                        "estado" => false,
                        "msj" => "El valor del dólar no ha sido actualizado hoy. Debe actualizarlo antes de continuar.",
                        "dollar_status" => $dollarUpdate,
                        "requires_update" => true
                    ]);
                }
                
                // Crear sesión única usando SessionManager
                $sessionManager = app(\App\Services\SessionManager::class);
                $sessionData = $sessionManager->createSession($d, $req);
                
                $arr_session = [
                    "id_usuario" => $d->id,
                    "tipo_usuario" => $d->tipo_usuario,
                    "nivel" => $this->nivel($d->tipo_usuario),
                    "role" => $this->role($d->tipo_usuario),
                    "usuario" => $d->usuario,
                    "nombre" => $d->nombre,
                    "sucursal" => $sucursal->codigo,
                    "iscentral" => $sucursal->iscentral,
                ];
                
                $estado = $this->selectRedirect();
                
                // Verificar si debe redirigir a gestión de almacén (Galpón Valencia 1)
                $redirectToWarehouse = ($sucursal->codigo === 'galponvalencia1');
                
                return Response::json([
                    "user" => $arr_session,
                    "estado" => true,
                    "msj" => "¡Inicio exitoso! Bienvenido/a, ".$d->nombre,
                    "session_token" => $sessionData['session_token'],
                    "dollar_info" => $dollarUpdate,
                    "redirect_to_warehouse" => $redirectToWarehouse
                ]);
                
            } else {
                throw new \Exception("¡Datos Incorrectos!", 1);
            } 
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
       
    }

    /**
     * Verificar si el dólar ha sido actualizado hoy
     */
    private function checkDollarUpdateToday()
    {
        try {
            $dollar = \DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->where('estatus', 'activo')
                ->first();

            if (!$dollar) {
                return [
                    'updated' => false,
                    'message' => 'No hay información del dólar en el sistema',
                    'last_update' => null,
                    'value' => null,
                    'origin' => null
                ];
            }

            // Si no hay fecha de actualización, considerar como no actualizado
            if (!$dollar->fecha_ultima_actualizacion) {
                return [
                    'updated' => false,
                    'message' => 'El dólar no tiene fecha de actualización registrada',
                    'last_update' => null,
                    'value' => $dollar->valor,
                    'origin' => $dollar->origen ?? 'Manual',
                    'notes' => $dollar->notas ?? 'Sin notas'
                ];
            }

            $today = \Carbon\Carbon::today();
            $lastUpdate = \Carbon\Carbon::parse($dollar->fecha_ultima_actualizacion);

            $isUpdatedToday = $lastUpdate->isSameDay($today);

            return [
                'updated' => $isUpdatedToday,
                'message' => $isUpdatedToday ? 'Dólar actualizado hoy' : 'Dólar no actualizado hoy',
                'last_update' => $dollar->fecha_ultima_actualizacion,
                'value' => $dollar->valor,
                'origin' => $dollar->origen ?? 'Manual',
                'notes' => $dollar->notas ?? 'Sin notas'
            ];

        } catch (\Exception $e) {
            return [
                'updated' => false,
                'message' => 'Error al verificar actualización del dólar: ' . $e->getMessage(),
                'last_update' => null,
                'value' => null,
                'origin' => null
            ];
        }
    }

}
