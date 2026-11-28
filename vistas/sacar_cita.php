<?php
$modalidadPreferida = $_POST['modalidad'] ?? 'presencial';
$modalidadSeleccionada = is_string($modalidadPreferida) && isset($tarifas[$modalidadPreferida])
    ? $modalidadPreferida : (array_key_first($tarifas) ?? '');
$precioInicial = $tarifas[$modalidadSeleccionada] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Agendar cita | Sistema de Citas Médicas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
    <link rel="stylesheet" href="css/reserva-moderna.css?v=1">
    <link rel="stylesheet" href="css/reserva-tarifas.css?v=1">
</head>
<body class="page-reserva reserva-moderna">
<main class="reserva-shell">
    <nav class="reserva-top" aria-label="Navegación de reserva">
        <a href="panel" class="reserva-back"><span aria-hidden="true">←</span> Volver al panel</a>
        <a href="lista_espera.php" class="reserva-back">Mi lista de espera</a>
        <span class="reserva-brand"><span aria-hidden="true">+</span> Sistema de Citas Médicas</span>
    </nav>
    <header class="reserva-hero">
        <div>
            <p class="reserva-kicker">AGENDA MÉDICA / NUEVA CITA</p>
            <h1 id="tituloReserva">Agenda tu consulta</h1>
            <p>Selecciona un profesional, elige una fecha y confirma el horario que mejor te convenga.</p>
        </div>
        <div class="reserva-persona"><span>Paciente</span><strong><?= htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"], ENT_QUOTES, "UTF-8") ?></strong></div>
    </header>
    <?php if ($mensaje !== ""): ?>
        <div class="reserva-aviso <?= htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8') ?>" role="<?= $tipo_mensaje === 'error' ? 'alert' : 'status' ?>">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
            <?php if ($tipo_mensaje === 'success'): ?><a href="panel">Ir al panel →</a><?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="POST" id="formCita" aria-labelledby="tituloReserva">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
        <input type="hidden" name="precio_mostrado" id="precioMostrado" value="<?= htmlspecialchars($precioInicial, ENT_QUOTES, 'UTF-8') ?>">
        <div class="reserva-pasos">
            <section class="reserva-card" aria-labelledby="pasoProfesional">
                <header class="reserva-step"><span class="reserva-numero" aria-hidden="true">1</span><div><h2 id="pasoProfesional">Elige tu especialista</h2><p>Profesional y modalidad de atención</p></div></header>
                <div class="reserva-field">
                    <label for="especialidad_id">Especialidad médica</label>
                    <select name="especialidad_id" id="especialidad_id" required>
<option value="">Selecciona una especialidad</option>
<?php foreach ($catalogo as $grupo => $opciones): ?>
<optgroup label="<?= htmlspecialchars($grupo) ?>">
    <?php foreach ($opciones as $especialidad): ?>
    <option value="<?= htmlspecialchars($especialidad) ?>" <?= $especialidadSeleccionada === $especialidad ? "selected" : "" ?>><?= htmlspecialchars($especialidad) ?></option>
    <?php endforeach; ?>
</optgroup>
<?php endforeach; ?>
</select>
                </div>
                <div class="reserva-field">
                    <label for="doctor_id">Profesional médico</label>
                    <select name="doctor_id" id="doctor_id" required disabled>
