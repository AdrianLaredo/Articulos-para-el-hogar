<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';
date_default_timezone_set('America/Mexico_City');

// Headers anti-caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? max(10, min(100, intval($_GET['registros']))) : 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener años y meses disponibles
$anios_disponibles = obtenerAniosDisponibles($conn);

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_anio = $_GET['anio'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';
$filtro_semana = $_GET['semana'] ?? '';
$filtro_empleado = $_GET['empleado'] ?? '';

// Obtener empleados activos
$query_empleados = "SELECT 
    id_empleado,
    (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo
    FROM Empleados 
    WHERE estado = 'activo' 
    ORDER BY nombre, apellido_paterno";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Obtener semanas según año/mes seleccionado
$semanas_filtradas = [];
if ($filtro_anio && $filtro_mes) {
    $semanas_filtradas = obtenerSemanasPorMesAnio($conn, $filtro_mes, $filtro_anio);
} elseif ($filtro_anio) {
    // Todas las semanas del año
    $query_semanas = "SELECT * FROM Semanas_Cobro 
                      WHERE strftime('%Y', fecha_inicio) = :anio 
                      ORDER BY fecha_inicio DESC";
    $stmt = $conn->prepare($query_semanas);
    $stmt->bindValue(':anio', $filtro_anio);
    $stmt->execute();
    $semanas_filtradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Construir query
$sql_where = "WHERE 1=1";
$params = [];

if ($filtro_estado !== 'todos') {
    $sql_where .= " AND estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($filtro_semana) {
    $sql_where .= " AND id_semana = :id_semana";
    $params[':id_semana'] = $filtro_semana;
} elseif ($filtro_mes && $filtro_anio) {
    // Filtrar por mes y año
    $sql_where .= " AND mes = :mes AND anio = :anio";
    $params[':mes'] = obtenerMesEspanol($filtro_mes);
    $params[':anio'] = $filtro_anio;
} elseif ($filtro_anio) {
    // Solo filtrar por año
    $sql_where .= " AND anio = :anio";
    $params[':anio'] = $filtro_anio;
}

if ($filtro_empleado) {
    $sql_where .= " AND id_empleado = :id_empleado";
    $params[':id_empleado'] = $filtro_empleado;
}

// Contar total de registros (para paginación)
$query_count = "SELECT COUNT(*) as total FROM Vista_Pagos_Sueldos_Completo $sql_where";
$stmt_count = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros_paginacion = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener pagos con paginación
$sql = "SELECT * FROM Vista_Pagos_Sueldos_Completo 
        $sql_where 
        ORDER BY fecha_registro DESC, nombre_empleado
        LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales (de todos los registros, no solo la página actual)
$query_totales = "
    SELECT 
        COALESCE(SUM(CASE WHEN estado = 'pagado' THEN total_pagar ELSE 0 END), 0) as total_pagados,
        COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN total_pagar ELSE 0 END), 0) as total_pendientes,
        COALESCE(SUM(total_pagar), 0) as total_pagos
    FROM Vista_Pagos_Sueldos_Completo
    $sql_where
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
    
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') $params['estado'] = $filtros['estado'];
    if (!empty($filtros['anio'])) $params['anio'] = $filtros['anio'];
    if (!empty($filtros['mes'])) $params['mes'] = $filtros['mes'];
    if (!empty($filtros['semana'])) $params['semana'] = $filtros['semana'];
    if (!empty($filtros['empleado'])) $params['empleado'] = $filtros['empleado'];
    
    return '?' . http_build_query($params);
}

