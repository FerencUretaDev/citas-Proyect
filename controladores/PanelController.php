<?php
session_start();
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/middlewares/SesionActiva.php";
verificarPerfilActivo($conexion);
require_once dirname(__DIR__) . "/servicios/CitaService.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"])) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$usuario_id = (int)$_SESSION["id"];
$rol = (int)$_SESSION["rol"];
$usuario = $_SESSION["usuario"];
$transicionesEstadoCita = TRANSICIONES_ESTADO_CITA;
$etiquetasEstadoCita = ETIQUETAS_ESTADO_CITA;

/* =====================================================
   VARIABLES GENERALES
===================================================== */

$nombreUsuario = $usuario;

$totalCitas = 0;
$atendidas = 0;
$pendientes = 0;
$confirmadas = 0;
$canceladas = 0;

$citas = [];
$datosEspecialidades = [];
$pacientesRegistrados = 0;
$doctoresRegistrados = 0;
$citasDeHoy = 0;
$citasProximosSieteDias = 0;


/* =====================================================
   ADMINISTRADOR
   ROL = 2
===================================================== */

if ($rol == 2) {

    $sqlResumen = "
        SELECT
            (SELECT COUNT(*) FROM pacientes) AS pacientes,
            (SELECT COUNT(*) FROM doctores) AS doctores,
            (SELECT COUNT(*) FROM citas) AS total_citas,
            (
                SELECT COUNT(*)
                FROM citas
                WHERE fecha = CURDATE()
                    AND estado <> 'cancelada'
            ) AS citas_hoy,
            (
                SELECT COUNT(*)
                FROM citas
                WHERE fecha > CURDATE()
                    AND fecha <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND estado IN ('pendiente', 'confirmada')
            ) AS citas_proximos_siete_dias
    ";

    $resumenAdmin = $conexion->query($sqlResumen)->fetch_assoc();
    $pacientesRegistrados = (int)$resumenAdmin["pacientes"];
    $doctoresRegistrados = (int)$resumenAdmin["doctores"];
    $totalCitas = (int)$resumenAdmin["total_citas"];
    $citasDeHoy = (int)$resumenAdmin["citas_hoy"];
    $citasProximosSieteDias = (int)$resumenAdmin["citas_proximos_siete_dias"];

    // Estados
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        GROUP BY estado
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }

    // Especialidades
    $sql = "
        SELECT
            d.especialidad,
            COUNT(*) AS total
        FROM citas c
        INNER JOIN doctores d
            ON c.doctor_id = d.id
        GROUP BY d.especialidad
        ORDER BY total DESC
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $datosEspecialidades[] = $fila;
    }

    // Últimas citas
    $sql = "
        SELECT
            c.id,
            (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado,
            c.fecha,
            c.hora,
            c.modalidad,
            c.estado,
            c.motivo,

            p.dni,
            p.nombres AS paciente_nombres,
            p.apellidos AS paciente_apellidos,

            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad

        FROM citas c

        INNER JOIN pacientes p
            ON c.paciente_id = p.id

        INNER JOIN doctores d
            ON c.doctor_id = d.id

        ORDER BY c.fecha DESC, c.hora DESC, c.id DESC
        LIMIT 12
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}


/* =====================================================
   DOCTOR
   ROL = 1
===================================================== */

if ($rol == 1) {

    $sql = "
        SELECT
            id,
            nombres,
            apellidos,
            especialidad,
            cmp,
            hora_inicio,
            hora_fin,
            refrigerio_inicio,
            refrigerio_fin
        FROM doctores
        WHERE usuario_id = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        die("No se encontró el perfil del doctor.");
    }

    $doctor_id = (int)$doctor["id"];

    $nombreUsuario = $doctor["nombres"] . " " . $doctor["apellidos"];

    // Total de citas del doctor
    $sql = "
        SELECT COUNT(*) AS total
        FROM citas
        WHERE doctor_id = ?
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $totalCitas = (int)$stmt->get_result()->fetch_assoc()["total"];


    // Estados del doctor
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        WHERE doctor_id = ?
        GROUP BY estado
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }


    // Citas del doctor
    $sql = "
        SELECT
            c.id,
            (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado,
            (SELECT h.id FROM historias_clinicas h WHERE h.cita_id=c.id) AS historia_id,
            c.fecha,
            c.hora,
            c.modalidad,
            c.estado,
            c.motivo,

            p.dni,
            p.nombres,
            p.apellidos

        FROM citas c

        INNER JOIN pacientes p
            ON c.paciente_id = p.id

        WHERE c.doctor_id = ?

        ORDER BY c.fecha ASC, c.hora ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}


/* =====================================================
   PACIENTE
   ROL = 0
===================================================== */

if ($rol == 0) {

    $sql = "
        SELECT
            id,
            nombres,
            apellidos,
            dni
        FROM pacientes
        WHERE usuario_id = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    $paciente = $stmt->get_result()->fetch_assoc();

    if (!$paciente) {
        die("No se encontró el perfil del paciente.");
    }

    $paciente_id = (int)$paciente["id"];

    $nombreUsuario =
        $paciente["nombres"] . " " .
        $paciente["apellidos"];


    // Total de citas
    $sql = "
        SELECT COUNT(*) AS total
        FROM citas
        WHERE paciente_id = ?
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $totalCitas = (int)$stmt->get_result()->fetch_assoc()["total"];


    // Estados
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        WHERE paciente_id = ?
        GROUP BY estado
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }


    // Citas del paciente
    $sql = "
        SELECT
            c.id,
            (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado,
            (SELECT h.id FROM historias_clinicas h WHERE h.cita_id=c.id) AS historia_id,
            c.fecha,
            c.hora,
            c.modalidad,
            c.estado,
            c.motivo,

            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad

        FROM citas c

        INNER JOIN doctores d
            ON c.doctor_id = d.id

        WHERE c.paciente_id = ?

        ORDER BY c.fecha DESC, c.hora DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}


require dirname(__DIR__) . "/vistas/panel.php";
