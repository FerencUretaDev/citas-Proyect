<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/servicios/HistorialCitasService.php';
function comprobarHistorialAvanzado(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$f=filtrosHistorialAvanzado(['doctor'=>" %_' OR 1=1 --! ",'fecha_inicio'=>'2026-11-01','fecha_fin'=>'2026-11-30','estado'=>'atendida','modalidad'=>'presencial','pago'=>'aprobado','especialidad'=>'Cardiología','pagina'=>'2','usuario_id'=>999,'paciente_id'=>999]);
[$sql,$tipos,$p]=condicionesHistorialPaciente(3,'2026-11-27 12:00:00',$f);
comprobarHistorialAvanzado($f['pagina']===2 && !isset($f['usuario_id'],$f['paciente_id']) && $f['desde']==='2026-11-01','Normalización y ámbito');
comprobarHistorialAvanzado($p[0]===3 && $p[1]==='2026-11-27 12:00:00' && $p[2]==="%!%!_' OR 1=1 --!!%" && !str_contains($sql,'OR 1=1'),'Parámetros y comodines');
comprobarHistorialAvanzado(substr_count($sql,'?')===count($p)&&strlen($tipos)===count($p),'Tipos de parámetros');
comprobarHistorialAvanzado(str_contains($sql,'p.usuario_id = ?')&&str_contains($sql,'p.activo = 1')&&str_contains($sql,"(c.estado IN ('atendida','cancelada') OR TIMESTAMP(c.fecha,c.hora) < ?)"),'Regla historial y aislamiento');
[$sql,$t,$p]=condicionesHistorialPaciente(3,'2026-11-27 12:00:00',filtrosHistorialAvanzado(['pago'=>'sin_pago']));
comprobarHistorialAvanzado(str_contains($sql,'NOT EXISTS')&&count($p)===2,'Sin pago');
foreach([['doctor'=>['x']],['doctor'=>str_repeat('a',101)],['modalidad'=>'otra'],['pago'=>'otro'],['estado'=>'otro'],['desde'=>'2026-02-30'],['desde'=>'2026-12-01','hasta'=>'2026-11-01'],['pagina'=>'0']] as $in){
    try{filtrosHistorialAvanzado($in);}catch(DomainException $e){continue;}throw new RuntimeException('Filtro inválido aceptado');
}
comprobarHistorialAvanzado(paginaDirectorio(41,2)===['pagina'=>2,'paginas'=>3,'offset'=>20,'limite'=>20],'Paginación');
comprobarHistorialAvanzado(auditoriaEvento(['id'=>3,'rol'=>0],'citas','consultar','exitoso')===[3,0,'citas','consultar',null,null,'exitoso'],'Auditoría sin criterios privados');
echo "Pruebas aisladas de filtros del historial del paciente: OK\n";
