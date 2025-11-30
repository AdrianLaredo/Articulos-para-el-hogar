<?php
/**
 * Funciones para el módulo de Folios de Venta
 */

/**
 * Obtener detalles completos de un folio
 */
function obtenerFolioCompleto($conn, $id_folio) {
    // Información básica del folio
    $sql = "SELECT 
                fv.*,
                CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', e.apellido_materno) as nombre_empleado,
                e.rol,
                a.id_asignacion,
                CONCAT(v.marca, ' ', v.modelo, ' (', a.placas, ')') as vehiculo
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
        return null;
    }
    
    // Productos del folio
    $sql_productos = "SELECT 
                        dfv.*,
                        p.nombre as producto_nombre,
                        p.precio_costo,
                        p.precio_venta
                      FROM Detalle_Folio_Venta dfv
                      INNER JOIN Productos p ON dfv.id_producto = p.id_producto
                      WHERE dfv.id_folio = :id_folio";
    
    $stmt_prod = $conn->prepare($sql_productos);
    $stmt_prod->bindParam(':id_folio', $id_folio);
    $stmt_prod->execute();
    $folio['productos'] = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
    
    return $folio;
}

/**
 * Calcular estadísticas de folios
 */
function obtenerEstadisticasFolios($conn) {
    $sql = "SELECT 
                COUNT(*) as total_folios,
                SUM(total_venta) as venta_total,
                SUM(enganche) as enganche_total,
                SUM(saldo_pendiente) as saldo_pendiente,
                COUNT(CASE WHEN tipo_pago = 'contado' THEN 1 END) as folios_contado,
                COUNT(CASE WHEN tipo_pago = 'credito' THEN 1 END) as folios_credito
            FROM Folios_Venta";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Verificar si un folio tiene productos de traspasos
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_folio - ID del folio
 * @return bool - true si tiene productos traspasados
 */
function folioTieneProductosTraspasados($conn, $id_folio) {
    // Obtener la asignación del folio
    $sql_asig = "SELECT id_asignacion, fecha_hora_venta FROM Folios_Venta WHERE id_folio = ?";
    $stmt_asig = $conn->prepare($sql_asig);
    $stmt_asig->execute([$id_folio]);
    $folio_info = $stmt_asig->fetch(PDO::FETCH_ASSOC);
    
    if (!$folio_info) {
        return false;
    }
    
    // Verificar si algún producto del folio fue traspasado a esta asignación
    $sql = "SELECT COUNT(*) as total
            FROM Detalle_Folio_Venta dfv
            WHERE dfv.id_folio = ?
            AND EXISTS (
                SELECT 1 FROM Traspasos_Asignaciones t
                WHERE t.id_asignacion_destino = ?
                AND t.id_producto = dfv.id_producto
                AND t.fecha_hora_traspaso <= ?
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $id_folio,
        $folio_info['id_asignacion'],
        $folio_info['fecha_hora_venta']
    ]);
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado['total'] > 0;
}

/**
 * Obtener información de traspasos para un folio específico
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_folio - ID del folio
 * @return array - Array con información de traspasos
 */
function obtenerTraspasosDelFolio($conn, $id_folio) {
    // Obtener la asignación del folio
    $sql_asig = "SELECT id_asignacion, fecha_hora_venta FROM Folios_Venta WHERE id_folio = ?";
    $stmt_asig = $conn->prepare($sql_asig);
    $stmt_asig->execute([$id_folio]);
    $folio_info = $stmt_asig->fetch(PDO::FETCH_ASSOC);
    
    if (!$folio_info) {
        return [];
    }
    
    $sql = "SELECT 
                t.*,
                p.nombre as producto_nombre,
                CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', e.apellido_materno) as empleado_origen,
                a.id_asignacion as asignacion_origen_num
            FROM Traspasos_Asignaciones t
            INNER JOIN Productos p ON t.id_producto = p.id_producto
            INNER JOIN Empleados e ON t.id_empleado_origen = e.id_empleado
            INNER JOIN Asignaciones a ON t.id_asignacion_origen = a.id_asignacion
            WHERE t.id_asignacion_destino = ?
            AND t.fecha_hora_traspaso <= ?
            AND EXISTS (
                SELECT 1 FROM Detalle_Folio_Venta dfv
                WHERE dfv.id_folio = ?
                AND dfv.id_producto = t.id_producto
            )
            ORDER BY t.fecha_hora_traspaso DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $folio_info['id_asignacion'],
        $folio_info['fecha_hora_venta'],
        $id_folio
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener productos con indicador de traspaso para un folio
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_folio - ID del folio
 * @return array - Array de productos con información de traspasos
 */
function obtenerProductosConTraspaso($conn, $id_folio) {
    // Obtener la asignación del folio
    $sql_asig = "SELECT id_asignacion, fecha_hora_venta FROM Folios_Venta WHERE id_folio = ?";
    $stmt_asig = $conn->prepare($sql_asig);
    $stmt_asig->execute([$id_folio]);
    $folio_info = $stmt_asig->fetch(PDO::FETCH_ASSOC);
    
    if (!$folio_info) {
        return [];
    }
    
    $sql = "SELECT 
                dfv.*,
                p.nombre as producto_nombre,
                p.precio_costo,
                p.precio_venta,
                (SELECT COUNT(*) 
                 FROM Traspasos_Asignaciones t
                 WHERE t.id_asignacion_destino = ?
                 AND t.id_producto = dfv.id_producto
                 AND t.fecha_hora_traspaso <= ?) as fue_traspasado
            FROM Detalle_Folio_Venta dfv
            INNER JOIN Productos p ON dfv.id_producto = p.id_producto
            WHERE dfv.id_folio = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $folio_info['id_asignacion'],
        $folio_info['fecha_hora_venta'],
        $id_folio
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>