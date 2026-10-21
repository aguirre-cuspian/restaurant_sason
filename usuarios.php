<?php
// 1. CONEXIÓN A LA BASE DE DATOS
$host = "127.0.0.1";
$db   = "restaurant_sason";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$fecha_actual = date("d/m/Y");
$mensaje_alerta = "";

// 2. ACCIONES POST (CRUD + RESTABLECER CONTRASEÑA)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ELIMINAR USUARIO
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id_delete = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id_delete]);
        header("Location: usuarios.php?msg=deleted");
        exit;
    }

    // GUARDAR O EDITAR USUARIO
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre = trim($_POST['nombre']);
        $username = trim($_POST['usuario']);
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'Administrador';
        $estado = $_POST['estado'] ?? 'Activo';

        if ($id > 0) {
            // Actualizar usuario existente
            if (!empty($_POST['clave'])) {
                $clave_hash = password_hash($_POST['clave'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, username = ?, email = ?, rol = ?, estado = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$nombre, $username, $email, $rol, $estado, $clave_hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, username = ?, email = ?, rol = ?, estado = ? WHERE id = ?");
                $stmt->execute([$nombre, $username, $email, $rol, $estado, $id]);
            }
        } else {
            // Crear nuevo usuario
            $clave_hash = password_hash(!empty($_POST['clave']) ? $_POST['clave'] : '123456', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, username, email, password_hash, rol, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nombre, $username, $email, $clave_hash, $rol, $estado]);
        }
        header("Location: usuarios.php?msg=saved");
        exit;
    }

    // RESTABLECER CONTRASEÑA RÁPIDA
    if (isset($_POST['action']) && $_POST['action'] === 'reset_pass') {
        $user_id = (int)$_POST['user_id'];
        $nueva_clave = $_POST['nueva_clave'];
        if ($user_id > 0 && !empty($nueva_clave)) {
            $clave_hash = password_hash($nueva_clave, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$clave_hash, $user_id]);
            header("Location: usuarios.php?msg=pass_reset");
            exit;
        }
    }
}

// Mensajes de confirmación
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $mensaje_alerta = "Usuario guardado exitosamente.";
    if ($_GET['msg'] === 'deleted') $mensaje_alerta = "Usuario eliminado correctamente.";
    if ($_GET['msg'] === 'pass_reset') $mensaje_alerta = "Contraseña restablecida con éxito.";
}

