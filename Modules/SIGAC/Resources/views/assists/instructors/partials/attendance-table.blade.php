 @if ($program->attendance_records->count() > 0)
                            <div class="alert alert-info d-flex align-items-center mb-4 shadow-sm">
                                <i class="fas fa-clipboard-check fa-lg me-2"></i>
                                <div>
                                    <strong>Asistencia registrada</strong><br>
                                    <small class="opacity-75">
                                        Estos aprendices ya tienen asistencia registrada para la fecha y franja
                                        seleccionada.
                                    </small>
                                </div>
                            </div>
                            <div class="row g-2 mb-3 align-items-end">

                                <!-- Filtro por estado -->
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">
                                        <i class="fas fa-filter me-1"></i> Filtrar por estado
                                    </label>
                                    <select id="attendanceFilter" class="form-select form-select-sm shadow-sm">
                                        <option value="">Todos los estados</option>
                                        <option value="present">Asistió</option>
                                        <option value="late">Llegada tardía</option>
                                        <option value="absent">Inasistencia</option>
                                        <option value="excused">Excusado</option>
                                        <option value="withdrawn">Retirado</option>
                                    </select>
                                </div>

                                <!-- Buscador por nombre / apellido -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">
                                        <i class="fas fa-search me-1"></i> Buscar aprendiz
                                    </label>
                                    <div class="input-group input-group-sm shadow-sm">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-user text-muted"></i>
                                        </span>
                                        <input type="text" id="attendanceSearch" class="form-control"
                                            placeholder="Nombre o apellido del aprendiz">
                                    </div>
                                </div>


                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle shadow-sm">
                                        <thead class="table-light">
                                            <tr class="text-center">
                                                <th style="width: 1%; white-space: nowrap;">#</th>
                                                <th class="text-start">Aprendiz</th>
                                                <th>Estado</th>
                                                <th class="text-start">Observación</th>
                                                <th class="text-center">Evidencia</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($program->attendance_records as $record)
                                                @php
                                                    $statusColors = [
                                                        'present' => 'success',
                                                        'late' => 'warning',
                                                        'absent' => 'danger',
                                                        'withdrawn' => 'secondary',
                                                        'excused' => 'info',
                                                    ];
                                                    $statusLabels = [
                                                        'present' => 'Asistió',
                                                        'late' => 'Llegada tardía',
                                                        'absent' => 'Inasistencia',
                                                        'withdrawn' => 'Retirado',
                                                        'excused' => 'Excusado',
                                                    ];

                                                @endphp

                                                <tr class="attendance-item"
                                                    data-status="{{ strtolower(trim($record->attendance_status)) }}"
                                                    data-name="{{ strtolower($record->apprentice->fullname) }}">

                                                    <!-- Número de fila, ancho mínimo -->
                                                    <td class="text-center" style="white-space: nowrap; width: 1%;">
                                                        {{ $loop->iteration . '.)' }}
                                                    </td>

                                                    <!-- Aprendiz -->
                                                    <td>
                                                        <i class="fas fa-user-graduate me-1 text-muted"></i>
                                                        {{ $record->apprentice->fullname }}
                                                    </td>
                                                    @php
                                                        // 1️⃣ Último registro real del aprendiz en el curso
                                                        $lastRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                                            'apprentice_id',
                                                            $record->apprentice_id,
                                                        )
                                                            ->where('course_id', $record->course_id)
                                                            ->orderBy('attendance_date', 'desc')
                                                            ->orderBy('attendance_time', 'desc')
                                                            ->first();

                                                        // 2️⃣ ¿Actualmente retirado?
                                                        $isCurrentlyWithdrawn =
                                                            $lastRecord &&
                                                            $lastRecord->attendance_status === 'withdrawn';

                                                        $withdrawRecord = null;

                                                        if ($isCurrentlyWithdrawn) {
                                                            // 3️⃣ Último registro NO withdrawn
                                                            $lastActiveRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                                                'apprentice_id',
                                                                $record->apprentice_id,
                                                            )
                                                                ->where('course_id', $record->course_id)
                                                                ->where('attendance_status', '!=', 'withdrawn')
                                                                ->orderBy('attendance_date', 'desc')
                                                                ->orderBy('attendance_time', 'desc')
                                                                ->first();

                                                            // 4️⃣ Primer withdrawn DESPUÉS del último estado activo
                                                            $withdrawRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                                                'apprentice_id',
                                                                $record->apprentice_id,
                                                            )
                                                                ->where('course_id', $record->course_id)
                                                                ->where('attendance_status', 'withdrawn')
                                                                ->when($lastActiveRecord, function ($q) use (
                                                                    $lastActiveRecord,
                                                                ) {
                                                                    $q->where(function ($q2) use ($lastActiveRecord) {
                                                                        $q2->where(
                                                                            'attendance_date',
                                                                            '>',
                                                                            $lastActiveRecord->attendance_date,
                                                                        )->orWhere(function ($q3) use (
                                                                            $lastActiveRecord,
                                                                        ) {
                                                                            $q3->where(
                                                                                'attendance_date',
                                                                                $lastActiveRecord->attendance_date,
                                                                            )->where(
                                                                                'attendance_time',
                                                                                '>',
                                                                                $lastActiveRecord->attendance_time,
                                                                            );
                                                                        });
                                                                    });
                                                                })
                                                                ->orderBy('attendance_date', 'asc')
                                                                ->orderBy('attendance_time', 'asc')
                                                                ->first();
                                                        }
                                                    @endphp

                                                    <!-- Estado -->
                                                    <td class="text-center">
                                                        <span
                                                            class="badge bg-{{ $statusColors[$record->attendance_status] ?? 'secondary' }}">
                                                            @if ($isCurrentlyWithdrawn && $withdrawRecord)
                                                                {{ $statusLabels['withdrawn'] }}
                                                                <br>
                                                                <small class="text-light">
                                                                    {{ \Carbon\Carbon::parse($withdrawRecord->attendance_date)->format('d/m/Y') }}
                                                                </small>
                                                            @else
                                                                {{ $statusLabels[$record->attendance_status] ?? 'No definido' }}
                                                            @endif
                                                        </span>
                                                    </td>


                                                    <!-- Observación -->
                                                    <td>
                                                        <span class="text-muted">
                                                            {{ $record->observations ?? 'Sin observaciones' }}
                                                        </span>
                                                    </td>

                                                    <!-- Evidencia -->
                                                    <td class="text-center">
                                                        @if ($record->evidence)
                                                            <a href="{{ asset('storage/' . $record->evidence) }}"
                                                                target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-file-alt me-1"></i> Ver
                                                            </a>
                                                        @else
                                                            <span class="text-muted small">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <!-- Botón editar asistencia -->
                                                        <button type="button" class="btn btn-sm sigac-btn-success"
                                                            data-bs-toggle="modal" data-bs-target="#editAttendanceModal"
                                                            data-id="{{ $record->id }}"
                                                            data-name="{{ $record->apprentice->fullname }}"
                                                            data-status="{{ strtolower($record->attendance_status) }}"
                                                            data-observations="{{ $record->observations }}">
                                                            <i class="fas fa-pen-to-square me-2"></i>
                                                            Editar
                                                        </button>
                                                    </td>


                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-secondary text-center py-4">
                                    <i class="fas fa-users-slash fa-lg mb-2"></i>
                                    <div class="fw-semibold">
                                        No hay aprendices registrados para esta franja
                                    </div>
                                </div>
                        @endif