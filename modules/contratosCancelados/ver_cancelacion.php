<?php
session_start();
require_once '../../bd/database.php';

if (!isset($_SESSION['usuario'])) {
    exit('No autorizado');
}

$id_cancelacion = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cancelacion <= 0) {
    exit('ID inválido');
}

// Obtener información completa de la cancelación
$sql = "SELECT 
            cf.*,
            fv.numero_folio,
            fv.nombre_cliente,
            fv.zona,
            fv.direccion,
            fv.enganche,
            fv.total_venta,
            fv.saldo_pendiente,
            fv.tipo_pago,
            fv.fecha_hora_venta,
            (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as vendedor,
            (u.nombre || ' ' || u.apellido_paterno || ' ' || u.apellido_materno) as usuario_que_cancelo
        FROM Cancelaciones_Folios cf
        INNER JOIN Folios_Venta fv ON cf.id_folio = fv.id_folio
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        LEFT JOIN Usuarios u ON cf.usuario_cancela = u.id
        WHERE cf.id_cancelacion = :id_cancelacion";

$stmt = $conn->prepare($sql);
$stmt->execute([':id_cancelacion' => $id_cancelacion]);
$cancelacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cancelacion) {
    exit('Cancelación no encontrada');
}

// Obtener productos recuperados
$sql_productos = "SELECT 
                      pr.*,
                      p.nombre as producto_nombre
                  FROM Productos_Recuperados pr
                  INNER JOIN Productos p ON pr.id_producto = p.id_producto
                  WHERE pr.id_cancelacion = :id_cancelacion
                  ORDER BY pr.fecha_recuperacion";

$stmt_prod = $conn->prepare($sql_productos);
$stmt_prod->execute([':id_cancelacion' => $id_cancelacion]);
$productos_recuperados = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_pagado = $cancelacion['total_venta'] - $cancelacion['saldo_pendiente'];

$motivos = [
    'morosidad_tardia' => 'Morosidad Tardía',
    'morosidad_inmediata' => 'Morosidad Inmediata',
    'situacion_extraordinaria' => 'Situación Extraordinaria',
    'otro' => 'Otro'
];

// Helper para estados de producto
$estados_producto = [
    'bueno' => ['color' => '#10b981', 'bg' => '#d1fae5', 'icon' => 'bx-check-circle', 'texto' => 'Buen estado'],
    'danado' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => 'bx-error', 'texto' => 'Dañado'],
    'incompleto' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'icon' => 'bx-x-circle', 'texto' => 'Incompleto']
];

// Helper para estilos de enganche
$color_enganche = $cancelacion['enganche_devuelto'] == 1 ? '#10b981' : '#6b7280';
$bg_enganche = $cancelacion['enganche_devuelto'] == 1 ? '#d1fae5' : '#f3f4f6';
$color_icono_enganche = $cancelacion['enganche_devuelto'] == 1 ? '#065f46' : '#6b7280';
$icono_enganche = $cancelacion['enganche_devuelto'] == 1 ? 'bx-check-circle' : 'bx-x-circle';
$color_texto_enganche = $cancelacion['enganche_devuelto'] == 1 ? '#065f46' : '#374151';
$color_span_enganche = $cancelacion['enganche_devuelto'] == 1 ? '#059669' : '#6b7280';
?>

<h2 style="color: var(--color-danger); border-bottom: 3px solid var(--color-danger); padding-bottom: 15px; margin-bottom: 25px;">
    <i class='bx bx-x-circle'></i> Detalles de Cancelación #<?php echo str_pad($cancelacion['id_cancelacion'], 4, '0', STR_PAD_LEFT); ?>
</h2>

<!-- Información del Folio Cancelado -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;">
        <i class='bx bx-file-text'></i> Información del Folio Cancelado
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <strong>Folio:</strong><br>
            <span style="font-size: 1.2rem; font-weight: 700; color: var(--color-danger);">
                <?php echo htmlspecialchars($cancelacion['numero_folio']); ?>
            </span>
        </div>
        <div>
            <strong>Cliente:</strong><br>
            <?php echo htmlspecialchars($cancelacion['nombre_cliente']); ?>
        </div>
        <div>
            <strong>Vendedor:</strong><br>
            <?php echo htmlspecialchars($cancelacion['vendedor']); ?>
        </div>
        <div>
            <strong>Zona:</strong><br>
            <?php echo htmlspecialchars($cancelacion['zona']); ?>
        </div>
        <div>
            <strong>Dirección:</strong><br>
            <?php echo htmlspecialchars($cancelacion['direccion']); ?>
        </div>
        <div>
            <strong>Fecha de Venta:</strong><br>
            <?php echo date('d/m/Y H:i', strtotime($cancelacion['fecha_hora_venta'])); ?>
        </div>
        <div>
            <strong>Tipo de Pago:</strong><br>
            <span style="text-transform: capitalize; font-weight: 600;">
                <?php echo $cancelacion['tipo_pago']; ?>
            </span>
        </div>
    </div>
</div>

