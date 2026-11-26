<?php
session_start();

date_default_timezone_set('America/Lima');

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método no permitido.');
}

$rolSesion = $_SESSION['rol'] ?? null;
$usuarioId = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($usuarioId === false || !in_array($rolSesion, [0, 2, '0', '2'], true)) {
    if ($metodo === 'GET' && !isset($_SESSION['id'])) {
        header('Location: index.php');
        exit;
    }
    http_response_code(403);
    exit('Acceso denegado.');
}
$rol = (int)$rolSesion;

if ($metodo === 'POST') {
    $tokenSesion = $_SESSION['csrf_token'] ?? null;
    $tokenRecibido = $_POST['csrf_token'] ?? null;
    if (!is_string($tokenSesion) || $tokenSesion === '' || !is_string($tokenRecibido) ||
        !hash_equals($tokenSesion, $tokenRecibido)) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }
} elseif (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$validarId = static function ($valor) {
    if (!is_string($valor) && !is_int($valor)) {
        return false;
    }
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
};
$citaId = $validarId($metodo === 'POST' ? ($_POST['cita_id'] ?? null) : ($_GET['id'] ?? null));
if ($citaId === false || ($metodo === 'POST' && isset($_GET['id']) && $validarId($_GET['id']) !== $citaId)) {
    http_response_code(400);
    exit('La cita seleccionada no es válida.');
}

$origenSolicitado = $metodo === 'POST' ? ($_POST['origen'] ?? '') : ($_GET['origen'] ?? '');
$destinosRetorno = ['reporte' => 'reporte_citas', 'citas' => 'citas_admin'];
$origen = $rol === 2 && is_string($origenSolicitado) && isset($destinosRetorno[$origenSolicitado])
    ? $origenSolicitado : 'panel';
$destinoRetorno = $destinosRetorno[$origen] ?? 'panel';

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require dirname(__DIR__) . '/config/Conexion.php';
    require_once dirname(__DIR__) . '/middlewares/SesionActiva.php';
    require_once dirname(__DIR__) . '/servicios/ReprogramacionService.php';
    verificarPerfilActivo($conexion);

    $cita = obtenerCitaReprogramacionDAO($conexion, $citaId);
    // Para pacientes, una cita ajena y una inexistente reciben la misma respuesta.
    if (!$cita || !autorizarReprogramacion($cita, $rol, $usuarioId)) {
        http_response_code($rol === 2 && !$cita ? 404 : 403);
        exit('No se puede acceder a esta cita.');
    }
    if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true) ||
        ($rol === 0 && !puedeReprogramarCitaPaciente($cita))) {
        http_response_code(409);
        exit('Esta cita ya no se puede reprogramar.');
    }

    $pagoAsociado=pagoCitaDAO($conexion,$citaId);
    if ($pagoAsociado && $pagoAsociado['estado']!=='aprobado') {http_response_code(409);exit('Verifica el pago antes de reprogramar esta cita.');}
    [, $opcionesPorClave] = cargarCatalogoEspecialidades($conexion);
    $opciones = opcionesDoctoresReprogramacion($conexion, $cita, $opcionesPorClave);
    $datos = $metodo === 'POST' ? $_POST : $_GET;
    $doctorPredeterminado = isset($opciones[(int)$cita['doctor_id']])
        ? (int)$cita['doctor_id'] : (array_key_first($opciones) ?? 0);
    $doctorId = $validarId($datos['doctor_id'] ?? ($metodo === 'GET' ? $doctorPredeterminado : null));
    $doctorElegido = $doctorId !== false ? ($opciones[$doctorId] ?? null) : null;
    $doctorId = $doctorId === false ? 0 : $doctorId;

    $hoy = ahoraReprogramacion()->format('Y-m-d');
    $fechaPredeterminada = max($hoy, $cita['fecha']);
    $fechaSolicitada = $datos['fecha'] ?? ($metodo === 'GET' ? $fechaPredeterminada : '');
    $fechaValida = is_string($fechaSolicitada) && fechaHoraReprogramacion($fechaSolicitada, '00:00:00') !== null && $fechaSolicitada >= $hoy;
    $fechaConsulta = $fechaValida ? $fechaSolicitada : $fechaPredeterminada;
    $horaSeleccionada = '';
    $horariosDisponibles = [];
    $mensaje = '';
    $tipoMensaje = 'error';
    $reprogramada = false;

    if (!$fechaValida) {
        http_response_code(400);
        $mensaje = 'Selecciona una fecha válida, igual o posterior a hoy.';
    } elseif (!$doctorElegido) {
        http_response_code($opciones ? 400 : 409);
        $mensaje = $opciones ? 'Selecciona un doctor activo de la misma especialidad.' : 'No hay doctores activos de esta especialidad.';
    } elseif ($metodo === 'POST') {
        $hora = $_POST['hora'] ?? null;
        if (!is_string($hora) || fechaHoraReprogramacion($fechaConsulta, $hora) === null) {
            http_response_code(400);
            $mensaje = 'Selecciona un horario válido.';
        } else {
            $horaSeleccionada = $hora;
            [$ok, $mensaje, $codigo] = reprogramarCitaServicio(
                $conexion, $cita, $rol, $usuarioId, $doctorId, $fechaConsulta, $hora, $opcionesPorClave
            );
            if (!$ok) {
                http_response_code($codigo);
                if ($codigo === 403) {
                    exit('No se puede reprogramar esta cita.');
                }
            } else {
                // La transacción ya fue confirmada por el servicio.
                $cita['doctor_id'] = $doctorId;
                $cita['doctor_nombres'] = $doctorElegido['nombres'];
                $cita['doctor_apellidos'] = $doctorElegido['apellidos'];
                $cita['especialidad'] = $doctorElegido['especialidad'];
                $cita['fecha'] = $fechaConsulta;
                $cita['hora'] = $hora;
                $mensaje = 'Cita reprogramada para el ' . date('d/m/Y', strtotime($fechaConsulta)) . ' a las ' . substr($hora, 0, 5) . '.';
                $tipoMensaje = 'success';
                $reprogramada = true;
            }
        }
    }

    if (!$reprogramada && $doctorElegido && $fechaValida) {
        $horariosDisponibles = horariosDisponiblesReprogramacionServicio($conexion, $cita, $doctorElegido, $fechaConsulta, $opcionesPorClave);
    }
} catch (Throwable $error) {
    error_log('Error en controlador de reprogramación: ' . get_class($error) . ' / ' . $error->getCode());
    http_response_code(500);
    exit('No se pudo procesar la reprogramación. Inténtalo nuevamente.');
}

require dirname(__DIR__) . '/vistas/reprogramar_cita.php';
