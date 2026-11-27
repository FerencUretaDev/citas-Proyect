<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    if (PHP_OS_FAMILY === "Windows") {

        // Entorno local: XAMPP
        $host = getenv("DB_HOST") ?: "localhost";
        $usuario = getenv("DB_USER") ?: "root";
        $password = getenv("DB_PASSWORD");

        if ($password === false) {
            $password = "";
        }

        $baseDatos = getenv("DB_NAME") ?: "sistema";

        $conexion = new mysqli(
            $host,
            $usuario,
            $password,
            $baseDatos
        );

    } else {

        // Entorno remoto: Linux
        $rutaConfig = getenv("DB_CONFIG_FILE")
            ?: "/home/sistemacitasmedicas/private/config/database.json";

        if (!is_readable($rutaConfig)) {
            throw new RuntimeException(
                "Configuracion privada no disponible."
            );
        }

        $config = json_decode(
            file_get_contents($rutaConfig),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            empty($config["DB_HOST"]) ||
            empty($config["DB_USER"]) ||
            empty($config["DB_PASSWORD"]) ||
            empty($config["DB_NAME"]) ||
            ($config["DB_SSL"] ?? false) !== true
        ) {
            throw new RuntimeException(
                "Configuracion de base de datos invalida."
            );
        }

        $conexion = mysqli_init();

        $conexion->options(
            MYSQLI_OPT_SSL_VERIFY_SERVER_CERT,
            true
        );

        $conexion->ssl_set(
            null,
            null,
            "/etc/ssl/certs/ca-certificates.crt",
            null,
            null
        );

        $conexion->real_connect(
            $config["DB_HOST"],
            $config["DB_USER"],
            $config["DB_PASSWORD"],
            $config["DB_NAME"],
            (int) ($config["DB_PORT"] ?? 3306),
            null,
            MYSQLI_CLIENT_SSL
        );

        // Comprobar que la conexion esta cifrada
        $resultado = $conexion->query(
            "SHOW SESSION STATUS LIKE 'Ssl_cipher'"
        );

        $fila = $resultado->fetch_row();

        if (empty($fila[1])) {
            $conexion->close();

            throw new RuntimeException(
                "Conexion TLS obligatoria."
            );
        }
    }

    $conexion->set_charset("utf8mb4");

// Establecer la zona horaria de Perú para PHP.
date_default_timezone_set("America/Lima");

// Establecer la zona horaria de la sesión MySQL/MariaDB.
$conexion->query("SET time_zone = '-05:00'");

} catch (Throwable $e) {

    error_log(
        "Error de conexion a MariaDB. Codigo: "
        . $e->getCode()
    );

    http_response_code(500);

    exit("No se pudo conectar con la base de datos.");
}
