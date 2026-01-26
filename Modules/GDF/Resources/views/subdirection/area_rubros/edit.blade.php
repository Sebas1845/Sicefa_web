@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Rubros por área')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    if(!$isSubdirection){ abort(403); }

    $selected = function($rubroId) use ($current){
        return (bool) optional($current->get($rubroId))->active;
    };

    $isVigente = function($rubroId) use ($vigencyMap){
        return (bool) ($vigencyMap[$rubroId] ?? false);
    };
@endphp

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Rubros permitidos por área</h4>
            <div class="text-muted">
                Área: <strong>{{ $area->name }}</strong>
                <span class="mx-2">|</span>
                Vigencia: <strong>{{ $year }}</strong>
            </div>
        </div>

        <a href="{{ route('gdf.subdirection.areas.index') }}" class="btn btn-outline-secondary">
            Volver a áreas
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label mb-1">Buscar rubro</label>
                    <input name="q" value="{{ $q }}" class="form-control" placeholder="Nombre o código">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1">Año (vigencia)</label>
                    <select name="year" class="form-select">
                        @foreach($yearsList as $yy)
                            <option value="{{ $yy }}" {{ (int)$yy === (int)$year ? 'selected' : '' }}>
                                {{ $yy }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary">Aplicar</button>
                </div>
            </form>

            <div class="small text-muted mt-2">
                Nota: Solo puedes <strong>habilitar</strong> rubros que estén <strong>vigentes</strong> en el año seleccionado.
                Si un rubro estaba permitido pero ya no está vigente, podrás <strong>deshabilitarlo</strong>.
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('gdf.subdirection.area_rubros.update', $area->id) }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span>Selecciona los rubros que esta área puede ejecutar</span>
                    <div class="small text-muted">Filtrando por vigencia {{ $year }}</div>
                </div>
                <button class="btn btn-success">Guardar cambios</button>
            </div>

            <div class="card-body">
                @if($rubros->count() === 0)
                    <div class="alert alert-info mb-0">No hay rubros para mostrar.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:90px;">Permitir</th>
                                    <th style="width:160px;">Código</th>
                                    <th>Nombre</th>
                                    <th style="width:160px;">Vigente {{ $year }}</th>
                                    <th style="width:260px;">Histórico</th>
                                    <th style="width:110px;">Activo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rubros as $r)
                                    @php
                                        $alreadyAllowed = $selected($r->id);
                                        $vigenteYear = $isVigente($r->id);

                                        // Regla UI:
                                        // - Si NO vigente y NO estaba permitido => deshabilitar checkbox (no se puede habilitar)
                                        // - Si NO vigente pero estaba permitido => permitir desmarcar (checkbox habilitado)
                                        $disableEnable = (!$vigenteYear && !$alreadyAllowed);

                                        $hist = $historyMap->get($r->id, collect());
                                    @endphp

                                    <tr>
                                        <td>
                                            <input type="checkbox"
                                                name="allowed[]"
                                                value="{{ $r->id }}"
                                                {{ $alreadyAllowed ? 'checked' : '' }}
                                                {{ $disableEnable ? 'disabled' : '' }}>
                                        </td>

                                        <td>{{ $r->code }}</td>

                                        <td>
                                            <div class="fw-semibold">{{ $r->name }}</div>
                                            @if(!empty($r->description))
                                                <div class="text-muted small">{{ $r->description }}</div>
                                            @endif
                                        </td>

                                        <td>
                                            @if($vigenteYear)
                                                <span class="badge bg-success">Vigente</span>
                                            @else
                                                <span class="badge bg-secondary">No vigente</span>
                                                @if($alreadyAllowed)
                                                    <div class="text-danger small mt-1">
                                                        Estaba permitido, pero no está vigente en {{ $year }}.
                                                    </div>
                                                @endif
                                            @endif

                                            {{-- Si tienes starts_on / ends_on en la fila del año, puedes mostrarlas aquí.
                                                 En esta vista no tenemos el row directo, solo map booleano e histórico.
                                                 Si quieres, el controlador puede mandar yearRows para mostrar rango. --}}
                                        </td>

                                        <td>
                                            @if($hist->isEmpty())
                                                <span class="text-muted small">Sin registros</span>
                                            @else
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($hist as $hy)
                                                        @php
                                                            $hyYear = (int)($hy->year ?? 0);
                                                            $hyActive = (bool)($hy->active ?? false);
                                                        @endphp
                                                        <span class="badge {{ $hyActive ? 'bg-success' : 'bg-secondary' }}"
                                                              title="{{ $hyActive ? 'Vigente' : 'No vigente' }}">
                                                            {{ $hyYear }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>

                                        <td>
                                            @if($r->active)
                                                <span class="badge bg-success">Sí</span>
                                            @else
                                                <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @error('allowed')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                @endif
            </div>
        </div>
    </form>

</div>
@endsection
