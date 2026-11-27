<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<title>Panel - Sistema de Citas Médicas</title>

<?php if ($rol == 2): ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php endif; ?>




    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
<?php if ($rol === 1): ?>
<link rel="stylesheet" href="css/filtros-directorio.css">
<?php endif; ?>
</head>


<body class="page-panel has-sidebar" data-user-role="<?php echo (int)$rol; ?>">

<?php
$paginaActiva = "panel";
include dirname(__DIR__) . "/vistas/componentes/sidebar.php";
?>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="contenido">


    <!-- CABECERA -->

    <div class="topbar">

        <div class="titulo">

            <?php if ($rol == 2): ?>

                <h1>Panel de Administración</h1>

                <p>
                    Resumen general de citas médicas del sistema.
                </p>

            <?php elseif ($rol == 1): ?>

                <h1>Panel del Doctor</h1>

                <p>
                    Consulta y gestiona tus citas médicas.
                </p>

            <?php else: ?>

                <h1>Mi Panel</h1>

                <p>
                    Consulta tus citas médicas y próximas atenciones.
                </p>

            <?php endif; ?>

        </div>


        <div class="usuario">

            👤

            <?php echo htmlspecialchars($nombreUsuario); ?>

        </div>

    </div>


    <!-- =================================================
         ADMINISTRADOR
    ================================================== -->

    <?php if ($rol == 2): ?>


        <div class="tarjetas admin-tarjetas">

            <!-- TOTAL -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro activo"
                data-filtro="todos"
            >

                <div class="tarjeta-icono azul">
                    📅
                </div>

                <div class="tarjeta-info">

                    <h3>Total de citas</h3>

                    <div class="numero">
                        <?php echo $totalCitas; ?>
                    </div>

                </div>

            </button>


            <!-- ATENDIDAS -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="atendida"
            >

                <div class="tarjeta-icono verde">
                    ✓
                </div>

                <div class="tarjeta-info">

                    <h3>Atendidas</h3>

                    <div class="numero">
                        <?php echo $atendidas; ?>
                    </div>

                </div>

            </button>


            <!-- PENDIENTES -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="pendiente"
            >

                <div class="tarjeta-icono amarillo">
                    ◷
                </div>

                <div class="tarjeta-info">

                    <h3>Pendientes</h3>

                    <div class="numero">
                        <?php echo $pendientes; ?>
                    </div>

                </div>

            </button>


            <!-- CONFIRMADAS -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="confirmada"
            >

                <div class="tarjeta-icono azul">
                    ✓
                </div>

                <div class="tarjeta-info">

                    <h3>Confirmadas</h3>

                    <div class="numero">
                        <?php echo $confirmadas; ?>
                    </div>

                </div>

            </button>

            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="cancelada"
            >

                <div class="tarjeta-icono rojo">
                    ×
                </div>

                <div class="tarjeta-info">

                    <h3>Canceladas</h3>

                    <div class="numero">
                        <?php echo $canceladas; ?>
                    </div>

                </div>

            </button>

        </div>


        <div class="info-grid admin-info-grid">

            <div class="info-card">
                <small>Pacientes registrados</small>
                <strong><?php echo $pacientesRegistrados; ?></strong>
            </div>

            <div class="info-card">
                <small>Doctores registrados</small>
                <strong><?php echo $doctoresRegistrados; ?></strong>
            </div>

            <div class="info-card">
                <small>Citas de hoy</small>
                <strong><?php echo $citasDeHoy; ?></strong>
            </div>

            <div class="info-card">
                <small>Citas próximos 7 días</small>
                <strong><?php echo $citasProximosSieteDias; ?></strong>
            </div>

        </div>


        <!-- GRÁFICOS -->

        <div class="graficos">


            <div class="grafico">

                <h2>🍩 Citas por estado</h2>

                <div class="canvas-box">

                    <canvas id="graficoEstados"></canvas>

                </div>

            </div>


            <div class="grafico">

                <h2>📊 Citas por especialidad</h2>

                <div class="canvas-box">

                    <canvas id="graficoEspecialidades"></canvas>

                </div>

            </div>

        </div>


        <!-- DETALLE -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Últimas 12 citas
                </h2>

                <a href="reporte_citas"
                   class="boton">

                    📊 Ver Dashboard

                </a>

            </div>


            <div class="filtro-resultado">
                <span>
                    Mostrando: <strong class="textoFiltro">Todas las últimas 12</strong>
                </span>
                <span>
                    <strong class="cantidadFiltro"><?php echo count($citas); ?></strong> resultado(s)
                </span>
            </div>


            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr
                        class="fila-cita"
                        data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>"
                    >

                        <td>
                            <?php
                            echo date(
                                "d/m/Y",
                                strtotime($fila["fecha"])
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo substr(
                                $fila["hora"],
                                0,
                                5
                            );
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["paciente_nombres"]
                                . " "
                                . $fila["paciente_apellidos"]
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["dni"]
                            );
                            ?>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                "Dr. "
                                . $fila["doctor_nombres"]
                                . " "
                                . $fila["doctor_apellidos"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["especialidad"]
                            );

                            ?>

                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                        <td>
                            <?php $opcionesEstado = $transicionesEstadoCita[$fila["estado"]] ?? []; $estadoPago = $fila['pago_estado'] ?? null;
                            if ($estadoPago !== null) { $opcionesEstado = $estadoPago === 'aprobado' ? array_values(array_intersect($opcionesEstado,['atendida'])) : []; ?>
                                <div>Pago: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$estadoPago))) ?></div>
                                <?php if ($rol===2): ?><a class="reprogramar-link" href="pago_cita.php?id=<?= (int)$fila['id'] ?>">Consultar pago</a><?php endif; ?>
                            <?php } ?>
                            <?php $opcionesEstado = array_values(array_diff($opcionesEstado, ['atendida'])); ?>
                            <?php if ($opcionesEstado): ?>
                                <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                    <input type="hidden" name="cita_id" value="<?php echo (int)$fila["id"]; ?>">
                                    <label class="sr-only" for="estado-cita-<?php echo (int)$fila["id"]; ?>">Nuevo estado de cita</label>
                                    <select id="estado-cita-<?php echo (int)$fila["id"]; ?>" name="estado" required>
                                        <option value="" selected disabled>Cambiar estado</option>
                                        <?php foreach ($opcionesEstado as $opcionEstado): ?>
                                            <option value="<?php echo htmlspecialchars($opcionEstado); ?>"><?php echo htmlspecialchars($etiquetasEstadoCita[$opcionEstado]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Guardar</button>
                                </form>
                            <?php else: ?>
                                <span class="accion-finalizada">Sin cambios</span>
                            <?php endif; ?>
                            <?php if (in_array($fila["estado"], ["pendiente", "confirmada"], true) && (($fila["pago_estado"] ?? null) === null || $fila["pago_estado"] === "aprobado")): ?>
                                <a class="reprogramar-link" href="reprogramar_cita?id=<?php echo (int)$fila["id"]; ?>&origen=panel">Reprogramar</a>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados">
                    <td colspan="9" class="mensaje-tabla-vacio">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="9" class="mensaje-tabla-vacio">

                            No hay citas registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


    <!-- =================================================
         DOCTOR
    ================================================== -->

    <?php elseif ($rol == 1): ?>


        <div class="tarjetas">

            <a href="panel" class="tarjeta tarjeta-filtro<?= $fDoctor['estado'] === '' ? ' activo' : '' ?>" style="text-decoration:none;color:inherit" <?= $fDoctor['estado'] === '' ? 'aria-current="true"' : '' ?>>
                <div class="tarjeta-icono azul">📅</div>
                <div class="tarjeta-info">
                    <h3>Total de citas</h3>
                    <div class="numero"><?php echo $totalCitas; ?></div>
                </div>
            </a>

            <a href="panel?estado=atendida" class="tarjeta tarjeta-filtro<?= $fDoctor['estado'] === 'atendida' ? ' activo' : '' ?>" style="text-decoration:none;color:inherit" <?= $fDoctor['estado'] === 'atendida' ? 'aria-current="true"' : '' ?>>
                <div class="tarjeta-icono verde">✓</div>
                <div class="tarjeta-info">
                    <h3>Atendidas</h3>
                    <div class="numero"><?php echo $atendidas; ?></div>
                </div>
            </a>

            <a href="panel?estado=pendiente" class="tarjeta tarjeta-filtro<?= $fDoctor['estado'] === 'pendiente' ? ' activo' : '' ?>" style="text-decoration:none;color:inherit" <?= $fDoctor['estado'] === 'pendiente' ? 'aria-current="true"' : '' ?>>
                <div class="tarjeta-icono amarillo">◷</div>
                <div class="tarjeta-info">
                    <h3>Pendientes</h3>
                    <div class="numero"><?php echo $pendientes; ?></div>
                </div>
            </a>

            <a href="panel?estado=confirmada" class="tarjeta tarjeta-filtro<?= $fDoctor['estado'] === 'confirmada' ? ' activo' : '' ?>" style="text-decoration:none;color:inherit" <?= $fDoctor['estado'] === 'confirmada' ? 'aria-current="true"' : '' ?>>
                <div class="tarjeta-icono azul">✓</div>
                <div class="tarjeta-info">
                    <h3>Confirmadas</h3>
                    <div class="numero"><?php echo $confirmadas; ?></div>
                </div>
            </a>

        </div>


        <!-- INFORMACIÓN DEL DOCTOR -->

        <div class="info-grid">

            <div class="info-card">

                <small>Especialidad</small>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $doctor["especialidad"]
                    );
                    ?>
                </strong>

            </div>


            <div class="info-card">

                <small>CMP</small>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $doctor["cmp"]
                    );
                    ?>
                </strong>

            </div>


            <div class="info-card">

                <small>Horario de atención</small>

                <strong>

                    <?php
                    echo substr(
                        $doctor["hora_inicio"],
                        0,
                        5
                    );
                    ?>

                    -

                    <?php
                    echo substr(
                        $doctor["hora_fin"],
                        0,
                        5
                    );
                    ?>

                </strong>

            </div>

        </div>


        <!-- CITAS DEL DOCTOR -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Mis citas médicas
                </h2>

            </div>


            <?php require __DIR__.'/componentes/filtros_citas_doctor.php'; ?>
            <p class="directorio-resultados"><?= (int)$listadoDoctor['total'] ?> resultado(s). <?= count($citas) ?> en esta página.</p>

            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr class="fila-cita" data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>">

                        <td>
                            <?php
                            echo date(
                                "d/m/Y",
                                strtotime($fila["fecha"])
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo substr(
                                $fila["hora"],
                                0,
                                5
                            );
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["nombres"]
                                . " "
                                . $fila["apellidos"]
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["dni"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["motivo"]
                            );
                            ?>
                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                        <td>
                            <?php $opcionesEstado = $transicionesEstadoCita[$fila["estado"]] ?? []; $estadoPago = $fila['pago_estado'] ?? null;
                            if ($estadoPago !== null) { $opcionesEstado = $estadoPago === 'aprobado' ? array_values(array_intersect($opcionesEstado,['atendida'])) : []; ?>
                                <div>Pago: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$estadoPago))) ?></div>
                                <?php if ($rol===2): ?><a class="reprogramar-link" href="pago_cita.php?id=<?= (int)$fila['id'] ?>">Consultar pago</a><?php endif; ?>
                            <?php } ?>
                            <?php $opcionesEstado = array_values(array_diff($opcionesEstado, ['atendida'])); ?>
                            <?php if ($fila['estado']==='confirmada' && ($estadoPago===null || $estadoPago==='aprobado') && ($fila['fecha'].' '.$fila['hora']) <= (new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s')): ?>
                                <a class="reprogramar-link" href="historia_clinica.php?id=<?= (int)$fila['id'] ?>">Registrar atención</a>
                            <?php elseif ($fila['estado']==='atendida' && !empty($fila['historia_id'])): ?>
                                <a class="reprogramar-link" href="historia_clinica.php?id=<?= (int)$fila['id'] ?>">Ver resumen clínico</a>
                            <?php endif; ?>
                            <?php if ($opcionesEstado): ?>
                                <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                    <input type="hidden" name="cita_id" value="<?php echo (int)$fila["id"]; ?>">
                                    <label class="sr-only" for="estado-cita-<?php echo (int)$fila["id"]; ?>">Nuevo estado de cita</label>
                                    <select id="estado-cita-<?php echo (int)$fila["id"]; ?>" name="estado" required>
                                        <option value="" selected disabled>Cambiar estado</option>
                                        <?php foreach ($opcionesEstado as $opcionEstado): ?>
                                            <option value="<?php echo htmlspecialchars($opcionEstado); ?>"><?php echo htmlspecialchars($etiquetasEstadoCita[$opcionEstado]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Guardar</button>
                                </form>
                            <?php else: ?>
                                <span class="accion-finalizada">Sin cambios</span>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="8" class="mensaje-tabla-vacio">

                            No hay citas que coincidan con los filtros.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>
            <?php $listado = $listadoDoctor; require __DIR__.'/componentes/paginacion_directorio.php'; ?>

        </div>


    <!-- =================================================
         PACIENTE
    ================================================== -->

    <?php elseif ($rol == 0): ?>

        <?php if (!empty($_SESSION["mensaje_cita"])): ?>
            <div role="status"
                 style="padding:15px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:15px;">
                <?php echo htmlspecialchars($_SESSION["mensaje_cita"], ENT_QUOTES, "UTF-8"); ?>
            </div>
            <?php unset($_SESSION["mensaje_cita"]); ?>
        <?php endif; ?>


        <div class="tarjetas">

            <button type="button" class="tarjeta tarjeta-filtro activo" data-filtro="todos">
                <div class="tarjeta-icono azul">📅</div>
                <div class="tarjeta-info">
                    <h3>Total de citas</h3>
                    <div class="numero"><?php echo $totalCitas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="atendida">
                <div class="tarjeta-icono verde">✓</div>
                <div class="tarjeta-info">
                    <h3>Atendidas</h3>
                    <div class="numero"><?php echo $atendidas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="pendiente">
                <div class="tarjeta-icono amarillo">◷</div>
                <div class="tarjeta-info">
                    <h3>Pendientes</h3>
                    <div class="numero"><?php echo $pendientes; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="confirmada">
                <div class="tarjeta-icono azul">✓</div>
                <div class="tarjeta-info">
                    <h3>Confirmadas</h3>
                    <div class="numero"><?php echo $confirmadas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="cancelada">
                <div class="tarjeta-icono rojo">×</div>
                <div class="tarjeta-info">
                    <h3>Canceladas</h3>
                    <div class="numero"><?php echo $canceladas; ?></div>
                </div>
            </button>

        </div>


        <div class="acciones">

            <a href="sacar_cita"
               class="boton boton-verde">

                ➕ Sacar nueva cita

            </a>

        </div>


        <!-- DATOS DEL PACIENTE -->

        <div class="info-grid">

            <div class="info-card">

                <small>Paciente</small>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $paciente["nombres"]
                        . " "
                        . $paciente["apellidos"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-card">

                <small>DNI</small>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $paciente["dni"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-card">

                <small>Próxima atención</small>

                <strong>

                    <?php

                    $proxima = null;

                    foreach ($citas as $cita) {

                        if (
                            $cita["estado"] != "cancelada" && (($cita["pago_estado"] ?? null) === null || $cita["pago_estado"] === "aprobado")
                            &&
                            $cita["fecha"] >= date("Y-m-d")
                        ) {
                            $proxima = $cita;
                            break;
                        }

                    }

                    if ($proxima) {

                        echo date(
                            "d/m/Y",
                            strtotime(
                                $proxima["fecha"]
                            )
                        );

                    } else {

                        echo "Sin citas";

                    }

                    ?>

                </strong>

            </div>

        </div>


        <!-- CITAS DEL PACIENTE -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Mis citas médicas
                </h2>

            </div>


            <div class="filtro-resultado">
                <span>
                    Mostrando: <strong class="textoFiltro">Todas las citas</strong>
                </span>
                <span>
                    <strong class="cantidadFiltro"><?php echo count($citas); ?></strong> resultado(s)
                </span>
            </div>


            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr class="fila-cita" data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>">

                        <td>

                            <?php

                            echo date(
                                "d/m/Y",
                                strtotime(
                                    $fila["fecha"]
                                )
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo substr($fila["hora"], 0, 5);
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                "Dr. "
                                . $fila["doctor_nombres"]
                                . " "
                                . $fila["doctor_apellidos"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["especialidad"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["motivo"]
                            );

                            ?>

                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                        <td>
                            <?php if ($fila['estado']==='atendida' && !empty($fila['historia_id'])): ?>
                                <a class="reprogramar-link" href="historia_clinica.php?id=<?= (int)$fila['id'] ?>">Ver resumen clínico</a>
                            <?php endif; ?>
                            <?php if (($fila['pago_estado'] ?? null) !== null): ?>
                                <div>Pago: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$fila['pago_estado']))) ?></div>
                                <a class="reprogramar-link" href="pago_cita.php?id=<?= (int)$fila['id'] ?>"><?= $fila['pago_estado']==='aprobado'?'Ver pago':'Consultar / pagar' ?></a>
                                <?php if ($fila['pago_estado']==='aprobado' && puedeCancelarCitaPaciente($fila)): ?><a class="reprogramar-link" href="reprogramar_cita?id=<?= (int)$fila['id'] ?>">Reprogramar cita</a><?php endif; ?>
                            <?php elseif (puedeCancelarCitaPaciente($fila)): ?>
                                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
                                    <a href="reprogramar_cita?id=<?php echo (int)$fila['id']; ?>&amp;origen=panel"
                                       style="display:inline-block; background:#2563eb; color:white; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:13px; line-height:1.4; white-space:nowrap;">
                                        Reprogramar cita
                                    </a>
                                    <form method="POST" action="actualizar_cita"
                                          style="margin:0;"
                                          onsubmit="return confirm('¿Seguro que deseas cancelar esta cita?');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="cita_id" value="<?php echo (int)$fila['id']; ?>">
                                        <input type="hidden" name="estado" value="cancelada">
                                        <button type="submit"
                                                style="background:#dc2626; color:white; border:0; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; line-height:1.4; white-space:nowrap;">
                                            Cancelar cita
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="accion-finalizada">Sin acciones</span>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados">
                    <td colspan="8" class="mensaje-tabla-vacio">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="8" class="mensaje-tabla-vacio">

                            No tienes citas registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


</main>


<?php if ($rol == 2): ?>

<script type="application/json" id="panel-chart-data"><?php
echo json_encode([
    "atendidas" => $atendidas,
    "pendientes" => $pendientes,
    "confirmadas" => $confirmadas,
    "canceladas" => $canceladas,
    "especialidades" => $datosEspecialidades
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?></script>

<?php endif; ?>

<script src="js/javascript.js" defer></script>

</body>

</html>
