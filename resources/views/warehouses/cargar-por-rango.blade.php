@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-layer-group text-blue-500 mr-2"></i>
            Cargar Ubicaciones por Rango
        </h1>
        <p class="text-gray-600 mt-1">Genera múltiples ubicaciones especificando rangos de valores</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Formulario -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <form id="formCargarRango" onsubmit="generarUbicaciones(event)">
                    <!-- Pasillo -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-route mr-2 text-blue-500"></i>
                            Pasillo (Letra)
                        </label>
                        <input type="text" 
                               id="pasillo" 
                               name="pasillo"
                               maxlength="1"
                               class="w-full px-4 py-3 text-2xl font-bold text-center border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase"
                               placeholder="A"
                               required
                               pattern="[A-Z]"
                               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                        <p class="text-xs text-gray-500 mt-1">Ingrese una letra de la A a la Z</p>
                    </div>

                    <!-- Rangos -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <!-- Cara -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-arrows-alt-h mr-2 text-green-500"></i>
                                Cara
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" 
                                       id="cara_desde" 
                                       name="cara_desde"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Desde"
                                       required>
                                <span class="text-gray-500">-</span>
                                <input type="number" 
                                       id="cara_hasta" 
                                       name="cara_hasta"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Hasta"
                                       required>
                            </div>
                        </div>

                        <!-- Rack -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-th mr-2 text-orange-500"></i>
                                Rack
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" 
                                       id="rack_desde" 
                                       name="rack_desde"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                                       placeholder="Desde"
                                       required>
                                <span class="text-gray-500">-</span>
                                <input type="number" 
                                       id="rack_hasta" 
                                       name="rack_hasta"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                                       placeholder="Hasta"
                                       required>
                            </div>
                        </div>

                        <!-- Nivel -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-layer-group mr-2 text-purple-500"></i>
                                Nivel
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" 
                                       id="nivel_desde" 
                                       name="nivel_desde"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                       placeholder="Desde"
                                       required>
                                <span class="text-gray-500">-</span>
                                <input type="number" 
                                       id="nivel_hasta" 
                                       name="nivel_hasta"
                                       min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                       placeholder="Hasta"
                                       required>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración Adicional -->
                    <div class="mb-6 border-t pt-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Configuración Adicional</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select id="tipo" name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="almacenamiento" selected>Almacenamiento</option>
                                    <option value="picking">Picking</option>
                                    <option value="recepcion">Recepción</option>
                                    <option value="despacho">Despacho</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                                <select id="estado" name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="activa" selected>Activa</option>
                                    <option value="inactiva">Inactiva</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                    <option value="bloqueada">Bloqueada</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Zona (Opcional)</label>
                                <input type="text" 
                                       id="zona" 
                                       name="zona"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="Ej: Zona A">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacidad Unidades (Opcional)</label>
                                <input type="number" 
                                       id="capacidad_unidades" 
                                       name="capacidad_unidades"
                                       min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="Ej: 100">
                            </div>
                        </div>
                    </div>

                    <!-- Botón Generar -->
                    <button type="submit" 
                            class="w-full px-6 py-4 bg-blue-500 hover:bg-blue-600 text-white font-bold text-lg rounded-lg shadow-lg transition">
                        <i class="fas fa-magic mr-2"></i>
                        Generar Ubicaciones
                    </button>
                </form>
            </div>
        </div>

        <!-- Panel de Resumen -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h3 class="text-lg font-bold text-gray-700 mb-4">Resumen</h3>
                <div class="space-y-3">
                    <div class="p-3 bg-blue-50 rounded">
                        <div class="text-sm text-gray-600">Pasillo</div>
                        <div id="resumenPasillo" class="font-bold text-blue-600 text-xl mt-1">-</div>
                    </div>
                    <div class="p-3 bg-green-50 rounded">
                        <div class="text-sm text-gray-600">Caras</div>
                        <div id="resumenCara" class="font-semibold text-gray-800 mt-1">-</div>
                    </div>
                    <div class="p-3 bg-orange-50 rounded">
                        <div class="text-sm text-gray-600">Racks</div>
                        <div id="resumenRack" class="font-semibold text-gray-800 mt-1">-</div>
                    </div>
                    <div class="p-3 bg-purple-50 rounded">
                        <div class="text-sm text-gray-600">Niveles</div>
                        <div id="resumenNivel" class="font-semibold text-gray-800 mt-1">-</div>
                    </div>
                    <div class="p-3 bg-yellow-50 rounded border-2 border-yellow-400">
                        <div class="text-sm text-gray-600">Total Ubicaciones</div>
                        <div id="resumenTotal" class="font-bold text-yellow-700 text-2xl mt-1">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notificaciones -->
