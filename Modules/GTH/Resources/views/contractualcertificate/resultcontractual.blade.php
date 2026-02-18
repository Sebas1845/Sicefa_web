@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ __('gth::contractualcertificate.results_title') }}</h4>
                    <a href="{{ route('cefa.contractualcertificate.view') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Nueva Búsqueda
                    </a>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="contractsTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>N°</th>
                                    <th>{{ __('gth::contractualcertificate.contract_number') }}</th>
                                    <th>{{ __('gth::contractualcertificate.contractor_name') }}</th>
                                    <th>{{ __('gth::contractualcertificate.document') }}</th>
                                    <th>{{ __('gth::contractualcertificate.start_date') }}</th>
                                    <th>{{ __('gth::contractualcertificate.end_date') }}</th>
                                    <th>{{ __('gth::contractualcertificate.total_value') }}</th>
                                    <th>{{ __('gth::contractualcertificate.status') }}</th>
                                    <th class="text-center">{{ __('gth::contractualcertificate.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($contractors as $index => $contractor)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $contractor->contract_number }}</td>
                                        <td>
                                            {{ $contractor->person->first_name }} 
                                            {{ $contractor->person->first_last_name }}
                                            {{ $contractor->person->second_last_name }}
                                        </td>
                                        <td>{{ number_format($contractor->person->document_number, 0, ',', '.') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($contractor->contract_start_date)->format('d/m/Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($contractor->contract_end_date)->format('d/m/Y') }}</td>
                                        <td class="text-right">${{ number_format($contractor->total_contract_value, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $contractor->state == 'Activo' ? 'success' : 'secondary' }}">
                                                {{ $contractor->state }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('cefa.contractualcertificate.generate', $contractor->id) }}" class="d-inline">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-sm btn-success" 
                                                    title="{{ __('gth::contractualcertificate.generate_certificate') }}"
                                                    onclick="return confirm('¿Está seguro de generar el certificado para este contrato?')">
                                                    <i class="fas fa-file-certificate"></i> Generar Certificado
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <p class="text-muted mb-0">No se encontraron contratos.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#contractsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[1, 'desc']],
            pageLength: 10
        });
    });
</script>
@endpush