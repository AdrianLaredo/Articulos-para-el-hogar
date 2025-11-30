<?php
date_default_timezone_set('America/Mexico_City'); // <-- CORRECCIÓN: Establece la zona horaria
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
    exit('No autorizado');
}

$id_asignacion = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_asignacion <= 0) {
    exit('ID inválido');
}

// Obtener asignación
$asignacion = obtenerAsignacion($conn, $id_asignacion);
if (!$asignacion) {
    exit('Asignación no encontrada');
}

// Obtener productos
$productos = obtenerProductosAsignacion($conn, $id_asignacion);

// Obtener folios
$folios = obtenerFoliosAsignacion($conn, $id_asignacion);

// Obtener comisión total usando PDO
$sql_comision = "SELECT total_comision 
                 FROM Comisiones_Asignacion 
                 WHERE id_asignacion = :id_asignacion AND id_empleado = :id_empleado";
$stmt = $conn->prepare($sql_comision);
$stmt->bindParam(':id_asignacion', $asignacion['id_asignacion'], PDO::PARAM_INT);
$stmt->bindParam(':id_empleado', $asignacion['id_empleado'], PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$comision_total = $row ? $row['total_comision'] : 0;

// ============================================
// MODIFICADO: Usar precio_unitario en lugar de precio_venta
// ============================================
// Obtener detalles de cada folio
$folios_con_productos = [];
foreach ($folios as $folio) {
    $sql = "SELECT dfv.*, p.nombre
            FROM Detalle_Folio_Venta dfv
            INNER JOIN Productos p ON dfv.id_producto = p.id_producto
            WHERE dfv.id_folio = :id_folio";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_folio', $folio['id_folio']);
    $stmt->execute();
    $folio['productos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $folios_con_productos[] = $folio;
}
$folios = $folios_con_productos;

// Calcular totales
$total_cargado = array_sum(array_column($productos, 'cantidad_cargada'));
$total_vendido = array_sum(array_column($productos, 'cantidad_vendida'));
$total_devuelto = array_sum(array_column($productos, 'cantidad_devuelta'));


// Calcular comisión total de la asignación
$sql_comision_total = "SELECT SUM(dfv.monto_comision) as total_comision,
                             SUM(dfv.subtotal) as total_vendido
                      FROM Folios_Venta fv
                      INNER JOIN Detalle_Folio_Venta dfv ON fv.id_folio = dfv.id_folio
                      WHERE fv.id_asignacion = :id_asignacion";
$stmt_com = $conn->prepare($sql_comision_total);
$stmt_com->bindParam(':id_asignacion', $id_asignacion);
$stmt_com->execute();
$comision_data = $stmt_com->fetch(PDO::FETCH_ASSOC);
$comision_total = $comision_data['total_comision'] ?? 0;
$venta_total = $comision_data['total_vendido'] ?? 0;

// ============================================
// OBTENER TRASPASOS ENVIADOS Y RECIBIDOS
// ============================================
$traspasos_enviados = obtenerTraspasosEnviados($conn, $id_asignacion);
$traspasos_recibidos = obtenerTraspasosRecibidos($conn, $id_asignacion);
?>

<h2><i class='bx bx-file-blank'></i> Asignación #<?php echo str_pad($asignacion['id_asignacion'], 4, '0', STR_PAD_LEFT); ?></h2>

<!-- Información General -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="color: #0c3c78; margin-bottom: 15px;"><i class='bx bx-info-circle'></i> Información General</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <strong>Empleado:</strong><br>
            <?php echo htmlspecialchars($asignacion['nombre_empleado']); ?>
        </div>
        <div>
            <strong>Vehículo:</strong><br>
            <?php echo htmlspecialchars($asignacion['marca'] . ' ' . $asignacion['modelo'] . ' (' . $asignacion['placas'] . ')'); ?>
        </div>
        <div>
            <strong>Salida:</strong><br>
            <?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_hora_salida'])); ?>
        </div>
        <div>
            <strong>Regreso:</strong><br>
            <?php 
            if ($asignacion['fecha_hora_regreso']) {
                echo date('d/m/Y H:i', strtotime($asignacion['fecha_hora_regreso']));
            } else {
                echo '<span style="color: #991b1b;">En ruta</span>';
            }
            ?>
        </div>
        <div>
            <strong>Estado:</strong><br>
            <span style="display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; background: <?php echo $asignacion['estado'] == 'abierta' ? '#fef3c7' : '#d1fae5'; ?>; color: <?php echo $asignacion['estado'] == 'abierta' ? '#92400e' : '#065f46'; ?>;">
                <?php echo ucfirst($asignacion['estado']); ?>
            </span>
        </div>
<div>
    <strong>Tiempo en ruta:</strong><br>
    <?php 
        if ($asignacion['fecha_hora_regreso']) {
            // Las fechas ya están en hora local - no necesitan conversión
            $fecha_salida = new DateTime($asignacion['fecha_hora_salida']);
            $fecha_regreso = new DateTime($asignacion['fecha_hora_regreso']);
            
            // Si el regreso es igual o anterior a la salida, tiempo = 0
            if ($fecha_regreso <= $fecha_salida) {
                echo '0h 0m';
            } else {
                $intervalo = $fecha_salida->diff($fecha_regreso);
                $horas = ($intervalo->days * 24) + $intervalo->h;
                echo $horas . 'h ' . $intervalo->i . 'm';
            }
        } else {
            echo '<span style="color: #991b1b;">En ruta</span>';
        }
    ?>
</div>
        <div>
            <strong>Comisión Total:</strong><br>
            <span style="color: #3b82f6; font-weight: bold; font-size: 1.2em;">
                $<?php echo number_format($comision_total, 2); ?>
            </span>
        </div>
    </div>
</div>

<!-- Productos -->
<div style="margin: 20px 0;">
    <h3 style="color: #0c3c78; margin-bottom: 15px;"><i class='bx bx-package'></i> Productos</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead style="background: linear-gradient(90deg, #0c3c78, #1e5799); color: white;">
            <tr>
                <th style="padding: 12px; text-align: left;">Producto</th>
                <th style="padding: 12px; text-align: center;">Cargado</th>
                <th style="padding: 12px; text-align: center;">Vendido</th>
                <th style="padding: 12px; text-align: center;">Devuelto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $prod): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                    <td style="padding: 12px; text-align: center; font-weight: 700;"><?php echo $prod['cantidad_cargada']; ?></td>
                    <td style="padding: 12px; text-align: center; font-weight: 700;"><?php echo $prod['cantidad_vendida']; ?></td>
                    <td style="padding: 12px; text-align: center; font-weight: 700;"><?php echo $prod['cantidad_devuelta']; ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="background: #f3f4f6; font-weight: 700;">
                <td style="padding: 12px;">TOTAL</td>
                <td style="padding: 12px; text-align: center;"><?php echo $total_cargado; ?></td>
                <td style="padding: 12px; text-align: center;"><?php echo $total_vendido; ?></td>
                <td style="padding: 12px; text-align: center;"><?php echo $total_devuelto; ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ============================================ -->
<!-- TRASPASOS RECIBIDOS -->
<!-- ============================================ -->
<?php if (count($traspasos_recibidos) > 0): ?>
    <div style="margin: 20px 0;">
        <h3 style="color: #059669; margin-bottom: 15px;">
            <i class='bx bx-import'></i> Traspasos Recibidos (<?php echo count($traspasos_recibidos); ?>)
        </h3>
        
        <?php foreach ($traspasos_recibidos as $traspaso): ?>
            <div style="background: #d1fae5; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #059669;">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items: center;">
                    <div>
                        <strong style="color: #065f46;">📦 Producto:</strong>
                        <span style="font-size: 1.1em;"><?php echo htmlspecialchars($traspaso['nombre_producto']); ?></span>
                    </div>
                    <div>
                        <strong style="color: #065f46;">Cantidad:</strong>
                        <span style="font-size: 1.2em; font-weight: 700; color: #059669;">
                            +<?php echo $traspaso['cantidad']; ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color: #065f46;">Fecha:</strong>
                        <span><?php echo date('d/m/Y H:i', strtotime($traspaso['fecha_hora_traspaso'])); ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #a7f3d0;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <div>
                            <strong style="color: #065f46;">👤 Proveniente de:</strong>
                            <span><?php echo htmlspecialchars($traspaso['empleado_origen']); ?></span>
                            <span style="color: #6b7280; font-size: 0.9em;">
                                (Asig. #<?php echo str_pad($traspaso['id_asignacion_origen'], 4, '0', STR_PAD_LEFT); ?>)
                            </span>
                        </div>
                        
                        <?php if (!empty($traspaso['observaciones'])): ?>
                            <div style="grid-column: span 2;">
                                <div style="color: #6b7280; font-size: 0.85rem; font-style: italic;">
                                    <i class='bx bx-note' style="color: #059669;"></i> 
                                    <strong>Observación:</strong> <?php echo htmlspecialchars($traspaso['observaciones']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- TRASPASOS ENVIADOS -->
<!-- ============================================ -->
<?php if (count($traspasos_enviados) > 0): ?>
    <div style="margin: 20px 0;">
        <h3 style="color: #dc2626; margin-bottom: 15px;">
            <i class='bx bx-export'></i> Traspasos Enviados (<?php echo count($traspasos_enviados); ?>)
        </h3>
        
        <?php foreach ($traspasos_enviados as $traspaso): ?>
            <div style="background: #fee2e2; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #dc2626;">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items: center;">
                    <div>
                        <strong style="color: #991b1b;">📦 Producto:</strong>
                        <span style="font-size: 1.1em;"><?php echo htmlspecialchars($traspaso['nombre_producto']); ?></span>
                    </div>
                    <div>
                        <strong style="color: #991b1b;">Cantidad:</strong>
                        <span style="font-size: 1.2em; font-weight: 700; color: #dc2626;">
                            -<?php echo $traspaso['cantidad']; ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color: #991b1b;">Fecha:</strong>
                        <span><?php echo date('d/m/Y H:i', strtotime($traspaso['fecha_hora_traspaso'])); ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #fecaca;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <div>
                            <strong style="color: #991b1b;">👤 Traspasado a:</strong>
                            <span><?php echo htmlspecialchars($traspaso['empleado_destino']); ?></span>
                            <span style="color: #6b7280; font-size: 0.9em;">
                                (Asig. #<?php echo str_pad($traspaso['id_asignacion_destino'], 4, '0', STR_PAD_LEFT); ?>)
                            </span>
                        </div>
                        
                        <?php if (!empty($traspaso['observaciones'])): ?>
                            <div style="grid-column: span 2;">
                                <div style="color: #6b7280; font-size: 0.85rem; font-style: italic;">
                                    <i class='bx bx-note' style="color: #10b981;"></i> 
                                    <strong>Observación:</strong> <?php echo htmlspecialchars($traspaso['observaciones']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Folios de Venta -->
<?php if (count($folios) > 0): ?>
    <div style="margin: 20px 0;">
        <h3 style="color: #0c3c78; margin-bottom: 15px;"><i class='bx bx-receipt'></i> Folios de Venta (<?php echo count($folios); ?>)</h3>
        
        <?php foreach ($folios as $folio): ?>
            <?php
                // Calcular comisión total por folio
                $sql_comision_folio = "SELECT SUM(monto_comision) as total_comisiones
                                       FROM Detalle_Folio_Venta
                                       WHERE id_folio = :id_folio";
                $stmt_com = $conn->prepare($sql_comision_folio);
                $stmt_com->bindParam(':id_folio', $folio['id_folio'], PDO::PARAM_INT);
                $stmt_com->execute();
                $comision_data = $stmt_com->fetch(PDO::FETCH_ASSOC);
                $folio['total_comisiones'] = $comision_data['total_comisiones'] ?? 0;
            ?>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3b82f6;">
                <h4 style="color: #0c3c78; margin-bottom: 10px;">
Folio #<?php echo htmlspecialchars($folio['numero_folio']); ?> - <?php echo date('d/m/Y H:i', strtotime($folio['fecha_hora_venta'])); ?>                </h4>
                
                <div style="margin-bottom: 15px;">
                    <strong>Cliente:</strong> <?php echo htmlspecialchars($folio['nombre_cliente']); ?><br>
                    <?php if ($folio['zona']): ?>
                        <strong>Zona:</strong> <?php echo htmlspecialchars($folio['zona']); ?><br>
                    <?php endif; ?>
                    <?php if ($folio['direccion']): ?>
                        <strong>Dirección:</strong> <?php echo htmlspecialchars($folio['direccion']); ?>
                    <?php endif; ?>
                </div>
                
                <!-- Tabla de Productos -->
                <table style="width: 100%; font-size: 0.9rem; margin-bottom: 10px;">
                    <thead style="background: #e5e7eb;">
                        <tr>
                            <th style="padding: 8px; text-align: left;">Producto</th>
                            <th style="padding: 8px; text-align: center;">Cantidad</th>
                            <th style="padding: 8px; text-align: right;">Precio Unit.</th>
                            <th style="padding: 8px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_folio = 0;
                        foreach ($folio['productos'] as $prod): 
                            // ============================================
                            // MODIFICADO: Usar precio_unitario en lugar de precio_venta
                            // ============================================
                            $subtotal = $prod['cantidad_vendida'] * $prod['precio_unitario'];
                            $total_folio += $subtotal;
                        ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 8px;"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                <td style="padding: 8px; text-align: center; font-weight: 700;"><?php echo $prod['cantidad_vendida']; ?></td>
                                <td style="padding: 8px; text-align: right;">$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                                <td style="padding: 8px; text-align: right; font-weight: 700;">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: 700;">
                            <td colspan="3" style="padding: 8px; text-align: right;">TOTAL:</td>
                            <td style="padding: 8px; text-align: right; color: #0c3c78; font-size: 1.1rem;">
                                $<?php echo number_format($total_folio, 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Desglose Completo -->
                <div class="desglose-venta" style="display: block; margin-top: 15px;">
                    <h5 style="color: #6b7280; margin-bottom: 10px;">
                        <i class='bx bx-list-ul'></i> Desglose de la Venta
                    </h5>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <?php 
                            $enganche = $folio['enganche'] ?? 0;
                            $comisiones = $folio['total_comisiones'] ?? 0;
                            $saldo = $total_folio - $enganche;
                        ?>
                        <table style="width: 100%;">
                            <tr>
                                <td>Total de Venta:</td>
                                <td style="text-align: right;">$<?php echo number_format($total_folio, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Enganche Recibido:</td>
                                <td style="text-align: right; color: #10b981;">$<?php echo number_format($enganche, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Total Comisiones:</td>
                                <td style="text-align: right; color: #3b82f6;">$<?php echo number_format($comisiones, 2); ?></td>
                            </tr>
                            <tr style="border-top: 2px solid #e5e7eb; font-weight: bold;">
                                <td>Saldo Pendiente:</td>
                                <td style="text-align: right; color: #dc2626;">$<?php echo number_format($saldo, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;">Tipo de Venta:</td>
                                <td style="text-align: right; padding: 5px;">
                                    <?php 
                                        $tipo_venta_bg = $saldo > 0 ? '#fee2e2' : '#d1fae5';
                                        $tipo_venta_color = $saldo > 0 ? '#991b1b' : '#065f46';
                                        $tipo_venta_text = $saldo > 0 ? 'Crédito' : 'Contado';
                                    ?>
                                    <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.9em; font-weight: 600; 
                                                 background: <?php echo $tipo_venta_bg; ?>; 
                                                 color: <?php echo $tipo_venta_color; ?>;">
                                        <?php echo $tipo_venta_text; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Resumen Total de Comisiones -->
    <div style="background: linear-gradient(135deg, #d4edda, #c3ddd6); padding: 20px; border-radius: 8px; margin-top: 20px;">
        <h4 style="color: #065f46; margin-bottom: 10px;">
            <i class='bx bx-calculator'></i> Resumen de Comisiones
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <strong>Total Vendido:</strong><br>
                <span style="font-size: 1.3em; font-weight: bold;">$<?php echo number_format($venta_total, 2); ?></span>
            </div>
            <div>
                <strong>Total Comisiones:</strong><br>
                <span style="font-size: 1.3em; font-weight: bold; color: #3b82f6;">$<?php echo number_format($comision_total, 2); ?></span>
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="text-align: center; padding: 30px; color: #6b7280;">
        <p>No se generaron folios de venta en esta asignación</p>
    </div>
<?php endif; ?>

<!-- Foto de Evidencia -->
<?php if ($asignacion['foto_salida']): ?>
    <div style="margin: 20px 0;">
        <h3 style="color: #0c3c78; margin-bottom: 15px;"><i class='bx bx-image'></i> Foto de Evidencia</h3>
        <img src="assets/images/<?php echo htmlspecialchars($asignacion['foto_salida']); ?>" 
             alt="Foto de evidencia" 
             style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    </div>
<?php endif; ?>