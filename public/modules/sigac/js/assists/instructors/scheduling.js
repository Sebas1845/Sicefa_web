
 //  {{-- SCRIPT 5 --}}
        function seleccionarProgramacion(fecha, horaInicio) {

            // Formato requerido por datetime-local
            const datetime = fecha + 'T' + horaInicio.substring(0, 5);

            // Asignar al input
            const input = document.getElementById('attendanceDate');
            input.value = datetime;

            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(
                document.getElementById('infoProgramacion')
            );
            modal.hide();

            // Disparar submit automáticamente
            input.dispatchEvent(new Event('change'));
        }

