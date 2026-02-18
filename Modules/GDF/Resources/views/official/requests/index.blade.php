{{-- Modules/GDF/Resources/views/official/requests/index.blade.php --}}
@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Mis solicitudes')

@php
  $areaKey   = $areaKey ?? ($ctx['area'] ?? 'academic');
  $areaLabel = $areaKey === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';

  $tab = request('tab', 'all'); // gdf|sitrav|all
  $q   = $q ?? request('q','');

  $money = fn($v) => '$ '.number_format((float)$v, 0, ',', '.');

  // Rutas de creación
  $createGdfRoute     = route('gdf.instructor.requests.create');
  $createSitravRoute  = route('gdf.instructor.sitrav.programs.index'); // si SITRAV se crea desde programas
@endphp

@section('content')
<div class="container py-4">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-1">Mis solicitudes</h3>
      <div class="text-muted">
        Área: <b>{{ $areaLabel }}</b>
        <span class="mx-2">•</span>
        Muestra: <b>{{ strtoupper($tab) }}</b>
      </div>
    </div>

    <div class="d-flex gap-2">
      @if($tab === 'sitrav')
        <a class="btn btn-primary" href="{{ $createSitravRoute }}">
          <i class="bi bi-plus-circle me-1"></i> Crear viaje (SITRAV)
        </a>
      @elseif($tab === 'gdf')
        <a class="btn btn-primary" href="{{ $createGdfRoute }}">
          <i class="bi bi-plus-circle me-1"></i> Crear viaje (GDF)
        </a>
      @else
        <a class="btn btn-outline-primary" href="{{ $createGdfRoute }}">
          <i class="bi bi-plus-circle me-1"></i> Crear viaje (GDF)
        </a>
        <a class="btn btn-primary" href="{{ $createSitravRoute }}">
          <i class="bi bi-plus-circle me-1"></i> Crear viaje (SITRAV)
        </a>
      @endif
    </div>
  </div>

  {{-- Alerts --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if (($gate['message'] ?? null)) <div class="alert alert-info">{{ $gate['message'] }}</div> @endif

  {{-- Tabs + Search --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="tabs">
          <a class="btn btn-outline-secondary {{ $tab==='all' ? 'active' : '' }}"
             href="{{ request()->fullUrlWithQuery(['tab'=>'all','page'=>null]) }}">
            Todas
          </a>
          <a class="btn btn-outline-secondary {{ $tab==='gdf' ? 'active' : '' }}"
             href="{{ request()->fullUrlWithQuery(['tab'=>'gdf','page'=>null]) }}">
            GDF
          </a>
          <a class="btn btn-outline-secondary {{ $tab==='sitrav' ? 'active' : '' }}"
             href="{{ request()->fullUrlWithQuery(['tab'=>'sitrav','page'=>null]) }}">
            SITRAV
          </a>
        </div>

        <form method="GET" class="d-flex gap-2">
          <input type="hidden" name="tab" value="{{ $tab }}">
          <input type="text" name="q" class="form-control" style="min-width:260px"
                 placeholder="Buscar por #, origen o destino..." value="{{ $q }}">
          <button class="btn btn-outline-secondary" type="submit">
            <i class="bi bi-search"></i>
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- Tabla --}}
  <div class="card">
    <div class="card-body">

      @php
        $rows = $requests ?? collect();
        $isPaginator = is_object($rows) && method_exists($rows, 'links');
      @endphp

      @if(($isPaginator && $rows->count()===0) || (!$isPaginator && $rows->isEmpty()))
        <div class="text-muted">No hay solicitudes para mostrar.</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr class="text-muted">
                <th>#</th>
                <th>Módulo</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>Fechas</th>
                <th>Estado</th>
                <th class="text-end">Total</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $r)
                @php
                  $module = (string)($r->module ?? 'gdf');
                  $moduleBadge = $module === 'sitrav' ? 'info' : 'primary';
                  $total  = (float)($r->total_amount ?? 0);

                  // ✅ show route común
                  $showRoute = route('gdf.instructor.requests.show', $r->id);
                @endphp

                {{-- Filtro por tab en la vista (si el controller no filtra) --}}
                @if($tab === 'gdf' && $module !== 'gdf') @continue @endif
                @if($tab === 'sitrav' && $module !== 'sitrav') @continue @endif

                <tr>
                  <td class="fw-semibold">{{ $r->id }}</td>
                  <td>
                    <span class="badge bg-{{ $moduleBadge }}">{{ strtoupper($module) }}</span>
                    @if(!empty($r->source))
                      <div class="small text-muted">{{ $r->source }}</div>
                    @endif
                  </td>
                  <td>{{ $r->origin }}</td>
                  <td>{{ $r->destination }}</td>
                  <td>
                    <div class="fw-semibold">{{ optional($r->start_date)->format('Y-m-d') ?? \Carbon\Carbon::parse($r->start_date)->format('Y-m-d') }}</div>
                    <div class="small text-muted">{{ optional($r->end_date)->format('Y-m-d') ?? \Carbon\Carbon::parse($r->end_date)->format('Y-m-d') }}</div>
                  </td>
                  <td>
                    <span class="badge bg-{{ $r->status_badge ?? 'secondary' }}">
                      {{ $r->status_label ?? strtoupper((string)($r->status ?? 'N/A')) }}
                    </span>
                  </td>
                  <td class="text-end fw-semibold">{{ $money($total) }}</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="{{ $showRoute }}">Ver</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($isPaginator)
          <div class="mt-3">
            {{ $rows->appends(request()->query())->links() }}
          </div>
        @endif
      @endif

    </div>
  </div>

</div>
@endsection
