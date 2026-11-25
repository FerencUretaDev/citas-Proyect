<?php

require_once __DIR__ . '/CitaDAO.php';

function obtenerCitaReprogramacionDAO(mysqli $conexion, int $citaId): ?array
{
    $stmt = $conexion->prepare("SELECT c.id, c.paciente_id, c.doctor_id, c.fecha, c.hora,
        c.modalidad, c.estado, p.usuario_id AS paciente_usuario_id, p.activo AS paciente_activo,
        p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
        d.nombres AS doctor_nombres, d.apellidos AS doctor_apellidos, d.especialidad,
        d.hora_inicio, d.hora_fin, d.refrigerio_inicio, d.refrigerio_fin
        FROM citas c JOIN pacientes p ON p.id = c.paciente_id
        JOIN doctores d ON d.id = c.doctor_id WHERE c.id = ?");
    try {
        $stmt->bind_param('i', $citaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
}

function listarDoctoresReprogramacionDAO(mysqli $conexion): array
{
    $resultado = $conexion->query("SELECT id, nombres, apellidos, especialidad,
        hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin
        FROM doctores WHERE activo = 1 ORDER BY apellidos, nombres, id");
    try {
        return $resultado->fetch_all(MYSQLI_ASSOC);
    } finally {
        $resultado->free();
    }
}

function horasOcupadasReprogramacionDAO(mysqli $conexion, int $citaId, int $doctorId, int $pacienteId, string $fecha): array
{
    $stmt = $conexion->prepare("SELECT hora FROM citas WHERE id <> ? AND fecha = ?
        AND estado <> 'cancelada' AND (doctor_id = ? OR paciente_id = ?)");
    try {
        $stmt->bind_param('isii', $citaId, $fecha, $doctorId, $pacienteId);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'hora');
    } finally {
        $stmt->close();
    }
}

// Se incluyen doctores inactivos para poder trasladar una cita desde uno de ellos.
function bloquearDoctorReprogramacionDAO(mysqli $conexion, int $doctorId): ?array
{
    $stmt = $conexion->prepare("SELECT id, activo, especialidad, hora_inicio, hora_fin,
        refrigerio_inicio, refrigerio_fin FROM doctores WHERE id = ? FOR UPDATE");
    try {
        $stmt->bind_param('i', $doctorId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
}

function bloquearPacienteReprogramacionDAO(mysqli $conexion, int $pacienteId): ?array
{
    $stmt = $conexion->prepare("SELECT id, usuario_id, activo FROM pacientes WHERE id = ? FOR UPDATE");
    try {
        $stmt->bind_param('i', $pacienteId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
}

function bloquearCitaReprogramacionDAO(mysqli $conexion, int $citaId): ?array
{
    $stmt = $conexion->prepare("SELECT id, paciente_id, doctor_id, fecha, hora, estado
        FROM citas WHERE id = ? FOR UPDATE");
    try {
        $stmt->bind_param('i', $citaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
}

function actualizarCitaReprogramacionDAO(mysqli $conexion, int $citaId, int $doctorId, string $fecha, string $hora): bool
{
    $stmt = $conexion->prepare("UPDATE citas SET doctor_id = ?, fecha = ?, hora = ?
        WHERE id = ? AND estado IN ('pendiente', 'confirmada')");
    try {
        $stmt->bind_param('issi', $doctorId, $fecha, $hora, $citaId);
        if (!$stmt->execute()) {
            throw new RuntimeException('No se pudo actualizar la cita.');
        }
        return $stmt->affected_rows === 1;
    } finally {
        $stmt->close();
    }
}
