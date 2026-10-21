<?php
require_once "conexion.php";

// Obtener la suma total de gastos del día de hoy
$stmt_hoy = $pdo->query("SELECT SUM(total) AS total FROM gastos WHERE DATE(fecha_hora) = CURDATE()");
$total_gastos = $stmt_hoy->fetch()['total'] ?? 0;

// Obtener gastos agrupados por categoría para la tarjeta de resumen
$stmt_cat = $pdo->query("SELECT categoria, SUM(total) AS total_cat FROM gastos WHERE DATE(fecha_hora) = CURDATE() GROUP BY categoria");
$gastos_categoria = $stmt_cat->fetchAll(PDO::FETCH_KEY_PAIR);

$compras = $gastos_categoria['Compras'] ?? 0;
$servicios = $gastos_categoria['Servicios'] ?? 0;
$otros = $gastos_categoria['Otros'] ?? 0;

// Obtener listado general de gastos
$stmt_lista = $pdo->query("SELECT * FROM gastos ORDER BY fecha_hora DESC LIMIT 50");
$lista_gastos = $stmt_lista->fetchAll();
?>