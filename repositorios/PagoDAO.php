<?php
function nuevoPagoCitaDAO(mysqli $db,int $cita,string $monto): void {
    $ref='cita-test-'.bin2hex(random_bytes(16));
    $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);
    $key=vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4));
    $s=$db->prepare("INSERT INTO pagos(cita_id,monto,referencia_externa,clave_idempotencia) VALUES(?,?,?,?)");
    try {$s->bind_param('isss',$cita,$monto,$ref,$key);$s->execute();} finally {$s->close();}
}
function pagoCitaDAO(mysqli $db,int $cita,bool $lock=false): ?array {
    $s=$db->prepare('SELECT pagos.*, TIMESTAMPDIFF(SECOND,fecha_registro,CURRENT_TIMESTAMP) AS edad_segundos FROM pagos WHERE cita_id=?'.($lock?' FOR UPDATE':''));
    try {$s->execute([$cita]);return $s->get_result()->fetch_assoc();} finally {$s->close();}
}
function citaPagoDAO(mysqli $db,int $cita,bool $lock=false): ?array {
    $s=$db->prepare('SELECT id,paciente_id,doctor_id,fecha,hora,modalidad,estado FROM citas WHERE id=?'.($lock?' FOR UPDATE':''));
    try {$s->execute([$cita]);return $s->get_result()->fetch_assoc();} finally {$s->close();}
}
function guardarOrdenPagoDAO(mysqli $db,int $id,string $orden,?string $url): void {
    $s=$db->prepare('UPDATE pagos SET id_orden_mp=?,url_checkout=? WHERE id=?');
    try {$s->execute([$orden,$url,$id]);} finally {$s->close();}
}
function actualizarPagoDAO(mysqli $db,int $id,string $estado,string $remoto,string $detalle,?string $idPago,?string $fechaPago): void {
    $s=$db->prepare('UPDATE pagos SET estado=?,estado_mp=?,detalle_estado=?,id_pago_mp=COALESCE(?,id_pago_mp),fecha_pago=COALESCE(fecha_pago,?) WHERE id=?');
    try {$s->execute([$estado,$remoto,$detalle,$idPago,$fechaPago,$id]);} finally {$s->close();}
}
function estadoCitaPagoDAO(mysqli $db,int $cita,string $estado): void {
    $s=$db->prepare("UPDATE citas SET estado=? WHERE id=? AND estado='pendiente'");
    try {$s->execute([$estado,$cita]);if($s->affected_rows!==1)throw new RuntimeException('Cita no pendiente.');} finally {$s->close();}
}
function propietarioCitaPagoDAO(mysqli $db,int $id,int $usuario): bool {
    $s=$db->prepare('SELECT id FROM pacientes WHERE id=? AND usuario_id=? AND activo=1');
    try {$s->execute([$id,$usuario]);return (bool)$s->get_result()->fetch_assoc();} finally {$s->close();}
}
