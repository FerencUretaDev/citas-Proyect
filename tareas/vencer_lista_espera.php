<?php
// Solo CLI. No publicar este script como endpoint web.
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
$modo=$argv[1]??'--simular';
if(count($argv)>2||!in_array($modo,['--simular','--aplicar'],true)){
    fwrite(STDERR,"Uso: php tareas/vencer_lista_espera.php [--simular|--aplicar]\n");exit(2);
}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/VencimientoListaEsperaService.php';
try {
    $ahora=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
    echo 'Fecha de corte Lima: '.fechaCorteListaEspera($ahora).PHP_EOL;
    echo 'Solicitudes elegibles: '.contarSolicitudesFechaPasada($conexion,$ahora).PHP_EOL;
    if($modo==='--simular'){echo "SIMULACION: sin cambios.\n";exit(0);}
    verificarMotorVencimientoListaEspera($conexion);
    $total=0;
    // Hasta 500 por ejecución; otra ejecución puede procesar las restantes.
    for($i=0;$i<5;$i++){
        $n=vencerLoteListaEspera($conexion,$ahora,100);$total+=$n;
        if($n<100)break;
    }
    echo 'Solicitudes vencidas: '.$total.PHP_EOL;
    echo 'Solicitudes elegibles restantes: '.contarSolicitudesFechaPasada($conexion,$ahora).PHP_EOL;
} catch(Throwable $e){
    error_log('Vencimiento lista espera: '.get_class($e).' / '.$e->getCode());
    fwrite(STDERR,"No se completó el mantenimiento; los lotes previamente confirmados se conservan.\n");exit(1);
}
