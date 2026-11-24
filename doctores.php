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
$paginaActiva = "doctores";
$doctorId = filter_var($_GET['editar'] ?? 0, FILTER_VALIDATE_INT);
if ($doctorId === false || $doctorId < 0) { http_response_code(400); exit("Doctor no válido."); }
$editando = $doctorId > 0;
$mostrarFormulario = $editando || ($_GET["nuevo"] ?? "") === "1" || $_SERVER["REQUEST_METHOD"] === "POST";

$verInactivos = ($_GET["vista"] ?? "") === "inactivos";
$estadoListado = $verInactivos ? 0 : 1;
$mensaje = "";
$tipoMensaje = "error";
$primerApellido = $segundoApellido = "";
$formulario = [
    "nombres" => "",
    "apellidos" => "",
    "cmp" => "",
    "especialidad" => "",
    "telefono" => "",
    "correo" => "",
    "hora_inicio" => "08:00",
    "hora_fin" => "17:00",
    "refrigerio_inicio" => "",
    "refrigerio_fin" => ""
];

require_once __DIR__ . '/catalogo_especialidades.php';
if ($mostrarFormulario) {
    [$catalogo, $opcionesPorClave] = cargarCatalogoEspecialidades($conexion);
}

if ($editando) {
    $stmtFicha = $conexion->prepare("SELECT nombres, apellidos, especialidad, cmp, telefono, correo,
        TIME_FORMAT(hora_inicio,'%H:%i') AS hora_inicio, TIME_FORMAT(hora_fin,'%H:%i') AS hora_fin,
        TIME_FORMAT(refrigerio_inicio,'%H:%i') AS refrigerio_inicio, TIME_FORMAT(refrigerio_fin,'%H:%i') AS refrigerio_fin
        FROM doctores WHERE id=? AND activo=1");
    $stmtFicha->bind_param("i", $doctorId);
    $stmtFicha->execute();
    $datos = $stmtFicha->get_result()->fetch_assoc();
    if (!$datos) { http_response_code(404); exit("Doctor activo no encontrado."); }
    $formulario = array_map(static fn($valor) => $valor ?? "", $datos);
    $formulario["especialidad"] = $opcionesPorClave[claveEspecialidad($formulario["especialidad"])] ?? "";
}

if (isset($_SESSION["mensaje_doctor"])) {
    $mensaje = $_SESSION["mensaje_doctor"];
    $tipoMensaje = "success";
    unset($_SESSION["mensaje_doctor"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    foreach ($formulario as $campo => $valor) {
        $entrada = $_POST[$campo] ?? ($campo === "especialidad" ? "" : $valor);
        $formulario[$campo] = is_string($entrada) ? trim($entrada) : "";
    }
    $password = $_POST["password"] ?? "";
    if (!$editando) {
        $primerApellido = trim(is_string($_POST["primer_apellido"] ?? null) ? $_POST["primer_apellido"] : "");
        $segundoApellido = trim(is_string($_POST["segundo_apellido"] ?? null) ? $_POST["segundo_apellido"] : "");
        $formulario["apellidos"] = trim($primerApellido . " " . $segundoApellido);
        $base = baseUsuario($formulario["nombres"], $primerApellido, $segundoApellido, 1);
    }
    $emailValido = $formulario["correo"] === "" || filter_var($formulario["correo"], FILTER_VALIDATE_EMAIL);
    $horaValida = static function ($hora) {
        return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $hora) === 1;
    };

    $datosValidos = $formulario["nombres"] !== "" && $formulario["apellidos"] !== "" &&
        $formulario["especialidad"] !== "" && ($editando || (is_string($password) && strlen($password) >= 10 && strlen($password) <= 72 && !str_contains($password, "\0"))) && $emailValido &&
        $horaValida($formulario["hora_inicio"]) && $horaValida($formulario["hora_fin"]) &&
        $formulario["hora_inicio"] < $formulario["hora_fin"];

    $tieneRefrigerio = $formulario["refrigerio_inicio"] !== "" || $formulario["refrigerio_fin"] !== "";
    if ($tieneRefrigerio) {
        $datosValidos = $datosValidos && $horaValida($formulario["refrigerio_inicio"]) &&
            $horaValida($formulario["refrigerio_fin"]) &&
            $formulario["refrigerio_inicio"] < $formulario["refrigerio_fin"] &&
            $formulario["refrigerio_inicio"] >= $formulario["hora_inicio"] &&
            $formulario["refrigerio_fin"] <= $formulario["hora_fin"];
    }

    if ((!$editando && $base === "") || preg_match('/^.{1,100}$/usD', $formulario["apellidos"]) !== 1) {
        $mensaje = "Revisa los nombres y apellidos. Los apellidos juntos admiten hasta 100 caracteres.";
    } elseif (!in_array($formulario["especialidad"], $opcionesPorClave, true)) {
        $mensaje = "Selecciona una especialidad de la lista.";
    } elseif (!$datosValidos) {
        $mensaje = "Revisa los campos: contraseña de 10 a 72 bytes, correo válido y horarios coherentes.";
    } else {
        try {
            $conexion->begin_transaction();

            $nombres = $formulario["nombres"];
            $apellidos = $formulario["apellidos"];
            $cmp = $formulario["cmp"] !== "" ? $formulario["cmp"] : null;
            $especialidad = $formulario["especialidad"];
            $telefono = $formulario["telefono"] !== "" ? $formulario["telefono"] : null;
            $correo = $formulario["correo"] !== "" ? $formulario["correo"] : null;
            $horaInicio = $formulario["hora_inicio"] . ":00";
            $horaFin = $formulario["hora_fin"] . ":00";
            $refrigerioInicio = $formulario["refrigerio_inicio"] !== "" ? $formulario["refrigerio_inicio"] . ":00" : null;
            $refrigerioFin = $formulario["refrigerio_fin"] !== "" ? $formulario["refrigerio_fin"] . ":00" : null;

            if ($editando) {
                $stmtDoctor = $conexion->prepare("UPDATE doctores SET nombres=?, apellidos=?, cmp=?, especialidad=?, telefono=?, correo=?,
                    hora_inicio=?, hora_fin=?, refrigerio_inicio=?, refrigerio_fin=? WHERE id=? AND activo=1");
                $stmtDoctor->bind_param("ssssssssssi", $nombres, $apellidos, $cmp, $especialidad, $telefono, $correo, $horaInicio, $horaFin, $refrigerioInicio, $refrigerioFin, $doctorId);
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                [$usuarioId, $usuario] = crearCuentaConUsuario($conexion, $base, $passwordHash, 1);
                $stmtDoctor = $conexion->prepare("INSERT INTO doctores (usuario_id, nombres, apellidos, cmp, especialidad, telefono, correo, hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtDoctor->bind_param("issssssssss", $usuarioId, $nombres, $apellidos, $cmp, $especialidad, $telefono, $correo, $horaInicio, $horaFin, $refrigerioInicio, $refrigerioFin);
            }
            $stmtDoctor->execute();
            $stmtDoctor->close();

            $conexion->commit();
            $_SESSION["mensaje_doctor"] = $editando ? "Datos del doctor actualizados." : "Doctor registrado. Usuario asignado: $usuario";
            header("Location: doctores");
            exit;
        } catch (mysqli_sql_exception $error) {
            $conexion->rollback();
            error_log("Error al registrar doctor: " . $error->getMessage());
            $mensaje = $error->getCode() === 1062
                ? "Ya existe un registro con esos datos."
                : "No se pudo registrar el doctor. Revisa los datos e inténtalo de nuevo.";
        }
    }
}

$doctores = [];
$sqlDoctores = "
    SELECT d.id, d.nombres, d.apellidos, d.cmp, d.especialidad, d.telefono,
           d.hora_inicio, d.hora_fin, u.usuario
    FROM doctores d
    INNER JOIN usuarios u ON u.id = d.usuario_id
    WHERE d.activo = $estadoListado
    ORDER BY d.apellidos, d.nombres
";
$resultadoDoctores = $conexion->query($sqlDoctores);
while ($doctor = $resultadoDoctores->fetch_assoc()) {
    $doctores[] = $doctor;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Directorio de doctores</title>
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
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
<?php include "sidebar.php"; ?>
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
                        <tr><th>Profesional</th><th>Especialidad</th><th>Horario</th><th>Acceso</th><th>Acciones</th></tr>
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
                                <td data-label="Acceso"><?php echo htmlspecialchars($doctor["usuario"], ENT_QUOTES, "UTF-8"); ?></td>
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
<script src="js/javascript.js" defer></script>
</body>
</html>
