{{-- Modules/GDF/Resources/views/subdirection/area_users/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Usuarios por área')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    if(!$isSubdirection){ abort(403); }

    $q       = $q ?? request('q');
    $areaId  = $areaId ?? request('area_id');
    $group   = $group ?? request('group', 'all');

    $stats = $stats ?? [
        'all'       => null,
        'academic'  => null,
        'campesena' => null,
        'shared'    => null,
        'none'      => null,
    ];

    // Opcional: si el controller te manda un map por user_id -> ['academic'=>'APOYO', 'campesena'=>'COORDINADOR']
    $roleBadgeMap = $roleBadgeMap ?? [];

    $qs = http_build_query(request()->query());

    $chip = function(string $key, string $label, string $icon, ?int $val, string $btnActive = 'btn-light') use ($group) {
        $active = $group === $key;
        $cls = $active ? $btnActive : 'btn-outline-light';
        $params = array_merge(request()->query(), ['group' => $key]);

        if (in_array($key, ['shared','none'], true)) {
            $params['area_id'] = '';
        }

        $url = route('gdf.subdirection.area_users.index', $params);
        $badge = is_null($val) ? '—' : $val;

        return <<<HTML
        <a class="btn {$cls} btn-sm" href="{$url}">
            <i class="bi {$icon}"></i> {$label}
            <span class="badge text-bg-dark border border-white border-opacity-10 ms-1">{$badge}</span>
        </a>
        HTML;
    };

    $isAcademicAreaName = function($name){
        $n = mb_strtoupper(trim((string)$name));
        return in_array($n, ['COORDINACION ACADEMICA','COORDINACIÓN ACADEMICA','COORDINACIÓN ACADÉMICA'], true);
    };
    $isCampesenaAreaName = fn($name) => mb_strtoupper(trim((string)$name)) === 'CAMPESENA';

    $prettyScope = function(?string $scope){
        $scope = strtolower(trim((string)$scope));
        return match($scope){
            'coordinator' => 'COORDINADOR',
            'support'     => 'APOYO',
            'instructor'  => 'INSTRUCTOR',
            default       => null
        };
    };

    // Dado un conjunto de áreas activas, retorna el scope (bonito) para el área lógica pedida
    $scopeForArea = function(string $areaKey, $activeAreas) use ($isAcademicAreaName, $isCampesenaAreaName, $prettyScope) {
        foreach ($activeAreas as $a) {
            if ($areaKey === 'academic' && $isAcademicAreaName($a->name)) {
                return $prettyScope($a->pivot->scope ?? null);
            }
            if ($areaKey === 'campesena' && $isCampesenaAreaName($a->name)) {
                return $prettyScope($a->pivot->scope ?? null);
            }
        }
        return null;
    };

    // Determina la “clave” principal por filtro o por áreas activas
    $mainAreaKeyResolver = function($areaId, $areas, $activeAreas) use ($isAcademicAreaName, $isCampesenaAreaName) {
        if ($areaId) {
            $a = $areas->firstWhere('id', (int)$areaId);
            if ($a && $isCampesenaAreaName($a->name)) return 'campesena';
            if ($a && $isAcademicAreaName($a->name))  return 'academic';
            return 'other';
        }

        $hasAcademic  = $activeAreas->first(fn($x) => $isAcademicAreaName($x->name)) ? true : false;
        $hasCampesena = $activeAreas->first(fn($x) => $isCampesenaAreaName($x->name)) ? true : false;

        if ($hasAcademic && !$hasCampesena) return 'academic';
        if ($hasCampesena && !$hasAcademic) return 'campesena';
        if ($hasAcademic && $hasCampesena)  return 'shared';
        return 'none';
    };

    // Obtiene texto de badge por (user_id, areaKey). Prioridad:
    // 1) roleBadgeMap (si viene)
    // 2) pivot scope del área activa
    // 3) SIN ROL
    $badgeFor = function($userId, string $areaKey, $activeAreas) use ($roleBadgeMap, $scopeForArea) {
        $fromMap = $roleBadgeMap[$userId][$areaKey] ?? null;
        if ($fromMap && trim($fromMap) !== '') return $fromMap;

        $fromPivot = $scopeForArea($areaKey, $activeAreas);
        if ($fromPivot) return $fromPivot;

        return 'SIN ROL';
    };

@endphp

<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Usuarios por área</h4>
            <div class="text-muted small">
                Asignación por pivote <code>gdf_area_user</code> (active=1).
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('gdf.subdirection.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <a href="{{ route('gdf.subdirection.users.index') }}" class="btn btn-outline-info">
                <i class="bi bi-people"></i> Usuarios y roles
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- Tabs --}}
    <div class="gdf-shell p-3 mb-3">
        <div class="d-flex flex-wrap gap-2">
            {!! $chip('all',      'Todos',       'bi-collection',    $stats['all']) !!}
            {!! $chip('academic', 'Académica',   'bi-mortarboard',   $stats['academic'], 'btn-primary') !!}
            {!! $chip('campesena','Campesena',   'bi-tree',          $stats['campesena'], 'btn-success') !!}
            {!! $chip('shared',   'Compartidos', 'bi-intersect',     $stats['shared'], 'btn-warning') !!}
            {!! $chip('none',     'Sin área',    'bi-slash-circle',  $stats['none'], 'btn-secondary') !!}
        </div>

        <div class="text-muted small mt-2">
            Tip: Si eliges “Compartidos” o “Sin área”, el filtro por área se desactiva automáticamente.
        </div>
    </div>

    {{-- Filters --}}
    <form class="row g-2 mb-3" method="GET" action="{{ route('gdf.subdirection.area_users.index') }}">
        <input type="hidden" name="group" value="{{ $group }}">

        <div class="col-md-4">
            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control"
                   placeholder="Buscar por nickname, email, nombres o documento...">
        </div>

        <div class="col-md-4">
            <select name="area_id" class="form-select" {{ in_array($group, ['shared','none'], true) ? 'disabled' : '' }}>
                <option value="">— Todas las áreas —</option>
                @foreach($areas as $a)
                    <option value="{{ $a->id }}" @selected((string)$areaId === (string)$a->id)>{{ $a->name }}</option>
                @endforeach
            </select>

            @if(in_array($group, ['shared','none'], true))
                <div class="form-text text-muted">Para “Compartidos” o “Sin área” el filtro por área no aplica.</div>
            @endif
        </div>

        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
            <a href="{{ route('gdf.subdirection.area_users.index') }}" class="btn btn-outline-secondary w-100">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>

    {{-- Grid --}}
    <div class="gdf-grid">
        @forelse($users as $u)
            @php
                $p = $u->person ?? null;
                $fullName = trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? ''));
                $doc = $p->document_number ?? null;

                $activeAreas = $u->activeGdfAreas ?? collect();
                $canRevoke = $activeAreas->isNotEmpty();

                $mainAreaKey = $mainAreaKeyResolver($areaId, $areas, $activeAreas);

                $badgeAcademic  = $badgeFor($u->id, 'academic',  $activeAreas);
                $badgeCampesena = $badgeFor($u->id, 'campesena', $activeAreas);
            @endphp

            <div class="gdf-card2">
                <div class="d-flex gap-2 align-items-start">
                    <div class="gdf-avatar2"><i class="bi bi-person-circle"></i></div>

                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $u->nickname }}</div>
                        <div class="gdf-muted small text-truncate">{{ $fullName ?: '—' }}</div>
                        <div class="gdf-meta mt-2">
                            <div class="text-truncate"><i class="bi bi-envelope"></i> {{ $u->email ?? '—' }}</div>
                            <div class="text-truncate"><i class="bi bi-credit-card-2-front"></i> {{ $doc ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="text-end gdf-muted small">
                        <div>ID: {{ $u->id }}</div>
                        <div>Áreas: <span class="fw-semibold">{{ $activeAreas->count() }}</span></div>
                    </div>
                </div>

                {{-- BADGE PRINCIPAL (rol/scope + área) --}}
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @if($mainAreaKey === 'academic')
                        <span class="badge gdf-badge-main gdf-badge-academic">
                            {{ $badgeAcademic }} · ACADÉMICA
                        </span>
                    @elseif($mainAreaKey === 'campesena')
                        <span class="badge gdf-badge-main gdf-badge-campesena">
                            {{ $badgeCampesena }} · CAMPESENA
                        </span>
                    @elseif($mainAreaKey === 'shared')
                        <span class="badge gdf-badge-main gdf-badge-academic">
                            {{ $badgeAcademic }} · ACADÉMICA
                        </span>
                        <span class="badge gdf-badge-main gdf-badge-campesena">
                            {{ $badgeCampesena }} · CAMPESENA
                        </span>
                    @elseif($mainAreaKey === 'other')
                        <span class="badge gdf-badge-main gdf-badge-neutral">
                            {{ $prettyScope($activeAreas->first()?->pivot?->scope ?? null) ?? 'SIN ROL' }} · ÁREA
                        </span>
                    @else
                        <span class="badge text-bg-secondary">SIN ÁREA</span>
                    @endif
                </div>

                {{-- ÁREAS ACTIVAS (pills) --}}
                <div class="mt-2">
                    @if($activeAreas->isEmpty())
                        <span class="badge text-bg-secondary">Sin área asignada</span>
                    @else
                        @foreach($activeAreas as $a)
                            @php
                                $isAcad = $isAcademicAreaName($a->name);
                                $isCamp = $isCampesenaAreaName($a->name);
                                $cls = $isAcad ? 'gdf-pill-academic' : ($isCamp ? 'gdf-pill-campesena' : 'gdf-pill-neutral');

                                // Si quieres mostrar scope pequeño al lado del área, descomenta:
                                // $sc = $prettyScope($a->pivot->scope ?? null);
                            @endphp
                            <span class="badge {{ $cls }} me-1 mb-1">
                                {{ $a->name }}
                                {{-- @if($sc) <span class="opacity-75">· {{ $sc }}</span> @endif --}}
                            </span>
                        @endforeach
                    @endif
                </div>

                <hr class="gdf-hr my-3">

                {{-- Assign --}}
                <div class="gdf-box">
                    <div class="gdf-box-title"><i class="bi bi-plus-circle"></i> Asignar área</div>

                    <form method="POST" action="{{ route('gdf.subdirection.area_users.assign') }}" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                        <input type="hidden" name="_redirect_qs" value="{{ $qs }}">

                        <select name="area_id" class="form-select form-select-sm" required>
                            @foreach($areas as $a)
                                <option value="{{ $a->id }}" @selected(!in_array($group,['shared','none'],true) && (string)$areaId === (string)$a->id)>
                                    {{ $a->name }}
                                </option>
                            @endforeach
                        </select>

                        <select name="scope" class="form-select form-select-sm">
                            <option value="">(scope)</option>
                            <option value="coordinator">coordinator</option>
                            <option value="support">support</option>
                            <option value="instructor">instructor</option>
                        </select>

                        <button class="btn btn-success btn-sm" type="submit" title="Asignar">
                            <i class="bi bi-check2-circle"></i>
                        </button>
                    </form>

                    <div class="gdf-muted small mt-2">
                        Se conserva el filtro actual después de asignar.
                    </div>
                </div>

                {{-- Revoke --}}
                <div class="gdf-box mt-2">
                    <div class="gdf-box-title"><i class="bi bi-dash-circle"></i> Desasignar</div>

                    <form method="POST" action="{{ route('gdf.subdirection.area_users.revoke') }}" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                        <input type="hidden" name="_redirect_qs" value="{{ $qs }}">

                        <select name="area_id" class="form-select form-select-sm" required {{ $canRevoke ? '' : 'disabled' }}>
                            @foreach($activeAreas as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>

                        <button class="btn btn-outline-danger btn-sm" type="submit"
                                onclick="return confirm('¿Desasignar esta área?');"
                                {{ $canRevoke ? '' : 'disabled' }}>
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>

                    @if(!$canRevoke)
                        <div class="gdf-muted small mt-2">No hay áreas activas para desasignar.</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-4">No hay resultados.</div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $users->links('pagination::bootstrap-4') }}
    </div>
</div>

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
.gdf-card2:hover{
    border-color: rgba(255,255,255,.18);
    background: rgba(20,26,36,.78);
}
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

/* badges principales */
.gdf-badge-main{
    padding: 10px 14px;
    border-radius: 10px;
    font-weight: 800;
    letter-spacing: .2px;
    border: 1px solid rgba(255,255,255,.10);
}
.gdf-badge-academic{
    background: rgba(13,110,253,.25);
    color: rgba(255,255,255,.92);
}
.gdf-badge-campesena{
    background: rgba(25,135,84,.25);
    color: rgba(255,255,255,.92);
}
.gdf-badge-neutral{
    background: rgba(255,255,255,.12);
    color: rgba(255,255,255,.92);
}

/* pills de áreas */
.gdf-pill-academic{ background: rgba(13,110,253,.12) !important; color: #fff !important; border: 1px solid rgba(13,110,253,.35) !important; }
.gdf-pill-campesena{ background: rgba(25,135,84,.12) !important; color: #fff !important; border: 1px solid rgba(25,135,84,.35) !important; }
.gdf-pill-neutral{ background: rgba(255,255,255,.10) !important; color: #fff !important; border: 1px solid rgba(255,255,255,.20) !important; }
</style>
@endsection
