<!DOCTYPE html>
<html>
<head>
    <title>Sincronización de Inventario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .stats {
            margin-top: 20px;
        }
        .stat-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sincronización de Inventario</h1>
        
        @if($success)
            <h2 class="success">Sincronización Completada</h2>
            
            <div class="stats">
                <h3>Resumen de Operaciones</h3>
                
                <div class="stat-item">
                    <strong>Tareas Procesadas:</strong>
                    <ul>
                        <li>Exitosas: {{ $stats['tasks_success'] }}</li>
                        <li>Con Error: {{ $stats['tasks_error'] }}</li>
                    </ul>
                </div>
                
                <div class="stat-item">
                    <strong>Productos Actualizados:</strong>
                    <ul>
                        <li>Modificados: {{ $stats['inventory_updated'] }}</li>
                        <li>Sin Cambios: {{ $stats['inventory_unchanged'] }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-4">
                <h5>Reportes Detallados:</h5>
                <div class="list-group">
                    <a href="{{ route('reports.view', 'tasks') }}" class="list-group-item list-group-item-action">
                        Ver Reporte de Tareas
                    </a>
                    <a href="{{ route('reports.view', 'global') }}" class="list-group-item list-group-item-action">
                        Ver Reporte de Inventario
                    </a>
                </div>
            </div>
        @else
            <h2 class="error">Error en la Sincronización</h2>
            <p>{{ $error }}</p>
        @endif
    </div>
</body>
</html> 