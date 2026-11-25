<?php

session_start();

header("Cache-Control: no-store, private");

// Verificar inicio de sesion.
if (!isset($_SESSION["id"], $_SESSION["rol"])) {
    header("Location: index.php");
    exit;
}

// Permitir exclusivamente el acceso al paciente.
if (!in_array($_SESSION["rol"], [0, "0"], true)) {
    http_response_code(403);
    exit("Acceso reservado al paciente.");
}

require_once dirname(__DIR__) . "/config/Conexion.php";

require_once dirname(__DIR__) .
    "/middlewares/SesionActiva.php";

require_once dirname(__DIR__) .
    "/servicios/HistorialCitasService.php";

// Verificar que el perfil se encuentre activo.
verificarPerfilActivo($conexion);

$usuarioId = (int) $_SESSION["id"];
$rol = 0;

if ($usuarioId <= 0) {
    http_response_code(403);
    exit("Sesion no valida.");
}

$nombreUsuario = $_SESSION["usuario"] ?? "Paciente";

// Token necesario para cerrar sesion.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Permitir solamente consultas GET.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    exit("Metodo no permitido.");
}

try {

    $resultado = consultarHistorialPaciente(
        $conexion,
        $usuarioId,
        $_GET
    );

    $citas = $resultado["citas"];
    $filtros = $resultado["filtros"];
    $especialidades = $resultado["especialidades"];
    $resumen = $resultado["resumen"];

} catch (InvalidArgumentException $e) {

    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    exit($e->getMessage());

} catch (Throwable $e) {

    error_log(
        "Error al consultar historial. Codigo: " .
        $e->getCode()
    );

    http_response_code(500);
    exit("No se pudo consultar el historial de citas.");
}

// Cargar la vista.
require dirname(__DIR__) .
    "/vistas/historial_citas.php";