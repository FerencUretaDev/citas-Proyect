<?php
session_start();
include "conexion.php";
require_once __DIR__ . "/generar_usuario.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || (int)$_SESSION["rol"] !== 2) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$rol = 2;
$paginaActiva = "pacientes";

$verInactivos = ($_GET["vista"] ?? "") === "inactivos";
$estadoListado = $verInactivos ? 0 : 1;
$mensaje = "";
$tipoMensaje = "error";
$pacienteId = (int)($_GET["editar"] ?? 0);
$estaCreando = $pacienteId === 0 && isset($_GET["nuevo"]);
$primerApellido = $segundoApellido = "";
$formulario = [
    "nombres" => "",
    "apellidos" => "",
    "dni" => "",
    "fecha_nacimiento" => "",
    "sexo" => "",
    "telefono" => "",
    "direccion" => "",
    "correo" => ""
];

if (isset($_SESSION["mensaje_paciente"])) {
    $mensaje = $_SESSION["mensaje_paciente"];
    $tipoMensaje = "success";
    unset($_SESSION["mensaje_paciente"]);
}

if ($pacienteId > 0) {
    $stmtEditar = $conexion->prepare("SELECT nombres, apellidos, dni, fecha_nacimiento, sexo, telefono, direccion, correo FROM pacientes WHERE activo=1 AND id = ?");
    $stmtEditar->bind_param("i", $pacienteId);
    $stmtEditar->execute();
    $pacienteEditar = $stmtEditar->get_result()->fetch_assoc();
    $stmtEditar->close();

    if (!$pacienteEditar) {
        http_response_code(404);
        exit("Paciente no encontrado.");
    }
    $formulario = array_merge($formulario, $pacienteEditar);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    $estaCreando = ($_POST["accion"] ?? "editar") === "crear";
    $pacienteId = filter_var($_POST["paciente_id"] ?? null, FILTER_VALIDATE_INT);
    if ($pacienteId === false || ($estaCreando ? $pacienteId !== 0 : $pacienteId <= 0)) {
        http_response_code(400);
        exit("Ficha de paciente no válida.");
    }
    $password = $_POST["password"] ?? "";
    foreach ($formulario as $campo => $valor) {
        $entrada = $_POST[$campo] ?? "";
        $entrada = is_string($entrada) ? $entrada : "";
        $formulario[$campo] = $campo === "dni" ? $entrada : trim($entrada);
    }

    if ($estaCreando) {
        $primerApellido = trim(is_string($_POST["primer_apellido"] ?? null) ? $_POST["primer_apellido"] : "");
        $segundoApellido = trim(is_string($_POST["segundo_apellido"] ?? null) ? $_POST["segundo_apellido"] : "");
        $formulario["apellidos"] = trim($primerApellido . " " . $segundoApellido);
        $base = baseUsuario($formulario["nombres"], $primerApellido, $segundoApellido, 0);
    }

    $fechaNacimiento = $formulario["fecha_nacimiento"];
    $fechaValida = true;
    if ($fechaNacimiento !== "") {
        $fecha = DateTime::createFromFormat("!Y-m-d", $fechaNacimiento);
        $erroresFecha = DateTime::getLastErrors();
        $fechaValida = $fecha !== false &&
            ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
            $fecha->format("Y-m-d") === $fechaNacimiento &&
            $fechaNacimiento <= date("Y-m-d");
    }

    $sexoValido = in_array($formulario["sexo"], ["", "M", "F", "X"], true);
    $correoValido = $formulario["correo"] === "" || filter_var($formulario["correo"], FILTER_VALIDATE_EMAIL);
    $datosValidos = $formulario["nombres"] !== "" &&
        $formulario["apellidos"] !== "" &&
        strlen($formulario["direccion"]) <= 200 &&
        strlen($formulario["telefono"]) <= 20 && strlen($formulario["correo"]) <= 100 &&
        $fechaValida && $sexoValido && $correoValido;

    if (!preg_match('/\A[0-9]{8}\z/', $formulario["dni"])) {
        $mensaje = "El DNI debe contener exactamente 8 dígitos, sin espacios ni letras.";
        $tipoMensaje = "error";
    } elseif ($estaCreando && ($base === "" || preg_match('/^.{1,100}$/usD', $formulario["apellidos"]) !== 1)) {
        $mensaje = "Revisa los nombres y apellidos. Los apellidos juntos admiten hasta 100 caracteres.";
    } elseif (!$datosValidos) {
        $mensaje = "Revisa los campos obligatorios, el formato de correo y la fecha de nacimiento.";
    } elseif ($estaCreando && (!is_string($password) || strlen($password) < 10 || strlen($password) > 72 || str_contains($password, "\0"))) {
        $mensaje = "La contraseña debe tener entre 10 y 72 bytes.";
    } else {
        try {
            $nombres = $formulario["nombres"];
            $apellidos = $formulario["apellidos"];
            $dni = $formulario["dni"];
            $fechaNacimientoDb = $fechaNacimiento !== "" ? $fechaNacimiento : null;
            $sexo = $formulario["sexo"] !== "" ? $formulario["sexo"] : null;
            $telefono = $formulario["telefono"] !== "" ? $formulario["telefono"] : null;
            $direccion = $formulario["direccion"] !== "" ? $formulario["direccion"] : null;
            $correo = $formulario["correo"] !== "" ? $formulario["correo"] : null;

            if ($estaCreando) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $conexion->begin_transaction();
                [$usuarioId, $usuario] = crearCuentaConUsuario($conexion, $base, $hash, 0);

                $stmt = $conexion->prepare("INSERT INTO pacientes (usuario_id, nombres, apellidos, dni, fecha_nacimiento, sexo, telefono, direccion, correo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssss", $usuarioId, $nombres, $apellidos, $dni, $fechaNacimientoDb, $sexo, $telefono, $direccion, $correo);
            } else {
                $stmt = $conexion->prepare("UPDATE pacientes SET nombres = ?, apellidos = ?, dni = ?, fecha_nacimiento = ?, sexo = ?, telefono = ?, direccion = ?, correo = ? WHERE activo=1 AND id = ?");
                $stmt->bind_param("ssssssssi", $nombres, $apellidos, $dni, $fechaNacimientoDb, $sexo, $telefono, $direccion, $correo, $pacienteId);
            }
            $stmt->execute();
            $stmt->close();
            if ($estaCreando) {
                $conexion->commit();
            }
            $_SESSION["mensaje_paciente"] = $estaCreando
                ? "Paciente registrado. Usuario asignado: $usuario"
                : "Datos del paciente actualizados correctamente.";
            header("Location: pacientes");
            exit;
        } catch (mysqli_sql_exception $error) {
            if ($estaCreando) {
                $conexion->rollback();
            }
            $tipoMensaje = "error";
            error_log("Error al guardar paciente. Código MySQL: " . $error->getCode());
            $mensaje = $error->getCode() === 1062
                ? "Ese DNI ya está asociado a otro paciente."
                : "No se pudieron guardar los datos. Inténtalo de nuevo.";
        }
    }
}

