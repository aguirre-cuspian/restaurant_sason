<?php
session_start();
include("inc/conexion.php");

// Si no ha solicitado código previamente, lo devolvemos a contraseña.php
if (!isset($_SESSION['codigo_verificacion']) || !isset($_SESSION['email_recuperacion'])) {
    header("Location: contraseña.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_codigo'])) {
    $codigo_ingresado = trim($_POST['codigo']);

    // Validar si el código ingresado coincide con el de la sesión
    if ($codigo_ingresado == $_SESSION['codigo_verificacion']) {
        $_SESSION['codigo_validado'] = true; // Permiso para cambiar la contraseña
        header("Location: nueva_clave.php");
        exit();
    } else {
        $error = "El código ingresado es incorrecto. Verifica tu correo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código - Restaurant Sason</title>
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
                <i class="fa-solid fa-shield-halved"></i>
            </div>
        </div>

        <h2>Ingresa el Código</h2>

        <div class="divisor-sombrero">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alerta-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="codigo">Código de 6 dígitos</label>
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="text" id="codigo" name="codigo" maxlength="6" placeholder="Ej. 123456" required autocomplete="off" style="text-align: center; font-size: 1.2rem; letter-spacing: 3px;">
            </div>

            <p class="instrucciones-clave">
                Revisa tu bandeja de entrada en <strong><?php echo htmlspecialchars($_SESSION['email_recuperacion']); ?></strong>.
            </p>

            <button type="submit" name="validar_codigo" class="btn-crear">
                VERIFICAR CÓDIGO
            </button>
        </form>

        <div class="separador">
            <span>o</span>
        </div>

        <div class="login-link">
            <a href="contraseña.php"><i class="fa-solid fa-arrow-left"></i> Solicitar otro código</a>
        </div>
    </div>

</body>
</html>