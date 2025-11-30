<?php
session_start();
require_once '../../bd/database.php';

if (!isset($_SESSION['usuario'])) {
    exit('No autorizado');
}

$id_folio = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_folio <= 0) {
    exit('ID inválido');
}

// Obtener información básica del folio
$sql = "SELECT 
            fv.*,
            (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
            e.rol,
            a.id_asignacion,
            (v.marca || ' ' || v.modelo || ' (' || a.placas || ')') as vehiculo
        FROM Folios_Venta fv
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        INNER JOIN Vehiculos v ON a.placas = v.placas
        WHERE fv.id_folio = :id_folio";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':id_folio', $id_folio);
$stmt->execute();
$folio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$folio) {
    exit('Folio no encontrado');
}

// Obtener productos del folio con información de traspasos
$sql_productos = "SELECT 
                      dfv.*,
                      p.nombre as producto_nombre,
                      p.precio_costo,
                      p.precio_venta,
                      (SELECT COUNT(*) 
                       FROM Traspasos_Asignaciones t
                       WHERE t.id_asignacion_destino = :id_asignacion
                       AND t.id_producto = dfv.id_producto
                       AND t.fecha_hora_traspaso <= :fecha_venta) as fue_traspasado
                  FROM Detalle_Folio_Venta dfv
                  INNER JOIN Productos p ON dfv.id_producto = p.id_producto
                  WHERE dfv.id_folio = :id_folio";

$stmt_prod = $conn->prepare($sql_productos);
$stmt_prod->bindParam(':id_folio', $id_folio);
$stmt_prod->bindParam(':id_asignacion', $folio['id_asignacion']);
$stmt_prod->bindParam(':fecha_venta', $folio['fecha_hora_venta']);
$stmt_prod->execute();
$productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_comision = array_sum(array_column($productos, 'monto_comision'));
$tiene_traspasos = array_sum(array_column($productos, 'fue_traspasado')) > 0;

