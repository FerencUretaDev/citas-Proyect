<?php
require_once dirname(__DIR__).'/servicios/FiltroCitasAdminService.php';
require_once __DIR__.'/DirectorioDAO.php';

function condicionesCitasAdmin(array $f, string $tipoFicha='', int $idFicha=0, string $ahora=''): array {
    $cond=['1=1']; $tipos=''; $params=[];
    if ($tipoFicha!=='' || $idFicha!==0) {
        if (!in_array($tipoFicha,['paciente','doctor'],true) || $idFicha<1 || $ahora==='') throw new DomainException('Ficha no válida.');
        $col=$tipoFicha==='paciente'?'c.paciente_id':'c.doctor_id';
        $cond[]="$col = ? AND TIMESTAMP(c.fecha,c.hora) >= ? AND c.estado IN ('pendiente','confirmada')";
        $tipos.='is'; array_push($params,$idFicha,$ahora);
    }
    foreach (['paciente'=>'p','doctor'=>'d'] as $k=>$a) {
        if ($f[$k]==='') continue;
        $campos=["CONCAT_WS(' ', $a.nombres, $a.apellidos)","CONCAT_WS(' ', $a.apellidos, $a.nombres)","$a.dni"];
        if ($k==='doctor') $campos[]='d.cmp';
        $cond[]='('.implode(' OR ',array_map(static fn($c)=>"$c LIKE ? ESCAPE '!'",$campos)).')';
        foreach ($campos as $_) { $tipos.='s'; $params[]=patronDirectorio($f[$k]); }
    }
    foreach (['especialidad'=>'d.especialidad','estado'=>'c.estado','modalidad'=>'c.modalidad'] as $k=>$col) {
        if ($f[$k]!=='') { $cond[]="$col = ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    if ($f['pago']==='sin_pago') $cond[]='NOT EXISTS (SELECT 1 FROM pagos g WHERE g.cita_id=c.id)';
    elseif ($f['pago']!=='') { $cond[]='EXISTS (SELECT 1 FROM pagos g WHERE g.cita_id=c.id AND g.estado=?)'; $tipos.='s'; $params[]=$f['pago']; }
    foreach (['desde'=>'>=','hasta'=>'<='] as $k=>$op) {
        if ($f[$k]!=='') { $cond[]="c.fecha $op ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    return [implode(' AND ',$cond),$tipos,$params];
}

function buscarCitasAdmin(mysqli $db,array $f,string $tipoFicha='',int $idFicha=0,string $ahora='',int $limite=20): array {
    if ($limite<1 || $limite>20) throw new InvalidArgumentException('Límite no válido.');
    [$where,$tipos,$params]=condicionesCitasAdmin($f,$tipoFicha,$idFicha,$ahora);
    $from=' FROM citas c INNER JOIN pacientes p ON p.id=c.paciente_id INNER JOIN doctores d ON d.id=c.doctor_id WHERE '.$where;
    $total=(int)consultarDirectorio($db,'SELECT COUNT(*) AS total'.$from,$tipos,$params)[0]['total'];
    $pag=paginaDirectorio($total,$f['pagina'],$limite);
    $sql='SELECT c.id, (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado, c.fecha,c.hora,c.modalidad,c.motivo,c.estado,
        c.paciente_id,c.doctor_id,p.nombres AS paciente_nombres,p.apellidos AS paciente_apellidos,p.dni,
        d.nombres AS doctor_nombres,d.apellidos AS doctor_apellidos,d.especialidad'.$from.' ORDER BY c.fecha DESC,c.hora DESC,c.id DESC LIMIT ? OFFSET ?';
    $filas=consultarDirectorio($db,$sql,$tipos.'ii',[...$params,$pag['limite'],$pag['offset']]);
    return ['filas'=>$filas,'total'=>$total]+$pag;
}
