<?php
// modulos/reporte_semanal/reporte_gasolina.php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

// Validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener parámetros
$periodo = $_GET['periodo'] ?? 'semanal';
$fecha_inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('monday this week'));
$fecha_fin = $_GET['fin'] ?? date('Y-m-d', strtotime('sunday this week'));

// Validar periodo
$periodos_validos = ['semanal', 'mensual', 'anual'];
if (!in_array($periodo, $periodos_validos)) {
    $periodo = 'semanal';
}

// Validar fechas
if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

// Obtener datos de gasolina desde la BD
$datosLitros = obtenerDatosLitros($fecha_inicio, $fecha_fin, $periodo);
$datosEfectivo = obtenerDatosEfectivo($fecha_inicio, $fecha_fin, $periodo);

// Formatear fechas para mostrar
$fecha_inicio_format = formatearFecha($fecha_inicio);
$fecha_fin_format = formatearFecha($fecha_fin);

// Calcular totales para el resumen
$total_litros = 0;
$total_dinero_litros = 0;
$total_efectivo = 0;

// Sumar litros Y dinero desde porTipo (que tiene los datos completos)
foreach ($datosLitros['porTipo'] as $item) {
    $total_litros += $item['value'];
    $total_dinero_litros += isset($item['dinero']) ? $item['dinero'] : 0;
}

// Si porTipo está vacío, sumar desde porDia
if (empty($datosLitros['porTipo'])) {
    foreach ($datosLitros['porDia'] as $item) {
        $total_litros += $item['value'];
        $total_dinero_litros += isset($item['dinero']) ? $item['dinero'] : 0;
    }
}

// Sumar efectivo
foreach ($datosEfectivo['porEmpleado'] as $item) {
    $total_efectivo += $item['value'];
}

// Título del periodo
$titulo_periodo = ucfirst($periodo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte de Gasolina - <?php echo $titulo_periodo; ?></title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/reporte.css">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="back-button">
        <a href="index.php">
          <i class='bx bx-arrow-back'></i> Volver a Reportes
        </a>
      </div>
      <h1>Reporte de Gasolina - <?php echo $titulo_periodo; ?></h1>
      <p>Periodo: <strong><?php echo $fecha_inicio_format; ?> - <?php echo $fecha_fin_format; ?></strong></p>
    </div>

    <!-- Resumen -->
    <div class="summary">
      <div class="chart-title">
         Resumen del Periodo
      </div>
      <div class="summary-grid">
        <div class="summary-item">
          <div class="summary-icon">
            <i class='bx bx-tint'></i>
          </div>
          <div class="summary-content">
            <h3><?php echo number_format($total_litros, 2, '.', ','); ?> L</h3>
            <p>Total de Litros</p>
<small style="color: #10b981; font-weight: 600; font-size: 1.2em;">
  <?php echo formatearMoneda($total_dinero_litros); ?>
</small>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon success">
            <i class='bx bx-dollar-circle'></i>
          </div>
          <div class="summary-content">
            <h3><?php echo formatearMoneda($total_efectivo); ?></h3>
            <p>Total de Efectivo</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Sección de Litros: Gráfica + Tabla -->
    <div class="section-with-table">
      <div class="chart-card-full">
        <div class="chart-title">
         Consumo de Litros
        </div>
        <div class="total-badge litros">
          Total: <?php echo number_format($total_litros, 2, '.', ','); ?> L
          <br>
          <small style="opacity: 0.9;"><?php echo formatearMoneda($total_dinero_litros); ?></small>
        </div>
        <div id="chart-litros" class="chart-container"></div>
      </div>

      <div class="card">
        <h2>Detalles por Vehículo</h2>
        <div class="table-responsive">
          <table class="details-table">
            <thead>
              <tr>
                <th>Placas</th>
                <th>Marca</th>
                <th>Litros</th>
                <th>Dinero</th>
                <th>% del Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($datosLitros['porTipo'] as $item): ?>
                <?php if ($item['placas'] !== 'Sin Placas' || $item['marca'] !== 'Sin Marca'): ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['placas']); ?></td>
                  <td><?php echo htmlspecialchars($item['marca']); ?></td>
                  <td><?php echo number_format($item['value'], 2, '.', ','); ?> L</td>
                  <td><?php echo formatearMoneda($item['dinero']); ?></td>
                  <td>
                    <?php 
                      $porcentaje = $total_litros > 0 ? ($item['value'] / $total_litros) * 100 : 0;
                      echo number_format($porcentaje, 1); 
                    ?>%
                  </td>
                </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty($datosLitros['porTipo']) || $datosLitros['porTipo'][0]['placas'] === 'Sin Placas'): ?>
                <tr>
                  <td colspan="5" class="no-data">No hay datos disponibles para este periodo</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Sección de Efectivo: Gráfica + Tabla -->
    <div class="section-with-table">
      <div class="chart-card-full">
        <div class="chart-title">
        Consumo de Efectivo
        </div>
        <div class="total-badge efectivo">
          Total: <?php echo formatearMoneda($total_efectivo); ?>
        </div>
        <div id="chart-efectivo" class="chart-container"></div>
      </div>

      <div class="card">
        <h2>Top Empleados por Efectivo</h2>
        <div class="table-responsive">
          <table class="details-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Empleado</th>
                <th>Efectivo</th>
                <th>% del Total</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                $posicion = 1;
                foreach ($datosEfectivo['porEmpleado'] as $item): 
                  if ($item['name'] !== 'Sin datos' && $item['name'] !== 'Error'):
              ?>
                <tr>
                  <td class="position"><?php echo $posicion++; ?></td>
                  <td><?php echo htmlspecialchars($item['name']); ?></td>
                  <td><?php echo formatearMoneda($item['value']); ?></td>
                  <td>
                    <?php 
                      $porcentaje = $total_efectivo > 0 ? ($item['value'] / $total_efectivo) * 100 : 0;
                      echo number_format($porcentaje, 1); 
                    ?>%
                  </td>
                </tr>
                <?php 
                  endif;
                endforeach; 
                ?>
              <?php if (empty($datosEfectivo['porEmpleado']) || $datosEfectivo['porEmpleado'][0]['name'] === 'Sin datos'): ?>
                <tr>
                  <td colspan="4" class="no-data">No hay datos disponibles para este periodo</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Botones de Exportar -->
    <div class="export-buttons">
      <button onclick="window.print()" class="btn-export">
        <i class='bx bx-printer'></i> Imprimir Reporte
      </button>
      <a href="index.php" class="btn-export outline">
        <i class='bx bx-home'></i> Nuevo Reporte
      </a>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
  <script>
    // Datos desde PHP
    const datosLitros = <?php echo json_encode($datosLitros); ?>;
    const datosEfectivo = <?php echo json_encode($datosEfectivo); ?>;
    const periodo = '<?php echo $periodo; ?>';
  </script>
  <script src="assets/js/gasolina.js"></script>
</body>
</html>