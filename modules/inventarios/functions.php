<?php
/**
 * Funciones auxiliares para el módulo de Inventario
 * Zeus Hogar - Gestión de Productos
 */

/**
 * Subir imagen de producto
 * @param array $archivo - Archivo de imagen desde $_FILES
 * @return array - Array con success y mensaje/filename
 */
function subirImagen($archivo) {
    $directorio_destino = "assets/images/";
    
    // Crear directorio si no existe
    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }
    
    // Validar que es una imagen
    $check = getimagesize($archivo["tmp_name"]);
    if($check === false) {
        return [
            'success' => false,
            'message' => "El archivo no es una imagen válida."
        ];
    }
    
    // Validar tamaño (máximo 5MB)
    if ($archivo["size"] > 5000000) {
        return [
            'success' => false,
            'message' => "La imagen es demasiado grande. Máximo 5MB."
        ];
    }
    
    // Validar formato
    $imageFileType = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    $formatos_permitidos = array("jpg", "jpeg", "png", "gif", "webp");
    if(!in_array($imageFileType, $formatos_permitidos)) {
        return [
            'success' => false,
            'message' => "Solo se permiten archivos JPG, JPEG, PNG, GIF y WEBP."
        ];
    }
    
    // Generar nombre único
    $nombre_archivo = "producto_" . uniqid() . "." . $imageFileType;
    $ruta_completa = $directorio_destino . $nombre_archivo;
    
    // Mover archivo
    if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
        // Redimensionar y corregir orientación de imagen
        redimensionarImagen($ruta_completa, $imageFileType);
        
        return [
            'success' => true,
            'filename' => $nombre_archivo
        ];
    } else {
        return [
            'success' => false,
            'message' => "Error al subir la imagen."
        ];
    }
}

/**
 * Redimensionar imagen para optimizar espacio y corregir orientación EXIF
 * @param string $ruta - Ruta de la imagen
 * @param string $tipo - Tipo de imagen (jpg, png, etc)
 */
function redimensionarImagen($ruta, $tipo) {
    $ancho_maximo = 800;
    $alto_maximo = 800;
    
    list($ancho_original, $alto_original) = getimagesize($ruta);
    
    // Crear imagen según tipo
    switch($tipo) {
        case 'jpg':
        case 'jpeg':
            $imagen_original = imagecreatefromjpeg($ruta);
            break;
        case 'png':
            $imagen_original = imagecreatefrompng($ruta);
            break;
        case 'gif':
            $imagen_original = imagecreatefromgif($ruta);
            break;
        case 'webp':
            $imagen_original = imagecreatefromwebp($ruta);
            break;
        default:
            return;
    }
    
    // Corregir orientación EXIF (para imágenes de cámaras/celulares)
    if (($tipo == 'jpg' || $tipo == 'jpeg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($ruta);
        if ($exif && isset($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $imagen_original = imagerotate($imagen_original, 180, 0);
                    break;
                case 6:
                    $imagen_original = imagerotate($imagen_original, -90, 0);
                    // Intercambiar dimensiones
                    $temp = $ancho_original;
                    $ancho_original = $alto_original;
                    $alto_original = $temp;
                    break;
                case 8:
                    $imagen_original = imagerotate($imagen_original, 90, 0);
                    // Intercambiar dimensiones
                    $temp = $ancho_original;
                    $ancho_original = $alto_original;
                    $alto_original = $temp;
                    break;
            }
        }
    }
    
    // Si la imagen ya es pequeña, solo guardarla con la orientación corregida
    if ($ancho_original <= $ancho_maximo && $alto_original <= $alto_maximo) {
        switch($tipo) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($imagen_original, $ruta, 90);
                break;
            case 'png':
                imagepng($imagen_original, $ruta, 8);
                break;
            case 'gif':
                imagegif($imagen_original, $ruta);
                break;
            case 'webp':
                imagewebp($imagen_original, $ruta, 90);
                break;
        }
        imagedestroy($imagen_original);
        return;
    }
    
    // Calcular nuevas dimensiones manteniendo proporción
    $ratio = $ancho_original / $alto_original;
    
    if ($ancho_original > $alto_original) {
        $nuevo_ancho = $ancho_maximo;
        $nuevo_alto = (int)round($ancho_maximo / $ratio);
    } else {
        $nuevo_alto = $alto_maximo;
        $nuevo_ancho = (int)round($alto_maximo * $ratio);
    }
    
    // Crear nueva imagen redimensionada
    $imagen_redimensionada = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Preservar transparencia para PNG y GIF
    if ($tipo == 'png' || $tipo == 'gif') {
        imagecolortransparent($imagen_redimensionada, imagecolorallocatealpha($imagen_redimensionada, 0, 0, 0, 127));
        imagealphablending($imagen_redimensionada, false);
        imagesavealpha($imagen_redimensionada, true);
    }
    
    // Redimensionar
    imagecopyresampled($imagen_redimensionada, $imagen_original, 0, 0, 0, 0, 
                      $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);
    
    // Guardar según tipo
    switch($tipo) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($imagen_redimensionada, $ruta, 90);
            break;
        case 'png':
            imagepng($imagen_redimensionada, $ruta, 8);
            break;
        case 'gif':
            imagegif($imagen_redimensionada, $ruta);
            break;
        case 'webp':
            imagewebp($imagen_redimensionada, $ruta, 90);
            break;
    }
    
    // Liberar memoria
    imagedestroy($imagen_original);
    imagedestroy($imagen_redimensionada);
}

