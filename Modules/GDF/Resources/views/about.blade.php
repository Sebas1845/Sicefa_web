@extends('gdf::layouts.master')

@section('title', 'GDF | Sobre el software')

@section('content')
<div class="container py-5">

  <div class="d-flex justify-content-end flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.index') }}"><i class="bi bi-house"></i> Inicio</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.developers') }}"><i class="bi bi-people"></i> Desarrolladores</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.tech') }}"><i class="bi bi-tools"></i> Tecnologías</a>
  </div>

  <style>
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    @keyframes slideIn {
      from { opacity: 0; transform: translateX(-20px); }
      to { opacity: 1; transform: translateX(0); }
    }

    .about-hero{
      border-radius: 24px;
      padding: 40px 24px;
      background:
        radial-gradient(1000px 500px at 15% 5%, rgba(46,125,50,.18), transparent 65%),
        radial-gradient(1000px 500px at 85% 10%, rgba(36,132,196,.18), transparent 65%),
        linear-gradient(135deg, #ffffff, #f0f8ff 50%, #fafffe);
      border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 20px 60px rgba(0,0,0,.12);
      position: relative;
      overflow: hidden;
      animation: fadeInUp 0.6s ease-out;
    }
    
    .about-hero::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(46,125,50,.05) 0%, transparent 70%);
      animation: pulse 8s ease-in-out infinite;
    }
    
    .about-hero > * {
      position: relative;
      z-index: 1;
    }

    .kpi{
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      background: linear-gradient(135deg, #ffffff, #fafbfc);
      padding: 24px;
      box-shadow: 0 12px 28px rgba(0,0,0,.08);
      height: 100%;
      transition: all 0.3s ease;
      animation: fadeInUp 0.6s ease-out backwards;
    }
    
    .kpi:nth-child(1) { animation-delay: 0.1s; }
    .kpi:nth-child(2) { animation-delay: 0.2s; }
    .kpi:nth-child(3) { animation-delay: 0.3s; }
    
    .kpi:hover{
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0,0,0,.15);
      border-color: rgba(36,132,196,.3);
    }
    
    .kpi-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
      background: linear-gradient(135deg, rgba(36,132,196,.1), rgba(36,132,196,.2));
      border: 1px solid rgba(36,132,196,.2);
    }
    
    .kpi-icon i {
      font-size: 24px;
      color: #2484c4;
    }

    .pill{
      display: inline-flex;
      gap: 0.5rem;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 999px;
      border: 1px solid rgba(0,0,0,.1);
      background: linear-gradient(135deg, rgba(46,125,50,.12), rgba(46,125,50,.08));
      color: #1f6a24;
      font-weight: 700;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(46,125,50,.15);
    }
    
    .pill:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(46,125,50,.25);
    }
    
    .pill.blue{
      background: linear-gradient(135deg, rgba(36,132,196,.12), rgba(36,132,196,.08));
      color: #1a5e8a;
      box-shadow: 0 4px 10px rgba(36,132,196,.15);
    }
    
    .pill.blue:hover {
      box-shadow: 0 6px 16px rgba(36,132,196,.25);
    }

    .role-card{
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      background: linear-gradient(135deg, #ffffff, #fafbfc);
      padding: 20px;
      box-shadow: 0 12px 28px rgba(0,0,0,.08);
      height: 100%;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      animation: slideIn 0.5s ease-out backwards;
    }
    
    .role-card:nth-child(1) { animation-delay: 0.1s; }
    .role-card:nth-child(2) { animation-delay: 0.15s; }
    .role-card:nth-child(3) { animation-delay: 0.2s; }
    .role-card:nth-child(4) { animation-delay: 0.25s; }
    .role-card:nth-child(5) { animation-delay: 0.3s; }
    .role-card:nth-child(6) { animation-delay: 0.35s; }
    .role-card:nth-child(7) { animation-delay: 0.4s; }
    
    .role-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #2484c4, #2e7d32);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }
    
    .role-card:hover::before {
      transform: scaleX(1);
    }
    
    .role-card:hover{
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0,0,0,.15);
      border-color: rgba(46,125,50,.3);
    }
    
    .role-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
      background: linear-gradient(135deg, rgba(46,125,50,.1), rgba(46,125,50,.2));
      border: 1px solid rgba(46,125,50,.2);
    }
    
    .role-icon i {
      font-size: 22px;
      color: #2e7d32;
    }

    .role-slug{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.78rem;
      color: rgba(0,0,0,.6);
      background: rgba(0,0,0,.03);
      padding: 4px 8px;
      border-radius: 6px;
      display: inline-block;
      margin-top: 6px;
    }
    
    .process-step {
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      background: linear-gradient(135deg, #ffffff, #fafbfc);
      padding: 28px 20px;
      box-shadow: 0 12px 28px rgba(0,0,0,.08);
      transition: all 0.3s ease;
      position: relative;
      animation: fadeInUp 0.6s ease-out backwards;
    }
    
    .process-step:nth-child(1) { animation-delay: 0.1s; }
    .process-step:nth-child(2) { animation-delay: 0.2s; }
    .process-step:nth-child(3) { animation-delay: 0.3s; }
    .process-step:nth-child(4) { animation-delay: 0.4s; }
    
    .process-step:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0,0,0,.15);
    }
    
    .process-icon {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      background: linear-gradient(135deg, rgba(36,132,196,.15), rgba(36,132,196,.25));
      border: 2px solid rgba(36,132,196,.3);
    }
    
    .process-icon i {
      font-size: 32px;
      color: #2484c4;
    }
    
    .process-number {
      position: absolute;
      top: -12px;
      right: -12px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #2e7d32, #1f6a24);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
      box-shadow: 0 4px 12px rgba(46,125,50,.3);
    }
    
    .stats-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1.2rem;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(46,125,50,.1), rgba(46,125,50,.15));
      border: 1px solid rgba(46,125,50,.2);
      font-weight: 600;
      color: #1f6a24;
      margin: 0.25rem;
    }
    
    .section-title {
      font-weight: 800;
      color: #0c3c5a;
      position: relative;
      display: inline-block;
      padding-bottom: 12px;
      margin-bottom: 24px;
    }
    
    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 4px;
      background: linear-gradient(90deg, #2484c4, #2e7d32);
      border-radius: 2px;
    }
    
    .feature-list {
      list-style: none;
      padding: 0;
      margin-top: 16px;
    }
    
    .feature-list li {
      padding: 8px 0;
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(0,0,0,.75);
    }
    
    .feature-list li::before {
      content: '✓';
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      background: linear-gradient(135deg, #2e7d32, #1f6a24);
      color: white;
      border-radius: 50%;
      font-weight: 700;
      font-size: 0.85rem;
      flex-shrink: 0;
    }
  </style>

  <div class="about-hero text-center mb-5">
    <h1 class="fw-bold mb-3" style="font-size: 2.5rem; color: #0c3c5a;">
      <i class="bi bi-clipboard-check-fill" style="color: #2e7d32;"></i> 
      Bienvenido a GDF / SITRAV
    </h1>
    <p class="text-muted mb-4" style="font-size: 1.1rem; max-width: 800px; margin: 0 auto;">
      Sistema integral de gestión de desplazamientos con control por áreas, flujo por roles y trazabilidad completa. 
      SITRAV integra solicitudes provenientes de SIGAC para una administración unificada.
    </p>

    <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
      <span class="pill"><i class="bi bi-geo-alt-fill"></i> Áreas: Académica / CampeSena</span>
      <span class="pill blue"><i class="bi bi-shield-lock-fill"></i> Acceso por roles + contexto</span>
      <span class="pill"><i class="bi bi-journal-check"></i> Trazabilidad y auditoría</span>
      <span class="pill blue"><i class="bi bi-graph-up-arrow"></i> Reportes y KPIs</span>
    </div>
  </div>

  <div class="row g-4 mb-5">
    <div class="col-lg-4">
      <div class="kpi">
        <div class="kpi-icon">
          <i class="bi bi-bullseye"></i>
        </div>
        <h5 class="fw-bold mb-3">Objetivo del Sistema</h5>
        <div class="text-muted mb-3">
          Centralizar y gestionar eficientemente todas las solicitudes de desplazamiento, proporcionando seguimiento en tiempo real 
          por etapas: validación inicial, control financiero y decisión final.
        </div>
        <ul class="feature-list">
          <li>Control por etapas</li>
          <li>Validación automática</li>
          <li>Auditoría completa</li>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="kpi">
        <div class="kpi-icon">
          <i class="bi bi-layers-fill"></i>
        </div>
        <h5 class="fw-bold mb-3">Gestión por Áreas</h5>
        <div class="text-muted mb-3">
          El sistema opera por <b>contexto dinámico</b>: cada usuario trabaja en el área <b>Académica</b> o <b>CampeSena</b> 
          según su asignación y rol específico.
        </div>
        <ul class="feature-list">
          <li>Contexto por usuario</li>
          <li>Permisos específicos</li>
          <li>Gateway inteligente</li>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="kpi">
        <div class="kpi-icon">
          <i class="bi bi-diagram-3-fill"></i>
        </div>
        <h5 class="fw-bold mb-3">GDF vs SITRAV</h5>
        <div class="text-muted mb-3">
          <b>GDF:</b> Solicitudes creadas directamente en el sistema.<br>
          <b>SITRAV:</b> Solicitudes importadas desde <b>SIGAC</b> (programación académica) con sincronización de fechas/horas.
        </div>
        <ul class="feature-list">
          <li>Integración SIGAC</li>
          <li>Sincronización automática</li>
        </ul>
      </div>
    </div>
  </div>

  <h3 class="text-center section-title mb-4">Roles del Sistema</h3>
  <div class="mb-4 text-center">
    <div class="d-inline-flex flex-wrap justify-content-center gap-2">
      <span class="stats-badge"><i class="bi bi-people-fill"></i> 7 roles definidos</span>
      <span class="stats-badge"><i class="bi bi-shield-check"></i> Acceso granular</span>
      <span class="stats-badge"><i class="bi bi-arrow-repeat"></i> Flujo optimizado</span>
    </div>
  </div>

  <div class="row g-4 mb-5">
    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Instructor / Funcionario</div>
        <div class="role-slug">gdf.instructor</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Crea y gestiona solicitudes, adjunta documentos de soporte, registra segmentos de viaje y costos asociados. 
          Opera dentro del área asignada (Académica o CampeSena).
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-person-check-fill"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Apoyo Operativo</div>
        <div class="role-slug">gdf.academic_support · gdf.campesena_support</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Valida información y soportes, ajusta datos según necesidad, y gestiona recursos operativos como asignación 
          de vehículos dentro de los cupos establecidos.
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Coordinación</div>
        <div class="role-slug">gdf.academic_coordinator · gdf.campesena_coordinator</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Supervisa solicitudes por área, coordina asignaciones operativas con el equipo de Apoyo, y gestiona 
          el flujo de revisión según políticas institucionales.
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-wallet2"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Tesorería</div>
        <div class="role-slug">gdf.treasury</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Valida disponibilidad de recursos financieros, realiza análisis y monitoreo de costos, y aprueba o 
          devuelve solicitudes en la etapa financiera según normativa.
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Subdirección</div>
        <div class="role-slug">gdf.subdirection</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Toma decisiones estratégicas como cupos por área, define políticas institucionales y aprueba solicitudes 
          por umbral o excepciones. Menor carga operativa.
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-shield-check"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Admin GDF</div>
        <div class="role-slug">gdf.admin</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Gestiona parametrización operativa del sistema, configura opciones y tablas maestras, y proporciona 
          soporte técnico-funcional interno al módulo.
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="role-card">
        <div class="role-icon">
          <i class="bi bi-stars"></i>
        </div>
        <div class="fw-bold" style="font-size: 1.1rem; color: #0c3c5a;">Super Admin</div>
        <div class="role-slug">gdf.superadmin</div>
        <div class="text-muted mt-3" style="font-size: 0.95rem;">
          Acceso total al sistema para soporte global de TI, realización de pruebas en diferentes ambientes, 
          y correcciones críticas cuando se requiera.
        </div>
      </div>
    </div>
  </div>

  <h3 class="text-center section-title mb-4">Flujo del Proceso</h3>
  <div class="row g-4">
    <div class="col-md-6 col-lg-3">
      <div class="process-step text-center">
        <div class="process-number">1</div>
        <div class="process-icon">
          <i class="bi bi-file-earmark-plus-fill"></i>
        </div>
        <h5 class="fw-bold mb-2">Creación</h5>
        <div class="text-muted">
          El instructor registra la solicitud con todos los detalles: destinos, fechas, segmentos de viaje, 
          costos estimados y documentos de soporte.
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="process-step text-center">
        <div class="process-number">2</div>
        <div class="process-icon">
          <i class="bi bi-clipboard-check-fill"></i>
        </div>
        <h5 class="fw-bold mb-2">Validación</h5>
        <div class="text-muted">
          Apoyo y Coordinación revisan consistencia de información, verifican documentación, validan políticas 
          y asignan recursos operativos.
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="process-step text-center">
        <div class="process-number">3</div>
        <div class="process-icon">
          <i class="bi bi-cash-coin"></i>
        </div>
        <h5 class="fw-bold mb-2">Control Financiero</h5>
        <div class="text-muted">
          Tesorería realiza análisis presupuestal, valida disponibilidad de recursos, verifica cumplimiento 
          normativo y controla ejecución.
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="process-step text-center">
        <div class="process-number">4</div>
        <div class="process-icon">
          <i class="bi bi-shield-check"></i>
        </div>
        <h5 class="fw-bold mb-2">Decisión Final</h5>
        <div class="text-muted">
          Subdirección toma la decisión definitiva: aprobar, devolver para ajustes o rechazar la solicitud 
          con las observaciones correspondientes.
        </div>
      </div>
    </div>
  </div>

</div>
@endsection