<?php
session_start();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/repositorios/DirectorioDAO.php";
require_once dirname(__DIR__) . "/servicios/UsuarioService.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || (int)$_SESSION["rol"] !== 2) {
    header("Location: index.php");
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
$usuarioRegistrado = null;
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

require_once dirname(__DIR__) . '/servicios/EspecialidadService.php';
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
    $usuarioRegistrado = $_SESSION["usuario_registrado_doctor"] ?? null;
    unset($_SESSION["mensaje_doctor"], $_SESSION["usuario_registrado_doctor"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tipoMensaje = "error";
    if (!is_string($_POST["csrf_token"] ?? null) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    foreach ($formulario as $campo => $valor) {
        $entrada = $_POST[$campo] ?? ($campo === "especialidad" ? "" : $valor);
        $formulario[$campo] = is_string($entrada) ? trim($entrada) : "";
    }
    $password = $_POST["password"] ?? "";
    $confirmacionPassword = $_POST["confirmar_password"] ?? "";
    $errorPassword = validarPasswordDirectorio($password, $confirmacionPassword, $editando);
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
        $formulario["especialidad"] !== "" && $emailValido &&
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
        $mensaje = "Revisa los campos obligatorios, correo válido y horarios coherentes.";
    } elseif ($errorPassword !== null) {
        $mensaje = $errorPassword;
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
                bloquearFichaDirectorio($conexion, $doctorId, 1);
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
            $idAuditoria = !$editando ? (int)$stmtDoctor->insert_id : (int)$doctorId;
            $stmtDoctor->close();

            if ($editando && $password !== "") {
                actualizarPasswordFichaDirectorio($conexion, $doctorId, 1, password_hash($password, PASSWORD_DEFAULT));
            }
            $conexion->commit();
            auditar($conexion,"doctores",!$editando ? "registrar" : "editar","exitoso","doctor",$idAuditoria);
            if ($editando && $password !== "") auditar($conexion,"doctores","cambiar_password","exitoso","doctor",$idAuditoria);
            $_SESSION["mensaje_doctor"] = $editando
                ? ($password !== "" ? "Datos y contraseña del doctor actualizados correctamente." : "Datos del doctor actualizados.")
                : "Doctor registrado correctamente.";
            if (!$editando) {
                $_SESSION["usuario_registrado_doctor"] = $usuario;
            } else {
                unset($_SESSION["usuario_registrado_doctor"]);
            }
            header("Location: doctores");
            exit;
        } catch (DomainException $error) {
            $conexion->rollback();
            $mensaje = $error->getMessage();
        } catch (mysqli_sql_exception $error) {
            $conexion->rollback();
            error_log("Error al guardar doctor. Código MySQL: " . $error->getCode());
            $mensaje = $error->getCode() === 1062
                ? "Ya existe un registro con esos datos."
                : "No se pudo registrar el doctor. Revisa los datos e inténtalo de nuevo.";
        }
    }
}

$doctores = listarDoctores($conexion, $estadoListado);
$usuarioFicha = $editando ? usuarioFichaDoctor($conexion, $doctorId) : null;

require dirname(__DIR__) . "/vistas/doctores.php";