/**
 * Formatear precio en formato mexicano
 * @param float $precio - Precio a formatear
 * @return string - Precio formateado
 */
function formatearPrecio($precio) {
    return '$' . number_format($precio, 2, '.', ',');
}

/**
 * Obtener clase CSS según nivel de stock
 * @param int $stock - Cantidad en stock
 * @return string - Clase CSS
 */
function obtenerClaseStock($stock) {
    if ($stock == 0) {
        return 'agotado';
    } elseif ($stock <= 5) {
        return 'bajo';
    } else {
        return 'disponible';
    }
}

/**
 * Validar si un producto está disponible
 * @param array $producto - Array con datos del producto
 * @return bool - true si está disponible
 */
function productoDisponible($producto) {
    return $producto['stock'] > 0 && $producto['estado'] == 'disponible';
}

/**
 * Generar slug para URL amigable
 * @param string $texto - Texto a convertir
 * @return string - Slug generado
 */
function generarSlug($texto) {
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9\s-]/', '', $texto);
    $texto = preg_replace('/[\s-]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto;
}

/**
 * Calcular valor total del inventario
 * @param array $productos - Array de productos
 * @return float - Valor total
 */
function calcularValorInventario($productos) {
    $total = 0;
    foreach ($productos as $producto) {
        $total += $producto['precio_venta'] * $producto['stock'];
    }
    return $total;
}

/**
 * Obtener productos con stock bajo (menos de 5 unidades)
 * @param PDO $conn - Conexión a la base de datos
 * @return array - Array de productos con stock bajo
 */
