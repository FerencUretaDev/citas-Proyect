<?php
session_start();
include dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) . "/servicios/AutenticacionService.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = trim(is_string($_POST["usuario"] ?? null) ? $_POST["usuario"] : "");
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    $datos = autenticarUsuario($conexion, $usuario, $password);
    if ($datos !== null) {
        session_regenerate_id(true);
        $_SESSION["id"] = $datos["id"];
        $_SESSION["usuario"] = $datos["usuario"];
        $_SESSION["rol"] = (int)$datos["rol"];
        header("Location: panel");
        exit;
    }
    $mensaje = "Usuario o contraseña incorrectos";
}

require dirname(__DIR__) . "/vistas/index.php";
