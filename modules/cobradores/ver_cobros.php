<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

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

$mensaje = '';
$tipo_mensaje = '';

// PROCESAR EDICIÓN DE COBRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_cobro') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $_SESSION['mensaje'] = "❌ Error: Token de seguridad inválido";
        $_SESSION['tipo_mensaje'] = "error";
    } else {
        $id_cobro = $_POST['id_cobro'];
        $monto_cobrado = floatval($_POST['monto_cobrado']);
        $clientes_visitados = intval($_POST['clientes_visitados']);
        $observaciones = trim($_POST['observaciones']);
        
        // Validar que no sean valores negativos
        if ($monto_cobrado < 0 || $clientes_visitados < 0) {
            $_SESSION['mensaje'] = "❌ Error: No se permiten valores negativos";
            $_SESSION['tipo_mensaje'] = "error";
        } else {
            try {
                // Verificar si el cobro ya tiene comisión generada
                $sql_check_comision = "
                    SELECT COUNT(*) as tiene_comision
                    FROM Comisiones_Cobradores cc
                    INNER JOIN Semanas_Cobro sc ON cc.id_semana = sc.id_semana
                    INNER JOIN Cobros_Diarios cd ON cc.id_empleado = cd.id_empleado
                    WHERE cd.id_cobro = :id_cobro
                    AND cd.fecha BETWEEN sc.fecha_inicio AND sc.fecha_fin
                    AND cc.estado IN ('pendiente', 'pagado')
                ";
                $stmt_check = $conn->prepare($sql_check_comision);
                $stmt_check->bindParam(':id_cobro', $id_cobro);
                $stmt_check->execute();
                $tiene_comision = $stmt_check->fetchColumn();
                
                if ($tiene_comision > 0) {
                    $_SESSION['mensaje'] = "❌ Error: No se puede editar este cobro porque ya tiene una comisión semanal generada";
                    $_SESSION['tipo_mensaje'] = "error";
                } else {
                    // Actualizar cobro
                    $sql = "UPDATE Cobros_Diarios 
                            SET monto_cobrado = :monto, 
                                clientes_visitados = :clientes, 
                                observaciones = :observaciones
                            WHERE id_cobro = :id";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':monto', $monto_cobrado);
                    $stmt->bindParam(':clientes', $clientes_visitados);
                    $stmt->bindParam(':observaciones', $observaciones);
                    $stmt->bindParam(':id', $id_cobro);
                    
                    if ($stmt->execute()) {
                        $_SESSION['mensaje'] = "✅ Cobro actualizado exitosamente";
                        $_SESSION['tipo_mensaje'] = "success";
                    } else {
                        $_SESSION['mensaje'] = "❌ Error al actualizar el cobro";
                        $_SESSION['tipo_mensaje'] = "error";
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
    }
    
    // Reconstruir la URL con los parámetros GET originales
    $url_params = [];
    if (isset($_GET['pagina'])) $url_params['pagina'] = $_GET['pagina'];
    if (isset($_GET['registros'])) $url_params['registros'] = $_GET['registros'];
    if (isset($_GET['empleado'])) $url_params['empleado'] = $_GET['empleado'];
    if (isset($_GET['fecha_inicio'])) $url_params['fecha_inicio'] = $_GET['fecha_inicio'];
    if (isset($_GET['fecha_fin'])) $url_params['fecha_fin'] = $_GET['fecha_fin'];
    if (isset($_GET['zona'])) $url_params['zona'] = $_GET['zona'];
    
    $redirect_url = 'ver_cobros.php';
    if (!empty($url_params)) {
        $redirect_url .= '?' . http_build_query($url_params);
    }
    
    header("Location: $redirect_url");
    exit;
}

// Mostrar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? max(10, min(100, intval($_GET['registros']))) : 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$filtro_empleado = isset($_GET['empleado']) ? $_GET['empleado'] : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$filtro_zona = isset($_GET['zona']) ? $_GET['zona'] : '';

// Obtener todos los empleados activos con nombre completo
$query_empleados = "SELECT 
    id_empleado,
    (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo
    FROM Empleados 
    WHERE estado = 'activo' 
    ORDER BY nombre, apellido_paterno";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Zonas disponibles
$zonas_disponibles = ['XZ', 'WZ', 'VZ', 'KZ', 'AKZ', 'TZ', 'RZ', 'YZ'];

// Construir query con filtros
$where = ["1=1"];
$params = [];

if ($filtro_empleado) {
    $where[] = "cd.id_empleado = :empleado";
    $params[':empleado'] = $filtro_empleado;
}
if ($filtro_fecha_inicio) {
    $where[] = "cd.fecha >= :fecha_inicio";
    $params[':fecha_inicio'] = $filtro_fecha_inicio;
}
if ($filtro_fecha_fin) {
    $where[] = "cd.fecha <= :fecha_fin";
    $params[':fecha_fin'] = $filtro_fecha_fin;
}
if ($filtro_zona) {
    $where[] = "e.zona = :zona";
    $params[':zona'] = $filtro_zona;
}

$where_clause = implode(" AND ", $where);

// Contar total de registros (para paginación)
$query_count = "
    SELECT COUNT(*) as total
    FROM Cobros_Diarios cd
    INNER JOIN Empleados e ON cd.id_empleado = e.id_empleado
    WHERE $where_clause
";
$stmt_count = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros_paginacion = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener cobros con paginación y verificar si tienen comisión
$query_cobros = "
    SELECT 
        cd.id_cobro,
        cd.id_empleado,
        (e.nombre || ' ' || e.apellido_paterno || ' ' || COALESCE(e.apellido_materno, '')) as nombre_empleado,
        e.rol as rol_empleado,
        e.zona,
        cd.fecha,
        CASE CAST(strftime('%w', cd.fecha) AS INTEGER)
            WHEN 0 THEN 'Domingo'
            WHEN 1 THEN 'Lunes'
            WHEN 2 THEN 'Martes'
            WHEN 3 THEN 'Miércoles'
            WHEN 4 THEN 'Jueves'
            WHEN 5 THEN 'Viernes'
            WHEN 6 THEN 'Sábado'
        END as dia_semana,
        cd.monto_cobrado,
        cd.clientes_visitados,
        cd.observaciones,
        cd.registrado_por,
        cd.fecha_registro,
        COALESCE(pe.monto, 0) as monto_prestamo,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM Comisiones_Cobradores cc 
                INNER JOIN Semanas_Cobro sc ON cc.id_semana = sc.id_semana 
                WHERE cc.id_empleado = cd.id_empleado 
                AND cd.fecha BETWEEN sc.fecha_inicio AND sc.fecha_fin
                AND cc.estado IN ('pendiente', 'pagado')
            ) THEN 1 
            ELSE 0 
        END as tiene_comision
    FROM Cobros_Diarios cd
    INNER JOIN Empleados e ON cd.id_empleado = e.id_empleado
    LEFT JOIN Prestamos_Empleados pe ON cd.id_empleado = pe.id_empleado 
        AND cd.fecha = pe.fecha_prestamo 
        AND pe.estado = 'activo'
    WHERE $where_clause
    ORDER BY cd.fecha DESC, e.nombre
    LIMIT :limit OFFSET :offset
";
$stmt_cobros = $conn->prepare($query_cobros);
foreach ($params as $key => $value) {
    $stmt_cobros->bindValue($key, $value);
}
$stmt_cobros->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_cobros->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_cobros->execute();
$cobros = $stmt_cobros->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales (de todos los registros, no solo la página actual)
$query_totales = "
    SELECT 
        COUNT(DISTINCT cd.id_cobro) as total_registros,
        COALESCE(SUM(cd.monto_cobrado), 0) as total_cobrado,
        COALESCE(SUM(cd.clientes_visitados), 0) as total_clientes,
        COALESCE(SUM(pe.monto), 0) as total_prestamos
    FROM Cobros_Diarios cd
    INNER JOIN Empleados e ON cd.id_empleado = e.id_empleado
    LEFT JOIN Prestamos_Empleados pe ON cd.id_empleado = pe.id_empleado 
        AND cd.fecha = pe.fecha_prestamo 
        AND pe.estado = 'activo'
    WHERE $where_clause
";
$stmt_totales = $conn->prepare($query_totales);
foreach ($params as $key => $value) {
    $stmt_totales->bindValue($key, $value);
}
$stmt_totales->execute();
$totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);

// Calcular total neto
$total_neto = $totales['total_cobrado'] - $totales['total_prestamos'];

// Función para construir URL de paginación
function construirUrlPaginacion($pagina, $filtros, $registros_por_pagina) {
    $params = [
        'pagina' => $pagina,
        'registros' => $registros_por_pagina
    ];
    
    if (!empty($filtros['empleado'])) $params['empleado'] = $filtros['empleado'];
    if (!empty($filtros['fecha_inicio'])) $params['fecha_inicio'] = $filtros['fecha_inicio'];
    if (!empty($filtros['fecha_fin'])) $params['fecha_fin'] = $filtros['fecha_fin'];
    if (!empty($filtros['zona'])) $params['zona'] = $filtros['zona'];
    
    return '?' . http_build_query($params);
}

$filtros_actuales = [
    'empleado' => $filtro_empleado,
    'fecha_inicio' => $filtro_fecha_inicio,
    'fecha_fin' => $filtro_fecha_fin,
    'zona' => $filtro_zona
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
    <title>Ver Cobros Diarios - Zeus Hogar</title>
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
            color: #2E7D32;
        }
        .zona-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #F3E5F5;
            color: #7B1FA2;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .text-muted {
            color: #999;
        }
        .text-bold {
            font-weight: 600;
        }
        .badge-comision {
            display: inline-block;
            padding: 3px 8px;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }
        .btn-disabled {
            background: #ccc !important;
            color: #666 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        .btn-disabled:hover {
            transform: none !important;
            background: #ccc !important;
        }

        /* Estilos del Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9998;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-container {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            background: linear-gradient(135deg, #0c3c78, #1e5799);
            color: white;
            padding: 20px 25px;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .info-box {
            background: #E3F2FD;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box strong {
            display: block;
            color: #1976D2;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box p {
            margin: 5px 0;
            color: #424242;
            font-size: 13px;
        }

        .info-box .prestamo-info {
            background: #FFF3E0;
            border: 1px solid #FFB74D;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box .prestamo-info i {
            color: #F57C00;
            font-size: 20px;
        }

        .modal-form-group {
            margin-bottom: 20px;
        }

        .modal-form-group label {
            display: block;
            font-weight: 600;
            color: #1e1f26;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .modal-form-group label i {
            color: #0c3c78;
            margin-right: 5px;
        }

        .modal-form-group input,
        .modal-form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .modal-form-group input:focus,
        .modal-form-group textarea:focus {
            outline: none;
            border-color: #0c3c78;
            background: #f8f9fa;
        }

        .modal-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .modal-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            border-radius: 0 0 16px 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, #0c3c78, #1e5799);
            color: white;
        }

        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(12, 60, 120, 0.4);
        }

        .btn-modal-secondary {
            background: #e5e7eb;
            color: #1e1f26;
        }

        .btn-modal-secondary:hover {
            background: #d1d5db;
        }

        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background: #FFF3E0;
            color: #F57C00;
        }

        .btn-edit:hover {
            background: #FFE0B2;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .mensaje {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .mensaje.success {
            background: #E8F5E9;
            color: #2E7D32;
            border-left: 4px solid #4CAF50;
        }

        .mensaje.error {
            background: #FFEBEE;
            color: #C62828;
            border-left: 4px solid #F44336;
        }

        .mensaje i {
            font-size: 24px;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .summary-card.emerald .card-icon {
            background: linear-gradient(135deg, #10B981, #059669);
        }

        .summary-card.red .card-icon {
            background: linear-gradient(135deg, #EF4444, #DC2626);
        }

        .subtitle {
            display: block;
            font-size: 11px;
            color: rgba(255,255,255,0.9);
            margin-top: 4px;
        }

        .badge-info {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #FFF3E0;
            color: #F57C00;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            color: #1e1f26;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .pagination-info strong {
            color: #0c3c78;
            font-weight: 700;
        }

        .pagination-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .pagination-controls select {
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-controls select:focus {
            outline: none;
            border-color: #0c3c78;
        }

        .pagination-controls label {
            color: #1e1f26;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .pagination-buttons {
            display: flex;
            gap: 8px;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border: 2px solid #e5e7eb;
            background: white;
            color: #1e1f26;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .pagination-btn:hover {
            border-color: #0c3c78;
            background: #f3f4f6;
            color: #0c3c78;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #0c3c78, #1e5799);
            border-color: #0c3c78;
            color: white;
            font-weight: 700;
        }

        .pagination-btn i {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-list-ul'></i> Ver Cobros Diarios</h1>
            <div class="header-actions">
                <a href="registrar_cobro.php" class="btn btn-primary">
                    <i class='bx bx-plus-circle'></i> Registrar Cobro
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <div><?php echo htmlspecialchars($mensaje); ?></div>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros de Búsqueda</h2>
            <form method="GET" action="ver_cobros.php" class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="empleado">Empleado</label>
                        <select name="empleado" id="empleado">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo $empleado['id_empleado']; ?>" <?php echo $filtro_empleado == $empleado['id_empleado'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>">
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>">
                    </div>

                    <div class="form-group">
                        <label for="zona">Zona</label>
                        <select name="zona" id="zona">
                            <option value="">Todas las zonas</option>
                            <?php foreach ($zonas_disponibles as $zona): ?>
                                <option value="<?php echo $zona; ?>" <?php echo $filtro_zona == $zona ? 'selected' : ''; ?>>
                                    <?php echo $zona; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <?php if ($filtro_empleado || $filtro_fecha_inicio || $filtro_fecha_fin || $filtro_zona): ?>
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
                    <i class='bx bx-file'></i>
                </div>
                <div class="card-content">
                    <h3>Total Registros</h3>
                    <p class="amount"><?php echo number_format($totales['total_registros']); ?></p>
                </div>
            </div>

            <div class="summary-card green">
                <div class="card-icon">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Total Cobrado</h3>
                    <p class="amount">$<?php echo number_format($totales['total_cobrado'], 2); ?></p>
                </div>
            </div>

            <div class="summary-card orange">
                <div class="card-icon">
                    <i class='bx bx-wallet'></i>
                </div>
                <div class="card-content">
                    <h3>Total Préstamos</h3>
                    <p class="amount">$<?php echo number_format($totales['total_prestamos'], 2); ?></p>
                </div>
            </div>

            <div class="summary-card <?php echo $total_neto >= 0 ? 'emerald' : 'red'; ?>">
                <div class="card-icon">
                    <i class='bx <?php echo $total_neto >= 0 ? 'bx-trending-up' : 'bx-trending-down'; ?>'></i>
                </div>
                <div class="card-content">
                    <h3>Total (Cobrado - Préstamos)</h3>
                    <p class="amount">$<?php echo number_format($total_neto, 2); ?></p>
                    <small class="subtitle">
                        <?php echo $total_neto >= 0 ? 'Saldo Positivo' : 'Saldo Negativo'; ?>
                    </small>
                </div>
            </div>

            <div class="summary-card purple">
                <div class="card-icon">
                    <i class='bx bx-group'></i>
                </div>
                <div class="card-content">
                    <h3>Clientes Visitados</h3>
                    <p class="amount"><?php echo number_format($totales['total_clientes']); ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de Cobros -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Listado de Cobros (<?php echo number_format($total_registros_paginacion); ?>)</h2>

            <?php if (count($cobros) > 0): ?>
                <div class="table-container">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Empleado</th>
                                <th>Zona</th>
                                <th>Monto</th>
                                <th>Préstamo</th>
                                <th>Clientes</th>
                                <th>Observaciones</th>
                                <th>Registrado Por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cobros as $cobro): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($cobro['fecha'])); ?>
                                        <?php if ($cobro['tiene_comision']): ?>
                                            <span class="badge-comision" title="Comisión generada">✓</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-info"><?php echo $cobro['dia_semana']; ?></span></td>
                                    <td><?php echo htmlspecialchars($cobro['nombre_empleado']); ?></td>
                                    <td><span class="zona-badge"><?php echo $cobro['zona']; ?></span></td>
                                    <td class="text-bold" style="color: #4CAF50;">$<?php echo number_format($cobro['monto_cobrado'], 2); ?></td>
                                    <td class="text-bold" style="color: <?php echo $cobro['monto_prestamo'] > 0 ? '#FF9800' : '#999'; ?>;">
                                        <?php if ($cobro['monto_prestamo'] > 0): ?>
                                            $<?php echo number_format($cobro['monto_prestamo'], 2); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $cobro['clientes_visitados']; ?></td>
                                    <td>
                                        <?php if ($cobro['observaciones']): ?>
                                            <small><?php echo htmlspecialchars(substr($cobro['observaciones'], 0, 50)); ?><?php echo strlen($cobro['observaciones']) > 50 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($cobro['registrado_por']); ?></small></td>
                                    <td class="actions">
                                        <?php if ($cobro['tiene_comision']): ?>
                                            <button type="button" class="btn-action btn-edit btn-disabled" title="Comisión ya generada - No editable">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action btn-edit" onclick="abrirModalEdicion(<?php echo htmlspecialchars(json_encode($cobro), ENT_QUOTES, 'UTF-8'); ?>)" title="Editar cobro">
                                                <i class='bx bx-edit'></i>
                                            </button>
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
                            
                            for ($i = $rango_inicio; $i <= $rango_fin; $i++) {
                                echo '<a href="' . construirUrlPaginacion($i, $filtros_actuales, $registros_por_pagina) . '" 
                                       class="pagination-btn ' . ($i === $pagina_actual ? 'active' : '') . '">' . $i . '</a>';
                            }
                            ?>

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
                    <p>No hay cobros registrados con los filtros seleccionados</p>
                    <a href="registrar_cobro.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Registrar Primer Cobro
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Edición -->
    <div class="modal-overlay" id="modalEdicion">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class='bx bx-edit'></i> Editar Cobro</h2>
                <button type="button" class="modal-close" onclick="cerrarModal()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            
            <form id="formEdicion" method="POST" action="ver_cobros.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="editar_cobro">
                <input type="hidden" name="id_cobro" id="edit_id_cobro">
                
                <!-- Mantener filtros actuales -->
                <?php if ($filtro_empleado): ?>
                    <input type="hidden" name="empleado" value="<?php echo $filtro_empleado; ?>">
                <?php endif; ?>
                <?php if ($filtro_fecha_inicio): ?>
                    <input type="hidden" name="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>">
                <?php endif; ?>
                <?php if ($filtro_fecha_fin): ?>
                    <input type="hidden" name="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>">
                <?php endif; ?>
                <?php if ($filtro_zona): ?>
                    <input type="hidden" name="zona" value="<?php echo $filtro_zona; ?>">
                <?php endif; ?>
                <input type="hidden" name="pagina" value="<?php echo $pagina_actual; ?>">
                <input type="hidden" name="registros" value="<?php echo $registros_por_pagina; ?>">
                
                <div class="modal-body">
                    <div class="info-box">
                        <strong>Información del Cobro</strong>
                        <p><i class='bx bx-user'></i> <strong>Empleado:</strong> <span id="info_empleado"></span></p>
                        <p><i class='bx bx-calendar'></i> <strong>Fecha:</strong> <span id="info_fecha"></span></p>
                        <p><i class='bx bx-map'></i> <strong>Zona:</strong> <span id="info_zona"></span></p>
                        <div id="info_prestamo_container" style="display: none;">
                            <div class="prestamo-info">
                                <i class='bx bx-wallet'></i>
                                <div>
                                    <strong>Préstamo asociado:</strong>
                                    <span id="info_prestamo"></span>
                                    <small style="display: block; margin-top: 5px; color: #666;">
                                        ⚠️ Los préstamos no se pueden editar desde aquí
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="edit_monto_cobrado">
                            <i class='bx bx-dollar'></i> Monto Cobrado <span style="color: red;">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="monto_cobrado" 
                            id="edit_monto_cobrado" 
                            step="0.01" 
                            min="0"
                            required 
                            placeholder="0.00">
                        <small>No se permiten valores negativos</small>
                    </div>

                    <div class="modal-form-group">
                        <label for="edit_clientes_visitados">
                            <i class='bx bx-user-check'></i> Clientes Visitados <span style="color: red;">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="clientes_visitados" 
                            id="edit_clientes_visitados" 
                            min="0"
                            required 
                            placeholder="0">
                        <small>No se permiten valores negativos</small>
                    </div>

                    <div class="modal-form-group">
                        <label for="edit_observaciones">
                            <i class='bx bx-note'></i> Observaciones
                        </label>
                        <textarea 
                            name="observaciones" 
                            id="edit_observaciones" 
                            maxlength="500"
                            placeholder="Observaciones opcionales..."></textarea>
                        <small id="contador_caracteres">0/500 caracteres</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="cerrarModal()">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-primary" onclick="confirmarEdicion()">
                        <i class='bx bx-save'></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS - DEBEN IR ANTES DEL CIERRE DE BODY -->
    <script>
        // FUNCIONES MOVIDAS AL PRINCIPIO PARA EVITAR ERRORES
        let datosOriginales = {};

        function abrirModalEdicion(cobro) {
            // Guardar datos originales
            datosOriginales = {
                monto_cobrado: cobro.monto_cobrado,
                clientes_visitados: cobro.clientes_visitados,
                observaciones: cobro.observaciones || ''
            };

            // Llenar el formulario
            document.getElementById('edit_id_cobro').value = cobro.id_cobro;
            document.getElementById('edit_monto_cobrado').value = cobro.monto_cobrado;
            document.getElementById('edit_clientes_visitados').value = cobro.clientes_visitados;
            document.getElementById('edit_observaciones').value = cobro.observaciones || '';

            // Actualizar contador de caracteres
            actualizarContador();

            // Información del cobro
            document.getElementById('info_empleado').textContent = cobro.nombre_empleado;
            document.getElementById('info_fecha').textContent = formatearFecha(cobro.fecha) + ' (' + cobro.dia_semana + ')';
            document.getElementById('info_zona').textContent = cobro.zona;

            // Mostrar préstamo si existe
            if (cobro.monto_prestamo && cobro.monto_prestamo > 0) {
                document.getElementById('info_prestamo_container').style.display = 'block';
                document.getElementById('info_prestamo').textContent = '$' + Number(cobro.monto_prestamo).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('info_prestamo_container').style.display = 'none';
            }

            // Mostrar modal
            document.getElementById('modalEdicion').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            document.getElementById('modalEdicion').classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('formEdicion').reset();
        }

        function confirmarEdicion() {
            const montoActual = parseFloat(document.getElementById('edit_monto_cobrado').value);
            const clientesActual = parseInt(document.getElementById('edit_clientes_visitados').value);
            const obsActual = document.getElementById('edit_observaciones').value.trim();

            // Validar que no sean valores negativos
            if (montoActual < 0) {
                alert('❌ Error: El monto cobrado no puede ser negativo');
                return;
            }

            if (clientesActual < 0) {
                alert('❌ Error: Los clientes visitados no pueden ser negativos');
                return;
            }

            // Verificar si hay cambios
            const huboCambios = 
                montoActual !== datosOriginales.monto_cobrado ||
                clientesActual !== datosOriginales.clientes_visitados ||
                obsActual !== datosOriginales.observaciones;

            if (!huboCambios) {
                alert('ℹ️ No se detectaron cambios en el cobro.');
                return;
            }

            // Construir mensaje de confirmación detallado
            let mensaje = '¿Está seguro de guardar los cambios?\n\n';
            mensaje += '📋 CAMBIOS DETECTADOS:\n\n';

            if (montoActual !== datosOriginales.monto_cobrado) {
                mensaje += '💰 Monto:\n';
                mensaje += '   Anterior: $' + datosOriginales.monto_cobrado.toFixed(2) + '\n';
                mensaje += '   Nuevo: $' + montoActual.toFixed(2) + '\n\n';
            }

            if (clientesActual !== datosOriginales.clientes_visitados) {
                mensaje += '👥 Clientes:\n';
                mensaje += '   Anterior: ' + datosOriginales.clientes_visitados + '\n';
                mensaje += '   Nuevo: ' + clientesActual + '\n\n';
            }

            if (obsActual !== datosOriginales.observaciones) {
                mensaje += '📝 Observaciones: Modificadas\n\n';
            }

            mensaje += '⚠️ Esta acción no se puede deshacer.';

            if (confirm(mensaje)) {
                document.getElementById('formEdicion').submit();
            }
        }

        function formatearFecha(fecha) {
            const opciones = { day: '2-digit', month: '2-digit', year: 'numeric' };
            return new Date(fecha + 'T00:00:00').toLocaleDateString('es-MX', opciones);
        }

        function actualizarContador() {
            const textarea = document.getElementById('edit_observaciones');
            const contador = document.getElementById('contador_caracteres');
            const length = textarea.value.length;
            contador.textContent = length + '/500 caracteres';
            
            if (length >= 450) {
                contador.style.color = '#EF4444';
            } else if (length >= 400) {
                contador.style.color = '#F59E0B';
            } else {
                contador.style.color = '#666';
            }
        }

        function cambiarRegistrosPorPagina(nuevoValor) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('registros', nuevoValor);
            urlParams.set('pagina', '1');
            window.location.search = urlParams.toString();
        }

        function limpiarFiltros() {
            window.location.href = 'ver_cobros.php';
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('edit_observaciones');
            if (textarea) {
                textarea.addEventListener('input', actualizarContador);
            }

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModal();
                }
            });

            // Cerrar modal al hacer clic fuera
            document.getElementById('modalEdicion').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModal();
                }
            });

            // Auto-ocultar mensaje después de 5 segundos
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.style.transition = 'opacity 0.5s';
                    mensaje.style.opacity = '0';
                    setTimeout(() => mensaje.remove(), 500);
                }, 5000);
            }
        });
    </script>
    
    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>