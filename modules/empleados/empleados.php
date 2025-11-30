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

// Función para validar que solo contenga letras, espacios y acentos
function validarNombre($texto) {
    return preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $texto);
}

// Función para validar teléfono (solo números, exactamente 10 dígitos)
function validarTelefono($telefono) {
    return preg_match("/^[0-9]{10}$/", $telefono);
}

// Función para validar zona
function validarZona($zona) {
    $zonas_validas = ['XZ', 'WZ', 'VZ', 'KZ', 'AKZ', 'TZ', 'RZ', 'YZ'];
    return in_array($zona, $zonas_validas);
}

// Función para capitalizar nombres correctamente
function capitalizarNombre($texto) {
    // Convertir a minúsculas primero
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    // Capitalizar cada palabra
    return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
}

// Función para limpiar espacios múltiples
function limpiarEspacios($texto) {
    return preg_replace('/\s+/', ' ', trim($texto));
}

// Función para validar fecha de ingreso
function validarFechaIngreso($fecha) {
    $fecha_obj = new DateTime($fecha);
    $hoy = new DateTime();
    $hace_50_anos = new DateTime('-50 years');
    
    // No puede ser fecha futura
    if ($fecha_obj > $hoy) {
        return ['valido' => false, 'mensaje' => 'La fecha de ingreso no puede ser futura'];
    }
    
    // No puede ser más de 50 años atrás
    if ($fecha_obj < $hace_50_anos) {
        return ['valido' => false, 'mensaje' => 'La fecha de ingreso no puede ser mayor a 50 años'];
    }
    
    return ['valido' => true, 'mensaje' => ''];
}

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// CREAR - Agregar nuevo empleado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $nombre = limpiarEspacios($_POST['nombre']);
        $apellido_paterno = limpiarEspacios($_POST['apellido_paterno']);
        $apellido_materno = limpiarEspacios($_POST['apellido_materno']);
        $telefono = trim($_POST['telefono']);
        $direccion = trim($_POST['direccion']);
        $fecha_ingreso = $_POST['fecha_ingreso'];
        $estado = $_POST['estado'];
        $rol = $_POST['rol'];
        $zona = $_POST['zona'];
        
        // Capitalizar nombres
        $nombre = capitalizarNombre($nombre);
        $apellido_paterno = capitalizarNombre($apellido_paterno);
        $apellido_materno = capitalizarNombre($apellido_materno);
        
        // Validar longitud mínima
        if (strlen($nombre) < 2) {
            $mensaje = "Error: El nombre debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (strlen($apellido_paterno) < 2) {
            $mensaje = "Error: El apellido paterno debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (strlen($apellido_materno) < 2) {
            $mensaje = "Error: El apellido materno debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($nombre)) {
            $mensaje = "Error: El nombre solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($apellido_paterno)) {
            $mensaje = "Error: El apellido paterno solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($apellido_materno)) {
            $mensaje = "Error: El apellido materno solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarTelefono($telefono)) {
            $mensaje = "Error: El teléfono debe contener exactamente 10 dígitos numéricos";
            $tipo_mensaje = "error";
        } elseif (!validarZona($zona)) {
            $mensaje = "Error: Zona no válida";
            $tipo_mensaje = "error";
        } else {
            // Validar fecha
            $validacion_fecha = validarFechaIngreso($fecha_ingreso);
            if (!$validacion_fecha['valido']) {
                $mensaje = "Error: " . $validacion_fecha['mensaje'];
                $tipo_mensaje = "error";
            } else {
                try {
                    $sql = "INSERT INTO Empleados (nombre, apellido_paterno, apellido_materno, telefono, direccion, fecha_ingreso, estado, rol, zona) 
                            VALUES (:nombre, :apellido_paterno, :apellido_materno, :telefono, :direccion, :fecha_ingreso, :estado, :rol, :zona)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':apellido_paterno', $apellido_paterno);
                    $stmt->bindParam(':apellido_materno', $apellido_materno);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':direccion', $direccion);
                    $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->bindParam(':rol', $rol);
                    $stmt->bindParam(':zona', $zona);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Empleado registrado exitosamente";
                        $tipo_mensaje = "success";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        if (strpos($e->getMessage(), 'telefono') !== false) {
                            $mensaje = "Error: El teléfono ya está registrado";
                        } else {
                            $mensaje = "Error: Ya existe un empleado con ese nombre completo";
                        }
                    } else {
                        $mensaje = "Error al registrar empleado: " . $e->getMessage();
                    }
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}

