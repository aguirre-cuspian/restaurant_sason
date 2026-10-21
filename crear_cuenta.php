<?php
session_start(); // Necesario para guardar los mensajes que van hacia index.php
include("inc/conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {
    $nombre   = $conexion->real_escape_string($_POST['nombre']);
    $username = $conexion->real_escape_string($_POST['username']);
    $email    = $conexion->real_escape_string($_POST['correo']);
    $clave    = $_POST['clave'];

    $password_hash = password_hash($clave, PASSWORD_DEFAULT);
    $rol = "Administrador";

    // 1. Verificar si el usuario o correo ya existen
    $check = $conexion->query("SELECT * FROM usuarios WHERE email='$email' OR username='$username'");
    
    if ($check && $check->num_rows > 0) {
        $_SESSION['mensaje_error'] = "El correo o el usuario ya se encuentran registrados.";
        header("Location: crear_cuenta.php");
        exit();
    }

    // 2. Insertar registros en MySQL
    $sql = "INSERT INTO usuarios (nombre, username, email, password_hash, rol) 
            VALUES ('$nombre', '$username', '$email', '$password_hash', '$rol')";

    if ($conexion->query($sql)) {
        // Mensaje creativo y personalizado para Restaurant Sason
        $_SESSION['mensaje_exito'] = "¡bienvenido a la familia de Restaurant Sason! 🍽️ Tu cuenta ha sido creada con éxito.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['mensaje_error'] = "Ocurrió un error al registrar la cuenta. Inténtalo de nuevo.";
        header("Location: crear_cuenta.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea tu cuenta - Restaurant Sason</title>
    
    <!-- Archivo de estilos CSS -->
    <link rel="stylesheet" href="css/crear.css?v=2">
    
    <!-- FontAwesome para los Íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Estilo para la alerta interna de error -->
    <style>
        .alerta-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <!-- Capa Oscura de Fondo -->
    <div class="overlay"></div>

    <!-- Tarjeta Principal de Registro -->
    <div class="card-registro">
        
        <div class="avatar-container">
            <div class="avatar">
                <i class="fa-solid fa-user-chef"></i>
            </div>
        </div>

        <h2>Crea tu cuenta</h2>

        <div class="divisor-sombrero">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <!-- Muestra alerta si hubo un error al registrar (ej. correo duplicado) -->
        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alerta-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo $_SESSION['mensaje_error']; ?></span>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <form method="POST" action="crear_cuenta.php">
            
            <label for="nombre">Nombre completo</label>
            <div class="input-box">
                <i class="fa-regular fa-user"></i>
                <input type="text" id="nombre" name="nombre" placeholder="Ingresa tu nombre completo" required>
            </div>

            <label for="username">Usuario (Username)</label>
            <div class="input-box">
                <i class="fa-solid fa-at"></i>
                <input type="text" id="username" name="username" placeholder="Ingresa tu nombre de usuario" required>
            </div>

            <label for="correo">Correo electrónico</label>
            <div class="input-box">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" id="correo" name="correo" placeholder="Ingresa tu correo electrónico" required>
            </div>

            <label for="clave">Clave</label>
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="clave" name="clave" placeholder="Crea una contraseña" required>
            </div>
            
            <p class="instrucciones-clave">
                La contraseña debe tener al menos 8 caracteres.
            </p>

            <button type="submit" name="registrar" class="btn-crear">
                CREAR CUENTA
            </button>
        </form>

        <div class="separador">
            <span>o</span>
        </div>

        <div class="login-link">
            ¿Ya tienes una cuenta? <a href="index.php">Inicia sesión</a>
        </div>
    </div>

</body>
</html>