<?php

$paginaActiva = "ausencias_doctores";

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
};

$etiquetasEstado = [
    "vigente" => "Vigente",
    "programada" => "Programada",
    "finalizada" => "Finalizada",
    "cancelada" => "Cancelada"
];

$tarjetas = [
    ["vigente", "Ausencias vigentes", "amarillo", "◷"],
    ["programada", "Programadas", "azul", "📅"],
    ["finalizada", "Finalizadas", "verde", "✓"],
    ["cancelada", "Canceladas", "rojo", "×"],
    ["citas_afectadas", "Citas afectadas", "rojo", "⚠"]
];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Gestión de ausencias de doctores</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/ausencias-doctores.css">

</head>

<body class="page-panel has-sidebar" data-user-role="2">

<?php require __DIR__ . "/componentes/sidebar.php"; ?>

<main class="contenido pagina-ausencias">

    <div class="topbar">

        <div class="titulo">
            <h1>Ausencias de doctores</h1>
            <p>Gestiona la disponibilidad del personal médico.</p>
        </div>

        <div class="usuario">
            👤 <?= $escapar($nombreUsuario) ?>
        </div>

    </div>

    <?php if ($mensaje !== null): ?>

        <div role="status"
             class="mensaje-ausencia <?= $escapar($mensaje["tipo"]) ?>">

            <?= $escapar($mensaje["texto"]) ?>

        </div>

    <?php endif; ?>

    <!-- TARJETAS -->

    <div class="tarjetas ausencias-tarjetas">

        <?php foreach ($tarjetas as $tarjeta): ?>

            <div class="tarjeta">

                <div class="tarjeta-icono <?= $escapar($tarjeta[2]) ?>">

                    <?= $escapar($tarjeta[3]) ?>

                </div>

                <div class="tarjeta-info">

                    <h3><?= $escapar($tarjeta[1]) ?></h3>

                    <div class="numero">
                        <?= (int)$resumen[$tarjeta[0]] ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- FORMULARIO DE REGISTRO -->

    <section class="seccion">

        <div class="seccion-header">
            <h2>📅 Registrar nueva ausencia</h2>
        </div>

        <form method="POST"
              action="ausencias_doctores.php"
              class="ausencias-formulario">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= $escapar($_SESSION["csrf_token"]) ?>">

            <input type="hidden"
                   name="accion"
                   value="registrar">

            <div class="ausencias-campo">

                <label for="doctor_id">Doctor</label>

                <select name="doctor_id"
                        id="doctor_id"
                        required>

                    <option value="">
                        Seleccionar doctor
                    </option>

                    <?php foreach ($doctores as $doctor): ?>

                        <option value="<?= (int)$doctor["id"] ?>">

                            <?= $escapar(
                                $doctor["nombres"] . " " .
                                $doctor["apellidos"] . " - " .
                                $doctor["especialidad"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="ausencias-campo">

                <label for="motivo">Motivo de ausencia</label>

                <select name="motivo" id="motivo" required>

                    <option value="">Seleccionar motivo</option>

                    <?php foreach (MOTIVOS_AUSENCIA as $clave => $texto): ?>

                        <option value="<?= $escapar($clave) ?>">
                            <?= $escapar($texto) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="ausencias-campo">

                <label for="fecha_inicio">Fecha inicial</label>

                <input type="date"
                       name="fecha_inicio"
                       id="fecha_inicio"
                       min="<?= $escapar($hoy) ?>"
                       required>

            </div>

            <div class="ausencias-campo">

                <label for="fecha_fin">Fecha final</label>

                <input type="date"
                       name="fecha_fin"
                       id="fecha_fin"
                       min="<?= $escapar($hoy) ?>"
                       required>

            </div>

            <div class="ausencias-campo campo-completo">

                <label for="observaciones">
                    Observaciones administrativas
                </label>

                <textarea name="observaciones"
                          id="observaciones"
                          maxlength="500"
                          rows="3"
                          placeholder="Descripción opcional. No incluir diagnósticos médicos."></textarea>

            </div>

            <p class="campo-completo">
                Las citas existentes no se cancelarán
                automáticamente. Deberás gestionar las
                citas afectadas.
            </p>

            <div class="campo-completo">

                <button type="submit"
                        class="boton boton-verde">

                    Registrar ausencia

                </button>

            </div>

        </form>

    </section>

    <!-- LISTADO DE AUSENCIAS -->

    <section class="seccion">

        <div class="seccion-header">
            <h2>📋 Ausencias registradas</h2>
        </div>

        <div class="ausencias-tabla">

            <table>

                <thead>

                    <tr>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Citas afectadas</th>
                        <th>Acciones</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($ausencias as $ausencia): ?>

                    <?php
                    $estado = estadoAusencia($ausencia, $hoy);
                    ?>

                    <tr>

                        <td>
                            <?= $escapar(
                                $ausencia["doctor_nombres"] . " " .
                                $ausencia["doctor_apellidos"]
                            ) ?>
                        </td>

                        <td>
                            <?= $escapar($ausencia["especialidad"]) ?>
                        </td>

                        <td>
                            <?= $escapar(date(
                                "d/m/Y",
                                strtotime($ausencia["fecha_inicio"])
                            )) ?>
                        </td>

                        <td>
                            <?= $escapar(date(
                                "d/m/Y",
                                strtotime($ausencia["fecha_fin"])
                            )) ?>
                        </td>

                        <td>

                            <?= $escapar(
                                MOTIVOS_AUSENCIA[$ausencia["motivo"]]
                                ?? $ausencia["motivo"]
                            ) ?>

                            <?php if (!empty($ausencia["observaciones"])): ?>

                                <details>
                                    <summary>Observaciones</summary>

                                    <?= $escapar($ausencia["observaciones"]) ?>

                                </details>

                            <?php endif; ?>

                        </td>

                        <td>

                            <span class="ausencia-estado <?= $escapar($estado) ?>">

                                <?= $escapar($etiquetasEstado[$estado]) ?>

                            </span>

                        </td>

                        <td>

                            <strong>
                                <?= (int)$ausencia["citas_afectadas"] ?>
                            </strong>

                        </td>

                        <td>

                            <?php if ((int)$ausencia["citas_afectadas"] > 0): ?>

                                <a class="boton"
                                   href="ausencias_doctores.php?ausencia=<?= (int)$ausencia["id"] ?>#citas-afectadas">

                                    Ver citas

                                </a>

                            <?php endif; ?>

                            <?php if (in_array($estado, ["vigente", "programada"], true)): ?>

                                <form method="POST"
                                      action="ausencias_doctores.php"
                                      onsubmit="return confirm('¿Deseas cancelar esta ausencia?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= $escapar($_SESSION["csrf_token"]) ?>">

                                    <input type="hidden"
                                           name="accion"
                                           value="cancelar">

                                    <input type="hidden"
                                           name="ausencia_id"
                                           value="<?= (int)$ausencia["id"] ?>">

                                    <input type="hidden"
                                           name="doctor_id"
                                           value="<?= (int)$ausencia["doctor_id"] ?>">

                                    <button type="submit"
                                            class="boton-cancelar-ausencia">

                                        Cancelar ausencia

                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($ausencias)): ?>

                    <tr>

                        <td colspan="8" class="mensaje-tabla-vacio">

                            No existen ausencias registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- CITAS AFECTADAS -->

    <?php if ($ausenciaSeleccionada !== null): ?>

        <section class="seccion" id="citas-afectadas">

            <div class="seccion-header">

                <h2>⚠ Citas afectadas</h2>

            </div>

            <p>

                Doctor:

                <strong>
                    <?= $escapar(
                        $ausenciaSeleccionada["doctor_nombres"] . " " .
                        $ausenciaSeleccionada["doctor_apellidos"]
                    ) ?>
                </strong>

            </p>

            <div class="ausencias-tabla">

                <table>

                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($citasAfectadas as $cita): ?>

                        <tr>

                            <td>

                                <?= $escapar(
                                    $cita["paciente_nombres"] . " " .
                                    $cita["paciente_apellidos"]
                                ) ?>

                            </td>

                            <td>

                                <?= $escapar(date(
                                    "d/m/Y",
                                    strtotime($cita["fecha"])
                                )) ?>

                            </td>

                            <td>
                                <?= $escapar(substr($cita["hora"], 0, 5)) ?>
                            </td>

                            <td>
                                <?= $escapar(ucfirst($cita["estado"])) ?>
                            </td>

                            <td>

                                <a class="boton"
                                   href="reprogramar_cita.php?id=<?= (int)$cita["id"] ?>&amp;origen=citas_admin">

                                    Reprogramar

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (empty($citasAfectadas)): ?>

                        <tr>
                            <td colspan="5">
                                No existen citas afectadas pendientes de gestionar.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</main>

<script src="js/javascript.js" defer></script>

</body>
</html>