{{-- Modules/GDF/Resources/views/coordination/review.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | ' . ($title ?? 'Coordinación') . ' - Revisión')

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  $path = request()->path();
  $areaKey = $areaKey ?? (str_contains($path, 'campesena') ? 'campesena' : 'academic');

  $isOk = function_exists('checkRol')
    ? ($areaKey === 'campesena'
        ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin'))
        : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support') || checkRol('gdf.superadmin')))
    : false;
  if(!$isOk) abort(403);

  $routePrefix = $routePrefix ?? ('gdf.' . ($areaKey === 'campesena' ? 'campesena' : 'academic'));
  $title = $title ?? ($areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica');

  $q   = $q ?? request('q', '');
  $tab = $tab ?? request('tab', 'all');
  if(!in_array($tab, ['all','gdf','sitrav'], true)) $tab = 'all';

  $requests = $requests ?? collect();

  $gdfCount    = $gdfCount ?? null;
  $sitravCount = $sitravCount ?? null;
  $allCount    = $allCount ?? (($gdfCount ?? 0) + ($sitravCount ?? 0));

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');
  $isPaginator = fn($x) => is_object($x) && method_exists($x, 'links') && method_exists($x, 'appends');

  $hasPeopleIndex  = Route::has($routePrefix.'.people.index');
  $hasPeopleCreate = Route::has($routePrefix.'.people.create');

  $hasMotosIndex = Route::has($routePrefix.'.motorcycles.index');
  $hasQueue      = Route::has($routePrefix.'.motorcycles.queue');
  $hasAssign     = Route::has($routePrefix.'.motorcycles.assign.create');
  $hasQuota      = Route::has($routePrefix.'.motorcycles.quota_status');

  $hasReviewShow = Route::has($routePrefix.'.review.show');

  $personName = function($r){
    if(isset($r->person_display) && trim((string)$r->person_display) !== '') return $r->person_display;
    if(isset($r->person) && $r->person){
      $p = $r->person;
      $nm = trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? ''));
      if($nm !== '') return $nm;
    }
    if(!empty($r->applicant_name)) return $r->applicant_name;
    if(!empty($r->instructor_name)) return $r->instructor_name;
    $pid = (int)($r->person_id ?? 0);
    return $pid > 0 ? "Persona #{$pid}" : '—';
  };

  $rubroName = function($r){
    if(isset($r->rubro_display) && trim((string)$r->rubro_display) !== '') return $r->rubro_display;
    if(isset($r->budgetItem) && $r->budgetItem){
      return trim(($r->budgetItem->code ?? '').' - '.($r->budgetItem->name ?? 'Rubro'));
    }
    $bid = (int)($r->budget_item_id ?? 0);
    return $bid > 0 ? "Rubro #{$bid}" : '—';
  };

  $destName = function($r){
    if(isset($r->village) && $r->village && !empty($r->village->name)) return $r->village->name;
    if(isset($r->municipality) && $r->municipality && !empty($r->municipality->name)) return $r->municipality->name;
    return (string)($r->destination ?? '—');
  };

  $tabLabel = fn($t) => $t === 'gdf' ? 'GDF' : ($t === 'sitrav' ? 'SITRAV' : 'TODAS');
@endphp

<div class="container py-4">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / {{ $title }}</div>
      <h4 class="mb-0">{{ $title }}</h4>
      <small class="text-muted">Pendientes de Coordinación (aprobadas por Tesorería).</small>
    </div>

    <div class="d-flex flex-wrap gap-2">
      @if($hasPeopleIndex)<a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.people.index') }}">Personas</a>@endif
      @if($hasPeopleCreate)<a class="btn btn-outline-primary" href="{{ route($routePrefix.'.people.create') }}">+ Registrar persona</a>@endif
      @if($hasMotosIndex)<a class="btn btn-outline-primary" href="{{ route($routePrefix.'.motorcycles.index') }}">Motos</a>@endif
      @if($hasQueue)<a class="btn btn-outline-primary" href="{{ route($routePrefix.'.motorcycles.queue') }}">Cola Apoyo</a>@endif
      @if($hasAssign)
        <a class="btn btn-primary" href="{{ route($routePrefix.'.motorcycles.assign.create') }}">+ Asignación directa</a>
      @endif
      @if($hasQuota)
        <a class="btn btn-outline-warning" href="{{ route($routePrefix.'.motorcycles.quota_status') }}">Cupo del área</a>
      @endif
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k)) <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div> @endif
  @endforeach

  @if ($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link {{ $tab==='all'?'active':'' }}" href="{{ route($routePrefix.'.review', ['tab'=>'all','q'=>$q]) }}">
        Todas @if($allCount!==null)<span class="badge bg-secondary ms-1">{{ $allCount }}</span>@endif
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='gdf'?'active':'' }}" href="{{ route($routePrefix.'.review', ['tab'=>'gdf','q'=>$q]) }}">
        GDF @if($gdfCount!==null)<span class="badge bg-secondary ms-1">{{ $gdfCount }}</span>@endif
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='sitrav'?'active':'' }}" href="{{ route($routePrefix.'.review', ['tab'=>'sitrav','q'=>$q]) }}">
        SITRAV @if($sitravCount!==null)<span class="badge bg-secondary ms-1">{{ $sitravCount }}</span>@endif
      </a>
    </li>
  </ul>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route($routePrefix.'.review') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Buscar</label>
            <input name="q" value="{{ $q }}" class="form-control" placeholder="Origen, destino, ID, cédula, radicado...">
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-primary">Filtrar</button>
          </div>
          <div class="col-md-4 text-muted small">
            Tab: <span class="fw-semibold">{{ $tabLabel($tab) }}</span>
            · Área: <span class="fw-semibold">{{ $areaKey }}</span>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div class="fw-bold">Solicitudes {{ $tabLabel($tab) }}</div>
      <div class="text-muted small">Área: {{ $areaKey }}</div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px">ID</th>
            <th>Detalle</th>
            <th style="width:220px">Fechas</th>
            <th style="width:160px" class="text-end">Total</th>
            <th style="width:130px">Acciones</th>
          </tr>
        </thead>

        <tbody>
        @forelse($requests as $r)
          @php
            $sol  = $personName($r);
            $rub  = $rubroName($r);
            $dst  = $destName($r);
            $orig = (string)($r->origin ?? '—');
            $rt   = (string)($r->request_type ?? '—');

            $mod  = strtolower((string)($r->module ?? 'gdf'));
            $labelMod = strtoupper($mod ?: 'GDF');
            $badgeMod = $mod === 'sitrav' ? 'bg-info' : 'bg-success';

            $rowTotal = (float)($r->computed_total ?? ($r->total_amount ?? 0));

            // ✅ ya vienen del controller
            $statusLabel = (string)($r->status_display ?? 'N/D');
            $statusCode  = (string)($r->status_code ?? ($r->status ?? '—'));
            $statusBadge = (string)($r->status_badge ?? 'text-bg-secondary');
          @endphp

          <tr>
            <td class="fw-semibold">#{{ $r->id }}</td>

            <td>
              <div class="fw-semibold">{{ $orig }} → {{ $dst }}</div>

              <small class="text-muted d-block">
                Solicitante: <span class="fw-semibold text-dark">{{ $sol }}</span>
              </small>

              <small class="text-muted d-block">
                Rubro: <span class="fw-semibold text-dark">{{ $rub }}</span>
              </small>

              <small class="text-muted d-block">
                {{ $rt }}
                · <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                <span class="text-muted small ms-1">({{ $statusCode }})</span>
                @if($tab==='all') · <span class="badge {{ $badgeMod }}">{{ $labelMod }}</span> @endif
              </small>
            </td>

            <td><div class="small">{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</div></td>
            <td class="text-end fw-semibold">{{ $fmtMoney($rowTotal) }}</td>

            <td>
              @if($hasReviewShow)
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route($routePrefix.'.review.show', ['travelRequest' => $r->id, 'tab'=>$tab, 'q'=>$q]) }}">
                  Ver
                </a>
              @else
                <span class="text-muted small">Ruta no registrada</span>
              @endif
            </td>
          </tr>

        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No hay solicitudes.</td></tr>
        @endforelse
        </tbody>

      </table>
    </div>

    <div class="card-footer bg-white">
      @if($isPaginator($requests))
        {{ $requests->appends(request()->query())->links() }}
      @else
        <span class="text-muted small">Sin paginación.</span>
      @endif
    </div>
  </div>

</div>
@endsection
