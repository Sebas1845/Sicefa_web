{{--  resources/views/.../subdirection/dashboard.blade.php  --}}
@extends('gdf::layouts.masteruser')

@section('title','GDF | Subdirección')

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

    $pendingRequests       = $pendingRequests ?? collect();
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
    $rReqIndex    = \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests');
    $rReqShow     = \Illuminate\Support\Facades\Route::has('gdf.subdirection.requests.show');
    $rAreasIdx    = \Illuminate\Support\Facades\Route::has('gdf.subdirection.areas.index');
    $rAreasCr     = \Illuminate\Support\Facades\Route::has('gdf.subdirection.areas.create');
    $hasMotoIndex = \Illuminate\Support\Facades\Route::has('gdf.subdirection.motorcycles.index');

    $spentPct = $globalTotal > 0 ? min(100, max(0, round(($globalSpent / $globalTotal) * 100, 1))) : 0;
    $availPct = 100 - $spentPct;

    $money = function($n){
        return '$ ' . number_format((float)$n, 0, ',', '.');
    };
@endphp

{{-- CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  :root{
    --primary:#6366f1; --primary-dark:#4f46e5;
    --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --info:#06b6d4;
  }

  [data-theme="dark"]{
    --bg-primary:#0a0f1e; --bg-secondary:#0f1629; --bg-tertiary:#1a2235;
    --bg-elevated:rgba(255,255,255,.03); --bg-hover:rgba(255,255,255,.06);
    --border:rgba(255,255,255,.08); --border-strong:rgba(255,255,255,.12);
    --text-primary:rgba(255,255,255,.95); --text-secondary:rgba(255,255,255,.65); --text-muted:rgba(255,255,255,.45);
    --shadow-sm:0 2px 8px rgba(0,0,0,.3); --shadow-md:0 8px 24px rgba(0,0,0,.4); --shadow-lg:0 16px 48px rgba(0,0,0,.5);
    --gradient-primary:radial-gradient(1400px 600px at 20% 0%, rgba(99,102,241,.18), transparent 60%);
    --gradient-secondary:radial-gradient(1000px 500px at 80% 10%, rgba(16,185,129,.12), transparent 60%);
  }

  [data-theme="light"]{
    --bg-primary:#f8fafc; --bg-secondary:#ffffff; --bg-tertiary:#f1f5f9;
    --bg-elevated:rgba(255,255,255,.95); --bg-hover:rgba(0,0,0,.04);
    --border:rgba(0,0,0,.08); --border-strong:rgba(0,0,0,.12);
    --text-primary:#0f172a; --text-secondary:#475569; --text-muted:#94a3b8;
    --shadow-sm:0 2px 8px rgba(0,0,0,.06); --shadow-md:0 8px 24px rgba(0,0,0,.08); --shadow-lg:0 16px 48px rgba(0,0,0,.12);
    --gradient-primary:radial-gradient(1400px 600px at 20% 0%, rgba(99,102,241,.08), transparent 60%);
    --gradient-secondary:radial-gradient(1000px 500px at 80% 10%, rgba(16,185,129,.06), transparent 60%);
  }

  @media (prefers-color-scheme: dark){
    :root:not([data-theme]){
      --bg-primary:#0a0f1e; --bg-secondary:#0f1629; --bg-tertiary:#1a2235;
      --bg-elevated:rgba(255,255,255,.03); --bg-hover:rgba(255,255,255,.06);
      --border:rgba(255,255,255,.08); --border-strong:rgba(255,255,255,.12);
      --text-primary:rgba(255,255,255,.95); --text-secondary:rgba(255,255,255,.65); --text-muted:rgba(255,255,255,.45);
      --shadow-sm:0 2px 8px rgba(0,0,0,.3); --shadow-md:0 8px 24px rgba(0,0,0,.4); --shadow-lg:0 16px 48px rgba(0,0,0,.5);
      --gradient-primary:radial-gradient(1400px 600px at 20% 0%, rgba(99,102,241,.18), transparent 60%);
      --gradient-secondary:radial-gradient(1000px 500px at 80% 10%, rgba(16,185,129,.12), transparent 60%);
    }
  }
  @media (prefers-color-scheme: light){
    :root:not([data-theme]){
      --bg-primary:#f8fafc; --bg-secondary:#ffffff; --bg-tertiary:#f1f5f9;
      --bg-elevated:rgba(255,255,255,.95); --bg-hover:rgba(0,0,0,.04);
      --border:rgba(0,0,0,.08); --border-strong:rgba(0,0,0,.12);
      --text-primary:#0f172a; --text-secondary:#475569; --text-muted:#94a3b8;
      --shadow-sm:0 2px 8px rgba(0,0,0,.06); --shadow-md:0 8px 24px rgba(0,0,0,.08); --shadow-lg:0 16px 48px rgba(0,0,0,.12);
      --gradient-primary:radial-gradient(1400px 600px at 20% 0%, rgba(99,102,241,.08), transparent 60%);
      --gradient-secondary:radial-gradient(1000px 500px at 80% 10%, rgba(16,185,129,.06), transparent 60%);
    }
  }

  /* IMPORTANTE: NO apagar el body (evita “pantalla en blanco” si falla JS) */
  body{ background:var(--bg-primary); color:var(--text-primary); opacity:1; transition:background-color .3s ease, color .3s ease; }

  .gdf-container{ max-width:1600px; margin:0 auto; padding:2rem 1.5rem; }

  .theme-toggle{
    position:fixed; bottom:2rem; right:2rem; width:56px; height:56px; border-radius:50%;
    background:var(--bg-elevated); border:1px solid var(--border-strong); box-shadow:var(--shadow-lg);
    cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(20px);
    transition:all .3s cubic-bezier(.4,0,.2,1);
  }
  .theme-toggle:hover{ transform:scale(1.08) rotate(8deg); }
  .theme-toggle i{ font-size:1.25rem; color:var(--text-primary); }

  .gdf-header{ margin-bottom:2rem; }
  .gdf-breadcrumb{ color:var(--text-muted); font-size:.875rem; display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem; }
  .gdf-title{
    font-size:2.5rem; font-weight:900;
    background:linear-gradient(135deg, var(--primary) 0%, var(--success) 100%);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    margin:0; letter-spacing:-.02em;
  }
  .gdf-subtitle{ color:var(--text-secondary); font-size:1rem; margin-top:.5rem; }

  .gdf-chip{
    display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1rem; border-radius:100px;
    background:var(--bg-elevated); border:1px solid var(--border); color:var(--text-primary);
    font-size:.875rem; font-weight:500; backdrop-filter:blur(10px); transition:all .2s ease;
  }
  .gdf-chip:hover{ background:var(--bg-hover); transform:translateY(-2px); }

  .gdf-card{
    background:var(--bg-secondary); border:1px solid var(--border); border-radius:20px; box-shadow:var(--shadow-md);
    overflow:hidden; transition:all .3s cubic-bezier(.4,0,.2,1); height:100%;
  }
  .gdf-card:hover{ transform:translateY(-4px); box-shadow:var(--shadow-lg); border-color:var(--border-strong); }
  .gdf-card-body{ padding:1.5rem; color:var(--text-primary); }

  .gdf-hero{
    background:var(--bg-secondary); border:1px solid var(--border-strong); border-radius:24px; padding:2.5rem;
    position:relative; overflow:hidden; box-shadow:var(--shadow-lg); margin-bottom:2rem;
  }
  .gdf-hero::before{
    content:''; position:absolute; inset:0;
    background:var(--gradient-primary), var(--gradient-secondary);
    pointer-events:none;
  }
  .gdf-hero-content{ position:relative; z-index:2; }

  .gdf-amount{
    font-size:3.5rem; font-weight:900;
    background:linear-gradient(135deg, var(--primary), var(--success));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    line-height:1; letter-spacing:-.03em; margin:1rem 0;
  }

  .gdf-progress{ height:12px; background:var(--bg-tertiary); border-radius:100px; overflow:hidden; border:1px solid var(--border); }
  .gdf-progress-bar{
    height:100%; width:0%;
    background:linear-gradient(90deg, var(--success), var(--primary));
    border-radius:100px; transition:width 1.2s cubic-bezier(.4,0,.2,1);
  }

  .gdf-kpi-label{ color:var(--text-muted); font-size:.875rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem; display:flex; align-items:center; gap:.5rem; }
  .gdf-kpi-value{ font-size:2rem; font-weight:900; color:var(--text-primary); line-height:1.1; letter-spacing:-.02em; }

  .gdf-kpi-icon{ width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; margin-bottom:.75rem; }
  .icon-primary{ background:rgba(99,102,241,.15); color:var(--primary); }
  .icon-success{ background:rgba(16,185,129,.15); color:var(--success); }
  .icon-warning{ background:rgba(245,158,11,.15); color:var(--warning); }
  .icon-danger{ background:rgba(239,68,68,.15); color:var(--danger); }
  .icon-info{ background:rgba(6,182,212,.15); color:var(--info); }

  .gdf-btn{
    padding:.625rem 1.25rem; border-radius:12px; border:1px solid var(--border-strong);
    background:var(--bg-elevated); color:var(--text-primary); font-weight:600; font-size:.875rem;
    transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem;
  }
  .gdf-btn:hover{ background:var(--bg-hover); transform:translateY(-2px); box-shadow:var(--shadow-sm); color:var(--text-primary); }
  .gdf-btn-primary{ background:var(--primary); color:#fff; border-color:var(--primary); }
  .gdf-btn-primary:hover{ background:var(--primary-dark); color:#fff; }

  .gdf-table{ width:100%; font-size:.875rem; }
  .gdf-table thead th{
    color:var(--text-muted); font-weight:700; text-transform:uppercase; font-size:.75rem; letter-spacing:.05em;
    padding:.75rem 1rem; border-bottom:1px solid var(--border); background:var(--bg-tertiary);
  }
  .gdf-table tbody td{ padding:1rem; border-bottom:1px solid var(--border); color:var(--text-secondary); }
  .gdf-table tbody tr:hover{ background:var(--bg-hover); }

  .gdf-divider{ height:1px; background:var(--border); margin:2rem 0; opacity:.6; }

  .gdf-rubros-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1.5rem; margin-top:1.5rem; }

  .gdf-rubro-card{
    background:var(--bg-secondary); border:1px solid var(--border); border-radius:20px; padding:1.5rem;
    transition:all .3s cubic-bezier(.4,0,.2,1);
  }
  .gdf-rubro-card:hover{ transform:translateY(-4px); box-shadow:var(--shadow-lg); border-color:var(--border-strong); }

  .gdf-badge{ padding:.25rem .75rem; border-radius:100px; font-size:.75rem; font-weight:700; display:inline-block; }
  .gdf-badge-success{ background:rgba(16,185,129,.15); color:var(--success); }
  .gdf-badge-secondary{ background:var(--bg-tertiary); color:var(--text-muted); }

  .gdf-chart{ height:240px; width:100%; }

  @media (max-width:768px){
    .gdf-title{ font-size:2rem; }
    .gdf-amount{ font-size:2.5rem; }
    .gdf-hero{ padding:1.5rem; }
    .gdf-rubros-grid{ grid-template-columns:1fr; }
    .theme-toggle{ bottom:1rem; right:1rem; width:48px; height:48px; }
  }
</style>

{{-- Toggle tema --}}
<button class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
  <i class="fas fa-moon" id="themeIcon"></i>
</button>

<div class="gdf-container">

  {{-- Header --}}
  <div class="gdf-header">
    <div class="gdf-breadcrumb">
      <i class="fas fa-home"></i>
      <span>GDF</span>
      <i class="fas fa-chevron-right" style="font-size:.7rem;"></i>
      <span>Subdirección</span>
    </div>

    <h1 class="gdf-title">Panel Ejecutivo</h1>
    <p class="gdf-subtitle">Gestión integral de presupuesto, solicitudes y recursos operativos</p>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <span class="gdf-chip">
        <i class="fas fa-calendar"></i>
        <span>Vigencia: <strong>{{ $year }}</strong></span>
      </span>
      <span class="gdf-chip">
        <i class="fas fa-wallet"></i>
        <span>Wallet: <strong>Global</strong></span>
      </span>
      <span class="gdf-chip">
        <i class="fas fa-clock"></i>
        <span>Pendientes: <strong>{{ (int)($kpis['pending_requests'] ?? 0) }}</strong></span>
      </span>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3">
      @if($rAudit) <a class="gdf-btn" href="{{ route('gdf.subdirection.audit') }}"><i class="fas fa-file-alt"></i> Auditoría</a> @endif
      @if($rMoves) <a class="gdf-btn" href="{{ route('gdf.subdirection.movements.index') }}"><i class="fas fa-exchange-alt"></i> Movimientos</a> @endif
      @if($rAreaUsers) <a class="gdf-btn" href="{{ route('gdf.subdirection.area_users.index') }}"><i class="fas fa-users-cog"></i> Áreas ↔ Usuarios</a> @endif
      @if($rUsers) <a class="gdf-btn gdf-btn-primary" href="{{ route('gdf.subdirection.users.index') }}"><i class="fas fa-users"></i> Usuarios</a> @endif
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

  {{-- Hero Wallet --}}
  <div class="gdf-hero">
    <div class="gdf-hero-content">
      <div class="row g-4">
        <div class="col-lg-7">
          <div style="color:var(--text-muted); font-size:.875rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">
            Presupuesto Global · {{ $year }}
          </div>

          <div class="gdf-amount">{{ $money($globalAvail) }}</div>

          <div style="color:var(--text-secondary); font-size:1.05rem; margin-bottom:1.25rem;">
            Disponible para ejecución
          </div>

          <div class="row g-3 mb-4">
            <div class="col-6">
              <div style="color:var(--text-muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.05em;">Ejecutado</div>
              <div style="font-size:1.5rem; font-weight:900; color:var(--success);">{{ $money($globalSpent) }}</div>
            </div>
            <div class="col-6">
              <div style="color:var(--text-muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.05em;">Presupuesto Total</div>
              <div style="font-size:1.5rem; font-weight:900; color:var(--text-primary);">{{ $money($globalTotal) }}</div>
            </div>
          </div>

          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span style="color:var(--text-muted); font-size:.875rem;">Progreso de ejecución</span>
              <span style="color:var(--text-secondary); font-size:.875rem; font-weight:700;">
                <span style="color:var(--success);">{{ $spentPct }}%</span> ejecutado ·
                <span style="color:var(--warning);">{{ $availPct }}%</span> disponible
              </span>
            </div>
            <div class="gdf-progress">
              <div class="gdf-progress-bar" id="heroProgressBar"></div>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-4">
            @if($rBudgets)
              <a class="gdf-btn" href="{{ route('gdf.subdirection.budgets.index', ['year'=>$year]) }}">
                <i class="fas fa-coins"></i> Presupuestos
              </a>
            @endif
            @if($rRubros)
              <a class="gdf-btn" href="{{ route('gdf.subdirection.rubros') }}">
                <i class="fas fa-list"></i> Rubros
              </a>
            @endif
            @if($rReqIndex)
              <a class="gdf-btn" href="{{ route('gdf.subdirection.requests', ['year'=>$year]) }}"
                 style="border-color:var(--warning); background:rgba(245,158,11,.1);">
                <i class="fas fa-inbox"></i> Bandeja de solicitudes
              </a>
            @endif
          </div>
        </div>

        <div class="col-lg-5">
          <div class="gdf-card">
            <div class="gdf-card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <div style="font-weight:800; font-size:1.1rem;">Distribución Presupuestal</div>
                  <div style="color:var(--text-muted); font-size:.875rem;">Disponible vs Ejecutado</div>
                </div>
                <span class="gdf-chip" style="font-size:.75rem; padding:.35rem .65rem;">
                  <i class="fas fa-chart-pie"></i> Live
                </span>
              </div>

              <div id="chartWallet" class="gdf-chart"></div>

              <div id="chartWalletFallback" class="d-none"
                   style="height:240px; display:flex; align-items:center; justify-content:center; color:var(--text-muted);">
                <div style="text-align:center;">
                  <i class="fas fa-chart-pie" style="font-size:3rem; opacity:.25; display:block; margin-bottom:1rem;"></i>
                  <div style="font-weight:700;">Gráfico no disponible</div>
                  <div style="font-size:.875rem;">(ECharts no cargó)</div>
                </div>
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
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-primary"><i class="fas fa-dollar-sign"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-coins"></i> Presupuesto Total</div>
          <div class="gdf-kpi-value">{{ $money($kpis['total_budget'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">Disponible + Ejecutado</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-success"><i class="fas fa-check-circle"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-arrow-up"></i> Ejecutado</div>
          <div class="gdf-kpi-value" style="color:var(--success);">{{ $money($kpis['executed_budget'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">Movimientos tipo execute</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-warning"><i class="fas fa-wallet"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-hand-holding-usd"></i> Disponible</div>
          <div class="gdf-kpi-value" style="color:var(--warning);">{{ $money($kpis['available_budget'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">SUM(budgets.current_amount)</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-danger"><i class="fas fa-clock"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-exclamation-circle"></i> Pendientes</div>
          <div class="gdf-kpi-value" style="color:var(--danger);">{{ (int)($kpis['pending_requests'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">Revisión Subdirección</div>
        </div>
      </div>
    </div>

    {{-- Motos --}}
    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-info"><i class="fas fa-motorcycle"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-warehouse"></i> Motos Total</div>
          <div class="gdf-kpi-value">{{ (int)($kpis['motos_total'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">Inventario completo</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-success"><i class="fas fa-check-double"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-thumbs-up"></i> Disponibles</div>
          <div class="gdf-kpi-value" style="color:var(--success);">{{ (int)($kpis['motos_available'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">status = available</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-warning"><i class="fas fa-user-tag"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-link"></i> Asignadas</div>
          <div class="gdf-kpi-value" style="color:var(--warning);">{{ (int)($kpis['motos_assigned'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">status = assigned</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="gdf-kpi-icon icon-danger"><i class="fas fa-tasks"></i></div>
          <div class="gdf-kpi-label"><i class="fas fa-list-check"></i> Asignaciones Activas</div>
          <div class="gdf-kpi-value" style="color:var(--danger);">{{ (int)($kpis['moto_assignments_open'] ?? 0) }}</div>
          <div style="color:var(--text-muted); font-size:.75rem; margin-top:.5rem;">approved + delivered</div>
        </div>
      </div>
    </div>

  </div>

  {{-- Rubros --}}
  <div class="gdf-card mb-4">
    <div class="gdf-card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
          <div style="color:var(--text-muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem;">
            <i class="fas fa-tags"></i> Gestión de Rubros
          </div>
          <h2 style="font-size:1.75rem; font-weight:900; margin:0;">Rubros Activos</h2>
          <p style="color:var(--text-secondary); margin-top:.5rem;">Distribución presupuestal por categoría con cobertura operativa</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          @if($rBudgets)
            <a class="gdf-btn" href="{{ route('gdf.subdirection.budgets.index', ['year'=>$year]) }}"><i class="fas fa-coins"></i> Ver Presupuestos</a>
          @endif
          @if($rRubros)
            <a class="gdf-btn gdf-btn-primary" href="{{ route('gdf.subdirection.rubros') }}"><i class="fas fa-list"></i> Catálogo Rubros</a>
          @endif
        </div>
      </div>

      <div class="gdf-rubros-grid">
        @forelse($rubroCards as $r)
          @php
            $avail = (int)($r['avail'] ?? 0);
            $spent = (int)($r['spent'] ?? 0);
            $total = (int)($r['total'] ?? 0);
            $pct   = $total > 0 ? min(100, max(0, round(($spent/$total)*100))) : 0;
          @endphp

          <div class="gdf-rubro-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div style="color:var(--text-muted); font-size:.75rem; font-weight:700;">{{ $r['code'] ?? '—' }}</div>
                <div style="font-weight:800; font-size:1.1rem; margin-top:.25rem;">{{ $r['name'] ?? 'Rubro' }}</div>
              </div>
              @if($rBudgets)
                <a class="gdf-btn" style="padding:.4rem .8rem; font-size:.8rem;"
                   href="{{ route('gdf.subdirection.budgets.index', ['q'=>($r['code'] ?? ''), 'year'=>$year]) }}">
                  <i class="fas fa-eye"></i>
                </a>
              @endif
            </div>

            <div class="d-flex gap-2 flex-wrap mb-3">
              <span class="gdf-chip" style="font-size:.75rem; padding:.35rem .65rem;">
                <i class="fas fa-building"></i> Áreas: <strong>{{ (int)($r['areas_count'] ?? 0) }}</strong>
              </span>
              <span class="gdf-chip" style="font-size:.75rem; padding:.35rem .65rem;">
                <i class="fas fa-users"></i> Personas: <strong>{{ (int)($r['people_count'] ?? 0) }}</strong>
              </span>
              @if((int)($r['primary_count'] ?? 0) > 0)
                <span class="gdf-chip" style="font-size:.75rem; padding:.35rem .65rem;">
                  <i class="fas fa-star"></i> Primarios: <strong>{{ (int)($r['primary_count'] ?? 0) }}</strong>
                </span>
              @endif
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <div style="color:var(--text-muted); font-size:.75rem; margin-bottom:.25rem;">Disponible</div>
                <div style="font-size:1.25rem; font-weight:900; color:var(--success);">{{ $money($avail) }}</div>
              </div>
              <div class="col-6 text-end">
                <div style="color:var(--text-muted); font-size:.75rem; margin-bottom:.25rem;">Ejecutado</div>
                <div style="font-size:1.25rem; font-weight:900;">{{ $money($spent) }}</div>
              </div>
            </div>

            <div style="color:var(--text-muted); font-size:.75rem; margin-bottom:.25rem;">Total: {{ $money($total) }}</div>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span style="color:var(--text-muted); font-size:.75rem;">Ejecución</span>
                <span style="color:var(--text-secondary); font-size:.75rem; font-weight:900;">{{ $pct }}%</span>
              </div>
              <div class="gdf-progress">
                <div class="gdf-progress-bar" style="width: {{ $pct }}%;"></div>
              </div>
            </div>

            @if($rReqIndex)
              <a href="{{ route('gdf.subdirection.requests', ['year'=>$year, 'q'=>($r['code'] ?? '')]) }}"
                 style="color:var(--primary); text-decoration:none; font-size:.875rem; font-weight:700; display:flex; gap:.5rem; align-items:center;">
                <i class="fas fa-arrow-right"></i> Ver solicitudes relacionadas
              </a>
            @endif
          </div>
        @empty
          <div style="grid-column:1/-1; text-align:center; padding:3rem; color:var(--text-muted);">
            <i class="fas fa-inbox" style="font-size:3rem; opacity:.3; margin-bottom:1rem;"></i>
            <p>No hay rubros activos para mostrar</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Áreas y bandejas --}}
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div style="font-weight:900; font-size:1.1rem;"><i class="fas fa-building"></i> Áreas</div>
              <div style="color:var(--text-muted); font-size:.875rem;">Estado operativo</div>
            </div>
            <div class="d-flex gap-2">
              @if($rAreasCr)
                <a href="{{ route('gdf.subdirection.areas.create') }}" class="gdf-btn gdf-btn-primary" style="padding:.4rem .8rem; font-size:.8rem;">
                  <i class="fas fa-plus"></i>
                </a>
              @endif
              @if($rAreasIdx)
                <a href="{{ route('gdf.subdirection.areas.index') }}" class="gdf-btn" style="padding:.4rem .8rem; font-size:.8rem;">
                  <i class="fas fa-list"></i>
                </a>
              @endif
            </div>
          </div>

          <div class="gdf-divider" style="margin:1rem 0;"></div>

          <div class="table-responsive">
            <table class="gdf-table">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th class="text-end">Estado</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($areas ?? collect()) as $a)
                  <tr>
                    <td style="font-weight:800;">{{ $a->name }}</td>
                    <td class="text-end">
                      <span class="gdf-badge {{ $a->active ? 'gdf-badge-success' : 'gdf-badge-secondary' }}">
                        <i class="fas fa-circle" style="font-size:.5rem;"></i>
                        {{ $a->active ? 'Activa' : 'Inactiva' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" style="text-align:center; padding:2rem; color:var(--text-muted);">
                      No hay áreas registradas
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div style="margin-top:1rem; padding:1rem; background:var(--bg-tertiary); border-radius:12px; border:1px solid var(--border);">
            <div style="color:var(--text-muted); font-size:.75rem; display:flex; gap:.5rem;">
              <i class="fas fa-info-circle" style="margin-top:.15rem;"></i>
              <span>Mantener "CAMPESENA" y "COORDINACIÓN ACADÉMICA" activas para operación normal.</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="gdf-card">
        <div class="gdf-card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div style="font-weight:900; font-size:1.1rem;"><i class="fas fa-inbox"></i> Bandejas de Solicitudes</div>
              <div style="color:var(--text-muted); font-size:.875rem;">Pendientes por módulo</div>
            </div>
            @if($rReqIndex)
              <a href="{{ route('gdf.subdirection.requests', ['year'=>$year]) }}" class="gdf-btn">
                <i class="fas fa-th-list"></i> Ver Todas
              </a>
            @endif
          </div>

          <div class="gdf-divider" style="margin:1rem 0;"></div>

          <div class="row g-3">
            {{-- GDF --}}
            <div class="col-md-6">
              <div style="background:var(--bg-tertiary); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <div style="font-weight:900;"><i class="fas fa-folder-open"></i> GDF</div>
                    <div style="color:var(--text-muted); font-size:.75rem;">Últimos 5 pendientes</div>
                  </div>
                  @if($rReqIndex)
                    <a class="gdf-btn" style="padding:.35rem .7rem; font-size:.75rem;"
                       href="{{ route('gdf.subdirection.requests', ['module'=>'gdf','status'=>'submitted','year'=>$year]) }}">
                      <i class="fas fa-external-link-alt"></i>
                    </a>
                  @endif
                </div>

                <div class="table-responsive">
                  <table class="gdf-table" style="font-size:.8rem;">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Persona</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse(($pendingGdfRequests ?? collect()) as $r)
                        <tr>
                          <td>{{ $r->id }}</td>
                          <td class="text-truncate" style="max-width:140px;">
                            {{ $r->instructor_name ?? $r->person_name ?? '—' }}
                          </td>
                          <td class="text-end" style="font-weight:900;">{{ $money($r->amount ?? $r->total_amount ?? 0) }}</td>
                          <td class="text-end">
                            @if($rReqShow)
                              <a href="{{ route('gdf.subdirection.requests.show', $r->id) }}"
                                 class="gdf-btn" style="padding:.25rem .5rem; font-size:.7rem; border-color:var(--warning); background:rgba(245,158,11,.1);">
                                <i class="fas fa-eye"></i>
                              </a>
                            @endif
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="4" style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size:1.5rem; opacity:.3; display:block; margin-bottom:.5rem;"></i>
                            Sin pendientes GDF
                          </td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            {{-- SITRAV --}}
            <div class="col-md-6">
              <div style="background:var(--bg-tertiary); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <div style="font-weight:900;"><i class="fas fa-route"></i> SITRAV</div>
                    <div style="color:var(--text-muted); font-size:.75rem;">Últimos 5 pendientes</div>
                  </div>
                  @if($rReqIndex)
                    <a class="gdf-btn" style="padding:.35rem .7rem; font-size:.75rem;"
                       href="{{ route('gdf.subdirection.requests', ['module'=>'sitrav','status'=>'submitted','year'=>$year]) }}">
                      <i class="fas fa-external-link-alt"></i>
                    </a>
                  @endif
                </div>

                <div class="table-responsive">
                  <table class="gdf-table" style="font-size:.8rem;">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Persona</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse(($pendingSitravRequests ?? collect()) as $r)
                        <tr>
                          <td>{{ $r->id }}</td>
                          <td class="text-truncate" style="max-width:140px;">
                            {{ $r->employee_name ?? $r->instructor_name ?? $r->person_name ?? '—' }}
                          </td>
                          <td class="text-end" style="font-weight:900;">{{ $money($r->amount ?? $r->total_amount ?? 0) }}</td>
                          <td class="text-end">
                            @if($rReqShow)
                              <a href="{{ route('gdf.subdirection.requests.show', $r->id) }}"
                                 class="gdf-btn" style="padding:.25rem .5rem; font-size:.7rem; border-color:var(--warning); background:rgba(245,158,11,.1);">
                                <i class="fas fa-eye"></i>
                              </a>
                            @endif
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="4" style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size:1.5rem; opacity:.3; display:block; margin-bottom:.5rem;"></i>
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

          <div class="gdf-divider" style="margin:1.5rem 0;"></div>

          <div class="d-flex flex-wrap gap-2">
            @if($hasMotoIndex)
              <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="gdf-btn">
                <i class="fas fa-motorcycle"></i> Gestión de Motos
              </a>
            @endif
            @if($rAreaUsers)
              <a href="{{ route('gdf.subdirection.area_users.index') }}" class="gdf-btn">
                <i class="fas fa-users-cog"></i> Asignación de Áreas
              </a>
            @endif
            @if($rMoves)
              <a href="{{ route('gdf.subdirection.movements.index') }}" class="gdf-btn">
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
(() => {
  // ====== Tema (aplicar lo antes posible) ======
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('gdf-theme');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
  root.setAttribute('data-theme', initialTheme);

  const themeToggle = document.getElementById('themeToggle');
  const themeIcon   = document.getElementById('themeIcon');

  function updateThemeIcon(theme){
    if (!themeIcon) return;
    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  }
  updateThemeIcon(initialTheme);

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const current = root.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('gdf-theme', next);
      updateThemeIcon(next);
      if (window.walletChart) updateChartTheme(window.walletChart, next);
    });
  }

  // ====== Barra de progreso ======
  const heroBar = document.getElementById('heroProgressBar');
  if (heroBar) {
    const pct = @json($spentPct);
    requestAnimationFrame(() => { heroBar.style.width = pct + '%'; });
  }

  // ====== ECharts (seguro) ======
  const chartEl = document.getElementById('chartWallet');
  const fallbackEl = document.getElementById('chartWalletFallback');

  const avail = @json((int)$globalAvail);
  const spent = @json((int)$globalSpent);

  function mountFallback() {
    if (chartEl) chartEl.classList.add('d-none');
    if (fallbackEl) fallbackEl.classList.remove('d-none');
  }

  function buildOptions(theme){
    return {
      tooltip: {
        trigger: 'item',
        formatter: '{b}: ${c} ({d}%)',
        backgroundColor: theme === 'dark' ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.95)',
        borderColor: theme === 'dark' ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.1)',
        textStyle: { color: theme === 'dark' ? 'rgba(255,255,255,.9)' : '#0f172a' }
      },
      legend: {
        bottom: '5%',
        textStyle: { color: theme === 'dark' ? 'rgba(255,255,255,.7)' : '#475569' }
      },
      series: [{
        type: 'pie',
        radius: ['55%', '85%'],
        avoidLabelOverlap: false,
        itemStyle: {
          borderRadius: 8,
          borderColor: theme === 'dark' ? '#0f1629' : '#ffffff',
          borderWidth: 3
        },
        label: { show: false },
        labelLine: { show: false },
        data: [
          {
            value: spent,
            name: 'Ejecutado',
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0,0,0,1,[
                { offset: 0, color: '#10b981' },
                { offset: 1, color: '#059669' }
              ])
            }
          },
          {
            value: avail,
            name: 'Disponible',
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0,0,0,1,[
                { offset: 0, color: '#6366f1' },
                { offset: 1, color: '#4f46e5' }
              ])
            }
          }
        ],
        emphasis: {
          itemStyle: { shadowBlur: 20, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,.3)' }
        }
      }],
      graphic: [{
        type: 'text',
        left: 'center',
        top: '42%',
        style: {
          text: `$ ${(spent + avail).toLocaleString('es-CO')}`,
          fill: theme === 'dark' ? 'rgba(255,255,255,.95)' : '#0f172a',
          fontSize: 18,
          fontWeight: 900
        }
      },{
        type: 'text',
        left: 'center',
        top: '55%',
        style: {
          text: 'Total',
          fill: theme === 'dark' ? 'rgba(255,255,255,.5)' : '#94a3b8',
          fontSize: 12,
          fontWeight: 700
        }
      }]
    };
  }

  if (chartEl && window.echarts) {
    try {
      window.walletChart = echarts.init(chartEl);
      window.walletChart.setOption(buildOptions(root.getAttribute('data-theme')));

      window.addEventListener('resize', () => {
        if (window.walletChart) window.walletChart.resize();
      });
    } catch (e) {
      console.warn('ECharts init error:', e);
      mountFallback();
    }
  } else {
    mountFallback();
  }

  function updateChartTheme(chart, theme) {
    if (!chart) return;
    chart.setOption(buildOptions(theme), true);
  }

})();
</script>
@endsection
