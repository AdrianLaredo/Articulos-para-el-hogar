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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validarCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_comision = $_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// ACTUALIZAR COMISIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $total_gasolina = floatval($_POST['total_gasolina']);
        $observaciones = trim($_POST['observaciones']);
        $estado = $_POST['estado'];
        
        // Obtener extras (montos y observaciones)
        $extras_montos = isset($_POST['extra_monto']) ? $_POST['extra_monto'] : [];
        $extras_observaciones = isset($_POST['extra_observacion']) ? $_POST['extra_observacion'] : [];
        
        if ($total_gasolina < 0) {
            $mensaje = "Error: La gasolina no puede ser negativa";
            $tipo_mensaje = "error";
        } else {
            try {
                $conn->beginTransaction();
                
                // Obtener datos actuales para recalcular
                $sql_actual = "SELECT * FROM Comisiones_Cobradores WHERE id_comision = :id";
                $stmt_actual = $conn->prepare($sql_actual);
                $stmt_actual->bindParam(':id', $id_comision);
                $stmt_actual->execute();
                $datos_actuales = $stmt_actual->fetch(PDO::FETCH_ASSOC);
                
                // Eliminar extras anteriores
                $sql_delete_extras = "DELETE FROM Extras_Comision WHERE id_comision = :id";
                $stmt_delete = $conn->prepare($sql_delete_extras);
                $stmt_delete->bindParam(':id', $id_comision);
                $stmt_delete->execute();
                
                // Insertar nuevos extras y calcular total
                $total_extras = 0;
                foreach ($extras_montos as $index => $monto) {
                    $monto = floatval($monto);
                    if ($monto > 0) {
                        $obs_extra = isset($extras_observaciones[$index]) ? trim($extras_observaciones[$index]) : '';
                        
                        $sql_insert_extra = "INSERT INTO Extras_Comision (id_comision, monto, observaciones) 
                                           VALUES (:id_comision, :monto, :observaciones)";
                        $stmt_insert = $conn->prepare($sql_insert_extra);
                        $stmt_insert->bindParam(':id_comision', $id_comision);
                        $stmt_insert->bindParam(':monto', $monto);
                        $stmt_insert->bindParam(':observaciones', $obs_extra);
                        $stmt_insert->execute();
                        
                        $total_extras += $monto;
                    }
                }
                
                // ✅ NUEVA FÓRMULA: Incluir comision_asignaciones, total_extras y prestamo_inhabilitado
                $comision_asignaciones = $datos_actuales['comision_asignaciones'] ?? 0;
                $prestamo_inhabilitado = $datos_actuales['prestamo_inhabilitado'] ?? 0;
                $total_comision = $datos_actuales['comision_cobro'] + $comision_asignaciones + $total_gasolina + $total_extras - $datos_actuales['prestamo'] + $prestamo_inhabilitado;
                
                // Determinar fecha_pago
                $fecha_pago = null;
                if ($estado === 'pagada' && !$datos_actuales['fecha_pago']) {
                    $fecha_pago = date('Y-m-d');
                } elseif ($estado === 'pagada' && $datos_actuales['fecha_pago']) {
                    $fecha_pago = $datos_actuales['fecha_pago'];
                }
                
                // Actualizar comisión
                $sql = "UPDATE Comisiones_Cobradores 
                        SET total_gasolina = :total_gasolina,
                            total_extras = :total_extras,
                            total_comision = :total_comision,
                            observaciones = :observaciones,
                            estado = :estado,
                            fecha_pago = :fecha_pago,
                            fecha_actualizacion = CURRENT_TIMESTAMP
                        WHERE id_comision = :id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':total_gasolina', $total_gasolina);
                $stmt->bindParam(':total_extras', $total_extras);
                $stmt->bindParam(':total_comision', $total_comision);
                $stmt->bindParam(':observaciones', $observaciones);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':fecha_pago', $fecha_pago);
                $stmt->bindParam(':id', $id_comision);
                $stmt->execute();
                
                $conn->commit();
                
                $mensaje = "✅ Comisión actualizada exitosamente";
                $tipo_mensaje = "success";
                
                // Redirigir usando JavaScript para que funcione en iframe
                echo "<script>
                    setTimeout(function() {
                        if (window.parent && window.parent !== window) {
                            window.parent.postMessage({
                                type: 'navigate',
                                page: 'ver_comision.php?id=$id_comision',
                                fullUrl: 'ver_comision.php?id=$id_comision'
                            }, '*');
                        } else {
                            window.location.href = 'ver_comision.php?id=$id_comision';
                        }
                    }, 2000);
                </script>";
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

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

