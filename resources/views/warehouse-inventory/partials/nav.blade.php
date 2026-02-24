@php
    $tipoUsuario = session('tipo_usuario');
    $nombreUsuario = session('nombre_usuario') ?? session('usuario') ?? 'Usuario';
    $esPasillero = $tipoUsuario == 8;
    $esDICI = $tipoUsuario == 7;
    $esAdmin = in_array($tipoUsuario, [1, 6]); // Gerente o SuperAdmin
@endphp

<!-- Nav responsive adaptado a móvil con Tailwind -->
<div class="bg-gradient-to-r shadow-md mb-4">
    <div class="container-fluid px-3 sm:px-4 py-3">
        <!-- Header con logo, título y usuario -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
                <i class="fas fa-warehouse text-xl sm:text-2xl mr-2"></i>
                <h5 class="font-bold text-lg sm:text-xl mb-0">Warehouse</h5>
            </div>
            
            <!-- Usuario y Logout -->
            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-sm font-medium">
                    <i class="fas fa-user mr-1"></i>{{ $nombreUsuario }}
                </span>
                <a href="/logout" 
                   class="flex items-center px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition shadow-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Salir</span>
                </a>
                <!-- Toggle para móvil -->
                <button type="button" 
                        onclick="toggleWarehouseNav()" 
                        class="lg:hidden hover:text-blue-200 focus:outline-none transition">
                    <i id="navIcon" class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Navegación -->
        <div id="warehouseNavLinks" class="hidden lg:block">
            <div class="grid grid-cols-1 gap-2 lg:flex lg:flex-wrap">
                
                @if($esPasillero)
                    {{-- PASILLERO (tipo 8): Solo TCR Pasillero y TCD Pasillero --}}
                    
                    <!-- TCR Pasilleros -->
                    <a href="/warehouse-inventory/tcr/pasillero" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcr.pasillero') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>TCR Pasillero</span>
                    </a>

                    <!-- TCD Pasilleros -->
                    <a href="{{ route('warehouse-inventory.tcd.pasillero') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcd.pasillero') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>TCD Pasillero</span>
                    </a>
                    
                @elseif($esDICI)
                    {{-- DICI (tipo 7): Todas las opciones menos las de pasillero --}}
                    
                    <!-- Ubicaciones -->
                    <a href="{{ route('warehouses.index') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouses.index') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span>Ubicaciones</span>
                    </a>
                    
                    <!-- Inventario por Ubicaciones -->
                    <a href="{{ route('warehouse-inventory.index') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.index') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-boxes mr-2"></i>
                        <span>Inventario</span>
                    </a>
                    
                    <!-- Inventariar Productos -->
                    <a href="{{ route('inventario.inventariar') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('inventario.inventariar') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        <span>Inventariar</span>
                    </a>
                    
                    <!-- Historial -->
                    <a href="{{ route('warehouse-inventory.historial') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.historial') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-history mr-2"></i>
                        <span>Historial</span>
                    </a>

                    <!-- TCR Chequeador -->
                    <a href="/warehouse-inventory/tcr" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcr.chequeador') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-truck mr-2"></i>
                        <span>TCR</span>
                    </a>

                    <!-- TCD Chequeador -->
                    <a href="{{ route('warehouse-inventory.tcd.chequeador') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcd.chequeador') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-tower-control mr-2"></i>
                        <span>TCD</span>
                    </a>

                    <!-- PPR Reporte -->
                    <a href="{{ route('ppr.reporte') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('ppr.reporte*') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>PPR</span>
                    </a>
                    
                @else
                    {{-- ADMIN (tipos 1, 6) u otros: Mostrar absolutamente todo --}}
                    
                    <!-- Ubicaciones -->
                    <a href="{{ route('warehouses.index') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouses.index') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span>Ubicaciones</span>
                    </a>
                    
                    <!-- Inventario por Ubicaciones -->
                    <a href="{{ route('warehouse-inventory.index') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.index') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-boxes mr-2"></i>
                        <span>Inventario</span>
                    </a>
                    
                    <!-- Inventariar Productos -->
                    <a href="{{ route('inventario.inventariar') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('inventario.inventariar') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        <span>Inventariar</span>
                    </a>
                    
                    <!-- Historial -->
                    <a href="{{ route('warehouse-inventory.historial') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.historial') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-history mr-2"></i>
                        <span>Historial</span>
                    </a>

                    <!-- TCR Chequeador -->
                    <a href="/warehouse-inventory/tcr" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcr.chequeador') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-truck mr-2"></i>
                        <span>TCR</span>
                    </a>

                    <!-- TCR Pasilleros -->
                    <a href="/warehouse-inventory/tcr/pasillero" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcr.pasillero') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>TCR Pasillero</span>
                    </a>

                    <!-- TCD Chequeador -->
                    <a href="{{ route('warehouse-inventory.tcd.chequeador') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcd.chequeador') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-tower-control mr-2"></i>
                        <span>TCD</span>
                    </a>

                    <!-- TCD Pasilleros -->
                    <a href="{{ route('warehouse-inventory.tcd.pasillero') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.tcd.pasillero') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>TCD Pasillero</span>
                    </a>

                    <!-- PPR Reporte -->
                    <a href="{{ route('ppr.reporte') }}" 
                       class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('ppr.reporte*') ? 'bg-white text-blue-700 shadow-md' : '' }}">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        <span>PPR</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function toggleWarehouseNav() {
    const navLinks = document.getElementById('warehouseNavLinks');
    const navIcon = document.getElementById('navIcon');
    
    navLinks.classList.toggle('hidden');
    
    if (navLinks.classList.contains('hidden')) {
        navIcon.classList.remove('fa-times');
        navIcon.classList.add('fa-bars');
    } else {
        navIcon.classList.remove('fa-bars');
        navIcon.classList.add('fa-times');
    }
}

</script>

