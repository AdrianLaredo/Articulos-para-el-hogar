<?php
session_start();
require_once __DIR__ . '/../../bd/database.php';

// Inicializar mensaje de error
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Debes llenar todos los campos";
    } else {
        $sql = "SELECT id, usuario, nombre, apellido_paterno, apellido_materno, rol, contraseña
                FROM Usuarios
                WHERE usuario = :usuario AND estado = 'activo'";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':usuario' => $username]);
        $user = $stmt->fetch();

        if ($user && $user['contraseña'] === md5($password)) {
            session_regenerate_id(true);

            $_SESSION['id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellido_paterno'] . ' ' . $user['apellido_materno'];
            $_SESSION['rol'] = $user['rol'];

            header("Location: ../dashboard/dashboard.php");
            exit;
        } else {
            $error_message = "Usuario o contraseña incorrectos";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Artículos para el hogar ZEUS</title>
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- Favicons -->
<!-- Favicons y manifest -->
<!-- Favicons y manifest -->
<link rel="icon" type="image/png" sizes="192x192" href="/icon/web-app-manifest-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/icon/web-app-manifest-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icon/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="/icon/favicon.svg">
<link rel="shortcut icon" href="/icon/favicon.ico">
<link rel="manifest" href="/icon/site.webmanifest">
<meta name="theme-color" content="#a81a1a">
<meta name="apple-mobile-web-app-title" content="Panel Zeus Hogar">

</head>
<body>
    <div class="login-container">
        <div class="brand">
            <h1>Artículos para el hogar <span>ZEUS</span></h1>
        </div>

        <!-- Mensaje de error -->
        <?php if ($error_message): ?>
            <div id="error-message" class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form class="login-form" action="" method="POST">
            <h2>Iniciar sesión</h2>

            <div class="input-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" placeholder="Ingresa tu usuario" required>
            </div>

            <div class="input-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
            </div>

            <button type="submit" class="btn">Entrar</button>
        </form>

        <footer>
            <p>© 2025 Artículos para el hogar ZEUS</p>
        </footer>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const errorDiv = document.getElementById("error-message");
        if (errorDiv && errorDiv.textContent.trim() !== "") {
            errorDiv.style.display = "block";
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    </script>
</body>
</html>
