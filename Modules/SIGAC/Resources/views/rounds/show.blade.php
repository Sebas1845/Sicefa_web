@extends('sigac::layouts.master')

@section('content')
<div class="container-fluid py-3">

    {{-- Encabezado --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
        <div>
            <h4 class="mb-1">
                Ronda {{ $round->shift }}
                — {{ \Carbon\Carbon::parse($round->date)->format('d/m/Y') }}
            </h4>
            <small class="text-muted">
                Entradas: {{ $entries->count() }}
            </small>
        </div>

        <div class="d-flex gap-2">
            @if(!$round->is_locked)
                <form method="POST" action="{{ route('sigac.coordinador.environment_rounds.lock', $round->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fa-solid fa-lock"></i> Cerrar ronda
                    </button>
                </form>
            @else
                <span class="badge bg-danger align-self-center">Ronda cerrada</span>
            @endif
        </div>
    </div>

    {{-- Panel de estadísticas --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                <small class="text-muted d-block">Fichas programadas</small>
                <div class="fw-bold fs-4">{{ $distinctCourses ?? 0 }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                <small class="text-muted d-block">Ambientes en ronda</small>
                <div class="fw-bold fs-4">{{ $totalEntries ?? $entries->count() }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                <small class="text-muted d-block">Ambientes con novedades</small>
                <div class="fw-bold fs-4 text-danger">{{ $entriesWithIssuesCount ?? 0 }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 rounded-3 text-center p-2 bg-light">
                <small class="text-muted d-block">Llaves en uso</small>
                <div class="fw-bold fs-4">
                    {{ $keysNotReturned ?? 0 }} / {{ $keysGivenToday ?? 0 }}
                </div>
            </div>
        </div>

    </div>

    {{-- Botón filtro de novedades --}}
    <div class="d-flex justify-content-end mb-2">
        <button id="toggle-damages" class="btn btn-sm btn-outline-danger">
            <i class="fa-solid fa-filter"></i>
            Mostrar solo ambientes con novedades
        </button>
    </div>

    {{-- Tarjetas por ambiente / ficha --}}
    <div class="row g-3">
        @foreach ($entries as $entry)
            @php
                $schedule   = $entry->schedule;
                $course     = optional($schedule)->course;
                $fichaCode  = $course->code ?? 'Sin ficha';
                $env        = optional(optional($schedule)->environmentInstructorPrograms->first())->environment ?? null;

                // si tienes relación directa schedule->environment, puedes usar: $env = $schedule->environment;
                $ambiente   = $env->name ?? 'POR DEFINIR';

                // instructor: people + instructors
                $instructorPerson = optional(optional($schedule)->people->first());
                $instructorName   = trim(($instructorPerson->first_name ?? '') . ' ' . ($instructorPerson->first_last_name ?? ''));

                $horaInicio = optional($schedule)->start_time;
                $horaFin    = optional($schedule)->end_time;

                $hasIssues = $entry->is_dirty
                    || $entry->ac_status === 'DANADO'
                    || (is_string($entry->other_issues) && trim($entry->other_issues) !== '');
            @endphp

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 rounded-3 environment-card"
                     data-has-damages="{{ $hasIssues ? '1' : '0' }}">

                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <div>
                            <div class="fw-semibold">
                                Ambiente: {{ $ambiente }}
                            </div>
                            <small class="text-muted">
                                Ficha: {{ $fichaCode }}
                            </small>
                        </div>
                        <span class="badge bg-light text-dark">
                            {{ $horaInicio }} - {{ $horaFin }}
                        </span>
                    </div>

                    <div class="card-body pt-2 pb-3">

                        <div class="mb-2">
                            <small class="text-muted d-block">Instructor</small>
                            <span>{{ $instructorName ?: 'Sin instructor' }}</span>
                        </div>

                        {{-- Formulario auto-guardado --}}
                        <form class="auto-save-form mt-2"
                              data-entry-id="{{ $entry->id }}"
                              action="{{ route('sigac.coordinador.environment_round_entries.update', $entry->id) }}"
                              method="POST">
                            @csrf
                            @method('PUT')

                            {{-- Asistencia --}}
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">Asistencia</small>
                                <div class="btn-group w-100" role="group">
                                    @php $att = $entry->attendance_status; @endphp

                                    <button type="button"
                                        class="btn btn-sm attendance-btn {{ $att === 'OK' ? 'btn-success' : 'btn-outline-success' }}"
                                        data-value="OK">
                                        OK
                                    </button>

                                    <button type="button"
                                        class="btn btn-sm attendance-btn {{ $att === 'SIN_INSTRUCTOR' ? 'btn-warning' : 'btn-outline-warning' }}"
                                        data-value="SIN_INSTRUCTOR">
                                        Sin inst.
                                    </button>

                                    <button type="button"
                                        class="btn btn-sm attendance-btn {{ $att === 'SOLO_APRENDICES' ? 'btn-info' : 'btn-outline-info' }}"
                                        data-value="SOLO_APRENDICES">
                                        Solo ap.
                                    </button>

                                    <button type="button"
                                        class="btn btn-sm attendance-btn {{ $att === 'VACIO' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                                        data-value="VACIO">
                                        Vacío
                                    </button>
                                </div>
                                <input type="hidden" name="attendance_status" value="{{ $entry->attendance_status }}">
                            </div>

                            {{-- Presencia --}}
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">¿Están en el ambiente?</small>
                                <div class="btn-group w-100" role="group">
                                    @php $present = $entry->present_in_environment; @endphp

                                    <button type="button"
                                        class="btn btn-sm present-btn {{ $present === 'SI' ? 'btn-primary' : 'btn-outline-primary' }}"
                                        data-value="SI">
                                        Sí
                                    </button>
                                    <button type="button"
                                        class="btn btn-sm present-btn {{ $present === 'NO' ? 'btn-danger' : 'btn-outline-danger' }}"
                                        data-value="NO">
                                        No
                                    </button>
                                </div>
                                <input type="hidden" name="present_in_environment" value="{{ $entry->present_in_environment }}">
                            </div>

                            {{-- Novedades físicas --}}
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">Novedades de ambiente</small>

                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="dirty_{{ $entry->id }}" name="is_dirty"
                                        {{ $entry->is_dirty ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dirty_{{ $entry->id }}">
                                        Ambiente sucio
                                    </label>
                                </div>

                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <small class="text-muted">Aire:</small>
                                    <select name="ac_status" class="form-select form-select-sm">
                                        <option value="NO_APLICA" {{ $entry->ac_status === 'NO_APLICA' ? 'selected' : '' }}>No aplica</option>
                                        <option value="OK"        {{ $entry->ac_status === 'OK' ? 'selected' : '' }}>OK</option>
                                        <option value="DANADO"    {{ $entry->ac_status === 'DANADO' ? 'selected' : '' }}>Dañado</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Otras novedades --}}
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">Observaciones</small>
                                <textarea name="other_issues" rows="2" class="form-control"
                                    placeholder="Ej: Silla rota, equipo dañado, puerta sin seguro...">{{ $entry->other_issues }}</textarea>
                            </div>

                            {{-- Ambiente sin novedades --}}
                            <div class="mt-2 mb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input mark-ok-switch"
                                        type="checkbox"
                                        id="ok_{{ $entry->id }}"
                                        {{ !$hasIssues ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ok_{{ $entry->id }}">
                                        Ambiente sin novedades
                                    </label>
                                </div>
                            </div>

                            {{-- Estado de guardado --}}
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted save-status-text">
                                    @if($entry->updated_at)
                                        Último guardado: {{ \Carbon\Carbon::parse($entry->updated_at)->format('H:i') }}
                                    @else
                                        Sin guardar todavía
                                    @endif
                                </small>
                                <div class="spinner-border spinner-border-sm text-primary d-none save-spinner" role="status">
                                    <span class="visually-hidden">Guardando...</span>
                                </div>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- JS: auto-guardado + filtro de novedades + "sin novedades" --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const cards  = document.querySelectorAll('.environment-card');
    const toggleDamagesBtn = document.getElementById('toggle-damages');
    let showOnlyDamages = false;

    // Filtro: mostrar solo ambientes con novedades
    if (toggleDamagesBtn) {
        toggleDamagesBtn.addEventListener('click', function () {
            showOnlyDamages = !showOnlyDamages;

            cards.forEach(card => {
                const hasDamages = card.dataset.hasDamages === '1';
                if (showOnlyDamages && !hasDamages) {
                    card.classList.add('d-none');
                } else {
                    card.classList.remove('d-none');
                }
            });

            this.classList.toggle('btn-outline-danger', !showOnlyDamages);
            this.classList.toggle('btn-danger', showOnlyDamages);
            this.innerHTML = showOnlyDamages
                ? '<i class="fa-solid fa-filter-circle-xmark"></i> Ver todos los ambientes'
                : '<i class="fa-solid fa-filter"></i> Mostrar solo ambientes con novedades';
        });
    }

    // Auto-guardado
    const forms = document.querySelectorAll('.auto-save-form');

    forms.forEach(form => {
        const saveText    = form.querySelector('.save-status-text');
        const saveSpinner = form.querySelector('.save-spinner');

        const card = form.closest('.environment-card');

        // Switch "ambiente sin novedades"
        const okSwitch    = form.querySelector('.mark-ok-switch');
        const dirtyInput  = form.querySelector('input[name="is_dirty"]');
        const acSelect    = form.querySelector('select[name="ac_status"]');
        const obsTextarea = form.querySelector('textarea[name="other_issues"]');

        function updateHasDamagesFlag() {
            let hasDamages = false;
            if (dirtyInput && dirtyInput.checked) hasDamages = true;
            if (acSelect && acSelect.value === 'DANADO') hasDamages = true;
            if (obsTextarea && obsTextarea.value.trim() !== '') hasDamages = true;
            card.dataset.hasDamages = hasDamages ? '1' : '0';
        }

        function setNoIssuesState(enabled) {
            if (enabled) {
                if (dirtyInput) {
                    dirtyInput.checked = false;
                    dirtyInput.setAttribute('disabled', 'disabled');
                }
                if (acSelect) {
                    acSelect.value = 'NO_APLICA';
                    acSelect.setAttribute('disabled', 'disabled');
                }
                if (obsTextarea) {
                    obsTextarea.value = '';
                    obsTextarea.setAttribute('disabled', 'disabled');
                }
                card.dataset.hasDamages = '0';
            } else {
                if (dirtyInput) dirtyInput.removeAttribute('disabled');
                if (acSelect) acSelect.removeAttribute('disabled');
                if (obsTextarea) obsTextarea.removeAttribute('disabled');
                updateHasDamagesFlag();
            }
        }

        if (okSwitch) {
            // Estado inicial
            setNoIssuesState(okSwitch.checked);

            okSwitch.addEventListener('change', function () {
                setNoIssuesState(this.checked);
                autoSave(form, saveText, saveSpinner);
            });
        }

        // Botones de asistencia
        form.querySelectorAll('.attendance-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const value = this.dataset.value;
                const hidden = form.querySelector('input[name="attendance_status"]');
                if (hidden) hidden.value = value;

                // actualizar estilos
                form.querySelectorAll('.attendance-btn').forEach(b => {
                    const v = b.dataset.value;
                    b.className = 'btn btn-sm attendance-btn';
                    if (v === 'OK') {
                        b.classList.add(value === 'OK' ? 'btn-success' : 'btn-outline-success');
                    } else if (v === 'SIN_INSTRUCTOR') {
                        b.classList.add(value === 'SIN_INSTRUCTOR' ? 'btn-warning' : 'btn-outline-warning');
                    } else if (v === 'SOLO_APRENDICES') {
                        b.classList.add(value === 'SOLO_APRENDICES' ? 'btn-info' : 'btn-outline-info');
                    } else if (v === 'VACIO') {
                        b.classList.add(value === 'VACIO' ? 'btn-secondary' : 'btn-outline-secondary');
                    }
                });

                autoSave(form, saveText, saveSpinner);
            });
        });

        // Botones de presencia
        form.querySelectorAll('.present-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const value = this.dataset.value;
                const hidden = form.querySelector('input[name="present_in_environment"]');
                if (hidden) hidden.value = value;

                form.querySelectorAll('.present-btn').forEach(b => {
                    const v = b.dataset.value;
                    b.className = 'btn btn-sm present-btn';
                    if (v === 'SI') {
                        b.classList.add(value === 'SI' ? 'btn-primary' : 'btn-outline-primary');
                    } else {
                        b.classList.add(value === 'NO' ? 'btn-danger' : 'btn-outline-danger');
                    }
                });

                autoSave(form, saveText, saveSpinner);
            });
        });

        // Otros campos: checkbox, select, textarea (con pequeño delay para textarea)
        let typingTimer;
        form.querySelectorAll('input[type="checkbox"], select, textarea').forEach(input => {
            if (input.tagName === 'TEXTAREA') {
                input.addEventListener('input', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {
                        updateHasDamagesFlag();
                        autoSave(form, saveText, saveSpinner);
                    }, 500);
                });
            } else {
                input.addEventListener('change', function () {
                    updateHasDamagesFlag();
                    autoSave(form, saveText, saveSpinner);
                });
            }
        });
    });

    function autoSave(form, saveText, saveSpinner) {
        const url = form.getAttribute('action');
        const formData = new FormData(form);
        formData.append('_method', 'PUT');

        saveSpinner.classList.remove('d-none');
        if (saveText) saveText.textContent = 'Guardando...';

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData
        })
        .then(response => {
            saveSpinner.classList.add('d-none');
            if (!response.ok) {
                if (saveText) saveText.textContent = 'Error al guardar (revisa conexión)';
                return;
            }
            if (saveText) {
                const now = new Date();
                const hh = String(now.getHours()).padStart(2,'0');
                const mm = String(now.getMinutes()).padStart(2,'0');
                saveText.textContent = 'Guardado: ' + hh + ':' + mm;
            }
        })
        .catch(() => {
            saveSpinner.classList.add('d-none');
            if (saveText) {
                saveText.textContent = 'Error al guardar (revisa conexión)';
            }
        });
    }

});
</script>
@endsection
