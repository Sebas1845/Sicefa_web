@extends('gth::layouts.master')

@section('content')

<style> 
.payment-card {
    cursor: pointer;
    transition: all 0.25s ease-in-out;
    background-color: #fff;
}

.btn-check:checked + .payment-card {
    border: 2px solid #0d6efd;
    background-color: #f0f6ff;
}

.btn-check:checked + .payment-card i {
    transform: scale(1.1);
}

.payment-card:hover {
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
}

.autocomplete-indicator {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    padding: 8px 12px;
    margin-top: 5px;
}
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        {{ trans('gth::menu.Contractual Certificate') }}
                    </h4>
                </div>

                <div class="card-body">
                    {{-- Mensajes --}}
                    @if(session('message'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> {{ session('message') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    {{-- Formulario de búsqueda --}}
                    <form method="POST" action="{{ route('cefa.contractualcertificate.search') }}">
                        @csrf

                        <div class="form-group">
                            <label for="document">
                                {{ trans('gth::menu.Document Number') }}
                            </label>

                            <input type="text" class="form-control @error('document') is-invalid @enderror"
                                id="document" name="document"
                                placeholder="{{ trans('gth::menu.Enter Document Number') }}"
                                value="{{ old('document') }}" required autofocus>

                            @error('document')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror

                            <small class="form-text text-muted">
                                Ingrese el número de documento sin puntos ni espacios.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            {{ trans('gth::menu.Search') }}
                        </button>
                    </form>

                    {{-- Resultados --}}
                    @if(isset($contractors) && $contractors->isNotEmpty())
                        <hr class="my-4">
                        <h5 class="mb-3">
                            {{ trans('gth::menu.Search Results') }}
                        </h5>

                        @foreach($contractors as $contractor)
                            @php
                                // Buscar solicitud específica para este año de contrato
                                $yearRequest = isset($requestsByYear) ? 
                                               $requestsByYear->get($contractor->contract_year) : 
                                               null;
                                $hasRequest = $yearRequest !== null;
                            @endphp

                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-contract"></i>
                                        Contrato {{ $contractor->contract_number }} 
                                        @if($hasRequest)
                                            <span class="badge badge-success float-right">
                                                <i class="fas fa-check-circle"></i> Con Solicitud
                                            </span>
                                        @endif
                                    </h5>
                                </div>
                                <div class="card-body">
                                    {{-- Información del Contratista --}}
                                    <h6 class="text-primary"><strong>Información del Contratista</strong></h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Nombre:</strong>
                                                {{ $contractor->person->first_name }}
                                                {{ $contractor->person->first_last_name }}
                                                {{ $contractor->person->second_last_name }}
                                            </p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-1"><strong>Documento:</strong>
                                                {{ $contractor->person->document_number }}
                                            </p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-1">
                                                <strong>Género:</strong>
                                                {{ $contractor->person->gender ?? 'No registrado' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Lugar de Expedición:</strong>
                                                {{ $contractor->person->place_of_issue ?? 'No registrado' }}
                                            </p>
                                        </div>
                                    </div>

                                    <hr>

                                    {{-- Información del Supervisor --}}
                                    <h6 class="text-primary"><strong>Información del Supervisor</strong></h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Nombre:</strong>
                                                @if($contractor->supervisor)
                                                    {{ $contractor->supervisor->first_name }}
                                                    {{ $contractor->supervisor->first_last_name }}
                                                    {{ $contractor->supervisor->second_last_name }}
                                                @else
                                                    <span class="text-muted">No asignado</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Documento:</strong>
                                                {{ $contractor->supervisor->document_number ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>

                                    <hr>

                                    {{-- Detalles del Contrato --}}
                                    <h6 class="text-primary"><strong>Detalles del Contrato</strong></h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Número de Contrato:</strong>
                                                {{ $contractor->contract_number }}</p>
                                            <p class="mb-1"><strong>Año de Contrato:</strong> {{ $contractor->contract_year }}
                                            </p>
                                            <p class="mb-1"><strong>Fecha Inicio:</strong>
                                                {{ \Carbon\Carbon::parse($contractor->contract_start_date)->format('d/m/Y') }}
                                            </p>
                                            <p class="mb-1"><strong>Fecha Fin:</strong>
                                                {{ \Carbon\Carbon::parse($contractor->contract_end_date)->format('d/m/Y') }}
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Tipo de Contrato:</strong>
                                                @if($contractor->contractorType)
                                                    {{ $contractor->contractorType->name }}
                                                @else
                                                    <span class="text-muted">No especificado</span>
                                                @endif
                                            </p>
                                            <p class="mb-1"><strong>Horas de Trabajo:</strong>
                                                {{ $contractor->amount_hours ?? 'N/A' }} horas
                                            </p>
                                            <p class="mb-1"><strong>Valor Total:</strong>
                                                ${{ number_format($contractor->total_contract_value, 0, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>

                                    <hr>

                                    {{-- Objeto del Contrato --}}
                                    <h6 class="text-primary"><strong>Objeto del Contrato</strong></h6>
                                    <p class="text-justify">{{ $contractor->contract_object ?? 'N/A' }}</p>

                                    {{-- Objetivos Específicos --}}
                                    <h6 class="text-primary"><strong>Objetivos Específicos</strong></h6>
                                    <div class="text-justify" style="white-space: pre-line;">
                                        @php
                                            $obligations = $contractor->contract_obligations ?? 'N/A';
                                            $formatted = preg_replace('/(\d+\.\d+)/', "\n$1", $obligations);
                                            $formatted = preg_replace('/\n+/', "\n", $formatted);
                                            $formatted = trim($formatted);
                                        @endphp
                                        {{ $formatted }}
                                    </div>

                                    <hr>

                                    {{-- Botón Generar Certificado --}}
                                    <div class="text-center mt-3">
                                        <button type="button" class="btn btn-success btn-lg" data-toggle="modal"
                                            data-target="#generateCertificateModal{{ $contractor->id }}">
                                            <i class="fas fa-file-pdf"></i>
                                            {{ trans('gth::menu.Generate Certificate') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal para Generar Certificado --}}
                            <div class="modal fade" id="generateCertificateModal{{ $contractor->id }}" tabindex="-1"
                                role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-file-certificate"></i>
                                                Generar Certificado Contractual
                                            </h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>

                                        <form method="POST"
                                            action="{{ route('cefa.contractualcertificate.generate', $contractor->id) }}">
                                            @csrf

                                            <div class="modal-body">
                                                <p class="alert alert-info">
                                                    <i class="fas fa-info-circle"></i>
                                                    Complete los siguientes datos para generar el certificado contractual.
                                                </p>

                                                {{-- Alerta si hay solicitud --}}
                                                @if($hasRequest)
                                                    <div class="alert alert-success">
                                                        <i class="fas fa-check-circle"></i> 
                                                        <strong>Solicitud encontrada para el año {{ $contractor->contract_year }}</strong>
                                                        <br>
                                                        <small>
                                                            Solicitado el: {{ $yearRequest->created_at->format('d/m/Y H:i') }}
                                                            <br>
                                                            Estado: <span class="badge badge-info">{{ $yearRequest->status }}</span>
                                                        </small>
                                                    </div>
                                                @endif

                                                {{-- Datos del Contratista --}}
                                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                                    <i class="fas fa-user"></i> Datos del Contratista
                                                </h6>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Género <span class="text-danger">*</span></label>
                                                            <select name="gender" class="form-control" required>
                                                                <option value="">-- Seleccione --</option>
                                                                <option value="Masculino" 
                                                                    {{ ($contractor->person->gender == 'Masculino') ? 'selected' : '' }}>
                                                                    Masculino
                                                                </option>
                                                                <option value="Femenino" 
                                                                    {{ ($contractor->person->gender == 'Femenino') ? 'selected' : '' }}>
                                                                    Femenino
                                                                </option>
                                                                <option value="Otro" 
                                                                    {{ ($contractor->person->gender == 'Otro') ? 'selected' : '' }}>
                                                                    Otro
                                                                </option>
                                                            </select>
                                                            @if($hasRequest && $contractor->person->gender)
                                                                <small class="text-success d-block mt-1">
                                                                    <i class="fas fa-check-circle"></i> Autocompletado desde solicitud
                                                                </small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Lugar de Expedición del Documento <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" 
                                                                   name="place_of_issue" 
                                                                   class="form-control"
                                                                   placeholder="Ej: Neiva, Huila"
                                                                   value="{{ $contractor->person->place_of_issue ?? '' }}"
                                                                   required>
                                                            @if($hasRequest && $contractor->person->place_of_issue)
                                                                <small class="text-success d-block mt-1">
                                                                    <i class="fas fa-check-circle"></i> Autocompletado desde solicitud
                                                                </small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Sección: Forma de Pago --}}
                                                <div class="form-group">
                                                    <label class="fw-bold mb-3 d-block">
                                                        Tipo de Pago <span class="text-danger">*</span>
                                                    </label>

                                                    <div class="row g-3">
                                                        {{-- Pago Mensual --}}
                                                        <div class="col-md-6">
                                                            <input type="radio"
                                                                   class="btn-check"
                                                                   name="payment_type"
                                                                   id="payment_mensual_{{ $contractor->id }}"
                                                                   value="mensual"
                                                                   {{ old('payment_type') == 'mensual' ? 'checked' : '' }}
                                                                   required>

                                                            <label class="payment-card border rounded p-3 w-100 h-100"
                                                                   for="payment_mensual_{{ $contractor->id }}">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <i class="fas fa-calendar-alt fa-2x text-primary me-3"></i>
                                                                    <div>
                                                                        <strong>Pago Mensual</strong>
                                                                        <div class="text-muted small">
                                                                            Honorarios mensuales
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="input-group mt-3">
                                                                    <span class="input-group-text">$</span>
                                                                    <input type="number"
                                                                           name="monthly_payment"
                                                                           class="form-control"
                                                                           placeholder="Ej: 2.500.000"
                                                                           value="{{ old('monthly_payment') }}">
                                                                </div>
                                                            </label>
                                                        </div>

                                                        {{-- Pago por Horas --}}
                                                        <div class="col-md-6">
                                                            <input type="radio"
                                                                   class="btn-check"
                                                                   name="payment_type"
                                                                   id="payment_horas_{{ $contractor->id }}"
                                                                   value="horas"
                                                                   {{ old('payment_type') == 'horas' ? 'checked' : '' }}
                                                                   required>

                                                            <label class="payment-card border rounded p-3 w-100 h-100"
                                                                   for="payment_horas_{{ $contractor->id }}">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <i class="fas fa-clock fa-2x text-success me-3"></i>
                                                                    <div>
                                                                        <strong>Pago por Horas</strong>
                                                                        <div class="text-muted small">
                                                                            Valor según horas ejecutadas
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="input-group mt-3">
                                                                    <span class="input-group-text">$</span>
                                                                    <input type="number"
                                                                           name="unit_hour_value"
                                                                           class="form-control"
                                                                           placeholder="Ej: 50.000"
                                                                           value="{{ old('unit_hour_value') }}">
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    @error('payment_type')
                                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                {{-- Firmas del Certificado --}}
                                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                                    <i class="fas fa-signature"></i> Firmas del Certificado
                                                </h6>

                                                {{-- Proyectado por --}}
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Proyectado por <span class="text-danger">*</span></label>
                                                            <input type="text" name="projected_by" class="form-control"
                                                                placeholder="Nombre completo"
                                                                value="Zaray Leandra Rojas Cuellar" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Cargo <span class="text-danger">*</span></label>
                                                            <input type="text" name="projected_by_role" class="form-control"
                                                                placeholder="Cargo del proyector" value="Aprendiz GTH" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Revisado por --}}
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Revisado por <span class="text-danger">*</span></label>
                                                            <input type="text" name="reviewed_by" class="form-control"
                                                                placeholder="Nombre completo" value="Doris Yolima Amaya Tovar"
                                                                required>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Cargo <span class="text-danger">*</span></label>
                                                            <input type="text" name="reviewed_by_role" class="form-control"
                                                                placeholder="Cargo del revisor" value="Profesional GTH"
                                                                required>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Director/Subdirector --}}
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Director/Subdirector <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="director_name" class="form-control"
                                                                placeholder="Nombre completo"
                                                                value="GLORIA MARITZA SÁNCHEZ ALARCÓN" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Cargo <span class="text-danger">*</span></label>
                                                            <input type="text" name="director_role" class="form-control"
                                                                placeholder="Cargo del director" value="Subdirectora (E)"
                                                                required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-file-pdf"></i> Generar Certificado
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Fade out de alertas después de 5 segundos
    setTimeout(function () {
        $('.alert').fadeOut('slow');
    }, 5000);
</script>
@endpush