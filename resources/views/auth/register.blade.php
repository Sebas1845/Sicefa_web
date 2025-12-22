@extends('layouts.app')

@section('content')
<link href="{{ asset('css/login.css') }}" rel="stylesheet">

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-10 col-lg-8 col-xl-8">
            <div class="card d-flex mx-auto my-5">
                <div class="row">

                    {{-- LADO IZQUIERDO --}}
                    <div class="col-md-7 col-sm-12 c2 px-5 pt-5">

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
                                <h3 class="font-weight-bold">Solicitar Usuario</h3>
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

                            {{-- EMAIL SELECT (si existen correos) --}}
                            <div id="email_select_block" style="display:none;">
                                <label>Seleccione el correo</label>
                                <select name="email_selected" id="email_selected" class="form-control input">
                                </select>
                                <small class="text-muted d-block mt-1">
                                    El sistema enviará la información al correo seleccionado.
                                </small>
                                <br>
                            </div>

                            {{-- EMAIL INPUT (si NO existen correos) --}}
                            <div id="email_input_block" style="display:none;">
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
                    <div class="col-md-5 col-sm-12 c1 p-3">
                        <div id="hero" class="bg-transparent h-auto hero-img">
                            <img class="img-fluid animated" src="{{ asset('general/images/Daco_227767.png') }}" alt="">
                        </div>
                        <div class="row justify-content-center">
                            <div class="w-75 mx-md-5 mx-1 mb-5 mt-4 px-2">
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

{{-- jQuery (si el layout no lo trae) --}}
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

<script>
(function waitForJQuery() {
    if (!window.jQuery) { setTimeout(waitForJQuery, 50); return; }

    $(function () {
        const $doc = $('#document_number');
        const $role = $('#role');
        const $name = $('#name');
        const $rol  = $('#rol');
        const $btn  = $('#solicitar');

        const $emailSelectBlock = $('#email_select_block');
        const $emailSelected = $('#email_selected');

        const $emailInputBlock = $('#email_input_block');
        const $email = $('#email');
        const $emailHelp = $('#email_help');
        const $emailError = $('#email_error');

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

            // ocultar bloques email
            $emailSelectBlock.hide();
            $emailSelected.empty().prop('required', false);

            $emailInputBlock.hide();
            $email.val('').prop('required', false);
            $emailHelp.text('');
            $emailError.hide().text('');
        }

        function evaluateSubmit() {
            const roleVal = ($role.val() || '').trim();
            const validRole = (roleVal === 'Instructor' || roleVal === 'Aprendiz');

            // si está visible el select => required
            if ($emailSelectBlock.is(':visible')) {
                const val = ($emailSelected.val() || '').trim();
                $btn.prop('disabled', !(validRole && val.length > 0));
                return;
            }

            // si está visible input => validar
            if ($emailInputBlock.is(':visible')) {
                const val = ($email.val() || '').trim();
                const ok = isValidEmail(val);
                $btn.prop('disabled', !(validRole && ok));

                if (val.length > 0 && !ok) $emailError.show().text('Ingresa un correo válido.');
                else $emailError.hide().text('');
                return;
            }

            // sin email visible (no debería pasar)
            $btn.prop('disabled', true);
        }

        // eventos
        $(document).on('change input', '#email_selected, #email', evaluateSubmit);

        function fetchPerson(documentNumber) {
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

                    // === AQUI: correos ===
                    const emails = response.emails || [];

                    if (emails.length > 0) {
                        // mostrar select
                        $emailSelectBlock.show();
                        $emailSelected.empty();

                        emails.forEach(function(e){
                            $emailSelected.append(
                                $('<option>', { value: e.value, text: `${e.value} (${e.type})` })
                            );
                        });

                        $emailSelected.prop('required', true);

                    } else {
                        // mostrar input manual
                        $emailInputBlock.show();
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
@endsection
