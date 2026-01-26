{{-- ============================================================
| Confirmación de asistencia de instructores
| Vista principal que orquesta:
| - filtros
| - listado de programas
| - estados vacíos
| - scripts de interacción
============================================================ --}}

@extends('sigac::layouts.master')

{{-- Modal informativo de programación --}}
@include('sigac::assists.instructors.partials.info-programacion-modal')

{{-- ================== ESTILOS ================== --}}
@push('head')
    {{-- Estilos específicos del módulo de asistencias --}}
    <link rel="stylesheet" href="{{ asset('modules/sigac/css/assists/instructors/attendance.css') }}">
@endpush

@section('content')
    {{-- ================== CONTENEDOR PRINCIPAL ================== --}}
    <div class="container">

        {{-- Filtros de fecha / franja horaria --}}
        @include('sigac::assists.instructors._filters')

        {{-- ================== LISTADO DE PROGRAMAS ================== --}}
        {{-- Se renderiza una tarjeta por cada programa asignado --}}
        @forelse($programs as $program)
            {{-- Tarjeta individual del programa --}}
            @include('sigac::assists.instructors._program-card')

        @empty
            {{-- Estado vacío cuando no hay programación --}}
            @include('sigac::assists.instructors.partials.empty-programs')
        @endforelse

    </div>
    {{-- =========================================================== --}}

    {{-- ================== SCRIPTS ================== --}}

    {{-- ===========================================================
    | SCRIPT 1
    | Lógica principal de selección y registro de asistencia
    | Maneja:
    | - Estados de asistencia
    | - Bloqueos por excusa sin evidencia
    | - Selección individual y masiva
    | - Envío de datos por fetch
    ============================================================ --}}
    <script>
        // Controla bloqueo cuando hay excusa sin evidencia
        let bloqueoJustificado = false;
        let aprendizBloqueado = null;

        /**
         * Cambia el estado de asistencia del aprendiz
         * y controla visibilidad de observaciones / evidencia
         */
        function setEstado(e, btn, estado) {
            e.stopPropagation();

            const item = btn.closest('.asistencia-item');
            const evidencia = item.querySelector('.evidencia');
            const observaciones = item.querySelector('.observaciones');

            // Reset de estados visuales
            item.classList.remove(
                'asistencia-asistio',
                'asistencia-tarde',
                'asistencia-no',
                'asistencia-excusa',
                'asistencia-retiro'
            );

            evidencia.classList.add('d-none');
            observaciones.classList.add('d-none');

            // Estados posibles
            if (estado === 'asistio') item.classList.add('asistencia-asistio');

            if (estado === 'tarde') {
                item.classList.add('asistencia-tarde');
                evidencia.classList.remove('d-none');
                observaciones.classList.remove('d-none');
            }

            if (estado === 'retiro') {
                item.classList.add('asistencia-retiro');
                observaciones.classList.remove('d-none');
            }

            if (estado === 'no') {
                item.classList.add('asistencia-no');
                observaciones.classList.remove('d-none');
            }

            if (estado === 'excusa') {
                item.classList.add('asistencia-excusa');
                evidencia.classList.remove('d-none');
                observaciones.classList.remove('d-none');

                // Bloqueo hasta que se cargue evidencia
                bloqueoJustificado = true;
                aprendizBloqueado = item;

                evidencia.querySelector('input[type="file"]').addEventListener('change', function() {
                    if (this.files.length > 0) {
                        bloqueoJustificado = false;
                        aprendizBloqueado = null;
                    } else {
                        bloqueoJustificado = true;
                        aprendizBloqueado = item;
                    }
                });
            } else {
                // Desbloquea si cambia a otro estado
                if (bloqueoJustificado && item === aprendizBloqueado) {
                    bloqueoJustificado = false;
                    aprendizBloqueado = null;
                }
            }
        }

        /**
         * Selección masiva de aprendices
         */
        function seleccionarTodos(master) {
            document.querySelectorAll('.asistencia-item').forEach(item => {

                // No se permite tocar aprendices retirados
                if (item.dataset.withdrawn === 'true') return;

                const check = item.querySelector('.aprendiz-check');
                check.checked = master.checked;
                item.classList.toggle('seleccionado', master.checked);
            });
        }

        /**
         * Maneja selección individual por clic en div o checkbox
         */
        function toggleSeleccion(element) {
            let item, check;

            if (element.tagName === 'INPUT') {
                // clic en checkbox: no alternamos manualmente, solo sincronizamos clase
                check = element;
                item = check.closest('.asistencia-item');

                // Bloqueos
                if (item.dataset.withdrawn === 'true') {
                    check.checked = false; // prevenir cambios
                    return;
                }
                if (bloqueoJustificado && item !== aprendizBloqueado) {
                    check.checked = false;
                    Swal.fire({
                        title: 'Atención',
                        text: 'No puedes seleccionar otro aprendiz hasta subir evidencia o cambiar el estado.',
                        icon: 'warning'
                    });
                    return;
                }

                // solo sincroniza clase con el estado real del checkbox
                item.classList.toggle('seleccionado', check.checked);

            } else {
                // clic en div: alternamos checkbox y clase
                item = element;
                check = item.querySelector('.aprendiz-check');

                if (item.dataset.withdrawn === 'true') return;
                if (bloqueoJustificado && item !== aprendizBloqueado) {
                    Swal.fire({
                        title: 'Atención',
                        text: 'No puedes seleccionar otro aprendiz hasta subir evidencia o cambiar el estado.',
                        icon: 'warning'
                    });
                    return;
                }

                check.checked = !check.checked;
                item.classList.toggle('seleccionado', check.checked);
            }
        }

        /**
         * Filtro por nombre o documento
         */
        function buscarAprendiz(input) {
            const texto = input.value.toLowerCase();

            document.querySelectorAll('.asistencia-item').forEach(item => {
                const nombre = item.dataset.nombre;
                const doc = item.dataset.documento;

                item.style.display =
                    nombre.includes(texto) || doc.includes(texto) ? 'block' : 'none';
            });
        }

        /**
         * Registro final de asistencia (fetch)
         */
        function registrarAsistencia() {

            const items = document.querySelectorAll('.asistencia-item.seleccionado');

            if (items.length === 0) {
                Swal.fire('Atención', 'Selecciona al menos un aprendiz', 'warning');
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Se registrará la asistencia de los aprendices seleccionados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, registrar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('course_id', items[0].dataset.courseId);
                formData.append('attendance_date', '{{ $fecha->format('Y-m-d') }}');
                formData.append('attendance_time', '{{ $fecha->format('H:i:s') }}');

                let index = 0;

                for (const item of items) {

                    let status = 'present';

                    if (item.classList.contains('asistencia-tarde')) status = 'late';
                    else if (item.classList.contains('asistencia-no')) status = 'absent';
                    else if (item.classList.contains('asistencia-retiro')) status = 'withdrawn';
                    else if (item.classList.contains('asistencia-excusa')) status = 'excused';

                    const observations = item.querySelector('textarea')?.value ?? '';
                    const evidenceInput = item.querySelector('input[type="file"]');

                    if (status === 'excused' && (!evidenceInput || evidenceInput.files.length === 0)) {
                        Swal.fire('Error', 'La excusa requiere evidencia obligatoria', 'error');
                        return;
                    }

                    formData.append(`records[${index}][apprentice_id]`, item.dataset.apprenticeId);
                    formData.append(`records[${index}][status]`, status);
                    formData.append(`records[${index}][observations]`, observations);

                    if (evidenceInput?.files.length) {
                        formData.append(`records[${index}][evidence]`, evidenceInput.files[0]);
                    }

                    index++;
                }

                fetch('{{ route('sigac.instructor.attendances.attendance.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    })
                    .then(async res => {
                        const data = await res.json();

                        if (res.status === 201) {
                            Swal.fire('¡Éxito!', data.message, 'success')
                                .then(() => location.reload());
                        } else if (res.status === 422) {
                            Swal.fire('Atención', data.message, 'warning');
                        } else {
                            Swal.fire('Error', 'Ocurrió un error al registrar la asistencia', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'Ocurrió un error al registrar la asistencia', 'error');
                    });
            });
        }

        // Checkbox maestro
        document.getElementById('seleccionar-todos')
            .addEventListener('change', function() {
                seleccionarTodos(this);
            });
    </script>

   
  

    {{-- Modal de edición de asistencia --}}
    @include('sigac::assists.instructors.edit-attendance-modal')

    {{-- Scripts externos --}}
    <script src="{{ asset('modules/sigac/js/assists/instructors/filters.js') }}"></script>
    <script src="{{ asset('modules/sigac/js/assists/instructors/edit-attendance-modal.js') }}"></script>
    <script src="{{ asset('modules/sigac/js/assists/instructors/scheduling.js') }}"></script>
@endsection
