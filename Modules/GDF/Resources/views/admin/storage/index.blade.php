@extends('gdf::layouts.masteruser')
@section('title', 'Admin | Storage')

@push('styles')
<style>
/* (TU CSS IGUAL, NO LO CAMBIÉ) */
:root{
  --sa-bg:#ffffff;
  --sa-soft:#f6f7f9;
  --sa-line:#e6e8ee;
  --sa-text:#111827;
  --sa-muted:#6b7280;
  --sa-accent:#111827;
  --sa-danger:#dc2626;

  --sa-radius:14px;
  --sa-shadow:0 6px 18px rgba(16,24,40,.06);
  --sa-font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  --sa-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
}
html, body{ background: var(--sa-bg) !important; color: var(--sa-text) !important; }
body{ font-family: var(--sa-font) !important; }
.content-wrapper, .main-content, .container, .container-fluid, .content, .app-content{
  background: var(--sa-bg) !important;
  color: var(--sa-text) !important;
}
a{ color: var(--sa-text); }
a:hover{ color: var(--sa-text); opacity:.8; }
.page-wrap{ max-width: 1320px; margin: 0 auto; padding: 20px 12px; }
.hero{
  background: var(--sa-bg);
  border: 1px solid var(--sa-line);
  border-radius: var(--sa-radius);
  padding: 16px;
  box-shadow: var(--sa-shadow);
}
.hero-title{ margin:0; font-weight:800; font-size:1.25rem; letter-spacing:-.01em; }
.hero-sub{ margin-top:6px; color: var(--sa-muted); font-weight:600; font-size:.92rem; }
.hero-actions{ display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.btnx{
  display:inline-flex; align-items:center; gap:8px;
  padding: 9px 12px;
  border-radius: 12px;
  border: 1px solid var(--sa-line);
  background: var(--sa-bg);
  color: var(--sa-text);
  text-decoration:none;
  font-weight:700;
  transition:.12s ease;
}
.btnx:hover{ background: var(--sa-soft); }
.btnx-primary{
  background: var(--sa-text);
  color: #fff;
  border-color: var(--sa-text);
}
.btnx-primary:hover{ filter: brightness(1.02); color:#fff; }
.alertx{
  margin-top: 12px;
  border-radius: var(--sa-radius);
  padding: 12px 14px;
  border: 1px solid var(--sa-line);
  background: var(--sa-soft);
  color: var(--sa-text);
  font-weight:700;
}
.alertx-success{ border-left: 4px solid #16a34a; }
.alertx-danger{ border-left: 4px solid var(--sa-danger); }
.bar{
  margin-top: 12px;
  background: var(--sa-bg);
  border: 1px solid var(--sa-line);
  border-radius: var(--sa-radius);
  padding: 12px 14px;
  box-shadow: var(--sa-shadow);
  display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;
}
.crumbs{ list-style:none; margin:0; padding:0; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.sep{ color: #c0c4ce; font-weight:900; }
.crumbs a{ font-weight:800; text-decoration:none; }
.path-pill{
  font-family: var(--sa-mono);
  font-size: .82rem;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid var(--sa-line);
  background: var(--sa-soft);
  color: var(--sa-text);
}
.toolbar{
  margin-top: 12px;
  background: var(--sa-bg);
  border: 1px solid var(--sa-line);
  border-radius: var(--sa-radius);
  padding: 12px 14px;
  box-shadow: var(--sa-shadow);
  display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;
}
.hint{ color: var(--sa-muted); font-weight:600; font-size:.9rem; }
.count-pill{
  display:inline-flex; align-items:center; gap:8px;
  padding: 7px 10px;
  border-radius: 999px;
  border: 1px solid var(--sa-line);
  background: var(--sa-soft);
  font-weight:800;
  color: var(--sa-text);
  font-size:.9rem;
}
.btn-solid-danger{
  border: 1px solid rgba(220,38,38,.25);
  border-radius: 12px;
  padding: 9px 12px;
  background: rgba(220,38,38,.10);
  color: #991b1b;
  font-weight:800;
  cursor:pointer;
  transition:.12s ease;
}
.btn-solid-danger:hover{ background: rgba(220,38,38,.14); }
.grid{ margin-top: 12px; display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 992px){ .grid{ grid-template-columns: 1fr; } .hero-actions{ justify-content:flex-start; } }
.cardx{
  background: var(--sa-bg);
  border: 1px solid var(--sa-line);
  border-radius: var(--sa-radius);
  box-shadow: var(--sa-shadow);
  overflow:hidden;
}
.cardx-head{
  padding: 12px 14px;
  background: var(--sa-bg);
  border-bottom: 1px solid var(--sa-line);
  display:flex; justify-content:space-between; gap:10px; align-items:center;
}
.cardx-title{ margin:0; font-weight:900; font-size:1rem; }
.cardx-sub{ color: var(--sa-muted); font-weight:600; font-size:.88rem; }
.badge2{
  display:inline-flex; align-items:center;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid var(--sa-line);
  background: var(--sa-soft);
  color: var(--sa-text);
  font-weight:800;
  font-size:.82rem;
}
.list{ padding: 10px; }
.item{
  border: 1px solid var(--sa-line);
  background: var(--sa-bg);
  border-radius: 12px;
  padding: 10px 12px;
  display:flex; justify-content:space-between; gap:12px;
  margin-bottom: 10px;
  transition:.12s ease;
}
.item:hover{ background: var(--sa-soft); }
.item-left{ display:flex; gap:10px; align-items:flex-start; min-width:0; }
.ck{ width:18px; height:18px; margin-top:3px; cursor:pointer; }
.ico{
  width: 38px; height: 38px; border-radius: 10px;
  display:flex; align-items:center; justify-content:center;
  background: var(--sa-soft);
  border: 1px solid var(--sa-line);
  font-size: 18px;
  flex: 0 0 auto;
}
.meta{ min-width:0; }
.name{
  margin:0; font-weight:900; color: var(--sa-text);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.smallpath{
  margin-top: 4px;
  font-family: var(--sa-mono);
  font-size: .78rem;
  color: var(--sa-muted);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.badges{ display:flex; gap:8px; flex-wrap:wrap; margin-top: 8px; }
.badges .badge2{ background: var(--sa-bg); }
.item-right{ display:flex; gap:10px; align-items:center; flex:0 0 auto; }
.mini-btn{
  border: 1px solid var(--sa-line);
  background: var(--sa-bg);
  border-radius: 12px;
  padding: 8px 10px;
  cursor:pointer;
  font-weight:800;
  color: var(--sa-text);
  text-decoration:none;
  transition:.12s ease;
}
.mini-btn:hover{ background: var(--sa-soft); }
.mini-danger{
  border-color: rgba(220,38,38,.25);
  background: rgba(220,38,38,.08);
  color:#991b1b;
}
.mini-danger:hover{ background: rgba(220,38,38,.12); }
.empty{ padding: 18px 12px; text-align:center; color: var(--sa-muted); font-weight:700; }
.footnote{ margin-top: 12px; color: var(--sa-muted); font-weight:600; font-size:.9rem; }
</style>
@endpush

@section('content')
<div class="page-wrap">

  {{-- HERO --}}
  <div class="hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="hero-title">📦 Storage Admin</h1>
        <div class="hero-sub">
          Administra archivos y carpetas en <b>storage/app/public</b>. Elimina con cuidado.
        </div>
        <div class="hero-badges">
          <span class="chip">🗂️ Disco: <code>{{ $diskName }}</code></span>
          <span class="chip">📍 Raíz: <code>storage/app/public</code></span>
        </div>
      </div>
      <div class="hero-actions">
        <a class="btnx" href="{{ route('gdf.admin.storage.index') }}">🏠 Ir a raíz</a>
        <a class="btnx btnx-primary" href="{{ route('gdf.admin.dashboard') }}">↩️ Volver al panel</a>
      </div>
    </div>
  </div>

  {{-- Alerts --}}
  @if (session('error'))
    <div class="alertx alertx-danger">⚠️ <div>{{ session('error') }}</div></div>
  @endif
  @if (session('success'))
    <div class="alertx alertx-success">✅ <div>{{ session('success') }}</div></div>
  @endif

  {{-- Breadcrumb --}}
  <div class="bar">
    <div>
      <ul class="crumbs">
        <li><a href="{{ route('gdf.admin.storage.index') }}">public</a></li>
        @foreach($crumbs as $c)
          <li class="sep">›</li>
          <li>
            <a href="{{ route('gdf.admin.storage.index', ['path' => $c['path']]) }}">{{ $c['label'] }}</a>
          </li>
        @endforeach
      </ul>
      <div class="footnote">Navegación por carpetas. Puedes borrar carpetas completas.</div>
    </div>
    <div class="path-pill">📍 {{ $path ?: '/' }}</div>
  </div>

  {{-- ✅ BULK FORM SOLO (SIN ENVOLVER EL GRID) --}}
  <form method="POST" action="{{ route('gdf.admin.storage.deleteBulk') }}" id="bulkForm">
    @csrf
    <div class="toolbar">
      <div class="toolbar-left">
        ✅ Acciones en lote
        <span class="hint">Selecciona carpetas/archivos y elimina.</span>
      </div>
      <div class="toolbar-right">
        <span class="count-pill">Seleccionados: <span id="selCount">0</span></span>
        <button type="submit" class="btn-solid-danger" onclick="return confirmBulk()">
          🗑️ Eliminar seleccionados
        </button>
      </div>
    </div>
    <div id="bulkHidden"></div>
  </form>

  {{-- ✅ GRID AFUERA DEL FORM (YA NO HAY FORMS ANIDADOS) --}}
  <div class="grid">

    {{-- Carpetas --}}
    <div class="cardx">
      <div class="cardx-head">
        <div>
          <h3 class="cardx-title">📁 Carpetas</h3>
          <div class="cardx-sub">Ingresa o elimina carpetas completas</div>
        </div>
        <div class="badge2">Total: {{ is_countable($directories) ? count($directories) : 0 }}</div>
      </div>

      <div class="list">
        @forelse($directories as $dir)
          @php
            $name = basename($dir);
            $st = $dirStats[$dir] ?? ['files'=>0,'size_h'=>'0 B'];
          @endphp

          <div class="item">
            <div class="item-left">
              <input type="checkbox" class="ck bulkCheck" data-type="dir" data-path="{{ $dir }}">
              <div class="ico">📁</div>

              <div class="meta">
                <a href="{{ route('gdf.admin.storage.index', ['path' => $dir]) }}" class="mini-btn" style="border:0;padding:0;background:transparent;">
                  <div>
                    <p class="name">{{ $name }}</p>
                    <div class="smallpath">{{ $dir }}</div>
                  </div>
                </a>

                <div class="badges">
                  <span class="badge2">📄 {{ (int)$st['files'] }} archivos</span>
                  <span class="badge2">💾 {{ $st['size_h'] }}</span>
                </div>
              </div>
            </div>

            <div class="item-right">
              <a class="mini-btn" href="{{ route('gdf.admin.storage.index', ['path' => $dir]) }}">➡️ Abrir</a>

              {{-- ✅ FORM INDIVIDUAL (YA NO ESTÁ ADENTRO DE OTRO FORM) --}}
              <form method="POST" action="{{ route('gdf.admin.storage.delete') }}" class="d-inline">
                @csrf
                <input type="hidden" name="type" value="dir">
                <input type="hidden" name="path" value="{{ $dir }}">
                <button class="mini-btn mini-danger" onclick="return confirm('¿Eliminar carpeta completa {{ $name }}?')">🗑️</button>
              </form>
            </div>
          </div>
        @empty
          <div class="empty">No hay carpetas en esta ubicación.</div>
        @endforelse
      </div>
    </div>

    {{-- Archivos --}}
    <div class="cardx">
      <div class="cardx-head">
        <div>
          <h3 class="cardx-title">📄 Archivos</h3>
          <div class="cardx-sub">Elimina archivos individuales</div>
        </div>
        <div class="badge2">Total: {{ is_countable($files) ? count($files) : 0 }}</div>
      </div>

      <div class="list">
        @forelse($files as $f)
          <div class="item">
            <div class="item-left">
              <input type="checkbox" class="ck bulkCheck" data-type="file" data-path="{{ $f['path'] }}">
              <div class="ico file">📄</div>

              <div class="meta">
                <p class="name">{{ $f['name'] }}</p>
                <div class="smallpath">{{ $f['path'] }}</div>

                <div class="badges">
                  <span class="badge2">💾 {{ $f['size_h'] }}</span>
                  <span class="badge2">🕒 {{ $f['last'] ?? '—' }}</span>
                </div>
              </div>
            </div>

            <div class="item-right">
              {{-- ✅ FORM INDIVIDUAL --}}
              <form method="POST" action="{{ route('gdf.admin.storage.delete') }}" class="d-inline">
                @csrf
                <input type="hidden" name="type" value="file">
                <input type="hidden" name="path" value="{{ $f['path'] }}">
                <button class="mini-btn mini-danger" onclick="return confirm('¿Eliminar este archivo?')">🗑️</button>
              </form>
            </div>
          </div>
        @empty
          <div class="empty">No hay archivos en esta ubicación.</div>
        @endforelse
      </div>
    </div>

  </div>

  <div class="footnote">
    💡 Consejo: borra primero carpetas grandes para liberar espacio rápido.
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  const bulkHidden = document.getElementById('bulkHidden');
  const checks = document.querySelectorAll('.bulkCheck');
  const selCount = document.getElementById('selCount');

  function rebuildHidden(){
    bulkHidden.innerHTML = '';
    let i = 0, count = 0;

    checks.forEach(chk => {
      if(!chk.checked) return;
      count++;

      const type = chk.dataset.type;
      const path = chk.dataset.path;

      const inType = document.createElement('input');
      inType.type = 'hidden';
      inType.name = `items[${i}][type]`;
      inType.value = type;

      const inPath = document.createElement('input');
      inPath.type = 'hidden';
      inPath.name = `items[${i}][path]`;
      inPath.value = path;

      bulkHidden.appendChild(inType);
      bulkHidden.appendChild(inPath);

      i++;
    });

    if (selCount) selCount.textContent = String(count);
    return count;
  }

  checks.forEach(c => c.addEventListener('change', rebuildHidden));
  rebuildHidden();

  window.confirmBulk = function(){
    const total = rebuildHidden();
    if(!total){
      alert('Selecciona al menos un archivo o carpeta.');
      return false;
    }
    return confirm(`¿Eliminar ${total} elemento(s) seleccionados? Esta acción no se puede deshacer.`);
  }
})();
</script>
@endpush
