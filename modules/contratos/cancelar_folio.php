<?php
session_start();
require_once '../../bd/database.php';

// Verificar permisos (solo admin)
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'admin') {
    exit('<div style="text-align: center; padding: 40px; color: red;">
            <i class="bx bx-error-circle" style="font-size: 48px;"></i>
            <h3>Acceso Denegado</h3>
            <p>Solo los administradores pueden cancelar folios.</p>
          </div>');
}

$id_folio = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_folio <= 0) {
    exit('<p style="color: red; text-align: center;">ID de folio inválido</p>');
}

// Obtener datos completos del folio
$sql = "SELECT 
            fv.*,
            (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as vendedor,
            e.id_empleado,
            a.id_asignacion
        FROM Folios_Venta fv
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        WHERE fv.id_folio = :id_folio";

$stmt = $conn->prepare($sql);
$stmt->execute([':id_folio' => $id_folio]);
$folio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$folio) {
    exit('<p style="color: red; text-align: center;">Folio no encontrado</p>');
}

// Verificar si ya está cancelado
$estado = isset($folio['estado']) ? $folio['estado'] : 'activo';
if ($estado == 'cancelado') {
    exit('<div style="text-align: center; padding: 40px; color: orange;">
            <i class="bx bx-info-circle" style="font-size: 48px;"></i>
            <h3>Folio Ya Cancelado</h3>
            <p>Este folio ya ha sido cancelado previamente.</p>
          </div>');
}

// Obtener productos del folio
$sql_prod = "SELECT 
                dfv.*,
                p.nombre as producto_nombre,
                p.precio_venta
             FROM Detalle_Folio_Venta dfv
             INNER JOIN Productos p ON dfv.id_producto = p.id_producto
             WHERE dfv.id_folio = :id_folio";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->execute([':id_folio' => $id_folio]);
$productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// Calcular cuánto pagó y qué productos alcanzó a cubrir
$total_venta = $folio['total_venta'];
$total_pagado = $total_venta - $folio['saldo_pendiente'];

// Calcular comisión total y proporcional
$comision_total = 0;
$productos_con_comision = [];
$monto_acumulado = 0;

foreach ($productos as $prod) {
    $comision_total += $prod['monto_comision'];
    
    $costo_producto = $prod['precio_unitario'] * $prod['cantidad_vendida'];
    
    if ($monto_acumulado + $costo_producto <= $total_pagado) {
        $productos_con_comision[] = [
            'id_producto' => $prod['id_producto'],
            'cantidad_pagada' => $prod['cantidad_vendida'],
            'comision' => $prod['monto_comision']
        ];
        $monto_acumulado += $costo_producto;
    } else {
        $saldo_disponible = $total_pagado - $monto_acumulado;
        $cantidad_pagada = floor($saldo_disponible / $prod['precio_unitario']);
        
        if ($cantidad_pagada > 0) {
            $comision_proporcional = ($prod['monto_comision'] / $prod['cantidad_vendida']) * $cantidad_pagada;
            $productos_con_comision[] = [
                'id_producto' => $prod['id_producto'],
                'cantidad_pagada' => $cantidad_pagada,
                'comision' => $comision_proporcional
            ];
        }
        break;
    }
}

$comision_proporcional = array_sum(array_column($productos_con_comision, 'comision'));
?>

<style>
/* Estilos base */
.info-folio-cancelar {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid #3b82f6;
}

.info-folio-cancelar h3 {
    color: #3b82f6;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.grid-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.grid-info > div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.grid-info strong {
    color: #6b7280;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.highlight-text {
    color: #3b82f6;
    font-weight: 700;
    font-size: 1.1rem;
}

.money-text { color: #111827; font-weight: 600; }
.money-positive { color: #10b981; font-weight: 600; }
.money-negative { color: #ef4444; font-weight: 600; }
.money-neutral { color: #3b82f6; font-weight: 600; }
.money-info { color: #f59e0b; font-weight: 600; }

.form-group {
    margin-bottom: 20px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-help {
    display: block;
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6b7280;
}

.productos-section {
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.productos-section h4 {
    color: #3b82f6;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.productos-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.producto-item {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
}

.producto-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.producto-nombre {
    font-weight: 700;
    font-size: 1.1rem;
    color: #111827;
}

.producto-detalles {
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 4px;
}

.badge-pagado {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #d1fae5;
    color: #065f46;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    border: 1px solid #10b981;
}

.badge-no-pagado {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fee2e2;
    color: #991b1b;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    border: 1px solid #ef4444;
}

.unidades-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.unidad-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 8px;
    border-left: 3px solid #0ea5e9;
}

.unidad-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    flex-shrink: 0;
}

.unidad-numero {
    font-weight: 600;
    color: #0c4a6e;
    min-width: 100px;
}

.estado-select {
    padding: 8px 12px;
    border: 2px solid #0ea5e9;
    border-radius: 6px;
    font-weight: 600;
    color: #0c4a6e;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

.estado-select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.contador-recuperados {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #dbeafe;
    color: #1e40af;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
}

.comision-ajustable {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #f59e0b;
    margin-top: 15px;
}

.comision-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.comision-label {
    font-weight: 600;
    color: #78350f;
    font-size: 0.95rem;
}

.comision-input {
    width: 120px;
    padding: 10px;
    border: 2px solid #f59e0b;
    border-radius: 6px;
    font-weight: 700;
    font-size: 1rem;
    text-align: center;
}

.comision-sugerida {
    font-size: 0.85rem;
    color: #92400e;
    font-style: italic;
}

.calculo-automatico {
    background: linear-gradient(135deg, #f0f7ff, #e0f2fe);
    padding: 20px;
    border-radius: 8px;
    margin: 25px 0;
    border-left: 4px solid #3b82f6;
}

.calculo-automatico h4 {
    color: #3b82f6;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.grid-calculo {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.calculo-item {
    background: white;
    padding: 15px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid #e0e0e0;
}

.calculo-item.danger {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.calculo-item.success {
    background: #d1fae5;
    border-left-color: #10b981;
}

.calculo-item.warning {
    background: #fef3c7;
    border-left-color: #f59e0b;
}

.calculo-label {
    font-size: 0.9rem;
    color: #6b7280;
    font-weight: 500;
}

.calculo-value {
    font-size: 1.2rem;
    font-weight: 700;
}

.calculo-value.danger-text { color: #ef4444; }
.calculo-value.success-text { color: #10b981; }
.calculo-value.warning-text { color: #f59e0b; }

.alerta-enganche {
    background: #fef3c7;
    border: 2px solid #f59e0b;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.alerta-enganche i {
    font-size: 24px;
    color: #f59e0b;
    flex-shrink: 0;
}

.alerta-enganche-texto {
    color: #78350f;
    font-size: 0.9rem;
    line-height: 1.6;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
}

.confirmacion-section {
    margin: 25px 0;
    padding: 20px;
    background: #fff3cd;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.checkbox-confirm {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}
</style>

<!-- Formulario de cancelación -->
<form id="formCancelacion" method="POST" action="procesar_cancelacion.php">
    <input type="hidden" name="id_folio" value="<?php echo $id_folio; ?>">
    <input type="hidden" name="id_empleado" value="<?php echo $folio['id_empleado']; ?>">
    
    <!-- Información del folio -->
    <div class="info-folio-cancelar">
        <h3><i class='bx bx-file-text'></i> Datos del Folio</h3>
        <div class="grid-info">
            <div>
                <strong>Folio:</strong> 
                <span class="highlight-text"><?php echo htmlspecialchars($folio['numero_folio']); ?></span>
            </div>
            <div>
                <strong>Cliente:</strong> 
                <?php echo htmlspecialchars($folio['nombre_cliente']); ?>
            </div>
            <div>
                <strong>Vendedor:</strong> 
                <?php echo htmlspecialchars($folio['vendedor']); ?>
            </div>
            <div>
                <strong>Total Venta:</strong> 
                <span class="money-text">$<?php echo number_format($folio['total_venta'], 2); ?></span>
            </div>
            <div>
                <strong>Enganche:</strong> 
                <span class="money-positive">$<?php echo number_format($folio['enganche'], 2); ?></span>
            </div>
            <div>
                <strong>Total Pagado:</strong> 
                <span class="money-neutral">$<?php echo number_format($total_pagado, 2); ?></span>
            </div>
            <div>
                <strong>Saldo Pendiente:</strong> 
                <span class="money-negative">$<?php echo number_format($folio['saldo_pendiente'], 2); ?></span>
            </div>
            <div>
                <strong>Comisión Total:</strong> 
                <span class="money-info">$<?php echo number_format($comision_total, 2); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Motivo de cancelación -->
    <div class="form-group">
        <label for="motivoCancelacion">
            <i class='bx bx-error-circle'></i> Motivo de Cancelación *
        </label>
        <select name="motivo_cancelacion" id="motivoCancelacion" required class="form-control">
            <option value="">-- Seleccione un motivo --</option>
            <option value="morosidad_tardia">Morosidad Tardía (cliente dejó de pagar)</option>
            <option value="morosidad_inmediata">Morosidad Detectada el Mismo Día (se devuelve enganche y NO hay comisión)</option>
            <option value="situacion_extraordinaria">Situación Extraordinaria</option>
            <option value="otro">Otro motivo</option>
        </select>
        <small class="form-help">
            <i class='bx bx-info-circle'></i> 
            <strong>Morosidad Inmediata:</strong> Se devuelve el enganche al cliente y el vendedor NO recibe comisión.
        </small>
    </div>
    
    <!-- Productos del folio -->
    <div class="productos-section">
        <h4><i class='bx bx-package'></i> Recuperación de Productos</h4>
        
        <div class="productos-list">
            <?php 
            foreach ($productos as $prod):
                // Determinar si este producto fue pagado
                $producto_pagado = false;
                $cantidad_pagada = 0;
                $comision_producto = 0;
                
                foreach ($productos_con_comision as $pc) {
                    if ($pc['id_producto'] == $prod['id_producto']) {
                        $producto_pagado = true;
                        $cantidad_pagada = $pc['cantidad_pagada'];
                        $comision_producto = $pc['comision'];
                        break;
                    }
                }
                
                $comision_unitaria = $prod['cantidad_vendida'] > 0 ? $prod['monto_comision'] / $prod['cantidad_vendida'] : 0;
            ?>
            <div class="producto-item" data-producto-id="<?php echo $prod['id_producto']; ?>">
                <div class="producto-header">
                    <div>
                        <div class="producto-nombre">
                            <?php echo htmlspecialchars($prod['producto_nombre']); ?>
                        </div>
                        <div class="producto-detalles">
                            Total: <?php echo $prod['cantidad_vendida']; ?> unidades • 
                            $<?php echo number_format($prod['precio_unitario'], 2); ?> c/u • 
                            Comisión: $<?php echo number_format($comision_unitaria, 2); ?> c/u
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <?php if ($producto_pagado): ?>
                            <span class="badge-pagado">
                                <i class='bx bx-check-circle'></i>
                                Cliente pagó <?php echo $cantidad_pagada; ?> de <?php echo $prod['cantidad_vendida']; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge-no-pagado">
                                <i class='bx bx-x-circle'></i>
                                NO pagado
                            </span>
                        <?php endif; ?>
                        <div class="contador-recuperados" id="contador_<?php echo $prod['id_producto']; ?>" style="margin-top: 8px;">
                            <i class='bx bx-package'></i>
                            Recuperados: <span class="num-recuperados">0</span> de <?php echo $prod['cantidad_vendida']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Unidades individuales -->
                <div class="unidades-container">
                    <?php for ($i = 1; $i <= $prod['cantidad_vendida']; $i++): ?>
                    <div class="unidad-item">
                        <input type="checkbox" 
                               class="unidad-checkbox"
                               name="recuperado_<?php echo $prod['id_producto']; ?>_<?php echo $i; ?>"
                               value="1"
                               data-producto-id="<?php echo $prod['id_producto']; ?>"
                               data-unidad="<?php echo $i; ?>">
                        
                        <span class="unidad-numero">Unidad <?php echo $i; ?></span>
                        
                        <select name="estado_<?php echo $prod['id_producto']; ?>_<?php echo $i; ?>" 
                                class="estado-select"
                                data-producto-id="<?php echo $prod['id_producto']; ?>"
                                disabled>
                            <option value="">-- Estado --</option>
                            <option value="bueno">✅ Buen estado</option>
                            <option value="danado">⚠️ Dañado</option>
                            <option value="incompleto">❌ Incompleto</option>
                        </select>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <!-- Comisión ajustable -->
                <div class="comision-ajustable">
                    <div class="comision-label">
                        💰 Comisión para este producto:
                    </div>
                    <div class="comision-input-group">
                        <span style="font-weight: 600; color: #78350f;">$</span>
                        <input type="number" 
                               name="comision_<?php echo $prod['id_producto']; ?>"
                               id="comision_<?php echo $prod['id_producto']; ?>"
                               class="comision-input"
                               min="0"
                               step="0.01"
                               value="<?php echo number_format($comision_producto, 2, '.', ''); ?>"
                               data-comision-sugerida="<?php echo number_format($comision_producto, 2, '.', ''); ?>"
                               data-comision-unitaria="<?php echo number_format($comision_unitaria, 2, '.', ''); ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <small class="form-help">
            <i class='bx bx-info-circle'></i> 
            <strong>Instrucciones:</strong> Marque cada unidad recuperada, indique su estado individual, y ajuste la comisión según corresponda.
        </small>
    </div>
    
    <!-- Alerta de enganche -->
    <div class="alerta-enganche" id="alertaEnganche" style="display: none;">
        <i class='bx bx-info-circle'></i>
        <div class="alerta-enganche-texto">
            <strong>ℹ️ Condición para devolver enganche:</strong><br>
            El enganche de <strong>$<?php echo number_format($folio['enganche'], 2); ?></strong> 
            solo se devolverá si <strong>TODOS</strong> los productos recuperados están en <strong>"Buen estado"</strong>.
            <br><br>
            <span id="estadoEnganche"></span>
        </div>
    </div>
    
    <!-- Cálculo de comisión -->
    <div class="calculo-automatico">
        <h4><i class='bx bx-calculator'></i> Resumen de Comisión</h4>
        <div class="grid-calculo">
            <div class="calculo-item">
                <div class="calculo-label">Comisión total original:</div>
                <div class="calculo-value">$<?php echo number_format($comision_total, 2); ?></div>
            </div>
            
            <div class="calculo-item success" id="comisionFinal" data-valor-original="$<?php echo number_format($comision_proporcional, 2); ?>">
                <div class="calculo-label">Comisión final del vendedor:</div>
                <div class="calculo-value success-text" id="valorComisionFinal">
                    $<?php echo number_format($comision_proporcional, 2); ?>
                </div>
            </div>
            
            <div class="calculo-item danger">
                <div class="calculo-label">Comisión a cancelar:</div>
                <div class="calculo-value danger-text" id="valorComisionCancelar">
                    $<?php echo number_format($comision_total - $comision_proporcional, 2); ?>
                </div>
            </div>
            
            <div class="calculo-item warning" id="engancheDevolucion" style="display: none;">
                <div class="calculo-label">💰 Enganche a devolver:</div>
                <div class="calculo-value warning-text">
                    $<?php echo number_format($folio['enganche'], 2); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Opciones -->
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="descontar_comision" value="1" checked>
            <span>
                <strong>Descontar comisión ya pagada de futuras comisiones del vendedor</strong>
            </span>
        </label>
    </div>
    
    <!-- Observaciones -->
    <div class="form-group">
        <label for="observaciones">
            <i class='bx bx-note'></i> Observaciones Adicionales
        </label>
        <textarea name="observaciones" 
                  id="observaciones" 
                  rows="4" 
                  class="form-control"
                  placeholder="Describa los detalles de la cancelación..."></textarea>
    </div>
    
    <!-- Confirmación -->
    <div class="confirmacion-section">
        <label class="checkbox-confirm">
            <input type="checkbox" name="confirmar" id="confirmarCheck" required>
            <span>
                <i class='bx bx-error-circle'></i>
                <strong>Confirmo que he verificado toda la información y autorizo la cancelación de este folio.</strong>
                Esta acción no se puede deshacer.
            </span>
        </label>
    </div>
    
    <!-- Inputs ocultos para el resumen -->
    <input type="hidden" name="comision_final_vendedor" id="inputComisionFinal" value="<?php echo $comision_proporcional; ?>">
    <input type="hidden" name="puede_devolver_enganche" id="inputPuedeEnganche" value="0">
    
    <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="cerrarModalCancelacion()">
            <i class='bx bx-x'></i> Cancelar
        </button>
        <button type="submit" class="btn btn-danger" id="btnSubmit">
            <i class='bx bx-x-circle'></i> Confirmar Cancelación
        </button>
    </div>
</form>