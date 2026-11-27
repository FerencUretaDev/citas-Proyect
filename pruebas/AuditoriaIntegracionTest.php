<?php
// Prueba de integración local; no conserva el evento de prueba.
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){exit("Ejecutar exclusivamente desde PHP CLI en XAMPP Windows.\n");}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
$base=$conexion->query('SELECT DATABASE()')->fetch_row()[0];
if($base!=='sistema'){fwrite(STDERR,"Base local inesperada.\n");exit(1);}
$conexion->begin_transaction();
try {
    $id=auditoriaInsertarDAO($conexion,auditoriaEvento(['id'=>2147483647,'rol'=>2],'auditoria','consultar','exitoso'));
    if($id<=0)throw new RuntimeException('ID no recuperado.');
    $filas=auditoriaConsultarDAO($conexion,auditoriaFiltros(['usuario_id'=>'2147483647','modulo'=>'auditoria','accion'=>'consultar']),50);
    if(!array_filter($filas,fn($r)=>(int)$r['id']===$id))throw new RuntimeException('Evento no recuperado.');
    $conexion->rollback();
    $s=$conexion->prepare('SELECT COUNT(*) FROM auditoria WHERE id=?');$s->execute([$id]);
    if((int)$s->get_result()->fetch_row()[0]!==0)throw new RuntimeException('Rollback no aplicado.');
    echo "Prueba de integración de auditoría: OK (evento revertido)\n";
} catch(Throwable $e){$conexion->rollback();fwrite(STDERR,"Prueba de integración fallida: ".$e->getMessage().PHP_EOL);exit(1);}
