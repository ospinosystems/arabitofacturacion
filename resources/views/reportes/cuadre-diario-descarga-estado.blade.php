<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descarga masiva - Cuadre diario</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin: 0; padding: 20px; background: #f5f7fa; color: #333; }
        .container { max-width: 580px; margin: 0 auto; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .subtitle { color: #888; margin: 0 0 20px; font-size: 14px; }
        .box { padding: 20px; border-radius: 10px; margin: 16px 0; }
        .box.pending, .box.processing { background: #fffbeb; border: 1px solid #f59e0b; }
        .box.ready { background: #ecfdf5; border: 1px solid #10b981; }
        .box.failed { background: #fef2f2; border: 1px solid #ef4444; }
        .box strong { font-size: 16px; }
        p { margin: 0 0 10px; line-height: 1.5; }

        .progress-wrapper { margin: 16px 0; }
        .progress-bar-bg { background: #e5e7eb; border-radius: 999px; height: 24px; overflow: hidden; position: relative; }
        .progress-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #f59e0b, #f97316); transition: width .5s ease; min-width: 2%; }
        .progress-bar-fill.done { background: linear-gradient(90deg, #10b981, #059669); }
        .progress-text { position: absolute; top: 0; left: 0; right: 0; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #333; }

        .stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 6px; }
        .stat-card { flex: 1 1 120px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .stat-card .label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 2px; }
        .stat-card .value { font-size: 20px; font-weight: 700; color: #333; margin: 0; }
        .stat-card .value.sm { font-size: 14px; }

        .current-date { display: inline-block; background: #fef3c7; color: #92400e; font-size: 13px; padding: 4px 10px; border-radius: 6px; font-weight: 500; margin-top: 4px; }
        .elapsed { font-size: 13px; color: #888; margin-top: 6px; }

        .btn { display: inline-block; padding: 11px 22px; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 10px; transition: background .2s; }
        .btn-download { background: #10b981; }
        .btn-download:hover { background: #059669; color: #fff; }
        .btn-back { background: #6b7280; }
        .btn-back:hover { background: #4b5563; color: #fff; }
        .error-msg { font-family: monospace; font-size: 13px; margin-top: 8px; color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 6px; word-break: break-word; }
        .auto-refresh { font-size: 12px; color: #aaa; margin-top: 12px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #f59e0b; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; vertical-align: middle; margin-right: 6px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="container">
    <h1>Descarga masiva</h1>
    <p class="subtitle">Cuadre diario &mdash; PDFs por día (carpetas Mes/Día)</p>

    @if(session('info'))
        <p style="background:#dbeafe; padding: 10px; border-radius: 8px; color: #1e40af; font-size: 14px;">{{ session('info') }}</p>
    @endif

    <div class="box {{ $status }}">
        @if($status === 'pending' || $status === 'processing')
            <p><span class="spinner"></span><strong>{{ $status === 'pending' ? 'En cola, esperando inicio…' : 'Generando PDFs…' }}</strong></p>

            @if($total_fechas > 0)
                @php
                    $pct = round(($fechas_procesadas / $total_fechas) * 100);
                @endphp
                <div class="progress-wrapper">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: {{ $pct }}%"></div>
                        <div class="progress-text">{{ $fechas_procesadas }} / {{ $total_fechas }} días ({{ $pct }}%)</div>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-card">
                        <p class="label">Días procesados</p>
                        <p class="value">{{ $fechas_procesadas }} <span style="font-size:14px;font-weight:400;color:#888">/ {{ $total_fechas }}</span></p>
                    </div>
                    <div class="stat-card">
                        <p class="label">PDFs generados</p>
                        <p class="value">{{ number_format($total_pedidos) }}</p>
                    </div>
                    @if($started_at)
                        <div class="stat-card">
                            <p class="label">Tiempo transcurrido</p>
                            <p class="value sm" id="elapsed">{{ \Carbon\Carbon::parse($started_at)->diffForHumans(null, true, true) }}</p>
                        </div>
                    @endif
                </div>

                @if($fecha_actual)
                    <p style="margin:8px 0 0;font-size:13px;color:#666;">Procesando: <span class="current-date">{{ $fecha_actual }}</span></p>
                @endif
            @else
                <p style="font-size:14px;color:#888;">Esperando a que el proceso inicie…</p>
            @endif

            <p class="auto-refresh">Esta página se actualiza automáticamente cada 10 segundos.</p>
            <meta http-equiv="refresh" content="10">

        @elseif($status === 'ready')
            <p><strong>&#10003; Descarga lista</strong></p>
            <p>El archivo ZIP con todos los pedidos está listo para descargar.</p>

            @if($total_fechas > 0)
                <div class="stats">
                    <div class="stat-card">
                        <p class="label">Total días</p>
                        <p class="value">{{ $total_fechas }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="label">Total PDFs</p>
                        <p class="value">{{ number_format($total_pedidos) }}</p>
                    </div>
                    @if($started_at && $ready_at)
                        <div class="stat-card">
                            <p class="label">Duración total</p>
                            <p class="value sm">{{ \Carbon\Carbon::parse($started_at)->diffForHumans(\Carbon\Carbon::parse($ready_at), true, true) }}</p>
                        </div>
                    @endif
                </div>
            @endif

            <a href="{{ route('reportes.cuadre-diario.descarga-masiva.descargar', ['token' => $token]) }}" class="btn btn-download">Descargar ZIP</a>

        @elseif($status === 'failed')
            <p><strong>&#10007; Error al preparar la descarga</strong></p>
            @if(!empty($error))
                <pre class="error-msg">{{ $error }}</pre>
            @endif
        @else
            <p>Estado: {{ $status }}</p>
        @endif
    </div>

    <a href="{{ route('reportes.cuadre-diario') }}" class="btn btn-back">Volver al cuadre diario</a>
</div>
</body>
</html>
