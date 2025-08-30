<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

         <!-- Icon -->
         <link rel="shortcut icon" href="/img/icon/favicon.ico" type="image/x-icon">
         <link rel="apple-touch-icon" href="/img/icon/apple-touch-icon.png">
         <link rel="icon" type="image/png" sizes="16x16"  href="/img/icon/favicon-16x16.png">
         <link rel="icon" type="image/png" sizes="32x32"  href="/img/icon/favicon-32x32.png">
         <link rel="icon" type="image/png" sizes="192x192"  href="/img/icon/android-chrome-192x192.png">
         <link rel="icon" type="image/png" sizes="512x512"  href="/img/icon/android-chrome-512x512.png">
         <link rel="manifest" href="/img/icon/manifest.json">

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
