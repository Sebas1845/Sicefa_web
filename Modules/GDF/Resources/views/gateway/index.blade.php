@extends('gdf::layouts.public.master')

@section('title','GDF | Selección de rol')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-10">

        <div class="gdf-card p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1">Acceso por rol</h3>
                    <p class="text-white-50 mb-0">
                        Selecciona el área y el rol con el que vas a operar.
                    </p>
                </div>
                <span class="badge text-bg-success">{{ auth()->user()->nickname }}</span>
            </div>

            <hr class="border-white border-opacity-10 my-4">

            @php
                $isSubdirection = checkRol('gdf.subdirection');
                $isTreasury = checkRol('gdf.treasury');

                $isAcademicCoord = checkRol('gdf.academic_coordination');
                $isCampesenaCoord = checkRol('gdf.campesena_coordination');

                $isSupportAcademic = checkRol('gdf.support_academic');
                $isSupportCampesena = checkRol('gdf.support_campesena');
            @endphp

            {{-- Bloque Admin (Subdir/Tesorería) --}}
            @if($isSubdirection || $isTreasury)
                <div class="gdf-card p-4 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-shield-check text-success fs-4"></i>
                        <h5 class="mb-0 fw-semibold">Administración</h5>
                    </div>
                    <p class="text-white-50 mb-3">
                        Acceso a bandejas de aprobación, presupuesto y reportes.
                    </p>
                    <a class="btn btn-success" href="{{ route('gdf.view.admin') }}">
                        Entrar como Admin
                    </a>
                    <a class="btn btn-outline-light ms-2" href="{{ url('/gdf/panel') }}">
                        Panel Filament (opcional)
                    </a>
                </div>
            @endif

            {{-- Selección Área + Rol (Coordinación/Campesena y apoyos) --}}
            <form method="POST" action="{{ route('gdf.gateway.select') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label class="form-label text-white-50">Área</label>
                    <select name="area" class="form-select">
                        <option value="">Selecciona…</option>
                        @if($isAcademicCoord || $isSupportAcademic)
                            <option value="academic">Coordinación Académica</option>
                        @endif
                        @if($isCampesenaCoord || $isSupportCampesena)
                            <option value="campesena">Campesena</option>
                        @endif
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-white-50">Rol operativo</label>
                    <select name="role" class="form-select">
                        <option value="">Selecciona…</option>
                        @if($isAcademicCoord || $isCampesenaCoord)
                            <option value="coord">Coordinador</option>
                        @endif
                        @if($isSupportAcademic || $isSupportCampesena)
                            <option value="support">Apoyo</option>
                        @endif
                    </select>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-light" type="submit">
                        Continuar <i class="bi bi-arrow-right"></i>
                    </button>
                    <a class="btn btn-outline-light" href="{{ route('cefa.gdf.index') }}">Volver</a>
                </div>
            </form>

        </div>

    </div>
</div>

@endsection
