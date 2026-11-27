<?php
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
function auditTest($ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
function auditRechaza(callable $f): void {try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Se aceptó un valor inválido.');}
$a=['id'=>'12','rol'=>'0','password'=>'NO_GUARDAR','dni'=>'NO_GUARDAR'];
auditTest(auditoriaEvento($a,'historia_clinica','consultar_resumen','exitoso','cita',7)===[12,0,'historia_clinica','consultar_resumen','cita',7,'exitoso'],'Evento de paciente');
auditTest(auditoriaEvento([],'seguridad','iniciar_sesion','fallido')===[null,null,'seguridad','iniciar_sesion',null,null,'fallido'],'Acceso anónimo');
auditRechaza(fn()=>auditoriaEvento($a,'historia_clinica','diagnostico privado','exitoso'));
auditRechaza(fn()=>auditoriaEvento($a,'citas','reservar','cualquier texto'));
auditRechaza(fn()=>auditoriaEvento($a,'citas','reservar','exitoso','DNI privado'));
auditTest(auditoriaAdmin(['id'=>1,'rol'=>'2'])&&!auditoriaAdmin($a)&&!auditoriaAdmin(['rol'=>2])&&!auditoriaAdmin(['id'=>1,'rol'=>'admin']),'Roles');
$f=auditoriaFiltros(['rol'=>'0','modulo'=>'pagos','accion'=>'acreditar','desde'=>'2026-11-26','hasta'=>'2026-11-30']);
[$sql,$p]=auditoriaCondiciones($f);
auditTest($p===['0','pagos','acreditar','2026-11-26 00:00:00','2026-11-30 23:59:59']&&substr_count($sql,'?')===5,'Filtros parametrizados');
auditRechaza(fn()=>auditoriaFiltros(['rol'=>'0 OR 1=1']));
auditRechaza(fn()=>auditoriaFiltros(['modulo'=>'citas','accion'=>'acreditar']));
auditRechaza(fn()=>auditoriaFiltros(['desde'=>'2026-02-30']));
auditRechaza(fn()=>auditoriaFiltros(['desde'=>'2026-12-01','hasta'=>'2026-11-30']));
auditRechaza(fn()=>auditoriaFiltros(['modulo'=>['citas']]));
auditTest(auditoriaCsvCelda('=1+1')==="'=1+1"&&auditoriaCsvCelda('  +1')==="'  +1"&&auditoriaCsvCelda('citas')==='citas','CSV seguro');
auditTest(auditoriaEvento(['id'=>4,'rol'=>1],'pagos','acceder','denegado')===[4,1,'pagos','acceder',null,null,'denegado'],'Acceso a pagos denegado por rol');
auditTest(auditoriaEvento($a,'pagos','acceder','denegado','cita',8)===[12,0,'pagos','acceder','cita',8,'denegado'],'Acceso a cita ajena denegado');
auditTest(auditoriaFiltros(['modulo'=>'pagos','accion'=>'acceder','resultado'=>'denegado'])['accion']==='acceder','Filtro de acceso a pagos');
echo "Pruebas aisladas de auditoría: OK\n";
