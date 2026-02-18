@extends('gth::layouts.master')

@section('title', 'Solicitar Certificado Contractual')

@section('content')
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            
            {{-- Header --}}
            <div class="card mb-4 shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-white py-4">
                    <h3 class="mb-0 font-weight-bold">
                        <i class="fas fa-certificate fa-lg"></i> Solicitar Certificado Contractual
                    </h3>
                    <p class="mb-0 mt-2 text-white-50">Centro de Formación Agroindustrial - SENA</p>
                </div>
            </div>

            {{-- Mensajes --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x mr-3"></i>
                        <div>
                            <strong>¡Éxito!</strong> {{ session('success') }}
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-times-circle fa-2x mr-3"></i>
                        <div>
                            <strong>Error:</strong> {{ session('error') }}
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif
            
            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x mr-3"></i>
                        <div>
                            {{ session('info') }}
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
                        <div>
                            {{ session('warning') }}
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle fa-2x mr-3 mt-1"></i>
                        <div>
                            <h5 class="font-weight-bold mb-3">Errores de validación:</h5>
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            {{-- Formulario --}}
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    
                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle fa-2x mr-3 mt-1"></i>
                            <div>
                                <strong class="d-block mb-2">Complete los datos para solicitar el certificado contractual.</strong>
                                <small>Los campos marcados con <span class="text-danger">(*)</span> son obligatorios. Recibirá respuesta en máximo 48 horas hábiles.</small>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('cefa.contractualcertificate.request') }}" method="POST" id="certificateForm">
                        @csrf

                        {{-- Sección 1: Identificación --}}
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-gradient-primary text-white">
                                <h5 class="mb-0 font-weight-bold">
                                    <i class="fas fa-id-card mr-2"></i> Datos de Identificación
                                </h5>
                            </div>
                            <div class="card-body bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="document_type" class="font-weight-semibold">
                                                Tipo de Documento <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-lg custom-select @error('document_type') is-invalid @enderror" 
                                                    name="document_type" id="document_type" required>
                                                <option value="">Seleccione...</option>
                                                <option value="Cédula de ciudadanía" {{ old('document_type') == 'Cédula de ciudadanía' ? 'selected' : '' }}>Cédula de ciudadanía</option>
                                                <option value="Tarjeta de identidad" {{ old('document_type') == 'Tarjeta de identidad' ? 'selected' : '' }}>Tarjeta de identidad</option>
                                                <option value="Cédula de extranjería" {{ old('document_type') == 'Cédula de extranjería' ? 'selected' : '' }}>Cédula de extranjería</option>
                                                <option value="Pasaporte" {{ old('document_type') == 'Pasaporte' ? 'selected' : '' }}>Pasaporte</option>
                                                <option value="Documento nacional de identidad" {{ old('document_type') == 'Documento nacional de identidad' ? 'selected' : '' }}>Documento nacional de identidad</option>
                                            </select>
                                            @error('document_type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="document_number" class="font-weight-semibold">
                                                Número de Documento <span class="text-danger">*</span>
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control form-control-lg @error('document_number') is-invalid @enderror" 
                                                id="document_number" 
                                                name="document_number" 
                                                placeholder="Ej: 1234567890"
                                                value="{{ old('document_number') }}" 
                                                required>
                                            @error('document_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">
                                                <i class="fas fa-exclamation-circle"></i> Solo números, sin puntos ni comas
                                            </small>
                                        </div>
                                    </div>

                            
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="contract_year" class="font-weight-semibold">
                                                Año del Contrato <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-lg custom-select @error('contract_year') is-invalid @enderror" 
                                                    id="contract_year" name="contract_year" required>
                                                <option value="">Seleccione...</option>
                                                @for($year = date('Y'); $year >= 1995; $year--)
                                                    <option value="{{ $year }}" {{ old('contract_year') == $year ? 'selected' : '' }}>
                                                        {{ $year }}
                                                    </option>
                                                @endfor
                                            </select>
                                            @error('contract_year')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sección 2: Nombres y Apellidos --}}
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-gradient-info text-white">
                                <h5 class="mb-0 font-weight-bold">
                                    <i class="fas fa-user mr-2"></i> Nombres y Apellidos
                                </h5>
                            </div>
                            <div class="card-body bg-light">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label class="font-weight-semibold mb-2">Nombre Completo (vista previa):</label>
                                        <div class="alert alert-primary border-0 shadow-sm mb-0">
                                            <h4 class="mb-0 font-weight-bold" id="full_name_display">
                                                <i class="fas fa-user-circle mr-2"></i> Se generará automáticamente
                                            </h4>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-info-circle"></i> El nombre se genera automáticamente según los campos que complete
                                        </small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="first_name" class="font-weight-semibold">
                                                Nombre completo <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg text-uppercase @error('first_name') is-invalid @enderror" 
                                                   id="first_name" name="first_name" 
                                                   placeholder="NOMBRE COMPLETO"
                                                   value="{{ old('first_name') }}" required>
                                            @error('first_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="first_last_name" class="font-weight-semibold">
                                                Primer Apellido <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg text-uppercase @error('first_last_name') is-invalid @enderror" 
                                                   id="first_last_name" name="first_last_name" 
                                                   placeholder="PRIMER APELLIDO"
                                                   value="{{ old('first_last_name') }}" required>
                                            @error('first_last_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="second_last_name" class="font-weight-semibold">
                                                Segundo Apellido <small class="text-muted">(Opcional)</small>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg text-uppercase @error('second_last_name') is-invalid @enderror" 
                                                   id="second_last_name" name="second_last_name" 
                                                   placeholder="SEGUNDO APELLIDO"
                                                   value="{{ old('second_last_name') }}">
                                            @error('second_last_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i> Dejar vacío si no tiene
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-light border shadow-sm">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-lightbulb fa-2x text-warning mr-3 mt-1"></i>
                                        <div>
                                            <strong class="d-block mb-2">Ejemplos válidos:</strong>
                                            <ul class="mb-0">
                                                <li><strong>Solo nombre y apellido:</strong> CARLOS MARTÍNEZ</li>
                                                <li><strong>Nombre y dos apellidos:</strong> JUAN PÉREZ GÓMEZ</li>
                                                <li><strong>Con segundo apellido:</strong> MARÍA RODRÍGUEZ LÓPEZ</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sección 3: Contacto --}}
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-gradient-success text-white">
                                <h5 class="mb-0 font-weight-bold">
                                    <i class="fas fa-address-book mr-2"></i> Información de Contacto
                                </h5>
                            </div>
                            <div class="card-body bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-md-0">
                                            <label for="personal_email" class="font-weight-semibold">
                                                <i class="fas fa-envelope mr-1"></i> Correo Electrónico <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" 
                                                   class="form-control form-control-lg @error('personal_email') is-invalid @enderror" 
                                                   id="personal_email" name="personal_email" 
                                                   placeholder="ejemplo@correo.com"
                                                   value="{{ old('personal_email') }}" required>
                                            @error('personal_email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">
                                                <i class="fas fa-bell"></i> Se enviará notificación a este correo
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label for="telephone1" class="font-weight-semibold">
                                                <i class="fas fa-mobile-alt mr-1"></i> Número de Celular <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg @error('telephone1') is-invalid @enderror" 
                                                   id="telephone1" name="telephone1" 
                                                   placeholder="3001234567" maxlength="10"
                                                   value="{{ old('telephone1') }}" required>
                                            @error('telephone1')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">
                                                <i class="fas fa-exclamation-circle"></i> 10 dígitos, sin espacios
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Botón de envío --}}
                        <div class="row mt-5">
                            <div class="col-12 text-center">
                                <button type="submit"
                                        class="btn btn-primary btn-lg btn-submit px-5 py-3 shadow-lg"
                                        id="btnSubmit">
                                    <i class="fas fa-paper-plane mr-2"></i> 
                                    <span>Enviar Solicitud de Certificado</span>
                                </button>
                                <div class="mt-3">
                                    <small class="d-block" id="validation_hint">
                                        <i class="fas fa-info-circle"></i> Complete todos los campos obligatorios (*)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Info cards --}}
            <div class="row mt-5 mb-5">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-0 shadow-sm h-100 info-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-info text-white mx-auto mb-3">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <h6 class="font-weight-bold mb-2">Respuesta Rápida</h6>
                            <p class="text-muted small mb-0">Máximo 48 horas hábiles</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-0 shadow-sm h-100 info-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-success text-white mx-auto mb-3">
                                <i class="fas fa-shield-alt fa-2x"></i>
                            </div>
                            <h6 class="font-weight-bold mb-2">Datos Protegidos</h6>
                            <p class="text-muted small mb-0">Tu información está segura</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 info-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-primary text-white mx-auto mb-3">
                                <i class="fas fa-certificate fa-2x"></i>
                            </div>
                            <h6 class="font-weight-bold mb-2">Certificado Válido</h6>
                            <p class="text-muted small mb-0">Con validez legal oficial</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    console.log('✅ JavaScript de validación cargado');

    // Función para actualizar el nombre completo en tiempo real
    function updateFullName() {
        const firstName = $('#first_name').val()?.trim().toUpperCase() || '';
        const firstLastName = $('#first_last_name').val()?.trim().toUpperCase() || '';
        const secondLastName = $('#second_last_name').val()?.trim().toUpperCase() || '';

        const nameParts = [];
        if (firstName) nameParts.push(firstName);
        if (firstLastName) nameParts.push(firstLastName);
        if (secondLastName) nameParts.push(secondLastName);

        $('#full_name_display').html(
            nameParts.length
                ? '<i class="fas fa-user-circle mr-2"></i> ' + nameParts.join(' ')
                : '<i class="fas fa-user-circle mr-2"></i> Se generará automáticamente'
        );
    }

    // Función para validar el formulario
    function validateForm() {
        // Campos OBLIGATORIOS (los que tienen *)
        const required = [
            'document_type',
            'document_number',
            'place_of_issue',
            'contract_year',
            'first_name',
            'first_last_name',
            'personal_email',
            'telephone1'
        ];

        let valid = true;

        // Validar que todos los campos requeridos tengan valor
        required.forEach(fieldName => {
            const field = $('[name="' + fieldName + '"]');
            const value = field.val()?.trim();
            
            if (!value || value === '') {
                valid = false;
            }
        });

        // Validar formato de correo electrónico
        const email = $('#personal_email').val()?.trim();
        if (email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                valid = false;
            }
        }

        // Validar teléfono (10 dígitos exactos)
        const phone = $('#telephone1').val()?.trim();
        if (phone) {
            if (!/^\d{10}$/.test(phone)) {
                valid = false;
            }
        }

        // Validar documento (solo números)
        const doc = $('#document_number').val()?.trim();
        if (doc) {
            if (!/^\d+$/.test(doc)) {
                valid = false;
            }
        }

        // Habilitar o deshabilitar el botón
        $('#btnSubmit').prop('disabled', !valid);
        
        // Actualizar mensaje de ayuda
        if (!valid) {
            $('#validation_hint').html(
                '<i class="fas fa-exclamation-triangle text-warning"></i> Complete todos los campos obligatorios (*) correctamente'
            ).removeClass('text-success').addClass('text-warning');
        } else {
            $('#validation_hint').html(
                '<i class="fas fa-check-circle text-success"></i> ¡Formulario completo y listo para enviar!'
            ).removeClass('text-warning').addClass('text-success');
        }

        console.log('Validación:', valid ? '✅ Válido' : '❌ Incompleto');
    }

    // Escuchar cambios en todos los campos
    $('input, select, textarea').on('input change blur keyup', function () {
        updateFullName();
        validateForm();
    });

    // Ejecutar validación al cargar la página
    updateFullName();
    validateForm();

    console.log('✅ Event listeners configurados correctamente');
});
</script>
@endpush

