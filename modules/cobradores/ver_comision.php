<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

// Prevenir caché de la página
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_comision = $_GET['id'];

// Obtener datos de la comisión
$query_comision = "SELECT * FROM Vista_Comisiones_Completo WHERE id_comision = :id";
$stmt_comision = $conn->prepare($query_comision);
$stmt_comision->bindParam(':id', $id_comision);
$stmt_comision->execute();
$comision = $stmt_comision->fetch(PDO::FETCH_ASSOC);

if (!$comision) {
    header("Location: index.php");
    exit;
}

// Obtener info del empleado
$query_empleado = "SELECT * FROM Empleados WHERE id_empleado = :id";
$stmt_empleado = $conn->prepare($query_empleado);
$stmt_empleado->bindParam(':id', $comision['id_empleado']);
$stmt_empleado->execute();
$empleado = $stmt_empleado->fetch(PDO::FETCH_ASSOC);

// Obtener cobros diarios de esa semana (domingo a viernes)
$query_cobros = "
    SELECT 
        fecha,
        CASE CAST(strftime('%w', fecha) AS INTEGER)
            WHEN 0 THEN 'Domingo'
            WHEN 1 THEN 'Lunes'
            WHEN 2 THEN 'Martes'
            WHEN 3 THEN 'Miércoles'
            WHEN 4 THEN 'Jueves'
            WHEN 5 THEN 'Viernes'
        END as dia_semana,
        monto_cobrado,
        clientes_visitados,
        observaciones
    FROM Cobros_Diarios 
    WHERE id_empleado = :id_empleado 
    AND fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND CAST(strftime('%w', fecha) AS INTEGER) BETWEEN 0 AND 5
    ORDER BY fecha
";
$stmt_cobros = $conn->prepare($query_cobros);
$stmt_cobros->bindParam(':id_empleado', $comision['id_empleado']);
$stmt_cobros->bindParam(':fecha_inicio', $comision['fecha_inicio']);
$stmt_cobros->bindParam(':fecha_fin', $comision['fecha_fin']);
$stmt_cobros->execute();
$cobros_detalle = $stmt_cobros->fetchAll(PDO::FETCH_ASSOC);

// Obtener extras de la comisión
$query_extras = "SELECT * FROM Extras_Comision WHERE id_comision = :id ORDER BY id_extra";
$stmt_extras = $conn->prepare($query_extras);
$stmt_extras->bindParam(':id', $id_comision);
$stmt_extras->execute();
$extras = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);

// OBTENER COMISIONES DE ASIGNACIONES (VENTAS)
$query_comisiones_asignaciones = "
    SELECT 
        fv.numero_folio,
        fv.nombre_cliente,
        fv.fecha_hora_venta,
        p.nombre as producto,
        dfv.cantidad_vendida,
        dfv.precio_unitario,
        dfv.porcentaje_comision,
        dfv.monto_comision
    FROM Detalle_Folio_Venta dfv
    INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
    INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
    INNER JOIN Productos p ON dfv.id_producto = p.id_producto
    WHERE a.id_empleado = :id_empleado
    AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
    AND COALESCE(fv.estado, 'activo') = 'activo'
    AND COALESCE(dfv.comision_cancelada, 0) = 0
    ORDER BY fv.fecha_hora_venta DESC
";
$stmt_comisiones_asig = $conn->prepare($query_comisiones_asignaciones);
$stmt_comisiones_asig->bindParam(':id_empleado', $comision['id_empleado']);
$stmt_comisiones_asig->bindParam(':fecha_inicio', $comision['fecha_inicio']);
$stmt_comisiones_asig->bindParam(':fecha_fin', $comision['fecha_fin']);
$stmt_comisiones_asig->execute();
$comisiones_asignaciones = $stmt_comisiones_asig->fetchAll(PDO::FETCH_ASSOC);

// OBTENER COMISIONES REASIGNADAS EN CANCELACIONES
$query_comisiones_reasignadas = "
    SELECT 
        fv.numero_folio,
        fv.nombre_cliente,
        cf.fecha_cancelacion,
        p.nombre as producto,
        cc.monto_comision
    FROM Comisiones_Cancelaciones cc
    INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
    INNER JOIN Folios_Venta fv ON cc.id_folio = fv.id_folio
    INNER JOIN Productos p ON cc.id_producto = p.id_producto
    WHERE cc.id_empleado = :id_empleado
    AND DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio AND :fecha_fin
    ORDER BY cf.fecha_cancelacion DESC
