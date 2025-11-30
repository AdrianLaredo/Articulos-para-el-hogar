<?php
session_start();
require_once '../../bd/database.php';

// Verificar método y permisos
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contratos.php');
    exit;
}

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'admin') {
    $_SESSION['mensaje'] = 'No tienes permisos para cancelar folios';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: contratos.php');
    exit;
}

// Obtener datos del formulario
$id_folio = intval($_POST['id_folio']);
$id_empleado = intval($_POST['id_empleado']);
$motivo = $_POST['motivo_cancelacion'];
$observaciones = trim($_POST['observaciones']);
// Eliminamos la dependencia de $_POST['productos_recuperados'] ya que lo vamos a calcular
$descontar_comision = isset($_POST['descontar_comision']) ? 1 : 0;

try {
    $conn->beginTransaction();
    
    // 1. Obtener información del folio
    $sql = "SELECT * FROM Folios_Venta WHERE id_folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_folio]);
    $folio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folio) {
        throw new Exception('Folio no encontrado');
    }
    
    $estado_actual = isset($folio['estado']) ? $folio['estado'] : 'activo';
    if ($estado_actual == 'cancelado') {
        throw new Exception('Este folio ya está cancelado');
    }
    
    // 2. Determinar si se devuelve enganche
    $devuelve_enganche = ($motivo === 'morosidad_inmediata') ? 1 : 0;
    
    // 3. Calcular comisión total a cancelar
    $sql_comision = "SELECT SUM(monto_comision) as total 
                     FROM Detalle_Folio_Venta 
                     WHERE id_folio = ? AND COALESCE(comision_cancelada, 0) = 0";
    $stmt_comision = $conn->prepare($sql_comision);
    $stmt_comision->execute([$id_folio]);
    $comision_total = $stmt_comision->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 4. Insertar en tabla de Cancelaciones
    $fecha_hora_cancelacion = date('Y-m-d H:i:s');

    $sql_cancel = "INSERT INTO Cancelaciones_Folios 
                   (id_folio, motivo, observaciones, enganche_devuelto, 
                    monto_comision_cancelada, descontar_comision,
                    fecha_cancelacion, usuario_cancela)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_cancel = $conn->prepare($sql_cancel);
    $stmt_cancel->execute([
        $id_folio,
        $motivo,
        $observaciones,
        $devuelve_enganche,
        $comision_total,
        $descontar_comision,
        $fecha_hora_cancelacion,
        $_SESSION['id']
    ]);
    
    $id_cancelacion = $conn->lastInsertId();
    
    // 5. Actualizar estado del folio
    $sql_update = "UPDATE Folios_Venta 
                   SET estado = 'cancelado'
                   WHERE id_folio = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$id_folio]);
    
    // 6. Marcar comisiones como canceladas
    $sql_comision_update = "UPDATE Detalle_Folio_Venta 
                             SET comision_cancelada = 1
                             WHERE id_folio = ?";
    $stmt_comision_update = $conn->prepare($sql_comision_update);
    $stmt_comision_update->execute([$id_folio]);
    
    // 6.5 ✅ NUEVO: Guardar nuevas comisiones asignadas durante la cancelación
    $fecha_registro_comision = date('Y-m-d H:i:s');
    
    foreach ($_POST as $key => $value) {
        // Buscar el patrón: comision_{ID_PRODUCTO}
        if (preg_match('/^comision_(\d+)$/', $key, $matches)) {
            $id_producto = $matches[1];
            $monto_nueva_comision = floatval($value);
            
            // Solo guardar si la nueva comisión es mayor a 0
            if ($monto_nueva_comision > 0) {
                $sql_nueva_comision = "INSERT INTO Comisiones_Cancelaciones 
                                       (id_cancelacion, id_folio, id_empleado, id_producto, monto_comision, fecha_registro)
                                       VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_nueva_comision = $conn->prepare($sql_nueva_comision);
                $stmt_nueva_comision->execute([
                    $id_cancelacion,
                    $id_folio,
                    $id_empleado,
                    $id_producto,
                    $monto_nueva_comision,
                    $fecha_registro_comision
                ]);
            }
        }
    }
    
    // =========================================================================
    // 7. LÓGICA CORREGIDA: Registrar productos recuperados (Agrupando unidades)
    // =========================================================================
    $recuperados_agrupados = [];

    // Recorrer todos los datos POST para encontrar los productos recuperados unidad por unidad
    foreach ($_POST as $key => $value) {
        // Busca el patrón: recuperado_{ID_PRODUCTO}_{NUM_UNIDAD}
        // $key es ej: "recuperado_4_1" o "recuperado_10_3"
        // $value es "1" si el checkbox fue marcado
        if (preg_match('/^recuperado_(\d+)_(\d+)$/', $key, $matches) && $value == 1) {
            $id_producto = $matches[1];
            $num_unidad = $matches[2];
            
            // Obtener el estado para esta unidad. La clave debe ser: estado_{ID_PRODUCTO}_{NUM_UNIDAD}
            $estado_key = "estado_{$id_producto}_{$num_unidad}";
            $estado = $_POST[$estado_key] ?? ''; 

            // Solo procesar si se marcó como recuperado y tiene un estado
            if (!empty($estado)) {
                // Agrupar por Producto y Estado (ej: '4_bueno', '10_danado')
                $clave = "{$id_producto}_{$estado}";
                if (!isset($recuperados_agrupados[$clave])) {
                    $recuperados_agrupados[$clave] = [
                        'id_producto' => $id_producto,
                        'estado' => $estado,
                        'cantidad' => 0
                    ];
                }
                // Contar la unidad
                $recuperados_agrupados[$clave]['cantidad']++;
            }
        }
    }

    // Insertar en la base de datos la cantidad total por cada producto y estado
    $fecha_recuperacion = date('Y-m-d H:i:s');
    
    foreach ($recuperados_agrupados as $item) {
        if ($item['cantidad'] > 0) {
            // Insertar en la tabla de productos recuperados
            $sql_prod_insert = "INSERT INTO Productos_Recuperados 
                                 (id_cancelacion, id_producto, cantidad, estado, fecha_recuperacion)
                                 VALUES (?, ?, ?, ?, ?)";
            $stmt_prod_insert = $conn->prepare($sql_prod_insert);
            $stmt_prod_insert->execute([
                $id_cancelacion, 
                $item['id_producto'], 
                $item['cantidad'], 
                $item['estado'], 
                $fecha_recuperacion
            ]);
            
            // ✅ NUEVO: Devolver el producto al inventario (incrementar stock)
            // Solo se actualiza el stock si el producto fue recuperado
            $sql_update_stock = "UPDATE Productos 
                                 SET stock = stock + ?,
                                     estado = CASE 
                                         WHEN estado IN ('agotado', 'descontinuado') THEN 'disponible'
                                         ELSE estado 
                                     END
                                 WHERE id_producto = ?";
            $stmt_update_stock = $conn->prepare($sql_update_stock);
            $stmt_update_stock->execute([$item['cantidad'], $item['id_producto']]);
        }
    }
    
    // =========================================================================
    // 8. Si se debe descontar comisión
    // =========================================================================
    if ($descontar_comision == 1 && $comision_total > 0) {
        $fecha_registro_descuento = date('Y-m-d H:i:s');
        
        $sql_descuento = "INSERT INTO Descuentos_Comision_Pendientes
                           (id_empleado, id_cancelacion, monto_descuento, aplicado, fecha_registro)
                           VALUES (?, ?, ?, 0, ?)";
        $stmt_descuento = $conn->prepare($sql_descuento);
        $stmt_descuento->execute([$id_empleado, $id_cancelacion, $comision_total, $fecha_registro_descuento]);
    }
    
    $conn->commit();
    
    $_SESSION['mensaje'] = "✅ Folio {$folio['numero_folio']} cancelado exitosamente";
    $_SESSION['tipo_mensaje'] = 'success';
    
    // Recargar todos los módulos afectados
    echo "<script>
        if (window.parent && window.parent.dashboardMenu) {
            window.parent.dashboardMenu.reloadSection('contratos');  // Módulo de contratos
            window.parent.dashboardMenu.reloadSection('contratosCancelados');  // Módulo de cancelados
            window.parent.dashboardMenu.reloadSection('inventario');  // ✅ Módulo de inventario
        }
        setTimeout(function() {
            window.location.href = 'contratos.php';
        }, 100);
    </script>";
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['mensaje'] = '❌ Error al cancelar folio: ' . $e->getMessage();
    $_SESSION['tipo_mensaje'] = 'error';
    
    header('Location: contratos.php');
    exit;
}