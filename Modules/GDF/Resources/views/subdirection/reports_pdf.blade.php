<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reportes Subdirección - {{ $year }}</title>
  <style>
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }
    h1,h2{ margin: 0 0 8px 0; }
    .muted{ color:#666; }
    .box{ border:1px solid #ddd; padding:10px; border-radius:8px; margin-bottom: 10px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #ddd; padding:6px; text-align:left; }
    th{ background:#f2f2f2; }
  </style>
</head>
<body>
  <h1>GDF · Subdirección · Reportes</h1>
  <div class="muted">Vigencia: {{ $year }}</div>

  <div class="box">
    <h2>Resumen</h2>
    <table>
      <tr><th>Solicitudes</th><td>{{ number_format($summary['total_requests'] ?? 0, 0, ',', '.') }}</td></tr>
      <tr><th>Total</th><td>$ {{ number_format($summary['total_amount'] ?? 0, 0, ',', '.') }}</td></tr>
      <tr><th>Transporte</th><td>$ {{ number_format($summary['total_transport'] ?? 0, 0, ',', '.') }}</td></tr>
      <tr><th>Viáticos</th><td>$ {{ number_format($summary['total_per_diem'] ?? 0, 0, ',', '.') }}</td></tr>
      <tr><th>Otros</th><td>$ {{ number_format($summary['total_other'] ?? 0, 0, ',', '.') }}</td></tr>
      <tr><th>Pendientes</th><td>{{ number_format($summary['pending_subdirection'] ?? 0, 0, ',', '.') }}</td></tr>
    </table>
  </div>

  <div class="box">
    <h2>Tendencia mensual</h2>
    <table>
      <thead>
        <tr><th>Mes</th><th>Solicitudes</th><th>Total</th></tr>
      </thead>
      <tbody>
      @foreach(($trend ?? []) as $r)
        <tr>
          <td>{{ $r['ym'] ?? ($r->ym ?? '—') }}</td>
          <td>{{ number_format($r['total_requests'] ?? ($r->total_requests ?? 0),0,',','.') }}</td>
          <td>$ {{ number_format($r['total_amount'] ?? ($r->total_amount ?? 0),0,',','.') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <div class="box">
    <h2>Top destinos</h2>
    <table>
      <thead>
        <tr><th>Destino</th><th>Tipo</th><th>Solicitudes</th><th>Costo</th></tr>
      </thead>
      <tbody>
      @foreach(($top ?? []) as $r)
        <tr>
          <td>{{ $r['label'] ?? ($r->label ?? '—') }}</td>
          <td>{{ $r['destination_type'] ?? ($r->destination_type ?? '—') }}</td>
          <td>{{ number_format($r['requests_count'] ?? ($r->requests_count ?? 0),0,',','.') }}</td>
          <td>$ {{ number_format($r['total_cost'] ?? ($r->total_cost ?? 0),0,',','.') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <div class="box">
    <h2>Mapa (Top puntos) - GDF</h2>
    <table>
      <thead><tr><th>Destino</th><th>Solicitudes</th><th>Costo</th><th>Lat</th><th>Lng</th></tr></thead>
      <tbody>
      @foreach(($mapGdf ?? []) as $r)
        <tr>
          <td>{{ $r['label'] ?? ($r->label ?? '—') }}</td>
          <td>{{ number_format($r['requests_count'] ?? ($r->requests_count ?? 0),0,',','.') }}</td>
          <td>$ {{ number_format($r['total_cost'] ?? ($r->total_cost ?? 0),0,',','.') }}</td>
          <td>{{ $r['lat'] ?? ($r->lat ?? '') }}</td>
          <td>{{ $r['lng'] ?? ($r->lng ?? '') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <div class="box">
    <h2>Mapa (Top puntos) - SITRAV</h2>
    <table>
      <thead><tr><th>Destino</th><th>Solicitudes</th><th>Costo</th><th>Lat</th><th>Lng</th></tr></thead>
      <tbody>
      @foreach(($mapSitrav ?? []) as $r)
        <tr>
          <td>{{ $r['label'] ?? ($r->label ?? '—') }}</td>
          <td>{{ number_format($r['requests_count'] ?? ($r->requests_count ?? 0),0,',','.') }}</td>
          <td>$ {{ number_format($r['total_cost'] ?? ($r->total_cost ?? 0),0,',','.') }}</td>
          <td>{{ $r['lat'] ?? ($r->lat ?? '') }}</td>
          <td>{{ $r['lng'] ?? ($r->lng ?? '') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</body>
</html>
