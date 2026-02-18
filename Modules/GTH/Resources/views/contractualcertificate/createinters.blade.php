@extends('gth::layouts.master')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-user-plus"></i> Crear Nuevo Pasante</h3>
        </div>

        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <form action="{{ route('gth.admin.interns.store') }}" method="POST">
                @csrf

                <h5 class="text-primary mb-3"><i class="fas fa-user"></i> Información Personal</h5>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Cédula <span class="text-danger">*</span></label>
                            <input type="text" name="document_number" id="document_number" class="form-control" value="{{ old('document_number') }}" required maxlength="20" placeholder="Ej: 1234567890">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Primer Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}" required maxlength="255" placeholder="Ej: Juan">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Primer Apellido <span class="text-danger">*</span></label>
                            <input type="text" name="first_last_name" id="first_last_name" class="form-control" value="{{ old('first_last_name') }}" required maxlength="255" placeholder="Ej: Pérez">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Segundo Apellido</label>
                            <input type="text" name="second_last_name" id="second_last_name" class="form-control" value="{{ old('second_last_name') }}" maxlength="255" placeholder="Ej: García">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="telephone1" class="form-control" value="{{ old('telephone1') }}" required maxlength="20" placeholder="Ej: 3001234567">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>EPS <span class="text-danger">*</span></label>
                            <select name="eps_id" class="form-control" required>
                                <option value="">-- Seleccione una EPS --</option>
                                @foreach($epsList as $eps)
                                    <option value="{{ $eps->id }}" {{ old('eps_id') == $eps->id ? 'selected' : '' }}>
                                        {{ $eps->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Fondo de Pensión <span class="text-danger">*</span></label>
                            <select name="pension_entity_id" class="form-control" required>
                                <option value="">-- Seleccione un fondo --</option>
                                @foreach($pensionEntities as $pension)
                                    <option value="{{ $pension->id }}" {{ old('pension_entity_id') == $pension->id ? 'selected' : '' }}>
                                        {{ $pension->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Grupo Poblacional <span class="text-danger">*</span></label>
                            <select name="population_group_id" class="form-control" required>
                                <option value="">-- Seleccione un grupo --</option>
                                @foreach($populationGroups as $group)
                                    <option value="{{ $group->id }}" {{ old('population_group_id') == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <hr>

                <h5 class="text-primary mb-3"><i class="fas fa-user-tie"></i> Supervisor Asignado</h5>

                <input type="hidden" name="supervisor_id" id="supervisor_id">

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Cédula Supervisor <span class="text-danger">*</span></label>
                            <input type="text" name="document_number_supervisor" id="document_number_supervisor" class="form-control" value="{{ old('document_number_supervisor') }}" required placeholder="Ingrese cédula">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Primer Nombre</label>
                            <input type="text" id="first_name_supervisor" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Primer Apellido</label>
                            <input type="text" id="first_last_name_supervisor" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Segundo Apellido</label>
                            <input type="text" id="second_last_name_supervisor" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <hr>

                <h5 class="text-primary mb-3"><i class="fas fa-briefcase"></i> Información del Contrato</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Área Productiva <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-control" required>
                                <option value="">-- Seleccione un área --</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Programa</label>
                            <select name="program_id" class="form-control">
                                <option value="">-- Seleccione un programa --</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tipo Contrato <span class="text-danger">*</span></label>
                            <select name="contractor_type_id" class="form-control" required>
                                <option value="">-- Seleccione tipo de contrato --</option>
                                @foreach($contractorTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('contractor_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tipo Empleado <span class="text-danger">*</span></label>
                            <select name="employee_type_id" class="form-control" required>
                                <option value="">-- Seleccione tipo de empleado --</option>
                                @foreach($employeeTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('employee_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Fecha Inicio <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Fecha Fin <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Número de Contrato</label>
                            <input type="text" name="contract_number" class="form-control" value="{{ old('contract_number') }}" maxlength="50" placeholder="Ej: 2024-001">
                        </div>
                    </div>
                </div>

                <hr>

                <div class="text-right">
                    <a href="{{ route('gth.admin.interns.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Pasante
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.js"></script>
<script>
$(document).ready(function() {
    $('#document_number_supervisor').on('change', function() {
        var numeroDocumento = $(this).val();

        $.ajax({
            url: '{{ route('cefa.gth.getPersonData') }}',
            method: 'GET',
            data: {
                document_number: numeroDocumento
            },
            success: function(data) {
                $('#supervisor_id').val(data.id);
                $('#first_name_supervisor').val(data.first_name);
                $('#first_last_name_supervisor').val(data.first_last_name);
                $('#second_last_name_supervisor').val(data.second_last_name);
            },
            error: function() {
                alert('No se encontró un supervisor con esa cédula');
                $('#supervisor_id').val('');
                $('#first_name_supervisor').val('');
                $('#first_last_name_supervisor').val('');
                $('#second_last_name_supervisor').val('');
            }
        });
    });
});
</script>
@endsection