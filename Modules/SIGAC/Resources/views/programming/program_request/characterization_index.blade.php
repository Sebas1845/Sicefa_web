@extends('sigac::layouts.master')

@section('title', $titlePage ?? 'Caracterización')

@section('content')
@php
    $areaLabel = fn($areaId) => (int)$areaId === 1 ? 'CAMPESENA' : ((int)$areaId === 2 ? 'COORD. ACADÉMICA' : '—');

    $isSupport = function_exists('checkRol') ? (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support') || checkRol('superadmin')) : false;
    if(!$isSupport){ abort(403); }
@endphp

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">{{ $titleView ?? 'Bandeja de Caracterización' }}</h3>
            <small class="text-muted">Solo solicitudes en estado <b>Preconfirmado</b>.</small>
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
                            <th style="width:70px">#</th>
                            <th>Área</th>
                            <th>Programa</th>
                            <th>Solicitante</th>
                            <th>Municipio / Vereda</th>
                            <th>Fechas</th>
                            <th>Documentos</th>
                            <th style="width:420px">Caracterizar</th>
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
                            @endphp

                            <tr>
                                <td>#{{ $pr->id }}</td>

                                <td><span class="badge bg-dark">{{ $areaLabel($pr->area_id) }}</span></td>

                                <td>{{ $programName }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $personName ?: '—' }}</div>
                                    @if(!empty($pr->email)) <small class="text-muted">{{ $pr->email }}</small> @endif
                                </td>

                                <td>
                                    <div>{{ $munName }}</div>
                                    @if($vilName) <small class="text-muted">Vereda: {{ $vilName }}</small> @endif
                                </td>

                                <td>
                                    <button class="btn btn-outline-info btn-sm btn-load-dates"
                                            data-bs-toggle="modal"
                                            data-bs-target="#datesModal{{ $pr->id }}"
                                            data-prom-id="{{ $pr->id }}">
                                        Ver ({{ $dates->count() }})
                                    </button>
                                </td>

                                {{-- Documentos: dropdown --}}
                                <td>
                                    @if($docs->isEmpty())
                                        <span class="text-muted">Sin docs</span>
                                    @else
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                                    type="button"
                                                    data-bs-toggle="dropdown">
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

                                {{-- Form de caracterización + devolver/desestimar --}}
                                <td>
                                    <form method="POST"
                                          action="{{ route('sigac.support.programming.program_request.characterization.store', $pr->id) }}"
                                          class="row g-2">
                                        @csrf

                                        <div class="col-12 col-md-4">
                                            <input type="text" name="code_course" class="form-control form-control-sm"
                                                   placeholder="Código curso (ficha)" required>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <input type="text" name="code_empresa" class="form-control form-control-sm"
                                                   placeholder="Código empresa">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <input type="date" name="date_inscription" class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12 d-flex flex-wrap gap-2">
                                            <button class="btn btn-primary btn-sm" type="submit">
                                                Confirmar caracterización
                                            </button>

                                            <button class="btn btn-outline-warning btn-sm" type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#returnModal{{ $pr->id }}">
                                                Devolver
                                            </button>

                                            <button class="btn btn-outline-danger btn-sm" type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#dismissModal{{ $pr->id }}">
                                                Desestimar
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>

                            {{-- Modal Fechas (reusa el partial que ya tienes) --}}
                            @include('sigac::programming.program_request.dates', ['pr' => $pr])

                            {{-- Modal Devolver --}}
                            <div class="modal fade" id="returnModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Devolver · Solicitud #{{ $pr->id }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST"
                                              action="{{ route('sigac.support.programming.program_request.characterization.devolution', $pr->id) }}">
                                            @csrf
                                            <div class="modal-body">
                                                <label class="form-label">Motivo</label>
                                                <textarea name="observation" class="form-control" rows="4" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-warning">Devolver</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal Desestimar --}}
                            <div class="modal fade" id="dismissModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Desestimar · Solicitud #{{ $pr->id }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST"
                                              action="{{ route('sigac.support.programming.program_request.characterization.dismiss', $pr->id) }}">
                                            @csrf
                                            <div class="modal-body">
                                                <label class="form-label">Motivo</label>
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

                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay solicitudes preconfirmadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-load-dates');
    if (!btn) return;

    const id = btn.dataset.promId;
    const body = document.getElementById(`datesBody${id}`);
    if (!body) return;

    body.innerHTML = `<div class="alert alert-secondary mb-0">Cargando...</div>`;

    try {
        const url = `{{ route('sigac.programming.program_request.dates_json', ':id') }}`.replace(':id', id);
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();

        if (!data.ok || !data.dates || data.dates.length === 0) {
            body.innerHTML = `<div class="alert alert-warning mb-0">No hay fechas registradas.</div>`;
            return;
        }

        const rows = data.dates.map(d => `
            <tr>
                <td>${d.date ?? ''}</td>
                <td>${d.start_time ?? ''}</td>
                <td>${d.end_time ?? ''}</td>
            </tr>
        `).join('');

        body.innerHTML = `
            <table class="table table-sm table-bordered mb-0">
                <thead><tr><th>Fecha</th><th>Hora inicio</th><th>Hora fin</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger mb-0">Error cargando fechas.</div>`;
    }
});
</script>
@endsection
