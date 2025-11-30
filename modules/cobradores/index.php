<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

// Prevenir caché de la página
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

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Actualizar semanas automáticamente al cargar la página
require_once 'actualizar_semanas_auto.php';

$mensaje = '';
$tipo_mensaje = '';

// MARCAR COMO PAGADA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_pagada') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_comision = $_POST['id_comision'];
        $fecha_pago = date('Y-m-d');
        
        try {
            $sql = "UPDATE Comisiones_Cobradores SET estado = 'pagada', fecha_pago = :fecha_pago WHERE id_comision = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':fecha_pago', $fecha_pago);
            $stmt->bindParam(':id', $id_comision);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Comisión marcada como pagada exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? max(10, min(100, intval($_GET['registros']))) : 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$filtro_semana = isset($_GET['semana']) ? $_GET['semana'] : '';
$filtro_empleado = isset($_GET['empleado']) ? $_GET['empleado'] : '';
$filtro_zona = isset($_GET['zona']) ? $_GET['zona'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Obtener semanas activas (solo las 3)
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio ASC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados activos con nombre completo
$query_empleados = "SELECT 
    id_empleado,
    (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo,
    rol
    FROM Empleados 
    WHERE estado = 'activo' 
    ORDER BY nombre, apellido_paterno";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Zonas disponibles
$zonas_disponibles = ['XZ', 'WZ', 'VZ', 'KZ', 'AKZ', 'TZ', 'RZ', 'YZ'];

// Construir query de comisiones
$where = ["1=1"];
$params = [];

if ($filtro_semana) {
    $where[] = "id_semana = :semana";
    $params[':semana'] = $filtro_semana;
}
if ($filtro_empleado) {
    $where[] = "id_empleado = :empleado";
    $params[':empleado'] = $filtro_empleado;
}
if ($filtro_zona && $filtro_zona !== 'todas') {
    $where[] = "zona = :zona";
    $params[':zona'] = $filtro_zona;
}
if ($filtro_estado !== 'todos') {
    $where[] = "estado = :estado";
    $params[':estado'] = $filtro_estado;
}

$where_clause = implode(" AND ", $where);

// Contar total de registros (para paginación)
$query_count = "SELECT COUNT(*) as total FROM Vista_Comisiones_Completo WHERE $where_clause";
$stmt_count = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros_paginacion = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener comisiones con paginación
$query_comisiones = "
    SELECT * FROM Vista_Comisiones_Completo 
    WHERE $where_clause 
    ORDER BY fecha_inicio DESC, nombre_empleado
    LIMIT :limit OFFSET :offset
";
$stmt_comisiones = $conn->prepare($query_comisiones);
foreach ($params as $key => $value) {
    $stmt_comisiones->bindValue($key, $value);
}
$stmt_comisiones->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_comisiones->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_comisiones->execute();
$comisiones = $stmt_comisiones->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales (de todos los registros, no solo la página actual)
$query_totales = "
    SELECT 
        COALESCE(SUM(comision_cobro), 0) as total_comision_cobro,
        COALESCE(SUM(comision_asignaciones), 0) as total_comision_asignaciones,
        COALESCE(SUM(total_gasolina), 0) as total_gasolina,
        COALESCE(SUM(total_extras), 0) as total_extras,
        COALESCE(SUM(total_comision), 0) as total_comisiones,
        COALESCE(SUM(CASE WHEN estado = 'pagada' THEN total_comision ELSE 0 END), 0) as total_pagadas,
        COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN total_comision ELSE 0 END), 0) as total_pendientes,
        COUNT(*) as total_registros,
        SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as count_pagadas,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as count_pendientes
    FROM Vista_Comisiones_Completo
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
    
    if (!empty($filtros['semana'])) $params['semana'] = $filtros['semana'];
    if (!empty($filtros['empleado'])) $params['empleado'] = $filtros['empleado'];
    if (!empty($filtros['zona'])) $params['zona'] = $filtros['zona'];
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') $params['estado'] = $filtros['estado'];
    
    return '?' . http_build_query($params);
}

