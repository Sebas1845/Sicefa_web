@extends('sigac::layouts.master')

@section('content')
    <div class="container-fluid py-3">

        {{-- Encabezado y filtro de fecha --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
            <h4 class="mb-0">
                <i class="fa-solid fa-key"></i> Control de Llaves
            </h4>

            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Fecha:</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Ver
                </button>
            </form>
        </div>

        {{-- Panel de estadísticas del día --}}

        <div class="row g-3 mb-3">

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Fichas del día (programadas)</small>
                    <div class="fw-bold fs-4">{{ $scheduledCoursesCount }}</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Ambientes del día (programados)</small>
                    <div class="fw-bold fs-4">{{ $scheduledEnvironmentsCount }}</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Ambientes revisados en rondas</small>
                    <div class="fw-bold fs-4">{{ $totalEntries }}</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Ambientes con novedades</small>
                    <div class="fw-bold fs-4 text-danger">{{ $entriesWithIssuesCount }}</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Llaves en uso</small>
                    <div class="fw-bold fs-4">
                        {{ $keysNotReturned }} / {{ $keysGivenToday }}
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Asistencia OK (en rondas)</small>
                    <div class="fw-bold fs-4 text-success">{{ $attendanceOk }}</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                    <small class="text-muted d-block">Con novedades de asistencia</small>
                    <div class="fw-bold fs-4 text-warning">{{ $attendanceIssues }}</div>
                </div>
            </div>

        </div>


        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Programación y llaves del día</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Ficha</th>
                                <th>Programa</th>
                                <th>Instructor</th>
                                <th>Horario</th>
                                <th>Ambiente</th>
                                <th>Llave</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $row)
                                @php
                                    $keyState = $keyStates[$row->schedule_id] ?? null;
                                    $hasKey = $keyState && $keyState['delivered_at'] && !$keyState['returned_at'];

                                    $instructorName = trim(
                                        ($row->first_name ?? '') . ' ' . ($row->first_last_name ?? ''),
                                    );
                                @endphp
                                <tr>
                                    <td>{{ $row->ficha }}</td>
                                    <td>{{ $row->programa }}</td>
                                    <td>{{ $instructorName ?: 'Sin instructor' }}</td>
                                    <td>{{ $row->start_time }} - {{ $row->end_time }}</td>
                                    <td>
                                        {{ $row->ambiente ?? 'POR DEFINIR' }}
                                        @if (!$row->ambiente)
                                            <span class="badge bg-warning text-dark ms-1">Sin ambiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($hasKey)
                                            <span class="badge bg-success">Entregada</span>
                                        @else
                                            <span class="badge bg-secondary">Sin entregar</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        {{-- Cambiar ambiente --}}
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-change-env"
                                            data-schedule="{{ $row->schedule_id }}"
                                            data-environment="{{ $row->environment_id ?? '' }}">
                                            <i class="fa-solid fa-right-left"></i> Cambiar ambiente
                                        </button>

                                        {{-- Entregar / devolver llave --}}
                                        @if (!$hasKey)
                                            <button type="button" class="btn btn-sm btn-outline-success btn-deliver-key"
                                                data-schedule="{{ $row->schedule_id }}"
                                                data-environment="{{ $row->environment_id ?? '' }}">
                                                <i class="fa-solid fa-key"></i> Entregar
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-return-key"
                                                data-schedule="{{ $row->schedule_id }}">
                                                <i class="fa-solid fa-key"></i> Devolver
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-3">
                                        No hay programación registrada para esta fecha.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- MODAL CAMBIAR AMBIENTE --}}
            <div class="modal fade" id="changeEnvironmentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Cambiar ambiente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" id="change_env_schedule_id">

                            <div class="mb-3">
                                <label for="change_env_select" class="form-label">
                                    Seleccione un ambiente
                                </label>
                                <select id="change_env_select" class="form-select">
                                    <option value="">-- Dejar POR DEFINIR --</option>
                                    @foreach ($environments as $env)
                                        <option value="{{ $env->id }}">{{ $env->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Si eliges "POR DEFINIR" se eliminará la asignación de ambiente.
                                </small>
                            </div>

                            <div class="alert alert-danger d-none" id="change_env_error"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="saveChangeEnvironment">
                                Guardar cambios
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {

    // ==========================
    //  MODAL CAMBIAR AMBIENTE
    // ==========================

    const changeEnvModalEl = document.getElementById('changeEnvironmentModal');
    const changeEnvModal   = new bootstrap.Modal(changeEnvModalEl);

    const scheduleInput = document.getElementById('change_env_schedule_id');
    const envSelect     = document.getElementById('change_env_select');
    const errorBox      = document.getElementById('change_env_error');

    // Abrir modal
    document.querySelectorAll('.btn-change-env').forEach(btn => {
        btn.addEventListener('click', function () {
            const scheduleId = this.dataset.schedule;
            const currentEnv = this.dataset.environment || '';

            scheduleInput.value = scheduleId;
            envSelect.value = currentEnv;
            errorBox.classList.add('d-none');
            errorBox.textContent = '';

            changeEnvModal.show();
        });
    });

    // Guardar cambio de ambiente
    document.getElementById('saveChangeEnvironment').addEventListener('click', function () {
        const scheduleId = scheduleInput.value;
        const envId      = envSelect.value;

        fetch("{{ route('sigac.coordinador.environment_keys.change_environment') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                schedule_id: scheduleId,
                environment_id: envId || null,
            })
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.ok) {
                errorBox.classList.remove('d-none');
                errorBox.textContent = data.message || 'Error al cambiar el ambiente.';
                return;
            }

            changeEnvModal.hide();
            location.reload();
        })
        .catch(() => {
            errorBox.classList.remove('d-none');
            errorBox.textContent = 'Error de conexión al cambiar el ambiente.';
        });
    });

    // ==========================
    //  ENTREGAR LLAVE (doble validación)
    // ==========================

    document.querySelectorAll('.btn-deliver-key').forEach(btn => {
        btn.addEventListener('click', function () {
            const scheduleId = this.dataset.schedule;
            const envId      = this.dataset.environment;

            // 1) Validación frontend: debe tener ambiente
            if (!envId) {
                alert('Primero debe asignar un ambiente antes de entregar la llave.');
                return;
            }

            // 2) Confirmación del usuario
            if (!confirm('¿Confirmas que vas a entregar la llave de este ambiente al instructor?')) {
                return;
            }

            fetch("{{ route('sigac.coordinador.environment_keys.deliver') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    schedule_id: scheduleId
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    alert(data.message || 'No se pudo registrar la entrega de la llave.');
                    return;
                }

                location.reload();
            })
            .catch(() => {
                alert('Error de conexión al entregar la llave.');
            });
        });
    });

    // ==========================
    //  DEVOLVER LLAVE
    // ==========================

    document.querySelectorAll('.btn-return-key').forEach(btn => {
        btn.addEventListener('click', function () {
            const scheduleId = this.dataset.schedule;

            if (!confirm('¿Confirmas que la llave fue devuelta?')) {
                return;
            }

            fetch("{{ route('sigac.coordinador.environment_keys.return') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    schedule_id: scheduleId
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    alert(data.message || 'No se pudo registrar la devolución de la llave.');
                    return;
                }

                location.reload();
            })
            .catch(() => {
                alert('Error de conexión al devolver la llave.');
            });
        });
    });

});
</script>
    

@endsection
