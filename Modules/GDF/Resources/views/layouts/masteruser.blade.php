<!DOCTYPE html>
<html lang="es">
<head>
    @include('gdf::layouts.partials.head')
    @stack('head')
</head>
<body class="gdf-public" data-theme="dark">

@include('gdf::layouts.partials.navbar')
<main class="py-5" id="info">
    <div class="container">
        @yield('content')
    </div>
</main>

@include('gdf::layouts.partials.footer')
@include('gdf::layouts.partials.scripts')

@stack('scripts')
</body>
</html>
