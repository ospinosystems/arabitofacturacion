{{-- Accesos a los módulos WMS/TMS. Se incluye desde nav.blade.php para DICI y admin. --}}

<!-- Clasificación ABC -->
<a href="{{ route('wms.abc.panel') }}"
   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('wms.abc.panel') ? 'bg-white text-blue-700 shadow-md' : '' }}">
    <i class="fas fa-chart-pie mr-2"></i>
    <span>ABC</span>
</a>

<!-- Conteo cíclico por ubicación -->
<a href="{{ route('wms.conteo.panel') }}"
   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('wms.conteo.panel') ? 'bg-white text-blue-700 shadow-md' : '' }}">
    <i class="fas fa-clipboard-check mr-2"></i>
    <span>Conteo cíclico</span>
</a>

<!-- TMS: transporte y distribución -->
<a href="{{ route('tms.panel') }}"
   class="flex items-center justify-center lg:justify-start px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('tms.panel') ? 'bg-white text-blue-700 shadow-md' : '' }}">
    <i class="fas fa-truck mr-2"></i>
    <span>TMS</span>
</a>
