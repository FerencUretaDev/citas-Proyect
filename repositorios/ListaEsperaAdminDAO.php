<?php
require_once __DIR__.'/ListaEsperaDAO.php';
function condicionesListaEsperaAdmin(array $f,string $hoy): array {
    $c=[];$p=[];
    foreach (['paciente'=>'p','doctor'=>'d'] as $k=>$alias) {
        if ($f[$k]!=='') {
            $expr="(LOCATE(?, CONCAT_WS(' ', $alias.nombres, $alias.apellidos))>0 OR $alias.dni=?";
            $p[]=$f[$k];$p[]=$f[$k];
            if ($k==='doctor') {$expr.=' OR d.cmp=?';$p[]=$f[$k];}
            $c[]=$expr.')';
        }
    }
    if ($f['estado']==='vencido') {
        $c[]="(l.estado='vencido' OR (l.solicitud_activa=1 AND l.fecha_solicitada<?))";$p[]=$hoy;
    } elseif ($f['estado']!=='') {
        $c[]='l.estado=?';$p[]=$f['estado'];
        if (in_array($f['estado'],['en_espera','notificado'],true)) {$c[]='l.fecha_solicitada>=?';$p[]=$hoy;}
    }
    foreach (['desde'=>'>=','hasta'=>'<='] as $k=>$op) if ($f[$k]!=='') {$c[]="l.fecha_solicitada $op ?";$p[]=$f[$k];}
    return [$c?' WHERE '.implode(' AND ',$c):'',$p];
}
function consultarListaEsperaAdminDAO(mysqli $db,array $f,string $hoy,int $pagina,int $limite=20): array {
    $limite=max(1,min(100,$limite));
    [$where,$p]=condicionesListaEsperaAdmin($f,$hoy);
    $from=' FROM lista_espera l JOIN pacientes p ON p.id=l.paciente_id JOIN doctores d ON d.id=l.doctor_id';
    $tipos=str_repeat('s',count($p));
    $total=(int)consultarListaEsperaDAO($db,'SELECT COUNT(*) total'.$from.$where,$tipos,$p)[0]['total'];
    $paginas=max(1,(int)ceil($total/$limite));$pagina=max(1,min($pagina,$paginas));
    $offset=($pagina-1)*$limite;
    $sql="SELECT l.id,l.paciente_id,l.doctor_id,l.fecha_solicitada,l.modalidad,l.estado,l.cita_id,l.fecha_registro,CONCAT_WS(' ',p.nombres,p.apellidos) paciente,CONCAT_WS(' ',d.nombres,d.apellidos) doctor,d.especialidad,p.activo paciente_activo,d.activo doctor_activo".$from.$where.' ORDER BY l.fecha_solicitada ASC,l.fecha_registro ASC,l.id ASC LIMIT ? OFFSET ?';
    $filas=consultarListaEsperaDAO($db,$sql,$tipos.'ii',array_merge($p,[$limite,$offset]));
    return ['filas'=>$filas,'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
}
function cancelarListaEsperaAdminDAO(mysqli $db,int $id,string $hoy): bool {
    $s=$db->prepare("UPDATE lista_espera SET estado='cancelado' WHERE id=? AND solicitud_activa=1 AND fecha_solicitada>=?");
    try {$s->bind_param('is',$id,$hoy);$s->execute();return $s->affected_rows===1;}finally{$s->close();}
}
