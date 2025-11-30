<?php
// modulos/reporte_semanal/reporte_ventas.php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Configurar locale a español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain', 'Spanish');

// Obtener parámetros
$periodo = $_GET['periodo'] ?? 'semanal';
$fecha_inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('monday this week'));
$fecha_fin = $_GET['fin'] ?? date('Y-m-d', strtotime('sunday this week'));

// Obtener datos de ventas
$datosVentas = obtenerDatosVentas($fecha_inicio, $fecha_fin, $periodo);

// Si es anual, obtener desglose por mes
$foliosPorMes = null;
if ($periodo === 'anual') {
    $foliosPorMes = obtenerFoliosPorEmpleadoPorMes($fecha_inicio, $fecha_fin);
}

// Formatear fechas en español
$meses_es = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$fecha_inicio_obj = new DateTime($fecha_inicio);
$fecha_fin_obj = new DateTime($fecha_fin);

$fecha_inicio_format = $fecha_inicio_obj->format('d') . ' ' . 
                      $meses_es[(int)$fecha_inicio_obj->format('n')] . ' ' . 
                      $fecha_inicio_obj->format('Y');
                      
$fecha_fin_format = $fecha_fin_obj->format('d') . ' ' . 
                   $meses_es[(int)$fecha_fin_obj->format('n')] . ' ' . 
                   $fecha_fin_obj->format('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte de Ventas - <?php echo ucfirst($periodo); ?></title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/ventas.css">
  <style>
    /* Estilos adicionales para tabla con meses */
    .tabla-meses {
      overflow-x: auto;
    }
    
    .tabla-meses table {
      min-width: 100%;
      font-size: 0.85rem;
    }
    
    .tabla-meses th.mes-col {
      background: linear-gradient(135deg, #1e5799, #0c3c78);
      color: white;
      text-align: center;
      font-size: 0.8rem;
      padding: 10px 8px;
      white-space: nowrap;
    }
    
    .tabla-meses td.mes-val {
      text-align: center;
      font-weight: 600;
      color: #0c3c78;
      background: #f8f9fa;
    }
    
    .tabla-meses td.mes-val.zero {
      color: #cbd5e1;
      font-weight: 400;
    }
    
    .tabla-meses td.total-col {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      font-weight: 700;
      color: #065f46;
      text-align: center;
      font-size: 1.1rem;
    }
    
    .tabla-meses th.total-header {
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
    }
    
    @media print {
      .tabla-meses {
        page-break-inside: avoid;
      }
      
      .tabla-meses table {
        font-size: 0.7rem;
      }
      
      .tabla-meses th,
      .tabla-meses td {
        padding: 6px 4px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="back-button">
        <a href="index.php">← Volver a Reportes</a>
      </div>
      <h1>Reporte de Ventas - <?php echo ucfirst($periodo); ?></h1>
      <p>Periodo: <strong><?php echo $fecha_inicio_format; ?> - <?php echo $fecha_fin_format; ?></strong></p>
    </div>
      <!-- Resumen -->
<div class="summary">
  <div class="chart-title">Resumen del Periodo</div>
  <div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">
    <div class="summary-item" style="padding: 18px 15px;">
      <h3 style="font-size: 0.8rem;">Total Folios</h3>
      <p id="total-folios" style="font-size: 1.8rem;"><?php echo $datosVentas['resumen']['total_folios']; ?></p>
    </div>
    <div class="summary-item" style="padding: 18px 15px;">
      <h3 style="font-size: 0.8rem;">Enganches</h3>
      <p id="total-enganches" style="font-size: 1.8rem;">$<?php echo number_format($datosVentas['resumen']['total_enganches'], 2); ?></p>
    </div>
    <div class="summary-item" style="padding: 18px 15px;">
      <h3 style="font-size: 0.8rem;">Saldo Pendiente</h3>
      <p id="saldo-pendiente" style="font-size: 1.8rem;">$<?php echo number_format($datosVentas['resumen']['total_saldo_pendiente'], 2); ?></p>
    </div>
    <div class="summary-item" style="padding: 18px 15px;">
      <h3 style="font-size: 0.8rem;">Total Ventas</h3>
      <p id="total-ventas" style="font-size: 1.8rem;">$<?php echo number_format($datosVentas['resumen']['total_general'], 2); ?></p>
    </div>
    <div class="summary-item" style="padding: 18px 15px;">
      <h3 style="font-size: 0.8rem;">Vendedor Destacado</h3>
      <p id="vendedor-top" style="font-size: 1.3rem;"><?php echo $datosVentas['resumen']['vendedor_top']; ?></p>
    </div>
  </div>
</div>
    <!-- Gráficos (VERTICALES) -->
    <div class="charts-grid">
      <!-- Gráfico de Folios por Periodo -->
      <div class="chart-card">
        <div class="chart-title">Folios Generados por Periodo</div>
        <div id="chart-periodo" class="chart-container"></div>
      </div>

      <!-- Gráfico de Top Empleados -->
      <div class="chart-card">
        <div class="chart-title">Ranking de Empleados por Folios</div>
        <div id="chart-empleados" class="chart-container"></div>
      </div>
    </div>



    <!-- Tabla de Empleados -->
    <div class="summary">
      <div class="chart-title">Detalle de Empleados</div>
      
      <?php if ($periodo === 'anual' && $foliosPorMes): ?>
        <!-- TABLA CON COLUMNAS POR MES -->
        <div class="tabla-productos tabla-meses">
          <table>
            <thead>
              <tr>
                <th style="position: sticky; left: 0; background: linear-gradient(90deg, #0c3c78, #1e5799); z-index: 10;">#</th>
                <th style="position: sticky; left: 40px; background: linear-gradient(90deg, #0c3c78, #1e5799); z-index: 10;">Empleado</th>
                <?php 
                foreach ($foliosPorMes['meses'] as $mes):
                  $fecha_mes = DateTime::createFromFormat('Y-m', $mes);
                  $mes_nombre = $meses_es[(int)$fecha_mes->format('n')];
                ?>
                  <th class="mes-col"><?php echo $mes_nombre; ?></th>
                <?php endforeach; ?>
                <th class="total-header">TOTAL</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($foliosPorMes['empleados']) > 0): ?>
                <?php foreach ($foliosPorMes['empleados'] as $index => $emp): ?>
                  <tr>
                    <td class="text-center" style="position: sticky; left: 0; background: white; font-weight: 700;">
                      <?php echo $index + 1; ?>
                    </td>
                    <td style="position: sticky; left: 40px; background: white;">
                      <?php echo htmlspecialchars($emp['nombre_empleado']); ?>
                    </td>
                    <?php foreach ($foliosPorMes['meses'] as $mes): ?>
                      <td class="mes-val <?php echo $emp['meses'][$mes] == 0 ? 'zero' : ''; ?>">
                        <?php echo $emp['meses'][$mes]; ?>
                      </td>
                    <?php endforeach; ?>
                    <td class="total-col">
                      <?php echo $emp['total']; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="<?php echo count($foliosPorMes['meses']) + 3; ?>" class="text-center" style="color: #9ca3af; padding: 40px;">
                    No hay datos de ventas en este periodo
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <!-- TABLA SIMPLE (NO ANUAL) -->
        <div class="tabla-productos">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Empleado</th>
                <th class="text-center">Folios Generados</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($datosVentas['porEmpleado']) > 0): ?>
                <?php foreach ($datosVentas['porEmpleado'] as $index => $emp): ?>
                  <tr>
                    <td class="text-center"><strong><?php echo $index + 1; ?></strong></td>
                    <td><?php echo htmlspecialchars($emp['name']); ?></td>
                    <td class="text-center" style="font-weight: bold; color: #0c3c78; font-size: 1.1rem;">
                      <?php echo $emp['value']; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-center" style="color: #9ca3af; padding: 40px;">
                    No hay datos de ventas en este periodo
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Botón de Exportar -->
    <div class="export-buttons">
      <button onclick="imprimirReporte()" class="btn-export">🖨️ Imprimir</button>
      <a href="index.php" class="btn-export outline">
        <i class='bx bx-home'></i> Nuevo Reporte
      </a>
    </div>
    
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
  <script>
    // Datos desde PHP
    const datosVentas = <?php echo json_encode($datosVentas); ?>;
    const periodo = '<?php echo $periodo; ?>';
  </script>
  <script src="assets/js/ventas.js"></script>
  <script>
    function imprimirReporte() {
    // Guardar dimensiones originales
    const originalDimensions = {
        periodo: {
            width: document.getElementById('chart-periodo').offsetWidth,
            height: document.getElementById('chart-periodo').offsetHeight
        },
        empleados: {
            width: document.getElementById('chart-empleados').offsetWidth,
            height: document.getElementById('chart-empleados').offsetHeight
        }
    };

    // Redimensionar gráficas para impresión
    const printWidth = 650; // Ancho fijo para impresión
    const printHeight = 300; // Alto fijo para impresión

    if (chartPeriodo) {
        chartPeriodo.resize({
            width: printWidth,
            height: printHeight
        });
    }

    if (chartEmpleados) {
        chartEmpleados.resize({
            width: printWidth,
            height: printHeight
        });
    }

    // Esperar a que ECharts termine de renderizar
    setTimeout(() => {
        // Forzar un nuevo renderizado
        if (chartPeriodo) {
            chartPeriodo.setOption(chartPeriodo.getOption(), { notMerge: false, lazyUpdate: false });
        }
        if (chartEmpleados) {
            chartEmpleados.setOption(chartEmpleados.getOption(), { notMerge: false, lazyUpdate: false });
        }

        // Esperar un poco más para asegurar el renderizado
        setTimeout(() => {
            window.print();

            // Restaurar dimensiones originales después de imprimir
            setTimeout(() => {
                if (chartPeriodo) {
                    chartPeriodo.resize({
                        width: originalDimensions.periodo.width,
                        height: originalDimensions.periodo.height
                    });
                }
                if (chartEmpleados) {
                    chartEmpleados.resize({
                        width: originalDimensions.empleados.width,
                        height: originalDimensions.empleados.height
                    });
                }

                // Re-renderizar con las dimensiones originales
                setTimeout(() => {
                    if (chartPeriodo) chartPeriodo.setOption(chartPeriodo.getOption());
                    if (chartEmpleados) chartEmpleados.setOption(chartEmpleados.getOption());
                }, 100);
            }, 500);
        }, 300);
    }, 200);
}
</script>
</body>
</html>