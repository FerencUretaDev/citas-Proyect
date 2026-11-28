<?php

require_once __DIR__.'/RecuperacionService.php';
require_once __DIR__.'/UsuarioService.php';
require_once dirname(__DIR__).'/repositorios/RestablecerPasswordDAO.php';

function procesarRestablecimiento(
    mysqli $conexion,
    array $datos
): ?string {

    $usuario = normalizarUsuarioRecuperacion(
        $datos['usuario'] ?? null
    );

    $codigo = $datos['codigo'] ?? null;

    $password = $datos['password'] ?? null;

    $confirmacion = $datos['confirmacion'] ?? null;

    if ($usuario === null
        || !is_string($codigo)
        || preg_match('/\A[a-f0-9]{32}\z/i', $codigo) !== 1) {

        return 'Usuario o codigo de recuperacion no valido.';
    }

    $error = validarPasswordDirectorio(
        $password,
        $confirmacion,
        false
    );

    if ($error !== null) {
        return $error;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $resultado = restablecerPasswordDAO(
        $conexion,
        $usuario,
        strtolower($codigo),
        $hash
    );

    auditar(
        $conexion,
        'seguridad',
        'restablecer_password',
        $resultado ? 'exitoso' : 'fallido',
        null,
        null,
        []
    );

    return $resultado
        ? null
        : 'Usuario o codigo no valido, vencido o utilizado.';
}