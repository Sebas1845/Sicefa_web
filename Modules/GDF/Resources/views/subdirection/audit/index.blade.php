@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Auditoría - Subdirección')

@section('content')
@php
  $year   = $year ?? now()->year;
  $area   = $area ?? 'all';
  $module = $module ?? 'all';
  $limit  = $limit ?? 10;

  $areaName = fn($k) => $k === 'academic' ? 'Académica' : ($k === 'campesena' ? 'Campesena' : strtoupper($k));

  $fmt = fn($s) => trim(mb_convert_case((string)$s, MB_CASE_TITLE, "UTF-8"));
@endphp

<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  :root{
    --bg:#f6f7fb; --card:#fff; --text:#0f172a; --muted:#64748b; --border:rgba(15,23,42,.10);
    --primary:#2e7d32; --radius:16px;
  }
  body{ background:var(--bg); color:var(--text); }
  .wrap{ max-width:1400px; margin:0 auto; padding:24px 18px 40px; }
  .cardx{ background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .bodyx{ padding:16px; }
  .hdr{
    background:var(--card); border:1px solid var(--border); border-radius:22px; padding:18px;
    display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px;
  }
  .title{ font-size:1.5rem; font-weight:900; margin:0; }
  .muted{ color:var(--muted); }
  .btnx{
    padding:.55rem .9rem; border-radius:12px; border:1px solid var(--border); background:#fff;
    color:var(--text); font-weight:900; font-size:.84rem; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem;
  }
  .btnx:hover{ border-color:rgba(46,125,50,.35); }
  .btnx.primary{ background:var(--primary); border-color:var(--primary); color:#fff; }
  .btnx.primary:hover{ color:#fff; }
  .tbl{ width:100%; font-size:.9rem; }
  .tbl thead th{
    color:var(--muted); font-weight:900; text-transform:uppercase; font-size:.72rem; letter-spacing:.05em;
    padding:.7rem .9rem; border-bottom:1px solid var(--border); background:#f8fafc;
  }
  .tbl tbody td{ padding:.8rem .9rem; border-bottom:1px solid var(--border); }
  .chart{ height:320px; width:100%; }
</style>

<div class="wrap">

  <div class="hdr">
    <div>
      <div class="muted" style="font-size:.85rem;">
        <i class="fas fa-home"></i> GDF <i class="fas fa-chevron-right" style="font-size:.7rem;"></i> Subdirección <i class="fas fa-chevron-right" style="font-size:.7rem;"></i> Auditoría
      </div>
      <h1 class="title">Auditoría de desplazamientos</h1>
      <div class="muted" style="margin-top:.25rem;">Top municipios/veredas por área (Académica vs Campesena)</div>
    </div>

    <form method="GET" class="d-flex flex-wrap gap-2 justify-content-end">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:110px;">
      <select class="form-select" name="area" style="width:170px;">
        <option value="all" {{ $area==='all'?'selected':'' }}>Todas</option>
        <option value="academic" {{ $area==='academic'?'selected':'' }}>Académica</option>
        <option value="campesena" {{ $area==='campesena'?'selected':'' }}>Campesena</option>
      </select>
      <select class="form-select" name="module" style="width:140px;">
        <option value="all" {{ $module==='all'?'selected':'' }}>Todos</option>
        <option value="gdf" {{ $module==='gdf'?'selected':'' }}>GDF</option>
        <option value="sitrav" {{ $module==='sitrav'?'selected':'' }}>SITRAV</option>
      </select>
      <select class="form-select" name="limit" style="width:120px;">
        @foreach([5,10,15,20] as $n)
          <option value="{{ $n }}" {{ (int)$limit===$n?'selected':'' }}>Top {{ $n }}</option>
        @endforeach
      </select>
      <button class="btnx primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
      <a class="btnx" href="{{ route('gdf.subdirection.dashboard') }}"><i class="fas fa-arrow-left"></i> Volver</a>
    </form>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="cardx"><div class="bodyx">
        <div style="font-weight:900;">Viajes por área ({{ $year }})</div>
        <div class="muted" style="font-size:.9rem;">Conteo total (según estados contables)</div>
        <div id="chartArea" class="chart"></div>
      </div></div>
    </div>

    <div class="col-lg-6">
      <div class="cardx"><div class="bodyx">
        <div style="font-weight:900;">Área por módulo</div>
        <div class="muted" style="font-size:.9rem;">GDF vs SITRAV apilado</div>
        <div id="chartStack" class="chart"></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    @foreach(['academic','campesena'] as $ak)
      <div class="col-lg-6">
        <div class="cardx"><div class="bodyx">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <div style="font-weight:900;">Top Municipios · {{ $areaName($ak) }}</div>
              <div class="muted" style="font-size:.9rem;">Top {{ (int)$limit }}</div>
            </div>
          </div>

          <div class="table-responsive mt-2">
            <table class="tbl">
              <thead><tr><th>Municipio</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              @forelse(($topMunicipalitiesByArea[$ak] ?? collect()) as $row)
                <tr>
                  <td>{{ $fmt($row->municipality ?? '—') }}</td>
                  <td class="text-end" style="font-weight:900;">{{ (int)($row->total ?? 0) }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="muted" style="padding:14px; text-align:center;">Sin datos</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div></div>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mt-1">
    @foreach(['academic','campesena'] as $ak)
      <div class="col-lg-6">
        <div class="cardx"><div class="bodyx">
          <div style="font-weight:900;">Top Veredas · {{ $areaName($ak) }}</div>
          <div class="muted" style="font-size:.9rem;">Top {{ (int)$limit }}</div>

          <div class="table-responsive mt-2">
            <table class="tbl">
              <thead><tr><th>Vereda</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              @forelse(($topVillagesByArea[$ak] ?? collect()) as $row)
                <tr>
                  <td>{{ $fmt($row->village ?? '—') }}</td>
                  <td class="text-end" style="font-weight:900;">{{ (int)($row->total ?? 0) }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="muted" style="padding:14px; text-align:center;">Sin datos</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div></div>
      </div>
    @endforeach
  </div>

</div>

<script>
(() => {
  const chart = @json($chart);

  // 1) Barras por área
  const el1 = document.getElementById('chartArea');
  if (el1 && window.echarts) {
    const c1 = echarts.init(el1);
    c1.setOption({
      tooltip: { trigger: 'axis' },
      xAxis: { type: 'category', data: chart.labelsAreas.map(x => x === 'academic' ? 'Académica' : 'Campesena') },
      yAxis: { type: 'value' },
      series: [{ type: 'bar', data: chart.countsByArea }]
    });
    window.addEventListener('resize', () => c1.resize());
  }

  // 2) Apilado área x módulo
  const el2 = document.getElementById('chartStack');
  if (el2 && window.echarts) {
    const c2 = echarts.init(el2);
    c2.setOption({
      tooltip: { trigger: 'axis' },
      legend: { data: ['GDF','SITRAV'] },
      xAxis: { type: 'category', data: chart.labelsAreas.map(x => x === 'academic' ? 'Académica' : 'Campesena') },
      yAxis: { type: 'value' },
      series: [
        { name:'GDF', type:'bar', stack:'total', data: chart.stack.gdf },
        { name:'SITRAV', type:'bar', stack:'total', data: chart.stack.sitrav },
      ]
    });
    window.addEventListener('resize', () => c2.resize());
  }
})();
</script>
@endsection
