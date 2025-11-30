<?php
// modulos/reporte_semanal/functions.php
require_once '../../bd/database.php';
/**
 * ============================================
 * FUNCIONES PARA REPORTE DE GASOLINA - SQLITE
 * ============================================
 */

/**
 * Obtener datos de litros de gasolina por periodo - SQLITE
 */
function obtenerDatosLitros($fecha_inicio, $fecha_fin, $periodo) {
    global $conn;
    
    try {
        // Total de LITROS Y DINERO por vehículo
        $sql_tipo = "SELECT 
                        COALESCE(v.placas, 'Sin Placas') as placas,
                        COALESCE(v.marca, 'Sin Marca') as marca,
                        COALESCE(v.modelo, '') as modelo,
                        SUM(litros) as total_litros,
                        SUM(total_gasto) as total_dinero
                    FROM Registro_Gasolina r
                    LEFT JOIN Vehiculos v ON r.id_vehiculo = v.id_vehiculo
                    WHERE tipo_carga = 'litros'
                    AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                    GROUP BY v.id_vehiculo, v.placas, v.marca, v.modelo
                    ORDER BY total_litros DESC
                    LIMIT 10";
        
        $stmt = $conn->prepare($sql_tipo);
        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        
        $porTipo = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $porTipo[] = [
                'value' => floatval($row['total_litros']),
                'dinero' => floatval($row['total_dinero']),
                'name' => $row['placas'] . ' - ' . $row['marca'],
                'placas' => $row['placas'],
                'marca' => $row['marca'],
                'modelo' => $row['modelo']
            ];
        }
        
        // Datos por día/semana/mes según el periodo - EN LITROS Y DINERO
        if ($periodo === 'semanal') {
            $sql_dia = "SELECT 
                            CASE strftime('%w', fecha_registro)
                                WHEN '0' THEN 'Domingo'
                                WHEN '1' THEN 'Lunes' 
                                WHEN '2' THEN 'Martes'
                                WHEN '3' THEN 'Miércoles'
                                WHEN '4' THEN 'Jueves'
                                WHEN '5' THEN 'Viernes'
                                WHEN '6' THEN 'Sábado'
                            END as nombre_dia,
                            strftime('%w', fecha_registro) as num_dia,
                            SUM(litros) as total_litros,
                            SUM(monto_efectivo) as total_dinero
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'litros'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%w', fecha_registro)
                        ORDER BY num_dia";
            
        } elseif ($periodo === 'mensual') {
            $sql_dia = "SELECT 
                            ('Semana ' || (strftime('%W', fecha_registro) - strftime('%W', :fecha_inicio) + 1)) as nombre_dia,
                            strftime('%W', fecha_registro) as num_semana,
                            SUM(litros) as total_litros,
                            SUM(monto_efectivo) as total_dinero
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'litros'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%W', fecha_registro)
                        ORDER BY num_semana";
            
        } else { // anual
            $sql_dia = "SELECT 
                            CASE strftime('%m', fecha_registro)
                                WHEN '01' THEN 'January'
                                WHEN '02' THEN 'February'
                                WHEN '03' THEN 'March'
                                WHEN '04' THEN 'April'
                                WHEN '05' THEN 'May'
                                WHEN '06' THEN 'June'
                                WHEN '07' THEN 'July'
                                WHEN '08' THEN 'August'
                                WHEN '09' THEN 'September'
                                WHEN '10' THEN 'October'
                                WHEN '11' THEN 'November'
                                WHEN '12' THEN 'December'
                            END as nombre_dia,
                            strftime('%m', fecha_registro) as num_mes,
                            SUM(litros) as total_litros,
                            SUM(monto_efectivo) as total_dinero
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'litros'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%m', fecha_registro)
                        ORDER BY num_mes";
        }
        
        $stmt = $conn->prepare($sql_dia);
        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        
        $porDia = [];
        $diasSemana = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 
                       'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
        $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
                  'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
                  'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $nombre = $row['nombre_dia'];
            
            // Traducir días y meses al español
            if ($periodo === 'semanal' && isset($diasSemana[$nombre])) {
                $nombre = $diasSemana[$nombre];
            } elseif ($periodo === 'anual' && isset($meses[$nombre])) {
                $nombre = $meses[$nombre];
            }
            
            $porDia[] = [
                'value' => floatval($row['total_litros']),
                'dinero' => floatval($row['total_dinero']),
                'name' => $nombre
            ];
        }
        
        return [
            'porTipo' => $porTipo,
            'porDia' => $porDia,
            'hayDatos' => !empty($porTipo) && !empty($porDia)
        ];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerDatosLitros: " . $e->getMessage());
        return [
            'porTipo' => [],
            'porDia' => [],
            'hayDatos' => false
        ];
    }
}