// 3. CONSULTA Y FILTROS
$search = $_GET['search'] ?? '';
$estado_filter = $_GET['estado'] ?? 'Todos';

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(nombre LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($estado_filter !== 'Todos') {
    $whereClauses[] = "estado = ?";
    $params[] = $estado_filter;
}

$whereSQL = count($whereClauses) > 0 ? " WHERE " . implode(" AND ", $whereClauses) : "";

$stmt = $pdo->prepare("SELECT * FROM usuarios $whereSQL ORDER BY id ASC");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// METRICAS / KPIS
$usuarios_activos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'Activo' OR estado IS NULL")->fetchColumn();
$usuarios_inactivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'Inactivo'")->fetchColumn();
$total_usuarios = count($usuarios);
$ultimo_registro_raw = $pdo->query("SELECT MAX(fecha_creacion) FROM usuarios")->fetchColumn();
$ultimo_acceso = $ultimo_registro_raw ? date("h:i A", strtotime($ultimo_registro_raw)) : date("h:i A");

function getIniciales($nombre) {
    $words = explode(" ", trim($nombre));
    $in = "";
    foreach ($words as $w) {
        if (!empty($w)) $in .= strtoupper($w[0]);
    }
    return substr($in, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sason - Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/configuracion.min.css">
    <link rel="stylesheet" href="css/login.css">

    <style>


        :root {
            --bg-main: #F8F4F0;
            --sidebar-bg: #22150C;
            --sidebar-active: #482B17;
            --card-bg: #FFFFFF;
            --text-dark: #22150C;
            --text-muted: #8C7B70;
            --border-color: #EFE6DD;
            --accent-btn: #3D220F;

            --bg-admin: #F0E6F7; --text-admin: #6C3483;
            --bg-status-active: #E8F8F5; --text-status-active: #117864;
            --bg-status-inactive: #FDEDEC; --text-status-inactive: #C0392B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-dark); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .dashboard-container { display: flex; width: 100%; max-width: 1440px; background: #FDFBF9; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        
        /* SIDEBAR */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: #FFF; display: flex; flex-direction: column; padding: 30px 18px; flex-shrink: 0; }
        .brand { text-align: center; margin-bottom: 35px; }
        .brand .logo-icon { font-size: 34px; color: #D4A373; margin-bottom: 5px; }
        .brand h2 { font-size: 18px; font-weight: 700; line-height: 1.1; }
        .brand h2 span { display: block; font-weight: 300; font-style: italic; font-size: 26px; }
        .menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .menu-item { display: flex; align-items: center; gap: 14px; padding: 12px 18px; color: #D2C4B7; text-decoration: none; font-size: 15px; font-weight: 500; border-radius: 10px; transition: all 0.3s ease; }
        .menu-item:hover, .menu-item.active { background-color: var(--sidebar-active); color: #FFF; }
        .sidebar-footer { border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; }
        .logout-btn { display: flex; align-items: center; gap: 12px; color: #D2C4B7; text-decoration: none; padding: 10px 18px; font-size: 15px; }
        
        /* CONTENIDO PRINCIPAL */
        .main-content { flex: 1; padding: 30px 35px; display: flex; flex-direction: column; gap: 20px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; }
        .welcome-text h1 { font-size: 26px; font-weight: 700; color: var(--text-dark); }
        .welcome-text p { color: var(--text-muted); font-size: 14px; }
        .header-actions { display: flex; align-items: center; gap: 15px; }
        .date-badge { background: #FFF; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 10px; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; }
        .btn-add-user { background-color: var(--accent-btn); color: #FFF; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }

        .alert-box { background: #E8F8F5; border: 1px solid #117864; color: #117864; padding: 10px 15px; border-radius: 8px; font-size: 13px; font-weight: 500; }

        /* KPIS */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .kpi-card { background: var(--card-bg); border-radius: 14px; padding: 18px 20px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; }
        .kpi-data h3 { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .kpi-data .amount { font-size: 24px; font-weight: 700; margin: 2px 0; color: var(--text-dark); }
        .kpi-data .comparison { font-size: 11px; font-weight: 500; color: var(--text-muted); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .kpi-icon.green { background: #E8F8F5; color: #117864; }
        .kpi-icon.red { background: #FDEDEC; color: #C0392B; }
        .kpi-icon.yellow { background: #FEF9E7; color: #D4AC0D; }
        .kpi-icon.blue { background: #EBF5FB; color: #2E86C1; }

        /* FILTROS */
        .filter-card { background: #FFF; border-radius: 12px; border: 1px solid var(--border-color); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
        .search-box { display: flex; align-items: center; gap: 10px; background: #FAF8F5; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 14px; flex: 1; max-width: 380px; }
        .search-box input { border: none; background: transparent; outline: none; font-size: 13px; width: 100%; color: var(--text-dark); }
        .filter-controls { display: flex; align-items: center; gap: 15px; }
        .select-group { display: flex; align-items: center; gap: 8px; }
        .select-group label { font-size: 11px; color: var(--text-muted); font-weight: 600; }
        .select-box { border: 1px solid var(--border-color); background: #FAF8F5; padding: 7px 12px; border-radius: 8px; font-size: 13px; color: var(--text-dark); outline: none; }
        .btn-filter { background-color: var(--accent-btn); color: #FFF; border: none; padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .btn-clean { background: #FFF; border: 1px solid var(--border-color); color: var(--text-dark); padding: 8px 14px; border-radius: 8px; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; }

        /* TABLA */
        .table-card { background: var(--card-bg); border-radius: 14px; border: 1px solid var(--border-color); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        th { background-color: #FAF2EA; padding: 12px 15px; font-size: 12px; color: var(--text-dark); font-weight: 600; text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .avatar-circle { width: 32px; height: 32px; border-radius: 50%; background: #D2C4B7; color: #22150C; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; }
        
        .badge-role { padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; background-color: var(--bg-admin); color: var(--text-admin); }

        .badge-status { font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; }
        .status-active { background-color: var(--bg-status-active); color: var(--text-status-active); }
        .status-inactive { background-color: var(--bg-status-inactive); color: var(--text-status-inactive); }
        .dot-status { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .status-active .dot-status { background-color: var(--text-status-active); }
        .status-inactive .dot-status { background-color: var(--text-status-inactive); }

        .actions-cell { display: flex; gap: 6px; }
        .btn-action { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border-color); background: #FFF; color: var(--text-dark); display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; }
        .btn-action.delete { color: #C0392B; }

        /* PAGINACIÓN */
        .table-footer { padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); background: #FFF; border-top: 1px solid var(--border-color); }

        /* TARJETAS INFERIORES DE ACCIONES RÁPIDAS */
        .bottom-actions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 5px; }
        .action-card { background: #FFF; border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 12px; color: var(--text-dark); transition: transform 0.2s; cursor: pointer; }
        .action-card:hover { transform: translateY(-2px); }
        .action-card-icon { width: 40px; height: 40px; border-radius: 10px; background: #FAF2EA; color: var(--accent-btn); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .action-card-info h4 { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .action-card-info p { font-size: 11px; color: var(--text-muted); line-height: 1.2; }
        .action-card-arrow { margin-left: auto; color: var(--text-muted); font-size: 12px; }

        /* MODALES */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: #FFF; padding: 25px; border-radius: 12px; width: 100%; max-width: 450px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 18px; color: var(--text-dark); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 4px; font-weight: 600; color: var(--text-muted); }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; outline: none; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
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
                <a href="reportes.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> Reportes</a>
                <a href="usuarios.php" class="menu-item active"><i class="fa-solid fa-user-group"></i> Usuarios</a>
                <a href="configuracion.php" class="menu-item"><i class="fa-solid fa-gear"></i> Configuración</a>
            </nav>
            <div class="sidebar-footer">
                <a href="index.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="welcome-text">
                    <h1>Usuarios</h1>
                    <p>Gestiona los administradores con acceso al sistema</p>
                </div>
                <div class="header-actions">
                    <div class="date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        <span><?php echo $fecha_actual; ?></span>
                    </div>
                    <button class="btn-add-user" onclick="openModal('userModal')">
                        <i class="fa-solid fa-plus"></i> Nuevo Usuario
                    </button>
                </div>
            </header>

            <?php if (!empty($mensaje_alerta)): ?>
                <div class="alert-box">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje_alerta; ?>
                </div>
            <?php endif; ?>

            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-data">
                        <h3>Usuarios Registrados</h3>
                        <p class="amount"><?php echo $total_usuarios; ?></p>
                        <span class="comparison">Total en la base de datos</span>
                    </div>
                    <div class="kpi-icon yellow"><i class="fa-solid fa-users"></i></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-data">
                        <h3>Usuarios Activos</h3>
                        <p class="amount"><?php echo $usuarios_activos; ?></p>
                        <span class="comparison" style="color:#117864;">Con acceso habilitado</span>
                    </div>
                    <div class="kpi-icon green"><i class="fa-solid fa-user-check"></i></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-data">
                        <h3>Usuarios Inactivos</h3>
                        <p class="amount"><?php echo $usuarios_inactivos; ?></p>
                        <span class="comparison" style="color:#C0392B;">Acceso deshabilitado</span>
                    </div>
                    <div class="kpi-icon red"><i class="fa-solid fa-user-xmark"></i></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-data">
                        <h3>Última Creación</h3>
                        <p class="amount" style="font-size: 18px;"><?php echo $ultimo_acceso; ?></p>
                        <span class="comparison">Registro más reciente</span>
                    </div>
                    <div class="kpi-icon blue"><i class="fa-solid fa-clock"></i></div>
                </div>
            </section>

            <form method="GET" action="usuarios.php" class="filter-card">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass" style="color: #A09085;"></i>
                    <input type="text" name="search" placeholder="Buscar por usuario, nombre o email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-controls">
                    <div class="select-group">
                        <label>ESTADO</label>
                        <select name="estado" class="select-box">
                            <option value="Todos" <?php if($estado_filter === 'Todos') echo 'selected'; ?>>Todos</option>
                            <option value="Activo" <?php if($estado_filter === 'Activo') echo 'selected'; ?>>Activo</option>
                            <option value="Inactivo" <?php if($estado_filter === 'Inactivo') echo 'selected'; ?>>Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filtrar</button>
                    <a href="usuarios.php" class="btn-clean" title="Limpiar"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>

            <section class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Usuario</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) > 0): ?>
                                <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><strong><?php echo $u['id']; ?></strong></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar-circle"><?php echo getIniciales($u['nombre']); ?></div>
                                            <span><?php echo htmlspecialchars($u['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? 'Sin correo'); ?></td>
                                    <td><span class="badge-role"><?php echo htmlspecialchars($u['rol'] ?? 'Administrador'); ?></span></td>
                                    <td>
                                        <?php $est = $u['estado'] ?? 'Activo'; ?>
                                        <?php if ($est === 'Activo'): ?>
                                            <span class="badge-status status-active"><span class="dot-status"></span> Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status status-inactive"><span class="dot-status"></span> Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <button class="btn-action" title="Editar" onclick='editUser(<?php echo json_encode($u); ?>)'><i class="fa-regular fa-pen-to-square"></i></button>
                                            <form method="POST" action="usuarios.php" onsubmit="return confirm('¿Deseas eliminar este usuario?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-action delete" title="Eliminar"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">No se encontraron usuarios.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span>Mostrando <?php echo count($usuarios); ?> usuarios en total</span>
                </div>
            </section>

            <section class="bottom-actions-grid">
                <div class="action-card" onclick="openModal('userModal')">
                    <div class="action-card-icon"><i class="fa-solid fa-user-plus"></i></div>
                    <div class="action-card-info">
                        <h4>Crear Usuario</h4>
                        <p>Agrega un nuevo usuario administrador</p>
                    </div>
                    <i class="fa-solid fa-chevron-right action-card-arrow"></i>
                </div>

                <div class="action-card" onclick="openModal('modalResetPass')">
                    <div class="action-card-icon"><i class="fa-solid fa-key"></i></div>
                    <div class="action-card-info">
                        <h4>Cambiar Contraseña</h4>
                        <p>Restablece la contraseña de un usuario</p>
                    </div>
                    <i class="fa-solid fa-chevron-right action-card-arrow"></i>
                </div>

                <div class="action-card" onclick="window.print()">
                    <div class="action-card-icon"><i class="fa-solid fa-print"></i></div>
                    <div class="action-card-info">
                        <h4>Imprimir Lista</h4>
                        <p>Genera reporte en PDF / Impresión</p>
                    </div>
                    <i class="fa-solid fa-chevron-right action-card-arrow"></i>
                </div>
            </section>
        </main>
    </div>

    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Usuario</h3>
                <i class="fa-solid fa-xmark" style="cursor: pointer;" onclick="closeModal('userModal')"></i>
            </div>
            <form method="POST" action="usuarios.php">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="userId" value="0">

                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" id="userName" class="form-control" placeholder="Ej. Juan Pérez" required>
                </div>

                <div class="form-group">
                    <label>Nombre de Usuario</label>
                    <input type="text" name="usuario" id="userUsername" class="form-control" placeholder="Ej. juanp" required>
                </div>

                <div class="form-group">
                    <label>Correo Electrónico (Email)</label>
                    <input type="email" name="email" id="userEmail" class="form-control" placeholder="ejemplo@correo.com">
                </div>

                <div class="form-group">
                    <label>Contraseña <small id="passNote" style="color:#888;"></small></label>
                    <input type="password" name="clave" id="userPass" class="form-control">
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="userEstado" class="form-control" required>
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-clean" onclick="closeModal('userModal')">Cancelar</button>
                    <button type="submit" class="btn-add-user">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="modalResetPass">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Restablecer Contraseña</h3>
                <i class="fa-solid fa-xmark" style="cursor: pointer;" onclick="closeModal('modalResetPass')"></i>
            </div>
            <form method="POST" action="usuarios.php">
                <input type="hidden" name="action" value="reset_pass">

                <div class="form-group">
                    <label>Seleccionar Usuario</label>
                    <select name="user_id" class="form-control" required>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nombre'] . ' (' . $u['username'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="nueva_clave" class="form-control" placeholder="Escribe la nueva clave..." required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-clean" onclick="closeModal('modalResetPass')">Cancelar</button>
                    <button type="submit" class="btn-add-user">Actualizar Clave</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            if (modalId === 'userModal') {
                document.getElementById('userId').value = "0";
                document.getElementById('userName').value = "";
                document.getElementById('userUsername').value = "";
                document.getElementById('userEmail').value = "";
                document.getElementById('userEstado').value = "Activo";
                document.getElementById('userPass').value = "";
                document.getElementById('passNote').innerText = "(Obligatorio)";
                document.getElementById('modalTitle').innerText = "Nuevo Usuario";
            }
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function editUser(user) {
            document.getElementById('userId').value = user.id;
            document.getElementById('userName').value = user.nombre;
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userEmail').value = user.email || "";
            document.getElementById('userEstado').value = user.estado || "Activo";
            document.getElementById('userPass').value = "";
            document.getElementById('passNote').innerText = "(Dejar en blanco para conservar la actual)";
            document.getElementById('modalTitle').innerText = "Editar Usuario";
            document.getElementById('userModal').classList.add('active');
        }
    </script>
</body>
</html>