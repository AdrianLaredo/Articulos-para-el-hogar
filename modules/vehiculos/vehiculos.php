<?php
session_start();
require_once '../../bd/database.php';

// Verificar si el usuario está logueado
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

// Función para validar placas (letras y números sin espacios, máximo 7 caracteres, máximo 5 letras)
function validarPlacas($placas) {
    if (!preg_match("/^[A-Z0-9]+$/", $placas)) {
        return ['valido' => false, 'mensaje' => 'Las placas solo deben contener letras y números sin espacios'];
    }
    
    if (strlen($placas) > 7) {
        return ['valido' => false, 'mensaje' => 'Las placas no pueden tener más de 7 caracteres'];
    }
    
    $letras = preg_replace('/[^A-Z]/', '', $placas);
    if (strlen($letras) > 5) {
        return ['valido' => false, 'mensaje' => 'Las placas no pueden contener más de 5 letras'];
    }
    
    return ['valido' => true, 'mensaje' => ''];
}

// Función para validar marca (solo letras, sin espacios, máximo 10 caracteres)
function validarMarca($marca) {
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ]{1,10}$/u", $marca);
}

// Función para validar modelo (letras y números, puede tener espacios, máximo 15 caracteres)
function validarModelo($modelo) {
    return preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]{1,15}$/u", $modelo);
}

// Función para validar color (solo letras, puede tener espacios, máximo 10 caracteres)
function validarColor($color) {
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{1,10}$/u", $color);
}

// Función para capitalizar texto
function capitalizarTexto($texto) {
    return mb_convert_case(trim($texto), MB_CASE_TITLE, 'UTF-8');
}

// Función para limpiar espacios múltiples
function limpiarEspacios($texto) {
    return preg_replace('/\s+/', ' ', trim($texto));
}

// Función para validar fecha de vigencia
function validarFechaVigencia($fecha) {
    if (empty($fecha)) {
        return ['valido' => true, 'mensaje' => ''];
    }
    
    $fecha_obj = new DateTime($fecha);
    $hace_2_anos = new DateTime('-2 years');
    $dentro_10_anos = new DateTime('+10 years');
    
    if ($fecha_obj < $hace_2_anos) {
        return ['valido' => false, 'mensaje' => 'La fecha de vigencia no puede ser mayor a 2 años en el pasado'];
    }
    
    if ($fecha_obj > $dentro_10_anos) {
        return ['valido' => false, 'mensaje' => 'La fecha de vigencia no puede ser mayor a 10 años en el futuro'];
    }
    
    return ['valido' => true, 'mensaje' => ''];
}

