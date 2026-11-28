<?php
// Ejecución exclusiva en XAMPP local. Datos temporales revertidos.
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){http_response_code(403);exit(1);}
if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1','::1'],true)||(getenv('DB_NAME')?:'sistema')!=='sistema'){fwrite(STDERR,"Entorno no local.\n");exit(1);}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/ListaEsperaService.php';
function verificarListaEspera(bool $ok,string $mensaje):void{if(!$ok)throw new RuntimeException($mensaje);echo "OK: $mensaje\n";}
$ids=[];$tx=false;
try{
    $dbName=$conexion->query('SELECT DATABASE()')->fetch_row()[0];
    if($dbName!=='sistema')throw new RuntimeException('Base inesperada.');
    $p=$conexion->query('SELECT id FROM pacientes WHERE activo=1 ORDER BY id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
    $d=$conexion->query('SELECT id FROM doctores WHERE activo=1 ORDER BY id LIMIT 1')->fetch_assoc();
    if(count($p)<2||!$d)throw new RuntimeException('Se requieren dos pacientes y un doctor activos.');
    $a=(int)$p[0]['id'];$b=(int)$p[1]['id'];$doctor=(int)$d['id'];
    $fecha=ahoraReprogramacion()->modify('+180 days')->format('Y-m-d');
    $conexion->begin_transaction();$tx=true;
    if(!bloquearDoctorReserva($conexion,$doctor)||!bloquearPacienteReserva($conexion,$a)||!bloquearPacienteReserva($conexion,$b))throw new RuntimeException('Perfiles no disponibles.');
    if(colaListaEsperaDAO($conexion,$doctor,$fecha))throw new RuntimeException('Fecha de prueba ocupada en lista de espera.');
    $ids[]= $uno=insertarListaEsperaDAO($conexion,$a,$doctor,$fecha,'presencial');
    $duplicada=false;
    try{insertarListaEsperaDAO($conexion,$a,$doctor,$fecha,'virtual');}catch(mysqli_sql_exception $e){if((int)$e->getCode()!==1062)throw $e;$duplicada=true;}
    verificarListaEspera($duplicada,'Duplicado activo rechazado incluso con otra modalidad');
    $ids[]= $dos=insertarListaEsperaDAO($conexion,$b,$doctor,$fecha,'virtual');
    $cola=array_map('intval',array_column(colaListaEsperaDAO($conexion,$doctor,$fecha),'id'));
    verificarListaEspera($cola===[$uno,$dos],'Prioridad FIFO por fecha e ID');
    $propias=array_map('intval',array_column(solicitudesPacienteListaEsperaDAO($conexion,$a),'id'));
    verificarListaEspera(in_array($uno,$propias,true)&&!in_array($dos,$propias,true),'Consulta limitada al paciente');
    verificarListaEspera(!cancelarListaEsperaDAO($conexion,$uno,$b),'Cancelación ajena rechazada');
    verificarListaEspera(cancelarListaEsperaDAO($conexion,$uno,$a),'Cancelación propia');
    verificarListaEspera(!cancelarListaEsperaDAO($conexion,$uno,$a),'Solicitud terminal no se modifica');
    $ids[]= $tres=insertarListaEsperaDAO($conexion,$a,$doctor,$fecha,'presencial');
    verificarListaEspera($tres!==$uno,'Nueva inscripción después de cancelar');
    verificarListaEspera(array_map('intval',array_column(colaListaEsperaDAO($conexion,$doctor,$fecha),'id'))===[$dos,$tres],'Reingreso al final de la cola');
    $conexion->rollback();$tx=false;
    foreach($ids as $id)verificarListaEspera(!consultarListaEsperaDAO($conexion,'SELECT id FROM lista_espera WHERE id=?','i',[$id]),'Rollback verificado');
    echo "Integración DAO: OK (sin solicitudes persistentes).\n";
}catch(Throwable $e){if($tx)$conexion->rollback();fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);}
