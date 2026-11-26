<?php
// Solo pruebas. No imprimir token ni respuesta completa (puede contener datos privados).
function configuracionMercadoPago(): array {
    $ruta=getenv('MP_CONFIG_FILE') ?: 'C:/xampp/private/config/mercadopago.ini';
    if (!is_readable($ruta)) throw new RuntimeException('Configuración de pago no disponible.');
    $c=parse_ini_file($ruta,false,INI_SCANNER_RAW);
    if (!is_array($c)||($c['MP_ENVIRONMENT']??'')!=='test'||($c['MP_CURRENCY']??'')!=='PEN'||empty($c['MP_ACCESS_TOKEN'])||str_contains($c['MP_ACCESS_TOKEN'],'PEGA_AQUI')||!extension_loaded('curl')) throw new RuntimeException('Configuración de prueba inválida.');
    return $c;
}
function mpRequest(string $method,string $path,?array $body=null,?string $key=null): array {
    $c=configuracionMercadoPago();
    $headers=['Authorization: Bearer '.trim($c['MP_ACCESS_TOKEN']),'Accept: application/json','Content-Type: application/json'];
    if($key!==null)$headers[]='X-Idempotency-Key: '.$key;
    $ch=curl_init('https://api.mercadopago.com/v1/orders'.$path);
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
    try {$r=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);} finally {unset($ch);}
    if($r===false||!in_array($code,[200,201],true)){error_log('Mercado Pago HTTP '.(int)$code);throw new RuntimeException('No se pudo obtener una respuesta válida de Mercado Pago.');}
    $d=json_decode($r,true,512,JSON_THROW_ON_ERROR); if(!is_array($d))throw new RuntimeException('Respuesta inválida.');return $d;
}
function mpIdPrueba($id): bool {return is_string($id)&&preg_match('/^ORDTST[A-Z0-9]{10,90}$/D',$id)===1;}
function mpUrlCheckout($url): ?string {
    if(!is_string($url)||preg_match('/[\r\n]/',$url))return null;
    $u=parse_url($url);
    return $u&&($u['scheme']??'')==='https'&&($u['host']??'')==='www.mercadopago.com.pe'&&!isset($u['user'])&&!isset($u['pass'])&&!isset($u['port'])&&str_starts_with($u['path']??'','/checkout/')?$url:null;
}
function mpMontoCentavos($n): ?int {
    if(!is_string($n)&&!is_int($n))return null;
    if(!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/D',(string)$n,$m))return null;
    return ((int)$m[1])*100+(int)str_pad($m[2]??'',2,'0');
}
function mpCoincideOrden(array $d,array $p): bool {
    return mpIdPrueba($d['id']??null)&&($p['id_orden_mp']===null||$p['id_orden_mp']===$d['id'])&&($d['external_reference']??null)===$p['referencia_externa']&&($d['currency']??null)==='PEN'&&$p['moneda']==='PEN'&&mpMontoCentavos($p['monto'])>0&&mpMontoCentavos($d['total_amount']??null)===mpMontoCentavos($p['monto']);
}
function mpCrearOrden(array $p): array {
    // No transmitir identidad, especialidad, motivo o diagnóstico. Payload estable para idempotencia.
    return mpRequest('POST','',['type'=>'online','processing_mode'=>'manual','total_amount'=>(string)$p['monto'],'external_reference'=>$p['referencia_externa'],'payer'=>['email'=>'prueba@example.com'],'items'=>[['title'=>'Servicio de prueba','unit_price'=>(string)$p['monto'],'quantity'=>1]]],$p['clave_idempotencia']);
}
function mpConsultarOrden(string $id): array {if(!mpIdPrueba($id))throw new RuntimeException('Orden no es de prueba.');return mpRequest('GET','/'.rawurlencode($id));}
function mpOrdenCancelable(array $orden): bool {
    return in_array($orden['status']??null,['created','action_required'],true);
}
// UUID determinista: distinto del de creación y estable para todos los reintentos.
function mpClaveCancelacion(string $key): string {
    $hex=substr(hash('sha256','cancel-'.$key),0,32);
    $hex[12]='5';
    $hex[16]=dechex((hexdec($hex[16])&3)|8);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
}
function mpCancelarOrden(string $id,string $key): void {if(!mpIdPrueba($id))throw new RuntimeException('Orden no es de prueba.');mpRequest('POST','/'.rawurlencode($id).'/cancel',null,mpClaveCancelacion($key));}

function mpPagoAcreditado(array $d,array $p): bool {
    return mpCoincideOrden($d,$p)&&($d['status']??null)==='processed'&&($d['status_detail']??null)==='accredited'&&mpMontoCentavos($d['total_paid_amount']??null)===mpMontoCentavos($p['monto']);
}
