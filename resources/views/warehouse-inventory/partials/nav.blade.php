<!-- Nav responsive adaptado a móvil con Tailwind -->
<div class="bg-gradient-to-r from-blue-600 to-blue-700 shadow-md mb-4">
    <div class="container-fluid px-3 sm:px-4 py-3">
        <!-- Header con logo y título -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
                <i class="fas fa-warehouse  text-xl sm:text-2xl mr-2"></i>
                <h5 class=" font-bold text-lg sm:text-xl mb-0">Warehouse</h5>
            </div>
            <!-- Toggle para móvil -->
            <button type="button" 
                    onclick="toggleWarehouseNav()" 
                    class="lg:hidden  hover:text-blue-200 focus:outline-none transition">
                <i id="navIcon" class="fas fa-bars text-xl"></i>
            </button>
        </div>
        
        <!-- Navegación -->
        <div id="warehouseNavLinks" class="hidden lg:block">
            <div class="grid grid-cols-1 gap-2 lg:flex lg:flex-wrap">
                <!-- Ubicaciones -->
                <a href="{{ route('warehouses.index') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouses.index') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <span>Ubicaciones</span>
                </a>
                
                <!-- Inventario por Ubicaciones -->
                <a href="{{ route('warehouse-inventory.index') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.index') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-boxes mr-2"></i>
                    <span>Inventario</span>
                </a>
                
                <!-- Consultar por Ubicación -->
                <a href="{{ route('warehouse-inventory.por-ubicacion') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.por-ubicacion') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-search-location mr-2"></i>
                    <span>Consultar Ubicación</span>
                </a>
                
                <!-- Buscar por Código -->
                <a href="{{ route('warehouse-inventory.buscar-codigo') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.buscar-codigo') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-barcode mr-2"></i>
                    <span class="hidden sm:inline">Buscar por Código</span>
                    <span class="sm:hidden">Escanear</span>
                </a>
                
                <!-- Historial -->
                <a href="{{ route('warehouse-inventory.historial') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.historial') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-history mr-2"></i>
                    <span>Historial</span>
                </a>
                
                <!-- Próximos a Vencer -->
                <a href="{{ route('warehouse-inventory.proximos-vencer') }}" 
                   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('warehouse-inventory.proximos-vencer') ? 'bg-white text-blue-700 shadow-md' : 'bg-blue-800  hover:bg-blue-500' }}">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span>Próximos a Vencer</span>
                </a>
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

