<?php
require_once dirname(__DIR__).'/repositorios/AuditoriaDAO.php';
function auditoriaCatalogo(): array {
    return [
        'seguridad'=>['iniciar_sesion','cerrar_sesion','perfil_inactivo'],
        'pacientes'=>['consultar','registrar','editar','archivar','reactivar','cambiar_password'],
        'doctores'=>['consultar','registrar','editar','desactivar','reactivar','cambiar_password'],
        'citas'=>['consultar','acceder','reservar','cancelar','reprogramar','confirmar','cambiar_estado'],
        'ausencias'=>['registrar','cancelar','consultar','acceder'],
        'pagos'=>['acceder','crear_orden','verificar','cancelar','acreditar','estado_cancelado','estado_rechazado','estado_revision','estado_reembolsado','estado_en_proceso','estado_pendiente','estado_expirado'],
        'historia_clinica'=>['registrar_atencion','consultar_resumen','consultar_listado','acceder'],
        'reportes'=>['exportar_citas_csv','exportar_pagos_csv','generar_excel_java'],
        'auditoria'=>['consultar','exportar_csv','acceder']
    ];
}
function auditoriaId($v): ?int {
    if (!is_int($v) && !is_string($v)) return null;
    $id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    return $id===false?null:$id;
}
function auditoriaRol($v): ?int { return in_array($v,[0,1,2,'0','1','2'],true)?(int)$v:null; }
function auditoriaEvento(array $actor,string $modulo,string $accion,string $resultado,?string $entidad=null,?int $id=null): array {
    $catalogo=auditoriaCatalogo();
    if (!in_array($accion,$catalogo[$modulo]??[],true)||!in_array($resultado,['exitoso','fallido','denegado'],true)||($entidad!==null&&!in_array($entidad,['usuario','paciente','doctor','cita','ausencia','pago'],true))) throw new InvalidArgumentException('Evento de auditoría no permitido.');
    $usuario=auditoriaId($actor['id']??null);
    return [$usuario,$usuario===null?null:auditoriaRol($actor['rol']??null),$modulo,$accion,$entidad,$entidad===null?null:auditoriaId($id),$resultado];
}
// Registro de mejor esfuerzo, posterior a la confirmación de la operación.
// Nunca imprimir datos privados ni convertir un error del registro en un fallo de la operación ya confirmada.
function auditar(mysqli $db,string $modulo,string $accion,string $resultado='exitoso',?string $entidad=null,?int $id=null,?array $actor=null): void {
    try { auditoriaInsertarDAO($db,auditoriaEvento($actor??($_SESSION??[]),$modulo,$accion,$resultado,$entidad,$id)); }
    catch(Throwable $e) { error_log('Auditoria: evento no registrado / '.get_class($e).' / '.$e->getCode()); }
}
function auditoriaConexionOpcional(): ?mysqli {
    try { $conexionOpcionalAuditoria=true; require dirname(__DIR__).'/config/Conexion.php'; return $conexion; }
    catch(Throwable $e) { error_log('Auditoria: conexion no disponible / '.$e->getCode()); return null; }
}
function auditoriaFecha($v): string {
    if ($v==='') return '';
    if (!is_string($v)||!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$v)) throw new DomainException('Fecha inválida.');
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('America/Lima'));
    if (!$d||$d->format('Y-m-d')!==$v||$v<'1000-01-01'||$v>'9999-12-31') throw new DomainException('Fecha inválida.');
    return $v;
}
function auditoriaFiltros(array $q): array {
    $f=[]; foreach(['usuario_id','rol','modulo','accion','resultado','desde','hasta'] as $k){$v=$q[$k]??'';if(!is_string($v))throw new DomainException('Filtro inválido.');$f[$k]=trim($v);}
    $cat=auditoriaCatalogo();
    if($f['usuario_id']!==''&&auditoriaId($f['usuario_id'])===null)throw new DomainException('ID de usuario inválido.');
    if($f['rol']!==''&&auditoriaRol($f['rol'])===null)throw new DomainException('Rol inválido.');
    if($f['modulo']!==''&&!isset($cat[$f['modulo']]))throw new DomainException('Módulo inválido.');
    $acciones=$f['modulo']!==''?$cat[$f['modulo']]:array_merge(...array_values($cat));
    if($f['accion']!==''&&!in_array($f['accion'],$acciones,true))throw new DomainException('Acción inválida para el módulo.');
    if($f['resultado']!==''&&!in_array($f['resultado'],['exitoso','fallido','denegado'],true))throw new DomainException('Resultado inválido.');
    $f['desde']=auditoriaFecha($f['desde']);$f['hasta']=auditoriaFecha($f['hasta']);
    if($f['desde']!==''&&$f['hasta']!==''&&$f['desde']>$f['hasta'])throw new DomainException('El rango de fechas no es válido.');
    return $f;
}
function auditoriaCsvCelda($v): string {
    $v=(string)$v; return preg_match('/^[\s]*[=+@\-\t\r]/u',$v)?"'".$v:$v;
}
function auditoriaAdmin(array $actor): bool {return auditoriaId($actor['id']??null)!==null&&auditoriaRol($actor['rol']??null)===2;}
