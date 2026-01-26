@php
    $dates = $pr->dates ?? collect();

    $fmtDate = function ($val) {
        try { return $val ? \Carbon\Carbon::parse($val)->format('d/m/Y') : ''; }
        catch (\Throwable $e) { return (string) $val; }
    };

    $fmtTime = function ($val) {
        try { return $val ? \Carbon\Carbon::parse($val)->format('H:i') : ''; }
        catch (\Throwable $e) { return (string) $val; }
    };
@endphp

<div class="modal fade" id="datesModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Programación · Solicitud #{{ $pr->id }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                @if($dates->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No hay fechas registradas para esta solicitud.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:34%">Fecha</th>
                                    <th style="width:33%">Hora inicio</th>
                                    <th style="width:33%">Hora fin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dates as $d)
                                    <tr>
                                        <td>{{ $fmtDate($d->date ?? null) }}</td>
                                        <td>{{ $fmtTime($d->start_time ?? null) }}</td>
                                        <td>{{ $fmtTime($d->end_time ?? null) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>
