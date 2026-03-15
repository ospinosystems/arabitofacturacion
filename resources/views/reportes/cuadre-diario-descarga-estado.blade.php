<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descarga masiva - Cuadre diario</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 560px; }
        .box { padding: 20px; border-radius: 8px; margin: 20px 0; }
        .box.pending, .box.processing { background: #fff3cd; border: 1px solid #ffc107; }
        .box.ready { background: #d4edda; border: 1px solid #28a745; }
        .box.failed { background: #f8d7da; border: 1px solid #dc3545; }
        .btn { display: inline-block; padding: 10px 20px; background: #28a745; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; margin-top: 10px; }
        .btn:hover { background: #218838; color: #fff; }
        .btn-back { background: #6c757d; }
        .btn-back:hover { background: #5a6268; color: #fff; }
        h1 { margin-bottom: 8px; }
        p { margin: 0 0 10px; }
        .error-msg { font-family: monospace; font-size: 13px; margin-top: 8px; color: #721c24; }
    </style>
</head>
<body>
    <h1>Descarga masiva</h1>
    <p style="color:#666;">Cuadre diario — PDFs por día (carpetas Mes/Día)</p>

    @if(session('info'))
        <p style="background:#e7f3ff; padding: 10px; border-radius: 6px;">{{ session('info') }}</p>
    @endif

    <div class="box {{ $status }}">
        @if($status === 'pending' || $status === 'processing')
            <p><strong>Preparando la descarga…</strong></p>
            <p>Se están generando los PDFs en segundo plano. Puede tardar varios minutos (incluso más de 30 min si hay muchos días).</p>
            <p>Esta página se actualiza cada 15 segundos. También puede recargar manualmente.</p>
            <meta http-equiv="refresh" content="15">
        @elseif($status === 'ready')
            <p><strong>Descarga lista</strong></p>
            <p>Puede descargar el archivo ZIP con todos los pedidos organizados en carpetas Mes/Día.</p>
            <a href="{{ route('reportes.cuadre-diario.descarga-masiva.descargar', ['token' => $token]) }}" class="btn">Descargar ZIP</a>
        @elseif($status === 'failed')
            <p><strong>Error al preparar la descarga</strong></p>
            @if(!empty($error))
                <pre class="error-msg">{{ $error }}</pre>
            @endif
        @else
            <p>Estado: {{ $status }}</p>
        @endif
    </div>

    <a href="{{ route('reportes.cuadre-diario') }}" class="btn btn-back">Volver al cuadre diario</a>
</body>
</html>
