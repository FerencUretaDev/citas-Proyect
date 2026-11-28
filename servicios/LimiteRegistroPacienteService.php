<?php
// Estado privado, compartido entre sesiones y procesos del mismo servidor.
function directorioLimiteRegistroPaciente(): string {
    return PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/private/registro-paciente' : dirname(__DIR__, 2).'/private/registro-paciente';
}
function permitirIntentoRegistroPaciente(string $directorio, string $ip, ?int $ahora = null): bool {
    $ahora ??= time();
    if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = 'desconocida';
    if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) throw new RuntimeException('Directorio privado no disponible');
    $ruta = $directorio.'/intentos.json';
    $archivo = @fopen($ruta, 'c+b');
    if (!$archivo) throw new RuntimeException('Control de intentos no disponible');
    try {
        if (!flock($archivo, LOCK_EX)) throw new RuntimeException('Bloqueo no disponible');
        @chmod($ruta, 0600);
        $raw = stream_get_contents($archivo, 262145);
        if ($raw === false || strlen($raw) > 262144) throw new RuntimeException('Estado de intentos inválido');
        $estado = $raw === '' ? ['salt'=>bin2hex(random_bytes(32)), 'eventos'=>[]] : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($estado) || !is_string($estado['salt'] ?? null) || !preg_match('/\A[0-9a-f]{64}\z/', $estado['salt']) || !is_array($estado['eventos'] ?? null)) throw new RuntimeException('Estado de intentos inválido');
        $clave = hash_hmac('sha256', $ip, $estado['salt']);
        $eventos = [];
        $propios = 0;
        foreach ($estado['eventos'] as $evento) {
            if (!is_array($evento) || !is_string($evento[0] ?? null) || !is_int($evento[1] ?? null)) throw new RuntimeException('Evento inválido');
            if ($evento[1] > $ahora - 900) { $eventos[] = $evento; if ($evento[0] === $clave) $propios++; }
        }
        if ($propios >= 8 || count($eventos) >= 120) return false;
        $eventos[] = [$clave, $ahora];
        $json = json_encode(['salt'=>$estado['salt'], 'eventos'=>$eventos], JSON_THROW_ON_ERROR);
        if (!rewind($archivo) || !ftruncate($archivo, 0) || fwrite($archivo, $json) !== strlen($json) || !fflush($archivo)) throw new RuntimeException('No se pudo guardar el límite');
        return true;
    } finally { flock($archivo, LOCK_UN); fclose($archivo); }
}
