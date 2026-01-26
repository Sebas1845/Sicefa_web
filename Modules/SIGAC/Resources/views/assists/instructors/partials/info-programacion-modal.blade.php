 <div class="modal fade" id="infoProgramacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content border-0 shadow-lg">

                <!-- HEADER -->
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-book me-2"></i>
                        Información de programación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- BODY -->
                <div class="modal-body px-4 py-3">

                    <!-- Fecha seleccionada -->
                    <div class="alert alert-info text-center mb-4">
                        <strong>Fecha seleccionada</strong><br>
                        {{ $fecha->format('d/m/Y') }} · {{ $fecha->format('H:i') }}
                    </div>

                    @if ($programs->count())
                        <p class="fw-semibold mb-3 text-center text-success">
                            <i class="fas fa-check-circle me-1"></i>
                            Programación encontrada para esta franja horaria
                        </p>

                        <div class="list-group list-group-flush">
                            @foreach ($programs as $program)
                                <div class="list-group-item border rounded mb-3 shadow-sm cursor-pointer"
                                    style="cursor:pointer"
                                    onclick="seleccionarProgramacion(
        '{{ $fecha->format('Y-m-d') }}',
        '{{ $program->start_time }}'
    )">

                                    <!-- Programa / Curso -->
                                    <div class="fw-bold text-primary mb-1">
                                        <i class="fas fa-book me-1"></i>
                                        {{ $program->course->code ?? 'Sin código' }} –
                                        {{ $program->course->program->name ?? 'Sin programa' }}
                                    </div>

                                    <!-- Actividad -->
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-tasks me-1"></i>
                                        <strong>Actividad:</strong>
                                        {{ $program->activity_name ?? 'Sin actividad' }}
                                    </div>

                                    <!-- Horario -->
                                    <div class="small mb-1">
                                        <i class="fas fa-clock me-1"></i>
                                        <strong>Horario:</strong>
                                        {{ $program->start_time }} – {{ $program->end_time }}
                                    </div>

                                    <!-- Ambiente -->
                                    <div class="small">
                                        <strong>
                                            <i class="fas fa-map-marker-alt me-1 text-secondary"></i>
                                            Ambiente asignado:
                                        </strong>

                                        @if ($program->environments->count())
                                            {{ $program->environments->pluck('name')->join(', ') }}
                                        @else
                                            <span class="text-muted">No asignado</span>
                                        @endif
                                    </div>


                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- ESTADO VACÍO PROFESIONAL -->
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>

                            <h6 class="fw-bold">
                                Sin programación asignada
                            </h6>

                            <p class="text-muted small mt-2">
                                Según la programación académica,<br>
                                en esta fecha y franja horaria <strong>no tiene actividades asignadas</strong>
                                con ningún programa.
                            </p>
                        </div>
                    @endif

                    <div class="alert alert-secondary small mt-4 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        La asistencia se registra según la fecha y franja horaria seleccionadas.
                    </div>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button class="btn btn-primary" data-bs-dismiss="modal">
                        Entendido
                    </button>
                </div>

            </div>
        </div>
    </div>