<div id="notificaciones" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
function mostrarNotificacion(mensaje, tipo = 'info') {
    const tipos = {
        success: { bg: 'bg-green-500', icon: 'fa-check-circle' },
        error: { bg: 'bg-red-500', icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-500', icon: 'fa-exclamation-triangle' },
        info: { bg: 'bg-blue-500', icon: 'fa-info-circle' }
    };
    
    const tipoConfig = tipos[tipo] || tipos.info;
    const notificacion = document.createElement('div');
    notificacion.className = `${tipoConfig.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md animate-slide-in`;
    notificacion.innerHTML = `
        <i class="fas ${tipoConfig.icon} text-xl"></i>
        <span class="flex-1">${mensaje}</span>
        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.getElementById('notificaciones').appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transition = 'opacity 0.3s';
        setTimeout(() => notificacion.remove(), 300);
    }, 5000);
}

function calcularTotal() {
    const pasillo = document.getElementById('pasillo').value;
    const caraDesde = parseInt(document.getElementById('cara_desde').value) || 0;
    const caraHasta = parseInt(document.getElementById('cara_hasta').value) || 0;
    const rackDesde = parseInt(document.getElementById('rack_desde').value) || 0;
    const rackHasta = parseInt(document.getElementById('rack_hasta').value) || 0;
    const nivelDesde = parseInt(document.getElementById('nivel_desde').value) || 0;
    const nivelHasta = parseInt(document.getElementById('nivel_hasta').value) || 0;

    if (caraDesde && caraHasta && rackDesde && rackHasta && nivelDesde && nivelHasta) {
        const totalCaras = caraHasta - caraDesde + 1;
        const totalRacks = rackHasta - rackDesde + 1;
        const totalNiveles = nivelHasta - nivelDesde + 1;
        const total = totalCaras * totalRacks * totalNiveles;

        document.getElementById('resumenPasillo').textContent = pasillo || '-';
        document.getElementById('resumenCara').textContent = `${caraDesde} - ${caraHasta} (${totalCaras})`;
        document.getElementById('resumenRack').textContent = `${rackDesde} - ${rackHasta} (${totalRacks})`;
        document.getElementById('resumenNivel').textContent = `${nivelDesde} - ${nivelHasta} (${totalNiveles})`;
        document.getElementById('resumenTotal').textContent = total;
    } else {
        document.getElementById('resumenPasillo').textContent = '-';
        document.getElementById('resumenCara').textContent = '-';
        document.getElementById('resumenRack').textContent = '-';
        document.getElementById('resumenNivel').textContent = '-';
        document.getElementById('resumenTotal').textContent = '0';
    }
}

function generarUbicaciones(event) {
    event.preventDefault();
    
    const form = document.getElementById('formCargarRango');
    const formData = new FormData(form);
    
    // Validar que "hasta" sea mayor o igual que "desde"
    const caraDesde = parseInt(formData.get('cara_desde'));
    const caraHasta = parseInt(formData.get('cara_hasta'));
    const rackDesde = parseInt(formData.get('rack_desde'));
    const rackHasta = parseInt(formData.get('rack_hasta'));
    const nivelDesde = parseInt(formData.get('nivel_desde'));
    const nivelHasta = parseInt(formData.get('nivel_hasta'));

    if (caraHasta < caraDesde || rackHasta < rackDesde || nivelHasta < nivelDesde) {
        mostrarNotificacion('El valor "Hasta" debe ser mayor o igual que "Desde"', 'error');
        return;
    }

    // Calcular total para confirmación
    const totalCaras = caraHasta - caraDesde + 1;
    const totalRacks = rackHasta - rackDesde + 1;
    const totalNiveles = nivelHasta - nivelDesde + 1;
    const total = totalCaras * totalRacks * totalNiveles;

    if (total > 1000) {
        if (!confirm(`Se generarán ${total} ubicaciones. ¿Desea continuar?`)) {
            return;
        }
    }

    const button = event.target.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generando...';

    fetch('/warehouses/generar-por-rango', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            pasillo: formData.get('pasillo'),
            cara_desde: caraDesde,
            cara_hasta: caraHasta,
            rack_desde: rackDesde,
            rack_hasta: rackHasta,
            nivel_desde: nivelDesde,
            nivel_hasta: nivelHasta,
            tipo: formData.get('tipo'),
            estado: formData.get('estado'),
            zona: formData.get('zona'),
            capacidad_unidades: formData.get('capacidad_unidades') ? parseInt(formData.get('capacidad_unidades')) : null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion(data.msj, 'success');
            if (data.errores && data.errores.length > 0) {
                console.warn('Errores:', data.errores);
            }
            form.reset();
            calcularTotal();
        } else {
            mostrarNotificacion(data.msj || 'Error al generar ubicaciones', 'error');
        }
    })
    .catch(error => {
        mostrarNotificacion('Error al generar ubicaciones', 'error');
        console.error(error);
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-magic mr-2"></i> Generar Ubicaciones';
    });
}

// Actualizar resumen cuando cambian los valores
['pasillo', 'cara_desde', 'cara_hasta', 'rack_desde', 'rack_hasta', 'nivel_desde', 'nivel_hasta'].forEach(id => {
    document.getElementById(id).addEventListener('input', calcularTotal);
});
</script>

<style>
@keyframes slide-in {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slide-in 0.3s ease-out;
}
</style>
@endsection

