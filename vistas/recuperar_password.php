<?php
if (empty($vistaRecuperacion)) {
    http_response_code(403);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña | Sistema de Citas Médicas</title>
    <link rel="stylesheet" href="css/recuperacion.css?v=3">
</head>

<body class="recuperacion-page">

<main class="recuperacion-card">

    <div class="recuperacion-logo">+</div>

    <h1>¿Olvidaste tu contraseña?</h1>

    <p class="subtitulo">
        Solicita asistencia para recuperar el acceso a tu cuenta.
    </p>

    <?php if ($mensaje !== ''): ?>

        <div role="status"
             class="mensaje <?= $exito ? 'exito' : 'error' ?>">

            <?= htmlspecialchars(
                $mensaje,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="recuperar_password.php">

        <input type="hidden"
               name="csrf_token"
               value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <label for="usuario">Nombre de usuario</label>

        <input type="text"
               id="usuario"
               name="usuario"
               placeholder="Ingresa tu nombre de usuario"
               autocomplete="username"
               maxlength="50"
               required>

        <button type="submit">
            Solicitar asistencia
        </button>

    </form>

    <div class="informacion">
        El administrador podrá comunicarse contigo por WhatsApp
        mediante el número de celular registrado en el sistema.
        Nunca compartas tu contraseña actual.
    </div>

    <a class="volver" href="restablecer_password.php">
        Ya tengo un código de recuperación
    </a>

    <a class="volver" href="index.php">
        ← Volver al inicio de sesión
    </a>

</main>

<footer>
    Sistema de Citas Médicas © 2026
</footer>

</body>
</html>