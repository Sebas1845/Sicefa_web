@extends('layouts.app')

@section('content')
<link href="{{ asset('css/login.css') }}" rel="stylesheet">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">

          <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">Cambiar contraseña</h4>
            <p class="text-muted mb-0">
              Por seguridad, debes definir una contraseña nueva para continuar.
            </p>
          </div>

          {{-- MENSAJES --}}
          @if(session('error'))
            <div class="alert alert-danger">
              {{ session('error') }}
            </div>
          @endif

          @if(session('success'))
            <div class="alert alert-success">
              {{ session('success') }}
            </div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              {{ $errors->first() }}
            </div>
          @endif

          <form method="POST" action="{{ route('otp.login.password.save') }}">
            @csrf

            {{-- NUEVA CONTRASEÑA --}}
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Nueva contraseña
              </label>
              <input
                type="password"
                name="new_password"
                class="form-control @error('new_password') is-invalid @enderror"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres"
              >
              @error('new_password')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
              @enderror
            </div>

            {{-- CONFIRMAR CONTRASEÑA --}}
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Confirmar contraseña
              </label>
              <input
                type="password"
                name="new_password_confirmation"
                class="form-control @error('new_password_confirmation') is-invalid @enderror"
                required
                autocomplete="new-password"
                placeholder="Repite la contraseña"
              >
            </div>

            {{-- INFO SEGURIDAD --}}
            <div class="alert alert-info small mb-4">
              <strong>Recomendación:</strong>
              Usa una contraseña que no hayas utilizado antes y evita datos personales
              como tu documento o fecha de nacimiento.
            </div>

            <button class="btn btn-primary w-100" type="submit">
              Guardar contraseña
            </button>
          </form>

        </div>
      </div>

    </div>
  </div>
</div>
@endsection
