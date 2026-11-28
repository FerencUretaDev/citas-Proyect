<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require_once dirname(__DIR__) . '/servicios/RegistroPacienteService.php';
$entrada = ['dni'=>'00000000','nombres'=>'Ana María','primer_apellido'=>'Prueba','segundo_apellido'=>'Ejemplo','fecha_nacimiento'=>'2000-01-01','sexo'=>'F','telefono'=>'900000000','correo'=>'paciente@example.invalid','direccion'=>'','acepta_privacidad'=>'1','password'=>'Prueba!2026','confirmar_password'=>'Prueba!2026'];
$ahora = new DateTimeImmutable('2026-11-27 12:00:00', new DateTimeZone('America/Lima'));
$total=0;
function verificarRegistro(bool $ok, string $nombre): void {
    global $total;
    if (!$ok) { fwrite(STDERR, 'FALLO: '.$nombre.PHP_EOL); exit(1); }
    echo 'OK: '.$nombre.PHP_EOL; $total++;
}
function rechazaRegistro(array $entrada, DateTimeImmutable $ahora): bool {
    try { validarRegistroPaciente($entrada, $ahora); return false; }
    catch (DomainException $e) { return true; }
}
$d=validarRegistroPaciente($entrada,$ahora);
verificarRegistro($d['base']==='pac.apruebae','Datos válidos y usuario automático');
verificarRegistro($d['dni']==='00000000','Conserva ceros iniciales');
verificarRegistro($d['nombres']==='Ana María','Conserva tildes');
$d=validarRegistroPaciente(array_replace($entrada,['rol'=>2,'activo'=>0,'usuario_id'=>9,'usuario'=>'admin']),$ahora);
verificarRegistro(!isset($d['rol']) && !isset($d['activo']) && !isset($d['usuario_id']) && !isset($d['usuario']) && $d['base']==='pac.apruebae','Campos de privilegio no aceptados');
foreach ([['dni','1234567','DNI corto'],['dni',['12345678'],'DNI como arreglo'],['dni','abcdefgh','DNI no numérico'],['nombres','','Nombre obligatorio'],['primer_apellido','<script>','Nombre con HTML'],['fecha_nacimiento','2026-02-30','Fecha imposible'],['fecha_nacimiento','2026-11-28','Fecha futura'],['sexo','X','Sexo inválido'],['telefono','abc','Teléfono inválido'],['correo','mal-correo','Correo inválido'],['acepta_privacidad','0','Privacidad no aceptada'],['password','abc','Contraseña débil'],['confirmar_password','Distinta!2026','Contraseñas distintas'],['direccion',str_repeat('a',201),'Dirección demasiado larga']] as [$campo,$valor,$nombre]) {
    verificarRegistro(rechazaRegistro(array_replace($entrada,[$campo=>$valor]),$ahora),$nombre);
}
$d=validarRegistroPaciente(array_replace($entrada,['segundo_apellido'=>'']),$ahora);
verificarRegistro($d['base']==='pac.aprueba','Segundo apellido opcional');
foreach ([['telefono','','Celular vacío rechazado'],['correo','','Correo vacío rechazado'],['telefono','123456789','Celular debe empezar con 9'],['telefono','90000000','Celular de ocho dígitos rechazado'],['telefono','9000000000','Celular de diez dígitos rechazado'],['telefono',['900000000'],'Celular como arreglo rechazado'],['correo',['paciente@example.invalid'],'Correo como arreglo rechazado']] as [$campo,$valor,$nombre]) {
    verificarRegistro(rechazaRegistro(array_replace($entrada,[$campo=>$valor]),$ahora),$nombre);
}
foreach (['telefono','correo'] as $campo) {
    $sinCampo=$entrada; unset($sinCampo[$campo]);
    verificarRegistro(rechazaRegistro($sinCampo,$ahora),'Campo ausente rechazado: '.$campo);
}
echo 'Total: '.$total.' pruebas superadas.'.PHP_EOL;
