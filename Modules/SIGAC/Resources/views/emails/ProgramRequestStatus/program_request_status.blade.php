@php
  $type = $type ?? $type ?? 'confirmed';
  $loginUrl = $payload['login_url'] ?? url('/login');
  $note = $payload['note'] ?? null;
  $dates = $payload['dates'] ?? [];
@endphp

<p>Hola,</p>

@if($type === 'confirmed')
  <p>Tu solicitud de programación <strong>#{{ $pr->id }}</strong> fue <strong>CONFIRMADA</strong>.</p>
@elseif($type === 'returned')
  <p>Tu solicitud de programación <strong>#{{ $pr->id }}</strong> fue <strong>DEVUELTA</strong> para ajustes.</p>
@elseif($type === 'dismissed')
  <p>Tu solicitud de programación <strong>#{{ $pr->id }}</strong> fue <strong>DESESTIMADA</strong>.</p>
@endif

@if($note)
  <p><strong>Observación:</strong> {{ $note }}</p>
@endif

@if(!empty($dates))
  <p><strong>Fechas asignadas:</strong></p>
  <ul>
    @foreach($dates as $d)
      <li>{{ $d['date'] }} ({{ $d['start_time'] }} - {{ $d['end_time'] }})</li>
    @endforeach
  </ul>
@endif

<p>
  Puedes ingresar a SICEFA aquí:
  <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
</p>

<p>— GDF/SITRAV - SICEFA</p>
