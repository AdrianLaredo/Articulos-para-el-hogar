<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

// Prevenir caché
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

// Función para obtener el mes en español
function obtenerMesEspanol($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$mes];
}

// Función para obtener años disponibles
function obtenerAniosDisponibles($conn) {
    $query = "SELECT DISTINCT strftime('%Y', fecha_inicio) as anio FROM Semanas_Cobro ORDER BY anio DESC";
    $stmt = $conn->query($query);
    $anios = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $anios[] = $row['anio'];
    }
    if (empty($anios)) {
        $anios[] = date('Y');
    }
    return $anios;
}

// Función para obtener semanas por mes y año
function obtenerSemanasPorMesAnio($conn, $mes, $anio) {
    $mes_espanol = obtenerMesEspanol($mes);
    $query = "SELECT * FROM Semanas_Cobro WHERE mes = :mes AND anio = :anio ORDER BY fecha_inicio ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':mes', $mes_espanol);
    $stmt->bindValue(':anio', $anio);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$mensaje = '';
$tipo_mensaje = '';

// REGISTRAR GASTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_gasto') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_categoria = $_POST['id_categoria'];
        $descripcion = trim($_POST['descripcion']);
        $articulo_servicio = trim($_POST['articulo_servicio']);
        $cantidad = intval($_POST['cantidad']);
        $total_gasto = floatval($_POST['total_gasto']);
        
        // La fecha siempre es HOY con hora actual
        $fecha_gasto = date('Y-m-d');
        $fecha_registro = date('Y-m-d H:i:s');
        
        try {
            $sql = "INSERT INTO Gastos 
                    (fecha_gasto, id_categoria, descripcion, articulo_servicio, cantidad, total_gasto, registrado_por, fecha_registro) 
                    VALUES 
                    (:fecha_gasto, :id_categoria, :descripcion, :articulo_servicio, :cantidad, :total_gasto, :registrado_por, :fecha_registro)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':fecha_gasto', $fecha_gasto);
            $stmt->bindParam(':id_categoria', $id_categoria);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':articulo_servicio', $articulo_servicio);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':total_gasto', $total_gasto);
            $stmt->bindParam(':registrado_por', $_SESSION['usuario']);
            $stmt->bindParam(':fecha_registro', $fecha_registro);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Gasto registrado exitosamente por $" . number_format($total_gasto, 2);
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al registrar gasto: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// ELIMINAR GASTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_gasto') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_gasto = $_POST['id_gasto'];
        
        try {
            $sql = "DELETE FROM Gastos WHERE id_gasto = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id_gasto);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Gasto eliminado exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// CREAR CATEGORÍA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_categoria') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $nombre_categoria = trim($_POST['nombre_categoria']);
        $descripcion_categoria = trim($_POST['descripcion_categoria']);
        
        try {
            $fecha_creacion = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO Categorias_Gastos 
                    (nombre_categoria, descripcion, creado_por, fecha_creacion) 
                    VALUES 
                    (:nombre_categoria, :descripcion, :creado_por, :fecha_creacion)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nombre_categoria', $nombre_categoria);
            $stmt->bindParam(':descripcion', $descripcion_categoria);
            $stmt->bindParam(':creado_por', $_SESSION['usuario']);
            $stmt->bindParam(':fecha_creacion', $fecha_creacion);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Categoría creada exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                $mensaje = "⚠️ Error: Ya existe una categoría con ese nombre";
                $tipo_mensaje = "error";
            } else {
                $mensaje = "Error al crear categoría: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// CAMBIAR ESTADO DE CATEGORÍA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado_categoria') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_categoria = $_POST['id_categoria'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        try {
            $sql = "UPDATE Categorias_Gastos SET estado = :estado WHERE id_categoria = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':estado', $nuevo_estado);
            $stmt->bindParam(':id', $id_categoria);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Estado de categoría actualizado";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar estado: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? max(10, min(100, intval($_GET['registros']))) : 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener años disponibles
$anios_disponibles = obtenerAniosDisponibles($conn);
$anio_actual = date('Y');
$mes_actual = date('n');

// Obtener semanas activas
$query_semanas_activas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio ASC";
$stmt_semanas_activas = $conn->query($query_semanas_activas);
$semanas_activas = $stmt_semanas_activas->fetchAll(PDO::FETCH_ASSOC);

// Determinar semana actual
$semana_actual = null;
$hoy = date('Y-m-d');
foreach ($semanas_activas as $sem) {
    if ($hoy >= $sem['fecha_inicio'] && $hoy <= $sem['fecha_fin']) {
        $semana_actual = $sem;
        break;
    }
}

// Filtros
$filtro_anio = isset($_GET['anio']) ? $_GET['anio'] : ($semana_actual ? date('Y', strtotime($semana_actual['fecha_inicio'])) : $anio_actual);
$filtro_mes = isset($_GET['mes']) ? $_GET['mes'] : ($semana_actual ? date('n', strtotime($semana_actual['fecha_inicio'])) : $mes_actual);
$filtro_semana = isset($_GET['semana']) ? $_GET['semana'] : ($semana_actual ? $semana_actual['id_semana'] : '');
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Obtener semanas según año/mes seleccionado
$semanas_filtradas = [];
if ($filtro_anio && $filtro_mes) {
    $semanas_filtradas = obtenerSemanasPorMesAnio($conn, $filtro_mes, $filtro_anio);
}

// Obtener categorías activas
$query_categorias = "SELECT * FROM Categorias_Gastos WHERE estado = 'activo' ORDER BY nombre_categoria";
$stmt_categorias = $conn->query($query_categorias);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Construir query de gastos
$where = ["1=1"];
$params = [];

if ($filtro_categoria) {
    $where[] = "g.id_categoria = :categoria";
    $params[':categoria'] = $filtro_categoria;
}

if ($filtro_semana) {
    // Obtener fechas de la semana
    $query_semana = "SELECT fecha_inicio, fecha_fin FROM Semanas_Cobro WHERE id_semana = :id_semana";
    $stmt_semana = $conn->prepare($query_semana);
    $stmt_semana->bindValue(':id_semana', $filtro_semana);
    $stmt_semana->execute();
    $semana_seleccionada = $stmt_semana->fetch(PDO::FETCH_ASSOC);
    
    if ($semana_seleccionada) {
        $where[] = "g.fecha_gasto BETWEEN :fecha_inicio AND :fecha_fin";
        $params[':fecha_inicio'] = $semana_seleccionada['fecha_inicio'];
        $params[':fecha_fin'] = $semana_seleccionada['fecha_fin'];
    }
}

$where_clause = implode(" AND ", $where);

// Contar total de registros
$query_count = "SELECT COUNT(*) as total 
                FROM Gastos g
                INNER JOIN Categorias_Gastos c ON g.id_categoria = c.id_categoria
                WHERE $where_clause";
$stmt_count = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros_paginacion = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener gastos con paginación
$query_gastos = "
    SELECT 
        g.id_gasto,
        g.fecha_gasto,
        g.id_categoria,
        c.nombre_categoria,
        g.descripcion,
        g.articulo_servicio,
        g.cantidad,
        g.total_gasto,
        g.registrado_por,
        g.fecha_registro
    FROM Gastos g
    INNER JOIN Categorias_Gastos c ON g.id_categoria = c.id_categoria
    WHERE $where_clause 
    ORDER BY g.fecha_gasto DESC, g.fecha_registro DESC
    LIMIT :limit OFFSET :offset
";
$stmt_gastos = $conn->prepare($query_gastos);
foreach ($params as $key => $value) {
    $stmt_gastos->bindValue($key, $value);
}
$stmt_gastos->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_gastos->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_gastos->execute();
$gastos = $stmt_gastos->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$query_totales = "
    SELECT 
        COUNT(*) as total_registros,
        COALESCE(SUM(g.total_gasto), 0) as total_gastado,
        COALESCE(SUM(g.cantidad), 0) as total_articulos
    FROM Gastos g
    INNER JOIN Categorias_Gastos c ON g.id_categoria = c.id_categoria
    WHERE $where_clause
";
$stmt_totales = $conn->prepare($query_totales);
foreach ($params as $key => $value) {
    $stmt_totales->bindValue($key, $value);
}
$stmt_totales->execute();
$totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);

// Función para construir URL de paginación
function construirUrlPaginacion($pagina, $filtros, $registros_por_pagina) {
    $params = [
        'pagina' => $pagina,
        'registros' => $registros_por_pagina
    ];
    
    if (!empty($filtros['anio'])) $params['anio'] = $filtros['anio'];
    if (!empty($filtros['mes'])) $params['mes'] = $filtros['mes'];
    if (!empty($filtros['semana'])) $params['semana'] = $filtros['semana'];
    if (!empty($filtros['categoria'])) $params['categoria'] = $filtros['categoria'];
    
    return '?' . http_build_query($params);
}

$filtros_actuales = [
    'anio' => $filtro_anio,
    'mes' => $filtro_mes,
    'semana' => $filtro_semana,
    'categoria' => $filtro_categoria
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastos - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/gastos.css">
    <style>
        /* Estilos adicionales para búsqueda de categorías */
        .categorias-search {
            margin-bottom: 15px;
        }
        
        .categorias-search input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .categorias-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .categorias-section {
            margin-bottom: 20px;
        }
        
        .categorias-section h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-content-large {
            max-width: 800px;
        }

        /* Estilos para el select con búsqueda */
        .searchable-select-container {
            position: relative;
        }

        .searchable-select-input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .searchable-select-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .searchable-select-option {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }

        .searchable-select-option:hover {
            background-color: #f5f5f5;
        }

        .searchable-select-option:last-child {
            border-bottom: none;
        }

        .searchable-select-selected {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje == 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class='bx bx-wallet-alt'></i> Gastos</h1>
            <div class="header-actions">
                <button onclick="toggleModal('modalRegistrar')" class="btn btn-primary">
                    <i class='bx bx-plus-circle'></i> Nuevo Gasto
                </button>
                <button onclick="toggleModal('modalCategorias')" class="btn btn-secondary">
                    <i class='bx bx-category'></i> Categorías
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <h2><i class='bx bx-filter'></i> Filtros</h2>
            <form method="GET" action="" id="formFiltros">
                <div class="filtros-grid">
                    <!-- Año -->
                    <div class="form-group">
                        <label><i class='bx bx-calendar'></i> Año</label>
                        <select name="anio" id="anio" onchange="actualizarMeses()">
                            <option value="">Seleccionar Año</option>
                            <?php foreach ($anios_disponibles as $anio): ?>
                                <option value="<?php echo $anio; ?>" <?php echo $filtro_anio == $anio ? 'selected' : ''; ?>>
                                    <?php echo $anio; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mes -->
                    <div class="form-group">
                        <label><i class='bx bx-calendar-event'></i> Mes</label>
                        <select name="mes" id="mes" onchange="actualizarSemanas()">
                            <option value="">Seleccionar Mes</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filtro_mes == $m ? 'selected' : ''; ?>>
                                    <?php echo obtenerMesEspanol($m); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Semana -->
                    <div class="form-group">
                        <label><i class='bx bx-list-ul'></i> Semana</label>
                        <select name="semana" id="semana">
                            <option value="">Todas las semanas</option>
                            <?php 
                            if (count($semanas_filtradas) > 0) {
                                foreach ($semanas_filtradas as $semana):
                                    $fecha_inicio = date('d/m', strtotime($semana['fecha_inicio']));
                                    $fecha_fin = date('d/m', strtotime($semana['fecha_fin']));
                                    $es_activa = $semana['activa'] == 1;
                            ?>
                                <option value="<?php echo $semana['id_semana']; ?>" 
                                        <?php echo ($filtro_semana == $semana['id_semana']) ? 'selected' : ''; ?>>
                                    <?php echo $es_activa ? '🟢 ' : ''; ?>Semana <?php echo $semana['numero_semana']; ?> 
                                    (<?php echo $fecha_inicio; ?> al <?php echo $fecha_fin; ?>)
                                </option>
                            <?php 
                                endforeach;
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Categoría con búsqueda -->
                    <div class="form-group">
                        <label><i class='bx bx-category'></i> Categoría</label>
                        <div class="searchable-select-container">
                            <input type="text" 
                                   class="searchable-select-input" 
                                   placeholder="🔍 Buscar categoría..."
                                   onfocus="mostrarOpcionesCategorias()"
                                   oninput="filtrarOpcionesCategorias(this.value)">
                            <select name="categoria" id="selectCategoria" style="display: none;">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" 
                                            <?php echo $filtro_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="searchable-select-options" id="opcionesCategorias">
                                <div class="searchable-select-option" data-value="">Todas las categorías</div>
                                <?php foreach ($categorias as $cat): ?>
                                    <div class="searchable-select-option" 
                                         data-value="<?php echo $cat['id_categoria']; ?>"
                                         <?php echo $filtro_categoria == $cat['id_categoria'] ? 'data-selected="true"' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <?php if ($filtro_categoria || $filtro_semana): ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="summary-cards">
            <div class="summary-card blue">
                <div class="card-icon">
                    <i class='bx bx-file'></i>
                </div>
                <div class="card-content">
                    <h3>Total Registros</h3>
                    <p class="amount"><?php echo number_format($totales['total_registros']); ?></p>
                </div>
            </div>

            <div class="summary-card orange">
                <div class="card-icon">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Total Gastado</h3>
                    <p class="amount">$<?php echo number_format($totales['total_gastado'], 2); ?></p>
                </div>
            </div>

            <div class="summary-card purple">
                <div class="card-icon">
                    <i class='bx bx-package'></i>
                </div>
                <div class="card-content">
                    <h3>Total Artículos</h3>
                    <p class="amount"><?php echo number_format($totales['total_articulos']); ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de Gastos -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Listado de Gastos (<?php echo number_format($total_registros_paginacion); ?>)</h2>

            <?php if (count($gastos) > 0): ?>
                <div class="table-container">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha/Hora</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <th>Artículo/Servicio</th>
                                <th>Cantidad</th>
                                <th>Total</th>
                                <th>Registrado Por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastos as $index => $gasto): ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($gasto['fecha_gasto'])); ?><br>
                                        <small style="color: #999;"><?php echo date('H:i', strtotime($gasto['fecha_registro'])); ?></small>
                                    </td>
                                    <td><span class="zona-badge"><?php echo htmlspecialchars($gasto['nombre_categoria']); ?></span></td>
                                    <td><?php echo htmlspecialchars($gasto['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($gasto['articulo_servicio']); ?></td>
                                    <td><?php echo $gasto['cantidad']; ?></td>
                                    <td class="text-bold">$<?php echo number_format($gasto['total_gasto'], 2); ?></td>
                                    <td><small><?php echo htmlspecialchars($gasto['registrado_por']); ?></small></td>
                                    <td class="actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este gasto?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="eliminar_gasto">
                                            <input type="hidden" name="id_gasto" value="<?php echo $gasto['id_gasto']; ?>">
                                            <button type="submit" class="btn-action btn-danger" title="Eliminar">
                                                <i class='bx bx-trash'></i>
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
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <strong><?php echo $offset + 1; ?> - <?php echo min($offset + $registros_por_pagina, $total_registros_paginacion); ?></strong> de <strong><?php echo number_format($total_registros_paginacion); ?></strong> registros
                        </div>
                        
                        <div class="pagination-controls">
                            <label for="registros_select">Mostrar:</label>
                            <select id="registros_select" onchange="cambiarRegistrosPorPagina(this.value)">
                                <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="pagination-buttons">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="<?php echo construirUrlPaginacion(1, $filtros_actuales, $registros_por_pagina); ?>" 
                                   class="pagination-btn" title="Primera página">
                                    <i class='bx bx-chevrons-left'></i>
                                </a>
                                <a href="<?php echo construirUrlPaginacion($pagina_actual - 1, $filtros_actuales, $registros_por_pagina); ?>" 
                                   class="pagination-btn" title="Página anterior">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $rango_inicio = max(1, $pagina_actual - 2);
                            $rango_fin = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $rango_inicio; $i <= $rango_fin; $i++): 
                            ?>
                                <a href="<?php echo construirUrlPaginacion($i, $filtros_actuales, $registros_por_pagina); ?>" 
                                   class="pagination-btn <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="<?php echo construirUrlPaginacion($pagina_actual + 1, $filtros_actuales, $registros_por_pagina); ?>" 
                                   class="pagination-btn" title="Página siguiente">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                                <a href="<?php echo construirUrlPaginacion($total_paginas, $filtros_actuales, $registros_por_pagina); ?>" 
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
                    <p>No hay gastos registrados con los filtros seleccionados</p>
                    <button onclick="toggleModal('modalRegistrar')" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Registrar Primer Gasto
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Registrar Gasto -->
    <div id="modalRegistrar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-plus-circle'></i> Registrar Nuevo Gasto</h2>
                <button class="close-btn" onclick="toggleModal('modalRegistrar')">&times;</button>
            </div>

            <form method="POST" onsubmit="return validarFormulario()">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="registrar_gasto">

                <div class="form-group">
                    <label for="fecha_registro_display">
                        <i class='bx bx-calendar'></i> Fecha y Hora de Registro
                    </label>
                    <input type="text" 
                           id="fecha_registro_display" 
                           value="<?php echo date('d/m/Y H:i:s'); ?>" 
                           readonly
                           style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small style="color: #999;">El gasto se registra automáticamente con la fecha y hora actual</small>
                </div>

                <div class="form-group">
                    <label for="id_categoria">
                        <i class='bx bx-category'></i> Categoría <span style="color: red;">*</span>
                    </label>
                    <div class="searchable-select-container">
                        <input type="text" 
                               class="searchable-select-input" 
                               id="inputCategoriaModal"
                               placeholder="🔍 Buscar categoría..."
                               required
                               onfocus="mostrarOpcionesCategoriasModal()"
                               oninput="filtrarOpcionesCategoriasModal(this.value)">
                        <select name="id_categoria" id="selectCategoriaModal" required style="display: none;">
                            <option value="">Seleccione una categoría...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="searchable-select-options" id="opcionesCategoriasModal">
                            <div class="searchable-select-option" data-value="">Seleccione una categoría...</div>
                            <?php foreach ($categorias as $cat): ?>
                                <div class="searchable-select-option" data-value="<?php echo $cat['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion">
                        <i class='bx bx-note'></i> Descripción <span style="color: red;">*</span>
                    </label>
                    <textarea name="descripcion" 
                              id="descripcion" 
                              rows="3" 
                              required
                              maxlength="500"
                              placeholder="Describe el gasto..."></textarea>
                    <small class="char-counter" id="counter-descripcion">0/500</small>
                </div>

                <div class="form-group">
                    <label for="articulo_servicio">
                        <i class='bx bx-package'></i> Artículo o Servicio <span style="color: red;">*</span>
                    </label>
                    <input type="text" 
                           name="articulo_servicio" 
                           id="articulo_servicio" 
                           required
                           maxlength="200"
                           placeholder="Especifica qué se compró...">
                </div>

                <div class="form-group">
                    <label for="cantidad">
                        <i class='bx bx-hash'></i> Cantidad Adquirida <span style="color: red;">*</span>
                    </label>
                    <input type="number" 
                           name="cantidad" 
                           id="cantidad" 
                           required
                           min="1"
                           value="1">
                </div>

                <div class="form-group">
                    <label for="total_gasto">
                        <i class='bx bx-dollar'></i> Total de Gasto <span style="color: red;">*</span>
                    </label>
                    <input type="number" 
                           name="total_gasto" 
                           id="total_gasto" 
                           required
                           min="0.01"
                           step="0.01"
                           placeholder="0.00">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Registrar Gasto
                    </button>
                    <button type="button" onclick="toggleModal('modalRegistrar')" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Categorías -->
    <div id="modalCategorias" class="modal">
        <div class="modal-content modal-content-large">
            <div class="modal-header">
                <h2><i class='bx bx-category'></i> Gestionar Categorías</h2>
                <button class="close-btn" onclick="toggleModal('modalCategorias')">&times;</button>
            </div>

            <div style="padding: 25px;">
                <div style="margin-bottom: 20px;">
                    <button onclick="toggleModal('modalNuevaCategoria')" class="btn btn-primary">
                        <i class='bx bx-plus-circle'></i> Nueva Categoría
                    </button>
                </div>

                <!-- Buscador -->
                <div class="categorias-search">
                    <input type="text" 
                           id="buscarCategoria" 
                           placeholder="🔍 Buscar categoría por nombre..." 
                           onkeyup="filtrarCategorias()">
                </div>

                <div class="categorias-container">
                    <?php 
                    // Obtener todas las categorías separadas por estado
                    $query_activas = "SELECT * FROM Categorias_Gastos WHERE estado = 'activo' ORDER BY nombre_categoria";
                    $stmt_activas = $conn->query($query_activas);
                    $categorias_activas = $stmt_activas->fetchAll(PDO::FETCH_ASSOC);

                    $query_inactivas = "SELECT * FROM Categorias_Gastos WHERE estado = 'inactivo' ORDER BY nombre_categoria";
                    $stmt_inactivas = $conn->query($query_inactivas);
                    $categorias_inactivas = $stmt_inactivas->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <!-- Categorías Activas -->
                    <div class="categorias-section">
                        <h3><i class='bx bx-check-circle' style="color: #4CAF50;"></i> Categorías Activas (<?php echo count($categorias_activas); ?>)</h3>
                        <div class="table-container">
                            <table class="table-comisiones" id="tablaActivas">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias_activas as $index => $cat): ?>
                                        <tr class="categoria-row" data-nombre="<?php echo strtolower($cat['nombre_categoria']); ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($cat['nombre_categoria']); ?></td>
                                            <td><small><?php echo htmlspecialchars($cat['descripcion'] ?: '-'); ?></small></td>
                                            <td class="actions">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Desactivar esta categoría?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="cambiar_estado_categoria">
                                                    <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                                    <input type="hidden" name="nuevo_estado" value="inactivo">
                                                    <button type="submit" class="btn-action" title="Desactivar">
                                                        <i class='bx bx-x-circle' style="color: var(--warning-color);"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Categorías Inactivas -->
                    <?php if (count($categorias_inactivas) > 0): ?>
                    <div class="categorias-section">
                        <h3><i class='bx bx-x-circle' style="color: #f44336;"></i> Categorías Inactivas (<?php echo count($categorias_inactivas); ?>)</h3>
                        <div class="table-container">
                            <table class="table-comisiones" id="tablaInactivas">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias_inactivas as $index => $cat): ?>
                                        <tr class="categoria-row" data-nombre="<?php echo strtolower($cat['nombre_categoria']); ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td style="opacity: 0.6;"><?php echo htmlspecialchars($cat['nombre_categoria']); ?></td>
                                            <td><small style="opacity: 0.6;"><?php echo htmlspecialchars($cat['descripcion'] ?: '-'); ?></small></td>
                                            <td class="actions">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Activar esta categoría?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="cambiar_estado_categoria">
                                                    <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                                    <input type="hidden" name="nuevo_estado" value="activo">
                                                    <button type="submit" class="btn-action" title="Activar">
                                                        <i class='bx bx-check-circle' style="color: var(--success-color);"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Categoría -->
    <div id="modalNuevaCategoria" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-plus-circle'></i> Nueva Categoría</h2>
                <button class="close-btn" onclick="toggleModal('modalNuevaCategoria')">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="crear_categoria">

                <div class="form-group">
                    <label for="nombre_categoria">
                        <i class='bx bx-category'></i> Nombre <span style="color: red;">*</span>
                    </label>
                    <input type="text" 
                           name="nombre_categoria" 
                           id="nombre_categoria" 
                           required
                           maxlength="100"
                           placeholder="Nombre de la categoría...">
                </div>

                <div class="form-group">
                    <label for="descripcion_categoria">
                        <i class='bx bx-note'></i> Descripción
                    </label>
                    <textarea name="descripcion_categoria" 
                              id="descripcion_categoria" 
                              rows="3" 
                              maxlength="200"
                              placeholder="Descripción opcional..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Crear Categoría
                    </button>
                    <button type="button" onclick="toggleModal('modalNuevaCategoria')" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            const isOpening = modal.style.display !== 'flex';
            
            modal.style.display = isOpening ? 'flex' : 'none';
            
            // Prevenir scroll del body cuando el modal está abierto
            if (isOpening) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function validarFormulario() {
            const total = parseFloat(document.getElementById('total_gasto').value);
            if (total <= 0) {
                alert('El total del gasto debe ser mayor a $0.00');
                return false;
            }
            return true;
        }

        function cambiarRegistrosPorPagina(nuevoValor) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('registros', nuevoValor);
            urlParams.set('pagina', '1');
            window.location.search = urlParams.toString();
        }

        function actualizarMeses() {
            const anio = document.getElementById('anio').value;
            const mes = document.getElementById('mes').value;
            
            if (anio && mes) {
                actualizarSemanas();
            }
        }

        function actualizarSemanas() {
            const anio = document.getElementById('anio').value;
            const mes = document.getElementById('mes').value;
            
            if (anio && mes) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('anio', anio);
                urlParams.set('mes', mes);
                urlParams.delete('semana');
                urlParams.delete('pagina');
                window.location.search = urlParams.toString();
            }
        }

        function filtrarCategorias() {
            const input = document.getElementById('buscarCategoria');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.categoria-row');
            
            rows.forEach(row => {
                const nombre = row.getAttribute('data-nombre');
                if (nombre.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Funciones para el select con búsqueda en filtros
        function mostrarOpcionesCategorias() {
            const opciones = document.getElementById('opcionesCategorias');
            opciones.style.display = 'block';
        }

        function filtrarOpcionesCategorias(busqueda) {
            const opciones = document.getElementById('opcionesCategorias');
            const items = opciones.getElementsByClassName('searchable-select-option');
            const input = document.querySelector('.searchable-select-input');
            
            for (let item of items) {
                const texto = item.textContent.toLowerCase();
                if (texto.includes(busqueda.toLowerCase())) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            }
            
            opciones.style.display = 'block';
        }

        // Funciones para el select con búsqueda en modal
        function mostrarOpcionesCategoriasModal() {
            const opciones = document.getElementById('opcionesCategoriasModal');
            opciones.style.display = 'block';
        }

        function filtrarOpcionesCategoriasModal(busqueda) {
            const opciones = document.getElementById('opcionesCategoriasModal');
            const items = opciones.getElementsByClassName('searchable-select-option');
            const input = document.getElementById('inputCategoriaModal');
            
            for (let item of items) {
                const texto = item.textContent.toLowerCase();
                if (texto.includes(busqueda.toLowerCase())) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            }
            
            opciones.style.display = 'block';
        }

        // Inicializar selects con búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            // Para filtros
            const opcionesFiltros = document.getElementById('opcionesCategorias');
            const selectFiltros = document.getElementById('selectCategoria');
            const inputFiltros = document.querySelector('.searchable-select-input');
            
            // Para modal
            const opcionesModal = document.getElementById('opcionesCategoriasModal');
            const selectModal = document.getElementById('selectCategoriaModal');
            const inputModal = document.getElementById('inputCategoriaModal');
            
            // Configurar eventos para filtros
            if (opcionesFiltros) {
                const itemsFiltros = opcionesFiltros.getElementsByClassName('searchable-select-option');
                for (let item of itemsFiltros) {
                    item.addEventListener('click', function() {
                        const valor = this.getAttribute('data-value');
                        const texto = this.textContent;
                        
                        inputFiltros.value = texto;
                        selectFiltros.value = valor;
                        opcionesFiltros.style.display = 'none';
                        
                        // Marcar como seleccionado
                        for (let i of itemsFiltros) {
                            i.classList.remove('searchable-select-selected');
                        }
                        this.classList.add('searchable-select-selected');
                    });
                }
            }
            
            // Configurar eventos para modal
            if (opcionesModal) {
                const itemsModal = opcionesModal.getElementsByClassName('searchable-select-option');
                for (let item of itemsModal) {
                    item.addEventListener('click', function() {
                        const valor = this.getAttribute('data-value');
                        const texto = this.textContent;
                        
                        inputModal.value = texto;
                        selectModal.value = valor;
                        opcionesModal.style.display = 'none';
                        
                        // Marcar como seleccionado
                        for (let i of itemsModal) {
                            i.classList.remove('searchable-select-selected');
                        }
                        this.classList.add('searchable-select-selected');
                    });
                }
            }
            
            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.searchable-select-container')) {
                    const dropdowns = document.getElementsByClassName('searchable-select-options');
                    for (let dropdown of dropdowns) {
                        dropdown.style.display = 'none';
                    }
                }
            });
            
            // Contador de caracteres
            const descripcion = document.getElementById('descripcion');
            const counter = document.getElementById('counter-descripcion');
            
            if (descripcion && counter) {
                descripcion.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = `${length}/500`;
                    
                    counter.classList.remove('warning', 'limit');
                    if (length >= 500) {
                        counter.classList.add('limit');
                    } else if (length >= 400) {
                        counter.classList.add('warning');
                    }
                });
            }

            // Auto-ocultar mensaje
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.style.transition = 'opacity 0.5s';
                    mensaje.style.opacity = '0';
                    setTimeout(() => mensaje.remove(), 500);
                }, 5000);
            }
            
            // Establecer valores iniciales para los selects con búsqueda
            const selectedOptionFiltros = opcionesFiltros?.querySelector('[data-selected="true"]');
            if (selectedOptionFiltros) {
                inputFiltros.value = selectedOptionFiltros.textContent;
                selectedOptionFiltros.classList.add('searchable-select-selected');
            }
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = ''; // Restaurar scroll
            }
        }
    </script>
</body>
</html>