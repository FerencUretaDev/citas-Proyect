<?php

// Comprobar si existe una ausencia dentro del periodo.
function existeAusenciaDoctorDAO(
    mysqli $conexion,
    int $doctorId,
    string $fechaInicio,
    string $fechaFin,
    bool $bloquear = false
): bool {

    $sql = "
        SELECT id
        FROM ausencias_doctores
        WHERE doctor_id = ?
          AND cancelada = 0
          AND fecha_inicio <= ?
          AND fecha_fin >= ?
        LIMIT 1
    ";

    // Usar dentro de una transaccion con el doctor bloqueado.
    if ($bloquear) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conexion->prepare($sql);

    try {

        $stmt->bind_param(
            "iss",
            $doctorId,
            $fechaFin,
            $fechaInicio
        );

        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;

    } finally {

        $stmt->close();

    }
}

// Comprobar la disponibilidad del doctor en un dia.
function doctorTieneAusenciaDAO(
    mysqli $conexion,
    int $doctorId,
    string $fecha,
    bool $bloquear = false
): bool {

    return existeAusenciaDoctorDAO(
        $conexion,
        $doctorId,
        $fecha,
        $fecha,
        $bloquear
    );
}

// Listar doctores activos para registrar ausencias.
function listarDoctoresAusenciasDAO(mysqli $conexion): array
{
    $resultado = $conexion->query("
        SELECT id, nombres, apellidos, especialidad
        FROM doctores
        WHERE activo = 1
        ORDER BY apellidos, nombres, id
    ");

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

// Registrar una ausencia.
function insertarAusenciaDAO(
    mysqli $conexion,
    int $doctorId,
    string $fechaInicio,
    string $fechaFin,
    string $motivo,
    string $observaciones,
    int $usuarioId
): int {

    $stmt = $conexion->prepare("
        INSERT INTO ausencias_doctores (
            doctor_id,
            fecha_inicio,
            fecha_fin,
            motivo,
            observaciones,
            registrado_por
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmt->bind_param(
            "issssi",
            $doctorId,
            $fechaInicio,
            $fechaFin,
            $motivo,
            $observaciones,
            $usuarioId
        );

        $stmt->execute();

        return (int)$conexion->insert_id;

    } finally {
        $stmt->close();
    }
}

// Cancelar una ausencia sin eliminar su historial.
function cancelarAusenciaDAO(
    mysqli $conexion,
    int $ausenciaId,
    int $doctorId,
    string $hoy,
    string $ahora
): bool {

    $stmt = $conexion->prepare("
        UPDATE ausencias_doctores
        SET cancelada = 1,
            fecha_cancelacion = ?
        WHERE id = ?
          AND doctor_id = ?
          AND cancelada = 0
          AND fecha_fin >= ?
    ");

    try {
        $stmt->bind_param(
            "siis",
            $ahora,
            $ausenciaId,
            $doctorId,
            $hoy
        );

        $stmt->execute();

        return $stmt->affected_rows === 1;

    } finally {
        $stmt->close();
    }
}

// Listar ausencias y cantidad de citas afectadas.
function listarAusenciasDAO(mysqli $conexion): array
{
    $resultado = $conexion->query("
        SELECT
            a.*,
            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad,

            (
                SELECT COUNT(*)
                FROM citas c
                WHERE c.doctor_id = a.doctor_id
                  AND c.fecha BETWEEN
                      a.fecha_inicio AND a.fecha_fin
                  AND c.estado IN (
                      'pendiente', 'confirmada'
                  )
                  AND a.cancelada = 0
            ) AS citas_afectadas

        FROM ausencias_doctores a

        INNER JOIN doctores d
            ON d.id = a.doctor_id

        ORDER BY a.fecha_inicio DESC, a.id DESC
    ");

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

// Obtener las citas afectadas por una ausencia.
function listarCitasAfectadasDAO(
    mysqli $conexion,
    int $ausenciaId
): array {

    $stmt = $conexion->prepare("
        SELECT
            c.id,
            c.fecha,
            c.hora,
            c.estado,
            p.nombres AS paciente_nombres,
            p.apellidos AS paciente_apellidos

        FROM ausencias_doctores a

        INNER JOIN citas c
            ON c.doctor_id = a.doctor_id

        INNER JOIN pacientes p
            ON p.id = c.paciente_id

        WHERE a.id = ?
          AND a.cancelada = 0
          AND c.fecha BETWEEN
              a.fecha_inicio AND a.fecha_fin
          AND c.estado IN (
              'pendiente', 'confirmada'
          )

        ORDER BY c.fecha, c.hora, c.id
    ");

    try {
        $stmt->bind_param("i", $ausenciaId);
        $stmt->execute();

        return $stmt->get_result()
            ->fetch_all(MYSQLI_ASSOC);

    } finally {
        $stmt->close();
    }
}