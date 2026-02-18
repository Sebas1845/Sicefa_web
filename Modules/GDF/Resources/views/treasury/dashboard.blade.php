@extends('gdf::layouts.masteruser')
@section('title','GDF | Tesorería - Dashboard')

@section('content')
@php
  $isTreasury = function_exists('checkRol') ? (checkRol('gdf.treasury') || checkRol('gdf.superadmin')) : false;
  if(!$isTreasury){ abort(403); }

  $area   = $area ?? request('area','all');       // academic|campesena|all
  $module = $module ?? request('module','all');   // gdf|sitrav|all
  $q      = $q ?? request('q','');
  $year   = $year ?? (int)request('year', now()->year);

  $fmt = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');

  $areaTabs = [
    'all'      => 'Todas',
    'academic' => 'Coordinación Académica',
    'campesena'=> 'Campesena',
  ];

  $moduleTabs = [
    'all'    => 'Todos',
    'gdf'    => 'GDF',
    'sitrav' => 'SITRAV',
  ];
@endphp

<style>
  .hover-card{position:relative}
  .budget-pop{
    display:none; position:absolute; z-index:20; top:100%; left:0;
    min-width: 360px; max-width: 520px;
    background:#fff; border:1px solid rgba(0,0,0,.12);
    border-radius:12px; padding:12px; box-shadow: 0 10px 22px rgba(0,0,0,.12);
  }
  .hover-card:hover .budget-pop{display:block}
  .pill{display:inline-block;padding:.18rem .5rem;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:#f8f9fa;font-size:.85rem}
</style>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-0">Tesorería</h4>
      <small class="text-muted">Bandeja · Vigencia {{ $year }}</small>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k)) <div class="alert alert-{{ $type }}">{{ session($k) }}</div> @endif
  @endforeach

  {{-- Tabs Área --}}
  <ul class="nav nav-tabs mb-2">
    @foreach($areaTabs as $k => $label)
      <li class="nav-item">
        <a class="nav-link {{ $area===$k ? 'active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['area'=>$k,'page'=>1]) }}">
          {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>

  {{-- Tabs Módulo + Buscar --}}
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">

      <div class="btn-group" role="group">
        @foreach($moduleTabs as $k => $label)
          <a class="btn btn-outline-secondary {{ $module===$k ? 'active' : '' }}"
             href="{{ request()->fullUrlWithQuery(['module'=>$k,'page'=>1]) }}">
            {{ $label }}
          </a>
        @endforeach
      </div>

      <form class="d-flex gap-2" method="GET" action="{{ route('gdf.treasury.dashboard') }}">
        <input type="hidden" name="area" value="{{ $area }}">
        <input type="hidden" name="module" value="{{ $module }}">
        <input type="hidden" name="year" value="{{ $year }}">

        <input class="form-control" name="q" value="{{ $q }}" style="min-width:280px"
               placeholder="Buscar por #, origen, destino, radicado...">

        <button class="btn btn-primary">Buscar</button>

        <a class="btn btn-outline-secondary"
           href="{{ route('gdf.treasury.dashboard', ['area'=>$area,'module'=>$module,'year'=>$year]) }}">
          Limpiar
        </a>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
      <div class="fw-semibold">Solicitudes</div>
      <small class="text-muted">Pasa el mouse sobre el rubro para ver disponibilidad</small>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr class="text-muted">
            <th style="width:90px;">#</th>
            <th style="width:110px;">Módulo</th>
            <th>Origen → Destino</th>
            <th style="width:160px;">Fechas</th>
            <th style="width:140px;">Total</th>
            <th style="width:240px;">Rubro (hover)</th>
            <th style="width:360px;">Acciones</th>
          </tr>
        </thead>

        <tbody>
        @forelse($requests as $r)
          @php
            $key = ((int)$r->area_id).'|'.((int)$r->budget_item_id);
            $a = $availMap[$key] ?? null;

            $need = (float)($r->total_amount ?? 0);
            $ok   = $a ? ((float)($a['available'] ?? 0) >= $need) : false;

            $mod = strtolower((string)($r->module ?? 'gdf'));
            $mod = in_array($mod, ['gdf','sitrav'], true) ? $mod : 'gdf';
            $modBadge = $mod==='sitrav' ? 'info' : 'primary';

            $rubroName = $a['name'] ?? ($r->budget_item_id ? ('Rubro #'.$r->budget_item_id) : 'Sin rubro');
          @endphp

          <tr>
            <td class="fw-semibold">#{{ $r->id }}</td>

            <td>
              <span class="badge bg-{{ $modBadge }}">{{ strtoupper($mod) }}</span>
              <div class="small text-muted">{{ $r->source ?? 'manual' }}</div>
            </td>

            <td>
              <div class="fw-semibold">{{ $r->origin ?? '—' }}</div>
              <div class="text-muted small">{{ $r->destination ?? '—' }}</div>
              @if(!empty($r->radicado_code))
                <div class="small"><span class="pill">Radicado: {{ $r->radicado_code }}</span></div>
              @endif
            </td>

            <td>
              <div class="fw-semibold">{{ $r->start_date ? \Carbon\Carbon::parse($r->start_date)->format('Y-m-d') : '—' }}</div>
              <div class="small text-muted">{{ $r->end_date ? \Carbon\Carbon::parse($r->end_date)->format('Y-m-d') : '—' }}</div>
            </td>

            <td class="fw-semibold">{{ $fmt($need) }}</td>

            <td>
              <div class="hover-card">
                <div class="fw-semibold {{ $ok ? 'text-success' : 'text-danger' }}">
                  {{ $rubroName }}
                </div>

                @if($a)
                  <div class="small {{ $ok ? 'text-success' : 'text-danger' }}">
                    {{ $ok ? 'Suficiente' : 'Insuficiente' }}
                    · Disponible: {{ $fmt($a['available'] ?? 0) }}
                  </div>
                @else
                  <div class="small text-muted">
                    Sin info de presupuesto (revisa budgets activos de {{ $year }}).
                  </div>
                @endif

                <div class="budget-pop">
                  <div class="d-flex justify-content-between">
                    <div class="fw-semibold">Presupuesto</div>
                    <div class="small text-muted">Área #{{ (int)$r->area_id }} · Rubro #{{ (int)$r->budget_item_id }}</div>
                  </div>
                  <hr class="my-2">

                  @if($a)
                    <div class="d-flex justify-content-between"><div class="text-muted">Budget ID</div><div class="fw-semibold">{{ $a['budget_id'] ?? '—' }}</div></div>
                    <div class="d-flex justify-content-between"><div class="text-muted">Inicial</div><div class="fw-semibold">{{ $fmt($a['initial'] ?? 0) }}</div></div>
                    <div class="d-flex justify-content-between"><div class="text-muted">Adiciones</div><div class="fw-semibold">{{ $fmt($a['additions'] ?? 0) }}</div></div>
                    <div class="d-flex justify-content-between"><div class="text-muted">Disponible</div>
                      <div class="fw-semibold {{ $ok ? 'text-success' : 'text-danger' }}">{{ $fmt($a['available'] ?? 0) }}</div>
                    </div>

                    <hr class="my-2">
                    <div class="d-flex justify-content-between"><div class="text-muted">Requerido</div><div class="fw-semibold">{{ $fmt($need) }}</div></div>
                  @else
                    <div class="text-muted small">
                      No se encontró presupuesto activo (budgets.active=1) para esta combinación en {{ $year }}.
                    </div>
                  @endif
                </div>

              </div>
            </td>

            <td>
              {{-- VER --}}
              <a class="btn btn-outline-primary btn-sm"
                 href="{{ route('gdf.treasury.requests.show', $r->id) }}">
                Ver
              </a>

              {{-- APROBAR --}}
              <form class="d-inline" method="POST"
                    action="{{ route('gdf.treasury.requests.approve', $r->id) }}">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="comment" value="">
                <button class="btn btn-success btn-sm" {{ ($a && $ok) ? '' : 'disabled' }}>
                  Aprobar
                </button>
              </form>

              {{-- DEVOLVER --}}
              <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#retSup{{ $r->id }}">
                Devolver Apoyo
              </button>
              <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#retApp{{ $r->id }}">
                Devolver Persona
              </button>

              {{-- RECHAZAR --}}
              <button class="btn btn-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#rej{{ $r->id }}">
                Rechazar
              </button>

              <div class="collapse mt-2" id="retSup{{ $r->id }}">
                <form method="POST" action="{{ route('gdf.treasury.requests.return', $r->id) }}">
                  @csrf
                  <input type="hidden" name="target" value="support">
                  <div class="input-group input-group-sm">
                    <input class="form-control" name="comment" required placeholder="Motivo (obligatorio)">
                    <button class="btn btn-outline-dark">Enviar</button>
                  </div>
                </form>
              </div>

              <div class="collapse mt-2" id="retApp{{ $r->id }}">
                <form method="POST" action="{{ route('gdf.treasury.requests.return', $r->id) }}">
                  @csrf
                  <input type="hidden" name="target" value="applicant">
                  <div class="input-group input-group-sm">
                    <input class="form-control" name="comment" required placeholder="Motivo (obligatorio)">
                    <button class="btn btn-outline-dark">Enviar</button>
                  </div>
                </form>
              </div>

              <div class="collapse mt-2" id="rej{{ $r->id }}">
                <form method="POST" action="{{ route('gdf.treasury.requests.reject', $r->id) }}">
                  @csrf
                  <div class="input-group input-group-sm">
                    <input class="form-control" name="comment" required placeholder="Motivo de rechazo (obligatorio)">
                    <button class="btn btn-outline-dark">Confirmar</button>
                  </div>
                </form>
              </div>

            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center py-4 text-muted">No hay solicitudes pendientes.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $requests->links() }}
    </div>
  </div>

</div>
@endsection
