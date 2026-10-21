<?php
// -------------------------------------------------------------
// EXPORTACIÓN A EXCEL (CSV)
// -------------------------------------------------------------
$host = "localhost";
$user = "root";
$password = "";
$database = "restaurant_sason";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Lógica de fechas
$fecha_actual_formatted = date('d/m/Y');
$rango_fechas_formatted = $_GET['rango'] ?? "Histórico General (Todas las fechas)";
$tipo_reporte = $_GET['tipo'] ?? 'resumen';

$where_ventas = "";
$where_gastos = "";

if (isset($_GET['rango']) && !empty($_GET['rango']) && $_GET['rango'] !== "Histórico General (Todas las fechas)") {
    $fechas = explode(" - ", $_GET['rango']);
    if (count($fechas) === 2) {
        $f_ini = explode('/', trim($fechas[0]));
        $f_fin = explode('/', trim($fechas[1]));

        if (count($f_ini) === 3 && count($f_fin) === 3) {
            $fecha_inicio = $conn->real_escape_string("{$f_ini[2]}-{$f_ini[1]}-{$f_ini[0]} 00:00:00");
            $fecha_fin = $conn->real_escape_string("{$f_fin[2]}-{$f_fin[1]}-{$f_fin[0]} 23:59:59");

            $where_ventas = " WHERE fecha_hora BETWEEN '$fecha_inicio' AND '$fecha_fin'";
            $where_gastos = " WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'";
        }
    } else if (count($fechas) === 1 && !empty(trim($fechas[0]))) {
        $busqueda = $conn->real_escape_string(trim($fechas[0]));
        $where_ventas = " WHERE fecha_hora LIKE '%$busqueda%'";
        $where_gastos = " WHERE fecha LIKE '%$busqueda%'";
    }
}

// 1. KPI Ventas
$sql_ventas = "SELECT SUM(total) AS total_ventas, COUNT(id) AS total_transacciones FROM ventas" . $where_ventas;
$res_ventas = $conn->query($sql_ventas);
$row_ventas = ($res_ventas) ? $res_ventas->fetch_assoc() : [];

$num_total_ventas = $row_ventas['total_ventas'] ?? 0;
$transacciones = $row_ventas['total_transacciones'] ?? 0;

// 2. KPI Gastos
$sql_gastos = "SELECT SUM(total) AS total_gastos FROM gastos" . $where_gastos;
$res_gastos = $conn->query($sql_gastos);
$row_gastos = ($res_gastos) ? $res_gastos->fetch_assoc() : [];

$num_total_gastos = $row_gastos['total_gastos'] ?? 0;
$num_ganancia_neta = $num_total_ventas - $num_total_gastos;

$total_ventas = "$" . number_format($num_total_ventas, 0, ',', '.');
$total_gastos = "$" . number_format($num_total_gastos, 0, ',', '.');
$ganancia_neta = "$" . number_format($num_ganancia_neta, 0, ',', '.');

// EXPORTAR EXCEL SI ES SOLICITADO
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Reporte_Sason_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['RESTAURANT SASON - REPORTE DE VENTAS']);
    fputcsv($output, ['Fecha de Emisión', date('d/m/Y H:i')]);
    fputcsv($output, []);
    
    fputcsv($output, ['Métrica', 'Valor']);
    fputcsv($output, ['Total Ventas', $total_ventas]);
    fputcsv($output, ['Total Gastos', $total_gastos]);
    fputcsv($output, ['Ganancia Neta', $ganancia_neta]);
    fputcsv($output, ['Transacciones', $transacciones]);
    fputcsv($output, []);

    $sql_productos_exp = "SELECT p.id, p.nombre AS producto, COALESCE(c.nombre, 'General') AS categoria, SUM(dv.cantidad) AS cantidad, SUM(dv.subtotal) AS total 
                    FROM detalle_ventas dv
                    JOIN productos p ON dv.producto_id = p.id
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    GROUP BY p.id ORDER BY cantidad DESC LIMIT 5";
    $res_prod_exp = $conn->query($sql_productos_exp);

    fputcsv($output, ['ID', 'Producto', 'Categoria', 'Cantidad Vendida', 'Total']);
    if ($res_prod_exp && $res_prod_exp->num_rows > 0) {
        while ($p = $res_prod_exp->fetch_assoc()) {
            fputcsv($output, [$p['id'], $p['producto'], $p['categoria'], $p['cantidad'], $p['total']]);
        }
    }

    fclose($output);
    exit();
}

