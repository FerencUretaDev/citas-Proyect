<?php
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
function verificarPerfilActivo(mysqli $conexion, bool $json = false): void
{
    $rol = $_SESSION['rol'] ?? null;
    if (!isset($_SESSION['id']) || !in_array($rol, [0,1,'0','1'], true)) {
        return;
    }
    $tabla = (int)$rol === 0 ? 'pacientes' : 'doctores';
    $stmt = $conexion->prepare("SELECT id FROM $tabla WHERE usuario_id=? AND activo=1 LIMIT 1");
    $stmt->bind_param('i', $_SESSION['id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) {
        auditar($conexion,"seguridad","perfil_inactivo","denegado");
        $_SESSION = [];
        session_destroy();
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['error' => 'Tu cuenta está inactiva.']));
        }
        exit('Tu cuenta está inactiva. Comunícate con administración.');
    }
}
