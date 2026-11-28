<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
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
header('Cache-Control: no-store');
$esBusqueda = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? null) === 'buscar_directorio';
if ($esBusqueda && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
    http_response_code(403); exit('Solicitud no válida.');
}
$rol = 2;
$paginaActiva = "pacientes";

$verInactivos = ($_GET["vista"] ?? "") === "inactivos";
$estadoListado = $verInactivos ? 0 : 1;
$mensaje = "";
$tipoMensaje = "error";
$usuarioRegistrado = null;
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
    $usuarioRegistrado = $_SESSION["usuario_registrado_paciente"] ?? null;
    unset($_SESSION["mensaje_paciente"], $_SESSION["usuario_registrado_paciente"]);
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

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$esBusqueda) {
    $tipoMensaje = "error";
    if (!is_string($_POST["csrf_token"] ?? null) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
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
    $confirmacionPassword = $_POST["confirmar_password"] ?? "";
    $errorPassword = validarPasswordDirectorio($password, $confirmacionPassword, !$estaCreando);
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

    $sexoValido = sexoPacienteValido($formulario["sexo"]);
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
    } elseif (!$sexoValido) {
        $mensaje = "Selecciona Masculino o Femenino en el campo Sexo.";
    } elseif (!$datosValidos) {
        $mensaje = "Revisa los campos obligatorios, el formato de correo y la fecha de nacimiento.";
    } elseif ($errorPassword !== null) {
        $mensaje = $errorPassword;
    } else {
        try {
            $conexion->begin_transaction();
            $nombres = $formulario["nombres"];
            $apellidos = $formulario["apellidos"];
            $dni = $formulario["dni"];
            $fechaNacimientoDb = $fechaNacimiento !== "" ? $fechaNacimiento : null;
            $sexo = $formulario["sexo"];
            $telefono = $formulario["telefono"] !== "" ? $formulario["telefono"] : null;
            $direccion = $formulario["direccion"] !== "" ? $formulario["direccion"] : null;
            $correo = $formulario["correo"] !== "" ? $formulario["correo"] : null;

            if ($estaCreando) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                [$usuarioId, $usuario] = crearCuentaConUsuario($conexion, $base, $hash, 0);

                $stmt = $conexion->prepare("INSERT INTO pacientes (usuario_id, nombres, apellidos, dni, fecha_nacimiento, sexo, telefono, direccion, correo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssss", $usuarioId, $nombres, $apellidos, $dni, $fechaNacimientoDb, $sexo, $telefono, $direccion, $correo);
            } else {
                bloquearFichaDirectorio($conexion, $pacienteId, 0);
                $stmt = $conexion->prepare("UPDATE pacientes SET nombres = ?, apellidos = ?, dni = ?, fecha_nacimiento = ?, sexo = ?, telefono = ?, direccion = ?, correo = ? WHERE activo=1 AND id = ?");
                $stmt->bind_param("ssssssssi", $nombres, $apellidos, $dni, $fechaNacimientoDb, $sexo, $telefono, $direccion, $correo, $pacienteId);
            }
            $stmt->execute();
            $idAuditoria = $estaCreando ? (int)$stmt->insert_id : (int)$pacienteId;
            $stmt->close();
            if (!$estaCreando && $password !== "") {
                actualizarPasswordFichaDirectorio($conexion, $pacienteId, 0, password_hash($password, PASSWORD_DEFAULT));
            }
            $conexion->commit();
            auditar($conexion,"pacientes",$estaCreando ? "registrar" : "editar","exitoso","paciente",$idAuditoria);
            if (!$estaCreando && $password !== "") auditar($conexion,"pacientes","cambiar_password","exitoso","paciente",$idAuditoria);
            $_SESSION["mensaje_paciente"] = $estaCreando
                ? "Paciente registrado correctamente."
                : ($password !== "" ? "Datos y contraseña del paciente actualizados correctamente." : "Datos del paciente actualizados correctamente.");
            if ($estaCreando) {
                $_SESSION["usuario_registrado_paciente"] = $usuario;
            } else {
                unset($_SESSION["usuario_registrado_paciente"]);
            }
            header("Location: pacientes");
            exit;
        } catch (DomainException $error) {
            $conexion->rollback();
            $mensaje = $error->getMessage();
        } catch (mysqli_sql_exception $error) {
            $conexion->rollback();
            $tipoMensaje = "error";
            error_log("Error al guardar paciente. Código MySQL: " . $error->getCode());
            $mensaje = $error->getCode() === 1062
                ? "Ese DNI ya está asociado a otro paciente."
                : "No se pudieron guardar los datos. Inténtalo de nuevo.";
        }
    }
}

$errorFiltros = '';
$filtros = filtrosDirectorio([], true, (string)$estadoListado);
try {
    if ($esBusqueda) $filtros = filtrosDirectorio($_POST, true, (string)$estadoListado);
    $listado = buscarDirectorio($conexion, true, $filtros);
    if ($esBusqueda) auditar($conexion, 'pacientes', 'consultar', 'exitoso');
} catch (DomainException $e) {
    $errorFiltros = $e->getMessage();
    $listado = ['filas'=>[], 'total'=>0, 'pagina'=>1, 'paginas'=>1];
    if ($esBusqueda) auditar($conexion, 'pacientes', 'consultar', 'fallido');
}
$pacientes = $listado['filas'];
$filtros['pagina'] = $listado['pagina'];
$verInactivos = $filtros['estado'] === '0';

$estaEditando = $pacienteId > 0;
$usuarioFicha = $estaEditando ? usuarioFichaPaciente($conexion, $pacienteId) : null;

require dirname(__DIR__) . "/vistas/pacientes.php";
