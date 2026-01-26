@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Seleccionar contexto')

@section('content')
@php
    $currentRole = $ctx['role'] ?? null;
    $currentArea = $ctx['area'] ?? null;

    $areaLabel = fn($a) => match($a) {
        'academic'  => 'Académica',
        'campesena' => 'Campesena',
        default     => '—',
    };
@endphp

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">

            <div class="gdf-card p-4">

                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="gdf-chip"><i class="bi bi-grid-1x2"></i> Contexto operativo</span>
                            <span class="gdf-chip"><i class="bi bi-shield-check"></i> Validado por roles</span>
                        </div>

                        <h3 class="fw-bold mb-1">Seleccionar contexto</h3>
                        <div class="text-white-50">Elige una opción y, si aplica, selecciona el área.</div>
                    </div>

                    <div class="text-end d-flex gap-2">
                        <form method="POST" action="{{ route('gdf.gateway.clear') }}">
                            @csrf
                            <button type="submit" class="btn btn-gdf-ghost btn-sm">
                                <i class="bi bi-trash"></i> Limpiar contexto
                            </button>
                        </form>

                        <a href="{{ route('gdf.index') }}" class="btn btn-gdf-ghost btn-sm">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </div>
                </div>

                <hr class="border-white border-opacity-10 my-4">

                @if(session('error'))   <div class="alert alert-danger mb-3">{{ session('error') }}</div> @endif
                @if(session('warning')) <div class="alert alert-warning mb-3">{{ session('warning') }}</div> @endif
                @if(session('success')) <div class="alert alert-success mb-3">{{ session('success') }}</div> @endif

                <div class="gdf-card p-3 mb-4" style="background:rgba(255,255,255,.03);">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="text-white-50 small">Contexto actual</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-dark border border-white border-opacity-10">
                                <i class="bi bi-person-badge"></i> {{ $currentRole ? strtoupper($currentRole) : '—' }}
                            </span>
                            <span class="badge text-bg-dark border border-white border-opacity-10">
                                <i class="bi bi-diagram-3"></i> {{ $areaLabel($currentArea) }}
                            </span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('gdf.gateway.select') }}" id="ctx-form">
                    @csrf

                    <input type="hidden" name="ctx" id="ctx-input" value="">
                    <input type="hidden" name="area" id="area-input" value="">

                    <div class="row g-3">
                        @foreach($options as $opt)
                            @php
                                $requiresArea = !empty($opt['areas']);
                                $areas = $opt['areas'] ?? [];
                            @endphp

                            <div class="col-md-6">
                                <button
                                    type="button"
                                    class="gdf-card p-4 w-100 text-start ctx-card"
                                    style="background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08);"
                                    data-ctx="{{ $opt['key'] }}"
                                    data-requires-area="{{ $requiresArea ? '1' : '0' }}"
                                    data-areas="{{ implode(',', $areas) }}"
                                >
                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="rounded-3 d-flex align-items-center justify-content-center"
                                                 style="width:46px;height:46px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.25);">
                                                <i class="bi {{ $opt['icon'] ?? 'bi-gear' }} fs-4 text-white-50"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold fs-5">{{ $opt['label'] }}</div>
                                                <div class="text-white-50 small">{{ $opt['desc'] ?? '' }}</div>

                                                @if($requiresArea)
                                                    <div class="mt-2 text-white-50 small">
                                                        <i class="bi bi-diagram-3"></i>
                                                        Áreas habilitadas:
                                                        <span class="fw-semibold">
                                                            {{ collect($areas)->map($areaLabel)->implode(' / ') }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="text-white-50"><i class="bi bi-chevron-right"></i></div>
                                    </div>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    {{-- ÁREA --}}
                    <div class="mt-4" id="area-box" style="display:none;">
                        <div class="gdf-card p-4" style="background:rgba(255,255,255,.03);">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">Seleccionar área</div>
                                    <div class="text-white-50 small">Aplica cuando el contexto lo requiere.</div>
                                </div>
                                <span class="badge text-bg-dark border border-white border-opacity-10" id="selected-ctx-badge">—</span>
                            </div>

                            <div class="row g-3 mt-2" id="area-options"></div>
                        </div>
                    </div>

                    <div class="mt-4 d-grid">
                        <button class="btn btn-gdf-primary" type="submit" id="submit-btn" disabled>
                            <i class="bi bi-check2-circle"></i> Continuar
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctxInput = document.getElementById('ctx-input');
    const areaInput = document.getElementById('area-input');
    const submitBtn = document.getElementById('submit-btn');

    const areaBox = document.getElementById('area-box');
    const areaOptions = document.getElementById('area-options');
    const selectedCtxBadge = document.getElementById('selected-ctx-badge');

    const cards = Array.from(document.querySelectorAll('.ctx-card'));

    const labelArea = (a) => (a === 'academic' ? 'Académica' : (a === 'campesena' ? 'Campesena' : '—'));

    const setActiveCard = (active) => {
        cards.forEach(btn => {
            const isActive = btn === active;
            btn.style.border = isActive ? '1px solid rgba(59,130,246,.55)' : '1px solid rgba(255,255,255,.08)';
            btn.style.background = isActive ? 'rgba(59,130,246,.10)' : 'rgba(255,255,255,.03)';
        });
    };

    const resetArea = () => {
        areaInput.value = '';
        submitBtn.disabled = true;
        areaBox.style.display = 'none';
        areaOptions.innerHTML = '';
    };

    const renderAreas = (areas) => {
        areaOptions.innerHTML = '';
        areas.forEach(a => {
            const col = document.createElement('div');
            col.className = 'col-md-6';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gdf-card p-3 w-100 text-start';
            btn.style.background = 'rgba(255,255,255,.03)';
            btn.style.border = '1px solid rgba(255,255,255,.08)';
            btn.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">${labelArea(a)}</div>
                    <i class="bi bi-chevron-right text-white-50"></i>
                </div>
                <div class="text-white-50 small mt-1">Operación por área: ${labelArea(a)}</div>
            `;

            btn.addEventListener('click', () => {
                areaInput.value = a;

                Array.from(areaOptions.querySelectorAll('button')).forEach(b => {
                    b.style.border = (b === btn) ? '1px solid rgba(34,197,94,.55)' : '1px solid rgba(255,255,255,.08)';
                    b.style.background = (b === btn) ? 'rgba(34,197,94,.10)' : 'rgba(255,255,255,.03)';
                });

                submitBtn.disabled = false;
            });

            col.appendChild(btn);
            areaOptions.appendChild(col);
        });
    };

    cards.forEach(btn => {
        btn.addEventListener('click', () => {
            setActiveCard(btn);

            const ctx = btn.getAttribute('data-ctx');
            const needsArea = btn.getAttribute('data-requires-area') === '1';
            const areasRaw = btn.getAttribute('data-areas') || '';
            const areas = areasRaw ? areasRaw.split(',').filter(Boolean) : [];

            ctxInput.value = ctx;
            selectedCtxBadge.textContent = ctx.toUpperCase();

            resetArea();

            if (needsArea) {
                areaBox.style.display = 'block';
                renderAreas(areas);
            } else {
                // contextos sin área (tesorería/subdirección)
                submitBtn.disabled = false;
            }
        });
    });
});
</script>
@endsection
