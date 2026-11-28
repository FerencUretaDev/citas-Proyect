<?php
require_once __DIR__ . '/../repositorios/UsuarioDAO.php';
function autenticarUsuario(mysqli $conexion, string $usuario, string $password): ?array
{
    $datos = buscarUsuarioActivo($conexion, $usuario);
    if ($datos === null) { return null; }
    if (!password_verify($password, $datos['password'])) {
        if (!hash_equals(hash('sha256', $password), $datos['password'])) { return null; }
        if (!actualizarHashUsuario(
            $conexion,
            (int)$datos['id'],
            password_hash($password, PASSWORD_DEFAULT),
            $datos['password'],
            (int)$datos['version_sesion']
        )) {
            return null;
        }
    }
    unset($datos['password']);
    return $datos;
}
