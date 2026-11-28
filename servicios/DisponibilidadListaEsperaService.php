<?php
require_once __DIR__.'/ListaEsperaService.php';

// Consulta informativa: no reserva, no notifica y no modifica la cola.
function turnosLibresListaEspera(array $doctor, string $fecha, array $ocupadas, bool $ausencia, DateTimeImmutable $ahora): array
{
    if ($ausencia || !fechaValidaListaEspera($fecha,$ahora)) return [];
    return array_values(array_filter(turnosValidosReprogramacion($doctor,$fecha,$ahora), static function(string $hora) use ($ocupadas): bool {
        foreach ($ocupadas as $otra) if (seCruzanTurnosReserva($hora,$otra)) return false;
        return true;
    }));
}

function turnosCompatiblesListaEspera(array $libres,array $restricciones): array
{
    return array_values(array_filter($libres,static fn(string $hora): bool=>conflictoReserva($restricciones,$hora)===null));
}

function disponibilidadSolicitudListaEspera(mysqli $db,array $s,DateTimeImmutable $ahora,array &$cache): string
{
    if (!in_array($s['estado'],['en_espera','notificado'],true)) return '—';
    if (!fechaValidaListaEspera($s['fecha_solicitada'],$ahora)) return 'Fecha vencida';
    $did=(int)$s['doctor_id'];$pid=(int)$s['paciente_id'];$fecha=$s['fecha_solicitada'];
    $key=$did.'/'.$fecha;
    if (!isset($cache['agenda'][$key])) {
        $doctor=consultarListaEsperaDAO($db,'SELECT especialidad,hora_inicio,hora_fin,refrigerio_inicio,refrigerio_fin FROM doctores WHERE id=? AND activo=1','i',[$did])[0]??null;
        $libres=$doctor?turnosLibresListaEspera($doctor,$fecha,horasDoctorListaEsperaDAO($db,$did,$fecha),doctorTieneAusenciaDAO($db,$did,$fecha),$ahora):[];
        $cache['agenda'][$key]=['doctor'=>$doctor,'libres'=>$libres];
    }
    $agenda=$cache['agenda'][$key];
    if (!$agenda['doctor']) return 'Doctor no disponible';
    if (!consultarListaEsperaDAO($db,'SELECT id FROM pacientes WHERE id=? AND activo=1','i',[$pid])) return 'Paciente no disponible';
    if (!$agenda['libres']) return 'Sin cupos disponibles';
    $cache['tarifas']??=listarTarifasCitasDAO($db);
    if (!isset($cache['tarifas'][$s['modalidad']])) return 'Modalidad no disponible';
    if (!isset($cache['mapa'])) { [, $cache['mapa']]=cargarCatalogoEspecialidades($db); }
    $mapa=$cache['mapa'];
    $especialidad=especialidadCanonicaReprogramacion($agenda['doctor']['especialidad'],$mapa);
    $r=resumenRestriccionesReserva(citasPacienteDelDia($db,$pid,$fecha),$especialidad,$mapa);
    $compatibles=turnosCompatiblesListaEspera($agenda['libres'],$r);
    if (!$compatibles) return 'Sin cupos compatibles con el paciente';
    if (!isset($cache['prioridad'][$key])) $cache['prioridad'][$key]=candidatosPrioridadEspera($db,$did,$fecha,$especialidad,$mapa);
    foreach ($compatibles as $hora) if (permiteTurnoPrioridadEspera($cache['prioridad'][$key],$pid,$hora)) return 'Hay cupo compatible';
    return 'Cupo con prioridad para otro paciente';
}
