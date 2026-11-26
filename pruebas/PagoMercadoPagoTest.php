<?php
require_once dirname(__DIR__).'/servicios/MercadoPagoService.php';
function comprobarPagoTest(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$p=['id_orden_mp'=>'ORDTST1234567890ABC','referencia_externa'=>'test-ref','moneda'=>'PEN','monto'=>'50.00'];
$d=['id'=>$p['id_orden_mp'],'external_reference'=>'test-ref','currency'=>'PEN','total_amount'=>'50.00','total_paid_amount'=>'50.00','status'=>'processed','status_detail'=>'accredited'];
comprobarPagoTest(mpPagoAcreditado($d,$p),'No reconoce pago acreditado');
foreach(['id'=>'ORDPROD1234567890','external_reference'=>'otra-cita','currency'=>'USD','total_amount'=>'80.00','total_paid_amount'=>'49.99','status'=>'created','status_detail'=>'partially_refunded'] as $k=>$v) comprobarPagoTest(!mpPagoAcreditado(array_replace($d,[$k=>$v]),$p),'Aceptó un dato no válido: '.$k);
foreach(['https://evil.example/checkout/','https://www.mercadopago.com.pe@evil.example/checkout/','https://x@www.mercadopago.com.pe/checkout/','http://www.mercadopago.com.pe/checkout/'] as $url) comprobarPagoTest(mpUrlCheckout($url)===null,'URL externa aceptada');
comprobarPagoTest(mpUrlCheckout('https://www.mercadopago.com.pe/checkout/v1/redirect?order_id=ORDTST1234567890ABC&pref_id=abc')!==null,'URL válida rechazada');
comprobarPagoTest(mpMontoCentavos('80.00')===8000 && mpMontoCentavos('50.00')===5000,'Conversión decimal incorrecta');
foreach(['created','action_required','processing','processed','canceled','failed','refunded','expired',null] as $status) {
    comprobarPagoTest(mpOrdenCancelable(['status'=>$status])===in_array($status,['created','action_required'],true),'Estado de cancelación incorrecto');
}
$clave='e118d5f0-4c1d-4b48-9920-7dd999b69a41';
$cancel=mpClaveCancelacion($clave);
comprobarPagoTest(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$cancel)===1,'UUID de cancelación inválido');
comprobarPagoTest($cancel!==$clave&&$cancel===mpClaveCancelacion($clave)&&$cancel!==mpClaveCancelacion('otra-clave'),'Clave de cancelación no estable o no diferenciada');
echo "Pruebas aisladas de verificación y cancelación de pagos: OK\n";
