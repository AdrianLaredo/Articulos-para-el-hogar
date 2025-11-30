<?php
/**
 * FUNCIONES PARA EL SISTEMA DE ASIGNACIONES
 * Zeus Hogar - Sistema completo con traspasos
 * Versión corregida para SQLite (CONCAT reemplazado por ||)
 */

/**
 * Calcular el tiempo transcurrido entre la salida y el regreso.
 * @param string $fecha_salida - Fecha y hora de salida (formato YYYY-MM-DD HH:MM:SS)
 * @param string $fecha_regreso - Fecha y hora de regreso (formato YYYY-MM-DD HH:MM:SS)
 * @return string - Tiempo en ruta formateado (Ej: "6h 0m" o "0h 0m")
 */
function calcularTiempoEnRuta($fecha_salida, $fecha_regreso) {
    try {
        $salida = new DateTime($fecha_salida);
        $regreso = new DateTime($fecha_regreso);

        if ($regreso <= $salida) {
            return "0h 0m";
        }

        $intervalo = $salida->diff($regreso);
        $horas = $intervalo->h + ($intervalo->days * 24);
        $minutos = $intervalo->i;

        return "{$horas}h {$minutos}m";

    } catch (Exception $e) {
        return "N/A";
    }
}

function subirFotoEvidencia($archivo) {
    $directorio_destino = "assets/images/";
    
    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }
    
    $check = getimagesize($archivo["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => "El archivo no es una imagen válida."];
    }
    
    if ($archivo["size"] > 5000000) {
        return ['success' => false, 'message' => "La imagen es demasiado grande. Máximo 5MB."];
    }
    
    $imageFileType = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    $formatos_permitidos = array("jpg", "jpeg", "png", "webp");
    if(!in_array($imageFileType, $formatos_permitidos)) {
        return ['success' => false, 'message' => "Solo se permiten archivos JPG, JPEG, PNG y WEBP."];
    }
    
    $nombre_archivo = "evidencia_" . date('Ymd_His') . "_" . uniqid() . "." . $imageFileType;
    $ruta_completa = $directorio_destino . $nombre_archivo;
    
    if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
        corregirOrientacionImagen($ruta_completa);

        if (extension_loaded('gd')) {
            redimensionarFoto($ruta_completa, $imageFileType);
        }

        return ['success' => true, 'filename' => $nombre_archivo];
    } else {
        return ['success' => false, 'message' => "Error al subir la imagen."];
    }
}

/**
 * Corregir orientación de imagen según EXIF
 */
function corregirOrientacionImagen($ruta) {
    if (!extension_loaded('gd') || !function_exists('exif_read_data')) {
        return;
    }
    
    $exif = @exif_read_data($ruta);
    if (!$exif || !isset($exif['Orientation'])) {
        return;
    }
    
    $orientation = $exif['Orientation'];
    $imagen = imagecreatefromstring(file_get_contents($ruta));
    
    if (!$imagen) {
        return;
    }
    
    switch ($orientation) {
        case 3:
            $imagen = imagerotate($imagen, 180, 0);
            break;
        case 6:
            $imagen = imagerotate($imagen, -90, 0);
            break;
        case 8:
            $imagen = imagerotate($imagen, 90, 0);
            break;
    }

    $tipo = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    switch($tipo) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($imagen, $ruta, 90);
            break;
        case 'png':
            imagepng($imagen, $ruta, 8);
            break;
        case 'webp':
            imagewebp($imagen, $ruta, 90);
            break;
    }
    
    imagedestroy($imagen);
}

/**
 * Redimensionar foto de evidencia
 * @param string $ruta - Ruta de la imagen
 * @param string $tipo - Tipo de imagen
 */