function obtenerProductosStockBajo($conn) {
    $sql = "SELECT * FROM Productos WHERE stock <= 5 AND stock > 0 AND estado = 'disponible' ORDER BY stock ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener productos más vendidos (requiere tabla de ventas)
 * @param PDO $conn - Conexión a la base de datos
 * @param int $limite - Número de productos a retornar
 * @return array - Array de productos más vendidos
 */
function obtenerProductosMasVendidos($conn, $limite = 10) {
    // Esta función requiere que exista una tabla de ventas
    // Por ahora retorna array vacío, se implementará con el módulo de ventas
    return [];
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
        $resultado = $stmt->execute();
        
        // Actualizar estado según el nuevo stock
        if ($resultado) {
            actualizarEstadoPorStock($conn, $id_producto);
        }
        
        return $resultado;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Verificar si hay stock suficiente
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_producto - ID del producto
 * @param int $cantidad_requerida - Cantidad necesaria
 * @return bool - true si hay suficiente stock
 */
function verificarStock($conn, $id_producto, $cantidad_requerida) {
    $sql = "SELECT stock FROM Productos WHERE id_producto = :id_producto";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_producto', $id_producto);
    $stmt->execute();
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $producto && $producto['stock'] >= $cantidad_requerida;
}

/**
 * FUNCIÓN MEJORADA: Actualizar estado del producto basado en stock
 * REGLAS:
 * - Si stock = 0 y estado NO es 'descontinuado' → cambiar a 'agotado'
 * - Si stock > 0 y estado es 'agotado' → cambiar a 'disponible'
 * - Si estado es 'descontinuado' → NO cambiar automáticamente
 * 
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_producto - ID del producto
 */
function actualizarEstadoPorStock($conn, $id_producto) {
    $sql = "UPDATE Productos 
            SET estado = CASE 
                WHEN stock = 0 AND estado != 'descontinuado' THEN 'agotado'
                WHEN stock > 0 AND estado = 'agotado' THEN 'disponible'
                ELSE estado
            END
            WHERE id_producto = :id_producto";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_producto', $id_producto);
    $stmt->execute();
}

/**
 * Obtener estadísticas de productos por estado
 * @param PDO $conn - Conexión a la base de datos
 * @return array - Array con conteos por estado
 */
function obtenerEstadisticasPorEstado($conn) {
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
                SUM(CASE WHEN estado = 'agotado' THEN 1 ELSE 0 END) as agotados,
                SUM(CASE WHEN estado = 'descontinuado' THEN 1 ELSE 0 END) as descontinuados
            FROM Productos";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * ============================================
 * NUEVAS FUNCIONES PARA VERIFICAR ELIMINACIÓN
 * ============================================
 */

/**
 * Verificar si un producto tiene registros relacionados en otras tablas
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_producto - ID del producto
 * @return array - Array con 'tiene_registros' (bool) y 'detalles' (array con información)
 */
function verificarProductoTieneRegistros($conn, $id_producto) {
    $detalles = [];
    $tiene_registros = false;
    
    try {
        // Verificar en Detalle_Asignacion
        $sql_asignaciones = "SELECT COUNT(*) as total FROM Detalle_Asignacion WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql_asignaciones);
        $stmt->bindParam(':id_producto', $id_producto);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] > 0) {
            $tiene_registros = true;
            $detalles[] = "Asignaciones de vehículos: " . $resultado['total'] . " registro(s)";
        }
        
        // Verificar en Detalle_Folio_Venta
        $sql_ventas = "SELECT COUNT(*) as total FROM Detalle_Folio_Venta WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql_ventas);
        $stmt->bindParam(':id_producto', $id_producto);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] > 0) {
            $tiene_registros = true;
            $detalles[] = "Ventas realizadas: " . $resultado['total'] . " registro(s)";
        }
        
        return [
            'tiene_registros' => $tiene_registros,
            'detalles' => $detalles
        ];
        
    } catch (PDOException $e) {
        return [
            'tiene_registros' => false,
            'detalles' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Verificar si un producto puede ser eliminado
 * @param PDO $conn - Conexión a la base de datos
 * @param int $id_producto - ID del producto
 * @return array - Array con 'puede_eliminar' (bool), 'mensaje' (string) y 'detalles' (array)
 */
function puedeEliminarProducto($conn, $id_producto) {
    $verificacion = verificarProductoTieneRegistros($conn, $id_producto);
    
    if (isset($verificacion['error'])) {
        return [
            'puede_eliminar' => false,
            'mensaje' => 'Error al verificar el producto',
            'detalles' => []
        ];
    }
    
    if ($verificacion['tiene_registros']) {
        $mensaje = "No se puede eliminar este producto porque tiene registros asociados:\n\n";
        $mensaje .= "• " . implode("\n• ", $verificacion['detalles']);
        $mensaje .= "\n\nSi deseas desactivarlo, cambia su estado a 'Descontinuado' en lugar de eliminarlo.";
        
        return [
            'puede_eliminar' => false,
            'mensaje' => $mensaje,
            'detalles' => $verificacion['detalles']
        ];
    }
    
    return [
        'puede_eliminar' => true,
        'mensaje' => 'El producto puede ser eliminado',
        'detalles' => []
    ];
}
?>