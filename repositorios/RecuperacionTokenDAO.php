<?php

function generarCodigoRecuperacionDAO(
    mysqli $conexion,
    int $solicitudId,
    int $adminId,
    bool $identidadConfirmada
): ?string {

    if (!$identidadConfirmada) {
        return null;
    }

    // Codigo aleatorio de 128 bits.
    $codigo = bin2hex(random_bytes(16));
    $hash = hash('sha256', $codigo);

    if (!$conexion->begin_transaction()) {
        throw new RuntimeException('Error de transaccion.');
    }

    try {

        // Bloquear la solicitud durante la operacion.
        $stmt = $conexion->prepare("
            SELECT usuario_id
            FROM solicitudes_recuperacion
            WHERE id=?
            AND administrador_id=?
            AND estado='en_atencion'
            FOR UPDATE
        ");

        $stmt->bind_param('ii', $solicitudId, $adminId);
        $stmt->execute();

        $solicitud = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$solicitud) {
            $conexion->rollback();
            return null;
        }

        $usuarioId = (int)$solicitud['usuario_id'];

        // Comprobar que el administrador sigue autorizado.
        $stmt = $conexion->prepare("
            SELECT id FROM usuarios
            WHERE id=? AND rol=2
        ");

        $stmt->bind_param('i', $adminId);
        $stmt->execute();

        $adminValido = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if (!$adminValido) {
            $conexion->rollback();
            return null;
        }

        // Comprobar que la cuenta beneficiaria sigue activa.
        $stmt = $conexion->prepare("
            SELECT u.id
            FROM usuarios u
            WHERE u.id=?
            AND (
                (u.rol=0 AND EXISTS (
                    SELECT 1 FROM pacientes p
                    WHERE p.usuario_id=u.id AND p.activo=1
                ))
                OR
                (u.rol=1 AND EXISTS (
                    SELECT 1 FROM doctores d
                    WHERE d.usuario_id=u.id AND d.activo=1
                ))
            )
            FOR UPDATE
        ");

        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();

        $activo = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if (!$activo) {
            $conexion->rollback();
            return null;
        }

        // Revocar codigos anteriores.
        $stmt = $conexion->prepare("
            UPDATE tokens_cuenta
            SET fecha_revocacion=UTC_TIMESTAMP()
            WHERE usuario_id=?
            AND finalidad='recuperar_asistencia'
            AND fecha_uso IS NULL
            AND fecha_revocacion IS NULL
        ");

        $stmt->bind_param('i', $usuarioId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Error al revocar.');
        }

        $stmt->close();

        // Guardar exclusivamente el hash del nuevo codigo.
        $stmt = $conexion->prepare("
            INSERT INTO tokens_cuenta (
                usuario_id,
                finalidad,
                correo_destino,
                token_hash,
                fecha_creacion,
                fecha_expiracion
            ) VALUES (
                ?,
                'recuperar_asistencia',
                NULL,
                ?,
                UTC_TIMESTAMP(),
                DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
            )
        ");

        $stmt->bind_param('is', $usuarioId, $hash);

        if (!$stmt->execute()) {
            throw new RuntimeException('Error al generar codigo.');
        }

        $tokenId = (string)$conexion->insert_id;
        $stmt->close();

        // Vincular el codigo con la solicitud.
        $stmt = $conexion->prepare("
            UPDATE solicitudes_recuperacion
            SET token_id=?,
                identidad_verificada_at=NOW()
            WHERE id=?
            AND administrador_id=?
            AND estado='en_atencion'
        ");

        $stmt->bind_param(
            'sii',
            $tokenId,
            $solicitudId,
            $adminId
        );

        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            throw new RuntimeException('Error al vincular codigo.');
        }

        $stmt->close();

        if (!$conexion->commit()) {
            throw new RuntimeException('Error al confirmar.');
        }

        return $codigo;

    } catch (Throwable $e) {

        $conexion->rollback();
        throw $e;
    }
}