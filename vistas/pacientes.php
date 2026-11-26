<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Administrar pacientes</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
    <link rel="stylesheet" href="css/usuarios-directorio.css?v=20261126-password-ui">
</head>
<body class="page-pacientes has-sidebar">
<?php include dirname(__DIR__) . "/vistas/componentes/sidebar.php"; ?>
<main class="pacientes-page">
    <header class="pacientes-header">
        <div>
            <p class="pacientes-eyebrow">ADMINISTRACIÓN / PACIENTES</p>
            <h1>Directorio de pacientes</h1>
            <p>Registra pacientes y actualiza sus datos personales o de contacto.</p>
        </div>
        <a class="pacientes-back" href="panel">Volver al panel</a>
    </header>

    <?php if ($mensaje !== ""): ?>
        <div class="pacientes-alerta <?php echo $tipoMensaje; ?>" role="status">
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

    <section class="pacientes-layout <?php echo $estaEditando || $estaCreando ? "is-editing" : ""; ?>">
        <?php if ($estaEditando || $estaCreando): ?>
            <section class="pacientes-form-section" aria-labelledby="tituloFichaPaciente">
                <div class="pacientes-section-heading">
                    <div>
                        <p class="pacientes-eyebrow">FICHA DEL PACIENTE</p>
                        <h2 id="tituloFichaPaciente"><?php echo $estaCreando ? "Agregar paciente" : "Editar datos"; ?></h2>
                    </div>
                    <a class="pacientes-cancel" href="pacientes">Cancelar</a>
                </div>

                <form method="POST" action="pacientes" class="paciente-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="accion" value="<?php echo $estaCreando ? "crear" : "editar"; ?>">
                    <input type="hidden" name="paciente_id" value="<?php echo $pacienteId; ?>">

                    <?php if ($estaEditando): ?>
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

                    <?php if ($estaCreando || ($usuarioFicha ?? "") !== ""): ?>
                        <fieldset class="password-admin" data-password-admin>
                            <legend>Credenciales de acceso</legend>
                            <div class="password-admin-campos">
                                <label for="password-admin-nueva"><?= $estaCreando ? "Contraseña" : "Nueva contraseña (opcional)" ?>
                                    <input id="password-admin-nueva" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"
                                           pattern="(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{Cc}\p{Zl}\p{Zp}]{8,}"
                                           aria-describedby="reglas-password" title="Mínimo 8 caracteres: mayúscula, minúscula, número y símbolo."
                                           <?= $estaCreando ? "required" : "" ?>>
                                </label>
                                <label for="password-admin-confirmar">Confirmar contraseña
                                    <input id="password-admin-confirmar" type="password" name="confirmar_password" minlength="8" maxlength="72" autocomplete="new-password"
                                           aria-describedby="reglas-password" <?= $estaCreando ? "required" : "" ?>>
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
                                <?php if (!($estaCreando)): ?>
                                    <p class="password-admin-nota">Deja ambos campos vacíos para conservar la contraseña actual.</p>
                                <?php endif; ?>
                                <p class="password-admin-estado" data-password-estado role="status" aria-live="polite"></p>
                            </div>
                        </fieldset>
                    <?php else: ?>
                        <p class="password-admin-nota">Esta ficha no tiene una cuenta asignada para cambiar la contraseña.</p>
                    <?php endif; ?>

                    <div class="paciente-form-grid">
                        <label>DNI <span>8 dígitos</span>
                            <input type="text" name="dni" inputmode="numeric" minlength="8" maxlength="8"
                                   pattern="[0-9]{8}" title="Ingresa exactamente 8 dígitos."
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')" required value="<?php echo htmlspecialchars($formulario["dni"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <?php if ($estaCreando): ?>
                            <div>
                                <button type="button" class="pacientes-edit" id="consultar-dni">Consultar DNI</button>
                                <small id="resultado-dni" role="status"></small>
                            </div>
                        <?php endif; ?>
                        <label>Nombres
                            <input name="nombres" maxlength="100" required value="<?php echo htmlspecialchars($formulario["nombres"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <?php if ($estaCreando): ?>
                            <label>Primer apellido
                                <input name="primer_apellido" maxlength="100" required value="<?php echo htmlspecialchars($primerApellido, ENT_QUOTES, "UTF-8"); ?>">
                            </label>
                            <label>Segundo apellido <span>Opcional</span>
                                <input name="segundo_apellido" maxlength="100" value="<?php echo htmlspecialchars($segundoApellido, ENT_QUOTES, "UTF-8"); ?>">
                            </label>
                        <?php else: ?>
                        <label>Apellidos
                            <input name="apellidos" maxlength="100" required value="<?php echo htmlspecialchars($formulario["apellidos"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <?php endif; ?>
                        <label>Fecha de nacimiento <span>Opcional</span>
                            <input type="date" name="fecha_nacimiento" max="<?php echo date("Y-m-d"); ?>" value="<?php echo htmlspecialchars($formulario["fecha_nacimiento"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Sexo <span>Opcional</span>
                            <select name="sexo">
                                <option value="">Sin especificar</option>
                                <option value="M" <?php echo ($formulario["sexo"] ?? "") === "M" ? "selected" : ""; ?>>Masculino</option>
                                <option value="F" <?php echo ($formulario["sexo"] ?? "") === "F" ? "selected" : ""; ?>>Femenino</option>
                                <option value="X" <?php echo ($formulario["sexo"] ?? "") === "X" ? "selected" : ""; ?>>Otro</option>
                            </select>
                        </label>
                        <label>Teléfono <span>Opcional</span>
                            <input name="telefono" maxlength="20" autocomplete="tel" value="<?php echo htmlspecialchars($formulario["telefono"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Correo <span>Opcional</span>
                            <input type="email" name="correo" maxlength="100" autocomplete="email" value="<?php echo htmlspecialchars($formulario["correo"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label class="paciente-direccion">Dirección <span>Opcional</span>
                            <input name="direccion" maxlength="200" autocomplete="street-address" value="<?php echo htmlspecialchars($formulario["direccion"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                    </div>

                    <div class="paciente-form-footer">
                        <?php if ($estaEditando): ?>
                            <p>El nombre de usuario no se modifica desde esta ficha.</p>
                        <?php endif; ?>
                        <button type="submit"><?php echo $estaCreando ? "Registrar paciente" : "Guardar cambios"; ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="pacientes-list-section" aria-labelledby="tituloListaPacientes">
            <div class="pacientes-section-heading">
                <div>
                    <p class="pacientes-eyebrow">REGISTROS</p>
                    <h2 id="tituloListaPacientes"><?= $verInactivos ? "Pacientes archivados" : "Pacientes registrados" ?></h2>
                </div>
                <?php if (!$estaCreando): ?>
                    <a class="pacientes-edit" href="pacientes?nuevo=1">Agregar paciente</a>
                <?php endif; ?>
                <span class="pacientes-count"><?php echo count($pacientes); ?> pacientes</span>
            </div>

            <p><a href="pacientes<?= $verInactivos ? '' : '?vista=inactivos' ?>"><?= $verInactivos ? 'Ver activos' : 'Ver archivados' ?></a></p>
            <div class="pacientes-table-wrap">
                <table class="pacientes-table">
                    <thead>
                        <tr><th>Paciente</th><th>DNI</th><th>Usuario</th><th>Contacto</th><th>Registro</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr>
                                <td data-label="Paciente"><strong><?php echo htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong></td>
                                <td data-label="DNI"><?php echo htmlspecialchars($paciente["dni"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td data-label="Usuario">
                                    <?php if (($paciente["usuario"] ?? "") !== ""): ?>
                                        <span class="usuario-acceso">
                                            <span class="usuario-acceso-valor"><?= htmlspecialchars($paciente["usuario"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                                            <button type="button" class="usuario-copiar" data-copiar-usuario="<?= htmlspecialchars($paciente["usuario"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>" aria-label="Copiar nombre de usuario">Copiar</button>
                                            <span class="usuario-copia-estado" role="status" aria-live="polite"></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="usuario-sin-cuenta">Sin usuario asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Contacto">
                                    <span><?php echo htmlspecialchars($paciente["telefono"] ?: "Sin teléfono", ENT_QUOTES, "UTF-8"); ?></span>
                                    <small><?php echo htmlspecialchars($paciente["correo"] ?: "Sin correo", ENT_QUOTES, "UTF-8"); ?></small>
                                </td>
                                <td data-label="Registro"><?php echo htmlspecialchars(date("d/m/Y", strtotime($paciente["fecha_registro"])), ENT_QUOTES, "UTF-8"); ?></td>
                                <td data-label="Acciones">
                                    <?php if (!$verInactivos): ?><a class="pacientes-edit" href="pacientes?editar=<?= (int)$paciente['id'] ?>">Editar</a><?php endif; ?>
                                    <a class="pacientes-edit" href="estado_perfil?tipo=paciente&amp;id=<?= (int)$paciente['id'] ?>"><?= $verInactivos ? 'Reactivar' : 'Archivar' ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pacientes): ?>
                            <tr><td colspan="6" class="pacientes-empty">No hay pacientes en esta lista.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<script src="js/javascript.js?v=20261124-usuarios" defer></script>
<script src="js/consulta_dni.js" defer></script>
<script src="js/password-admin.js?v=2" defer></script>
</body>
</html>
