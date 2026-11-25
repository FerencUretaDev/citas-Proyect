<?php

require_once __DIR__ . '/ReservaService.php';
require_once dirname(__DIR__) . '/repositorios/ReprogramacionDAO.php';

function ahoraReprogramacion(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
}

function fechaHoraReprogramacion(string $fecha, string $hora): ?DateTimeImmutable
{
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $fecha) ||
        !preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $hora)) {
        return null;
    }
    $valor = $fecha . ' ' . $hora;
    $instante = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor, new DateTimeZone('America/Lima'));
    return $instante !== false && $instante->format('Y-m-d H:i:s') === $valor ? $instante : null;
}

function puedeReprogramarCitaPaciente(array $cita, ?DateTimeImmutable $ahora = null): bool
{
    if (!in_array($cita['estado'] ?? '', ['pendiente', 'confirmada'], true)) {
        return false;
    }
    $inicio = fechaHoraReprogramacion($cita['fecha'] ?? '', $cita['hora'] ?? '');
    return $inicio !== null && $inicio > ($ahora ?? ahoraReprogramacion());
}

function autorizarReprogramacion(array $cita, int $rol, int $usuarioId): bool
{
    if ($usuarioId <= 0 || !in_array($rol, [0, 2], true) || (int)($cita['paciente_activo'] ?? 0) !== 1) {
        return false;
    }
    return $rol === 2 || (int)($cita['paciente_usuario_id'] ?? 0) === $usuarioId;
}

function especialidadCanonicaReprogramacion(string $especialidad, array $opcionesPorClave): string
{
    return $opcionesPorClave[claveEspecialidad($especialidad)] ?? $especialidad;
}

function mismaEspecialidadReprogramacion(string $a, string $b, array $opcionesPorClave): bool
{
    return claveEspecialidad(especialidadCanonicaReprogramacion($a, $opcionesPorClave)) ===
        claveEspecialidad(especialidadCanonicaReprogramacion($b, $opcionesPorClave));
}

function opcionesDoctoresReprogramacion(mysqli $conexion, array $cita, array $opcionesPorClave): array
{
    $opciones = [];
    foreach (listarDoctoresReprogramacionDAO($conexion) as $doctor) {
        if (mismaEspecialidadReprogramacion($cita['especialidad'], $doctor['especialidad'], $opcionesPorClave)) {
            $opciones[(int)$doctor['id']] = $doctor;
        }
    }
    return $opciones;
}

// Turnos de 30 minutos, en la zona horaria de Lima.
function turnosValidosReprogramacion(array $doctor, string $fecha, ?DateTimeImmutable $ahora = null): array
{
    $ahora ??= ahoraReprogramacion();
    $inicio = fechaHoraReprogramacion($fecha, $doctor['hora_inicio'] ?? '');
    $fin = fechaHoraReprogramacion($fecha, $doctor['hora_fin'] ?? '');
    if ($inicio === null || $fin === null || $fin <= $inicio) {
        return [];
    }
    $inicioRefrigerio = null;
    $finRefrigerio = null;
    if (!empty($doctor['refrigerio_inicio']) && !empty($doctor['refrigerio_fin'])) {
        $inicioRefrigerio = fechaHoraReprogramacion($fecha, $doctor['refrigerio_inicio']);
        $finRefrigerio = fechaHoraReprogramacion($fecha, $doctor['refrigerio_fin']);
        if ($inicioRefrigerio === null || $finRefrigerio === null || $finRefrigerio <= $inicioRefrigerio) {
            return [];
        }
    } elseif (!empty($doctor['refrigerio_inicio']) || !empty($doctor['refrigerio_fin'])) {
        return [];
    }
    $turnos = [];
    for ($turno = $inicio; $turno->modify('+30 minutes') <= $fin; $turno = $turno->modify('+30 minutes')) {
        $finTurno = $turno->modify('+30 minutes');
        if ($turno <= $ahora || ($inicioRefrigerio !== null && $turno < $finRefrigerio && $finTurno > $inicioRefrigerio)) {
            continue;
        }
        $turnos[] = $turno->format('H:i:s');
    }
    return $turnos;
}

function esMismoTurnoReprogramacion(array $cita, int $doctorId, string $fecha, string $hora): bool
{
    return (int)$cita['doctor_id'] === $doctorId && $cita['fecha'] === $fecha && $cita['hora'] === $hora;
}

// Consulta informativa: la disponibilidad se vuelve a comprobar al confirmar.
function horariosDisponiblesReprogramacionServicio(mysqli $conexion, array $cita, array $doctor, string $fecha, array $opcionesPorClave): array
{
    if (!mismaEspecialidadReprogramacion($cita['especialidad'], $doctor['especialidad'], $opcionesPorClave)) {
        return [];
    }
    $citaId = (int)$cita['id'];
    $pacienteId = (int)$cita['paciente_id'];
    $doctorId = (int)$doctor['id'];
    $especialidad = especialidadCanonicaReprogramacion($doctor['especialidad'], $opcionesPorClave);
    $restricciones = resumenRestriccionesReserva(citasPacienteDelDia($conexion, $pacienteId, $fecha, false, $citaId), $especialidad, $opcionesPorClave);
    if ($restricciones['misma_especialidad']) {
        return [];
    }
    $ocupadas = horasOcupadasReprogramacionDAO($conexion, $citaId, $doctorId, $pacienteId, $fecha);
    $disponibles = [];
    foreach (turnosValidosReprogramacion($doctor, $fecha) as $hora) {
        if (esMismoTurnoReprogramacion($cita, $doctorId, $fecha, $hora) || conflictoReserva($restricciones, $hora) !== null) {
            continue;
        }
        foreach ($ocupadas as $ocupada) {
            if (seCruzanTurnosReserva($hora, $ocupada)) {
                continue 2;
            }
        }
        $disponibles[] = $hora;
    }
    return $disponibles;
}

