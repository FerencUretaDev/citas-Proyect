<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
include dirname(__DIR__) . "/config/Conexion.php";

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 2) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/repositorios/DashboardPagoDAO.php';
$rol = 2;
$paginaActiva = "reportes";

$fecha_inicio = fechaDashboard($_GET["fecha_inicio"] ?? '');
$fecha_fin = fechaDashboard($_GET["fecha_fin"] ?? '');
$dni = is_string($_GET["dni"] ?? null) ? trim($_GET["dni"]) : "";
$estadoSeleccionado = $_GET["estado"] ?? "";
$etiquetasEstado = [
    "pendiente" => "Pendiente",
    "confirmada" => "Confirmada",
    "atendida" => "Atendida",
    "cancelada" => "Cancelada"
];

if (!is_string($estadoSeleccionado) || !array_key_exists($estadoSeleccionado, $etiquetasEstado)) {
    $estadoSeleccionado = "";
}

/* ==========================================
   CONDICIONES DEL FILTRO
========================================== */

$where = [];
$params = [];
$types = "";

if ($fecha_inicio != "") {
    $where[] = "c.fecha >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
}

if ($fecha_fin != "") {
    $where[] = "c.fecha <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}

if ($dni != "") {
    $where[] = "p.dni LIKE ?";
    $params[] = "%" . $dni . "%";
    $types .= "s";
}

if ($estadoSeleccionado !== "") {
    $where[] = "c.estado = ?";
    $params[] = $estadoSeleccionado;
    $types .= "s";
}

$whereSQL = "";

if (count($where) > 0) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

if (($_GET["exportar"] ?? "") === "excel") {
    $sqlExportar = "
        SELECT
            c.fecha,
            c.hora,
            p.nombres AS paciente_nombres,
            p.apellidos AS paciente_apellidos,
            p.dni,
            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad,
            c.modalidad,
            c.estado,
            c.motivo
        FROM citas c
        INNER JOIN pacientes p ON c.paciente_id = p.id
        INNER JOIN doctores d ON c.doctor_id = d.id
        $whereSQL
        ORDER BY c.fecha DESC, c.hora DESC
    ";

    $stmt = $conexion->prepare($sqlExportar);

    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $resultadoExportar = $stmt->get_result();

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=reporte_citas_" . date("Ymd_His") . ".csv");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    $salida = fopen("php://output", "w");
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ["Fecha", "Hora", "Paciente", "DNI", "Doctor", "Especialidad", "Modalidad", "Estado", "Motivo"], ";", '"', "\\");

    while ($fila = $resultadoExportar->fetch_assoc()) {
        $valores = [
            $fila["fecha"],
            $fila["hora"],
            $fila["paciente_apellidos"] . ", " . $fila["paciente_nombres"],
            $fila["dni"],
            $fila["doctor_apellidos"] . ", " . $fila["doctor_nombres"],
            $fila["especialidad"],
            $fila["modalidad"],
            $fila["estado"],
            $fila["motivo"] ?? ""
        ];

        foreach ($valores as &$valor) {
            $valor = (string)$valor;
            if (preg_match('/^[=+@\\-\t\r]/', $valor)) {
                $valor = "'" . $valor;
            }
        }
        unset($valor);

        fputcsv($salida, $valores, ";", '"', "\\");
    }

    fclose($salida);
    auditar($conexion,"reportes","exportar_citas_csv");
    exit;
}


/* ==========================================
   TOTAL DE CITAS
========================================== */

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
";

$stmt = $conexion->prepare($sqlTotal);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()["total"];


/* ==========================================
   CITAS POR ESTADO
========================================== */

$sqlEstados = "
    SELECT c.estado, COUNT(*) AS cantidad
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
    GROUP BY c.estado
    ORDER BY cantidad DESC
";

$stmt = $conexion->prepare($sqlEstados);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultEstados = $stmt->get_result();

$estados = [
    "atendida" => 0,
    "pendiente" => 0,
    "confirmada" => 0,
    "cancelada" => 0
];

while ($fila = $resultEstados->fetch_assoc()) {
    $estados[$fila["estado"]] = (int)$fila["cantidad"];
}


/* ==========================================
   CITAS POR ESPECIALIDAD
========================================== */

$sqlEspecialidades = "
    SELECT
        d.especialidad,
        COUNT(*) AS total,
        SUM(c.estado = 'atendida') AS atendidas,
        SUM(c.estado = 'pendiente') AS pendientes,
        SUM(c.estado = 'confirmada') AS confirmadas,
        SUM(c.estado = 'cancelada') AS canceladas
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    INNER JOIN doctores d ON c.doctor_id = d.id
    $whereSQL
    GROUP BY d.especialidad
    ORDER BY total DESC
";

$stmt = $conexion->prepare($sqlEspecialidades);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultEspecialidades = $stmt->get_result();

$datosEspecialidades = [];
while ($fila = $resultEspecialidades->fetch_assoc()) {
    $datosEspecialidades[] = $fila;
}


/* ==========================================
   CITAS POR MES
========================================== */

$sqlMes = "
    SELECT
        DATE_FORMAT(c.fecha, '%Y-%m') AS mes,
        COUNT(*) AS total,
        SUM(c.estado = 'atendida') AS atendidas,
        SUM(c.estado = 'pendiente') AS pendientes,
        SUM(c.estado = 'confirmada') AS confirmadas,
        SUM(c.estado = 'cancelada') AS canceladas
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
    GROUP BY DATE_FORMAT(c.fecha, '%Y-%m')
    ORDER BY mes DESC
";

$stmt = $conexion->prepare($sqlMes);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultMes = $stmt->get_result();

$datosMes = [];
while ($fila = $resultMes->fetch_assoc()) {
    $datosMes[] = $fila;
}



$filtrosPago = filtrosDashboardPago($_GET);
$analiticaPago = analiticaDashboardPago($conexion, $filtrosPago);
if (($_GET['exportar'] ?? '') === 'pagos_csv') {
    exportarDashboardPago($conexion, $filtrosPago);
    auditar($conexion,"reportes","exportar_pagos_csv");
    exit;
}
require dirname(__DIR__) . "/vistas/reporte_citas.php";
