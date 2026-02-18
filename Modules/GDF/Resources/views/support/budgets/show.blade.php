<form method="POST" action="{{ route($routePrefix.'.budgets.additions.store', $budget->id) }}">
  @csrf

  <div class="mb-2">
    <label class="form-label">Monto de adición</label>
    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
  </div>

  <div class="mb-2">
    <label class="form-label">Justificación</label>
    <textarea name="justification" class="form-control" rows="3"></textarea>
  </div>

  {{-- si quieres que quede como "aprobado" de una vez --}}
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="approve_now" value="1" id="approveNow">
    <label class="form-check-label" for="approveNow">Marcar como aprobado</label>
  </div>

  <button class="btn btn-primary w-100">Guardar adición</button>
</form>