// ACTUALIZAR - Modificar empleado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $nombre = limpiarEspacios($_POST['nombre']);
        $apellido_paterno = limpiarEspacios($_POST['apellido_paterno']);
        $apellido_materno = limpiarEspacios($_POST['apellido_materno']);
        $telefono = trim($_POST['telefono']);
        $direccion = trim($_POST['direccion']);
        $fecha_ingreso = $_POST['fecha_ingreso'];
        $estado = $_POST['estado'];
        $rol = $_POST['rol'];
        $zona = $_POST['zona'];
        
        // Capitalizar nombres
        $nombre = capitalizarNombre($nombre);
        $apellido_paterno = capitalizarNombre($apellido_paterno);
        $apellido_materno = capitalizarNombre($apellido_materno);
        
        // Validar longitud mínima
        if (strlen($nombre) < 2) {
            $mensaje = "Error: El nombre debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (strlen($apellido_paterno) < 2) {
            $mensaje = "Error: El apellido paterno debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (strlen($apellido_materno) < 2) {
            $mensaje = "Error: El apellido materno debe tener al menos 2 caracteres";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($nombre)) {
            $mensaje = "Error: El nombre solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($apellido_paterno)) {
            $mensaje = "Error: El apellido paterno solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarNombre($apellido_materno)) {
            $mensaje = "Error: El apellido materno solo debe contener letras";
            $tipo_mensaje = "error";
        } elseif (!validarTelefono($telefono)) {
            $mensaje = "Error: El teléfono debe contener exactamente 10 dígitos numéricos";
            $tipo_mensaje = "error";
        } elseif (!validarZona($zona)) {
            $mensaje = "Error: Zona no válida";
            $tipo_mensaje = "error";
        } else {
            // Validar fecha
            $validacion_fecha = validarFechaIngreso($fecha_ingreso);
            if (!$validacion_fecha['valido']) {
                $mensaje = "Error: " . $validacion_fecha['mensaje'];
                $tipo_mensaje = "error";
            } else {
                try {
                    $sql = "UPDATE Empleados 
                            SET nombre = :nombre, apellido_paterno = :apellido_paterno, 
                                apellido_materno = :apellido_materno, telefono = :telefono, 
                                direccion = :direccion, fecha_ingreso = :fecha_ingreso, 
                                estado = :estado, rol = :rol, zona = :zona
                            WHERE id_empleado = :id_empleado";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_empleado', $id_empleado);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':apellido_paterno', $apellido_paterno);
                    $stmt->bindParam(':apellido_materno', $apellido_materno);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':direccion', $direccion);
                    $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->bindParam(':rol', $rol);
                    $stmt->bindParam(':zona', $zona);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Empleado actualizado exitosamente";
                        $tipo_mensaje = "success";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        if (strpos($e->getMessage(), 'telefono') !== false) {
                            $mensaje = "Error: El teléfono ya está registrado en otro empleado";
                        } else {
                            $mensaje = "Error: Ya existe otro empleado con ese nombre completo";
                        }
                    } else {
                        $mensaje = "Error al actualizar empleado: " . $e->getMessage();
                    }
                    $tipo_mensaje = "error";
                }
            }
        }
    }
}

