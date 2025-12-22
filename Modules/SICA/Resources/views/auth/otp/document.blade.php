@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:520px">
  <h4>Ingresar con documento</h4>

  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <form method="POST" action="{{ route('otp.login.send') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label">Documento</label>
      <input type="text" name="document_number" class="form-control" value="{{ old('document_number') }}" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Enviar código</button>
  </form>
</div>
@endsection
