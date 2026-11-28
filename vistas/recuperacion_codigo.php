<?php

if (empty($vistaCodigoTemporal)
    || !is_string($codigoTemporal ?? null)) {

    http_response_code(403);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código temporal de recuperación</title>
    <link rel="stylesheet" href="css/recuperacion.css?v=3">
</head>

<body class="recuperacion-page">

<main class="recuperacion-card">

    <div class="recuperacion-logo">+</div>

    <h1>Código temporal generado</h1>

    <p class="subtitulo">
        Solicitud de recuperación N.º <?= (int)$id ?>
    </p>

    <div class="mensaje exito">
        Código generado correctamente.
        Su vigencia es de 30 minutos y solo puede utilizarse una vez.
    </div>

    <label for="codigo">Código de recuperación</label>

    <input type="text"
           id="codigo"
           value="<?= htmlspecialchars(
               $codigoTemporal,
               ENT_QUOTES,
               'UTF-8'
           ) ?>"
           readonly
           autocomplete="off"
           spellcheck="false">

    <p class="informacion">
        Selecciona el código y cópialo con Ctrl+C.
        Entrégalo exclusivamente al usuario cuya identidad has verificado.
        Por seguridad, no volverá a mostrarse al regresar al listado.
    </p>

    <a class="volver" href="recuperacion_admin.php">
        Volver a las solicitudes
    </a>

</main>

<footer>
    Sistema de Citas Médicas © 2026
</footer>

</body>
</html>