<?php
// 1. Conexión a la base de datos con PDO
$host = "localhost";
$dbname = "restaurant_sason";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 2. Procesar cambio de estado (Activo / Inactivo)
if (isset($_GET['cambiar_estado']) && isset($_GET['id'])) {
    $id_estado = $_GET['id'];
    $nuevo_estado = $_GET['cambiar_estado']; // Recibe 'Activo' o 'Inactivo'
    
    $stmt_est = $pdo->prepare("UPDATE productos SET estado = ? WHERE id = ?");
    $stmt_est->execute([$nuevo_estado, $id_estado]);
    header("Location: menu.php");
    exit();
}

// 3. Procesar eliminación de plato (Opcional, si deseas mantenerlo)
// 3. Procesar eliminación de plato y sus detalles de venta
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];

    // 1. Eliminar primero los registros del plato en detalle_ventas
    $stmt_det = $pdo->prepare("DELETE FROM detalle_ventas WHERE producto_id = ?");
    $stmt_det->execute([$id_eliminar]);

    // 2. Eliminar el plato de la tabla productos
    $stmt_del = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt_del->execute([$id_eliminar]);

    header("Location: menu.php");
    exit();
}

// 4. Obtener datos del plato si se va a editar
$plato_a_editar = null;
if (isset($_GET['editar'])) {
    $stmt_edit = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt_edit->execute([$_GET['editar']]);
    $plato_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// 5. Procesar inserción o actualización de plato (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nombre'])) {
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $categoria_id = $_POST['categoria'];
    $estado = $_POST['estado']; // Capturar el estado seleccionado en el formulario

    if (isset($_POST['id_producto']) && !empty($_POST['id_producto'])) {
        // Actualizar plato existente
        $id_prod = $_POST['id_producto'];
        $sql = "UPDATE productos SET nombre = ?, precio = ?, categoria_id = ?, estado = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $precio, $categoria_id, $estado, $id_prod]);
    } else {
        // Insertar nuevo plato (por defecto se puede guardar como 'Activo')
        $sql = "INSERT INTO productos (nombre, precio, categoria_id, estado) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $precio, $categoria_id, $estado]);
    }
    
    header("Location: menu.php");
    exit();
}

// 6. Cargar las categorías desde la base de datos
$stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY id ASC");
$lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$nombres_categorias = ["Todos"];
foreach ($lista_categorias as $cat) {
    $nombres_categorias[] = $cat['nombre'];
}

$categoria_activa = isset($_GET['cat']) ? $_GET['cat'] : "Todos";

// 7. Consulta con JOIN entre productos y categorías
$query = "SELECT p.id, p.nombre, p.precio, p.estado, c.nombre as categoria 
          FROM productos p 
          JOIN categorias c ON p.categoria_id = c.id";

if ($categoria_activa !== "Todos") {
    $query .= " WHERE c.nombre = " . $pdo->quote($categoria_activa);
}

