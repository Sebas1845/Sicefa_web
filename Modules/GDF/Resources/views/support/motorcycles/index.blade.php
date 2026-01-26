@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Apoyo - Asignaciones de motos')

@section('content')
@php
    // guard mínimo (ajusta slugs a tu realidad)
    $ok = function_exists('checkRol') && (
        checkRol('gdf.academic_support') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin')
    );
    if(!$ok) abort(403);
@endphp

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Asignaciones de motos (Apoyo)</h4>
            <div class="text-muted">Consulta por rubro, persona, placa y estado.</div>
        </div>

        {{-- Si Apoyo también puede crear asignación, apunta a su ruta --}}
        @if(!empty($createRoute))
            <a href="{{ $createRoute }}" class="btn btn-success">Nueva asignación</a>
        @endif
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Buscar</label>
                    <input name="q" value="{{ $q ?? '' }}" class="form-control"
                           placeholder="Cédula / Nombre / Placa">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1">Rubro</label>
                    <select name="rubro_id" class="form-select">
                        <option value="">-- Todos --</option>
                        @foreach($rubros as $r)
                            <option value="{{ $r->id }}" {{ (string)($rubroId ?? '') === (string)$r->id ? 'selected' : '' }}>
                                {{ $r->code }} - {{ $r->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1">Estado</label>
                    <select name="status" class="form-select">
                        @php $st = $status ?? ''; @endphp
                        <option value="" {{ $st===''?'selected':'' }}>-- Todos --</option>
                        <option value="pending"   {{ $st==='pending'?'selected':'' }}>pending</option>
                        <option value="approved"  {{ $st==='approved'?'selected':'' }}>approved</option>
                        <option value="delivered" {{ $st==='delivered'?'selected':'' }}>delivered</option>
                        <option value="returned"  {{ $st==='returned'?'selected':'' }}>returned</option>
                        <option value="cancelled" {{ $st==='cancelled'?'selected':'' }}>cancelled</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Resultados</strong></div>
        <div class="card-body">

            @if($rows->count() === 0)
                <div class="alert alert-info mb-0">No hay registros con los filtros actuales.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width:120px;">Estado</th>
                                <th style="width:120px;">Placa</th>
                                <th>Persona</th>
                                <th style="width:220px;">Área</th>
                                <th style="width:260px;">Rubro</th>
                                <th style="width:180px;">Fechas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $a)
                                @php
                                    $badge = match($a->status){
                                        'approved','delivered' => 'bg-success',
                                        'returned' => 'bg-secondary',
                                        'pending' => 'bg-warning text-dark',
                                        default => 'bg-danger'
                                    };

                                    $person = $a->person ?? null;
                                    $fullName = $person
                                        ? trim(($person->first_name ?? '') . ' ' . ($person->first_last_name ?? '') . ' ' . ($person->second_last_name ?? ''))
                                        : 'N/D';
                                @endphp
                                <tr>
                                    <td><span class="badge {{ $badge }}">{{ $a->status }}</span></td>

                                    <td>
                                        {{ optional($a->motorcycle)->plate ?? '—' }}
                                    </td>

                                    <td>
                                        <div class="fw-semibold">{{ $fullName }}</div>
                                        <div class="text-muted small">{{ $person->document_number ?? '' }}</div>
                                    </td>

                                    <td>{{ optional($a->area)->name ?? '—' }}</td>

                                    <td>
                                        @if($a->budgetItem)
                                            <div class="fw-semibold">{{ $a->budgetItem->code }}</div>
                                            <div class="text-muted small">{{ $a->budgetItem->name }}</div>
                                        @else
                                            <span class="text-muted">Sin rubro</span>
                                        @endif
                                    </td>

                                    <td class="small">
                                        <div>Creada: {{ optional($a->created_at)->format('Y-m-d H:i') }}</div>
                                        <div>Entregada: {{ $a->delivered_at ? \Carbon\Carbon::parse($a->delivered_at)->format('Y-m-d H:i') : '—' }}</div>
                                        <div>Devuelta: {{ $a->returned_at ? \Carbon\Carbon::parse($a->returned_at)->format('Y-m-d H:i') : '—' }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $rows->links() }}
                </div>
            @endif

        </div>
    </div>

</div>
@endsection