// 3. Ventas por Día
$sql_dias = "SELECT DATE(fecha_hora) as fecha, SUM(total) as total 
            FROM ventas 
            " . ($where_ventas ? $where_ventas : "WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)") . "
            GROUP BY DATE(fecha_hora) 
            ORDER BY fecha ASC";

$res_dias = $conn->query($sql_dias);
$ventas_por_dia = [];

if ($res_dias && $res_dias->num_rows > 0) {
    while ($r = $res_dias->fetch_assoc()) {
        $ventas_por_dia[$r['fecha']] = (float)$r['total'];
    }
}

$max_venta = !empty($ventas_por_dia) ? max($ventas_por_dia) : 1;
if ($max_venta == 0) $max_venta = 1;

$puntos_path = [];
$puntos_svg = [];
$cant_puntos = max(count($ventas_por_dia), 1);
$x_step = 455 / max(($cant_puntos - 1), 1);
$i = 0;

foreach ($ventas_por_dia as $fecha => $monto) {
    $x = ($cant_puntos === 1) ? 250 : 30 + ($i * $x_step);
    $y = 140 - (($monto / $max_venta) * 80);
    
    $puntos_path[] = "$x,$y";
    $puntos_svg[] = ['x' => $x, 'y' => $y, 'total' => $monto, 'fecha' => $fecha];
    $i++;
}

$path_linea = !empty($puntos_path) ? "M " . implode(" L ", $puntos_path) : "M 30,140 L 485,140";
$path_area = $path_linea . " L 485,160 L 30,160 Z";

// 4. Ventas por Categoría
$sql_cat = "SELECT COALESCE(c.nombre, 'General') AS categoria, SUM(dv.subtotal) AS total 
            FROM detalle_ventas dv
            JOIN productos p ON dv.producto_id = p.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            GROUP BY c.id";

$res_cat = $conn->query($sql_cat);
$categorias_ventas = [];
$total_cat_suma = 0;

if ($res_cat && $res_cat->num_rows > 0) {
    while ($r = $res_cat->fetch_assoc()) {
        $categorias_ventas[] = $r;
        $total_cat_suma += $r['total'];
    }
}

$colores_cat = ['#537D4D', '#E6B055', '#5B82A6', '#9B70A6', '#D0C3B7'];
$conic_segments = [];
$acumulado_porcentaje = 0;

foreach ($categorias_ventas as $idx => &$cat) {
    $porcentaje = ($total_cat_suma > 0) ? ($cat['total'] / $total_cat_suma) * 100 : 0;
    $cat['porcentaje'] = round($porcentaje, 1);
    $cat['color'] = $colores_cat[$idx % count($colores_cat)];
    
    $inicio = $acumulado_porcentaje;
    $acumulado_porcentaje += $porcentaje;
    $conic_segments[] = "{$cat['color']} {$inicio}% {$acumulado_porcentaje}%";
}

$gradient_style = !empty($conic_segments) 
    ? "conic-gradient(" . implode(', ', $conic_segments) . ")" 
    : "conic-gradient(#EFE6DD 0% 100%)";

// 5. Productos Más Vendidos
$sql_productos = "SELECT p.id, p.nombre AS producto, COALESCE(c.nombre, 'General') AS categoria, SUM(dv.cantidad) AS cantidad, SUM(dv.subtotal) AS total 
                  FROM detalle_ventas dv
                  JOIN productos p ON dv.producto_id = p.id
                  LEFT JOIN categorias c ON p.categoria_id = c.id
                  GROUP BY p.id ORDER BY cantidad DESC LIMIT 5";
$res_productos = $conn->query($sql_productos);