";
$stmt_comisiones_reasig = $conn->prepare($query_comisiones_reasignadas);
$stmt_comisiones_reasig->bindParam(':id_empleado', $comision['id_empleado']);
$stmt_comisiones_reasig->bindParam(':fecha_inicio', $comision['fecha_inicio']);
$stmt_comisiones_reasig->bindParam(':fecha_fin', $comision['fecha_fin']);
$stmt_comisiones_reasig->execute();
$comisiones_reasignadas = $stmt_comisiones_reasig->fetchAll(PDO::FETCH_ASSOC);

// CALCULAR TOTAL DE COMISIONES DE ASIGNACIONES
$total_comision_asignaciones = 0;
foreach ($comisiones_asignaciones as $ca) {
    $total_comision_asignaciones += $ca['monto_comision'];
}
foreach ($comisiones_reasignadas as $cr) {
    $total_comision_asignaciones += $cr['monto_comision'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Detalle de Comisión - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        .detail-item label {
            font-size: 12px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 5px;
        }
        .detail-item .value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Tabla profesional y limpia */
        .tabla-profesional {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .tabla-profesional thead {
            background: linear-gradient(135deg, #0c3c78 0%, #1e5799 100%);
            color: white;
        }

        .tabla-profesional thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabla-profesional tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }

        .tabla-profesional tbody tr:hover {
            background: #f8f9fa;
        }

        .tabla-profesional tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: #333;
        }

        .tabla-profesional tbody tr:last-child {
            border-bottom: none;
        }

        .tabla-profesional .fila-total {
            background: #f0f7ff;
            font-weight: 600;
            border-top: 2px solid #0c3c78;
        }

        .tabla-profesional .fila-total td {
            padding: 16px;
            font-size: 15px;
            color: #0c3c78;
        }

        .tabla-profesional .badge-folio {
            display: inline-block;
            padding: 4px 10px;
            background: #E3F2FD;
            color: #1976D2;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .tabla-profesional .badge-reasignada {
            display: inline-block;
            padding: 4px 10px;
            background: #FFF3E0;
            color: #F57C00;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .fila-reasignada {
            background: #fffbf5 !important;
        }

        .monto-positivo {
            color: #4CAF50;
            font-weight: 600;
        }

        /* Sección de extras limpia */
        .extras-list {
            margin-top: 20px;
        }

        .extra-item-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .extra-item-card:hover {
            border-color: #10B981;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        .extra-info {
            flex: 1;
        }

        .extra-monto {
            font-size: 24px;
            font-weight: 700;
            color: #10B981;
        }

        .extra-obs {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        .extra-fecha {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header no-print">
            <h1><i class='bx bx-file-blank'></i> Detalle de Comisión</h1>
            <div class="header-actions">
                <a href="#" onclick="navegarA('editar_comision.php?id=<?php echo $comision['id_comision']; ?>'); return false;" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Editar
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class='bx bx-printer'></i> Imprimir
                </button>
                <a href="#" onclick="volverIndex(); return false;" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Volver
                </a>
            </div>
        </div>

        <!-- Información de la Semana -->
        <div class="card">
            <h2><i class='bx bx-calendar-week'></i> Información de la Semana</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Mes y Año</label>
                    <div class="value"><?php echo $comision['mes'] . ' ' . $comision['anio']; ?></div>
                </div>
                <div class="detail-item">
                    <label>Número de Semana</label>
                    <div class="value">Semana <?php echo $comision['numero_semana']; ?></div>
                </div>
                <div class="detail-item">
                    <label>Período</label>
                    <div class="value">
                        <?php echo date('d/m/Y', strtotime($comision['fecha_inicio'])); ?> - 
                        <?php echo date('d/m/Y', strtotime($comision['fecha_fin'])); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <label>Estado</label>
                    <div class="value">
                        <?php
                        $badge_class = 'badge-warning';
                        if ($comision['estado'] == 'revisada') $badge_class = 'badge-info';
                        if ($comision['estado'] == 'pagada') $badge_class = 'badge-success';
                        ?>
                        <span class="estado-badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($comision['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Empleado -->
        <div class="card">
            <h2><i class='bx bx-user'></i> Información del Empleado</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Nombre Completo</label>
                    <div class="value"><?php echo htmlspecialchars($comision['nombre_empleado']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Teléfono</label>
                    <div class="value"><?php echo $empleado['telefono']; ?></div>
                </div>
                <div class="detail-item">
                    <label>Zona Asignada</label>
                    <div class="value">
                        <span class="zona-badge"><?php echo $comision['zona']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desglose de Cobros Diarios -->
        <div class="card">
            <h2><i class='bx bx-calendar-check'></i> Desglose de Cobros (Domingo a Viernes)</h2>
            
            <?php if (count($cobros_detalle) > 0): ?>
                <table class="tabla-profesional">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Monto Cobrado</th>
                            <th>Clientes</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cobros_detalle as $cobro): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($cobro['fecha'])); ?></td>
                                <td><span class="badge-info"><?php echo $cobro['dia_semana']; ?></span></td>
                                <td class="monto-positivo">$<?php echo number_format($cobro['monto_cobrado'], 2); ?></td>
                                <td><?php echo $cobro['clientes_visitados']; ?></td>
                                <td>
                                    <?php if ($cobro['observaciones']): ?>
                                        <small><?php echo htmlspecialchars($cobro['observaciones']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="fila-total">
                            <td colspan="2" style="text-align: right;">TOTAL COBRADO:</td>
                            <td colspan="3">$<?php echo number_format($comision['total_cobros'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #f44336; margin-top: 15px;">⚠️ No hay cobros registrados para esta semana</p>
            <?php endif; ?>
        </div>

        <!-- Comisiones de Asignaciones (Ventas) -->
        <div class="card">
            <h2><i class='bx bx-shopping-bag'></i> Comisiones por Ventas</h2>
            
            <?php if (count($comisiones_asignaciones) > 0 || count($comisiones_reasignadas) > 0): ?>
                <table class="tabla-profesional">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comisiones_asignaciones as $ca): ?>
                            <tr>
                                <td><span class="badge-folio"><?php echo $ca['numero_folio']; ?></span></td>
                                <td><?php echo htmlspecialchars($ca['nombre_cliente']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($ca['fecha_hora_venta'])); ?></td>
                                <td><?php echo htmlspecialchars($ca['producto']); ?></td>
                                <td><?php echo $ca['cantidad_vendida']; ?></td>
                                <td class="monto-positivo">$<?php echo number_format($ca['monto_comision'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php foreach ($comisiones_reasignadas as $cr): ?>
                            <tr class="fila-reasignada">
                                <td><span class="badge-folio"><?php echo $cr['numero_folio']; ?></span> <span class="badge-reasignada">Reasignada</span></td>
                                <td><?php echo htmlspecialchars($cr['nombre_cliente']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cr['fecha_cancelacion'])); ?></td>
                                <td><?php echo htmlspecialchars($cr['producto']); ?></td>
                                <td>-</td>
                                <td class="monto-positivo">$<?php echo number_format($cr['monto_comision'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="fila-total">
                            <td colspan="5" style="text-align: right;">TOTAL COMISIÓN POR VENTAS:</td>
                            <td>$<?php echo number_format($total_comision_asignaciones, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #666; margin-top: 15px;">
                    <i class='bx bx-info-circle'></i> No hay ventas registradas en esta semana
                </p>
            <?php endif; ?>
        </div>

        <!-- Gasolina de la Semana -->
        <div class="card">
            <h2><i class='bx bxs-gas-pump'></i> Gasolina de la Semana</h2>
            
            <div style="margin-top: 20px; padding: 20px; background: #FFF3E0; border-radius: 8px; border-left: 4px solid #FF9800;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; font-size: 14px; color: #666;">
                            <i class='bx bxs-gas-pump' style="color: #FF9800;"></i>
                            <strong>Monto en Efectivo</strong>
                        </p>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #999;">
                            Gasolina capturada al generar la comisión
                        </p>
                    </div>
                    <div>
                        <p style="margin: 0; font-size: 28px; font-weight: bold; color: #FF9800;">
                            $<?php echo number_format($comision['total_gasolina'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Montos Extras -->
        <?php if (count($extras) > 0): ?>
        <div class="card">
            <h2><i class='bx bx-dollar-circle'></i> Montos Extras</h2>
            
            <div class="extras-list">
                <?php foreach ($extras as $extra): ?>
                    <div class="extra-item-card">
                        <div class="extra-info">
                            <?php if ($extra['observaciones']): ?>
                                <div class="extra-obs">
                                    <i class='bx bx-note' style="color: #666;"></i>
                                    <?php echo htmlspecialchars($extra['observaciones']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="extra-fecha">
                                <i class='bx bx-time'></i>
                                Registrado: <?php echo date('d/m/Y H:i', strtotime($extra['fecha_registro'])); ?>
                            </div>
                        </div>
                        <div class="extra-monto">
                            +$<?php echo number_format($extra['monto'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="margin-top: 15px; padding: 15px; background: #E8F5E9; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: #2E7D32;">TOTAL EXTRAS:</strong>
                        <strong style="font-size: 20px; color: #2E7D32;">$<?php echo number_format($comision['total_extras'], 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cálculo de Comisión -->
        <div class="card">
            <h2><i class='bx bx-calculator'></i> Cálculo de Comisión</h2>
            
            <table style="width: 100%; font-size: 16px; margin-top: 20px;">
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-dollar' style="color: var(--primary-color);"></i>
                        <strong>Total Cobrado (Dom-Vie)</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px;">
                        $<?php echo number_format($comision['total_cobros'], 2); ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-trending-up' style="color: var(--success-color);"></i>
                        <strong>Comisión por Cobros (10%)</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                        +$<?php echo number_format($comision['comision_cobro'], 2); ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color); background: #f8f9fa;">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-shopping-bag' style="color: var(--success-color);"></i>
                        <strong>Comisión por Ventas</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                        +$<?php echo number_format($total_comision_asignaciones, 2); ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 15px 0;">
                        <i class='bx bxs-gas-pump' style="color: var(--success-color);"></i>
                        <strong>Gasolina</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                        +$<?php echo number_format($comision['total_gasolina'], 2); ?>
                    </td>
                </tr>
                <?php if ($comision['total_extras'] > 0): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-dollar-circle' style="color: var(--success-color);"></i>
                        <strong>Extras</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                        +$<?php echo number_format($comision['total_extras'], 2); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($comision['prestamo'] > 0): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-wallet' style="color: var(--danger-color);"></i>
                        <strong>Préstamo</strong>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--danger-color);">
                        -$<?php echo number_format($comision['prestamo'], 2); ?>
                    </td>
                </tr>
                <?php 
                    $prestamo_inhabilitado = floatval($comision['prestamo_inhabilitado']);
                    if ($prestamo_inhabilitado > 0): 
                ?>
                <tr style="border-bottom: 1px solid var(--border-color); background: #fff3e0;">
                    <td style="padding: 15px 0;">
                        <i class='bx bx-info-circle' style="color: #f5576c;"></i>
                        <strong>Préstamo Inhabilitado (Absorbe Empresa)</strong>
                        <br><small style="color: #666; font-weight: normal;">No se descuenta al empleado</small>
                    </td>
                    <td style="padding: 15px 0; text-align: right; font-size: 20px; color: #f5576c;">
                        +$<?php echo number_format($prestamo_inhabilitado, 2); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
                <tr style="border-top: 3px solid var(--text-dark); background: #f8f9fa;">
                    <td style="padding: 20px 0;">
                        <i class='bx bx-check-circle' style="color: var(--primary-color);"></i>
                        <strong style="font-size: 18px;">TOTAL A PAGAR</strong>
                    </td>
                    <td style="padding: 20px 0; text-align: right; font-size: 28px; font-weight: bold; color: var(--primary-color);">
                        $<?php echo number_format($comision['total_comision'], 2); ?>
                    </td>
                </tr>
            </table>

            <?php if ($comision['fecha_pago']): ?>
                <div style="margin-top: 20px; padding: 15px; background: #E8F5E9; border-radius: 8px; border-left: 4px solid var(--success-color);">
                    <i class='bx bx-check-circle' style="color: var(--success-color); font-size: 20px;"></i>
                    <strong>Fecha de Pago:</strong> <?php echo date('d/m/Y', strtotime($comision['fecha_pago'])); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Observaciones -->
        <?php if ($comision['observaciones']): ?>
        <div class="card">
            <h2><i class='bx bx-note'></i> Observaciones</h2>
            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; margin-top: 15px;">
                <?php echo nl2br(htmlspecialchars($comision['observaciones'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Información de Registro -->
        <div class="card" style="background: #f8f9fa;">
            <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
                <i class='bx bx-info-circle'></i>
                <strong>Fecha de Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($comision['fecha_creacion'])); ?>
            </p>
        </div>
    </div>

    <script>
        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'navigate', 
                    page: pagina,
                    fullUrl: pagina
                }, '*');
            } else {
                window.location.href = pagina;
            }
        }

        function volverIndex() {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'navigate', 
                    page: 'index.php',
                    fullUrl: 'index.php'
                }, '*');
                
                setTimeout(function() {
                    const iframe = window.parent.document.querySelector('#comisiones iframe');
                    if (iframe) {
                        iframe.src = '../cobradores/index.php?t=' + new Date().getTime();
                    }
                }, 100);
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>