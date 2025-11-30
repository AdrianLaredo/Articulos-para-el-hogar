<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

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

$mensaje = '';
$tipo_mensaje = '';

// REGISTRAR PRÉSTAMO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $monto = floatval($_POST['monto']);
        $fecha_prestamo = $_POST['fecha_prestamo'];
        $motivo = trim($_POST['motivo']);
        
        // VALIDACIÓN 1: Validar que la fecha sea de domingo (0) a viernes (5)
        $fecha_obj = new DateTime($fecha_prestamo);
        $dia_semana = (int)$fecha_obj->format('w');
        
        if ($dia_semana == 6) { // Sábado
            $mensaje = "⚠️ Error: No se pueden registrar préstamos los sábados. Solo de domingo a viernes.";
            $tipo_mensaje = "error";
        } elseif ($monto > 1500) {
            $mensaje = "⚠️ Error: El préstamo no puede exceder de $1,500.00 por semana";
            $tipo_mensaje = "error";
        } elseif ($monto <= 0) {
            $mensaje = "Error: El monto debe ser mayor a $0.00";
            $tipo_mensaje = "error";
        } else {
            try {
                // Obtener la semana a la que corresponde la fecha del préstamo
                $sql_semana = "SELECT id_semana, fecha_inicio, fecha_fin FROM Semanas_Cobro 
                               WHERE :fecha BETWEEN fecha_inicio AND fecha_fin 
                               AND activa = 1 
                               LIMIT 1";
                $stmt_semana = $conn->prepare($sql_semana);
                $stmt_semana->bindParam(':fecha', $fecha_prestamo);
                $stmt_semana->execute();
                $semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);
                
                if (!$semana) {
                    $mensaje = "⚠️ Error: No se encontró una semana activa para la fecha seleccionada";
                    $tipo_mensaje = "error";
                } else {
                    // VALIDACIÓN 2: Verificar préstamos totales de la semana para este empleado
                    // ⭐ CORRECCIÓN: Solo contar préstamos activos (excluir cancelados)
                    $sql_total_semana = "SELECT COALESCE(SUM(monto), 0) as total_prestado 
                                         FROM Prestamos_Empleados 
                                         WHERE id_empleado = :id 
                                         AND id_semana = :id_semana 
                                         AND estado = 'activo'";
                    $stmt_total = $conn->prepare($sql_total_semana);
                    $stmt_total->bindParam(':id', $id_empleado);
                    $stmt_total->bindParam(':id_semana', $semana['id_semana']);
                    $stmt_total->execute();
                    $total_prestado = $stmt_total->fetchColumn();
                    
                    $nuevo_total = $total_prestado + $monto;
                    
                    if ($nuevo_total > 1500) {
                        $disponible = 1500 - $total_prestado;
                        if ($disponible > 0) {
                            $mensaje = "⚠️ Error: Este empleado ya tiene $" . number_format($total_prestado, 2) . " prestados esta semana. Solo puede prestar $" . number_format($disponible, 2) . " más (máximo $1,500.00 por semana)";
                        } else {
                            $mensaje = "🚫 Error: Este empleado ya ha alcanzado su límite semanal de préstamos ($1,500.00). No puede solicitar más préstamos esta semana.";
                        }
                        $tipo_mensaje = "error";
                    } else {
                        // Registrar el préstamo
                        $sql = "INSERT INTO Prestamos_Empleados 
                                (id_empleado, id_semana, monto, monto_pendiente, fecha_prestamo, motivo, estado) 
                                VALUES 
                                (:id_empleado, :id_semana, :monto, :monto_pendiente, :fecha_prestamo, :motivo, 'activo')";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id_empleado', $id_empleado);
                        $stmt->bindParam(':id_semana', $semana['id_semana']);
                        $stmt->bindParam(':monto', $monto);
                        $stmt->bindParam(':monto_pendiente', $monto);
                        $stmt->bindParam(':fecha_prestamo', $fecha_prestamo);
                        $stmt->bindParam(':motivo', $motivo);
                        
                        if ($stmt->execute()) {
                            $mensaje = "✅ Préstamo registrado exitosamente por $" . number_format($monto, 2) . " | Total prestado esta semana: $" . number_format($nuevo_total, 2) . " de $1,500.00";
                            $tipo_mensaje = "success";
                        }
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error al registrar préstamo: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// CANCELAR PRÉSTAMO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_prestamo = $_POST['id_prestamo'];
        
        try {
            $sql = "UPDATE Prestamos_Empleados SET estado = 'cancelado' WHERE id_prestamo = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id_prestamo);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Préstamo cancelado exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al cancelar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? max(10, min(100, intval($_GET['registros']))) : 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$filtro_empleado = isset($_GET['empleado']) ? $_GET['empleado'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_semana = isset($_GET['semana']) ? $_GET['semana'] : '';

// Obtener empleados activos con nombre completo
$query_empleados = "SELECT 
    id_empleado,
    (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo,
    rol,
    zona
    FROM Empleados 
    WHERE estado = 'activo' 
    ORDER BY nombre, apellido_paterno";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Obtener semanas activas
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Construir query de préstamos
$where = ["1=1"];
$params = [];

if ($filtro_empleado) {
    $where[] = "p.id_empleado = :empleado";
    $params[':empleado'] = $filtro_empleado;
}
if ($filtro_estado !== 'todos') {
    $where[] = "p.estado = :estado";
    $params[':estado'] = $filtro_estado;
}
if ($filtro_semana) {
    $where[] = "p.id_semana = :semana";
    $params[':semana'] = $filtro_semana;
}

$where_clause = implode(" AND ", $where);

// Contar total de registros (para paginación)
$query_count = "
    SELECT COUNT(*) as total
    FROM Prestamos_Empleados p
    INNER JOIN Empleados e ON p.id_empleado = e.id_empleado
    INNER JOIN Semanas_Cobro s ON p.id_semana = s.id_semana
    WHERE $where_clause
";
$stmt_count = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros_paginacion = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener préstamos con paginación
$query_prestamos = "
    SELECT 
        p.*,
        (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_empleado,
        e.rol,
        e.zona,
        s.mes,
        s.numero_semana,
        s.fecha_inicio,
        s.fecha_fin,
        s.anio
    FROM Prestamos_Empleados p
    INNER JOIN Empleados e ON p.id_empleado = e.id_empleado
    INNER JOIN Semanas_Cobro s ON p.id_semana = s.id_semana
    WHERE $where_clause
    ORDER BY p.fecha_prestamo DESC, e.nombre
    LIMIT :limit OFFSET :offset
";
$stmt_prestamos = $conn->prepare($query_prestamos);
foreach ($params as $key => $value) {
    $stmt_prestamos->bindValue($key, $value);
}
$stmt_prestamos->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_prestamos->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_prestamos->execute();
$prestamos = $stmt_prestamos->fetchAll(PDO::FETCH_ASSOC);

// ⭐ CORRECCIÓN: Calcular totales excluyendo préstamos cancelados
$query_totales = "
    SELECT 
        COALESCE(SUM(CASE WHEN p.estado != 'cancelado' THEN p.monto ELSE 0 END), 0) as total_prestado,
        COALESCE(SUM(CASE WHEN p.estado = 'activo' THEN p.monto_pendiente ELSE 0 END), 0) as total_pendiente,
        COALESCE(SUM(CASE WHEN p.estado = 'activo' THEN p.monto ELSE 0 END), 0) as total_activo,
        COALESCE(SUM(CASE WHEN p.estado = 'pagado' THEN p.monto ELSE 0 END), 0) as total_liquidado
    FROM Prestamos_Empleados p
    INNER JOIN Empleados e ON p.id_empleado = e.id_empleado
    INNER JOIN Semanas_Cobro s ON p.id_semana = s.id_semana
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
    
    if (!empty($filtros['empleado'])) $params['empleado'] = $filtros['empleado'];
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') $params['estado'] = $filtros['estado'];
    if (!empty($filtros['semana'])) $params['semana'] = $filtros['semana'];
    
    return '?' . http_build_query($params);
}

$filtros_actuales = [
    'empleado' => $filtro_empleado,
    'estado' => $filtro_estado,
    'semana' => $filtro_semana
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos a Empleados - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
    <style>
        .rol-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .rol-badge.vendedor {
            background: #E3F2FD;
            color: #1976D2;
        }
        .rol-badge.cobrador {
            background: #F3E5F5;
            color: #7B1FA2;
        }
        .rol-badge.gerencia {
            background: #FFF3E0;
            color: #F57C00;
        }

        .estado-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #E8F5E9;
            color: #2E7D32;
        }
        .badge-warning {
            background: #FFF3E0;
            color: #F57C00;
        }
        .badge-danger {
            background: #FFEBEE;
            color: #C62828;
        }

        .zona-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #E3F2FD;
            color: #1976D2;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .text-bold {
            font-weight: 700;
        }

        .text-success {
            color: #2E7D32;
        }

        .text-danger {
            color: #C62828;
        }

        .text-muted {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" id="mensaje">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Header con título y botón -->
        <div class="page-header">
            <h1><i class='bx bx-wallet'></i> Préstamos a Empleados</h1>
            <button onclick="toggleModal('modalRegistrar')" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> Nuevo Préstamo
            </button>
        </div>

        <!-- Filtros -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros de Búsqueda</h2>
            <form method="GET" action="prestamos.php" class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="empleado">Empleado</label>
                        <select name="empleado" id="empleado">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>" 
                                        <?php echo $filtro_empleado == $emp['id_empleado'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['nombre_completo']); ?> (<?php echo $emp['zona']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="pagado" <?php echo $filtro_estado == 'pagado' ? 'selected' : ''; ?>>Pagados</option>
                            <option value="cancelado" <?php echo $filtro_estado == 'cancelado' ? 'selected' : ''; ?>>Cancelados</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="semana">Semana</label>
                        <select name="semana" id="semana">
                            <option value="">Todas las semanas</option>
                            <?php foreach ($semanas as $sem): ?>
                                <option value="<?php echo $sem['id_semana']; ?>" 
                                        <?php echo $filtro_semana == $sem['id_semana'] ? 'selected' : ''; ?>>
                                    <?php echo $sem['mes'] . ' ' . $sem['anio']; ?> - Semana <?php echo $sem['numero_semana']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <?php if ($filtro_empleado || $filtro_estado !== 'todos' || $filtro_semana): ?>
                        <button type="button" onclick="limpiarFiltros()" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Limpiar
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="summary-cards">
            <div class="summary-card blue">
                <div class="card-icon">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Total Prestado</h3>
                    <p class="amount">$<?php echo number_format($totales['total_prestado'], 2); ?></p>
                    <small style="opacity: 0.8; font-size: 11px;">Excluye cancelados</small>
                </div>
            </div>

            <div class="summary-card orange">
                <div class="card-icon">
                    <i class='bx bx-time-five'></i>
                </div>
                <div class="card-content">
                    <h3>Pendiente de Pagar</h3>
                    <p class="amount">$<?php echo number_format($totales['total_pendiente'], 2); ?></p>
                    <small style="opacity: 0.8; font-size: 11px;">Solo activos</small>
                </div>
            </div>

            <div class="summary-card green">
                <div class="card-icon">
                    <i class='bx bx-check-double'></i>
                </div>
                <div class="card-content">
                    <h3>Total Pagado</h3>
                    <p class="amount">$<?php echo number_format($totales['total_liquidado'], 2); ?></p>
                    <small style="opacity: 0.8; font-size: 11px;">Liquidados</small>
                </div>
            </div>

            <div class="summary-card purple">
                <div class="card-icon">
                    <i class='bx bx-wallet'></i>
                </div>
                <div class="card-content">
                    <h3>Préstamos Activos</h3>
                    <p class="amount">$<?php echo number_format($totales['total_activo'], 2); ?></p>
                    <small style="opacity: 0.8; font-size: 11px;">Por cobrar</small>
                </div>
            </div>
        </div>

        <!-- Tabla de Préstamos -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Listado de Préstamos (<?php echo number_format($total_registros_paginacion); ?>)</h2>

            <?php if (count($prestamos) > 0): ?>
                <div class="table-container">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Rol</th>
                                <th>Zona</th>
                                <th>Semana</th>
                                <th>Fecha Préstamo</th>
                                <th>Monto</th>
                                <th>Pendiente</th>
                                <th>Estado</th>
                                <th>Motivo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $prestamo): ?>
                                <tr <?php echo $prestamo['estado'] == 'cancelado' ? 'style="opacity: 0.6; background: #fafafa;"' : ''; ?>>
                                    <td><?php echo htmlspecialchars($prestamo['nombre_empleado']); ?></td>
                                    <td>
                                        <span class="rol-badge <?php echo $prestamo['rol']; ?>">
                                            <?php echo ucfirst($prestamo['rol']); ?>
                                        </span>
                                    </td>
                                    <td><span class="zona-badge"><?php echo $prestamo['zona']; ?></span></td>
                                    <td>
                                        <small>
                                            <?php echo $prestamo['mes'] . ' ' . $prestamo['anio']; ?><br>
                                            Semana <?php echo $prestamo['numero_semana']; ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                    <td class="text-bold <?php echo $prestamo['estado'] == 'cancelado' ? '' : ''; ?>">
                                        $<?php echo number_format($prestamo['monto'], 2); ?>
                                        <?php if ($prestamo['estado'] == 'cancelado'): ?>
                                            <small style="color: #999; display: block;">(Cancelado)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo $prestamo['monto_pendiente'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        $<?php echo number_format($prestamo['monto_pendiente'], 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'badge-warning';
                                        if ($prestamo['estado'] == 'pagado') $badge_class = 'badge-success';
                                        if ($prestamo['estado'] == 'cancelado') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="estado-badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($prestamo['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($prestamo['motivo']): ?>
                                            <small><?php echo htmlspecialchars(substr($prestamo['motivo'], 0, 30)); ?><?php echo strlen($prestamo['motivo']) > 30 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?php if ($prestamo['estado'] == 'activo'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Confirmar la cancelación de este préstamo?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="cancelar">
                                                <input type="hidden" name="id_prestamo" value="<?php echo $prestamo['id_prestamo']; ?>">
                                                <button type="submit" class="btn-action btn-danger" title="Cancelar préstamo">
                                                    <i class='bx bx-x-circle'></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                    <p>No hay préstamos registrados con los filtros seleccionados</p>
                    <button onclick="toggleModal('modalRegistrar')" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Registrar Primer Préstamo
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Registrar Préstamo -->
    <div id="modalRegistrar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-plus-circle'></i> Registrar Nuevo Préstamo</h2>
                <button class="close-btn" onclick="toggleModal('modalRegistrar')">&times;</button>
            </div>

            <form method="POST" onsubmit="return validarFormulario()">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="registrar">

                <div class="form-group">
                    <label for="id_empleado_modal">
                        <i class='bx bx-user'></i> Empleado <span class="required">*</span>
                    </label>
                    <select name="id_empleado" 
                            id="id_empleado_modal" 
                            required 
                            onchange="actualizarLimite()">
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo $emp['id_empleado']; ?>">
                                <?php echo htmlspecialchars($emp['nombre_completo']); ?> - 
                                <?php echo $emp['rol']; ?> (<?php echo $emp['zona']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_prestamo">
                        <i class='bx bx-calendar'></i> Fecha del Préstamo <span class="required">*</span>
                    </label>
                    <input type="date" 
                           name="fecha_prestamo" 
                           id="fecha_prestamo" 
                           required
                           max="<?php echo date('Y-m-d'); ?>"
                           onchange="validarDia(); actualizarLimite();">
                    <small id="dia-texto" style="display: block; margin-top: 5px; color: #666;">
                        Selecciona una fecha
                    </small>
                </div>

                <div class="form-group">
                    <label for="monto">
                        <i class='bx bx-dollar'></i> Monto <span class="required">*</span>
                    </label>
                    <input type="number" 
                           name="monto" 
                           id="monto" 
                           step="0.01" 
                           min="0.01" 
                           max="1500" 
                           required
                           placeholder="0.00">
                    <small style="display: block; margin-top: 5px; color: #F57C00;">
                        ⚠️ Máximo $1,500.00 por semana por empleado
                    </small>
                    <div id="limite-info" style="display: none; margin-top: 10px; padding: 10px; border-radius: 8px;">
                        <small id="limite-texto" style="font-weight: 600;"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="motivo">
                        <i class='bx bx-note'></i> Motivo
                    </label>
                    <textarea name="motivo" 
                              id="motivo" 
                              rows="3" 
                              maxlength="200"
                              placeholder="Razón del préstamo (opcional)..."></textarea>
                    <small class="char-counter" id="counter-motivo">0/200</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        <i class='bx bx-save'></i> Registrar Préstamo
                    </button>
                    <button type="button" onclick="toggleModal('modalRegistrar')" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Datos de préstamos actuales para validación en cliente
        // ⭐ CORRECCIÓN: Solo incluir préstamos activos
        const prestamosActuales = <?php 
            $prestamos_json = [];
            $sql_all = "SELECT id_empleado, id_semana, SUM(monto) as total FROM Prestamos_Empleados WHERE estado = 'activo' GROUP BY id_empleado, id_semana";
            $stmt_all = $conn->query($sql_all);
            while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
                $prestamos_json[$row['id_empleado'] . '_' . $row['id_semana']] = $row['total'];
            }
            echo json_encode($prestamos_json);
        ?>;

        const semanas = <?php echo json_encode($semanas); ?>;

        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
            
            if (modal.style.display === 'flex') {
                validarDia();
                actualizarLimite();
            }
        }

        function validarDia() {
            const fechaInput = document.getElementById('fecha_prestamo');
            const diaTexto = document.getElementById('dia-texto');
            const btnSubmit = document.getElementById('btnSubmit');
            
            if (!fechaInput.value) {
                diaTexto.textContent = 'Selecciona una fecha';
                diaTexto.style.color = '#666';
                return;
            }
            
            const fecha = new Date(fechaInput.value + 'T00:00:00');
            const dia = fecha.getDay();
            const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            
            if (dia === 6) { // Sábado
                diaTexto.textContent = '❌ ' + dias[dia] + ' - NO se permiten préstamos los sábados';
                diaTexto.style.color = '#f44336';
                btnSubmit.disabled = true;
                btnSubmit.style.opacity = '0.5';
            } else {
                diaTexto.textContent = '✅ ' + dias[dia] + ' - Válido';
                diaTexto.style.color = '#4CAF50';
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = '1';
                actualizarLimite();
            }
        }

        function actualizarLimite() {
            const empleadoId = document.getElementById('id_empleado_modal').value;
            const fechaInput = document.getElementById('fecha_prestamo').value;
            const limiteInfo = document.getElementById('limite-info');
            const limiteTexto = document.getElementById('limite-texto');
            
            if (!empleadoId || !fechaInput) {
                limiteInfo.style.display = 'none';
                return;
            }
            
            // Encontrar la semana correspondiente
            let semanaId = null;
            for (let semana of semanas) {
                if (fechaInput >= semana.fecha_inicio && fechaInput <= semana.fecha_fin) {
                    semanaId = semana.id_semana;
                    break;
                }
            }
            
            if (!semanaId) {
                limiteInfo.style.display = 'none';
                return;
            }
            
            const key = empleadoId + '_' + semanaId;
            const prestado = prestamosActuales[key] || 0;
            const disponible = 1500 - prestado;
            
            if (prestado > 0) {
                limiteInfo.style.display = 'block';
                if (disponible > 0) {
                    limiteTexto.textContent = `Ya prestado esta semana: $${prestado.toFixed(2)} | Disponible: $${disponible.toFixed(2)}`;
                    limiteInfo.style.background = '#FFF3E0';
                    limiteTexto.style.color = '#E65100';
                } else {
                    limiteTexto.textContent = `⚠️ Este empleado ya alcanzó su límite semanal ($1,500.00)`;
                    limiteInfo.style.background = '#FEE2E2';
                    limiteTexto.style.color = '#991B1B';
                }
                document.getElementById('monto').max = disponible > 0 ? disponible : 0;
            } else {
                limiteInfo.style.display = 'block';
                limiteTexto.textContent = `Disponible esta semana: $1,500.00`;
                limiteInfo.style.background = '#E8F5E9';
                limiteTexto.style.color = '#1B5E20';
                document.getElementById('monto').max = 1500;
            }
        }

        function validarFormulario() {
            const fecha = document.getElementById('fecha_prestamo').value;
            const fechaObj = new Date(fecha + 'T00:00:00');
            const dia = fechaObj.getDay();
            
            if (dia === 6) {
                alert('❌ No se pueden registrar préstamos los sábados. Solo de domingo a viernes.');
                return false;
            }
            
            const monto = parseFloat(document.getElementById('monto').value);
            if (monto > 1500) {
                alert('❌ El monto no puede exceder $1,500.00 por semana');
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

        function limpiarFiltros() {
            window.location.href = 'prestamos.php';
        }

        // Función para actualizar el dashboard cuando se usa en iframe
        function actualizarDashboard(event) {
            if (window.parent && window.parent !== window) {
                event.preventDefault();
                window.parent.postMessage({type: 'navigate', page: 'index.php'}, '*');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // Contador de caracteres
            const motivo = document.getElementById('motivo');
            const counter = document.getElementById('counter-motivo');
            
            if (motivo && counter) {
                motivo.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = `${length}/200`;
                    
                    counter.classList.remove('warning', 'limit');
                    if (length >= 200) {
                        counter.classList.add('limit');
                    } else if (length >= 160) {
                        counter.classList.add('warning');
                    }
                });
            }
            
            // Validar día inicial
            validarDia();
        });
    </script>


    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>