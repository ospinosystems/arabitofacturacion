<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e3a5f">
    <link rel="icon" type="image/ico" href="{{ asset('images/icon.ico') }}">
    <title>PPR - Pendiente por Retirar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; -webkit-overflow-scrolling: touch; }
        body { position: fixed; inset: 0; }
        #ppr-root {
            min-height: 100%;
            min-height: 100dvh;
            height: 100%;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: auto;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
            box-sizing: border-box;
        }
        .safe-area-padding { padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right); padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="bg-gray-100 antialiased" style="font-family: 'DM Sans', sans-serif;">
    <div id="ppr-root"></div>
    <script src="{{ asset('js/ppr.js') }}?v={{ time() }}"></script>
</body>
</html>
