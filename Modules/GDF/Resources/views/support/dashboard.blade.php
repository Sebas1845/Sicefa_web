@extends('gdf::layouts.masteruser')
@section('title', 'GDF | ' . ($title ?? 'Apoyo'))

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  // ✅ Detectar área desde URL
  $areaKey = $areaKey ?? (Str::contains(request()->path(), 'support/campesena') ? 'campesena' : 'academic');

  // ✅ IMPORTANTE: tus rutas reales son support.campesena.* / support.academic.*
  $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'support.campesena' : 'support.academic');

  $title = $title ?? ($areaKey === 'campesena' ? 'Apoyo Campesena' : 'Apoyo Coordinación Académica');

  $money = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  $routeExists = function (string $name): bool {
      try { return Route::has($name); } catch (\Throwable $e) { return false; }
  };

  $tab  = $tab ?? request('tab','pending');
  $q    = $q   ?? request('q','');
  $year = $year?? (int)request('year', now()->year);

  $toDashboard = route($routePrefix.'.dashboard', ['year'=>$year]);
  $toRequests  = route($routePrefix.'.requests.index', ['year'=>$year]);

  $tabUrl = fn($t) => route($routePrefix.'.dashboard', ['tab'=>$t,'year'=>$year,'q'=>$q]);

  // Donut presupuesto
  $budgetChart = $budgetChart ?? ['executed'=>0,'available'=>0,'total'=>0];
  $availableBudget = $availableBudget ?? ($budgetChart['available'] ?? 0);
  $executedBudget  = $executedBudget  ?? ($budgetChart['executed'] ?? 0);
  $totalBudget     = $totalBudget     ?? ($budgetChart['total'] ?? (($availableBudget ?? 0) + ($executedBudget ?? 0)));

  $pctExec = ($totalBudget > 0) ? round(($executedBudget / $totalBudget) * 100, 1) : 0;

  // Charts
  $areaAllocChart = $areaAllocChart ?? ['labels'=>[], 'values'=>[], 'total'=>0];

  // ✅ viene del controller como confirmedByAreaChart
  $confirmedAreaChart = $confirmedByAreaChart ?? ['labels'=>[], 'values'=>[], 'total'=>0];

  // ✅ viene del controller como confirmedByItemChart
  $confirmedByRubroChart = [
    'labels'       => $confirmedByItemChart['labels'] ?? [],
    'confirmed'    => $confirmedByItemChart['values'] ?? [],
    'actionBudget' => $confirmedByItemChart['actionBudgets'] ?? [],
    'total'        => $confirmedByItemChart['total'] ?? 0,
  ];

  // ✅ viene del controller como topConfirmedRubros
  $topRubroRows = collect($topConfirmedRubros ?? [])->map(function($r){
      return [
        'label' => trim(($r->code ?? '').' '.($r->name ?? '')),
        'confirmed' => (float)($r->confirmed ?? 0),
        'action_budget' => (float)($r->action_budget ?? 0),
        'pct' => isset($r->pct) ? $r->pct : null,
      ];
  })->take(10)->values()->all();

  // ✅ ruta store adiciones (según tu web.php)
  $additionsStoreRoute = $routePrefix.'.budgets.additions.store';
  $addActionTemplate = $routeExists($additionsStoreRoute)
      ? route($additionsStoreRoute, ['budget' => '__BUDGET__'])
      : null;
@endphp

<style>
  .soft-card{
    border-radius: 18px;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 10px 30px rgba(0,0,0,.06);
  }
  .kpi-tile{
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,.06);
    background: rgba(255,255,255,.92);
    box-shadow: 0 10px 25px rgba(0,0,0,.05);
    transition: transform .15s ease;
  }
  .kpi-tile:hover{ transform: translateY(-2px); }
  .kpi-num{ font-size: 1.9rem; font-weight: 900; line-height: 1; }
  .kpi-lbl{ font-size: .85rem; color: rgba(0,0,0,.6); }
  .mini-muted{ color: rgba(0,0,0,.58); font-size: .86rem; }
  .table-compact td, .table-compact th{ padding: .55rem .65rem; }
  .pill{
    display:inline-flex; align-items:center; gap:.5rem;
    padding:.35rem .7rem; border-radius:999px;
    border:1px solid rgba(0,0,0,.08); background:#fff;
    font-size:.85rem;
  }
  .pill b{ font-weight:800; }
  .chart-container { position: relative; height: 280px; width: 100%; }
  .chart-container-sm { position: relative; height: 220px; width: 100%; }
