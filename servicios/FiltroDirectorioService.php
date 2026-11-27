<?php
// Filtros administrativos: valores acotados; nunca se usan como fragmentos SQL.
// El DNI se conserva como texto para no perder ceros iniciales.
function dniDoctorValido($valor): bool
{
    return is_string($valor) && preg_match('/\A[0-9]{8}\z/', $valor) === 1;
}

function sexoPacienteValido($valor): bool
{
    return in_array($valor, ['M', 'F'], true);
}

function filtrosDirectorio(array $entrada, bool $pacientes, string $estadoInicial = '1'): array
{
    $f = [];
    foreach (['buscar' => '', 'estado' => $estadoInicial, 'sexo' => '', 'especialidad' => '', 'pagina' => '1'] as $campo => $defecto) {
        $valor = $entrada[$campo] ?? $defecto;
        if (!is_string($valor) || !preg_match('//u', $valor)) {
            throw new DomainException('Filtro no válido.');
        }
        $f[$campo] = trim($valor);
    }
    if (strlen($f['buscar']) > 400 || preg_match('/^.{0,100}$/usD', $f['buscar']) !== 1 ||
        !in_array($f['estado'], ['0', '1', 'todos'], true) ||
        ($f['sexo'] !== '' && (!$pacientes || !sexoPacienteValido($f['sexo']))) ||
        ($f['especialidad'] !== '' && ($pacientes || strlen($f['especialidad']) > 400))) {
        throw new DomainException('Revisa los filtros de búsqueda.');
    }
    $pagina = filter_var($f['pagina'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($pagina === false) throw new DomainException('Página no válida.');
    $f['pagina'] = $pagina;
    return $f;
}

function patronDirectorio(string $texto): string
{
    return '%' . strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}

function paginaDirectorio(int $total, int $solicitada, int $limite = 20): array
{
    $paginas = max(1, (int)ceil($total / $limite));
    $pagina = min(max(1, $solicitada), $paginas);
    return ['pagina' => $pagina, 'paginas' => $paginas, 'offset' => ($pagina - 1) * $limite, 'limite' => $limite];
}
