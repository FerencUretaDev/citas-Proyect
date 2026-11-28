<?php

if (empty($vistaRecuperacionAdmin)) {
    http_response_code(403);
    exit;
}

$esc = static fn($valor): string =>
    htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');

$estados = [
    'pendiente' => 'Pendiente',
    'en_atencion' => 'En atención',
    'resuelta' => 'Resuelta',
    'rechazada' => 'Rechazada'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitudes de recuperación</title>
    <link rel="stylesheet" href="css/recuperacion-admin.css?v=3">
</head>
<body>

<main class="contenedor">

    <a class="volver" href="panel">
        ← Volver al panel administrativo
    </a>

    <h1>🔔 Solicitudes de recuperación</h1>

    <p>Asistencia a pacientes y doctores.</p>

<?php if ($mensajeAdmin !== ''): ?>
    <p class="mensaje-admin" role="status">
        <?= $esc($mensajeAdmin) ?>
    </p>
<?php endif; ?>

    <section class="resumen">
        <span>Solicitudes pendientes</span>
        <strong><?= (int)$recuperacionesPendientes ?></strong>
    </section>

    <section class="tarjeta">

        <h2>Solicitudes recibidas</h2>

        <div class="tabla-scroll">

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Celular</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($solicitudes as $fila): ?>

                    <?php

                    $telefono = trim((string)($fila['telefono'] ?? ''));

                    $telefonoValido =
                        preg_match('/\A9[0-9]{8}\z/', $telefono) === 1;

                    $activa = in_array(
                        $fila['estado'],
                        ['pendiente', 'en_atencion'],
                        true
                    );

                    $mensaje = 'Hola. Hemos recibido una solicitud de asistencia para recuperar el acceso a tu cuenta. Comunícate con administración para verificar tu identidad. No compartas tu contraseña.';

                    $whatsapp = 'https://wa.me/51'
                        .$telefono.'?text='.rawurlencode($mensaje);

                    ?>

                    <tr>
                        <td><?= (int)$fila['id'] ?></td>
                        <td><?= $esc($fila['usuario']) ?></td>
                        <td><?= $esc($fila['nombre'] ?? 'No disponible') ?></td>

                        <td>
                            <?= match ((int)$fila['rol']) {
                                0 => 'Paciente',
                                1 => 'Doctor',
                                default => 'No disponible'
                            } ?>
                        </td>

                        <td><?= $esc($telefono ?: 'No disponible') ?></td>

                        <td><?= $esc($fila['fecha_solicitud']) ?></td>

                        <td>
                            <span class="estado <?= $esc($fila['estado']) ?>">
                                <?= $esc($estados[$fila['estado']] ?? 'Desconocido') ?>
                            </span>
                        </td>

                        <td>

                        <?php if ($activa): ?>

    <form method="POST"
          action="recuperacion_admin.php"
          class="acciones-recuperacion">

        <input type="hidden"
               name="csrf_token"
               value="<?= $esc($csrfAdmin) ?>">

        <input type="hidden"
               name="solicitud_id"
               value="<?= (int)$fila['id'] ?>">

        <?php if ($fila['estado'] === 'pendiente'): ?>

            <button name="accion"
                    value="atender"
                    class="btn-atender"
                    type="submit">
                Iniciar atención
            </button>

        <?php endif; ?>

        <button name="accion"
                value="rechazar"
                class="btn-rechazar"
                type="submit">
            Rechazar
        </button>

    </form>

<?php endif; ?>

<?php if ($fila['estado'] === 'en_atencion'): ?>

    <form method="POST"
          action="recuperacion_admin.php"
          class="form-generar-codigo">

        <input type="hidden"
               name="csrf_token"
               value="<?= $esc($csrfAdmin) ?>">

        <input type="hidden"
               name="solicitud_id"
               value="<?= (int)$fila['id'] ?>">

        <input type="hidden"
               name="accion"
               value="generar_codigo">

        <label class="verificar-identidad">
            <input type="checkbox"
                   name="identidad_verificada"
                   value="1"
                   required>

            Confirmo que he verificado la identidad del usuario.
        </label>

        <label>
            Contraseña del administrador

            <input type="password"
                   name="password_admin"
                   autocomplete="current-password"
                   maxlength="72"
                   required>
        </label>

        <button class="btn-generar" type="submit">
            Generar código temporal
        </button>

        <small>Vigencia: 30 minutos.</small>

    </form>

<?php endif; ?>

<?php if ($activa && $telefonoValido): ?>

                            <a class="whatsapp"
                               href="<?= $esc($whatsapp) ?>"
                               target="_blank"
                               rel="noopener noreferrer">

                                Contactar por WhatsApp

                            </a>

                        <?php elseif ($activa): ?>

                            Celular inválido o no disponible

                        <?php else: ?>

                            Solicitud finalizada

                        <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$solicitudes): ?>

                    <tr>
                        <td colspan="8">
                            No hay solicitudes registradas.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

    <p class="nota">
        Verifica la identidad del usuario antes de autorizar
        cualquier restablecimiento de contraseña.
    </p>

</main>

</body>
</html>