@php
    $dates = $pr->program_request_dates ?? ($pr->dates ?? collect());
@endphp

<div class="modal fade" id="datesModal{{ $pr->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Fechas · Solicitud #{{ $pr->id }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="datesBody{{ $pr->id }}">
                @if($dates->isEmpty())
                    <div class="alert alert-warning mb-0">No hay fechas registradas.</div>
                @else
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
                @endif
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>
