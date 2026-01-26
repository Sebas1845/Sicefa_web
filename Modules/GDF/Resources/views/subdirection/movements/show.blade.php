@extends('gdf::layouts.masteruser')
@section('title','GDF | Movimiento')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-0">Movimiento #{{ $movement->id }}</h4>
        </div>
        <a href="{{ route('gdf.subdirection.movements.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Fecha</div>
                    <div class="fw-semibold">{{ optional($movement->created_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Tipo</div>
                    <div class="fw-semibold">{{ $movement->type ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Monto</div>
                    <div class="fw-semibold">$ {{ number_format($movement->amount ?? 0,0,',','.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Usuario</div>
                    <div class="fw-semibold">{{ $movement->user->email ?? ('ID '.$movement->user_id) }}</div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Área</div>
                    <div class="fw-semibold">{{ $movement->budget->area->name ?? '—' }}</div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Rubro</div>
                    <div class="fw-semibold">
                        {{ $movement->budget->budgetItem->code ?? '' }}
                        {{ $movement->budget->budgetItem->name ?? '—' }}
                    </div>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Descripción</div>
                    <div class="fw-semibold">{{ $movement->description ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
