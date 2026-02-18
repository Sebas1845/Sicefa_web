@extends('gth::layouts.master')

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>{{ trans('gth::menu.Employment Contract Record') }}</h1>
            </div>
            <div class="card-body">
                {{-- Mostrar errores de validación --}}
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>¡Error!</strong> Por favor corrija los siguientes problemas:
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif



                <form method="POST" action="{{ route('cefa.gth.contractreports.store') }}" id="contractForm">
                    @csrf

                    <!-- Información de la Persona -->
                    <h2>{{ trans('gth::menu.Personal Information') }}</h2>
                    <input type="hidden" name="person_id" id="person_id" value="{{ old('person_id') }}">
                    
                    <div class="form-group">
                        <label for="document_number">{{ trans('gth::menu.ID number:') }} <span class="text-danger">*</span></label>
                        <input type="number" 
                               name="document_number"
                               id="document_number"
                               class="form-control @error('document_number') is-invalid @enderror"
                               value="{{ old('document_number') }}" 
                               required
                               placeholder="Ingrese el número de documento y presione TAB">
                        @error('document_number')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        <small class="form-text text-muted">Presione TAB después de ingresar el número</small>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="first_name">{{ trans('gth::menu.First Name:') }}</label>
                                <input type="text" name="first_name" id="first_name" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                        

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="first_last_name">{{ trans('gth::menu.First Surname:') }}</label>
                                <input type="text" name="first_last_name" id="first_last_name" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                        
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="second_last_name">{{ trans('gth::menu.Second Surname:') }}</label>
                                <input type="text" name="second_last_name" id="second_last_name" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>

                    <!-- Supervisor -->
                    <h2>{{ trans('gth::menu.Personal Supervisor') }}</h2>
                    <input type="hidden" name="supervisor_id" id="supervisor_id" value="{{ old('supervisor_id') }}">

                    <div class="form-group">
                        <label for="document_number_supervisor">{{ trans('gth::menu.ID number:') }} <span class="text-danger">*</span></label>
                        <input type="number" 
                               name="document_number_supervisor" 
                               id="document_number_supervisor"
                               class="form-control @error('document_number_supervisor') is-invalid @enderror"
                               value="{{ old('document_number_supervisor') }}" 
                               required
                               placeholder="Ingrese el número de documento del supervisor y presione TAB">
                        @error('document_number_supervisor')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="first_name_supervisor">{{ trans('gth::menu.First Name:') }}</label>
                                <input type="text" name="first_name_supervisor" id="first_name_supervisor" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                   

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="first_last_name_supervisor">{{ trans('gth::menu.First Surname:') }}</label>
                                <input type="text" name="first_last_name_supervisor" id="first_last_name_supervisor" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="second_last_name_supervisor">{{ trans('gth::menu.Second Surname:') }}</label>
                                <input type="text" name="second_last_name_supervisor" id="second_last_name_supervisor" class="form-control bg-light" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>

                    <!-- Detalles del Contrato - Sección 1 -->
                    <h3 class="mt-4">Detalles del Contrato</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contract_number">{{ trans('gth::menu.Contract Number:') }} <span class="text-danger">*</span></label>
                                <input type="text" name="contract_number" id="contract_number"
                                    class="form-control @error('contract_number') is-invalid @enderror"
                                    value="{{ old('contract_number') }}" 
                                    placeholder="{{ trans('gth::menu.Enter the contract number') }}" required>
                                @error('contract_number')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contract_date">{{ trans('gth::menu.Contract Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="contract_date" id="contract_date"
                                    class="form-control @error('contract_date') is-invalid @enderror"
                                    value="{{ old('contract_date') }}" required>
                                @error('contract_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contract_start_date">{{ trans('gth::menu.Contract Start Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="contract_start_date" id="contract_start_date"
                                    class="form-control @error('contract_start_date') is-invalid @enderror"
                                    value="{{ old('contract_start_date') }}" required>
                                @error('contract_start_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contract_end_date">{{ trans('gth::menu.Contract End Date:') }}</label>
                                <input type="date" name="contract_end_date" id="contract_end_date"
                                    class="form-control @error('contract_end_date') is-invalid @enderror"
                                    value="{{ old('contract_end_date') }}">
                                @error('contract_end_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2 -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="contractor_type_id">{{ trans('gth::menu.Type of Contract:') }} <span class="text-danger">*</span></label>
                                <select name="contractor_type_id" id="contractor_type_id"
                                    class="form-control @error('contractor_type_id') is-invalid @enderror" required>
                                    <option value="">{{ trans('gth::menu.---Choose the Contract Type---') }}</option>
                                    @foreach ($contractorTypes as $contractorType)
                                        <option value="{{ $contractorType->id }}" {{ old('contractor_type_id') == $contractorType->id ? 'selected' : '' }}>
                                            {{ $contractorType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('contractor_type_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="employee_type_id">{{ trans('gth::menu.Type of Employee:') }} <span class="text-danger">*</span></label>
                                <select name="employee_type_id" id="employee_type_id"
                                    class="form-control @error('employee_type_id') is-invalid @enderror" required>
                                    <option value="">{{ trans('gth::menu.--- Choose the Employee Type ---') }}</option>
                                    @foreach ($employeeTypes as $employeeType)
                                        <option value="{{ $employeeType->id }}" {{ old('employee_type_id') == $employeeType->id ? 'selected' : '' }}>
                                            {{ $employeeType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_type_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="amount_hours">{{ trans('gth::menu.Hours of Work:') }} <span class="text-danger">*</span></label>
                                <input type="number" name="amount_hours" id="amount_hours"
                                    class="form-control @error('amount_hours') is-invalid @enderror"
                                    value="{{ old('amount_hours') }}" 
                                    placeholder="{{ trans('gth::menu.Enter the working hours') }}" 
                                    min="1" required>
                                @error('amount_hours')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="total_contract_value">{{ trans('gth::menu.Total Contract Value:') }} <span class="text-danger">*</span></label>
                                <input type="number" name="total_contract_value" id="total_contract_value"
                                    class="form-control @error('total_contract_value') is-invalid @enderror"
                                    value="{{ old('total_contract_value') }}" 
                                    placeholder="{{ trans('gth::menu.Enter the total value of the contract') }}" 
                                    min="0" step="0.01" required>
                                @error('total_contract_value')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Sección 4: Póliza -->
                    <h3 class="mt-4">Información de Póliza</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="policy_issue_date">{{ trans('gth::menu.Policy Issuance Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="policy_issue_date" id="policy_issue_date"
                                    class="form-control @error('policy_issue_date') is-invalid @enderror"
                                    value="{{ old('policy_issue_date') }}" required>
                                @error('policy_issue_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="policy_approval_date">{{ trans('gth::menu.Policy Approval Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="policy_approval_date" id="policy_approval_date"
                                    class="form-control @error('policy_approval_date') is-invalid @enderror"
                                    value="{{ old('policy_approval_date') }}" required>
                                @error('policy_approval_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="policy_effective_date">{{ trans('gth::menu.Policy Effective Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="policy_effective_date" id="policy_effective_date"
                                    class="form-control @error('policy_effective_date') is-invalid @enderror"
                                    value="{{ old('policy_effective_date') }}" required>
                                @error('policy_effective_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="policy_expiration_date">{{ trans('gth::menu.Policy Expiration Date:') }} <span class="text-danger">*</span></label>
                                <input type="date" name="policy_expiration_date" id="policy_expiration_date"
                                    class="form-control @error('policy_expiration_date') is-invalid @enderror"
                                    value="{{ old('policy_expiration_date') }}" required>
                                @error('policy_expiration_date')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="policy_number">{{ trans('gth::menu.Policy Number:') }}</label>
                                <input type="text" name="policy_number" id="policy_number"
                                    class="form-control @error('policy_number') is-invalid @enderror"
                                    value="{{ old('policy_number') }}" 
                                    placeholder="{{ trans('gth::menu.Enter Policy Number') }}">
                                @error('policy_number')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="risk_type">{{ trans('gth::menu.Type of Risk:') }} <span class="text-danger">*</span></label>
                                <select name="risk_type" id="risk_type"
                                    class="form-control @error('risk_type') is-invalid @enderror" required>
                                    <option value="">{{ trans('gth::menu.---Choose the Type of Risk---') }}</option>
                                    <option value="I" {{ old('risk_type') == 'I' ? 'selected' : '' }}>I</option>
                                    <option value="II" {{ old('risk_type') == 'II' ? 'selected' : '' }}>II</option>
                                    <option value="III" {{ old('risk_type') == 'III' ? 'selected' : '' }}>III</option>
                                    <option value="IV" {{ old('risk_type') == 'IV' ? 'selected' : '' }}>IV</option>
                                    <option value="V" {{ old('risk_type') == 'V' ? 'selected' : '' }}>V</option>
                                </select>
                                @error('risk_type')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="state">{{ trans('gth::menu.Status:') }} <span class="text-danger">*</span></label>
                                <select name="state" id="state"
                                    class="form-control @error('state') is-invalid @enderror" required>
                                    <option value="">{{ trans('gth::menu.--- Choose the contractors status ---') }}</option>
                                    <option value="Activo" {{ old('state') === 'Activo' ? 'selected' : '' }}>Activo</option>
                                    <option value="Inactivo" {{ old('state') === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
                                </select>
                                @error('state')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3 -->
                    <h3 class="mt-4">Información Adicional</h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="SIIF_code">{{ trans('gth::menu.SIIF Code:') }} <span class="text-danger">*</span></label>
                                <input type="number" name="SIIF_code" id="SIIF_code"
                                    class="form-control @error('SIIF_code') is-invalid @enderror"
                                    value="{{ old('SIIF_code') }}" 
                                    placeholder="{{ trans('gth::menu.Enter the assignment value') }}" required>
                                @error('SIIF_code')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="assigment_value">{{ trans('gth::menu.Assignment Value:') }} <span class="text-danger">*</span></label>
                                <input type="number" name="assigment_value" id="assigment_value"
                                    class="form-control @error('assigment_value') is-invalid @enderror"
                                    value="{{ old('assigment_value') }}" 
                                    placeholder="{{ trans('gth::menu.Enter the assignment value') }}" 
                                    min="0" step="0.01" required>
                                @error('assigment_value')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="insurer_entity_id">{{ trans('gth::menu.Insurance Company:') }} <span class="text-danger">*</span></label>
                                <select name="insurer_entity_id" id="insurer_entity_id"
                                    class="form-control @error('insurer_entity_id') is-invalid @enderror" required>
                                    <option value="">{{ trans('gth::menu.--- Choose the insurance entity ---') }}</option>
                                    @foreach ($insurerEntity as $entity)
                                        <option value="{{ $entity->id }}" {{ old('insurer_entity_id') == $entity->id ? 'selected' : '' }}>
                                            {{ $entity->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('insurer_entity_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="contract_object">{{ trans('gth::menu.Contract Object:') }} <span class="text-danger">*</span></label>
                        <textarea name="contract_object" id="contract_object"
                            class="form-control @error('contract_object') is-invalid @enderror" 
                            placeholder="{{ trans('gth::menu.Enter the subject of the contract') }}" 
                            rows="3" required>{{ old('contract_object') }}</textarea>
                        @error('contract_object')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="contract_obligations">{{ trans('gth::menu.Contract Obligations:') }} <span class="text-danger">*</span></label>
                        <textarea name="contract_obligations" id="contract_obligations"
                            class="form-control @error('contract_obligations') is-invalid @enderror" 
                            placeholder="{{ trans('gth::menu.Enter the obligations of the contract') }}" 
                            rows="4" required>{{ old('contract_obligations') }}</textarea>
                        @error('contract_obligations')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-success btn-lg" id="guardarContrato">
                            <i class="fas fa-save"></i> {{ trans('gth::menu.Save Contract') }}
                        </button>
                        <a href="{{ route('gth.admin.contractreports.index') }}" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Función para buscar persona
            function buscarPersona(documentNumber, tipoPersona) {
                if (!documentNumber) return;

                $.ajax({
                    url: '{{ route('cefa.gth.getPersonData') }}',
                    method: 'GET',
                    data: { document_number: documentNumber },
                    success: function(data) {
                        if (tipoPersona === 'contratista') {
                            $('#person_id').val(data.id);
                            $('#first_name').val(data.first_name);
                            $('#second_name').val(data.second_name || '');
                            $('#first_last_name').val(data.first_last_name);
                            $('#second_last_name').val(data.second_last_name || '');
                        } else if (tipoPersona === 'supervisor') {
                            $('#supervisor_id').val(data.id);
                            $('#first_name_supervisor').val(data.first_name);
                            $('#second_name_supervisor').val(data.second_name || '');
                            $('#first_last_name_supervisor').val(data.first_last_name);
                            $('#second_last_name_supervisor').val(data.second_last_name || '');
                        }
                    },
                    error: function(xhr) {
                        alert('No se encontró ninguna persona con ese número de documento');
                        if (tipoPersona === 'contratista') {
                            $('#person_id').val('');
                            $('#first_name').val('');
                            $('#second_name').val('');
                            $('#first_last_name').val('');
                            $('#second_last_name').val('');
                        } else if (tipoPersona === 'supervisor') {
                            $('#supervisor_id').val('');
                            $('#first_name_supervisor').val('');
                            $('#first_last_name_supervisor').val('');
                            $('#second_name_supervisor').val('');
                            $('#second_last_name_supervisor').val('');
                        }
                    }
                });
            }

            // Buscar cuando se cambia el número de documento del contratista
            $('#document_number').on('change blur', function() {
                buscarPersona($(this).val(), 'contratista');
            });

            // Buscar cuando se cambia el número de documento del supervisor
            $('#document_number_supervisor').on('change blur', function() {
                buscarPersona($(this).val(), 'supervisor');
            });

            // Validación antes de enviar
            $('#contractForm').on('submit', function(e) {
                if (!$('#person_id').val()) {
                    e.preventDefault();
                    alert('Por favor, busque y seleccione una persona válida');
                    $('#document_number').focus();
                    return false;
                }

                if (!$('#supervisor_id').val()) {
                    e.preventDefault();
                    alert('Por favor, busque y seleccione un supervisor válido');
                    $('#document_number_supervisor').focus();
                    return false;
                }

                // Deshabilitar botón para evitar doble envío
                $('#guardarContrato').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
            });
        });
    </script>
@endsection