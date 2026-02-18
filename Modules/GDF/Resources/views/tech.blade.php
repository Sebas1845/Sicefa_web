@extends('gdf::layouts.master')

@section('title', 'GDF | Tecnologías')

@section('content')
@php
  $toolsBase = 'modules/gdf/css/images/tools';
@endphp

<div class="container py-4">

  <div class="d-flex justify-content-end flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.index') }}"><i class="bi bi-house"></i> Inicio</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.about') }}"><i class="bi bi-info-circle"></i> Sobre el software</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('gdf.developers') }}"><i class="bi bi-people"></i> Desarrolladores</a>
  </div>

  <style>
    :root{
      --blue:#2484c4;
      --green:#2e7d32;
      --ink:#0b0f11;
    }
    
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes glow {
      0%, 100% { box-shadow: 0 18px 45px rgba(0,0,0,.08); }
      50% { box-shadow: 0 18px 45px rgba(36,132,196,.2); }
    }

    .tech-hero{
      border-radius: 24px;
      padding: 40px 24px;
      background:
        radial-gradient(1000px 500px at 15% 10%, rgba(36,132,196,.25), transparent 65%),
        radial-gradient(900px 500px at 80% 0%, rgba(46,125,50,.20), transparent 60%),
        linear-gradient(135deg, #ffffff, #f0f8ff 50%, #fafffe);
      border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 20px 60px rgba(0,0,0,.12);
      animation: fadeInUp 0.6s ease-out, glow 4s ease-in-out infinite;
      position: relative;
      overflow: hidden;
    }
    
    .tech-hero::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, rgba(36,132,196,.08) 0%, transparent 70%);
      animation: float 6s ease-in-out infinite;
    }
    
    .tech-hero > * {
      position: relative;
      z-index: 1;
    }

    .tech-title{
      font-weight: 900;
      color: #0c3c5a;
      margin: 0;
      font-size: 2.2rem;
      text-shadow: 0 2px 10px rgba(36,132,196,.1);
    }
    
    .tech-sub{
      color: rgba(12,60,90,.75);
      max-width: 950px;
      margin: 1rem auto 0 auto;
      font-size: 1.1rem;
      line-height: 1.6;
    }

    .lane{
      margin-top: 32px;
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid rgba(36,132,196,.2);
      background: linear-gradient(135deg, #eef7ff, #ffffff);
      box-shadow: 0 16px 40px rgba(0,0,0,.08);
      position: relative;
    }
    
    .track{
      display: flex;
      width: max-content;
      gap: 18px;
      padding: 24px 18px;
      animation: scroll 45s linear infinite;
      will-change: transform;
    }
    
    .lane:hover .track{ animation-play-state: paused; }

    .cardx{
      flex: 0 0 auto;
      width: 320px;
      background: #fff;
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 12px 28px rgba(0,0,0,.1);
      padding: 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .cardx::before {
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
    
    .cardx:hover::before {
      transform: scaleX(1);
    }
    
    .cardx:hover{
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0,0,0,.15);
      border-color: rgba(36,132,196,.3);
    }
    
    .iconWrap{
      width: 80px;
      height: 80px;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(36,132,196,.12), rgba(36,132,196,.18));
      border: 2px solid rgba(36,132,196,.2);
      margin: 0 auto 16px auto;
      transition: all 0.3s ease;
    }
    
    .cardx:hover .iconWrap {
      transform: scale(1.1) rotate(5deg);
      background: linear-gradient(135deg, rgba(36,132,196,.2), rgba(46,125,50,.15));
    }
    
    .cardx img{
      width: 48px;
      height: 48px;
      object-fit: contain;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,.1));
    }
    
    .cardx h5{
      font-weight: 900;
      color: #0c3c5a;
      margin: 0 0 10px 0;
      text-align: center;
      font-size: 1.2rem;
    }
    
    .cardx p{
      margin: 0;
      color: rgba(0,0,0,.7);
      font-size: 0.95rem;
      text-align: center;
      line-height: 1.6;
    }
    
    .hint{
      position: absolute;
      right: 16px;
      top: 16px;
      font-size: 0.85rem;
      padding: 0.5rem 1rem;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(46,125,50,.12), rgba(46,125,50,.18));
      border: 1px solid rgba(46,125,50,.25);
      color: #1f6a24;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(46,125,50,.2);
      animation: float 3s ease-in-out infinite;
    }
    
    .tech-stats {
      display: flex;
      justify-content: center;
      gap: 20px;
      flex-wrap: wrap;
      margin-top: 32px;
    }
    
    .stat-box {
      background: linear-gradient(135deg, #ffffff, #fafbfc);
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 16px;
      padding: 20px 32px;
      box-shadow: 0 12px 28px rgba(0,0,0,.08);
      transition: all 0.3s ease;
      text-align: center;
      min-width: 180px;
    }
    
    .stat-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0,0,0,.15);
    }
    
    .stat-number {
      font-size: 2.5rem;
      font-weight: 900;
      background: linear-gradient(135deg, #2484c4, #2e7d32);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .stat-label {
      color: rgba(0,0,0,.65);
      font-weight: 600;
      font-size: 0.9rem;
      margin-top: 8px;
    }
    
    .tech-category {
      margin-top: 48px;
      padding: 32px;
      background: linear-gradient(135deg, #ffffff, #fafbfc);
      border-radius: 20px;
      border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 12px 28px rgba(0,0,0,.08);
    }
    
    .category-title {
      font-weight: 800;
      color: #0c3c5a;
      font-size: 1.5rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .category-title i {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(36,132,196,.15), rgba(36,132,196,.25));
      border-radius: 10px;
      font-size: 20px;
      color: #2484c4;
    }
    
    .tech-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
      margin-top: 20px;
    }
    
    .tech-item {
      background: white;
      padding: 16px;
      border-radius: 12px;
      border: 1px solid rgba(0,0,0,.06);
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .tech-item:hover {
      transform: translateX(5px);
      box-shadow: 0 8px 20px rgba(0,0,0,.1);
      border-color: rgba(36,132,196,.3);
    }
    
    .tech-item i {
      font-size: 24px;
      color: #2484c4;
    }
    
    .tech-item-content h6 {
      margin: 0;
      font-weight: 700;
      color: #0c3c5a;
      font-size: 0.95rem;
    }
    
    .tech-item-content p {
      margin: 4px 0 0 0;
      font-size: 0.85rem;
      color: rgba(0,0,0,.6);
    }

    @keyframes scroll{
      0%{ transform: translateX(0); }
      100%{ transform: translateX(-50%); }
    }
  </style>

  <div class="tech-hero text-center">
    <h2 class="tech-title">🛠️ Tecnologías Utilizadas</h2>
    <p class="tech-sub">
      Stack tecnológico utilizado en <b>GDF</b> y <b>SITRAV</b> para la gestión de desplazamientos y fondos.
    </p>

    @php
      $tools = [
        ['lenguajes.png',  'Lenguajes',     'PHP, JavaScript, HTML5, CSS3 y Blade.'],
        ['frameworks.png', 'Framework',    'Laravel como framework principal con Bootstrap para UI.'],
        ['mysql.png',      'Base de Datos',  'MySQL con Eloquent ORM.'],
        ['security.png',   'Seguridad',     'Sistema de roles y permisos por contexto.'],
        ['estructura.png', 'Arquitectura',  'Diseño modular por dominios (GDF/SITRAV).'],
      ];
    @endphp

    <div class="lane">
      <div class="hint"><i class="bi bi-hand-index"></i> Hover para pausar</div>

      <div class="track">
        @foreach($tools as $t)
          <div class="cardx">
            <div class="iconWrap">
              <img src="{{ asset($toolsBase.'/'.$t[0]) }}" alt="{{ $t[1] }}">
            </div>
            <h5>{{ $t[1] }}</h5>
            <p>{{ $t[2] }}</p>
          </div>
        @endforeach

        {{-- duplicado para scroll infinito --}}
        @foreach($tools as $t)
          <div class="cardx">
            <div class="iconWrap">
              <img src="{{ asset($toolsBase.'/'.$t[0]) }}" alt="{{ $t[1] }}">
            </div>
            <h5>{{ $t[1] }}</h5>
            <p>{{ $t[2] }}</p>
          </div>
        @endforeach
      </div>
    </div>

    <div class="tech-stats">
      <div class="stat-box">
        <div class="stat-number">6</div>
        <div class="stat-label">Tecnologías</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">2</div>
        <div class="stat-label">Módulos</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">7</div>
        <div class="stat-label">Roles</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">100%</div>
        <div class="stat-label">Laravel</div>
      </div>
    </div>
  </div>

  {{-- Frontend --}}
  <div class="tech-category">
    <div class="category-title">
      <i class="bi bi-code-square"></i>
      Frontend
    </div>
    <div class="tech-grid">
      <div class="tech-item">
        <i class="bi bi-bootstrap-fill"></i>
        <div class="tech-item-content">
          <h6>Bootstrap 5</h6>
          <p>Framework CSS para diseño responsivo</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-table"></i>
        <div class="tech-item-content">
          <h6>DataTables</h6>
          <p>Tablas interactivas con búsqueda</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-map-fill"></i>
        <div class="tech-item-content">
          <h6>Leaflet.js</h6>
          <p>Mapas interactivos</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-filetype-js"></i>
        <div class="tech-item-content">
          <h6>JavaScript y CSS</h6>
          <p>Interactividad del cliente</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Backend --}}
  <div class="tech-category">
    <div class="category-title">
      <i class="bi bi-server"></i>
      Backend
    </div>
    <div class="tech-grid">
      <div class="tech-item">
        <i class="bi bi-code-slash"></i>
        <div class="tech-item-content">
          <h6>Laravel</h6>
          <p>Framework PHP</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-database-fill"></i>
        <div class="tech-item-content">
          <h6>MySQL</h6>
          <p>Base de datos relacional</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-gear-fill"></i>
        <div class="tech-item-content">
          <h6>Middleware</h6>
          <p>Control de acceso</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-file-earmark-code"></i>
        <div class="tech-item-content">
          <h6>Blade</h6>
          <p>Motor de plantillas</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Seguridad --}}
  <div class="tech-category">
    <div class="category-title">
      <i class="bi bi-shield-check"></i>
      Seguridad
    </div>
    <div class="tech-grid">
      <div class="tech-item">
        <i class="bi bi-person-check-fill"></i>
        <div class="tech-item-content">
          <h6>Autenticación</h6>
          <p>Sistema de login seguro</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-shield-lock-fill"></i>
        <div class="tech-item-content">
          <h6>Roles & Permisos</h6>
          <p>Control por rol y contexto</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-journal-check"></i>
        <div class="tech-item-content">
          <h6>Auditoría</h6>
          <p>Trazabilidad de operaciones</p>
        </div>
      </div>
      <div class="tech-item">
        <i class="bi bi-file-earmark-lock"></i>
        <div class="tech-item-content">
          <h6>Validación</h6>
          <p>Validación de datos</p>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection