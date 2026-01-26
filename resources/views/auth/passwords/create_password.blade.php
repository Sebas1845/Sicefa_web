@extends('layouts.app')

@section('title','Crear contraseña')
<link href="{{ asset('css/login.css') }}" rel="stylesheet">

@section('content')
<div class="card">
    <div class="card-body">
        <h5 class="fw-bold mb-2">Crear contraseña</h5>
        <p class="text-muted small">
            Por seguridad, debes definir tu contraseña antes de continuar.
        </p>

        <form method="POST" action="{{ route('gdf.coordination.password.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Nueva contraseña</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>

            <button class="btn btn-primary w-100" type="submit">
                Guardar contraseña
            </button>
        </form>
    </div>
</div>
@endsection
