<?php
// modulos/reportes/reporte_nomina.php
session_start();
require_once '../../bd/database.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain', 'Spanish');

$periodo = $_GET['periodo'] ?? 'semanal';
$fecha_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-d', strtotime('last sunday'));
$fecha_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-d', strtotime('next friday'));

if (empty($fecha_inicio) || empty($fecha_fin)) {
    $fecha_inicio = date('Y-m-d', strtotime('last sunday'));
    $fecha_fin = date('Y-m-d', strtotime('next friday'));
}

// ✅ CONSULTA ACTUALIZADA: Ahora coincide con el reporte de comisiones
$query_nomina = "
    WITH 
    ComisionesVentas AS (
        -- Comisiones de folios ACTIVOS (NO canceladas)
        SELECT 
            a.id_empleado,
            SUM(dfv.monto_comision) as total_comision_ventas,
            COUNT(DISTINCT fv.id_folio) as total_folios,
            SUM(DISTINCT fv.total_venta) as total_ventas
        FROM Detalle_Folio_Venta dfv
        INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        WHERE COALESCE(dfv.comision_cancelada, 0) = 0
        AND (fv.estado = 'activo' OR fv.estado IS NULL)
        AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY a.id_empleado
    ),
    ComisionesCancelaciones AS (
        -- Comisiones REASIGNADAS por cancelaciones
        SELECT 
            cc.id_empleado,
            SUM(cc.monto_comision) as total_comision_cancelaciones
        FROM Comisiones_Cancelaciones cc
        INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
        WHERE DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio_canc AND :fecha_fin_canc
        GROUP BY cc.id_empleado
    ),
    ComisionesCobradores AS (
        SELECT 
            cc.id_empleado,
            SUM(cc.comision_cobro) as total_comision_cobros,
            SUM(cc.total_cobros) as total_cobrado,
            SUM(cc.total_gasolina) as total_gasolina,
            SUM(cc.prestamo) as total_prestamo,
            SUM(COALESCE(cc.prestamo_inhabilitado, 0)) as total_prestamo_inhabilitado,
            SUM(COALESCE(cc.total_extras, 0)) as total_extras
        FROM Comisiones_Cobradores cc
        INNER JOIN Semanas_Cobro sc ON cc.id_semana = sc.id_semana
        WHERE sc.fecha_inicio >= :fecha_inicio2
        AND sc.fecha_fin <= :fecha_fin2
        GROUP BY cc.id_empleado
    ),
    PagosFijos AS (
        SELECT 
            psf.id_empleado,
            SUM(psf.sueldo_fijo) as total_sueldo_fijo,
            SUM(psf.gasolina) as total_gasolina_fija,
            SUM(psf.total_pagar) as total_pago_fijo
        FROM Pagos_Sueldos_Fijos psf
        INNER JOIN Semanas_Cobro sc ON psf.id_semana = sc.id_semana
        WHERE sc.fecha_inicio >= :fecha_inicio3
        AND sc.fecha_fin <= :fecha_fin3
        AND psf.estado IN ('pendiente', 'pagado')
        GROUP BY psf.id_empleado
    )
    
    -- EMPLEADOS
    SELECT 
        e.id_empleado,
        e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '') as nombre_completo,
        e.rol,
        e.zona,
        'empleado' as tipo_registro,
        -- ✅ Suma comisiones de ventas activas + comisiones reasignadas por cancelaciones
        (COALESCE(cv.total_comision_ventas, 0) + COALESCE(ccanc.total_comision_cancelaciones, 0)) as comision_ventas,
        COALESCE(cob.total_comision_cobros, 0) as comision_cobros,
        COALESCE(pf.total_sueldo_fijo, 0) as sueldo_fijo,
        COALESCE(pf.total_gasolina_fija, 0) as gasolina_fija,
        COALESCE(cob.total_gasolina, 0) as gasolina_cobros,
        COALESCE(cob.total_prestamo, 0) as total_prestamo,
        COALESCE(cob.total_prestamo_inhabilitado, 0) as prestamo_inhabilitado,
        COALESCE(cob.total_extras, 0) as total_extras,
        (
            COALESCE(cv.total_comision_ventas, 0) +
            COALESCE(ccanc.total_comision_cancelaciones, 0) +
            COALESCE(cob.total_comision_cobros, 0) +
            COALESCE(pf.total_sueldo_fijo, 0) +
            COALESCE(cob.total_gasolina, 0) +
            COALESCE(pf.total_gasolina_fija, 0) +
            COALESCE(cob.total_extras, 0) +
            COALESCE(cob.total_prestamo_inhabilitado, 0) -
            COALESCE(cob.total_prestamo, 0)
        ) as total_nomina
    FROM Empleados e
    LEFT JOIN ComisionesVentas cv ON e.id_empleado = cv.id_empleado
    LEFT JOIN ComisionesCancelaciones ccanc ON e.id_empleado = ccanc.id_empleado
    LEFT JOIN ComisionesCobradores cob ON e.id_empleado = cob.id_empleado
    LEFT JOIN PagosFijos pf ON e.id_empleado = pf.id_empleado
    WHERE e.estado = 'activo'
    AND (
        cv.total_comision_ventas > 0 
        OR ccanc.total_comision_cancelaciones > 0
        OR cob.total_comision_cobros > 0 
        OR pf.total_sueldo_fijo > 0
    )
    
    UNION ALL
    
    -- USUARIOS ADMIN
    SELECT 
        NULL as id_empleado,
        TRIM(REPLACE(REPLACE(SUBSTR(psf.observaciones, INSTR(psf.observaciones, '-') + 1), '(Gerencia)', ''), '-', '')) as nombre_completo,
        'admin' as rol,
        'Oficina' as zona,
        'usuario' as tipo_registro,
        0 as comision_ventas,
        0 as comision_cobros,
        SUM(psf.sueldo_fijo) as sueldo_fijo,
        SUM(psf.gasolina) as gasolina_fija,
        0 as gasolina_cobros,
        0 as total_prestamo,
        0 as prestamo_inhabilitado,
        0 as total_extras,
        SUM(psf.total_pagar) as total_nomina
    FROM Pagos_Sueldos_Fijos psf
    INNER JOIN Semanas_Cobro sc ON psf.id_semana = sc.id_semana
    WHERE psf.id_empleado IS NULL
    AND psf.observaciones LIKE 'ADMIN ID:%'
    AND sc.fecha_inicio >= :fecha_inicio4
    AND sc.fecha_fin <= :fecha_fin4
    AND psf.estado IN ('pendiente', 'pagado')
    GROUP BY psf.observaciones
    
    UNION ALL
    
    -- PAGOS MANUALES
    SELECT 
        NULL as id_empleado,
        TRIM(REPLACE(REPLACE(REPLACE(psf.observaciones, 'PAGO MANUAL - Beneficiario:', ''), '|', ''), 'Estoy haciendo una prueb', '')) as nombre_completo,
        'manual' as rol,
        'N/A' as zona,
        'manual' as tipo_registro,
        0 as comision_ventas,
        0 as comision_cobros,
        SUM(psf.sueldo_fijo) as sueldo_fijo,
        SUM(psf.gasolina) as gasolina_fija,
        0 as gasolina_cobros,
        0 as total_prestamo,
        0 as prestamo_inhabilitado,
        0 as total_extras,
        SUM(psf.total_pagar) as total_nomina
    FROM Pagos_Sueldos_Fijos psf
    INNER JOIN Semanas_Cobro sc ON psf.id_semana = sc.id_semana
    WHERE psf.id_empleado IS NULL
    AND psf.observaciones LIKE 'PAGO MANUAL%'
    AND sc.fecha_inicio >= :fecha_inicio5
    AND sc.fecha_fin <= :fecha_fin5
    AND psf.estado IN ('pendiente', 'pagado')
    GROUP BY psf.observaciones
    
    ORDER BY total_nomina DESC
