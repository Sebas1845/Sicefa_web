{{-- Modules/GDF/Resources/views/support/documents_modal_body.blade.php --}}
{{-- ⚠️ NO usar @extends aquí - esta es una vista parcial pura --}}

@php
  use Carbon\Carbon;

  $badge = fn($s) => match((string)$s){
    'approved' => 'success',
    'rejected' => 'danger',
    default    => 'secondary',
  };

  // Función para detectar si un archivo es previsualizable
  $canPreview = function($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);
  };

  $getFileIcon = function($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    return match($ext) {
      'pdf' => 'bi-file-earmark-pdf text-danger',
      'doc', 'docx' => 'bi-file-earmark-word text-primary',
      'xls', 'xlsx' => 'bi-file-earmark-excel text-success',
      'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bi-file-earmark-image text-info',
      'zip', 'rar' => 'bi-file-earmark-zip text-warning',
      'txt' => 'bi-file-earmark-text text-secondary',
      default => 'bi-file-earmark text-secondary'
    };
  };

  $docs = $docs ?? collect();
  $sigacDocs = $sigacDocs ?? collect();
  $hasAny = $docs->count() > 0 || $sigacDocs->count() > 0;

  // ✅ URL para recargar el body del modal (misma ruta documentsIndex)
  $documentsIndexUrl = $documentsIndexUrl ?? null;

  // Seguridad: prefijo de rutas
  $routePrefix = $routePrefix ?? 'gdf.support.academic';

  // Helper simple
  $safe = fn($v, $fallback='N/D') => (is_null($v) || $v === '') ? $fallback : $v;
@endphp

@if(!$hasAny)
  <div class="text-center py-4 text-muted">
    <i class="bi bi-file-earmark-text" style="font-size:2rem;opacity:.3;"></i>
    <div class="mt-2">Esta solicitud no tiene documentos cargados.</div>
  </div>
@else

  {{-- =========================
       1) Documentos GDF
  ========================= --}}
  @if($docs->count() > 0)
    <div class="mb-2 fw-semibold">
      <i class="bi bi-folder2-open me-1"></i> Documentos GDF
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Documento</th>
            <th style="width:120px;">Estado</th>
            <th>Notas</th>
            <th class="text-end" style="width:320px;">Acciones</th>
          </tr>
        </thead>

        <tbody>
        @foreach($docs as $d)
          @php
            $fname   = $d->original_name ?? $d->filename ?? ('Doc #'.$d->id);
            $created = $d->created_at ? $d->created_at->format('Y-m-d H:i') : 'N/D';

            // ✅ DESCARGA (attachment)
            $downloadUrl = route($routePrefix.'.documents.download', ['documentId'=>$d->id]);

            // ✅ PREVIEW (inline) -> verifica si existe la ruta
            $previewUrl = \Illuminate\Support\Facades\Route::has($routePrefix.'.documents.preview')
              ? route($routePrefix.'.documents.preview', ['documentId'=>$d->id])
              : null;

            $reviewUrl = route($routePrefix.'.documents.review', ['documentId'=>$d->id]);
          @endphp

          <tr>
            <td class="fw-semibold">
              <div class="d-flex align-items-center gap-2">
                <i class="{{ $getFileIcon($fname) }}" style="font-size:1.2rem;"></i>
                <div>
                  <div class="text-truncate" style="max-width:420px;">{{ $fname }}</div>
                  <div class="small text-muted">{{ $created }}</div>
                </div>
              </div>
            </td>

            <td>
              <span class="badge bg-{{ $badge($d->status ?? 'pending') }}">
                {{ ucfirst($d->status ?? 'pending') }}
              </span>
            </td>

            <td class="small text-muted">{{ $d->review_notes ?? '—' }}</td>

            <td class="text-end">

              {{-- ✅ CORREGIDO: Ver/Previsualizar --}}
              @if($canPreview($fname))
                @if($previewUrl)
                  {{-- Previsualizar en nueva pestaña (inline) --}}
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ $previewUrl }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     title="Ver documento">
                    <i class="bi bi-eye"></i>
                  </a>
                @else
                  {{-- Fallback: abrir descarga en nueva pestaña --}}
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ $downloadUrl }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     title="Abrir documento">
                    <i class="bi bi-eye"></i>
                  </a>
                @endif
              @endif

              {{-- Descargar (attachment) --}}
              <a class="btn btn-sm btn-outline-secondary"
                 href="{{ $downloadUrl }}"
                 download
                 title="Descargar">
                <i class="bi bi-download"></i>
              </a>

              {{-- Aprobar --}}
              @if(($d->status ?? 'pending') !== 'approved')
                <form class="d-inline js-approve-form"
                      data-document-id="{{ $d->id }}"
                      data-document-name="{{ $fname }}"
                      data-review-url="{{ $reviewUrl }}">
                  @csrf
                  <button class="btn btn-sm btn-success" type="button"
                          onclick="approveDocument(this)"
                          title="Aprobar">
                    <i class="bi bi-check2"></i>
                  </button>
                </form>
              @endif

              {{-- Rechazar --}}
              @if(($d->status ?? 'pending') !== 'rejected')
                <form class="d-inline js-reject-form"
                      data-document-id="{{ $d->id }}"
                      data-document-name="{{ $fname }}"
                      data-review-url="{{ $reviewUrl }}">
                  @csrf
                  <button class="btn btn-sm btn-danger" type="button"
                          onclick="rejectDocument(this)"
                          title="Rechazar">
                    <i class="bi bi-x"></i>
                  </button>
                </form>
              @endif

            </td>
          </tr>
        @endforeach
        </tbody>

      </table>
    </div>
  @endif

  {{-- =========================
       2) Documentos SIGAC (SITRAV)
  ========================= --}}
  @if($sigacDocs->count() > 0)
    <hr class="my-3">

    <div class="mb-2 fw-semibold">
      <i class="bi bi-folder me-1"></i> Documentos SIGAC
      <span class="text-muted small fw-normal"> (solo lectura)</span>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Documento</th>
            <th class="text-end" style="width:220px;">Acciones</th>
          </tr>
        </thead>

        <tbody>
        @foreach($sigacDocs as $sd)
          @php
            $sname    = $sd->name ?? ('Doc SIGAC #'.$sd->id);
            $screated = !empty($sd->created_at) ? Carbon::parse($sd->created_at)->format('Y-m-d H:i') : 'N/D';

            // ✅ DESCARGA
            $sDownloadUrl = route($routePrefix.'.documents.sigac.download', ['documentId'=>$sd->id]);

            // ✅ PREVIEW (inline)
            $sPreviewUrl = \Illuminate\Support\Facades\Route::has($routePrefix.'.documents.sigac.preview')
              ? route($routePrefix.'.documents.sigac.preview', ['documentId'=>$sd->id])
              : null;
          @endphp

          <tr>
            <td class="fw-semibold">
              <div class="d-flex align-items-center gap-2">
                <i class="{{ $getFileIcon($sname) }}" style="font-size:1.2rem;"></i>
                <div>
                  <div class="text-truncate" style="max-width:520px;">{{ $sname }}</div>
                  <div class="small text-muted">{{ $screated }}</div>
                </div>
              </div>
            </td>

            <td class="text-end">

              {{-- ✅ CORREGIDO: Ver/Previsualizar SIGAC --}}
              @if($canPreview($sname))
                @if($sPreviewUrl)
                  {{-- Previsualizar en nueva pestaña (inline) --}}
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ $sPreviewUrl }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     title="Ver documento SIGAC">
                    <i class="bi bi-eye"></i>
                  </a>
                @else
                  {{-- Fallback: abrir descarga en nueva pestaña --}}
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ $sDownloadUrl }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     title="Abrir documento SIGAC">
                    <i class="bi bi-eye"></i>
                  </a>
                @endif
              @endif

              {{-- Descargar --}}
              <a class="btn btn-sm btn-outline-secondary"
                 href="{{ $sDownloadUrl }}"
                 download
                 title="Descargar SIGAC">
                <i class="bi bi-download"></i>
              </a>

            </td>
          </tr>
        @endforeach
        </tbody>

      </table>
    </div>
  @endif

