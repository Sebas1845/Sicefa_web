@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Usuarios (Subdirección)')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    if (!$isSubdirection) abort(403);

    $pretty = function (string $slug): string {
        return match ($slug) {
            'gdf.treasury'              => 'Tesorería',
            'gdf.academic_coordinator'  => 'Coord. Académico',
            'gdf.campesena_coordinator' => 'Coord. Campesena',
            'gdf.academic_support'      => 'Apoyo Académico',
            'gdf.campesena_support'     => 'Apoyo Campesena',
            'gdf.instructor'            => 'Instructor',
            default                     => $slug,
        };
    };

    $cleanRoleName = function (?string $name): string {
        $name = (string) $name;
        return trim(str_replace('slug>', '', $name));
    };

    $q                  = $q ?? request('q');
    $roleSlug            = $roleSlug ?? request('role_slug');
    $onlyUsers           = request()->boolean('only_users');
    $src                 = $src ?? request('src');
    $importType          = $importType ?? request('import_type', 'none');
    $importQ             = $importQ ?? request('import_q');
    $includeApprentices  = $includeApprentices ?? request()->boolean('include_apprentices');
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="gdf-shell p-4">

            {{-- Header --}}
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <div class="text-muted small">GDF / Subdirección</div>
                    <h3 class="fw-bold mb-1">Usuarios y roles</h3>
                    <p class="gdf-muted mb-0">
                        Administración de usuarios del módulo GDF. Restricción: no se asigna <b>gdf.admin</b> ni <b>gdf.subdirection</b>.
                        <br>
                        <span class="badge text-bg-secondary mt-2">
                            {{ $includeApprentices ? 'Mostrando aprendices (SIGAC)' : 'Aprendices SIGAC ocultos (por defecto)' }}
                        </span>
                    </p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('gdf.subdirection.dashboard') }}" class="btn btn-gdf-ghost btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>

                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createUserByDocModal">
                        <i class="bi bi-person-plus"></i> Alta rápida (Cédula)
                    </button>

                    <a class="btn btn-outline-light btn-sm"
                       href="{{ route('gdf.subdirection.users.index', array_merge(request()->query(), ['import_type' => 'both'])) }}">
                        <i class="bi bi-people-fill"></i> Importar (sin usuario)
                    </a>

                    <a class="btn btn-outline-info btn-sm" href="{{ route('gdf.subdirection.area_users.index') }}">
                        <i class="bi bi-diagram-3"></i> Usuarios por área
                    </a>
                </div>
            </div>

            <hr class="gdf-hr my-4">

            {{-- Flash --}}
            @foreach (['success' => 'success', 'warning' => 'warning', 'info' => 'info', 'error' => 'danger'] as $k => $type)
                @if (session($k))
                    <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
                @endif
            @endforeach

            {{-- Filters --}}
            <form class="row g-2 mb-3" method="GET" action="{{ route('gdf.subdirection.users.index') }}">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" value="{{ $q ?? '' }}"
                           placeholder="Buscar por nickname, email, nombres o documento...">
                </div>

                <div class="col-md-3">
                    <select name="role_slug" class="form-select">
                        <option value="">— Roles GDF permitidos —</option>
                        @foreach ($gdfRoles as $r)
                            <option value="{{ $r->slug }}" @selected(($roleSlug ?? '') === $r->slug)>
                                {{ $cleanRoleName($r->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-5 d-flex gap-2 flex-wrap">
                    <button class="btn btn-light" type="submit">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>

                    <a class="btn {{ $onlyUsers ? 'btn-light' : 'btn-outline-light' }}"
                       href="{{ route('gdf.subdirection.users.index', array_merge(request()->query(), ['only_users' => 1])) }}">
                        <i class="bi bi-people"></i> Usuarios
                    </a>

                    <a class="btn {{ $src === 'contractor' ? 'btn-light' : 'btn-outline-light' }}"
                       href="{{ route('gdf.subdirection.users.index', array_merge(request()->query(), ['src' => 'contractor'])) }}">
                        <i class="bi bi-person-badge"></i> Contractors
                    </a>

                    <a class="btn {{ $src === 'employee' ? 'btn-light' : 'btn-outline-light' }}"
                       href="{{ route('gdf.subdirection.users.index', array_merge(request()->query(), ['src' => 'employee'])) }}">
                        <i class="bi bi-person-vcard"></i> Employees
                    </a>

                    <a class="btn {{ $includeApprentices ? 'btn-light' : 'btn-outline-light' }}"
                       href="{{ route('gdf.subdirection.users.index', array_merge(request()->query(), [
                            'include_apprentices' => $includeApprentices ? 0 : 1,
                       ])) }}">
                        <i class="bi bi-mortarboard"></i>
                        {{ $includeApprentices ? 'Ocultar aprendices' : 'Incluir aprendices' }}
                    </a>

                    <a class="btn btn-outline-secondary" href="{{ route('gdf.subdirection.users.index') }}">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>

            {{-- Grid --}}
            <div class="gdf-grid">
                @forelse($users as $u)
                    @php
                        $allRoles = $u->roles ?? collect();
                        $gdfUserRoles = $allRoles->filter(fn($r) => is_string($r->slug) && str_starts_with($r->slug, 'gdf.'));
                        $gdfUserRoleSlugs = $gdfUserRoles->pluck('slug')->values();

                        $revokeOptions = $gdfUserRoles->sortBy('name');
                        $assignOptions = $gdfRoles->filter(fn($r) => !$gdfUserRoleSlugs->contains($r->slug))->sortBy('name');

                        $p = $u->person ?? null;
                        $fullName = trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? ''));
                        $doc = $p->document_number ?? null;

                        $email = $u->email ?? null;
                        $hasEmail = !empty($email);
                        $isForced = (bool)($u->force_password_change ?? false);
                    @endphp

                    <div class="gdf-card2">
                        <div class="d-flex gap-2 align-items-start">
                            <div class="gdf-avatar2"><i class="bi bi-person-circle"></i></div>

                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-semibold text-truncate">{{ $u->nickname }}</div>
                                    @if($isForced)
                                        <span class="badge text-bg-warning">Pendiente contraseña</span>
                                    @endif
                                </div>

                                @if($fullName)
                                    <div class="gdf-muted small text-truncate">{{ $fullName }}</div>
                                @endif

                                <div class="gdf-meta mt-2">
                                    <div class="text-truncate"><i class="bi bi-envelope"></i> {{ $email ?? '—' }}</div>
                                    @if($doc)
                                        <div class="text-truncate"><i class="bi bi-credit-card-2-front"></i> {{ $doc }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="text-end gdf-muted small">
                                <div>ID: {{ $u->id }}</div>
                                <div>Roles: <span class="fw-semibold">{{ $gdfUserRoles->count() }}</span></div>
                            </div>
                        </div>

                        <div class="mt-3">
                            @if($gdfUserRoles->isEmpty())
                                <span class="badge text-bg-secondary">Sin roles GDF</span>
                            @else
                                @foreach($gdfUserRoles as $r)
                                    <span class="badge text-bg-success me-1 mb-1">{{ $pretty($r->slug) }}</span>
                                @endforeach
                            @endif
                        </div>

                        <hr class="gdf-hr my-3">

                        {{-- Add role --}}
                        <div class="gdf-box">
                            <div class="gdf-box-title"><i class="bi bi-plus-circle"></i> Agregar rol</div>
                            @if($assignOptions->isEmpty())
                                <div class="gdf-muted small">Sin roles disponibles para asignar.</div>
                            @else
                                <form method="POST" action="{{ route('gdf.subdirection.users.assignRole') }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <select name="role_slug" class="form-select form-select-sm" required>
                                        @foreach($assignOptions as $r)
                                            <option value="{{ $r->slug }}">{{ $cleanRoleName($r->name) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-success btn-sm" type="submit" title="Asignar">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Revoke role --}}
                        <div class="gdf-box mt-2">
                            <div class="gdf-box-title"><i class="bi bi-dash-circle"></i> Quitar rol</div>
                            @if($revokeOptions->isEmpty())
                                <div class="gdf-muted small">No tiene roles GDF para quitar.</div>
                            @else
                                <form method="POST" action="{{ route('gdf.subdirection.users.revokeRole') }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <select name="role_slug" class="form-select form-select-sm" required>
                                        @foreach($revokeOptions as $r)
                                            <option value="{{ $r->slug }}">{{ $cleanRoleName($r->name) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-danger btn-sm" type="submit"
                                            onclick="return confirm('¿Seguro que deseas quitar este rol?');">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Support --}}
                        <div class="gdf-box mt-2">
                            <div class="gdf-box-title"><i class="bi bi-shield-lock"></i> Soporte</div>

                            @if(!$hasEmail)
                                <div class="gdf-muted small">Sin correo: no se puede enviar enlace.</div>
                            @else
                                <form method="POST" action="{{ route('gdf.subdirection.users.sendResetLink', $u->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-sm w-100"
                                            onclick="return confirm('¿Enviar enlace a {{ $email }}?');">
                                        <i class="bi bi-envelope"></i> Enviar enlace de acceso
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="gdf-muted small mt-2">
                            Solo roles <span class="fw-semibold">gdf.*</span> permitidos (sin Admin/Subdirección).
                        </div>
                    </div>
                @empty
                    <div class="text-center gdf-muted py-4">No hay resultados.</div>
                @endforelse
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $users->links('pagination::bootstrap-4') }}
            </div>

        </div>
    </div>
</div>

{{-- MODAL: Alta rápida (Cédula + Rol + Área) --}}
<div class="modal fade" id="createUserByDocModal" tabindex="-1" aria-labelledby="createUserByDocModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('gdf.subdirection.users.createByDocument') }}">
            @csrf
            <div class="modal-content gdf-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserByDocModalLabel">
                        <i class="bi bi-person-plus"></i> Alta rápida (Cédula + Rol + Área)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p class="gdf-muted mb-3">
                        Busca la persona en <span class="fw-semibold">contractors</span> o <span class="fw-semibold">employees</span>.
                        Si ya existe usuario, se actualiza (y se fuerza creación de contraseña).
                    </p>

                    <div class="mb-2">
                        <label class="form-label">Cédula</label>
                        <input type="text" name="document_number" class="form-control" placeholder="Ej: 1234567890" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Origen</label>
                        <select name="source" class="form-select">
                            <option value="auto">Auto</option>
                            <option value="contractor">Contractor</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>

                    <hr class="gdf-hr">

                    <div class="mb-2">
                        <label class="form-label">Rol inicial</label>
                        <select name="role_slug" class="form-select" required>
                            <option value="gdf.instructor">Instructor</option>
                            <option value="gdf.academic_support">Apoyo Académico</option>
                            <option value="gdf.campesena_support">Apoyo Campesena</option>
                            <option value="gdf.academic_coordinator">Coord. Académico</option>
                            <option value="gdf.campesena_coordinator">Coord. Campesena</option>
                            <option value="gdf.treasury">Tesorería</option>
                        </select>

                        <div class="gdf-muted small mt-1">
                            El sistema validará coherencia: roles Académico/Campesena requieren su área.
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Áreas</label>

                        <div class="d-flex flex-wrap gap-3">
                            <label class="d-flex align-items-center gap-2">
                                <input type="checkbox" name="areas[]" value="academic">
                                <span>Académica</span>
                            </label>

                            <label class="d-flex align-items-center gap-2">
                                <input type="checkbox" name="areas[]" value="campesena">
                                <span>Campesena</span>
                            </label>
                        </div>

                        <div class="gdf-muted small mt-2">
                            Instructor puede ir sin área (si así lo manejas). Coordinación/Apoyo debe marcar su área.
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Scope (opcional)</label>
                        <select name="scope" class="form-select">
                            <option value="">(sin scope)</option>
                            <option value="coordinator">coordinator</option>
                            <option value="support">support</option>
                            <option value="instructor">instructor</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Crear / Actualizar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- MODAL: Importar usuarios (sin usuario) --}}
