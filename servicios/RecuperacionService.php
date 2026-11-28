<?php

require_once dirname(__DIR__).'/repositorios/RecuperacionDAO.php';
require_once __DIR__.'/LimiteRegistroPacienteService.php';
require_once __DIR__.'/AuditoriaService.php';

function directorioLimiteRecuperacion(): string
{
    return dirname(directorioLimiteRegistroPaciente())
        .'/recuperacion-solicitudes';
}

function mensajeSolicitudRecuperacion(): string
{
    return 'Si la cuenta es válida y está activa, el administrador recibirá tu solicitud y podrá contactarte mediante tu celular registrado.';
}

function normalizarUsuarioRecuperacion(mixed $valor): ?string
{
    if (!is_string($valor) || strlen($valor) > 200) {
        return null;
    }

    $usuario = trim($valor);

    if (preg_match('/\A[^\p{C}\s]{1,50}\z/u', $usuario) !== 1) {
        return null;
    }

    return $usuario;
}

function procesarSolicitudRecuperacion(
    mysqli $conexion,
    mixed $valor
): void {
    $usuario = normalizarUsuarioRecuperacion($valor);

    $registrada = $usuario !== null
        && solicitarRecuperacionDAO($conexion, $usuario);

    auditar(
        $conexion,
        'seguridad',
        'solicitar_recuperacion',
        $registrada ? 'exitoso' : 'fallido',
        null,
        null,
        []
    );
}