// ELIMINAR - Borrar empleado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        
        try {
            $sql = "DELETE FROM Empleados WHERE id_empleado = :id_empleado";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id_empleado', $id_empleado);
            
            if ($stmt->execute()) {
                $mensaje = "Empleado eliminado exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar empleado: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// PAGINACIÓN Y FILTROS
$filtro_estado = isset($_GET['filtro_estado']) ? $_GET['filtro_estado'] : 'todos';
$filtro_rol = isset($_GET['filtro_rol']) ? $_GET['filtro_rol'] : 'todos';
$filtro_zona = isset($_GET['filtro_zona']) ? $_GET['filtro_zona'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 5;

// Construir consulta base
$sql_count = "SELECT COUNT(*) FROM Empleados WHERE 1=1";
$sql = "SELECT * FROM Empleados WHERE 1=1";
$params = [];

// Aplicar filtros
if ($filtro_estado !== 'todos') {
    $sql_count .= " AND estado = :estado";
    $sql .= " AND estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($filtro_rol !== 'todos') {
    $sql_count .= " AND rol = :rol";
    $sql .= " AND rol = :rol";
    $params[':rol'] = $filtro_rol;
}

if ($filtro_zona !== 'todos') {
    $sql_count .= " AND zona = :zona";
    $sql .= " AND zona = :zona";
    $params[':zona'] = $filtro_zona;
}

// Aplicar búsqueda
if (!empty($busqueda)) {
    $sql_count .= " AND (nombre LIKE :busqueda OR apellido_paterno LIKE :busqueda OR apellido_materno LIKE :busqueda OR telefono LIKE :busqueda OR direccion LIKE :busqueda)";
    $sql .= " AND (nombre LIKE :busqueda OR apellido_paterno LIKE :busqueda OR apellido_materno LIKE :busqueda OR telefono LIKE :busqueda OR direccion LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

// Contar total de registros
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetchColumn();

// Calcular paginación
$total_paginas = ceil($total_registros / $registros_por_pagina);
$pagina_actual = max(1, min($pagina_actual, $total_paginas));
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener registros de la página actual
$sql .= " ORDER BY id_empleado DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleado para editar
$empleado_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $sql = "SELECT * FROM Empleados WHERE id_empleado = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id_editar);
    $stmt->execute();
    $empleado_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calcular fecha mínima (hace 50 años) y máxima (hoy)
$fecha_minima = date('Y-m-d', strtotime('-50 years'));
$fecha_maxima = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/empleados.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-user'></i> Gestión de Empleados</h1>

        <!-- Mensajes de éxito o error -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para agregar/editar empleado -->
        <div class="card">
            <h2>
                <?php echo $empleado_editar ? '<i class="bx bx-edit"></i> Editar Empleado' : '<i class="bx bx-plus-circle"></i> Registrar Nuevo Empleado'; ?>
            </h2>
            <form method="POST" action="" class="form-empleado" id="formEmpleado">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="<?php echo $empleado_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($empleado_editar): ?>
                    <input type="hidden" name="id_empleado" value="<?php echo $empleado_editar['id_empleado']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">
                            <i class='bx bx-user'></i> Nombre *
                        </label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               value="<?php echo $empleado_editar ? htmlspecialchars($empleado_editar['nombre']) : ''; ?>"
                               placeholder="Ej: Juan"
                               required 
                               minlength="2"
                               maxlength="20"
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
                               title="Solo se permiten letras y espacios (mínimo 2, máximo 20 caracteres)">
                        <small class="char-counter" id="counter-nombre">0/20</small>
                        <div class="error-message" id="error-nombre">Solo se permiten letras y espacios (mínimo 2 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="apellido_paterno">
                            <i class='bx bx-user'></i> Apellido Paterno *
                        </label>
                        <input type="text" 
                               id="apellido_paterno" 
                               name="apellido_paterno" 
                               value="<?php echo $empleado_editar ? htmlspecialchars($empleado_editar['apellido_paterno']) : ''; ?>"
                               placeholder="Ej: Pérez"
                               required 
                               minlength="2"
                               maxlength="20"
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
                               title="Solo se permiten letras y espacios (mínimo 2, máximo 20 caracteres)">
                        <small class="char-counter" id="counter-apellido_paterno">0/20</small>
                        <div class="error-message" id="error-apellido_paterno">Solo se permiten letras y espacios (mínimo 2 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="apellido_materno">
                            <i class='bx bx-user'></i> Apellido Materno *
                        </label>
                        <input type="text" 
                               id="apellido_materno" 
                               name="apellido_materno" 
                               value="<?php echo $empleado_editar ? htmlspecialchars($empleado_editar['apellido_materno']) : ''; ?>"
                               placeholder="Ej: González"
                               required 
                               minlength="2"
                               maxlength="20"
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
                               title="Solo se permiten letras y espacios (mínimo 2, máximo 20 caracteres)">
                        <small class="char-counter" id="counter-apellido_materno">0/20</small>
                        <div class="error-message" id="error-apellido_materno">Solo se permiten letras y espacios (mínimo 2 caracteres)</div>
                    </div>

                    <div class="form-group">
                        <label for="telefono">
                            <i class='bx bx-phone'></i> Teléfono *
                        </label>
                        <input type="text" 
                               id="telefono" 
                               name="telefono" 
                               value="<?php echo $empleado_editar ? htmlspecialchars($empleado_editar['telefono']) : ''; ?>"
                               placeholder="Ej: 7221234567"
                               required 
                               maxlength="10"
                               pattern="[0-9]{10}"
                               inputmode="numeric"
                               title="Debe contener exactamente 10 dígitos numéricos">
                        <small class="char-counter" id="counter-telefono">0/10</small>
                        <div class="error-message" id="error-telefono">Solo se permiten números (10 dígitos)</div>
                    </div>

                    <div class="form-group">
                        <label for="direccion">
                            <i class='bx bx-map'></i> Dirección
                        </label>
                        <input type="text" 
                               id="direccion" 
                               name="direccion" 
                               value="<?php echo $empleado_editar ? htmlspecialchars($empleado_editar['direccion']) : ''; ?>"
                               placeholder="Ej: Calle Principal #123"
                               maxlength="150">
                        <small class="char-counter" id="counter-direccion">0/150</small>
                    </div>

                    <div class="form-group">
                        <label for="fecha_ingreso">
                            <i class='bx bx-calendar'></i> Fecha de Ingreso *
                        </label>
                        <input type="date" 
                               id="fecha_ingreso" 
                               name="fecha_ingreso" 
                               value="<?php echo $empleado_editar ? $empleado_editar['fecha_ingreso'] : date('Y-m-d'); ?>"
                               min="<?php echo $fecha_minima; ?>"
                               max="<?php echo $fecha_maxima; ?>"
                               required>
                        <div class="error-message" id="error-fecha">La fecha no puede ser futura ni mayor a 50 años</div>
                    </div>

                    <div class="form-group">
                        <label for="rol">
                            <i class='bx bx-briefcase'></i> Rol *
                        </label>
                        <select id="rol" name="rol" required>
                            <option value="vendedor" <?php echo ($empleado_editar && $empleado_editar['rol'] == 'vendedor') ? 'selected' : ''; ?>>Vendedor</option>
                            <option value="cobrador" <?php echo ($empleado_editar && $empleado_editar['rol'] == 'cobrador') ? 'selected' : ''; ?>>Cobrador</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="zona">
                            <i class='bx bx-map'></i> Zona *
                        </label>
                        <select id="zona" name="zona" required>
                            <option value="XZ" <?php echo (!$empleado_editar || $empleado_editar['zona'] == 'XZ') ? 'selected' : ''; ?>>XZ</option>
                            <option value="WZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'WZ') ? 'selected' : ''; ?>>WZ</option>
                            <option value="VZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'VZ') ? 'selected' : ''; ?>>VZ</option>
                            <option value="KZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'KZ') ? 'selected' : ''; ?>>KZ</option>
                            <option value="AKZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'AKZ') ? 'selected' : ''; ?>>AKZ</option>
                            <option value="TZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'TZ') ? 'selected' : ''; ?>>TZ</option>
                            <option value="RZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'RZ') ? 'selected' : ''; ?>>RZ</option>
                            <option value="YZ" <?php echo ($empleado_editar && $empleado_editar['zona'] == 'YZ') ? 'selected' : ''; ?>>YZ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">
                            <i class='bx bx-toggle-right'></i> Estado *
                        </label>
                        <select id="estado" name="estado" required>
                            <option value="activo" <?php echo (!$empleado_editar || $empleado_editar['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($empleado_editar && $empleado_editar['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx <?php echo $empleado_editar ? 'bx-save' : 'bx-plus'; ?>'></i>
                        <?php echo $empleado_editar ? 'Actualizar' : 'Registrar'; ?>
                    </button>
                    <?php if ($empleado_editar): ?>
                        <a href="empleados.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filtros -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros y Búsqueda</h2>
            <form method="GET" action="" class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="filtro_estado">Estado</label>
                        <select id="filtro_estado" name="filtro_estado">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivo" <?php echo $filtro_estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_rol">Puesto</label>
                        <select id="filtro_rol" name="filtro_rol">
                            <option value="todos" <?php echo $filtro_rol == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="vendedor" <?php echo $filtro_rol == 'vendedor' ? 'selected' : ''; ?>>Vendedores</option>
                            <option value="cobrador" <?php echo $filtro_rol == 'cobrador' ? 'selected' : ''; ?>>Cobradores</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_zona">Zona</label>
                        <select id="filtro_zona" name="filtro_zona">
                            <option value="todos" <?php echo $filtro_zona == 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <option value="XZ" <?php echo $filtro_zona == 'XZ' ? 'selected' : ''; ?>>XZ</option>
                            <option value="WZ" <?php echo $filtro_zona == 'WZ' ? 'selected' : ''; ?>>WZ</option>
                            <option value="VZ" <?php echo $filtro_zona == 'VZ' ? 'selected' : ''; ?>>VZ</option>
                            <option value="KZ" <?php echo $filtro_zona == 'KZ' ? 'selected' : ''; ?>>KZ</option>
                            <option value="AKZ" <?php echo $filtro_zona == 'AKZ' ? 'selected' : ''; ?>>AKZ</option>
                            <option value="TZ" <?php echo $filtro_zona == 'TZ' ? 'selected' : ''; ?>>TZ</option>
                            <option value="RZ" <?php echo $filtro_zona == 'RZ' ? 'selected' : ''; ?>>RZ</option>
                            <option value="YZ" <?php echo $filtro_zona == 'YZ' ? 'selected' : ''; ?>>YZ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="busqueda">Buscar</label>
                        <div class="search-form">
                            <input type="text" 
                                   id="busqueda" 
                                   name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>"
                                   placeholder="Nombre, teléfono, dirección..."
                                   style="flex: 1;">
                            <button type="submit">
                                <i class='bx bx-search'></i> Buscar
                            </button>
                            <?php if (!empty($busqueda) || $filtro_estado != 'todos' || $filtro_rol != 'todos' || $filtro_zona != 'todos'): ?>
                                <a href="empleados.php" class="btn btn-secondary" style="padding: 12px 20px; text-decoration: none;">
                                    <i class='bx bx-x'></i> Limpiar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de empleados -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Lista de Empleados (<?php echo $total_registros; ?>)</h2>

            <?php if (count($empleados) > 0): ?>
                <div class="table-container">
                    <table class="table-empleados" id="tablaEmpleados">
                        <thead>
                            <tr>
                                <th>No. Empleado</th>
                                <th>Nombre Completo</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th>Fecha Ingreso</th>
                                <th>Puesto</th>
                                <th>Zona</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empleados as $empleado): ?>
                                <tr>
                                    <td><?php echo $empleado['id_empleado']; ?></td>
                                    <td class="nombre-completo">
                                        <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']); ?>
                                    </td>
                                    <td>
                                        <i class='bx bx-phone'></i>
                                        <?php echo htmlspecialchars($empleado['telefono']); ?>
                                    </td>
                                    <td class="direccion">
                                        <?php echo $empleado['direccion'] ? htmlspecialchars($empleado['direccion']) : '<span class="text-muted">No especificada</span>'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($empleado['fecha_ingreso']) {
                                            $fecha = new DateTime($empleado['fecha_ingreso']);
                                            echo $fecha->format('d/m/Y');
                                        } else {
                                            echo '<span class="text-muted">No especificada</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="rol-badge <?php echo $empleado['rol']; ?>">
                                            <i class='bx <?php echo $empleado['rol'] == 'vendedor' ? 'bx-cart' : 'bx-money'; ?>'></i>
                                            <?php echo ucfirst($empleado['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="zona-badge <?php echo $empleado['zona']; ?>">
                                            <?php echo $empleado['zona']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="estado-badge <?php echo $empleado['estado']; ?>">
                                            <?php echo ucfirst($empleado['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="?editar=<?php echo $empleado['id_empleado']; ?>&pagina=<?php echo $pagina_actual; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                           class="btn-action btn-edit" 
                                           title="Editar">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> empleados
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="?pagina=1&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                   class="pagination-btn" title="Primera página">
                                    <i class='bx bx-chevrons-left'></i>
                                </a>
                                <a href="?pagina=<?php echo $pagina_actual - 1; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                   class="pagination-btn" title="Página anterior">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $rango_inicio = max(1, $pagina_actual - 2);
                            $rango_fin = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $rango_inicio; $i <= $rango_fin; $i++): 
                            ?>
                                <a href="?pagina=<?php echo $i; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                   class="pagination-btn <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="?pagina=<?php echo $pagina_actual + 1; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                   class="pagination-btn" title="Página siguiente">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                                <a href="?pagina=<?php echo $total_paginas; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_rol=<?php echo $filtro_rol; ?>&filtro_zona=<?php echo $filtro_zona; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
                                   class="pagination-btn" title="Última página">
                                    <i class='bx bx-chevrons-right'></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-search-alt' style="font-size: 48px;"></i>
                    <?php if (!empty($busqueda) || $filtro_estado != 'todos' || $filtro_rol != 'todos' || $filtro_zona != 'todos'): ?>
                        <p>No se encontraron empleados con los criterios de búsqueda</p>
                        <p class="text-muted">Intenta con otros filtros o búsqueda</p>
                        <a href="empleados.php" class="btn btn-primary" style="margin-top: 15px; text-decoration: none; display: inline-block;">
                            <i class='bx bx-refresh'></i> Ver todos los empleados
                        </a>
                    <?php else: ?>
                        <p>No hay empleados registrados</p>
                        <p class="text-muted">Agrega tu primer empleado usando el formulario de arriba</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== VARIABLES GLOBALES =====
            const camposNombre = ['nombre', 'apellido_paterno', 'apellido_materno'];
            const patronNombre = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/;
            const patronTelefono = /^[0-9]*$/;
            let formularioModificado = false;
            
            // ===== AUTO-SCROLL A MENSAJES =====
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // ===== CAPITALIZACIÓN AUTOMÁTICA =====
            function capitalizarTexto(texto) {
                return texto.toLowerCase().replace(/\b\w/g, function(letra) {
                    return letra.toUpperCase();
                });
            }

            camposNombre.forEach(campo => {
                const input = document.getElementById(campo);
                
                input.addEventListener('blur', function() {
                    if (this.value.trim()) {
                        this.value = capitalizarTexto(this.value.trim());
                        // Limpiar espacios múltiples
                        this.value = this.value.replace(/\s+/g, ' ');
                    }
                });
            });

            // ===== CONTADORES DE CARACTERES =====
            function updateCharCounter(inputId, max) {
                const input = document.getElementById(inputId);
                const counter = document.getElementById('counter-' + inputId);
                
                if (input && counter) {
                    const currentLength = input.value.length;
                    counter.textContent = currentLength + '/' + max;
                    
                    // Cambiar color según proximidad al límite
                    counter.classList.remove('warning', 'limit');
                    if (currentLength >= max) {
                        counter.classList.add('limit');
                    } else if (currentLength >= max * 0.8) {
                        counter.classList.add('warning');
                    }
                }
            }

            // Inicializar contadores al cargar
            camposNombre.forEach(campo => {
                updateCharCounter(campo, 20);
            });
            updateCharCounter('direccion', 150);
            updateCharCounter('telefono', 10);

            // Actualizar contadores en tiempo real
            camposNombre.forEach(campo => {
                const input = document.getElementById(campo);
                input.addEventListener('input', () => updateCharCounter(campo, 20));
            });
            document.getElementById('direccion').addEventListener('input', () => updateCharCounter('direccion', 150));
            document.getElementById('telefono').addEventListener('input', () => updateCharCounter('telefono', 10));

            // ===== VALIDACIÓN CAMPOS DE NOMBRE =====
            camposNombre.forEach(campo => {
                const input = document.getElementById(campo);
                const errorMsg = document.getElementById('error-' + campo);

                input.addEventListener('input', function(e) {
                    formularioModificado = true;
                    const valor = e.target.value;
                    
                    if (!patronNombre.test(valor)) {
                        input.classList.add('input-error');
                        errorMsg.classList.add('show');
                        e.target.value = valor.slice(0, -1);
                    } else {
                        if (valor.length >= 2 || valor.length === 0) {
                            input.classList.remove('input-error');
                            errorMsg.classList.remove('show');
                        }
                    }
                });

                input.addEventListener('blur', function(e) {
                    const valor = e.target.value.trim();
                    if (valor && (valor.length < 2 || !patronNombre.test(valor))) {
                        input.classList.add('input-error');
                        errorMsg.classList.add('show');
                    }
                });
            });

            // ===== VALIDACIÓN TELÉFONO =====
            const inputTelefono = document.getElementById('telefono');
            const errorTelefono = document.getElementById('error-telefono');

            inputTelefono.addEventListener('input', function(e) {
                formularioModificado = true;
                const valor = e.target.value;
                
                if (!patronTelefono.test(valor)) {
                    inputTelefono.classList.add('input-error');
                    errorTelefono.classList.add('show');
                    e.target.value = valor.slice(0, -1);
                } else {
                    if (valor.length === 10 || valor.length === 0) {
                        inputTelefono.classList.remove('input-error');
                        errorTelefono.classList.remove('show');
                    } else if (valor.length > 0) {
                        inputTelefono.classList.add('input-error');
                        errorTelefono.classList.add('show');
                        errorTelefono.textContent = 'Debe contener exactamente 10 dígitos (' + valor.length + '/10)';
                    }
                }
            });

            // ===== VALIDACIÓN FECHA =====
            const inputFecha = document.getElementById('fecha_ingreso');
            const errorFecha = document.getElementById('error-fecha');
            const fechaMaxima = new Date('<?php echo $fecha_maxima; ?>');
            const fechaMinima = new Date('<?php echo $fecha_minima; ?>');

            inputFecha.addEventListener('change', function() {
                formularioModificado = true;
                const fechaSeleccionada = new Date(this.value);
                
                if (fechaSeleccionada > fechaMaxima) {
                    inputFecha.classList.add('input-error');
                    errorFecha.classList.add('show');
                    errorFecha.textContent = 'La fecha no puede ser futura';
                } else if (fechaSeleccionada < fechaMinima) {
                    inputFecha.classList.add('input-error');
                    errorFecha.classList.add('show');
                    errorFecha.textContent = 'La fecha no puede ser mayor a 50 años';
                } else {
                    inputFecha.classList.remove('input-error');
                    errorFecha.classList.remove('show');
                }
            });

            // ===== DETECTAR CAMBIOS EN OTROS CAMPOS =====
            const otrosCampos = document.querySelectorAll('#direccion, #rol, #estado, #zona');
            otrosCampos.forEach(campo => {
                campo.addEventListener('change', function() {
                    formularioModificado = true;
                });
            });

            // ===== VALIDACIÓN ANTES DE ENVIAR =====
            document.getElementById('formEmpleado').addEventListener('submit', function(e) {
                let esValido = true;

                // Validar nombres
                camposNombre.forEach(campo => {
                    const input = document.getElementById(campo);
                    const valor = input.value.trim();
                    const errorMsg = document.getElementById('error-' + campo);

                    if (valor.length < 2 || !patronNombre.test(valor)) {
                        e.preventDefault();
                        input.classList.add('input-error');
                        errorMsg.classList.add('show');
                        esValido = false;
                    }
                });

                // Validar teléfono
                const telefonoValor = inputTelefono.value.trim();
                if (!patronTelefono.test(telefonoValor) || telefonoValor.length !== 10) {
                    e.preventDefault();
                    inputTelefono.classList.add('input-error');
                    errorTelefono.classList.add('show');
                    errorTelefono.textContent = 'Solo se permiten números (10 dígitos)';
                    esValido = false;
                }

                // Validar fecha
                const fechaSeleccionada = new Date(inputFecha.value);
                if (fechaSeleccionada > fechaMaxima || fechaSeleccionada < fechaMinima) {
                    e.preventDefault();
                    inputFecha.classList.add('input-error');
                    errorFecha.classList.add('show');
                    esValido = false;
                }

                if (!esValido) {
                    alert('Por favor, corrija los errores en el formulario.');
                } else {
                    formularioModificado = false;
                }
            });

            // ===== ADVERTENCIA AL SALIR SIN GUARDAR =====
            window.addEventListener('beforeunload', function(e) {
                if (formularioModificado) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            // Resetear flag cuando se cancela
            const btnCancelar = document.querySelector('.btn-secondary');
            if (btnCancelar) {
                btnCancelar.addEventListener('click', function() {
                    formularioModificado = false;
                });
            }
        });
    </script>
</body>
</html>