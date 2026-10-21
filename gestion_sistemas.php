<?php
session_start();

// 1. Encabezados estrictos anti-caché
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// 2. Validación de sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Datos de la sesión
$usuario      = $_SESSION['usuario'] ?? "Fatima";
$rol          = $_SESSION['rol'] ?? "Administrador";
$fecha_actual = date("d/m/Y");

// 3. Conexión a la base de datos mediante PDO
$host     = "localhost";
$dbname   = "restaurant_sason";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// 4. Obtener Total Ventas Acumulado (Histórico completo)
try {
    $stmtVentas = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM ventas");
    $total_ventas = floatval($stmtVentas->fetchColumn());
} catch (PDOException $e) {
    $total_ventas = 0;
}

// 5. Obtener Total Gastos Acumulado (Histórico completo)
try {
    $stmtGastos = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM gastos");
    $total_gastos = floatval($stmtGastos->fetchColumn());
} catch (PDOException $e) {
    $total_gastos = 0;
}

// 6. Ganancia Total Acumulada
$ganancia_dia = $total_ventas - $total_gastos;

// 7. Obtener lista de Ventas Vinculada con Detalle y Categorías
$ventas_hoy = [];
try {
    $sql = "SELECT 
                v.id, 
                v.fecha_hora, 
                v.total, 
                v.metodo_pago_id,
                COALESCE(p.nombre, 'Venta General') AS producto,
                COALESCE(c.nombre, 'General') AS categoria,
                COALESCE(dv.cantidad, 1) AS cantidad,
                COALESCE(dv.precio_unitario, v.total) AS precio
            FROM ventas v
            LEFT JOIN detalle_ventas dv ON v.id = dv.venta_id
            LEFT JOIN productos p ON dv.producto_id = p.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            ORDER BY v.fecha_hora DESC
            LIMIT 10";

    $stmtTabla = $pdo->query($sql);
    $ventas_hoy = $stmtTabla->fetchAll();
} catch (PDOException $e) {
    try {
        $stmtTabla = $pdo->query("SELECT id, fecha_hora, total, metodo_pago_id FROM ventas ORDER BY fecha_hora DESC LIMIT 10");
        $ventas_hoy = $stmtTabla->fetchAll();
    } catch (PDOException $e2) {
        $ventas_hoy = [];
    }
}

