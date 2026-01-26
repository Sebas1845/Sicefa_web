  <form method="GET" class="mb-5">
            <div class="d-flex justify-content-center">
                <div class="card border-0 shadow-lg w-100" style="max-width: 520px;">
                    <div class="card-body px-4 py-4">

                        <!-- Título -->
                        <div class="text-center mb-3">
                            <span class="fw-bold text-primary text-uppercase small">
                                Selección de fecha y hora
                            </span>
                        </div>

                        <label class="form-label fw-semibold text-muted mb-2">
                            Fecha y hora de asistencia
                        </label>

                        <!-- Input + botón -->
                        <div class="d-flex gap-2 align-items-stretch">

                            <!-- Campo fecha -->
                            <div class="flex-grow-1">
                                <div class="input-group input-group-lg shadow-sm rounded">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-calendar-alt text-primary fs-4"></i>
                                    </span>
                                    <input type="datetime-local" name="date" id="attendanceDate"
                                        class="form-control border-start-0 fw-semibold" style="font-size: 1.1rem;"
                                        value="{{ request('date') ?? $fecha->format('Y-m-d\TH:i') }}"
                                        max="{{ now()->format('Y-m-d\TH:i') }}" onchange="this.form.submit()" />

                                </div>
                            </div>

                            <!-- Botón libro -->
                            <button type="button" class="btn btn-outline-primary shadow-sm px-3" data-bs-toggle="modal"
                                data-bs-target="#infoProgramacion" title="Información de programación">
                                <i class="fas fa-book fs-4"></i>
                            </button>

                        </div>

                        <!-- Ayuda -->
                        <div class="form-text mt-2 text-center">
                            Selecciona la <strong>fecha</strong> y la <strong>hora</strong> para consultar la programación
                        </div>

                    </div>
                </div>
            </div>
        </form>