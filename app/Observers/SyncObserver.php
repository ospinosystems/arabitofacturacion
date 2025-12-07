<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Observer para detectar cambios en modelos sincronizables
 * Cuando un registro es actualizado, marca push=0 para re-sincronizar
 */
class SyncObserver
{
    /**
     * Campos que NO deben disparar re-sincronización
     * (para evitar loops infinitos)
     */
    protected $camposIgnorados = [
        'push',
        'sincronizado',
        'sync_at',
    ];
    
    /**
     * Handle the Model "updating" event.
     * Se ejecuta ANTES de guardar los cambios
     */
    public function updating(Model $model)
    {
        // Verificar si el modelo tiene campo push
        if (!$this->tieneCampoPush($model)) {
            return;
        }
        
        // Obtener cambios pendientes
        $cambios = $model->getDirty();
        
        // Remover campos ignorados
        $cambiosRelevantes = array_diff_key($cambios, array_flip($this->camposIgnorados));
        
        // Si hay cambios relevantes y el registro ya estaba sincronizado
        if (!empty($cambiosRelevantes) && $model->getOriginal('push') == 1) {
            // Marcar para re-sincronizar
            $model->push = 0;
            
            Log::info("SyncObserver: Marcado para re-sync", [
                'modelo' => get_class($model),
                'id' => $model->id,
                'campos_modificados' => array_keys($cambiosRelevantes),
            ]);
        }
    }
    
    /**
     * Handle the Model "created" event.
     * Los registros nuevos siempre tienen push=0 por defecto
     */
    public function created(Model $model)
    {
        // Asegurar que registros nuevos tengan push=0
        if ($this->tieneCampoPush($model) && $model->push === null) {
            $model->push = 0;
            $model->saveQuietly(); // Guardar sin disparar eventos
        }
    }
    
    /**
     * Verifica si el modelo tiene el campo push
     */
    protected function tieneCampoPush(Model $model): bool
    {
        return in_array('push', $model->getFillable()) || 
               $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'push');
    }
}