$pacientes = [];
$sqlPacientes = "
    SELECT p.id, p.nombres, p.apellidos, p.dni, p.telefono, p.correo, p.fecha_registro
    FROM pacientes p
    WHERE p.activo = $estadoListado
    ORDER BY p.apellidos, p.nombres
";
$resultadoPacientes = $conexion->query($sqlPacientes);
while ($paciente = $resultadoPacientes->fetch_assoc()) {
    $pacientes[] = $paciente;
}
$estaEditando = $pacienteId > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Administrar pacientes</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
</head>
<body class="page-pacientes has-sidebar">
<?php include "sidebar.php"; ?>
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
                            <label>Contraseña
                                <input type="password" name="password" minlength="10" maxlength="72" autocomplete="new-password" required>
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
                            <p>El nombre de usuario y la contraseña no se modifican desde esta ficha.</p>
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
                        <tr><th>Paciente</th><th>DNI</th><th>Contacto</th><th>Registro</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr>
                                <td data-label="Paciente"><strong><?php echo htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong></td>
                                <td data-label="DNI"><?php echo htmlspecialchars($paciente["dni"], ENT_QUOTES, "UTF-8"); ?></td>
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
                            <tr><td colspan="5" class="pacientes-empty">No hay pacientes en esta lista.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<script src="js/javascript.js" defer></script>
<script src="js/consulta_dni.js" defer></script>
</body>
</html>
