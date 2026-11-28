<?php

if (empty($vistaRestablecer)) {
    http_response_code(403);
    exit;
}

$esc = static fn($valor): string =>
    htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Restablecer contraseña | Sistema de Citas Médicas</title>

    <link rel="stylesheet" href="css/recuperacion.css?v=3">

    <style>
        .campo {
            margin-bottom: 18px;
        }

        .requisitos {
            margin-top: 18px;
            line-height: 1.6;
        }
    </style>
</head>

<body class="recuperacion-page">

<main class="recuperacion-card">

    <div class="recuperacion-logo">+</div>

    <h1>Restablecer contraseña</h1>

    <?php if ($mensaje !== ''): ?>

        <div role="status"
             class="mensaje <?= $exito ? 'exito' : 'error' ?>">

            <?= $esc($mensaje) ?>

        </div>

    <?php endif; ?>

    <?php if (!$exito): ?>

        <p class="subtitulo">
            Introduce el código proporcionado por el administrador.
        </p>

        <form method="POST" action="restablecer_password.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= $esc($csrf) ?>">

            <div class="campo">

                <label for="usuario">Nombre de usuario</label>

                <input type="text"
                       id="usuario"
                       name="usuario"
                       autocomplete="username"
                       maxlength="50"
                       required>

            </div>

            <div class="campo">

                <label for="codigo">Código temporal</label>

                <input type="password"
                       id="codigo"
                       name="codigo"
                       autocomplete="off"
                       minlength="32"
                       maxlength="32"
                       pattern="[a-fA-F0-9]{32}"
                       required>

            </div>

            <div class="campo">

                <label for="password">Nueva contraseña</label>

                <input type="password"
                       id="password"
                       name="password"
                       autocomplete="new-password"
                       minlength="8"
                       maxlength="72"
                       required>

            </div>

            <div class="campo">

                <label for="confirmacion">
                    Confirmar nueva contraseña
                </label>

                <input type="password"
                       id="confirmacion"
                       name="confirmacion"
                       autocomplete="new-password"
                       minlength="8"
                       maxlength="72"
                       required>

            </div>

            <div class="informacion requisitos">

                <strong>Requisitos de la contraseña:</strong>

                <ul>
                    <li>Mínimo 8 caracteres.</li>
                    <li>Una letra mayúscula.</li>
                    <li>Una letra minúscula.</li>
                    <li>Un número.</li>
                    <li>Un símbolo.</li>
                </ul>

            </div>

            <button type="submit">
                Restablecer contraseña
            </button>

        </form>

        <a class="volver" href="recuperar_password.php">
            Solicitar un nuevo código
        </a>

    <?php endif; ?>

    <a class="volver" href="index.php">
        Volver al inicio de sesión
    </a>

</main>

<footer>
    Sistema de Citas Médicas © 2026
</footer>

</body>
</html>