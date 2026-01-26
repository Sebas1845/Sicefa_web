@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Reportes')

@section('content')
@php
  $isSubdirection = function_exists('checkRol') ? (checkRol('gdf.subdirection') || checkRol('gdf.superadmin')) : false;
  if(!$isSubdirection){ abort(403); }

  $year = $year ?? (int) request('year', now()->year);
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js"></script>

<style>
  :root { --b: rgba(0,0,0,.08); --shadow: 0 10px 26px rgba(0,0,0,.10); }
  .rep-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; }
  .rep-card { border:1px solid var(--b); border-radius:16px; box-shadow: var(--shadow); background:#fff; }
  .kpi { font-size:1.45rem; font-weight:800; }
  .chart { height: 320px; width:100%; }
  #mapGdf, #mapSitrav { height: 520px; border-radius: 16px; overflow:hidden; }
  .pill { display:inline-flex; align-items:center; gap:8px; padding:.35rem .6rem; border-radius:999px; border:1px solid var(--b); background: #f8fafc; }
  .muted { color: rgba(0,0,0,.55); }
</style>

<div class="container py-4">

  <div class="rep-head mb-3">
    <div>
      <div class="text-muted small">GDF / Subdirección</div>
      <h3 class="fw-bold mb-1">Reportes</h3>
      <div class="muted">Resumen, tendencia, top destinos y mapa (puntos con más solicitudes) separado por módulo.</div>
      <div class="mt-2 d-flex gap-2 flex-wrap">
        <span class="pill">Vigencia: <b id="chipYear">{{ $year }}</b></span>
        <span class="pill">Mapa: <b>Leaflet</b></span>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="{{ route('gdf.subdirection.dashboard', ['year'=>$year]) }}">Volver</a>
      <a class="btn btn-outline-warning" id="btnPdf" href="{{ route('gdf.subdirection.reports.export.pdf', ['year'=>$year]) }}">PDF</a>
      <a class="btn btn-outline-info" id="btnZip" href="{{ route('gdf.subdirection.reports.export.zip', ['year'=>$year]) }}">ZIP</a>
    </div>
  </div>

  {{-- Filtros --}}
  <form id="filters" class="rep-card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Año</label>
        <input class="form-control" type="number" name="year" value="{{ $year }}">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Estado</label>
        <select class="form-select" name="status">
          <option value="">Todos</option>
          <option value="submitted">submitted</option>
          <option value="approved">approved</option>
          <option value="executed">executed</option>
          <option value="rejected">rejected</option>
          <option value="returned">returned</option>
          <option value="cancelled">cancelled</option>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Área (id)</label>
        <input class="form-control" name="area_id" type="number" placeholder="Ej: 1">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Rubro (id)</label>
        <input class="form-control" name="budget_item_id" type="number" placeholder="Ej: 12">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Desde</label>
        <input class="form-control" name="from" type="date">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Hasta</label>
        <input class="form-control" name="to" type="date">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-muted">Top puntos (mapa)</label>
        <input class="form-control" name="limit" type="number" value="200" min="50" max="2000">
      </div>

      <div class="col-12 col-md-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Aplicar</button>
        <button class="btn btn-outline-secondary" type="button" id="btnReset">Reset</button>
      </div>
    </div>
  </form>

  {{-- KPIs + Trend --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-4">
      <div class="rep-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Solicitudes</div>
            <div class="kpi" id="k_total_requests">—</div>
            <div class="text-muted small mt-2">Pendientes: <b id="k_pending">—</b></div>
          </div>
        </div>
        <hr>
        <div class="text-muted small">Total ($): <b id="k_total_amount">—</b></div>
        <div class="text-muted small">Transporte: <b id="k_total_transport">—</b></div>
        <div class="text-muted small">Viáticos: <b id="k_total_per_diem">—</b></div>
        <div class="text-muted small">Otros: <b id="k_total_other">—</b></div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="rep-card p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <div class="fw-bold">Tendencia mensual</div>
            <div class="text-muted small">Total ($) y cantidad de solicitudes por mes</div>
          </div>
        </div>
        <div id="chartTrend" class="chart mt-2"></div>
      </div>
    </div>
  </div>

  {{-- Top destinos + Mapas --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-5">
      <div class="rep-card p-3">
        <div class="fw-bold">Top destinos</div>
        <div class="text-muted small">Por costo total (SUM(ts.total_cost))</div>
        <div id="chartTop" class="chart mt-2"></div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="rep-card p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <div class="fw-bold">Mapa de puntos</div>
            <div class="text-muted small">Los destinos con más solicitudes (separado por módulo)</div>
          </div>

          <ul class="nav nav-pills" id="mapTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-gdf" data-bs-toggle="pill" data-bs-target="#pane-gdf" type="button" role="tab">
                GDF
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-sitrav" data-bs-toggle="pill" data-bs-target="#pane-sitrav" type="button" role="tab">
                SITRAV
              </button>
            </li>
          </ul>
        </div>

        <div class="tab-content mt-3">
          <div class="tab-pane fade show active" id="pane-gdf" role="tabpanel">
            <div id="mapGdf"></div>
          </div>
          <div class="tab-pane fade" id="pane-sitrav" role="tabpanel">
            <div id="mapSitrav"></div>
          </div>
        </div>

        <div class="text-muted small mt-2">
          Los círculos más grandes representan más solicitudes; el tooltip muestra conteo y costo total.
        </div>
      </div>
    </div>
  </div>

</div>

<script>
(() => {
  const routes = {
    summary: @json(route('gdf.subdirection.reports.summary')),
    trend:   @json(route('gdf.subdirection.reports.trend')),
    top:     @json(route('gdf.subdirection.reports.top')),
    mapGdf:  @json(route('gdf.subdirection.reports.map.gdf')),
    mapSitr: @json(route('gdf.subdirection.reports.map.sitrav')),
    pdf:     @json(route('gdf.subdirection.reports.export.pdf')),
    zip:     @json(route('gdf.subdirection.reports.export.zip')),
  };

  const money = (n) => '$ ' + Number(n || 0).toLocaleString('es-CO', {maximumFractionDigits: 0});

  const qsFromForm = (form) => {
    const fd = new FormData(form);
    const p = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      const val = String(v ?? '').trim();
      if (val !== '') p.set(k, val);
    }
    return p;
  };

  // ---------- Charts ----------
  const trendChart = echarts.init(document.getElementById('chartTrend'));
  const topChart = echarts.init(document.getElementById('chartTop'));

  const renderTrend = (rows) => {
    const x = rows.map(r => r.ym);
    const total = rows.map(r => Number(r.total_amount || 0));
    const cnt = rows.map(r => Number(r.total_requests || 0));

    trendChart.setOption({
      tooltip: { trigger: 'axis' },
      legend: { data: ['Total ($)', 'Solicitudes'] },
      grid: { left: 55, right: 20, top: 40, bottom: 35 },
      xAxis: { type: 'category', data: x },
      yAxis: [{ type: 'value' }, { type: 'value' }],
      series: [
        { name: 'Total ($)', type: 'line', smooth: true, data: total },
        { name: 'Solicitudes', type: 'bar', yAxisIndex: 1, data: cnt }
      ]
    });
  };

  const renderTop = (rows) => {
    const labels = rows.map(r => (r.label || '—')).reverse();
    const costs = rows.map(r => Number(r.total_cost || 0)).reverse();

    topChart.setOption({
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      grid: { left: 10, right: 20, top: 10, bottom: 10, containLabel: true },
      xAxis: { type: 'value' },
      yAxis: { type: 'category', data: labels },
      series: [{ type: 'bar', data: costs }]
    });
  };

  // ---------- Leaflet maps ----------
  const initMap = (id) => {
    const m = L.map(id, { zoomControl: true }).setView([4.60971, -74.08175], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap'
    }).addTo(m);

    return m;
  };

  const mapGdf = initMap('mapGdf');
  const mapSitr = initMap('mapSitrav');

  let layerGdf = L.layerGroup().addTo(mapGdf);
  let layerSitr = L.layerGroup().addTo(mapSitr);

  const drawPoints = (map, layer, rows) => {
    layer.clearLayers();

    if (!rows || !rows.length) return;

    // Escala simple por requests_count
    const maxCount = Math.max(...rows.map(r => Number(r.requests_count || 0)), 1);

    rows.forEach(r => {
      const lat = Number(r.lat);
      const lng = Number(r.lng);
      if (!isFinite(lat) || !isFinite(lng)) return;

      const count = Number(r.requests_count || 0);
      const cost = Number(r.total_cost || 0);

      const radius = 6 + Math.round((count / maxCount) * 18); // 6..24 aprox

      const marker = L.circleMarker([lat, lng], {
        radius,
        weight: 1,
        opacity: 0.9,
        fillOpacity: 0.55
      });

      const html = `
        <div style="min-width:220px">
          <div style="font-weight:800">${(r.label || 'Destino')}</div>
          <div style="opacity:.75">Tipo: <b>${(r.destination_type || '—')}</b></div>
          <div style="margin-top:6px">Solicitudes: <b>${count.toLocaleString('es-CO')}</b></div>
          <div>Costo total: <b>${money(cost)}</b></div>
        </div>
      `;

      marker.bindTooltip(html, { sticky: true });
      marker.addTo(layer);
    });

    // Ajustar bounds
    const bounds = layer.getBounds();
    if (bounds.isValid()) map.fitBounds(bounds.pad(0.15));
  };

  // ---------- KPIs ----------
  const setKpis = (s) => {
    document.getElementById('k_total_requests').textContent = (s.total_requests ?? 0).toLocaleString('es-CO');
    document.getElementById('k_total_amount').textContent = money(s.total_amount);
    document.getElementById('k_total_transport').textContent = money(s.total_transport);
    document.getElementById('k_total_per_diem').textContent = money(s.total_per_diem);
    document.getElementById('k_total_other').textContent = money(s.total_other);
    document.getElementById('k_pending').textContent = (s.pending_subdirection ?? 0).toLocaleString('es-CO');
  };

  // ---------- Load all ----------
  const refreshAll = async (evt) => {
    if (evt) evt.preventDefault();

    const form = document.getElementById('filters');
    const params = qsFromForm(form);
    document.getElementById('chipYear').textContent = params.get('year') || '{{ $year }}';

    // export links con mismos filtros
    document.getElementById('btnPdf').href = routes.pdf + '?' + params.toString();
    document.getElementById('btnZip').href = routes.zip + '?' + params.toString();

    const [summary, trend, top, ptsGdf, ptsSitr] = await Promise.all([
      fetch(routes.summary + '?' + params.toString()).then(r => r.json()),
      fetch(routes.trend + '?' + params.toString()).then(r => r.json()),
      fetch(routes.top + '?' + params.toString()).then(r => r.json()),
      fetch(routes.mapGdf + '?' + params.toString()).then(r => r.json()),
      fetch(routes.mapSitr + '?' + params.toString()).then(r => r.json()),
    ]);

    setKpis(summary);
    renderTrend(Array.isArray(trend) ? trend : []);
    renderTop(Array.isArray(top) ? top : []);

    drawPoints(mapGdf, layerGdf, Array.isArray(ptsGdf) ? ptsGdf : []);
    drawPoints(mapSitr, layerSitr, Array.isArray(ptsSitr) ? ptsSitr : []);

    setTimeout(() => { mapGdf.invalidateSize(); mapSitr.invalidateSize(); }, 200);
  };

  document.getElementById('filters').addEventListener('submit', refreshAll);
  document.getElementById('btnReset').addEventListener('click', () => {
    const f = document.getElementById('filters');
    f.reset();
    f.querySelector('[name="year"]').value = '{{ $year }}';
    f.querySelector('[name="limit"]').value = '200';
    refreshAll();
  });

  window.addEventListener('resize', () => {
    trendChart.resize();
    topChart.resize();
    mapGdf.invalidateSize();
    mapSitr.invalidateSize();
  });

  // Bootstrap tabs: al cambiar, Leaflet necesita invalidateSize
  document.addEventListener('shown.bs.tab', () => {
    setTimeout(() => { mapGdf.invalidateSize(); mapSitr.invalidateSize(); }, 150);
  });

  refreshAll();
})();
</script>
@endsection
