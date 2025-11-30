<?php
// modulos/reportes/reporte_cobradores.php
session_start();
require_once '../../bd/database.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain', 'Spanish');

$periodo = $_GET['periodo'] ?? 'semanal';
$fecha_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-d', strtotime('last sunday'));
$fecha_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-d', strtotime('last friday'));

if (empty($fecha_inicio) || empty($fecha_fin)) {
    $fecha_inicio = date('Y-m-d', strtotime('last sunday'));
    $fecha_fin = date('Y-m-d', strtotime('last friday'));
}

// ✅ CONSULTA CORREGIDA: Ahora coincide EXACTAMENTE con el reporte de nómina
$query_empleados = "
    WITH 
    CobrosEmpleado AS (
        -- Datos de cobros diarios (para estadísticas)
        SELECT 
            e.id_empleado,
            COUNT(DISTINCT cd.id_cobro) as total_cobros,
            COALESCE(SUM(cd.monto_cobrado), 0) as total_cobrado,
            COALESCE(SUM(cd.clientes_visitados), 0) as total_clientes,
            MIN(cd.fecha) as primera_fecha,
            MAX(cd.fecha) as ultima_fecha
        FROM Empleados e
        LEFT JOIN Cobros_Diarios cd ON e.id_empleado = cd.id_empleado
            AND DATE(cd.fecha) BETWEEN :fecha_inicio AND :fecha_fin
        WHERE e.estado = 'activo'
        GROUP BY e.id_empleado
    ),
    ComisionesCobradores AS (
        -- ✅ Comisiones calculadas en Comisiones_Cobradores (igual que nómina)
        SELECT 
            cc.id_empleado,
            SUM(cc.comision_cobro) as total_comision_cobros,
            SUM(cc.total_cobros) as total_cobrado_comisiones,
            SUM(cc.total_gasolina) as total_gasolina,
            SUM(cc.prestamo) as total_prestamo,
            SUM(COALESCE(cc.prestamo_inhabilitado, 0)) as total_prestamo_inhabilitado,
            SUM(COALESCE(cc.total_extras, 0)) as total_extras
        FROM Comisiones_Cobradores cc
        INNER JOIN Semanas_Cobro sc ON cc.id_semana = sc.id_semana
        WHERE sc.fecha_inicio >= :fecha_inicio2
        AND sc.fecha_fin <= :fecha_fin2
        GROUP BY cc.id_empleado
    )
    
    SELECT 
        e.id_empleado,
        (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_completo,
        e.nombre as nombre,
        e.apellido_paterno,
        e.zona,
        e.rol,
        COALESCE(ce.total_cobros, 0) as total_cobros,
        COALESCE(ce.total_cobrado, 0) as total_cobrado,
        COALESCE(ce.total_clientes, 0) as total_clientes,
        ce.primera_fecha,
        ce.ultima_fecha,
        -- ✅ Datos de comisiones (igual que en nómina)
        COALESCE(cob.total_comision_cobros, 0) as total_comision,
        COALESCE(cob.total_gasolina, 0) as total_gasolina,
        COALESCE(cob.total_prestamo, 0) as total_prestamo,
        COALESCE(cob.total_prestamo_inhabilitado, 0) as prestamo_inhabilitado,
        COALESCE(cob.total_extras, 0) as total_extras,
        -- ✅ Total a pagar calculado igual que en nómina
        (
            COALESCE(cob.total_comision_cobros, 0) +
            COALESCE(cob.total_gasolina, 0) +
            COALESCE(cob.total_extras, 0) +
            COALESCE(cob.total_prestamo_inhabilitado, 0) -
            COALESCE(cob.total_prestamo, 0)
        ) as total_pagar
    FROM Empleados e
    LEFT JOIN CobrosEmpleado ce ON e.id_empleado = ce.id_empleado
    LEFT JOIN ComisionesCobradores cob ON e.id_empleado = cob.id_empleado
    WHERE e.estado = 'activo'
    AND (ce.total_cobrado > 0 OR cob.total_comision_cobros > 0)
    ORDER BY total_pagar DESC
";

$stmt_empleados = $conn->prepare($query_empleados);
$stmt_empleados->bindParam(':fecha_inicio', $fecha_inicio);
$stmt_empleados->bindParam(':fecha_fin', $fecha_fin);
$stmt_empleados->bindParam(':fecha_inicio2', $fecha_inicio);
$stmt_empleados->bindParam(':fecha_fin2', $fecha_fin);
$stmt_empleados->execute();

$empleados_cobros = [];
$total_general_cobrado = 0;
$total_general_clientes = 0;
$total_general_comision = 0;
$total_general_gasolina = 0;
$total_general_prestamo = 0;
$total_general_prestamo_inhab = 0;
$total_general_extras = 0;
$total_general_pagar = 0;
$empleado_destacado = 'N/A';

while ($row = $stmt_empleados->fetch(PDO::FETCH_ASSOC)) {
    $empleados_cobros[] = $row;
    $total_general_cobrado += $row['total_cobrado'] ?? 0;
    $total_general_clientes += $row['total_clientes'] ?? 0;
    $total_general_comision += $row['total_comision'] ?? 0;
    $total_general_gasolina += $row['total_gasolina'] ?? 0;
    $total_general_prestamo += $row['total_prestamo'] ?? 0;
    $total_general_prestamo_inhab += $row['prestamo_inhabilitado'] ?? 0;
    $total_general_extras += $row['total_extras'] ?? 0;
    $total_general_pagar += $row['total_pagar'] ?? 0;
}

if (count($empleados_cobros) > 0) {
    $empleado_destacado = trim($empleados_cobros[0]['nombre_completo']);
}

// Obtener cobros por zona
$query_zonas = "
    SELECT 
        e.zona,
        COUNT(cd.id_cobro) as total_cobros,
        COALESCE(SUM(cd.monto_cobrado), 0) as total_cobrado,
        COALESCE(SUM(cd.clientes_visitados), 0) as total_clientes
    FROM Empleados e
    INNER JOIN Cobros_Diarios cd ON e.id_empleado = cd.id_empleado
    WHERE DATE(cd.fecha) BETWEEN :fecha_inicio AND :fecha_fin
        AND e.estado = 'activo'
    GROUP BY e.zona
    ORDER BY total_cobrado DESC
";

$stmt_zonas = $conn->prepare($query_zonas);
$stmt_zonas->bindParam(':fecha_inicio', $fecha_inicio);
$stmt_zonas->bindParam(':fecha_fin', $fecha_fin);
$stmt_zonas->execute();

$cobros_por_zona = [];
while ($row = $stmt_zonas->fetch(PDO::FETCH_ASSOC)) {
    $cobros_por_zona[] = $row;
}

// Preparar datos para las gráficas
$nombres_empleados = [];
$montos_empleados = [];

foreach ($empleados_cobros as $emp) {
    $nombres_empleados[] = $emp['nombre'] . ' ' . substr($emp['apellido_paterno'], 0, 1) . '.';
    $montos_empleados[] = floatval($emp['total_cobrado']);
}

$zonas_nombres = [];
$zonas_montos = [];

foreach ($cobros_por_zona as $zona) {
    $zonas_nombres[] = $zona['zona'];
    $zonas_montos[] = floatval($zona['total_cobrado']);
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
  <title>Reporte de Cobradores - <?php echo ucfirst($periodo); ?></title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/reportes_cobradores.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="back-button">
        <a href="index.php">← Volver a Reportes</a>
      </div>
      <h1><i class='bx bx-wallet'></i> Reporte de Cobros - <?php echo ucfirst($periodo); ?></h1>
      <p>Periodo: <strong><?php echo $fecha_inicio_format; ?> - <?php echo $fecha_fin_format; ?></strong></p>
    </div>

    <!-- Resumen ACTUALIZADO -->
    <div class="summary">
      <div class="chart-title"><i class='bx bx-bar-chart-alt-2'></i> Resumen del Periodo</div>
      <div class="summary-grid">
        <div class="summary-item">
          <div class="summary-icon success">
            <i class='bx bx-dollar-circle'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_cobrado, 2); ?></h3>
            <p>Total Cobrado</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon warning">
            <i class='bx bx-trending-up'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_comision, 2); ?></h3>
            <p>Comisión</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class='bx bxs-gas-pump'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_gasolina, 2); ?></h3>
            <p>Total Gasolina</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
            <i class='bx bx-gift'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_extras, 2); ?></h3>
            <p>Extras</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class='bx bx-wallet'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_prestamo, 2); ?></h3>
            <p>Total Préstamos</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class='bx bx-check-circle'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_prestamo_inhab, 2); ?></h3>
            <p>Prést. Inhabilitados</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class='bx bx-money'></i>
          </div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_pagar, 2); ?></h3>
            <p>Total a Pagar</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon primary">
            <i class='bx bx-group'></i>
          </div>
          <div class="summary-content">
            <h3><?php echo number_format($total_general_clientes); ?></h3>
            <p>Clientes Visitados</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
            <i class='bx bx-trophy'></i>
          </div>
          <div class="summary-content">
            <h3 style="font-size: 1.3rem;"><?php echo $empleado_destacado; ?></h3>
            <p>Empleado Destacado</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
      <div class="chart-card-full">
        <div class="chart-title"><i class='bx bx-bar-chart-square'></i> Cobros por Empleado</div>
        <div id="chart-empleados" class="chart-container"></div>
      </div>

      <div class="chart-card-full">
        <div class="chart-title"><i class='bx bx-map'></i> Cobros por Zona</div>
        <div id="chart-zonas" class="chart-container"></div>
      </div>
    </div>

    <!-- Tabla de Empleados ACTUALIZADA -->
    <div class="section-with-table">
      <div class="card">
        <h2><i class='bx bx-table'></i> Detalle de Empleados</h2>
        <div class="table-responsive">
          <table class="details-table">
            <thead>
              <tr>
                <th class="position">#</th>
                <th>Empleado</th>
                <th class="text-center">Zona</th>
                <th class="text-center">Cobros</th>
                <th class="text-center">Clientes</th>
                <th class="text-center">Total Cobrado</th>
                <th class="text-center">Comisión</th>
                <th class="text-center">Gasolina</th>
                <th class="text-center">Extras</th>
                <th class="text-center">Préstamo</th>
                <th class="text-center">Prést. Inhab.</th>
                <th class="text-center">Total a Pagar</th>
                <th class="text-center">Primera Fecha</th>
                <th class="text-center">Última Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($empleados_cobros) > 0): ?>
                <?php foreach ($empleados_cobros as $index => $emp): ?>
                  <tr>
                    <td class="position"><?php echo $index + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($emp['nombre_completo']); ?></strong></td>
                    <td class="text-center">
                      <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; background: #FFF3E0; color: #F57C00; font-size: 11px; font-weight: 600;">
                        <?php echo $emp['zona']; ?>
                      </span>
                    </td>
                    <td class="text-center"><?php echo $emp['total_cobros']; ?></td>
                    <td class="text-center"><?php echo $emp['total_clientes']; ?></td>
                    <td class="text-center" style="font-weight: bold; color: #10b981;">
                      $<?php echo number_format($emp['total_cobrado'], 2); ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #10b981;">
                      $<?php echo number_format($emp['total_comision'], 2); ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #f59e0b;">
                      $<?php echo number_format($emp['total_gasolina'], 2); ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #06b6d4;">
                      $<?php echo number_format($emp['total_extras'], 2); ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #ef4444;">
                      <?php if ($emp['total_prestamo'] > 0): ?>
                        -$<?php echo number_format($emp['total_prestamo'], 2); ?>
                      <?php else: ?>
                        $0.00
                      <?php endif; ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #10b981;">
                      <?php if ($emp['prestamo_inhabilitado'] > 0): ?>
                        +$<?php echo number_format($emp['prestamo_inhabilitado'], 2); ?>
                      <?php else: ?>
                        $0.00
                      <?php endif; ?>
                    </td>
                    <td class="text-center" style="font-weight: bold; color: #3b82f6; font-size: 1.1rem;">
                      $<?php echo number_format($emp['total_pagar'], 2); ?>
                    </td>
                    <td class="text-center">
                      <small><?php echo $emp['primera_fecha'] ? date('d/m/Y', strtotime($emp['primera_fecha'])) : '-'; ?></small>
                    </td>
                    <td class="text-center">
                      <small><?php echo $emp['ultima_fecha'] ? date('d/m/Y', strtotime($emp['ultima_fecha'])) : '-'; ?></small>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr style="background: rgba(59, 130, 246, 0.1); font-weight: 700;">
                  <td colspan="4" class="text-center"><strong>TOTALES:</strong></td>
                  <td class="text-center"><?php echo $total_general_clientes; ?></td>
                  <td class="text-center" style="color: #10b981; font-size: 1.1rem;">
                    $<?php echo number_format($total_general_cobrado, 2); ?>
                  </td>
                  <td class="text-center" style="color: #10b981; font-size: 1.1rem;">
                    $<?php echo number_format($total_general_comision, 2); ?>
                  </td>
                  <td class="text-center" style="color: #f59e0b; font-size: 1.1rem;">
                    $<?php echo number_format($total_general_gasolina, 2); ?>
                  </td>
                  <td class="text-center" style="color: #06b6d4; font-size: 1.1rem;">
                    $<?php echo number_format($total_general_extras, 2); ?>
                  </td>
                  <td class="text-center" style="color: #ef4444; font-size: 1.1rem;">
                    -$<?php echo number_format($total_general_prestamo, 2); ?>
                  </td>
                  <td class="text-center" style="color: #10b981; font-size: 1.1rem;">
                    +$<?php echo number_format($total_general_prestamo_inhab, 2); ?>
                  </td>
                  <td class="text-center" style="color: #3b82f6; font-size: 1.2rem;">
                    $<?php echo number_format($total_general_pagar, 2); ?>
                  </td>
                  <td colspan="2"></td>
                </tr>
              <?php else: ?>
                <tr>
                  <td colspan="14" class="no-data">
                    <i class='bx bx-data' style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                    No hay cobros en el periodo seleccionado
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

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
    const nombresEmpleados = <?php echo json_encode($nombres_empleados); ?>;
    const montosEmpleados = <?php echo json_encode($montos_empleados); ?>;
    const zonasNombres = <?php echo json_encode($zonas_nombres); ?>;
    const zonasMontos = <?php echo json_encode($zonas_montos); ?>;
    const totalEmpleados = <?php echo count($empleados_cobros); ?>;

    let chartEmpleados = null;
    let chartZonas = null;
    let isPrinting = false;

    document.addEventListener('DOMContentLoaded', function() {
      if (totalEmpleados > 0) {
        initCharts();
      } else {
        mostrarMensajeSinDatos();
      }
    });

    function initCharts() {
      chartEmpleados = echarts.init(document.getElementById('chart-empleados'), null, {
        renderer: 'canvas',
        useDirtyRect: false
      });

      const optionEmpleados = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          formatter: function(params) {
            return params[0].name + '<br/>' +
                   '<strong>Cobrado:</strong> $' + params[0].value.toLocaleString('es-MX', {minimumFractionDigits: 2});
          }
        },
        grid: {
          left: '15%',
          right: '4%',
          bottom: '15%',
          top: '12%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: nombresEmpleados,
          axisLabel: {
            rotate: 45,
            interval: 0,
            fontSize: 10,
            color: '#64748b'
          },
          axisTick: {
            alignWithLabel: true
          }
        },
        yAxis: {
          type: 'value',
          name: 'Cobrado ($)',
          nameTextStyle: {
            color: '#64748b',
            fontSize: 11,
            padding: [0, 0, 0, 10]
          },
          axisLabel: {
            formatter: '${value}',
            color: '#64748b',
            fontSize: 10
          }
        },
        series: [
          {
            name: 'Cobrado',
            data: montosEmpleados,
            type: 'bar',
            barWidth: '60%',
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: '#0c3c78' },
                { offset: 1, color: '#1e5799' }
              ]),
              borderRadius: [4, 4, 0, 0]
            },
            emphasis: {
              itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                  { offset: 0, color: '#10b981' },
                  { offset: 1, color: '#059669' }
                ])
              }
            },
            label: {
              show: true,
              position: 'top',
              formatter: function(params) {
                return '$' + params.value.toLocaleString('es-MX', {minimumFractionDigits: 0});
              },
              fontSize: 10,
              fontWeight: 'bold',
              color: '#0c3c78'
            }
          }
        ]
      };

      chartEmpleados.setOption(optionEmpleados);

      chartZonas = echarts.init(document.getElementById('chart-zonas'), null, {
        renderer: 'canvas',
        useDirtyRect: false
      });

      const optionZonas = {
        tooltip: {
          trigger: 'item',
          formatter: function(params) {
            return params.name + '<br/>' +
                   '<strong>Cobrado:</strong> $' + params.value.toLocaleString('es-MX', {minimumFractionDigits: 2}) + '<br/>' +
                   '<strong>Porcentaje:</strong> ' + params.percent.toFixed(1) + '%';
          }
        },
        legend: {
          orient: 'vertical',
          left: 'left',
          textStyle: {
            fontSize: 11
          }
        },
        series: [
          {
            name: 'Zona',
            type: 'pie',
            radius: ['40%', '70%'],
            center: ['50%', '50%'],
            avoidLabelOverlap: false,
            itemStyle: {
              borderRadius: 10,
              borderColor: '#fff',
              borderWidth: 2
            },
            label: {
              show: true,
              fontSize: 11,
              formatter: function(params) {
                return params.name + '\n$' + params.value.toLocaleString('es-MX', {minimumFractionDigits: 0});
              }
            },
            emphasis: {
              label: {
                show: true,
                fontSize: 13,
                fontWeight: 'bold'
              }
            },
            data: zonasNombres.map((zona, index) => ({
              name: zona,
              value: zonasMontos[index]
            }))
          }
        ]
      };

      chartZonas.setOption(optionZonas);

      window.addEventListener('resize', function() {
        if (chartEmpleados && !isPrinting) chartEmpleados.resize();
        if (chartZonas && !isPrinting) chartZonas.resize();
      });

      window.addEventListener('beforeprint', prepararImpresion);
      window.addEventListener('afterprint', restaurarDespuesImpresion);
    }

    function prepararImpresion() {
      isPrinting = true;
      
      setTimeout(function() {
        if (chartEmpleados) {
          const optionEmp = chartEmpleados.getOption();
          optionEmp.grid[0].left = '16%';
          optionEmp.grid[0].top = '12%';
          optionEmp.grid[0].bottom = '16%';
          chartEmpleados.setOption(optionEmp);
          
          chartEmpleados.resize({
            width: 500,
            height: 320
          });
        }
        
        if (chartZonas) {
          const optionZon = chartZonas.getOption();
          optionZon.series[0].center = ['50%', '50%'];
          optionZon.series[0].radius = ['32%', '60%'];
          optionZon.legend[0].left = '5%';
          chartZonas.setOption(optionZon);
          
          chartZonas.resize({
            width: 340,
            height: 280
          });
        }
      }, 100);
    }

    function restaurarDespuesImpresion() {
      setTimeout(function() {
        if (chartEmpleados) {
          const optionEmp = chartEmpleados.getOption();
          optionEmp.grid[0].left = '15%';
          optionEmp.grid[0].top = '12%';
          optionEmp.grid[0].bottom = '15%';
          chartEmpleados.setOption(optionEmp);
          chartEmpleados.resize();
        }
        
        if (chartZonas) {
          const optionZon = chartZonas.getOption();
          optionZon.series[0].center = ['50%', '50%'];
          optionZon.series[0].radius = ['40%', '70%'];
          optionZon.legend[0].left = 'left';
          chartZonas.setOption(optionZon);
          chartZonas.resize();
        }
        
        isPrinting = false;
      }, 100);
    }

    function mostrarMensajeSinDatos() {
      const containers = document.querySelectorAll('.chart-container');
      containers.forEach(container => {
        container.innerHTML = `
          <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #9ca3af;">
            <i class='bx bx-data' style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <p style="font-size: 1.1rem;">No hay datos de cobros en este periodo</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Intenta seleccionar otro periodo</p>
          </div>
        `;
      });
    }

    function imprimirReporte() {
      isPrinting = true;
      
      const originalDimensionsEmp = chartEmpleados ? {
        width: document.getElementById('chart-empleados').offsetWidth,
        height: document.getElementById('chart-empleados').offsetHeight
      } : null;

      const originalDimensionsZon = chartZonas ? {
        width: document.getElementById('chart-zonas').offsetWidth,
        height: document.getElementById('chart-zonas').offsetHeight
      } : null;

      const printWidthEmp = 500;
      const printHeightEmp = 320;
      const printWidthZon = 340;
      const printHeightZon = 280;

      if (chartEmpleados) {
        const optionEmp = chartEmpleados.getOption();
        optionEmp.grid[0].left = '16%';
        optionEmp.grid[0].top = '12%';
        optionEmp.grid[0].bottom = '16%';
        chartEmpleados.setOption(optionEmp);
        chartEmpleados.resize({ width: printWidthEmp, height: printHeightEmp });
      }
      
      if (chartZonas) {
        const optionZon = chartZonas.getOption();
        optionZon.series[0].center = ['50%', '50%'];
        optionZon.series[0].radius = ['32%', '60%'];
        optionZon.legend[0].left = '5%';
        chartZonas.setOption(optionZon);
        chartZonas.resize({ width: printWidthZon, height: printHeightZon });
      }

      setTimeout(() => {
        if (chartEmpleados) {
          chartEmpleados.setOption(chartEmpleados.getOption(), { notMerge: false, lazyUpdate: false });
        }
        if (chartZonas) {
          chartZonas.setOption(chartZonas.getOption(), { notMerge: false, lazyUpdate: false });
        }

        setTimeout(() => {
          window.print();

          setTimeout(() => {
            if (chartEmpleados && originalDimensionsEmp) {
              const optionEmp = chartEmpleados.getOption();
              optionEmp.grid[0].left = '15%';
              optionEmp.grid[0].top = '12%';
              optionEmp.grid[0].bottom = '15%';
              chartEmpleados.setOption(optionEmp);
              chartEmpleados.resize({
                width: originalDimensionsEmp.width,
                height: originalDimensionsEmp.height
              });
              setTimeout(() => {
                if (chartEmpleados) chartEmpleados.setOption(chartEmpleados.getOption());
              }, 100);
            }
            
            if (chartZonas && originalDimensionsZon) {
              const optionZon = chartZonas.getOption();
              optionZon.series[0].center = ['50%', '50%'];
              optionZon.series[0].radius = ['40%', '70%'];
              optionZon.legend[0].left = 'left';
              chartZonas.setOption(optionZon);
              chartZonas.resize({
                width: originalDimensionsZon.width,
                height: originalDimensionsZon.height
              });
              setTimeout(() => {
                if (chartZonas) chartZonas.setOption(chartZonas.getOption());
              }, 100);
            }
            
            isPrinting = false;
          }, 500);
        }, 300);
      }, 200);
    }
  </script>
</body>
</html>