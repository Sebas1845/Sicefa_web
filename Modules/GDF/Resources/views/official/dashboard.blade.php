{{-- Modules/GDF/Resources/views/official/dashboard.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Instructor / Funcionario')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $areaKey   = $areaKey ?? ($ctx['area'] ?? 'academic');
    $areaLabel = $areaKey === 'campesena' ? 'Campesena' : 'Académica';

    $canEnter  = $gate['can_enter']  ?? true;
    $canCreate = $gate['can_create'] ?? true;
    $gateMsg   = $gate['message']    ?? null;

    $sitravEnabled = $sitravEnabled ?? true;

    $routeExists = fn($name) => Route::has($name);

    $rIndex = $routeExists('gdf.instructor.requests.index') ? route('gdf.instructor.requests.index') : '#';
    $rCreateGdf = $routeExists('gdf.instructor.requests.create') ? route('gdf.instructor.requests.create') : '#';

    $rSitravPrograms = $routeExists('gdf.instructor.sitrav.programs.index')
        ? route('gdf.instructor.sitrav.programs.index')
        : '#';

    $isDraft = fn($tr) => (string)($tr->status ?? '') === 'draft';

    // ✅ SITRAV: "solo lectura" (enviada o más)
    $isSitravReadOnly = function($tr) {
        $st = (string)($tr->status ?? '');
        return in_array($st, ['submitted','approved','confirmed','liquidated'], true);
    };

    // ✅ Acción por fila (GDF y SITRAV)
    $actionFor = function($tr) use ($routeExists, $isDraft, $isSitravReadOnly) {
        $id = (int)($tr->id ?? 0);
        $module = (string)($tr->module ?? 'gdf');

        if ($module === 'sitrav') {
            if ($isSitravReadOnly($tr)) {
                if ($routeExists('gdf.instructor.sitrav.request.show')) {
                    return [
                        'href'  => route('gdf.instructor.sitrav.request.show', $id),
                        'text'  => 'Ver',
                        'class' => 'btn btn-sm btn-gdf-ghost',
                        'icon'  => 'bi bi-chevron-right',
                    ];
                }
                return ['href'=>'#','text'=>'N/D','class'=>'btn btn-sm btn-secondary disabled','icon'=>'bi bi-slash-circle'];
            }

            if ($routeExists('gdf.instructor.sitrav.requests.edit')) {
                return [
                    'href'  => route('gdf.instructor.sitrav.requests.edit', ['travelRequestId' => $id]),
                    'text'  => 'Continuar',
                    'class' => 'btn btn-sm btn-warning text-dark',
                    'icon'  => 'bi bi-pencil',
                ];
            }

            return ['href'=>'#','text'=>'N/D','class'=>'btn btn-sm btn-secondary disabled','icon'=>'bi bi-slash-circle'];
        }

        // GDF
        if ($isDraft($tr)) {
            if ($routeExists('gdf.instructor.requests.edit')) {
                return [
                    'href'  => route('gdf.instructor.requests.edit', ['gdfRequest' => $id]),
                    'text'  => 'Continuar',
                    'class' => 'btn btn-sm btn-warning text-dark',
                    'icon'  => 'bi bi-pencil',
                ];
            }
        } else {
            if ($routeExists('gdf.instructor.requests.show')) {
                return [
                    'href'  => route('gdf.instructor.requests.show', ['gdfRequest' => $id]),
                    'text'  => 'Ver',
                    'class' => 'btn btn-sm btn-gdf-ghost',
                    'icon'  => 'bi bi-chevron-right',
                ];
            }
        }

        return ['href'=>'#','text'=>'N/D','class'=>'btn btn-sm btn-secondary disabled','icon'=>'bi bi-slash-circle'];
    };

    $recentCol = $recent ?? collect();
    $returned = $recentCol->filter(fn($x) => (string)($x->status ?? '') === 'returned')->values();
    $normal   = $recentCol->reject(fn($x) => (string)($x->status ?? '') === 'returned')->values();

    $hasMoto = (bool)($hasMoto ?? false);
    $activeMoto = $activeMoto ?? null;

    $motoId = $activeMoto->motorcycle_id ?? null;

    $linkedRequestId =
        $activeMoto->travel_request_id
        ?? $activeMoto->request_id
        ?? $activeMoto->gdf_request_id
        ?? $activeMoto->sitrav_request_id
        ?? null;

    $assignType = $linkedRequestId ? 'Temporal (ligada a solicitud)' : 'Directa (asignación por cupo)';
@endphp

<div class="container py-4">

    @foreach (['success','error','warning','info'] as $k)
        @if(session($k))
            <div class="alert alert-{{ $k==='error' ? 'danger' : $k }}">
                {{ session($k) }}
            </div>
        @endif
    @endforeach

    {{-- ✅ ALERT MOTO + BOTÓN VISIBLE --}}
    @if ($hasMoto)
        <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Moto asignada</div>
                <div class="small">
                    Tienes una moto activa asignada
                    @if($motoId)
                        (ID moto: <b>{{ $motoId }}</b>)
                    @endif
                    · <span class="badge bg-light text-dark">{{ $assignType }}</span>
                </div>
                <div class="small mt-1">
                    Regla: dentro de <b>Huila</b> el sistema fuerza transporte en <b>Moto</b>.
                    Fuera de Huila, <b>Moto</b> no aplica.
                </div>
            </div>

            <button type="button"
                    class="btn btn-sm btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#motoModal">
                <i class="bi bi-bicycle"></i> Ver moto
            </button>
        </div>
    @endif

    @if (!$canCreate && $gateMsg)
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Creación de solicitudes restringida</div>
            <div>{{ $gateMsg }}</div>
        </div>
    @endif

    <div class="gdf-card p-4 mb-4" style="background:rgba(255,255,255,.03);">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-white-50 small mb-1">Instructor / Funcionario</div>
                <h3 class="fw-bold mb-1">Panel de solicitudes</h3>
                <div class="text-white-50">
                    Área activa: <span class="fw-semibold text-white">{{ $areaLabel }}</span>
                </div>
            </div>

            <div class="d-flex gap-2 align-items-center" style="position:relative; z-index:5;">
                <a class="btn btn-gdf-ghost" href="{{ $rIndex }}">
                    <i class="bi bi-list-ul"></i> Mis solicitudes
                </a>

                <div class="dropdown">
                    <button class="btn btn-gdf-primary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false"
                        @if(!$canEnter) disabled @endif>
                        <i class="bi bi-plus-circle"></i> Nueva solicitud
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">
                        @if ($canCreate && $rCreateGdf !== '#')
                            <a class="dropdown-item" href="{{ $rCreateGdf }}">
                                <i class="bi bi-send me-2"></i> GDF - Desplazamiento / Viáticos
                            </a>
                        @else
                            <span class="dropdown-item disabled" title="{{ $gateMsg ?? 'No disponible' }}">
                                <i class="bi bi-send me-2"></i> GDF - Desplazamiento / Viáticos
                            </span>
                        @endif

                        <div class="dropdown-divider"></div>

                        @if ($sitravEnabled && $rSitravPrograms !== '#')
                            <a class="dropdown-item" href="{{ $rSitravPrograms }}">
                                <i class="bi bi-calendar-check me-2"></i> SITRAV - Desde programación (SIGAC)
                            </a>
                        @else
                            <span class="dropdown-item disabled">
                                <i class="bi bi-calendar-check me-2"></i> SITRAV - Programación (SIGAC)
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                <div class="text-white-50 small">Borrador</div>
                <div class="fs-3 fw-bold">{{ $stats['draft'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                <div class="text-white-50 small">En proceso</div>
                <div class="fs-3 fw-bold">{{ $stats['in_process'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                <div class="text-white-50 small">Aprobadas</div>
                <div class="fs-3 fw-bold">{{ $stats['approved'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                <div class="text-white-50 small">Rechazadas / Devueltas</div>
                <div class="fs-3 fw-bold">{{ ($stats['rejected'] ?? 0) + ($stats['returned'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    {{-- DEVUELTAS --}}
    <div class="gdf-card p-4 mb-4" style="background:rgba(255,255,255,.03);">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">
                Solicitudes devueltas
                @if($returned->count() > 0)
                    <span class="badge bg-warning text-dark ms-2">{{ $returned->count() }}</span>
                @endif
            </div>
            <a class="text-decoration-none text-white-50" href="{{ $rIndex }}?q=&tab=all">
                Ver todas <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        @if ($returned->isEmpty())
            <div class="text-white-50">No tienes solicitudes devueltas en este momento.</div>
        @else
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Módulo</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Documento</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returned as $r)
                            @php
                                $module = (string)($r->module ?? 'gdf');
                                $moduleBadge = $module === 'sitrav' ? 'info' : 'primary';

                                $stLabel = $r->status_label ?? strtoupper((string)($r->status ?? 'N/A'));
                                $stBadge = $r->status_badge ?? 'warning';

                                $a = $actionFor($r);

                                $hasDoc   = !empty($r->doc_url);
                                $docLabel = $r->doc_label ?? ($module === 'sitrav' ? 'DOCS SIGAC' : 'AUTORIZACIÓN');
                                $docBadge = $r->doc_badge ?? ($module === 'sitrav' ? 'info' : 'success');
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $r->id }}</td>
                                <td><span class="badge bg-{{ $moduleBadge }}">{{ strtoupper($module) }}</span></td>
                                <td>{{ $r->origin }}</td>
                                <td>{{ $r->destination }}</td>
                                <td><span class="badge bg-{{ $stBadge }}">{{ $stLabel }}</span></td>

                                <td>
                                    @if($hasDoc)
                                        <a class="badge bg-{{ $docBadge }} text-decoration-none" href="{{ $r->doc_url }}" target="_blank">
                                            <i class="bi bi-file-earmark"></i> {{ $docLabel }}
                                        </a>
                                    @else
                                        <span class="badge bg-secondary">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <a class="{{ $a['class'] }}" href="{{ $a['href'] }}">
                                        {{ $a['text'] }} <i class="{{ $a['icon'] }}"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ÚLTIMAS --}}
    <div class="gdf-card p-4" style="background:rgba(255,255,255,.03);">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">Últimas solicitudes</div>
            <a class="text-decoration-none text-white-50" href="{{ $rIndex }}">
                Ver todas <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        @if ($normal->isEmpty())
            <div class="text-white-50">Aún no tienes solicitudes recientes (sin contar devueltas).</div>
        @else
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Módulo</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Documento</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($normal as $r)
                            @php
                                $module = (string)($r->module ?? 'gdf');
                                $moduleBadge = $module === 'sitrav' ? 'info' : 'primary';

                                $stLabel = $r->status_label ?? strtoupper((string)($r->status ?? 'N/A'));
                                $stBadge = $r->status_badge ?? 'secondary';

                                $a = $actionFor($r);

                                $hasDoc   = !empty($r->doc_url);
                                $docLabel = $r->doc_label ?? ($module === 'sitrav' ? 'DOCS SIGAC' : 'AUTORIZACIÓN');
                                $docBadge = $r->doc_badge ?? ($module === 'sitrav' ? 'info' : 'success');
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $r->id }}</td>
                                <td><span class="badge bg-{{ $moduleBadge }}">{{ strtoupper($module) }}</span></td>
                                <td>{{ $r->origin }}</td>
                                <td>{{ $r->destination }}</td>
                                <td><span class="badge bg-{{ $stBadge }}">{{ $stLabel }}</span></td>

                                <td>
                                    @if($hasDoc)
                                        <a class="badge bg-{{ $docBadge }} text-decoration-none" href="{{ $r->doc_url }}" target="_blank">
                                            <i class="bi bi-file-earmark"></i> {{ $docLabel }}
                                        </a>
                                    @else
                                        <span class="badge bg-secondary">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <a class="{{ $a['class'] }}" href="{{ $a['href'] }}">
                                        {{ $a['text'] }} <i class="{{ $a['icon'] }}"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ✅ MODAL MOTO --}}
    @if ($hasMoto)
    <div class="modal fade" id="motoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detalle de moto asignada</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>

          <div class="modal-body">
            <div class="mb-2">
              <div class="text-muted small">Tipo de asignación</div>
              <div class="fw-semibold">{{ $assignType }}</div>
              @if($linkedRequestId)
                  <div class="small text-muted">
                      Ligada a solicitud ID: <b>{{ $linkedRequestId }}</b>
                  </div>
              @endif
            </div>

            <hr>

            <div class="row g-2">
              <div class="col-6">
                <div class="text-muted small">ID Moto</div>
                <div class="fw-semibold">{{ $motoId ?? '—' }}</div>
              </div>

              <div class="col-6">
                <div class="text-muted small">Placa</div>
                <div class="fw-semibold">{{ $activeMoto->plate ?? '—' }}</div>
              </div>

              <div class="col-6">
                <div class="text-muted small">Modelo</div>
                <div class="fw-semibold">{{ $activeMoto->model ?? '—' }}</div>
              </div>

              <div class="col-6">
                <div class="text-muted small">Kilometraje</div>
                <div class="fw-semibold">{{ $activeMoto->odometer ?? '—' }}</div>
              </div>
            </div>

            <hr>

            <div class="small text-muted">
              Regla: dentro de <b>Huila</b> el sistema fuerza transporte en <b>Moto</b>. Fuera de Huila, <b>Moto</b> no aplica.
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    @endif

</div>
@endsection
