<?php
// Acceso a datos de cuentas y directorios administrativos.

function listarPacientes(mysqli $conexion, int $estadoListado): array
{
    $stmt = $conexion->prepare("
    SELECT p.id, p.nombres, p.apellidos, p.dni, p.telefono, p.correo, p.fecha_registro, u.usuario
    FROM pacientes p
    LEFT JOIN usuarios u ON u.id = p.usuario_id
    WHERE p.activo = ?
    ORDER BY p.apellidos, p.nombres
");
    try {
        $stmt->bind_param("i", $estadoListado);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally {
        $stmt->close();
    }
}

function usuarioFichaPaciente(mysqli $conexion, int $id): ?string
{
    $stmt = $conexion->prepare("SELECT u.usuario FROM pacientes p
        LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? AND p.activo = 1");
    try {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()["usuario"] ?? null;
    } finally {
        $stmt->close();
    }
}

function listarDoctores(mysqli $conexion, int $estadoListado): array
{
    $stmt = $conexion->prepare("
    SELECT d.id, d.nombres, d.apellidos, d.cmp, d.especialidad, d.telefono,
           d.hora_inicio, d.hora_fin, u.usuario
    FROM doctores d
    LEFT JOIN usuarios u ON u.id = d.usuario_id
    WHERE d.activo = ?
    ORDER BY d.apellidos, d.nombres
");
    try {
        $stmt->bind_param("i", $estadoListado);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally {
        $stmt->close();
    }
}

function usuarioFichaDoctor(mysqli $conexion, int $id): ?string
{
    $stmt = $conexion->prepare("SELECT u.usuario FROM doctores d
        LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.id = ? AND d.activo = 1");
    try {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()["usuario"] ?? null;
    } finally {
        $stmt->close();
    }
}
