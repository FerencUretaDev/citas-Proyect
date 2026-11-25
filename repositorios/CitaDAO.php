<?php

// Consultas de citas usadas por las reglas de reserva.
// citaExcluir: 0 para una nueva reserva; ID de la cita al reprogramar.
function citasPacienteDelDia(
    mysqli $conexion,
    int $pacienteId,
    string $fecha,
    bool $bloquear = false,
    int $citaExcluir = 0
): array {
    $sql = "SELECT c.hora, d.especialidad
            FROM citas c
            INNER JOIN doctores d ON d.id = c.doctor_id
            WHERE c.paciente_id = ?
            AND c.fecha = ?
            AND c.estado <> 'cancelada'
            AND c.id <> ?";

    // Bloqueo compatible con MySQL y MariaDB.
    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);

    try {
        $stmt->bind_param('isi', $pacienteId, $fecha, $citaExcluir);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } finally {
        $stmt->close();
    }
}


// Bloquear doctor durante la reserva.
function bloquearDoctorReserva(
    mysqli $conexion,
    int $doctor_id
): ?array {
    $sqlDoctor = "
        SELECT
            id,
            especialidad,
            hora_inicio,
            hora_fin,
            refrigerio_inicio,
            refrigerio_fin
        FROM doctores
        WHERE activo = 1 AND id = ?
        FOR UPDATE
    ";

    $stmt = $conexion->prepare($sqlDoctor);

    try {
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();

    } finally {
        $stmt->close();
    }
}


// Bloquear paciente durante la reserva.
function bloquearPacienteReserva(
    mysqli $conexion,
    int $paciente_id
): bool {
    $stmt = $conexion->prepare("
        SELECT id
        FROM pacientes
        WHERE activo = 1 AND id = ?
        FOR UPDATE
    ");

    try {
        $stmt->bind_param("i", $paciente_id);
        $stmt->execute();

        return (bool)$stmt->get_result()->fetch_assoc();

    } finally {
        $stmt->close();
    }
}


// Verificar cruces de horarios, excluyendo la propia cita al reprogramar.
function existeCruceReserva(
    mysqli $conexion,
    int $doctor_id,
    int $paciente_id,
    string $fecha,
    string $hora,
    int $citaExcluir = 0
): bool {
    $sqlExiste = "
        SELECT id
        FROM citas
        WHERE fecha = ?
        AND id <> ?
        AND estado != 'cancelada'
        AND (
            (
                doctor_id = ?
                AND hora < ADDTIME(?, '00:30:00')
                AND ADDTIME(hora, '00:30:00') > ?
            )
            OR
            (
                paciente_id = ?
                AND hora < ADDTIME(?, '00:30:00')
                AND ADDTIME(hora, '00:30:00') > ?
            )
        )
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $conexion->prepare($sqlExiste);

    try {
        $stmt->bind_param(
            "siississ",
            $fecha,
            $citaExcluir,
            $doctor_id,
            $hora,
            $hora,
            $paciente_id,
            $hora,
            $hora
        );

        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;

    } finally {
        $stmt->close();
    }
}


// Registrar una nueva cita.
function insertarReserva(
    mysqli $conexion,
    int $paciente_id,
    int $doctor_id,
    string $fecha,
    string $hora,
    string $modalidad,
    string $motivo
): array {
    $sqlInsert = "
        INSERT INTO citas
        (
            paciente_id,
            doctor_id,
            fecha,
            hora,
            modalidad,
            motivo,
            estado
        )
        VALUES
        (?, ?, ?, ?, ?, ?, 'pendiente')
    ";

    $stmt = $conexion->prepare($sqlInsert);

    try {
        $stmt->bind_param(
            "iissss",
            $paciente_id,
            $doctor_id,
            $fecha,
            $hora,
            $modalidad,
            $motivo
        );

        $ok = $stmt->execute();

        return [$ok, $stmt->errno];

    } finally {
        $stmt->close();
    }
}


// Cancelación de citas por el paciente.
function cancelarCitaPacienteDAO(
    mysqli $conexion,
    int $citaId,
    int $usuarioId,
    string $ahora
): bool {
    $sql = "
        UPDATE citas c
        INNER JOIN pacientes p
            ON p.id = c.paciente_id
        SET c.estado = 'cancelada'
        WHERE c.id = ?
          AND p.usuario_id = ?
          AND p.activo = 1
          AND c.estado IN ('pendiente', 'confirmada')
          AND TIMESTAMP(c.fecha, c.hora) > ?
    ";

    $stmt = $conexion->prepare($sql);

    try {
        $stmt->bind_param(
            "iis",
            $citaId,
            $usuarioId,
            $ahora
        );

        if (!$stmt->execute()) {
            throw new RuntimeException(
                "No se pudo cancelar la cita."
            );
        }

        return $stmt->affected_rows === 1;

    } finally {
        $stmt->close();
    }
}

// Consultar el historial de citas del paciente autenticado.
function obtenerHistorialCitasPacienteDAO(
    mysqli $conexion,
    int $usuarioId,
    string $ahora,
    string $fechaInicio = "",
    string $fechaFin = "",
    string $especialidad = "",
    string $estado = ""
): array {

    $sql = "
        SELECT
            c.id,
            c.fecha,
            c.hora,
            c.modalidad,
            c.motivo,
            c.estado,
            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad

        FROM citas c

        INNER JOIN pacientes p
            ON p.id = c.paciente_id

        INNER JOIN doctores d
            ON d.id = c.doctor_id

        WHERE p.usuario_id = ?
          AND p.activo = 1

          AND (
              c.estado IN ('atendida', 'cancelada')
              OR TIMESTAMP(c.fecha, c.hora) < ?
          )
    ";

    $tipos = "is";
    $parametros = [$usuarioId, $ahora];

    // Filtrar por fecha inicial.
    if ($fechaInicio !== "") {
        $sql .= " AND c.fecha >= ?";
        $tipos .= "s";
        $parametros[] = $fechaInicio;
    }

    // Filtrar por fecha final.
    if ($fechaFin !== "") {
        $sql .= " AND c.fecha <= ?";
        $tipos .= "s";
        $parametros[] = $fechaFin;
    }

    // Filtrar por especialidad.
    if ($especialidad !== "") {
        $sql .= " AND d.especialidad = ?";
        $tipos .= "s";
        $parametros[] = $especialidad;
    }

    // Filtrar por estado.
    if ($estado !== "") {
        $sql .= " AND c.estado = ?";
        $tipos .= "s";
        $parametros[] = $estado;
    }

    $sql .= "
        ORDER BY c.fecha DESC,
                 c.hora DESC,
                 c.id DESC
    ";

    $stmt = $conexion->prepare($sql);

    try {

        $stmt->bind_param(
            $tipos,
            ...$parametros
        );

        $stmt->execute();

        return $stmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);

    } finally {

        $stmt->close();

    }
}