</style>

<div class="container py-4">

  {{-- Alerts --}}
  @foreach (['success','error','warning','info'] as $k)
    @if(session($k))
      <div class="alert alert-{{ $k==='error'?'danger':$k }} mb-3">{{ session($k) }}</div>
    @endif
  @endforeach

  {{-- Header --}}
  <div class="soft-card p-4 mb-4 bg-white">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="mini-muted mb-1">GDF / {{ $title }}</div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <h3 class="fw-black mb-0" style="font-weight:900;">Panel de Apoyo</h3>
          <span class="pill">Vigencia <b>{{ $year }}</b></span>
          <span class="pill">Bandeja <b>SITRAV (SIGAC)</b></span>
        </div>
        <div class="mini-muted mt-2">
          Vista ejecutiva: presupuesto accionable, confirmadas, y seguimiento de bandeja.
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-gdf-ghost" href="{{ $toRequests }}">
          <i class="bi bi-inboxes"></i> Bandeja solicitudes
        </a>

        <div class="dropdown">
          <button class="btn btn-gdf-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-bicycle"></i> Gestión de motos
          </button>
          <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="{{ route($routePrefix.'.motorcycles.queue', ['type'=>'direct']) }}">
              <i class="bi bi-person-check me-2"></i> Asignaciones directas
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-warning fw-semibold" href="{{ route($routePrefix.'.motorcycles.return', ['year'=>now()->year]) }}">
              <i class="bi bi-arrow-repeat me-2"></i> Devoluciones
            </a>
          </div>
        </div>

        @if($routeExists($routePrefix.'.people.index'))
          <a class="btn btn-gdf-ghost" href="{{ route($routePrefix.'.people.index') }}">
            <i class="bi bi-people"></i> Personas / Rubros
          </a>
        @endif

        <button type="button" class="btn btn-gdf-primary" data-bs-toggle="modal" data-bs-target="#modalBudgetAddition">
          <i class="bi bi-plus-circle"></i> Registrar adición
        </button>
      </div>
    </div>
  </div>

  {{-- Presupuesto + Donut --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <div class="soft-card bg-white">
        <div class="p-4">
          <div class="text-uppercase mini-muted fw-bold mb-2">Presupuesto accionable · {{ $year }}</div>

          <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
              <div class="display-6 fw-black" style="font-weight:900;">{{ $money($totalBudget) }}</div>
              <div class="mini-muted">Base de control (Disponible + Ejecutado) — solo acción</div>
            </div>

            <div class="d-flex gap-4">
              <div>
                <div class="mini-muted fw-bold">EJECUTADO</div>
                <div class="fw-bold">{{ $money($executedBudget) }}</div>
              </div>
              <div>
                <div class="mini-muted fw-bold">DISPONIBLE</div>
                <div class="fw-bold">{{ $money($availableBudget) }}</div>
              </div>
            </div>
          </div>

          <div class="mt-3">
            <div class="d-flex justify-content-between mini-muted mb-1">
              <span>% ejecución</span>
              <span class="fw-bold">{{ $pctExec }}%</span>
            </div>
            <div class="progress" style="height:10px;">
              <div class="progress-bar" role="progressbar" style="width: {{ $pctExec }}%"></div>
            </div>
          </div>

          <hr class="my-4" style="opacity:.12">

          <div class="fw-bold mb-1">Distribución por áreas</div>
          <div class="mini-muted mb-2">Asignado (budget_area_allocations) — solo acción</div>
          <div class="chart-container">
            <canvas id="areaAllocBar"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="soft-card bg-white">
        <div class="p-4">
          <div class="fw-bold">Distribución presupuestal</div>
          <div class="mini-muted mb-3">Disponible vs Ejecutado — solo acción</div>
          <div class="chart-container-sm" style="max-width:360px; margin:0 auto;">
            <canvas id="budgetDonut"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Confirmadas --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-5">
      <div class="soft-card bg-white">
        <div class="p-4">
          <div class="fw-bold">Confirmadas por área</div>
          <div class="mini-muted mb-3">Dinero a sacar (status: confirmed)</div>
          <div class="chart-container-sm" style="max-width:360px; margin:0 auto;">
            <canvas id="confirmedAreaDonut"></canvas>
          </div>
          <div class="mt-2 mini-muted text-center">
            Total confirmado: <span class="fw-bold">{{ $money($confirmedAreaChart['total'] ?? 0) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="soft-card bg-white">
        <div class="p-4">
          <div class="fw-bold">Confirmadas por rubro ({{ $areaKey }})</div>
          <div class="mini-muted mb-3">Confirmado vs Presupuesto de acción</div>
          <div class="chart-container">
            <canvas id="confirmedByRubroBar"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <a class="text-decoration-none" href="{{ $tabUrl('pending') }}">
        <div class="kpi-tile p-3">
          <div class="kpi-lbl">Pendiente Apoyo</div>
          <div class="kpi-num text-dark">{{ (int)($kpis['pending_support'] ?? 0) }}</div>
          <div class="mini-muted mt-2">Ver en dashboard</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="text-decoration-none" href="{{ $tabUrl('seen') }}">
        <div class="kpi-tile p-3">
          <div class="kpi-lbl">Vistas / Validadas</div>
          <div class="kpi-num text-dark">{{ (int)($kpis['seen_support'] ?? 0) }}</div>
          <div class="mini-muted mt-2">Ver en dashboard</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="text-decoration-none" href="{{ $tabUrl('returned') }}">
        <div class="kpi-tile p-3">
          <div class="kpi-lbl">Devueltas</div>
          <div class="kpi-num text-dark">{{ (int)($kpis['returned'] ?? 0) }}</div>
          <div class="mini-muted mt-2">Ver en dashboard</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="text-decoration-none" href="{{ $tabUrl('treasury') }}">
        <div class="kpi-tile p-3">
          <div class="kpi-lbl">Pendiente Tesorería</div>
          <div class="kpi-num text-dark">{{ (int)($kpis['pending_treasury'] ?? 0) }}</div>
          <div class="mini-muted mt-2">Ver en dashboard</div>
        </div>
      </a>
    </div>
  </div>

  {{-- Top rubros --}}
  <div class="soft-card bg-white mb-4">
    <div class="p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
          <div class="fw-bold">Top 10 rubros por confirmado</div>
          <div class="mini-muted">Área actual ({{ $areaKey }}) · {{ $year }}</div>
        </div>
        <a class="btn btn-sm btn-gdf-ghost" href="{{ $toRequests }}"><i class="bi bi-inboxes"></i> Ir a bandeja</a>
      </div>

      @if(empty($topRubroRows))
        <div class="mini-muted">Aún no hay rubros con acción/confirmadas para mostrar.</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm table-compact align-middle mb-0">
            <thead>
              <tr class="text-muted">
                <th>Rubro</th>
                <th class="text-end">Confirmado</th>
                <th class="text-end">Presupuesto acción</th>
                <th class="text-end">% consumo</th>
              </tr>
            </thead>
            <tbody>
              @foreach($topRubroRows as $row)
                @php
                  $pct = $row['pct'];
                  $danger = ($pct !== null && $pct >= 90);
                @endphp
                <tr>
                  <td>{{ $row['label'] }}</td>
                  <td class="text-end fw-bold">{{ $money($row['confirmed']) }}</td>
                  <td class="text-end">{{ $money($row['action_budget']) }}</td>
                  <td class="text-end">
                    @if($pct === null)
                      <span class="text-muted">—</span>
                    @else
                      <span class="fw-bold {{ $danger ? 'text-danger' : 'text-dark' }}">{{ $pct }}%</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Bandeja rápida --}}
  <div class="soft-card bg-white">
    <div class="p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold">Bandeja rápida · {{ strtoupper($tab) }}</div>
        <a class="btn btn-sm btn-gdf-ghost" href="{{ $toRequests }}">
          <i class="bi bi-inboxes"></i> Ver bandeja completa
        </a>
      </div>

      @if(($quickRequests ?? collect())->isEmpty())
        <div class="mini-muted">No hay solicitudes en este filtro.</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr class="text-muted">
                <th style="width:90px;">#</th>
                <th style="width:220px;">Estado</th>
                <th>Solicitante</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="text-end" style="width:130px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach($quickRequests as $r)
                @php
                  $st = strtolower((string)($r->status ?? ''));
                  $badge = match($st){
                    'submitted' => 'bg-warning text-dark',
                    'approved' => 'bg-info text-dark',
                    'pending_treasury' => 'bg-primary',
                    'returned' => 'bg-secondary',
                    'executed' => 'bg-success',
                    default => 'bg-dark',
                  };
                  $lbl = match($st){
                    'submitted' => 'Enviada',
                    'approved' => 'Validada',
                    'pending_treasury' => 'En Tesorería',
                    'returned' => 'Devuelta',
                    'executed' => 'Ejecutada',
                    default => strtoupper($st ?: '—'),
                  };
                @endphp
                <tr>
                  <td class="fw-bold">{{ $r->id }}</td>
                  <td><span class="badge {{ $badge }}">{{ $lbl }}</span></td>
                  <td>{{ $programsById[(int)($r->source_request_id ?? 0)]->person->full_name ?? ('Persona #'.($r->person_id ?? '—')) }}</td>
                  <td>{{ $r->origin ?? 'Centro / SENA' }}</td>
                  <td>{{ $r->destination ?? '—' }}</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-gdf-ghost" href="{{ route($routePrefix.'.requests.show', $r->id) }}">
                      Abrir <i class="bi bi-chevron-right"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

      @if($requests)
        <hr class="my-4" style="opacity:.12">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold">Listado completo (paginado)</div>
          <span class="mini-muted">Filtro actual: {{ $tab }}</span>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr class="text-muted">
                <th style="width:90px;">#</th>
                <th style="width:220px;">Estado</th>
                <th>Solicitante</th>
                <th>Destino</th>
                <th style="width:180px;">Fechas</th>
                <th class="text-end" style="width:130px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach($requests as $r)
                @php
                  $st = strtolower((string)($r->status ?? ''));
                  $badge = match($st){
                    'submitted' => 'bg-warning text-dark',
                    'approved' => 'bg-info text-dark',
                    'pending_treasury' => 'bg-primary',
                    'returned' => 'bg-secondary',
                    'executed' => 'bg-success',
                    default => 'bg-dark',
                  };
                  $lbl = match($st){
                    'submitted' => 'Enviada',
                    'approved' => 'Validada',
                    'pending_treasury' => 'En Tesorería',
                    'returned' => 'Devuelta',
                    'executed' => 'Ejecutada',
                    default => strtoupper($st ?: '—'),
                  };
                @endphp
                <tr>
                  <td class="fw-bold">{{ $r->id }}</td>
                  <td><span class="badge {{ $badge }}">{{ $lbl }}</span></td>
                  <td>{{ $programsById[(int)($r->source_request_id ?? 0)]->person->full_name ?? ('Persona #'.($r->person_id ?? '—')) }}</td>
                  <td>{{ $r->destination ?? '—' }}</td>
                  <td class="mini-muted">
                    {{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}
                    <div class="fw-bold text-dark">{{ $money($r->total_amount ?? 0) }}</div>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-gdf-ghost" href="{{ route($routePrefix.'.requests.show', $r->id) }}">
                      Abrir <i class="bi bi-chevron-right"></i>
                    </a>
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

{{-- Modal: Registrar adición --}}
<div class="modal fade" id="modalBudgetAddition" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-0" style="border-radius:16px;">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Registrar adición (rubro)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        @if(!$addActionTemplate)
          <div class="alert alert-warning">
            No se encontró la ruta para guardar adiciones. Revisa el <code>name()</code> en web.php.
          </div>
        @endif

        <form id="formBudgetAddition" method="POST" action="#" data-action-template="{{ $addActionTemplate ?? '' }}">
          @csrf

          <div class="mb-3">
            <label class="form-label">Rubro (Área / Rubro)</label>
            {{-- ✅ NO enviamos budget_id: el budget va en la URL (model binding) --}}
            <select class="form-select bg-dark text-white border-secondary" id="budgetSelect" required>
              <option value="">Seleccione un rubro...</option>
              @foreach(($budgetOptions ?? collect()) as $b)
                <option value="{{ $b->id }}">
                  {{ $b->area_name }} · {{ $b->item_code }} {{ $b->item_name }} · saldo: {{ $money($b->current_amount ?? 0) }}
                </option>
              @endforeach
            </select>
            <div class="text-white-50 small mt-1">Budgets cargados: {{ ($budgetOptions ?? collect())->count() }}</div>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-md-6">
              <label class="form-label">Monto de adición</label>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control bg-dark text-white border-secondary" required>
            </div>
            <div class="col-md-6">
              <div class="text-white-50 small">
              </div>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label">Justificación</label>
            <textarea name="justification" rows="4" class="form-control bg-dark text-white border-secondary" placeholder="Motivo / soporte..."></textarea>
          </div>
        </form>
      </div>

      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" form="formBudgetAddition" class="btn btn-success" id="btnSaveAddition" {{ !$addActionTemplate ? 'disabled' : '' }}>
          <i class="bi bi-check2-circle me-1"></i> Guardar adición
        </button>
      </div>
    </div>
  </div>
</div>

{{-- JS: armar action con budget --}}
<script>
(function() {
  const form = document.getElementById('formBudgetAddition');
  const sel  = document.getElementById('budgetSelect');
  const btn  = document.getElementById('btnSaveAddition');
  if (!form || !sel) return;

  function syncAction() {
    const tpl = form.getAttribute('data-action-template') || '';
    const id  = sel.value;

    if (!tpl || !id) {
      form.setAttribute('action', '#');
      if (btn) btn.disabled = true;
      return;
    }
    form.setAttribute('action', tpl.replace('__BUDGET__', id));
    if (btn) btn.disabled = false;
  }

  sel.addEventListener('change', syncAction);
  syncAction();
})();
</script>

{{-- Chart.js CDN y Charts --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') {
    console.error('Chart.js no está cargado correctamente');
    return;
  }

  const colors = {
    primary: 'rgba(54, 162, 235, 0.8)',
    success: 'rgba(75, 192, 192, 0.8)',
    warning: 'rgba(255, 206, 86, 0.8)',
    danger:  'rgba(255, 99, 132, 0.8)',
    info:    'rgba(153, 102, 255, 0.8)',
    gray:    'rgba(201, 203, 207, 0.8)',
  };

  // ===== Donut presupuesto =====
  const executed  = Number(@json($budgetChart['executed'] ?? 0));
  const available = Number(@json($budgetChart['available'] ?? 0));
  const donutCtx = document.getElementById('budgetDonut');

  if (donutCtx) {
    const hasData = (executed + available) > 0;
    new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: ['Ejecutado', 'Disponible'],
        datasets: [{
          data: hasData ? [executed, available] : [0, 1],
          backgroundColor: hasData ? [colors.danger, colors.success] : [colors.gray],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.parsed || 0;
                return label + ': $' + value.toLocaleString('es-CO');
              }
            }
          }
        },
        cutout: '65%'
      }
    });
  }

  // ===== Barras: asignación por áreas =====
  const labels = @json($areaAllocChart['labels'] ?? []);
  const values = @json($areaAllocChart['values'] ?? []);
  const barCtx = document.getElementById('areaAllocBar');

  if (barCtx) {
    const sum = Array.isArray(values) ? values.reduce((a,b)=>a+Number(b||0),0) : 0;
    const has = sum > 0;

    new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: (labels && labels.length) ? labels : ['Sin datos'],
        datasets: [{
          label: 'Asignado',
          data: has ? values : [1],
          backgroundColor: has ? colors.primary : colors.gray,
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.parsed.y || 0;
                return 'Asignado: $' + value.toLocaleString('es-CO');
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (v) => '$' + Number(v).toLocaleString('es-CO')
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // ===== Donut confirmadas por área =====
  const ca = @json($confirmedAreaChart ?? ['labels'=>[],'values'=>[],'total'=>0]);
  const donut2Ctx = document.getElementById('confirmedAreaDonut');

  if (donut2Ctx) {
    const hasDataCa = Number(ca.total || 0) > 0;
    new Chart(donut2Ctx, {
      type: 'doughnut',
      data: {
        labels: ca.labels || [],
        datasets: [{
          data: hasDataCa ? (ca.values || []) : [1],
          backgroundColor: hasDataCa ? [colors.info, colors.warning] : [colors.gray],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        cutout: '65%'
      }
    });
  }

  // ===== Barras confirmadas por rubro =====
  const rb = @json($confirmedByRubroChart ?? ['labels'=>[],'confirmed'=>[],'actionBudget'=>[]]);
  const bar2Ctx = document.getElementById('confirmedByRubroBar');

  if (bar2Ctx) {
    const hasRb = Array.isArray(rb.labels) && rb.labels.length > 0;

    new Chart(bar2Ctx, {
      type: 'bar',
      data: {
        labels: hasRb ? rb.labels : ['Sin datos'],
        datasets: [
          {
            label: 'Confirmado',
            data: hasRb ? (rb.confirmed || []) : [0],
            backgroundColor: hasRb ? colors.danger : colors.gray,
            borderWidth: 0
          },
          {
            label: 'Presupuesto acción',
            data: hasRb ? (rb.actionBudget || []) : [1],
            backgroundColor: hasRb ? colors.success : colors.gray,
            borderWidth: 0
          },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.dataset.label || '';
                const value = context.parsed.y || 0;
                return label + ': $' + value.toLocaleString('es-CO');
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (v) => '$' + Number(v).toLocaleString('es-CO') },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }
});
</script>
@endsection