/**
 * Obtener datos de efectivo de gasolina - SOLO EFECTIVO - SQLITE
 */

function obtenerDatosEfectivo($fecha_inicio, $fecha_fin, $periodo) {
    global $conn;
    
    try {
        // Top empleados que más efectivo consumieron - SOLO TIPO 'efectivo'
        $sql_empleados = "SELECT 
                             (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_completo,
                             SUM(r.monto_efectivo) as total_efectivo
                         FROM Registro_Gasolina r
                         INNER JOIN Empleados e ON r.id_empleado = e.id_empleado
                         WHERE r.tipo_carga = 'efectivo'
                         AND DATE(r.fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                         GROUP BY r.id_empleado, e.nombre, e.apellido_paterno, e.apellido_materno
                         ORDER BY total_efectivo DESC
                         LIMIT 8";
        
        $stmt = $conn->prepare($sql_empleados);
        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        
        $porEmpleado = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $porEmpleado[] = [
                'value' => floatval($row['total_efectivo']),
                'name' => $row['nombre_completo']
            ];
        }
        
        // Datos por día/semana/mes según el periodo - SOLO EFECTIVO
        if ($periodo === 'semanal') {
            $sql_dia = "SELECT 
                            CASE strftime('%w', fecha_registro)
                                WHEN '0' THEN 'Domingo'
                                WHEN '1' THEN 'Lunes' 
                                WHEN '2' THEN 'Martes'
                                WHEN '3' THEN 'Miércoles'
                                WHEN '4' THEN 'Jueves'
                                WHEN '5' THEN 'Viernes'
                                WHEN '6' THEN 'Sábado'
                            END as nombre_dia,
                            strftime('%w', fecha_registro) as num_dia,
                            SUM(monto_efectivo) as total_efectivo
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'efectivo'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%w', fecha_registro)
                        ORDER BY num_dia";
            
        } elseif ($periodo === 'mensual') {
            $sql_dia = "SELECT 
                            ('Semana ' || (strftime('%W', fecha_registro) - strftime('%W', :fecha_inicio) + 1)) as nombre_dia,
                            strftime('%W', fecha_registro) as num_semana,
                            SUM(monto_efectivo) as total_efectivo
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'efectivo'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%W', fecha_registro)
                        ORDER BY num_semana";
            
        } else { // anual
            $sql_dia = "SELECT 
                            CASE strftime('%m', fecha_registro)
                                WHEN '01' THEN 'January'
                                WHEN '02' THEN 'February'
                                WHEN '03' THEN 'March'
                                WHEN '04' THEN 'April'
                                WHEN '05' THEN 'May'
                                WHEN '06' THEN 'June'
                                WHEN '07' THEN 'July'
                                WHEN '08' THEN 'August'
                                WHEN '09' THEN 'September'
                                WHEN '10' THEN 'October'
                                WHEN '11' THEN 'November'
                                WHEN '12' THEN 'December'
                            END as nombre_dia,
                            strftime('%m', fecha_registro) as num_mes,
                            SUM(monto_efectivo) as total_efectivo
                        FROM Registro_Gasolina
                        WHERE tipo_carga = 'efectivo'
                        AND DATE(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY strftime('%m', fecha_registro)
                        ORDER BY num_mes";
        }
        
        $stmt = $conn->prepare($sql_dia);
        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        
        $porDia = [];
        $diasSemana = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 
                       'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
        $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
                  'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
                  'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $nombre = $row['nombre_dia'];
            
            // Traducir días y meses al español
            if ($periodo === 'semanal' && isset($diasSemana[$nombre])) {
                $nombre = $diasSemana[$nombre];
            } elseif ($periodo === 'anual' && isset($meses[$nombre])) {
                $nombre = $meses[$nombre];
            }
            
            $porDia[] = [
                'value' => floatval($row['total_efectivo']),
                'name' => $nombre
            ];
        }
        
        return [
            'porEmpleado' => $porEmpleado,
            'porDia' => $porDia,
            'hayDatos' => !empty($porEmpleado) && !empty($porDia)
        ];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerDatosEfectivo: " . $e->getMessage());
        return [
            'porEmpleado' => [],
            'porDia' => [],
            'hayDatos' => false
        ];
    }
}



/**
 * ============================================
 * FUNCIONES PARA REPORTE DE VENTAS - SQLITE
 * ============================================
 */
/**
 * Obtener datos de ventas (folios) - SQLITE
 * SOLO FOLIOS ACTIVOS + AJUSTE CORRECTO POR CANCELACIONES
 */
function obtenerDatosVentas($fecha_inicio, $fecha_fin, $periodo) {
    global $conn;
    
    // ✅ NUEVO: Obtener enganches y totales por separado
    $sql_total = "SELECT 
                     COUNT(*) as total_folios,
                     COALESCE(SUM(fv.total_venta), 0) as total_ventas,
                     COALESCE(SUM(fv.enganche), 0) as total_enganches,
                     COALESCE(SUM(fv.total_venta - fv.enganche), 0) as total_saldo_pendiente
                 FROM Folios_Venta fv
                 INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                 WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                 AND (fv.estado = 'activo' OR fv.estado IS NULL)";
    
    $stmt = $conn->prepare($sql_total);
    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt->bindParam(':fecha_fin', $fecha_fin);
    $stmt->execute();
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ Obtener enganches NO devueltos de cancelaciones
    $sql_cancelaciones = "SELECT 
                            COALESCE(SUM(CASE WHEN cf.enganche_devuelto = 0 THEN fv.enganche ELSE 0 END), 0) as total_enganche_no_devuelto
                         FROM Folios_Venta fv
                         INNER JOIN Cancelaciones_Folios cf ON fv.id_folio = cf.id_folio
                         WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin";
    
    $stmt_cancel = $conn->prepare($sql_cancelaciones);
    $stmt_cancel->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_cancel->bindParam(':fecha_fin', $fecha_fin);
    $stmt_cancel->execute();
    $cancelaciones = $stmt_cancel->fetch(PDO::FETCH_ASSOC);
    
    // ✅ CALCULAR TOTALES SEPARADOS
    $total_enganches = floatval($totales['total_enganches']) 
                     + floatval($cancelaciones['total_enganche_no_devuelto']);
    
    $total_saldo_pendiente = floatval($totales['total_saldo_pendiente']);
    
    $total_general = $total_enganches + $total_saldo_pendiente;
    
    // Top empleados por folios (SOLO ACTIVOS)
    $sql_empleados = "SELECT 
                         (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_completo,
                         COUNT(*) as total_folios
                     FROM Folios_Venta fv
                     INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                     INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
                     WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                     AND (fv.estado = 'activo' OR fv.estado IS NULL)
                     GROUP BY e.id_empleado, e.nombre, e.apellido_paterno, e.apellido_materno
                     ORDER BY total_folios DESC
                     LIMIT 8";
    
    $stmt = $conn->prepare($sql_empleados);
    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt->bindParam(':fecha_fin', $fecha_fin);
    $stmt->execute();
    
    $porEmpleado = [];
    $empleado_top = 'N/A';
    $empleado_top_folios = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $porEmpleado[] = [
            'value' => intval($row['total_folios']),
            'name' => $row['nombre_completo']
        ];
        
        if ($empleado_top === 'N/A') {
            $empleado_top = $row['nombre_completo'];
            $empleado_top_folios = $row['total_folios'];
        }
    }
    
    // Folios por día/semana/mes según el periodo
    $porPeriodo = obtenerFoliosPorPeriodo($fecha_inicio, $fecha_fin, $periodo);
    
    return [
        'resumen' => [
            'total_folios' => intval($totales['total_folios']),
            'total_enganches' => $total_enganches,
            'total_saldo_pendiente' => $total_saldo_pendiente,
            'total_general' => $total_general,
            'vendedor_top' => $empleado_top,
            'vendedor_top_folios' => $empleado_top_folios
        ],
        'porEmpleado' => $porEmpleado,
        'porPeriodo' => $porPeriodo
    ];
}

/**
 * Obtener folios por periodo (día/semana/mes)
 * SOLO FOLIOS ACTIVOS
 */
function obtenerFoliosPorPeriodo($fecha_inicio, $fecha_fin, $periodo) {
    global $conn;
    
    if ($periodo == 'semanal') {
        // Agrupar por día de la semana
        $sql = "SELECT 
                    CASE strftime('%w', fv.fecha_hora_venta)
                        WHEN '0' THEN 'Domingo'
                        WHEN '1' THEN 'Lunes'
                        WHEN '2' THEN 'Martes'
                        WHEN '3' THEN 'Miércoles'
                        WHEN '4' THEN 'Jueves'
                        WHEN '5' THEN 'Viernes'
                        WHEN '6' THEN 'Sábado'
                    END as periodo,
                    COUNT(*) as total_folios
                FROM Folios_Venta fv
                INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                GROUP BY strftime('%w', fv.fecha_hora_venta)
                ORDER BY strftime('%w', fv.fecha_hora_venta)";
    } elseif ($periodo == 'mensual') {
        // Agrupar por semana del mes
        $sql = "SELECT 
                    ('Semana ' || strftime('%W', fv.fecha_hora_venta)) as periodo,
                    COUNT(*) as total_folios
                FROM Folios_Venta fv
                INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                GROUP BY strftime('%W', fv.fecha_hora_venta)
                ORDER BY strftime('%W', fv.fecha_hora_venta)";
    } elseif ($periodo == 'anual') {
        // Agrupar por mes del año
        $sql = "SELECT 
                    CASE strftime('%m', fv.fecha_hora_venta)
                        WHEN '01' THEN 'Enero'
                        WHEN '02' THEN 'Febrero'
                        WHEN '03' THEN 'Marzo'
                        WHEN '04' THEN 'Abril'
                        WHEN '05' THEN 'Mayo'
                        WHEN '06' THEN 'Junio'
                        WHEN '07' THEN 'Julio'
                        WHEN '08' THEN 'Agosto'
                        WHEN '09' THEN 'Septiembre'
                        WHEN '10' THEN 'Octubre'
                        WHEN '11' THEN 'Noviembre'
                        WHEN '12' THEN 'Diciembre'
                    END as periodo,
                    COUNT(*) as total_folios
                FROM Folios_Venta fv
                INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                GROUP BY strftime('%Y', fv.fecha_hora_venta), strftime('%m', fv.fecha_hora_venta)
                ORDER BY strftime('%Y', fv.fecha_hora_venta), strftime('%m', fv.fecha_hora_venta)";
    } else {
        // Personalizado: agrupar por día
        $sql = "SELECT 
                    strftime('%d/%m', fv.fecha_hora_venta) as periodo,
                    COUNT(*) as total_folios
                FROM Folios_Venta fv
                INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                WHERE DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                GROUP BY DATE(fv.fecha_hora_venta)
                ORDER BY DATE(fv.fecha_hora_venta)";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt->bindParam(':fecha_fin', $fecha_fin);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $datos = [];
    foreach ($resultados as $row) {
        $datos[] = [
            'name' => $row['periodo'],
            'value' => intval($row['total_folios'])
        ];
    }
    
    return $datos;
}

/**
 * Obtener folios por empleado desglosados por mes
 * SOLO FOLIOS ACTIVOS
 */
function obtenerFoliosPorEmpleadoPorMes($fecha_inicio, $fecha_fin) {
    global $conn;
    
    // Generar todos los meses del rango de forma manual
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    
    // Ajustar al primer día del mes de inicio
    $inicio->modify('first day of this month');
    
    // Ajustar al último día del mes de fin
    $fin->modify('last day of this month');
    
    $meses = [];
    $actual = clone $inicio;
    
    // Generar todos los meses del rango
    while ($actual <= $fin) {
        $meses[] = $actual->format('Y-m');
        $actual->modify('first day of next month');
    }
    
    // Obtener empleados activos
    $sql_empleados = "SELECT id_empleado, 
                             (nombre || ' ' || apellido_paterno || ' ' || apellido_materno) as nombre_empleado
                     FROM Empleados 
                     WHERE estado = 'activo'
                     ORDER BY nombre";
    $stmt_emp = $conn->prepare($sql_empleados);
    $stmt_emp->execute();
    $empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado = [];
    
    foreach ($empleados as $empleado) {
        $fila = [
            'id_empleado' => $empleado['id_empleado'],
            'nombre_empleado' => $empleado['nombre_empleado'],
            'meses' => [],
            'total' => 0
        ];
        
        // Para cada mes, obtener folios del empleado (SOLO ACTIVOS)
        foreach ($meses as $mes) {
            $sql_folios = "SELECT COUNT(fv.id_folio) as total_folios
                           FROM Folios_Venta fv
                           INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                           WHERE a.id_empleado = :id_empleado
                           AND strftime('%Y-%m', fv.fecha_hora_venta) = :mes
                           AND (fv.estado = 'activo' OR fv.estado IS NULL)";
            
            $stmt_folios = $conn->prepare($sql_folios);
            $stmt_folios->bindParam(':id_empleado', $empleado['id_empleado']);
            $stmt_folios->bindParam(':mes', $mes);
            $stmt_folios->execute();
            $resultado_mes = $stmt_folios->fetch(PDO::FETCH_ASSOC);
            
            $folios_mes = intval($resultado_mes['total_folios']);
            $fila['meses'][$mes] = $folios_mes;
            $fila['total'] += $folios_mes;
        }
        
        // Solo agregar empleados que tienen folios
        if ($fila['total'] > 0) {
            $resultado[] = $fila;
        }
    }
    
    // Ordenar por total descendente
    usort($resultado, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    
    return [
        'empleados' => $resultado,
        'meses' => $meses
    ];
}

/**
 * ============================================
 * FUNCIONES AUXILIARES
 * ============================================
 */

/**
 * Formatear número como moneda
 */
function formatearMoneda($numero) {
    return '$' . number_format($numero, 2, '.', ',');
}

/**
 * Formatear fecha en español
 */
function formatearFecha($fecha) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $anio = date('Y', $timestamp);
    
    return "$dia de $mes de $anio";
}