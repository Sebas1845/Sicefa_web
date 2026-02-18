{{-- Modules/GDF/Resources/views/subdirection/dashboard.blade.php --}}
@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Subdirección')

@section('content')
@php
  $isSubdirection = function_exists('checkRol')
      ? (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'))
      : false;
  if(!$isSubdirection){ abort(403); }

  $year = $year ?? (int) request('year', now()->year);

  $kpis = $kpis ?? [
      'total_budget'     => 0,
      'executed_budget'  => 0,
      'available_budget' => 0,
      'pending_requests' => 0,
      'motos_total' => 0,
      'motos_available' => 0,
      'motos_assigned' => 0,
      'moto_assignments_open' => 0,
  ];

  $globalTotal = (int)($kpis['total_budget'] ?? 0);
  $globalSpent = (int)($kpis['executed_budget'] ?? 0);
  $globalAvail = (int)($kpis['available_budget'] ?? max(0, ($globalTotal - $globalSpent)));

  $pendingGdfRequests    = $pendingGdfRequests ?? collect();
  $pendingSitravRequests = $pendingSitravRequests ?? collect();

  $areas      = $areas ?? collect();
  $rubroCards = $rubroCards ?? collect();

  $rAudit       = \Illuminate\Support\Facades\Route::has('gdf.subdirection.audit');
  $rMoves       = \Illuminate\Support\Facades\Route::has('gdf.subdirection.movements.index');
  $rAreaUsers   = \Illuminate\Support\Facades\Route::has('gdf.subdirection.area_users.index');
  $rUsers       = \Illuminate\Support\Facades\Route::has('gdf.subdirection.users.index');
  $rBudgets     = \Illuminate\Support\Facades\Route::has('gdf.subdirection.budgets.index');
  $rRubros      = \Illuminate\Support\Facades\Route::has('gdf.subdirection.rubros');

  $rReqIndex    = \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests')
                || \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests.index');

  $reqIndexName = \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests')
                ? 'gdf.subdirection.requests'
                : 'gdf.subdirection.requests.index';

  $rReqShow     = \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests.show');

  $rAreasIdx    = \Illuminate\Support\Facades\Route::has('gdf.subdirection.areas.index');
  $rAreasCr     = \Illuminate\Support\Facades\Route::has('gdf.subdirection.areas.create');
  $hasMotoIndex = \Illuminate\Support\Facades\Route::has('gdf.subdirection.motorcycles.index');

  $rAreaRubrosIndex = \Illuminate\Support\Facades\Route::has('gdf.subdirection.area_budget_items.index');

  // ✅ NUEVO: firmantes
  $rAuthSigners = \Illuminate\Support\Facades\Route::has('gdf.subdirection.authorization_signers.index');

  // ✅ NUEVO: tarifas (elige la primera que exista)
  $rRatesIndexA = \Illuminate\Support\Facades\Route::has('gdf.subdirection.rates.index');
  $rRatesIndexB = \Illuminate\Support\Facades\Route::has('gdf.subdirection.tariffs.index');
  $rRatesIndexC = \Illuminate\Support\Facades\Route::has('gdf.subdirection.rates');

  $hasRates = $rRatesIndexA || $rRatesIndexB || $rRatesIndexC;

  $ratesRouteName = $rRatesIndexA ? 'gdf.subdirection.rates.index'
                : ($rRatesIndexB ? 'gdf.subdirection.tariffs.index'
                : ($rRatesIndexC ? 'gdf.subdirection.rates' : null));

  // % real (puede ser 0.0393...)
  $spentPctRaw = $globalTotal > 0 ? ($globalSpent / $globalTotal) * 100 : 0;

  // Para que NO se vea "0.0%" cuando es muy pequeño:
  $spentPct = $spentPctRaw > 0 && $spentPctRaw < 0.01 ? 0.01 : round($spentPctRaw, 2);
  $availPct = round(100 - $spentPct, 2);

  // Para la barra: si hay gasto >0 pero demasiado pequeño, mínimo 0.5% visual
  $barPct = $spentPctRaw > 0 && $spentPctRaw < 0.5 ? 0.5 : round($spentPctRaw, 2);

  $money = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');
@endphp

{{-- ✅ ECharts estable --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  :root{
    --bg:#f6f7fb;
    --card:#ffffff;
    --text:#0f172a;
    --muted:#64748b;
    --border:rgba(15,23,42,.10);

    --primary:#2e7d32;
    --primary2:#1b5e20;

    --success:#16a34a;
    --warning:#f59e0b;
    --danger:#ef4444;

    --radius:16px;
  }
  body{ background:var(--bg); color:var(--text); }

  .gdf-container{ max-width:1400px; margin:0 auto; padding:24px 18px 40px; }

  .hdr{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
    margin-bottom:14px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
  }
  .crumb{ color:var(--muted); font-size:.85rem; display:flex; align-items:center; gap:.5rem; margin:0 0 .25rem; }
  .title{ font-size:1.75rem; font-weight:900; margin:0; }
  .subtitle{ color:var(--muted); margin:.25rem 0 0; font-size:.95rem; }

  .chip{
    display:inline-flex; align-items:center; gap:.5rem;
    padding:.38rem .75rem;
    border-radius:999px;
    background:#f1f5f9;
    border:1px solid var(--border);
    color:var(--text);
    font-size:.8rem;
    font-weight:800;
  }

  .btnx{
    padding:.55rem .9rem;
    border-radius:12px;
    border:1px solid var(--border);
    background:#fff;
    color:var(--text);
    font-weight:900;
    font-size:.84rem;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    transition:background .12s ease, border-color .12s ease, transform .12s ease;
  }
  .btnx:hover{ border-color:rgba(46,125,50,.35); color:var(--text); transform: translateY(-1px); }
  .btnx.primary{
    background:var(--primary);
    border-color:var(--primary);
    color:#fff;
  }
  .btnx.primary:hover{ background:var(--primary2); color:#fff; }

  .cardx{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:var(--radius);
    overflow:hidden;
  }
  .cardx .body{ padding:16px; }

  .hero{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
    margin-bottom:14px;
  }
  .amount{ font-size:2.25rem; font-weight:900; margin:.35rem 0; }
  .muted{ color:var(--muted); }

  .progress{
    height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden;
  }
  .bar{
    height:100%; width:0%; background:var(--primary);
    transition:width .9s ease;
  }

  .kpi-icon{
    width:38px; height:38px; border-radius:12px;
    background:#f1f5f9; border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center;
    color:var(--muted);
    margin-bottom:.5rem;
  }
  .kpi-label{
    color:var(--muted);
    font-size:.74rem;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:.25rem;
  }
  .kpi-value{ font-size:1.6rem; font-weight:900; }

  .tbl{ width:100%; font-size:.88rem; }
  .tbl thead th{
    color:var(--muted);
    font-weight:900;
    text-transform:uppercase;
    font-size:.72rem;
    letter-spacing:.05em;
    padding:.7rem .9rem;
    border-bottom:1px solid var(--border);
    background:#f8fafc;
  }
  .tbl tbody td{
    padding:.8rem .9rem;
    border-bottom:1px solid var(--border);
    color:var(--text);
    vertical-align:middle;
  }
  .tbl tbody tr:hover{ background:#fafafa; }

  .badgeX{
    padding:.25rem .6rem;
    border-radius:999px;
    font-size:.75rem;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    border:1px solid var(--border);
    background:#f8fafc;
    color:var(--muted);
  }
  .badgeX.ok{ background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.25); color:var(--success); }

  .divider{ height:1px; background:var(--border); margin:14px 0; }

  .chart{ height:240px; width:100%; }

  @media (max-width:768px){
    .hdr{ flex-direction:column; }
  }
</style>

<div class="gdf-container">

  {{-- HEADER --}}
  <div class="hdr">
    <div>
      <div class="crumb">
        <i class="fas fa-home"></i>
        <span>GDF</span>
        <i class="fas fa-chevron-right" style="font-size:.7rem;"></i>
        <span>Subdirección</span>
      </div>

      <h1 class="title">Panel de Subdirección</h1>
      <p class="subtitle">Presupuesto, solicitudes y recursos operativos</p>

      <div class="d-flex flex-wrap gap-2 mt-2">
        <span class="chip"><i class="fas fa-calendar"></i> Vigencia: <strong>{{ $year }}</strong></span>
        <span class="chip"><i class="fas fa-wallet"></i> Wallet: <strong>Global</strong></span>
        <span class="chip"><i class="fas fa-clock"></i> Pendientes: <strong>{{ (int)($kpis['pending_requests'] ?? 0) }}</strong></span>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
      @if($rUsers)
        <a class="btnx primary" href="{{ route('gdf.subdirection.users.index') }}">
          <i class="fas fa-users"></i> Usuarios
        </a>
      @endif

      {{-- ✅ NUEVO: Firmantes --}}
      @if($rAuthSigners)
        <a class="btnx" href="{{ route('gdf.subdirection.authorization_signers.index', ['module'=>'gdf','area_key'=>'all']) }}">
          <i class="fas fa-signature"></i> Firmantes
        </a>
      @endif

      {{-- ✅ NUEVO: Tarifas --}}
      @if($hasRates && $ratesRouteName)
        <a class="btnx" href="{{ route($ratesRouteName) }}">
          <i class="fas fa-tags"></i> Tarifas
        </a>
      @endif

      @if($rAreaRubrosIndex)
        <a class="btnx" href="{{ route('gdf.subdirection.area_budget_items.index', ['year'=>$year]) }}">
          <i class="fas fa-sitemap"></i> Áreas por rubro
        </a>
      @elseif($rAreasIdx)
        <a class="btnx" href="{{ route('gdf.subdirection.areas.index') }}">
          <i class="fas fa-sitemap"></i> Áreas por rubro
        </a>
      @endif

      @if($rAreaUsers) <a class="btnx" href="{{ route('gdf.subdirection.area_users.index') }}"><i class="fas fa-users-cog"></i> Áreas ↔ Usuarios</a> @endif
      @if($rBudgets)   <a class="btnx" href="{{ route('gdf.subdirection.budgets.index', ['year'=>$year]) }}"><i class="fas fa-coins"></i> Presupuestos</a> @endif
      @if($rMoves)     <a class="btnx" href="{{ route('gdf.subdirection.movements.index') }}"><i class="fas fa-exchange-alt"></i> Movimientos</a> @endif
      @if($rAudit)     <a class="btnx" href="{{ route('gdf.subdirection.audit') }}"><i class="fas fa-file-alt"></i> Auditoría</a> @endif
    </div>
  </div>

  {{-- Alerts --}}
  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm mb-3">
        {{ session($k) }}
      </div>
    @endif
  @endforeach

  {{-- HERO --}}
  <div class="hero">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="kpi-label">Presupuesto Global · {{ $year }}</div>
        <div class="amount">{{ $money($globalAvail) }}</div>
        <div class="muted mb-2">Disponible para ejecución</div>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <div class="kpi-label">Ejecutado</div>
            <div style="font-weight:900; color:var(--success);">{{ $money($globalSpent) }}</div>
          </div>
          <div class="col-6">
            <div class="kpi-label">Total</div>
            <div style="font-weight:900;">{{ $money($globalTotal) }}</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="muted" style="font-size:.85rem;">Ejecución</span>
          <span class="muted" style="font-size:.85rem;">
            <strong>{{ $spentPct }}%</strong>
          </span>
        </div>

        <div class="progress">
          <div class="bar" id="heroProgressBar"></div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          @if($rRubros)
            <a class="btnx" href="{{ route('gdf.subdirection.rubros') }}">
              <i class="fas fa-list"></i> Rubros
            </a>
          @endif

          @if($rReqIndex)
            <a class="btnx" href="{{ route($reqIndexName, ['year'=>$year, 'status'=>'pending_subdirection']) }}">
              <i class="fas fa-inbox"></i> Bandeja pendientes
            </a>
          @endif

          {{-- ✅ NUEVO: acceso rápido Firmantes --}}
          @if($rAuthSigners)
            <a class="btnx" href="{{ route('gdf.subdirection.authorization_signers.index', ['module'=>'gdf','area_key'=>'all']) }}">
              <i class="fas fa-signature"></i> Firmantes
            </a>
          @endif

          {{-- ✅ NUEVO: acceso rápido Tarifas --}}
          @if($hasRates && $ratesRouteName)
            <a class="btnx" href="{{ route($ratesRouteName) }}">
              <i class="fas fa-tags"></i> Control de tarifas
            </a>
          @endif
        </div>
      </div>

      <div class="col-lg-5">
        <div class="cardx">
          <div class="body">
            <div style="font-weight:900;">Distribución presupuestal</div>
            <div class="muted" style="font-size:.9rem; margin-bottom:.5rem;">Disponible vs Ejecutado</div>

            <div id="chartWallet" class="chart"></div>

            <div id="chartWalletFallback" class="d-none"
                 style="height:240px; display:flex; align-items:center; justify-content:center; color:var(--muted);">
              <div style="text-align:center;">
                <i class="fas fa-chart-pie" style="font-size:2.3rem; opacity:.25; display:block; margin-bottom:.5rem;"></i>
                <div style="font-weight:900;">Gráfico no disponible</div>
                <div style="font-size:.85rem;">Sin datos o librería no cargó</div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- KPI Grid --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-coins"></i></div>
        <div class="kpi-label">Presupuesto Total</div>
        <div class="kpi-value">{{ $money($kpis['total_budget'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="kpi-label">Ejecutado</div>
        <div class="kpi-value" style="color:var(--success);">{{ $money($kpis['executed_budget'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
        <div class="kpi-label">Disponible</div>
        <div class="kpi-value" style="color:var(--warning);">{{ $money($kpis['available_budget'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-label">Pendientes</div>
        <div class="kpi-value" style="color:var(--danger);">{{ (int)($kpis['pending_requests'] ?? 0) }}</div>
      </div></div>
    </div>

    {{-- Motos --}}
    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-motorcycle"></i></div>
        <div class="kpi-label">Motos Total</div>
        <div class="kpi-value">{{ (int)($kpis['motos_total'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
        <div class="kpi-label">Disponibles</div>
        <div class="kpi-value" style="color:var(--success);">{{ (int)($kpis['motos_available'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-user-tag"></i></div>
        <div class="kpi-label">Asignadas</div>
        <div class="kpi-value" style="color:var(--warning);">{{ (int)($kpis['motos_assigned'] ?? 0) }}</div>
      </div></div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="cardx"><div class="body">
        <div class="kpi-icon"><i class="fas fa-list-check"></i></div>
        <div class="kpi-label">Asignaciones Activas</div>
        <div class="kpi-value" style="color:var(--danger);">{{ (int)($kpis['moto_assignments_open'] ?? 0) }}</div>
      </div></div>
    </div>
  </div>

  {{-- Áreas y bandeja --}}
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="cardx">
        <div class="body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div style="font-weight:900;"><i class="fas fa-building"></i> Áreas</div>
            <div class="d-flex gap-2">
              @if($rAreasCr)
                <a href="{{ route('gdf.subdirection.areas.create') }}" class="btnx primary" style="padding:.45rem .7rem; font-size:.8rem;">
                  <i class="fas fa-plus"></i>
                </a>
              @endif
              @if($rAreasIdx)
                <a href="{{ route('gdf.subdirection.areas.index') }}" class="btnx" style="padding:.45rem .7rem; font-size:.8rem;">
                  <i class="fas fa-list"></i>
                </a>
              @endif
            </div>
          </div>

          <div class="divider"></div>

          <div class="table-responsive">
            <table class="tbl">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th class="text-end">Estado</th>
                </tr>
              </thead>
              <tbody>
                @forelse($areas as $a)
                  <tr>
                    <td style="font-weight:900;">{{ $a->name }}</td>
                    <td class="text-end">
                      <span class="badgeX {{ $a->active ? 'ok' : '' }}">
                        <i class="fas fa-circle" style="font-size:.55rem;"></i>
                        {{ $a->active ? 'Activa' : 'Inactiva' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" style="text-align:center; padding:1.2rem; color:var(--muted);">
                      No hay áreas registradas
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="muted" style="font-size:.8rem; margin-top:.75rem;">
            <i class="fas fa-info-circle"></i>
            Mantener “CAMPESENA” y “COORDINACIÓN ACADÉMICA” activas para operación normal.
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="cardx">
        <div class="body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <div style="font-weight:900;"><i class="fas fa-inbox"></i> Pendientes de Confirmación</div>
              <div class="muted" style="font-size:.9rem;">Últimas 5 en estado <strong>approved</strong> por módulo</div>
            </div>
            @if($rReqIndex)
              <a href="{{ route($reqIndexName, ['year'=>$year, 'status'=>'pending_subdirection']) }}" class="btnx">
                <i class="fas fa-th-list"></i> Ver todas
              </a>
            @endif
          </div>

          <div class="divider"></div>

          <div class="row g-3">
            {{-- GDF --}}
            <div class="col-md-6">
              <div class="cardx" style="background:#f8fafc;">
                <div class="body">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="font-weight:900;"><i class="fas fa-folder-open"></i> GDF</div>
                    @if($rReqIndex)
                      <a class="btnx" style="padding:.45rem .7rem; font-size:.78rem;"
                         href="{{ route($reqIndexName, ['module'=>'gdf','status'=>'pending_subdirection','year'=>$year]) }}">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                      </a>
                    @endif
                  </div>

                  <div class="table-responsive">
                    <table class="tbl" style="font-size:.85rem;">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Persona</th>
                          <th class="text-end">Valor</th>
                          <th class="text-end">Acción</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($pendingGdfRequests as $r)
                          <tr>
                            <td>{{ $r->id }}</td>
                            <td class="text-truncate" style="max-width:160px;">{{ $r->person_name ?? '—' }}</td>
                            <td class="text-end" style="font-weight:900;">{{ $money($r->amount ?? 0) }}</td>
                            <td class="text-end">
                              @if($rReqShow)
                                <a href="{{ route('gdf.subdirection.requests.show', $r->id) }}" class="btnx" style="padding:.4rem .6rem; font-size:.75rem;">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @endif
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="4" style="text-align:center; padding:1.2rem; color:var(--muted);">
                              Sin pendientes GDF
                            </td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            {{-- SITRAV --}}
            <div class="col-md-6">
              <div class="cardx" style="background:#f8fafc;">
                <div class="body">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="font-weight:900;"><i class="fas fa-route"></i> SITRAV</div>
                    @if($rReqIndex)
                      <a class="btnx" style="padding:.45rem .7rem; font-size:.78rem;"
                         href="{{ route($reqIndexName, ['module'=>'sitrav','status'=>'pending_subdirection','year'=>$year]) }}">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                      </a>
                    @endif
                  </div>

                  <div class="table-responsive">
                    <table class="tbl" style="font-size:.85rem;">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Persona</th>
                          <th class="text-end">Valor</th>
                          <th class="text-end">Acción</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($pendingSitravRequests as $r)
                          <tr>
                            <td>{{ $r->id }}</td>
                            <td class="text-truncate" style="max-width:160px;">{{ $r->person_name ?? '—' }}</td>
                            <td class="text-end" style="font-weight:900;">{{ $money($r->amount ?? 0) }}</td>
                            <td class="text-end">
                              @if($rReqShow)
                                <a href="{{ route('gdf.subdirection.requests.show', $r->id) }}" class="btnx" style="padding:.4rem .6rem; font-size:.75rem;">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @endif
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="4" style="text-align:center; padding:1.2rem; color:var(--muted);">
                              Sin pendientes SITRAV
                            </td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="divider"></div>

          <div class="d-flex flex-wrap gap-2">
            @if($hasMotoIndex)
              <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="btnx">
                <i class="fas fa-motorcycle"></i> Gestión de Motos
              </a>
            @endif
            @if($rAreaUsers)
              <a href="{{ route('gdf.subdirection.area_users.index') }}" class="btnx">
                <i class="fas fa-users-cog"></i> Asignación de Áreas
              </a>
            @endif
            @if($rMoves)
              <a href="{{ route('gdf.subdirection.movements.index') }}" class="btnx">
                <i class="fas fa-exchange-alt"></i> Historial de Movimientos
              </a>
            @endif
          </div>

        </div>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Barra progreso (mínimo visual si hay gasto pequeño)
  const heroBar = document.getElementById('heroProgressBar');
  if (heroBar) {
    const pct = @json($barPct);
    heroBar.style.width = pct + '%';
  }

  // Donut ECharts
  const chartEl = document.getElementById('chartWallet');
  const fallbackEl = document.getElementById('chartWalletFallback');

  const avail = @json((int)$globalAvail);
  const spent = @json((int)$globalSpent);

  const mountFallback = () => {
    if (chartEl) chartEl.classList.add('d-none');
    if (fallbackEl) fallbackEl.classList.remove('d-none');
  };

  if (!chartEl) return;
  if (!window.echarts) { mountFallback(); return; }
  if ((avail + spent) <= 0) { mountFallback(); return; }

  try {
    const c = echarts.init(chartEl);

    c.setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
      legend: { bottom: 0 },
      series: [{
        type: 'pie',
        radius: ['55%', '85%'],
        avoidLabelOverlap: true,
        label: { show: false },
        labelLine: { show: false },
        data: [
          { value: spent, name: 'Ejecutado' },
          { value: avail, name: 'Disponible' }
        ]
      }]
    });

    window.addEventListener('resize', () => c.resize());
  } catch (e) {
    console.warn('ECharts init error:', e);
    mountFallback();
  }
});
</script>
@endsection
