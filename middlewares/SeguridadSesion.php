<?php

function versionSesionValida(
    mysqli $conexion,
    array $sesion
): bool {

    $id = $sesion['id'] ?? null;
    $rol = $sesion['rol'] ?? null;
    $version = $sesion['version_sesion'] ?? null;

    if (
        (!is_int($id) && !is_string($id))
        || !in_array($rol, [0,1,2,'0','1','2'], true)
        || (!is_int($version) && !is_string($version))
    ) {
        return false;
    }

    $id = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        return false;
    }

    $stmt = $conexion->prepare("
        SELECT rol, version_sesion
        FROM usuarios
        WHERE id=?
    ");

    try {

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'No se pudo verificar la sesion.'
            );
        }

        $cuenta = $stmt->get_result()->fetch_assoc();

        return $cuenta !== null
            && (int)$cuenta['rol'] === (int)$rol
            && (string)$cuenta['version_sesion'] === (string)$version;

    } finally {
        $stmt->close();
    }
}


function verificarVersionSesion(
    mysqli $conexion,
    bool $json = false
): void {

    if (!isset($_SESSION['id'])) {
        return;
    }

    try {

        if (versionSesionValida($conexion, $_SESSION)) {
            return;
        }

    } catch (Throwable $e) {

        error_log(
            'SeguridadSesion: '.get_class($e).' / '.$e->getCode()
        );

        http_response_code(503);
        header('Cache-Control: no-store');

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'error' => 'Servicio temporalmente no disponible.'
            ]));
        }

        exit('Servicio temporalmente no disponible.');
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    header('Cache-Control: no-store');

    http_response_code(401);

    $mensaje = 'Tu sesion ha finalizado. Inicia sesion nuevamente.';

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => $mensaje]));
    }

    exit($mensaje);
}

// MEJORA 03 - Inicio centralizado de sesiones protegidas.
function iniciarSesionSegura(bool $json = false): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {

        if (!session_start()) {
            http_response_code(503);
            exit('No se pudo iniciar la sesion.');
        }
    }

    if (!isset($_SESSION['id'])) {
        return;
    }

    $json = $json || str_contains(
        (string)($_SERVER['HTTP_ACCEPT'] ?? ''),
        'application/json'
    );

    require_once dirname(__DIR__).'/servicios/AuditoriaService.php';

    $dbSesion = auditoriaConexionOpcional();

    if (!($dbSesion instanceof mysqli)) {

        http_response_code(503);
        header('Cache-Control: no-store');

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'error' => 'Servicio temporalmente no disponible.'
            ]));
        }

        exit('Servicio temporalmente no disponible.');
    }

    try {

        verificarVersionSesion($dbSesion, $json);

    } finally {

        $dbSesion->close();
    }
}
