@extends('gdf::layouts.masteruser')
@section('title', 'SITRAV | Programas aprobados')

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0">Programas aprobados (SIGAC)</h4>
      <small class="text-muted">Selecciona un programa para iniciar la solicitud SITRAV.</small>
    </div>

    <form method="GET" action="{{ route('gdf.instructor.sitrav.programs.index') }}" class="d-flex gap-2">
      <select name="state" class="form-select form-select-sm" style="min-width: 180px;">
        @foreach (['approved' => 'Aprobado', 'confirmed' => 'Confirmado', 'characterized' => 'Caracterizado'] as $k => $lbl)
          <option value="{{ $k }}" @selected(($state ?? 'approved') === $k)>{{ $lbl }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-primary" type="submit">Filtrar</button>
    </form>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 90px;">#</th>
              <th>Programa</th>
              <th style="width: 140px;">Estado</th>
              <th>Fechas</th>
              <th class="text-end" style="width: 170px;">Acción</th>
            </tr>
          </thead>

          <tbody>
          @forelse($programRequests as $pr)
            @php
              $dates = $pr->programRequestDates ?? collect();
              $min = $dates->min('date');
              $max = $dates->max('date');
              $count = $dates->count();
            @endphp

            <tr>
              <td class="fw-semibold">{{ $pr->id }}</td>

              <td>
                <div class="fw-semibold">{{ $pr->name ?? ('Solicitud #' . $pr->id) }}</div>
                <small class="text-muted">
                  {{ $count }} fecha(s)
                  @if($min && $max) · {{ $min }} → {{ $max }} @endif
                </small>
              </td>

              <td>
                <span class="badge bg-success text-uppercase">{{ $pr->state }}</span>
              </td>

              <td>
                @if($count)
                  <small class="text-muted">
                    @foreach($dates->take(3) as $d)
                      <span class="badge bg-light text-dark border">
                        {{ $d->date }} ({{ substr($d->start_time,0,5) }}-{{ substr($d->end_time,0,5) }})
                      </span>
                    @endforeach

                    @if($count > 3)
                      <span class="text-muted">+{{ $count-3 }} más</span>
                    @endif
                  </small>
                @else
                  <span class="text-muted">Sin fechas</span>
                @endif
              </td>

              <td class="text-end">
                <a href="{{ route('gdf.instructor.sitrav.programs.create', $pr->id) }}"
                   class="btn btn-sm btn-primary">
                  Iniciar SITRAV
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                No hay programas en estado "{{ $state ?? 'approved' }}".
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($programRequests->hasPages())
      <div class="card-footer">
        {{ $programRequests->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