// ✅ Si no existe comision_asignaciones en la vista, obtenerlo directamente
if (!isset($comision['comision_asignaciones'])) {
    $sql_comision_asig = "SELECT COALESCE(comision_asignaciones, 0) as comision_asignaciones 
                          FROM Comisiones_Cobradores 
                          WHERE id_comision = :id";
    $stmt_asig = $conn->prepare($sql_comision_asig);
    $stmt_asig->bindParam(':id', $id_comision);
    $stmt_asig->execute();
    $result_asig = $stmt_asig->fetch(PDO::FETCH_ASSOC);
    $comision['comision_asignaciones'] = $result_asig['comision_asignaciones'] ?? 0;
}

// Obtener extras existentes
$sql_extras = "SELECT * FROM Extras_Comision WHERE id_comision = :id ORDER BY id_extra";
$stmt_extras = $conn->prepare($sql_extras);
$stmt_extras->bindParam(':id', $id_comision);
$stmt_extras->execute();
$extras_existentes = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Editar Comisión - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
    <style>
        /* ESTILOS PARA EL RESUMEN DE COMISIÓN - MISMO DISEÑO QUE GENERAR COMISIÓN */
        .calculo-resumen {
            margin-top: 30px;
        }

        .calculo-resumen h3 {
            color: #333333;
            font-size: 20px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .calculo-resumen h3 i {
            font-size: 24px;
            color: #2196F3;
        }

        .tabla-resumen {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #e0e0e0;
        }

        .tabla-resumen tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .tabla-resumen tr:last-child {
            border-bottom: none;
        }

        .tabla-resumen tr:hover:not(.total-row) {
            background: #f9f9f9;
        }

        .tabla-resumen td {
            padding: 15px 20px;
            font-size: 15px;
        }

        .tabla-resumen td:first-child {
            font-weight: 500;
            color: #424242;
        }

        .tabla-resumen td:last-child {
            font-weight: 600;
        }

        .tabla-resumen .text-right {
            text-align: right;
        }

        .tabla-resumen .text-success {
            color: #4CAF50;
        }

        .tabla-resumen .text-danger {
            color: #f44336;
        }

        .tabla-resumen .total-row {
            background: linear-gradient(135deg, #0c3c78 0%, #1e5799 100%);
            color: white;
            font-size: 17px;
            font-weight: 700;
        }

        .tabla-resumen .total-row td {
            padding: 18px 20px;
        }

        .tabla-resumen .total-row:hover {
            background: linear-gradient(135deg, #0a2f5e 0%, #16447a 100%);
        }

        .icon-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-item i {
            font-size: 20px;
            color: #666;
        }

        .tabla-resumen .total-row .icon-item i {
            color: #FFD700;
            font-size: 22px;
        }

        .char-counter {
            display: block;
            text-align: right;
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.limit {
            color: #f44336;
            font-weight: bold;
        }

        /* ESTILOS PARA EXTRAS */
        .extras-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }

        .extras-section h3 {
            color: #333;
            font-size: 18px;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .extras-section h3 i {
            color: #10B981;
            font-size: 22px;
        }

        .extra-item {
            display: grid;
            grid-template-columns: 150px 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            align-items: start;
        }

        .extra-item input[type="number"] {
            padding: 8px 12px;
        }

        .extra-item input[type="text"] {
            padding: 8px 12px;
        }

        .btn-remove-extra {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-remove-extra:hover {
            background: #dc2626;
        }

        .btn-add-extra {
            background: #10B981;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-add-extra:hover {
            background: #059669;
        }

        .extras-info {
            background: #E0F2FE;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #0284C7;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0c4a6e;
        }

        .extras-info i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-page">
            <div class="header-content">
                <div class="titulo-seccion">
                    <h1><i class='bx bx-edit-alt'></i> Editar Comisión</h1>
                    <p>Modifica los datos de la comisión semanal</p>
                </div>
                <a href="#" onclick="navegarA('ver_comision.php?id=<?php echo $id_comision; ?>'); return false;" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Volver
                </a>
            </div>
        </div>

        <!-- Mensaje -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>" id="mensaje">
            <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <?php if ($comision['prestamo'] > 0): ?>
        <div class="card" style="background: #FFF3E0; border-left: 4px solid #FF9800;">
            <p style="margin: 0; font-size: 14px;">
                <i class='bx bx-info-circle' style="color: #FF9800;"></i>
                <strong>Nota sobre préstamos:</strong> El monto del préstamo ($<?php echo number_format($comision['prestamo'], 2); ?>) 
                fue descontado automáticamente al generar la comisión y ya está marcado como pagado. Este monto no se puede modificar.
            </p>
        </div>
        <?php endif; ?>

        <!-- Formulario de Edición -->
        <form method="POST" id="formEditar">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="actualizar">

            <div class="card">
                <h2><i class='bx bx-edit-alt'></i> Datos Editables</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="total_gasolina">
                            <i class='bx bxs-gas-pump'></i> Gasolina Semanal *
                        </label>
                        <input type="number" 
                               name="total_gasolina" 
                               id="total_gasolina" 
                               step="0.01" 
                               min="0"
                               value="<?php echo $comision['total_gasolina']; ?>"
                               required
                               oninput="calcularTotalFinal()">
                        <small class="form-text">Monto total en efectivo para gasolina</small>
                    </div>

                    <div class="form-group">
                        <label for="estado">
                            <i class='bx bx-toggle-right'></i> Estado *
                        </label>
                        <select name="estado" id="estado" required>
                            <option value="pendiente" <?php echo $comision['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="revisada" <?php echo $comision['estado'] == 'revisada' ? 'selected' : ''; ?>>Revisada</option>
                            <option value="pagada" <?php echo $comision['estado'] == 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                        </select>
                    </div>
                </div>

                <!-- SECCIÓN DE EXTRAS -->
                <div class="extras-section">
                    <h3><i class='bx bx-dollar-circle'></i> Montos Extras</h3>
                    <div class="extras-info">
                        <i class='bx bx-info-circle'></i>
                        <strong>Agrega cantidades extra</strong> para bonificaciones, compensaciones u otros conceptos. Las observaciones son opcionales.
                    </div>
                    
                    <div id="extras-container">
                        <?php if (count($extras_existentes) > 0): ?>
                            <?php foreach ($extras_existentes as $index => $extra): ?>
                            <div class="extra-item">
                                <input type="number" 
                                       name="extra_monto[]" 
                                       placeholder="$0.00" 
                                       step="0.01" 
                                       min="0"
                                       value="<?php echo $extra['monto']; ?>"
                                       oninput="calcularTotalFinal()"
                                       required>
                                <input type="text" 
                                       name="extra_observacion[]" 
                                       placeholder="Observaciones (opcional)"
                                       value="<?php echo htmlspecialchars($extra['observaciones']); ?>"
                                       maxlength="200">
                                <button type="button" class="btn-remove-extra" onclick="eliminarExtra(this)">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-extra" onclick="agregarExtra()">
                        <i class='bx bx-plus-circle'></i> Agregar Extra
                    </button>
                </div>

                <div class="form-group">
                    <label for="observaciones">
                        <i class='bx bx-note'></i> Observaciones Generales
                    </label>
                    <textarea name="observaciones" 
                              id="observaciones" 
                              rows="4" 
                              maxlength="500"><?php echo htmlspecialchars($comision['observaciones']); ?></textarea>
                    <small class="char-counter" id="counter-observaciones"><?php echo strlen($comision['observaciones']); ?>/500</small>
                </div>

                <div class="calculo-resumen">
                    <h3><i class='bx bx-calculator'></i> Cálculo Actualizado</h3>
                    
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
                                +$<?php echo number_format($comision['comision_asignaciones'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bxs-gas-pump' style="color: var(--success-color);"></i>
                                <strong>Gasolina</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<span id="display_gasolina"><?php echo number_format($comision['total_gasolina'], 2); ?></span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-dollar-circle' style="color: var(--success-color);"></i>
                                <strong>Extras</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<span id="display_extras"><?php echo number_format($comision['total_extras'], 2); ?></span>
                            </td>
                        </tr>
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
                            $prestamo_inh = floatval($comision['prestamo_inhabilitado'] ?? 0);
                            if ($prestamo_inh > 0): 
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color); background: #fff3e0;">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-info-circle' style="color: #f5576c;"></i>
                                <strong>Préstamo Inhabilitado (Absorbe Empresa)</strong>
                                <br><small style="color: #666; font-weight: normal;">No se descuenta al empleado</small>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: #f5576c;">
                                +$<?php echo number_format($prestamo_inh, 2); ?>
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
                                $<span id="display_total"><?php echo number_format($comision['total_comision'], 2); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>


                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Guardar Cambios
                    </button>
                    <a href="#" onclick="navegarA('ver_comision.php?id=<?php echo $id_comision; ?>'); return false;" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Función para navegar dentro del iframe o página normal
        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                console.log('🔄 Navegando a:', pagina);
                // Si está en iframe, navega usando postMessage
                window.parent.postMessage({
                    type: 'navigate', 
                    page: pagina,
                    fullUrl: pagina
                }, '*');
            } else {
                // Si está en página normal, navega directamente
                window.location.href = pagina;
            }
        }

        // Variables base para cálculo
        const comision_cobradores = <?php echo $comision['comision_cobro']; ?>;
        const comision_asignaciones = <?php echo $comision['comision_asignaciones']; ?>;
        const prestamo = <?php echo $comision['prestamo']; ?>;
        const prestamo_inhabilitado = <?php echo $comision['prestamo_inhabilitado'] ?? 0; ?>;

        // Función para agregar un nuevo extra
        function agregarExtra() {
            const container = document.getElementById('extras-container');
            const extraItem = document.createElement('div');
            extraItem.className = 'extra-item';
            extraItem.innerHTML = `
                <input type="number" 
                       name="extra_monto[]" 
                       placeholder="$0.00" 
                       step="0.01" 
                       min="0"
                       oninput="calcularTotalFinal()"
                       required>
                <input type="text" 
                       name="extra_observacion[]" 
                       placeholder="Observaciones (opcional)"
                       maxlength="200">
                <button type="button" class="btn-remove-extra" onclick="eliminarExtra(this)">
                    <i class='bx bx-trash'></i>
                </button>
            `;
            container.appendChild(extraItem);
            calcularTotalFinal();
        }

        // Función para eliminar un extra
        function eliminarExtra(button) {
            button.closest('.extra-item').remove();
            calcularTotalFinal();
        }

        // Función para calcular el total final
        function calcularTotalFinal() {
            const gasolina = parseFloat(document.getElementById('total_gasolina').value) || 0;
            
            // Calcular total de extras
            let totalExtras = 0;
            const extrasInputs = document.querySelectorAll('input[name="extra_monto[]"]');
            extrasInputs.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                totalExtras += valor;
            });
            
            // Calcular total final
            const total = comision_cobradores + comision_asignaciones + gasolina + totalExtras - prestamo + prestamo_inhabilitado;
            
            // Actualizar display
            document.getElementById('display_gasolina').textContent = gasolina.toFixed(2);
            document.getElementById('display_extras').textContent = totalExtras.toFixed(2);
            document.getElementById('display_total').textContent = total.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // Contador de caracteres
            const observaciones = document.getElementById('observaciones');
            const counter = document.getElementById('counter-observaciones');
            
            observaciones.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/500`;
                
                counter.classList.remove('warning', 'limit');
                if (length >= 500) {
                    counter.classList.add('limit');
                } else if (length >= 400) {
                    counter.classList.add('warning');
                }
            });

            // Si no hay extras, agregar uno por defecto vacío
            const container = document.getElementById('extras-container');
            if (container.children.length === 0) {
                // No agregar ninguno por defecto, el usuario decidirá si quiere agregar
            }

            // Calcular totales al cargar
            calcularTotalFinal();
        });
    </script>
    
    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>