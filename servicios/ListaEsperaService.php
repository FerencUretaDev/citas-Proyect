<?php
require_once dirname(__DIR__).'/repositorios/ListaEsperaDAO.php';
require_once __DIR__.'/ReprogramacionService.php';
require_once dirname(__DIR__).'/repositorios/TarifaDAO.php';

function fechaValidaListaEspera(string $fecha, DateTimeImmutable $ahora): bool
{
    $dia = fechaHoraReprogramacion($fecha,'00:00:00');
    return $dia !== null && $fecha >= $ahora->setTimezone(new DateTimeZone('America/Lima'))->format('Y-m-d');
}

// Solo falta de cupos reales, no ausencia, día concluido o conflicto personal.
function situacionListaEspera(array $doctor, string $fecha, array $ocupadas, array $restricciones, bool $ausencia, DateTimeImmutable $ahora): string
{
    if (!fechaValidaListaEspera($fecha,$ahora)) return 'fecha_invalida';
    if ($ausencia) return 'ausencia';
    $turnos = turnosValidosReprogramacion($doctor,$fecha,$ahora);
    if (!$turnos) return 'sin_jornada';
    if ($restricciones['misma_especialidad']) return 'misma_especialidad';
    $posibles = array_values(array_filter($turnos,fn($h)=>conflictoReserva($restricciones,$h)===null));
    if (!$posibles) return 'conflicto_paciente';
    foreach ($turnos as $hora) {
        $libre = true;
        foreach ($ocupadas as $otra) {
            if (seCruzanTurnosReserva($hora,$otra)) { $libre=false; break; }
        }
        if ($libre) return 'hay_cupos'; // Aunque el paciente no pueda usar ese cupo.
    }
    return 'agenda_llena';
}

// $usuarioId debe provenir exclusivamente de la sesión autenticada (rol 0).
// No invocar dentro de otra transacción. No crea citas ni realiza pagos.
function inscribirListaEsperaServicio(mysqli $db, int $usuarioId, int $doctorId, string $fecha, string $modalidad): array
{
    if ($usuarioId<=0 || $doctorId<=0 || !fechaValidaListaEspera($fecha,ahoraReprogramacion()) || !in_array($modalidad,['presencial','virtual'],true)) {
        return ['ok'=>false,'codigo'=>'datos_invalidos'];
    }
    $tx=false;
    try {
        $db->begin_transaction(); $tx=true;
        $doctor=bloquearDoctorReserva($db,$doctorId);
        $paciente=$doctor ? pacienteListaEsperaDAO($db,$usuarioId,true) : null;
        if (!$doctor || !$paciente) { $db->rollback(); return ['ok'=>false,'codigo'=>'perfil_no_disponible']; }
        $pid=(int)$paciente['id'];
        if (solicitudActivaListaEsperaDAO($db,$pid,$doctorId,$fecha)) { $db->rollback(); return ['ok'=>false,'codigo'=>'duplicada']; }
        [, $mapa]=cargarCatalogoEspecialidades($db);
        $especialidad=$mapa[claveEspecialidad($doctor['especialidad'])] ?? $doctor['especialidad'];
        $ausencia=doctorTieneAusenciaDAO($db,$doctorId,$fecha,true);
        $restricciones=resumenRestriccionesReserva(citasPacienteDelDia($db,$pid,$fecha,true),$especialidad,$mapa);
        $estado=situacionListaEspera($doctor,$fecha,horasDoctorListaEsperaDAO($db,$doctorId,$fecha,true),$restricciones,$ausencia,ahoraReprogramacion());
        if ($estado!=='agenda_llena') { $db->rollback(); return ['ok'=>false,'codigo'=>$estado]; }
        if (obtenerTarifaCitaDAO($db,$modalidad)===null) { $db->rollback(); return ['ok'=>false,'codigo'=>'modalidad_no_disponible']; }
        $id=insertarListaEsperaDAO($db,$pid,$doctorId,$fecha,$modalidad);
        if ($id<=0 || !$db->commit()) throw new RuntimeException('No se confirmó la inscripción.');
        return ['ok'=>true,'codigo'=>'inscrita','id'=>$id];
    } catch (Throwable $e) {
        if ($tx) $db->rollback();
        $codigo=(int)$e->getCode();
        if ($codigo===1062) return ['ok'=>false,'codigo'=>'duplicada'];
        error_log('Lista de espera: error de inscripción. Código '.$codigo);
        return ['ok'=>false,'codigo'=>in_array($codigo,[1205,1213],true)?'reintentar':'error'];
    }
}

// Consulta informativa: nunca autoriza una inscripción sin repetir las comprobaciones en la transacción.
function puedeMostrarInscripcionListaEspera(mysqli $db, int $pacienteId, int $doctorId, array $doctor, string $fecha): bool
{
    [, $mapa] = cargarCatalogoEspecialidades($db);
    $especialidad = $mapa[claveEspecialidad($doctor['especialidad'])] ?? $doctor['especialidad'];
    $restricciones = resumenRestriccionesReserva(citasPacienteDelDia($db,$pacienteId,$fecha),$especialidad,$mapa);
    return situacionListaEspera($doctor,$fecha,horasDoctorListaEsperaDAO($db,$doctorId,$fecha),$restricciones,
        doctorTieneAusenciaDAO($db,$doctorId,$fecha),ahoraReprogramacion()) === 'agenda_llena'
        && !solicitudActivaListaEsperaDAO($db,$pacienteId,$doctorId,$fecha);
}

function datosInscripcionListaEspera(array $entrada, DateTimeImmutable $ahora): ?array
{
    $doctor = $entrada['doctor_id'] ?? null;
    $fecha = $entrada['fecha'] ?? null;
    $modalidad = $entrada['modalidad'] ?? null;
    if ((!is_string($doctor) && !is_int($doctor)) || !is_string($fecha) || !is_string($modalidad)) return null;
    $id = filter_var($doctor,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    if ($id===false || !fechaValidaListaEspera($fecha,$ahora) || !in_array($modalidad,['presencial','virtual'],true)) return null;
    return [$id,$fecha,$modalidad];
}

function mensajeListaEspera(string $codigo): string
{
    return [
        'inscrita'=>'Inscripción registrada. No se ha reservado ni cobrado una cita.',
        'duplicada'=>'Ya tienes una solicitud activa para ese doctor y fecha.',
        'hay_cupos'=>'La agenda tiene cupos. Vuelve a consultar los horarios antes de inscribirte.',
        'ausencia'=>'El doctor está ausente en esa fecha. Elige otra fecha.',
        'sin_jornada'=>'No quedan turnos de atención para esa fecha.',
        'misma_especialidad'=>'Ya tienes una cita de esa especialidad para ese día.',
        'conflicto_paciente'=>'Tus citas actuales no permiten un turno compatible ese día.',
        'fecha_invalida'=>'Selecciona una fecha válida, no pasada.',
        'datos_invalidos'=>'Selecciona un doctor, fecha y modalidad válidos.',
        'perfil_no_disponible'=>'El perfil solicitado no está disponible.',
        'modalidad_no_disponible'=>'La modalidad solicitada no está disponible.',
        'reintentar'=>'La agenda cambió mientras consultabas. Vuelve a intentarlo.',
        'cancelada'=>'Te retiraste de la lista de espera.',
        'no_cancelada'=>'No se pudo retirar la solicitud. Puede no estar activa o no pertenecerte.',
    ][$codigo] ?? 'No se pudo completar la operación. Intenta nuevamente.';
}
