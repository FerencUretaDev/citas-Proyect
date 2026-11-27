<?php
require_once dirname(__DIR__).'/servicios/FiltroAusenciasService.php';
require_once __DIR__.'/DirectorioDAO.php';

function condicionesFiltroAusencias(array $f,string $hoy): array {
    if (!fechaValidaAusencia($hoy)) throw new DomainException('Fecha de referencia no válida.');
    $cond=['1=1']; $tipos=''; $p=[];
    if ($f['doctor']!=='') {
        $cond[]="(CONCAT_WS(' ',d.nombres,d.apellidos) LIKE ? ESCAPE '!' OR CONCAT_WS(' ',d.apellidos,d.nombres) LIKE ? ESCAPE '!' OR d.dni LIKE ? ESCAPE '!' OR d.cmp LIKE ? ESCAPE '!')";
        $patron=patronDirectorio($f['doctor']); $tipos.='ssss'; array_push($p,$patron,$patron,$patron,$patron);
    }
    foreach (['especialidad'=>'d.especialidad','motivo'=>'a.motivo'] as $k=>$col) {
        if ($f[$k]!=='') {$cond[]="$col = ?"; $tipos.='s'; $p[]=$f[$k];}
    }
    switch ($f['estado']) {
        case 'cancelada': $cond[]='a.cancelada = 1'; break;
        case 'programada': $cond[]='a.cancelada = 0 AND a.fecha_inicio > ?'; $tipos.='s'; $p[]=$hoy; break;
        case 'finalizada': $cond[]='a.cancelada = 0 AND a.fecha_fin < ?'; $tipos.='s'; $p[]=$hoy; break;
        case 'vigente': $cond[]='a.cancelada = 0 AND a.fecha_inicio <= ? AND a.fecha_fin >= ?'; $tipos.='ss'; array_push($p,$hoy,$hoy); break;
    }
    // Intervalos que se superponen, incluyendo los extremos.
    if ($f['desde']!=='') {$cond[]='a.fecha_fin >= ?'; $tipos.='s'; $p[]=$f['desde'];}
    if ($f['hasta']!=='') {$cond[]='a.fecha_inicio <= ?'; $tipos.='s'; $p[]=$f['hasta'];}
    return [implode(' AND ',$cond),$tipos,$p];
}
function baseFiltroAusencias(): string {return ' FROM ausencias_doctores a INNER JOIN doctores d ON d.id=a.doctor_id';}
function columnasFiltroAusencias(): string {
    return "a.*,d.nombres AS doctor_nombres,d.apellidos AS doctor_apellidos,d.especialidad,
        (SELECT COUNT(*) FROM citas c WHERE c.doctor_id=a.doctor_id AND c.fecha BETWEEN a.fecha_inicio AND a.fecha_fin AND c.estado IN ('pendiente','confirmada') AND a.cancelada=0) AS citas_afectadas";
}
function buscarAusenciasFiltradas(mysqli $db,array $f,string $hoy,int $limite=20): array {
    if ($limite<1||$limite>20) throw new DomainException('Límite no válido.');
    [$where,$tipos,$p]=condicionesFiltroAusencias($f,$hoy);
    $base=baseFiltroAusencias().' WHERE '.$where;
    $total=(int)consultarDirectorio($db,'SELECT COUNT(*) AS total'.$base,$tipos,$p)[0]['total'];
    $pag=paginaDirectorio($total,$f['pagina'],$limite);
    $filas=consultarDirectorio($db,'SELECT '.columnasFiltroAusencias().$base.' ORDER BY a.fecha_inicio DESC,a.id DESC LIMIT ? OFFSET ?',$tipos.'ii',[...$p,$pag['limite'],$pag['offset']]);
    return ['filas'=>$filas,'total'=>$total]+$pag;
}
function resumenAusenciasFiltradas(mysqli $db,string $hoy): array {
    // Indicadores generales, no limitados a la página ni al filtro.
    $sql="SELECT CASE WHEN a.cancelada=1 THEN 'cancelada' WHEN a.fecha_inicio > ? THEN 'programada' WHEN a.fecha_fin < ? THEN 'finalizada' ELSE 'vigente' END AS estado, COUNT(*) AS total,
        SUM((SELECT COUNT(*) FROM citas c WHERE c.doctor_id=a.doctor_id AND c.fecha BETWEEN a.fecha_inicio AND a.fecha_fin AND c.estado IN ('pendiente','confirmada') AND a.cancelada=0)) AS afectadas".baseFiltroAusencias().' GROUP BY estado';
    $r=['vigente'=>0,'programada'=>0,'finalizada'=>0,'cancelada'=>0,'citas_afectadas'=>0];
    foreach(consultarDirectorio($db,$sql,'ss',[$hoy,$hoy]) as $fila){$r[$fila['estado']]=(int)$fila['total'];$r['citas_afectadas']+=(int)$fila['afectadas'];}
    return $r;
}
function detalleAusenciaFiltrada(mysqli $db,int $id): ?array {
    return consultarDirectorio($db,'SELECT '.columnasFiltroAusencias().baseFiltroAusencias().' WHERE a.id=?','i',[$id])[0]??null;
}
