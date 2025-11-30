<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Función para validar token CSRF
function validarCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para validar nombre del producto (letras, números y espacios)
function validarNombreProducto($nombre) {
    return preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/u", $nombre);
}

// Función para validar material (solo letras y espacios)
function validarMaterial($material) {
    if (empty($material)) return true; // Campo opcional
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $material);
}

// Función para validar color (solo letras y espacios)
function validarColor($color) {
    if (empty($color)) return true; // Campo opcional
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $color);
}

// Función para capitalizar texto
function capitalizarTexto($texto) {
    return mb_convert_case(trim($texto), MB_CASE_TITLE, 'UTF-8');
}

// Función para limpiar espacios múltiples
function limpiarEspacios($texto) {
    return preg_replace('/\s+/', ' ', trim($texto));
}

$mensaje = '';
$tipo_mensaje = '';

// CREAR - Agregar nuevo producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $_SESSION['mensaje'] = "Error: Token de seguridad inválido";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $nombre = limpiarEspacios($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $material = limpiarEspacios($_POST['material']);
        $color = limpiarEspacios($_POST['color']);
        $precio_costo = floatval($_POST['precio_costo']);
        $precio_venta = floatval($_POST['precio_venta']);
        $stock = intval($_POST['stock']);
        $estado = $_POST['estado'];
        
        // Capitalizar
        $nombre = capitalizarTexto($nombre);
        $material = $material ? capitalizarTexto($material) : '';
        $color = $color ? capitalizarTexto($color) : '';
        
        // Validaciones
        if (strlen($nombre) < 3) {
            $_SESSION['mensaje'] = "Error: El nombre del producto debe tener al menos 3 caracteres";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif (!validarNombreProducto($nombre)) {
            $_SESSION['mensaje'] = "Error: El nombre solo debe contener letras, números y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($material && !validarMaterial($material)) {
            $_SESSION['mensaje'] = "Error: El material solo debe contener letras y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($color && !validarColor($color)) {
            $_SESSION['mensaje'] = "Error: El color solo debe contener letras y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_costo < 0) {
            $_SESSION['mensaje'] = "Error: El precio de costo no puede ser negativo";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_venta < 0) {
            $_SESSION['mensaje'] = "Error: El precio de venta no puede ser negativo";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_venta < $precio_costo) {
            $_SESSION['mensaje'] = "Advertencia: El precio de venta es menor al precio de costo";
            $_SESSION['tipo_mensaje'] = "warning";
        } else {
            // Manejo de imagen
            $imagen = '';
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                $resultado_imagen = subirImagen($_FILES['imagen']);
                if ($resultado_imagen['success']) {
                    $imagen = $resultado_imagen['filename'];
                } else {
                    $_SESSION['mensaje'] = $resultado_imagen['message'];
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
            
            if (!isset($_SESSION['tipo_mensaje']) || $_SESSION['tipo_mensaje'] !== 'error') {
                try {
                    $sql = "INSERT INTO Productos (nombre, descripcion, material, color, precio_costo, precio_venta, stock, estado, imagen) 
                            VALUES (:nombre, :descripcion, :material, :color, :precio_costo, :precio_venta, :stock, :estado, :imagen)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':material', $material);
                    $stmt->bindParam(':color', $color);
                    $stmt->bindParam(':precio_costo', $precio_costo);
                    $stmt->bindParam(':precio_venta', $precio_venta);
                    $stmt->bindParam(':stock', $stock);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->bindParam(':imagen', $imagen);
                    
                    if ($stmt->execute()) {
                        // Actualizar estado automáticamente según stock
                        $id_nuevo_producto = $conn->lastInsertId();
                        actualizarEstadoPorStock($conn, $id_nuevo_producto);
                        
                        $_SESSION['mensaje'] = "Producto registrado exitosamente";
                        $_SESSION['tipo_mensaje'] = "success";
                    }
                } catch (PDOException $e) {
                    $_SESSION['mensaje'] = "Error al registrar producto: " . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
        }
        
        // Redireccionar para evitar reenvío de formulario (POST-Redirect-GET)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ACTUALIZAR - Modificar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $_SESSION['mensaje'] = "Error: Token de seguridad inválido";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: " . $_SERVER['PHP_SELF'] . "?editar=" . $_POST['id_producto']);
        exit;
    } else {
        $id_producto = $_POST['id_producto'];
        $nombre = limpiarEspacios($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $material = limpiarEspacios($_POST['material']);
        $color = limpiarEspacios($_POST['color']);
        $precio_costo = floatval($_POST['precio_costo']);
        $precio_venta = floatval($_POST['precio_venta']);
        $stock = intval($_POST['stock']);
        $estado = $_POST['estado'];
        $imagen_actual = $_POST['imagen_actual'];
        
        // Capitalizar
        $nombre = capitalizarTexto($nombre);
        $material = $material ? capitalizarTexto($material) : '';
        $color = $color ? capitalizarTexto($color) : '';
        
        // Validaciones
        if (strlen($nombre) < 3) {
            $_SESSION['mensaje'] = "Error: El nombre del producto debe tener al menos 3 caracteres";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif (!validarNombreProducto($nombre)) {
            $_SESSION['mensaje'] = "Error: El nombre solo debe contener letras, números y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($material && !validarMaterial($material)) {
            $_SESSION['mensaje'] = "Error: El material solo debe contener letras y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($color && !validarColor($color)) {
            $_SESSION['mensaje'] = "Error: El color solo debe contener letras y espacios";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_costo < 0) {
            $_SESSION['mensaje'] = "Error: El precio de costo no puede ser negativo";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_venta < 0) {
            $_SESSION['mensaje'] = "Error: El precio de venta no puede ser negativo";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif ($precio_venta < $precio_costo) {
            $_SESSION['mensaje'] = "Advertencia: El precio de venta es menor al precio de costo";
            $_SESSION['tipo_mensaje'] = "warning";
        } else {
            // Manejo de nueva imagen (opcional)
            $imagen_final = $imagen_actual;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                $resultado_imagen = subirImagen($_FILES['imagen']);
                if ($resultado_imagen['success']) {
                    // Eliminar imagen anterior si existe
                    if ($imagen_actual && file_exists("assets/images/" . $imagen_actual)) {
                        unlink("assets/images/" . $imagen_actual);
                    }
                    $imagen_final = $resultado_imagen['filename'];
                } else {
                    $_SESSION['mensaje'] = $resultado_imagen['message'];
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
            
            if (!isset($_SESSION['tipo_mensaje']) || $_SESSION['tipo_mensaje'] !== 'error') {
                try {
                    $sql = "UPDATE Productos SET 
                            nombre = :nombre, 
                            descripcion = :descripcion, 
                            material = :material, 
                            color = :color,
                            precio_costo = :precio_costo, 
                            precio_venta = :precio_venta, 
                            stock = :stock, 
                            estado = :estado,
                            imagen = :imagen
                            WHERE id_producto = :id_producto";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':material', $material);
                    $stmt->bindParam(':color', $color);
                    $stmt->bindParam(':precio_costo', $precio_costo);
                    $stmt->bindParam(':precio_venta', $precio_venta);
                    $stmt->bindParam(':stock', $stock);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->bindParam(':imagen', $imagen_final);
                    $stmt->bindParam(':id_producto', $id_producto);
                    
                    if ($stmt->execute()) {
                        // Actualizar estado automáticamente según stock
                        actualizarEstadoPorStock($conn, $id_producto);
                        
                        $_SESSION['mensaje'] = "Producto actualizado exitosamente";
                        $_SESSION['tipo_mensaje'] = "success";
                    }
                } catch (PDOException $e) {
                    $_SESSION['mensaje'] = "Error al actualizar producto: " . $e->getMessage();
                    $_SESSION['tipo_mensaje'] = "error";
                }
            }
        }
        
        // Redireccionar
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ELIMINAR - Borrar producto (con verificación de registros)
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    // Verificar si el producto puede ser eliminado
    $verificacion = puedeEliminarProducto($conn, $id_eliminar);
    
    if ($verificacion['puede_eliminar']) {
        // Obtener nombre de imagen antes de eliminar
        $sql = "SELECT imagen FROM Productos WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_producto', $id_eliminar);
        $stmt->execute();
        $producto_eliminar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            $sql = "DELETE FROM Productos WHERE id_producto = :id_producto";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id_producto', $id_eliminar);
            
            if ($stmt->execute()) {
                // Eliminar imagen física si existe
                if ($producto_eliminar['imagen'] && file_exists("assets/images/" . $producto_eliminar['imagen'])) {
                    unlink("assets/images/" . $producto_eliminar['imagen']);
                }
                
                $_SESSION['mensaje'] = "Producto eliminado exitosamente";
                $_SESSION['tipo_mensaje'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje'] = "Error al eliminar producto: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
    } else {
        $_SESSION['mensaje'] = $verificacion['mensaje'];
        $_SESSION['tipo_mensaje'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Mostrar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// OBTENER ESTADÍSTICAS
$estadisticas = obtenerEstadisticasPorEstado($conn);

// PAGINACIÓN Y FILTROS
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;
$offset = ($page - 1) * $records_per_page;

// Filtros
$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir consulta con filtros
$sql = "SELECT * FROM Productos WHERE 1=1";
$params = [];

if (!empty($filtro_busqueda)) {
    $sql .= " AND (nombre LIKE :busqueda OR descripcion LIKE :busqueda OR material LIKE :busqueda OR color LIKE :busqueda)";
    $params[':busqueda'] = '%' . $filtro_busqueda . '%';
}

if (!empty($filtro_estado)) {
    $sql .= " AND estado = :estado";
    $params[':estado'] = $filtro_estado;
}

// Contar total de registros
$sql_count = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Obtener productos
$sql .= " ORDER BY id_producto DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si se está editando un producto
$producto_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $sql = "SELECT * FROM Productos WHERE id_producto = :id_producto";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_producto', $id_editar);
    $stmt->execute();
    $producto_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Productos - Zeus Hogar</title>
    <link rel="stylesheet" href="assets/css/inventario.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-package'></i> Gestión de Inventario</h1>

        <!-- MENSAJES -->
        <?php if ($mensaje): ?>
            <div id="mensaje" class="mensaje <?php echo $tipo_mensaje; ?>">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : ($tipo_mensaje === 'error' ? 'bx-x-circle' : 'bx-error-circle'); ?>'></i>
                <?php echo nl2br(htmlspecialchars($mensaje)); ?>
            </div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class='bx bx-package'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['total']; ?></h3>
                    <p>Total Productos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['disponibles']; ?></h3>
                    <p>Disponibles</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class='bx bx-error-circle'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['agotados']; ?></h3>
                    <p>Agotados</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon descontinuado">
                    <i class='bx bx-x-circle'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['descontinuados']; ?></h3>
                    <p>Descontinuados</p>
                </div>
            </div>
        </div>

        <!-- FORMULARIO AGREGAR/EDITAR PRODUCTO -->
        <div class="card">
            <h2>
                <i class='bx <?php echo $producto_editar ? 'bx-edit' : 'bx-plus-circle'; ?>'></i>
                <?php echo $producto_editar ? 'Editar Producto' : 'Agregar Nuevo Producto'; ?>
            </h2>

            <form id="formProducto" class="form-producto" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="<?php echo $producto_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($producto_editar): ?>
                    <input type="hidden" name="id_producto" value="<?php echo $producto_editar['id_producto']; ?>">
                    <input type="hidden" name="imagen_actual" value="<?php echo $producto_editar['imagen']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre"><i class='bx bx-package'></i> Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['nombre']) : ''; ?>"
                               minlength="3" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="material"><i class='bx bx-wrench'></i> Material</label>
                        <input type="text" id="material" name="material" maxlength="50"
                               value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['material']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="color"><i class='bx bx-palette'></i> Color</label>
                        <input type="text" id="color" name="color" maxlength="30"
                               value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['color']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="precio_costo"><i class='bx bx-dollar-circle'></i> Precio Costo *</label>
                        <input type="number" id="precio_costo" name="precio_costo" required 
                               value="<?php echo $producto_editar ? $producto_editar['precio_costo'] : ''; ?>"
                               min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="precio_venta"><i class='bx bx-money'></i> Precio Venta *</label>
                        <input type="number" id="precio_venta" name="precio_venta" required 
                               value="<?php echo $producto_editar ? $producto_editar['precio_venta'] : ''; ?>"
                               min="0" step="0.01">
                        <span id="warning-precio" class="error-message">
                            ⚠️ El precio de venta es menor al costo
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="stock"><i class='bx bx-box'></i> Stock *</label>
                        <input type="number" id="stock" name="stock" required 
                               value="<?php echo $producto_editar ? $producto_editar['stock'] : '0'; ?>"
                               min="0">
                    </div>

                    <div class="form-group">
                        <label for="estado"><i class='bx bx-info-circle'></i> Estado *</label>
                        <select id="estado" name="estado" required>
                            <option value="disponible" <?php echo ($producto_editar && $producto_editar['estado'] === 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                            <option value="agotado" <?php echo ($producto_editar && $producto_editar['estado'] === 'agotado') ? 'selected' : ''; ?>>Agotado</option>
                            <option value="descontinuado" <?php echo ($producto_editar && $producto_editar['estado'] === 'descontinuado') ? 'selected' : ''; ?>>Descontinuado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class='bx bx-image'></i> Imagen del Producto</label>
                        <div class="image-upload-container">
                            <div class="image-upload-buttons">
                                <button type="button" class="btn-upload btn-camera" onclick="document.getElementById('imagen').click()">
                                    <i class='bx bx-camera'></i>
                                    <span>Tomar Foto</span>
                                </button>
                                <button type="button" class="btn-upload btn-gallery" onclick="document.getElementById('imagen').click()">
                                    <i class='bx bx-image-add'></i>
                                    <span>Desde Galería</span>
                                </button>
                            </div>
                            <input type="file" id="imagen" name="imagen" accept="image/*" capture="environment" class="input-file-hidden" onchange="mostrarNombreArchivo(this)">
                            <span id="file-name" class="text-muted">JPG, PNG, GIF o WEBP (máx. 5MB)</span>
                            <?php if ($producto_editar && $producto_editar['imagen']): ?>
                                <span class="info-message">Imagen actual: <?php echo htmlspecialchars($producto_editar['imagen']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="descripcion"><i class='bx bx-detail'></i> Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" maxlength="500"><?php echo $producto_editar ? htmlspecialchars($producto_editar['descripcion']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx <?php echo $producto_editar ? 'bx-save' : 'bx-plus-circle'; ?>'></i>
                        <?php echo $producto_editar ? 'Actualizar Producto' : 'Registrar Producto'; ?>
                    </button>
                    <?php if ($producto_editar): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- FILTROS Y LISTA DE PRODUCTOS -->
        <div class="card">
            <h2><i class='bx bx-filter'></i> Inventario de Productos</h2>

            <!-- FILTROS -->
            <form method="GET" class="filtros-grid">
                <div class="form-group">
                    <label for="busqueda"><i class='bx bx-search'></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" 
                           value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                           placeholder="Nombre, descripción, material...">
                </div>

                <div class="form-group">
                    <label for="filtro_estado"><i class='bx bx-filter-alt'></i> Estado</label>
                    <select id="filtro_estado" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="disponible" <?php echo $filtro_estado === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                        <option value="agotado" <?php echo $filtro_estado === 'agotado' ? 'selected' : ''; ?>>Agotado</option>
                        <option value="descontinuado" <?php echo $filtro_estado === 'descontinuado' ? 'selected' : ''; ?>>Descontinuado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="per_page"><i class='bx bx-list-ul'></i> Mostrar</label>
                    <select id="per_page" name="per_page">
                        <option value="12" <?php echo $records_per_page == 12 ? 'selected' : ''; ?>>12 productos</option>
                        <option value="24" <?php echo $records_per_page == 24 ? 'selected' : ''; ?>>24 productos</option>
                        <option value="48" <?php echo $records_per_page == 48 ? 'selected' : ''; ?>>48 productos</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class='bx bx-search'></i> Buscar
                    </button>
                </div>
            </form>

            <!-- LISTA DE PRODUCTOS -->
            <?php if (count($productos) > 0): ?>
                <div class="productos-grid">
                    <?php foreach ($productos as $producto): ?>
                        <div class="producto-card">
                            <div class="producto-imagen">
                                <?php if ($producto['imagen']): ?>
                                    <img src="assets/images/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                <?php else: ?>
                                    <div class="no-imagen">
                                        <i class='bx bx-image'></i>
                                        <p>Sin imagen</p>
                                    </div>
                                <?php endif; ?>
                                <span class="estado-badge <?php echo $producto['estado']; ?>">
                                    <?php echo ucfirst($producto['estado']); ?>
                                </span>
                            </div>

                            <div class="producto-info">
                                <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                
                                <?php if ($producto['descripcion']): ?>
                                    <p class="descripcion"><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                                <?php endif; ?>

                                <div class="producto-detalles">
                                    <?php if ($producto['material']): ?>
                                        <span><i class='bx bx-wrench'></i> <?php echo htmlspecialchars($producto['material']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($producto['color']): ?>
                                        <span><i class='bx bx-palette'></i> <?php echo htmlspecialchars($producto['color']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="producto-precio-stock">
                                    <div class="precio">
                                        <i class='bx bx-money'></i>
                                        <?php echo formatearPrecio($producto['precio_venta']); ?>
                                    </div>
                                    <div class="stock <?php echo $producto['stock'] == 0 ? 'agotado' : ''; ?>">
                                        <i class='bx bx-box'></i>
                                        Stock: <?php echo $producto['stock']; ?>
                                    </div>
                                </div>

                                <div class="producto-acciones">
                                    <a href="?editar=<?php echo $producto['id_producto']; ?>" 
                                       class="btn-action btn-edit">
                                        <i class='bx bx-edit'></i> Editar
                                    </a>
                                    <a href="?eliminar=<?php echo $producto['id_producto']; ?>" 
                                       class="btn-action btn-delete"
                                       onclick="return confirm('¿Estás seguro de eliminar este producto?\n\n<?php echo addslashes($producto['nombre']); ?>');">
                                        <i class='bx bx-trash'></i> Eliminar
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $records_per_page, $total_records); ?> 
                            de <?php echo $total_records; ?> productos
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($page > 1): ?>
                                <a href="?page=1&per_page=<?php echo $records_per_page; ?>&busqueda=<?php echo urlencode($filtro_busqueda); ?>&estado=<?php echo $filtro_estado; ?>" 
                                   class="pagination-btn">
                                    <i class='bx bx-chevrons-left'></i>
                                </a>
                                <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $records_per_page; ?>&busqueda=<?php echo urlencode($filtro_busqueda); ?>&estado=<?php echo $filtro_estado; ?>" 
                                   class="pagination-btn">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>&busqueda=<?php echo urlencode($filtro_busqueda); ?>&estado=<?php echo $filtro_estado; ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $records_per_page; ?>&busqueda=<?php echo urlencode($filtro_busqueda); ?>&estado=<?php echo $filtro_estado; ?>" 
                                   class="pagination-btn">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?>&busqueda=<?php echo urlencode($filtro_busqueda); ?>&estado=<?php echo $filtro_estado; ?>" 
                                   class="pagination-btn">
                                    <i class='bx bx-chevrons-right'></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-package'></i>
                    <p>No se encontraron productos</p>
                    <p class="text-muted">Intenta con otros filtros o agrega un nuevo producto</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function mostrarNombreArchivo(input) {
            const fileName = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileName.textContent = '📷 ' + input.files[0].name;
                fileName.style.color = '#10B981';
                fileName.style.fontWeight = '600';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let formularioModificado = false;
            
            // Auto-scroll a mensajes
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // Capitalización automática
            function capitalizarTexto(texto) {
                texto = texto.replace(/\s+/g, ' ').trim();
                return texto.toLowerCase().replace(/\b\w/g, function(letra) {
                    return letra.toUpperCase();
                });
            }

            ['nombre', 'material', 'color'].forEach(campo => {
                const input = document.getElementById(campo);
                if (input) {
                    input.addEventListener('blur', function() {
                        if (this.value.trim()) {
                            this.value = capitalizarTexto(this.value);
                        }
                    });
                }
            });

            // Validación de Stock y Estado Automático
            const inputStock = document.getElementById('stock');
            const selectEstado = document.getElementById('estado');
            
            if (inputStock && selectEstado) {
                inputStock.addEventListener('input', function() {
                    formularioModificado = true;
                    const stockValue = parseInt(this.value) || 0;
                    
                    if (selectEstado.value !== 'descontinuado') {
                        if (stockValue === 0) {
                            selectEstado.value = 'agotado';
                        } else if (stockValue > 0 && selectEstado.value === 'agotado') {
                            selectEstado.value = 'disponible';
                        }
                    }
                });
            }

            // Validación de Precios
            const inputPrecioCosto = document.getElementById('precio_costo');
            const inputPrecioVenta = document.getElementById('precio_venta');
            const warningPrecio = document.getElementById('warning-precio');

            function validarPrecios() {
                const costo = parseFloat(inputPrecioCosto.value) || 0;
                const venta = parseFloat(inputPrecioVenta.value) || 0;

                if (venta > 0 && costo > 0 && venta < costo) {
                    warningPrecio.classList.add('show');
                } else {
                    warningPrecio.classList.remove('show');
                }
            }

            if (inputPrecioCosto && inputPrecioVenta) {
                inputPrecioCosto.addEventListener('input', function() {
                    formularioModificado = true;
                    validarPrecios();
                });
                inputPrecioVenta.addEventListener('input', function() {
                    formularioModificado = true;
                    validarPrecios();
                });
            }

            // Detectar cambios en el formulario
            const formInputs = document.querySelectorAll('#formProducto input, #formProducto select, #formProducto textarea');
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    formularioModificado = true;
                });
            });

            // Advertencia al salir sin guardar
            window.addEventListener('beforeunload', function(e) {
                if (formularioModificado) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            // Resetear flag al enviar formulario
            const formulario = document.getElementById('formProducto');
            if (formulario) {
                formulario.addEventListener('submit', function() {
                    formularioModificado = false;
                });
            }
        });
    </script>
</body>
</html>