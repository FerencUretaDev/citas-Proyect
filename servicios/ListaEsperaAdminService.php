<?php
require_once __DIR__.'/AuditoriaService.php';
require_once dirname(__DIR__).'/repositorios/ListaEsperaAdminDAO.php';
function filtrosListaEsperaAdmin(array $datos): array {
    $f=[];
    foreach (['paciente','doctor','estado','desde','hasta'] as $k) {
        $v=$datos[$k]??'';
        if (!is_string($v)||strlen($v)>200||!preg_match('//u',$v)) throw new DomainException('Filtro inválido.');
        $f[$k]=trim($v);
    }
    if (!in_array($f['estado'],['','en_espera','notificado','reservado','cancelado','vencido'],true)) throw new DomainException('Estado inválido.');
    $f['desde']=auditoriaFecha($f['desde']); $f['hasta']=auditoriaFecha($f['hasta']);
    if ($f['desde']!==''&&$f['hasta']!==''&&$f['desde']>$f['hasta']) throw new DomainException('Rango de fechas inválido.');
    return $f;
}
function paginaListaEsperaAdmin($v): int {
    if (!is_string($v)&&!is_int($v)) throw new DomainException('Página inválida.');
    $p=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    if ($p===false) throw new DomainException('Página inválida.');
    return $p;
}
function estadoVisibleListaEsperaAdmin(array $s,string $hoy): string {
    return in_array($s['estado'],['en_espera','notificado'],true)&&$s['fecha_solicitada']<$hoy?'vencido':$s['estado'];
}
