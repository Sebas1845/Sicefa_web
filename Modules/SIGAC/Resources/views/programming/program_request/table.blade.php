{{-- Modules/SIGAC/Resources/views/programming/program_request/table.blade.php --}}
@extends('sigac::layouts.master')

@section('title', $titlePage ?? 'Solicitudes')

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

  .badge-state{ font-size:.78rem; padding:.35rem .55rem; border-radius:.6rem; }

  .table-compact td, .table-compact th{
    padding: .45rem .55rem !important;
    font-size: .88rem;
  }

  .text-truncate{
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
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
  $isInstructor = function_exists('checkRol') ? checkRol('sigac.instructor') : false;
  $isCoordAcad  = function_exists('checkRol') ? (checkRol('sigac.academic_coordinator') || checkRol('superadmin')) : false;
  $isCampesena  = function_exists('checkRol') ? checkRol('sigac.campesena') : false;

  // Apoyo (según tus roles actuales)
  $isSupport = function_exists('checkRol')
      ? (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support'))
      : false;

  $roleRoute = $roleRoute ?? (
      function_exists('getRoleRouteName')
          ? getRoleRouteName(\Illuminate\Support\Facades\Route::currentRouteName())
          : 'instructor'
  );

  // aprobar -> pasa a Preconfirmado
  $canApprove = ($isCoordAcad || $isCampesena);

  // caracterizar -> Apoyo y/o Coordinación
  $canCharacterizeRole = ($isSupport || $isCoordAcad || $isCampesena);

  $stateBadge = function($state){
    return match($state){
      'Pendiente'      => 'warning',
      'Preconfirmado'  => 'info',
      'Confirmado'     => 'success',
      'Devuelto'       => 'secondary',
      'Desestimado'    => 'danger',
      default          => 'dark'
    };
  };

  // helper: detectar extensión del documento (por nombre o path)
  $docExt = function($doc){
    $candidate = '';
    if (isset($doc->path) && is_string($doc->path) && $doc->path !== '') $candidate = $doc->path;
    elseif (isset($doc->name) && is_string($doc->name) && $doc->name !== '') $candidate = $doc->name;

    $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
    return $ext;
  };

  // helper: tipos "visibles" inline en navegador
  $isViewable = function(string $ext){
    return in_array($ext, ['pdf','jpg','jpeg','png','gif','webp']);
  };
@endphp

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">{{ $titleView ?? 'Solicitudes de Programación' }}</h3>
    </div>
    <div>
      <a class="btn btn-outline-secondary btn-sm"
         href="{{ route("sigac.$roleRoute.programming.program_request.index") }}">
        Crear / Buscar
      </a>
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
              <th style="width:260px">Instructor</th>
              <th>Programa</th>
              <th style="width:160px" class="text-center">Ubicación</th>
              <th style="width:52px" class="text-center" title="Info solicitante">Info</th>
              <th style="width:52px" class="text-center" title="Programación">Prog</th>
              <th style="width:52px" class="text-center" title="Documentos">Docs</th>
              <th style="width:210px" class="text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>
            @foreach($program_requests as $pr)
              @php
                $state = $pr->state ?? '—';
                $badge = $stateBadge($state);

                $instructorName = $pr->person->fullname
                  ?? trim(($pr->person->first_name ?? '').' '.($pr->person->first_last_name ?? ''));

                $programName  = $pr->program->name ?? '—';
                $specialName  = $pr->special_program->name ?? null;
                $munName      = $pr->municipality->name ?? '—';
                $vilName      = $pr->village?->name ?? null;

                $docs  = $pr->documents ?? collect();
                $dates = $pr->dates ?? collect();

                $startDate = optional($dates->sortBy('date')->first())->date;
                $endDate   = optional($dates->sortByDesc('date')->first())->date;

                // Horas desde fechas (suma)
                $hours = 0;
                foreach($dates as $d){
                  try{
                    if(!empty($d->start_time) && !empty($d->end_time)){
                      $st = \Carbon\Carbon::parse($d->start_time);
                      $en = \Carbon\Carbon::parse($d->end_time);
                      $hours += max(0, $st->diffInMinutes($en)) / 60;
                    }
                  }catch(\Throwable $e){}
                }
                $hoursText = (int)$hours == $hours ? (string)(int)$hours : number_format($hours, 1);

                $rubroName = $pr->budgetItem->name ?? ($pr->budgetItem->rubric_name ?? '—');
                $cupo      = $pr->quotas ?? $pr->cupo ?? '—';

                $canCharacterizeThis = $canCharacterizeRole && $state === 'Preconfirmado';
                $isAlreadyConfirmed  = $state === 'Confirmado';
                $canApproveThis      = $canApprove && $state === 'Pendiente';
              @endphp

              <tr>
                <td class="nowrap">
                  <span class="badge badge-state badge-{{ $badge }}">{{ $pr->id }}</span>
                  <div class="mt-1">
                    <span class="badge badge-{{ $badge }}">{{ $state }}</span>
                  </div>
                </td>

                <td>
                  <div class="font-weight-bold text-truncate" style="max-width:240px">{{ $instructorName ?: '—' }}</div>
                  <div class="text-muted small text-truncate" style="max-width:240px">{{ $pr->person->email ?? '' }}</div>
                </td>

                <td>
                  <div class="font-weight-bold">{{ $programName }}</div>

                  <div class="small text-muted">
                    <span class="mr-2"><b>H:</b> {{ $hoursText }}</span>
                    <span class="mr-2"><b>Cupo:</b> {{ $cupo }}</span>
                    <span class="mr-2"><b>Fechas:</b> {{ $startDate ?? '—' }} → {{ $endDate ?? '—' }}</span>
                  </div>

                  <div class="small text-muted text-truncate" style="max-width: 820px;">
                    @if($specialName)
                      <span class="mr-2"><b>Especial:</b> {{ $specialName }}</span>
                    @endif
                    <span class="mr-2"><b>Rubro:</b> {{ $rubroName }}</span>
                    @if($vilName)
                      <span class="mr-2"><b>Vereda:</b> {{ $vilName }}</span>
                    @endif
                  </div>
                </td>

                <td class="text-center nowrap">{{ $munName }}</td>

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
                  @if($isAlreadyConfirmed)
                    <button class="btn btn-secondary btn-sm btn-block" disabled>Caracterizado</button>
                  @else
                    @if($canApproveThis)
                      <form method="POST"
                            action="{{ route("sigac.$roleRoute.programming.program_request.approve", $pr->id) }}"
                            onsubmit="return confirm('¿Aprobar y pasar a Preconfirmado?');"
                            class="mb-1">
                        @csrf
                        <button class="btn btn-success btn-sm btn-block">Aprobar</button>
                      </form>
                    @endif

                    @if($canCharacterizeThis)
                      <button type="button" class="btn btn-warning btn-sm btn-block mb-1"
                              data-toggle="modal" data-target="#characterizeModal{{ $pr->id }}">
                        Caracterizar
                      </button>

                      <button type="button" class="btn btn-danger btn-sm btn-block"
                              data-toggle="modal" data-target="#returnModal{{ $pr->id }}">
                        Devolver
                      </button>
                    @endif
                  @endif
                </td>
              </tr>
            @endforeach
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
    $dates = $pr->dates ?? collect();
    $state = $pr->state ?? '—';
    $canCharacterizeThis = $canCharacterizeRole && $state === 'Preconfirmado';
  @endphp

  {{-- ===========================
     MODAL: INFO
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
              <h6 class="text-muted mb-1">Empresa</h6>
              <p class="mb-0">{{ $pr->company?->name ?? $pr->empresa ?? $pr->company_name ?? 'No registrada' }}</p>
            </div>

            <div class="col-md-6">
              <h6 class="text-muted mb-1">Dirección</h6>
              <p class="mb-0">{{ $pr->address ?? 'No registrada' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Nombre del solicitante</h6>
              <p class="mb-0">{{ $pr->applicant ?? 'No registrado' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Correo</h6>
              <p class="mb-0">{{ $pr->email ?? 'No registrado' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Teléfono</h6>
              <p class="mb-0">{{ $pr->telephone ?? 'No registrado' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Área (solo aquí)</h6>
              <p class="mb-0">{{ $pr->area?->name ?? '—' }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Rubro</h6>
              <p class="mb-0">{{ $pr->budgetItem?->name ?? ($pr->budgetItem?->rubric_name ?? '—') }}</p>
            </div>

            <div class="col-md-6 mt-3">
              <h6 class="text-muted mb-1">Cupo</h6>
              <p class="mb-0">{{ $pr->quotas ?? $pr->cupo ?? '—' }}</p>
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
     MODAL: PROGRAMACIÓN / FECHAS
     =========================== --}}
  <div class="modal fade" id="datesModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Programación · Solicitud #{{ $pr->id }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          @if($dates->isEmpty())
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
                  @foreach($dates as $d)
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
     MODAL: DOCUMENTOS (ZIP + individuales)
     - Ver solo si es PDF/imagen
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
                 href="{{ route("sigac.$roleRoute.programming.program_request.download", $pr->id) }}">
                Descargar ZIP (todo)
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Documento</th>
                    <th style="width:240px" class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($docs as $doc)
                    @php
                      $name = $doc->name ?? ('Documento #'.$doc->id);
                      $ext  = $docExt($doc);
                      $viewable = $isViewable($ext);
                    @endphp
                    <tr>
                      <td class="nowrap">
                        {{ $name }}
                        @if($ext)
                          <span class="badge badge-light ml-2">.{{ $ext }}</span>
                        @endif
                      </td>
                      <td class="text-center">
                        @if($viewable)
                          {{-- VER (inline) --}}
                          <a class="btn btn-outline-info btn-sm"
                             href="{{ route('sigac.programming.program_request.document.view', [$pr->id, $doc->id]) }}"
                             target="_blank" rel="noopener">
                            Ver
                          </a>
                        @endif

                        {{-- DESCARGAR (siempre) --}}
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
     MODAL: DEVOLVER
     =========================== --}}
  <div class="modal fade" id="returnModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">Devolver · Solicitud #{{ $pr->id }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <form method="POST"
              action="{{ route('sigac.support.programming.program_request.characterization.devolution', $pr->id) }}">
          @csrf
          <div class="modal-body">
            <div class="form-group mb-0">
              <label>Observación</label>
              <textarea name="observation" class="form-control" rows="4" maxlength="5000" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger" type="submit">Devolver</button>
          </div>
        </form>

      </div>
    </div>
  </div>

  {{-- ===========================
     MODAL: CARACTERIZAR
     =========================== --}}
  @if($canCharacterizeThis)
    <div class="modal fade" id="characterizeModal{{ $pr->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">

          <form method="POST"
                action="{{ route('sigac.support.programming.program_request.characterization.store', $pr->id) }}">
            @csrf

            <div class="modal-header">
              <h5 class="modal-title">Caracterizar – Solicitud #{{ $pr->id }}</h5>
              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <label class="form-label">Ficha (code_course)</label>
                  <input name="code_course" class="form-control"
                         value="{{ old('code_course') ?? ($pr->code_course ?? '') }}"
                         placeholder="Si vacío, se genera automáticamente">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Código empresa (code_empresa)</label>
                  <input name="code_empresa" class="form-control"
                         value="{{ old('code_empresa') ?? ($pr->code_empresa ?? '') }}">
                </div>

                <div class="col-md-6 mt-2">
                  <label class="form-label">Fecha inscripción</label>
                  <input type="date" name="date_inscription" class="form-control"
                         value="{{ old('date_inscription') ?? ($pr->date_inscription ?? '') }}">
                </div>

                <div class="col-12 mt-3">
                  <div class="alert alert-info mb-0">
                    Al guardar: se confirma, crea/actualiza Course por ficha, crea InstructorProgram por cada fecha y (si existe) importa aprendices desde el “Cargue Masivo”.
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-secondary" type="button" data-dismiss="modal">Cerrar</button>
              <button class="btn btn-success" type="submit">Guardar</button>
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
