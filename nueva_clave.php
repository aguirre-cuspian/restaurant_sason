<?php
session_start();
include("inc/conexion.php");

// Si no se ha validado el código correctamente, bloqueamos el acceso
if (!isset($_SESSION['codigo_validado']) || $_SESSION['codigo_validado'] !== true) {
    header("Location: contraseña.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_clave'])) {
    $nueva_clave = $_POST['nueva_clave'];
    $confirmar_clave = $_POST['confirmar_clave'];

    if ($nueva_clave !== $confirmar_clave) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($nueva_clave) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $email = $_SESSION['email_recuperacion'];
        
        // Encriptamos la contraseña tal cual está en tu base de datos
        $clave_encriptada = password_hash($nueva_clave, PASSWORD_DEFAULT);

        // AQUÍ ESTÁ EL CAMBIO: 'password_hash' es el nombre real de tu columna en MySQL
        $stmt = $conexion->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $clave_encriptada, $email);

        if ($stmt->execute()) {
            // Limpiamos las variables de sesión usadas en la recuperación
            unset($_SESSION['codigo_verificacion']);
            unset($_SESSION['email_recuperacion']);
            unset($_SESSION['codigo_validado']);

            // Guardamos mensaje de éxito para el login
            $_SESSION['mensaje_exito'] = "¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.";
            header("Location: index.php");
            exit();
        } else {
            $error = "Error al actualizar en la base de datos. Inténtalo de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Restaurant Sason</title>
    <link rel="stylesheet" href="css/crear.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alerta-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="overlay"></div>

    <div class="card-registro">
        
        <div class="avatar-container">
            <div class="avatar">
                <i class="fa-solid fa-key"></i>
            </div>
        </div>

        <h2>Restablecer Contraseña</h2>

        <div class="divisor-sombrero">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="nueva_clave">Nueva Contraseña</label>
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="nueva_clave" name="nueva_clave" placeholder="Ingresa tu nueva contraseña" required>
            </div>

            <label for="confirmar_clave">Confirmar Contraseña</label>
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirmar_clave" name="confirmar_clave" placeholder="Repite la contraseña" required>
            </div>

            <button type="submit" name="cambiar_clave" class="btn-crear">
                GUARDAR CONTRASEÑA
            </button>
        </form>

    </div>

</body>
</html>