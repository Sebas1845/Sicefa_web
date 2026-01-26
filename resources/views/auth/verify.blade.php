@extends('layouts.app')

@section('content')
<link href="{{ asset('css/login.css') }}" rel="stylesheet">

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-8">
            <div class="card d-flex mx-auto my-5">
                <div class="row">

                    {{-- LADO IZQUIERDO --}}
                    <div class="col-md-7 col-sm-12 col-xs-12 c2 px-5 pt-5">
                        <div class="row">
                            <a href="{{ route('login', ['redirect' => url()->current()]) }}">
                                <i class="fa fa-arrow-left fa-2x"></i>
                            </a>
                        </div>

                        <br>

                        <div class="row mb-3 m-3">
                            <a href="{{ route('cefa.welcome') }}">
                                <img src="{{ asset('general/images/Group1.png') }}" width="40%" height="auto" alt="">
                            </a>
                        </div>

                        {{-- FLASH / ERRORES --}}
                        @if (session('message'))
                            <div class="alert alert-{{ session('typealert', 'info') }} mt-2">
                                {{ session('message') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger mt-2">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="alert alert-success mt-2">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger mt-2">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex">
                            <h3 class="font-weight-bold">Verificar código</h3>
                        </div>

                        <p class="text-muted">
                            Ingresa el código enviado a tu correo personal.
                        </p>

                        {{-- FORM VERIFICAR OTP --}}
                        <form method="POST" action="{{ route('otp.login.verify') }}">
                            @csrf

                            <input type="hidden" name="document_number" value="{{ $document_number ?? old('document_number') }}">

                            <label>Código</label>
                            <input
                                type="text"
                                name="code"
                                class="form-control input"
                                placeholder="Código"
                                value="{{ old('code') }}"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required
                            >

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary bt">
                                    Ingresar
                                </button>
                            </div>
                        </form>

                        {{-- REENVIAR OTP --}}
                        <form method="POST" action="{{ route('otp.login.send') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="document_number" value="{{ $document_number ?? old('document_number') }}">
                            <div class="text-center">
                                <button type="submit" class="btn btn-outline-secondary bt">
                                    Reenviar código
                                </button>
                            </div>
                        </form>

                    </div>

                    {{-- LADO DERECHO --}}
                    <div class="col-md-5 col-sm-12 col-xs-12 c1 p-3">
                        <div id="hero" class="bg-transparent h-auto order-1 order-lg-2 hero-img" data-aos="zoom-in" data-aos-delay="200">
                            <img class="img-fluid animated" src="{{ asset('general/images/Daco_227767.png') }}" alt="">
                        </div>
                        <div class="row justify-content-center">
                            <div class="w-75 mx-md-5 mx-1 mx-sm-2 mb-5 mt-4 px-sm-5 px-md-2 px-xl-1 px-2">
                                <h1 class="wlcm">Verificar código</h1>
                                <span class="sp1">
                                    <span class="px-3 bg-danger rounded-pill"></span>
                                    <span class="ml-2 px-1 rounded-circle"></span>
                                    <span class="ml-2 px-1 rounded-circle"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>{{-- row --}}
            </div>{{-- card --}}
        </div>
    </div>
</div>
@endsection
