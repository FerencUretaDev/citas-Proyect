<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/repositorios/FiltroCitasAdminDAO.php';
function comprobarCitasAdmin(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
function rechazarCitasAdmin(callable $fn): void {try{$fn();}catch(DomainException $e){return;}throw new RuntimeException('Entrada inválida aceptada');}
$f=filtrosCitasAdmin(['paciente'=>' 00123456 ','doctor'=>"%' OR 1=1 --_!",'especialidad'=>'Cardiología','estado'=>'confirmada','modalidad'=>'virtual','pago'=>'aprobado','desde'=>'2026-11-01','hasta'=>'2026-11-30','pagina'=>'2']);
[$sql,$tipos,$p]=condicionesCitasAdmin($f,'doctor',7,'2026-11-27 12:00:00');
comprobarCitasAdmin($f['paciente']==='00123456'&&$f['pagina']===2,'Normalización');
comprobarCitasAdmin(substr_count($sql,'?')===count($p)&&strlen($tipos)===count($p)&&count($p)===15,'Parámetros completos');
comprobarCitasAdmin($p[0]===7&&$p[1]==='2026-11-27 12:00:00'&&$p[2]==='%00123456%'&&$p[5]==="%!%' OR 1=1 --!_!!%"&&!str_contains($sql,'OR 1=1'),'SQL sin inyección, comodines literales');
comprobarCitasAdmin(str_contains($sql,'d.dni LIKE ?')&&str_contains($sql,'d.cmp LIKE ?')&&str_contains($sql,'c.fecha <= ?')&&str_contains($sql,'c.doctor_id = ?'),'Búsquedas y ámbito');
[$sql,$tipos,$p]=condicionesCitasAdmin(filtrosCitasAdmin(['pago'=>'sin_pago']));
comprobarCitasAdmin(str_contains($sql,'NOT EXISTS')&&$p===[],'Sin pago asociado');
foreach ([['estado'=>'otro'],['modalidad'=>'otra'],['pago'=>'otro'],['pagina'=>'0'],['pagina'=>['1']],['paciente'=>['x']],['doctor'=>str_repeat('a',101)],['desde'=>'2026-02-30'],['desde'=>'2026-12-01','hasta'=>'2026-11-01']] as $in) rechazarCitasAdmin(fn()=>filtrosCitasAdmin($in));
foreach ([['tipo'=>'doctor'],['id'=>'1'],['tipo'=>'admin','id'=>'1'],['tipo'=>'paciente','id'=>['1']]] as $in) rechazarCitasAdmin(fn()=>fichaFiltroCitasAdmin($in));
comprobarCitasAdmin(fichaFiltroCitasAdmin(['tipo'=>'paciente','id'=>'2'])===['paciente',2]&&fichaFiltroCitasAdmin([])===['',0],'Ficha');
comprobarCitasAdmin(paginaDirectorio(41,2)===['pagina'=>2,'paginas'=>3,'offset'=>20,'limite'=>20],'Paginación');
comprobarCitasAdmin(auditoriaEvento(['id'=>1,'rol'=>2],'citas','consultar','exitoso')===[1,2,'citas','consultar',null,null,'exitoso'],'Auditoría sin datos privados');
comprobarCitasAdmin(auditoriaEvento(['id'=>4,'rol'=>1],'citas','acceder','denegado')===[4,1,'citas','acceder',null,null,'denegado'],'Auditoría de acceso');
echo "Pruebas aisladas de filtros de citas administrativas: OK\n";