@push('styles')
<style>
    /* ============================================
       VARIABLES Y CONFIGURACIÓN GENERAL
       ============================================ */
    :root {
        --primary-blue: #004481;
        --primary-dark: #002d56;
        --light-blue: #e7f1ff;
        --accent-blue: #007bff;
        --success-green: #28a745;
        --info-cyan: #17a2b8;
        --warning-yellow: #ffc107;
        --danger-red: #dc3545;
    }

    /* ============================================
       GRADIENTES PERSONALIZADOS
       ============================================ */
    .bg-gradient-primary {
        background: linear-gradient(135deg, var(--primary-blue) 0%, #0056b3 100%) !important;
    }

    .bg-gradient-info {
        background: linear-gradient(135deg, var(--info-cyan) 0%, #138496 100%) !important;
    }

    .bg-gradient-success {
        background: linear-gradient(135deg, var(--success-green) 0%, #1e7e34 100%) !important;
    }

    /* ============================================
       CARDS Y CONTENEDORES
       ============================================ */
    .card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .card-header {
        border: none;
        padding: 1.25rem 1.5rem;
    }

    .card-body {
        padding: 2rem;
    }

    .shadow-lg {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
    }

    .shadow-sm {
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08) !important;
    }

    /* ============================================
       FORMULARIOS E INPUTS
       ============================================ */
    .form-control {
        border-radius: 10px;
        border: 2px solid #e0e0e0;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        background-color: #fff;
    }

    .form-control-lg {
        padding: 0.9rem 1.2rem;
        font-size: 1.05rem;
    }

    .custom-select {
        cursor: pointer;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.6rem;
        font-size: 0.95rem;
    }

    .font-weight-semibold {
        font-weight: 600;
    }

    /* ============================================
       ALERTAS PERSONALIZADAS
       ============================================ */
    .alert {
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        border: none;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
        color: #856404;
    }

    .alert-primary {
        background: linear-gradient(135deg, var(--light-blue) 0%, #cfe2ff 100%);
        color: var(--primary-blue);
        border: 2px dashed var(--accent-blue);
    }

    .alert-light {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }

    /* ============================================
       VISTA PREVIA DEL NOMBRE
       ============================================ */
    #full_name_display {
        font-size: 1.6rem;
        letter-spacing: 0.5px;
        font-weight: 700;
    }

    /* ============================================
       BOTÓN DE ENVÍO
       ============================================ */
    .btn-submit {
        background: linear-gradient(135deg, var(--primary-blue) 0%, #0056b3 100%);
        color: white;
        border: none;
        border-radius: 50px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-size: 1rem;
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-submit::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-submit:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-submit:hover:not(:disabled) {
        background: linear-gradient(135deg, #0056b3 0%, var(--primary-dark) 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 68, 129, 0.4);
    }

    .btn-submit:active:not(:disabled) {
        transform: translateY(-1px);
    }

    .btn-submit:disabled {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        cursor: not-allowed;
        opacity: 0.65;
    }

    .btn-submit span {
        position: relative;
        z-index: 1;
    }

    /* Animación de pulso para botón activo */
    .btn-submit:not(:disabled) {
        animation: pulseGlow 2.5s infinite;
    }

    @keyframes pulseGlow {
        0% {
            box-shadow: 0 0 0 0 rgba(0, 68, 129, 0.5);
        }
        70% {
            box-shadow: 0 0 0 20px rgba(0, 68, 129, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(0, 68, 129, 0);
        }
    }

    /* ============================================
       TARJETAS DE INFORMACIÓN (Info Cards)
       ============================================ */
    .info-card {
        transition: all 0.3s ease;
        border: none;
    }

    .info-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15) !important;
    }

    .icon-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .info-card:hover .icon-circle {
        transform: scale(1.1) rotate(5deg);
    }

    /* ============================================
       UTILIDADES Y HELPERS
       ============================================ */
    .text-uppercase {
        text-transform: uppercase;
    }

    .text-white-50 {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    /* ============================================
       RESPONSIVE - ADAPTACIÓN MÓVIL
       ============================================ */
    @media (max-width: 768px) {
        .card-body {
            padding: 1.5rem;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem !important;
            font-size: 0.95rem;
        }

        #full_name_display {
            font-size: 1.3rem;
        }

        .icon-circle {
            width: 60px;
            height: 60px;
        }

        .icon-circle i {
            font-size: 1.5rem !important;
        }
    }

    @media (max-width: 576px) {
        .form-control-lg {
            font-size: 1rem;
            padding: 0.75rem 1rem;
        }

        .card-header h3 {
            font-size: 1.3rem;
        }

        .card-header h5 {
            font-size: 1.1rem;
        }
    }

    /* ============================================
       MEJORAS DE ACCESIBILIDAD
       ============================================ */
    .form-control:focus,
    .btn:focus,
    .custom-select:focus {
        outline: none;
    }

    .invalid-feedback {
        display: block;
        font-size: 0.875rem;
        margin-top: 0.4rem;
    }

    /* ============================================
       ANIMACIONES ADICIONALES
       ============================================ */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeIn 0.5s ease-out;
    }
</style>
@endpush