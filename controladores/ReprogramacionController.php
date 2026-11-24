<?php
session_start();
include dirname(__DIR__) . "/config/Conexion.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || (int)$_SESSION["rol"] !== 2) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$citaId = (int)($_GET["id"] ?? $_POST["cita_id"] ?? 0);
$origenSolicitado = $_GET["origen"] ?? $_POST["origen"] ?? "";
$destinosRetorno = ["reporte" => "reporte_citas", "citas" => "citas_admin"];
$origen = isset($destinosRetorno[$origenSolicitado]) ? $origenSolicitado : "panel";
$destinoRetorno = $destinosRetorno[$origen] ?? "panel";
$mensaje = "";
$tipoMensaje = "error";
$reprogramada = false;
$fechaConsulta = "";
$horaSeleccionada = $_POST["hora"] ?? "";
$horariosDisponibles = [];

if ($citaId <= 0) {
    http_response_code(400);
    exit("La cita seleccionada no es válida.");
}

function obtenerHorariosReprogramacion($conexion, $citaId, $doctorId, $pacienteId, $fecha, $doctor)
{
    $horariosOcupados = [];
    $sqlOcupadas = "
        SELECT hora
        FROM citas
        WHERE id <> ?
          AND fecha = ?
          AND estado != 'cancelada'
          AND (doctor_id = ? OR paciente_id = ?)
    ";
    $stmtOcupadas = $conexion->prepare($sqlOcupadas);
    $stmtOcupadas->bind_param("isii", $citaId, $fecha, $doctorId, $pacienteId);
    $stmtOcupadas->execute();
    $resultadoOcupadas = $stmtOcupadas->get_result();
    while ($filaOcupada = $resultadoOcupadas->fetch_assoc()) {
        $horariosOcupados[] = new DateTime($filaOcupada["hora"]);
    }
    $resultadoOcupadas->free();
    $stmtOcupadas->close();

    $inicio = new DateTime($doctor["hora_inicio"]);
    $fin = new DateTime($doctor["hora_fin"]);
    $ultimoInicio = clone $fin;
    $ultimoInicio->modify("-30 minutes");
    $horarios = [];

    while ($inicio <= $ultimoInicio) {
        $finCita = clone $inicio;
        $finCita->modify("+30 minutes");
        $noDisponible = false;

        if (!empty($doctor["refrigerio_inicio"]) && !empty($doctor["refrigerio_fin"])) {
            $inicioRefrigerio = new DateTime($doctor["refrigerio_inicio"]);
            $finRefrigerio = new DateTime($doctor["refrigerio_fin"]);
            $noDisponible = $inicio < $finRefrigerio && $finCita > $inicioRefrigerio;
        }

        foreach ($horariosOcupados as $horaOcupada) {
            $finOcupada = clone $horaOcupada;
            $finOcupada->modify("+30 minutes");
            if ($inicio < $finOcupada && $finCita > $horaOcupada) {
                $noDisponible = true;
                break;
            }
        }

        $hora = $inicio->format("H:i:s");
        if ($fecha === date("Y-m-d") && $hora <= date("H:i:s")) {
            $noDisponible = true;
        }

        if (!$noDisponible) {
            $horarios[] = $hora;
        }

        $inicio->modify("+30 minutes");
    }

    return $horarios;
}

$sqlCita = "
    SELECT c.id, c.paciente_id, c.doctor_id, c.fecha, c.hora, c.modalidad, c.estado,
           p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
           d.nombres AS doctor_nombres, d.apellidos AS doctor_apellidos,
           d.especialidad, d.hora_inicio, d.hora_fin, d.refrigerio_inicio, d.refrigerio_fin
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN doctores d ON d.id = c.doctor_id
    WHERE c.id = ?
";
$stmtCita = $conexion->prepare($sqlCita);
$stmtCita->bind_param("i", $citaId);
$stmtCita->execute();
$cita = $stmtCita->get_result()->fetch_assoc();
$stmtCita->close();

if (!$cita) {
    http_response_code(404);
    exit("Cita no encontrada.");
}

if (!in_array($cita["estado"], ["pendiente", "confirmada"], true)) {
    http_response_code(409);
    exit("Solo se pueden reprogramar citas pendientes o confirmadas.");
}

$stmtOpciones = $conexion->prepare("SELECT id, nombres, apellidos, hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin
    FROM doctores WHERE activo=1 AND especialidad=? ORDER BY apellidos, nombres");
$stmtOpciones->bind_param("s", $cita["especialidad"]);
$stmtOpciones->execute();
$opciones = array_column($stmtOpciones->get_result()->fetch_all(MYSQLI_ASSOC), null, "id");
$doctorId = filter_var($_POST["doctor_id"] ?? $_GET["doctor_id"] ?? $cita["doctor_id"], FILTER_VALIDATE_INT);
$doctorElegido = $opciones[$doctorId] ?? null;

$fechaConsulta = max(date("Y-m-d"), $cita["fecha"]);
$fechaSolicitada = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["fecha"] ?? "")
    : ($_GET["fecha"] ?? $fechaConsulta);