@endif

<script>
/**
 * Esta parcial se inserta vía AJAX dentro del modal.
 * Necesitamos funciones globales (window.*) para que existan después del reemplazo del HTML.
 */

window.reloadDocsModalBody = async function () {
  try {
    const docsModal = document.getElementById('docsModal');
    const bodyEl = document.getElementById('docsModalBody');
    if (!docsModal || !bodyEl) return;

    // ✅ Preferimos URL desde data-documents-url del modal (recomendado)
    let url = docsModal.dataset.documentsUrl || null;

    // Fallback: URL renderizada desde el server
    if (!url) url = @json($documentsIndexUrl);

    if (!url) {
      console.warn('No hay documentsIndexUrl para recargar el modal.');
      return;
    }

    const res = await fetch(url, {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin'
    });

    const html = await res.text();
    bodyEl.innerHTML = html;
  } catch (e) {
    console.error(e);
  }
};

window.approveDocument = async function (btn) {
  const form = btn.closest('form');
  const docName = form?.dataset?.documentName || 'documento';
  const url = form?.dataset?.reviewUrl;

  if (!url) return alert('No se encontró la ruta de revisión (reviewUrl).');
  if (!confirm(`¿Aprobar el documento "${docName}"?`)) return;

  const csrf = form.querySelector('[name="_token"]')?.value || '';
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: 'action=approve'
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status} ${res.statusText} :: ${text.slice(0,300)}`);
    }

    await window.reloadDocsModalBody();
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check2"></i>';
  }
};

window.rejectDocument = async function (btn) {
  const form = btn.closest('form');
  const docName = form?.dataset?.documentName || 'documento';
  const url = form?.dataset?.reviewUrl;

  if (!url) return alert('No se encontró la ruta de revisión (reviewUrl).');

  const reason = prompt(`¿Por qué rechazas "${docName}"?`);
  if (!reason) return;

  const csrf = form.querySelector('[name="_token"]')?.value || '';
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: `action=reject&reason=${encodeURIComponent(reason)}`
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status} ${res.statusText} :: ${text.slice(0,300)}`);
    }

    await window.reloadDocsModalBody();
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-x"></i>';
  }
};
</script>