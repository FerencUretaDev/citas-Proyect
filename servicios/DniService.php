<?php
function consultarDniServicio(string $dni): array
{
    $ruta = getenv('DNI_CONFIG_FILE') ?: (
    PHP_OS_FAMILY === 'Windows'
        ? 'C:/xampp/private/config/dni.ini'
        : '/home/sistemacitasmedicas/private/config/dni.ini'
    );

    $config = is_readable($ruta) ? parse_ini_file($ruta, false, INI_SCANNER_RAW) : [];
    $token = trim($config['APIPERU_TOKEN'] ?? '');
    if ($token === '' || $token === 'PEGA_AQUI_TU_TOKEN' || preg_match('/[\r\n]/', $token)) {
        return [503, ['error' => 'Falta configurar la clave de API Perú. Puedes continuar manualmente.']];
    }
    if (!function_exists('curl_init')) {
        return [503, ['error' => 'Activa la extensión cURL de PHP para consultar el DNI.']];
    }
    
    $consulta = curl_init('https://api.apiperu.pe/dni');
    curl_setopt_array($consulta, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['dni' => $dni]),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    $contenido = curl_exec($consulta);
    $estado = curl_getinfo($consulta, CURLINFO_RESPONSE_CODE);
    if ($estado !== 200 || !is_string($contenido)) {
        $mensaje = match ($estado) {
            401, 403 => 'API Perú rechazó la clave. Revisa la configuración.',
            404 => 'La consulta no devolvió datos. Revisa el DNI y la clave de API Perú.',
            429 => 'Se alcanzó el límite de consultas de API Perú.',
            default => 'API Perú no está disponible. Puedes continuar manualmente.',
        };
        return [502, ['error' => $mensaje]];
    }
    $resultado = json_decode($contenido, true);
    $datos = $resultado['data'] ?? [];
    if (($resultado['success'] ?? false) !== true || ($datos['numero'] ?? null) !== $dni ||
        !is_string($datos['nombres'] ?? null) || trim($datos['nombres']) === '' ||
        !is_string($datos['apellido_paterno'] ?? null) || trim($datos['apellido_paterno']) === '' ||
        !is_string($datos['apellido_materno'] ?? '')) {
        return [502, ['error' => 'No se obtuvieron nombres válidos para este DNI. Puedes continuar manualmente.']];
    }
    return [200, ['nombres' => trim($datos['nombres']), 'primer_apellido' => trim($datos['apellido_paterno']),
        'segundo_apellido' => trim($datos['apellido_materno'] ?? '')]];
}
