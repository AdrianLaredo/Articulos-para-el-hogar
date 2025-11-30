<?php
require_once '../../bd/database.php';

/**
 * Obtener lista de vehículos activos
 */
function obtenerVehiculos() {
    global $conn;
    try {
        $sql = "SELECT 
                    id_vehiculo, 
                    placas, 
                    color, 
                    modelo, 
                    marca,
                    (marca || ' ' || modelo || ' (' || placas || ')') as descripcion
                FROM Vehiculos 
                WHERE estado = 'activo' 
                ORDER BY marca, modelo";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en obtenerVehiculos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener lista de empleados activos
 */
function obtenerEmpleados() {
    global $conn;
    try {
        $sql = "SELECT id_empleado, nombre, apellido_paterno, apellido_materno 
                FROM Empleados 
                WHERE estado = 'activo' 
                ORDER BY nombre, apellido_paterno";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Registrar carga de gasolina
 */
function registrarGasolina($datos) {
    global $conn;
    
    try {
        $tipo_carga = $datos['tipo_carga'];
        $registrado_por = $datos['registrado_por'];
        $observaciones = $datos['observaciones'] ?? '';
        
        // Obtener fecha/hora actual con la zona horaria de PHP (America/Mexico_City)
        $fecha_registro = date('Y-m-d H:i:s');
        
        if ($tipo_carga === 'litros') {
            // Registro por litros
            $id_vehiculo = $datos['id_vehiculo'];
            $placas = $datos['placas'];
            $litros = $datos['litros'];
            $precio_litro = $datos['precio_litro'];
            $total_gasto = $litros * $precio_litro;
            
            $sql = "INSERT INTO Registro_Gasolina 
                    (tipo_carga, id_vehiculo, placas, litros, precio_litro, total_gasto,
                     registrado_por, observaciones, fecha_registro) 
                    VALUES (:tipo_carga, :id_vehiculo, :placas, :litros, :precio_litro, :total_gasto,
                            :registrado_por, :observaciones, :fecha_registro)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':tipo_carga', $tipo_carga);
            $stmt->bindParam(':id_vehiculo', $id_vehiculo, PDO::PARAM_INT);
            $stmt->bindParam(':placas', $placas);
            $stmt->bindParam(':litros', $litros);
            $stmt->bindParam(':precio_litro', $precio_litro);
            $stmt->bindParam(':total_gasto', $total_gasto);
            $stmt->bindParam(':registrado_por', $registrado_por);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':fecha_registro', $fecha_registro);
            
        } else {
            // Registro por efectivo
            $id_empleado = $datos['id_empleado'];
            $monto_efectivo = $datos['monto_efectivo'];
            $total_gasto = $monto_efectivo;
            
            $sql = "INSERT INTO Registro_Gasolina 
                    (tipo_carga, id_empleado, monto_efectivo, total_gasto,
                     registrado_por, observaciones, fecha_registro) 
                    VALUES (:tipo_carga, :id_empleado, :monto_efectivo, :total_gasto,
                            :registrado_por, :observaciones, :fecha_registro)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':tipo_carga', $tipo_carga);
            $stmt->bindParam(':id_empleado', $id_empleado, PDO::PARAM_INT);
            $stmt->bindParam(':monto_efectivo', $monto_efectivo);
            $stmt->bindParam(':total_gasto', $total_gasto);
            $stmt->bindParam(':registrado_por', $registrado_por);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':fecha_registro', $fecha_registro);
        }
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'id' => $conn->lastInsertId(),
                'message' => 'Registro guardado exitosamente'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al guardar el registro'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtener historial de registros con filtros opcionales y paginación
 */
function obtenerHistorial($filtros = []) {
    global $conn;
    
    try {
        $sql = "SELECT 
                    r.id_registro,
                    r.tipo_carga,
                    r.fecha_registro,
                    r.placas,
                    r.litros,
                    r.precio_litro,
                    r.total_gasto,
                    r.monto_efectivo,
                    r.registrado_por,
                    r.observaciones,
                    v.marca,
                    v.modelo,
                    v.color,
                    e.nombre,
                    e.apellido_paterno,
                    e.apellido_materno,
                    CASE 
                        WHEN r.tipo_carga = 'litros' THEN 
                            (v.marca || ' ' || v.modelo || ' - ' || r.placas)
                        ELSE 
                            (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, ''))
                    END as descripcion
                FROM Registro_Gasolina r
                LEFT JOIN Vehiculos v ON r.id_vehiculo = v.id_vehiculo
                LEFT JOIN Empleados e ON r.id_empleado = e.id_empleado
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(r.fecha_registro) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(r.fecha_registro) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= " AND r.tipo_carga = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        
        $sql .= " ORDER BY r.fecha_registro DESC";
        
        // Agregar límite y offset para paginación
        if (isset($filtros['limit']) && isset($filtros['offset'])) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $conn->prepare($sql);
        
        // Bind parámetros normales
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Bind limit y offset si existen
        if (isset($filtros['limit']) && isset($filtros['offset'])) {
            $stmt->bindValue(':limit', (int)$filtros['limit'], PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$filtros['offset'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener historial: " . $e->getMessage());
        return [];
    }
}

/**
 * Contar total de registros con filtros (para paginación)
 */
function contarRegistros($filtros = []) {
    global $conn;
    
    try {
        $sql = "SELECT COUNT(*) as total
                FROM Registro_Gasolina r
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar los mismos filtros que en obtenerHistorial
        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(r.fecha_registro) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(r.fecha_registro) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= " AND r.tipo_carga = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        
        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
        
    } catch (PDOException $e) {
        error_log("Error al contar registros: " . $e->getMessage());
        return 0;
    }
}

/**
 * ⭐ NUEVA FUNCIÓN: Obtener la fecha de inicio de la semana actual (Lunes 7:00 AM)
 * Si es lunes antes de las 7:00 AM, usa el lunes anterior
 * 
 * ✅ OPTIMIZADA PARA SQLITE
 * ✅ Zona horaria: America/Mexico_City (CDMX)
 * ✅ Retorna fecha en formato SQLite: 'YYYY-MM-DD HH:MM:SS'
 * 
 * Esta función permite que las estadísticas se reinicien automáticamente
 * cada lunes a las 7:00 AM sin necesidad de intervención manual
 * 
 * @return string Fecha en formato 'Y-m-d H:i:s' (ejemplo: '2025-10-31 07:00:00')
 */
function obtenerInicioSemanaActual() {
    // ⭐ CONFIGURAR ZONA HORARIA DE MÉXICO (CDMX)
    // Esto asegura que todas las operaciones de fecha/hora usen el horario correcto
    date_default_timezone_set('America/Mexico_City');
    
    // Obtener fecha/hora actual en CDMX
    $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    $hora_actual = (int)$ahora->format('G'); // Hora en formato 24h (0-23)
    $dia_semana = (int)$ahora->format('N'); // 1 (Lunes) a 7 (Domingo)
    
    // LÓGICA DE REINICIO SEMANAL
    // Si es lunes (1) y son menos de las 7:00 AM, usar el lunes anterior
    if ($dia_semana === 1 && $hora_actual < 7) {
        $inicio_semana = new DateTime('last monday', new DateTimeZone('America/Mexico_City'));
    } 
    // Si es lunes y son las 7:00 AM o más, usar hoy
    elseif ($dia_semana === 1 && $hora_actual >= 7) {
        $inicio_semana = new DateTime('today', new DateTimeZone('America/Mexico_City'));
    }
    // Cualquier otro día, buscar el lunes anterior
    else {
        $inicio_semana = new DateTime('last monday', new DateTimeZone('America/Mexico_City'));
    }
    
    // Establecer la hora a las 7:00 AM
    $inicio_semana->setTime(7, 0, 0);
    
    // ✅ RETORNAR EN FORMATO SQLITE: 'YYYY-MM-DD HH:MM:SS'
    return $inicio_semana->format('Y-m-d H:i:s');
}

/**
 * Obtener estadísticas totales (sin paginación)
 * ⭐ Por defecto filtra desde el lunes actual a las 7:00 AM (REINICIO SEMANAL AUTOMÁTICO)
 * 
 * ✅ OPTIMIZADA PARA SQLITE
 * Las estadísticas se reinician automáticamente cada lunes a las 7:00 AM
 * mostrando solo los datos de la semana actual (Lunes 7:00 AM - Hoy)
 */
function obtenerEstadisticas($filtros = []) {
    global $conn;
    
    // ⭐ CONFIGURAR ZONA HORARIA (por si no se ha configurado globalmente)
    date_default_timezone_set('America/Mexico_City');
    
    // ⭐ REINICIO SEMANAL AUTOMÁTICO
    // Si no hay filtro de fecha_inicio personalizado, usar el lunes actual a las 7:00 AM
    if (empty($filtros['fecha_inicio'])) {
        $filtros['fecha_inicio'] = obtenerInicioSemanaActual();
    }
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_registros,
                    SUM(CASE WHEN r.tipo_carga = 'litros' THEN r.litros ELSE 0 END) as total_litros,
                    SUM(CASE WHEN r.tipo_carga = 'efectivo' THEN r.monto_efectivo ELSE 0 END) as total_efectivo,
                    SUM(CASE 
                        WHEN r.tipo_carga = 'litros' THEN r.total_gasto 
                        WHEN r.tipo_carga = 'efectivo' THEN r.monto_efectivo 
                        ELSE 0 
                    END) as total_gasto
                FROM Registro_Gasolina r
                WHERE 1=1";
        
        $params = [];
        
        // ✅ COMPARACIÓN DE FECHAS EN SQLITE
        // SQLite compara strings de fecha directamente si están en formato 'YYYY-MM-DD HH:MM:SS'
        // Usamos r.fecha_registro >= :fecha_inicio para incluir desde la hora exacta
        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND r.fecha_registro >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            // Para fecha_fin, usar DATE() para incluir todo el día
            $sql .= " AND DATE(r.fecha_registro) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= " AND r.tipo_carga = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        
        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_registros' => (int)$result['total_registros'],
            'total_litros' => (float)$result['total_litros'],
            'total_efectivo' => (float)$result['total_efectivo'],
            'total_gasto' => (float)$result['total_gasto']
        ];
        
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        return [
            'total_registros' => 0,
            'total_litros' => 0,
            'total_efectivo' => 0,
            'total_gasto' => 0
        ];
    }
}

/**
 * Obtener detalle de un registro específico
 */
function obtenerDetalleRegistro($id_registro) {
    global $conn;
    
    try {
        $sql = "SELECT 
                    r.*,
                    v.marca,
                    v.modelo,
                    v.color,
                    e.nombre,
                    e.apellido_paterno,
                    e.apellido_materno,
                    CASE 
                        WHEN r.tipo_carga = 'litros' THEN 
                            (v.marca || ' ' || v.modelo)
                        ELSE 
                            (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, ''))
                    END as info_principal,
                    (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_empleado
                FROM Registro_Gasolina r
                LEFT JOIN Vehiculos v ON r.id_vehiculo = v.id_vehiculo
                LEFT JOIN Empleados e ON r.id_empleado = e.id_empleado
                WHERE r.id_registro = :id_registro";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_registro', $id_registro, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en obtenerDetalleRegistro: " . $e->getMessage());
        return null;
    }
}

/**
 * Eliminar registro
 */
function eliminarRegistro($id_registro) {
    global $conn;
    
    try {
        $sql = "DELETE FROM Registro_Gasolina WHERE id_registro = :id_registro";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_registro', $id_registro, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Registro eliminado exitosamente'
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Error al eliminar el registro'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}
?>