<option value=""><?php echo $doctores ? "Selecciona primero una especialidad" : "No hay doctores registrados"; ?></option>
<?php
foreach ($doctores as $doctor) {
    ?>
        <option
            value="<?php echo $doctor["id"]; ?>"
            <?php echo $doctorSeleccionado === (int)$doctor["id"] ? "selected" : ""; ?>
            data-especialidad="<?php echo htmlspecialchars($doctor["especialidad"]); ?>"
            data-inicio="<?php echo $doctor["hora_inicio"]; ?>"
            data-fin="<?php echo $doctor["hora_fin"]; ?>"
            data-refrigerio-inicio="<?php echo $doctor["refrigerio_inicio"] ?? ""; ?>"
            data-refrigerio-fin="<?php echo $doctor["refrigerio_fin"] ?? ""; ?>"
        >
            Dr(a). <?php echo htmlspecialchars($doctor["nombres"] . " " . $doctor["apellidos"]); ?>
            - <?php echo htmlspecialchars($doctor["especialidad"]); ?>
        </option>
    <?php
}
?>
</select>
                </div>
                <fieldset class="modalidad-selector" aria-describedby="ayudaModalidad">
                    <legend>Modalidad de atención</legend>
                    <p id="ayudaModalidad">Elige cómo recibirás la consulta y revisa su precio en soles.</p>
                    <div class="modalidad-opciones">
                        <?php foreach (['presencial' => ['Presencial', 'En consultorio'], 'virtual' => ['Virtual', 'Por videollamada']] as $modo => [$nombre, $descripcion]): ?>
                            <?php $precio = $tarifas[$modo] ?? ''; ?>
                            <label class="modalidad-opcion<?= $precio === '' ? ' tarifa-no-disponible' : '' ?>">
                                <input type="radio" name="modalidad" value="<?= $modo ?>" data-precio="<?= htmlspecialchars($precio, ENT_QUOTES, 'UTF-8') ?>" required <?= $modalidadSeleccionada === $modo ? 'checked' : '' ?> <?= $precio === '' ? 'disabled' : '' ?>>
                                <span>
                                    <strong><?= $nombre ?></strong>
                                    <small><?= $descripcion ?></small>
                                    <strong class="reserva-tarifa-precio"><?= $precio === '' ? 'No disponible' : 'S/ ' . htmlspecialchars($precio, ENT_QUOTES, 'UTF-8') ?></strong>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$tarifas): ?>
                        <p class="reserva-tarifa-error" role="alert">No hay tarifas disponibles en este momento. No podrás registrar una cita hasta que estén habilitadas.</p>
                    <?php endif; ?>
                </fieldset>
                <div class="info-doctor" id="infoDoctor">
                    <div><span>Especialidad</span><strong id="especialidadDoctor"></strong></div>
                    <div><span>Atención</span><strong id="horarioDoctor"></strong></div>
                    <div><span>Refrigerio</span><strong id="refrigerioDoctor"></strong></div>
                    <div><span>Duración</span><strong>30 minutos</strong></div>
                </div>
            </section>
            <section class="reserva-card reserva-card-fecha" aria-labelledby="pasoFecha">
                <header class="reserva-step"><span class="reserva-numero" aria-hidden="true">2</span><div><h2 id="pasoFecha">Selecciona la fecha</h2><p>Una cita por especialidad al día. Elige una fecha sin cruces de horario.</p></div></header>
                <div class="reserva-calendario" id="calendarioVisor" role="group" aria-label="Calendario de consulta"></div>
                <div id="reservaFechaAlternativa" class="reserva-field">
                    <label for="fecha">Fecha de la consulta</label>
                    <input type="date" name="fecha" id="fecha" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="reserva-leyenda" aria-hidden="true"><span><i class="reserva-punto hoy"></i> Hoy</span><span><i class="reserva-punto elegido"></i> Seleccionado</span></div>
                <p class="reserva-nota" id="fechaElegida" aria-live="polite">Selecciona un día para ver los horarios.</p>
            </section>
            <section class="reserva-card reserva-card-turnos" aria-labelledby="pasoHorario">
                <header class="reserva-step"><span class="reserva-numero" aria-hidden="true">3</span><div><h2 id="pasoHorario">Elige tu horario</h2><p>Turnos de 30 minutos</p></div></header>
                <div id="mensajeHorarios" role="status" aria-live="polite">Selecciona un doctor y una fecha para consultar los turnos.</div>
                <div id="listaHorarios" class="horarios" role="group" aria-label="Horarios disponibles" aria-busy="false"></div>
                <div id="ofertaListaEspera" hidden>
                    <p class="reserva-nota">La agenda está llena. Puedes inscribirte sin reservar ni pagar una cita.</p>
                    <button type="button" id="btnListaEspera" class="reserva-confirmar" disabled>Unirme a la lista de espera</button>
                </div>
                <input type="hidden" name="hora" id="hora">
                <div class="reserva-field reserva-motivo">
                    <label for="motivo">Motivo de consulta <span>Opcional</span></label>
                    <textarea name="motivo" id="motivo" rows="3" placeholder="Cuéntanos brevemente el motivo de tu consulta"><?= htmlspecialchars(is_string($_POST['motivo'] ?? null) && $tipo_mensaje !== 'success' ? $_POST['motivo'] : '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </section>
        </div>
        <section class="reserva-resumen" aria-labelledby="tituloResumen">
            <div class="reserva-resumen-datos">
                <h2 id="tituloResumen">Resumen de tu cita</h2>
                <p id="reservaResumen" aria-live="polite">Completa los tres pasos para confirmar.</p>
            </div>
            <div class="reserva-total" aria-live="polite" aria-atomic="true">
                <span class="reserva-total-label">Costo de consulta</span>
                <strong id="reservaTotal"><?= $precioInicial === '' ? 'No disponible' : 'S/ ' . htmlspecialchars($precioInicial, ENT_QUOTES, 'UTF-8') ?></strong>
                <small>Tarifa en PEN</small>
            </div>
            <div class="reserva-botones">
                <a href="panel" class="reserva-cancelar">Cancelar</a>
                <button type="submit" class="reserva-confirmar" id="btnGuardar" disabled>Confirmar cita y pagar <span aria-hidden="true">→</span></button>
            </div>
        </section>
        <noscript><p class="reserva-aviso error">Activa JavaScript para consultar los horarios disponibles.</p></noscript>
    </form>
    <form id="formListaEspera" method="POST" action="lista_espera.php" hidden>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="inscribir">
        <input type="hidden" name="doctor_id">
        <input type="hidden" name="fecha">
        <input type="hidden" name="modalidad">
    </form>
    <p class="reserva-pie">La disponibilidad y la tarifa se verifican en el servidor. Continuarás a Mercado Pago en modo de prueba.</p>
</main>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script src="js/reserva-moderna.js?v=6-prioridad-espera" defer></script>
</body>
</html>