$productos_top = [];
if ($res_productos && $res_productos->num_rows > 0) {
    while ($row = $res_productos->fetch_assoc()) {
        $row['total'] = "$" . number_format($row['total'], 0, ',', '.');
        $productos_top[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sason - Reportes</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/corazon.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/boton.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="brand">
                <i class="fa-solid fa-utensils logo-icon"></i>
                <h2>Restaurant <span>Sason</span></h2>
            </div>
            <nav class="menu">
                <a href="gestion_sistemas.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="ventas.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i> Ventas</a>
                <a href="menu.php" class="menu-item"><i class="fa-solid fa-utensils"></i> Menú</a>
                <a href="gastos.php" class="menu-item"><i class="fa-solid fa-wallet"></i> Gastos</a>
                <a href="reportes.php" class="menu-item active"><i class="fa-solid fa-chart-simple"></i> Reportes</a>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users"></i> Usuarios</a>
                <a href="configuracion.php" class="menu-item"><i class="fa-solid fa-gear"></i> Configuración</a>
            </nav>
            <div class="sidebar-footer">
                <a href="index.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="welcome-text">
                    <h1>Reportes</h1>
                    <p>Análisis de ventas, gastos y rendimiento</p>
                </div>
                <div class="header-actions">
                    <div class="date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        <span><?php echo htmlspecialchars($fecha_actual_formatted, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <a href="configuracion.php" class="user-avatar">
                        <i class="fa-solid fa-user"></i>
                    </a>
                </div>
            </header>

            <form method="GET" action="reportes.php" class="filter-bar">
                <div style="display: flex; gap: 20px; align-items: center; width: 100%;">
                    <div class="filter-group">
                        <label for="rango">Rango de fechas</label>
                        <input type="text" id="rango" name="rango" class="input-box" value="<?php echo htmlspecialchars($rango_fechas_formatted, ENT_QUOTES, 'UTF-8'); ?>" onfocus="if(this.value==='Histórico General (Todas las fechas)') this.value='';" onblur="if(this.value==='') this.value='Histórico General (Todas las fechas)';">
                    </div>
                    <div class="filter-group">
                        <label for="tipo">Tipo de reporte</label>
                        <select id="tipo" class="select-box" name="tipo">
                            <option value="resumen" <?php echo ($tipo_reporte === 'resumen') ? 'selected' : ''; ?>>Resumen General</option>
                            <option value="ventas" <?php echo ($tipo_reporte === 'ventas') ? 'selected' : ''; ?>>Ventas Detalladas</option>
                            <option value="gastos" <?php echo ($tipo_reporte === 'gastos') ? 'selected' : ''; ?>>Gastos por Categoria</option>
                        </select>
                    </div>
                    
                    <!-- BOTÓN INTERACTIVO QUE ABRE EL MODAL -->
                    <button type="button" class="btn-report" id="btnAbrirExportar" style="margin-left: auto;">
                        <i class="fa-solid fa-download"></i> Generar Reporte
                    </button>
                </div>
            </form>

            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="kpi-data">
                        <h3>Total Ventas</h3>
                        <p class="amount"><?php echo $total_ventas; ?></p>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon red"><i class="fa-solid fa-wallet"></i></div>
                    <div class="kpi-data">
                        <h3>Total Gastos</h3>
                        <p class="amount"><?php echo $total_gastos; ?></p>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon yellow"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="kpi-data">
                        <h3>Ganancia Neta</h3>
                        <p class="amount"><?php echo $ganancia_neta; ?></p>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon blue"><i class="fa-solid fa-pie-chart"></i></div>
                    <div class="kpi-data">
                        <h3>Transacciones</h3>
                        <p class="amount"><?php echo $transacciones; ?></p>
                    </div>
                </div>
            </section>

            <section class="charts-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>Ventas por día</h3>
                    </div>
                    <div class="chart-svg-container">
                        <svg viewBox="0 0 500 180" style="width:100%; height:100%;">
                            <line x1="0" y1="30" x2="500" y2="30" stroke="#EFE6DD" stroke-dasharray="4"/>
                            <line x1="0" y1="80" x2="500" y2="80" stroke="#EFE6DD" stroke-dasharray="4"/>
                            <line x1="0" y1="130" x2="500" y2="130" stroke="#EFE6DD" stroke-dasharray="4"/>
                            
                            <defs>
                                <linearGradient id="grad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#2D7A46" stop-opacity="0.25"/>
                                    <stop offset="100%" stop-color="#2D7A46" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="<?php echo $path_area; ?>" fill="url(#grad)" />
                            <path d="<?php echo $path_linea; ?>" fill="none" stroke="#2D7A46" stroke-width="2.5"/>

                            <?php foreach ($puntos_svg as $pt): ?>
                                <circle cx="<?php echo $pt['x']; ?>" cy="<?php echo $pt['y']; ?>" r="4" fill="#FFF" stroke="#2D7A46" stroke-width="2">
                                    <title><?php echo $pt['fecha'] . ': $' . number_format($pt['total'], 0, ',', '.'); ?></title>
                                </circle>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Ventas por categoría</h3>
                    </div>
                    <div class="donut-container">
                        <div class="donut-chart" style="background: <?php echo $gradient_style; ?>;">
                            <div class="donut-center"></div>
                        </div>
                        <div class="legend-list">
                            <?php if(!empty($categorias_ventas)): ?>
                                <?php foreach ($categorias_ventas as $c): ?>
                                    <div class="legend-item">
                                        <div class="legend-color-box">
                                            <span class="dot" style="background: <?php echo $c['color']; ?>;"></span>
                                            <span><?php echo htmlspecialchars($c['categoria'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <strong><?php echo $c['porcentaje']; ?>%</strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="font-size: 11px; color: var(--text-muted);">Sin ventas registradas</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="charts-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>Productos más vendidos</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th>Total Ventas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($productos_top)): ?>
                                    <?php foreach ($productos_top as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($item['categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($item['cantidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['total'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">No hay productos registrados en ventas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="menu.php" class="btn-view-more">
                        Ver todos los productos <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Resumen General</h3>
                    </div>
                    <div class="payment-summary-box" style="margin-top: 30px;">
                        <span>Total Ventas Recibidas</span>
                        <h4><?php echo $total_ventas; ?></h4>
                    </div>
                </div>
            </section>

            <div class="available-reports-header">
                <h3>Reportes disponibles</h3>
            </div>

            <section class="reports-grid">
                <a href="ventas.php" class="report-card">
                    <div class="report-icon" style="background-color: #2D7A46;">
                        <i class="fa-solid fa-chart-column"></i>
                    </div>
                    <div class="report-info">
                        <h4>Reporte de Ventas</h4>
                        <p>Detalle completo de ventas</p>
                    </div>
                    <i class="fa-solid fa-chevron-right report-arrow"></i>
                </a>

                <a href="gastos.php" class="report-card">
                    <div class="report-icon" style="background-color: #C0392B;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="report-info">
                        <h4>Reporte de Gastos</h4>
                        <p>Análisis de gastos por categoría</p>
                    </div>
                    <i class="fa-solid fa-chevron-right report-arrow"></i>
                </a>

                <a href="menu.php" class="report-card">
                    <div class="report-icon" style="background-color: #B78103;">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                    <div class="report-info">
                        <h4>Reporte de Productos</h4>
                        <p>Rendimiento de productos</p>
                    </div>
                    <i class="fa-solid fa-chevron-right report-arrow"></i>
                </a>

                <a href="usuarios.php" class="report-card">
                    <div class="report-icon" style="background-color: #2980B9;">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <div class="report-info">
                        <h4>Reporte de Clientes</h4>
                        <p>Análisis de clientes frecuentes</p>
                    </div>
                    <i class="fa-solid fa-chevron-right report-arrow"></i>
                </a>
            </section>
        </main>
    </div>

    <!-- MODAL CREATIVO DE EXPORTACIÓN -->
    <div id="modalExportar" class="modal-export">
        <div class="modal-export-content">
            <div class="modal-export-header">
                <h3><i class="fa-solid fa-file-export"></i> Opciones de Reporte</h3>
                <span class="close-modal" id="btnCerrarExportar">&times;</span>
            </div>
            <p class="modal-subtitle">Selecciona cómo deseas generar la información de **Restaurant Sason**</p>
            
            <div class="export-options-grid">
                <button type="button" class="export-card pdf-card" onclick="window.print(); document.getElementById('modalExportar').style.display='none';">
                    <div class="export-icon"><i class="fa-solid fa-file-pdf"></i></div>
                    <h4>Imprimir / PDF</h4>
                    <p>Genera un documento visual limpio con gráficas y métricas.</p>
                </button>

                <a href="reportes.php?exportar=excel" class="export-card excel-card" onclick="document.getElementById('modalExportar').style.display='none';">
                    <div class="export-icon"><i class="fa-solid fa-file-excel"></i></div>
                    <h4>Hoja de Excel (CSV)</h4>
                    <p>Descarga la tabla de datos y métricas en formato ejecutable.</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById("modalExportar");
        const btnAbrir = document.getElementById("btnAbrirExportar");
        const btnCerrar = document.getElementById("btnCerrarExportar");

        btnAbrir.onclick = function() {
            modal.style.display = "flex";
        }

        btnCerrar.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>