";

$stmt = $conn->prepare($query_nomina);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
$stmt->bindParam(':fecha_inicio_canc', $fecha_inicio);
$stmt->bindParam(':fecha_fin_canc', $fecha_fin);
$stmt->bindParam(':fecha_inicio2', $fecha_inicio);
$stmt->bindParam(':fecha_fin2', $fecha_fin);
$stmt->bindParam(':fecha_inicio3', $fecha_inicio);
$stmt->bindParam(':fecha_fin3', $fecha_fin);
$stmt->bindParam(':fecha_inicio4', $fecha_inicio);
$stmt->bindParam(':fecha_fin4', $fecha_fin);
$stmt->bindParam(':fecha_inicio5', $fecha_inicio);
$stmt->bindParam(':fecha_fin5', $fecha_fin);
$stmt->execute();

$registros_nomina = [];
$total_general_comision_ventas = 0;
$total_general_comision_cobros = 0;
$total_general_sueldos_fijos = 0;
$total_general_gasolina_cobros = 0;
$total_general_gasolina_fija = 0;
$total_general_prestamo = 0;
$total_general_prestamo_inhab = 0;
$total_general_extras = 0;
$total_general_nomina = 0;
$persona_destacada = 'N/A';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $registros_nomina[] = $row;
    $total_general_comision_ventas += $row['comision_ventas'] ?? 0;
    $total_general_comision_cobros += $row['comision_cobros'] ?? 0;
    $total_general_sueldos_fijos += $row['sueldo_fijo'] ?? 0;
    $total_general_gasolina_cobros += $row['gasolina_cobros'] ?? 0;
    $total_general_gasolina_fija += $row['gasolina_fija'] ?? 0;
    $total_general_prestamo += $row['total_prestamo'] ?? 0;
    $total_general_prestamo_inhab += $row['prestamo_inhabilitado'] ?? 0;
    $total_general_extras += $row['total_extras'] ?? 0;
    $total_general_nomina += $row['total_nomina'] ?? 0;
}

