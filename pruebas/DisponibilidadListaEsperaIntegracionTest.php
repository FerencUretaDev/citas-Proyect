<?php
// Exclusivamente XAMPP local. Prueba cambios de disponibilidad con rollback.
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){http_response_code(403);exit(1);}
if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1','::1'],true)||(getenv('DB_NAME')?:'sistema')!=='sistema'){fwrite(STDERR,"Entorno no local.\n");exit(1);}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/DisponibilidadListaEsperaService.php';
function comprobarDisponibilidadEspera(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "OK: $msg\n";}
$tx=false;
try {
    if($conexion->query('SELECT DATABASE()')->fetch_row()[0]!=='sistema')throw new RuntimeException('Base inesperada.');
    $conexion->begin_transaction();$tx=true;
    $doctor=consultarListaEsperaDAO($conexion,"SELECT id,hora_inicio,hora_fin,refrigerio_inicio,refrigerio_fin FROM doctores WHERE id=3 AND cmp='CMP-TEST01' AND activo=1 FOR UPDATE",'',[])[0]??null;
    if(!$doctor||$doctor['hora_inicio']!=='08:00:00'||$doctor['hora_fin']!=='08:30:00'||$doctor['refrigerio_inicio']!==null||$doctor['refrigerio_fin']!==null)throw new RuntimeException('Doctor ficticio fuera de configuración prevista.');
    $c=consultarListaEsperaDAO($conexion,"SELECT id FROM citas WHERE id=11 AND doctor_id=3 AND paciente_id=1 AND fecha='2026-12-07' AND hora='08:00:00' AND estado='pendiente' AND motivo='PRUEBA_MEJORA11' FOR UPDATE",'',[])[0]??null;
    $s=consultarListaEsperaDAO($conexion,"SELECT id,paciente_id,doctor_id,fecha_solicitada,modalidad,estado FROM lista_espera WHERE id=14 AND paciente_id=3 AND doctor_id=3 AND fecha_solicitada='2026-12-07' AND estado='en_espera' FOR UPDATE",'',[])[0]??null;
    if(!$c||!$s||consultarListaEsperaDAO($conexion,'SELECT cita_id FROM pagos WHERE cita_id=11 FOR UPDATE','',[]))throw new RuntimeException('No se reconoce la cita/solicitud de prueba o existe pago asociado. No se modifica nada.');
    $ahora=ahoraReprogramacion();$cache=[];
    comprobarDisponibilidadEspera(disponibilidadSolicitudListaEspera($conexion,$s,$ahora,$cache)==='Sin cupos disponibles','Agenda inicial llena');
    // Primero mover la cita temporalmente; no reactivar una cita cancelada.
    $otraFecha='2026-12-08';
    if(consultarListaEsperaDAO($conexion,"SELECT id FROM citas WHERE id<>11 AND fecha=? AND estado<>'cancelada' AND (doctor_id=3 OR paciente_id=1) FOR UPDATE",'s',[$otraFecha]))throw new RuntimeException('Fecha alternativa ocupada.');
    if(doctorTieneAusenciaDAO($conexion,3,$otraFecha,true))throw new RuntimeException('Fecha alternativa con ausencia.');
    $conexion->query("UPDATE citas SET fecha='2026-12-08' WHERE id=11 AND estado='pendiente'");
    comprobarDisponibilidadEspera($conexion->affected_rows===1,'Reprogramación temporal de cita ficticia');
    $cache=[];
    comprobarDisponibilidadEspera(disponibilidadSolicitudListaEspera($conexion,$s,$ahora,$cache)==='Hay cupo compatible','Cupo detectado tras reprogramación');
    $conexion->query("UPDATE citas SET fecha='2026-12-07' WHERE id=11 AND estado='pendiente'");
    $cache=[];
    comprobarDisponibilidadEspera(disponibilidadSolicitudListaEspera($conexion,$s,$ahora,$cache)==='Sin cupos disponibles','Agenda vuelve a estar llena');
    $conexion->query("UPDATE citas SET estado='cancelada' WHERE id=11 AND estado='pendiente'");
    comprobarDisponibilidadEspera($conexion->affected_rows===1,'Cancelación temporal de cita ficticia');
    $cache=[];
    comprobarDisponibilidadEspera(disponibilidadSolicitudListaEspera($conexion,$s,$ahora,$cache)==='Hay cupo compatible','Cupo detectado tras cancelación');
    $conexion->rollback();$tx=false;
    comprobarDisponibilidadEspera((bool)consultarListaEsperaDAO($conexion,"SELECT id FROM citas WHERE id=11 AND fecha='2026-12-07' AND estado='pendiente' AND turno_activo=1",'',[]),'Rollback: cita original conservada');
    comprobarDisponibilidadEspera((bool)consultarListaEsperaDAO($conexion,"SELECT id FROM lista_espera WHERE id=14 AND estado='en_espera' AND cita_id IS NULL",'',[]),'Solicitud 14 conservada');
    echo "Integración de disponibilidad: OK (sin cambios persistentes).\n";
} catch(Throwable $e){if($tx)$conexion->rollback();fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);}
