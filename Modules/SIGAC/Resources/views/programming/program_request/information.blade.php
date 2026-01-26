{{-- sigac::programming.program_request.partials.information_modal --}}

<div class="modal fade"
     id="informationproyecto{{ $pr->id }}"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <strong>Información del solicitante</strong>
                </h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div class="row g-3">

                    {{-- Empresa --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Empresa</h6>
                        <p class="mb-0">
                            {{ $pr->company->name ?? $pr->empresa ?? 'No registrada' }}
                        </p>
                    </div>

                    {{-- Dirección --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Dirección</h6>
                        <p class="mb-0">
                            {{ $pr->address ?? 'No registrada' }}
                        </p>
                    </div>

                    {{-- Nombre solicitante --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Nombre del solicitante</h6>
                        <p class="mb-0">
                            {{ $pr->applicant ?? 'No registrado' }}
                        </p>
                    </div>

                    {{-- Correo --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Correo electrónico</h6>
                        <p class="mb-0">
                            {{ $pr->email ?? 'No registrado' }}
                        </p>
                    </div>

                    {{-- Teléfono --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Teléfono</h6>
                        <p class="mb-0">
                            {{ $pr->telephone ?? 'No registrado' }}
                        </p>
                    </div>

                    {{-- Observaciones (opcional pero recomendado) --}}
                    @if(!empty($prom->observation))
                        <div class="col-12">
                            <h6 class="text-muted mb-1">Observaciones</h6>
                            <p class="mb-0">
                                {{ $pr->observation }}
                            </p>
                        </div>
                    @endif

                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary"
                        type="button"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>
