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


// El rol determina la tabla; nunca se acepta un nombre de tabla del cliente.
function tablaFichaDirectorio(int $rol): string
{
    return match ($rol) {
        0 => 'pacientes',
        1 => 'doctores',
        default => throw new LogicException("Rol de ficha no permitido.")
    };
}

// El llamador debe tener una transacción abierta. Evita editar fichas inactivas
// o inexistentes aunque el identificador enviado por el cliente sea manipulado.
function bloquearFichaDirectorio(mysqli $conexion, int $fichaId, int $rol): void
{
    $tabla = tablaFichaDirectorio($rol);
    $stmt = $conexion->prepare("SELECT id FROM $tabla WHERE id = ? AND activo = 1 FOR UPDATE");
    try {
        $stmt->bind_param("i", $fichaId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new DomainException("La ficha activa ya no está disponible.");
        }
    } finally {
        $stmt->close();
    }
}

// Actualiza únicamente la cuenta vinculada a la ficha y al rol esperado.
// Recibe un hash, nunca la contraseña en texto plano. Requiere transacción.
function actualizarPasswordFichaDirectorio(mysqli $conexion, int $fichaId, int $rol, string $hash): void
{
    $tabla = tablaFichaDirectorio($rol);
    $stmt = $conexion->prepare("SELECT u.id FROM $tabla f
        INNER JOIN usuarios u ON u.id = f.usuario_id
        WHERE f.id = ? AND f.activo = 1 AND u.rol = ? FOR UPDATE");
    try {
        $stmt->bind_param("ii", $fichaId, $rol);
        $stmt->execute();
        $cuenta = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$cuenta) {
        throw new DomainException("No existe una cuenta vinculada con el rol correcto. No se guardaron los cambios.");
    }
    $usuarioId = (int)$cuenta["id"];
    $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ? AND rol = ?");
    try {
        $stmt->bind_param("sii", $hash, $usuarioId, $rol);
        $stmt->execute();
    } finally {
        $stmt->close();
    }
}
