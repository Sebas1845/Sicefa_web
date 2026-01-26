(function () {
    const instructorBtn = document.getElementById('instructorBtn');
    const instructorBtnText = instructorBtn ? instructorBtn.querySelector('.btn-text') : null;
    const instructorIdInput = document.getElementById('instructor_id');
    const instructorSearch = document.getElementById('instructorSearch');
    const instructorList = document.getElementById('instructorList');

    const dateInput = document.getElementById('date');
    const searchInput = document.getElementById('search');
    const tableWrap = document.getElementById('tableWrap');

    const rangeBadge = document.getElementById('rangeBadge');
    const countBadge = document.getElementById('countBadge');

    const msg = document.getElementById('msg');
    const loadingPill = document.getElementById('loadingPill');
    const savedPill = document.getElementById('savedPill');
    const errorPill = document.getElementById('errorPill');

    const metaBar = document.getElementById('metaBar');
    const metaFicha = document.getElementById('metaFicha');
    const metaPrograma = document.getElementById('metaPrograma');
    const metaFechaHora = document.getElementById('metaFechaHora');
    const metaFranja = document.getElementById('metaFranja');

    // Modal refs
    const modalRecordId = document.getElementById('modalRecordId');
    const modalStatus = document.getElementById('modalStatus');
    const modalTime = document.getElementById('modalTime');
    const modalObs = document.getElementById('modalObs');
    const modalEvidence = document.getElementById('modalEvidence');
    const modalEvidenceLinkWrap = document.getElementById('modalEvidenceLinkWrap');
    const modalEvidenceLink = document.getElementById('modalEvidenceLink');
    const modalSave = document.getElementById('modalSave');
    const modalMsg = document.getElementById('modalMsg');
    // Modal Observación (solo ver)
    const obsModalBody = document.getElementById('obsModalBody');

    function showObsModal() {
        if (hasBS5) {
            const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('obsModal'));
            m.show();
        } else if (hasJQ && $('#obsModal').modal) {
            $('#obsModal').modal('show');
        } else {
            document.getElementById('obsModal').classList.add('show');
            document.getElementById('obsModal').style.display = 'block';
        }
    }

    function openObsModal(text) {
        const full = String(text ?? '').trim();
        obsModalBody.textContent = full || 'Sin observación.';
        showObsModal();
    }

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const filterButtons = document.querySelectorAll('.filter-btn');
    // ===== Persistencia (para NO perder instructor/fecha al recargar) =====
    const STATE_KEY = 'sigac_ca_attendance_state_v1';

    function saveState(partial) {
        const current = JSON.parse(localStorage.getItem(STATE_KEY) || '{}');
        const next = Object.assign({}, current, partial);
        localStorage.setItem(STATE_KEY, JSON.stringify(next));
    }

    function loadState() {
        try {
            return JSON.parse(localStorage.getItem(STATE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    // Compat Bootstrap 4/5
    const hasBS5 = !!(window.bootstrap && bootstrap.Modal);
    const hasJQ = !!(window.$ && $.fn);

    function showModal() {
        if (hasBS5) {
            const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
            m.show();
        } else if (hasJQ && $('#editModal').modal) {
            $('#editModal').modal('show');
        } else {
            document.getElementById('editModal').classList.add('show');
            document.getElementById('editModal').style.display = 'block';
        }
    }

    function hideModal() {
        if (hasBS5) {
            const m = bootstrap.Modal.getInstance(document.getElementById('editModal'));
            if (m) m.hide();
        } else if (hasJQ && $('#editModal').modal) {
            $('#editModal').modal('hide');
        } else {
            document.getElementById('editModal').classList.remove('show');
            document.getElementById('editModal').style.display = 'none';
        }
    }

    function hideDropdown() {
        // BS5
        if (hasBS5 && bootstrap.Dropdown) {
            const inst = bootstrap.Dropdown.getInstance(instructorBtn);
            if (inst) inst.hide();
            else instructorBtn.click();
            return;
        }
        // BS4
        if (hasJQ && $(instructorBtn).dropdown) {
            try {
                $(instructorBtn).dropdown('toggle');
            } catch (e) { }
            return;
        }
        // manual
        const menu = instructorBtn.parentElement.querySelector('.dropdown-menu');
        if (menu) menu.classList.remove('show');
        instructorBtn.classList.remove('show');
    }

    let activeStatusFilter = 'all';
    let lastRecords = [];
    let t = null;
    let currentRange = null; // franja actual que llega del backend
    let editingOriginalTime = null; // hora original del registro al abrir modal
    // Fecha/hora hoy y max hoy
    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function setMaxToday() {
        const now = new Date();
        const maxVal =
            `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
        dateInput.max = maxVal;
    }
    // ===== Restaurar estado al cargar =====
    const savedState = loadState();

    // restaurar fecha si existe
    if (savedState.date) {
        dateInput.value = savedState.date;
    }

    // si no había fecha guardada, poner hoy
    if (!dateInput.value) {
        const now = new Date();
        dateInput.value =
            `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    }

    // max siempre hoy
    setMaxToday();

    // restaurar instructor si existe
    if (savedState.instructor_id && savedState.instructor_name) {
        instructorIdInput.value = savedState.instructor_id;
        if (instructorBtnText) instructorBtnText.textContent = savedState.instructor_name;
        else instructorBtn.textContent = savedState.instructor_name;

        // si ya hay fecha, cargar automáticamente
        if (dateInput.value) {
            setTimeout(() => debounceLoad(), 50);
        }
    }


    function debounceLoad() {
        clearTimeout(t);
        t = setTimeout(loadRecords, 200);
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", "&#039;");
    }

    function toMinutes(hms) {
        if (!hms) return null;
        const parts = String(hms).split(':');
        const h = parseInt(parts[0] || '0', 10);
        const m = parseInt(parts[1] || '0', 10);
        return (h * 60) + m;
    }

    function inRange(timeHHmm, startHHmmss, endHHmmss) {
        const t = toMinutes(timeHHmm);
        const a = toMinutes(startHHmmss);
        const b = toMinutes(endHHmmss);
        if (t == null || a == null || b == null) return true;
        return t >= a && t <= b;
    }

    function hhmmFromAny(hms) {
        if (!hms) return '';
        return String(hms).slice(0, 5);
    }

    function formatTimeForTable(value) {
        if (!value) return '';

        let s = String(value).trim();

        // Si ya viene con AM/PM, lo dejamos (solo quitamos segundos si existieran)
        if (/(AM|PM)$/i.test(s)) {
            // Ej: "09:10:00 AM" -> "09:10 AM"
            s = s.replace(/^(\d{2}:\d{2})(:\d{2})?\s*(AM|PM)$/i, '$1 $3');
            return s.toUpperCase();
        }

        // Si viene tipo "09:10:00" o "09:10"
        const m = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (!m) return s; // si viene raro, lo mostramos tal cual

        let H = parseInt(m[1], 10);
        const M = parseInt(m[2], 10);

        const ampm = H >= 12 ? 'PM' : 'AM';
        const h12 = ((H + 11) % 12) + 1; // 0->12, 13->1, etc

        return `${String(h12).padStart(2, '0')}:${String(M).padStart(2, '0')} ${ampm}`;
    }

    function toastSuccess(title = 'Actualización completa') {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title,
                timer: 1300,
                showConfirmButton: false
            });
        } else {
            alert(title); // fallback si no carga Swal
        }
    }

    function toastError(title = 'Error') {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title,
                timer: 1800,
                showConfirmButton: false
            });
        } else {
            alert(title);
        }
    }


    function showPill(el) {
        [loadingPill, savedPill, errorPill].forEach(x => x.classList.add('d-none'));
        if (el) el.classList.remove('d-none');
    }

    function setMessage(text, type = 'muted') {
        msg.className = 'small text-' + type;
        msg.textContent = text || '';
    }

    function setRange(range) {
        if (!range) {
            rangeBadge.classList.add('d-none');
            rangeBadge.textContent = '';
            return;
        }
        rangeBadge.classList.remove('d-none');
        rangeBadge.className = 'badge bg-success';
        rangeBadge.textContent = `Franja: ${range.label}`;
    }

    function setCount(n) {
        if (typeof n !== 'number') {
            countBadge.classList.add('d-none');
            return;
        }
        countBadge.classList.remove('d-none');
        countBadge.textContent = `${n} registros`;
    }

    function statusPill(s) {
        s = (s || '').toLowerCase();

        if (s === 'present')
            return `<span class="badge badge-success" style="border-radius:999px;padding:.45rem .6rem;">PRESENTE</span>`;
        if (s === 'late')
            return `<span class="badge badge-warning" style="border-radius:999px;padding:.45rem .6rem;">TARDE</span>`;
        if (s === 'absent')
            return `<span class="badge badge-danger" style="border-radius:999px;padding:.45rem .6rem;">AUSENTE</span>`;
        if (s === 'excused')
            return `<span class="badge badge-primary" style="border-radius:999px;padding:.45rem .6rem;">EXCUSA</span>`;
        if (s === 'withdrawn')
            return `<span class="badge badge-secondary" style="border-radius:999px;padding:.45rem .6rem;">RETIRO</span>`;

        return `<span class="badge badge-light border" style="border-radius:999px;padding:.45rem .6rem;">—</span>`;
    }




    function setMeta(meta) {
        if (!meta) {
            metaBar.classList.add('d-none');
            metaFicha.textContent = '—';
            metaPrograma.textContent = '—';
            metaFechaHora.textContent = '—';
            metaFranja.textContent = '—';
            return;
        }
        metaBar.classList.remove('d-none');
        metaFicha.textContent = meta.ficha || '—';
        metaPrograma.textContent = meta.program_name || '—';
        metaFechaHora.textContent = `${meta.date || '—'} ${meta.time || '—'}`;
        metaFranja.textContent = meta.range_label ? `Franja: ${meta.range_label}` : '—';
    }

    function showTableLoading() {
        tableWrap.innerHTML = `
    <div class="position-relative" style="min-height:180px;">
      <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
           style="background:rgba(255,255,255,.75);border-radius:12px;z-index:2;">
        <div class="text-center">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2 text-muted">Cargando aprendices…</div>
        </div>
      </div>

      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Aprendiz</th>
            <th>Estado</th>
            <th>Observación</th>
            <th>Evidencia</th>
            <th>Hora</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          ${Array.from({ length: 6 }).map(() => `
            <tr>
              <td><span class="text-muted">Cargando…</span></td>
              <td><span class="text-muted">—</span></td>
              <td><span class="text-muted">—</span></td>
              <td><span class="text-muted">—</span></td>
              <td><span class="text-muted">—</span></td>
              <td><span class="text-muted">—</span></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
    }

    function applyFilters() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const filtered = lastRecords.filter(r => {
            const st = (r.attendance_status || '').toLowerCase();
            const okStatus = (activeStatusFilter === 'all') ? true : (st === activeStatusFilter);
            const text = `${r.apprentice?.name || ''} ${r.apprentice?.document || ''}`.toLowerCase();
            const okSearch = (!q) ? true : text.includes(q);
            return okStatus && okSearch;
        });
        renderTable(filtered);
        setCount(filtered.length);
    }

    function renderTable(records) {

        // ✅ Si NO hay registros por el filtro (pero sí hay data base cargada)
        if (!records || records.length === 0) {
            searchInput.disabled = false; // ✅ NO BLOQUEAR
            tableWrap.innerHTML = `
      <div class="alert alert-warning mb-0 py-2" style="border-radius:12px;">
        No se encontraron resultados para “<b>${escapeHtml(searchInput.value || '')}</b>”.
      </div>`;
            return;
        }

        // ✅ Si hay registros, normal
        searchInput.disabled = false;


        searchInput.disabled = false;

        const rows = records.map(r => `
      <tr data-row-id="${r.id}">
        <td style="min-width:260px;">
          <div class="fw-semibold">${escapeHtml(r.apprentice?.name || '')}</div>
          <div class="text-muted"><small>${escapeHtml(r.apprentice?.document || '')}</small></div>
        </td>

       <td style="min-width:180px;">
  <div class="d-flex align-items-center">
    <span data-badge="${r.id}">${statusPill(r.attendance_status)}</span>
  </div>
</td>


       <td style="min-width:260px;">
  ${(r.observations && String(r.observations).trim().length)
                ? `<button type="button"
                      class="btn btn-sm btn-outline-secondary"
                      style="border-radius:10px;"
                      data-obs="${r.id}">
                      Ver
                    </button>`
                : `<span class="text-muted"><small><em>Sin observación</em></small></span>`
            }
</td>


      <td style="min-width:140px;">
  ${r.evidence_url
                ? `<a href="${escapeHtml(r.evidence_url)}"
                   target="_blank"
                   class="btn btn-sm btn-outline-secondary"
                   style="border-radius:10px;">
                   Ver
                 </a>`
                : `<span class="text-muted"><small><em>Sin evidencia</em></small></span>`
            }
</td>


        <td style="white-space:nowrap;">
          <small class="text-muted">${escapeHtml(formatTimeForTable(r.attendance_time) || '')}</small>
        </td>

        <td style="width:160px;white-space:nowrap;">
          <button class="btn btn-sm btn-outline-primary" style="border-radius:10px;"
                  data-edit="${r.id}">
            Editar
          </button>
        </td>
      </tr>
    `).join('');

        tableWrap.innerHTML = `
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Aprendiz</th>
            <th>Estado</th>
            <th>Observación</th>
            <th>Evidencia</th>
            <th>Hora</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>`;
    }



    function openEditModal(record) {
        modalRecordId.value = record.id;
        modalStatus.value = record.attendance_status || 'present';
        modalObs.value = record.observations || '';
        modalEvidence.value = '';

        if (record.evidence_url) {
            modalEvidenceLinkWrap.style.display = '';
            modalEvidenceLink.href = record.evidence_url;
        } else {
            modalEvidenceLinkWrap.style.display = 'none';
            modalEvidenceLink.href = '#';
        }

        modalMsg.textContent = '';
        modalMsg.className = 'me-auto text-muted small';

        /* ✅ PEGAR AQUÍ */
        editingOriginalTime = hhmmFromAny(record.attendance_time);

        if (modalTime) {
            modalTime.value = editingOriginalTime || '';

            if (currentRange && currentRange.start && currentRange.end) {
                modalTime.min = hhmmFromAny(currentRange.start);
                modalTime.max = hhmmFromAny(currentRange.end);
            } else {
                modalTime.min = '';
                modalTime.max = '';
            }
        }
        /* ✅ HASTA AQUÍ */

        showModal();

    }

    // Instructor seleccionar
    instructorList.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-instructor-id]');
        if (!btn) return;

        const id = btn.getAttribute('data-instructor-id');
        const name = btn.getAttribute('data-instructor-name');

        instructorIdInput.value = id;
        if (instructorBtnText) instructorBtnText.textContent = name;
        else instructorBtn.textContent = name;

        // ✅ guardar instructor
        saveState({
            instructor_id: id,
            instructor_name: name
        });

        hideDropdown();
        debounceLoad();

    });

    // Instructor buscar
    instructorSearch.addEventListener('input', () => {
        const q = (instructorSearch.value || '').trim().toLowerCase();
        instructorList.querySelectorAll('[data-instructor-id]').forEach(it => {
            const txt = (it.textContent || '').toLowerCase();
            it.style.display = (!q || txt.includes(q)) ? '' : 'none';
        });
    });

    // Fecha cambia
    dateInput.addEventListener('change', () => {
        saveState({
            date: dateInput.value || ''
        });
        debounceLoad();
    });


    // filtros estado
    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeStatusFilter = btn.getAttribute('data-status') || 'all';
            applyFilters();
        });
    });

    // búsqueda tabla
    searchInput.addEventListener('input', applyFilters);

    // Editar modal
    tableWrap.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-edit]');
        if (!btn) return;

        const id = btn.getAttribute('data-edit');
        const record = lastRecords.find(x => String(x.id) === String(id));
        if (record) openEditModal(record);
    });
    // Ver Observación (modal)
    tableWrap.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-obs]');
        if (!btn) return;

        const id = btn.getAttribute('data-obs');
        const record = lastRecords.find(x => String(x.id) === String(id));
        openObsModal(record ? record.observations : '');
    });

    async function loadRecords() {
        const instructor_id = instructorIdInput.value;
        const date = dateInput.value;

        if (!instructor_id || !date) {
            setRange(null);
            setCount(null);
            setMeta(null);
            setMessage('Selecciona instructor y fecha/hora.', 'muted');
            showPill(null);
            searchInput.value = '';
            searchInput.disabled = true;
            tableWrap.innerHTML =
                `<div class="alert alert-info mb-0 py-2" style="border-radius:12px;">Selecciona instructor y fecha/hora.</div>`;
            return;
        }

        showTableLoading();
        showPill(loadingPill);
        setMessage('Consultando registros…', 'muted');

        const params = new URLSearchParams({
            instructor_id,
            date
        });

        try {
            const res = await fetch(
                `${window.AttendanceCfg.routes.viewEdit}?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
            );

            const data = await res.json();

            if (!data.ok) {
                showPill(errorPill);
                setRange(null);
                setCount(null);
                setMeta(null);
                setMessage(data.message || 'Error', 'danger');
                tableWrap.innerHTML =
                    `<div class="alert alert-danger mb-0 py-2" style="border-radius:12px;">${escapeHtml(data.message || 'Error')}</div>`;
                return;
            }

            lastRecords = data.records || [];
            setRange(data.range);
            setMeta(data.meta);
            currentRange = data.range || null;
            showPill(null);
            setMessage('', 'muted');

            applyFilters();

            if (lastRecords.length === 0) {
                setCount(0);
                renderTable([]);
            }

        } catch (err) {
            console.error(err);
            showPill(errorPill);
            setMessage('Error de red/servidor', 'danger');
            tableWrap.innerHTML =
                `<div class="alert alert-danger mb-0 py-2" style="border-radius:12px;">Error de red/servidor</div>`;
        }
    }


    // Guardar desde modal (FormData)
    modalSave.addEventListener('click', async () => {
        const id = modalRecordId.value;

        const fd = new FormData();
        fd.append('id', id);
        fd.append('attendance_status', modalStatus.value);
        fd.append('observations', modalObs.value);

        /* ✅ PEGAR AQUÍ */
        const newTime = modalTime ? hhmmFromAny(modalTime.value) : '';
        const originalTime = editingOriginalTime || '';

        if (newTime && newTime !== originalTime) {

            if (currentRange && currentRange.start && currentRange.end) {
                if (!inRange(newTime, currentRange.start, currentRange.end)) {
                    if (window.Swal) Swal.close();
                    toastError(`La hora debe estar dentro de la franja ${currentRange.label}`);
                    return;
                }
            }

            fd.append('attendance_time', newTime);
        }
        /* ✅ HASTA AQUÍ */

        if (modalEvidence.files && modalEvidence.files[0]) {
            fd.append('evidence', modalEvidence.files[0]);
        }

        // UI local
        modalMsg.textContent = 'Guardando…';
        modalMsg.className = 'me-auto text-muted small';

        // (opcional) Swal loading
        if (window.Swal) {
            Swal.fire({
                title: 'Guardando cambios…',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        }

        try {
            const res = await fetch(window.AttendanceCfg.routes.edit, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.AttendanceCfg.csrf
                },
                body: fd
            });


            const data = await res.json();

            if (!data.ok) {
                showPill(errorPill);
                modalMsg.textContent = data.message || 'Error al guardar.';
                modalMsg.className = 'me-auto text-danger small';
                if (window.Swal) Swal.close();
                toastError(data.message || 'Error al guardar');
                return;
            }

            // ✅ Cerrar modal
            hideModal();
            // ✅ actualizar SOLO la fila editada en la tabla (sin recargar todo)
            if (data.record) {
                patchRowInDom(data.record);
            } else {
                patchRowInDom({
                    id: id,
                    attendance_status: modalStatus.value,
                    observations: modalObs.value,
                    evidence_url: null, // si el backend no responde evidencia
                    attendance_time: '' // si el backend no responde hora
                });
            }


            // Pills
            showPill(savedPill);
            setTimeout(() => showPill(null), 800);

            // ✅ Swal éxito
            if (window.Swal) Swal.close();
            toastSuccess('Actualización completa');

        } catch (e) {
            console.error(e);
            showPill(errorPill);
            if (window.Swal) Swal.close();
            toastError('Error de red/servidor');
        }
    });

    // OJO: instructor se carga al seleccionar en dropdown, ya llama debounceLoad()
    // Aquí solo garantizamos que si el usuario cambia fecha, recargue (ya está)
    // y que al abrir la página no dispare hasta que haya instructor (tu lógica actual)
    function patchRowInDom(updated) {
        const tr = tableWrap.querySelector(`tr[data-row-id="${updated.id}"]`);
        if (!tr) return;

        const badge = tr.querySelector(`[data-badge="${updated.id}"]`);
        if (badge) badge.innerHTML = statusPill(updated.attendance_status);

        const obsCell = tr.children[2];
        if (obsCell) {
            const full = String(updated.observations ?? '').trim();

            if (full) {
                obsCell.innerHTML = `
      <button type="button"
        class="btn btn-sm btn-outline-secondary"
        style="border-radius:10px;"
        data-obs="${updated.id}">
        Ver
      </button>`;
            } else {
                obsCell.innerHTML = `<span class="text-muted"><small><em>Sin observación</em></small></span>`;
            }

            // importante: mantener actualizado el lastRecords
            const idx = lastRecords.findIndex(x => String(x.id) === String(updated.id));
            if (idx !== -1) lastRecords[idx].observations = updated.observations;
        }


        const evCell = tr.children[3];
        if (evCell) {
            if (updated.evidence_url) {
                evCell.innerHTML =
                    `<a href="${escapeHtml(updated.evidence_url)}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;">Ver</a>`;
            } else {
                evCell.innerHTML = `<span class="text-muted"><small>—</small></span>`;
            }
        }


        const timeCell = tr.children[4];
        if (timeCell && updated.attendance_time != null) {
            timeCell.innerHTML =
                `<small class="text-muted">${escapeHtml(updated.attendance_time || '')}</small>`;
        }
    }

})();