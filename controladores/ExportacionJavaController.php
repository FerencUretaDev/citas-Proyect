<?php
session_start();

// Solo administradores
if (!isset($_SESSION["id"]) ||
    !in_array($_SESSION["rol"] ?? null, [2, "2"], true)) {
    http_response_code(403);
    exit("Acceso denegado");
}

session_write_close();

// Identificar el entorno
$windows = PHP_OS_FAMILY === "Windows";

$base = $windows
    ? "C:/xampp/private"
    : "/home/sistemacitasmedicas/private";

$java = $windows
    ? "C:/Program Files/Java/jdk-27.0.1.0.3/bin/java.exe"
    : "/usr/bin/java";

$jar = $base . "/java/modulo-reportes.jar";
$carpeta = $base . "/reportes";

// Leer configuracion privada
try {
    if ($windows) {
        $config = parse_ini_file(
            $base . "/config/reportes.ini",
            false,
            INI_SCANNER_RAW
        );
    } else {
        umask(0077);

        $config = json_decode(
            file_get_contents($base . "/config/database.json"),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    if (
        !is_array($config) ||
        empty($config["DB_USER"]) ||
        !isset($config["DB_PASSWORD"])
    ) {
        throw new RuntimeException("Configuracion invalida.");
    }

    if (
        !$windows &&
        (
            empty($config["DB_HOST"]) ||
            empty($config["DB_NAME"]) ||
            empty($config["DB_PASSWORD"]) ||
            ($config["DB_SSL"] ?? false) !== true
        )
    ) {
        throw new RuntimeException("Configuracion TLS invalida.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    exit("Error de configuracion del reporte.");
}

// Verificar los recursos necesarios
if (
    !function_exists("proc_open") ||
    !is_file($java) ||
    !is_file($jar) ||
    !is_dir($carpeta) ||
    !is_writable($carpeta)
) {
    http_response_code(500);
    exit("No se encontraron los recursos necesarios.");
}

// Recibir y validar filtros
$fechaInicio = $_GET["fecha_inicio"] ?? "";
$fechaFin = $_GET["fecha_fin"] ?? "";
$dni = $_GET["dni"] ?? "";
$estado = $_GET["estado"] ?? "";

foreach ([$fechaInicio, $fechaFin, $dni, $estado] as $valor) {
    if (!is_string($valor)) {
        http_response_code(400);
        exit("Filtro no valido.");
    }
}

$dni = trim($dni);

// Validar fechas
foreach ([$fechaInicio, $fechaFin] as $fecha) {
    if ($fecha !== "") {
        $f = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $fecha
        );

        if (!$f || $f->format("Y-m-d") !== $fecha) {
            http_response_code(400);
            exit("Fecha no valida.");
        }
    }
}

if (
    $fechaInicio !== "" &&
    $fechaFin !== "" &&
    $fechaInicio > $fechaFin
) {
    http_response_code(400);
    exit("El rango de fechas no es valido.");
}

// Validar DNI
if (!preg_match('/\A(?:[0-9]{8})?\z/', $dni)) {
    http_response_code(400);
    exit("DNI no valido.");
}

// Validar estado
$estadosPermitidos = [
    "",
    "pendiente",
    "confirmada",
    "atendida",
    "cancelada"
];

if (!in_array($estado, $estadosPermitidos, true)) {
    http_response_code(400);
    exit("Estado no valido.");
}

// Nombre unico del archivo temporal
$archivo = $carpeta . "/reporte_" .
    bin2hex(random_bytes(12)) . ".xlsx";

// Preparar variables de entorno
$entorno = getenv();

$entorno["DB_HOST"] = $config["DB_HOST"] ?? "localhost";
$entorno["DB_NAME"] = $config["DB_NAME"] ?? "sistema";
$entorno["DB_USER"] = $config["DB_USER"];
$entorno["DB_PASSWORD"] = $config["DB_PASSWORD"];
$entorno["DB_PORT"] = (string) ($config["DB_PORT"] ?? 3306);

if ($windows) {
    unset($entorno["DB_SSL"]);

    $entorno["SystemRoot"] =
        getenv("SystemRoot") ?: "C:\\Windows";

    $entorno["TEMP"] =
        getenv("TEMP") ?: "C:\\Windows\\Temp";

    $entorno["TMP"] =
        getenv("TMP") ?: "C:\\Windows\\Temp";
} else {
    $entorno["DB_SSL"] = "true";
}

// Preparar ejecucion de Java
$comando = [$java];

// Limitar recursos en alwaysdata
if (!$windows) {
    $comando = array_merge($comando, [
        "-Xms16m",
        "-Xmx96m",
        "-XX:ActiveProcessorCount=1"
    ]);
}

$comando = array_merge($comando, [
    "-Djava.io.tmpdir=" . $carpeta,
    "-jar",
    $jar,
    $archivo,
    $fechaInicio,
    $fechaFin,
    $dni,
    $estado
]);

$descriptores = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["redirect", 1]
];

try {
    $proceso = proc_open(
        $comando,
        $descriptores,
        $tuberias,
        $carpeta,
        $entorno
    );

    if (!is_resource($proceso)) {
        throw new RuntimeException("No se pudo iniciar Java.");
    }

    fclose($tuberias[0]);

    $salida = stream_get_contents($tuberias[1]);
    fclose($tuberias[1]);

    $codigo = proc_close($proceso);

    clearstatcache(true, $archivo);

    if (
        $codigo !== 0 ||
        !is_file($archivo) ||
        filesize($archivo) === 0
    ) {
        throw new RuntimeException(
            "Error de Java. Codigo: " . $codigo
        );
    }

    // Descargar archivo Excel
    header(
        "Content-Type: application/vnd.openxmlformats-" .
        "officedocument.spreadsheetml.sheet"
    );

    header(
        'Content-Disposition: attachment; ' .
        'filename="reporte_citas_java.xlsx"'
    );

    header("Content-Length: " . filesize($archivo));
    header("Cache-Control: no-store");
    header("X-Content-Type-Options: nosniff");

    readfile($archivo);

} catch (Throwable $e) {

    error_log("Error en exportacion Java. Codigo: " . $e->getCode());

    if (!headers_sent()) {
        http_response_code(500);
        echo "No se pudo generar el reporte Excel.";
    }

} finally {

    // Eliminar archivo temporal
    if (is_file($archivo)) {
        unlink($archivo);
    }
}

exit;