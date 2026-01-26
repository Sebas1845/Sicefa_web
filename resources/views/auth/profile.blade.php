@extends('layouts.app')

@section('content')
@php
    // Si el controller no envía $apps, lo armamos aquí para que no se rompa
    if (!isset($apps)) {
        $apps = $user->roles
            ->loadMissing('app')
            ->groupBy(fn($r) => optional($r->app)->name ?? 'SICEFA')
            ->map(function ($roles, $appName) {
                return [
                    'app' => $appName,
                    'roles' => $roles->pluck('name')->unique()->values(),
                ];
            })->values();
    }

    $fullName = trim(
        ($user->person->first_name ?? '') . ' ' .
        ($user->person->first_last_name ?? '') . ' ' .
        ($user->person->second_last_name ?? '')
    );

    $displayName = $fullName !== '' ? $fullName : ($user->nickname ?? 'Usuario');
    $initials = collect(explode(' ', strtoupper($displayName)))
        ->filter()
        ->take(2)
        ->map(fn($p) => mb_substr($p, 0, 1))
        ->join('');

    if ($initials === '') $initials = 'U';
@endphp

<div class="container py-4">
    {{-- Encabezado --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Mi perfil</h4>
            <div class="text-muted small">
                Revisa tu información, roles y accesos disponibles en SICEFA.
            </div>
        </div>

        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a class="btn btn-outline-secondary" href="{{ route('cefa.welcome') ?? '#' }}">
                Volver al inicio
            </a>
            <a class="btn btn-primary" href="{{ route('cefa.password.change.index') ?? '#' }}">
                Cambiar contraseña
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('message'))
        <div class="alert alert-{{ session('typealert','info') }} shadow-sm">
            {{ session('message') }}
        </div>
    @endif

    <div class="row g-3">

        {{-- =======================
            COLUMNA IZQUIERDA
        ======================== --}}
        <div class="col-lg-4">

            {{-- Tarjeta usuario --}}
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                             style="width:56px;height:56px;font-weight:700;letter-spacing:.5px;">
                            {{ $initials }}
                        </div>

                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $displayName }}</div>
                            <div class="text-muted small">{{ $user->email ?? 'Sin correo' }}</div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted small">Documento</div>
                            <div class="fw-semibold">
                                {{ $user->person->document_number ?? '—' }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Nickname</div>
                            <div class="fw-semibold">
                                {{ $user->nickname ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Roles --}}
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white border-0 fw-semibold">
                    Roles asignados
                </div>
                <div class="card-body">
                    @forelse($user->roles as $role)
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <div class="me-2">
                                <div class="fw-semibold">{{ $role->name }}</div>
                                <div class="text-muted small">{{ $role->slug ?? '' }}</div>
                            </div>
                            <span class="badge bg-light text-dark border">
                                {{ $role->app->name ?? 'SICEFA' }}
                            </span>
                        </div>
                        @if(!$loop->last) <hr class="my-2"> @endif
                    @empty
                        <div class="text-muted">Sin roles asignados.</div>
                    @endforelse
                </div>
            </div>

            {{-- Módulos disponibles --}}
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white border-0 fw-semibold">
                    Módulos disponibles
                </div>
                <div class="card-body">
                    @forelse($apps as $a)
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $a['app'] }}</div>
                            <div class="text-muted small">
                                Roles: {{ is_array($a['roles']) ? implode(', ', $a['roles']) : $a['roles']->join(', ') }}
                            </div>

                            {{-- Atajos por módulo (ajusta rutas reales) --}}
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @if(str_contains(strtolower($a['app']), 'sigac'))
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.home') ?? '#' }}">
                                        Abrir SIGAC
                                    </a>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.schedule.index') ?? '#' }}">
                                        Mi horario
                                    </a>
                                @endif
                            </div>
                        </div>
                        @if(!$loop->last) <hr class="my-2"> @endif
                    @empty
                        <div class="text-muted">No hay módulos detectados.</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- =======================
            COLUMNA DERECHA
        ======================== --}}
        <div class="col-lg-8">

            {{-- Información personal --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 fw-semibold">
                    Información personal
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Nombres</div>
                            <div class="fw-semibold">{{ $user->person->first_name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Apellidos</div>
                            <div class="fw-semibold">
                                {{ trim(($user->person->first_last_name ?? '') . ' ' . ($user->person->second_last_name ?? '')) ?: '—' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Correo</div>
                            <div class="fw-semibold">{{ $user->email ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Módulo principal</div>
                            <div class="fw-semibold">{{ $apps->first()['app'] ?? 'SICEFA' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SIGAC: horario --}}
            @if(!empty($sigac) && !empty($sigac['items']))
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center">
                        <div class="fw-semibold">SIGAC · Mi horario (semana)</div>
                        <div class="text-muted small">Semana: {{ $sigac['week_range'] ?? '—' }}</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Día</th>
                                    <th>Hora</th>
                                    <th>Ficha</th>
                                    <th>Programa</th>
                                    <th>Ambiente</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($sigac['items'] as $i)
                                    <tr>
                                        <td class="fw-semibold">{{ $i['day'] ?? '-' }}</td>
                                        <td>{{ ($i['start'] ?? '-') }} - {{ ($i['end'] ?? '-') }}</td>
                                        <td>{{ $i['ficha'] ?? '-' }}</td>
                                        <td>{{ $i['programa'] ?? '-' }}</td>
                                        <td>{{ $i['ambiente'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-sm btn-primary" href="{{ route('sigac.schedule.index') ?? '#' }}">
                                Ver horario completo
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.schedule.export') ?? '#' }}">
                                Exportar
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Aprendiz: solicitudes --}}
            @if(!empty($apprenticeWidgets) && !empty($apprenticeWidgets['requests']))
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-header bg-white border-0 fw-semibold">
                        Mis solicitudes
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @foreach($apprenticeWidgets['requests'] as $r)
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $r['type'] ?? 'Solicitud' }}</div>
                                        <div class="text-muted small">Actualizado: {{ $r['updated_at'] ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-light text-dark border">
                                        {{ $r['status'] ?? '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-sm btn-primary" href="{{ route('sigac.requests.create') ?? '#' }}">
                                Nueva solicitud
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.requests.index') ?? '#' }}">
                                Ver todas
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Actualizar correo --}}
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white border-0 fw-semibold">
                    Actualizar correo
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger shadow-sm">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.email.update') }}">
                        @csrf

                        <div class="mb-2">
                            <label class="form-label">Correo</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email) }}"
                                autocomplete="email"
                            >
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                Guardar
                            </button>
                            <div class="text-muted small">
                                Recomendación: usa tu correo institucional cuando aplique.
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
