<?php
require_once dirname(__DIR__).'/servicios/FiltroCitasAdminService.php';
require_once __DIR__.'/DirectorioDAO.php';

function filtrosCitasDoctor(array $entrada): array {
    // Reutilizar validación de estados, fechas, texto y página. No aceptar ámbito desde el cliente.
    return filtrosCitasAdmin(array_intersect_key($entrada,array_flip(['paciente','estado','modalidad','desde','hasta','pagina'])));
}

function condicionesCitasDoctor(int $doctorId,array $f): array {
    if ($doctorId<1) throw new InvalidArgumentException('Doctor no válido.');
    $cond=['c.doctor_id = ?']; $tipos='i'; $params=[$doctorId];
    if ($f['paciente']!=='') {
        $cond[]="(CONCAT_WS(' ',p.nombres,p.apellidos) LIKE ? ESCAPE '!' OR CONCAT_WS(' ',p.apellidos,p.nombres) LIKE ? ESCAPE '!' OR p.dni LIKE ? ESCAPE '!')";
        $patron=patronDirectorio($f['paciente']); $tipos.='sss'; array_push($params,$patron,$patron,$patron);
    }
    foreach (['estado'=>'c.estado','modalidad'=>'c.modalidad'] as $k=>$col) {
        if ($f[$k]!=='') { $cond[]="$col = ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    foreach (['desde'=>'>=','hasta'=>'<='] as $k=>$op) {
        if ($f[$k]!=='') { $cond[]="c.fecha $op ?"; $tipos.='s'; $params[]=$f[$k]; }
    }
    return [implode(' AND ',$cond),$tipos,$params];
}

function buscarCitasDoctor(mysqli $db,int $doctorId,array $f,int $limite=20): array {
    if ($limite<1 || $limite>20) throw new InvalidArgumentException('Límite no válido.');
    [$where,$tipos,$params]=condicionesCitasDoctor($doctorId,$f);
    $from=' FROM citas c INNER JOIN pacientes p ON p.id=c.paciente_id WHERE '.$where;
    $total=(int)consultarDirectorio($db,'SELECT COUNT(*) AS total'.$from,$tipos,$params)[0]['total'];
    $pag=paginaDirectorio($total,$f['pagina'],$limite);
    $sql='SELECT c.id,c.doctor_id,c.fecha,c.hora,c.modalidad,c.estado,c.motivo,p.dni,p.nombres,p.apellidos,
        (SELECT g.estado FROM pagos g WHERE g.cita_id=c.id) AS pago_estado,
        (SELECT h.id FROM historias_clinicas h WHERE h.cita_id=c.id) AS historia_id'.$from.' ORDER BY c.fecha ASC,c.hora ASC,c.id ASC LIMIT ? OFFSET ?';
    $filas=consultarDirectorio($db,$sql,$tipos.'ii',[...$params,$pag['limite'],$pag['offset']]);
    return ['filas'=>$filas,'total'=>$total]+$pag;
}
