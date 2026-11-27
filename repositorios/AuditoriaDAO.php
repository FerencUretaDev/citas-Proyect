<?php
function auditoriaInsertarDAO(mysqli $db, array $evento): int {
    $s = $db->prepare('INSERT INTO auditoria (usuario_id, rol, modulo, accion, entidad, entidad_id, resultado) VALUES (?, ?, ?, ?, ?, ?, ?)');
    try { $s->execute(array_values($evento)); return (int)$s->insert_id; } finally { $s->close(); }
}
function auditoriaCondiciones(array $f): array {
    $where=[]; $params=[];
    foreach (['usuario_id','rol','modulo','accion','resultado'] as $k) {
        if ($f[$k] !== '') { $where[]="a.$k = ?"; $params[]=$f[$k]; }
    }
    if ($f['desde'] !== '') { $where[]='a.fecha_evento >= ?'; $params[]=$f['desde'].' 00:00:00'; }
    if ($f['hasta'] !== '') { $where[]='a.fecha_evento <= ?'; $params[]=$f['hasta'].' 23:59:59'; }
    return [$where ? ' WHERE '.implode(' AND ', $where) : '', $params];
}
function auditoriaConsultarDAO(mysqli $db, array $f, int $limite, int $offset=0): array {
    [$where,$params]=auditoriaCondiciones($f);
    $limite=max(1,min(5001,$limite)); $offset=max(0,$offset);
    $s=$db->prepare('SELECT a.id,a.fecha_evento,a.usuario_id,a.rol,a.modulo,a.accion,a.entidad,a.entidad_id,a.resultado FROM auditoria a'.$where.' ORDER BY a.fecha_evento DESC,a.id DESC LIMIT '.$limite.' OFFSET '.$offset);
    try { $s->execute($params); return $s->get_result()->fetch_all(MYSQLI_ASSOC); } finally { $s->close(); }
}
function auditoriaResumenDAO(mysqli $db,array $f): array {
    [$where,$params]=auditoriaCondiciones($f);
    $s=$db->prepare("SELECT COUNT(*) total, COALESCE(SUM(a.resultado='exitoso'),0) exitosos, COALESCE(SUM(a.resultado='fallido'),0) fallidos, COALESCE(SUM(a.resultado='denegado'),0) denegados FROM auditoria a".$where);
    try {$s->execute($params); return $s->get_result()->fetch_assoc();} finally {$s->close();}
}
