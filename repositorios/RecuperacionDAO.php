<?php

// Registrar una solicitud exclusivamente para cuentas activas.
function solicitarRecuperacionDAO(
    mysqli $conexion,
    string $usuario
): bool {
    $sql = "
        INSERT INTO solicitudes_recuperacion (usuario_id)
        SELECT u.id
        FROM usuarios u
        WHERE u.usuario = ?
        AND (
            (u.rol = 0 AND EXISTS (
                SELECT 1 FROM pacientes p
                WHERE p.usuario_id = u.id
                AND p.activo = 1
            ))
            OR
            (u.rol = 1 AND EXISTS (
                SELECT 1 FROM doctores d
                WHERE d.usuario_id = u.id
                AND d.activo = 1
            ))
        )
    ";

    $stmt = $conexion->prepare($sql);

    try {
        $stmt->bind_param("s", $usuario);

        if (!$stmt->execute()) {
            if ($stmt->errno === 1062) {
                return false;
            }

            throw new RuntimeException(
                "No se pudo registrar la solicitud."
            );
        }

        return $stmt->affected_rows === 1;

    } catch (mysqli_sql_exception $e) {

        // Impide solicitudes activas duplicadas.
        if ($e->getCode() === 1062) {
            return false;
        }

        throw $e;

    } finally {
        $stmt->close();
    }
}


// Contador para la alerta administrativa.
function contarRecuperacionesPendientesDAO(
    mysqli $conexion
): int {
    $resultado = $conexion->query("
        SELECT COUNT(*) AS total
        FROM solicitudes_recuperacion
        WHERE estado = 'pendiente'
    ");

    return (int)$resultado->fetch_assoc()["total"];
}


// Listado exclusivo para el modulo administrativo.
function listarRecuperacionesDAO(
    mysqli $conexion
): array {
    $sql = "
        SELECT
            s.id,
            s.usuario_id,
            s.estado,
            s.fecha_solicitud,
            s.fecha_atencion,
            s.fecha_resolucion,
            u.usuario,
            u.rol,

            CASE
                WHEN u.rol = 0 THEN p.nombre
                WHEN u.rol = 1 THEN d.nombre
            END AS nombre,

            CASE
                WHEN u.rol = 0 THEN p.telefono
                WHEN u.rol = 1 THEN d.telefono
            END AS telefono

        FROM solicitudes_recuperacion s

        INNER JOIN usuarios u
            ON u.id = s.usuario_id

        LEFT JOIN (
            SELECT usuario_id,
                MIN(CONCAT(nombres, ' ', apellidos)) AS nombre,
                MIN(telefono) AS telefono
            FROM pacientes
            WHERE activo = 1
            GROUP BY usuario_id
            HAVING COUNT(*) = 1
        ) p ON p.usuario_id = u.id AND u.rol = 0

        LEFT JOIN (
            SELECT usuario_id,
                MIN(CONCAT(nombres, ' ', apellidos)) AS nombre,
                MIN(telefono) AS telefono
            FROM doctores
            WHERE activo = 1
            GROUP BY usuario_id
            HAVING COUNT(*) = 1
        ) d ON d.usuario_id = u.id AND u.rol = 1

        ORDER BY
            CASE s.estado
                WHEN 'pendiente' THEN 1
                WHEN 'en_atencion' THEN 2
                WHEN 'resuelta' THEN 3
                ELSE 4
            END,
            s.fecha_solicitud DESC,
            s.id DESC

        LIMIT 100
    ";

    $resultado = $conexion->query($sql);

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

// MEJORA 03 - Gestion administrativa de solicitudes.
function gestionarRecuperacionDAO(
    mysqli $conexion,
    int $id,
    int $adminId,
    string $accion
): bool {

    $sql = match ($accion) {

        'atender' => "
            UPDATE solicitudes_recuperacion
            SET estado='en_atencion',
                administrador_id=?,
                fecha_atencion=NOW()
            WHERE id=? AND estado='pendiente'
        ",

        'rechazar' => "
            UPDATE solicitudes_recuperacion
            SET estado='rechazada',
                administrador_id=?,
                fecha_resolucion=NOW()
            WHERE id=?
            AND estado IN ('pendiente','en_atencion')
        ",

        default => throw new DomainException('Accion no permitida.')
    };

    $conexion->begin_transaction();

    try {

        $stmt = $conexion->prepare($sql);

        try {
            $stmt->bind_param('ii', $adminId, $id);

            if (!$stmt->execute()) {
                throw new RuntimeException('Error al actualizar solicitud.');
            }

            $actualizada = $stmt->affected_rows === 1;

        } finally {
            $stmt->close();
        }

        if ($actualizada && $accion === 'rechazar') {

            $stmt = $conexion->prepare("
                UPDATE tokens_cuenta t
                INNER JOIN solicitudes_recuperacion s
                    ON s.token_id=t.id
                    AND s.usuario_id=t.usuario_id
                SET t.fecha_revocacion=UTC_TIMESTAMP()
                WHERE s.id=?
                AND t.finalidad='recuperar_asistencia'
                AND t.fecha_uso IS NULL
                AND t.fecha_revocacion IS NULL
            ");

            try {
                $stmt->bind_param('i', $id);

                if (!$stmt->execute()) {
                    throw new RuntimeException('Error al revocar token.');
                }

            } finally {
                $stmt->close();
            }
        }

        $conexion->commit();

        return $actualizada;

    } catch (Throwable $e) {
        $conexion->rollback();
        throw $e;
    }
}
