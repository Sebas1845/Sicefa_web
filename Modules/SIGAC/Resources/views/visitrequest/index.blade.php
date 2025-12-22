@extends('sigac::layouts.master')

{{-- Si tu layout YA trae Bootstrap/Icons, puedes quitar estos CDN --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

@section('content')
    {{-- MENSAJES FLASH GLOBALES --}}
    @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
        @if (session($key))
            <div class="alert alert-{{ $type }} alert-dismissible fade show mt-2" role="alert">
                {{ session($key) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    @endforeach

    @php
        /** @var \Illuminate\Support\Collection|\Modules\SIGAC\Entities\VisitRequest[] $visitRequests */

        $unscheduled = $visitRequests->filter(fn($visit) => !$visit->schedules || $visit->schedules->isEmpty());
        $scheduled = $visitRequests->filter(fn($visit) => $visit->schedules && $visit->schedules->isNotEmpty());

        $tz = 'America/Bogota';
        $now = \Illuminate\Support\Carbon::now($tz);
        $today = \Illuminate\Support\Carbon::today($tz);
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h2 class="mb-0">{{ trans('sigac::visits.title.create_application') }}</h2>
                <small class="text-muted">
                    {{ trans('sigac::visits.index.subtitle') ?? 'Gestión de solicitudes de visitas y su programación.' }}
                </small>
            </div>

            @if (Route::has('sigac.academic_coordination.visitrequest.create'))
                <a href="{{ route('sigac.academic_coordination.visitrequest.create') }}" class="btn btn-primary"
                    data-bs-title="{{ trans('sigac::visits.actions.new_request') ?? 'Nueva solicitud' }}">
                    <i class="bi bi-plus-circle me-1"></i>
                    {{ trans('sigac::visits.actions.new_request') ?? 'Nueva solicitud' }}
                </a>
            @endif
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reporte de visitas</h5>
                    <small class="text-muted">Genera un reporte por rango de fechas (según agenda).</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="#"
                        class="row g-3">
                        @csrf

                        <div class="col-md-4">
                            <label class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Fecha fin</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Formato</label>
                            <select class="form-select" name="format" required>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <a href="{{ route('sigac.academic_coordination.visitrequest.index') }}"
                                class="btn btn-outline-secondary">
                                Volver
                            </a>
                            <button class="btn btn-primary">
                                <i class="bi bi-download me-1"></i> Generar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>{{ trans('sigac::visits.alert.check_data') }}</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- RESUMEN RÁPIDO --}}
            <div class="mb-3 d-flex gap-2 flex-wrap">
                <span class="badge text-bg-secondary">Sin agenda: {{ $unscheduled->count() }}</span>
                <span class="badge text-bg-primary">Con agenda: {{ $scheduled->count() }}</span>
            </div>

            {{-- ================== TABLA 1: SIN AGENDA ================== --}}
            <h5 class="mt-2 mb-2">
                <i class="bi bi-hourglass-split me-1"></i>
                Solicitudes sin agenda
            </h5>

            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Empresa / Entidad</th>
                            <th>Contacto</th>
                            <th>Correo</th>
                            <th style="width:110px;">Tipo</th>
                            <th>Observación</th>
                            <th style="width:160px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($unscheduled as $visit)
                            @php
                                // Excel
                                $excelPathRaw = (string) ($visit->people_list_path ?? '');
                                $excelPath = str_replace('\\', '/', $excelPathRaw);
                                if (
                                    \Illuminate\Support\Str::startsWith($excelPath, ['storage/app/', '/storage/app/'])
                                ) {
                                    $excelPath = \Illuminate\Support\Str::after($excelPath, 'storage/app/');
                                }
                                $existsNew =
                                    $excelPath &&
                                    \Illuminate\Support\Facades\Storage::disk('public')->exists($excelPath);
                                $existsLegacy =
                                    $excelPath &&
                                    \Illuminate\Support\Facades\Storage::disk('local')->exists($excelPath);
                                $canViewExcel = $existsNew || $existsLegacy;

                                $modalDetailId = "modal-uns-detail-{$visit->id}";
                            @endphp

                            <tr>
                                <td>{{ $visit->id }}</td>
                                <td>{{ optional($visit->company)->name ?? '—' }}</td>
                                <td>{{ $visit->contact_name ?? (optional($visit->person)->full_name ?? '—') }}</td>
                                <td>{{ $visit->contact_email ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-secondary text-capitalize">
                                        {{ $visit->type ?? '—' }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    {{ \Illuminate\Support\Str::limit($visit->observations ?? '—', 60) }}
                                </td>
                                <td>
                                    <div class="actions-wrap">
                                        {{-- Agendar --}}
                                        <a href="{{ route('sigac.academic_coordination.visitschedule.create', ['request' => $visit->id]) }}"
                                            class="btn btn-sm btn-primary btn-icon" data-bs-title="Agendar">
                                            <i class="bi bi-calendar-plus"></i>
                                        </a>

                                        {{-- Ver Excel --}}
                                        @if ($canViewExcel)
                                            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute(
                                                'sigac.academic_coordination.visits.peoplelist.preview',
                                                now()->addMinutes(30),
                                                ['visit' => $visit->id],
                                            ) }}"
                                                class="btn btn-sm btn-outline-success btn-icon" target="_blank"
                                                rel="noopener" data-bs-title="Ver listado (Excel)">
                                                <i class="bi bi-file-earmark-excel"></i>
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-sm btn-secondary btn-icon" disabled
                                                data-bs-title="Sin archivo">
                                                <i class="bi bi-file-earmark-x"></i>
                                            </button>
                                        @endif

                                        {{-- Ver detalle --}}
                                        <button type="button" class="btn btn-sm btn-light btn-icon"
                                            data-bs-title="Ver detalle" data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalDetailId }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            @push('modals')
                                {{-- Modal detalle (sin agenda) --}}
                                <div class="modal fade" id="{{ $modalDetailId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Solicitud #{{ $visit->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4">Empresa / Entidad</dt>
                                                    <dd class="col-sm-8">{{ optional($visit->company)->name ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Contacto</dt>
                                                    <dd class="col-sm-8">{{ $visit->contact_name ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Correo</dt>
                                                    <dd class="col-sm-8">{{ $visit->contact_email ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Teléfono</dt>
                                                    <dd class="col-sm-8">{{ $visit->contact_phone ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Tipo</dt>
                                                    <dd class="col-sm-8 text-capitalize">{{ $visit->type ?? '—' }}</dd>

                                                    @if (($visit->type ?? null) === 'practica')
                                                        <dt class="col-sm-4">Requisitos práctica</dt>
                                                        <dd class="col-sm-8">{{ $visit->practice_requirements ?? '—' }}</dd>
                                                    @endif

                                                    <dt class="col-sm-4">Observaciones</dt>
                                                    <dd class="col-sm-8">{{ $visit->observations ?? '—' }}</dd>
                                                </dl>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">Cerrar</button>
                                                <a href="{{ route('sigac.academic_coordination.visitschedule.create', ['request' => $visit->id]) }}"
                                                    class="btn btn-primary">
                                                    <i class="bi bi-calendar-plus me-1"></i> Agendar
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endpush
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay solicitudes pendientes por
                                    agendar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ================== TABLA 2: CON AGENDA ================== --}}
            <h5 class="mt-3 mb-2">
                <i class="bi bi-calendar-check me-1"></i>
                Solicitudes con visita agendada
            </h5>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th style="width:100px;">Empresa / Entidad</th>
                            <th style="width:240px;">Agenda</th>
                            <th style="width:240px;">Encargado</th>
                            <th style="width:140px;">Estado</th>
                            <th style="width:210px;">Portería</th>
                            <th style="width:360px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scheduled as $visit)
                            @php
                                $lastSchedule = optional($visit->schedules)->last();
                                $scheduleId = $lastSchedule->id ?? null;

                                // Excel
                                $excelPathRaw = (string) ($visit->people_list_path ?? '');
                                $excelPath = str_replace('\\', '/', $excelPathRaw);
                                if (
                                    \Illuminate\Support\Str::startsWith($excelPath, ['storage/app/', '/storage/app/'])
                                ) {
                                    $excelPath = \Illuminate\Support\Str::after($excelPath, 'storage/app/');
                                }
                                $existsNew =
                                    $excelPath &&
                                    \Illuminate\Support\Facades\Storage::disk('public')->exists($excelPath);
                                $existsLegacy =
                                    $excelPath &&
                                    \Illuminate\Support\Facades\Storage::disk('local')->exists($excelPath);
                                $canViewExcel = $existsNew || $existsLegacy;

                                // Estado temporal (para UI)
                                $rtKey = 'scheduled';
                                $rtColor = 'primary';
                                $rtLabel = 'Programada';

                                $isCancelled = strcasecmp((string) ($visit->state ?? ''), 'Cancelada') === 0;

                                $isVisitDay = false;
                                $startAt = null;
                                $endAt = null;

                                if ($lastSchedule) {
                                    $day = \Illuminate\Support\Carbon::parse($lastSchedule->date, $tz)->startOfDay();
                                    $startAt = \Illuminate\Support\Carbon::parse(
                                        $lastSchedule->date,
                                        $tz,
                                    )->setTimeFromTimeString($lastSchedule->start_time);
                                    $endAt = \Illuminate\Support\Carbon::parse(
                                        $lastSchedule->date,
                                        $tz,
                                    )->setTimeFromTimeString($lastSchedule->end_time);
                                    $isVisitDay = $day->isSameDay($today);

                                    if ($isCancelled) {
                                        $rtKey = 'cancelled';
                                        $rtColor = 'danger';
                                        $rtLabel = 'Cancelada';
                                    } elseif ($now->lt($day)) {
                                        $rtKey = 'scheduled';
                                        $rtColor = 'primary';
                                        $rtLabel = 'Programada';
                                    } elseif ($now->isSameDay($day)) {
                                        if ($now->lt($startAt)) {
                                            $rtKey = 'today';
                                            $rtColor = 'info';
                                            $rtLabel = 'Hoy';
                                        } elseif ($now->between($startAt, $endAt, true)) {
                                            $rtKey = 'in_progress';
                                            $rtColor = 'warning';
                                            $rtLabel = 'En curso';
                                        } else {
                                            $rtKey = 'finished';
                                            $rtColor = 'secondary';
                                            $rtLabel = 'Finalizada';
                                        }
                                    } else {
                                        $rtKey = 'finished';
                                        $rtColor = 'secondary';
                                        $rtLabel = 'Finalizada';
                                    }
                                }

                                // <24h badge
                                $soonBadge = null;
                                if ($startAt && !$isCancelled) {
                                    $diffH = $startAt->diffInHours($now, false);
                                    if ($diffH < 0 && abs($diffH) <= 24 && $rtKey === 'scheduled') {
                                        $soonBadge = '< 24h';
                                    }
                                }

                                // Portería
                                $checkInAt =
                                    $lastSchedule && $lastSchedule->check_in_at
                                        ? \Illuminate\Support\Carbon::parse($lastSchedule->check_in_at, $tz)
                                        : null;
                                $checkOutAt =
                                    $lastSchedule && $lastSchedule->check_out_at
                                        ? \Illuminate\Support\Carbon::parse($lastSchedule->check_out_at, $tz)
                                        : null;

                                $securityLabel = 'Sin registrar';
                                $securityColor = 'secondary';
                                if ($checkInAt && !$checkOutAt) {
                                    $securityLabel = 'Ingresaron';
                                    $securityColor = 'success';
                                } elseif ($checkInAt && $checkOutAt) {
                                    $securityLabel = 'Salida registrada';
                                    $securityColor = 'secondary';
                                }

                                $pdfAvailable = (bool) $lastSchedule;

                                // Encargado
                                $assigneeName =
                                    optional($lastSchedule?->personInCharge)->full_name ??
                                    trim(
                                        (string) optional($lastSchedule?->personInCharge)->first_name .
                                            ' ' .
                                            (string) optional($lastSchedule?->personInCharge)->first_last_name,
                                    );
                                $assigneeName = $assigneeName ? $assigneeName : '—';
                                $assigneeEmail = $lastSchedule?->notification_email ?: '—';

                                // IDs únicos de modales
                                $modalDetailId = "modal-sch-detail-{$visit->id}";
                                $modalNotifyId = "modal-sch-notify-{$visit->id}";
                                $modalReprogramId = $scheduleId ? "modal-sch-reprogram-{$scheduleId}" : null;
                                $modalCancelId = $scheduleId ? "modal-sch-cancel-{$scheduleId}" : null;

                                // Helpers de UI para cancelada
                                $disabledAttr = $isCancelled ? 'disabled' : '';
                                $disabledAria = $isCancelled ? 'aria-disabled=true tabindex=-1' : '';
                            @endphp

                            <tr data-state-key="{{ $rtKey }}" data-visit-id="{{ $visit->id }}">
                                <td>{{ $visit->id }}</td>
                                <td>{{ optional($visit->company)->name ?? '—' }}</td>

                                {{-- AGENDA (recuadro) --}}
                                <td>
                                    @if ($lastSchedule)
                                        <div class="agenda-box">
                                            <div class="agenda-date">
                                                {{ \Illuminate\Support\Carbon::parse($lastSchedule->date, $tz)->format('d/m/Y') }}
                                            </div>
                                            <div class="agenda-time text-muted">
                                                {{ $lastSchedule->start_time }} – {{ $lastSchedule->end_time }}
                                            </div>
                                            <div class="agenda-place">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                {{ optional($lastSchedule->environment)->name ?? 'SENA' }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">Sin agenda</span>
                                    @endif
                                </td>

                                {{-- ENCARGADO --}}
                                <td>
                                    <div class="small">
                                        <div class="fw-semibold">{{ $assigneeName }}</div>
                                        <div class="text-muted">{{ $assigneeEmail }}</div>
                                    </div>
                                </td>

                                {{-- ESTADO --}}
                                <td>
                                    <span class="badge bg-{{ $rtColor }}">{{ $rtLabel }}</span>
                                    @if ($soonBadge)
                                        <span class="badge bg-warning text-dark ms-1">{{ $soonBadge }}</span>
                                    @endif
                                </td>

                                {{-- PORTERÍA --}}
                                <td>
                                    @if ($lastSchedule)
                                        <span class="badge bg-{{ $securityColor }}">{{ $securityLabel }}</span>
                                        <div class="small text-muted mt-1">
                                            Ingreso: {{ $checkInAt ? $checkInAt->format('d/m H:i') : '—' }}<br>
                                            Salida: {{ $checkOutAt ? $checkOutAt->format('d/m H:i') : '—' }}
                                        </div>
                                    @else
                                        <span class="text-muted">Sin agenda</span>
                                    @endif
                                </td>

                                {{-- ACCIONES --}}
                                <td>
                                    <div class="actions-wrap">
                                        {{-- Ver detalle (SIEMPRE) --}}
                                        <button type="button" class="btn btn-light btn-icon" data-bs-title="Ver detalle"
                                            data-bs-toggle="modal" data-bs-target="#{{ $modalDetailId }}">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @if ($scheduleId)
                                            {{-- Reenviar notificación --}}
                                            <button type="button" class="btn btn-outline-primary btn-icon"
                                                data-bs-title="{{ $isCancelled ? 'Visita cancelada (bloqueado)' : 'Reenviar notificación' }}"
                                                data-bs-toggle="{{ $isCancelled ? '' : 'modal' }}"
                                                data-bs-target="{{ $isCancelled ? '' : '#' . $modalNotifyId }}"
                                                {{ $disabledAttr }}>
                                                <i class="bi bi-send"></i>
                                            </button>

                                            {{-- Reprogramar --}}
                                            <button type="button" class="btn btn-warning btn-icon"
                                                data-bs-title="{{ $isCancelled ? 'Visita cancelada (bloqueado)' : 'Reprogramar' }}"
                                                data-bs-toggle="{{ $isCancelled ? '' : 'modal' }}"
                                                data-bs-target="{{ $isCancelled ? '' : '#' . $modalReprogramId }}"
                                                {{ $disabledAttr }}>
                                                <i class="bi bi-calendar2-event"></i>
                                            </button>

                                            {{-- Cancelar --}}
                                            <button type="button" class="btn btn-danger btn-icon"
                                                data-bs-title="{{ $isCancelled ? 'Ya está cancelada' : 'Cancelar' }}"
                                                data-bs-toggle="{{ $isCancelled ? '' : 'modal' }}"
                                                data-bs-target="{{ $isCancelled ? '' : '#' . $modalCancelId }}"
                                                {{ $disabledAttr }}>
                                                <i class="bi bi-x-octagon"></i>
                                            </button>

                                            {{-- Enviar autorización a portería --}}
                                            <form
                                                action="{{ route('sigac.academic_coordination.visitschedule.security_authorize', $scheduleId) }}"
                                                method="POST" class="d-inline"
                                                onsubmit="return confirm('¿Enviar autorización de esta visita a portería/seguridad?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-dark btn-icon"
                                                    data-bs-title="{{ $isCancelled ? 'Visita cancelada (bloqueado)' : 'Enviar autorización a portería' }}"
                                                    {{ $disabledAttr }}>
                                                    <i class="bi bi-shield-lock"></i>
                                                </button>
                                            </form>

                                            {{-- PDF autorización (solo día de visita) --}}
                                            @if ($pdfAvailable && $isVisitDay && !$isCancelled)
                                                <a href="{{ route('sigac.academic_coordination.visitschedule.authorization_pdf', $scheduleId) }}"
                                                    class="btn btn-outline-secondary btn-icon" target="_blank"
                                                    rel="noopener" data-bs-title="Ver autorización PDF">
                                                    <i class="bi bi-file-earmark-lock"></i>
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-outline-secondary btn-icon" disabled
                                                    data-bs-title="{{ $isCancelled ? 'Visita cancelada' : ($pdfAvailable ? 'Disponible el día de la visita' : 'Aún no hay autorización') }}">
                                                    <i class="bi bi-file-earmark-lock"></i>
                                                </button>
                                            @endif
                                        @endif

                                        {{-- Excel --}}
                                        @if ($canViewExcel && !$isCancelled)
                                            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute(
                                                'sigac.academic_coordination.visits.peoplelist.preview',
                                                now()->addMinutes(30),
                                                ['visit' => $visit->id],
                                            ) }}"
                                                class="btn btn-outline-success btn-icon" target="_blank" rel="noopener"
                                                data-bs-title="Ver listado (Excel)">
                                                <i class="bi bi-file-earmark-excel"></i>
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-secondary btn-icon" disabled
                                                data-bs-title="{{ $isCancelled ? 'Visita cancelada' : 'Sin archivo' }}">
                                                <i class="bi bi-file-earmark-x"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @push('modals')
                                {{-- Modal detalle (con agenda) --}}
                                <div class="modal fade" id="{{ $modalDetailId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Solicitud #{{ $visit->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row">
                                                    <dt class="col-sm-4">Empresa / Entidad</dt>
                                                    <dd class="col-sm-8">{{ optional($visit->company)->name ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Contacto</dt>
                                                    <dd class="col-sm-8">
                                                        {{ $visit->contact_name ?? (optional($visit->person)->full_name ?? '—') }}
                                                    </dd>

                                                    <dt class="col-sm-4">Correo</dt>
                                                    <dd class="col-sm-8">{{ $visit->contact_email ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Teléfono</dt>
                                                    <dd class="col-sm-8">{{ $visit->contact_phone ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Tipo</dt>
                                                    <dd class="col-sm-8 text-capitalize">{{ $visit->type ?? '—' }}</dd>

                                                    <dt class="col-sm-4">Observaciones</dt>
                                                    <dd class="col-sm-8">{{ $visit->observations ?? '—' }}</dd>
                                                </dl>

                                                @if ($lastSchedule)
                                                    <hr>
                                                    <h6 class="mb-2"><i class="bi bi-calendar-event me-1"></i>Agenda</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 small">
                                                            <div><strong>Fecha:</strong>
                                                                {{ \Illuminate\Support\Carbon::parse($lastSchedule->date, $tz)->format('d/m/Y') }}
                                                            </div>
                                                            <div><strong>Hora:</strong> {{ $lastSchedule->start_time }} –
                                                                {{ $lastSchedule->end_time }}</div>
                                                            <div><strong>Ambiente:</strong>
                                                                {{ optional($lastSchedule->environment)->name ?? 'SENA' }}
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 small">
                                                            <div><strong>Encargado:</strong> {{ $assigneeName }}</div>
                                                            <div><strong>Correo encargado:</strong> {{ $assigneeEmail }}</div>
                                                            <div class="mt-2"><strong>Portería:</strong> <span
                                                                    class="badge bg-{{ $securityColor }}">{{ $securityLabel }}</span>
                                                            </div>
                                                            <div><strong>Ingreso:</strong>
                                                                {{ $checkInAt ? $checkInAt->format('d/m/Y H:i') : '—' }}</div>
                                                            <div><strong>Salida:</strong>
                                                                {{ $checkOutAt ? $checkOutAt->format('d/m/Y H:i') : '—' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if ($scheduleId)
                                    {{-- Modal reenviar notificación --}}
                                    <div class="modal fade" id="{{ $modalNotifyId }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-md modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST"
                                                    action="{{ route('sigac.academic_coordination.visitrequest.notify', $visit->id) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reenviar notificación — #{{ $visit->id }}
                                                        </h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="text-muted mb-2">Selecciona destinatarios:</p>

                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="targets[]"
                                                                value="visitor" id="t-visitor-{{ $visit->id }}" checked>
                                                            <label class="form-check-label"
                                                                for="t-visitor-{{ $visit->id }}">Invitado /
                                                                Solicitante</label>
                                                        </div>

                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="targets[]"
                                                                value="assignee" id="t-assignee-{{ $visit->id }}" checked>
                                                            <label class="form-check-label"
                                                                for="t-assignee-{{ $visit->id }}">Encargado</label>
                                                        </div>

                                                        <div class="alert alert-info small mt-3 mb-0">
                                                            Selecciona a quién reenviar el correo.No sea tan canson con los
                                                            correos.😒
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            data-bs-dismiss="modal">Cerrar</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-send me-1"></i> Reenviar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Modal reprogramar --}}
                                    <div class="modal fade" id="{{ $modalReprogramId }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST"
                                                    action="{{ route('sigac.academic_coordination.visitschedule.update', $scheduleId) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reprogramar visita — Solicitud
                                                            #{{ $visit->id }}</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Fecha</label>
                                                                <input type="date" class="form-control" name="date"
                                                                    value="{{ $lastSchedule->date ? \Carbon\Carbon::parse($lastSchedule->date)->format('Y-m-d') : '' }}"
                                                                    required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Hora inicio</label>
                                                                <input type="time" class="form-control" name="start_time"
                                                                    value="{{ $lastSchedule->start_time }}" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Hora fin</label>
                                                                <input type="time" class="form-control" name="end_time"
                                                                    value="{{ $lastSchedule->end_time }}" required>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label class="form-label">Actividad (opcional)</label>
                                                                <input type="text" class="form-control" name="activity"
                                                                    value="{{ $lastSchedule->activity }}">
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label class="form-label">Ambiente (opcional)</label>
                                                                <select class="form-select" name="environment_id">
                                                                    <option value="">Asignar después</option>
                                                                    @foreach ($environments ?? [] as $envId => $envName)
                                                                        <option value="{{ $envId }}"
                                                                            @selected($lastSchedule->environment_id == $envId)>{{ $envName }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label">Observaciones (opcional)</label>
                                                                <textarea class="form-control" rows="2" name="observations">{{ $lastSchedule->observations }}</textarea>
                                                            </div>
                                                        </div>

                                                        <div class="alert alert-info small mt-3 mb-0">
                                                            Si tu controlador reenvía correo al reprogramar, aquí quedará
                                                            reflejado.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            data-bs-dismiss="modal">Cerrar</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="bi bi-check2-circle me-1"></i> Guardar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Modal cancelar --}}
                                    <div class="modal fade" id="{{ $modalCancelId }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-md modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST"
                                                    action="{{ route('sigac.academic_coordination.visitschedule.cancel', $scheduleId) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Cancelar visita — Solicitud
                                                            #{{ $visit->id }}</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label">Motivo (opcional)</label>
                                                        <textarea class="form-control" rows="3" name="reason" placeholder="Observación"></textarea>

                                                        <div class="alert alert-warning mt-3 mb-0">
                                                            Esta acción marca la visita como cancelada.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            data-bs-dismiss="modal">Cerrar</button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="bi bi-x-octagon-fill me-1"></i> Confirmar
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endpush
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    {{ trans('sigac::visits.index.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Renderiza todos los modales aquí, fuera de las tablas --}}
            @stack('modals')
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .actions-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            align-items: center;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: .6rem;
            padding: 0;
        }

        .btn-icon i {
            font-size: 1.05rem;
        }

        .agenda-box {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: .6rem;
            padding: .55rem .65rem;
            background: #fff;
        }

        .agenda-date {
            font-weight: 700;
        }

        .agenda-time {
            font-size: .9rem;
        }

        .agenda-place {
            font-size: .9rem;
            margin-top: .15rem;
        }
    </style>
@endpush

@push('scripts')
    {{-- Si tu layout ya incluye bootstrap.bundle.min.js, elimina esta línea para evitar doble carga --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function() {
            document.querySelectorAll('[data-bs-title]').forEach(function(el) {
                var t = el.getAttribute('data-bs-title');
                if (!t) return;
                new bootstrap.Tooltip(el, {
                    title: t
                });
            });
        })();
    </script>
@endpush
