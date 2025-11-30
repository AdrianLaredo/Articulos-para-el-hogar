<?php
// modulos/reportes/reportes_comisiones.php
session_start();
require_once '../../bd/database.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Configurar locale a español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain', 'Spanish');

// Obtener parámetros
$periodo = $_GET['periodo'] ?? 'anual';
$fecha_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y') . '-01-01';
$fecha_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-d');

// Validar fechas
if (empty($fecha_inicio) || empty($fecha_fin)) {
    $fecha_inicio = date('Y') . '-01-01';
    $fecha_fin = date('Y-m-d');
}

// ✅ CONSULTA CORREGIDA: Evita duplicar total_venta por cada producto
$query_comisiones = "
    WITH ComisionesEmpleado AS (
        -- Comisiones de folios ACTIVOS (NO canceladas)
        SELECT 
            a.id_empleado,
            fv.id_folio,
            fv.id_asignacion,
            dfv.monto_comision as comision,
            fv.fecha_hora_venta,
            'activo' as tipo_comision
        FROM Detalle_Folio_Venta dfv
        INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        WHERE COALESCE(dfv.comision_cancelada, 0) = 0
        AND (fv.estado = 'activo' OR fv.estado IS NULL)
        AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
        
        UNION ALL
        
        -- Comisiones de CANCELACIONES (nuevas comisiones asignadas)
        SELECT 
            cc.id_empleado,
            cc.id_folio,
            fv.id_asignacion,
            cc.monto_comision as comision,
            cf.fecha_cancelacion as fecha_hora_venta,
            'cancelacion' as tipo_comision
        FROM Comisiones_Cancelaciones cc
        INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
        INNER JOIN Folios_Venta fv ON cc.id_folio = fv.id_folio
        WHERE DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio2 AND :fecha_fin2
    ),
    VentasPorFolio AS (
        -- Total de venta por folio (UNA VEZ, sin duplicar)
        SELECT 
            fv.id_folio,
            fv.total_venta
        FROM Folios_Venta fv
        WHERE (fv.estado = 'activo' OR fv.estado IS NULL)
        AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio3 AND :fecha_fin3
    )
    SELECT 
        e.id_empleado,
        (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_completo,
        e.nombre as nombre,
        e.apellido_paterno,
        COUNT(DISTINCT ce.id_folio) as total_folios,
        COUNT(DISTINCT ce.id_asignacion) as total_asignaciones,
        SUM(ce.comision) as total_comisiones,
        COALESCE(SUM(DISTINCT vpf.total_venta), 0) as total_ventas,
        MIN(ce.fecha_hora_venta) as primera_venta,
        MAX(ce.fecha_hora_venta) as ultima_venta
    FROM Empleados e
    INNER JOIN ComisionesEmpleado ce ON e.id_empleado = ce.id_empleado
    LEFT JOIN VentasPorFolio vpf ON ce.id_folio = vpf.id_folio
    WHERE e.estado = 'activo'
    GROUP BY e.id_empleado
    HAVING total_comisiones > 0
    ORDER BY total_comisiones DESC
";

$stmt = $conn->prepare($query_comisiones);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
$stmt->bindParam(':fecha_inicio2', $fecha_inicio);
$stmt->bindParam(':fecha_fin2', $fecha_fin);
$stmt->bindParam(':fecha_inicio3', $fecha_inicio);
$stmt->bindParam(':fecha_fin3', $fecha_fin);
$stmt->execute();

$empleados_comisiones = [];
$total_general_comisiones = 0;
$total_general_ventas = 0;
$total_general_folios = 0;
$empleado_destacado = 'N/A';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $empleados_comisiones[] = $row;
    $total_general_comisiones += $row['total_comisiones'] ?? 0;
    $total_general_ventas += $row['total_ventas'] ?? 0;
    $total_general_folios += $row['total_folios'] ?? 0;
}

// Obtener empleado con mayor comisión
if (count($empleados_comisiones) > 0) {
    $empleado_destacado = $empleados_comisiones[0]['nombre'] . ' ' . 
                         substr($empleados_comisiones[0]['apellido_paterno'], 0, 1) . '.';
}

// Preparar datos para las gráficas
$nombres_empleados = [];
$comisiones_empleados = [];

