<?php
session_start();

// 🔒 Cabeceras de seguridad
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// 🔐 Si ya hay sesión iniciada, redirigir
if (isset($_SESSION['usuario_id'])) {
    header("Location: gestion_sistemas.php");
    exit();
}

// 📌 Incluir conexión
include("inc/conexion.php");

if (!$conexion || $conexion->connect_error) {
    $_SESSION['mensaje_error'] = "Error de conexión con el sistema. Intenta más tarde.";
}

// 🔑 Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ⏱️ Control de intentos
$max_intentos = 5;
$tiempo_bloqueo = 300;
$ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!isset($_SESSION['intentos_login'])) $_SESSION['intentos_login'] = 0;
if (!isset($_SESSION['tiempo_bloqueo'])) $_SESSION['tiempo_bloqueo'] = 0;

$error = "";
$exito = "";

if (isset($_SESSION['mensaje_exito'])) {
    $exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

if ($_SESSION['tiempo_bloqueo'] > time()) {
    $tiempo_restante = ceil(($_SESSION['tiempo_bloqueo'] - time()) / 60);
    $error = "Demasiados intentos. Intenta nuevamente en $tiempo_restante minutos.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && empty($error)) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Solicitud inválida. Recarga e intenta de nuevo.";
    } elseif ($_SESSION['tiempo_bloqueo'] > time()) {
        $tiempo_restante = ceil(($_SESSION['tiempo_bloqueo'] - time()) / 60);
        $error = "Demasiados intentos. Intenta en $tiempo_restante min.";
    } else {
        $login_input = trim($_POST['nombre'] ?? '');
        $password_input = $_POST['clave'] ?? '';

        if (empty($login_input) || empty($password_input)) {
            $error = "Completa todos los campos.";
        } else {
            $stmt = $conexion->prepare("SELECT id, nombre, username, password_hash, rol 
                                        FROM usuarios 
                                        WHERE username = ? OR email = ? OR nombre = ? 
                                        LIMIT 1");
            $stmt->bind_param("sss", $login_input, $login_input, $login_input);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado && $resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();
                if (password_verify($password_input, $usuario['password_hash'])) {
                    $_SESSION['intentos_login'] = 0;
                    $_SESSION['tiempo_bloqueo'] = 0;
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario'] = $usuario['nombre'];
                    $_SESSION['username'] = $usuario['username'];
                    $_SESSION['rol'] = $usuario['rol'];
                    unset($_SESSION['csrf_token']);
                    header("Location: gestion_sistemas.php");
                    exit();
                }
            }
            $_SESSION['intentos_login']++;
            if ($_SESSION['intentos_login'] >= $max_intentos) {
                $_SESSION['tiempo_bloqueo'] = time() + $tiempo_bloqueo;
                $error = "Demasiados intentos. Bloqueo por 5 minutos.";
            } else {
                $error = "Credenciales incorrectas. Intento {$_SESSION['intentos_login']} de $max_intentos.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Restaurant Sason</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/login.css">
<style>
        /* 🔹 Estilos Base del Modal con Desplazamiento */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Alineación arriba para permitir scroll */
            z-index: 2000;
            padding: 16px;
            box-sizing: border-box;
            animation: fadeIn 0.3s ease forwards;
            overflow-y: auto; /* Barra de desplazamiento vertical */
        }

        .modal-container {
            background: #ffffff;
            width: 100%;
            max-width: 850px;
            margin: 2rem auto; /* Espacio superior/inferior en pantallas altas */
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            animation: slideUp 0.3s ease forwards;
        }

        .modal-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #059669;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 18px;
            font-weight: bold;
            color: #ecf1ee;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 10;
        }
        .modal-close-btn:hover {
            background: #285846;
            color: #fcf8f8;
            width: 39px;
            height: 39px;
        }

        .modal-header {
            padding: 30px 30px 10px 30px;
            text-align: center;
        }
        .modal-header h2 {
            margin: 0 0 8px 0;
            font-size: 26px;
            color: #1f2937;
        }
        .modal-header p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
        }

        .devs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px 30px 35px 30px;
        }

        .dev-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dev-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }

        .dev-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 12px auto;
            border: 3px solid #10b981;
            background-color: #e5e7eb;
        }
        .dev-name {
            margin: 0 0 6px 0;
            font-size: 17px;
            color: #111827;
            font-weight: 600;
        }
        .dev-role {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #059669;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dev-desc {
            margin: 0;
            font-size: 13px;
            color: #4b5563;
            line-height: 1.4;
        }

        /* 🎨 Estilo Personalizado de la Barra de Desplazamiento */
        .modal-overlay::-webkit-scrollbar {
            width: 8px;
        }
        .modal-overlay::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        .modal-overlay::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            border-radius: 4px;
        }
        .modal-overlay::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #059669 0%, #047857 100%);
        }
        /* Firefox */
        @supports (scrollbar-color: #10b981 transparent) {
            .modal-overlay {
                scrollbar-width: thin;
                scrollbar-color: #10b981 rgba(0, 0, 0, 0.1);
            }
        }

        .btn-open-devs {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 50px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .btn-open-devs:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .btn-open-devs svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .alerta-exito {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alerta-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* 📱 Responsive: Una columna + Scroll en móviles */
        @media (max-width: 768px) {
            .devs-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                padding: 20px 20px 30px 20px;
            }
            .modal-container {
                margin: 1rem auto;
                border-radius: 16px;
            }
            .modal-header {
                padding: 20px 20px 10px 20px;
            }
        }
    </style>
</head>
<body style="display:flex">

<div class="contenedor">
    <div class="login">
        <h1>Inicia sesión ahora</h1>

        <?php if (!empty($exito)): ?>
            <div class="alerta-exito">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($exito) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alerta-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form id="formLogin" method="POST" action="index.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <label>Nombre o Correo *</label>
            <div class="input">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="nombre" id="inputUsuario" 
                       placeholder="Ingresa tu nombre o correo" 
                       autocomplete="username" required>
            </div>

            <label>Contraseña *</label>
            <div class="input">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="clave" id="inputPassword" 
                       placeholder="Ingresa tu contraseña" 
                       autocomplete="current-password" required>
            </div>

            <button type="submit" name="login" class="boton">ACCESO</button>
        </form>

        <div class="links">
            <a href="crear_cuenta.php">¿No tienes una cuenta?</a>
            <a href="contraseña.php">¿Se te olvidó tu contraseña?</a>
        </div>
    </div>
</div>

<script>
    function limpiarCampos() {
        const form = document.getElementById('formLogin');
        if (form) form.reset();
        ['inputUsuario', 'inputPassword'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }
    window.addEventListener('load', limpiarCampos);
    window.addEventListener('pageshow', limpiarCampos);
</script>

<!-- 👥 Modal con Desplazamiento -->
<div class="modal-overlay" id="devModal" style="display: none;">
    <div class="modal-container">
        <button class="btn-open-devs" onclick="cerrarModal()">Volver</button>
        <br>
        <div class="modal-header">
            <h2>Conoce a los Desarrolladores</h2>
            <p>El equipo detrás de la creación de esta aplicación</p>
        </div>

        <div class="devs-grid">
            <div class="dev-card">
                <img src="imagen/logo.jpg" alt="Logo SENA" class="dev-avatar">
                <h3 class="dev-name">SENA</h3>
                <p class="dev-role">Institución Educativa</p>
                <p class="dev-desc">Servicio Nacional de Aprendizaje</p>
            </div>
            <div class="dev-card">
                <img src="imagen/escudo.jpg" alt="escudo SENA" class="dev-avatar">
                <h3 class="dev-name">Escudo</h3>
                <p class="dev-role">Institución Educativa</p>
                <p class="dev-desc">Servicio Nacional de Aprendizaje</p>
            </div>
            <div class="dev-card">
                <img src="imagen/desa1.jpeg" alt="Foto Lubín Andrey" class="dev-avatar">
                <h3 class="dev-name">Lubín Andrey</h3>
                <p class="dev-role">Lead Frontend</p>
                <p class="dev-desc">Diseño de interfaces modernas y experiencia de usuario.</p>
            </div>
            <div class="dev-card">
                <img src="imagen/desa2.jpeg" alt="Foto Yeferson Estiben" class="dev-avatar">
                <h3 class="dev-name">Yeferson Estiben</h3>
                <p class="dev-role">Backend Engineer</p>
                <p class="dev-desc">Lógica de servidores y arquitectura de bases de datos.</p>
            </div>
            <div class="dev-card">
                <img src="imagen/desa3.jpeg" alt="Foto Dann Yasuri" class="dev-avatar">
                <h3 class="dev-name">Dann Yasuri</h3>
                <p class="dev-role">UI/UX Designer</p>
                <p class="dev-desc">Flujos de usuario e identidad visual de la plataforma.</p>
            </div>
            <!-- Puedes agregar más tarjetas aquí y la barra aparecerá automáticamente -->
            <!-- <div class="dev-card"> ... </div> -->
        </div>
    </div>
</div>

<button class="btn-open-devs" onclick="abrirModal()">
    <svg viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
    </svg>
    Desarrolladores
</button>

<script>
function abrirModal() {
    document.getElementById('devModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Bloquea el fondo
}
function cerrarModal() {
    document.getElementById('devModal').style.display = 'none';
    document.body.style.overflow = ''; // Desbloquea el fondo
}
document.getElementById('devModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) cerrarModal();
});
</script>

</body>
</html>