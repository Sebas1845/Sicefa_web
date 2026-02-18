@extends('gdf::layouts.master')

@section('title', 'GDF | Créditos / Equipos')

@section('content')

@php
  $imgBase = 'modules/gdf/css/images/Contacto';

  $teamGdf = [
    ['nombre'=>'Leonel Zuñiga Alvarez', 'rol'=>'Desarrollador', 'foto'=>'Leonel.jpg', 'linkedin'=>'#', 'github'=>'https://github.com/kira44-cmy'],
    ['nombre'=>'Fabián Torres Andrade', 'rol'=>'Desarrollador', 'foto'=>'Fabian.jpg', 'linkedin'=>'#', 'github'=>'https://github.com/Fabian3115'],
    ['nombre'=>'Sebastián Cuellar Ramírez', 'rol'=>'Desarrollador', 'foto'=>'Sebas.jpg', 'linkedin'=>'www.linkedin.com/in/Sebas1845', 'github'=>'https://github.com/Sebas1845'],
    ['nombre'=>'Camilo Cuellar Rubio', 'rol'=>'Desarrollador', 'foto'=>'Camilo.jpg', 'linkedin'=>'#', 'github'=>'https://github.com/CamiloCR26'],
    ['nombre'=>'Claudia Moreno Palomino', 'rol'=>'Desarrolladora', 'foto'=>'Claudia.jpg', 'linkedin'=>'#', 'github'=>'https://github.com/claudia20252'],
  ];

  $teamSitrav = [
    ['nombre'=>'Sebastián Cuellar Ramírez', 'rol'=>'Desarrollador', 'foto'=>'Sebas.jpg', 'linkedin'=>'www.linkedin.com/in/Sebas1845', 'github'=>'https://github.com/Sebas1845'],
    ['nombre'=>'William Chilito Sabi', 'rol'=>'Desarrollador', 'foto'=>'William.jpg', 'linkedin'=>'#', 'github'=>'#'],
  ];

  $fallbackPhoto = asset($imgBase.'/default.png');
@endphp

