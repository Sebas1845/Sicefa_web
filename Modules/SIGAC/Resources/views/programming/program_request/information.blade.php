{{-- MODAL: INFO (Bootstrap 4 / AdminLTE) --}}
<div class="modal fade" id="infoModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-labelledby="infoModalLabel{{ $pr->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel{{ $pr->id }}">
                    <strong>Información de la solicitud</strong>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Empresa</h6>
                        <p class="mb-0">
                            {{ $pr->company->name ?? $pr->company_name ?? 'No registrada' }}
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Dirección</h6>
                        <p class="mb-0">
                            {{ $pr->address ?? 'No registrada' }}
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Nombre del solicitante</h6>
                        <p class="mb-0">
                            {{ $pr->applicant ?? 'No registrado' }}
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Correo</h6>
                        <p class="mb-0">
                            {{ $pr->email ?? 'No registrado' }}
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Teléfono</h6>
                        <p class="mb-0">
                            {{ $pr->telephone ?? 'No registrado' }}
                        </p>
                    </div>

                    {{-- Si quieres mostrar área aquí (porque quitaste la columna área) --}}
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Área</h6>
                        <p class="mb-0">
                            {{ $pr->area->name ?? ($pr->area_id == 1 ? 'CAMPESENA' : ($pr->area_id == 2 ? 'COORD. ACADÉMICA' : '—')) }}
                        </p>
                    </div>

                    {{-- Si quieres mostrar rubro aquí también --}}
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Rubro</h6>
                        <p class="mb-0">
                            {{ $pr->budgetItem->name ?? '—' }}
                        </p>
                    </div>

                    {{-- Municipio / Vereda --}}
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Municipio</h6>
                        <p class="mb-0">
                            {{ $pr->municipality->name ?? '—' }}
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Vereda</h6>
                        <p class="mb-0">
                            {{ $pr->village->name ?? '—' }}
                        </p>
                    </div>

                    @if(!empty($pr->observation))
                        <div class="col-12">
                            <h6 class="text-muted mb-1">Observaciones</h6>
                            <div class="alert alert-secondary mb-0">
                                {{ $pr->observation }}
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>
