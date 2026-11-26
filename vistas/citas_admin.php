<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Administrar citas</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
</head>
<body class="page-reporte has-sidebar">
<?php include dirname(__DIR__) . "/vistas/componentes/sidebar.php"; ?>
<div class="contenedor">
    <div class="citas-admin-header">
        <div>
            <p class="doctores-eyebrow">ADMINISTRACIÓN / AGENDA</p>
            <h1>Citas médicas</h1>
            <p>Consulta el historial completo y gestiona el estado o la programación.</p>
        </div>
        <nav class="citas-admin-nav" aria-label="Navegación administrativa">
            <a href="panel" class="boton boton-limpiar">Panel</a>
            <a href="reporte_citas" class="boton">Dashboard</a>
        </nav>
    </div>

    <div class="filtros">
        <h2>Filtrar citas</h2>
        <?php if ($filtrarFicha): ?>
            <p>Citas futuras de la ficha seleccionada. <a href="estado_perfil?tipo=<?= $tipoFicha ?>&amp;id=<?= $idFicha ?>">Volver a la ficha</a></p>
        <?php endif; ?>
        <form method="GET" action="citas_admin">
            <?php if ($filtrarFicha): ?>
                <input type="hidden" name="tipo" value="<?= $tipoFicha ?>">
                <input type="hidden" name="id" value="<?= $idFicha ?>">
            <?php endif; ?>
            <div class="campo">
                <label for="estadoCitas">Estado</label>
                <select id="estadoCitas" name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach ($etiquetasEstado as $valor => $etiqueta): ?>
                        <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, "UTF-8"); ?>" <?php echo $estadoSeleccionado === $valor ? "selected" : ""; ?>><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, "UTF-8"); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Aplicar filtro</button>
            <a href="citas_admin" class="boton boton-limpiar">Limpiar</a>
        </form>
    </div>

    <div class="seccion">
        <div class="seccion-header">
            <h2><?php echo $estadoSeleccionado !== "" ? "Citas: " . htmlspecialchars($etiquetasEstado[$estadoSeleccionado], ENT_QUOTES, "UTF-8") : "Todas las citas"; ?></h2>
            <span><?php echo $totalVisible; ?> resultado(s)</span>
        </div>
        <div class="citas-admin-table-wrap">
            <table class="citas-admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($cita = $resultadoCitas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date("d/m/Y", strtotime($cita["fecha"])); ?></td>
                            <td><?php echo htmlspecialchars(substr($cita["hora"], 0, 5), ENT_QUOTES, "UTF-8"); ?></td>
                            <td><span class="badge-modalidad modalidad-<?php echo htmlspecialchars($cita["modalidad"], ENT_QUOTES, "UTF-8"); ?>"><?php echo $cita["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?></span></td>
                            <td><?php echo htmlspecialchars($cita["paciente_nombres"] . " " . $cita["paciente_apellidos"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($cita["dni"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars("Dr. " . $cita["doctor_nombres"] . " " . $cita["doctor_apellidos"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($cita["especialidad"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($cita["motivo"] ?: "Sin motivo", ENT_QUOTES, "UTF-8"); ?></td>
                            <td><span class="estado estado-<?php echo htmlspecialchars($cita["estado"], ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($etiquetasEstado[$cita["estado"]] ?? $cita["estado"], ENT_QUOTES, "UTF-8"); ?></span></td>
                            <td class="citas-admin-actions">
                                <?php $accionesEstado=$transiciones[$cita['estado']]??[]; $pagoEstado=$cita['pago_estado']??null;
                                if($pagoEstado!==null){$accionesEstado=$pagoEstado==='aprobado'?array_values(array_intersect($accionesEstado,['atendida'])):[]; ?>
                                    <div>Pago: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$pagoEstado))) ?></div><a class="reprogramar-link" href="pago_cita.php?id=<?= (int)$cita['id'] ?>">Consultar pago</a>
                                <?php } ?>
                                <?php if ($accionesEstado): ?>
                                    <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                        <input type="hidden" name="cita_id" value="<?php echo (int)$cita["id"]; ?>">
                                        <input type="hidden" name="origen" value="citas">
                                        <label class="sr-only" for="estado-cita-admin-<?php echo (int)$cita["id"]; ?>">Nuevo estado</label>
                                        <select id="estado-cita-admin-<?php echo (int)$cita["id"]; ?>" name="estado" required>
                                            <option value="" selected disabled>Cambiar estado</option>
                                            <?php foreach ($accionesEstado as $nuevoEstado): ?>
                                                <option value="<?php echo htmlspecialchars($nuevoEstado, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($etiquetasEstado[$nuevoEstado], ENT_QUOTES, "UTF-8"); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Guardar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="accion-finalizada">Sin cambios</span>
                                <?php endif; ?>
                                <?php if (in_array($cita["estado"], ["pendiente", "confirmada"], true) && ($pagoEstado===null || $pagoEstado==='aprobado')): ?>
                                    <a class="reprogramar-link" href="reprogramar_cita?id=<?php echo (int)$cita["id"]; ?>&origen=citas">Reprogramar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($totalVisible === 0): ?>
                        <tr><td colspan="10" class="mensaje-tabla-vacio">No hay citas para este estado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="js/javascript.js" defer></script>
</body>
</html>