// Función para convertir nombre de color a código hexadecimal
function obtenerCodigoColor($nombreColor) {
    $colores = [
        'rojo' => '#dc2626',
        'azul' => '#2563eb',
        'verde' => '#16a34a',
        'amarillo' => '#eab308',
        'negro' => '#000000',
        'blanco' => '#ffffff',
        'gris' => '#6b7280',
        'naranja' => '#ea580c',
        'morado' => '#9333ea',
        'rosa' => '#ec4899',
        'cafe' => '#92400e',
        'café' => '#92400e',
        'beige' => '#d4a574',
        'plateado' => '#c0c0c0',
        'dorado' => '#ffd700',
        'plata' => '#c0c0c0',
        'oro' => '#ffd700',
        'turquesa' => '#14b8a6',
        'celeste' => '#38bdf8',
        'vino' => '#881337',
        'guinda' => '#881337'
    ];
    
    $nombreLower = strtolower(trim($nombreColor));
    return $colores[$nombreLower] ?? '#6b7280';
}

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// CREAR - Agregar nuevo vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $placas = strtoupper(trim($_POST['placas']));
        $marca = limpiarEspacios($_POST['marca']);
        $modelo = limpiarEspacios($_POST['modelo']);
        $color = limpiarEspacios($_POST['color']);
        $fecha_vigencia = $_POST['fecha_vigencia'];
        
        $marca = capitalizarTexto($marca);
        $modelo = capitalizarTexto($modelo);
        $color = capitalizarTexto($color);
        
        $validacion_placas = validarPlacas($placas);
        if (!$validacion_placas['valido']) {
            $mensaje = "Error: " . $validacion_placas['mensaje'];
            $tipo_mensaje = "error";
        } elseif (strlen($placas) < 3) {
            $mensaje = "Error: Las placas deben tener al menos 3 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarMarca($marca)) {
            $mensaje = "Error: La marca solo debe contener letras sin espacios (máximo 10 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($marca) < 2) {
            $mensaje = "Error: La marca debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarModelo($modelo)) {
            $mensaje = "Error: El modelo solo debe contener letras y números (máximo 15 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($modelo) < 2) {
            $mensaje = "Error: El modelo debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarColor($color)) {
            $mensaje = "Error: El color solo debe contener letras (máximo 10 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($color) < 2) {
            $mensaje = "Error: El color debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } else {
            $validacion_fecha = validarFechaVigencia($fecha_vigencia);
            if (!$validacion_fecha['valido']) {
                $mensaje = "Error: " . $validacion_fecha['mensaje'];
                $tipo_mensaje = "error";
            } else {
                try {
                    $sql = "INSERT INTO Vehiculos (placas, marca, modelo, color, fecha_de_vigencia, estado) 
                            VALUES (:placas, :marca, :modelo, :color, :fecha_vigencia, 'activo')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':placas', $placas);
                    $stmt->bindParam(':marca', $marca);
                    $stmt->bindParam(':modelo', $modelo);
                    $stmt->bindParam(':color', $color);
                    $fecha_vigencia_param = $fecha_vigencia ?: null;
                    $stmt->bindParam(':fecha_vigencia', $fecha_vigencia_param);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Vehículo registrado exitosamente";
                        $tipo_mensaje = "success";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $mensaje = "Error: Las placas ya están registradas";
                    } else {
                        $mensaje = "Error al registrar vehículo: " . $e->getMessage();
                    }
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}

