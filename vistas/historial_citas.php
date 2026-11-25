<?php
$paginaActiva = "historial_citas";

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
};

$etiquetas = [
    "pendiente" => "Pendiente",
    "confirmada" => "Confirmada",
    "atendida" => "Atendida",
    "cancelada" => "Cancelada"
];

$tarjetas = [
    ["total", "Total de citas", "azul", "📅"],
    ["atendida", "Atendidas", "verde", "✓"],
    ["pendiente", "Pendientes", "amarillo", "◷"],
    ["confirmada", "Confirmadas", "azul", "✓"],
    ["cancelada", "Canceladas", "rojo", "×"]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Mi historial - Sistema de Citas Médicas</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/historial-citas.css">
</head>

<body class="page-panel has-sidebar"
      data-user-role="0">

<?php
require __DIR__ . "/componentes/sidebar.php";
?>

<main class="contenido pagina-historial">

    <div class="topbar">
        <div class="titulo">
            <h1>Mi historial de citas</h1>

            <p>
                Consulta tus citas anteriores y finalizadas.
            </p>
        </div>

        <div class="usuario">
            👤 <?= $escapar($nombreUsuario) ?>
        </div>
    </div>

    <!-- Resumen del historial -->

    <div class="tarjetas historial-tarjetas">

        <?php foreach ($tarjetas as $tarjeta): ?>

            <div class="tarjeta">

                <div class="tarjeta-icono <?= $escapar($tarjeta[2]) ?>">
                    <?= $escapar($tarjeta[3]) ?>
                </div>

                <div class="tarjeta-info">

                    <h3><?= $escapar($tarjeta[1]) ?></h3>

                    <div class="numero">
                        <?= (int) $resumen[$tarjeta[0]] ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- Filtros de búsqueda -->

    <section class="seccion historial-filtros">

        <div class="seccion-header">
            <h2>🔍 Buscar en mi historial</h2>
        </div>

        <form method="GET"
              action="historial_citas.php"
              class="historial-formulario">

            <div class="historial-campo">

                <label for="fecha_inicio">
                    Fecha inicial
                </label>

                <input type="date"
                       id="fecha_inicio"
                       name="fecha_inicio"
                       value="<?= $escapar($filtros["fecha_inicio"]) ?>">

            </div>

            <div class="historial-campo">

                <label for="fecha_fin">
                    Fecha final
                </label>

                <input type="date"
                       id="fecha_fin"
                       name="fecha_fin"
                       value="<?= $escapar($filtros["fecha_fin"]) ?>">

            </div>

            <div class="historial-campo">

                <label for="especialidad">
                    Especialidad
                </label>

                <select id="especialidad"
                        name="especialidad">

                    <option value="">
                        Todas las especialidades
                    </option>

                    <?php foreach ($especialidades as $especialidad): ?>

                        <option
                            value="<?= $escapar($especialidad) ?>"
                            <?= $filtros["especialidad"] === $especialidad
                                ? "selected" : "" ?>>

                            <?= $escapar($especialidad) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="historial-campo">

                <label for="estado">
                    Estado de la cita
                </label>

                <select id="estado" name="estado">

                    <option value="">
                        Todos los estados
                    </option>

                    <?php foreach ($etiquetas as $clave => $etiqueta): ?>

                        <option
                            value="<?= $escapar($clave) ?>"
                            <?= $filtros["estado"] === $clave
                                ? "selected" : "" ?>>

                            <?= $escapar($etiqueta) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="historial-acciones">

                <button type="submit"
                        class="historial-boton buscar">

                    🔍 Buscar citas

                </button>

                <a href="historial_citas.php"
                   class="historial-boton limpiar">

                    Limpiar filtros

                </a>

            </div>

        </form>

    </section>

    <!-- Resultados del historial -->

    <section class="seccion historial-resultados">

        <div class="seccion-header">

            <h2>📋 Historial de citas médicas</h2>

        </div>

        <p class="historial-contador">

            Se encontraron
            <strong><?= count($citas) ?></strong>
            cita(s).

        </p>

        <div class="historial-tabla">

            <table>

                <thead>

                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Modalidad</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($citas as $cita): ?>

                    <tr>

                        <td>
                            <?= $escapar(
                                date(
                                    "d/m/Y",
                                    strtotime($cita["fecha"])
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= $escapar(
                                substr($cita["hora"], 0, 5)
                            ) ?>
                        </td>

                        <td>
                            <?= $escapar(
                                $cita["doctor_nombres"] . " " .
                                $cita["doctor_apellidos"]
                            ) ?>
                        </td>

                        <td>
                            <?= $escapar($cita["especialidad"]) ?>
                        </td>

                        <td>

                            <span class="badge-modalidad modalidad-<?= $escapar($cita["modalidad"]) ?>">

                                <?= $cita["modalidad"] === "virtual"
                                    ? "Virtual" : "Presencial" ?>

                            </span>

                        </td>

                        <td class="historial-motivo">
                            <?= $escapar($cita["motivo"]) ?>
                        </td>

                        <td>

                            <span class="estado estado-<?= $escapar($cita["estado"]) ?>">

                                <?= $escapar(
                                    $etiquetas[$cita["estado"]]
                                    ?? $cita["estado"]
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($citas)): ?>

                    <tr>

                        <td colspan="7"
                            class="historial-vacio">

                            <div>📋</div>

                            <strong>
                                No se encontraron citas.
                            </strong>

                            <p>
                                No tienes citas en el historial
                                que coincidan con los filtros.
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="historial-regresar">

        <a href="panel" class="historial-boton limpiar">
            ← Regresar al panel
        </a>

    </div>

</main>

<script src="js/javascript.js" defer></script>

</body>
</html>