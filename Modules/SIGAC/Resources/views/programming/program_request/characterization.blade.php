<div class="modal fade" id="characterizeModal{{ $pr->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <form method="POST"
                  action="{{ route('sigac.support.programming.program_request.characterization.store', $pr->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Caracterizar – Solicitud #{{ $pr->id }}</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código SICEFA</label>
                            <input name="sicefa_code" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Caracterización</label>
                            <select name="characterization" class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Programado">Programado</option>
                                <option value="Reprogramado">Reprogramado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>

                    @if(!empty($isCoordAcad))
                        <div class="alert alert-info mt-3 mb-0">
                            También puedes caracterizar como Coordinación desde esta misma bandeja (si tu política lo permite).
                        </div>
                    @endif
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button class="btn btn-success">Guardar</button>
                </div>
            </form>

        </div>
    </div>
</div>
