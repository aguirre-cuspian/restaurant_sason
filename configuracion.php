<?php
session_start();

// 🔒 CONTROL DE ACCESO: Si no hay sesión activa, redirige al Login
if (!isset($_SESSION['usuario']) && !isset($_SESSION['id']) && !isset($_SESSION['usuario_id']) && !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$database = "restaurant_sason";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$mensaje = "";
$fecha_actual = date("d/m/Y");

// Detectar el ID del usuario desde la sesión o buscar por usuario activo
$id_usuario = $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['user_id'] ?? null;

if (!$id_usuario && isset($_SESSION['usuario'])) {
    $usr_nombre = $_SESSION['usuario'];
    $res_id = $conn->query("SELECT id FROM usuarios WHERE username = '$usr_nombre' LIMIT 1");
    if ($res_id && $r = $res_id->fetch_assoc()) {
        $id_usuario = $r['id'];
    }
}

// Mantiene activa la pestaña actual tras enviar el formulario
$tab_activa = $_POST['tab_activa'] ?? "tab-restaurante";

// Guardar cambios según la pestaña en la que esté el usuario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $seccion = $_POST['seccion'] ?? '';

    if ($seccion === 'restaurante') {
        $tab_activa = "tab-restaurante";
        $nombre = $_POST['nombre'] ?? '';
        $nit = $_POST['nit'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $email = $_POST['email'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';

        $stmt = $conn->prepare("UPDATE configuracion SET nombre=?, nit=?, direccion=?, telefono=?, email=?, descripcion=? WHERE id=1");
        $stmt->bind_param("ssssss", $nombre, $nit, $direccion, $telefono, $email, $descripcion);
        $stmt->execute();
        $stmt->close();
        $mensaje = "<div class='alert-success'>¡Información del restaurante guardada con éxito!</div>";
    } 
    elseif ($seccion === 'impuestos') {
        $tab_activa = "tab-impuestos";
        $impuesto = $_POST['impuesto_porcentaje'] ?? 0;
        $moneda = $_POST['moneda'] ?? '';
        $propina = $_POST['propina_sugerida'] ?? 0;

        $stmt = $conn->prepare("UPDATE configuracion SET impuesto_porcentaje=?, moneda=?, propina_sugerida=? WHERE id=1");
        $stmt->bind_param("dsd", $impuesto, $moneda, $propina);
        $stmt->execute();
        $stmt->close();
        $mensaje = "<div class='alert-success'>¡Impuestos y moneda actualizados!</div>";
    }
    elseif ($seccion === 'pagos') {
        $tab_activa = "tab-pagos";
        $efectivo = isset($_POST['pago_efectivo']) ? 1 : 0;
        $tarjeta = isset($_POST['pago_tarjeta']) ? 1 : 0;
        $nequi = isset($_POST['pago_nequi']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE configuracion SET pago_efectivo=?, pago_tarjeta=?, pago_nequi=? WHERE id=1");
        $stmt->bind_param("iii", $efectivo, $tarjeta, $nequi);
        $stmt->execute();
        $stmt->close();
        $mensaje = "<div class='alert-success'>¡Métodos de pago guardados!</div>";
    }
    elseif ($seccion === 'seguridad') {
        $tab_activa = "tab-seguridad";
        $nueva_clave = trim($_POST['clave_admin'] ?? '');

        if (!empty($nueva_clave)) {
            // Generar el hash BCRYPT de la contraseña para guardar en la tabla usuarios
            $hash_clave = password_hash($nueva_clave, PASSWORD_BCRYPT);

            if ($id_usuario) {
                // Actualiza la contraseña encriptada del usuario en sesión
                $stmt = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
                $stmt->bind_param("si", $hash_clave, $id_usuario);
                $stmt->execute();
                $stmt->close();
            } else {
                // Si no detectó ID de sesión, actualiza el usuario administrador principal (id = 1)
                $stmt = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE id=1");
                $stmt->bind_param("s", $hash_clave);
                $stmt->execute();
                $stmt->close();
            }

            // Guardar también en la configuración general
            $stmt_conf = $conn->prepare("UPDATE configuracion SET clave_admin=? WHERE id=1");
            $stmt_conf->bind_param("s", $nueva_clave);
            $stmt_conf->execute();
            $stmt_conf->close();

            $mensaje = "<div class='alert-success'>¡Contraseña actualizada con éxito!</div>";
        } else {
            $mensaje = "<div class='alert-danger'>La contraseña no puede estar vacía.</div>";
        }
    }
}

// Consultar datos de configuración
$resultado = $conn->query("SELECT * FROM configuracion WHERE id=1");
$restaurante = $resultado ? $resultado->fetch_assoc() : [];

$clave_usuario_actual = $restaurante['clave_admin'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Sason - Configuración</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/configuracion.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-card { cursor: pointer; }
        
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-container input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #777;
        }
        .toggle-password:hover {
            color: #333;
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
                <a href="gestion_sistemas.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="ventas.php" class="menu-item"><i class="fa-solid fa-cart-shopping"></i> Ventas</a>
                <a href="menu.php" class="menu-item"><i class="fa-solid fa-utensils"></i> Menú</a>
                <a href="gastos.php" class="menu-item"><i class="fa-solid fa-wallet"></i> Gastos</a>
                <a href="reportes.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> Reportes</a>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-user-group"></i> Usuarios</a>
                <a href="configuracion.php" class="menu-item active"><i class="fa-solid fa-gear"></i> Configuración</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-content">
            <header class="top-header">
                <div class="welcome-text">
                    <h1>Configuración</h1>
                    <p>Gestiona los ajustes del sistema y las preferencias de tu restaurante</p>
                </div>
                <div class="header-actions">
                    <div class="date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        <span><?php echo $fecha_actual; ?></span>
                    </div>
                </div>
            </header>

            <?php echo $mensaje; ?>

            <!-- Pestañas de Configuración -->
            <section class="settings-tabs">
                <div class="tab-card <?php echo ($tab_activa === 'tab-restaurante') ? 'active' : ''; ?>" data-tab="tab-restaurante">
                    <div class="tab-icon brown"><i class="fa-solid fa-store"></i></div>
                    <div class="tab-info">
                        <h4>Información del Restaurante</h4>
                        <p>Datos básicos</p>
                    </div>
                </div>
                <div class="tab-card <?php echo ($tab_activa === 'tab-impuestos') ? 'active' : ''; ?>" data-tab="tab-impuestos">
                    <div class="tab-icon green"><i class="fa-solid fa-percent"></i></div>
                    <div class="tab-info">
                        <h4>Impuestos y Monedas</h4>
                        <p>Moneda e IVA</p>
                    </div>
                </div>
                <div class="tab-card <?php echo ($tab_activa === 'tab-pagos') ? 'active' : ''; ?>" data-tab="tab-pagos">
                    <div class="tab-icon blue"><i class="fa-solid fa-credit-card"></i></div>
                    <div class="tab-info">
                        <h4>Métodos de Pago</h4>
                        <p>Aceptados</p>
                    </div>
                </div>
                <div class="tab-card <?php echo ($tab_activa === 'tab-seguridad') ? 'active' : ''; ?>" data-tab="tab-seguridad">
                    <div class="tab-icon yellow"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="tab-info">
                        <h4>Seguridad</h4>
                        <p>Permisos y claves</p>
                    </div>
                </div>
            </section>

            <!-- INTERFAZ 1: Información del Restaurante -->
            <div id="tab-restaurante" class="tab-content <?php echo ($tab_activa === 'tab-restaurante') ? 'active' : ''; ?>">
                <section class="card-section">
                    <div class="card-title"><i class="fa-solid fa-store"></i> Información del Restaurante</div>
                    <form action="configuracion.php" method="POST" class="config-form">
                        <input type="hidden" name="seccion" value="restaurante">
                        <input type="hidden" name="tab_activa" value="tab-restaurante">
                        <div class="form-group">
                            <label>Nombre del Restaurante</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($restaurante['nombre'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>NIT / Identificación</label>
                            <input type="text" name="nit" class="form-control" value="<?php echo htmlspecialchars($restaurante['nit'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($restaurante['direccion'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($restaurante['telefono'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($restaurante['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group align-top">
                            <label>Descripción</label>
                            <textarea name="descripcion" class="form-control"><?php echo htmlspecialchars($restaurante['descripcion'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
                    </form>
                </section>
            </div>

            <!-- INTERFAZ 2: Impuestos y Monedas -->
            <div id="tab-impuestos" class="tab-content <?php echo ($tab_activa === 'tab-impuestos') ? 'active' : ''; ?>">
                <section class="card-section">
                    <div class="card-title"><i class="fa-solid fa-percent"></i> Impuestos y Monedas</div>
                    <form action="configuracion.php" method="POST" class="config-form">
                        <input type="hidden" name="seccion" value="impuestos">
                        <input type="hidden" name="tab_activa" value="tab-impuestos">
                        <div class="form-group">
                            <label>Porcentaje del INC (%)</label>
                            <input type="number" step="0.01" name="impuesto_porcentaje" class="form-control" value="<?php echo htmlspecialchars($restaurante['impuesto_porcentaje'] ?? '19.00'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Símbolo de Moneda</label>
                            <input type="text" name="moneda" class="form-control" value="<?php echo htmlspecialchars($restaurante['moneda'] ?? 'COP ($)'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Propina Sugerida (%)</label>
                            <input type="number" step="0.01" name="propina_sugerida" class="form-control" value="<?php echo htmlspecialchars($restaurante['propina_sugerida'] ?? '10.00'); ?>">
                        </div>
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Guardar Impuestos</button>
                    </form>
                </section>
            </div>

            <!-- INTERFAZ 3: Métodos de Pago -->
            <div id="tab-pagos" class="tab-content <?php echo ($tab_activa === 'tab-pagos') ? 'active' : ''; ?>">
                <section class="card-section">
                    <div class="card-title"><i class="fa-solid fa-credit-card"></i> Métodos de Pago Aceptados</div>
                    <form action="configuracion.php" method="POST" class="config-form">
                        <input type="hidden" name="seccion" value="pagos">
                        <input type="hidden" name="tab_activa" value="tab-pagos">
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h5>Efectivo</h5>
                                <p>Permitir pagos con dinero en efectivo en el POS</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="pago_efectivo" <?php echo ($restaurante['pago_efectivo'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h5>Tarjeta de Crédito/Débito</h5>
                                <p>Habilitar cobro por datáfono</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="pago_tarjeta" <?php echo ($restaurante['pago_tarjeta'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <h5>Transferencias / Nequi / Daviplata</h5>
                                <p>Recibir dinero por plataformas digitales</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="pago_nequi" <?php echo ($restaurante['pago_nequi'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Guardar Métodos de Pago</button>
                    </form>
                </section>
            </div>

            <!-- INTERFAZ 4: Seguridad -->
            <div id="tab-seguridad" class="tab-content <?php echo ($tab_activa === 'tab-seguridad') ? 'active' : ''; ?>">
                <section class="card-section">
                    <div class="card-title"><i class="fa-solid fa-shield-halved"></i> Ajustes de Seguridad</div>
                    <form action="configuracion.php" method="POST" class="config-form">
                        <input type="hidden" name="seccion" value="seguridad">
                        <input type="hidden" name="tab_activa" value="tab-seguridad">
                        <div class="form-group">
                            <label>Nueva Contraseña</label>
                            <div class="password-container">
                                <input type="password" id="clave_input" name="clave_admin" class="form-control" value="<?php echo htmlspecialchars($clave_usuario_actual); ?>" placeholder="Ingresa la nueva clave" required>
                                <i class="fa-solid fa-eye toggle-password" id="btn_eye"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Actualizar Clave</button>
                    </form>
                </section>
            </div>

        </main>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const tabs = document.querySelectorAll(".tab-card");
        const contents = document.querySelectorAll(".tab-content");

        tabs.forEach(tab => {
            tab.addEventListener("click", function () {
                const targetId = this.getAttribute("data-tab");

                tabs.forEach(t => t.classList.remove("active"));
                contents.forEach(c => c.classList.remove("active"));

                this.classList.add("active");
                document.getElementById(targetId).classList.add("active");
            });
        });

        // Alternar visibilidad de la contraseña
        const eyeBtn = document.getElementById("btn_eye");
        const passInput = document.getElementById("clave_input");

        if (eyeBtn && passInput) {
            eyeBtn.addEventListener("click", function () {
                if (passInput.type === "password") {
                    passInput.type = "text";
                    this.classList.remove("fa-eye");
                    this.classList.add("fa-eye-slash");
                } else {
                    passInput.type = "password";
                    this.classList.remove("fa-eye-slash");
                    this.classList.add("fa-eye");
                }
            });
        }
    });
    </script>
</body>
</html>