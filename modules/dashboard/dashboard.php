<?php
session_start();
require_once '../../bd/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../../modules/login/login.php");
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$rol_usuario = $_SESSION['rol'] ?? 'empleado';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <title>Panel Zeus Hogar</title>

  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/dashboard.css">

  <!-- Favicons generados por RealFaviconGenerator -->
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

  <!-- HEADER PRINCIPAL -->
  <header>
    <!-- ☰ BOTÓN HAMBURGUESA PARA MÓVILES -->
    <button class="mobile-menu-btn" id="mobile-menu-btn">
      <i class='bx bx-menu'></i>
    </button>

    <!-- USER INFO - SIEMPRE CENTRADO -->
    <div class="user-info">
      <span><?php echo htmlspecialchars($nombre_usuario); ?></span>
      <span class="rol-badge">
        <?php 
          if ($rol_usuario == 'admin') {
              echo 'Gerencia';
          } else {
              echo htmlspecialchars($rol_usuario);
          }
        ?>
      </span>
    </div>

    <!-- NAV PRINCIPAL - A LA DERECHA -->
    <nav class="nav-principal">
      <a href="../../modules/login/logout.php">
        <i class='bx bx-log-out'></i>
        <span>Salir</span>
      </a>
    </nav>
  </header>

  <!-- CONTENEDOR PRINCIPAL -->
  <div class="layout">
    <aside id="sidebar">
      <!-- BOTÓN ☰ PARA COLAPSAR / EXPANDIR -->
      <button id="toggle-menu" class="toggle-btn"><i class='bx bx-menu'></i></button>

      <div class="menu">
        <ul>
          <!-- INICIO -->
          <li class="active" data-section="inicio">
            <i class='bx bx-grid-alt'></i>Inicio
          </li>
          
          <!-- PERSONAL -->
          <li class="menu-category" data-category="personal">
            PERSONAL<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-personal">
            <li data-section="empleados">Empleados</li>
          </ul>
          
          <!-- OPERACIONES -->
          <li class="menu-category" data-category="operaciones">
            OPERACIONES<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-operaciones">
            <li data-section="vehiculos">Vehículos y Placas</li>
            <li data-section="inventario">Inventario Bodega</li>
          </ul>

          <!-- ASIGNACIONES -->
          <li class="menu-category" data-category="asignaciones">
            ASIGNACIONES<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-asignaciones">
            <li data-section="nueva-salida">Nueva Salida</li>
            <li data-section="asignaciones-activas">Asignaciones Activas</li>
            <li data-section="historial-asignaciones">Historial</li>
          </ul>

          <!-- VENTAS -->
          <li class="menu-category" data-category="ventas">
            VENTAS<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-ventas">
            <li data-section="contratos">Contratos de Venta</li>
          </ul>

                    <!-- CANCELACION -->
          <li class="menu-category" data-category="cancelacion">
            CANCELACION<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-cancelacion">
            <li data-section="contratosCancelados">Contratos Cancelados</li>
          </ul>


          <!-- COBRADORES -->
          <li class="menu-category" data-category="cobradores">
            COBRADORES<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-cobradores">
            <li data-section="registrar-cobro">Registrar Cobro Diario</li>
            <li data-section="ver-cobros">Ver Cobros Diarios</li>
            <li data-section="generar-comision">Generar Comisión Semanal</li>
            <li data-section="comisiones">Ver Comisiones</li>
            <li data-section="semanas-cobro">Gestionar Semanas</li>
            <li data-section="prestamos">Préstamos</li>
          </ul>

          <!-- PAGOS -->
          <li class="menu-category" data-category="pagos">
            PAGOS<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-pagos">
            <li data-section="resumen-pagos">Resumen General</li>
            <li data-section="registrar-pago">Registrar Sueldo Fijo</li>
            <li data-section="historial-pagos">Historial de Pagos</li>
          </ul>


          
          <!-- CONTROL -->
          <li class="menu-category" data-category="control">
            CONTROL<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-control">
            <li data-section="gasolina">Control Gasolina</li>
            <li data-section="registrarGasto">Registrar Gasto</li>
          </ul>
          
    
          
          <!-- REPORTES -->
          <li class="menu-category" data-category="reportes">
            REPORTES<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-reportes">
            <li data-section="reporte-semanal">Ver Reportes</li>
          </ul>
        </ul>

              <!-- USUARIOS -->
          <li class="menu-category" data-category="usuarios">
            USUARIOS<i class='bx bx-chevron-down'></i>
          </li>
          <ul class="submenu" id="submenu-usuarios">
            <li data-section="gestion-usuarios">Gestión de Usuarios</li>
          </ul>
      </div>
    </aside>

    <!-- OVERLAY OSCURO PARA CERRAR MENÚ EN MÓVIL -->
    <div class="sidebar-overlay"></div>

    <main>
      <!-- SECCIÓN INICIO -->
      <section id="inicio" class="active">
        <h1>¡Hola, <?php echo htmlspecialchars($nombre_usuario); ?>!</h1>
        <div class="card">
          <h3>Panel de Control</h3>
          <p>Selecciona una opción del menú lateral para comenzar.</p>
        </div>
      </section>

      <!-- SECCIÓN EMPLEADOS -->
      <section id="empleados">
        <iframe src="../empleados/empleados.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN VEHÍCULOS -->
      <section id="vehiculos">
        <iframe src="../vehiculos/vehiculos.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN CONTRATOS -->
      <section id="contratos">
        <iframe src="../contratos/contratos.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- ============================================ -->
      <!-- MÓDULO DE COBRADORES -->
      <!-- ============================================ -->

      <!-- SECCIÓN REGISTRAR COBRO DIARIO -->
      <section id="registrar-cobro">
        <iframe src="../cobradores/registrar_cobro.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN VER COBROS DIARIOS -->
      <section id="ver-cobros">
        <iframe src="../cobradores/ver_cobros.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN GENERAR COMISIÓN SEMANAL -->
      <section id="generar-comision">
        <iframe src="../cobradores/generar_comision.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN COMISIONES - Ver Listado -->
      <section id="comisiones">
        <iframe src="../cobradores/index.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN SEMANAS DE COBRO -->
      <section id="semanas-cobro">
        <iframe src="../cobradores/gestionar_semanas.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN PRÉSTAMOS -->
      <section id="prestamos">
        <iframe src="../cobradores/prestamos.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- ============================================ -->
      <!-- FIN MÓDULO DE COBRADORES -->
      <!-- ============================================ -->

      <!-- ============================================ -->
      <!-- MÓDULO DE PAGOS - SECCIONES -->
      <!-- ============================================ -->

      <!-- SECCIÓN RESUMEN DE PAGOS -->
      <section id="resumen-pagos">
        <iframe src="../pagos/index.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN REGISTRAR PAGO -->
      <section id="registrar-pago">
        <iframe src="../pagos/registrar_pago.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN HISTORIAL DE PAGOS -->
      <section id="historial-pagos">
        <iframe src="../pagos/historial_pagos.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- ============================================ -->
      <!-- FIN MÓDULO DE PAGOS -->
      <!-- ============================================ -->

      <!-- SECCIÓN CONTRATOS CANCELADOS -->
      <section id="contratosCancelados">
        <iframe src="../contratosCancelados/folios_cancelados.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN INVENTARIO -->
      <section id="inventario">
        <iframe src="../inventarios/inventarios.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN NUEVA SALIDA -->
      <section id="nueva-salida">
        <iframe data-src="../asignaciones/nueva_salida.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN ASIGNACIONES ACTIVAS -->
      <section id="asignaciones-activas">
        <iframe data-src="../asignaciones/asignaciones_activas.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN HISTORIAL ASIGNACIONES -->
      <section id="historial-asignaciones">
        <iframe id="iframeHistorial"
                src="../asignaciones/historial_asignaciones.php"
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN GASOLINA -->
      <section id="gasolina">
        <?php 
        $tab = isset($_GET['tab']) ? '?tab=' . htmlspecialchars($_GET['tab']) : '';
        ?>
        <iframe src="../gasolina/index.php<?php echo $tab; ?>" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN REGISTRAR GASTO -->
      <section id="registrarGasto">
        <iframe src="../gastos/index.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>

      <!-- SECCIÓN GESTIÓN USUARIOS -->
      <section id="gestion-usuarios">
        <iframe src="../usuarios/usuarios.php" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>
      
      <!-- SECCIÓN REPORTE SEMANAL -->
      <section id="reporte-semanal">
        <iframe src="../reportes/index.php<?php echo $tab; ?>" 
                style="width: 100%; height: calc(100vh - 120px); border: none; border-radius: 12px;">
        </iframe>
      </section>
    </main>
  </div>

  <script src="assets/js/dashboard.js"></script>
</body>
</html>