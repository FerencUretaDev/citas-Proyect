<?php
function claveEspecialidad(string $nombre): string
{
    $nombre = strtr(trim($nombre), [
        "Á"=>"a", "É"=>"e", "Í"=>"i", "Ó"=>"o", "Ú"=>"u", "Ü"=>"u", "Ñ"=>"n",
        "á"=>"a", "é"=>"e", "í"=>"i", "ó"=>"o", "ú"=>"u", "ü"=>"u", "ñ"=>"n",
    ]);
    return strtolower(preg_replace('/\s+/u', ' ', $nombre));
}

function cargarCatalogoEspecialidades(mysqli $conexion): array
{
    // Catálogo de registro: consulta general y nomenclatura médica de CONAREME.
    $catalogo = [
        "Consulta general" => [
            "Medicina General",
        ],
        "Especialidades médicas" => [
            "Administración y gestión en salud",
            "Adolescentología",
            "Anatomía patológica",
            "Anestesiología",
            "Cardiología",
            "Cirugía de cabeza, cuello y maxilofacial",
            "Cirugía de tórax y cardiovascular",
            "Cirugía general",
            "Cirugía oncológica",
            "Cirugía pediátrica",
            "Cirugía plástica",
            "Dermatología",
            "Endocrinología",
            "Gastroenterología",
            "Genética médica",
            "Geriatría",
            "Ginecología y obstetricia",
            "Hematología",
            "Inmunología y alergia",
            "Medicina de emergencias y desastres",
            "Medicina de enfermedades infecciosas y tropicales",
            "Medicina del deporte",
            "Medicina familiar y comunitaria",
            "Medicina física y de rehabilitación",
            "Medicina hiperbárica y subacuática",
            "Medicina intensiva",
            "Medicina interna",
            "Medicina legal",
            "Medicina nuclear",
            "Medicina ocupacional y del medio ambiente",
            "Medicina oncológica",
            "Nefrología",
            "Neumología",
            "Neurocirugía",
            "Neurología",
            "Oftalmología",
            "Ortopedia y traumatología",
            "Otorrinolaringología",
            "Patología clínica",
            "Pediatría",
            "Psiquiatría",
            "Radiología",
            "Radioterapia",
            "Reumatología",
            "Urología",
        ],
        "Subespecialidades" => [
            "Anestesiología cardiovascular",
            "Anestesiología obstétrica",
            "Anestesiología y terapia intensiva cardiovascular",
            "Cardiología pediátrica",
            "Cirugía cardiovascular pediátrica",
            "Cirugía de colon, recto y ano",
            "Cirugía de mano",
            "Cirugía de retina y vítreo",
            "Cirugía de tórax oncológica",
            "Cirugía hepatopancreatobiliar y trasplante",
            "Cirugía oncológica abdominal",
            "Cirugía oncológica de cabeza, cuello y maxilofacial",
            "Cirugía oncológica de mamas, tejidos blandos y piel",
            "Cirugía plástica y reconstructiva",
            "Dermatología pediátrica",
            "Emergencias pediátricas",
            "Endocrinología pediátrica",
            "Gastroenterología pediátrica",
            "Ginecología oncológica",
            "Hematología pediátrica",
            "Infectología pediátrica",
            "Medicina intensiva pediátrica",
            "Nefrología pediátrica",
            "Neonatología",
            "Neumología oncológica",
            "Neumología pediátrica",
            "Neurocirugía pediátrica",
            "Neurología pediátrica",
            "Oftalmología oncológica",
            "Oftalmología pediátrica y estrabismo",
            "Oncología pediátrica",
            "Patología oncológica",
            "Pediatría de emergencias y desastres",
            "Psiquiatría de adicciones",
            "Psiquiatría del niño y del adolescente",
            "Radiología intervencionista",
            "Urología oncológica",
            "Urología pediátrica",
        ],
    ];
    $opcionesPorClave = [];
    foreach ($catalogo as $opciones) {
        foreach ($opciones as $nombre) {
            $opcionesPorClave[claveEspecialidad($nombre)] = $nombre;
        }
    }
    $opcionesPorClave["medecina general"] = "Medicina General";
    $opcionesPorClave["traumatologia"] = "Ortopedia y traumatología";

    $registradas = $conexion->query("SELECT DISTINCT especialidad FROM doctores ORDER BY especialidad");
    while ($fila = $registradas->fetch_assoc()) {
        $nombre = trim($fila["especialidad"] ?? "");
        $clave = claveEspecialidad($nombre);
        if ($clave !== "" && !isset($opcionesPorClave[$clave])) {
            $catalogo["Otras registradas en la clínica"][] = $nombre;
            $opcionesPorClave[$clave] = $nombre;
        }
    }
    return [$catalogo, $opcionesPorClave];
}
