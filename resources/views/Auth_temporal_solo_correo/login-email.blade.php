<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login por Email</title>
</head>
<body>

    <h2>Iniciar sesión solo con correo electrónico</h2>

    @if ($errors->any())
        <div style="color: red;">
            {{ $errors->first('email') }}
        </div>
    @endif

    <form action="{{ route('login.email.post') }}" method="POST">
        @csrf

        <label for="email">Correo electrónico:</label><br>
        <input type="email" name="email" id="email" required><br><br>

        <button type="submit">Entrar</button>
    </form>

</body>
</html>
