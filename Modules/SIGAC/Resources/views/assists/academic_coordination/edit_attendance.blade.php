@extends('sigac::layouts.master')

@section('content')
    <div class="container-fluid">

        {{-- Header compacto --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <small class="text-muted">Coordinación Académica · Edición de asistencias</small>
            </div>

            <div class="d-flex align-items-center">
                <span id="rangeBadge" class="badge bg-secondary d-none mr-2"></span>
                <span id="countBadge" class="badge bg-light text-dark border d-none"></span>
            </div>
        </div>

        {{-- CARD FILTROS --}}
        <div class="card shadow-sm mb-2" style="border-radius:14px;">
            <div class="card-body py-3">

                <style>
                    /* --- Layout estable (BS4/BS5) --- */
                    .ui-row {
                        margin-left: -8px;
                        margin-right: -8px;
                    }

                    .ui-col {
                        padding-left: 8px;
                        padding-right: 8px;
                    }

                    /* Controles con altura igual */
                    .control-h {
                        height: 40px !important;
                        min-height: 40px !important;
                        line-height: 40px !important;
                        padding-top: 0 !important;
                        padding-bottom: 0 !important;
                    }

                    /* Dropdown botón como input */
                    #instructorBtn {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        padding-left: .75rem !important;
                        padding-right: .75rem !important;
                    }

                    #instructorBtn .btn-text {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        display: block;
                        width: calc(100% - 18px);
                    }

                    #instructorBtn::after {
                        margin-left: .5rem;
                    }

                    /* Dropdown menu full */
                    .control-shell .dropdown-menu {
                        width: 100%;
                    }

                    /* Chips en grid consistente */
                    .chip-grid {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: .4rem;
                    }

                    @media (max-width: 575.98px) {
                        .chip-grid {
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                        }
                    }

                    .chip-grid .btn {
                        width: 100%;
                        border-radius: 999px;
                    }

                    /* Barra inferior (msg + badges) */
                    .status-bar {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        margin-top: .85rem;
                    }

                    .status-left {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                    }

                    .status-right {
                        margin-left: auto;
                        /* BS4 compatible */
                        display: flex;
                        align-items: center;
                    }

                    .status-left>* {
                        margin-right: .5rem;
                        margin-bottom: .35rem;
                    }

                    .status-right>* {
                        margin-left: .5rem;
                        margin-bottom: .35rem;
                    }

                    /* Meta centrada */
                    .meta-grid {
                        display: grid;
                        grid-template-columns: 220px minmax(260px, 1fr) 260px;
                        gap: .75rem;
                        justify-content: center;
                        margin-top: .9rem;
                    }

                    @media (max-width: 991.98px) {
                        .meta-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    .meta-card {
                        border-radius: 14px;
                        background: #f8f9fa;
                        border: 1px solid rgba(0, 0, 0, .08);
                        padding: .75rem .9rem;
                        text-align: center;
                    }

                    .meta-card .k {
                        font-size: .78rem;
                        color: #6c757d;
                    }

                    .meta-card .v {
                        font-weight: 700;
                        line-height: 1.15;
                    }

                    .meta-card .sub {
                        font-size: .78rem;
                        color: #6c757d;
                        margin-top: .15rem;
                    }
                </style>

                {{-- FILTROS (alineados) --}}
                <div class="row ui-row align-items-end">

                    {{-- Instructor --}}
                    <div class="col-xl-4 col-lg-4 col-md-6 ui-col mb-2">
                        <label class="form-label small text-muted mb-1">Instructor</label>
                        <div class="control-shell">
                            <div class="dropdown w-100">
                                <button id="instructorBtn"
                                    class="btn btn-outline-secondary w-100 text-start dropdown-toggle control-h"
                                    type="button" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"
                                    style="border-radius:12px;">
                                    <span class="btn-text">Seleccione instructor…</span>
                                </button>
                                <small class="text-muted">Selecciona un Instructor</small>
                                <div class="dropdown-menu p-2 shadow"
                                    style="border-radius:12px;max-height:320px;overflow:auto;">
                                    <input id="instructorSearch" type="text" class="form-control form-control-sm mb-2"
                                        placeholder="Buscar instructor…" style="border-radius:10px;">

                                    <div id="instructorList" class="list-group" style="border-radius:10px;">
                                        @foreach ($instructors as $p)
                                            <button type="button" class="list-group-item list-group-item-action"
                                                data-instructor-id="{{ $p->id }}"
                                                data-instructor-name="{{ $p->full_name }}">
                                                {{ $p->full_name }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="instructor_id" value="">
                    </div>

                    {{-- Fecha/Hora --}}
                    <div class="col-xl-3 col-lg-3 col-md-6 ui-col mb-2">
                        <label class="form-label small text-muted mb-1">Fecha y hora</label>
                        <div class="control-shell">
                            <input id="date" type="datetime-local" class="form-control control-h"
                                style="border-radius:12px;">
                        </div>
                        <small class="text-muted">No permite fecha futura</small>
                    </div>
                    {{-- Estado --}}
                    <div class="col-xl-3 col-lg-3 col-md-12 ui-col mb-2">
                        <label class="form-label small text-muted mb-1">Estado</label>
                        <div class="chip-grid">
                            <button type="button" class="btn btn-sm btn-outline-dark filter-btn active"
                                data-status="all">Todos</button>

                            <button type="button" class="btn btn-sm btn-outline-success filter-btn"
                                data-status="present">Presente</button>

                            <!-- Tarde: AMARILLO -->
                            <button type="button" class="btn btn-sm btn-outline-warning filter-btn"
                                data-status="late">Tarde</button>

                            <!-- Ausente: ROJO -->
                            <button type="button" class="btn btn-sm btn-outline-danger filter-btn"
                                data-status="absent">Ausente</button>

                            <!-- Excusa: AZUL -->
                            <button type="button" class="btn btn-sm btn-outline-primary filter-btn"
                                data-status="excused">Excusa</button>

                            <!-- Retiro: GRIS -->
                            <button type="button" class="btn btn-sm btn-outline-secondary filter-btn"
                                data-status="withdrawn">Retiro</button>
                        </div>
                    </div>

                    {{-- Buscar --}}
                    <div class="col-xl-2 col-lg-2 col-md-12 ui-col mb-2">
                        <label class="form-label small text-muted mb-1">Buscar</label>
                        <div class="control-shell">
                            <input id="search" type="text" class="form-control form-control-sm control-h"
                                placeholder="Aprendiz / documento" style="border-radius:12px;" disabled>
                        </div>
                    </div>

                </div>

                {{-- Barra de estado (alineada) --}}
                <div class="status-bar">
                    <div class="status-left">
                        <span id="msg" class="small text-muted"></span>
                        <span id="loadingPill" class="badge bg-primary d-none">Cargando…</span>
                        <span id="savedPill" class="badge bg-success d-none">Guardado</span>
                        <span id="errorPill" class="badge bg-danger d-none">Error</span>
                    </div>

                    <div class="status-right">
                        <span id="rangeBadge" class="badge bg-secondary d-none"></span>
                        <span id="countBadge" class="badge bg-light text-dark border d-none"></span>
                    </div>
                </div>

                {{-- Meta centrada --}}
                <div id="metaBar" class="d-none">
                    <div class="meta-grid">
                        <div class="meta-card">
                            <div class="k">Ficha</div>
                            <div class="v" id="metaFicha">—</div>
                        </div>

                        <div class="meta-card">
                            <div class="k">Programa</div>
                            <div class="v text-truncate" id="metaPrograma" style="max-width:100%;margin:0 auto;">—</div>
                        </div>

                        <div class="meta-card">
                            <div class="k">Fecha / Hora</div>
                            <div class="v" id="metaFechaHora">—</div>
                            <div class="sub" id="metaFranja">—</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- CARD TABLA --}}
        <div class="card shadow-sm" style="border-radius:14px;">
            <div class="card-body py-2">
                <div id="tableWrap" class="table-responsive">
                    <div class="alert alert-info mb-0 py-2" style="border-radius:12px;">
                        Selecciona instructor y fecha/hora.
                    </div>
                </div>
            </div>
        </div>

    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- MODAL EDICIÓN --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <h6 class="modal-title">Editar asistencia</h6>
                    <button type="button" class="close btn btn-light" data-dismiss="modal" data-bs-dismiss="modal"
                        aria-label="Cerrar" style="border-radius:10px;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="modalRecordId">

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <select id="modalStatus" class="form-control" style="border-radius:12px;">
                                <option value="present">PRESENTE</option>
                                <option value="late">TARDE</option>
                                <option value="absent">AUSENTE</option>
                                <option value="excused">EXCUSA</option>
                                <option value="withdrawn">RETIRO</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora (opcional)</label>
                            <input id="modalTime" type="time" class="form-control" style="border-radius:12px;">
                            <small class="text-muted">Debe estar dentro de la franja. Si no la cambias, se
                                mantiene.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Evidencia (opcional)</label>
                            <input id="modalEvidence" type="file" class="form-control" style="border-radius:12px;">
                            <div class="mt-2" id="modalEvidenceLinkWrap" style="display:none;">
                                <a id="modalEvidenceLink" href="#" target="_blank"
                                    class="btn btn-sm btn-outline-secondary" style="border-radius:10px;">
                                    Ver evidencia actual
                                </a>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observación</label>
                            <textarea id="modalObs" class="form-control" rows="4" style="border-radius:12px;"
                                placeholder="Escribe la observación..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <span id="modalMsg" class="me-auto text-muted small"></span>
                    <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal"
                        style="border-radius:10px;">
                        Cancelar
                    </button>
                    <button type="button" id="modalSave" class="btn btn-primary" style="border-radius:10px;">
                        Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- MODAL VER OBSERVACIÓN --}}
    <div class="modal fade" id="obsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <h6 class="modal-title">Observación</h6>
                    <button type="button" class="close btn btn-light" data-dismiss="modal" data-bs-dismiss="modal"
                        aria-label="Cerrar" style="border-radius:10px;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div id="obsModalBody" class="text-muted" style="white-space:pre-wrap;word-break:break-word;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal"
                        style="border-radius:10px;">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
   <script>
  window.AttendanceCfg = {
    routes: {
      viewEdit: @json(route('sigac.academic_coordination.attendancesrecord.view_edit_page')),
      edit: @json(route('sigac.academic_coordination.attendancesrecord.edit')),
    },
    csrf: @json(csrf_token())
  };
</script>

<script src="{{ asset('modules/sigac/js/assists/academic_coordination/attendance-edit.js') }}"></script>
@endsection

