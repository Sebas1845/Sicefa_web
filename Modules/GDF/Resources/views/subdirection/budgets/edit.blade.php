@extends('gdf::layouts.masteruser')
@section('title','GDF | Editar Presupuesto')

@section('content')
<div class="container py-4">
<h4 class="fw-bold mb-3">Editar Presupuesto</h4>

<form method="POST" action="{{ route('gdf.subdirection.budgets.update',$budget->id) }}" class="card p-4 shadow-sm">
@csrf @method('PUT')

<div class="row g-3">
    <div class="col-md-2">
        <label>Año</label>
        <input type="number" name="year" value="{{ $budget->year }}" class="form-control">
    </div>

    <div class="col-md-5">
        <label>Área</label>
        <select name="area_id" class="form-select">
            @foreach($areas as $a)
                <option value="{{ $a->id }}" {{ $budget->area_id==$a->id?'selected':'' }}>{{ $a->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label>Rubro</label>
        <select name="budget_item_id" class="form-select">
            @foreach($rubros as $r)
                <option value="{{ $r->id }}" {{ $budget->budget_item_id==$r->id?'selected':'' }}>{{ $r->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label>Monto Inicial</label>
        <input type="number" name="initial_amount" value="{{ $budget->initial_amount }}" class="form-control">
    </div>

    <div class="col-md-6">
        <label>Monto Actual</label>
        <input type="number" name="current_amount" value="{{ $budget->current_amount }}" class="form-control">
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <a href="{{ route('gdf.subdirection.budgets.index') }}" class="btn btn-outline-secondary">Volver</a>
    <button class="btn btn-primary">Guardar</button>
</div>
</form>
</div>
@endsection