<div class="modal fade" id="importUsersModal" tabindex="-1" aria-labelledby="importUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content gdf-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="importUsersModalLabel">
                    <i class="bi bi-people-fill"></i> Importar usuarios (sin usuario)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small">
                    Selecciona personas (Contractors/Employees) que aún no tienen usuario.
                </div>

                <form method="GET" action="{{ route('gdf.subdirection.users.index') }}" class="row g-2 mb-3">
                    <input type="hidden" name="q" value="{{ $q ?? '' }}">
                    <input type="hidden" name="role_slug" value="{{ $roleSlug ?? '' }}">
                    @if($src) <input type="hidden" name="src" value="{{ $src }}"> @endif
                    @if($onlyUsers) <input type="hidden" name="only_users" value="1"> @endif
                    <input type="hidden" name="include_apprentices" value="{{ $includeApprentices ? 1 : 0 }}">

                    <div class="col-md-4">
                        <select name="import_type" class="form-select">
                            <option value="both" @selected(($importType ?? '') === 'both')>Contractors + Employees</option>
                            <option value="contractors" @selected(($importType ?? '') === 'contractors')>Solo Contractors</option>
                            <option value="employees" @selected(($importType ?? '') === 'employees')>Solo Employees</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="import_q" class="form-control" value="{{ $importQ ?? '' }}"
                               placeholder="Buscar (nombre o cédula)...">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-light w-100"><i class="bi bi-arrow-repeat"></i> Cargar</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('gdf.subdirection.users.import.create') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="gdf-box2">
                                <div class="gdf-box2-title"><i class="bi bi-person-badge"></i> Contractors</div>
                                <div class="gdf-scroll">
                                    @forelse(($contractors ?? []) as $c)
                                        @php $p = $c->person ?? null; @endphp
                                        @if($p)
                                            <label class="gdf-rowcheck">
                                                <input type="checkbox" name="selected[]" value="contractor:{{ $p->id }}">
                                                <div>
                                                    <div class="fw-semibold">{{ $p->first_name }} {{ $p->first_last_name }}</div>
                                                    <div class="gdf-muted small">CC {{ $p->document_number }}</div>
                                                </div>
                                            </label>
                                        @endif
                                    @empty
                                        <div class="gdf-muted small p-3">Sin resultados.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="gdf-box2">
                                <div class="gdf-box2-title"><i class="bi bi-person-vcard"></i> Employees</div>
                                <div class="gdf-scroll">
                                    @forelse(($employees ?? []) as $e)
                                        @php $p = $e->person ?? null; @endphp
                                        @if($p)
                                            <label class="gdf-rowcheck">
                                                <input type="checkbox" name="selected[]" value="employee:{{ $p->id }}">
                                                <div>
                                                    <div class="fw-semibold">{{ $p->first_name }} {{ $p->first_last_name }}</div>
                                                    <div class="gdf-muted small">CC {{ $p->document_number }}</div>
                                                </div>
                                            </label>
                                        @endif
                                    @empty
                                        <div class="gdf-muted small p-3">Sin resultados.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer mt-3">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('¿Crear usuarios seleccionados?');">
                            <i class="bi bi-check2-circle"></i> Crear usuarios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if(in_array(request('import_type'), ['both','contractors','employees'], true))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('importUsersModal');
    if (el && window.bootstrap) new bootstrap.Modal(el).show();
});
</script>
@endif

