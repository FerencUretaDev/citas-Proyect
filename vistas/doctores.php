<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Directorio de doctores</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
    <link rel="stylesheet" href="css/usuarios-directorio.css?v=20261124">
    <style>
        .doctor-form-grid select {
            box-sizing: border-box; width: 100%; min-width: 0; min-height: 44px;
            padding: 10px 12px; border: 1px solid #d6e1ec; border-radius: 8px;
            background-color: white; color: #17345c; font: inherit;
        }
        .doctores-section-heading { flex-wrap: wrap; }
        body.page-doctores .doctores-section-heading .doctores-back {
            width: auto;
            min-height: 40px;
            padding: 0 13px;
            border-radius: 7px;
            font-size: 13px;
            white-space: nowrap;
        }
    </style>
</head>
<body class="page-doctores has-sidebar">
<?php include dirname(__DIR__) . "/vistas/componentes/sidebar.php"; ?>
<main class="doctores-page">
    <header class="doctores-header">
        <div>
            <p class="doctores-eyebrow">ADMINISTRACIÓN / PERSONAL MÉDICO</p>
            <h1>Directorio de doctores</h1>
            <p>Administra perfiles profesionales y sus cuentas de acceso.</p>
        </div>
        <a class="doctores-back" href="panel">Volver al panel</a>
    </header>

    <?php if ($mensaje !== ""): ?>
        <div class="doctores-alerta <?php echo $tipoMensaje; ?>" role="status">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
            <?php if ($tipoMensaje === "success" && $usuarioRegistrado !== null && $usuarioRegistrado !== ""): ?>
                <div class="usuario-acceso-ficha">
                    <strong>Usuario asignado:</strong>
                    <?php if (($usuarioRegistrado ?? "") !== ""): ?>
                        <span class="usuario-acceso">
                            <span class="usuario-acceso-valor"><?= htmlspecialchars($usuarioRegistrado, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                            <button type="button" class="usuario-copiar" data-copiar-usuario="<?= htmlspecialchars($usuarioRegistrado, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>" aria-label="Copiar nombre de usuario">Copiar</button>
                            <span class="usuario-copia-estado" role="status" aria-live="polite"></span>
                        </span>
                    <?php else: ?>
                        <span class="usuario-sin-cuenta">Sin usuario asignado</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section<?php if ($mostrarFormulario): ?> class="doctores-layout"<?php endif; ?>>
        <?php if ($mostrarFormulario): ?>
        <section class="doctores-form-section" aria-labelledby="tituloNuevoDoctor">
            <div class="doctores-section-heading">
                <div>
                    <p class="doctores-eyebrow">FICHA PROFESIONAL</p>
                    <h2 id="tituloNuevoDoctor"><?= $editando ? "Editar doctor" : "Agregar doctor" ?></h2>
                </div>
                <a class="doctores-back" href="doctores">Cancelar</a>
            </div>

            <form method="POST" action="doctores<?= $editando ? '?editar='.$doctorId : '' ?>" class="doctor-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">

                <?php if ($editando): ?>
                    <div class="usuario-acceso-ficha">
                        <strong>Usuario de inicio de sesión (solo lectura)</strong>
                        <?php if (($usuarioFicha ?? "") !== ""): ?>
                            <span class="usuario-acceso">
                                <span class="usuario-acceso-valor"><?= htmlspecialchars($usuarioFicha, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                                <button type="button" class="usuario-copiar" data-copiar-usuario="<?= htmlspecialchars($usuarioFicha, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>" aria-label="Copiar nombre de usuario">Copiar</button>
                                <span class="usuario-copia-estado" role="status" aria-live="polite"></span>
                            </span>
                        <?php else: ?>
                            <span class="usuario-sin-cuenta">Sin usuario asignado</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="doctor-form-grid">
                    <label>Nombres
                        <input name="nombres" maxlength="100" autocomplete="given-name" required value="<?php echo htmlspecialchars($formulario["nombres"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <?php if ($editando): ?>
                    <label>Apellidos
                        <input name="apellidos" maxlength="100" required value="<?= htmlspecialchars($formulario['apellidos']) ?>">
                    </label>
                    <?php else: ?>
                    <label>Primer apellido
                        <input name="primer_apellido" maxlength="100" required value="<?php echo htmlspecialchars($primerApellido, ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Segundo apellido <span>Opcional</span>
                        <input name="segundo_apellido" maxlength="100" value="<?php echo htmlspecialchars($segundoApellido, ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Contraseña
                        <input type="password" name="password" minlength="10" autocomplete="new-password" required>
                    </label>
                    <?php endif; ?>
                    <label>Especialidad
                        <select name="especialidad" required>
                            <option value="">Selecciona una especialidad</option>
                            <?php foreach ($catalogo as $grupo => $opciones): ?>
                                <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                    <?php foreach ($opciones as $opcion): ?>
                                        <option value="<?= htmlspecialchars($opcion) ?>" <?= $formulario["especialidad"] === $opcion ? "selected" : "" ?>><?= htmlspecialchars($opcion) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Registro CMP <span>Opcional</span>
                        <input name="cmp" maxlength="30" value="<?php echo htmlspecialchars($formulario["cmp"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Teléfono <span>Opcional</span>
                        <input name="telefono" maxlength="20" autocomplete="tel" value="<?php echo htmlspecialchars($formulario["telefono"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Correo <span>Opcional</span>
                        <input type="email" name="correo" maxlength="100" autocomplete="email" value="<?php echo htmlspecialchars($formulario["correo"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Inicio de atención
                        <input type="time" name="hora_inicio" required value="<?php echo htmlspecialchars($formulario["hora_inicio"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Fin de atención
                        <input type="time" name="hora_fin" required value="<?php echo htmlspecialchars($formulario["hora_fin"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Inicio de refrigerio <span>Opcional</span>
                        <input type="time" name="refrigerio_inicio" value="<?php echo htmlspecialchars($formulario["refrigerio_inicio"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Fin de refrigerio <span>Opcional</span>
                        <input type="time" name="refrigerio_fin" value="<?php echo htmlspecialchars($formulario["refrigerio_fin"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                </div>

                <div class="doctor-form-footer">
                    <p>El horario se usará para generar los turnos disponibles.</p>
                    <button type="submit"><?= $editando ? "Guardar cambios" : "Registrar doctor" ?></button>
                </div>
            </form>
        </section>

        <?php endif; ?>

        <section class="doctores-list-section" aria-labelledby="tituloListaDoctores">
            <div class="doctores-section-heading">
                <div>
                    <p class="doctores-eyebrow">REGISTROS</p>
                    <h2 id="tituloListaDoctores"><?= $verInactivos ? "Doctores inactivos" : "Doctores registrados" ?></h2>
                </div>
                <?php if (!$mostrarFormulario): ?>
                    <a class="doctores-back" href="doctores?nuevo=1">Agregar doctor</a>
                <?php endif; ?>
                <span class="doctores-count"><?php echo count($doctores) === 1 ? "1 doctor" : count($doctores) . " doctores"; ?></span>
            </div>

            <p><a href="doctores<?= $verInactivos ? '' : '?vista=inactivos' ?>"><?= $verInactivos ? 'Ver activos' : 'Ver inactivos' ?></a></p>
            <div class="doctores-table-wrap">
                <table class="doctores-table">
                    <thead>
                        <tr><th>Profesional</th><th>Especialidad</th><th>Horario</th><th>Usuario</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctores as $doctor): ?>
                            <tr>
                                <td data-label="Profesional">
                                    <strong><?php echo htmlspecialchars($doctor["nombres"] . " " . $doctor["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong>
                                    <small><?php echo htmlspecialchars($doctor["cmp"] ?: "CMP no registrado", ENT_QUOTES, "UTF-8"); ?></small>
                                </td>
                                <td data-label="Especialidad"><?php echo htmlspecialchars($doctor["especialidad"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td data-label="Horario"><?php echo htmlspecialchars(substr($doctor["hora_inicio"], 0, 5) . " - " . substr($doctor["hora_fin"], 0, 5), ENT_QUOTES, "UTF-8"); ?></td>
                                <td data-label="Usuario">
                                    <?php if (($doctor["usuario"] ?? "") !== ""): ?>
                                        <span class="usuario-acceso">
                                            <span class="usuario-acceso-valor"><?= htmlspecialchars($doctor["usuario"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                                            <button type="button" class="usuario-copiar" data-copiar-usuario="<?= htmlspecialchars($doctor["usuario"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>" aria-label="Copiar nombre de usuario">Copiar</button>
                                            <span class="usuario-copia-estado" role="status" aria-live="polite"></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="usuario-sin-cuenta">Sin usuario asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Acciones">
                                    <?php if (!$verInactivos): ?><a class="doctores-back" href="doctores?editar=<?= (int)$doctor['id'] ?>">Editar</a><?php endif; ?>
                                    <a class="doctores-back" href="estado_perfil?tipo=doctor&amp;id=<?= (int)$doctor['id'] ?>"><?= $verInactivos ? 'Reactivar' : 'Desactivar' ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$doctores): ?>
                            <tr><td colspan="5" class="doctores-empty">No hay doctores en esta lista.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<script src="js/javascript.js?v=20261124-usuarios" defer></script>
</body>
</html>