function redimensionarFoto($ruta, $tipo) {
    if (!extension_loaded('gd')) {
        return;
    }
    
    $ancho_maximo = 1280;
    $alto_maximo = 720;
    
    list($ancho_original, $alto_original) = getimagesize($ruta);
    
    if ($ancho_original <= $ancho_maximo && $alto_original <= $alto_maximo) {
        return;
    }
    
    $ratio = $ancho_original / $alto_original;
    
    if ($ancho_original > $alto_original) {
        $nuevo_ancho = $ancho_maximo;
        $nuevo_alto = $ancho_maximo / $ratio;
    } else {
        $nuevo_alto = $alto_maximo;
        $nuevo_ancho = $alto_maximo * $ratio;
    }
    
    switch($tipo) {
        case 'jpg':
        case 'jpeg':
            $imagen_original = imagecreatefromjpeg($ruta);
            break;
        case 'png':
            $imagen_original = imagecreatefrompng($ruta);
            break;
        case 'webp':
            $imagen_original = imagecreatefromwebp($ruta);
            break;
        default:
            return;
    }
    
    $imagen_redimensionada = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    if ($tipo == 'png') {
        imagecolortransparent($imagen_redimensionada, imagecolorallocatealpha($imagen_redimensionada, 0, 0, 0, 127));
        imagealphablending($imagen_redimensionada, false);
        imagesavealpha($imagen_redimensionada, true);
    }

    imagecopyresampled($imagen_redimensionada, $imagen_original, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);
    
    switch($tipo) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($imagen_redimensionada, $ruta, 85);
            break;
        case 'png':
            imagepng($imagen_redimensionada, $ruta, 8);
            break;
        case 'webp':
            imagewebp($imagen_redimensionada, $ruta, 85);
            break;
    }
    
    imagedestroy($imagen_original);
    imagedestroy($imagen_redimensionada);
}

/**
 * Actualizar stock de producto
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_producto - ID del producto
 * @param int $cantidad - Cantidad a sumar (negativo para restar)
 * @return bool - true si se actualizó correctamente
 */
