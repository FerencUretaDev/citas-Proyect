<?php

if (!defined('ENTRADA_RECUPERACION_ADMIN')) {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__).'/servicios/AuditoriaService.php';

if (!auditoriaAdmin($_SESSION)) {
    http_response_code(403);
    exit('Acceso reservado al administrador.');
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($metodo, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if (!is_string($_SESSION['csrf_recuperacion_admin'] ?? null)) {
    $_SESSION['csrf_recuperacion_admin'] = bin2hex(random_bytes(32));
}

$csrfAdmin = $_SESSION['csrf_recuperacion_admin'];

require_once dirname(__DIR__).'/repositorios/RecuperacionDAO.php';
require_once dirname(__DIR__).'/repositorios/RecuperacionTokenDAO.php';

try {

    require dirname(__DIR__).'/config/Conexion.php';

    $adminId = (int)$_SESSION['id'];

    $stmt = $conexion->prepare(
        "SELECT id, password FROM usuarios WHERE id=? AND rol=2"
    );

    $stmt->bind_param('i', $adminId);
    $stmt->execute();

    $admin = $stmt->get_result()->fetch_assoc(); $autorizado = $admin !== null;

    $stmt->close();

    if (!$autorizado) {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    if ($metodo === 'POST') {

        $token = $_POST['csrf_token'] ?? null;

        if (!is_string($token) || !hash_equals($csrfAdmin, $token)) {
            http_response_code(403);
            exit('Solicitud no valida.');
        }

        $id = auditoriaId($_POST['solicitud_id'] ?? null);
        $accion = $_POST['accion'] ?? null;

        if ($id === null || !in_array($accion, ['atender','rechazar','generar_codigo'], true)) {
            http_response_code(422);
            exit('Datos no validos.');
        }

        if ($accion === 'generar_codigo') {

            // Limite independiente para la autorizacion administrativa.
            require_once dirname(__DIR__).'/servicios/LimiteRegistroPacienteService.php';

            $directorioLimite = dirname(
                directorioLimiteRegistroPaciente()
            ).'/recuperacion-admin';

            $permitido = permitirIntentoRegistroPaciente(
                $directorioLimite,
                (string)($_SERVER['REMOTE_ADDR'] ?? '')
            );

            if (!$permitido) {
                http_response_code(429);
                header('Retry-After: 900');
                exit('Demasiados intentos. Intentalo en 15 minutos.');
            }

            $identidad = ($_POST['identidad_verificada'] ?? null) === '1';

            $claveAdmin = $_POST['password_admin'] ?? null;

            $claveValida = is_string($claveAdmin)
                && strlen($claveAdmin) <= 72
                && password_verify($claveAdmin, $admin['password']);

            $codigoTemporal = null;

            if ($identidad && $claveValida) {

                $codigoTemporal = generarCodigoRecuperacionDAO(
                    $conexion,
                    $id,
                    $adminId,
                    true
                );
            }

            auditar(
                $conexion,
                'seguridad',
                'generar_codigo_recuperacion',
                $codigoTemporal !== null ? 'exitoso' : 'denegado',
                'solicitud_recuperacion',
                $id
            );

            if ($codigoTemporal === null) {

                $_SESSION['mensaje_recuperacion_admin'] =
                    'No se pudo autorizar la generacion del codigo.';

                header('Location: recuperacion_admin.php', true, 303);
                exit;
            }

            unset($_SESSION['csrf_recuperacion_admin']);

            $vistaCodigoTemporal = true;

            require dirname(__DIR__).'/vistas/recuperacion_codigo.php';

            exit;
        }

        $resultado = gestionarRecuperacionDAO(
            $conexion,
            $id,
            $adminId,
            $accion
        );

        auditar(
            $conexion,
            'seguridad',
            $accion === 'atender'
                ? 'atender_recuperacion'
                : 'rechazar_recuperacion',
            $resultado ? 'exitoso' : 'fallido',
            'solicitud_recuperacion',
            $id
        );

        $_SESSION['mensaje_recuperacion_admin'] = $resultado
            ? 'Solicitud actualizada correctamente.'
            : 'La solicitud ya no admite esta acción.';

        header('Location: recuperacion_admin.php', true, 303);
        exit;
    }

    $mensajeAdmin = $_SESSION['mensaje_recuperacion_admin'] ?? '';
    unset($_SESSION['mensaje_recuperacion_admin']);

    $solicitudes = listarRecuperacionesDAO($conexion);

    $recuperacionesPendientes =
        contarRecuperacionesPendientesDAO($conexion);

} catch (Throwable $e) {

    error_log('Recuperacion admin: '.get_class($e).' / '.$e->getCode());

    http_response_code(503);
    exit('No se pudo completar la operación.');
}

$vistaRecuperacionAdmin = true;

require dirname(__DIR__).'/vistas/recuperacion_admin.php';