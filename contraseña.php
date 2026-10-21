<?php
session_start();
include("inc/conexion.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_codigo'])) {
    $email = $conexion->real_escape_string($_POST['correo']);

    // 1. Verificar si el correo existe en la base de datos
    $verificar = $conexion->query("SELECT * FROM usuarios WHERE email = '$email'");

    if ($verificar && $verificar->num_rows > 0) {
        
        // 2. Generar el código aleatorio
        $codigo_verificacion = rand(100000, 999999);

        // 3. Guardar en la sesión
        $_SESSION['email_recuperacion'] = $email;
        $_SESSION['codigo_verificacion'] = $codigo_verificacion;

        // 4. Enviar con PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'estibenalarcon156@gmail.com';
            $mail->Password   = 'zvoksrjqxuxlkodq'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('estibenalarcon156@gmail.com', 'Restaurant Sason');
            $mail->addAddress($email); 

            $mail->isHTML(true);
            $mail->Subject = 'Codigo de Verificacion - Restaurant Sason';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2>Restablecer Contraseña</h2>
                    <p>Tu código de verificación es:</p>
                    <h1 style='color: #d32f2f; letter-spacing: 5px;'>$codigo_verificacion</h1>
                </div>
            ";

            $mail->send();

            // Redirige al paso 2: verificar_codigo.php
            header("Location: codigo_verificacion.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['mensaje_error'] = "Error al enviar el correo. Inténtalo más tarde.";
            header("Location: contraseña.php");
            exit();
        }

    } else {
        $_SESSION['mensaje_error'] = "El correo ingresado no está registrado.";
        header("Location: contraseña.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Restaurant Sason</title>
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

        <h2>Recuperar contraseña</h2>

        <div class="divisor-sombrero">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alerta-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php 
                    echo $_SESSION['mensaje_error']; 
                    unset($_SESSION['mensaje_error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <label for="correo">Correo electrónico</label>
            <div class="input-box">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" id="correo" name="correo" placeholder="Ingresa tu correo registrado" required>
            </div>

            <p class="instrucciones-clave">
                Te enviaremos un código de verificación para que puedas restablecer tu contraseña.
            </p>

            <button type="submit" name="solicitar_codigo" class="btn-crear">
                ENVIAR CÓDIGO DE VERIFICACIÓN
            </button>
        </form>

        <div class="separador">
            <span>o</span>
        </div>

        <div class="login-link">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión</a>
        </div>
    </div>

</body>
</html>