// Calcular total de sueldos (sueldo_fijo + gasolina_fija)
$total_general_sueldos = $total_general_sueldos_fijos + $total_general_gasolina_fija;

if (count($registros_nomina) > 0) {
    $persona_destacada = $registros_nomina[0]['nombre_completo'];
}

$meses_es = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$fecha_inicio_obj = new DateTime($fecha_inicio);
$fecha_fin_obj = new DateTime($fecha_fin);
$fecha_inicio_format = $fecha_inicio_obj->format('d') . ' ' . $meses_es[(int)$fecha_inicio_obj->format('n')] . ' ' . $fecha_inicio_obj->format('Y');
$fecha_fin_format = $fecha_fin_obj->format('d') . ' ' . $meses_es[(int)$fecha_fin_obj->format('n')] . ' ' . $fecha_fin_obj->format('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nómina General - <?php echo ucfirst($periodo); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/reporte_nomina.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="back-button">
        <a href="index.php"><i class='bx bx-arrow-back'></i> Volver a Reportes</a>
      </div>
      <h1><i class='bx bx-receipt'></i> Nómina General - <?php echo ucfirst($periodo); ?></h1>
      <p>Periodo: <strong><?php echo $fecha_inicio_format; ?> - <?php echo $fecha_fin_format; ?></strong></p>
    </div>

    <div class="summary">
      <div class="chart-title"><i class='bx bx-bar-chart-alt-2'></i> Resumen del Periodo</div>
      <div class="summary-grid">
        <div class="summary-item">
          <div class="summary-icon success"><i class='bx bx-dollar-circle'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_nomina, 2); ?></h3>
            <p>Total Nómina</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon primary"><i class='bx bx-user-check'></i></div>
          <div class="summary-content">
            <h3><?php echo count($registros_nomina); ?></h3>
            <p>Personas en Nómina</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class='bx bx-shopping-bag'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_comision_ventas, 2); ?></h3>
            <p>Comisiones Ventas</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon info"><i class='bx bx-wallet'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_comision_cobros, 2); ?></h3>
            <p>Comisiones Cobros</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon purple"><i class='bx bx-money'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_sueldos, 2); ?></h3>
            <p>Sueldos Fijos</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><i class='bxs-gas-pump'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_gasolina_cobros, 2); ?></h3>
            <p>Gasolina</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);"><i class='bx bx-gift'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_extras, 2); ?></h3>
            <p>Extras</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class='bx bx-check-circle'></i></div>
          <div class="summary-content">
            <h3>$<?php echo number_format($total_general_prestamo_inhab, 2); ?></h3>
            <p>Prést. Inhabilitados</p>
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-icon warning"><i class='bx bx-trophy'></i></div>
          <div class="summary-content">
            <h3 style="font-size: 1.3rem;"><?php echo $persona_destacada; ?></h3>
            <p>Persona Destacada</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2><i class='bx bx-table'></i> Detalle de Nómina por Persona</h2>
      <div class="table-responsive">
        <table class="details-table">
          <thead>
            <tr>
              <th class="position">#</th>
              <th>Persona</th>
              <th>Tipo/Rol</th>
              <th class="text-center">Com. Ventas</th>
              <th class="text-center">Com. Cobros</th>
              <th class="text-center">Sueldo Fijo</th>
              <th class="text-center">Gasolina</th>
              <th class="text-center">Extras</th>
              <th class="text-center">Préstamo</th>
              <th class="text-center">Prést. Inhab.</th>
              <th class="text-center total-column">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($registros_nomina) > 0): ?>
              <?php foreach ($registros_nomina as $index => $reg): ?>
                <tr>
                  <td class="position"><?php echo $index + 1; ?></td>
                  <td>
                    <strong><?php echo htmlspecialchars($reg['nombre_completo']); ?></strong>
                    <?php if ($reg['zona'] != 'N/A'): ?>
                      <small><?php echo $reg['zona']; ?></small>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php 
                    if ($reg['tipo_registro'] == 'usuario') {
                      echo '<span class="badge badge-purple">Gerencia</span>';
                    } elseif ($reg['tipo_registro'] == 'manual') {
                      echo '<span class="badge badge-info">Manual</span>';
                    } else {
                      echo '<span class="badge badge-primary">' . ucfirst($reg['rol']) . '</span>';
                    }
                    ?>
                  </td>
                  <td class="text-center">$<?php echo number_format($reg['comision_ventas'], 2); ?></td>
                  <td class="text-center">$<?php echo number_format($reg['comision_cobros'], 2); ?></td>
                  <td class="text-center">$<?php echo number_format($reg['sueldo_fijo'] + $reg['gasolina_fija'], 2); ?></td>
                  <td class="text-center">$<?php echo number_format($reg['gasolina_cobros'], 2); ?></td>
                  <td class="text-center">$<?php echo number_format($reg['total_extras'], 2); ?></td>
                  <td class="text-center" style="color: #ef4444;">
                    <?php if ($reg['total_prestamo'] > 0): ?>
                      -$<?php echo number_format($reg['total_prestamo'], 2); ?>
                    <?php else: ?>
                      $0.00
                    <?php endif; ?>
                  </td>
                  <td class="text-center" style="color: #10b981;">
                    <?php if ($reg['prestamo_inhabilitado'] > 0): ?>
                      +$<?php echo number_format($reg['prestamo_inhabilitado'], 2); ?>
                    <?php else: ?>
                      $0.00
                    <?php endif; ?>
                  </td>
                  <td class="text-center total-column">
                    <strong>$<?php echo number_format($reg['total_nomina'], 2); ?></strong>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr class="totals-row">
                <td colspan="3" class="text-right"><strong>TOTALES:</strong></td>
                <td class="text-center"><strong>$<?php echo number_format($total_general_comision_ventas, 2); ?></strong></td>
                <td class="text-center"><strong>$<?php echo number_format($total_general_comision_cobros, 2); ?></strong></td>
                <td class="text-center"><strong>$<?php echo number_format($total_general_sueldos, 2); ?></strong></td>
                <td class="text-center"><strong>$<?php echo number_format($total_general_gasolina_cobros, 2); ?></strong></td>
                <td class="text-center"><strong>$<?php echo number_format($total_general_extras, 2); ?></strong></td>
                <td class="text-center" style="color: #ef4444;"><strong>-$<?php echo number_format($total_general_prestamo, 2); ?></strong></td>
                <td class="text-center" style="color: #10b981;"><strong>+$<?php echo number_format($total_general_prestamo_inhab, 2); ?></strong></td>
                <td class="text-center total-column"><strong>$<?php echo number_format($total_general_nomina, 2); ?></strong></td>
              </tr>
            <?php else: ?>
              <tr>
                <td colspan="11" class="text-center" style="padding: 3rem;">
                  <i class='bx bx-data' style="font-size: 3rem; color: #9ca3af;"></i>
                  <p style="color: #9ca3af; margin-top: 1rem;">No hay datos de nómina en este periodo</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="export-buttons">
      <button onclick="window.print()" class="btn-export">
        <i class='bx bx-printer'></i> Imprimir Reporte
      </button>
      <a href="index.php" class="btn-export outline">
        <i class='bx bx-home'></i> Nuevo Reporte
      </a>
    </div>
  </div>
</body>
</html>