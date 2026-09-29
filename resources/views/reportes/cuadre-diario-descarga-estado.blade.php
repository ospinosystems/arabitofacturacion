<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Descarga masiva - Reporte de ventas</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin: 0; padding: 20px; background: #f5f7fa; color: #333; }
        .container { max-width: 760px; margin: 0 auto; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .subtitle { color: #888; margin: 0 0 20px; font-size: 14px; }
        .box { padding: 20px; border-radius: 10px; margin: 16px 0; background: #fffbeb; border: 1px solid #f59e0b; }
        .box.ready { background: #ecfdf5; border-color: #10b981; }
        .box.failed { background: #fef2f2; border-color: #ef4444; }
        p { margin: 0 0 10px; line-height: 1.5; }
        .progress-bar-bg { background: #e5e7eb; border-radius: 999px; height: 24px; overflow: hidden; position: relative; margin: 14px 0; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #f97316); transition: width .5s ease; }
        .progress-bar-fill.done { background: linear-gradient(90deg, #10b981, #059669); }
        .progress-text { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; font-size: 14px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 12px; text-transform: uppercase; color: #666; }
        .btn { display: inline-block; padding: 7px 14px; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px; background: #10b981; }
        .btn:hover { background: #059669; }
        .btn-back { background: #6b7280; padding: 10px 20px; margin-top: 10px; }
        .pendiente { color: #999; font-size: 13px; }
        .nota { font-size: 13px; color: #666; }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #f59e0b; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; vertical-align: middle; margin-right: 6px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="container">
    <h1>Descarga masiva</h1>
    <p class="subtitle">
        {{ $resumen['dias'] }} {{ $resumen['dias'] === 1 ? 'día' : 'días' }} ({{ $resumen['desde'] }} → {{ $resumen['hasta'] }}) ·
        {{ number_format($resumen['total']) }} facturas · lotes de {{ number_format($resumen['tamano']) }} PDFs (carpetas Mes/Día)
    </p>

    <div class="box" id="caja">
        <p id="titulo"><span class="spinner"></span><strong>Generando los PDFs…</strong></p>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" id="barra" style="width: 0%"></div>
            <div class="progress-text" id="texto">0 / {{ number_format($resumen['total']) }}</div>
        </div>
        <p class="nota" id="nota">Mantenga esta página abierta: cada lote aparece abajo para descargarlo en cuanto está listo.
            Si la cierra, al volver a abrir este enlace continúa donde quedó.</p>
    </div>

    <table>
        <thead><tr><th>Lote</th><th>Facturas</th><th>Fechas</th><th></th></tr></thead>
        <tbody id="lotes"></tbody>
    </table>

    <a href="{{ route('reportes.cuadre-diario') }}" class="btn btn-back">Volver al reporte</a>
</div>
<script>
(function () {
    var resumen = @json($resumen);
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var fmt = new Intl.NumberFormat('es-VE');

    function pintar(r) {
        var pct = r.total ? Math.floor(r.procesados * 100 / r.total) : 100;
        var barra = document.getElementById('barra');
        barra.style.width = pct + '%';
        barra.classList.toggle('done', r.terminado);
        document.getElementById('texto').textContent = fmt.format(r.procesados) + ' / ' + fmt.format(r.total) + ' (' + pct + '%)';
        var filas = '';
        for (var n = 1; n <= r.total_lotes; n++) {
            var l = r.lotes.find(function (x) { return x.n === n; });
            var fechas = l && l.desde ? (l.desde === l.hasta ? l.desde : l.desde + ' → ' + l.hasta) : '';
            var accion = l && l.url
                ? '<a class="btn" href="' + l.url + '">Descargar ZIP</a>'
                : '<span class="pendiente">' + (l ? 'generando…' : 'en espera') + '</span>';
            filas += '<tr><td>' + n + ' de ' + r.total_lotes + '</td><td>' + (l ? fmt.format(l.pedidos) : '') + '</td><td>' + fechas + '</td><td>' + accion + '</td></tr>';
        }
        document.getElementById('lotes').innerHTML = filas;
        if (r.terminado) {
            document.getElementById('caja').className = 'box ready';
            document.getElementById('titulo').innerHTML = '<strong>&#10003; Todos los lotes están listos</strong>';
            document.getElementById('nota').textContent = r.errores ? (r.errores + ' pedido(s) no se pudieron generar.') : 'Descargue cada lote con su botón.';
        }
    }

    function siguiente() {
        fetch(resumen.procesar, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
            .then(function (x) {
                pintar(x.j);
                if (x.j.terminado) return;
                setTimeout(siguiente, x.ok && !x.j.ocupado ? 200 : 5000);
            })
            .catch(function () { setTimeout(siguiente, 5000); });
    }

    pintar(resumen);
    if (!resumen.terminado) siguiente();
})();
</script>
</body>
</html>
