<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Spendly</title>

    </head>
    <body>
        <main>
            <p>Spendly</p>
            <h1>The dashboard is available in Filament.</h1>
            <a href="{{ url('/spendly') }}">Open dashboard</a>
        </main>
    </body>
</html>
