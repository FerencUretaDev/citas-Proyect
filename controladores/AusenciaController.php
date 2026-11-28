<?php

require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';

header("Cache-Control: no-store, private");
header("Referrer-Policy: no-referrer");

if (!isset($_SESSION["id"], $_SESSION["rol"])) {
    if ($dbAud = auditoriaConexionOpcional()) auditar($dbAud,"ausencias","acceder","denegado");
    header("Location: index.php");
    exit;
}

// Acceso exclusivo del administrador.
if (!auditoriaAdmin($_SESSION)) {
    if ($dbAud = auditoriaConexionOpcional()) auditar($dbAud,"ausencias","acceder","denegado");
    http_response_code(403);
    exit("Acceso reservado al administrador.");
}

require dirname(__DIR__) . "/config/Conexion.php";

require_once dirname(__DIR__) .
    "/servicios/AusenciaService.php";
require_once dirname(__DIR__)."/repositorios/FiltroAusenciasDAO.php";

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

$metodo = $_SERVER["REQUEST_METHOD"] ?? "";
$esBusqueda = $metodo === "POST" && ($_POST["accion"] ?? null) === "buscar_ausencias";

// Procesar operaciones administrativas.
if ($metodo === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {

        if ($esBusqueda) auditar($conexion,"ausencias","consultar","denegado");
        http_response_code(403);
        exit("Solicitud no valida.");
    }

    if (!$esBusqueda) {
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
}

if ($metodo !== "GET" && !$esBusqueda) {
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

    $filtros = filtrosAusencias($esBusqueda ? $_POST : []);
    $especialidadesFiltro = especialidadesDirectorio($conexion);
    if ($filtros['especialidad']!=='' && !in_array($filtros['especialidad'],$especialidadesFiltro,true)) throw new DomainException('Especialidad no válida.');
    $listado = buscarAusenciasFiltradas($conexion,$filtros,$hoy);
    $ausencias = $listado['filas'];
    $resumen = resumenAusenciasFiltradas($conexion,$hoy);

    // Consultar por ID independiente de la página actual del listado.
    if (isset($_GET["ausencia"])) {
        $ausenciaId = identificadorAusencia($_GET,"ausencia");
        $ausenciaSeleccionada = detalleAusenciaFiltrada($conexion,$ausenciaId);
        if ($ausenciaSeleccionada === null) {
            auditar($conexion,"ausencias","consultar","fallido");
            http_response_code(404);
            exit("Ausencia no encontrada.");
        }

        $citasAfectadas = listarCitasAfectadasDAO(
            $conexion,
            $ausenciaId
        );
    }

    auditar($conexion,"ausencias","consultar");

} catch (InvalidArgumentException | DomainException $e) {

    auditar($conexion,"ausencias","consultar","fallido");
    http_response_code(400);
    exit("Solicitud no valida.");

} catch (Throwable $e) {

    auditar($conexion,"ausencias","consultar","fallido");
    error_log(
        "Error al consultar ausencias. Codigo: " .
        $e->getCode()
    );

    http_response_code(500);
    exit("No se pudo consultar las ausencias.");
}

require dirname(__DIR__) .
    "/vistas/ausencias_doctores.php";