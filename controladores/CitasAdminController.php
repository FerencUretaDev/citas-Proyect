<?php
session_start();
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/servicios/CitaService.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || (int)$_SESSION["rol"] !== 2) {
    header("Location: index.php");
    exit;
}

$rol = 2;
$paginaActiva = "citas";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$etiquetasEstado = ETIQUETAS_ESTADO_CITA;
$transiciones = TRANSICIONES_ESTADO_CITA;
$estadoSeleccionado = $_GET["estado"] ?? "";
if (!is_string($estadoSeleccionado) || !array_key_exists($estadoSeleccionado, $etiquetasEstado)) {
    $estadoSeleccionado = "";
}

$sqlCitas = "
    SELECT c.id, (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado, c.fecha, c.hora, c.modalidad, c.motivo, c.estado,
           p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos, p.dni,
           d.nombres AS doctor_nombres, d.apellidos AS doctor_apellidos, d.especialidad
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN doctores d ON d.id = c.doctor_id
";

$tipoFicha = $_GET['tipo'] ?? '';
$idFicha = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$filtrarFicha = $idFicha > 0 && in_array($tipoFicha, ['paciente', 'doctor'], true);
$condiciones = [];
$parametros = [];
if ($filtrarFicha) {
    $columna = $tipoFicha === 'paciente' ? 'c.paciente_id' : 'c.doctor_id';
    $condiciones[] = "$columna = ? AND TIMESTAMP(c.fecha,c.hora) >= ? AND c.estado IN ('pendiente','confirmada')";
    $parametros[] = $idFicha;
    $parametros[] = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}
if ($estadoSeleccionado !== "") {
    $condiciones[] = 'c.estado = ?';
    $parametros[] = $estadoSeleccionado;
}
if ($condiciones) {
    $sqlCitas .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sqlCitas .= " ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";
$stmtCitas = $conexion->prepare($sqlCitas);
$stmtCitas->execute($parametros);
$resultadoCitas = $stmtCitas->get_result();
$totalVisible = $resultadoCitas->num_rows;

require dirname(__DIR__) . "/vistas/citas_admin.php";