// Si tiene traspasos, obtener detalles
$info_traspasos = [];
if ($tiene_traspasos) {
    $sql_traspasos = "SELECT 
                          t.*,
                          p.nombre as producto_nombre,
                          (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as empleado_origen,
                          a.id_asignacion as asignacion_origen_num
                      FROM Traspasos_Asignaciones t
                      INNER JOIN Productos p ON t.id_producto = p.id_producto
                      INNER JOIN Empleados e ON t.id_empleado_origen = e.id_empleado
                      INNER JOIN Asignaciones a ON t.id_asignacion_origen = a.id_asignacion
                      WHERE t.id_asignacion_destino = :id_asignacion
                      AND t.fecha_hora_traspaso <= :fecha_venta
                      ORDER BY t.fecha_hora_traspaso DESC";
    
    $stmt_trasp = $conn->prepare($sql_traspasos);
    $stmt_trasp->bindParam(':id_asignacion', $folio['id_asignacion']);
    $stmt_trasp->bindParam(':fecha_venta', $folio['fecha_hora_venta']);
    $stmt_trasp->execute();
    $info_traspasos = $stmt_trasp->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2 style="color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: 10px;">
    <i class='bx bx-file-text'></i> Folio de Venta: <?php echo htmlspecialchars($folio['numero_folio']); ?>
    <?php if ($tiene_traspasos): ?>
        <span style="display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; border: 1px solid #fbbf24; margin-left: 10px;">
            <i class='bx bx-transfer' style="font-size: 1.2rem;"></i>
            Incluye Traspasos
        </span>
    <?php endif; ?>
</h2>

<!-- Alerta de Traspaso -->
<?php if ($tiene_traspasos && count($info_traspasos) > 0): ?>
    <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
        <h4 style="color: #92400e; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
            <i class='bx bx-transfer' style="font-size: 1.5rem;"></i>
            Traspasos Recibidos para esta Venta
        </h4>
        <p style="color: #78350f; margin-bottom: 15px;">
            Este folio incluye productos que fueron traspasados de otras asignaciones:
        </p>
        
        <?php foreach ($info_traspasos as $traspaso): ?>
            <div style="background: white; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong style="color: #92400e;">
                            <?php echo $traspaso['cantidad']; ?> × <?php echo htmlspecialchars($traspaso['producto_nombre']); ?>
                        </strong>
                        <div style="font-size: 0.9rem; color: #78350f; margin-top: 4px;">
                            <i class='bx bx-user'></i> De: <?php echo htmlspecialchars($traspaso['empleado_origen']); ?> 
                            (Asig. #<?php echo str_pad($traspaso['asignacion_origen_num'], 4, '0', STR_PAD_LEFT); ?>)
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.85rem; color: #78350f;">
                        <i class='bx bx-calendar'></i> 
                        <?php echo date('d/m/Y H:i', strtotime($traspaso['fecha_hora_traspaso'])); ?>
                    </div>
                </div>
                <?php if ($traspaso['observaciones']): ?>
                    <div style="margin-top: 8px; font-size: 0.85rem; color: #78350f; font-style: italic;">
                        <i class='bx bx-note'></i> <?php echo htmlspecialchars($traspaso['observaciones']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Información General -->
<div style="background: #f8f9fa; padding: 20px; border-radius: var(--radius); margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;"><i class='bx bx-info-circle'></i> Información General</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div>
            <strong>Número de Folio:</strong><br>
            <span style="font-weight: bold; color: var(--color-primary);"><?php echo htmlspecialchars($folio['numero_folio']); ?></span>
        </div>
        <div>
            <strong>Cliente:</strong><br>
            <?php echo htmlspecialchars($folio['nombre_cliente']); ?>
        </div>
        <div>
            <strong>Vendedor:</strong><br>
            <?php echo htmlspecialchars($folio['nombre_empleado']); ?>
        </div>
        <div>
            <strong>Zona:</strong><br>
            <?php echo htmlspecialchars($folio['zona']); ?>
        </div>
        <div>
            <strong>Dirección:</strong><br>
            <?php echo htmlspecialchars($folio['direccion'] ?? 'N/A'); ?>
        </div>
        <div>
            <strong>Fecha de Venta:</strong><br>
            <?php echo date('d/m/Y H:i', strtotime($folio['fecha_hora_venta'])); ?>
        </div>
        <div>
            <strong>Vehículo:</strong><br>
            <?php echo htmlspecialchars($folio['vehiculo']); ?>
        </div>
        <div>
            <strong>Tipo de Venta:</strong><br>
            <span class="estado-badge <?php echo $folio['tipo_pago']; ?>">
                <?php echo ucfirst($folio['tipo_pago']); ?>
            </span>
        </div>
        <div>
            <strong>Asignación:</strong><br>
            #<?php echo str_pad($folio['id_asignacion'], 4, '0', STR_PAD_LEFT); ?>
        </div>
    </div>
</div>

<!-- Productos Vendidos -->
<div style="margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;"><i class='bx bx-package'></i> Productos Vendidos</h3>
    <div class="tabla-productos">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                    <th>Comisión C/U </th>
                    <th>Comisión $</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr <?php echo $producto['fue_traspasado'] > 0 ? 'style="background: rgba(254, 243, 199, 0.3);"' : ''; ?>>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php echo htmlspecialchars($producto['producto_nombre']); ?>
                                <?php if ($producto['fue_traspasado'] > 0): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 3px; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; border: 1px solid #fbbf24;">
                                        <i class='bx bx-transfer' style="font-size: 0.9rem;"></i>
                                        Traspaso
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center"><strong><?php echo $producto['cantidad_vendida']; ?></strong></td>
                        <td class="text-right">$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                        <td class="text-right"><strong>$<?php echo number_format($producto['subtotal'], 2); ?></strong></td>
                        <td class="text-center"><?php echo number_format($producto['porcentaje_comision'], 1); ?></td>
                        <td class="text-right" style="color: var(--color-info); font-weight: 700;">
                            $<?php echo number_format($producto['monto_comision'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #f8f9fa; font-weight: 700;">
                <tr>
                    <td colspan="3" style="padding: 12px; text-align: right;">TOTALES:</td>
                    <td style="padding: 12px; text-align: right; color: var(--color-primary);">
                        $<?php echo number_format($folio['total_venta'], 2); ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">-</td>
                    <td style="padding: 12px; text-align: right; color: var(--color-info);">
                        $<?php echo number_format($total_comision, 2); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Desglose Financiero -->
<div style="background: linear-gradient(135deg, #f0f7ff, #e0f2fe); padding: 20px; border-radius: var(--radius); margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;"><i class='bx bx-calculator'></i> Desglose Financiero</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="text-align: center; padding: 15px; background: white; border-radius: var(--radius);">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">
                $<?php echo number_format($folio['total_venta'], 2); ?>
            </div>
            <div style="color: var(--color-muted);">Total Venta</div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: white; border-radius: var(--radius);">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success);">
                $<?php echo number_format($folio['enganche'], 2); ?>
            </div>
            <div style="color: var(--color-muted);">Enganche</div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: white; border-radius: var(--radius);">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-info);">
                $<?php echo number_format($total_comision, 2); ?>
            </div>
            <div style="color: var(--color-muted);">Comisión</div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: white; border-radius: var(--radius);">
            <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $folio['saldo_pendiente'] > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>">
                $<?php echo number_format($folio['saldo_pendiente'], 2); ?>
            </div>
            <div style="color: var(--color-muted);">Saldo Pendiente</div>
        </div>
    </div>
</div>

<!-- Observaciones -->
<?php if (!empty($folio['observaciones'])): ?>
<div style="background: #fff3cd; padding: 15px; border-radius: var(--radius); margin: 20px 0; border-left: 4px solid var(--color-warning);">
    <h4 style="color: #856404; margin-bottom: 10px;"><i class='bx bx-note'></i> Observaciones</h4>
    <p style="color: #856404; margin: 0;"><?php echo nl2br(htmlspecialchars($folio['observaciones'])); ?></p>
</div>
<?php endif; ?>