// Datos por defecto (Solo en caso de que la tabla esté totalmente vacía)
$productos_ejemplo = [
    1 => ["producto" => "Desayuno Especial", "categoria" => "Desayunos", "cantidad" => 8, "precio" => 12000, "total" => 96000, "metodo_pago_id" => 1],
    2 => ["producto" => "Almuerzo Ejecutivo", "categoria" => "Almuerzos", "cantidad" => 15, "precio" => 18000, "total" => 270000, "metodo_pago_id" => 2],
    3 => ["producto" => "Bebida Natural", "categoria" => "Bebidas", "cantidad" => 20, "precio" => 4000, "total" => 80000, "metodo_pago_id" => 1],
    4 => ["producto" => "Jugo en Leche", "categoria" => "Bebidas", "cantidad" => 10, "precio" => 6000, "total" => 60000, "metodo_pago_id" => 2],
    5 => ["producto" => "Porción de Fruta", "categoria" => "Adicionales", "cantidad" => 12, "precio" => 3000, "total" => 36000, "metodo_pago_id" => 1],
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sason - Inicio</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/gestion.css">

    <style>
        /* Estilos directos para el menú flotante del usuario */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-avatar {
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background-color: #ffffff;
            padding: 8px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 160px;
        }

        .dropdown-menu.show {
            display: block !important;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: #fdf2f2;
            color: #d32f2f;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: #ffebee;
        }

        /* Estilos para Badges de Métodos de Pago */
        .badge.efectivo {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .badge.otros {
            background-color: #fff3e0;
            color: #e65100;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand">
                <i class="fa-solid fa-utensils logo-icon"></i>
                <h2>Restaurant <span>Sason</span></h2>
            </div>

            <nav class="menu">
                <a href="gestion_sistemas.php" class="menu-item active"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="ventas.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i> Ventas</a>
                <a href="menu.php" class="menu-item"><i class="fa-solid fa-utensils"></i> Menú</a>
                <a href="gastos.php" class="menu-item"><i class="fa-solid fa-wallet"></i> Gastos</a>
                <a href="reportes.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> Reportes</a>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users"></i> Usuarios</a>
                <a href="configuracion.php" class="menu-item"><i class="fa-solid fa-gear"></i> Configuración</a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-content">
            <header class="top-header">
                <div class="welcome-text">
                    <h1>¡Bienvenido, <?php echo htmlspecialchars($usuario); ?>!</h1>
                    <p>Rol: <strong><?php echo htmlspecialchars($rol); ?></strong></p>
                </div>
                <div class="header-actions">
                    <div class="date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        <span><?php echo $fecha_actual; ?></span>
                    </div>
                    <div class="user-dropdown">
                        <div class="user-avatar" id="userMenuBtn">
                            <i class="fa-solid fa-user"></i>
                            <i class="fa-solid fa-chevron-down arrow"></i>
                        </div>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <a href="logout.php" class="dropdown-item">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Tarjetas de Métricas -->
            <section class="metrics-grid">
                <div class="card metric-card">
                    <div class="icon-box green"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="metric-info">
                        <span class="label">Total Ventas </span>
                        <div class="value green">$<?php echo number_format($total_ventas, 0, ',', '.'); ?></div>
                        <span class="indicator positive"><i class="fa-solid fa-arrow-up"></i> Actualizado</span>
                    </div>
                </div>

                <div class="card metric-card">
                    <div class="icon-box red"><i class="fa-solid fa-wallet"></i></div>
                    <div class="metric-info">
                        <span class="label">Total Gastos </span>
                        <div class="value red">$<?php echo number_format($total_gastos, 0, ',', '.'); ?></div>
                        <span class="indicator negative"><i class="fa-solid fa-arrow-down"></i> Actualizado</span>
                    </div>
                </div>

                <div class="card metric-card">
                    <div class="icon-box yellow"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="metric-info">
                        <span class="label">Ganancia Total Acumulada</span>
                        <div class="value dark">$<?php echo number_format($ganancia_dia, 0, ',', '.'); ?></div>
                        <span class="indicator positive"><i class="fa-solid fa-arrow-up"></i> Neto acumulado</span>
                    </div>
                </div>
            </section>

            <!-- Sección de la Tabla -->
            <section class="card table-card" style="margin-top: 25px;">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-bars" style="font-size: 18px;"></i>
                        <h3 style="margin: 0; font-size: 18px;">Últimas ventas registradas</h3>
                    </div>
                    <a href="ventas.php" class="btn-primary" style="text-decoration: none; padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                        Ver todas <i class="fa-solid fa-arrow-right"></i>
                    </a>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $listado_mostrar = !empty($ventas_hoy) ? $ventas_hoy : $productos_ejemplo;
                            foreach ($listado_mostrar as $index => $item): 
                                $id        = $item['id'] ?? $index;
                                $producto  = $item['producto'] ?? 'Venta General';
                                $categoria = $item['categoria'] ?? 'General';
                                $cantidad  = $item['cantidad'] ?? 1;
                                $total     = $item['total'] ?? 0;
                                $precio    = $item['precio'] ?? ($total > 0 ? $total / max($cantidad, 1) : 0);

                                $metodo_id = $item['metodo_pago_id'] ?? 1;
                                $metodo    = ($metodo_id == 1) ? 'Efectivo' : (($metodo_id == 2) ? 'otros Medios' : 'otros Medios');
                            ?>
                                <tr>
                                    <td><strong><?php echo $id; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($producto); ?></strong></td>
                                    <td><?php echo htmlspecialchars($categoria); ?></td>
                                    <td><?php echo $cantidad; ?></td>
                                    <td>$<?php echo number_format($precio, 0, ',', '.'); ?></td>
                                    <td><strong>$<?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo ($metodo_id == 1) ? 'efectivo' : 'otros'; ?>">
                                            <i class="fa-solid <?php echo ($metodo_id == 1) ? 'fa-money-bill-wave' : 'fa-wallet'; ?>"></i>
                                            <?php echo htmlspecialchars($metodo); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Script anti-caché de historial -->
    <script>
        window.addEventListener("pageshow", function (event) {
            var perfEntries = performance.getEntriesByType("navigation");
            if (event.persisted || (perfEntries.length > 0 && perfEntries[0].type === "back_forward")) {
                window.location.reload();
            }
        });
    </script>

    <!-- Script interactivo del avatar -->
    <script>
        const userMenuBtn = document.getElementById('userMenuBtn');
        const dropdownMenu = document.getElementById('dropdownMenu');

        userMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!userMenuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    </script>
</body>
</html>