// ACTUALIZAR - Modificar vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_vehiculo = $_POST['id_vehiculo'];
        $placas = strtoupper(trim($_POST['placas']));
        $marca = limpiarEspacios($_POST['marca']);
        $modelo = limpiarEspacios($_POST['modelo']);
        $color = limpiarEspacios($_POST['color']);
        $fecha_vigencia = $_POST['fecha_vigencia'];
        
        $marca = capitalizarTexto($marca);
        $modelo = capitalizarTexto($modelo);
        $color = capitalizarTexto($color);
        
        $validacion_placas = validarPlacas($placas);
        if (!$validacion_placas['valido']) {
            $mensaje = "Error: " . $validacion_placas['mensaje'];
            $tipo_mensaje = "error";
        } elseif (strlen($placas) < 3) {
            $mensaje = "Error: Las placas deben tener al menos 3 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarMarca($marca)) {
            $mensaje = "Error: La marca solo debe contener letras sin espacios (máximo 10 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($marca) < 2) {
            $mensaje = "Error: La marca debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarModelo($modelo)) {
            $mensaje = "Error: El modelo solo debe contener letras y números (máximo 15 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($modelo) < 2) {
            $mensaje = "Error: El modelo debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarColor($color)) {
            $mensaje = "Error: El color solo debe contener letras (máximo 10 caracteres)";
            $tipo_mensaje = "error";
        } elseif (strlen($color) < 2) {
            $mensaje = "Error: El color debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } else {
            $validacion_fecha = validarFechaVigencia($fecha_vigencia);
            if (!$validacion_fecha['valido']) {
                $mensaje = "Error: " . $validacion_fecha['mensaje'];
                $tipo_mensaje = "error";
            } else {
                try {
                    $sql = "UPDATE Vehiculos 
                            SET placas = :placas, marca = :marca, modelo = :modelo, 
                                color = :color, fecha_de_vigencia = :fecha_vigencia
                            WHERE id_vehiculo = :id_vehiculo";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_vehiculo', $id_vehiculo);
                    $stmt->bindParam(':placas', $placas);
                    $stmt->bindParam(':marca', $marca);
                    $stmt->bindParam(':modelo', $modelo);
                    $stmt->bindParam(':color', $color);
                    $fecha_vigencia_param = $fecha_vigencia ?: null;
                    $stmt->bindParam(':fecha_vigencia', $fecha_vigencia_param);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Vehículo actualizado exitosamente";
                        $tipo_mensaje = "success";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $mensaje = "Error: Las placas ya están registradas en otro vehículo";
                    } else {
                        $mensaje = "Error al actualizar vehículo: " . $e->getMessage();
                    }
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}

// CAMBIAR ESTADO - Activar/Inactivar vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_vehiculo = $_POST['id_vehiculo'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        try {
            $sql = "UPDATE Vehiculos SET estado = :estado WHERE id_vehiculo = :id_vehiculo";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':estado', $nuevo_estado);
            $stmt->bindParam(':id_vehiculo', $id_vehiculo);
            
            if ($stmt->execute()) {
                $mensaje = "Estado del vehículo actualizado a: " . ucfirst($nuevo_estado);
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al cambiar estado: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// PAGINACIÓN
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM Vehiculos";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// LEER - Obtener vehículos con paginación
$sql = "SELECT * FROM Vehiculos ORDER BY id_vehiculo DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener vehículo para editar
$vehiculo_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $sql = "SELECT * FROM Vehiculos WHERE id_vehiculo = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id_editar);
    $stmt->execute();
    $vehiculo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ALERTAS DE VIGENCIA - Verificar vehículos próximos a vencer (21 días = 3 semanas)
$sql_alertas = "SELECT * FROM Vehiculos 
                WHERE estado = 'activo' 
                AND fecha_de_vigencia IS NOT NULL 
                AND fecha_de_vigencia <= date('now', '+21 days')
                AND fecha_de_vigencia >= date('now')
                ORDER BY fecha_de_vigencia ASC";
$stmt_alertas = $conn->prepare($sql_alertas);
$stmt_alertas->execute();
$vehiculos_por_vencer = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);

// Vehículos ya vencidos
$sql_vencidos = "SELECT * FROM Vehiculos 
                 WHERE estado = 'activo' 
                 AND fecha_de_vigencia IS NOT NULL 
                 AND fecha_de_vigencia < date('now')
                 ORDER BY fecha_de_vigencia ASC";
$stmt_vencidos = $conn->prepare($sql_vencidos);
$stmt_vencidos->execute();
$vehiculos_vencidos = $stmt_vencidos->fetchAll(PDO::FETCH_ASSOC);

