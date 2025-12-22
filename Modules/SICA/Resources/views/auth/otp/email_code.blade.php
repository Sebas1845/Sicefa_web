<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Código de seguridad</title>
</head>
<body>
  <p>Tu código de seguridad para iniciar sesión es:</p>
  <h2 style="letter-spacing:3px">{{ $code }}</h2>
  <p>Este código vence en {{ $ttlMinutes }} minutos.</p>
  <p>Si tú no solicitaste este código, ignora este correo.</p>
</body>
</html>