<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
  
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
  }
  
  body {
    background: #f8f9fa;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .page-container {
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    min-height: 100vh;
    padding: 40px 0;
  }
  
  .top-nav {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 50px;
    flex-wrap: wrap;
  }
  
  .top-nav .btn {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
  }
  
  .top-nav .btn:hover {
    border-color: #2e7d32;
    color: #2e7d32;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.15);
  }
  
  .hero-section {
    text-align: center;
    margin-bottom: 60px;
    animation: fadeIn 0.8s ease-out;
  }
  
  .hero-title {
    font-size: 4rem;
    font-weight: 900;
    background: linear-gradient(135deg, #2e7d32 0%, #1565c0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
    letter-spacing: -2px;
  }
  
  .hero-subtitle {
    font-size: 1.25rem;
    color: #64748b;
    max-width: 800px;
    margin: 0 auto 40px;
    line-height: 1.8;
    font-weight: 400;
  }
  
  .metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    max-width: 1000px;
    margin: 0 auto 60px;
  }
  
  .metric-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    border: 2px solid #f1f5f9;
    transition: all 0.3s ease;
    animation: slideUp 0.6s ease-out backwards;
  }
  
  .metric-card:nth-child(1) { animation-delay: 0.1s; }
  .metric-card:nth-child(2) { animation-delay: 0.2s; }
  .metric-card:nth-child(3) { animation-delay: 0.3s; }
  .metric-card:nth-child(4) { animation-delay: 0.4s; }
  
  .metric-card:hover {
    border-color: #2e7d32;
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.1);
  }
  
  .metric-value {
    font-size: 3rem;
    font-weight: 900;
    background: linear-gradient(135deg, #2e7d32 0%, #1565c0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  
  .metric-label {
    font-size: 0.95rem;
    color: #94a3b8;
    font-weight: 600;
    margin-top: 8px;
  }
  
  .tabs-container {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-bottom: 50px;
  }
  
  .tab-button {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 16px 40px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .tab-button:hover {
    border-color: #cbd5e1;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  }
  
  .tab-button.active {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    border-color: #2e7d32;
    color: white;
    box-shadow: 0 15px 35px rgba(46, 125, 50, 0.25);
  }
  
  .tab-button.sitrav.active {
    background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
    border-color: #1565c0;
    box-shadow: 0 15px 35px rgba(21, 101, 192, 0.25);
  }
  
  .section-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 2px solid #bbf7d0;
    color: #166534;
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1.05rem;
    margin-bottom: 40px;
  }
  
  .section-badge.sitrav {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-color: #bfdbfe;
    color: #1e40af;
  }
  
  .team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
  }
  
  .member-card {
    background: white;
    border-radius: 24px;
    border: 2px solid #f1f5f9;
    padding: 35px 30px;
    text-align: center;
    transition: all 0.4s ease;
    animation: slideUp 0.6s ease-out backwards;
    position: relative;
    overflow: hidden;
  }
  
  .member-card:nth-child(1) { animation-delay: 0.1s; }
  .member-card:nth-child(2) { animation-delay: 0.2s; }
  .member-card:nth-child(3) { animation-delay: 0.3s; }
  .member-card:nth-child(4) { animation-delay: 0.4s; }
  .member-card:nth-child(5) { animation-delay: 0.5s; }
  
  .member-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #2e7d32 0%, #1565c0 100%);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }
  
  .member-card:hover::before {
    transform: scaleX(1);
  }
  
  .member-card:hover {
    border-color: #2e7d32;
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(46, 125, 50, 0.15);
  }
  
  .member-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 20px;
    border: 4px solid #f1f5f9;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
  }
  
  .member-card:hover .member-avatar {
    transform: scale(1.08);
    border-color: #2e7d32;
    box-shadow: 0 15px 40px rgba(46, 125, 50, 0.2);
  }
  
  .member-name {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
  }
  
  .member-role {
    font-size: 1rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 20px;
  }
  
  .social-links {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
  }
  
  .social-link {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid transparent;
  }
  
  .social-link.linkedin {
    background: #0077b5;
    color: white;
  }
  
  .social-link.linkedin:hover {
    background: #005582;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 119, 181, 0.3);
  }
  
  .social-link.github {
    background: #333;
    color: white;
  }
  
  .social-link.github:hover {
    background: #000;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
  }
  
  .info-alert {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #1565c0;
    border-radius: 16px;
    padding: 25px;
    margin-top: 40px;
    display: flex;
    gap: 16px;
    align-items: start;
  }
  
  .info-alert i {
    font-size: 28px;
    color: #1565c0;
    flex-shrink: 0;
  }
  
  .info-alert-content {
    color: #1e40af;
    line-height: 1.7;
  }
  
  .info-alert strong {
    font-weight: 700;
    color: #1e3a8a;
  }
  
  .panel {
    display: block;
    animation: fadeIn 0.5s ease-out;
  }
  
  .panel.hide {
    display: none;
  }
  
  @media (max-width: 768px) {
    .hero-title {
      font-size: 2.5rem;
    }
    
    .tabs-container {
      flex-direction: column;
      align-items: stretch;
    }
    
    .team-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="page-container">
  <div class="container">
    <div class="top-nav">
      <a class="btn" href="{{ route('gdf.index') }}">
        <i class="bi bi-house-fill"></i> Inicio
      </a>
      <a class="btn" href="{{ route('gdf.about') }}">
        <i class="bi bi-info-circle-fill"></i> Sobre el Software
      </a>
      <a class="btn" href="{{ route('gdf.tech') }}">
        <i class="bi bi-tools"></i> Tecnologías
      </a>
    </div>
    
    <div class="hero-section">
      <h1 class="hero-title">Desarrolladores</h1>
      <p class="hero-subtitle">
        Equipo de desarrollo de <strong>GDF</strong> y <strong>SITRAV</strong>
      </p>
    </div>
    
    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-value">6</div>
        <div class="metric-label">Desarrolladores</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">2</div>
        <div class="metric-label">Módulos</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">12</div>
        <div class="metric-label">Meses</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">100%</div>
        <div class="metric-label">Compromiso</div>
      </div>
    </div>
    
    <div class="tabs-container">
      <button class="tab-button active" id="tab-gdf" data-team="gdf">
        <i class="bi bi-briefcase-fill"></i> Equipo GDF
      </button>
      <button class="tab-button sitrav" id="tab-sitrav" data-team="sitrav">
        <i class="bi bi-diagram-3-fill"></i> Equipo SITRAV
      </button>
    </div>
    
    <!-- Panel GDF -->
    <div id="team-gdf" class="panel">
      <div class="text-center">
        <span class="section-badge">
          <i class="bi bi-briefcase-fill"></i>
          Módulo GDF
        </span>
      </div>
      
      <div class="team-grid">
        @foreach($teamGdf as $dev)
          <div class="member-card">
            <img class="member-avatar"
                 src="{{ asset($imgBase.'/'.$dev['foto']) }}"
                 alt="{{ $dev['nombre'] }}"
                 onerror="this.onerror=null;this.src='{{ $fallbackPhoto }}'">
            <h3 class="member-name">{{ $dev['nombre'] }}</h3>
            <div class="member-role">{{ $dev['rol'] }}</div>
            
            <div class="social-links">
              <a href="{{ $dev['linkedin'] }}" class="social-link linkedin" target="_blank" title="LinkedIn">
                <i class="bi bi-linkedin"></i>
              </a>
              <a href="{{ $dev['github'] }}" class="social-link github" target="_blank" title="GitHub">
                <i class="bi bi-github"></i>
              </a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
    
    <!-- Panel SITRAV -->
    <div id="team-sitrav" class="panel hide">
      <div class="text-center">
        <span class="section-badge sitrav">
          <i class="bi bi-diagram-3-fill"></i>
          Módulo SITRAV
        </span>
      </div>
      
      <div class="team-grid">
        @foreach($teamSitrav as $dev)
          <div class="member-card">
            <img class="member-avatar"
                 src="{{ asset($imgBase.'/'.$dev['foto']) }}"
                 alt="{{ $dev['nombre'] }}"
                 onerror="this.onerror=null;this.src='{{ $fallbackPhoto }}'">
            <h3 class="member-name">{{ $dev['nombre'] }}</h3>
            <div class="member-role">{{ $dev['rol'] }}</div>
            
            <div class="social-links">
              <a href="{{ $dev['linkedin'] }}" class="social-link linkedin" target="_blank" title="LinkedIn">
                <i class="bi bi-linkedin"></i>
              </a>
              <a href="{{ $dev['github'] }}" class="social-link github" target="_blank" title="GitHub">
                <i class="bi bi-github"></i>
              </a>
            </div>
          </div>
        @endforeach
      </div>
      
      <div class="info-alert">
        <i class="bi bi-info-circle-fill"></i>
        <div class="info-alert-content">
          <strong>Integración SIGAC → SITRAV:</strong>
          SITRAV recibe solicitudes de programación académica desde SIGAC y las convierte en solicitudes 
          de desplazamiento completas con gestión de costos y viáticos.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const btnGdf = document.getElementById('tab-gdf');
  const btnSitrav = document.getElementById('tab-sitrav');
  const boxGdf = document.getElementById('team-gdf');
  const boxSitrav = document.getElementById('team-sitrav');

  function setTeam(team) {
    const isGdf = team === 'gdf';
    
    btnGdf.classList.toggle('active', isGdf);
    btnSitrav.classList.toggle('active', !isGdf);
    boxGdf.classList.toggle('hide', !isGdf);
    boxSitrav.classList.toggle('hide', isGdf);
  }

  btnGdf.addEventListener('click', () => setTeam('gdf'));
  btnSitrav.addEventListener('click', () => setTeam('sitrav'));
})();
</script>

@endsection