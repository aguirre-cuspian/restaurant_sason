<?php
session_start();

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Evitar caché de redirección
header("Cache-Control: no-cache, no-store, must-revalidate"); // Para navegadores modernos
header("Pragma: no-cache");                                   // Para navegadores antiguos / compatibilidad
header("Expires: 0");                                         // Expira la página inmediatamente

header("Location: index.php");
exit();
?>