<?php
session_start();
if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'] ?? null, [2, '2'], true)) {
    http_response_code(403);
    exit('Acceso denegado.');
}
require __DIR__ . '/conexion.php';
require __DIR__ . '/modelos/Directorio.php';

$tipo = $_GET['tipo'] ?? '';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($tipo, ['doctor', 'paciente'], true) || !$id || $id < 1) {
    http_response_code(400);
    exit('Ficha no válida.');
}
$paginaActiva = $tipo === 'doctor' ? 'doctores' : 'pacientes';
$rol = 2;
$modelo = new Directorio($conexion, $tipo);
$ficha = $modelo->ficha($id);
if (!$ficha) {
    http_response_code(404);
    exit('Ficha no encontrada.');
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$motivo = $tipo === 'doctor' ? 'Renuncia' : 'Retiro del paciente';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }
    $accion = $_POST['accion'] ?? '';
    $motivo = trim(is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '');
    try {
        if (!in_array($accion, ['baja', 'reactivar'], true)) {
            throw new DomainException('Acción no válida.');
        }
        if ($accion === 'baja' && !preg_match('/\A.{1,160}\z/u', $motivo)) {
            throw new DomainException('Escribe un motivo de hasta 160 caracteres.');
        }
        $modelo->cambiarEstado($id, $accion === 'reactivar', $motivo);
        $_SESSION['mensaje_' . $tipo] = $accion === 'reactivar' ? 'Perfil reactivado.' :
            ($tipo === 'doctor' ? 'Doctor desactivado.' : 'Paciente archivado.');
        header('Location: ' . $paginaActiva . ($accion === 'baja' ? '?vista=inactivos' : ''), true, 303);
        exit;
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $error = 'No se pudo guardar el cambio. Inténtalo de nuevo.';
    }
}
$activo = (bool)$ficha['activo'];
$pendientes = $activo ? $modelo->citasFuturas($id) : 0;
$accionVisible = $activo ? ($tipo === 'doctor' ? 'Desactivar' : 'Archivar') : 'Reactivar';
require __DIR__ . '/vistas/estado_perfil.php';