// Calcular fecha mínima y máxima
$fecha_minima = date('Y-m-d', strtotime('-2 years'));
$fecha_maxima = date('Y-m-d', strtotime('+10 years'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vehículos - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/vehiculos.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-car'></i> Gestión de Vehículos</h1>

        <!-- Badge de Alertas Desplegable -->
        <?php 
        $total_alertas = count($vehiculos_vencidos) + count($vehiculos_por_vencer);
        ?>

        <?php if ($total_alertas > 0): ?>
            <div class="alerta-toggle-container">
                <button type="button" class="btn-alerta-toggle" id="btnAlertaToggle">
                    <i class='bx bx-bell'></i>
                    <span>Alertas de Vigencia</span>
                    <span class="badge-count"><?php echo $total_alertas; ?></span>
                    <i class='bx bx-chevron-down' id="chevronIcon"></i>
                </button>
            </div>

            <div class="alerta-panel" id="alertaPanel" style="display: none;">
                <?php if (count($vehiculos_vencidos) > 0): ?>
                    <div class="alerta alerta-vencido">
                        <div class="alerta-header">
                            <i class='bx bx-error-circle'></i>
                            <strong>Vehículos con vigencia VENCIDA (<?php echo count($vehiculos_vencidos); ?>)</strong>
                        </div>
                        <div class="alerta-contenido">
                            <?php foreach ($vehiculos_vencidos as $vehiculo): 
                                $fecha_venc = new DateTime($vehiculo['fecha_de_vigencia']);
                                $dias_vencidos = (new DateTime())->diff($fecha_venc)->days;
                            ?>
                                <div class="alerta-item">
                                    <span class="alerta-placas"><?php echo htmlspecialchars($vehiculo['placas']); ?></span>
                                    <span class="alerta-info"><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></span>
                                    <span class="alerta-fecha">Venció hace <?php echo $dias_vencidos; ?> día(s)</span>
                                    <a href="?editar=<?php echo $vehiculo['id_vehiculo']; ?>" class="btn-mini">
                                        <i class='bx bx-edit'></i> Editar
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (count($vehiculos_por_vencer) > 0): ?>
                    <div class="alerta alerta-advertencia">
                        <div class="alerta-header">
                            <i class='bx bx-time-five'></i>
                            <strong>Vehículos próximos a vencer en 3 semanas (<?php echo count($vehiculos_por_vencer); ?>)</strong>
                        </div>
                        <div class="alerta-contenido">
                            <?php foreach ($vehiculos_por_vencer as $vehiculo): 
                                $fecha_venc = new DateTime($vehiculo['fecha_de_vigencia']);
                                $dias_restantes = (new DateTime())->diff($fecha_venc)->days;
                                $semanas_restantes = floor($dias_restantes / 7);
                            ?>
                                <div class="alerta-item">
                                    <span class="alerta-placas"><?php echo htmlspecialchars($vehiculo['placas']); ?></span>
                                    <span class="alerta-info"><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></span>
                                    <span class="alerta-fecha">
                                        Vence en <?php echo $dias_restantes; ?> día(s) 
                                        <?php if ($semanas_restantes > 0): ?>
                                            (<?php echo $semanas_restantes; ?> semana<?php echo $semanas_restantes > 1 ? 's' : ''; ?>)
                                        <?php endif; ?>
                                        - <?php echo $fecha_venc->format('d/m/Y'); ?>
                                    </span>
                                    <a href="?editar=<?php echo $vehiculo['id_vehiculo']; ?>" class="btn-mini">
                                        <i class='bx bx-edit'></i> Editar
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Mensajes de éxito o error -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para agregar/editar vehículo -->
        <div class="card">
            <h2>
                <?php echo $vehiculo_editar ? '<i class="bx bx-edit"></i> Editar Vehículo' : '<i class="bx bx-plus-circle"></i> Registrar Nuevo Vehículo'; ?>
            </h2>
            <form method="POST" action="" class="form-vehiculo" id="formVehiculo">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="<?php echo $vehiculo_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($vehiculo_editar): ?>
                    <input type="hidden" name="id_vehiculo" value="<?php echo $vehiculo_editar['id_vehiculo']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="placas">
                            <i class='bx bx-id-card'></i> Placas *
                        </label>
                        <input type="text" 
                               id="placas" 
                               name="placas" 
                               value="<?php echo $vehiculo_editar ? htmlspecialchars($vehiculo_editar['placas']) : ''; ?>"
                               placeholder="Ej: ABC123D"
                               required
                               minlength="3"
                               maxlength="7"
                               style="text-transform: uppercase;">
                        <div class="letra-count" id="letra-count">Letras: 0/5 | Total: 0/7</div>
                        <div class="error-message" id="error-placas">Solo letras y números sin espacios (máx. 7 caracteres, máx. 5 letras)</div>
                    </div>

                    <div class="form-group">
                        <label for="marca">
                            <i class='bx bx-shield'></i> Marca * <span class="char-limit">(máx. 10)</span>
                        </label>
                        <input type="text" 
                               id="marca" 
                               name="marca" 
                               value="<?php echo $vehiculo_editar ? htmlspecialchars($vehiculo_editar['marca']) : ''; ?>"
                               placeholder="Ej: Toyota"
                               required
                               minlength="2"
                               maxlength="10">
                        <div class="char-counter" id="marca-counter">0/10</div>
                        <div class="error-message" id="error-marca">Solo letras sin espacios (2-10 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="modelo">
                            <i class='bx bx-car'></i> Modelo * <span class="char-limit">(máx. 15)</span>
                        </label>
                        <input type="text" 
                               id="modelo" 
                               name="modelo" 
                               value="<?php echo $vehiculo_editar ? htmlspecialchars($vehiculo_editar['modelo']) : ''; ?>"
                               placeholder="Ej: Corolla 2020"
                               required
                               minlength="2"
                               maxlength="15">
                        <div class="char-counter" id="modelo-counter">0/15</div>
                        <div class="error-message" id="error-modelo">Solo letras y números (2-15 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="color">
                            <i class='bx bx-palette'></i> Color * <span class="char-limit">(máx. 10)</span>
                        </label>
                        <input type="text" 
                               id="color" 
                               name="color" 
                               value="<?php echo $vehiculo_editar ? htmlspecialchars($vehiculo_editar['color']) : ''; ?>"
                               placeholder="Ej: Blanco"
                               required
                               minlength="2"
                               maxlength="10">
                        <div class="char-counter" id="color-counter">0/10</div>
                        <div class="error-message" id="error-color">Solo letras (2-10 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="fecha_vigencia">
                            <i class='bx bx-calendar'></i> Fecha de Vigencia
                        </label>
                        <input type="date" 
                               id="fecha_vigencia" 
                               name="fecha_vigencia" 
                               value="<?php echo $vehiculo_editar ? $vehiculo_editar['fecha_de_vigencia'] : ''; ?>"
                               min="<?php echo $fecha_minima; ?>"
                               max="<?php echo $fecha_maxima; ?>">
                        <div class="error-message" id="error-fecha">La fecha debe estar entre 2 años atrás y 10 años adelante</div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx <?php echo $vehiculo_editar ? 'bx-save' : 'bx-plus'; ?>'></i>
                        <?php echo $vehiculo_editar ? 'Actualizar' : 'Registrar'; ?>
                    </button>
                    <?php if ($vehiculo_editar): ?>
                        <a href="vehiculos.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabla de vehículos -->
        <div class="card">
            <div class="table-header-section">
                <h2><i class='bx bx-list-ul'></i> Lista de Vehículos (<span id="total-vehiculos"><?php echo $total_registros; ?></span>)</h2>
                
                <!-- Filtros de Estado -->
                <div class="filter-buttons">
                    <button type="button" class="filter-btn active" data-filter="todos">
                        <i class='bx bx-list-ul'></i> Todos
                    </button>
                    <button type="button" class="filter-btn" data-filter="activo">
                        <i class='bx bx-check-circle'></i> Activos
                    </button>
                    <button type="button" class="filter-btn" data-filter="inactivo">
                        <i class='bx bx-x-circle'></i> Inactivos
                    </button>
                </div>
            </div>
            
            <!-- Buscador -->
            <div class="search-box">
                <i class='bx bx-search'></i>
                <input type="text" 
                       id="searchInput" 
                       placeholder="Buscar por placas, marca, modelo o color..."
                       autocomplete="off">
                <button type="button" class="clear-search" id="clearSearch">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <?php if (count($vehiculos) > 0): ?>
                <div class="table-container">
                    <table class="table-vehiculos">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Placas</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Color</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="vehiculosTableBody">
                            <?php foreach ($vehiculos as $vehiculo): ?>
                                <tr class="vehiculo-row" 
                                    data-estado="<?php echo $vehiculo['estado']; ?>"
                                    data-vehiculo='<?php echo json_encode([
                                        "placas" => $vehiculo['placas'],
                                        "marca" => $vehiculo['marca'],
                                        "modelo" => $vehiculo['modelo'],
                                        "color" => $vehiculo['color']
                                    ]); ?>'>
                                    <td><?php echo $vehiculo['id_vehiculo']; ?></td>
                                    <td class="placas"><?php echo htmlspecialchars($vehiculo['placas']); ?></td>
                                    <td><?php echo htmlspecialchars($vehiculo['marca']); ?></td>
                                    <td><?php echo htmlspecialchars($vehiculo['modelo']); ?></td>
                                    <td>
                                        <div class="color-display">
                                            <span class="color-circle" style="background-color: <?php echo obtenerCodigoColor($vehiculo['color']); ?>; <?php echo (strtolower($vehiculo['color']) === 'blanco') ? 'border: 2px solid #e5e7eb;' : ''; ?>"></span>
                                            <span class="color-name"><?php echo htmlspecialchars($vehiculo['color']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($vehiculo['fecha_de_vigencia']) {
                                            $fecha = new DateTime($vehiculo['fecha_de_vigencia']);
                                            $hoy = new DateTime();
                                            $diferencia = $hoy->diff($fecha);
                                            
                                            if ($fecha < $hoy) {
                                                echo '<span class="badge-vencido"><i class="bx bx-x-circle"></i> Vencido</span>';
                                            } elseif ($diferencia->days <= 21) {
                                                echo '<span class="badge-por-vencer"><i class="bx bx-time"></i> ' . $fecha->format('d/m/Y') . '</span>';
                                            } else {
                                                echo '<span class="badge-vigente">' . $fecha->format('d/m/Y') . '</span>';
                                            }
                                        } else {
                                            echo '<span class="text-muted">No especificada</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-estado badge-estado-<?php echo $vehiculo['estado']; ?>">
                                            <i class='bx <?php echo $vehiculo['estado'] === 'activo' ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i>
                                            <?php echo ucfirst($vehiculo['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="?editar=<?php echo $vehiculo['id_vehiculo']; ?>" 
                                           class="btn-action btn-edit" 
                                           title="Editar">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="cambiar_estado">
                                            <input type="hidden" name="id_vehiculo" value="<?php echo $vehiculo['id_vehiculo']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $vehiculo['estado'] === 'activo' ? 'inactivo' : 'activo'; ?>">
                                            <button type="submit" 
                                                    class="btn-action <?php echo $vehiculo['estado'] === 'activo' ? 'btn-inactivar' : 'btn-activar'; ?>" 
                                                    title="<?php echo $vehiculo['estado'] === 'activo' ? 'Inactivar' : 'Activar'; ?>">
                                                <i class='bx <?php echo $vehiculo['estado'] === 'activo' ? 'bx-toggle-right' : 'bx-toggle-left'; ?>'></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="?pagina=<?php echo $pagina_actual - 1; ?>" class="pagination-btn">
                                <i class='bx bx-chevron-left'></i> Anterior
                            </a>
                        <?php endif; ?>

                        <div class="pagination-numbers">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                                    <a href="?pagina=<?php echo $i; ?>" 
                                       class="pagination-number <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                                    <span class="pagination-dots">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="?pagina=<?php echo $pagina_actual + 1; ?>" class="pagination-btn">
                                Siguiente <i class='bx bx-chevron-right'></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="no-results" id="noResults" style="display: none;">
                    <i class='bx bx-search-alt' style="font-size: 48px; color: #bdc3c7;"></i>
                    <p>No se encontraron vehículos que coincidan con tu búsqueda</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-car'></i>
                    <p>No hay vehículos registrados</p>
                    <p class="text-muted">Agrega tu primer vehículo usando el formulario de arriba</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/vehiculos.js"></script>
</body>
</html>