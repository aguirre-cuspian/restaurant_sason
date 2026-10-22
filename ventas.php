<?php
session_start();

// =====================================================
// ENCABEZADOS ANTI-CACHÉ
// =====================================================
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// =====================================================
// VALIDAR SESIÓN
// =====================================================
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario'] ?? "Fatima";
$usuario_rol    = $_SESSION['rol'] ?? "Administrador";

// =====================================================
// CONEXIÓN A LA BASE DE DATOS
// =====================================================
$host = "localhost";
$dbname = "restaurant_sason";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// =====================================================
// PRODUCTOS ACTIVOS
// =====================================================
$stmtProductosList = $pdo->query("
    SELECT id, nombre, precio
    FROM productos
    WHERE estado = 'Activo'
    ORDER BY nombre ASC
");

$lista_productos = $stmtProductosList->fetchAll();

// =====================================================
// FILTROS
// =====================================================
$fecha_evaluar = $_GET['fecha'] ?? '';
$metodo_evaluar = $_GET['metodo_pago'] ?? '';

$fecha_actual_label = $fecha_evaluar
    ? date("d M, Y", strtotime($fecha_evaluar))
    : "Todas las fechas";

// =====================================================
// ELIMINAR VENTA
// =====================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'eliminar_venta'
) {

    $venta_id_eliminar = intval($_POST['venta_id']);

    if ($venta_id_eliminar > 0) {

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                DELETE FROM detalle_ventas
                WHERE venta_id = ?
            ")->execute([$venta_id_eliminar]);

            $pdo->prepare("
                DELETE FROM ventas
                WHERE id = ?
            ")->execute([$venta_id_eliminar]);

            $pdo->commit();

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            die("Error al eliminar la venta: " . $e->getMessage());
        }

        header(
            "Location: ventas.php?fecha=" .
            urlencode($fecha_evaluar) .
            "&metodo_pago=" .
            urlencode($metodo_evaluar)
        );

        exit();
    }
}

