<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta name="theme-color" content="#059669">
    <meta name="application-name" content="Vidriería Maradiaga ERP">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/icons/vidrieria-maradiaga-192.png">

    @vite(['resources/js/app.ts'])

    @inertiaHead
</head>

<body>
    @inertia
</body>
</html>
