@extends('gth::layouts.master')

@section('content')
<div class="container-fluid">
    {{-- VERIFICAR SI ES VISTA DE DETALLES (un solo pasante) --}}
    @if(isset($intern))
        {{-- VISTA DE DETALLES DE UN PASANTE --}}
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-user-graduate"></i> Información del Pasante</h3>
                <div>
                    @if(Route::has('gth.admin.interns.edit'))
                    <a href="{{ route('gth.admin.interns.edit', $intern->id) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    @endif
                    <a href="{{ route('gth.admin.interns.index') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                <!-- Información Personal -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-user"></i> Información Personal
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="text-muted small">Cédula</label>
                                    <p class="font-weight-bold">{{ $intern->person->document_number ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="text-muted small">Primer Nombre</label>
                                    <p class="font-weight-bold">{{ $intern->person->first_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="text-muted small">Primer Apellido</label>
                                    <p class="font-weight-bold">{{ $intern->person->first_last_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="text-muted small">Segundo Apellido</label>
                                    <p class="font-weight-bold">{{ $intern->person->second_last_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">Teléfono</label>
                                    <p class="font-weight-bold">
                                        <i class="fas fa-phone text-success"></i> {{ $intern->person->telephone1 ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">EPS</label>
                                    <p class="font-weight-bold">
                                        @if(isset($intern->person) && method_exists($intern->person, 'e_p_s'))
                                            {{ $intern->person->e_p_s->name ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">Fondo de Pensión</label>
                                    <p class="font-weight-bold">
                                        @if(isset($intern->person) && method_exists($intern->person, 'pension_entity'))
                                            {{ $intern->person->pension_entity->name ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Grupo Poblacional</label>
                                    <p class="font-weight-bold">
                                        @if(isset($intern->person) && method_exists($intern->person, 'population_group'))
                                            {{ $intern->person->population_group->name ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Supervisor -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-user-tie"></i> Supervisor Asignado
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="text-muted small">Cédula</label>
                                    <p class="font-weight-bold">
                                        @if(isset($intern->supervisor))
                                            {{ $intern->supervisor->document_number ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="mb-3">
                                    <label class="text-muted small">Nombre Completo</label>
                                    <p class="font-weight-bold">
                                        @if(isset($intern->supervisor))
                                            {{ $intern->supervisor->first_name ?? '' }} 
                                            {{ $intern->supervisor->first_last_name ?? '' }} 
                                            {{ $intern->supervisor->second_last_name ?? '' }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Contrato -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-briefcase"></i> Información del Contrato
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Área Asignada</label>
                                    <p class="font-weight-bold">{{ $intern->assigned_area ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Programa</label>
                                    <p class="font-weight-bold">{{ $intern->program_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">Fecha de Inicio</label>
                                    <p class="font-weight-bold">
                                        <i class="fas fa-calendar-check text-success"></i> 
                                        {{ $intern->start_date ? \Carbon\Carbon::parse($intern->start_date)->format('d/m/Y') : 'N/A' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">Fecha de Fin</label>
                                    <p class="font-weight-bold">
                                        <i class="fas fa-calendar-times text-danger"></i> 
                                        {{ $intern->end_date ? \Carbon\Carbon::parse($intern->end_date)->format('d/m/Y') : 'N/A' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="text-muted small">Número de Contrato</label>
                                    <p class="font-weight-bold">{{ $intern->contract_number ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Estado del Contrato -->
                        @if($intern->start_date && $intern->end_date)
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="text-muted small">Estado del Contrato</label>
                                    <p>
                                        @php
                                            $today = \Carbon\Carbon::now();
                                            $startDate = \Carbon\Carbon::parse($intern->start_date);
                                            $endDate = \Carbon\Carbon::parse($intern->end_date);
                                        @endphp
                                        
                                        @if($today->lt($startDate))
                                            <span class="badge badge-info badge-pill">
                                                <i class="fas fa-clock"></i> Próximo a iniciar
                                            </span>
                                        @elseif($today->between($startDate, $endDate))
                                            <span class="badge badge-success badge-pill">
                                                <i class="fas fa-check-circle"></i> Activo
                                            </span>
                                        @else
                                            <span class="badge badge-secondary badge-pill">
                                                <i class="fas fa-times-circle"></i> Finalizado
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="text-right">
                    <a href="{{ route('gth.admin.interns.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver al Listado
                    </a>
                    @if(Route::has('gth.admin.interns.edit'))
                    <a href="{{ route('gth.admin.interns.edit', $intern->id) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Editar Información
                    </a>
                    @endif
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmación de Eliminación -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">
                            <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar al pasante <strong>{{ $intern->person->first_name ?? '' }} {{ $intern->person->first_last_name ?? '' }}</strong>?</p>
                        <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <form action="{{ route('gth.admin.interns.destroy', $intern->id) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    @elseif(isset($interns))
        {{-- VISTA DE LISTADO DE PASANTES --}}
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-users"></i> Lista de Pasantes</h3>
                @if(Route::has('gth.admin.interns.create'))
                <a href="{{ route('gth.admin.interns.create') }}" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Nuevo Pasante
                </a>
                @endif
            </div>

            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover" id="internsTable">
                        <thead>
                            <tr>
                                <th>Cédula</th>
                                <th>Nombre Completo</th>
                                <th>Programa</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($interns as $intern)
                            <tr data-intern-id="{{ $intern->id }}">
                                <td>{{ $intern->person->document_number ?? 'N/A' }}</td>
                                <td>
                                    {{ $intern->person->first_name ?? '' }} 
                                    {{ $intern->person->first_last_name ?? '' }} 
                                    {{ $intern->person->second_last_name ?? '' }}
                                </td>
                                <td>{{ $intern->program_name ?? 'N/A' }}</td>
                                <td>{{ $intern->start_date ? \Carbon\Carbon::parse($intern->start_date)->format('d/m/Y') : 'N/A' }}</td>
                                <td>{{ $intern->end_date ? \Carbon\Carbon::parse($intern->end_date)->format('d/m/Y') : 'N/A' }}</td>
                                <td>
                                    @if($intern->start_date && $intern->end_date)
                                        @php
                                            $today = \Carbon\Carbon::now();
                                            $startDate = \Carbon\Carbon::parse($intern->start_date);
                                            $endDate = \Carbon\Carbon::parse($intern->end_date);
                                        @endphp
                                        
                                        @if($today->lt($startDate))
                                            <span class="badge badge-info">Próximo</span>
                                        @elseif($today->between($startDate, $endDate))
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-secondary">Finalizado</span>
                                        @endif
                                    @else
                                        <span class="badge badge-warning">Sin fechas</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('gth.admin.interns.show', $intern->id) }}" 
                                       class="btn btn-sm btn-info" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-danger delete-intern-btn" 
                                            data-intern-id="{{ $intern->id }}"
                                            data-intern-name="{{ $intern->person->first_name ?? '' }} {{ $intern->person->first_last_name ?? '' }}"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    No hay pasantes registrados
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmación de Eliminación (para tabla) -->
        <div class="modal fade" id="deleteModalTable" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar al pasante <strong id="internNameToDelete"></strong>?</p>
                        <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <form id="deleteFormTable" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('styles')
<style>
    .card {
        border: none;
        border-radius: 10px;
    }
    
    .card-header {
        border-radius: 10px 10px 0 0 !important;
        padding: 1rem 1.5rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .mb-3 label {
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    
    .mb-3 p {
        font-size: 1rem;
        margin-bottom: 0;
    }
    
    .badge-pill {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }

    .table-responsive {
        margin-top: 1rem;
    }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    console.log('🔵 Script de eliminación de pasantes cargado');
    
    // Manejador para los botones de eliminar en la tabla
    $('.delete-intern-btn').on('click', function() {
        console.log('🔴 Botón eliminar clickeado');
        
        const internId = $(this).data('intern-id');
        const internName = $(this).data('intern-name');
        
        console.log('📊 Datos del pasante:', {
            id: internId,
            nombre: internName
        });
        
        // Actualizar el modal
        $('#internNameToDelete').text(internName);
        
        // Construir la URL de eliminación
        const deleteUrl = "{{ route('gth.admin.interns.destroy', ':id') }}".replace(':id', internId);
        console.log('🔗 URL de eliminación:', deleteUrl);
        
        $('#deleteFormTable').attr('action', deleteUrl);
        
        // Mostrar el modal
        $('#deleteModalTable').modal('show');
    });
    
    // Manejador del submit del formulario
    $('#deleteFormTable').on('submit', function(e) {
        console.log('📤 Formulario de eliminación enviado');
        console.log('🔗 Action URL:', $(this).attr('action'));
        console.log('📋 Form data:', $(this).serialize());
    });
});
</script>
@endsection