// =====================================================
// GUARDAR NUEVA VENTA
// =====================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'guardar_venta'
) {

    $producto_id    = intval($_POST['producto_id']);
    $cantidad       = intval($_POST['cantidad']);
    $metodo_pago_id = intval($_POST['metodo_pago_id']);

    if ($producto_id <= 0 || $cantidad <= 0) {
        echo "
        <script>
            alert('Datos de venta inválidos.');
            window.location.href='ventas.php';
        </script>";
        exit();
    }

    // Buscar producto
    $stmtP = $pdo->prepare("
        SELECT precio, estado
        FROM productos
        WHERE id = ?
    ");

    $stmtP->execute([$producto_id]);

    $prodInfo = $stmtP->fetch();

    if (!$prodInfo || $prodInfo['estado'] !== 'Activo') {

        echo "
        <script>
            alert('Error: Este producto está inactivo y no se puede vender.');
            window.location.href='ventas.php';
        </script>";

        exit();
    }

    // Usuario
    $stmtUser = $pdo->query("
        SELECT id
        FROM usuarios
        LIMIT 1
    ");

    $uReg = $stmtUser->fetch();

    $usuario_id = $uReg['id'] ?? 1;

    $precio_unitario = floatval($prodInfo['precio']);
    $total = $precio_unitario * $cantidad;

    $fecha_actual = date("Y-m-d H:i:s");

    try {

        $pdo->beginTransaction();

        // Crear venta
        $stmtIns = $pdo->prepare("
            INSERT INTO ventas
            (fecha_hora, total, metodo_pago_id, usuario_id)
            VALUES (?, ?, ?, ?)
        ");

        $stmtIns->execute([
            $fecha_actual,
            $total,
            $metodo_pago_id,
            $usuario_id
        ]);

        $venta_id = $pdo->lastInsertId();

        // Crear detalle
        $subtotal = $cantidad * $precio_unitario;

        $stmtDet = $pdo->prepare("
            INSERT INTO detalle_ventas
            (venta_id, producto_id, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmtDet->execute([
            $venta_id,
            $producto_id,
            $cantidad,
            $precio_unitario,
            $subtotal
        ]);

        $pdo->commit();

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die("Error al registrar la venta: " . $e->getMessage());
    }

    header("Location: ventas.php");
    exit();
}

// =====================================================
// MÉTRICAS GENERALES
// =====================================================
if ($fecha_evaluar) {

    $stmtTotal = $pdo->prepare("
        SELECT
            COUNT(id) AS total_ventas,
            COALESCE(SUM(total), 0) AS suma_total
        FROM ventas
        WHERE DATE(fecha_hora) = ?
    ");

    $stmtTotal->execute([$fecha_evaluar]);

} else {

    $stmtTotal = $pdo->query("
        SELECT
            COUNT(id) AS total_ventas,
            COALESCE(SUM(total), 0) AS suma_total
        FROM ventas
    ");
}

$res_total = $stmtTotal->fetch();

$ventas_dia = floatval($res_total['suma_total']);
$cant_ventas_dia = intval($res_total['total_ventas']);

// =====================================================
// PAGOS EN EFECTIVO
// =====================================================
if ($fecha_evaluar) {

    $stmtEf = $pdo->prepare("
        SELECT
            COUNT(id) AS cant,
            COALESCE(SUM(total), 0) AS suma
        FROM ventas
        WHERE DATE(fecha_hora) = ?
        AND metodo_pago_id = 1
    ");

    $stmtEf->execute([$fecha_evaluar]);

    $res_efectivo = $stmtEf->fetch();

    // =================================================
    // OTROS MEDIOS
    // =================================================

    $stmtTj = $pdo->prepare("
        SELECT
            COUNT(id) AS cant,
            COALESCE(SUM(total), 0) AS suma
        FROM ventas
        WHERE DATE(fecha_hora) = ?
        AND metodo_pago_id = 2
    ");

    $stmtTj->execute([$fecha_evaluar]);

    $res_otros = $stmtTj->fetch();

} else {

    $stmtEf = $pdo->query("
        SELECT
            COUNT(id) AS cant,
            COALESCE(SUM(total), 0) AS suma
        FROM ventas
        WHERE metodo_pago_id = 1
    ");

    $res_efectivo = $stmtEf->fetch();

    $stmtTj = $pdo->query("
        SELECT
            COUNT(id) AS cant,
            COALESCE(SUM(total), 0) AS suma
        FROM ventas
        WHERE metodo_pago_id = 2
    ");

    $res_otros = $stmtTj->fetch();
}

$pagos_efectivo = floatval($res_efectivo['suma']);
$cant_efectivo   = intval($res_efectivo['cant']);

$pagos_otros = floatval($res_otros['suma']);
$cant_otros  = intval($res_otros['cant']);

$ticket_promedio = ($cant_ventas_dia > 0)
    ? ($ventas_dia / $cant_ventas_dia)
    : 0;

$pct_efectivo = ($ventas_dia > 0)
    ? round(($pagos_efectivo / $ventas_dia) * 100)
    : 0;

$pct_otros = ($ventas_dia > 0)
    ? round(($pagos_otros / $ventas_dia) * 100)
    : 0;

// =====================================================
// OBTENER DETALLE DE VENTAS
// =====================================================
$where_clauses = [];
$params = [];

if ($fecha_evaluar) {

    $where_clauses[] = "DATE(v.fecha_hora) = ?";
    $params[] = $fecha_evaluar;
}

if ($metodo_evaluar !== '') {

    $where_clauses[] = "v.metodo_pago_id = ?";
    $params[] = $metodo_evaluar;
}

$sql_ventas = "
    SELECT
        v.id AS venta_id,
        v.fecha_hora,
        v.metodo_pago_id,

        p.nombre AS producto_nombre,

        COALESCE(c.nombre, 'General') AS categoria_nombre,

        COALESCE(dv.cantidad, 1) AS cantidad,

        COALESCE(
            dv.precio_unitario,
            v.total
        ) AS precio_unitario,

        v.total

    FROM ventas v

    LEFT JOIN detalle_ventas dv
        ON v.id = dv.venta_id

    LEFT JOIN productos p
        ON dv.producto_id = p.id

    LEFT JOIN categorias c
        ON p.categoria_id = c.id
";

if (!empty($where_clauses)) {

    $sql_ventas .= "
        WHERE " .
        implode(" AND ", $where_clauses);
}

$sql_ventas .= "
    ORDER BY v.id DESC
";

$stmtVentas = $pdo->prepare($sql_ventas);

$stmtVentas->execute($params);

$resultado_ventas = $stmtVentas->fetchAll();

?>
<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Módulo de Ventas - Restaurant Sason</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link
rel="stylesheet"
href="css/corazon.css">

<style>

    /* =====================================================
   TABLA CON BARRA DESPLEGABLE
===================================================== */

.table-responsive {
    max-height: 270px;
    overflow-y: auto;
    overflow-x: auto;
}

/* Mantener encabezado visible al desplazarse */
.table-responsive table {
    width: 100%;
    border-collapse: collapse;
}

.table-responsive thead {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #ffffff;
}

/* Barra de desplazamiento */
.table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #8c7b70;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #3d220f;
}

/* =====================================================
   FONDO
===================================================== */

body {

    margin: 0;
    padding: 20px;

    background: url('imagen/fondo.png');

    background-size: cover;
    background-position: center;
    background-attachment: fixed;

    display: flex;
    justify-content: center;
    align-items: center;

    min-height: 100vh;
}

/* =====================================================
   MODAL DETALLE
===================================================== */

.detalle-modal {

    display: none;

    position: fixed;

    inset: 0;

    background: rgba(0, 0, 0, 0.65);

    align-items: center;
    justify-content: center;

    z-index: 9999;

    padding: 20px;
}

.detalle-contenido {

    background: #ffffff;

    width: 100%;
    max-width: 500px;

    border-radius: 16px;

    box-shadow:
        0 20px 60px rgba(0,0,0,0.30);

    overflow: hidden;

    animation: aparecer 0.2s ease;
}

@keyframes aparecer {

    from {

        transform: scale(0.90);
        opacity: 0;

    }

    to {

        transform: scale(1);
        opacity: 1;

    }
}

.detalle-header {

    background: #2b1810;

    color: white;

    padding: 18px 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.detalle-header h3 {

    margin: 0;

    font-size: 18px;

}

.detalle-cerrar {

    border: none;

    background: transparent;

    color: white;

    font-size: 25px;

    cursor: pointer;

    width: 35px;
    height: 35px;

    border-radius: 50%;
}

.detalle-cerrar:hover {

    background: rgba(255,255,255,0.15);
}

.detalle-body {

    padding: 22px;
}

.detalle-fila {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    padding: 13px 0;

    border-bottom: 1px solid #eeeeee;

}

.detalle-fila:last-child {

    border-bottom: none;

}

.detalle-label {

    color: #777;

    font-size: 14px;

}

.detalle-valor {

    color: #222;

    font-weight: 600;

    text-align: right;

}

.detalle-total {

    margin-top: 10px;

    padding: 15px;

    border-radius: 10px;

    background: #f5f5f5;

    display: flex;

    justify-content: space-between;

    font-size: 18px;

    font-weight: 700;
}

.detalle-footer {

    padding: 0 22px 22px;

    text-align: right;
}

.btn-cerrar-detalle {

    border: none;

    background: #2b1810;

    color: white;

    padding: 10px 20px;

    border-radius: 8px;

    cursor: pointer;

    font-family: Poppins, sans-serif;
}

.btn-cerrar-detalle:hover {

    opacity: 0.9;
}

/* =====================================================
   MODAL VENTA
===================================================== */

.modal-overlay {

    z-index: 5000;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 700px) {

    .detalle-contenido {

        max-width: 95%;
    }

    .detalle-fila {

        flex-direction: column;

        align-items: flex-start;

        gap: 4px;
    }

    .detalle-valor {

        text-align: left;
    }
}

</style>

</head>

<body>

<div class="dashboard-container">

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <i class="fa-solid fa-utensils logo-icon"></i>

        <h2>
            Restaurant <span>Sason</span>
        </h2>

    </div>

    <nav class="menu">

        <a href="gestion_sistemas.php"
           class="menu-item">

            <i class="fa-solid fa-house"></i>

            Inicio

        </a>

        <a href="ventas.php"
           class="menu-item active">

            <i class="fa-solid fa-cart-shopping"></i>

            Ventas

        </a>

        <a href="menu.php"
           class="menu-item">

            <i class="fa-solid fa-utensils"></i>

            Menú

        </a>

        <a href="gastos.php"
           class="menu-item">

            <i class="fa-solid fa-wallet"></i>

            Gastos

        </a>

        <a href="reportes.php"
           class="menu-item">

            <i class="fa-solid fa-chart-simple"></i>

            Reportes

        </a>

        <a href="usuarios.php"
           class="menu-item">

            <i class="fa-solid fa-users"></i>

            Usuarios

        </a>

        <a href="configuracion.php"
           class="menu-item">

            <i class="fa-solid fa-gear"></i>

            Configuración

        </a>

    </nav>

    <div class="sidebar-footer">

        <a href="logout.php"
           class="logout-btn">

            <i class="fa-solid fa-arrow-right-from-bracket"></i>

            Cerrar sesión

        </a>

    </div>

</aside>


<!-- =====================================================
     CONTENIDO PRINCIPAL
===================================================== -->

<main class="main-content">

<header class="top-header">

    <div class="welcome-text">

        <h1>Módulo de Ventas</h1>

        <p>
            Bienvenido,
            <?php echo htmlspecialchars($usuario_nombre); ?>

            (<?php echo htmlspecialchars($usuario_rol); ?>)
        </p>

    </div>

    <div class="header-actions">

        <div
        class="user-profile-dropdown"
        style="
        position:relative;
        display:inline-block;
        ">

            <button
            type="button"
            class="btn-profile-avatar"
            onclick="toggleUserMenu()"
            style="
            background:#2b1810;
            color:#fff;
            border:none;
            border-radius:50%;
            width:44px;
            height:44px;
            display:flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            ">

                <i class="fa-solid fa-user"></i>

            </button>

            <div
            id="userMenuDropdown"
            style="
            display:none;
            position:absolute;
            right:0;
            top:52px;
            background:#fff;
            border:1px solid #e0e0e0;
            border-radius:8px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            min-width:160px;
            z-index:1000;
            overflow:hidden;
            ">

                <a
                href="logout.php"
                style="
                display:flex;
                align-items:center;
                gap:8px;
                padding:12px 16px;
                text-decoration:none;
                color:#d32f2f;
                font-weight:500;
                font-size:14px;
                ">

                    <i class="fa-solid fa-arrow-right-from-bracket"></i>

                    Cerrar sesión

                </a>

            </div>

        </div>

    </div>

</header>


<!-- =====================================================
     CABECERA
===================================================== -->

<div class="section-header">

    <div class="section-title">

        <i class="fa-solid fa-chart-line"></i>

        <div>

            <h2>
                Resumen e Historial de Ventas
            </h2>

            <p>
                Monitorea los ingresos e historial
                de transacciones en tiempo real
            </p>

        </div>

    </div>

    <button
    class="btn-primary"
    onclick="abrirModalVenta()">

        <i class="fa-solid fa-plus"></i>

        Nueva Venta

    </button>

</div>


<!-- =====================================================
     MÉTRICAS
===================================================== -->

<div class="metrics-grid">

    <div class="card metric-card">

        <div class="icon-box green">

            <i class="fa-solid fa-dollar-sign"></i>

        </div>

        <div class="metric-info">

            <div class="label">
                Ventas Registradas
            </div>

            <div class="value green">

                $<?php
                echo number_format(
                    $ventas_dia,
                    0,
                    ',',
                    '.'
                );
                ?>

            </div>

            <div class="indicator">

                <?php echo $cant_ventas_dia; ?>

                transacciones realizadas

            </div>

        </div>

    </div>


    <div class="card metric-card">

        <div class="icon-box blue">

            <i class="fa-solid fa-money-bill-wave"></i>

        </div>

        <div class="metric-info">

            <div class="label">
                Pagos Efectivo
            </div>

            <div class="value blue">

                $<?php
                echo number_format(
                    $pagos_efectivo,
                    0,
                    ',',
                    '.'
                );
                ?>

            </div>

            <div class="indicator">

                <?php echo $cant_efectivo; ?>

                ventas hechas

            </div>

        </div>

    </div>


    <div class="card metric-card">

        <div class="icon-box yellow">

            <i class="fa-solid fa-wallet"></i>

        </div>

        <div class="metric-info">

            <div class="label">
                Otros Medios
            </div>

            <div class="value dark">

                $<?php
                echo number_format(
                    $pagos_otros,
                    0,
                    ',',
                    '.'
                );
                ?>

            </div>

            <div class="indicator">

                <?php echo $cant_otros; ?>

                ventas hechas

            </div>

        </div>

    </div>


    <div class="card metric-card">

        <div class="icon-box red">

            <i class="fa-solid fa-receipt"></i>

        </div>

        <div class="metric-info">

            <div class="label">
                Ticket Promedio
            </div>

            <div class="value red">

                $<?php
                echo number_format(
                    $ticket_promedio,
                    0,
                    ',',
                    '.'
                );
                ?>

            </div>

            <div class="indicator">
                Por transacción
            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     FILTROS
===================================================== -->

<form
method="GET"
action="ventas.php"
class="card filters-card">

    <div class="filter-group search-group">

        <label>Buscar</label>

        <div class="input-wrapper">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
            type="text"
            class="filter-control"
            placeholder="Buscar por ID o detalles...">

        </div>

    </div>


    <div class="filter-group">

        <label>

            Fecha

            <a
            href="ventas.php"
            style="
            font-size:11px;
            margin-left:5px;
            ">

                (Ver todas)

            </a>

        </label>

        <div
        class="date-input-wrapper"
        style="
        display:flex;
        gap:5px;
        align-items:center;
        ">

            <input
            type="date"
            name="fecha"
            class="filter-control"
            value="<?php echo htmlspecialchars($fecha_evaluar); ?>"
            onchange="this.form.submit()">

        </div>

    </div>


    <div class="filter-group">

        <label>
            Método Pago
        </label>

        <select
        name="metodo_pago"
        class="filter-control"
        onchange="this.form.submit()">

            <option value="">
                Todos los métodos
            </option>

            <option
            value="1"
            <?php
            echo ($metodo_evaluar === '1')
                ? 'selected'
                : '';
            ?>>

                Efectivo

            </option>

            <option
            value="2"
            <?php
            echo ($metodo_evaluar === '2')
                ? 'selected'
                : '';
            ?>>

                Otros medios

            </option>

        </select>

    </div>

</form>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<div class="content-grid">


<!-- =====================================================
     TABLA
===================================================== -->

<div class="card table-card">

    <div class="table-header">

        <i class="fa-solid fa-list"></i>

        Historial de Ventas

        <?php

        echo $fecha_evaluar
            ? "(" . date(
                "d/m/Y",
                strtotime($fecha_evaluar)
            ) . ")"
            : "(Todas)";

        ?>

    </div>


    <div class="table-responsive">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Producto</th>

                    <th>Categoría</th>

                    <th>Cantidad</th>

                    <th>Precio Unit.</th>

                    <th>Total</th>

                    <th>Método de pago</th>

                    <th>Acciones</th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($resultado_ventas)): ?>

                <?php foreach ($resultado_ventas as $venta): ?>

                    <?php

                    $metodo_nombre =
                        ($venta['metodo_pago_id'] == 1)
                        ? 'Efectivo'
                        : 'Otros medios';

                    $prod_nombre =
                        $venta['producto_nombre']
                        ?? 'Producto General';

                    $cat_nombre =
                        $venta['categoria_nombre']
                        ?? 'General';

                    $cantidad =
                        $venta['cantidad'];

                    $precio_u_fmt =
                        "$" .
                        number_format(
                            $venta['precio_unitario'],
                            0,
                            ',',
                            '.'
                        );

                    $total_fmt =
                        "$" .
                        number_format(
                            $venta['total'],
                            0,
                            ',',
                            '.'
                        );

                    $fecha_fmt =
                        date(
                            "d/m/Y H:i",
                            strtotime(
                                $venta['fecha_hora']
                            )
                        );

                    ?>

                    <tr>

                        <td>

                            <strong>
                                <?php
                                echo $venta['venta_id'];
                                ?>
                            </strong>

                        </td>


                        <td>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $prod_nombre
                                );
                                ?>
                            </strong>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $cat_nombre
                            );
                            ?>

                        </td>


                        <td>
                            <?php
                            echo $cantidad;
                            ?>
                        </td>


                        <td>
                            <?php
                            echo $precio_u_fmt;
                            ?>
                        </td>


                        <td>

                            <strong>
                                <?php
                                echo $total_fmt;
                                ?>
                            </strong>

                        </td>


                        <td>

                            <span
                            class="badge
                            <?php
                            echo
                                ($venta['metodo_pago_id'] == 1)
                                ? 'efectivo'
                                : 'otros';
                            ?>">

                                <i
                                class="fa-solid
                                <?php
                                echo
                                    ($venta['metodo_pago_id'] == 1)
                                    ? 'fa-money-bill-wave'
                                    : 'fa-wallet';
                                ?>">
                                </i>

                                <?php
                                echo $metodo_nombre;
                                ?>

                            </span>

                        </td>


                        <td>

                            <!-- BOTÓN OJO -->

                            <button
                            type="button"
                            class="btn-icon edit"
                            title="Ver detalle"

                            onclick="verDetalleVenta(
                                <?php echo (int)$venta['venta_id']; ?>,
                                '<?php echo htmlspecialchars($fecha_fmt, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($prod_nombre, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($cat_nombre, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($cantidad, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($metodo_nombre, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($precio_u_fmt, ENT_QUOTES, 'UTF-8'); ?>',
                                '<?php echo htmlspecialchars($total_fmt, ENT_QUOTES, 'UTF-8'); ?>'
                            )">

                                <i class="fa-solid fa-eye"></i>

                            </button>


                            <!-- BOTÓN ELIMINAR -->

                            <button
                            type="button"
                            class="btn-icon delete"
                            title="Eliminar Venta"

                            onclick="eliminarVenta(
                                <?php
                                echo (int)$venta['venta_id'];
                                ?>
                            )">

                                <i
                                class="fa-solid fa-trash">
                                </i>

                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                    colspan="8"
                    style="
                    text-align:center;
                    padding:20px;
                    ">

                        No hay registros
                        de ventas guardados.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     PANEL LATERAL
===================================================== -->

<div class="right-panel">

    <div class="card summary-card">

        <div class="summary-title">

            Distribución de Ingresos

        </div>


        <div class="chart-container">

            <div
            class="donut-chart"
            style="
            background:
            conic-gradient(
                #4CAF50 0%
                <?php echo $pct_efectivo; ?>%,

                #2196F3
                <?php echo $pct_efectivo; ?>%
                100%
            );
            ">

            </div>


            <div class="chart-legend">

                <div class="legend-item">

                    <span
                    class="dot servicios">
                    </span>

                    <div class="legend-info">

                        Efectivo

                        <span>
                            <?php
                            echo $pct_efectivo;
                            ?>%
                        </span>

                    </div>

                </div>


                <div class="legend-item">

                    <span
                    class="dot compras">
                    </span>

                    <div class="legend-info">

                        Nequi

                        <span>
                            <?php
                            echo $pct_otros;
                            ?>%
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</div>

</main>

</div>


<!-- =====================================================
     FORMULARIO ELIMINAR
===================================================== -->

<form
id="formEliminar"
method="POST"
action="ventas.php"
style="display:none;">

    <input
    type="hidden"
    name="accion"
    value="eliminar_venta">

    <input
    type="hidden"
    name="venta_id"
    id="input_venta_id"
    value="">

</form>


<!-- =====================================================
     MODAL NUEVA VENTA
===================================================== -->

<div
id="modalVenta"
class="modal-overlay"
style="display:none;">

    <div class="modal-content">

        <div class="modal-header">

            <h3>

                <i
                class="fa-solid fa-cart-plus">
                </i>

                Registrar Nueva Venta

            </h3>

            <button
            class="close-btn"
            type="button"
            onclick="cerrarModalVenta()">

                &times;

            </button>

        </div>


        <form
        action="ventas.php"
        method="POST">

            <input
            type="hidden"
            name="accion"
            value="guardar_venta">


            <div
            class="form-group-modal"
            style="margin-bottom:12px;">

                <label>
                    Producto:
                </label>

                <select
                name="producto_id"
                class="filter-control"
                required>

                    <option value="">
                        -- Seleccionar Producto --
                    </option>

                    <?php foreach ($lista_productos as $prod): ?>

                        <option
                        value="<?php
                        echo $prod['id'];
                        ?>">

                            <?php
                            echo htmlspecialchars(
                                $prod['nombre']
                            );
                            ?>

                            -

                            $<?php
                            echo number_format(
                                $prod['precio'],
                                0,
                                ',',
                                '.'
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div
            class="form-group-modal"
            style="margin-bottom:12px;">

                <label>
                    Cantidad:
                </label>

                <input
                type="number"
                name="cantidad"
                class="filter-control"
                value="1"
                min="1"
                required>

            </div>


            <div
            class="form-group-modal"
            style="margin-bottom:18px;">

                <label>
                    Método de Pago:
                </label>

                <select
                name="metodo_pago_id"
                class="filter-control"
                required>

                    <option value="1">
                        Efectivo
                    </option>

                    <option value="2">
                        Otros Medios
                    </option>

                </select>

            </div>


            <div class="modal-actions">

                <button
                type="button"
                class="btn-outline-full"
                onclick="cerrarModalVenta()">

                    Cancelar

                </button>

                <button
                type="submit"
                class="btn-primary">

                    Guardar Venta

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     MODAL VER DETALLE
===================================================== -->

<div
id="modalDetalle"
class="detalle-modal"
onclick="cerrarDetalleDesdeFondo(event)">

    <div
    class="detalle-contenido"
    onclick="event.stopPropagation()">


        <div class="detalle-header">

            <h3>

                <i
                class="fa-solid fa-eye">
                </i>

                Detalle de la venta

            </h3>

            <button
            type="button"
            class="detalle-cerrar"
            onclick="cerrarDetalle()">

                &times;

            </button>

        </div>


        <div class="detalle-body">

            <div class="detalle-fila">

                <span class="detalle-label">
                    Número de venta
                </span>

                <span
                class="detalle-valor"
                id="detalleId">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Fecha
                </span>

                <span
                class="detalle-valor"
                id="detalleFecha">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Producto
                </span>

                <span
                class="detalle-valor"
                id="detalleProducto">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Categoría
                </span>

                <span
                class="detalle-valor"
                id="detalleCategoria">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Cantidad
                </span>

                <span
                class="detalle-valor"
                id="detalleCantidad">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Precio unitario
                </span>

                <span
                class="detalle-valor"
                id="detallePrecio">
                </span>

            </div>


            <div class="detalle-fila">

                <span class="detalle-label">
                    Método de pago
                </span>

                <span
                class="detalle-valor"
                id="detalleMetodo">
                </span>

            </div>


            <div class="detalle-total">

                <span>
                    Total
                </span>

                <span id="detalleTotal">
                </span>

            </div>

        </div>


        <div class="detalle-footer">

            <button
            type="button"
            class="btn-cerrar-detalle"
            onclick="cerrarDetalle()">

                Cerrar

            </button>

        </div>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

// =====================================================
// MENÚ DE USUARIO
// =====================================================

function toggleUserMenu() {

    const menu =
        document.getElementById(
            'userMenuDropdown'
        );

    if (!menu) return;

    menu.style.display =
        menu.style.display === 'block'
        ? 'none'
        : 'block';
}


// =====================================================
// CERRAR MENÚ AL HACER CLIC AFUERA
// =====================================================

document.addEventListener(
    'click',
    function(event) {

        const menu =
            document.getElementById(
                'userMenuDropdown'
            );

        const boton =
            document.querySelector(
                '.btn-profile-avatar'
            );

        if (
            menu &&
            boton &&
            !menu.contains(event.target) &&
            !boton.contains(event.target)
        ) {

            menu.style.display = 'none';

        }

    }
);


// =====================================================
// ABRIR MODAL NUEVA VENTA
// =====================================================

function abrirModalVenta() {

    const modal =
        document.getElementById(
            'modalVenta'
        );

    if (modal) {

        modal.style.display = 'flex';

    }
}


// =====================================================
// CERRAR MODAL NUEVA VENTA
// =====================================================

function cerrarModalVenta() {

    const modal =
        document.getElementById(
            'modalVenta'
        );

    if (modal) {

        modal.style.display = 'none';

    }
}


// =====================================================
// VER DETALLE DE VENTA
// =====================================================

function verDetalleVenta(
    id,
    fecha,
    producto,
    categoria,
    cantidad,
    metodo,
    precio,
    total
) {

    document.getElementById(
        'detalleId'
    ).textContent = '#' + id;

    document.getElementById(
        'detalleFecha'
    ).textContent = fecha;

    document.getElementById(
        'detalleProducto'
    ).textContent = producto;

    document.getElementById(
        'detalleCategoria'
    ).textContent = categoria;

    document.getElementById(
        'detalleCantidad'
    ).textContent = cantidad;

    document.getElementById(
        'detallePrecio'
    ).textContent = precio;

    document.getElementById(
        'detalleMetodo'
    ).textContent = metodo;

    document.getElementById(
        'detalleTotal'
    ).textContent = total;


    const modal =
        document.getElementById(
            'modalDetalle'
        );

    modal.style.display = 'flex';

}


// =====================================================
// CERRAR DETALLE
// =====================================================

function cerrarDetalle() {

    const modal =
        document.getElementById(
            'modalDetalle'
        );

    if (modal) {

        modal.style.display = 'none';

    }

}


// =====================================================
// CERRAR DETALLE HACIENDO CLIC EN EL FONDO
// =====================================================

function cerrarDetalleDesdeFondo(event) {

    if (
        event.target.id ===
        'modalDetalle'
    ) {

        cerrarDetalle();

    }

}


// =====================================================
// ELIMINAR VENTA
// =====================================================

function eliminarVenta(id) {

    if (
        confirm(
            '¿Desea eliminar la venta #' +
            id +
            '?'
        )
    ) {

        document.getElementById(
            'input_venta_id'
        ).value = id;

        document.getElementById(
            'formEliminar'
        ).submit();

    }

}


// =====================================================
// CERRAR MODALES CON ESC
// =====================================================

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            cerrarDetalle();

            cerrarModalVenta();

        }

    }
);

</script>

</body>

</html>