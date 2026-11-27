<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/repositorios/FiltroCitasAdminDAO.php';
function comprobarIntegracionCitasAdmin(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$base=' FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN doctores d ON d.id=c.doctor_id';
$total=(int)$conexion->query('SELECT COUNT(*)'.$base)->fetch_row()[0];
$r=buscarCitasAdmin($conexion,filtrosCitasAdmin([]));
comprobarIntegracionCitasAdmin($r['total']===$total&&count($r['filas'])===min(20,$total),'Conteo o tamaño incorrecto');
foreach (['estado'=>array_keys(ETIQUETAS_ESTADO_CITA),'modalidad'=>array_keys(FILTRO_MODALIDADES_CITAS),'pago'=>array_keys(FILTRO_PAGOS_CITAS)] as $k=>$valores) {
    foreach ($valores as $v) {
        $r=buscarCitasAdmin($conexion,filtrosCitasAdmin([$k=>$v]));
        $where=match($k){'estado'=>'c.estado=?','modalidad'=>'c.modalidad=?','pago'=>$v==='sin_pago'?'NOT EXISTS (SELECT 1 FROM pagos WHERE cita_id=c.id)':'EXISTS (SELECT 1 FROM pagos WHERE cita_id=c.id AND estado=?)'};
        $esperado=(int)consultarDirectorio($conexion,'SELECT COUNT(*) AS total'.$base.' WHERE '.$where,($k==='pago'&&$v==='sin_pago')?'':'s',($k==='pago'&&$v==='sin_pago')?[]:[$v])[0]['total'];
        comprobarIntegracionCitasAdmin($r['total']===$esperado,'Conteo incorrecto para '.$k);
        foreach ($r['filas'] as $fila) comprobarIntegracionCitasAdmin($k==='pago'?($v==='sin_pago'?$fila['pago_estado']===null:$fila['pago_estado']===$v):$fila[$k]===$v,'Filtro incorrecto');
    }
}
$ejemplo=$conexion->query('SELECT c.id,c.fecha,p.dni,d.cmp,d.especialidad,c.estado,c.modalidad'.$base.' ORDER BY c.id LIMIT 1')->fetch_assoc();
if ($ejemplo) {
    $r=buscarCitasAdmin($conexion,filtrosCitasAdmin(['paciente'=>$ejemplo['dni'],'doctor'=>$ejemplo['cmp']??'','especialidad'=>$ejemplo['especialidad'],'estado'=>$ejemplo['estado'],'modalidad'=>$ejemplo['modalidad'],'desde'=>$ejemplo['fecha'],'hasta'=>$ejemplo['fecha']]));
    comprobarIntegracionCitasAdmin($r['total']>0,'Sin resultados para combinación existente');
    foreach ($r['filas'] as $fila) comprobarIntegracionCitasAdmin($fila['dni']===$ejemplo['dni']&&$fila['fecha']===$ejemplo['fecha']&&$fila['estado']===$ejemplo['estado']&&$fila['modalidad']===$ejemplo['modalidad']&&$fila['especialidad']===$ejemplo['especialidad'],'Combinación incorrecta');
}
$r=buscarCitasAdmin($conexion,filtrosCitasAdmin(['paciente'=>"zz_%!'sin_coincidencia_27112026"]));
comprobarIntegracionCitasAdmin($r['total']===0,'Coincidencia inesperada');
// Prueba de paginación real con límite interno de dos para no crear citas artificiales.
if ($total>2) {
    $a=buscarCitasAdmin($conexion,filtrosCitasAdmin([]),'',0,'',2);
    $b=buscarCitasAdmin($conexion,filtrosCitasAdmin(['pagina'=>'2']),'',0,'',2);
    comprobarIntegracionCitasAdmin($b['pagina']===2&&count($b['filas'])===min(2,$total-2)&&!array_intersect(array_column($a['filas'],'id'),array_column($b['filas'],'id')),'Paginación incorrecta');
} else echo "Paginación real pendiente: menos de tres citas.\n";
foreach (['paciente','doctor'] as $tipo) {
    $col=$tipo==='paciente'?'c.paciente_id':'c.doctor_id';
    $id=(int)($conexion->query('SELECT '.$col.' AS id FROM citas c LIMIT 1')->fetch_assoc()['id']??0);
    if (!$id) continue;
    $ahora=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    $r=buscarCitasAdmin($conexion,filtrosCitasAdmin([]),$tipo,$id,$ahora);
    foreach($r['filas'] as $fila) comprobarIntegracionCitasAdmin((int)$fila[$tipo.'_id']===$id&&$fila['fecha'].' '.$fila['hora']>=$ahora&&in_array($fila['estado'],['pendiente','confirmada'],true),'Ámbito de ficha incorrecto');
}
echo "Pruebas de integración de filtros de citas administrativas: OK (solo lectura)\n";
