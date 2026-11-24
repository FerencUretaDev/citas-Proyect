<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respuestaDni(int $estado, array $contenido): never
{
    http_response_code($estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'] ?? null, [2, '2'], true)) {
    respuestaDni(403, ['error' => 'Acceso reservado al administrador.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respuestaDni(405, ['error' => 'Solicitud no válida.']);
}
$csrf = $_POST['csrf_token'] ?? null;
if (!is_string($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    respuestaDni(403, ['error' => 'Recarga el formulario y vuelve a intentarlo.']);
}
$dni = $_POST['dni'] ?? '';
if (!is_string($dni) || !preg_match('/\A[0-9]{8}\z/', $dni)) {
    respuestaDni(422, ['error' => 'El DNI debe tener exactamente 8 dígitos.']);
}
session_write_close();
require_once dirname(__DIR__) . '/servicios/DniService.php';
[$estado, $contenido] = consultarDniServicio($dni);
respuestaDni($estado, $contenido);
