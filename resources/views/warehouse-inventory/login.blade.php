@extends('layouts.app')
@section('tittle', "Login - Warehouse")
@section('content')
    <script>
        // Limpiar localStorage al cargar la página de login
        localStorage.removeItem('user_data');
        localStorage.removeItem('session_token');
    </script>
    <script src="{{ asset('js/index.js') }}?v={{ time() }}&nocache=1"></script>
@endsection