<style>
.gdf-shell{
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 18px;
    background: rgba(10,14,20,.55);
    box-shadow: 0 10px 30px rgba(0,0,0,.35);
    backdrop-filter: blur(8px);
}
.gdf-muted{ color: rgba(255,255,255,.70) !important; }
.gdf-hr{ border-color: rgba(255,255,255,.10) !important; }

.gdf-grid{
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
@media (max-width: 1200px){ .gdf-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 768px){ .gdf-grid{ grid-template-columns: 1fr; } }

.gdf-card2{
    padding:14px;
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 16px;
    background: rgba(18,22,30,.72);
    box-shadow: 0 6px 18px rgba(0,0,0,.25);
    min-width: 0;
}
.gdf-card2:hover{ border-color: rgba(255,255,255,.18); background: rgba(20,26,36,.78); }
.gdf-avatar2{
    width:44px; height:44px;
    display:flex; align-items:center; justify-content:center;
    border-radius: 12px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    font-size:22px;
    flex: 0 0 auto;
}
.gdf-meta{
    font-size: .88rem;
    color: rgba(255,255,255,.72);
    display:flex;
    flex-direction:column;
    gap:3px;
}
.gdf-box{
    padding:12px;
    border-radius: 14px;
    background: rgba(0,0,0,.22);
    border: 1px solid rgba(255,255,255,.10);
}
.gdf-box-title{
    display:flex; align-items:center; gap:8px;
    font-weight: 700;
    color: rgba(255,255,255,.88);
    margin-bottom: 8px;
}
.gdf-modal{
    background: rgba(18,22,30,.98);
    border: 1px solid rgba(255,255,255,.10);
    color: #fff;
}
.gdf-modal .modal-header{ border-bottom: 1px solid rgba(255,255,255,.10); }
.gdf-modal .modal-footer{ border-top: 1px solid rgba(255,255,255,.10); }
.gdf-box2{
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,.10);
    background: rgba(0,0,0,.20);
}
.gdf-box2-title{
    display:flex; align-items:center; gap:8px;
    font-weight:700;
    margin-bottom:10px;
}
.gdf-scroll{
    max-height: 320px;
    overflow: auto;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,.08);
    background: rgba(18,22,30,.55);
}
.gdf-rowcheck{
    display:flex; gap:10px;
    padding:10px 12px;
    margin:0;
    cursor:pointer;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.gdf-rowcheck:hover{ background: rgba(255,255,255,.04); }
.gdf-rowcheck input{ margin-top: 3px; }
</style>
@endsection
