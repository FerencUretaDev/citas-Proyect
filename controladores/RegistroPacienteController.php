<?php
session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
if (isset($_SESSION['id'])) { header('Location: panel', true, 303); exit; }
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metodo, ['GET','POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
require_once dirname(__DIR__).'/servicios/RegistroPacienteService.php';
require_once dirname(__DIR__).'/servicios/LimiteRegistroPacienteService.php';
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
if (!is_string($_SESSION['csrf_registro_paciente'] ?? null)) $_SESSION['csrf_registro_paciente'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_registro_paciente'];
$mensaje = '';
$usuarioCreado = null;
$datos = [];
$campos = ['dni','nombres','primer_apellido','segundo_apellido','fecha_nacimiento','sexo','telefono','correo','direccion','acepta_privacidad'];
if ($metodo === 'GET') {
    $usuarioCreado = $_SESSION['registro_paciente_exitoso'] ?? null;
    unset($_SESSION['registro_paciente_exitoso']);
} else {
    foreach ($campos as $campo) {
        $valor = $_POST[$campo] ?? '';
        $datos[$campo] = is_string($valor) && strlen($valor) <= 800 ? $valor : '';
    }
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($csrf, $token)) {
        http_response_code(403); $mensaje = 'Solicitud no válida. Recarga el formulario e inténtalo de nuevo.';
    } else {
        $conexion = null;
        try {
            if (!permitirIntentoRegistroPaciente(directorioLimiteRegistroPaciente(), (string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
                http_response_code(429); header('Retry-After: 900'); $mensaje = 'Demasiados intentos. Inténtalo nuevamente en 15 minutos.';
            } else {
                require dirname(__DIR__).'/config/Conexion.php';
                $resultado = registrarPacientePublico($conexion, $_POST);
                auditar($conexion, 'pacientes', 'registrar', 'exitoso', 'paciente', $resultado['paciente_id'], []);
                session_regenerate_id(true);
                unset($_SESSION['csrf_registro_paciente']);
                $_SESSION['registro_paciente_exitoso'] = $resultado['usuario'];
                header('Location: registro_paciente.php', true, 303); exit;
            }
        } catch (DomainException $e) {
            http_response_code(422); $mensaje = $e->getMessage();
            if ($conexion instanceof mysqli) auditar($conexion, 'pacientes', 'registrar', 'fallido', null, null, []);
        } catch (Throwable $e) {
            http_response_code(503); $mensaje = 'No se pudo completar el registro. Inténtalo más tarde.';
            error_log('Autorregistro: '.get_class($e).' / '.$e->getCode());
            if ($conexion instanceof mysqli) auditar($conexion, 'pacientes', 'registrar', 'fallido', null, null, []);
        }
    }
}
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
$vistaRegistroPaciente = true;
require dirname(__DIR__).'/vistas/registro_paciente.php';
