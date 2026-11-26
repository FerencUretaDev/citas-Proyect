<?php

require_once dirname(__DIR__) .
    '/repositorios/AusenciaDAO.php';

require_once dirname(__DIR__) .
    '/repositorios/ReprogramacionDAO.php';

const MOTIVOS_AUSENCIA = [
    'vacaciones' => 'Vacaciones',
    'descanso_medico' => 'Descanso medico',
    'permiso_personal' => 'Permiso personal',
    'capacitacion' => 'Capacitacion',
    'otros' => 'Otros'
];

// Obtener fecha y hora de Peru.
function ahoraAusencia(): DateTimeImmutable
{
    return new DateTimeImmutable(
        'now',
        new DateTimeZone('America/Lima')
    );
}

// Validar fechas.
function fechaValidaAusencia(string $fecha): bool
{
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $fecha)) {
        return false;
    }

    $objeto = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    return $objeto !== false &&
        $objeto->format('Y-m-d') === $fecha;
}

// Validar los datos de una ausencia.
function validarDatosAusencia(
    int $doctorId,
    string $fechaInicio,
    string $fechaFin,
    string $motivo,
    string $observaciones,
    string $hoy
): void {

    if ($doctorId <= 0) {
        throw new InvalidArgumentException(
            'Selecciona un doctor valido.'
        );
    }

    if (
        !fechaValidaAusencia($fechaInicio) ||
        !fechaValidaAusencia($fechaFin)
    ) {
        throw new InvalidArgumentException(
            'Las fechas ingresadas no son validas.'
        );
    }

    if ($fechaInicio < $hoy) {
        throw new InvalidArgumentException(
            'La ausencia no puede comenzar antes de hoy.'
        );
    }

    if ($fechaFin < $fechaInicio) {
        throw new InvalidArgumentException(
            'La fecha final no puede ser anterior a la inicial.'
        );
    }

    if (!array_key_exists($motivo, MOTIVOS_AUSENCIA)) {
        throw new InvalidArgumentException(
            'Selecciona un motivo valido.'
        );
    }

    if (!preg_match('/\A.{0,500}\z/us', $observaciones)) {
        throw new InvalidArgumentException(
            'Las observaciones no pueden superar 500 caracteres.'
        );
    }
}

// Determinar el estado de una ausencia.
function estadoAusencia(
    array $ausencia,
    string $hoy
): string {

    if ((int)$ausencia['cancelada'] === 1) {
        return 'cancelada';
    }

    if ($ausencia['fecha_inicio'] > $hoy) {
        return 'programada';
    }

    if ($ausencia['fecha_fin'] < $hoy) {
        return 'finalizada';
    }

    return 'vigente';
}

// Registrar una ausencia con control transaccional.
function registrarAusenciaServicio(
    mysqli $conexion,
    int $rol,
    int $usuarioId,
    int $doctorId,
    string $fechaInicio,
    string $fechaFin,
    string $motivo,
    string $observaciones
): int {

    if ($rol !== 2 || $usuarioId <= 0) {
        throw new DomainException(
            'Acceso no autorizado.',
            403
        );
    }

    validarDatosAusencia(
        $doctorId,
        $fechaInicio,
        $fechaFin,
        $motivo,
        $observaciones,
        ahoraAusencia()->format('Y-m-d')
    );

    if (!$conexion->begin_transaction()) {
        throw new RuntimeException(
            'No se pudo iniciar la transaccion.'
        );
    }

    try {

        // Utilizar el mismo bloqueo de las reservas.
        $doctor = bloquearDoctorReprogramacionDAO(
            $conexion,
            $doctorId
        );

        if (!$doctor || (int)$doctor['activo'] !== 1) {
            throw new DomainException(
                'El doctor no se encuentra activo.',
                409
            );
        }

        // Revalidar la fecha despues del bloqueo.
        validarDatosAusencia(
            $doctorId,
            $fechaInicio,
            $fechaFin,
            $motivo,
            $observaciones,
            ahoraAusencia()->format('Y-m-d')
        );

        // Evitar ausencias superpuestas.
        if (existeAusenciaDoctorDAO(
            $conexion,
            $doctorId,
            $fechaInicio,
            $fechaFin,
            true
        )) {
            throw new DomainException(
                'El doctor ya tiene una ausencia en ese periodo.',
                409
            );
        }

        $ausenciaId = insertarAusenciaDAO(
            $conexion,
            $doctorId,
            $fechaInicio,
            $fechaFin,
            $motivo,
            trim($observaciones),
            $usuarioId
        );

        if ($ausenciaId <= 0 || !$conexion->commit()) {
            throw new RuntimeException(
                'No se pudo registrar la ausencia.'
            );
        }

        return $ausenciaId;

    } catch (Throwable $error) {

        $conexion->rollback();
        throw $error;
    }
}

// Cancelar una ausencia registrada.
function cancelarAusenciaServicio(
    mysqli $conexion,
    int $rol,
    int $usuarioId,
    int $ausenciaId,
    int $doctorId
): void {

    if ($rol !== 2 || $usuarioId <= 0) {
        throw new DomainException(
            'Acceso no autorizado.',
            403
        );
    }

    if ($ausenciaId <= 0 || $doctorId <= 0) {
        throw new InvalidArgumentException(
            'La ausencia seleccionada no es valida.'
        );
    }

    if (!$conexion->begin_transaction()) {
        throw new RuntimeException(
            'No se pudo iniciar la transaccion.'
        );
    }

    try {

        $doctor = bloquearDoctorReprogramacionDAO(
            $conexion,
            $doctorId
        );

        if (!$doctor) {
            throw new DomainException(
                'No se encontro el doctor.',
                409
            );
        }

        $ahora = ahoraAusencia();

        $cancelada = cancelarAusenciaDAO(
            $conexion,
            $ausenciaId,
            $doctorId,
            $ahora->format('Y-m-d'),
            $ahora->format('Y-m-d H:i:s')
        );

        if (!$cancelada) {
            throw new DomainException(
                'La ausencia no existe, ya finalizo o fue cancelada.',
                409
            );
        }

        if (!$conexion->commit()) {
            throw new RuntimeException(
                'No se pudo cancelar la ausencia.'
            );
        }

    } catch (Throwable $error) {

        $conexion->rollback();
        throw $error;
    }
}