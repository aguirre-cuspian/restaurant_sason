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

// Datos de sesión
$usuario      = $_SESSION['usuario'] ?? "Fatima";
$rol          = $_SESSION['rol'] ?? "Administrador";
$usuario_id   = $_SESSION['id'] ?? 1;
$fecha_actual = date("d/m/Y");

// 3. Conexión DB mediante PDO
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

// 4. Guardar / Editar Gasto (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'guardar_gasto') {
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? 'Otros');
        $proveedor   = trim($_POST['proveedor'] ?? 'N/A');
        $total       = floatval($_POST['total'] ?? 0);

        if (!empty($descripcion) && $total > 0) {
            $stmt = $pdo->prepare("INSERT INTO gastos (descripcion, categoria, proveedor, total, fecha, usuario_id) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$descripcion, $categoria, $proveedor, $total, $usuario_id]);
            header("Location: gastos.php");
            exit;
        }
    } elseif ($_POST['action'] === 'editar_gasto') {
        $id_editar   = intval($_POST['gasto_id'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? 'Otros');
        $proveedor   = trim($_POST['proveedor'] ?? 'N/A');
        $total       = floatval($_POST['total'] ?? 0);

        if ($id_editar > 0 && !empty($descripcion) && $total > 0) {
            $stmt = $pdo->prepare("UPDATE gastos SET descripcion = ?, categoria = ?, proveedor = ?, total = ? WHERE id = ?");
            $stmt->execute([$descripcion, $categoria, $proveedor, $total, $id_editar]);
            header("Location: gastos.php");
            exit;
        }
    }
}

// 5. Eliminar Gasto (GET)
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM gastos WHERE id = ?");
    $stmt->execute([$id_eliminar]);
    header("Location: gastos.php");
    exit;
}

// 6. Obtener Parámetros de Filtro
$f_inicio    = $_GET['f_inicio'] ?? date('Y-m-d');
$f_fin       = $_GET['f_fin'] ?? date('Y-m-d');
$f_categoria = $_GET['categoria'] ?? 'Todos';
$f_buscar    = trim($_GET['buscar'] ?? '');

