<?php

namespace App\Services;

use App\Models\UserSession;
use App\Models\usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SessionManager
{
    /**
     * Crear una nueva sesión única para un usuario
     */
    public function createSession(usuarios $usuario, Request $request): array
    {
        // Invalidar todas las sesiones previas del usuario
        $this->invalidateUserSessions($usuario->id);
        
        // Generar ID único para la sesión
        $sessionId = Str::uuid()->toString();
        
        // Preparar datos legacy para compatibilidad con frontend
        $legacyData = [
            'id_usuario' => $usuario->id,
            'usuario' => $usuario->usuario,
            'nombre' => $usuario->nombre,
            'tipo_usuario' => $usuario->tipo_usuario,
            'nivel' => $this->getAuthLevel($usuario->tipo_usuario),
            'role' => $this->getRoleLabel($usuario->tipo_usuario),
            'iscentral' => $usuario->iscentral ?? false
        ];
        
        // Crear la nueva sesión
        UserSession::create([
            'usuario_id' => $usuario->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'Artisan Command',
            'last_activity' => now(),
            'is_active' => true,
            'legacy_session_data' => $legacyData
        ]);
        
        // Establecer sesión legacy para compatibilidad inmediata
        foreach ($legacyData as $key => $value) {
            session([$key => $value]);
        }
        
        Log::info("Nueva sesión creada", [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->usuario,
            'session_id' => $sessionId,
            'ip' => $request->ip() ?? '127.0.0.1'
        ]);
        
        return [
            'session_token' => $sessionId,
            'legacy_data' => $legacyData
        ];
    }
    
    /**
     * Validar una sesión existente
     */
    public function validateSession(string $sessionId): ?array
    {
        $session = UserSession::where('session_id', $sessionId)
            ->active()
            ->recent()
            ->with('usuario')
            ->first();
            
        if (!$session) {
            return null;
        }
        
        // Actualizar última actividad
        $session->updateActivity();
        
        // Actualizar sesión legacy
        if ($session->legacy_session_data) {
            foreach ($session->legacy_session_data as $key => $value) {
                session([$key => $value]);
            }
        }
        
        return [
            'usuario' => $session->usuario,
            'legacy_data' => $session->legacy_session_data,
            'session' => $session
        ];
    }
    
    /**
     * Invalidar todas las sesiones de un usuario
     */
    public function invalidateUserSessions(int $usuarioId): void
    {
        $count = UserSession::where('usuario_id', $usuarioId)
            ->active()
            ->update(['is_active' => false]);
            
        if ($count > 0) {
            Log::info("Sesiones invalidadas", [
                'usuario_id' => $usuarioId,
                'count' => $count
            ]);
        }
    }
    
    /**
     * Invalidar una sesión específica
     */
    public function invalidateSession(string $sessionId): bool
    {
        $session = UserSession::where('session_id', $sessionId)->first();
        
        if ($session) {
            $session->invalidate();
            Log::info("Sesión invalidada", [
                'session_id' => $sessionId,
                'usuario_id' => $session->usuario_id
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Limpiar sesiones expiradas
     */
    public function cleanupExpiredSessions(): int
    {
        $count = UserSession::where('last_activity', '<', now()->subHours(8))
            ->active()
            ->update(['is_active' => false]);
            
        if ($count > 0) {
            Log::info("Sesiones expiradas limpiadas", ['count' => $count]);
        }
        
        return $count;
    }
    
    /**
     * Obtener sesiones activas de un usuario
     */
    public function getUserActiveSessions(int $usuarioId): array
    {
        return UserSession::where('usuario_id', $usuarioId)
            ->active()
            ->recent()
            ->get()
            ->toArray();
    }
    
    /**
     * Obtener estadísticas de sesiones
     */
    public function getSessionStats(): array
    {
        return [
            'total_active' => UserSession::active()->count(),
            'total_expired' => UserSession::where('last_activity', '<', now()->subHours(8))->count(),
            'users_with_multiple_sessions' => UserSession::active()
                ->selectRaw('usuario_id, COUNT(*) as session_count')
                ->groupBy('usuario_id')
                ->having('session_count', '>', 1)
                ->count()
        ];
    }
    
    /**
     * Obtener nivel de autenticación para compatibilidad
     */
    private function getAuthLevel(int $tipoUsuario): int
    {
        return match($tipoUsuario) {
            1 => 2, // GERENTE
            4 => 5, // Cajero Vendedor
            5 => 4, // SUPERVISOR DE CAJA
            6 => 1, // SUPERADMIN
            7 => 3, // DICI
            10 => 6, // PORTERO
            default => 0
        };
    }
    
    /**
     * Obtener etiqueta del rol para compatibilidad
     */
    private function getRoleLabel(int $tipoUsuario): string
    {
        return match($tipoUsuario) {
            1 => 'GERENTE',
            4 => 'Cajero Vendedor',
            5 => 'SUPERVISOR DE CAJA',
            6 => 'SUPERADMIN',
            7 => 'DICI',
            10 => 'Portero',
            default => 'Usuario'
        };
    }
} 