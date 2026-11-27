<?php

session_start();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';

header("Cache-Control: no-store, private");

if (!isset($_SESSION["id"], $_SESSION["rol"])) {
    header("Location: index.php");
    exit;
}

// Acceso exclusivo del administrador.
if (!in_array($_SESSION["rol"], [2, "2"], true)) {
    http_response_code(403);
    exit("Acceso reservado al administrador.");
}

require_once dirname(__DIR__) . "/config/Conexion.php";

require_once dirname(__DIR__) .
    "/servicios/AusenciaService.php";

$usuarioId = (int)$_SESSION["id"];
$rol = 2;
$nombreUsuario = $_SESSION["usuario"] ?? "Administrador";

if ($usuarioId <= 0) {
    http_response_code(403);
    exit("Sesion no valida.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Obtener entradas de texto de manera segura.
function entradaAusencia(array $datos, string $campo): string
{
    $valor = $datos[$campo] ?? "";

    if (!is_string($valor)) {
        throw new InvalidArgumentException(
            "Datos ingresados no validos."
        );
    }

    return trim($valor);
}

// Validar identificadores.
function identificadorAusencia(array $datos, string $campo): int
{
    $valor = entradaAusencia($datos, $campo);

    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($id === false) {
        throw new InvalidArgumentException(
            "Identificador no valido."
        );
    }

    return $id;
}

$metodo = $_SERVER["REQUEST_METHOD"];

// Procesar operaciones administrativas.
if ($metodo === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {

        http_response_code(403);
        exit("Solicitud no valida.");
    }

    try {

        $accion = entradaAusencia($_POST, "accion");

        if ($accion === "registrar") {

            $idAuditoria = registrarAusenciaServicio(
                $conexion,
                $rol,
                $usuarioId,
                identificadorAusencia($_POST, "doctor_id"),
                entradaAusencia($_POST, "fecha_inicio"),
                entradaAusencia($_POST, "fecha_fin"),
                entradaAusencia($_POST, "motivo"),
                entradaAusencia($_POST, "observaciones")
            );

            auditar($conexion,"ausencias","registrar","exitoso","ausencia",$idAuditoria);
            $_SESSION["mensaje_ausencia"] = [
                "tipo" => "success",
                "texto" => "Ausencia registrada correctamente."
            ];

        } elseif ($accion === "cancelar") {

            cancelarAusenciaServicio(
                $conexion,
                $rol,
                $usuarioId,
                identificadorAusencia($_POST, "ausencia_id"),
                identificadorAusencia($_POST, "doctor_id")
            );

            auditar($conexion,"ausencias","cancelar","exitoso","ausencia",identificadorAusencia($_POST,"ausencia_id"));
            $_SESSION["mensaje_ausencia"] = [
                "tipo" => "success",
                "texto" => "Ausencia cancelada correctamente."
            ];

        } else {

            throw new InvalidArgumentException(
                "Operacion no valida."
            );
        }

    } catch (InvalidArgumentException | DomainException $e) {

        $_SESSION["mensaje_ausencia"] = [
            "tipo" => "error",
            "texto" => $e->getMessage()
        ];

    } catch (Throwable $e) {

        error_log(
            "Error al gestionar ausencia. Codigo: " .
            $e->getCode()
        );

        $_SESSION["mensaje_ausencia"] = [
            "tipo" => "error",
            "texto" => "No se pudo completar la operacion."
        ];
    }

    // Evitar repetir operaciones al recargar.
    header("Location: ausencias_doctores.php", true, 303);
    exit;
}

if ($metodo !== "GET") {
    header("Allow: GET, POST");
    http_response_code(405);
    exit("Metodo no permitido.");
}

$hoy = ahoraAusencia()->format("Y-m-d");

$mensaje = $_SESSION["mensaje_ausencia"] ?? null;

unset($_SESSION["mensaje_ausencia"]);

$ausenciaSeleccionada = null;
$citasAfectadas = [];

try {

    $doctores = listarDoctoresAusenciasDAO($conexion);

    $ausencias = listarAusenciasDAO($conexion);

    $resumen = [
        "vigente" => 0,
        "programada" => 0,
        "finalizada" => 0,
        "cancelada" => 0,
        "citas_afectadas" => 0
    ];

    foreach ($ausencias as $ausencia) {

        $estado = estadoAusencia($ausencia, $hoy);

        $resumen[$estado]++;

        $resumen["citas_afectadas"] +=
            (int)$ausencia["citas_afectadas"];
    }

    // Consultar citas afectadas de una ausencia.
    if (isset($_GET["ausencia"])) {

        $ausenciaId = identificadorAusencia(
            $_GET,
            "ausencia"
        );

        foreach ($ausencias as $ausencia) {

            if ((int)$ausencia["id"] === $ausenciaId) {
                $ausenciaSeleccionada = $ausencia;
                break;
            }
        }

        if ($ausenciaSeleccionada === null) {
            http_response_code(404);
            exit("Ausencia no encontrada.");
        }

        $citasAfectadas = listarCitasAfectadasDAO(
            $conexion,
            $ausenciaId
        );
    }

} catch (InvalidArgumentException $e) {

    http_response_code(400);
    exit("Solicitud no valida.");

} catch (Throwable $e) {

    error_log(
        "Error al consultar ausencias. Codigo: " .
        $e->getCode()
    );

    http_response_code(500);
    exit("No se pudo consultar las ausencias.");
}

require dirname(__DIR__) .
    "/vistas/ausencias_doctores.php";