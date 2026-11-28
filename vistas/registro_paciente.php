<?php
if (empty($vistaRegistroPaciente)) { http_response_code(403); exit; }
$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$valor = static fn(string $k): string => $esc($datos[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Registro de paciente - Sistema de Citas Médicas</title><link rel="stylesheet" href="css/registro-paciente.css?v=12-1"></head>
<body class="registro-publico"><main class="registro-contenedor">
<header class="registro-cabecera"><div class="registro-logo" aria-hidden="true">+</div><div><p>SISTEMA DE CITAS MÉDICAS</p><h1>Registro de paciente</h1><span>Crea tu cuenta para gestionar tus citas.</span></div></header>
<?php if ($usuarioCreado !== null): ?>
<section class="registro-exito" role="status"><h2>¡Cuenta creada correctamente!</h2><p>Tu nombre de usuario es:</p><strong class="registro-usuario"><?= $esc($usuarioCreado) ?></strong><p>Guárdalo e inicia sesión con la contraseña que elegiste.</p><a class="registro-boton" href="index.php">Ir al inicio de sesión</a></section>
<?php else: ?>
<?php if ($mensaje !== ''): ?><p class="registro-error" role="alert"><?= $esc($mensaje) ?></p><?php endif; ?>
<form method="post" action="registro_paciente.php" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= $esc($csrf) ?>">
<fieldset><legend>Datos personales</legend><p class="registro-ayuda">El celular y el correo electrónico son obligatorios. Se utilizarán como datos de contacto y podrán servir para recuperar tu cuenta cuando se habilite y verifique ese servicio.</p><div class="registro-grid">
<label>DNI <input name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" required value="<?= $valor('dni') ?>" placeholder="8 dígitos"></label>
<label>Nombres <input name="nombres" maxlength="100" required autocomplete="given-name" value="<?= $valor('nombres') ?>"></label>
<label>Apellido paterno <input name="primer_apellido" maxlength="100" required autocomplete="family-name" value="<?= $valor('primer_apellido') ?>"></label>
<label>Apellido materno (opcional) <input name="segundo_apellido" maxlength="100" value="<?= $valor('segundo_apellido') ?>"></label>
<label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" min="1000-01-01" max="<?= $esc($hoy) ?>" required autocomplete="bday" value="<?= $valor('fecha_nacimiento') ?>"></label>
<label>Sexo <select name="sexo" required><option value="">Seleccionar</option><option value="M" <?= ($datos['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option><option value="F" <?= ($datos['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option></select></label>
<label>Celular (obligatorio) <input type="tel" name="telefono" inputmode="numeric" pattern="9[0-9]{8}" minlength="9" maxlength="9" required autocomplete="tel-national" placeholder="9 dígitos, empieza con 9" title="Número peruano de 9 dígitos que comienza con 9" value="<?= $valor('telefono') ?>"></label>
<label>Correo electrónico (obligatorio) <input type="email" name="correo" maxlength="100" required autocomplete="email" placeholder="correo@ejemplo.com" value="<?= $valor('correo') ?>"></label>
<label class="registro-ancho">Dirección (opcional) <input name="direccion" maxlength="200" autocomplete="street-address" value="<?= $valor('direccion') ?>"></label>
</div></fieldset>
<fieldset><legend>Datos de acceso</legend><p class="registro-ayuda">Tu usuario se generará automáticamente. La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo (máximo 72 bytes).</p><div class="registro-grid">
<label>Contraseña <input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
<label>Confirmar contraseña <input type="password" name="confirmar_password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
</div></fieldset>
<aside class="registro-aviso"><strong>Aviso de privacidad del proyecto académico</strong><p>Los datos se utilizan para crear una cuenta y demostrar la gestión de citas. Usa únicamente datos ficticios; este portal académico no verifica la identidad ni presta atención médica real. No introduzcas datos clínicos reales.</p></aside>
<label class="registro-check"><input type="checkbox" name="acepta_privacidad" value="1" required <?= ($datos['acepta_privacidad'] ?? '') === '1' ? 'checked' : '' ?>> He leído y acepto el aviso del proyecto académico.</label>
<button type="submit" class="registro-boton">Crear cuenta de paciente</button>
<a class="registro-volver" href="index.php">Volver al inicio de sesión</a>
</form>
<?php endif; ?>
</main></body></html>