$platos_filtrados = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
$total_platos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restaurant Sason - Menú</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #F5EFEB;
            --sidebar-bg: #2C1A0E;
            --sidebar-active: #5A391F;
            --accent-brown: #3D220F;
            --card-bg: #FFFFFF;
            --border-color: #EFE6DD;
            --text-main: #2C1A0E;
            --text-muted: #8C7A6B;
            --badge-green-bg: #E8F5E9;
            --badge-green-text: #2E7D32;
            --badge-red-bg: #FFEBEE;
            --badge-red-text: #C62828;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

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

        .dashboard-container {
            display: flex;
            width: 100%;
            max-width: 1500px;
            height: 90vh;
            background: #FAFAF7;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .sidebar {
            width: 260px;
            background-color: #2C1A0E;
            color: #FFF;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-top {
            display: flex;
            flex-direction: column;
        }

        .brand {
            text-align: center;
            margin-bottom: 35px;
            color: #FFF;
        }

        .brand i {
            font-size: 32px;
            color: #D4A373;
            margin-bottom: 8px;
            display: block;
        }

        .brand h2 {
            font-size: 20px;
            font-weight: 600;
            line-height: 1.2;
        }

        .brand h2 span {
            display: block;
            font-style: italic;
            font-weight: 300;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 18px;
            color: #D2C4B7;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 6px;
            font-size: 15px;
            transition: 0.2s;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .menu-item:hover, .menu-item.active {
            background-color: var(--sidebar-active);
            color: #FFF;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 18px;
            color: #D2C4B7;
            text-decoration: none;
            border-radius: 12px;
            font-size: 15px;
            margin-top: auto;
        }

        .main-content {
            flex: 1;
            padding: 35px 40px 35px 20px;
            overflow-y: auto;
            margin-left: 0;
        }

        h1 {
            color: var(--text-main);
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .tab-btn {
            background: #FFF;
            border: 1px solid var(--border-color);
            padding: 10px 22px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: 0.2s;
        }

        .tab-btn.active {
            background-color: var(--accent-brown);
            color: #FFF;
            border-color: var(--accent-brown);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 25px;
            align-items: start;
        }

        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 12px 15px;
            text-align: left;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 14px;
            vertical-align: middle;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: var(--badge-green-bg);
            color: var(--badge-green-text);
        }

        .status-inactive {
            background: var(--badge-red-bg);
            color: var(--badge-red-text);
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #FFF;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-action:hover {
            background: #F9F9F9;
        }

        .btn-action.toggle-status:hover {
            color: #1976D2;
            border-color: #BBDEFB;
        }

        .btn-action.delete:hover {
            color: #D32F2F;
            border-color: #FFCDD2;
        }

        .card h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            background: #FFF;
            outline: none;
            transition: 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-brown);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--text-main);
            color: #FFF;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 5px;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background-color: var(--accent-brown);
        }

        .pagination-text {
            text-align: right;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="brand">
                    <i class="fa-solid fa-utensils"></i>
                    <h2>Restaurant <span>Sason</span></h2>
                </div>
                <nav class="menu">
                    <a href="gestion_sistemas.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
                    <a href="ventas.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i> Ventas</a>
                    <a href="menu.php" class="menu-item active"><i class="fa-solid fa-utensils"></i> Menú</a>
                    <a href="gastos.php" class="menu-item"><i class="fa-solid fa-wallet"></i> Gastos</a>
                    <a href="reportes.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> Reportes</a>
                    <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users"></i> Usuarios</a>
                    <a href="configuracion.php" class="menu-item"><i class="fa-solid fa-gear"></i> Configuración</a>
                </nav>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
        </aside>

        <!-- Contenido Principal -->
        <main class="main-content">
            <h1>Menú</h1>

            <!-- Pestañas de Categorías -->
            <div class="tabs-container">
                <?php foreach ($nombres_categorias as $cat): ?>
                    <a href="?cat=<?php echo urlencode($cat); ?>" class="tab-btn <?php echo ($cat === $categoria_activa) ? 'active' : ''; ?>">
                        <?php echo $cat; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="content-grid">
                <!-- Tabla de Platos -->
                <section class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Plato</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($platos_filtrados) > 0): ?>
                                <?php foreach ($platos_filtrados as $plato): ?>
                                <tr>
                                    <td><?php echo $plato['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($plato['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($plato['categoria']); ?></td>
                                    <td>$<?php echo number_format($plato['precio'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php if (isset($plato['estado']) && $plato['estado'] === 'Inactivo'): ?>
                                            <span class="badge status-inactive">Inactivo</span>
                                        <?php else: ?>
                                            <span class="badge status-active">Activo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <!-- Botón para cambiar estado rápidamente -->
                                            <?php 
                                                $nuevo_estado_btn = (isset($plato['estado']) && $plato['estado'] === 'Inactivo') ? 'Activo' : 'Inactivo';
                                                $icono_estado = ($nuevo_estado_btn === 'Activo') ? 'fa-eye' : 'fa-eye-slash';
                                                $titulo_estado = ($nuevo_estado_btn === 'Activo') ? 'Activar plato' : 'Inativar plato';
                                            ?>
                                            <a href="menu.php?cambiar_estado=<?php echo $nuevo_estado_btn; ?>&id=<?php echo $plato['id']; ?>" class="btn-action toggle-status" title="<?php echo $titulo_estado; ?>">
                                                <i class="fa-solid <?php echo $icono_estado; ?>"></i>
                                            </a>
                                            <a href="menu.php?editar=<?php echo $plato['id']; ?>" class="btn-action" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                            <a href="menu.php?eliminar=<?php echo $plato['id']; ?>" class="btn-action delete" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este plato?');"><i class="fa-solid fa-trash-can"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">No hay platos registrados en esta categoría.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination-text">
                        Mostrando <?php echo count($platos_filtrados); ?> platos
                    </div>
                </section>

                <!-- Formulario Lateral -->
                <aside class="card">
                    <h3><?php echo $plato_a_editar ? 'Editar plato' : 'Agregar nuevo plato'; ?></h3>
                    <form method="POST" action="menu.php">
                        <?php if ($plato_a_editar): ?>
                            <input type="hidden" name="id_producto" value="<?php echo $plato_a_editar['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Nombre del plato</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej. Desayuno Especial" value="<?php echo $plato_a_editar ? htmlspecialchars($plato_a_editar['nombre']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="categoria" class="form-control" required>
                                <option value="" disabled <?php echo !$plato_a_editar ? 'selected' : ''; ?>>Selecciona una categoría</option>
                                <?php foreach ($lista_categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($plato_a_editar && $plato_a_editar['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Precio</label>
                            <input type="number" step="any" name="precio" class="form-control" placeholder="$ Ej. 12000" value="<?php echo $plato_a_editar ? $plato_a_editar['precio'] : ''; ?>" required>
                        </div>
                        
                        <!-- Selector de Estado -->
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-control" required>
                                <option value="Activo" <?php echo ($plato_a_editar && isset($plato_a_editar['estado']) && $plato_a_editar['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="Inactivo" <?php echo ($plato_a_editar && isset($plato_a_editar['estado']) && $plato_a_editar['estado'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fa-solid <?php echo $plato_a_editar ? 'fa-pen-to-square' : 'fa-plus'; ?>" style="margin-right: 8px;"></i> 
                            <?php echo $plato_a_editar ? 'Actualizar plato' : 'Guardar plato'; ?>
                        </button>

                        <?php if ($plato_a_editar): ?>
                            <a href="menu.php" style="display: block; text-align: center; margin-top: 12px; font-size: 13px; color: var(--text-muted); text-decoration: none;">Cancelar edición</a>
                        <?php endif; ?>
                    </form>
                </aside>
            </div>
        </main>
    </div>
</body>
</html>