{{-- Modules/GDF/Resources/views/coordination/creatpeople.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | ' . ($title ?? 'Coordinación') . ' - Personas / Contratos / Rubros')

@section('content')
@php
  $areaKey = $areaKey ?? (str_contains(request()->path(), 'gdf/campesena') ? 'campesena' : 'academic');

  $isOk = function_exists('checkRol')
      ? ($areaKey === 'campesena'
          ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin'))
          : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support') || checkRol('gdf.superadmin')))
      : false;

  if(!$isOk){ abort(403); }

  // ✅ Soporta coordinator y support (como tu flujo actual)
  $routePrefix = $routePrefix ?? ($areaKey === 'campesena'
      ? (function_exists('checkRol') && (checkRol('gdf.campesena_support') || checkRol('gdf.superadmin')) ? 'gdf.support.campesena' : 'gdf.campesena')
      : (function_exists('checkRol') && (checkRol('gdf.academic_support') || checkRol('gdf.superadmin')) ? 'gdf.support.academic' : 'gdf.academic')
  );

  $title = $title ?? ($areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica');

  $today = now()->format('Y-m-d');

  // ✅ Vigencia para filtrar rubros desde Budgets (area + year)
  $year  = (int)($year ?? request('year', now()->year));
@endphp

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h4 class="mb-1">{{ $title }}</h4>
      <div class="text-muted">
        Flujo: <span class="fw-semibold">Buscar</span> → <span class="fw-semibold">Asignar área/rubros</span> → <span class="fw-semibold">Vínculo</span> (solo si no existe) + notificación.
      </div>
      <div class="small text-muted mt-1">
        Vigencia: <span class="fw-semibold">{{ $year }}</span> · Rubros salen de <code>budgets</code> por área + año
      </div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.people.index', ['year'=>$year]) }}">Listado</a>
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.dashboard', ['year'=>$year]) }}">Volver</a>
    </div>
  </div>

  @if(session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <div class="fw-semibold mb-1">Revisa estos campos:</div>
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- ALERTAS DINÁMICAS (desde search) --}}
  <div id="flowAlertEmployee" class="alert alert-info border-0 shadow-sm d-none">
    <div class="fw-semibold">Esta persona ya está registrada como <strong>PLANTA</strong>.</div>
    <div class="small">No se solicitarán datos de planta. Solo se asignará al área/rubros.</div>
  </div>

  <div id="flowAlertContractor" class="alert alert-info border-0 shadow-sm d-none">
    <div class="fw-semibold">Esta persona ya tiene un <strong>CONTRATO ACTIVO</strong>.</div>
    <div class="small">No se solicitarán datos de contrato. Solo se asignará al área/rubros.</div>
    <div class="small mt-1" id="flowAlertContractorText"></div>
  </div>

  <div id="flowAlertOtherArea" class="alert alert-warning border-0 shadow-sm d-none">
    <div class="fw-semibold">Atención: tiene asignación activa en otra área</div>
    <div class="small" id="flowAlertOtherAreaText"></div>
  </div>

  {{-- PASO 1: BUSCAR --}}
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Paso 1) Buscar persona</div>
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Documento</label>
          <input id="doc" class="form-control" placeholder="Ej: 1000123456">
        </div>
        <div class="col-md-3">
          <button type="button" id="btnSearch" class="btn btn-primary w-100">Buscar</button>
        </div>
        <div class="col-md-5">
          <div id="searchStatus" class="text-muted small"></div>
        </div>
      </div>

      <div id="resultBox" class="mt-3 d-none">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <span class="badge bg-secondary">People</span>
          <span class="badge bg-info text-dark d-none" id="badgeHasUser">Tiene usuario</span>
          <span class="badge bg-warning text-dark d-none" id="badgeNoUser">No tiene usuario</span>

          <span class="badge bg-success d-none" id="badgeEmployee">PLANTA</span>
          <span class="badge bg-success d-none" id="badgeContractorActive">CONTRATO ACTIVO</span>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <div class="small text-muted">Nombre</div>
            <div class="fw-semibold" id="rName">-</div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted">Email personal</div>
            <div class="fw-semibold" id="rPersonal">-</div>
            <div class="small text-danger d-none" id="noPersonalEmail">No tiene correo personal registrado</div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted">Email login actual</div>
            <div class="fw-semibold" id="rLogin">-</div>
          </div>
        </div>

        <hr>

        <div class="fw-semibold mb-1">Asignaciones área/rubro (histórico)</div>
        <div id="assignmentsBox" class="small text-muted">Sin datos</div>

        <div class="mt-3 fw-semibold mb-1">Contratos</div>
        <div id="contractsBox" class="small text-muted">Sin datos</div>
      </div>
    </div>
  </div>

  {{-- FORM PRINCIPAL --}}
  <form method="POST" action="{{ route($routePrefix.'.people.store') }}" class="card shadow-sm" id="mainForm">
    @csrf

    {{-- ✅ Year: para validar rubros por budgets (area + year) en store + ajax --}}
    <input type="hidden" name="year" id="year" value="{{ $year }}">

    <div class="card-header fw-semibold">Paso 2) Registrar persona + Asignar área y rubros</div>
    <div class="card-body">
      <div class="row g-3">

        {{-- Datos básicos --}}
        <div class="col-md-4">
          <label class="form-label">Documento</label>
          <input class="form-control" name="document_number" id="document_number" required value="{{ old('document_number') }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Nombres</label>
          <input class="form-control" name="first_name" id="first_name" required value="{{ old('first_name') }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Primer apellido</label>
          <input class="form-control" name="first_last_name" id="first_last_name" required value="{{ old('first_last_name') }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Segundo apellido</label>
          <input class="form-control" name="second_last_name" id="second_last_name" value="{{ old('second_last_name') }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Email Misena</label>
          <input class="form-control" name="misena_email" id="misena_email" value="{{ old('misena_email') }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Email personal</label>
          <input class="form-control" name="personal_email" id="personal_email" value="{{ old('personal_email') }}">
        </div>

        {{-- Área y rubros --}}
        <div class="col-md-6">
          <label class="form-label">Área</label>
          <select class="form-select" name="area_id" id="area_id" required>
            <option value="">-- Selecciona --</option>
            @foreach($areas as $a)
              <option value="{{ $a->id }}">
                {{ $a->name }}
              </option>
            @endforeach
          </select>
          <div class="form-text">Los rubros se cargan desde <code>budgets</code> (área + año).</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Rubros / Budget Items</label>
          <select class="form-select" name="budget_item_ids[]" id="budget_item_ids" multiple required size="7">
            @foreach(($budgetItems ?? []) as $b)
              <option value="{{ $b->id }}">
                {{ $b->name }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- Vigencias de asignación --}}
        <div class="col-md-4">
          <label class="form-label">Vigencia asignación (inicio)</label>
          <input type="date" class="form-control" name="assignment_start_date" value="{{ old('assignment_start_date',$today) }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Vigencia asignación (fin)</label>
          <input type="date" class="form-control" name="assignment_end_date" value="{{ old('assignment_end_date') }}">
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="is_primary" @checked(old('is_primary'))>
            <label class="form-check-label" for="is_primary">Marcar como asignación principal</label>
          </div>
        </div>

        <hr class="my-2">

        {{-- PASO 3 --}}
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Paso 3) Vínculo (solo si no existe)</div>
            <div class="small text-muted">Si ya existe como Planta o tiene contrato activo, este bloque se omite.</div>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Vínculo</label>
          <select class="form-select" name="link_type" id="link_type" required>
            <option value="contractor" @selected(old('link_type','contractor')==='contractor')>Contratista</option>
            <option value="employee" @selected(old('link_type')==='employee')>Planta</option>
          </select>
        </div>

        <div class="col-md-8 d-flex align-items-end">
          <div class="alert alert-light border mb-0 w-100">
            <div class="fw-semibold">Regla</div>
            <div class="small">
              Si ya existe el vínculo (Planta o Contrato activo), el sistema solo asigna área/rubros.
            </div>
          </div>
        </div>

        {{-- BLOQUE CONTRATISTA --}}
        <div class="col-12" id="contractorFields">
          <div class="border rounded p-3">
            <div class="fw-semibold mb-2">Contratista (solo si NO tiene contrato activo)</div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Modo contrato</label>
                <select class="form-select" name="contract_mode" id="contract_mode">
                  <option value="days">Por días</option>
                  <option value="hours">Por horas</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Número de contrato</label>
                <input class="form-control" name="contract_number" id="contract_number" value="{{ old('contract_number') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Año</label>
                <input class="form-control" name="contract_year" id="contract_year" value="{{ old('contract_year') }}">
              </div>

              <div class="col-md-6">
                <label class="form-label">Tipo de contratista</label>
                <select class="form-select" name="contractor_type_id" id="contractor_type_id">
                  <option value="">-- Selecciona --</option>
                  @foreach(($contractorTypes ?? []) as $t)
                    <option value=>{{ $t->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Tipo de empleado (NOT NULL en contractors)</label>
                <select class="form-select" name="employee_type_id" id="employee_type_id">
                  <option value="">-- Selecciona --</option>
                  @foreach(($employeeTypes ?? []) as $et)
                    <option value="{{ $et->id }}">
                      {{ $et->name }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Fecha inicio</label>
                <input type="date" class="form-control" name="contract_start_date" id="contract_start_date" value="{{ old('contract_start_date',$today) }}">
              </div>

              <div class="col-md-4" id="endDateBox">
                <label class="form-label">Fecha fin</label>
                <input type="date" class="form-control" name="contract_end_date" id="contract_end_date" value="{{ old('contract_end_date') }}">
              </div>

              <div class="col-md-4" id="hoursBox">
                <label class="form-label">Horas</label>
                <input class="form-control" name="amount_hours" id="amount_hours" value="{{ old('amount_hours', 0) }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Valor asignación</label>
                <input class="form-control" name="assigment_value" id="assigment_value" value="{{ old('assigment_value', 0) }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Valor total contrato</label>
                <input class="form-control" name="total_contract_value" id="total_contract_value" value="{{ old('total_contract_value') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="state_contractor" id="state_contractor">
                  <option value="Activo" @selected(old('state_contractor','Activo')==='Activo')>Activo</option>
                  <option value="Inactivo" @selected(old('state_contractor')==='Inactivo')>Inactivo</option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">Objeto del contrato</label>
                <textarea class="form-control" name="contract_object" rows="2">{{ old('contract_object') }}</textarea>
              </div>

              <div class="col-12">
                <label class="form-label">Obligaciones</label>
                <textarea class="form-control" name="contract_obligations" rows="2">{{ old('contract_obligations') }}</textarea>
              </div>

              <hr class="my-2">

              <div class="col-12">
                <div class="fw-semibold">SIIF + Póliza + Riesgo</div>
                <div class="small text-muted">Solo aplica si estás creando contrato nuevo.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Código SIIF</label>
                <input class="form-control" name="SIIF_code" id="SIIF_code" value="{{ old('SIIF_code') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Aseguradora</label>
                <select class="form-select" name="insurer_entity_id" id="insurer_entity_id">
                  <option value="">-- Selecciona --</option>
                  @foreach(($insurers ?? []) as $ins)
                    <option value="{{ $ins->id }}" @selected((int)old('insurer_entity_id')===(int)$ins->id)>{{ $ins->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Número póliza</label>
                <input class="form-control" name="policy_number" id="policy_number" value="{{ old('policy_number') }}">
              </div>

              <div class="col-md-3">
                <label class="form-label">Expedición</label>
                <input type="date" class="form-control" name="policy_issue_date" id="policy_issue_date" value="{{ old('policy_issue_date',$today) }}">
              </div>

              <div class="col-md-3">
                <label class="form-label">Aprobación</label>
                <input type="date" class="form-control" name="policy_approval_date" id="policy_approval_date" value="{{ old('policy_approval_date',$today) }}">
              </div>

              <div class="col-md-3">
                <label class="form-label">Inicio vigencia</label>
                <input type="date" class="form-control" name="policy_effective_date" id="policy_effective_date" value="{{ old('policy_effective_date',$today) }}">
              </div>

              <div class="col-md-3">
                <label class="form-label">Fin vigencia</label>
                <input type="date" class="form-control" name="policy_expiration_date" id="policy_expiration_date" value="{{ old('policy_expiration_date') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Riesgo</label>
                <select class="form-select" name="risk_type" id="risk_type">
                  @foreach(['I','II','III','IV','V'] as $rt)
                    <option value="{{ $rt }}" @selected(old('risk_type')===$rt)>{{ $rt }}</option>
                  @endforeach
                </select>
              </div>

            </div>
          </div>
        </div>

        {{-- BLOQUE PLANTA --}}
        <div class="col-12 d-none" id="employeeFields">
          <div class="border rounded p-3">
            <div class="fw-semibold mb-2">Planta (solo si NO existe en Employees)</div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Número contrato</label>
                <input class="form-control" name="employee_contract_number" id="employee_contract_number" value="{{ old('employee_contract_number') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Fecha contrato</label>
                <input type="date" class="form-control" name="contract_date" id="contract_date" value="{{ old('contract_date',$today) }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Tipo de empleado</label>
                <select class="form-select" name="employee_type_id_planta" id="employee_type_id_planta">
                  <option value="">-- Selecciona --</option>
                  @foreach(($employeeTypes ?? []) as $et)
                    <option value="{{ $et->id }}" @selected((int)old('employee_type_id_planta')===(int)$et->id)>{{ $et->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Grado del Funcionario</label>
                {{-- ✅ tu código tenía input libre; si tienes tabla positions, cámbialo a select --}}
                <input class="form-control" name="position_id" id="position_id" value="{{ old('position_id') }}">
              </div>

              <div class="col-md-6">
                <label class="form-label">Riesgo</label>
                <select class="form-select" name="risk_type_employee" id="risk_type_employee">
                  @foreach(['I','II','III','IV','V'] as $rt)
                    <option value="{{ $rt }}" @selected(old('risk_type_employee')===$rt)>{{ $rt }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Tarjeta profesional (opcional)</label>
                <input class="form-control" name="professional_card_number" id="professional_card_number" value="{{ old('professional_card_number') }}">
              </div>

              <div class="col-md-6">
                <label class="form-label">Fecha expedición tarjeta (opcional)</label>
                <input type="date" class="form-control" name="professional_card_issue_date" id="professional_card_issue_date" value="{{ old('professional_card_issue_date') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="state_employee" id="state_employee">
                  <option value="Activo" @selected(old('state_employee','Activo')==='Activo')>Activo</option>
                  <option value="Inactivo" @selected(old('state_employee')==='Inactivo')>Inactivo</option>
                </select>
              </div>
            </div>

          </div>
        </div>

        {{-- USUARIO + NOTIFICACIÓN --}}
        <div class="col-12" id="userBox">
          <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div class="fw-semibold">Usuario y notificación</div>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="create_user" value="1" id="create_user" checked>
                  <label class="form-check-label" for="create_user">Crear usuario</label>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" @checked(old('send_email',1))>
                  <label class="form-check-label" for="send_email">Enviar correo</label>
                </div>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Email login</label>
                <input class="form-control" name="user_email" id="user_email" value="{{ old('user_email') }}" placeholder="Usa personal o Misena">
              </div>
              <div class="col-md-3">
                <label class="form-label">Nickname</label>
                <input class="form-control" name="nickname" id="nickname" value="{{ old('nickname') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Rol inicial</label>
                <input class="form-control" name="role_slug" id="role_slug" value="{{ old('role_slug','gdf.instructor') }}">
              </div>
            </div>

            <div class="small text-muted mt-2" id="userHint"></div>
          </div>
        </div>

      </div>
    </div>

    <div class="card-footer text-end">
      <button class="btn btn-primary" id="btnSave">Guardar</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const routePrefix = @json($routePrefix);

  // Alerts
  const flowAlertEmployee = document.getElementById('flowAlertEmployee');
  const flowAlertContractor = document.getElementById('flowAlertContractor');
  const flowAlertContractorText = document.getElementById('flowAlertContractorText');
  const flowAlertOtherArea = document.getElementById('flowAlertOtherArea');
  const flowAlertOtherAreaText = document.getElementById('flowAlertOtherAreaText');

  // UI refs vínculo
  const linkType = document.getElementById('link_type');
  const contractorFields = document.getElementById('contractorFields');
  const employeeFields = document.getElementById('employeeFields');

  // required toggles helpers
  const contractorRequiredIds = [
    'contractor_type_id','employee_type_id','SIIF_code','insurer_entity_id','policy_number',
    'policy_issue_date','policy_approval_date','policy_effective_date','policy_expiration_date','risk_type',
    'contract_start_date'
  ];
  const employeeRequiredIds = [
    'employee_contract_number','contract_date','employee_type_id_planta','position_id','risk_type_employee'
  ];

  function setRequired(ids, required){
    ids.forEach(id => {
      const el = document.getElementById(id);
      if(!el) return;
      if(required) el.setAttribute('required','required');
      else el.removeAttribute('required');
    });
  }

  function hideAllFlowAlerts(){
    flowAlertEmployee.classList.add('d-none');
    flowAlertContractor.classList.add('d-none');
    flowAlertOtherArea.classList.add('d-none');
    flowAlertContractorText.textContent = '';
    flowAlertOtherAreaText.textContent = '';
  }

  // State que viene de search()
  let state = {
    found: false,
    employeeExists: false,
    contractorActiveExists: false,
    contractorActive: null,
    activeAreaAssignment: null,
  };

  function applyFlowRules(){
    hideAllFlowAlerts();

    if(state.employeeExists){
      flowAlertEmployee.classList.remove('d-none');
      employeeFields.classList.add('d-none');
      setRequired(employeeRequiredIds, false);
    }

    if(state.contractorActiveExists){
      flowAlertContractor.classList.remove('d-none');
      if(state.contractorActive){
        const cn = state.contractorActive.contract_number || ('#' + state.contractorActive.id);
        const sd = state.contractorActive.start_date || '-';
        const ed = state.contractorActive.end_date || '-';
        flowAlertContractorText.textContent = `Contrato activo: ${cn} (${sd} → ${ed}).`;
      }
      contractorFields.classList.add('d-none');
      setRequired(contractorRequiredIds, false);
    }

    const selectedAreaId = document.getElementById('area_id').value;
    if(state.activeAreaAssignment && selectedAreaId){
      if(String(state.activeAreaAssignment.area_id) !== String(selectedAreaId)){
        flowAlertOtherArea.classList.remove('d-none');
        flowAlertOtherAreaText.textContent =
          `Tiene una asignación activa en "${state.activeAreaAssignment.area_name}". Puedes asignarlo también a esta área; el sistema registrará la nueva asignación.`;
      }
    }

    if(!state.employeeExists){
      if(linkType.value === 'employee'){
        employeeFields.classList.remove('d-none');
        setRequired(employeeRequiredIds, true);
      }
    }
    if(!state.contractorActiveExists){
      if(linkType.value === 'contractor'){
        contractorFields.classList.remove('d-none');
        setRequired(contractorRequiredIds, true);
      }
    }
  }

  function toggleFieldsByLinkType(){
    contractorFields.classList.toggle('d-none', linkType.value !== 'contractor');
    employeeFields.classList.toggle('d-none', linkType.value !== 'employee');

    setRequired(contractorRequiredIds, linkType.value === 'contractor');
    setRequired(employeeRequiredIds, linkType.value === 'employee');

    applyFlowRules();
  }

  // search UI refs
  const btn = document.getElementById('btnSearch');
  const status = document.getElementById('searchStatus');
  const resultBox = document.getElementById('resultBox');
  const badgeHasUser = document.getElementById('badgeHasUser');
  const badgeNoUser = document.getElementById('badgeNoUser');
  const badgeEmployee = document.getElementById('badgeEmployee');
  const badgeContractorActive = document.getElementById('badgeContractorActive');

  const rName = document.getElementById('rName');
  const rPersonal = document.getElementById('rPersonal');
  const rLogin = document.getElementById('rLogin');
  const noPersonalEmail = document.getElementById('noPersonalEmail');
  const assignmentsBox = document.getElementById('assignmentsBox');
  const contractsBox = document.getElementById('contractsBox');

  const docInput = document.getElementById('doc');
  const documentNumber = document.getElementById('document_number');
  const firstName = document.getElementById('first_name');
  const firstLast = document.getElementById('first_last_name');
  const secondLast = document.getElementById('second_last_name');
  const misena = document.getElementById('misena_email');
  const personal = document.getElementById('personal_email');
  const userEmail = document.getElementById('user_email');

  const userBox = document.getElementById('userBox');
  const createUser = document.getElementById('create_user');
  const userHint = document.getElementById('userHint');

  const areaSelect = document.getElementById('area_id');
  const budgetMulti = document.getElementById('budget_item_ids');
  const yearInput = document.getElementById('year');

  function fmtMoney(n){
    if(n === null || n === undefined || n === '') return '-';
    const x = Number(n);
    if(Number.isNaN(x)) return n;
    return '$ ' + x.toLocaleString('es-CO', { maximumFractionDigits: 0 });
  }

  // ✅ carga rubros filtrados por budgets (area + year)
  async function loadBudgetItemsForArea(areaId){
    if(!areaId){
      budgetMulti.innerHTML = '<option value="">-- Selecciona área primero --</option>';
      return;
    }

    const year = yearInput ? (yearInput.value || '') : '';
    budgetMulti.disabled = true;
    budgetMulti.innerHTML = '<option value="">Cargando...</option>';

    const url = "{{ route($routePrefix.'.budget_items.by_area') }}"
      + "?area_id=" + encodeURIComponent(areaId)
      + "&year=" + encodeURIComponent(year);

    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

    if(!res.ok){
      budgetMulti.innerHTML = '<option value="">Error cargando rubros</option>';
      budgetMulti.disabled = false;
      return;
    }

    const json = await res.json();
    const items = (json.items || []);

    budgetMulti.innerHTML = items.length
      ? items.map(i => `<option value="${i.id}">${i.name}</option>`).join('')
      : '<option value="">No hay rubros con presupuesto en esta área/vigencia</option>';

    budgetMulti.disabled = false;
  }

  // ✅ Modo contrato: por días vs por horas
  function toggleContractModeUI(){
    const mode = (document.getElementById('contract_mode')?.value || 'days');
    const endBox = document.getElementById('endDateBox');
    const hoursBox = document.getElementById('hoursBox');
    const endInput = document.getElementById('contract_end_date');
    const hoursInput = document.getElementById('amount_hours');

    if(mode === 'hours'){
      if(endBox) endBox.classList.add('d-none');
      if(endInput) endInput.removeAttribute('required');
      if(hoursBox) hoursBox.classList.remove('d-none');
      if(hoursInput) hoursInput.setAttribute('required','required');
    } else {
      if(endBox) endBox.classList.remove('d-none');
      if(hoursBox) hoursBox.classList.add('d-none');
      if(hoursInput) hoursInput.removeAttribute('required');
      // end_date no lo forzamos required (puede ser null)
    }
  }

  // init
  toggleFieldsByLinkType();
  linkType.addEventListener('change', toggleFieldsByLinkType);

  toggleContractModeUI();
  document.getElementById('contract_mode')?.addEventListener('change', toggleContractModeUI);

  if(areaSelect.value){
    loadBudgetItemsForArea(areaSelect.value);
  }
  areaSelect.addEventListener('change', () => {
    loadBudgetItemsForArea(areaSelect.value);
    applyFlowRules();
  });

  // SEARCH
  btn.addEventListener('click', async () => {
    hideAllFlowAlerts();

    const doc = (docInput.value || '').trim();
    if (!doc) { status.textContent = 'Ingresa un documento.'; return; }

    status.textContent = 'Buscando...';
    badgeHasUser.classList.add('d-none');
    badgeNoUser.classList.add('d-none');
    badgeEmployee.classList.add('d-none');
    badgeContractorActive.classList.add('d-none');
    noPersonalEmail.classList.add('d-none');

    // ✅ manda area_id para que el backend pueda calcular alertas
    const areaId = areaSelect.value || '';

    const url = "{{ route($routePrefix.'.people.search') }}"
      + "?document_number=" + encodeURIComponent(doc)
      + "&area_id=" + encodeURIComponent(areaId);

    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const json = await res.json();

    resultBox.classList.remove('d-none');

    // Reset state
    state = {
      found: !!json.found,
      employeeExists: !!(json.employee && json.employee.exists),
      contractorActiveExists: !!(json.contractor && json.contractor.active && json.contractor.active.exists),
      contractorActive: (json.contractor && json.contractor.active) ? json.contractor.active.contract : null,
      activeAreaAssignment: json.active_area_assignment || null,
    };

    if (!json.found) {
      status.textContent = 'No existe en People. Completa el formulario para crearla.';
      documentNumber.value = doc;

      rName.textContent = '-';
      rPersonal.textContent = '-';
      rLogin.textContent = '-';
      assignmentsBox.innerHTML = '<span class="text-muted">Sin datos</span>';
      contractsBox.innerHTML = '<span class="text-muted">Sin datos</span>';

      userBox.classList.remove('d-none');
      if(createUser){ createUser.checked = true; createUser.disabled = false; }
      if(userHint) userHint.textContent = '';

      applyFlowRules();
      return;
    }

    status.textContent = 'Encontrado. Datos cargados.';
    const p = json.person;

    documentNumber.value = p.document_number || doc;
    firstName.value = p.first_name || '';
    firstLast.value = p.first_last_name || '';
    secondLast.value = p.second_last_name || '';
    misena.value = p.misena_email || '';
    personal.value = p.personal_email || '';

    rName.textContent = `${p.first_name||''} ${p.first_last_name||''} ${p.second_last_name||''}`.trim();
    rPersonal.textContent = p.personal_email || '-';

    if(!p.personal_email){
      noPersonalEmail.classList.remove('d-none');
    }

    if(state.employeeExists) badgeEmployee.classList.remove('d-none');
    if(state.contractorActiveExists) badgeContractorActive.classList.remove('d-none');

    const u = json.user || { exists:false };
    if (u.exists) {
      badgeHasUser.classList.remove('d-none');
      rLogin.textContent = u.email || '-';

      userBox.classList.add('d-none');
      if(createUser){ createUser.checked = false; createUser.disabled = true; }
      if(userHint) userHint.textContent = `Ya tiene usuario: ${u.email || ''}.`;
    } else {
      badgeNoUser.classList.remove('d-none');
      rLogin.textContent = '-';

      userBox.classList.remove('d-none');
      if(createUser){ createUser.disabled = false; createUser.checked = true; }

      if (userEmail && (!userEmail.value || userEmail.value.trim() === '')) {
        userEmail.value = (p.personal_email || p.misena_email || '');
      }
      if(userHint) userHint.textContent = '';
    }

    const assigns = json.assignments || [];
    assignmentsBox.innerHTML = assigns.length ? assigns.map(a => {
      const active = a.is_active ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>';
      const primary = a.is_primary ? '<span class="badge bg-primary ms-1">Principal</span>' : '';
      return `<div class="border rounded p-2 mb-1">
        <div class="fw-semibold">${a.area_name || ('Área '+a.area_id)} · ${a.budget_item_name || ('Rubro '+a.budget_item_id)} ${active}${primary}</div>
        <div class="small text-muted">Vigencia: ${a.start_date || '-'} → ${a.end_date || '-'}</div>
        <div class="small text-muted">contractor_id: ${a.contractor_id ?? '-'}</div>
      </div>`;
    }).join('') : '<span class="text-muted">No hay asignaciones registradas.</span>';

    const cs = (json.contractor && json.contractor.recent) ? json.contractor.recent : [];
    contractsBox.innerHTML = cs.length ? cs.map(c => {
      const active = c.is_active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Finalizado/Inactivo</span>';
      return `<div class="border rounded p-2 mb-1">
        <div class="fw-semibold">Contrato ${c.contract_number ?? c.id} ${active}</div>
        <div class="small text-muted">${c.start_date || '-'} → ${c.end_date || '-'} · ${fmtMoney(c.total_contract_value)}</div>
      </div>`;
    }).join('') : '<span class="text-muted">No hay contratos recientes.</span>';

    // aplicar reglas flujo
    applyFlowRules();
  });

});
</script>
@endsection
