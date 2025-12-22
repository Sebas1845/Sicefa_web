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
@endphp

<div class="container py-4">
    <div class="row g-3">

        {{-- =======================
            COLUMNA IZQUIERDA
        ======================== --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-secondary mx-auto mb-3"
                         style="width:90px;height:90px;opacity:.2;"></div>

                    <h5 class="mb-1">{{ $user->person->first_name ?? $user->nickname }}</h5>
                    <div class="text-muted small">{{ $user->email }}</div>

                    <div class="mt-3">
                        <a href="{{ route('cefa.password.change.index') ?? '#' }}"
                           class="btn btn-outline-primary btn-sm">
                            Cambiar contraseña
                        </a>
                    </div>
                </div>
            </div>

            {{-- ROLES --}}
            <div class="card mt-3">
                <div class="card-header">Roles</div>
                <div class="card-body">
                    @forelse($user->roles as $role)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>{{ $role->name }}</span>
                            <span class="badge bg-light text-dark">{{ $role->app->name ?? 'SICEFA' }}</span>
                        </div>
                    @empty
                        <div class="text-muted">Sin roles asignados</div>
                    @endforelse
                </div>
            </div>

            {{-- MODULOS / APPS --}}
            <div class="card mt-3">
                <div class="card-header">Módulos disponibles</div>
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
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.home') ?? '#' }}">Abrir SIGAC</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.schedule.index') ?? '#' }}">Mi horario</a>
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
        <div class="col-md-8">
            @if (session('message'))
                <div class="alert alert-{{ session('typealert','info') }}">
                    {{ session('message') }}
                </div>
            @endif

            {{-- INFORMACIÓN PERSONAL --}}
            <div class="card">
                <div class="card-header">Información personal</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Documento</div>
                            <div class="fw-semibold">{{ $user->person->document_number ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Nickname</div>
                            <div class="fw-semibold">{{ $user->nickname ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Nombres</div>
                            <div class="fw-semibold">{{ $user->person->first_name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Apellidos</div>
                            <div class="fw-semibold">
                                {{ $user->person->first_last_name ?? '' }} {{ $user->person->second_last_name ?? '' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SIGAC: HORARIO SEMANAL (solo si llega $sigac) --}}
            @if(!empty($sigac) && !empty($sigac['items']))
                <div class="card mt-3">
                    <div class="card-header">SIGAC · Mi horario (semana)</div>
                    <div class="card-body">
                        <div class="text-muted small mb-2">
                            Semana: {{ $sigac['week_range'] ?? '—' }}
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
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

                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary" href="{{ route('sigac.schedule.index') ?? '#' }}">Ver horario completo</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.schedule.export') ?? '#' }}">Exportar</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- APRENDIZ: ESTADO DE SOLICITUDES (solo si llega $apprenticeWidgets) --}}
            @if(!empty($apprenticeWidgets) && !empty($apprenticeWidgets['requests']))
                <div class="card mt-3">
                    <div class="card-header">Mis solicitudes</div>
                    <div class="card-body">
                        @foreach($apprenticeWidgets['requests'] as $r)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold">{{ $r['type'] ?? 'Solicitud' }}</div>
                                    <div class="text-muted small">Actualizado: {{ $r['updated_at'] ?? '-' }}</div>
                                </div>
                                <span class="badge bg-light text-dark">{{ $r['status'] ?? '—' }}</span>
                            </div>
                        @endforeach

                        <div class="mt-3 d-flex gap-2">
                            <a class="btn btn-sm btn-primary" href="{{ route('sigac.requests.create') ?? '#' }}">Nueva solicitud</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sigac.requests.index') ?? '#' }}">Ver todas</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ACTUALIZAR CORREO --}}
            <div class="card mt-3">
                <div class="card-header">Actualizar correo</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.email.update') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn btn-primary">Guardar</button>
                        <div class="text-muted small mt-2">
                            Recomendación: usa tu correo institucional cuando aplique.
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
