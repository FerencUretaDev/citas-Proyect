<?php
function buscarUsuarioActivo(mysqli $conexion, string $usuario): ?array
{
    $stmt = $conexion->prepare("SELECT id, usuario, password, rol, version_sesion FROM usuarios
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
function actualizarHashUsuario(
    mysqli $conexion,
    int $id,
    string $hash,
    string $hashAnterior,
    int $version
): bool {

    $stmt = $conexion->prepare("
        UPDATE usuarios
        SET password = ?
        WHERE id = ?
        AND BINARY password = BINARY ?
        AND version_sesion = ?
    ");

    try {

        $stmt->bind_param(
            'sisi',
            $hash,
            $id,
            $hashAnterior,
            $version
        );

        $stmt->execute();

        return $stmt->affected_rows === 1;

    } finally {

        $stmt->close();
    }
}