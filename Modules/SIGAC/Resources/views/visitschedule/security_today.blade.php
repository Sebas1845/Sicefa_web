@extends('sigac::layouts.master')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="mb-0">Control de visitas de hoy</h2>
        <span class="badge bg-primary">
            Fecha: {{ \Illuminate\Support\Carbon::parse($today)->format('Y-m-d') }}
        </span>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($schedules->isEmpty())
            <p class="text-muted mb-0">
                No hay visitas programadas para hoy.
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Empresa</th>
                            <th>Actividad</th>
                            <th>Ambiente</th>
                            <th>Hora prog.</th>
                            <th>Encargado</th>
                            <th>Estado</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th style="width: 180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $i => $schedule)
                            @php
                                $company   = $schedule->visitRequest->company->name  ?? '—';
                                $activity  = $schedule->activity                     ?? 'Visita';
                                $envName   = $schedule->environment->name            ?? '—';
                                $start     = $schedule->start_time ? substr($schedule->start_time, 0, 5) : '—';
                                $end       = $schedule->end_time   ? substr($schedule->end_time, 0, 5)   : '—';

                                $person    = $schedule->personInCharge
                                    ? trim($schedule->personInCharge->first_name . ' ' . $schedule->personInCharge->first_last_name)
                                    : '—';

                                $status    = $schedule->status ?? 'Programada';

                                // Formatear checkIn / checkOut
                                $checkInAt  = $schedule->check_in_at
                                    ? \Illuminate\Support\Carbon::parse($schedule->check_in_at)->format('H:i')
                                    : null;
                                $checkOutAt = $schedule->check_out_at
                                    ? \Illuminate\Support\Carbon::parse($schedule->check_out_at)->format('H:i')
                                    : null;

                                // Lógica de habilitar botones
                                $isCanceled   = (strcasecmp($status, 'Cancelada') === 0);
                                $canCheckIn   = !$isCanceled && !$checkInAt;
                                $canCheckOut  = !$isCanceled && $checkInAt && !$checkOutAt;
                            @endphp

                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $company }}</td>
                                <td>{{ $activity }}</td>
                                <td>{{ $envName }}</td>
                                <td>{{ $start }} - {{ $end }}</td>
                                <td>{{ $person }}</td>
                                <td>
                                    @php
                                        $badge = 'secondary';
                                        if ($status === 'Programada')  $badge = 'primary';
                                        if ($status === 'En curso')    $badge = 'warning';
                                        if ($status === 'Finalizada')  $badge = 'secondary';
                                        if ($status === 'Cancelada')   $badge = 'danger';
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ $status }}</span>
                                </td>
                                <td>
                                    @if($checkInAt)
                                        <span class="badge bg-success">Ingresó {{ $checkInAt }}</span>
                                    @else
                                        <span class="text-muted">Sin registro</span>
                                    @endif
                                </td>
                                <td>
                                    @if($checkOutAt)
                                        <span class="badge bg-dark">Salió {{ $checkOutAt }}</span>
                                    @else
                                        <span class="text-muted">Sin registro</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        {{-- Ver detalle de la solicitud (calendario por solicitud) --}}
                                        <a href="{{ route('sigac.academic_coordination.visitschedule.calendar', ['request' => $schedule->visit_request_id]) }}"
                                           class="btn btn-outline-secondary btn-sm"
                                           target="_blank">
                                            Ver
                                        </a>

                                        {{-- Check-In --}}
                                        <form action="{{ route('sigac.security.visits.checkin', $schedule) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-success btn-sm"
                                                {{ $canCheckIn ? '' : 'disabled' }}>
                                                Check-In
                                            </button>
                                        </form>

                                        {{-- Check-Out --}}
                                        <form action="{{ route('sigac.security.visits.checkout', $schedule) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-danger btn-sm"
                                                {{ $canCheckOut ? '' : 'disabled' }}>
                                                Check-Out
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
