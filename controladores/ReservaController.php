<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
date_default_timezone_set('America/Lima');
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/middlewares/SesionActiva.php";
verificarPerfilActivo($conexion);

/* =====================================================
   SEGURIDAD
===================================================== */

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 0) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$usuario_id = $_SESSION["id"];
$mensaje = "";
$tipo_mensaje = "";
$especialidadSeleccionada = "";
$doctorSeleccionado = 0;

/* =====================================================
   OBTENER PACIENTE
===================================================== */

$sqlPaciente = "
    SELECT id, nombres, apellidos, dni
    FROM pacientes
    WHERE activo = 1 AND usuario_id = ?
";

$stmtPaciente = $conexion->prepare($sqlPaciente);
$stmtPaciente->bind_param("i", $usuario_id);
$stmtPaciente->execute();

$resultPaciente = $stmtPaciente->get_result();

if ($resultPaciente->num_rows == 0) {
    die("No se encontraron los datos del paciente.");
}

$paciente = $resultPaciente->fetch_assoc();
$resultPaciente->free();
$stmtPaciente->close();
$paciente_id = $paciente["id"];
require_once dirname(__DIR__) . '/servicios/EspecialidadService.php';
[$catalogo, $opcionesPorClave] = cargarCatalogoEspecialidades($conexion);
require_once dirname(__DIR__) . '/servicios/ReservaService.php';
require_once dirname(__DIR__) . '/repositorios/TarifaDAO.php';

// Consulta anticipada: solo las citas del paciente autenticado, sin nombres ni DNI.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['reglas'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $fechaConsulta = $_GET['fecha'] ?? '';
    $especialidadConsulta = $_GET['especialidad'] ?? '';
    $dia = is_string($fechaConsulta) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $fechaConsulta)
        ? DateTime::createFromFormat('!Y-m-d', $fechaConsulta) : false;
    if (!$dia || $dia->format('Y-m-d') !== $fechaConsulta || $fechaConsulta < date('Y-m-d') ||
        !is_string($especialidadConsulta) || !in_array($especialidadConsulta, $opcionesPorClave, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Selecciona una fecha y especialidad válidas.']);
        exit;
    }
    try {
        $citasDia = citasPacienteDelDia($conexion, (int)$paciente_id, $fechaConsulta);
        echo json_encode(resumenRestriccionesReserva($citasDia, $especialidadConsulta, $opcionesPorClave), JSON_UNESCAPED_UNICODE);
    } catch (mysqli_sql_exception $error) {
        error_log('Error al consultar restricciones de reserva: ' . $error->getCode());
        http_response_code(500);
        echo json_encode(['error' => 'No se pudieron verificar tus citas. Intenta nuevamente.']);
    }
    exit;
}


/* =====================================================
   REGISTRAR CITA
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!is_string($_POST["csrf_token"] ?? null) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        http_response_code(403);
        die("Solicitud no válida.");
    }

    $doctor_id = intval($_POST["doctor_id"] ?? 0);
    $especialidadSeleccionada = (is_string($_POST["especialidad_id"] ?? null) ? trim($_POST["especialidad_id"]) : "");
    $doctorSeleccionado = $doctor_id;
    $fecha = is_string($_POST["fecha"] ?? null) ? $_POST["fecha"] : "";
    $hora = is_string($_POST["hora"] ?? null) ? $_POST["hora"] : "";
    $modalidad = is_string($_POST["modalidad"] ?? null) ? $_POST["modalidad"] : "";
    $motivo = is_string($_POST["motivo"] ?? null) ? trim($_POST["motivo"]) : "";
    // Solo representa el precio visto por el paciente; nunca es el importe autorizado.
    $precioMostrado = is_string($_POST["precio_mostrado"] ?? null) ? $_POST["precio_mostrado"] : "";
    $fechaValidada = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $fecha) ? DateTime::createFromFormat("!Y-m-d", $fecha) : false;
    $erroresFecha = DateTime::getLastErrors();
    $horaValidada = preg_match('/\A[0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $hora) ? DateTime::createFromFormat("!H:i:s", $hora) : false;
    $erroresHora = DateTime::getLastErrors();

    $fechaValida = $fechaValidada !== false &&
        ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
        $fechaValidada->format("Y-m-d") === $fecha;

    $horaValida = $horaValidada !== false &&
        ($erroresHora === false || ($erroresHora["warning_count"] === 0 && $erroresHora["error_count"] === 0)) &&
        $horaValidada->format("H:i:s") === $hora;

    if ($doctor_id <= 0 || $especialidadSeleccionada === "" || !$fechaValida || !$horaValida || !in_array($modalidad, ["presencial", "virtual"], true)) {

        $mensaje = "Selecciona un doctor, modalidad, fecha y horario válidos.";
        $tipo_mensaje = "error";

    } elseif (!preg_match('/\A[0-9]{1,8}\.[0-9]{2}\z/', $precioMostrado)) {
        $mensaje = "No se pudo verificar el precio mostrado. Revisa la tarifa y vuelve a seleccionar tu cita.";
        $tipo_mensaje = "error";
    } else {

        require_once dirname(__DIR__) . '/servicios/RegistroCitaService.php';
        $resultadoReserva = registrarCita($conexion, (int)$paciente_id, $doctor_id, $especialidadSeleccionada, $fecha, $hora, $modalidad, $motivo, $opcionesPorClave, $precioMostrado);
        [$mensaje, $tipo_mensaje] = $resultadoReserva;
        if (!isset($resultadoReserva[2])) auditar($conexion,"citas","reservar","fallido");
        if (isset($resultadoReserva[2])) {
            $citaId=(int)$resultadoReserva[2];
            auditar($conexion,"citas","reservar","exitoso","cita",$citaId);
            require_once dirname(__DIR__).'/servicios/PagoService.php';
            try {
                [$aviso,$url]=gestionarPago($conexion,$citaId,0,(int)$usuario_id,'crear');
                if ($url) {header('Location: '.$url,true,303);exit;}
                $_SESSION['mensaje_pago']=$aviso;
            } catch (Throwable $e) {
                auditar($conexion,'pagos','crear_orden','fallido','cita',$citaId);
                error_log('Creación de orden: '.get_class($e).' / '.$e->getCode());
                $_SESSION['mensaje_pago']='Reserva guardada. No se pudo abrir Checkout. Consulta esta misma operación; no crees otra cita.';
            }
            header('Location: pago_cita.php?id='.$citaId,true,303);exit;
        }
    }
}

/* =====================================================
   OBTENER DOCTORES
===================================================== */

$sqlDoctores = "
    SELECT
        id,
        nombres,
        apellidos,
        especialidad,
        hora_inicio,
        hora_fin,
        refrigerio_inicio,
        refrigerio_fin
    FROM doctores
    WHERE activo = 1
    ORDER BY apellidos ASC, nombres ASC
";

$resultDoctores = $conexion->query($sqlDoctores);
$doctores = [];
while ($doctor = $resultDoctores->fetch_assoc()) {
    $doctor["especialidad"] = $opcionesPorClave[claveEspecialidad($doctor["especialidad"])] ?? "";
    if ($doctor["especialidad"] !== "") {
        $doctores[] = $doctor;
    }
}


// Volver a leer las tarifas tras un POST para mostrar el precio vigente.
$tarifas = [];
try {
    $tarifas = listarTarifasCitasDAO($conexion);
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar tarifas de citas: ' . $error->getCode());
}

require dirname(__DIR__) . "/vistas/sacar_cita.php";