$filtros_actuales = [
    'estado' => $filtro_estado,
    'anio' => $filtro_anio,
    'mes' => $filtro_mes,
    'semana' => $filtro_semana,
    'empleado' => $filtro_empleado
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/pagos.css">
    <style>
        .select-search {
            position: relative;
        }
        
        .select-search input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .select-search input:focus {
            outline: none;
            border-color: #0c3c78;
            box-shadow: 0 0 0 3px rgba(12, 60, 120, 0.1);
        }
        
        .select-search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .select-search-dropdown.active {
            display: block;
        }
        
        .select-search-option {
            padding: 10px 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .select-search-option:hover {
            background: #f5f5f5;
        }
        
        .select-search-option.selected {
            background: #e3f2fd;
            color: #0c3c78;
            font-weight: 600;
        }
        
        .select-search-empty {
            padding: 10px 12px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class='bx bx-history'></i> Historial de Pagos</h1>
        </div>
        
        <div class="header-actions">
            <a href="#" onclick="navegarA('registrar_pago.php'); return false;" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> Nuevo Pago
            </a>
        </div>

        <!-- Filtros -->
        <div class="card">
            <h2><i class='bx bx-filter'></i> Filtros</h2>
            <form method="GET" action="" id="formFiltros">
                <div class="filters-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <!-- Estado -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="estado"><i class='bx bx-check-circle'></i> Estado</label>
                        <select name="estado" id="estado">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                            <option value="pagado" <?php echo $filtro_estado == 'pagado' ? 'selected' : ''; ?>>Pagados</option>
                        </select>
                    </div>

                    <!-- Año -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="anio"><i class='bx bx-calendar'></i> Año</label>
                        <select name="anio" id="anio" onchange="actualizarMeses()">
                            <option value="">Todos los años</option>
                            <?php foreach ($anios_disponibles as $anio): ?>
                                <option value="<?php echo $anio; ?>" <?php echo $filtro_anio == $anio ? 'selected' : ''; ?>>
                                    <?php echo $anio; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mes -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="mes"><i class='bx bx-calendar-event'></i> Mes</label>
                        <select name="mes" id="mes" onchange="actualizarSemanas()">
                            <option value="">Todos los meses</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filtro_mes == $m ? 'selected' : ''; ?>>
                                    <?php echo obtenerMesEspanol($m); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Semana -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="semana"><i class='bx bx-list-ul'></i> Semana</label>
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
                            } else {
                                if ($filtro_anio && $filtro_mes) {
                                    echo '<option value="">No hay semanas en este mes</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Empleado con búsqueda -->
                    <div class="form-group select-search" style="margin-bottom: 0;">
                        <label for="empleado_search"><i class='bx bx-user'></i> Empleado</label>
                        <input type="text" 
                               id="empleado_search" 
                               placeholder="Buscar o seleccionar empleado..."
                               autocomplete="off"
                               value="<?php 
                                   if ($filtro_empleado) {
                                       foreach ($empleados as $emp) {
                                           if ($emp['id_empleado'] == $filtro_empleado) {
                                               echo htmlspecialchars($emp['nombre_completo']);
                                               break;
                                           }
                                       }
                                   }
                               ?>">
                        <input type="hidden" name="empleado" id="empleado" value="<?php echo $filtro_empleado; ?>">
                        <div class="select-search-dropdown" id="empleado_dropdown">
                            <div class="select-search-option" data-value="">Todos los empleados</div>
                            <?php foreach ($empleados as $emp): ?>
                                <div class="select-search-option" 
                                     data-value="<?php echo $emp['id_empleado']; ?>"
                                     data-text="<?php echo htmlspecialchars($emp['nombre_completo']); ?>">
                                    <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($filtro_anio && $filtro_mes && count($semanas_filtradas) == 0): ?>
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <i class='bx bx-info-circle'></i>
                    No hay semanas registradas para <strong><?php echo obtenerMesEspanol($filtro_mes) . ' ' . $filtro_anio; ?></strong>
                </div>
                <?php endif; ?>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <a href="historial_pagos.php" class="btn btn-secondary">
                        <i class='bx bx-refresh'></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Resumen -->
        <div class="card">
            <h2><i class='bx bx-bar-chart-alt'></i> Resumen</h2>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <i class='bx bx-file' style="color: #2196F3;"></i>
                    <div>
                        <label>Total de Pagos</label>
                        <div class="valor"><?php echo $total_registros_paginacion; ?></div>
                    </div>
                </div>
                <div class="resumen-item">
                    <i class='bx bx-time-five' style="color: #FF9800;"></i>
                    <div>
                        <label>Pendientes</label>
                        <div class="valor">$<?php echo number_format($totales['total_pendientes'], 2); ?></div>
                    </div>
                </div>
                <div class="resumen-item">
                    <i class='bx bx-check-circle' style="color: #4CAF50;"></i>
                    <div>
                        <label>Pagados</label>
                        <div class="valor text-success">$<?php echo number_format($totales['total_pagados'], 2); ?></div>
                    </div>
                </div>
                <div class="resumen-item">
                    <i class='bx bx-calculator' style="color: #9C27B0;"></i>
                    <div>
                        <label>Total General</label>
                        <div class="valor">$<?php echo number_format($totales['total_pagos'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Pagos -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Listado de Pagos (<?php echo number_format($total_registros_paginacion); ?>)</h2>
            
            <?php if (count($pagos) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Empleado</th>
                                <th>Rol</th>
                                <th>Zona</th>
                                <th>Semana</th>
                                <th>Sueldo Fijo</th>
                                <th>Gasolina</th>
                                <th class="text-right">Total</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $index => $pago): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($pago['nombre_empleado']); ?></td>
                                <td>
                                    <span class="rol-badge <?php echo $pago['rol_empleado']; ?>">
                                        <?php echo ucfirst($pago['rol_empleado']); ?>
                                    </span>
                                </td>
                                <td><span class="zona-badge"><?php echo $pago['zona']; ?></span></td>
                                <td>
                                    <small>
                                        <?php echo $pago['mes'] . ' ' . $pago['anio']; ?><br>
                                        Semana <?php echo $pago['numero_semana']; ?>
                                    </small>
                                </td>
                                <td>$<?php echo number_format($pago['sueldo_fijo'], 2); ?></td>
                                <td>$<?php echo number_format($pago['gasolina'], 2); ?></td>
                                <td class="text-right text-bold">$<?php echo number_format($pago['total_pagar'], 2); ?></td>
                                <td>
                                    <?php if ($pago['estado'] == 'pagado'): ?>
                                        <span class="estado-badge badge-success">
                                            <i class='bx bx-check'></i> Pagado
                                        </span>
                                    <?php else: ?>
                                        <span class="estado-badge badge-warning">
                                            <i class='bx bx-time'></i> Pendiente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($pago['fecha_registro'])); ?></small>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="#" onclick="verDetalle(<?php echo $pago['id_pago']; ?>); return false;" 
                                       class="btn-icon" title="Ver detalle">
                                        <i class='bx bx-show'></i>
                                    </a>
                                    <?php if ($pago['estado'] == 'pendiente'): ?>
                                        <a href="#" onclick="marcarPagado(<?php echo $pago['id_pago']; ?>); return false;" 
                                           class="btn-icon" title="Marcar como pagado">
                                            <i class='bx bx-check-circle'></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="7" class="text-right"><strong>TOTALES (página actual):</strong></td>
                                <td class="text-right"><strong>$<?php echo number_format(array_sum(array_column($pagos, 'total_pagar')), 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
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
                    <i class='bx bx-file-blank'></i>
                    <p>No hay pagos registrados con los filtros seleccionados</p>
                    <a href="#" onclick="navegarA('registrar_pago.php'); return false;" class="btn btn-primary">
                        <i class='bx bx-plus-circle'></i> Registrar Primer Pago
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Select con búsqueda para empleados
        const empleadoSearch = document.getElementById('empleado_search');
        const empleadoHidden = document.getElementById('empleado');
        const empleadoDropdown = document.getElementById('empleado_dropdown');
        const empleadoOptions = empleadoDropdown.querySelectorAll('.select-search-option');

        empleadoSearch.addEventListener('focus', function() {
            empleadoDropdown.classList.add('active');
            filterEmpleados('');
        });

        empleadoSearch.addEventListener('input', function() {
            filterEmpleados(this.value);
        });

        empleadoSearch.addEventListener('blur', function() {
            setTimeout(() => {
                empleadoDropdown.classList.remove('active');
            }, 200);
        });

        empleadoOptions.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const text = this.getAttribute('data-text') || 'Todos los empleados';
                
                empleadoHidden.value = value;
                empleadoSearch.value = value ? text : '';
                empleadoDropdown.classList.remove('active');
                
                // Marcar opción seleccionada
                empleadoOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        function filterEmpleados(searchText) {
            const text = searchText.toLowerCase();
            let hasResults = false;
            
            empleadoOptions.forEach(option => {
                const optionText = option.textContent.toLowerCase();
                if (optionText.includes(text) || option.getAttribute('data-value') === '') {
                    option.style.display = 'block';
                    hasResults = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            if (!hasResults && text !== '') {
                if (!empleadoDropdown.querySelector('.select-search-empty')) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'select-search-empty';
                    emptyDiv.textContent = 'No se encontraron empleados';
                    empleadoDropdown.appendChild(emptyDiv);
                }
            } else {
                const emptyDiv = empleadoDropdown.querySelector('.select-search-empty');
                if (emptyDiv) emptyDiv.remove();
            }
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.select-search')) {
                empleadoDropdown.classList.remove('active');
            }
        });

        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({type: 'navigate', page: pagina}, '*');
            } else {
                window.location.href = pagina;
            }
        }

        function verDetalle(id) {
            navegarA('ver_detalle_pago.php?id=' + id);
        }

        function marcarPagado(id) {
            if (confirm('¿Está seguro de marcar este pago como pagado?')) {
                window.location.href = 'marcar_pagado.php?id=' + id;
            }
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
            
            if (!anio) {
                document.getElementById('mes').value = '';
                document.getElementById('semana').innerHTML = '<option value="">Todas las semanas</option>';
            } else if (mes) {
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
            } else if (anio) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('anio', anio);
                urlParams.delete('mes');
                urlParams.delete('semana');
                urlParams.delete('pagina');
                window.location.search = urlParams.toString();
            }
        }
    </script>
</body>
</html>