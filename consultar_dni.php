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
$ruta = 'C:/xampp/private/config/dni.ini';
$config = is_readable($ruta) ? parse_ini_file($ruta, false, INI_SCANNER_RAW) : [];
$token = trim($config['APIPERU_TOKEN'] ?? '');
if ($token === '' || $token === 'PEGA_AQUI_TU_TOKEN' || preg_match('/[\r\n]/', $token)) {
    respuestaDni(503, ['error' => 'Falta configurar la clave de API Perú. Puedes continuar manualmente.']);
}
if (!function_exists('curl_init')) {
    respuestaDni(503, ['error' => 'Activa la extensión cURL de PHP para consultar el DNI.']);
}

$consulta = curl_init('https://api.apiperu.pe/dni');
curl_setopt_array($consulta, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['dni' => $dni]),
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 8,
]);
$contenido = curl_exec($consulta);
$estado = curl_getinfo($consulta, CURLINFO_RESPONSE_CODE);
if ($estado !== 200 || !is_string($contenido)) {
    $mensaje = match ($estado) {
        401, 403 => 'API Perú rechazó la clave. Revisa la configuración.',
        404 => 'La consulta no devolvió datos. Revisa el DNI y la clave de API Perú.',
        429 => 'Se alcanzó el límite de consultas de API Perú.',
        default => 'API Perú no está disponible. Puedes continuar manualmente.',
    };
    respuestaDni(502, ['error' => $mensaje]);
}
$resultado = json_decode($contenido, true);
$datos = $resultado['data'] ?? [];
if (($resultado['success'] ?? false) !== true || ($datos['numero'] ?? null) !== $dni ||
    !is_string($datos['nombres'] ?? null) || trim($datos['nombres']) === '' ||
    !is_string($datos['apellido_paterno'] ?? null) || trim($datos['apellido_paterno']) === '' ||
    !is_string($datos['apellido_materno'] ?? '')) {
    respuestaDni(502, ['error' => 'No se obtuvieron nombres válidos para este DNI. Puedes continuar manualmente.']);
}
respuestaDni(200, ['nombres' => trim($datos['nombres']), 'primer_apellido' => trim($datos['apellido_paterno']),
    'segundo_apellido' => trim($datos['apellido_materno'] ?? '')]);
