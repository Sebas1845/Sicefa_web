@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:520px">
  <h4>Verificar código</h4>

  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <form method="POST" action="{{ route('otp.login.verify') }}">
    @csrf
    <input type="hidden" name="document_number" value="{{ $document_number ?? old('document_number') }}">

    <div class="mb-3">
      <label class="form-label">Código</label>
      <input type="text" name="code" class="form-control" required autofocus>
    </div>

    <button class="btn btn-primary w-100" type="submit">Ingresar</button>
  </form>

  <form class="mt-3" method="POST" action="{{ route('otp.login.send') }}">
    @csrf
    <input type="hidden" name="document_number" value="{{ $document_number ?? old('document_number') }}">
    <button class="btn btn-outline-secondary w-100" type="submit">Reenviar código</button>
  </form>
</div>
@endsection
