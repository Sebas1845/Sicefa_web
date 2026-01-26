@extends('gdf::layouts.masteruser')
@section('title','GDF | Crear Presupuesto')

@section('content')
@php
    $money = function($v){
        $v = (float)($v ?? 0);
        return '$ ' . number_format($v, 0, ',', '.');
    };

    $selectedAreaId = old('area_id', $selectedAreaId ?? null);
    $selectedRubroId = old('budget_item_id', $selectedRubroId ?? null);

    $existingRubroIds = $existingRubroIds ?? collect();
@endphp

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">Presupuestos / Crear</div>
            <h4 class="fw-bold mb-1">Crear Presupuesto</h4>
            <div class="text-muted small">
                Evita duplicados por <strong>Año + Área + Rubro</strong>. Los valores se guardan en COP (sin puntos).
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('gdf.subdirection.budgets.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-bold mb-1">Revisa los campos</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        {{-- Form --}}
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('gdf.subdirection.budgets.store') }}" id="budgetCreateForm">
                        @csrf

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Año *</label>
                                <input type="number" name="year" class="form-control"
                                       value="{{ old('year', $year ?? now()->year) }}"
                                       min="2000" max="2100" required>
                                <div class="text-muted small mt-1">Define la vigencia del presupuesto.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Área *</label>
                                <select name="area_id" class="form-select" id="areaSelect" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach($areas as $a)
                                        <option value="{{ $a->id }}" {{ (string)$selectedAreaId === (string)$a->id ? 'selected' : '' }}>
                                            {{ $a->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-muted small mt-1">Se filtrarán rubros ya creados.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold">Rubro *</label>
                                <select name="budget_item_id" class="form-select" id="rubroSelect" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach($rubros as $r)
                                        @php
                                            $already = $existingRubroIds->contains($r->id);
                                        @endphp
                                        <option value="{{ $r->id }}"
                                            {{ (string)$selectedRubroId === (string)$r->id ? 'selected' : '' }}
                                            {{ $already ? 'disabled' : '' }}>
                                            {{ $r->name }}{{ $already ? ' (ya existe en este año/área)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-muted small mt-1">
                                    Si el rubro ya existe, debes editar el presupuesto existente.
                                </div>
                            </div>

                            {{-- Inputs visibles con formato --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Monto Inicial (COP) *</label>
                                <input type="text" class="form-control text-end" id="initialPretty"
                                       inputmode="numeric" autocomplete="off"
                                       placeholder="Ej: 900.000.000">
                                <input type="hidden" name="initial_amount" id="initialAmount"
                                       value="{{ old('initial_amount') }}">
                                <div class="text-muted small mt-1">Se guardará sin puntos ni símbolos.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Monto Actual (COP) *</label>
                                <input type="text" class="form-control text-end" id="currentPretty"
                                       inputmode="numeric" autocomplete="off"
                                       placeholder="Ej: 900.000.000">
                                <input type="hidden" name="current_amount" id="currentAmount"
                                       value="{{ old('current_amount') }}">
                                <div class="text-muted small mt-1">
                                    Sugerencia: normalmente inicia igual al monto inicial.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="active" value="1"
                                           id="active" {{ old('active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="active">
                                        Presupuesto activo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="text-muted small">
                                Tip: si necesitas referencia, revisa el panel de histórico a la derecha.
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('gdf.subdirection.budgets.index') }}" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                                <button class="btn btn-primary">
                                    Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Presupuestos ya creados para el área (año) --}}
            @if(($existing ?? collect())->count())
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white fw-bold">
                        Ya existen presupuestos en {{ $year }} para el área seleccionada
                    </div>
                    <div class="card-body">
                        <div class="text-muted small mb-2">
                            Esto ayuda a evitar duplicados. Si necesitas el mismo rubro, edita el existente.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rubro</th>
                                        <th class="text-end">Inicial</th>
                                        <th class="text-end">Actual</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($existing as $ex)
                                        @php
                                            $rname = optional($rubros->firstWhere('id',$ex->budget_item_id))->name ?? ('Rubro #' . $ex->budget_item_id);
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $rname }}</td>
                                            <td class="text-end">{{ $money($ex->initial_amount) }}</td>
                                            <td class="text-end">{{ $money($ex->current_amount) }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary"
                                                   href="{{ route('gdf.subdirection.budgets.edit', $ex->id) }}">
                                                    Editar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            @endif
        </div>

        {{-- Histórico / Promedios --}}
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">
                    Histórico / Referencias
                </div>
                <div class="card-body">
                    @if($avgByAreaRubro !== null)
                        <div class="p-3 border rounded mb-3">
                            <div class="text-muted small">Promedio (misma Área + Rubro)</div>
                            <div class="fw-bold fs-5">{{ $money($avgByAreaRubro) }}</div>
                        </div>
                    @endif

                    @if($avgByRubro !== null)
                        <div class="p-3 border rounded mb-3">
                            <div class="text-muted small">Promedio (mismo Rubro - global)</div>
                            <div class="fw-bold fs-5">{{ $money($avgByRubro) }}</div>
                        </div>
                    @endif

                    @if(($historyByAreaRubro ?? collect())->count())
                        <div class="mb-3">
                            <div class="fw-semibold">Últimos (Área + Rubro)</div>
                            <div class="text-muted small mb-2">Top 5 más recientes</div>
                            <ul class="list-group list-group-flush">
                                @foreach($historyByAreaRubro as $h)
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <div class="text-muted small">{{ $h->year }} · {{ optional($h->created_at)->format('Y-m-d') }}</div>
                                            <div class="fw-semibold">{{ $money($h->current_amount) }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(($historyByRubro ?? collect())->count())
                        <div>
                            <div class="fw-semibold">Últimos (Rubro global)</div>
                            <div class="text-muted small mb-2">Top 5 más recientes</div>
                            <ul class="list-group list-group-flush">
                                @foreach($historyByRubro as $h)
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <div class="text-muted small">
                                                {{ $h->year }} · Área #{{ $h->area_id }}
                                            </div>
                                            <div class="fw-semibold">{{ $money($h->current_amount) }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!(($historyByRubro ?? collect())->count()) && !(($historyByAreaRubro ?? collect())->count()))
                        <div class="text-muted small">
                            Selecciona Área y Rubro (y recarga) para ver histórico y promedio.
                        </div>
                    @endif
                </div>
            </div>

            <div class="text-muted small mt-2">
                Nota: para que el histórico se actualice al seleccionar, puedes recargar la página con querystring (ver JS).
            </div>
        </div>
    </div>

</div>

{{-- JS: Formato COP y recarga para histórico --}}
<script>
(function(){
    const form = document.getElementById('budgetCreateForm');
    const initialPretty = document.getElementById('initialPretty');
    const currentPretty = document.getElementById('currentPretty');

    const initialHidden = document.getElementById('initialAmount');
    const currentHidden = document.getElementById('currentAmount');

    // Convierte "900.000.000" -> "900000000"
    function toRawNumber(str){
        if(!str) return '';
        return String(str).replace(/[^\d]/g, '');
    }

    // Convierte "900000000" -> "900.000.000"
    function toCOPFormat(raw){
        raw = toRawNumber(raw);
        if(!raw) return '';
        return raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function sync(prettyEl, hiddenEl){
        const raw = toRawNumber(prettyEl.value);
        hiddenEl.value = raw ? Number(raw) : '';
        prettyEl.value = toCOPFormat(raw);
    }

    // Precargar desde old() si venía numérico
    const oldInitial = initialHidden.value;
    const oldCurrent = currentHidden.value;

    if(oldInitial) initialPretty.value = toCOPFormat(oldInitial);
    if(oldCurrent) currentPretty.value = toCOPFormat(oldCurrent);

    initialPretty.addEventListener('input', () => sync(initialPretty, initialHidden));
    currentPretty.addEventListener('input', () => sync(currentPretty, currentHidden));

    // Al enviar, asegurar valores raw
    form.addEventListener('submit', () => {
        sync(initialPretty, initialHidden);
        sync(currentPretty, currentHidden);
    });

    // Recarga para histórico: al cambiar área/rubro/año, recargar con query
    const areaSelect = document.getElementById('areaSelect');
    const rubroSelect = document.getElementById('rubroSelect');
    const yearInput = document.querySelector('input[name="year"]');

    function reloadWithQuery(){
        const year = yearInput.value || '';
        const area = areaSelect.value || '';
        const rubro = rubroSelect.value || '';
        const url = new URL(window.location.href);
        url.searchParams.set('year', year);
        if(area) url.searchParams.set('area_id', area); else url.searchParams.delete('area_id');
        if(rubro) url.searchParams.set('budget_item_id', rubro); else url.searchParams.delete('budget_item_id');
        window.location.href = url.toString();
    }

    // Solo recargar si el usuario cambió selects (para ver histórico y filtrar)
    areaSelect.addEventListener('change', reloadWithQuery);
    rubroSelect.addEventListener('change', reloadWithQuery);
    yearInput.addEventListener('change', reloadWithQuery);

})();
</script>
@endsection
