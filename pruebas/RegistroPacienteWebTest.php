<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require_once dirname(__DIR__).'/servicios/LimiteRegistroPacienteService.php';
$n=0;
function comprobarWebRegistro(bool $ok,string $nombre): void { global $n; if (!$ok) throw new RuntimeException($nombre); $n++; echo 'OK: '.$nombre.PHP_EOL; }
$dir=sys_get_temp_dir().'/registro-test-'.bin2hex(random_bytes(10));
try {
    $t=1700000000;
    for($i=0;$i<8;$i++) if(!permitirIntentoRegistroPaciente($dir,'192.0.2.1',$t)) throw new RuntimeException('Intento permitido rechazado');
    comprobarWebRegistro(true,'Ocho intentos permitidos');
    comprobarWebRegistro(!permitirIntentoRegistroPaciente($dir,'192.0.2.1',$t),'Noveno intento bloqueado');
    comprobarWebRegistro(permitirIntentoRegistroPaciente($dir,'192.0.2.2',$t),'IP distinta conserva acceso');
    comprobarWebRegistro(permitirIntentoRegistroPaciente($dir,'192.0.2.1',$t+900),'Límite vence a los quince minutos');
    for($i=1;$i<=119;$i++) if(!permitirIntentoRegistroPaciente($dir,'198.51.100.'.$i,$t+900)) throw new RuntimeException('Límite global prematuro');
    comprobarWebRegistro(!permitirIntentoRegistroPaciente($dir,'203.0.113.1',$t+900),'Límite global de 120 intentos');
    comprobarWebRegistro(!str_contains(file_get_contents($dir.'/intentos.json'),'192.0.2.1'),'No guarda IP literal');
    $vistaRegistroPaciente=true;$datos=['nombres'=>'<script>alert(1)</script>'];$csrf='token-de-prueba';$hoy='2026-11-27';$mensaje='';$usuarioCreado=null;
    ob_start();require dirname(__DIR__).'/vistas/registro_paciente.php';$html=ob_get_clean();
    comprobarWebRegistro(str_contains($html,'method="post"')&&str_contains($html,'name="csrf_token"'),'Formulario POST con CSRF');
    comprobarWebRegistro(!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;'),'Datos escapados');
    comprobarWebRegistro(!preg_match('/name="(?:rol|activo|usuario_id|usuario)"/',$html),'Sin campos de privilegio');
    comprobarWebRegistro(str_contains($html,'autocomplete="new-password"')&&!preg_match('/type="password"[^>]*value=/',$html),'Contraseñas no se repueblan');
    $login=file_get_contents(dirname(__DIR__).'/vistas/index.php');
    comprobarWebRegistro(str_contains($login,'href="registro_paciente.php"'),'Enlace de registro en login');
    comprobarWebRegistro(preg_match('/<input[^>]*name="telefono"[^>]*\brequired\b[^>]*>/', $html)===1 && str_contains($html,'pattern="9[0-9]{8}"'),'Celular obligatorio con patrón peruano');
    comprobarWebRegistro(preg_match('/<input[^>]*name="correo"[^>]*\brequired\b[^>]*>/', $html)===1,'Correo obligatorio');
    echo "Total: $n pruebas superadas.".PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);} finally { @unlink($dir.'/intentos.json'); @rmdir($dir); }
