<div class="modal fade" id="dates{{ $pr->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Programación solicitada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                @php
                    $dates = $pr->dates ?? collect();
                @endphp

                @if($dates->isEmpty())
                    <div class="alert alert-warning">
                        No hay fechas registradas para esta solicitud.
                    </div>
                @else
                    <table class="table table-sm table-bordered">
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
                                    <td>{{ $d->date }}</td>
                                    <td>{{ $d->start_time }}</td>
                                    <td>{{ $d->end_time }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>
