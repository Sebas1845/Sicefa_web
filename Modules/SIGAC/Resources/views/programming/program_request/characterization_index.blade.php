{{-- Modules/SIGAC/Resources/views/programming/program_request/characterization_index.blade.php --}}
@extends('sigac::layouts.master')

@section('title', $titlePage ?? 'Caracterización')

@push('styles')
<style>
  .table td, .table th { vertical-align: middle !important; }

  .nowrap { white-space: nowrap; }
  .cell-sm { width: 1%; white-space: nowrap; }

  .btn-icon{
    width: 38px; height: 34px;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius: 8px;
  }
  .btn-yellow{ background:#f4b400; border-color:#f4b400; color:#111; }
  .btn-blue  { background:#1a73e8; border-color:#1a73e8; color:#fff; }
  .btn-doc   { background:#fbbc04; border-color:#fbbc04; color:#111; }
  .btn-dark-soft{ background:#111827; border-color:#111827; color:#fff; }
  .btn-purple{ background:#6f42c1; border-color:#6f42c1; color:#fff; }

  .badge-state{ font-size:.78rem; padding:.35rem .55rem; border-radius:.6rem; }

  .table-compact td, .table-compact th{
    padding: .45rem .55rem !important;
    font-size: .88rem;
  }

  .text-truncate{
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }

  .pill{
    display:inline-block; padding:.20rem .55rem; border-radius:999px;
    background: rgba(0,0,0,.05);
    font-size:.78rem;
  }

  /* DataTables look */
  .dataTables_wrapper .dataTables_filter input{
    border-radius: 8px; padding: .35rem .6rem;
    border: 1px solid rgba(0,0,0,.15);
  }
  .dataTables_wrapper .dataTables_length select{
    border-radius: 8px; padding: .25rem .5rem;
    border: 1px solid rgba(0,0,0,.15);
  }
</style>
@endpush

@section('content')
@php
  use Carbon\Carbon;

  $areaLabel = fn($areaId) => (int)$areaId === 1 ? 'CAMPESENA' : ((int)$areaId === 2 ? 'COORD. ACADÉMICA' : '—');

  // Roles (ajusta si manejas otros en SIGAC)
  $isSupport = function_exists('checkRol')
      ? (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support') || checkRol('superadmin'))
      : false;

  $isCoordAcad = function_exists('checkRol')
      ? (checkRol('sigac.academic_coordinator') || checkRol('superadmin'))
      : false;

  $isCampesena = function_exists('checkRol')
      ? checkRol('sigac.campesena')
      : false;

  if (!($isSupport || $isCoordAcad || $isCampesena)) abort(403);

  $canCharacterize = ($isSupport || $isCoordAcad || $isCampesena);

  $stateBadge = function($state){
    return match($state){
      'Preconfirmado'  => 'info',
      'Confirmado'     => 'success',
      'Devuelto'       => 'secondary',
      'Desestimado'    => 'danger',
      default          => 'dark'
    };
  };
@endphp

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">{{ $titleView ?? 'Bandeja de Caracterización (Preconfirmadas)' }}</h3>
      <div class="text-muted small mt-1">Solo solicitudes en estado <b>Preconfirmado</b>.</div>
    </div>
  </div>

  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table id="prTable" class="table table-bordered table-striped mb-0 table-compact">
          <thead>
            <tr>
              <th style="width:90px">#</th>
              <th style="width:140px" class="text-center">Estado</th>
              <th style="width:140px" class="text-center">Área</th>
              <th>Programa</th>
              <th style="width:260px">Instructor</th>
              <th style="width:200px" class="text-center">Ubicación</th>
              <th style="width:70px" class="text-center" title="Info">Info</th>
              <th style="width:70px" class="text-center" title="Fechas">Prog</th>
              <th style="width:70px" class="text-center" title="Documentos">Docs</th>
              <th style="width:260px" class="text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>
            @forelse($program_requests as $pr)
              @php
                $state = $pr->state ?? '—';
                $badge = $stateBadge($state);

                $programName = $pr->program->name ?? '—';

                $instructorName = $pr->person->fullname
                  ?? trim(($pr->person->first_name ?? '').' '.($pr->person->first_last_name ?? ''));

                $instructorEmail = $pr->person->email ?? null;
                $applicantEmail  = $pr->email ?? null;

                $munName = $pr->municipality->name ?? '—';
                $vilName = $pr->village?->name ?? null;

                $docs  = $pr->documents ?? collect();
                $dates = $pr->program_request_dates ?? ($pr->dates ?? collect());

                $startDate = optional($dates->sortBy('date')->first())->date;
                $endDate   = optional($dates->sortByDesc('date')->first())->date;

                // Horas desde fechas
                $hours = 0;
                foreach($dates as $d){
                  try{
                    if(!empty($d->start_time) && !empty($d->end_time)){
                      $st = Carbon::parse($d->start_time);
                      $en = Carbon::parse($d->end_time);
                      $hours += max(0, $st->diffInMinutes($en)) / 60;
                    }
                  }catch(\Throwable $e){}
                }
                $hoursText = (int)$hours == $hours ? (string)(int)$hours : number_format($hours, 1);

                $canCharacterizeThis = $canCharacterize && $state === 'Preconfirmado';
              @endphp

              <tr>
                <td class="nowrap">
                  <span class="badge badge-state badge-{{ $badge }}">{{ $pr->id }}</span>
                  <div class="mt-1">
                    <span class="badge badge-{{ $badge }}">{{ $state }}</span>
                  </div>
                </td>

                <td class="text-center">
                  <span class="badge badge-{{ $badge }}">{{ $state }}</span>
                </td>

                <td class="text-center">
                  <span class="badge badge-dark">{{ $areaLabel($pr->area_id) }}</span>
                </td>

                <td>
                  <div class="font-weight-bold">{{ $programName }}</div>
                  <div class="small text-muted">
                    <span class="mr-2"><b>H:</b> {{ $hoursText }}</span>
                    <span class="mr-2"><b>Fechas:</b> {{ $startDate ?? '—' }} → {{ $endDate ?? '—' }}</span>
                  </div>
                </td>

                <td>
                  <div class="font-weight-bold text-truncate" style="max-width:240px">{{ $instructorName ?: '—' }}</div>
                  @if($instructorEmail)
                    <div class="text-muted small text-truncate" style="max-width:240px">{{ $instructorEmail }}</div>
                  @endif
                  @if($applicantEmail)
                    <div class="text-muted small text-truncate" style="max-width:240px">Solicitante: {{ $applicantEmail }}</div>
                  @endif
                </td>

                <td class="text-center">
                  <div class="nowrap">{{ $munName }}</div>
                  @if($vilName)
                    <div class="small text-muted nowrap">Vereda: {{ $vilName }}</div>
                  @endif
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-icon btn-yellow"
                          data-toggle="modal" data-target="#infoModal{{ $pr->id }}"
                          title="Más información">
                    <i class="fas fa-eye"></i>
                  </button>
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-icon btn-blue"
                          data-toggle="modal" data-target="#datesModal{{ $pr->id }}"
                          title="Programación / Fechas">
                    <i class="far fa-calendar-alt"></i>
                  </button>
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-icon btn-doc"
                          data-toggle="modal" data-target="#docsModal{{ $pr->id }}"
                          title="Documentos (ZIP o individual)">
                    <i class="far fa-file-alt"></i>
                  </button>
                </td>

                <td class="text-center">
                  <div class="d-flex flex-column" style="gap:.35rem;">
                    @if($canCharacterizeThis)
                      <button type="button" class="btn btn-purple btn-sm btn-block"
                              data-toggle="modal" data-target="#characterizeModal{{ $pr->id }}">
                        Caracterizar
                      </button>
                    @else
                      <button class="btn btn-secondary btn-sm btn-block" disabled>
                        {{ $state === 'Confirmado' ? 'Caracterizado' : 'No disponible' }}
                      </button>
                    @endif

                    @if(!empty($pr->observation))
                      <button type="button" class="btn btn-outline-secondary btn-sm btn-block"
                              data-toggle="modal" data-target="#obsModal{{ $pr->id }}">
                        Ver observación
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center text-muted py-4">
                  No hay solicitudes preconfirmadas.
                </td>
              </tr>
            @endforelse
          </tbody>

        </table>
      </div>
    </div>
  </div>
</div>

{{-- ==========================================================
   MODALES FUERA DE LA TABLA (CLAVE)
   ========================================================== --}}
@foreach($program_requests as $pr)
  @php
    $docs  = $pr->documents ?? collect();
    $dates = $pr->program_request_dates ?? ($pr->dates ?? collect());
    $datesSorted = ($dates ?? collect())->sortBy('date')->values();

    $state = $pr->state ?? '—';
    $badge = $stateBadge($state);

    $programName = $pr->program->name ?? '—';
    $munName = $pr->municipality->name ?? '—';
    $vilName = $pr->village?->name ?? null;

    $instructorName = $pr->person->fullname
      ?? trim(($pr->person->first_name ?? '').' '.($pr->person->first_last_name ?? ''));

    $instructorEmail = $pr->person->email ?? null;
    $applicantEmail  = $pr->email ?? null;

    $canCharacterizeThis = $canCharacterize && $state === 'Preconfirmado';
  @endphp

  {{-- ===========================
     MODAL: FECHAS
     =========================== --}}
  <div class="modal fade" id="datesModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Programación · Solicitud #{{ $pr->id }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          @if($datesSorted->isEmpty())
            <div class="alert alert-warning mb-0">No hay fechas registradas.</div>
          @else
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Hora inicio</th>
                    <th>Hora fin</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($datesSorted as $d)
                    <tr>
                      <td>{{ $d->date ?? '' }}</td>
                      <td>{{ $d->start_time ?? '' }}</td>
                      <td>{{ $d->end_time ?? '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  {{-- ===========================
     MODAL: INFO (sin depender del include)
     =========================== --}}
  <div class="modal fade" id="infoModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title"><strong>Información de la solicitud</strong> · #{{ $pr->id }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Programa</h6>
              <p class="mb-0">{{ $programName }}</p>
            </div>

            <div class="col-md-6">
              <h6 class="text-muted mb-1">Estado</h6>
              <p class="mb-0"><span class="badge badge-{{ $badge }}">{{ $state }}</span></p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Instructor</h6>
              <p class="mb-0">{{ $instructorName ?: '—' }}</p>
              @if($instructorEmail)
                <small class="text-muted d-block">{{ $instructorEmail }}</small>
              @endif
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Solicitante</h6>
              <p class="mb-0">{{ $pr->applicant ?? '—' }}</p>
              @if($applicantEmail)
                <small class="text-muted d-block">{{ $applicantEmail }}</small>
              @endif
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Empresa</h6>
              <p class="mb-0">{{ $pr->company?->name ?? $pr->empresa ?? $pr->company_name ?? 'No registrada' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Dirección</h6>
              <p class="mb-0">{{ $pr->address ?? 'No registrada' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Teléfono</h6>
              <p class="mb-0">{{ $pr->telephone ?? 'No registrado' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Ubicación</h6>
              <p class="mb-0">{{ $munName }} @if($vilName) <span class="text-muted">· Vereda: {{ $vilName }}</span> @endif</p>
            </div>

            @if(!empty($pr->observation))
              <div class="col-12 mt-3">
                <h6 class="text-muted mb-1">Observaciones</h6>
                <div class="alert alert-secondary mb-0">{{ $pr->observation }}</div>
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

  {{-- ===========================
     MODAL: DOCUMENTOS (VER/DESCARGAR + ZIP)
     (IMPORTANTE: prId + docId)
     =========================== --}}
  <div class="modal fade" id="docsModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Documentos · Solicitud #{{ $pr->id }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          @if($docs->isEmpty())
            <div class="alert alert-warning mb-0">Esta solicitud no tiene documentos.</div>
          @else
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
              <div class="text-muted small">
                Puedes descargar todo en ZIP o ver/descargar individualmente.
              </div>
              <a class="btn btn-outline-primary btn-sm"
                 href="{{ route('sigac.support.programming.program_request.download', $pr->id) }}">
                Descargar ZIP (todo)
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Documento</th>
                    <th style="width:230px" class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($docs as $doc)
                    <tr>
                      <td>{{ $doc->name ?? ('Documento #'.$doc->id) }}</td>
                      <td class="text-center">
                        {{-- VER (inline) --}}
                        <a class="btn btn-outline-info btn-sm"
                           href="{{ route('sigac.programming.program_request.document.view', [$pr->id, $doc->id]) }}"
                           target="_blank" rel="noopener">
                          Ver
                        </a>

                        {{-- DESCARGAR --}}
                        <a class="btn btn-outline-secondary btn-sm"
                           href="{{ route('sigac.programming.program_request.document.download', [$pr->id, $doc->id]) }}">
                          Descargar
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  {{-- ===========================
     MODAL: OBSERVACIÓN (si existe)
     =========================== --}}
  @if(!empty($pr->observation))
    <div class="modal fade" id="obsModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Observación · Solicitud #{{ $pr->id }}</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-secondary mb-0">{{ $pr->observation }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- ===========================
     MODAL: CARACTERIZAR
     =========================== --}}
  @if($canCharacterizeThis)
    <div class="modal fade" id="characterizeModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <form method="POST" action="{{ route('sigac.support.programming.program_request.characterization.store', $pr->id) }}">
            @csrf

            <div class="modal-header">
              <h5 class="modal-title">Caracterizar – Solicitud #{{ $pr->id }}</h5>
              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body">
              <div class="alert alert-info mb-3">
                Al confirmar: se guarda la caracterización y se pasa a <b>Confirmado</b> (según tu backend).
                Si falta algo, mejor usa <b>Devolver</b>.
              </div>

              <div class="form-row">
                <div class="form-group col-md-4">
                  <label>Código curso (Ficha) <span class="text-danger">*</span></label>
                  <input type="text" name="code_course" class="form-control"
                         value="{{ old('code_course') ?? ($pr->code_course ?? '') }}"
                         placeholder="Ej: 2698123" required>
                </div>

                <div class="form-group col-md-4">
                  <label>Código empresa</label>
                  <input type="text" name="code_empresa" class="form-control"
                         value="{{ old('code_empresa') ?? ($pr->code_empresa ?? '') }}">
                </div>

                <div class="form-group col-md-4">
                  <label>Fecha inscripción</label>
                  <input type="date" name="date_inscription" class="form-control"
                         value="{{ old('date_inscription') ?? ($pr->date_inscription ?? '') }}">
                </div>
              </div>

              <hr>

              <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="text-muted">
                  Vas a confirmar la caracterización de la solicitud <b>#{{ $pr->id }}</b>.
                </div>
                <button type="submit" class="btn btn-primary">
                  Confirmar caracterización
                </button>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

@endforeach
@endsection

@push('scripts')
{{-- jQuery + Bootstrap4 + DataTables (AdminLTE) --}}
<script src="{{ asset('AdminLTE/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('AdminLTE/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<script src="{{ asset('AdminLTE/plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>

<script>
  $(function () {
    $('#prTable').DataTable({
      responsive: true,
      autoWidth: false,
      pageLength: 10,
      order: [[0, 'desc']],
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Sin registros",
        infoFiltered: "(filtrado de _MAX_)",
        zeroRecords: "No hay resultados"
      }
    });
  });
</script>
@endpush
