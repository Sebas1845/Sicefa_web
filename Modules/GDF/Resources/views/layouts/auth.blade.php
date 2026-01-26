<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SICEFA')</title>

    {{-- Bootstrap (o tu CSS base) --}}
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="bg-dark text-light">

    <main class="container py-5" style="max-width: 520px;">
        <div class="mb-4 text-center">
            <h3 class="fw-bold">SICEFA</h3>
            <div class="text-white-50">Acceso / Seguridad</div>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>

    {{-- Bootstrap bundle (IMPORTANTE para modales y JS) --}}
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>