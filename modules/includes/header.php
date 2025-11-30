<?php
// header.php
session_start();

// Verifica si el usuario inició sesión
if(!isset($_SESSION['usuario'])){
    header("Location: ../modules/login/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | ZEUS</title>

  <!-- Estilos -->
  <link rel="stylesheet" href="assets/css/dashboard.css">

  <!-- Favicon para todos los módulos -->
  <link rel="icon" href="/Zeus/icon/favicon.ico" type="image/x-icon">
  <link rel="icon" href="/Zeus/icon/favicon-96x96.png" type="image/png">
  <link rel="icon" href="/Zeus/icon/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/Zeus/icon/apple-touch-icon.png">
  <link rel="manifest" href="/Zeus/icon/site.webmanifest">

</head>
<body>
  <header class="main-header">
    <div class="logo">🏠 ZEUS Inventario</div>
    <nav>
      <ul>
        <li><a href="dashboard.php">Inicio</a></li>
        <li><a href="#">Productos</a></li>
        <li><a href="#">Entradas</a></li>
        <li><a href="#">Salidas</a></li>
        <li><a href="../modules/login/logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>
  <main class="content">
