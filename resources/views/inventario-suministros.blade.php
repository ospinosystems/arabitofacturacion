@extends('layouts.app')
@section('title', 'Inventario de Suministros')
@section('content')
    <script src="{{ asset('js/inventario-suministros.js') }}?v={{ time() }}&nocache=1"></script>
@endsection
