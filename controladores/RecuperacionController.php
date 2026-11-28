<?php

if (!defined('ENTRADA_RECUPERACION')) {
    http_response_code(403);
    exit;
}

session_start();

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if (isset($_SESSION['id'])) {
    header('Location: panel', true, 303);
    exit;
}

require_once dirname(__DIR__).'/servicios/RecuperacionService.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if (empty($_SESSION['csrf_recuperacion'])) {
    $_SESSION['csrf_recuperacion'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_recuperacion'];
$mensaje = '';
$exito = false;

if ($metodo === 'GET') {
    if (!empty($_SESSION['solicitud_recuperacion_enviada'])) {
        unset($_SESSION['solicitud_recuperacion_enviada']);
        $mensaje = mensajeSolicitudRecuperacion();
        $exito = true;
    }
}

if ($metodo === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrf, $token)) {

        http_response_code(403);
        $mensaje = 'Solicitud no válida. Recarga el formulario.';

    } else {

        try {

            $permitido = permitirIntentoRegistroPaciente(
                directorioLimiteRecuperacion(),
                (string)($_SERVER['REMOTE_ADDR'] ?? '')
            );

            if (!$permitido) {

                http_response_code(429);
                header('Retry-After: 900');
                $mensaje = 'Demasiados intentos. Inténtalo en 15 minutos.';

            } else {

                require dirname(__DIR__).'/config/Conexion.php';

                procesarSolicitudRecuperacion(
                    $conexion,
                    $_POST['usuario'] ?? null
                );

                $_SESSION['solicitud_recuperacion_enviada'] = true;

                unset($_SESSION['csrf_recuperacion']);

                header('Location: recuperar_password.php', true, 303);
                exit;
            }

        } catch (Throwable $e) {

            http_response_code(503);

            $mensaje = 'Servicio temporalmente no disponible.';

            error_log(
                'Recuperacion: '.get_class($e).' / '.$e->getCode()
            );
        }
    }
}

$vistaRecuperacion = true;

require dirname(__DIR__).'/vistas/recuperar_password.php';