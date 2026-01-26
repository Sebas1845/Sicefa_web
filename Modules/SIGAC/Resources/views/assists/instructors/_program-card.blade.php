   @php
                // Aprendices activos reales
                $activeApprentices = $program->course->apprentices->filter(fn($a) => !$a->withdrawn);

                // Asistencias registradas HOY (sin duplicar)
                $attendanceTodayCount = $program->attendance_records
                    ->where('attendance_date', $fecha->toDateString())
                    ->unique('apprentice_id')
                    ->count();
            @endphp

            <div class="card mb-4 shadow">

                {{-- HEADER --}}
                <div class="card-header sigac-card-header d-flex align-items-center">

                    <!-- IZQUIERDA -->
                    <div>
                        <strong>
                            <i class="fas fa-book me-1"></i>
                            {{ $program->course->code ?? 'Curso sin código' }} –
                            {{ $program->course->program->name ?? 'Curso sin nombre' }}
                        </strong>

                        <div class="mt-1 small opacity-90">
                            <i class="fas fa-tasks me-1"></i>
                            {{ $program->activity_name ?? 'Sin nombre' }}
                            &nbsp;|&nbsp;
                            <i class="fas fa-clock me-1"></i>
                            {{ $program->start_time }} - {{ $program->end_time }}
                        </div>
                    </div>

                    <!-- DERECHA (pegado al borde) -->
                    <div class="ms-auto text-end pe-1">
                        <!-- Total de aprendices iniciales -->
                        <div class="small opacity-75">
                            Total de aprendices
                        </div>
                        <div class="fw-bold fs-4">
                            {{ $program->total_apprentices }}
                        </div>

                        @php
                            $courseId = $program->course->id;
                            $currentDate = $fecha->toDateString();

                            /**
                             * Tomamos SOLO el último estado de cada aprendiz
                             * hasta la fecha seleccionada
                             */
                            $lastStatuses = \Modules\SIGAC\Entities\AttendanceRecord::select(
                                'apprentice_id',
                                'attendance_status',
                            )
                                ->where('course_id', $courseId)
                                ->whereDate('attendance_date', '<=', $currentDate)
                                ->orderBy('attendance_date', 'desc')
                                ->orderBy('attendance_time', 'desc')
                                ->get()
                                ->unique('apprentice_id');

                            // Contar retirados reales hasta la fecha
                            $withdrawnCount = $lastStatuses->where('attendance_status', 'withdrawn')->count();

                            // Activos reales según fecha
                            $activeCount = $program->total_apprentices - $withdrawnCount;
                        @endphp

                        <!-- Activos y Retirados -->
                        <div class="small mt-1">
                            Activos:
                            <span class="fw-bold" style="color: #28a745; font-weight: 600;">
                                {{ $activeCount }}
                            </span>
                            &nbsp;|&nbsp;
                            Retirados:
                            <span class="fw-bold" style="color: #dc3545; font-weight: 600;">
                                {{ $withdrawnCount }}
                            </span>
                        </div>
                    </div>

                </div>


                <div class="card-body">
                    @if ($activeApprentices->count() > 0 && $attendanceTodayCount < $activeApprentices->count())
                        <div class="d-flex mb-4 align-items-center">

                            <!-- Buscador -->
                            <div class="input-group w-100">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg"
                                    placeholder="Buscar aprendiz por nombre o documento" onkeyup="buscarAprendiz(this)">
                            </div>

                            <!-- Botón -->
                            <button type="button" class="btn sigac-btn-primary ms-3" onclick="registrarAsistencia()">
                                <i class="fas fa-clipboard-check me-2"></i>
                                Registrar asistencia
                            </button>

                        </div>
                    @endif

                    {{-- <p>Controlador actual: {{ $currentController }}</p> --}}

                    @if ($activeApprentices->count())
                        <div class="list-group" id="lista-aprendices">

                            {{-- Seleccionar todos --}}
                            <div class="list-group-item bg-light d-flex align-items-center">
                                <input type="checkbox" class="form-check-input me-2" id="seleccionar-todos">
                                <label for="seleccionar-todos" class="mb-0"
                                    style="cursor: pointer; font-weight: 500; margin-left: 20px;">
                                    Seleccionar todos
                                </label>
                            </div>

                            @foreach ($activeApprentices->sortBy(fn($a) => $a->person->fullname) as $apprentice)
                                @php
                                    // 1️⃣ Último registro real
                                    $lastRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                        'apprentice_id',
                                        $apprentice->person->id,
                                    )
                                        ->where('course_id', $program->course->id)
                                        ->orderBy('attendance_date', 'desc')
                                        ->orderBy('attendance_time', 'desc')
                                        ->first();

                                    // 2️⃣ ¿Actualmente retirado?
                                    $isWithdrawn = $lastRecord && $lastRecord->attendance_status === 'withdrawn';

                                    $withdrawRecord = null;

                                    if ($isWithdrawn) {
                                        // 3️⃣ Último registro NO withdrawn
                                        $lastActiveRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                            'apprentice_id',
                                            $apprentice->person->id,
                                        )
                                            ->where('course_id', $program->course->id)
                                            ->where('attendance_status', '!=', 'withdrawn')
                                            ->orderBy('attendance_date', 'desc')
                                            ->orderBy('attendance_time', 'desc')
                                            ->first();

                                        // 4️⃣ Primer withdrawn DESPUÉS del último estado activo
                                        $withdrawRecord = \Modules\SIGAC\Entities\AttendanceRecord::where(
                                            'apprentice_id',
                                            $apprentice->person->id,
                                        )
                                            ->where('course_id', $program->course->id)
                                            ->where('attendance_status', 'withdrawn')
                                            ->when($lastActiveRecord, function ($q) use ($lastActiveRecord) {
                                                $q->where(function ($q2) use ($lastActiveRecord) {
                                                    $q2->where(
                                                        'attendance_date',
                                                        '>',
                                                        $lastActiveRecord->attendance_date,
                                                    )->orWhere(function ($q3) use ($lastActiveRecord) {
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


                                <div class="list-group-item asistencia-item
    {{ $isWithdrawn ? 'asistencia-retirado retirado-bloqueado seleccionado' : 'asistencia-asistio' }}"
                                    data-apprentice-id="{{ $apprentice->person->id }}"
                                    data-course-id="{{ $program->course->id }}"
                                    data-withdrawn="{{ $isWithdrawn ? 'true' : 'false' }}"
                                    onclick="{{ $isWithdrawn ? '' : 'toggleSeleccion(this)' }}"
                                    data-nombre="{{ strtolower($apprentice->person->fullname) }}"
                                    data-documento="{{ $apprentice->person->document_number }}">

                                    <div class="row align-items-center">

                                        <!-- Aquí mostramos el ID del curso -->
                                        <!--<p><strong>ID del Curso:</strong> {{ $program->course->id }}</p>-->
                                        {{-- Checkbox --}}
                                        <div class="col-md-1 text-center">
                                            <input type="checkbox"
                                                class="form-check-input aprendiz-check {{ $isWithdrawn ? 'checkbox-retirado' : '' }}"
                                                {{ $isWithdrawn ? 'checked disabled' : '' }}
                                                onclick="event.stopPropagation(); {{ $isWithdrawn ? '' : 'toggleSeleccion(this);' }}">


                                        </div>

                                        {{-- Datos --}}
                                        <div class="col-md-3">

                                            <strong>{{ $apprentice->person->fullname }}</strong><br>
                                            <small>{{ $apprentice->person->document_number }}</small>
                                        </div>

                                        <div class="col-md-5">
                                            @if (!$isWithdrawn)
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-success"
                                                        onclick="setEstado(event,this,'asistio')">
                                                        <i class="fas fa-check-circle"></i> Asistió
                                                    </button>
                                                    <button class="btn btn-warning" onclick="setEstado(event,this,'tarde')">
                                                        <i class="fas fa-clock"></i> Tardanza
                                                    </button>
                                                    <button class="btn btn-info" onclick="setEstado(event,this,'retiro')">
                                                        <i class="fas fa-door-open"></i> Retirado
                                                    </button>
                                                    <button class="btn btn-danger" onclick="setEstado(event,this,'no')">
                                                        <i class="fas fa-times-circle"></i> Ausente
                                                    </button>
                                                    <button class="btn btn-secondary"
                                                        onclick="setEstado(event,this,'excusa')">
                                                        <i class="fas fa-paperclip"></i> Justificado
                                                    </button>
                                                </div>
                                            @endif
                                            @if ($isWithdrawn)
                                                <div class="mt-1 text-muted small fw-semibold">
                                                    <i class="fas fa-user-slash me-1"></i>
                                                    Retirado
                                                    <br>
                                                    <span class="small">
                                                        {{ optional($withdrawRecord)->attendance_date }}
                                                    </span>
                                                </div>
                                            @endif

                                        </div>

                                        {{-- Evidencia --}}
                                        <div class="col-md-3 evidencia d-none">
                                            <input type="file" class="form-control form-control-sm"
                                                accept=".pdf,image/*" onclick="event.stopPropagation()">
                                            <small class="text-muted">
                                                Excusa / Evidencia (opcional)
                                            </small>
                                        </div>

                                        {{-- OBSERVACIONES --}}
                                        <div class="col-md-12 mt-2 observaciones d-none">
                                            <textarea class="form-control form-control-sm" rows="2" placeholder="Observaciones (opcional)"
                                                onclick="event.stopPropagation()" onkeydown="event.stopPropagation()"></textarea>
                                        </div>

                                    </div>
                                </div>
                            @endforeach

                        </div>
                    @else
                        {{-- 🔴 NO hay aprendices a registrar --}}
                        @include('sigac::assists.instructors.partials.attendance-table')
                    @endif
                </div>
            </div>