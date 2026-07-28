<!doctype html>
<html lang="hy">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#0b1f33">
        <meta name="robots" content="noindex, nofollow">
        <meta name="govista-site-url" content="{{ config('app.frontend_url') }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/brand/govista-mark.png">
        <title>GoVista Admin</title>
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