// La cita original se actualiza solo si todas las validaciones son satisfactorias.
// El administrador conserva la posibilidad de trasladar una cita pasada no terminal.
function reprogramarCitaServicio(mysqli $conexion, array $citaOriginal, int $rol, int $usuarioId, int $doctorId, string $fecha, string $hora, array $opcionesPorClave): array
{
    if (!autorizarReprogramacion($citaOriginal, $rol, $usuarioId)) {
        return [false, 'No se puede reprogramar esta cita.', 403];
    }
    if ($doctorId <= 0 || fechaHoraReprogramacion($fecha, $hora) === null) {
        return [false, 'Selecciona un doctor, fecha y hora válidos.', 400];
    }
    $transaccion = false;
    try {
        if (!$conexion->begin_transaction()) {
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }
        $transaccion = true;
        $origenId = (int)$citaOriginal['doctor_id'];
        $pacienteId = (int)$citaOriginal['paciente_id'];
        $citaId = (int)$citaOriginal['id'];
        // Orden estable de bloqueo: doctores, paciente, cita, cruces.
        $ids = array_values(array_unique([$origenId, $doctorId]));
        sort($ids, SORT_NUMERIC);
        $doctores = [];
        foreach ($ids as $id) {
            $doctores[$id] = bloquearDoctorReprogramacionDAO($conexion, $id);
        }
        $origen = $doctores[$origenId];
        $destino = $doctores[$doctorId];
        $paciente = bloquearPacienteReprogramacionDAO($conexion, $pacienteId);
        $cita = bloquearCitaReprogramacionDAO($conexion, $citaId);
        if (!$paciente || (int)$paciente['activo'] !== 1 || ($rol === 0 && (int)$paciente['usuario_id'] !== $usuarioId)) {
            throw new DomainException('No se puede reprogramar esta cita.', 403);
        }
        if (!$cita || (int)$cita['paciente_id'] !== $pacienteId || (int)$cita['doctor_id'] !== $origenId ||
            $cita['fecha'] !== $citaOriginal['fecha'] || $cita['hora'] !== $citaOriginal['hora'] ||
            $cita['estado'] !== $citaOriginal['estado']) {
            throw new DomainException('La cita cambió. Vuelve a consultar sus datos.', 409);
        }
        $ahora = ahoraReprogramacion();
        if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true) || ($rol === 0 && !puedeReprogramarCitaPaciente($cita, $ahora))) {
            throw new DomainException('Esta cita ya no se puede reprogramar.', 409);
        }
        if (!$origen || !$destino || (int)$destino['activo'] !== 1 ||
            !mismaEspecialidadReprogramacion($origen['especialidad'], $destino['especialidad'], $opcionesPorClave)) {
            throw new DomainException('Selecciona un doctor activo de la misma especialidad.', 409);
        }
        if (esMismoTurnoReprogramacion($cita, $doctorId, $fecha, $hora)) {
            throw new DomainException('Selecciona un turno diferente al actual.', 409);
        }
        if (!in_array($hora, turnosValidosReprogramacion($destino, $fecha, $ahora), true)) {
            throw new DomainException('El horario seleccionado no está disponible.', 409);
        }
        $especialidad = especialidadCanonicaReprogramacion($destino['especialidad'], $opcionesPorClave);
        $restricciones = resumenRestriccionesReserva(citasPacienteDelDia($conexion, $pacienteId, $fecha, true, $citaId), $especialidad, $opcionesPorClave);
        $conflicto = conflictoReserva($restricciones, $hora);
        if ($conflicto !== null) {
            throw new DomainException($conflicto, 409);
        }
        if (existeCruceReserva($conexion, $doctorId, $pacienteId, $fecha, $hora, $citaId)) {
            throw new DomainException('Ese turno ya está reservado. Selecciona otro.', 409);
        }
        // Revalidar el tiempo después de adquirir todos los bloqueos.
        $ahora = ahoraReprogramacion();
        if (fechaHoraReprogramacion($fecha, $hora) <= $ahora || ($rol === 0 && !puedeReprogramarCitaPaciente($cita, $ahora))) {
            throw new DomainException('El turno ya no se puede reprogramar.', 409);
        }
        if (!actualizarCitaReprogramacionDAO($conexion, $citaId, $doctorId, $fecha, $hora)) {
            throw new DomainException('No se pudo actualizar la cita.', 409);
        }
        if (!$conexion->commit()) {
            throw new RuntimeException('No se pudo confirmar la transacción.');
        }
        $transaccion = false;
        return [true, 'Cita reprogramada correctamente.', 200];
    } catch (Throwable $error) {
        if ($transaccion) {
            try {
                $conexion->rollback();
            } catch (Throwable $errorRollback) {
                error_log('Error al revertir reprogramación: ' . $errorRollback->getCode());
            }
        }
        if ($error instanceof DomainException) {
            return [false, $error->getMessage(), $error->getCode() === 403 ? 403 : 409];
        }
        error_log('Error de reprogramación: ' . $error->getCode());
        if ($error instanceof mysqli_sql_exception && in_array($error->getCode(), [1062, 1205, 1213], true)) {
            return [false, 'La disponibilidad cambió. Vuelve a consultar los horarios.', 409];
        }
        return [false, 'No se pudo reprogramar la cita. Inténtalo nuevamente.', 500];
    }
}
