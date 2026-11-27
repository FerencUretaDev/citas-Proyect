<?php
require_once __DIR__.'/FiltroDirectorioService.php';
require_once __DIR__.'/AuditoriaService.php';
require_once __DIR__.'/AusenciaService.php';

const ESTADOS_FILTRO_AUSENCIAS = ['vigente'=>'Vigente','programada'=>'Programada','finalizada'=>'Finalizada','cancelada'=>'Cancelada'];

function filtrosAusencias(array $entrada): array {
    $f=[];
    foreach (['doctor','especialidad','estado','motivo','desde','hasta'] as $k) {
        $v=$entrada[$k]??'';
        if (!is_string($v)||strlen($v)>400||preg_match('/[\x00-\x1F\x7F]/',$v)||preg_match('/\A.{0,100}\z/usD',$v)!==1) throw new DomainException('Filtro de ausencias no válido.');
        $f[$k]=trim($v);
    }
    foreach (['estado'=>ESTADOS_FILTRO_AUSENCIAS,'motivo'=>MOTIVOS_AUSENCIA] as $k=>$opciones) {
        if ($f[$k]!==''&&!array_key_exists($f[$k],$opciones)) throw new DomainException('Opción de filtro no válida.');
    }
    $f['desde']=auditoriaFecha($f['desde']); $f['hasta']=auditoriaFecha($f['hasta']);
    if ($f['desde']!==''&&$f['hasta']!==''&&$f['desde']>$f['hasta']) throw new DomainException('El rango de fechas no es válido.');
    $v=$entrada['pagina']??'1';
    if (!is_string($v)&&!is_int($v)) throw new DomainException('Página no válida.');
    $f['pagina']=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    if ($f['pagina']===false) throw new DomainException('Página no válida.');
    return $f;
}
