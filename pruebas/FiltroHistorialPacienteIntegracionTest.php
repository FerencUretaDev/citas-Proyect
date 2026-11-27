<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/HistorialCitasService.php';
function comprobarHistorialIntegracion(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function idsHistorialAvanzado(array $filas):array{return array_map('intval',array_column($filas,'id'));}
$ahora=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
$pacientes=$conexion->query('SELECT p.id,p.usuario_id FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE p.activo=1 AND u.rol=0 ORDER BY p.id')->fetch_all(MYSQLI_ASSOC);
if(!$pacientes)throw new RuntimeException('No hay pacientes activos para probar.');
$paginas=0;$combinaciones=0;
foreach($pacientes as $paciente){
    $uid=(int)$paciente['usuario_id'];$pid=(int)$paciente['id'];
    $ref=consultarDirectorio($conexion,'SELECT c.id,c.fecha,c.hora,c.estado,c.modalidad,d.nombres,d.apellidos,d.especialidad,g.estado AS pago_estado FROM citas c JOIN doctores d ON d.id=c.doctor_id LEFT JOIN pagos g ON g.cita_id=c.id WHERE c.paciente_id=? ORDER BY c.fecha DESC,c.hora DESC,c.id DESC','i',[$pid]);
    $ref=array_values(array_filter($ref,static fn($c)=>in_array($c['estado'],['atendida','cancelada'],true)||$c['fecha'].' '.$c['hora']<$ahora));
    $r=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado(['paciente_id'=>999999,'usuario_id'=>999999]));
    comprobarHistorialIntegracion($r['resumen']['total']===count($ref)&&idsHistorialAvanzado($r['citas'])===idsHistorialAvanzado(array_slice($ref,0,20)),'Ámbito, total u orden incorrecto');
    foreach(array_keys(ETIQUETAS_ESTADO_CITA) as $e){$n=count(array_filter($ref,static fn($c)=>$c['estado']===$e));comprobarHistorialIntegracion($r['resumen'][$e]===$n,'Resumen incorrecto');}
    foreach(['estado'=>array_keys(ETIQUETAS_ESTADO_CITA),'modalidad'=>array_keys(FILTRO_MODALIDADES_CITAS),'pago'=>array_keys(FILTRO_PAGOS_CITAS)] as $k=>$opciones){
        foreach($opciones as $v){
            $esperadas=array_values(array_filter($ref,static fn($c)=>$k==='pago'?($c['pago_estado']??'sin_pago')===$v:$c[$k]===$v));
            $r=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado([$k=>$v]));
            comprobarHistorialIntegracion($r['resumen']['total']===count($esperadas)&&idsHistorialAvanzado($r['citas'])===idsHistorialAvanzado(array_slice($esperadas,0,20)),'Filtro incorrecto: '.$k);
        }
    }
    if($ref){
        $e=$ref[0];$nombre=$e['nombres'].' '.$e['apellidos'];
        $r=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado(['doctor'=>$nombre,'especialidad'=>$e['especialidad'],'estado'=>$e['estado'],'modalidad'=>$e['modalidad'],'pago'=>$e['pago_estado']??'sin_pago','desde'=>$e['fecha'],'hasta'=>$e['fecha']]));
        comprobarHistorialIntegracion(in_array((int)$e['id'],idsHistorialAvanzado($r['citas']),true),'Combinación existente no encontrada');$combinaciones++;
    }
    $r=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado(['doctor'=>"zz_%!'sin_coincidencia_27112026"]));
    comprobarHistorialIntegracion($r['resumen']['total']===0,'Coincidencia inesperada');
    if(count($ref)>1){
        $a=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado([]),1);
        $b=buscarHistorialPaciente($conexion,$uid,$ahora,filtrosHistorialAvanzado(['pagina'=>'2']),1);
        comprobarHistorialIntegracion($b['pagina']===2 && idsHistorialAvanzado($a['citas'])===idsHistorialAvanzado(array_slice($ref,0,1)) && idsHistorialAvanzado($b['citas'])===idsHistorialAvanzado(array_slice($ref,1,1)) && $b['resumen']['total']===count($ref),'Paginación incorrecta');$paginas++;
    }
}
if(!$paginas)echo "Paginación real pendiente: historial insuficiente.\n";
if(!$combinaciones)echo "Filtros combinados pendientes: historial sin citas.\n";
if(count($pacientes)<2)echo "Aislamiento entre pacientes pendiente: solo una cuenta activa.\n";
echo "Pruebas de integración de filtros del historial del paciente: OK (solo lectura)\n";