function actualizarStock($conn, $id_producto, $cantidad) {
    try {
        $sql = "UPDATE Productos SET stock = stock + :cantidad WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();

        $sql_check = "SELECT stock, estado FROM Productos WHERE id_producto = :id_producto";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt_check->execute();
        $producto = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            $nuevo_stock = $producto['stock'];
            $estado_actual = $producto['estado'];
            $nuevo_estado = null;

            if ($nuevo_stock <= 0 && $estado_actual === 'disponible') {
                $nuevo_estado = 'agotado';
            }
            elseif ($nuevo_stock > 0 && $estado_actual === 'agotado') {
                $nuevo_estado = 'disponible';
            }

            if ($nuevo_estado !== null) {
                $sql_estado = "UPDATE Productos SET estado = :estado WHERE id_producto = :id_producto";
                $stmt_estado = $conn->prepare($sql_estado);
                $stmt_estado->bindParam(':estado', $nuevo_estado, PDO::PARAM_STR);
                $stmt_estado->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
                $stmt_estado->execute();
            }
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtener detalles de una asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return array|null - Datos de la asignación o null
 */
function obtenerAsignacion($conn, $id_asignacion) {
    $sql = "SELECT a.*, 
                   (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
                   e.rol,
                   v.marca, v.modelo, v.color
            FROM Asignaciones a
            INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
            INNER JOIN Vehiculos v ON a.placas = v.placas
            WHERE a.id_asignacion = :id_asignacion";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener productos de una asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return array - Array de productos
 */
function obtenerProductosAsignacion($conn, $id_asignacion) {
    $sql = "SELECT da.*, p.nombre, p.precio_venta
            FROM Detalle_Asignacion da
            INNER JOIN Productos p ON da.id_producto = p.id_producto
            WHERE da.id_asignacion = :id_asignacion
            ORDER BY p.nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener folios de una asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return array - Array de folios
 */
function obtenerFoliosAsignacion($conn, $id_asignacion) {
    $sql = "SELECT * FROM Folios_Venta 
            WHERE id_asignacion = :id_asignacion 
            ORDER BY id_folio";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener asignaciones activas (abiertas)
 * @param PDO $conn - Conexión a la base de datos
 * @return array - Array de asignaciones activas
 */
function obtenerAsignacionesActivas($conn) {
    $sql = "SELECT a.*, 
                   (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
                   (v.marca || ' ' || v.modelo || ' (' || a.placas || ')') as vehiculo_desc,
                   COUNT(da.id_detalle) as total_productos
            FROM Asignaciones a
            INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
            INNER JOIN Vehiculos v ON a.placas = v.placas
            LEFT JOIN Detalle_Asignacion da ON a.id_asignacion = da.id_asignacion
            WHERE a.estado = 'abierta'
            GROUP BY a.id_asignacion, nombre_empleado, vehiculo_desc
            ORDER BY a.fecha_hora_salida DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcular comisión por asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @param string $rol - Rol del empleado (vendedor/cobrador)
 * @return float - Monto de comisión
 */
function calcularComision($conn, $id_asignacion, $rol) {
    // Obtener total vendido
    $sql = "SELECT SUM(dv.cantidad_vendida * p.precio_venta) as total_vendido
            FROM Folios_Venta fv
            INNER JOIN Detalle_Folio_Venta dv ON fv.id_folio = dv.id_folio
            INNER JOIN Productos p ON dv.id_producto = p.id_producto
            WHERE fv.id_asignacion = :id_asignacion";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_vendido = $resultado['total_vendido'] ?? 0;
    $porcentaje = ($rol == 'vendedor') ? 0.05 : 0.03;

    return $total_vendido * $porcentaje;
}

/**
 * Generar número de folio automático
 * @param PDO $conn - Conexión a la base de datos
 * @return string - Número de folio
 */
function generarNumeroFolio($conn) {
    $sql = "SELECT MAX(id_folio) as ultimo FROM Folios_Venta";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $siguiente = ($resultado['ultimo'] ?? 0) + 1;
    return 'FV-' . date('Y') . '-' . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
}

/**
 * Validar que productos vendidos no excedan cargados
 * @param array $productos_cargados - Array de productos cargados
 * @param array $productos_vendidos - Array de productos vendidos
 * @return array - Array con 'valid' (bool) y 'message' (string)
 */
function validarCantidadesVendidas($productos_cargados, $productos_vendidos) {
    foreach ($productos_vendidos as $id_producto => $cantidad_vendida) {
        $cargado = array_filter($productos_cargados, function($p) use ($id_producto) {
            return $p['id_producto'] == $id_producto;
        });
        
        if (empty($cargado)) {
            return [
                'valid' => false,
                'message' => "Producto ID {$id_producto} no está en la carga"
            ];
        }
        
        $cargado = reset($cargado);
        if ($cantidad_vendida > $cargado['cantidad_cargada']) {
            return [
                'valid' => false,
                'message' => "Cantidad vendida de {$cargado['nombre']} excede la cantidad cargada"
            ];
        }
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Obtener asignaciones activas para traspasos (excepto la actual)
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion_actual - ID de la asignación actual (para excluirla)
 * @return array - Array de asignaciones disponibles para traspaso
 */
function obtenerAsignacionesParaTraspaso($conn, $id_asignacion_actual) {
    $sql = "SELECT a.id_asignacion,
                   a.id_empleado,
                   (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
                   (v.marca || ' ' || v.modelo || ' (' || a.placas || ')') as vehiculo_desc,
                   COUNT(da.id_detalle) as productos_cargados
            FROM Asignaciones a
            INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
            INNER JOIN Vehiculos v ON a.placas = v.placas
            LEFT JOIN Detalle_Asignacion da ON a.id_asignacion = da.id_asignacion
            WHERE a.estado = 'abierta' 
            AND a.id_asignacion != :id_asignacion_actual
            GROUP BY a.id_asignacion
            ORDER BY a.fecha_hora_salida DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion_actual', $id_asignacion_actual, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function registrarTraspaso($conn, $datos) {
    try {
        $conn->beginTransaction();

        $sql_check = "SELECT cantidad_cargada, cantidad_vendida 
                      FROM Detalle_Asignacion 
                      WHERE id_asignacion = :id_asignacion 
                      AND id_producto = :id_producto";
        
        $stmt = $conn->prepare($sql_check);
        $stmt->bindParam(':id_asignacion', $datos['id_asignacion_origen'], PDO::PARAM_INT);
        $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
        $stmt->execute();
        $detalle_origen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detalle_origen) {
            throw new Exception("El producto no existe en la asignación origen");
        }
        
        $disponible = $detalle_origen['cantidad_cargada'] - $detalle_origen['cantidad_vendida'];
        
        if ($disponible < $datos['cantidad']) {
            throw new Exception("No hay suficiente cantidad disponible para traspasar. Disponible: {$disponible}");
        }

        $fecha_hora_registro = date('Y-m-d H:i:s');

        $sql_traspaso = "INSERT INTO Traspasos_Asignaciones (
                             id_asignacion_origen,
                             id_empleado_origen,
                             id_asignacion_destino,
                             id_empleado_destino,
                             id_producto,
                             cantidad,
                             fecha_hora_traspaso,
                             fecha_hora_registro,
                             registrado_por,
                             observaciones
                         ) VALUES (
                             :id_asignacion_origen,
                             :id_empleado_origen,
                             :id_asignacion_destino,
                             :id_empleado_destino,
                             :id_producto,
                             :cantidad,
                             :fecha_hora_traspaso,
                             :fecha_hora_registro,
                             :registrado_por,
                             :observaciones
                         )";
        
        $stmt = $conn->prepare($sql_traspaso);
        $stmt->execute([
            ':id_asignacion_origen' => $datos['id_asignacion_origen'],
            ':id_empleado_origen' => $datos['id_empleado_origen'],
            ':id_asignacion_destino' => $datos['id_asignacion_destino'],
            ':id_empleado_destino' => $datos['id_empleado_destino'],
            ':id_producto' => $datos['id_producto'],
            ':cantidad' => $datos['cantidad'],
            ':fecha_hora_traspaso' => $datos['fecha_hora_traspaso'],
            ':fecha_hora_registro' => $fecha_hora_registro,
            ':registrado_por' => $datos['registrado_por'],
            ':observaciones' => $datos['observaciones']
        ]);

        $sql_update_origen = "UPDATE Detalle_Asignacion 
                              SET cantidad_cargada = cantidad_cargada - :cantidad
                              WHERE id_asignacion = :id_asignacion 
                              AND id_producto = :id_producto";
        
        $stmt = $conn->prepare($sql_update_origen);
        $stmt->execute([
            ':cantidad' => $datos['cantidad'],
            ':id_asignacion' => $datos['id_asignacion_origen'],
            ':id_producto' => $datos['id_producto']
        ]);

        $sql_check_destino = "SELECT id_detalle, cantidad_cargada 
                              FROM Detalle_Asignacion 
                              WHERE id_asignacion = :id_asignacion 
                              AND id_producto = :id_producto";
        
        $stmt = $conn->prepare($sql_check_destino);
        $stmt->bindParam(':id_asignacion', $datos['id_asignacion_destino'], PDO::PARAM_INT);
        $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
        $stmt->execute();
        $detalle_destino = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detalle_destino) {
            $sql_update_destino = "UPDATE Detalle_Asignacion 
                                     SET cantidad_cargada = cantidad_cargada + :cantidad
                                     WHERE id_asignacion = :id_asignacion 
                                     AND id_producto = :id_producto";
            
            $stmt = $conn->prepare($sql_update_destino);
            $stmt->execute([
                ':cantidad' => $datos['cantidad'],
                ':id_asignacion' => $datos['id_asignacion_destino'],
                ':id_producto' => $datos['id_producto']
            ]);
        } else {
            $sql_insert_destino = "INSERT INTO Detalle_Asignacion (
                                         id_asignacion,
                                         id_producto,
                                         cantidad_cargada,
                                         cantidad_vendida,
                                         cantidad_devuelta
                                     ) VALUES (
                                         :id_asignacion,
                                         :id_producto,
                                         :cantidad,
                                         0,
                                         0
                                     )";
            
            $stmt = $conn->prepare($sql_insert_destino);
            $stmt->execute([
                ':id_asignacion' => $datos['id_asignacion_destino'],
                ':id_producto' => $datos['id_producto'],
                ':cantidad' => $datos['cantidad']
            ]);
        }
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Traspaso registrado correctamente'
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        return [
            'success' => false,
            'message' => 'Error al registrar traspaso: ' . $e->getMessage()
        ];
    }
}
/**
 * Obtener traspasos enviados desde una asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return array - Array de traspasos enviados
 */
function obtenerTraspasosEnviados($conn, $id_asignacion) {
    $sql = "SELECT t.*,
                   (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as empleado_destino,
                   p.nombre as producto_nombre
            FROM Traspasos_Asignaciones t
            INNER JOIN Empleados e ON t.id_empleado_destino = e.id_empleado
            INNER JOIN Productos p ON t.id_producto = p.id_producto
            WHERE t.id_asignacion_origen = :id_asignacion
            ORDER BY t.fecha_hora_traspaso DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener traspasos recibidos en una asignación
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return array - Array de traspasos recibidos
 */
function obtenerTraspasosRecibidos($conn, $id_asignacion) {
    $sql = "SELECT t.*,
                   (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as empleado_origen,
                   p.nombre as producto_nombre
            FROM Traspasos_Asignaciones t
            INNER JOIN Empleados e ON t.id_empleado_origen = e.id_empleado
            INNER JOIN Productos p ON t.id_producto = p.id_producto
            WHERE t.id_asignacion_destino = :id_asignacion
            ORDER BY t.fecha_hora_traspaso DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verificar si una asignación tiene traspasos
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_asignacion - ID de la asignación
 * @return bool - true si tiene traspasos
 */
function tieneTranspasos($conn, $id_asignacion) {
    $sql = "SELECT COUNT(*) as total
            FROM Traspasos_Asignaciones
            WHERE id_asignacion_origen = ?
            OR id_asignacion_destino = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_asignacion, $id_asignacion]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado['total'] > 0;
}
?>