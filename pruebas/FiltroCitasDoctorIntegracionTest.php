<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/repositorios/FiltroCitasDoctorDAO.php';
function comprobarIntegracionDoctor(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);}
$doctores=$conexion->query('SELECT DISTINCT doctor_id FROM citas ORDER BY doctor_id')->fetch_all(MYSQLI_ASSOC);
if(!$doctores)throw new RuntimeException('Se requiere al menos una cita para probar la integración.');
$paginasProbadas=0;
foreach($doctores as $d){
    $id=(int)$d['doctor_id'];
    $base=' FROM citas c JOIN pacientes p ON p.id=c.paciente_id WHERE c.doctor_id=?';
    $total=(int)consultarDirectorio($conexion,'SELECT COUNT(*) AS total'.$base,'i',[$id])[0]['total'];
    $r=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor(['doctor_id'=>$id+100000]));
    comprobarIntegracionDoctor($r['total']===$total&&count($r['filas'])===min(20,$total),'Conteo o ámbito');
    foreach($r['filas'] as $fila)comprobarIntegracionDoctor((int)$fila['doctor_id']===$id,'Cita ajena');
    foreach(['estado'=>array_keys(ETIQUETAS_ESTADO_CITA),'modalidad'=>array_keys(FILTRO_MODALIDADES_CITAS)] as $k=>$opciones){
        foreach($opciones as $v){
            $r=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor([$k=>$v]));
            $n=(int)consultarDirectorio($conexion,'SELECT COUNT(*) AS total'.$base.' AND c.'.$k.'=?','is',[$id,$v])[0]['total'];
            comprobarIntegracionDoctor($r['total']===$n,'Conteo por '.$k);
            foreach($r['filas'] as $fila)comprobarIntegracionDoctor((int)$fila['doctor_id']===$id&&$fila[$k]===$v,'Filtro incorrecto');
        }
    }
    $e=consultarDirectorio($conexion,'SELECT p.dni,c.fecha,c.estado,c.modalidad'.$base.' ORDER BY c.id LIMIT 1','i',[$id])[0];
    $r=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor(['paciente'=>$e['dni'],'estado'=>$e['estado'],'modalidad'=>$e['modalidad'],'desde'=>$e['fecha'],'hasta'=>$e['fecha']]));
    comprobarIntegracionDoctor($r['total']>0,'Combinación existente');
    foreach($r['filas'] as $fila)comprobarIntegracionDoctor((int)$fila['doctor_id']===$id&&$fila['dni']===$e['dni']&&$fila['fecha']===$e['fecha']&&$fila['estado']===$e['estado']&&$fila['modalidad']===$e['modalidad'],'Combinación incorrecta');
    $r=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor(['paciente'=>"zz_%!'sin_coincidencia_27112026"]));
    comprobarIntegracionDoctor($r['total']===0,'Coincidencia inesperada');
    if($total>1){
        $a=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor([]),1);
        $b=buscarCitasDoctor($conexion,$id,filtrosCitasDoctor(['pagina'=>'2']),1);
        comprobarIntegracionDoctor($b['pagina']===2&&count($b['filas'])===1&&!array_intersect(array_column($a['filas'],'id'),array_column($b['filas'],'id'))&&(int)$b['filas'][0]['doctor_id']===$id,'Paginación');$paginasProbadas++;
    }
}
if(!$paginasProbadas)echo "Paginación con datos reales pendiente: ningún doctor tiene dos citas.\n";
echo "Pruebas de integración de filtros de citas del doctor: OK (solo lectura)\n";
