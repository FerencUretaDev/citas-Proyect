<?php

function restablecerPasswordDAO(
    mysqli $conexion,
    string $usuario,
    string $codigo,
    string $nuevoHash
): bool {

    if (preg_match('/\A[a-f0-9]{32}\z/', $codigo) !== 1) {
        return false;
    }

    $tokenHash = hash('sha256', $codigo);

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conexion->begin_transaction();

    try {

        // Identificar la solicitud activa.
        $stmt = $conexion->prepare("
            SELECT s.id
            FROM solicitudes_recuperacion s
            INNER JOIN usuarios u ON u.id=s.usuario_id
            WHERE u.usuario=?
            AND u.rol IN (0,1)
            AND s.estado='en_atencion'
        ");

        $stmt->bind_param('s', $usuario);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) {
            $conexion->rollback();
            return false;
        }

        $solicitudId = (int)$fila['id'];

        // Bloquear la solicitud durante el restablecimiento.
        $stmt = $conexion->prepare("
            SELECT usuario_id, token_id
            FROM solicitudes_recuperacion
            WHERE id=?
            AND estado='en_atencion'
            AND identidad_verificada_at IS NOT NULL
            AND token_id IS NOT NULL
            FOR UPDATE
        ");

        $stmt->bind_param('i', $solicitudId);
        $stmt->execute();

        $solicitud = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$solicitud) {
            $conexion->rollback();
            return false;
        }

        $usuarioId = (int)$solicitud['usuario_id'];
        $tokenId = (string)$solicitud['token_id'];

        // Verificar y bloquear la cuenta beneficiaria.
        $stmt = $conexion->prepare("
            SELECT rol
            FROM usuarios
            WHERE id=?
            AND usuario=?
            AND rol IN (0,1)
            FOR UPDATE
        ");

        $stmt->bind_param('is', $usuarioId, $usuario);
        $stmt->execute();

        $cuenta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cuenta) {
            $conexion->rollback();
            return false;
        }

        $tabla = (int)$cuenta['rol'] === 0
            ? 'pacientes'
            : 'doctores';

        $stmt = $conexion->prepare("
            SELECT id FROM $tabla
            WHERE usuario_id=? AND activo=1
            FOR UPDATE
        ");

        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();

        $activo = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if (!$activo) {
            $conexion->rollback();
            return false;
        }

        // Consumir exclusivamente un token vigente y no utilizado.
        $stmt = $conexion->prepare("
            UPDATE tokens_cuenta
            SET fecha_uso=UTC_TIMESTAMP()
            WHERE id=?
            AND usuario_id=?
            AND token_hash=?
            AND finalidad='recuperar_asistencia'
            AND fecha_expiracion>UTC_TIMESTAMP()
            AND fecha_uso IS NULL
            AND fecha_revocacion IS NULL
        ");

        $stmt->bind_param(
            'sis',
            $tokenId,
            $usuarioId,
            $tokenHash
        );

        $stmt->execute();

        $valido = $stmt->affected_rows === 1;
        $stmt->close();

        if (!$valido) {
            $conexion->rollback();
            return false;
        }

        // Actualizar la contrasena e invalidar sesiones anteriores.
        $stmt = $conexion->prepare("
            UPDATE usuarios
            SET password=?,
                version_sesion=version_sesion+1
            WHERE id=? AND rol IN (0,1)
        ");

        $stmt->bind_param('si', $nuevoHash, $usuarioId);
        $stmt->execute();

        $actualizado = $stmt->affected_rows === 1;
        $stmt->close();

        if (!$actualizado) {
            throw new RuntimeException(
                'No se pudo actualizar la cuenta.'
            );
        }

        // Revocar otros codigos de recuperacion.
        $stmt = $conexion->prepare("
            UPDATE tokens_cuenta
            SET fecha_revocacion=UTC_TIMESTAMP()
            WHERE usuario_id=?
            AND finalidad IN (
                'recuperar_asistencia',
                'recuperar_password'
            )
            AND fecha_uso IS NULL
            AND fecha_revocacion IS NULL
        ");

        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $stmt->close();

        // Finalizar la solicitud.
        $stmt = $conexion->prepare("
            UPDATE solicitudes_recuperacion
            SET estado='resuelta',
                fecha_resolucion=NOW()
            WHERE id=? AND estado='en_atencion'
        ");

        $stmt->bind_param('i', $solicitudId);
        $stmt->execute();

        $resuelta = $stmt->affected_rows === 1;
        $stmt->close();

        if (!$resuelta) {
            throw new RuntimeException(
                'No se pudo finalizar la solicitud.'
            );
        }

        $conexion->commit();

        return true;

    } catch (Throwable $e) {

        $conexion->rollback();

        throw $e;
    }
}