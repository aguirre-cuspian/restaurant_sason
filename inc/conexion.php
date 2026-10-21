<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "restaurant_sason";

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>