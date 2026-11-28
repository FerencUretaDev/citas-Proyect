<?php
// Exclusivamente en XAMPP. Comprueba el núcleo transaccional con rollback;
// no llama al servicio público de reserva (este confirma su propia transacción).
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){http_response_code(403);exit(1);}
if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1','::1'],true)||(getenv('DB_NAME')?:'sistema')!=='sistema'){fwrite(STDERR,"Entorno no local.\n");exit(1);}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/DisponibilidadListaEsperaService.php';
require_once dirname(__DIR__).'/servicios/PrioridadListaEsperaService.php';
function comprobarPrioridadEspera(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "OK: $msg\n";}
$tx=false;
try {
    if($conexion->query('SELECT DATABASE()')->fetch_row()[0]!=='sistema')throw new RuntimeException('Base inesperada.');
    $fecha='2026-12-07';$hora='08:00:00';
    if(fechaHoraReprogramacion($fecha,$hora)<=ahoraReprogramacion())throw new RuntimeException('La fecha de prueba pasó; no se modifica nada.');
    $conexion->begin_transaction();$tx=true;
    $d=consultarListaEsperaDAO($conexion,"SELECT id,especialidad,hora_inicio,hora_fin,refrigerio_inicio,refrigerio_fin FROM doctores WHERE id=3 AND cmp='CMP-TEST01' AND activo=1 FOR UPDATE",'',[])[0]??null;
    if(!$d||$d['hora_inicio']!=='08:00:00'||$d['hora_fin']!=='08:30:00'||$d['refrigerio_inicio']!==null||$d['refrigerio_fin']!==null)throw new RuntimeException('Doctor ficticio fuera de configuración.');
    $c=consultarListaEsperaDAO($conexion,"SELECT id FROM citas WHERE id=11 AND doctor_id=3 AND paciente_id=1 AND fecha='2026-12-07' AND hora='08:00:00' AND estado='pendiente' AND motivo='PRUEBA_MEJORA11' AND turno_activo=1 FOR UPDATE",'',[])[0]??null;
    $s=consultarListaEsperaDAO($conexion,"SELECT id FROM lista_espera WHERE id=14 AND paciente_id=3 AND doctor_id=3 AND fecha_solicitada='2026-12-07' AND estado='en_espera' AND cita_id IS NULL FOR UPDATE",'',[])[0]??null;
    if(!$c||!$s||consultarListaEsperaDAO($conexion,'SELECT cita_id FROM pagos WHERE cita_id=11 FOR UPDATE','',[]))throw new RuntimeException('Datos de prueba no reconocidos o pago asociado.');
    if(doctorTieneAusenciaDAO($conexion,3,$fecha,true))throw new RuntimeException('Doctor ausente.');
    [, $mapa]=cargarCatalogoEspecialidades($conexion);$esp=$mapa[claveEspecialidad($d['especialidad'])]??$d['especialidad'];
    $conexion->query("UPDATE citas SET estado='cancelada' WHERE id=11 AND estado='pendiente'");
    comprobarPrioridadEspera($conexion->affected_rows===1,'Liberación temporal del turno');
    comprobarPrioridadEspera(turnosLibresListaEspera($d,$fecha,horasDoctorListaEsperaDAO($conexion,3,$fecha,true),false,ahoraReprogramacion())===[$hora],'Turno libre detectado');
    $cola=candidatosPrioridadEspera($conexion,3,$fecha,$esp,$mapa,true,3);
    $primero=primerCompatiblePrioridadEspera($cola,$hora);
    comprobarPrioridadEspera((int)($primero['id']??0)===14,'Solicitud 14 es prioritaria');
    comprobarPrioridadEspera(!permiteTurnoPrioridadEspera($cola,2,$hora),'Paciente ajeno a la prioridad rechazado');
    comprobarPrioridadEspera(permiteTurnoPrioridadEspera($cola,3,$hora),'Paciente prioritario autorizado');
    comprobarPrioridadEspera(bloquearPacienteReserva($conexion,3)&&!existeCruceReserva($conexion,3,3,$fecha,$hora),'Paciente prioritario sin cruces');
    [$ok,,$id]=insertarReserva($conexion,3,3,$fecha,$hora,'presencial','PRUEBA_MEJORA11_PRIORIDAD');
    comprobarPrioridadEspera($ok&&$id>0,'Cita temporal creada sin pago');
    vincularReservaPrioridadEspera($conexion,14,3,3,$fecha,$id);
    $v=consultarListaEsperaDAO($conexion,'SELECT estado,cita_id,solicitud_activa FROM lista_espera WHERE id=14','',[])[0];
    comprobarPrioridadEspera($v['estado']==='reservado'&&(int)$v['cita_id']===$id&&$v['solicitud_activa']===null,'Vínculo y salida de cola en misma transacción');
    comprobarPrioridadEspera(desvincularEsperaReprogramada($conexion,$id,3,$fecha)===null,'Misma fecha y doctor conserva vínculo');
    // Verificar transición para un traslado a otro día, no llamar a servicio que confirma.
    comprobarPrioridadEspera(desvincularEsperaReprogramada($conexion,$id,3,'2026-12-08')===14,'Traslado a otra fecha desvincula solicitud original');
    $v=consultarListaEsperaDAO($conexion,'SELECT estado,cita_id FROM lista_espera WHERE id=14','',[])[0];
    comprobarPrioridadEspera($v['estado']==='cancelado'&&$v['cita_id']===null,'Solicitud original conserva historial cancelado');
    $conexion->rollback();$tx=false;
    comprobarPrioridadEspera((bool)consultarListaEsperaDAO($conexion,"SELECT id FROM citas WHERE id=11 AND estado='pendiente' AND turno_activo=1",'',[]),'Rollback conserva cita 11');
    comprobarPrioridadEspera((bool)consultarListaEsperaDAO($conexion,"SELECT id FROM lista_espera WHERE id=14 AND estado='en_espera' AND cita_id IS NULL",'',[]),'Rollback conserva solicitud 14');
    comprobarPrioridadEspera(!consultarListaEsperaDAO($conexion,'SELECT id FROM citas WHERE id=?','i',[$id]),'Rollback elimina cita temporal');
    echo "Integración prioridad: OK (sin citas ni solicitudes nuevas persistentes).\n";
} catch(Throwable $e){if($tx)$conexion->rollback();fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);}
