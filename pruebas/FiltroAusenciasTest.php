<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/repositorios/FiltroAusenciasDAO.php';
function comprobarFiltroAusencias(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
function rechazarFiltroAusencias(callable $fn): void {try{$fn();}catch(DomainException $e){return;}throw new RuntimeException('Entrada inválida aceptada');}
$hoy='2026-11-27';
$f=filtrosAusencias(['doctor'=>"  00%_!' OR 1=1 --  ",'especialidad'=>'Cardiología','estado'=>'vigente','motivo'=>'vacaciones','desde'=>'2026-11-01','hasta'=>'2026-11-30','pagina'=>'2']);
[$sql,$tipos,$p]=condicionesFiltroAusencias($f,$hoy);
comprobarFiltroAusencias($f['pagina']===2&&$f['doctor']==="00%_!' OR 1=1 --",'Normalización');
comprobarFiltroAusencias(count($p)===10&&strlen($tipos)===10&&substr_count($sql,'?')===10,'Parámetros incompletos');
comprobarFiltroAusencias($p[0]==="%00!%!_!!' OR 1=1 --%"&&!str_contains($sql,'OR 1=1'),'Comodines e inyección');
comprobarFiltroAusencias(str_contains($sql,'a.fecha_fin >= ?')&&str_contains($sql,'a.fecha_inicio <= ?')&&array_slice($p,-2)===['2026-11-01','2026-11-30'],'Intersección de fechas');
foreach(['cancelada'=>'a.cancelada = 1','programada'=>'a.fecha_inicio > ?','finalizada'=>'a.fecha_fin < ?','vigente'=>'a.fecha_inicio <= ? AND a.fecha_fin >= ?'] as $estado=>$cond){
    [$sql,$t,$p]=condicionesFiltroAusencias(filtrosAusencias(['estado'=>$estado]),$hoy);
    comprobarFiltroAusencias(str_contains($sql,$cond)&&substr_count($sql,'?')===count($p),'Estado incorrecto');
}
foreach([['doctor'=>['x']],['doctor'=>str_repeat('a',101)],['doctor'=>"\x00"],['estado'=>'invalido'],['motivo'=>'x'],['desde'=>'2026-02-30'],['desde'=>'2026-12-01','hasta'=>'2026-11-30'],['pagina'=>'0'],['pagina'=>'1 OR 1=1']] as $entrada) rechazarFiltroAusencias(fn()=>filtrosAusencias($entrada));
comprobarFiltroAusencias(paginaDirectorio(41,3)['offset']===40&&paginaDirectorio(0,3)['pagina']===1,'Paginación');
comprobarFiltroAusencias(auditoriaEvento(['id'=>1,'rol'=>2],'ausencias','consultar','exitoso')===[1,2,'ausencias','consultar',null,null,'exitoso'],'Auditoría de consultas');
comprobarFiltroAusencias(auditoriaEvento(['id'=>4,'rol'=>1],'ausencias','acceder','denegado')===[4,1,'ausencias','acceder',null,null,'denegado'],'Auditoría de denegación');
echo "Pruebas aisladas de filtros de ausencias: OK\n";
