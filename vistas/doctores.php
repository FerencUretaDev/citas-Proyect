<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Directorio de doctores</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
    <link rel="stylesheet" href="css/usuarios-directorio.css?v=20261126-password-ui">
    <style>
        .doctor-form-grid select {
            box-sizing: border-box; width: 100%; min-width: 0; min-height: 44px;
            padding: 10px 12px; border: 1px solid #d6e1ec; border-radius: 8px;
            background-color: white; color: #17345c; font: inherit;
        }
        .doctores-section-heading { flex-wrap: wrap; }
        .doctor-dni-consulta { display: flex; flex-direction: column; align-items: flex-start; justify-content: center; gap: 6px; min-width: 0; }
        .doctor-dni-consulta button { cursor: pointer; font: inherit; }
        .doctor-dni-consulta small { overflow-wrap: anywhere; }
        .doctor-dni-consulta button:disabled { opacity: .65; cursor: wait; }
        body.page-doctores .doctores-section-heading .doctores-back {
            width: auto;
            min-height: 40px;
            padding: 0 13px;
            border-radius: 7px;
            font-size: 13px;
            white-space: nowrap;
        }
    </style>
    <link rel="stylesheet" href="css/filtros-directorio.css?v=1">
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

                <?php if (!$editando || ($usuarioFicha ?? "") !== ""): ?>
                    <fieldset class="password-admin" data-password-admin>
                        <legend>Credenciales de acceso</legend>
                        <div class="password-admin-campos">
                            <label for="password-admin-nueva"><?= !$editando ? "Contraseña" : "Nueva contraseña (opcional)" ?>
                                <input id="password-admin-nueva" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"
                                       pattern="(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{Cc}\p{Zl}\p{Zp}]{8,}"
                                       aria-describedby="reglas-password" title="Mínimo 8 caracteres: mayúscula, minúscula, número y símbolo."
                                       <?= !$editando ? "required" : "" ?>>
                            </label>
                            <label for="password-admin-confirmar">Confirmar contraseña
                                <input id="password-admin-confirmar" type="password" name="confirmar_password" minlength="8" maxlength="72" autocomplete="new-password"
                                       aria-describedby="reglas-password" <?= !$editando ? "required" : "" ?>>
                            </label>
                        </div>
                        <button class="password-admin-toggle" type="button" data-mostrar-password aria-pressed="false" aria-controls="password-admin-nueva password-admin-confirmar" hidden>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-admin-ojo-tachado" d="m3 3 18 18"/></svg>
                            <span data-texto-password>Mostrar contraseñas</span>
                        </button>
                        <div id="reglas-password" class="password-reglas-admin">
                            <strong>Requisitos de contraseña</strong>
                            <ul class="password-admin-lista">
                                <li data-regla="longitud">Mínimo 8 caracteres</li>
                                <li data-regla="mayuscula">Una letra mayúscula</li>
                                <li data-regla="minuscula">Una letra minúscula</li>
                                <li data-regla="numero">Un número</li>
                                <li data-regla="simbolo">Un símbolo especial</li>
                                <li data-regla="coincidencia">Las contraseñas coinciden</li>
                            </ul>
                            <p class="password-admin-nota">Máximo 72 bytes.</p>
                            <?php if (!(!$editando)): ?>
                                <p class="password-admin-nota">Deja ambos campos vacíos para conservar la contraseña actual.</p>
                            <?php endif; ?>
                            <p class="password-admin-estado" data-password-estado role="status" aria-live="polite"></p>
                        </div>
                    </fieldset>
                <?php else: ?>
                    <p class="password-admin-nota">Esta ficha no tiene una cuenta asignada para cambiar la contraseña.</p>
                <?php endif; ?>

                <div class="doctor-form-grid">
                    <label>DNI <span>8 dígitos · obligatorio</span>
                        <input type="text" name="dni" inputmode="numeric" minlength="8" maxlength="8"
                               pattern="[0-9]{8}" title="Ingresa exactamente 8 dígitos." autocomplete="off"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')" required value="<?= htmlspecialchars($formulario['dni'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <div class="doctor-dni-consulta">
                        <button type="button" class="doctores-back" id="consultar-dni">Consultar DNI</button>
                        <small id="resultado-dni" role="status" aria-live="polite"></small>
                    </div>
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
                    <h2 id="tituloListaDoctores"><?= $verInactivos ? "Doctores inactivos" : ($filtros['estado'] === 'todos' ? "Todos los doctores" : "Doctores registrados") ?></h2>
                </div>
                <?php if (!$mostrarFormulario): ?>
                    <a class="doctores-back" href="doctores?nuevo=1">Agregar doctor</a>
                <?php endif; ?>
                <span class="doctores-count"><?= (int)$listado['total'] ?> doctores</span>
            </div>

            <?php $esDirectorioPaciente = false; require __DIR__.'/componentes/filtros_directorio.php'; ?>
            <div class="doctores-table-wrap">
                <table class="doctores-table">
                    <thead>
                        <tr><th>Profesional</th><th>DNI</th><th>Especialidad</th><th>Horario</th><th>Usuario</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctores as $doctor): ?>
                            <tr>
                                <td data-label="Profesional">
                                    <strong><?php echo htmlspecialchars($doctor["nombres"] . " " . $doctor["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong>
                                    <small><?php echo htmlspecialchars($doctor["cmp"] ?: "CMP no registrado", ENT_QUOTES, "UTF-8"); ?> · <?= (int)$doctor['activo'] === 1 ? 'Activo' : 'Inactivo' ?></small>
                                </td>
                                <td data-label="DNI"><?= htmlspecialchars($doctor["dni"] ?? "Pendiente de registrar", ENT_QUOTES, "UTF-8") ?></td>
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
                                    <?php if ((int)$doctor['activo'] === 1): ?><a class="doctores-back" href="doctores?editar=<?= (int)$doctor['id'] ?>">Editar</a><?php endif; ?>
                                    <a class="doctores-back" href="estado_perfil?tipo=doctor&amp;id=<?= (int)$doctor['id'] ?>"><?= (int)$doctor['activo'] !== 1 ? 'Reactivar' : 'Desactivar' ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$doctores): ?>
                            <tr><td colspan="6" class="doctores-empty">No se encontraron doctores con estos filtros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php require __DIR__.'/componentes/paginacion_directorio.php'; ?>
        </section>
    </section>
</main>
<script src="js/javascript.js?v=20261124-usuarios" defer></script>
<script src="js/password-admin.js?v=2" defer></script>
<script src="js/consulta_dni.js?v=20261127-doctores" defer></script>
</body>
</html>
