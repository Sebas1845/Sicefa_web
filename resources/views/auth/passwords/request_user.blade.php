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

                        @if ($errors->any())
                            <div class="alert alert-danger mt-2">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- FORM --}}
                        <form method="POST" action="{{ route('cefa.user.register.store') }}" id="register-form">
                            @csrf

                            <div class="d-flex">
                                <h3 class="font-weight-bold">Registrarse</h3>
                            </div>

                            <label>Número de documento</label>
                            <input
                                type="text"
                                name="document_number"
                                id="document_number"
                                class="form-control input"
                                placeholder="Documento"
                                value="{{ old('document_number') }}"
                                inputmode="numeric"
                                autocomplete="off"
                                required
                            >

                            <input type="hidden" name="role" id="role" value="{{ old('role') }}">

                            <div class="row">
                                <div class="col-sm-12">
                                    <br>
                                    <div id="name" class="text-center text-bold"></div>
                                    <br>
                                    <div id="rol" class="text-center text-bold"></div>
                                    <br>
                                </div>
                            </div>

                            {{-- EMAIL (solo cuando haga falta) --}}
                            <div id="email_block" style="display:none;">
                                <label>Correo electrónico</label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control input"
                                    placeholder="correo@ejemplo.com"
                                    value="{{ old('email') }}"
                                    autocomplete="email"
                                >
                                <small id="email_help" class="text-muted d-block mt-1"></small>
                                <div id="email_error" class="text-danger mt-1" style="display:none;"></div>
                                <br>
                            </div>

                            <div class="text-center">
                                <button
                                    type="submit"
                                    class="btn btn-primary bt"
                                    id="solicitar"
                                    disabled
                                >
                                    Solicitar Usuario
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
                                <h1 class="wlcm">Solicitar Usuario</h1>
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

{{-- =========================================
     SCRIPTS (sin @push para evitar problemas)
========================================= --}}

{{-- 1) Cargar jQuery si el layout no lo tiene --}}
<script>
(function() {
    if (window.jQuery) return;
    var s = document.createElement('script');
    s.src = "https://code.jquery.com/jquery-3.7.1.min.js";
    s.integrity = "sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=";
    s.crossOrigin = "anonymous";
    document.head.appendChild(s);
})();
</script>

{{-- 2) Script principal --}}
<script>
(function waitForJQuery() {
    if (!window.jQuery) {
        setTimeout(waitForJQuery, 50);
        return;
    }

    $(function () {
        const $doc = $('#document_number');
        const $role = $('#role');
        const $name = $('#name');
        const $rol  = $('#rol');
        const $btn  = $('#solicitar');

        const $emailBlock = $('#email_block');
        const $email = $('#email');
        const $emailHelp = $('#email_help');
        const $emailError = $('#email_error');

        let lastQuery = null;
        let debounceTimer = null;
        let ajaxReq = null;

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function resetUI() {
            $name.text('');
            $rol.text('');
            $btn.prop('disabled', true);
            $role.val('');

            $email.val('').prop('required', false);
            $emailBlock.hide();
            $emailHelp.text('');
            $emailError.hide().text('');
        }

        function evaluateSubmit() {
            const roleVal = ($role.val() || '').trim();
            const validRole = (roleVal === 'Instructor' || roleVal === 'Aprendiz');

            const emailVisible = $emailBlock.is(':visible');
            const emailVal = ($email.val() || '').trim();
            const validEmail = !emailVisible || isValidEmail(emailVal);

            $btn.prop('disabled', !(validRole && validEmail));

            if (emailVisible) {
                if (emailVal.length > 0 && !isValidEmail(emailVal)) {
                    $emailError.show().text('Ingresa un correo válido.');
                } else {
                    $emailError.hide().text('');
                }
            } else {
                $emailError.hide().text('');
            }
        }

        $email.on('input', evaluateSubmit);

        function fetchPerson(documentNumber) {
            if (documentNumber === lastQuery) return;
            lastQuery = documentNumber;

            if (ajaxReq && ajaxReq.readyState !== 4) ajaxReq.abort();

            $rol.text('Consultando...');
            $btn.prop('disabled', true);

            ajaxReq = $.ajax({
                url: '{{ route('cefa.user.register.searchperson') }}',
                method: 'GET',
                data: { document_number: documentNumber },
                success: function (response) {
                    resetUI();

                    if (response.error) {
                        $name.text(response.error);
                        return;
                    }

                    if (!response.person || !response.rol) {
                        $name.text('No se encontró información.');
                        return;
                    }

                    $name.text(
                        (response.person.first_name || '') + ' ' +
                        (response.person.first_last_name || '') + ' ' +
                        (response.person.second_last_name || '')
                    );

                    if (response.rol === 'Instructor') {
                        $rol.text('Rol : Instructor');
                    } else if (response.rol === 'Aprendiz') {
                        $rol.text('Rol : Aprendiz');
                    } else {
                        $rol.text('No eres instructor o aprendiz');
                        return;
                    }

                    $role.val(response.rol);

                    const personEmail = (response.person.email || '').toString().trim();
                    if (personEmail) {
                        $email.val(personEmail);
                        $emailBlock.hide();
                        $email.prop('required', false);
                    } else {
                        $emailBlock.show();
                        $emailHelp.text('No hay correo registrado. Ingresa uno para crear el usuario.');
                        $email.prop('required', true);
                    }

                    evaluateSubmit();
                },
                error: function (xhr) {
                    if (xhr.statusText === 'abort') return;
                    resetUI();
                    $name.text('Error consultando el documento. Intenta de nuevo.');
                }
            });
        }

        $doc.on('input', function () {
            const val = ($(this).val() || '').toString().trim();

            // evita consultar con pocos dígitos
            if (!val || val.length < 6) {
                lastQuery = null;
                resetUI();
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchPerson(val), 300);
        });

        // estado inicial
        resetUI();
    });
})();
</script>
