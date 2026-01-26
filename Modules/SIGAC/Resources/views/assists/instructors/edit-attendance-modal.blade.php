<!-- Modal Editar Asistencia -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1"
    aria-labelledby="editAttendanceModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">

            <!-- Header -->
            <div class="modal-header bg-success text-white rounded-top-4">
                <h5 class="modal-title" id="editAttendanceModalLabel">
                    <i class="fas fa-pen-to-square me-2"></i>Editar asistencia
                </h5>
                <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <form id="editAttendanceForm" method="POST"
                action="{{ route('sigac.instructor.attendancesrecord.update') }}"
                enctype="multipart/form-data">

                @csrf
                <input type="hidden" name="id" id="attendanceId">

                <div class="modal-body p-4">

                    <!-- Aprendiz -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Aprendiz</label>
                        <input type="text" class="form-control bg-light"
                            id="apprenticeName" readonly>
                    </div>

                    <!-- Estado -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Estado</label>
                        <select class="form-select" id="attendanceStatus"
                            name="attendance_status" required>
                            <option value="">-- Seleccione un estado --</option>
                            <option value="present">Presente</option>
                            <option value="late">Tarde</option>
                            <option value="absent">Ausente</option>
                            <option value="withdrawn">Retirado</option>
                            <option value="excused">Excusa</option>
                        </select>
                    </div>

                    <!-- Fecha y Hora -->
                    <div class="mb-3 row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">Fecha</label>
                            <input type="date" class="form-control"
                                name="attendance_date" readonly
                                value="{{ $fecha->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Hora</label>
                            <input type="time" class="form-control"
                                id="attendanceTime" name="attendance_time" required>
                        </div>

                        @if (!empty($currentRange))
                            <div class="alert alert-info py-2 mt-2">
                                <i class="fas fa-clock me-1"></i>
                                La hora debe estar dentro de la franja
                                <strong>{{ $currentRange['label'] }}</strong>
                            </div>
                        @endif

                        <div class="text-danger small d-none" id="timeRangeError">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            La hora seleccionada no pertenece a esta franja.
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Observaciones</label>
                        <textarea class="form-control" name="observations" rows="3"></textarea>
                    </div>

                    <!-- Evidencia -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Evidencia
                            <span class="text-danger d-none" id="evidenceRequiredLabel">
                                * obligatorio para excusa
                            </span>
                        </label>
                        <input type="file" class="form-control"
                            name="evidence" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">
                            Se aceptan archivos JPG, PNG o PDF.
                        </small>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn sigac-btn-success">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Guardar cambios
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
