@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">
    <!-- Header -->
    <div class="mb-3 sm:mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-boxes text-blue-500 mr-2"></i>
                <span class="hidden sm:inline">Inventario por Ubicaciones</span>
                <span class="sm:hidden">Inventario</span>
            </h1>
            <div class="flex gap-2">
                <button type="button" 
                        onclick="abrirModalTraslado()"
                        class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition">
                    <i class="fas fa-exchange-alt mr-2"></i>
                    <span class="hidden sm:inline">Trasladar Producto</span>
                    <span class="sm:hidden">Trasladar</span>
                </button>
            </div>
        </div>
    </div>

    @include('warehouse-inventory.partials.tabla-unificada')
</div>

@endsection

