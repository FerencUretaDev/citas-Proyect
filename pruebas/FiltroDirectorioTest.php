<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/repositorios/DirectorioDAO.php';
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
function comprobarFiltro(bool $ok, string $mensaje): void { if (!$ok) throw new RuntimeException($mensaje); }
function rechazarFiltro(callable $f): void { try { $f(); } catch (DomainException $e) { return; } throw new RuntimeException('Filtro inválido aceptado'); }
comprobarFiltro(dniDoctorValido('01234567'), 'DNI con cero inicial');
foreach (['', null, '1234567', '123456789', 'abcdefgh', '1234567a', 12345678, ['12345678'], "12345678\n"] as $dni) comprobarFiltro(!dniDoctorValido($dni), 'DNI inválido aceptado');
comprobarFiltro(sexoPacienteValido('M') && sexoPacienteValido('F'), 'Sexos válidos');
foreach (['X', '', null, 'm', ['M'], 'otro'] as $v) comprobarFiltro(!sexoPacienteValido($v), 'Sexo inválido aceptado');
$f = filtrosDirectorio(['buscar'=>' Ana López ', 'sexo'=>'F', 'estado'=>'todos', 'pagina'=>'2'], true);
comprobarFiltro($f['buscar']==='Ana López' && $f['pagina']===2, 'Normalización');
[$sql,$tipos,$params] = condicionesDirectorio($f,true);
comprobarFiltro($tipos==='ssss' && substr_count($sql,'?')===4 && $params===['%Ana López%','%Ana López%','%Ana López%','F'] && !str_contains($sql,'Ana López'), 'Parámetros independientes del SQL');
$f = filtrosDirectorio(['buscar'=>"%' OR 1=1 --_!", 'estado'=>'0','especialidad'=>'Cardiología'],false);
[$sql,$tipos,$params] = condicionesDirectorio($f,false);
comprobarFiltro($tipos==='isssss' && substr_count($sql,'?')===6 && str_contains($sql,'f.dni LIKE ?') && str_contains($sql,'f.cmp LIKE ?') && $params[0]===0 && $params[1]==="%!%' OR 1=1 --!_!!%" && $params[5]==='Cardiología' && !str_contains($sql,'OR 1=1'), 'Texto literal, sin inyección');
foreach ([['estado'=>'2'],['sexo'=>'X'],['buscar'=>['x']],['buscar'=>str_repeat('a',101)],['pagina'=>'0'],['pagina'=>'-2'],['pagina'=>'1 OR 1=1'],['especialidad'=>'Cardiología']] as $in) rechazarFiltro(fn()=>filtrosDirectorio($in,true));
rechazarFiltro(fn()=>filtrosDirectorio(['sexo'=>'M'],false));
comprobarFiltro(paginaDirectorio(41,2)===['pagina'=>2,'paginas'=>3,'offset'=>20,'limite'=>20], 'Paginación');
comprobarFiltro(paginaDirectorio(0,9)['pagina']===1 && paginaDirectorio(21,99)['pagina']===2,'Límites de página');
comprobarFiltro(auditoriaEvento(['id'=>1,'rol'=>2],'pacientes','consultar','exitoso')===[1,2,'pacientes','consultar',null,null,'exitoso'], 'Auditoría sin texto de búsqueda');
comprobarFiltro(auditoriaFiltros(['modulo'=>'doctores','accion'=>'consultar'])['accion']==='consultar','Filtro de auditoría');
echo "Pruebas aisladas de filtros de directorio: OK\n";
