<?php

session_start();
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/middlewares/SesionActiva.php";
verificarPerfilActivo($conexion);
require_once dirname(__DIR__) . "/servicios/CitaService.php";

if (
    !isset($_SESSION["id"]) ||
    !isset($_SESSION["rol"]) ||
    !in_array($_SESSION["rol"], [0, 1, 2, "0", "1", "2"], true)
) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

$tokenRecibido = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($tokenRecibido) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $tokenRecibido
    )
) {
    http_response_code(403);
    exit("Solicitud no válida.");
}

$rol = (int)$_SESSION["rol"];
$usuario_id = (int)$_SESSION["id"];
$origen = $_POST["origen"] ?? "";
$destinos = ["reporte" => "reporte_citas", "citas" => "citas_admin"];
$destino = $rol === 2 && isset($destinos[$origen]) ? $destinos[$origen] : "panel";

    $cita_id = (int)($_POST["cita_id"] ?? 0);
    $estado = $_POST["estado"] ?? "";

    $estadosPermitidos = array_keys(ETIQUETAS_ESTADO_CITA);

    if ($cita_id <= 0 || !in_array($estado, $estadosPermitidos, true)) {
        http_response_code(400);
        die("La cita o el estado no son válidos.");
    }

    // Cancelación exclusiva del paciente
if ($rol === 0) {

    if ($estado !== "cancelada") {
        http_response_code(403);
        exit("Operación no autorizada.");
    }

    try {

        $cancelada = cancelarCitaPaciente(
            $conexion,
            $cita_id,
            $usuario_id
        );

        if (!$cancelada) {
            http_response_code(409);
            exit(
                "La cita no está disponible para cancelación."
            );
        }

        $_SESSION["mensaje_cita"] =
            "Tu cita fue cancelada correctamente.";

        header("Location: panel");
        exit;

    } catch (Throwable $e) {

        error_log(
            "Error al cancelar cita del paciente."
        );

        http_response_code(500);
        exit("No se pudo cancelar la cita.");
    }
}

    $doctor_id = null;

    if ($rol === 1) {
        $sqlDoctor = "SELECT id FROM doctores WHERE usuario_id = ? LIMIT 1";
        $stmtDoctor = $conexion->prepare($sqlDoctor);
        $stmtDoctor->bind_param("i", $usuario_id);
        $stmtDoctor->execute();
        $resultadoDoctor = $stmtDoctor->get_result();
        $doctor = $resultadoDoctor->fetch_assoc();
        $resultadoDoctor->free();
        $stmtDoctor->close();

        if (!$doctor) {
            http_response_code(403);
            die("Doctor no encontrado.");
        }

        $doctor_id = (int)$doctor["id"];
    }

    $conexion->begin_transaction();

    if ($rol === 2) {
        $sqlEstadoActual = "SELECT estado FROM citas WHERE id = ? FOR UPDATE";
        $stmtEstadoActual = $conexion->prepare($sqlEstadoActual);
        $stmtEstadoActual->bind_param("i", $cita_id);
    } else {
        $sqlEstadoActual = "SELECT estado FROM citas WHERE id = ? AND doctor_id = ? FOR UPDATE";
        $stmtEstadoActual = $conexion->prepare($sqlEstadoActual);
        $stmtEstadoActual->bind_param("ii", $cita_id, $doctor_id);
    }

    $stmtEstadoActual->execute();
    $resultadoEstadoActual = $stmtEstadoActual->get_result();

    if ($resultadoEstadoActual->num_rows === 0) {
        $resultadoEstadoActual->free();
        $stmtEstadoActual->close();
        $conexion->rollback();
        http_response_code(404);
        die("Cita no encontrada.");
    }

    $estadoActual = $resultadoEstadoActual->fetch_assoc()["estado"];
    $resultadoEstadoActual->free();
    $stmtEstadoActual->close();
    $transiciones = TRANSICIONES_ESTADO_CITA;

    if ($estadoActual !== $estado && !in_array($estado, $transiciones[$estadoActual], true)) {
        $conexion->rollback();
        http_response_code(400);
        die("La transición de estado no está permitida.");
    }

    require_once dirname(__DIR__).'/repositorios/PagoDAO.php';
    $pagoAsociado=pagoCitaDAO($conexion,$cita_id,true);
    if ($pagoAsociado && $estadoActual !== $estado && ($estado === 'cancelada' || $pagoAsociado['estado'] !== 'aprobado')) {
        $conexion->rollback(); http_response_code(409); exit('Gestiona primero el pago desde el panel. Las citas pagadas requieren revisión antes de cancelar.');
    }
    if ($estadoActual === $estado) {
        $conexion->commit();
        header("Location: " . $destino);
        exit;
    }


    if ($rol === 2) {
        $sql = "UPDATE citas SET estado = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("si", $estado, $cita_id);
    } else {
        $sql = "UPDATE citas SET estado = ? WHERE id = ? AND doctor_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sii", $estado, $cita_id, $doctor_id);
    }

    if ($stmt->execute()) {

        $conexion->commit();
        header("Location: " . $destino);
        exit;

    } else {

        $conexion->rollback();
        echo "Error al actualizar la cita: " . $stmt->error;
    }
?>