<?php
require_once __DIR__.'/DirectorioDAO.php';
require_once dirname(__DIR__).'/servicios/FiltroCitasAdminService.php';

function condicionesHistorialPaciente(int $usuarioId,string $ahora,array $f): array {
    if($usuarioId<1 || $ahora==='') throw new InvalidArgumentException('Usuario o fecha no válida.');
    $cond=["p.usuario_id = ?", "p.activo = 1", "(c.estado IN ('atendida','cancelada') OR TIMESTAMP(c.fecha,c.hora) < ?)"];
    $tipos='is'; $params=[$usuarioId,$ahora];
    if($f['doctor']!=='') {
        $cond[]="(CONCAT_WS(' ',d.nombres,d.apellidos) LIKE ? ESCAPE '!' OR CONCAT_WS(' ',d.apellidos,d.nombres) LIKE ? ESCAPE '!')";
        $patron=patronDirectorio($f['doctor']); $tipos.='ss'; array_push($params,$patron,$patron);
    }
    foreach(['especialidad'=>'d.especialidad','estado'=>'c.estado','modalidad'=>'c.modalidad'] as $k=>$col) {
        if($f[$k]!=='') { $cond[]="$col = ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    if($f['pago']==='sin_pago') $cond[]='NOT EXISTS (SELECT 1 FROM pagos g WHERE g.cita_id=c.id)';
    elseif($f['pago']!=='') { $cond[]='EXISTS (SELECT 1 FROM pagos g WHERE g.cita_id=c.id AND g.estado=?)'; $tipos.='s'; $params[]=$f['pago']; }
    foreach(['desde'=>'>=','hasta'=>'<='] as $k=>$op) {
        if($f[$k]!=='') { $cond[]="c.fecha $op ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    return [implode(' AND ',$cond),$tipos,$params];
}

function buscarHistorialPaciente(mysqli $db,int $usuarioId,string $ahora,array $f,int $limite=20): array {
    if($limite<1 || $limite>20) throw new InvalidArgumentException('Límite no válido.');
    $base=' FROM citas c INNER JOIN pacientes p ON p.id=c.paciente_id INNER JOIN doctores d ON d.id=c.doctor_id WHERE ';
    [$where,$tipos,$params]=condicionesHistorialPaciente($usuarioId,$ahora,$f);
    $from=$base.$where;
    $resumen=['total'=>0,'atendida'=>0,'pendiente'=>0,'confirmada'=>0,'cancelada'=>0];
    foreach(consultarDirectorio($db,'SELECT c.estado,COUNT(*) AS total'.$from.' GROUP BY c.estado',$tipos,$params) as $r) {
        $n=(int)$r['total']; $resumen['total']+=$n;
        if(array_key_exists($r['estado'],ETIQUETAS_ESTADO_CITA)) $resumen[$r['estado']]=$n;
    }
    $pag=paginaDirectorio($resumen['total'],$f['pagina'],$limite);
    $sql='SELECT c.id,c.fecha,c.hora,c.modalidad,c.motivo,c.estado,d.nombres AS doctor_nombres,d.apellidos AS doctor_apellidos,d.especialidad,
        (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado'.$from.' ORDER BY c.fecha DESC,c.hora DESC,c.id DESC LIMIT ? OFFSET ?';
    $citas=consultarDirectorio($db,$sql,$tipos.'ii',[...$params,$pag['limite'],$pag['offset']]);
    [$w,$t,$p]=condicionesHistorialPaciente($usuarioId,$ahora,array_fill_keys(['doctor','especialidad','estado','modalidad','pago','desde','hasta'],''));
    $especialidades=array_column(consultarDirectorio($db,"SELECT DISTINCT d.especialidad".$base.$w." AND d.especialidad IS NOT NULL AND d.especialidad <> '' ORDER BY d.especialidad",$t,$p),'especialidad');
    return ['citas'=>$citas,'resumen'=>$resumen,'especialidades'=>$especialidades,'filtros'=>$f]+$pag;
}
