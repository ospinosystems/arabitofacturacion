<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1080, height=1920, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/ico" href="{{ asset('images/icon.ico') }}">
    <title>Titanio - Autopago</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/autopago.css') }}" rel="stylesheet">
</head>
<body class="bg-gray-50 overflow-hidden font-kiosk antialiased">
    <div id="autopago-root" class="w-full h-full"></div>
    <script src="{{ asset('js/autopago.js') }}?v={{ time() }}"></script>
</body>
</html>
