document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editAttendanceModal');

    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // botón que abrió el modal

        // Obtener datos del botón
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const status = button.getAttribute('data-status');
        const observations = button.getAttribute('data-observations');

        // Llenar campos del modal
        editModal.querySelector('#attendanceId').value = id;
        editModal.querySelector('#apprenticeName').value = name;
        editModal.querySelector('#attendanceStatus').value = status;
        editModal.querySelector('#editAttendanceForm textarea[name="observations"]').value = observations;

        // Rellenar la hora con la hora actual
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        editModal.querySelector('#attendanceTime').value = `${hours}:${minutes}`;
    });
});
