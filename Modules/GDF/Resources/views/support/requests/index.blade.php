{{-- Modules/GDF/Resources/views/support/requests/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Bandeja Apoyo')

@section('content')
@php
  use Illuminate\Support\Str;

  $routePrefix = $routePrefix ?? 'gdf.support.academic';
  $tab  = $tab ?? (string) request('tab','pending');
  $mod  = $mod ?? (string) request('module','all');
  $year = $year ?? (int) request('year', now()->year);
  $q    = $q ?? (string) request('q','');

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  $tabLink = fn($t) => route($routePrefix.'.requests.index', array_merge(request()->query(), ['tab'=>$t]));
  $pill = fn($active) => $active ? 'btn btn-gdf-primary' : 'btn btn-gdf-ghost';

  $badgeModule = fn($m) => (strtolower((string)$m) === 'sitrav') ? 'text-bg-info' : 'text-bg-secondary';

  $statusBadge = function($s){
    $s = strtolower((string)$s);
    return match(true){
      Str::contains($s,'returned') => 'text-bg-secondary',
      Str::contains($s,'rejected') => 'text-bg-danger',
      Str::contains($s,'submitted') => 'text-bg-warning',
      Str::contains($s,'pending_support') => 'text-bg-warning',
      Str::contains($s,'pending_treasury') => 'text-bg-info',
      Str::contains($s,'treasury') => 'text-bg-info',
      Str::contains($s,'approved') => 'text-bg-primary',
      Str::contains($s,'confirmed') => 'text-bg-success',
      Str::contains($s,'executed') => 'text-bg-success',
      Str::contains($s,'completed') => 'text-bg-success',
      default => 'text-bg-dark',
    };
  };

  $statusLabel = function($s){
    $s = (string)$s;
    $map = [
      'draft' => 'Borrador',
      'submitted' => 'Pendiente Apoyo',
      'pending_support' => 'Pendiente Apoyo',
      'returned' => 'Devuelta',
      'rejected' => 'Rechazada',
      'approved' => 'Pend. Tesorería',
      'pending_treasury' => 'Pend. Tesorería',
      'approved_by_treasury' => 'Pend. Subdirección',
      'confirmed' => 'Confirmada',
      'executed' => 'Ejecutada',
      'completed' => 'Finalizada',
    ];
    return $map[$s] ?? ucwords(str_replace('_',' ', $s));
  };

  $kpis = $kpis ?? ['pending'=>0,'returned'=>0,'treasury'=>0,'executed'=>0];
@endphp

<style>
  .glass{
    background: rgba(255,255,255,.035);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 14px;
    overflow: hidden;
  }
  .soft-muted{ color: rgba(255,255,255,.65); }

  .kpi{
    border-radius: 14px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
  }
  .kpi .kpi-num{ font-size: 1.4rem; font-weight: 900; line-height: 1; }
  .kpi .kpi-lbl{ font-size: .8rem; color: rgba(255,255,255,.65); }

  /* ======= TABLA SIN SCROLL (layout fixed) ======= */
  .gdf-table{
    width:100%;
    table-layout: fixed;            /* CLAVE: obliga a que quepa */
    font-size:.82rem;               /* más compacta */
    margin:0;
  }
  .gdf-table th{
    color: rgba(255,255,255,.78);
    font-weight: 800;
    padding: .45rem .55rem;
    white-space: nowrap;
    border-bottom: 1px solid rgba(255,255,255,.08);
  }
  .gdf-table td{
    padding: .45rem .55rem;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-top: 1px solid rgba(255,255,255,.06);
  }

  /* Columnas (porcentaje) para que NO reviente */
  .c-id{ width: 8%; }
  .c-mod{ width: 8%; }
  .c-status{ width: 14%; }
  .c-person{ width: 18%; }
  .c-route{ width: 26%; }
  .c-dates{ width: 16%; }
  .c-act{ width: 10%; }

  .mini-muted{ font-size:.76rem; color: rgba(255,255,255,.60); }
  .truncate{ overflow:hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* En pantallas medianas: ocultar cosas no críticas */
  @media (max-width: 992px){
    .gdf-table{ font-size:.78rem; }
    .hide-md{ display:none; }
    .c-person{ width: 24%; }
    .c-route{ width: 36%; }
    .c-act{ width: 14%; }
  }
</style>

<div class="container py-4">

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <div class="text-white-50 small">GDF · Apoyo</div>
      <h3 class="fw-bold mb-1">Bandeja de Solicitudes</h3>
      <div class="soft-muted small">
        Incluye solicitudes de <span class="text-white fw-semibold">GDF</span> y <span class="text-white fw-semibold">SITRAV</span>
      </div>
    </div>
    <a class="btn btn-gdf-ghost" href="{{ route($routePrefix.'.dashboard') }}">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
  </div>

  {{-- KPIs --}}
  <div class="row g-2 mb-3">
    <div class="col-6 col-lg-3"><div class="kpi p-3"><div class="kpi-num">{{ (int)($kpis['pending'] ?? 0) }}</div><div class="kpi-lbl">Pendiente Apoyo</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi p-3"><div class="kpi-num">{{ (int)($kpis['returned'] ?? 0) }}</div><div class="kpi-lbl">Devueltas</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi p-3"><div class="kpi-num">{{ (int)($kpis['treasury'] ?? 0) }}</div><div class="kpi-lbl">Pend. Tesorería</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi p-3"><div class="kpi-num">{{ (int)($kpis['executed'] ?? 0) }}</div><div class="kpi-lbl">Ejecutadas</div></div></div>
  </div>

  {{-- Filtros --}}
  <form class="glass p-3 mb-3" method="GET" action="{{ route($routePrefix.'.requests.index') }}">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label text-white-50 small mb-1">Año</label>
        <input type="number" class="form-control" name="year" value="{{ $year }}" min="2020" max="{{ now()->year + 1 }}">
      </div>
      <div class="col-md-3">
        <label class="form-label text-white-50 small mb-1">Módulo</label>
        <select class="form-select" name="module">
          <option value="all" @selected($mod==='all')>GDF + SITRAV</option>
          <option value="gdf" @selected($mod==='gdf')>Solo GDF</option>
          <option value="sitrav" @selected($mod==='sitrav')>Solo SITRAV</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label text-white-50 small mb-1">Buscar</label>
        <input class="form-control" name="q" value="{{ $q }}" placeholder="ID, radicado, origen, destino">
      </div>
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-gdf-primary w-100" type="submit">
          <i class="bi bi-search"></i> Filtrar
        </button>
        <a class="btn btn-gdf-ghost" href="{{ route($routePrefix.'.requests.index', ['tab'=>$tab]) }}" title="Limpiar filtros">
          <i class="bi bi-x-lg"></i>
        </a>
      </div>
    </div>
  </form>

  {{-- Tabs --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="{{ $pill($tab==='pending') }}"  href="{{ $tabLink('pending') }}">Pendiente Apoyo <span class="ms-1 badge text-bg-dark">{{ (int)($kpis['pending'] ?? 0) }}</span></a>
    <a class="{{ $pill($tab==='returned') }}" href="{{ $tabLink('returned') }}">Devueltas <span class="ms-1 badge text-bg-dark">{{ (int)($kpis['returned'] ?? 0) }}</span></a>
    <a class="{{ $pill($tab==='treasury') }}" href="{{ $tabLink('treasury') }}">Pend. Tesorería <span class="ms-1 badge text-bg-dark">{{ (int)($kpis['treasury'] ?? 0) }}</span></a>
    <a class="{{ $pill($tab==='executed') }}" href="{{ $tabLink('executed') }}">Ejecutadas <span class="ms-1 badge text-bg-dark">{{ (int)($kpis['executed'] ?? 0) }}</span></a>
    <a class="{{ $pill($tab==='all') }}"      href="{{ $tabLink('all') }}">Todas</a>
  </div>

  {{-- Tabla --}}
  <div class="glass p-0">
    <table class="table table-dark table-hover align-middle gdf-table">
      <thead>
        <tr>
          <th class="c-id">#</th>
          <th class="c-mod">Módulo</th>
          <th class="c-status">Estado</th>
          <th class="c-person hide-md">Persona</th>
          <th class="c-route">Ruta</th>
          <th class="c-dates">Fechas</th>
          <th class="c-act text-end">Acción</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $r)
          @php
            $person = $r->person->full_name ?? $r->person_name ?? '—';
            $origin = $r->origin ?? '—';
            $dest   = $r->destination ?? '—';
            $dates  = trim(($r->start_date ?? '—').' → '.($r->end_date ?? '—'));
          @endphp
          <tr>
            <td class="c-id fw-semibold">{{ $r->id }}</td>
            <td class="c-mod">
              <span class="badge {{ $badgeModule($r->module ?? '') }}">{{ strtoupper($r->module ?? '-') }}</span>
            </td>
            <td class="c-status">
              <span class="badge {{ $statusBadge($r->status ?? '') }}">{{ $statusLabel($r->status ?? '') }}</span>
            </td>
            <td class="c-person hide-md" title="{{ $person }}">
              <div class="truncate">{{ $person }}</div>
              <div class="mini-muted truncate" title="{{ $r->radicado_code ?? '' }}">{{ $r->radicado_code ? ('Rad: '.$r->radicado_code) : '' }}</div>
            </td>
            <td class="c-route" title="{{ $origin.' → '.$dest }}">
              <div class="truncate">{{ $origin }} → {{ $dest }}</div>
              <div class="mini-muted truncate">
                {{ $r->area->name ?? '' }}{{ ($r->budgetItem?->code ?? null) ? ' · '.$r->budgetItem->code : '' }}
              </div>
            </td>
            <td class="c-dates" title="{{ $dates }}">
              <div class="truncate">{{ $dates }}</div>
              <div class="mini-muted truncate">{{ $fmtMoney($r->total_amount ?? 0) }}</div>
            </td>
            <td class="c-act text-end">
              <a class="btn btn-sm btn-gdf-ghost" href="{{ route($routePrefix.'.requests.show', $r->id) }}">
                Abrir <i class="bi bi-chevron-right"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center soft-muted py-4">No hay solicitudes con ese filtro.</td>
          </tr>
        @endforelse
      </tbody>
    </table>

    <div class="p-3">
      {{ $requests->links() }}
    </div>
  </div>

</div>
@endsection
