<?php
function buscarUsuarioActivo(mysqli $conexion, string $usuario): ?array
{
    $stmt = $conexion->prepare("SELECT id, usuario, password, rol FROM usuarios
        WHERE usuario = ? AND (rol = 2 OR (rol = 0 AND EXISTS
        (SELECT 1 FROM pacientes p WHERE p.usuario_id=usuarios.id AND p.activo=1))
        OR (rol = 1 AND EXISTS (SELECT 1 FROM doctores d WHERE d.usuario_id=usuarios.id AND d.activo=1)))");
    try {
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();
        return $resultado->num_rows === 1 ? $resultado->fetch_assoc() : null;
    } finally { $stmt->close(); }
}
function actualizarHashUsuario(mysqli $conexion, int $id, string $hash): void
{
    $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    try { $stmt->bind_param("si", $hash, $id); $stmt->execute(); }
    finally { $stmt->close(); }
}