$filtros_actuales = [
    'semana' => $filtro_semana,
    'empleado' => $filtro_empleado,
    'zona' => $filtro_zona,
    'estado' => $filtro_estado
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Comisiones Semanales - Zeus Hogar</title>
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
            background: #E8F5E9;
            color: #388E3C;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-dollar-circle'></i> Comisiones Semanales</h1>
            <div class="header-actions">
                <a href="#" onclick="navegarA('generar_comision.php'); return false;" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Generar Comisión
                </a>
                <a href="#" onclick="navegarA('gestionar_semanas.php'); return false;" class="btn btn-secondary">
                    <i class='bx bx-calendar-week'></i> Ver Semanas
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Info del Sistema -->
        <div class="card" style="background: #E8F5E9; border-left: 4px solid #4CAF50; padding: 15px;">
            <p style="margin: 0;">
                <i class='bx bx-info-circle' style="color: #4CAF50;"></i>
                <strong>Sistema Automático:</strong> Siempre hay 3 semanas activas (Anterior, Actual, Siguiente). 
                Actualización: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>

        <!-- Filtros -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros</h2>
            <form method="GET" action="" class="filtros-form">
                <input type="hidden" name="pagina" value="1">
                <input type="hidden" name="registros" value="<?php echo $registros_por_pagina; ?>">
                
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="semana"><i class='bx bx-calendar'></i> Semana</label>
                        <select name="semana" id="semana" class="form-control">
                            <option value="">Todas las semanas</option>
                            <?php foreach ($semanas as $sem): ?>
                                <option value="<?php echo $sem['id_semana']; ?>" <?php echo $filtro_semana == $sem['id_semana'] ? 'selected' : ''; ?>>
                                    <?php echo $sem['mes']; ?> <?php echo $sem['anio']; ?> - Semana <?php echo $sem['numero_semana']; ?> 
                                    (<?php echo date('d/m', strtotime($sem['fecha_inicio'])); ?> - <?php echo date('d/m', strtotime($sem['fecha_fin'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="empleado"><i class='bx bx-user'></i> Empleado</label>
                        <select name="empleado" id="empleado" class="form-control">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>" <?php echo $filtro_empleado == $emp['id_empleado'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(trim($emp['nombre_completo'])); ?> (<?php echo ucfirst($emp['rol']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="zona"><i class='bx bx-map'></i> Zona</label>
                        <select name="zona" id="zona" class="form-control">
                            <option value="">Todas las zonas</option>
                            <?php foreach ($zonas_disponibles as $zona): ?>
                                <option value="<?php echo $zona; ?>" <?php echo $filtro_zona == $zona ? 'selected' : ''; ?>>
                                    <?php echo $zona; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado"><i class='bx bx-check-circle'></i> Estado</label>
                        <select name="estado" id="estado" class="form-control">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="revisada" <?php echo $filtro_estado == 'revisada' ? 'selected' : ''; ?>>Revisada</option>
                            <option value="pagada" <?php echo $filtro_estado == 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <?php if ($filtro_semana || $filtro_empleado || $filtro_zona || $filtro_estado !== 'todos'): ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Resumen de Comisiones -->
        <?php if (count($comisiones) > 0): ?>
        <style>
            .summary-cards-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .summary-card {
                background: white;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                display: flex;
                align-items: center;
                gap: 15px;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .summary-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            }

            .summary-card .card-icon {
                width: 50px;
                height: 50px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .summary-card .card-icon i {
                font-size: 24px;
                color: white;
            }

            .summary-card .card-content {
                flex: 1;
            }

            .summary-card .card-content h3 {
                margin: 0 0 5px 0;
                font-size: 14px;
                color: #666;
                font-weight: 500;
            }

            .summary-card .card-content .amount {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
                color: #333;
            }

            .summary-card.blue .card-icon {
                background: linear-gradient(135deg, #2196F3, #1976D2);
            }

            .summary-card.orange .card-icon {
                background: linear-gradient(135deg, #FF9800, #F57C00);
            }

            .summary-card.green .card-icon {
                background: linear-gradient(135deg, #4CAF50, #388E3C);
            }

            .summary-card.purple .card-icon {
                background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            }
        </style>

        <div class="summary-cards-container">
            <div class="summary-card blue">
                <div class="card-icon">
                    <i class='bx bx-file'></i>
                </div>
                <div class="card-content">
                    <h3>Total de Comisiones</h3>
                    <p class="amount"><?php echo $totales['total_registros']; ?></p>
                </div>
            </div>

            <div class="summary-card orange">
                <div class="card-icon">
                    <i class='bx bx-time-five'></i>
                </div>
                <div class="card-content">
                    <h3>Pendientes</h3>
                    <p class="amount">$<?php echo number_format($totales['total_pendientes'], 2); ?></p>
                </div>
            </div>

            <div class="summary-card green">
                <div class="card-icon">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Pagadas</h3>
                    <p class="amount">$<?php echo number_format($totales['total_pagadas'], 2); ?></p>
                </div>
            </div>

            <div class="summary-card purple">
                <div class="card-icon">
                    <i class='bx bx-calculator'></i>
                </div>
                <div class="card-content">
                    <h3>Total General</h3>
                    <p class="amount">$<?php echo number_format($totales['total_comisiones'], 2); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla de Comisiones -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Comisiones Registradas</h2>
            
            <?php if (count($comisiones) > 0): ?>
                <div class="table-container">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>Semana</th>
                                <th>Empleado</th>
                                <th>Zona</th>
                                <th>Cobros</th>
                                <th>Com. Cobradores</th>
                                <th>Com. Ventas</th>
                                <th>Gasolina</th>
                                <th>Extras</th>
                                <th>Préstamo</th>
                                <th>P. Inhab.</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comisiones as $com): ?>
                                <tr>
                                    <td>
                                        <div><?php echo $com['mes']; ?> <?php echo $com['anio']; ?></div>
                                        <small class="text-muted">Sem. <?php echo $com['numero_semana']; ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($com['nombre_completo']); ?></strong>
                                        <span class="rol-badge cobrador">Cobrador</span>
                                    </td>
                                    <td><span class="zona-badge"><?php echo $com['zona']; ?></span></td>
                                    <td>$<?php echo number_format($com['total_cobros'] ?? 0, 2); ?></td>
                                    <td class="text-success"><strong>$<?php echo number_format($com['comision_cobro'] ?? 0, 2); ?></strong></td>
                                    <td class="text-primary"><strong>$<?php echo number_format($com['comision_asignaciones'] ?? 0, 2); ?></strong></td>
                                    <td>$<?php echo number_format($com['total_gasolina'] ?? 0, 2); ?></td>
                                    <td class="text-warning"><strong>$<?php echo number_format($com['total_extras'] ?? 0, 2); ?></strong></td>
                                    <td class="text-danger">
                                        <?php
                                        $prestamo = $com['prestamo'] ?? 0;
                                        if ($prestamo > 0) {
                                            echo '-$' . number_format($prestamo, 2);
                                        } else {
                                            echo '$0.00';
                                        }
                                        ?>
                                    </td>
                                    <td style="color: #10b981; font-weight: 600;">
                                        <?php
                                        $prestamo_inh = $com['prestamo_inhabilitado'] ?? 0;
                                        if ($prestamo_inh > 0) {
                                            echo '+$' . number_format($prestamo_inh, 2);
                                        } else {
                                            echo '$0.00';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-bold"><strong>$<?php echo number_format($com['total_comision'] ?? 0, 2); ?></strong></td>
                                    <td>
                                        <?php
                                        $badge_class = 'badge-warning';
                                        if ($com['estado'] == 'revisada') $badge_class = 'badge-info';
                                        if ($com['estado'] == 'pagada') $badge_class = 'badge-success';
                                        ?>
                                        <span class="estado-badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($com['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="#" 
                                           onclick="navegarA('ver_comision.php?id=<?php echo $com['id_comision']; ?>'); return false;"
                                           class="btn-action btn-view" 
                                           title="Ver detalles">
                                            <i class='bx bx-show'></i>
                                        </a>
                                        <a href="#" 
                                           onclick="navegarA('editar_comision.php?id=<?php echo $com['id_comision']; ?>'); return false;"
                                           class="btn-action btn-edit" 
                                           title="Editar">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <?php if ($com['estado'] != 'pagada'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Marcar esta comisión como pagada?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="marcar_pagada">
                                                <input type="hidden" name="id_comision" value="<?php echo $com['id_comision']; ?>">
                                                <button type="submit" class="btn-action btn-success" title="Marcar como pagada">
                                                    <i class='bx bx-check-circle'></i>
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
                    <p>No hay comisiones registradas con los filtros seleccionados</p>
                    <a href="#" onclick="navegarA('generar_comision.php'); return false;" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Generar Primera Comisión
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Función para navegar dentro del iframe o página normal
        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                console.log('🔄 Navegando a:', pagina);
                window.parent.postMessage({
                    type: 'navigate', 
                    page: pagina,
                    fullUrl: pagina
                }, '*');
            } else {
                window.location.href = pagina;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
                
                setTimeout(() => {
                    mensaje.style.transition = 'opacity 0.5s';
                    mensaje.style.opacity = '0';
                    setTimeout(() => mensaje.remove(), 500);
                }, 5000);
            }
        });
        
        function cambiarRegistrosPorPagina(nuevoValor) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('registros', nuevoValor);
            urlParams.set('pagina', '1');
            window.location.search = urlParams.toString();
        }
    </script>
    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>