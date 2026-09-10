<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Temporary Document Store')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
</head>
<body>
    <div class="container py-4">
        @yield('content')
    </div>
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    @yield('scripts')
</body>
</html>
