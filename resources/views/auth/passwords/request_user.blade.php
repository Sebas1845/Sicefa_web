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

                        {{-- ALERTA AJAX DETALLADA --}}
                        <div id="ajax_error_alert" class="alert alert-danger mt-2" style="display:none;"></div>

                        {{-- FORM --}}
                        {{-- CAMBIO 1: action ahora apunta al controlador OTP unificado --}}
                        <form method="POST" action="{{ route('otp.login.send') }}" id="register-form">
                            @csrf

                            <div class="d-flex">
                                <h3 class="font-weight-bold">Solicitar código</h3>
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

                            {{-- EMAIL: SOLO LECTURA (no se permite escribir) --}}
                            <div id="email_personal_block" style="display:none;">
                                <label>Correo personal (registrado)</label>
                                <input
                                    type="email"
                                    id="email_personal"
                                    class="form-control input"
                                    readonly
                                >
                                <small class="text-muted d-block mt-1">
                                    El sistema usará este correo para enviarte el código.
                                </small>
                                <br>
                            </div>

                            <div class="text-center">
                                <button
                                    type="submit"
                                    class="btn btn-primary bt"
                                    id="solicitar"
                                    disabled
                                    onclick="this.disabled=true; this.form.submit();"
                                >
                                    Enviar código
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
                                <h1 class="wlcm">Solicitar código</h1>
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
    if (!window.jQuery) { setTimeout(waitForJQuery, 50); return; }

    $(function () {
        const $doc   = $('#document_number');
        const $role  = $('#role');
        const $name  = $('#name');
        const $rol   = $('#rol');
        const $btn   = $('#solicitar');

        const $ajaxAlert = $('#ajax_error_alert');

        const $emailBlock = $('#email_personal_block');
        const $emailInput = $('#email_personal');

        let debounceTimer = null;
        let ajaxReq = null;

        function showAjaxError(message, hint = '') {
            let html = `<strong>${message}</strong>`;
            if (hint) html += `<br><small>${hint}</small>`;
            $ajaxAlert.html(html).show();
        }

        function clearAjaxError() {
            $ajaxAlert.hide().html('');
        }

        function resetUI() {
            $name.text('');
            $rol.text('');
            $btn.prop('disabled', true);
            $role.val('');

            $emailInput.val('');
            $emailBlock.hide();

            clearAjaxError();
        }

        function enableIfReady() {
            const roleVal  = ($role.val() || '').trim();
            const emailVal = ($emailInput.val() || '').trim();

            // CAMBIO 2: ahora solo permitimos Aprendiz (según tu OtpAuthController)
            const ok = (roleVal === 'Aprendiz') && emailVal.length > 0;

            $btn.prop('disabled', !ok);
        }

        function fetchPerson(documentNumber) {
            if (ajaxReq && ajaxReq.readyState !== 4) ajaxReq.abort();

            resetUI();
            $rol.text('Consultando...');

            ajaxReq = $.ajax({
                // CAMBIO 3: mantenemos el mismo endpoint AJAX de búsqueda (no es OTP)
                url: '{{ route('cefa.user.register.searchperson') }}',
                method: 'GET',
                data: { document_number: documentNumber },

                success: function (response) {
                    resetUI();

                    if (!response) {
                        showAjaxError('Respuesta vacía del servidor.', 'Revisa consola / Network.');
                        console.log('EMPTY RESPONSE', response);
                        return;
                    }

                    if (response.ok !== true) {
                        const msg  = response.message || response.error || 'No fue posible continuar.';
                        const hint = response.hint || '';
                        showAjaxError(msg, hint);

                        if (response.person) {
                            $name.text(
                                (response.person.first_name || '') + ' ' +
                                (response.person.first_last_name || '') + ' ' +
                                (response.person.second_last_name || '')
                            );
                        }
                        if (response.rol) {
                            $rol.text('Rol : ' + response.rol);
                            $role.val(response.rol);
                        }
                        return;
                    }

                    if (!response.person) {
                        showAjaxError('Respuesta inválida: falta person.', 'Revisa el endpoint.');
                        console.log('INVALID RESPONSE', response);
                        return;
                    }

                    $name.text(
                        (response.person.first_name || '') + ' ' +
                        (response.person.first_last_name || '') + ' ' +
                        (response.person.second_last_name || '')
                    );

                    $rol.text('Rol : ' + (response.rol || ''));
                    $role.val(response.rol || '');

                    const personalEmail = (
                        (response.personal_email ?? '') ||
                        (response.person.personal_email ?? '')
                    ).toString().trim();

                    if (personalEmail.length > 0) {
                        $emailBlock.show();
                        $emailInput.val(personalEmail);
                        enableIfReady();
                    } else {
                        showAjaxError(
                            'No tienes correo personal registrado (personal_email).',
                            'Contacta a Coordinación Académica para actualizar tu correo.'
                        );
                        $btn.prop('disabled', true);
                    }
                },

                error: function (xhr) {
                    if (xhr.statusText === 'abort') return;

                    resetUI();

                    console.error('AJAX ERROR', {
                        status: xhr.status,
                        responseText: xhr.responseText,
                        responseJSON: xhr.responseJSON
                    });

                    if (xhr.responseJSON) {
                        const msg  = xhr.responseJSON.message || xhr.responseJSON.error || 'Error consultando el documento.';
                        const hint = xhr.responseJSON.hint || '';
                        showAjaxError(msg, hint);
                    } else {
                        showAjaxError('Error consultando el documento.', 'Intenta de nuevo.');
                    }
                }
            });
        }

        $doc.on('input', function () {
            const val = ($(this).val() || '').toString().trim();

            if (!val || val.length < 6) {
                resetUI();
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchPerson(val), 300);
        });

        resetUI();
    });
})();
</script>
```
