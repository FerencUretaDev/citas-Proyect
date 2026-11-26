<?php

// Las fichas conservan la ortografía original. Solo el usuario se normaliza.
function letrasParaUsuario(string $texto): string
{
    $texto = strtr($texto, [
        'Á'=>'a', 'á'=>'a', 'É'=>'e', 'é'=>'e', 'Í'=>'i', 'í'=>'i',
        'Ó'=>'o', 'ó'=>'o', 'Ú'=>'u', 'ú'=>'u', 'Ü'=>'u', 'ü'=>'u',
        'Ñ'=>'n', 'ñ'=>'n',
    ]);
    $texto = preg_replace('/\p{M}+/u', '', $texto);
    return preg_replace('/[^a-z]/', '', strtolower($texto ?? ''));
}

function baseUsuario(string $nombres, string $primero, string $segundo, int $rol): string
{
    $inicial = substr(letrasParaUsuario($nombres), 0, 1);
    $apellido = letrasParaUsuario($primero);
    $otraInicial = substr(letrasParaUsuario($segundo), 0, 1);
    if ($inicial === '' || $apellido === '' || ($segundo !== '' && $otraInicial === '')) {
        return '';
    }
    $prefijo = match ($rol) { 0 => 'pac.', 1 => 'doc.' };
    return $prefijo . $inicial . substr($apellido, 0, 45 - strlen($otraInicial)) . $otraInicial;
}

// El índice UNIQUE de usuarios.usuario decide incluso ante registros simultáneos.
// La cuenta y la ficha se guardan dentro de la transacción abierta por el llamador.
function crearCuentaConUsuario(mysqli $conexion, string $base, string $hash, int $rol): array
{
    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
    $usuario = $base;
    try {
        $stmt->bind_param("ssi", $usuario, $hash, $rol);
        for ($numero = 1; ; $numero++) {
            $sufijo = $numero === 1 ? '' : (string)$numero;
            $usuario = substr($base, 0, 50 - strlen($sufijo)) . $sufijo;
            try {
                $stmt->execute();
                return [$conexion->insert_id, $usuario];
            } catch (mysqli_sql_exception $error) {
                if ($error->getCode() !== 1062) {
                    throw $error;
                }
            }
        }
    } finally {
        $stmt->close();
    }
}


// Política común para el alta y el restablecimiento administrativo.
// 72 bytes evita el truncamiento silencioso de bcrypt (PASSWORD_DEFAULT actual).
function validarPasswordDirectorio(mixed $password, mixed $confirmacion, bool $editando): ?string
{
    if (!is_string($password) || !is_string($confirmacion)) {
        return "Formato de contraseña no válido.";
    }
    if ($editando && $password === "" && $confirmacion === "") {
        return null;
    }
    if (strlen($password) > 72) {
        return "La contraseña no puede superar 72 bytes.";
    }
    if (preg_match('/\A(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[0-9])(?=.*[\p{P}\p{S}])[^\p{Cc}\p{Zl}\p{Zp}]{8,}\z/u', $password) !== 1) {
        return "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.";
    }
    if ($password !== $confirmacion) {
        return "Las contraseñas no coinciden.";
    }
    return null;
}
