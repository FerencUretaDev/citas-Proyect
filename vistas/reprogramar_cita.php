<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reprogramar cita</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
</head>
<body class="page-reserva">
<main class="reserva-overlay">
    <section class="modal-cita" role="dialog" aria-modal="true" aria-labelledby="tituloReprogramar">
        <header class="modal-cita-header">
            <div>
                <p class="modal-eyebrow">ADMINISTRACIÓN / AGENDA</p>
                <h1 id="tituloReprogramar">Reprogramar cita</h1>
                <p class="modal-subtitulo">Consulta un turno disponible con el doctor actual u otro de la misma especialidad.</p>
            </div>
            <a class="modal-cerrar" href="<?php echo htmlspecialchars($destinoRetorno, ENT_QUOTES, "UTF-8"); ?>" aria-label="Cerrar y volver">&times;</a>
            <div class="modal-paciente">
                <span>Paciente</span>
                <strong><?php echo htmlspecialchars($cita["paciente_nombres"] . " " . $cita["paciente_apellidos"], ENT_QUOTES, "UTF-8"); ?></strong>
            </div>
        </header>

        <div class="formulario">
            <div class="reprogramar-resumen">
                <div><span>Doctor</span><strong><?php echo htmlspecialchars("Dr. " . $cita["doctor_nombres"] . " " . $cita["doctor_apellidos"], ENT_QUOTES, "UTF-8"); ?></strong></div>
                <div><span>Especialidad</span><strong><?php echo htmlspecialchars($cita["especialidad"], ENT_QUOTES, "UTF-8"); ?></strong></div>
                <div><span>Modalidad</span><strong><?php echo $cita["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?></strong></div>
                <div><span>Estado actual</span><strong><?php echo htmlspecialchars(ucfirst($cita["estado"]), ENT_QUOTES, "UTF-8"); ?></strong></div>
                    <div><span>Turno actual</span><strong><?php echo htmlspecialchars(substr($cita["hora"], 0, 5), ENT_QUOTES, "UTF-8"); ?></strong></div>
            </div>

            <?php if ($mensaje !== ""): ?>
                <div class="mensaje <?php echo $tipoMensaje; ?>" role="status">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <?php if (!$reprogramada): ?>
                <form method="GET" action="reprogramar_cita" class="campo reprogramar-fecha-form">
                    <input type="hidden" name="id" value="<?php echo $citaId; ?>">
                    <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>">
                    <label for="doctorDestino">Doctor para esta cita</label>
                    <select id="doctorDestino" name="doctor_id" required>
                        <?php foreach ($opciones as $opcion): ?>
                            <option value="<?= (int)$opcion['id'] ?>" <?= (int)$opcion['id'] === $doctorId ? 'selected' : '' ?>><?= htmlspecialchars($opcion['nombres'].' '.$opcion['apellidos']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="fechaReprogramacion">Nueva fecha</label>
                    <div class="reprogramar-fecha-controles">
                        <input type="date" id="fechaReprogramacion" name="fecha" min="<?php echo date("Y-m-d"); ?>" value="<?php echo htmlspecialchars($fechaConsulta, ENT_QUOTES, "UTF-8"); ?>" required>
                        <button type="submit" class="btn-guardar">Consultar horarios</button>
                    </div>
                </form>

                <form method="POST" action="reprogramar_cita?id=<?php echo $citaId; ?>&origen=<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>" class="reprogramar-confirmar-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="doctor_id" value="<?= (int)$doctorId ?>">
                    <input type="hidden" name="cita_id" value="<?php echo $citaId; ?>">
                    <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fechaConsulta, ENT_QUOTES, "UTF-8"); ?>">
                    <div class="campo">
                        <label for="horaReprogramacion">Horarios disponibles<?= $doctorElegido ? ' con '.htmlspecialchars($doctorElegido['nombres'].' '.$doctorElegido['apellidos']) : '' ?></label>
                        <select id="horaReprogramacion" name="hora" required <?php echo !$horariosDisponibles ? "disabled" : ""; ?>>
                            <option value="">Selecciona un horario</option>
                            <?php foreach ($horariosDisponibles as $horario): ?>
                                <option value="<?php echo htmlspecialchars($horario, ENT_QUOTES, "UTF-8"); ?>" <?php echo $horaSeleccionada === $horario ? "selected" : ""; ?>><?php echo htmlspecialchars(substr($horario, 0, 5), ENT_QUOTES, "UTF-8"); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$horariosDisponibles): ?>
                            <p class="reprogramar-sin-horarios">No hay horarios disponibles para esa fecha.</p>
                        <?php endif; ?>
                    </div>
                    <div class="botones reserva-acciones">
                        <button type="submit" class="btn-guardar" <?php echo !$horariosDisponibles ? "disabled" : ""; ?>>Confirmar reprogramación</button>
                        <a class="boton btn-volver" href="<?php echo htmlspecialchars($destinoRetorno, ENT_QUOTES, "UTF-8"); ?>">Cancelar</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="botones reserva-acciones">
                    <a class="boton btn-volver" href="<?php echo htmlspecialchars($destinoRetorno, ENT_QUOTES, "UTF-8"); ?>">Volver a las citas</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
