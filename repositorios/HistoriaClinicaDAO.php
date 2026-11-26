<?php
function hcPerfilActivo(mysqli $db, int $rol, int $usuario): ?int {
    if (!in_array($rol,[0,1],true)) return null;
    $tabla=$rol===1?'doctores':'pacientes';
    $s=$db->prepare("SELECT id FROM $tabla WHERE usuario_id=? AND activo=1 LIMIT 2");
    try { $s->execute([$usuario]); $r=$s->get_result(); return $r->num_rows===1?(int)$r->fetch_assoc()['id']:null; } finally {$s->close();}
}
function hcConsultar(mysqli $db,int $rol,int $perfil,?int $cita=null): array {
    if(!in_array($rol,[0,1],true)||$perfil<=0)return [];
    $campo=$rol===1?'c.doctor_id':'c.paciente_id';
    $detalle=$cita!==null?',h.antecedentes,h.alergias,h.evaluacion,h.diagnostico,h.tratamiento,h.recomendaciones,h.proximo_control,h.fecha_atencion':'';
    $sql="SELECT c.id AS cita_id,c.fecha,c.hora,c.modalidad,c.estado,c.motivo,
        CONCAT(p.nombres,' ',p.apellidos) AS paciente,CONCAT(d.nombres,' ',d.apellidos) AS doctor,d.especialidad,
        g.estado AS pago_estado,h.id AS historia_id$detalle
        FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN doctores d ON d.id=c.doctor_id
        LEFT JOIN pagos g ON g.cita_id=c.id LEFT JOIN historias_clinicas h ON h.cita_id=c.id
        WHERE $campo=? AND ".($rol===1?"(c.estado='confirmada' OR (c.estado='atendida' AND h.id IS NOT NULL))":"c.estado='atendida' AND h.id IS NOT NULL");
    $params=[$perfil]; if($cita!==null){$sql.=' AND c.id=?';$params[]=$cita;}
    $sql.=' ORDER BY c.fecha DESC,c.hora DESC,c.id DESC';
    $s=$db->prepare($sql);try{$s->execute($params);return $s->get_result()->fetch_all(MYSQLI_ASSOC);}finally{$s->close();}
}
function hcBloquearDoctor(mysqli $db,int $doctor,int $usuario): bool {
    $s=$db->prepare('SELECT id FROM doctores WHERE id=? AND usuario_id=? AND activo=1 FOR UPDATE');
    try{$s->execute([$doctor,$usuario]);return (bool)$s->get_result()->fetch_assoc();}finally{$s->close();}
}
function hcBloquearCita(mysqli $db,int $cita,int $doctor): ?array {
    $s=$db->prepare('SELECT id,estado,fecha,hora FROM citas WHERE id=? AND doctor_id=? FOR UPDATE');
    try{$s->execute([$cita,$doctor]);return $s->get_result()->fetch_assoc();}finally{$s->close();}
}
function hcExiste(mysqli $db,int $cita): bool {
    $s=$db->prepare('SELECT id FROM historias_clinicas WHERE cita_id=? FOR UPDATE');
    try{$s->execute([$cita]);return (bool)$s->get_result()->fetch_assoc();}finally{$s->close();}
}
function hcInsertar(mysqli $db,int $cita,array $v,string $ahora): void {
    $s=$db->prepare('INSERT INTO historias_clinicas(cita_id,antecedentes,alergias,evaluacion,diagnostico,tratamiento,recomendaciones,proximo_control,fecha_atencion) VALUES (?,?,?,?,?,?,?,?,?)');
    try{$s->execute([$cita,$v['antecedentes'],$v['alergias'],$v['evaluacion'],$v['diagnostico'],$v['tratamiento'],$v['recomendaciones'],$v['proximo_control'],$ahora]);}finally{$s->close();}
    $s=$db->prepare("UPDATE citas SET estado='atendida' WHERE id=? AND estado='confirmada'");
    try{$s->execute([$cita]);if($s->affected_rows!==1)throw new RuntimeException('No se pudo finalizar la cita.');}finally{$s->close();}
}