<!-- Detalles de la Cancelación -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;">
        <i class='bx bx-info-circle'></i> Detalles de la Cancelación
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <strong>Fecha de Cancelación:</strong><br>
            <?php echo date('d/m/Y H:i', strtotime($cancelacion['fecha_cancelacion'])); ?>
        </div>
        <div>
            <strong>Motivo:</strong><br>
            <span class="badge-motivo <?php echo $cancelacion['motivo']; ?>">
                <?php echo $motivos[$cancelacion['motivo']] ?? 'Desconocido'; ?>
            </span>
        </div>
        <div>
            <strong>Cancelado por:</strong><br>
            <?php echo htmlspecialchars($cancelacion['usuario_que_cancelo'] ?? 'Sistema'); ?>
        </div>
        <div>
            <strong>Descuento de Comisión:</strong><br>
            <span style="display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; background: <?php echo $cancelacion['descontar_comision'] == 1 ? '#fef3c7' : '#d1fae5'; ?>; color: <?php echo $cancelacion['descontar_comision'] == 1 ? '#92400e' : '#065f46'; ?>;">
                <?php echo $cancelacion['descontar_comision'] == 1 ? 'Sí' : 'No'; ?>
            </span>
        </div>
    </div>
</div>

<!-- Resumen Financiero -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;">
        <i class='bx bx-dollar-circle'></i> Resumen Financiero
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <strong>Total de Venta:</strong><br>
            <span style="font-size: 1.3em; font-weight: bold;">$<?php echo number_format($cancelacion['total_venta'], 2); ?></span>
        </div>
        <div>
            <strong>Total Pagado:</strong><br>
            <span style="font-size: 1.3em; font-weight: bold; color: #10b981;">$<?php echo number_format($total_pagado, 2); ?></span>
        </div>
        <div>
            <strong>Saldo Pendiente:</strong><br>
            <span style="font-size: 1.3em; font-weight: bold; color: #ef4444;">$<?php echo number_format($cancelacion['saldo_pendiente'], 2); ?></span>
        </div>
        <div>
            <strong>Comisión Cancelada:</strong><br>
            <span style="font-size: 1.3em; font-weight: bold; color: #f59e0b;">$<?php echo number_format($cancelacion['monto_comision_cancelada'], 2); ?></span>
        </div>
    </div>
</div>

<!-- Enganche -->
<div style="background: <?php echo $bg_enganche; ?>; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid <?php echo $color_enganche; ?>;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;">
        <i class='bx bx-wallet'></i> Enganche
    </h3>
    <div style="display: flex; align-items: center; gap: 12px;">
        <i class='bx <?php echo $icono_enganche; ?>' style="font-size: 32px; color: <?php echo $color_icono_enganche; ?>;"></i>
        <div style="flex: 1;">
            <strong style="display: block; font-size: 1.1rem; color: <?php echo $color_texto_enganche; ?>; margin-bottom: 5px;">
                Monto: $<?php echo number_format($cancelacion['enganche'], 2); ?>
            </strong>
            <span style="color: <?php echo $color_span_enganche; ?>; font-weight: 600;">
                <?php if ($cancelacion['enganche_devuelto'] == 1): ?>
                    ✅ DEVUELTO al cliente
                <?php else: ?>
                    ❌ NO devuelto
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Productos Recuperados -->
<div style="margin: 20px 0;">
    <h3 style="color: var(--color-primary); margin-bottom: 15px;">
        <i class='bx bx-package'></i> Productos Recuperados (<?php echo count($productos_recuperados); ?>)
    </h3>
    <?php if (count($productos_recuperados) > 0): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: linear-gradient(90deg, #0c3c78, #1e5799); color: white;">
                <tr>
                    <th style="padding: 12px; text-align: left;">Producto</th>
                    <th style="padding: 12px; text-align: center;">Cantidad</th>
                    <th style="padding: 12px; text-align: center;">Estado</th>
                    <th style="padding: 12px; text-align: center;">Fecha de Recuperación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos_recuperados as $prod): 
                    $est = $estados_producto[$prod['estado']] ?? ['color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => 'bx-help-circle', 'texto' => 'Desconocido'];
                ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px;"><?php echo htmlspecialchars($prod['producto_nombre']); ?></td>
                        <td style="padding: 12px; text-align: center; font-weight: 700;"><?php echo $prod['cantidad']; ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: <?php echo $est['bg']; ?>; color: <?php echo $est['color']; ?>; border-radius: 12px; font-weight: 600; font-size: 0.9rem; border: 1px solid <?php echo $est['color']; ?>;">
                                <i class='bx <?php echo $est['icon']; ?>'></i>
                                <?php echo $est['texto']; ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;"><?php echo date('d/m/Y', strtotime($prod['fecha_recuperacion'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: #6b7280;">
            <i class='bx bx-package' style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            <p>No se recuperaron productos</p>
        </div>
    <?php endif; ?>
</div>

<!-- Observaciones -->
<?php if (!empty($cancelacion['observaciones'])): ?>
    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
        <h3 style="color: #856404; margin-bottom: 10px;">
            <i class='bx bx-note'></i> Observaciones
        </h3>
        <p style="color: #856404; line-height: 1.8; white-space: pre-wrap;">
            <?php echo nl2br(htmlspecialchars($cancelacion['observaciones'])); ?>
        </p>
    </div>
<?php endif; ?>

<!-- Botón Cerrar -->
<div style="text-align: right; padding-top: 20px; border-top: 2px solid #e5e7eb;">
    <button onclick="cerrarModal()" class="btn btn-secondary" style="padding: 12px 24px; background: #6b7280; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
        <i class='bx bx-x'></i> Cerrar
    </button>
</div>

<style>
.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>