foreach ($empleados_comisiones as $emp) {
    $nombres_empleados[] = $emp['nombre'] . ' ' . substr($emp['apellido_paterno'], 0, 1) . '.';
    $comisiones_empleados[] = floatval($emp['total_comisiones']);
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
  <title>Reporte de Comisiones - <?php echo ucfirst($periodo); ?></title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/reportes_comisiones.css">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="back-button">
        <a href="index.php">← Volver a Reportes</a>
      </div>
      <h1><i class='bx bx-money'></i> Reporte de Comisiones - <?php echo ucfirst($periodo); ?></h1>
      <p>Periodo: <strong><?php echo $fecha_inicio_format; ?> - <?php echo $fecha_fin_format; ?></strong></p>
    </div>

    <!-- Resumen -->
    <div class="summary">
      <div class="chart-title"><i class='bx bx-bar-chart-alt-2'></i> Resumen del Periodo</div>
      <div class="summary-grid">
        <div class="summary-item">
          <div class="summary-icon success">
            <i class='bx bx-dollar-circle'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_comisiones, 2); ?></h3>
            <p>Total Comisiones</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon primary">
            <i class='bx bx-user-check'></i>
          </div>
          <div class="summary-content">
            <h3><?php echo count($empleados_comisiones); ?></h3>
            <p>Empleados con Comisiones</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon warning">
            <i class='bx bx-trophy'></i>
          </div>
          <div class="summary-content">
            <h3 style="font-size: 1.5rem;"><?php echo $empleado_destacado; ?></h3>
            <p>Empleado Destacado</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Gráfico de Comisiones -->
    <div class="section-with-table">
      <div class="chart-card-full">
        <div class="chart-title"><i class='bx bx-bar-chart-square'></i> Comisiones por Empleado</div>
        <div id="chart-comisiones" class="chart-container"></div>
      </div>

      <!-- Tabla de Empleados -->
      <div class="card">
        <h2><i class='bx bx-table'></i> Detalle de Comisiones</h2>
        <div class="table-responsive">
          <table class="details-table">
            <thead>
              <tr>
                <th class="position">#</th>
                <th>Empleado</th>
                <th class="text-center">Asignaciones</th>
                <th class="text-center">Folios</th>
                <th class="text-center">Total Ventas</th>
                <th class="text-center">Comisiones</th>
                <th class="text-center">Primera Venta</th>
                <th class="text-center">Última Venta</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($empleados_comisiones) > 0): ?>
                <?php foreach ($empleados_comisiones as $index => $emp): ?>
                  <tr>
                    <td class="position"><?php echo $index + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($emp['nombre_completo']); ?></strong></td>
                    <td class="text-center"><?php echo $emp['total_asignaciones']; ?></td>
                    <td class="text-center"><?php echo $emp['total_folios']; ?></td>
                    <td class="text-center">$<?php echo number_format($emp['total_ventas'], 2); ?></td>
                    <td class="text-center" style="font-weight: bold; color: #10b981; font-size: 1.1rem;">
                      $<?php echo number_format($emp['total_comisiones'], 2); ?>
                    </td>
                    <td class="text-center">
                      <small><?php echo date('d/m/Y', strtotime($emp['primera_venta'])); ?></small>
                    </td>
                    <td class="text-center">
                      <small><?php echo date('d/m/Y', strtotime($emp['ultima_venta'])); ?></small>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr style="background: rgba(16, 185, 129, 0.1); font-weight: 700;">
                  <td colspan="3" class="text-center"><strong>TOTALES:</strong></td>
                  <td class="text-center"><?php echo $total_general_folios; ?></td>
                  <td class="text-center">$<?php echo number_format($total_general_ventas, 2); ?></td>
                  <td class="text-center" style="color: #10b981; font-size: 1.2rem;">
                    $<?php echo number_format($total_general_comisiones, 2); ?>
                  </td>
                  <td colspan="2"></td>
                </tr>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="text-center" style="padding: 30px; color: #9ca3af;">
                    <i class='bx bx-info-circle' style="font-size: 2rem;"></i><br>
                    No se encontraron comisiones en el periodo seleccionado
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Botones de Exportar -->
    <div class="export-buttons">
      <button onclick="imprimirReporte()" class="btn-export">
        <i class='bx bx-printer'></i> Imprimir
      </button>
      <a href="index.php" class="btn-export outline">
        <i class='bx bx-home'></i> Nuevo Reporte
      </a>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
  <script>
    // Datos desde PHP
    const nombresEmpleados = <?php echo json_encode($nombres_empleados); ?>;
    const comisionesEmpleados = <?php echo json_encode($comisiones_empleados); ?>;
    const totalEmpleados = <?php echo count($empleados_comisiones); ?>;

    let chartComisiones = null;

    document.addEventListener('DOMContentLoaded', function() {
      if (totalEmpleados > 0) {
        initCharts();
      } else {
        mostrarMensajeSinDatos();
      }
    });

    /**
     * Inicializar gráficos
     */
    function initCharts() {
      chartComisiones = echarts.init(document.getElementById('chart-comisiones'), null, {
        renderer: 'canvas',
        useDirtyRect: false
      });

      const option = {
        title: {
          text: 'Comisiones Generadas',
          left: 'center',
          top: 10,
          textStyle: {
            fontSize: 16,
            fontWeight: 'bold',
            color: '#1e1f26'
          }
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          formatter: function(params) {
            return params[0].name + '<br/>' +
                   '<strong>Comisión:</strong> $' + params[0].value.toLocaleString('es-MX', {minimumFractionDigits: 2});
          }
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '8%',
          top: '15%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: nombresEmpleados,
          axisLabel: {
            rotate: 45,
            interval: 0,
            fontSize: 11,
            color: '#64748b'
          },
          axisTick: {
            alignWithLabel: true
          }
        },
        yAxis: {
          type: 'value',
          name: 'Comisión ($)',
          nameTextStyle: {
            color: '#64748b',
            fontSize: 12
          },
          axisLabel: {
            formatter: '${value}',
            color: '#64748b'
          }
        },
        series: [
          {
            name: 'Comisión',
            data: comisionesEmpleados,
            type: 'bar',
            barWidth: '60%',
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: '#10b981' },
                { offset: 1, color: '#059669' }
              ]),
              borderRadius: [4, 4, 0, 0]
            },
            emphasis: {
              itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                  { offset: 0, color: '#34d399' },
                  { offset: 1, color: '#10b981' }
                ])
              }
            },
            label: {
              show: true,
              position: 'top',
              formatter: function(params) {
                return '$' + params.value.toLocaleString('es-MX', {minimumFractionDigits: 0});
              },
              fontSize: 11,
              fontWeight: 'bold',
              color: '#065f46'
            }
          }
        ]
      };

      chartComisiones.setOption(option);

      // Responsive
      window.addEventListener('resize', () => chartComisiones.resize());

      // Eventos de impresión
      window.addEventListener('beforeprint', prepararImpresion);
      window.addEventListener('afterprint', restaurarDespuesImpresion);
    }

    /**
     * Preparar gráficas para impresión
     */
    function prepararImpresion() {
      setTimeout(function() {
        if (chartComisiones) {
          chartComisiones.resize({
            width: 700,
            height: 280
          });
        }
      }, 100);
    }

    /**
     * Restaurar gráficas después de imprimir
     */
    function restaurarDespuesImpresion() {
      setTimeout(function() {
        if (chartComisiones) chartComisiones.resize();
      }, 100);
    }

    /**
     * Mostrar mensaje cuando no hay datos
     */
    function mostrarMensajeSinDatos() {
      const container = document.getElementById('chart-comisiones');
      container.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #9ca3af;">
          <i class='bx bx-data' style="font-size: 4rem; margin-bottom: 1rem;"></i>
          <p style="font-size: 1.1rem;">No hay datos de comisiones en este periodo</p>
          <p style="font-size: 0.9rem; margin-top: 0.5rem;">Intenta seleccionar otro periodo</p>
        </div>
      `;
    }

    /**
     * Imprimir reporte
     */
    function imprimirReporte() {
      // Guardar dimensiones originales
      const originalDimensions = chartComisiones ? {
        width: document.getElementById('chart-comisiones').offsetWidth,
        height: document.getElementById('chart-comisiones').offsetHeight
      } : null;

      // Redimensionar gráfica para impresión
      const printWidth = 650;
      const printHeight = 300;

      if (chartComisiones) {
        chartComisiones.resize({
          width: printWidth,
          height: printHeight
        });
      }

      // Esperar a que ECharts termine de renderizar
      setTimeout(() => {
        if (chartComisiones) {
          chartComisiones.setOption(chartComisiones.getOption(), { notMerge: false, lazyUpdate: false });
        }

        setTimeout(() => {
          window.print();

          // Restaurar dimensiones originales después de imprimir
          setTimeout(() => {
            if (chartComisiones && originalDimensions) {
              chartComisiones.resize({
                width: originalDimensions.width,
                height: originalDimensions.height
              });

              setTimeout(() => {
                if (chartComisiones) chartComisiones.setOption(chartComisiones.getOption());
              }, 100);
            }
          }, 500);
        }, 300);
      }, 200);
    }
  </script>
</body>
</html>