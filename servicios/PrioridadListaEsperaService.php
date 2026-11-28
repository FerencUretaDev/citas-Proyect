<?php
require_once dirname(__DIR__).'/repositorios/ListaEsperaDAO.php';
require_once __DIR__.'/ReservaService.php';
require_once __DIR__.'/AuditoriaService.php';

// La cola se ordena en SQL por fecha de inscripción e ID. Para cada turno,
// se elige el primer solicitante activo sin cruces ni cita de la especialidad.
function primerCompatiblePrioridadEspera(array $cola, string $hora): ?array
{
    foreach ($cola as $s) {
        if (!empty($s['elegible']) && conflictoReserva($s['restricciones'], $hora) === null) return $s;
    }
    return null;
}

// En escrituras, llamar dentro de la transacción con el doctor y el paciente
// de la operación ya bloqueados. Las lecturas de la cola y de los conflictos
// son actuales y bloqueantes. Los posibles deadlocks se revierten en el servicio llamante.
function candidatosPrioridadEspera(mysqli $db, int $doctorId, string $fecha, string $especialidad, array $mapa, bool $lock = false, int $pacienteOperacion = 0, int $citaExcluir = 0): array
{
    $sql = "SELECT id,paciente_id,modalidad FROM lista_espera WHERE doctor_id=? AND fecha_solicitada=? AND solicitud_activa=1 ORDER BY fecha_registro,id";
    $cola = consultarListaEsperaDAO($db, $sql.($lock?' FOR UPDATE':''), 'is', [$doctorId,$fecha]);
    $resultado=[];
    foreach ($cola as $s) {
        $pid=(int)$s['paciente_id'];
        $perfil=consultarListaEsperaDAO($db,'SELECT activo FROM pacientes WHERE id=?'.($lock?' FOR UPDATE':''),'i',[$pid])[0]??null;
        if (!$perfil || (int)$perfil['activo']!==1) continue;
        // La modalidad de la solicitud es una preferencia; la cola es por doctor/fecha.
        $excluir=$pid===$pacienteOperacion?$citaExcluir:0;
        $s['restricciones']=resumenRestriccionesReserva(citasPacienteDelDia($db,$pid,$fecha,$lock,$excluir),$especialidad,$mapa);
        $s['elegible']=true;
        $resultado[]=$s;
    }
    return $resultado;
}

function permiteTurnoPrioridadEspera(array $cola,int $pacienteId,string $hora): bool
{
    $primero=primerCompatiblePrioridadEspera($cola,$hora);
    return $primero===null || (int)$primero['paciente_id']===$pacienteId;
}

// Debe ejecutarse dentro de la transacción que crea/mueve la cita.
function vincularReservaPrioridadEspera(mysqli $db,int $solicitudId,int $pacienteId,int $doctorId,string $fecha,int $citaId): void
{
    $s=$db->prepare("UPDATE lista_espera SET estado='reservado',cita_id=? WHERE id=? AND paciente_id=? AND doctor_id=? AND fecha_solicitada=? AND solicitud_activa=1");
    try {
        $s->bind_param('iiiis',$citaId,$solicitudId,$pacienteId,$doctorId,$fecha);
        $s->execute();
        if ($s->affected_rows!==1) throw new RuntimeException('La solicitud cambió durante la reserva.');
    } finally {$s->close();}
}

// Si una cita vinculada cambia de doctor/fecha, la solicitud original deja de
// representar el turno solicitado. Se conserva como cancelada y sin vínculo.
// Una reprogramación dentro del mismo doctor y fecha conserva la asociación.
function desvincularEsperaReprogramada(mysqli $db,int $citaId,int $doctorId,string $fecha): ?int
{
    $r=consultarListaEsperaDAO($db,"SELECT id FROM lista_espera WHERE cita_id=? AND estado='reservado' AND (doctor_id<>? OR fecha_solicitada<>?) FOR UPDATE",'iis',[$citaId,$doctorId,$fecha])[0]??null;
    if (!$r) return null;
    $id=(int)$r['id'];
    $s=$db->prepare("UPDATE lista_espera SET estado='cancelado',cita_id=NULL WHERE id=? AND cita_id=? AND estado='reservado'");
    try {$s->bind_param('ii',$id,$citaId);$s->execute();if($s->affected_rows!==1)throw new RuntimeException('No se pudo desvincular la solicitud anterior.');}
    finally {$s->close();}
    return $id;
}
