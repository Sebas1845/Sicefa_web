
  
  //{{-- SCRIPT 3 --}}

        const attendanceFilter = document.getElementById('attendanceFilter');

        if (attendanceFilter) {
            attendanceFilter.addEventListener('change', function() {
                const selectedStatus = this.value;

                document.querySelectorAll('.attendance-item').forEach(row => {
                    const status = row.dataset.status;

                    if (!selectedStatus || status === selectedStatus) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        }

 // {{-- SCRIPT 4 --}}
        const statusFilter = document.getElementById('attendanceFilter');
        const searchInput = document.getElementById('attendanceSearch');

        function aplicarFiltros() {
            const estadoSeleccionado = statusFilter.value;
            const textoBusqueda = searchInput.value.toLowerCase();

            document.querySelectorAll('.attendance-item').forEach(row => {
                const estado = row.dataset.status;
                const nombre = row.dataset.name;

                const coincideEstado = !estadoSeleccionado || estado === estadoSeleccionado;

                const coincideTexto = !textoBusqueda || nombre.includes(textoBusqueda);

                if (coincideEstado && coincideTexto) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        }

        if (statusFilter) statusFilter.addEventListener('change', aplicarFiltros);
        if (searchInput) searchInput.addEventListener('keyup', aplicarFiltros);
