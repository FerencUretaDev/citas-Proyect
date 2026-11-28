<?php

if (!defined('ENTRADA_RESTABLECER_PASSWORD')) {
    http_response_code(403);
    exit;
}

if (!session_start()) {
    http_response_code(503);
    exit('No se pudo iniciar la sesion.');
}

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if (isset($_SESSION['id'])) {
    header('Location: panel', true, 303);
    exit;
}

require_once dirname(__DIR__).'/servicios/RestablecerPasswordService.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($metodo, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if (!is_string($_SESSION['csrf_restablecer'] ?? null)) {
    $_SESSION['csrf_restablecer'] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_restablecer'];

$mensaje = '';
$exito = false;

if ($metodo === 'GET'
    && !empty($_SESSION['password_restablecido'])) {

    unset($_SESSION['password_restablecido']);

    $mensaje = 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.';

    $exito = true;
}

if ($metodo === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token)
        || !hash_equals($csrf, $token)) {

        http_response_code(403);

        $mensaje = 'Solicitud no válida. Recarga el formulario.';

    } else {

        try {

            $directorio = dirname(
                directorioLimiteRegistroPaciente()
            ).'/restablecer-password';

            $permitido = permitirIntentoRegistroPaciente(
                $directorio,
                (string)($_SERVER['REMOTE_ADDR'] ?? '')
            );

            if (!$permitido) {

                http_response_code(429);
                header('Retry-After: 900');

                $mensaje = 'Demasiados intentos. Inténtalo en 15 minutos.';

            } else {

                require dirname(__DIR__).'/config/Conexion.php';

                $error = procesarRestablecimiento(
                    $conexion,
                    $_POST
                );

                if ($error === null) {

                    unset($_SESSION['csrf_restablecer']);

                    $_SESSION['password_restablecido'] = true;

                    header(
                        'Location: restablecer_password.php',
                        true,
                        303
                    );

                    exit;
                }

                http_response_code(422);
                $mensaje = $error;
            }

        } catch (Throwable $e) {

            http_response_code(503);

            $mensaje = 'Servicio temporalmente no disponible.';

            error_log(
                'Restablecimiento: '.get_class($e)
                .' / '.$e->getCode()
            );
        }
    }
}

$vistaRestablecer = true;

require dirname(__DIR__).'/vistas/restablecer_password.php';