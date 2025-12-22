<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>@yield('title', 'GDF | Gestión de Traslados')</title>
<link rel="icon" href="{{ asset('modules/gdf/images/log/logo_sena.png') }}" type="image/x-icon">

{{-- Bootstrap + Icons --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

{{-- Fonts --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

{{-- UI base GDF --}}
<link rel="stylesheet" href="{{ asset('modules/gdf/css/gdf-ui.css') }}">

@stack('styles')
