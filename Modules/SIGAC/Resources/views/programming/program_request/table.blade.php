@extends('sigac::layouts.master')

@section('title', $titlePage ?? 'Solicitudes')

@section('content')
@php
    $areaLabel = fn($areaId) => (int)$areaId === 1 ? 'CAMPESENA' : ((int)$areaId === 2 ? 'COORD. ACADÉMICA' : '—');

    $isInstructor = function_exists('checkRol') ? checkRol('sigac.instructor') : false;
    $isCoordAcad  = function_exists('checkRol') ? (checkRol('sigac.academic_coordinator') || checkRol('superadmin')) : false;
    $isCampesena  = function_exists('checkRol') ? checkRol('sigac.campesena') : false;

    $roleRoute = $roleRoute ?? (function_exists('getRoleRouteName')
        ? getRoleRouteName(\Illuminate\Support\Facades\Route::currentRouteName())
        : 'instructor'
    );

    $canApprove = $isCoordAcad || $isCampesena;
@endphp

<div class="container-fluid py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">{{ $titleView ?? 'Solicitudes de Programación' }}</h3>
            <small class="text-muted">
                Instructor: ves tus solicitudes. Coordinación: ves pendientes del área.
            </small>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm"
               href="{{ route("sigac.$roleRoute.programming.program_request.index") }}">
                Todas (según rol)
            </a>
        </div>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
    @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>   @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">

                <table class="table table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:80px">#</th>
                            <th>Estado</th>
                            <th>Área</th>
                            <th>Programa</th>
                            <th>Instructor</th>
                            <th>Municipio / Vereda</th>
                            <th>Fechas</th>
                            <th>Docs</th>
                            <th style="width:360px">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($program_requests as $pr)
                            @php
                                $programName = $pr->program->name ?? '—';
                                $personName  = $pr->person->fullname ?? trim(($pr->person->first_name ?? '').' '.($pr->person->first_last_name ?? ''));
                                $munName     = $pr->municipality->name ?? '—';
                                $vilName     = $pr->village->name ?? null;

                                $docs  = $pr->documents ?? collect();
                                $dates = $pr->dates ?? collect();

                                $state = $pr->state ?? '—';

                                $needsDocs      = $docs->isEmpty();
                                $canUploadDocs  = $isInstructor && in_array($state, ['Pendiente','Devuelto'], true);
                                $canApproveThis = $canApprove && $state === 'Pendiente';

                                $badge = match($state) {
                                    'Pendiente'     => 'warning',
                                    'Preconfirmado' => 'info',
                                    'Confirmado'    => 'success',
                                    'Devuelto'      => 'secondary',
                                    'Desestimado'   => 'danger',
                                    default         => 'dark'
                                };
                            @endphp

                            <tr>
                                <td>#{{ $pr->id }}</td>

                                <td>
                                    <span class="badge bg-{{ $badge }}">{{ $state }}</span>
                                </td>

                                <td>
                                    <span class="badge bg-dark">{{ $areaLabel($pr->area_id) }}</span>
                                </td>

                                <td class="fw-semibold">{{ $programName }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $personName ?: '—' }}</div>
                                    @if(!empty($pr->email))
                                        <small class="text-muted">{{ $pr->email }}</small>
                                    @endif
                                </td>

                                <td>
                                    <div>{{ $munName }}</div>
                                    @if($vilName) <small class="text-muted">Vereda: {{ $vilName }}</small> @endif
                                </td>

                                {{-- Fechas: SOLO BOTÓN (no se renderiza nada en la tabla) --}}
                                <td>
                                    <button type="button"
                                            class="btn btn-outline-info btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#datesModal{{ $pr->id }}">
                                        Ver ({{ $dates->count() }})
                                    </button>
                                </td>

                                {{-- Docs --}}
                                <td>
                                    @if($docs->isEmpty())
                                        <span class="text-muted">Sin docs</span>
                                    @else
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                                    type="button" data-bs-toggle="dropdown">
                                                Ver ({{ $docs->count() }})
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="{{ route('sigac.support.programming.program_request.download', $pr->id) }}">
                                                        Descargar ZIP (todo)
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                @foreach($docs as $doc)
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="{{ route('sigac.support.programming.program_request.document.download', $doc->id) }}">
                                                            {{ $doc->name ?? ('Documento #'.$doc->id) }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if($canUploadDocs)
                                            <button class="btn btn-outline-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#uploadModal{{ $pr->id }}">
                                                Subir documentos
                                            </button>
                                        @endif

                                        @if($canApproveThis)
                                            <form method="POST"
                                                  action="{{ route("sigac.$roleRoute.programming.program_request.approve", $pr->id) }}"
                                                  onsubmit="return confirm('¿Aprobar y pasar a Preconfirmado?');">
                                                @csrf
                                                <button class="btn btn-success btn-sm" type="submit">Aprobar</button>
                                            </form>

                                            <button class="btn btn-outline-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#dismissModal{{ $pr->id }}">
                                                Desestimar
                                            </button>
                                        @endif

                                        @if(!empty($pr->observation))
                                            <button class="btn btn-outline-secondary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#obsModal{{ $pr->id }}">
                                                Ver observación
                                            </button>
                                        @endif

                                        @if($needsDocs && $state === 'Pendiente')
                                            <span class="badge bg-warning text-dark align-self-center">
                                                Falta documentación
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- MODAL FECHAS: include limpio --}}
                            @include('sigac::programming.program_request.modals.dates', ['pr' => $pr])

                            {{-- Modal subir docs --}}
                            @if($canUploadDocs)
                                <div class="modal fade" id="uploadModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Subir documentos · Solicitud #{{ $pr->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST"
                                                  action="{{ route("sigac.$roleRoute.programming.program_request.document_store", $pr->id) }}"
                                                  enctype="multipart/form-data">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="alert alert-info">
                                                        Sube aquí los documentos requeridos. Puedes seleccionar varios archivos.
                                                    </div>
                                                    <input type="file" name="documents[]" class="form-control" multiple required>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Modal desestimar --}}
                            @if($canApproveThis)
                                <div class="modal fade" id="dismissModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Desestimar · Solicitud #{{ $pr->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST"
                                                  action="{{ route("sigac.$roleRoute.programming.program_request.dismiss", $pr->id) }}">
                                                @csrf
                                                <div class="modal-body">
                                                    <label class="form-label">Motivo / Observación</label>
                                                    <textarea name="observation" class="form-control" rows="4" required></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-danger">Desestimar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Modal ver observación --}}
                            @if(!empty($pr->observation))
                                <div class="modal fade" id="obsModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Observación · Solicitud #{{ $pr->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-secondary mb-0">{{ $pr->observation }}</div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No hay solicitudes para mostrar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>

</div>
@endsection
