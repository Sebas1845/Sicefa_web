@extends('gth::layouts.master')

@section('title', 'Solicitudes Pendientes de Certificados')

@section('content')
<div class="container-fluid mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-bell"></i> Solicitudes Pendientes de Certificados</h2>
        <a href="{{ route('cefa.index.view') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle"></i> {{ session('info') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if($pendingCertificates->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No hay solicitudes pendientes</h4>
                <p class="text-muted">Cuando los usuarios soliciten certificados, aparecerán aquí.</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> 
                    Total de solicitudes: <span class="badge badge-light">{{ $pendingCertificates->count() }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th width="3%">#</th>
                                <th width="15%">Solicitante</th>
                                <th width="10%">Tipo Documento</th>
                                <th width="10%">N° Documento</th>
                                <th width="15%">Correo Electrónico</th>
                                <th width="10%">Celular</th>
                                <th width="8%">Año</th>
                                <th width="12%">Fecha Solicitud</th>
                                <th width="10%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingCertificates as $request)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $request->person->full_name ?? ($request->person->first_name . ' ' . $request->person->first_last_name) }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        {{ $request->person->document_type ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        {{ $request->person->document_number }}
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-envelope text-primary"></i>
                                        {{ $request->person->personal_email ?? 'No registrado' }}
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-mobile-alt text-success"></i>
                                        {{ $request->person->telephone1 ?? 'No registrado' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <i class="fas fa-calendar"></i> {{ $request->contract_year }}
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-calendar"></i>
                                        {{ $request->requested_at->format('d/m/Y') }}
                                        <br>
                                        <i class="fas fa-clock"></i>
                                        {{ $request->requested_at->format('h:i A') }}
                                    </small>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-danger btn-sm"
                                            data-toggle="modal"
                                            data-target="#deleteModal{{ $request->id }}"
                                            title="Eliminar solicitud">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>

                            {{-- Modal de eliminación --}}
                            <div class="modal fade" id="deleteModal{{ $request->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-trash"></i> Eliminar Solicitud
                                            </h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <form action="{{ route('cefa.contractualcertificate.destroy', $request->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <strong>Datos de la solicitud:</strong>
                                                    <ul class="mb-0 mt-2">
                                                        <li><strong>Solicitante:</strong> {{ $request->person->full_name ?? ($request->person->first_name . ' ' . $request->person->first_last_name) }}</li>
                                                        <li><strong>Tipo de documento:</strong> {{ $request->person->document_type ?? 'No especificado' }}</li>
                                                        <li><strong>Número de documento:</strong> {{ $request->person->document_number }}</li>
                                                        <li><strong>Correo electrónico:</strong> {{ $request->person->personal_email ?? 'No registrado' }}</li>
                                                        <li><strong>Celular:</strong> {{ $request->person->telephone1 ?? 'No registrado' }}</li>
                                                        <li><strong>Año solicitado:</strong> {{ $request->contract_year }}</li>
                                                        <li><strong>Fecha de solicitud:</strong> {{ $request->requested_at->format('d/m/Y h:i A') }}</li>
                                                    </ul>
                                                </div>
                                                
                                                <p class="text-danger font-weight-bold">
                                                    <i class="fas fa-exclamation-triangle"></i> ¿Está seguro de eliminar esta solicitud?
                                                </p>
                                                <p class="text-muted">
                                                    Esta acción no se puede deshacer.
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Confirmar Eliminación
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Activar tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
</script>
@endpush

@push('styles')
<style>
    .table td {
        vertical-align: middle;
    }
    
    .table td small {
        display: block;
        line-height: 1.4;
    }
    
    .badge {
        font-size: 0.85rem;
        padding: 0.4rem 0.6rem;
    }
    
    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.875rem;
    }
    
    .modal-body ul {
        list-style: none;
        padding-left: 0;
    }
    
    .modal-body ul li {
        padding: 0.3rem 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .modal-body ul li:last-child {
        border-bottom: none;
    }
</style>
@endpush
@endsection