<?php
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){http_response_code(403);exit(1);}
if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1','::1'],true)||(getenv('DB_NAME')?:'sistema')!=='sistema'){fwrite(STDERR,"Entorno no local.\n");exit(1);}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/VencimientoListaEsperaService.php';
function comprobarVencimiento(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "OK: $msg\n";}
$tx=false;
try {
    if($conexion->query('SELECT DATABASE()')->fetch_row()[0]!=='sistema')throw new RuntimeException('Base inesperada.');
    verificarMotorVencimientoListaEspera($conexion);
    $ahora=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
    $hoy=fechaCorteListaEspera($ahora);$ayer=$ahora->modify('-1 day')->format('Y-m-d');$manana=$ahora->modify('+1 day')->format('Y-m-d');
    $conexion->begin_transaction();$tx=true;
    $d=consultarListaEsperaDAO($conexion,"SELECT id FROM doctores WHERE id=3 AND cmp='CMP-TEST01' AND activo=1 FOR UPDATE",'',[])[0]??null;
    if(!$d)throw new RuntimeException('Doctor ficticio no disponible.');
    $p=consultarListaEsperaDAO($conexion,'SELECT id FROM pacientes WHERE id IN (1,2,3) AND activo=1 ORDER BY id FOR UPDATE','',[]);
    if(count($p)!==3)throw new RuntimeException('Pacientes de prueba no disponibles.');
    $ids=[];
    $ids[]=$pasada=insertarListaEsperaDAO($conexion,1,3,$ayer,'presencial');
    $ids[]=$notificada=insertarListaEsperaDAO($conexion,2,3,$ayer,'presencial');
    $ids[]=$cancelada=insertarListaEsperaDAO($conexion,3,3,$ayer,'presencial');
    $ids[]=$actual=insertarListaEsperaDAO($conexion,1,3,$hoy,'presencial');
    $ids[]=$futura=insertarListaEsperaDAO($conexion,1,3,$manana,'presencial');
    foreach([[$notificada,'notificado'],[$cancelada,'cancelado']] as [$id,$estado]){
        $s=$conexion->prepare('UPDATE lista_espera SET estado=? WHERE id=?');try{$s->bind_param('si',$estado,$id);$s->execute();}finally{$s->close();}
    }
    comprobarVencimiento(vencerSolicitudFechaPasada($conexion,$pasada,$ahora),'Solicitud pasada activa vence');
    comprobarVencimiento(vencerSolicitudFechaPasada($conexion,$notificada,$ahora),'Solicitud pasada notificada vence');
    comprobarVencimiento(!vencerSolicitudFechaPasada($conexion,$pasada,$ahora),'Segunda ejecución no repite vencimiento');
    comprobarVencimiento(!vencerSolicitudFechaPasada($conexion,$cancelada,$ahora),'Solicitud cancelada se conserva');
    comprobarVencimiento(!vencerSolicitudFechaPasada($conexion,$actual,$ahora),'Solicitud de hoy se conserva');
    comprobarVencimiento(!vencerSolicitudFechaPasada($conexion,$futura,$ahora),'Solicitud futura se conserva');
    $r=consultarListaEsperaDAO($conexion,'SELECT estado,solicitud_activa FROM lista_espera WHERE id=?','i',[$pasada])[0];
    comprobarVencimiento($r['estado']==='vencido'&&$r['solicitud_activa']===null,'Vencimiento persistente y salida de cola');
    $audit=consultarListaEsperaDAO($conexion,"SELECT id,usuario_id FROM auditoria WHERE modulo='lista_espera' AND accion='vencer' AND entidad='solicitud_espera' AND entidad_id IN (?,?)",'ii',[$pasada,$notificada]);
    comprobarVencimiento(count($audit)===2&&$audit[0]['usuario_id']===null&&$audit[1]['usuario_id']===null,'Dos eventos de auditoría sin duplicados');
    $conexion->rollback();$tx=false;
    foreach($ids as $id)if(consultarListaEsperaDAO($conexion,'SELECT id FROM lista_espera WHERE id=?','i',[$id]))throw new RuntimeException('Solicitud temporal persistente.');
    foreach($audit as $a)if(consultarListaEsperaDAO($conexion,'SELECT id FROM auditoria WHERE id=?','i',[(int)$a['id']]))throw new RuntimeException('Auditoría temporal persistente.');
    comprobarVencimiento(true,'Rollback de solicitudes y auditoría');
    echo "Integración vencimiento: OK (sin cambios persistentes).\n";
} catch(Throwable $e){if($tx)$conexion->rollback();fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);}
