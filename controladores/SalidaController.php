<?php

session_start();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	header("Allow: POST");
	http_response_code(405);
	exit("Método no permitido.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
	http_response_code(403);
	exit("Solicitud no válida.");
}

$actorAuditoria = $_SESSION;
$_SESSION = [];

if (ini_get("session.use_cookies")) {
	$cookieParams = session_get_cookie_params();
	setcookie(session_name(), "", time() - 42000, $cookieParams["path"], $cookieParams["domain"], $cookieParams["secure"], $cookieParams["httponly"]);
}

session_destroy();
if (auditoriaId($actorAuditoria["id"] ?? null)!==null && ($dbAud=auditoriaConexionOpcional())) {
    auditar($dbAud,"seguridad","cerrar_sesion","exitoso","usuario",auditoriaId($actorAuditoria["id"]),$actorAuditoria);
    $dbAud->close();
}

header("Location: index.php");
exit;

?>