// Métricas de la cabecera (Gastos de hoy)
$stmt_metricas = $pdo->query("
    SELECT 
        COALESCE(SUM(total), 0) AS total_gastos,
        COALESCE(SUM(CASE WHEN categoria = 'Compras' THEN total ELSE 0 END), 0) AS compras,
        COALESCE(SUM(CASE WHEN categoria = 'Servicios' THEN total ELSE 0 END), 0) AS servicios,
        COALESCE(SUM(CASE WHEN categoria NOT IN ('Compras', 'Servicios') THEN total ELSE 0 END), 0) AS otros,
        COUNT(id) AS cant_total,
        SUM(CASE WHEN categoria = 'Compras' THEN 1 ELSE 0 END) AS cant_compras,
        SUM(CASE WHEN categoria = 'Servicios' THEN 1 ELSE 0 END) AS cant_servicios,
        SUM(CASE WHEN categoria NOT IN ('Compras', 'Servicios') THEN 1 ELSE 0 END) AS cant_otros
    FROM gastos
    WHERE DATE(fecha) = CURDATE()
");
$m = $stmt_metricas->fetch();

$total_gastos = $m['total_gastos'];
$compras      = $m['compras'];
$servicios    = $m['servicios'];
$otros        = $m['otros'];

$p_compras   = $total_gastos > 0 ? round(($compras / $total_gastos) * 100, 1) : 0;
$p_servicios = $total_gastos > 0 ? round(($servicios / $total_gastos) * 100, 1) : 0;
$p_otros     = $total_gastos > 0 ? round(($otros / $total_gastos) * 100, 1) : 0;

$deg_compras   = $p_compras * 3.6;
$deg_servicios = $deg_compras + ($p_servicios * 3.6);

// 7. Consulta SQL con Filtro Exacto de Fecha y Búsqueda Directa
$sql = "SELECT * FROM gastos WHERE DATE(fecha) BETWEEN :f_inicio AND :f_fin";
$params = [':f_inicio' => $f_inicio, ':f_fin' => $f_fin];

if ($f_categoria !== 'Todos') {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $f_categoria;
}

if (!empty($f_buscar)) {
    $sql .= " AND (LOWER(descripcion) LIKE LOWER(:buscar) OR LOWER(categoria) LIKE LOWER(:buscar) OR LOWER(proveedor) LIKE LOWER(:buscar))";
    $params[':buscar'] = "%$f_buscar%";
}

$sql .= " ORDER BY fecha DESC";
$stmt_lista = $pdo->prepare($sql);
$stmt_lista->execute($params);
$lista_gastos = $stmt_lista->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sason - Gastos</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/corazon.css?v=<?php echo time(); ?>">
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
                <a href="gestion_sistemas.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="ventas.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i> Ventas</a>
                <a href="menu.php" class="menu-item"><i class="fa-solid fa-utensils"></i> Menú</a>
                <a href="gastos.php" class="menu-item active"><i class="fa-solid fa-wallet"></i> Gastos</a>
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
                    <!-- Avatar con Menú Desplegable (Igual a Inicio) -->
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

            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-wallet"></i>
                    <div>
                        <h2>Gastos</h2>
                        <p>Registra y gestiona los gastos del restaurante.</p>
                    </div>
                </div>
                <button class="btn-primary" id="btnAbrirModal">
                    <i class="fa-solid fa-plus"></i> Nuevo Gasto
                </button>
            </div>

            <!-- Métricas generales -->
            <section class="metrics-grid">
                <div class="card metric-card">
                    <div class="icon-box red"><i class="fa-solid fa-cart-shopping"></i></div>
                    <div class="metric-info">
                        <span class="label">Total Gastos Hoy</span>
                        <div class="value red">$<?php echo number_format($total_gastos, 0, ',', '.'); ?></div>
                        <span class="indicator"><?php echo $m['cant_total']; ?> gastos</span>
                    </div>
                </div>

                <div class="card metric-card">
                    <div class="icon-box blue"><i class="fa-solid fa-basket-shopping"></i></div>
                    <div class="metric-info">
                        <span class="label">Compras</span>
                        <div class="value blue">$<?php echo number_format($compras, 0, ',', '.'); ?></div>
                        <span class="indicator"><?php echo $m['cant_compras']; ?> gastos</span>
                    </div>
                </div>

                <div class="card metric-card">
                    <div class="icon-box green"><i class="fa-solid fa-bell-concierge"></i></div>
                    <div class="metric-info">
                        <span class="label">Servicios</span>
                        <div class="value green">$<?php echo number_format($servicios, 0, ',', '.'); ?></div>
                        <span class="indicator"><?php echo $m['cant_servicios']; ?> gasto</span>
                    </div>
                </div>

                <div class="card metric-card">
                    <div class="icon-box yellow"><i class="fa-solid fa-ellipsis"></i></div>
                    <div class="metric-info">
                        <span class="label">Otros Gastos</span>
                        <div class="value dark">$<?php echo number_format($otros, 0, ',', '.'); ?></div>
                        <span class="indicator"><?php echo $m['cant_otros']; ?> gasto</span>
                    </div>
                </div>
            </section>

            <!-- Formulario de Filtros de Gastos -->
            <form method="GET" action="gastos.php" class="card filters-card" id="filterForm">
                <div class="filter-group">
                    <label>Fecha inicio</label>
                    <input type="date" name="f_inicio" class="filter-control" value="<?php echo $f_inicio; ?>" onchange="document.getElementById('filterForm').submit()">
                </div>
                <div class="filter-group">
                    <label>Fecha fin</label>
                    <input type="date" name="f_fin" class="filter-control" value="<?php echo $f_fin; ?>" onchange="document.getElementById('filterForm').submit()">
                </div>
                <div class="filter-group">
                    <label>Categoría</label>
                    <select name="categoria" class="filter-control" onchange="document.getElementById('filterForm').submit()">
                        <option value="Todos" <?php echo $f_categoria === 'Todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="Compras" <?php echo $f_categoria === 'Compras' ? 'selected' : ''; ?>>Compras</option>
                        <option value="Servicios" <?php echo $f_categoria === 'Servicios' ? 'selected' : ''; ?>>Servicios</option>
                        <option value="Otros" <?php echo $f_categoria === 'Otros' ? 'selected' : ''; ?>>Otros</option>
                    </select>
                </div>
                <div class="filter-group search-group" style="grid-column: span 2;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="buscar" class="filter-control" placeholder="Buscar gasto..." value="<?php echo htmlspecialchars($f_buscar); ?>">
                </div>
            </form>

            <div class="content-grid">
                <section class="card table-card">
                    <div class="table-header">
                        <i class="fa-solid fa-list"></i>
                        <span>Lista de gastos</span>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha / Hora</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Proveedor</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_gastos)): ?>
                                    <?php foreach ($lista_gastos as $gasto): ?>
                                    <tr>
                                        <td><strong><?php echo str_pad($gasto['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($gasto['fecha'])); ?></td>
                                        <td>
                                            <span class="badge-cat <?php echo htmlspecialchars($gasto['categoria']); ?>">
                                                <?php echo htmlspecialchars($gasto['categoria']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($gasto['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($gasto['proveedor']); ?></td>
                                        <td><strong>$<?php echo number_format($gasto['total'], 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" 
                                                        class="btn-icon edit btnEditarGasto" 
                                                        data-id="<?php echo $gasto['id']; ?>"
                                                        data-descripcion="<?php echo htmlspecialchars($gasto['descripcion']); ?>"
                                                        data-categoria="<?php echo htmlspecialchars($gasto['categoria']); ?>"
                                                        data-proveedor="<?php echo htmlspecialchars($gasto['proveedor']); ?>"
                                                        data-total="<?php echo $gasto['total']; ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <a href="gastos.php?eliminar=<?php echo $gasto['id']; ?>" onclick="return confirm('¿Eliminar este gasto?');" class="btn-icon delete"><i class="fa-solid fa-trash-can"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #888; padding: 20px;">No hay gastos registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer-banner">
                        <span>Mostrando <?php echo count($lista_gastos); ?> gastos</span>
                        <div>
                            <span>Total Filtrado: </span>
                            <strong style="font-size: 18px;">$<?php echo number_format(array_sum(array_column($lista_gastos, 'total')), 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                </section>

                <div class="right-panel">
                    <section class="card summary-card">
                        <h3 class="summary-title">Resumen del día</h3>
                        <div class="chart-container">
                            <div class="donut-chart" style="background: conic-gradient(
                                var(--compras-color) 0deg <?php echo $deg_compras; ?>deg, 
                                var(--servicios-color) <?php echo $deg_compras; ?>deg <?php echo $deg_servicios; ?>deg, 
                                var(--otros-color) <?php echo $deg_servicios; ?>deg 360deg
                            );"></div>
                            <div class="chart-legend">
                                <div class="legend-item">
                                    <span class="dot compras"></span>
                                    <div class="legend-info">
                                        <strong>Compras</strong>
                                        <span>$<?php echo number_format($compras, 0, ',', '.'); ?> (<?php echo $p_compras; ?>%)</span>
                                    </div>
                                </div>
                                <div class="legend-item">
                                    <span class="dot servicios"></span>
                                    <div class="legend-info">
                                        <strong>Servicios</strong>
                                        <span>$<?php echo number_format($servicios, 0, ',', '.'); ?> (<?php echo $p_servicios; ?>%)</span>
                                    </div>
                                </div>
                                <div class="legend-item">
                                    <span class="dot otros"></span>
                                    <div class="legend-info">
                                        <strong>Otros</strong>
                                        <span>$<?php echo number_format($otros, 0, ',', '.'); ?> (<?php echo $p_otros; ?>%)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Nuevo Gasto -->
    <div class="modal-overlay" id="modalGasto">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registrar Nuevo Gasto</h3>
                <button class="close-modal" id="btnCerrarModal">&times;</button>
            </div>
            <form method="POST" action="gastos.php">
                <input type="hidden" name="action" value="guardar_gasto">
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" placeholder="Ej: Compra de verduras" required>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria" required>
                        <option value="Compras">Compras</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Proveedor</label>
                    <input type="text" name="proveedor" placeholder="Ej: Distribuidora Central">
                </div>
                <div class="form-group">
                    <label>Total ($)</label>
                    <input type="number" step="0.01" name="total" placeholder="0.00" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="btnCancelarModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar Gasto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Gasto -->
    <div class="modal-overlay" id="modalEditarGasto">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Gasto</h3>
                <button class="close-modal" id="btnCerrarEditarModal">&times;</button>
            </div>
            <form method="POST" action="gastos.php">
                <input type="hidden" name="action" value="editar_gasto">
                <input type="hidden" name="gasto_id" id="edit_gasto_id">
                
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" id="edit_descripcion" required>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria" id="edit_categoria" required>
                        <option value="Compras">Compras</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Proveedor</label>
                    <input type="text" name="proveedor" id="edit_proveedor">
                </div>
                <div class="form-group">
                    <label>Total ($)</label>
                    <input type="number" step="0.01" name="total" id="edit_total" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="btnCancelarEditarModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Actualizar Gasto</button>
                </div>
            </form>
        </div>
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

        // Modales
        const modal = document.getElementById('modalGasto');
        const btnAbrir = document.getElementById('btnAbrirModal');
        const btnCerrar = document.getElementById('btnCerrarModal');
        const btnCancelar = document.getElementById('btnCancelarModal');

        btnAbrir.addEventListener('click', () => modal.style.display = 'flex');
        btnCerrar.addEventListener('click', () => modal.style.display = 'none');
        btnCancelar.addEventListener('click', () => modal.style.display = 'none');

        const modalEditar = document.getElementById('modalEditarGasto');
        const btnCerrarEditar = document.getElementById('btnCerrarEditarModal');
        const btnCancelarEditar = document.getElementById('btnCancelarEditarModal');
        const botonesEditar = document.querySelectorAll('.btnEditarGasto');

        botonesEditar.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_gasto_id').value = this.dataset.id;
                document.getElementById('edit_descripcion').value = this.dataset.descripcion;
                document.getElementById('edit_categoria').value = this.dataset.categoria;
                document.getElementById('edit_proveedor').value = this.dataset.proveedor;
                document.getElementById('edit_total').value = this.dataset.total;

                modalEditar.style.display = 'flex';
            });
        });

        btnCerrarEditar.addEventListener('click', () => modalEditar.style.display = 'none');
        btnCancelarEditar.addEventListener('click', () => modalEditar.style.display = 'none');

        window.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
            if (e.target === modalEditar) modalEditar.style.display = 'none';
        });
    </script>
</body>
</html>