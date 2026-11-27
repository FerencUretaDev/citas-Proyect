<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/repositorios/FiltroCitasDoctorDAO.php';
function comprobarDoctor(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);}
$f=filtrosCitasDoctor(['paciente'=>"001%_!' OR 1=1",'estado'=>'confirmada','modalidad'=>'virtual','desde'=>'2026-11-01','hasta'=>'2026-12-01','pagina'=>'2','doctor_id'=>99,'doctor'=>'otro','pago'=>'aprobado']);
[$sql,$tipos,$params]=condicionesCitasDoctor(7,$f);
comprobarDoctor(str_starts_with($sql,'c.doctor_id = ? AND (')&&$params[0]===7&&!in_array(99,$params,true)&&$f['doctor']===''&&$f['pago']==='','Ámbito manipulado');
comprobarDoctor(substr_count($sql,'?')===count($params)&&strlen($tipos)===count($params)&&$params[1]==="%001!%!_!!' OR 1=1%"&&!str_contains($sql,'OR 1=1'),'Parámetros');
comprobarDoctor($f['pagina']===2&&paginaDirectorio(41,2)['offset']===20,'Paginación');
foreach ([['estado'=>'otro'],['modalidad'=>'otra'],['paciente'=>['x']],['pagina'=>0],['desde'=>'2026-02-30'],['desde'=>'2026-12-01','hasta'=>'2026-11-01']] as $in) {
    try {filtrosCitasDoctor($in);throw new RuntimeException('Filtro inválido aceptado');} catch(DomainException $e) {}
}
try {condicionesCitasDoctor(0,filtrosCitasDoctor([]));throw new RuntimeException('Doctor inválido aceptado');} catch(InvalidArgumentException $e) {}
comprobarDoctor(auditoriaEvento(['id'=>4,'rol'=>1],'citas','consultar','exitoso')===[4,1,'citas','consultar',null,null,'exitoso'],'Auditoría');
echo "Pruebas aisladas de filtros de citas del doctor: OK\n";
