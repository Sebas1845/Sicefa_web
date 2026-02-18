{{-- Modules/GDF/Resources/views/coordination/dashboard.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | ' . ($title ?? 'Coordinación'))

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Facades\Route;

  $path = request()->path();
  $areaKey = $areaKey ?? (Str::contains($path, 'campesena') ? 'campesena' : 'academic');

  $isAllowed = function_exists('checkRol')
      ? ($areaKey === 'campesena'
          ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin'))
          : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support') || checkRol('gdf.superadmin')))
      : false;
  if(!$isAllowed) abort(403);

  $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic');
  $title = $title ?? ($areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica');

  $routeExists = fn(string $name) => Route::has($name);

  $reviewRoute = $routePrefix.'.review';

  $badgeStatus = function(?string $s){
      $s = strtolower(trim((string)$s));
      return match($s){
          'submitted'            => 'text-bg-warning',
          'returned'             => 'text-bg-secondary',
          'approved_by_treasury' => 'text-bg-info',
          'approved'             => 'text-bg-primary',
          'executed'             => 'text-bg-success',
          'rejected'             => 'text-bg-danger',
          'cancelled'            => 'text-bg-dark',
          'draft'                => 'text-bg-dark',
          default                => 'text-bg-secondary',
      };
  };

  $fmtDate = function($d){
      if(!$d) return '—';
      try { return Carbon::parse($d)->format('d/m/Y'); }
      catch (\Throwable $e) { return (string)$d; }
  };

  $kpis = $kpis ?? [
      'pending_coord' => 0,
      'returned'      => 0,
      'scheduled'     => 0,
  ];

  $quickRequests = $quickRequests ?? collect();
  $requests      = $requests ?? null;

  // ✅ Helpers de display (fallback robusto)
  $personName = function($r){
      if(isset($r->person_display) && $r->person_display) return $r->person_display;

      if(isset($r->person) && $r->person){
          $p = $r->person;
          $nm = trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? ''));
          if($nm !== '') return $nm;
      }

      return (string)($r->applicant_display ?? $r->applicant_name ?? $r->instructor_name ?? ('Persona #'.((int)($r->person_id ?? 0) ?: '—')));
  };

  $rubroName = function($r){
      if(isset($r->rubro_display) && $r->rubro_display) return $r->rubro_display;

      if(isset($r->budgetItem) && $r->budgetItem){
          return trim(($r->budgetItem->code ?? '').' - '.($r->budgetItem->name ?? 'Rubro'));
      }

      $bid = (int)($r->budget_item_id ?? 0);
      return $bid > 0 ? "Rubro #{$bid}" : '—';
  };

  $destName = function($r){
      if(isset($r->destination_display) && $r->destination_display) return $r->destination_display;

      // si tienes relaciones municipio/vereda en TravelRequest
      if(isset($r->village) && $r->village) return $r->village->name ?? ($r->destination ?? '—');
      if(isset($r->municipality) && $r->municipality) return $r->municipality->name ?? ($r->destination ?? '—');

      return (string)($r->destination ?? '—');
  };
@endphp

<div class="container py-4">

  {{-- Alerts --}}
  @foreach (['success','error','warning','info'] as $k)
    @if(session($k))
      <div class="alert alert-{{ $k==='error' ? 'danger' : $k }} mb-3">
        {{ session($k) }}
      </div>
    @endif
  @endforeach

  {{-- Header --}}
  <div class="gdf-card p-4 mb-4" style="background:rgba(255,255,255,.03);">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="text-white-50 small mb-1">GDF / {{ $title }}</div>
        <h3 class="fw-bold mb-1">Panel de Coordinación</h3>
        <div class="text-white-50">
          Área: <span class="fw-semibold text-white">{{ $areaKey==='campesena' ? 'Campesena' : 'Académica' }}</span>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-center" style="position:relative; z-index:5;">
        @if($routeExists($reviewRoute))
          <a class="btn btn-gdf-ghost" href="{{ route($reviewRoute) }}">
            <i class="bi bi-clipboard-check"></i> Revisión
          </a>
        @endif

        @if($routeExists($routePrefix.'.people.index'))
          <a class="btn btn-gdf-ghost" href="{{ route($routePrefix.'.people.index') }}">
            <i class="bi bi-people"></i> Personas
          </a>
        @endif

        @if($routeExists($routePrefix.'.motorcycles.index'))
          <a class="btn btn-gdf-ghost" href="{{ route($routePrefix.'.motorcycles.index') }}">
            <i class="bi bi-bicycle"></i> Motos
          </a>
        @endif
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
        <div class="text-white-50 small">Pendiente Coordinación</div>
        <div class="fs-3 fw-bold">{{ $kpis['pending_coord'] ?? 0 }}</div>
        @if($routeExists($reviewRoute))
          <div class="small text-white-50 mt-2">
            <a class="text-decoration-none text-white-50" href="{{ route($reviewRoute) }}">
              Abrir revisión <i class="bi bi-chevron-right"></i>
            </a>
          </div>
        @endif
      </div>
    </div>

    <div class="col-md-4">
      <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
        <div class="text-white-50 small">Devueltas</div>
        <div class="fs-3 fw-bold">{{ $kpis['returned'] ?? 0 }}</div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
        <div class="text-white-50 small">Agendadas</div>
        <div class="fs-3 fw-bold">{{ $kpis['scheduled'] ?? 0 }}</div>
      </div>
    </div>
  </div>

  {{-- Bandeja --}}
  <div class="row g-3">
    <div class="col-12">
      <div class="gdf-card p-4" style="background:rgba(255,255,255,.03);">

        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="fw-semibold">Bandeja rápida</div>
            <div class="text-white-50 small">Pendiente Coordinación (top 8)</div>
          </div>

          @if($routeExists($reviewRoute))
            <a class="btn btn-sm btn-gdf-ghost" href="{{ route($reviewRoute) }}">
              Ver revisión <i class="bi bi-chevron-right"></i>
            </a>
          @endif
        </div>

        @if(($quickRequests ?? collect())->isEmpty())
          <div class="mt-3 p-3 rounded" style="background:rgba(255,255,255,.04);">
            <div class="text-white-50">
              <i class="bi bi-check2-circle me-2"></i>
              No hay solicitudes pendientes.
            </div>
          </div>
        @else
          <div class="table-responsive mt-3">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:90px;">#</th>
                  <th style="width:260px;">Estado</th>
                  <th>Solicitante</th>
                  <th>Destino</th>
                  <th style="width:260px;">Rubro</th>
                  <th style="width:190px;">Fechas</th>
                  <th class="text-end" style="width:130px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                @foreach($quickRequests as $r)
                  @php $st = (string)($r->status ?? ''); @endphp
                  <tr>
                    <td class="fw-semibold">{{ $r->id }}</td>
                    <td>
                      <span class="badge {{ $badgeStatus($st) }}">
                        {{ \Modules\GDF\Entities\TravelRequest::statusLabel($st) }}
                      </span>
                      <div class="small text-white-50 mt-1">Código: {{ $st ?: '—' }}</div>
                    </td>
                    <td>{{ $personName($r) }}</td>
                    <td>{{ $destName($r) }}</td>
                    <td class="small text-white-50">{{ $rubroName($r) }}</td>
                    <td class="small text-white-50">
                      {{ $fmtDate($r->start_date ?? null) }} → {{ $fmtDate($r->end_date ?? null) }}
                    </td>
                    <td class="text-end">
                      @if($routeExists($reviewRoute))
                        <a class="btn btn-sm btn-gdf-ghost" href="{{ route($reviewRoute, ['q'=>$r->id]) }}">
                          Revisar <i class="bi bi-chevron-right"></i>
                        </a>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        {{-- Listado completo --}}
        @if($requests)
          <hr class="my-4" style="opacity:.15;">
          <div class="fw-semibold mb-2">Listado completo (paginado)</div>

          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:90px;">#</th>
                  <th style="width:260px;">Estado</th>
                  <th>Solicitante</th>
                  <th>Destino</th>
                  <th style="width:260px;">Rubro</th>
                  <th style="width:190px;">Fechas</th>
                  <th class="text-end" style="width:130px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                @foreach($requests as $r)
                  @php $st = (string)($r->status ?? ''); @endphp
                  <tr>
                    <td class="fw-semibold">{{ $r->id }}</td>
                    <td>
                      <span class="badge {{ $badgeStatus($st) }}">
                        {{ \Modules\GDF\Entities\TravelRequest::statusLabel($st) }}
                      </span>
                      <div class="small text-white-50 mt-1">Código: {{ $st ?: '—' }}</div>
                    </td>
                    <td>{{ $personName($r) }}</td>
                    <td>{{ $destName($r) }}</td>
                    <td class="small text-white-50">{{ $rubroName($r) }}</td>
                    <td class="small text-white-50">
                      {{ $fmtDate($r->start_date ?? null) }} → {{ $fmtDate($r->end_date ?? null) }}
                    </td>
                    <td class="text-end">
                      @if($routeExists($reviewRoute))
                        <a class="btn btn-sm btn-gdf-ghost" href="{{ route($reviewRoute, ['q'=>$r->id]) }}">
                          Revisar <i class="bi bi-chevron-right"></i>
                        </a>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            {{ $requests->links() }}
          </div>
        @endif

      </div>
    </div>
  </div>

</div>
@endsection