$fechaValidada = DateTime::createFromFormat("!Y-m-d", $fechaSolicitada);
$erroresFecha = DateTime::getLastErrors();
$fechaValida = $fechaValidada !== false &&
    ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
    $fechaValidada->format("Y-m-d") === $fechaSolicitada &&
    $fechaSolicitada >= date("Y-m-d");

if (!$fechaValida) {
    $mensaje = "Selecciona una fecha válida, igual o posterior a hoy.";
    $fechaConsulta = date("Y-m-d");
} else {
    $fechaConsulta = $fechaSolicitada;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    $hora = $_POST["hora"] ?? "";
    $horaValidada = DateTime::createFromFormat("!H:i:s", $hora);
    $erroresHora = DateTime::getLastErrors();
    $horaValida = $horaValidada !== false &&
        ($erroresHora === false || ($erroresHora["warning_count"] === 0 && $erroresHora["error_count"] === 0)) &&
        $horaValidada->format("H:i:s") === $hora;

    if (!$doctorElegido || !$fechaValida || !$horaValida) {
        $mensaje = "Selecciona una fecha y un horario válidos.";
    } else {
        try {
            $conexion->begin_transaction();

            $pacienteId = (int)$cita["paciente_id"];

            $stmtDoctor = $conexion->prepare("SELECT hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin FROM doctores WHERE activo = 1 AND id = ? AND especialidad = ? FOR UPDATE");
            $stmtDoctor->bind_param("is", $doctorId, $cita["especialidad"]);
            $stmtDoctor->execute();
            $resultadoDoctor = $stmtDoctor->get_result();
            $doctorBloqueado = $resultadoDoctor->fetch_assoc();
            $resultadoDoctor->free();
            $stmtDoctor->close();

            $stmtPaciente = $conexion->prepare("SELECT id FROM pacientes WHERE activo = 1 AND id = ? FOR UPDATE");
            $stmtPaciente->bind_param("i", $pacienteId);
            $stmtPaciente->execute();
            $resultadoPaciente = $stmtPaciente->get_result();
            $pacienteBloqueado = $resultadoPaciente->fetch_assoc();
            $resultadoPaciente->free();
            $stmtPaciente->close();

            $stmtBloqueoCita = $conexion->prepare("SELECT paciente_id, doctor_id, estado FROM citas WHERE id = ? FOR UPDATE");
            $stmtBloqueoCita->bind_param("i", $citaId);
            $stmtBloqueoCita->execute();
            $resultadoBloqueoCita = $stmtBloqueoCita->get_result();
            $citaBloqueada = $resultadoBloqueoCita->fetch_assoc();
            $resultadoBloqueoCita->free();
            $stmtBloqueoCita->close();

            if (
                !$doctorBloqueado ||
                !$pacienteBloqueado ||
                !$citaBloqueada ||
                (int)$citaBloqueada["doctor_id"] !== (int)$cita["doctor_id"] ||
                (int)$citaBloqueada["paciente_id"] !== $pacienteId ||
                !in_array($citaBloqueada["estado"], ["pendiente", "confirmada"], true)
            ) {
                throw new RuntimeException("No se pudo validar el doctor o el paciente de la cita.");
            }

            $horariosDisponibles = obtenerHorariosReprogramacion($conexion, $citaId, $doctorId, $pacienteId, $fechaConsulta, $doctorBloqueado);
            if ($doctorId === (int)$cita["doctor_id"] && $fechaConsulta === $cita["fecha"] && $hora === $cita["hora"]) {
                throw new RuntimeException("Selecciona una fecha u horario distinto al actual.");
            }
            if (!in_array($hora, $horariosDisponibles, true)) {
                throw new RuntimeException("Ese horario ya no está disponible. Selecciona otro.");
            }

            $stmtActualizar = $conexion->prepare("UPDATE citas SET doctor_id = ?, fecha = ?, hora = ? WHERE id = ? AND estado IN ('pendiente', 'confirmada')");
            $stmtActualizar->bind_param("issi", $doctorId, $fechaConsulta, $hora, $citaId);
            $stmtActualizar->execute();
            $stmtActualizar->close();
            $conexion->commit();

            $cita["doctor_nombres"] = $doctorElegido["nombres"];
            $cita["doctor_apellidos"] = $doctorElegido["apellidos"];
            $cita["fecha"] = $fechaConsulta;
            $cita["hora"] = $hora;
            $horaSeleccionada = $hora;
                $mensaje = "Cita reprogramada para el " . date("d/m/Y", strtotime($fechaConsulta)) . " a las " . substr($hora, 0, 5) . ".";
            $tipoMensaje = "success";
            $reprogramada = true;
        } catch (Throwable $error) {
            $conexion->rollback();
            $mensaje = $error instanceof RuntimeException
                ? $error->getMessage()
                : "No se pudo reprogramar la cita. Inténtalo de nuevo.";
            if (!$error instanceof RuntimeException) {
                error_log("Error al reprogramar cita: " . $error->getMessage());
            }
        }
    }
}

if ($doctorElegido && $fechaValida && !$reprogramada && !$horariosDisponibles) {
    $horariosDisponibles = obtenerHorariosReprogramacion(
        $conexion,
        $citaId,
        $doctorId,
        (int)$cita["paciente_id"],
        $fechaConsulta,
        $doctorElegido
    );
}

require dirname(__DIR__) . "/vistas/reprogramar_cita.php";
