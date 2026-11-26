<?php
// Analítica financiera: los filtros de transacciones no afectan a las citas.
function fechaDashboard($v): string {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)) return '';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : '';
}
function filtrosDashboardPago(array $q): array {
    $e = $q['ambiente_pago'] ?? 'test';
    $m = $q['modalidad_pago'] ?? '';
    $s = $q['estado_pago'] ?? '';
    return ['ambiente' => in_array($e,['test','production'],true) ? $e : 'test',
        'modalidad' => in_array($m,['presencial','virtual'],true) ? $m : '',
        'estado' => in_array($s,['pendiente','en_proceso','aprobado','rechazado','cancelado','expirado','reembolsado','revision'],true) ? $s : '',
        'desde' => fechaDashboard($q['pago_desde'] ?? ''), 'hasta' => fechaDashboard($q['pago_hasta'] ?? '')];
}
function consultaDashboardPago(mysqli $db, string $sql, array $f, bool $filtrarEstado=true): array {
    $where = ["g.ambiente = ?", "g.moneda = 'PEN'"];
    $p = [$f['ambiente']];
    if ($f['modalidad'] !== '') { $where[] = 'c.modalidad = ?'; $p[] = $f['modalidad']; }
    if ($filtrarEstado && $f['estado'] !== '') { $where[] = 'g.estado = ?'; $p[] = $f['estado']; }
    if ($f['desde'] !== '') { $where[] = 'COALESCE(g.fecha_pago,g.fecha_registro) >= ?'; $p[] = $f['desde'].' 00:00:00'; }
    if ($f['hasta'] !== '') { $where[] = 'COALESCE(g.fecha_pago,g.fecha_registro) <= ?'; $p[] = $f['hasta'].' 23:59:59'; }
    $sql = str_replace('{base}', ' FROM pagos g JOIN citas c ON c.id=g.cita_id JOIN pacientes p ON p.id=c.paciente_id JOIN doctores d ON d.id=c.doctor_id WHERE '.implode(' AND ',$where).' ', $sql);
    $stmt=$db->prepare($sql);
    try { $stmt->execute($p); return $stmt->get_result()->fetch_all(MYSQLI_ASSOC); } finally { $stmt->close(); }
}
function analiticaDashboardPago(mysqli $db, array $f): array {
    $resumen=consultaDashboardPago($db,"SELECT g.estado, COUNT(*) cantidad, SUM(g.monto) monto {base} GROUP BY g.estado",$f,false);
    $modalidades=consultaDashboardPago($db,"SELECT c.modalidad, SUM(g.monto) monto {base} AND g.estado='aprobado' GROUP BY c.modalidad",$f,false);
    $meses=consultaDashboardPago($db,"SELECT DATE_FORMAT(g.fecha_pago,'%Y-%m') mes, SUM(g.monto) monto {base} AND g.estado='aprobado' AND g.fecha_pago IS NOT NULL GROUP BY mes ORDER BY mes",$f,false);
    $ahora=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
    // Fechas generadas internamente, sin entrada del usuario.
    $dia=$ahora->format('Y-m-d'); $mes=$ahora->format('Y-m'); $anio=$ahora->format('Y');
    $periodos=consultaDashboardPago($db,"SELECT COALESCE(SUM(CASE WHEN DATE(g.fecha_pago)='$dia' THEN g.monto ELSE 0 END),0) hoy, COALESCE(SUM(CASE WHEN DATE_FORMAT(g.fecha_pago,'%Y-%m')='$mes' THEN g.monto ELSE 0 END),0) mes, COALESCE(SUM(CASE WHEN YEAR(g.fecha_pago)='$anio' THEN g.monto ELSE 0 END),0) anio {base} AND g.estado='aprobado'",$f,false)[0];
    $filas=filasDashboardPago($db,$f,true);
    return compact('resumen','modalidades','meses','periodos','filas');
}
function filasDashboardPago(mysqli $db,array $f, bool $limitado): array {
    return consultaDashboardPago($db,"SELECT g.cita_id, c.modalidad, CONCAT(p.nombres,' ',p.apellidos) paciente, CONCAT(d.nombres,' ',d.apellidos) doctor, g.monto,g.estado,g.fecha_registro,g.fecha_pago,g.id_pago_mp {base} ORDER BY g.fecha_registro DESC,g.id DESC".($limitado?' LIMIT 50':''),$f);
}
function exportarDashboardPago(mysqli $db,array $f): void {
    $filas=filasDashboardPago($db,$f,false);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pagos_'.$f['ambiente'].'_'.date('Ymd_His').'.csv"');
    header('Cache-Control: no-store');
    $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Cita','Modalidad','Paciente','Doctor','Monto PEN','Estado','Registro','Acreditación','Operación'], ';','"','');
    foreach($filas as $r){$r=array_map(static function($v){$v=(string)$v;return preg_match('/^[\s]*[=+@\-\t\r]/u',$v)?"'".$v:$v;},$r); fputcsv($out,array_values($r),';','"','');} fclose($out);
}
