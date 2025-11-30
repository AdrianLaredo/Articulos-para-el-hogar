<?php
session_start();
require_once '../../bd/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Filtros
$filtro_motivo = isset($_GET['filtro_motivo']) ? $_GET['filtro_motivo'] : 'todos';
$filtro_empleado = isset($_GET['filtro_empleado']) ? $_GET['filtro_empleado'] : 'todos';
$filtro_zona = isset($_GET['filtro_zona']) ? $_GET['filtro_zona'] : 'todos';
$filtro_folio = isset($_GET['filtro_folio']) ? $_GET['filtro_folio'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$filtro_enganche_devuelto = isset($_GET['filtro_enganche_devuelto']) ? $_GET['filtro_enganche_devuelto'] : 'todos';

// Obtener folios cancelados con información de cancelación
$sql = "SELECT 
            fv.id_folio,
            fv.numero_folio,
            fv.nombre_cliente,
            fv.zona,
            fv.direccion,
            fv.enganche,
            fv.total_venta,
            fv.saldo_pendiente,
            fv.tipo_pago,
            fv.fecha_hora_venta,
            (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
            e.rol,
            cf.id_cancelacion,
            cf.motivo,
            cf.observaciones,
            cf.enganche_devuelto,
            cf.monto_comision_cancelada,
            cf.descontar_comision,
            cf.fecha_cancelacion,
            COALESCE((SELECT SUM(pr.cantidad) FROM Productos_Recuperados pr 
             WHERE pr.id_cancelacion = cf.id_cancelacion), 0) as total_productos_recuperados,
            COALESCE((SELECT SUM(pr.cantidad) FROM Productos_Recuperados pr 
             WHERE pr.id_cancelacion = cf.id_cancelacion AND pr.estado = 'bueno'), 0) as productos_buen_estado,
            COALESCE((SELECT SUM(pr.cantidad) FROM Productos_Recuperados pr 
             WHERE pr.id_cancelacion = cf.id_cancelacion AND pr.estado = 'danado'), 0) as productos_danados,
            COALESCE((SELECT SUM(pr.cantidad) FROM Productos_Recuperados pr 
             WHERE pr.id_cancelacion = cf.id_cancelacion AND pr.estado = 'incompleto'), 0) as productos_incompletos,
            COALESCE((SELECT SUM(dfv.cantidad_vendida) FROM Detalle_Folio_Venta dfv 
             WHERE dfv.id_folio = fv.id_folio), 0) as total_productos_vendidos
        FROM Folios_Venta fv
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        INNER JOIN Cancelaciones_Folios cf ON fv.id_folio = cf.id_folio
        WHERE fv.estado = 'cancelado'";

$params = [];

// Aplicar filtros
if (!empty($filtro_folio)) {
    $sql .= " AND fv.numero_folio LIKE :folio";
    $params[':folio'] = '%' . $filtro_folio . '%';
}

if ($filtro_motivo !== 'todos') {
    $sql .= " AND cf.motivo = :motivo";
    $params[':motivo'] = $filtro_motivo;
}

if ($filtro_empleado !== 'todos') {
    $sql .= " AND a.id_empleado = :id_empleado";
    $params[':id_empleado'] = $filtro_empleado;
}

if ($filtro_zona !== 'todos') {
    $sql .= " AND fv.zona = :zona";
    $params[':zona'] = $filtro_zona;
}

if ($fecha_desde) {
    $sql .= " AND DATE(cf.fecha_cancelacion) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND DATE(cf.fecha_cancelacion) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

if ($filtro_enganche_devuelto !== 'todos') {
    $sql .= " AND cf.enganche_devuelto = :enganche_devuelto";
    $params[':enganche_devuelto'] = $filtro_enganche_devuelto == 'si' ? 1 : 0;
}

$sql .= " ORDER BY cf.fecha_cancelacion DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$folios_cancelados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados para filtro
$sql_empleados = "SELECT id_empleado, (nombre || ' ' || apellido_paterno || ' ' || apellido_materno) as nombre_completo 
                  FROM Empleados 
                  WHERE estado = 'activo' 
                  ORDER BY nombre";
$stmt_emp = $conn->prepare($sql_empleados);
$stmt_emp->execute();
$empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

// Obtener zonas únicas
$sql_zonas = "SELECT DISTINCT zona FROM Folios_Venta WHERE zona IS NOT NULL AND zona != '' ORDER BY zona";
$stmt_zonas = $conn->prepare($sql_zonas);
$stmt_zonas->execute();
$zonas = $stmt_zonas->fetchAll(PDO::FETCH_COLUMN);

// Calcular estadísticas
$total_cancelados = count($folios_cancelados);
$total_comision_cancelada = 0;
$total_enganche_devuelto = 0;
$total_productos_recuperados = 0;

foreach ($folios_cancelados as $folio) {
    $total_comision_cancelada += $folio['monto_comision_cancelada'];
    if ($folio['enganche_devuelto'] == 1) {
        $total_enganche_devuelto += $folio['enganche'];
    }
    $total_productos_recuperados += $folio['total_productos_recuperados'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folios Cancelados - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/folios_cancelados.css">
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div>
                <h1><i class='bx bx-x-circle'></i> Folios Cancelados</h1>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Búsqueda Rápida por Folio -->
        <div class="folio-search-section">
            <h2><i class='bx bx-search-alt'></i> Búsqueda Rápida</h2>
            <form method="GET" action="" class="quick-search-form">
                <div class="form-group">
                    <label for="filtro_folio">
                        <i class='bx bx-hash'></i> Número de Folio
                    </label>
                    <input type="text" 
                           id="filtro_folio" 
                           name="filtro_folio" 
                           value="<?php echo htmlspecialchars($filtro_folio); ?>"
                           placeholder="Ej: FV-001-2024">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-search'></i> Buscar
                </button>
                <?php if (!empty($filtro_folio)): ?>
                    <a href="folios_cancelados.php" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Filtros Avanzados -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros Avanzados</h2>
            <form method="GET" action="" class="filtros-form">
                <input type="hidden" name="filtro_folio" value="<?php echo htmlspecialchars($filtro_folio); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filtro_motivo">
                            <i class='bx bx-error-circle'></i> Motivo de Cancelación
                        </label>
                        <select id="filtro_motivo" name="filtro_motivo">
                            <option value="todos" <?php echo $filtro_motivo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="morosidad_tardia" <?php echo $filtro_motivo == 'morosidad_tardia' ? 'selected' : ''; ?>>Morosidad Tardía</option>
                            <option value="morosidad_inmediata" <?php echo $filtro_motivo == 'morosidad_inmediata' ? 'selected' : ''; ?>>Morosidad Inmediata</option>
                            <option value="situacion_extraordinaria" <?php echo $filtro_motivo == 'situacion_extraordinaria' ? 'selected' : ''; ?>>Situación Extraordinaria</option>
                            <option value="otro" <?php echo $filtro_motivo == 'otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_empleado">
                            <i class='bx bx-user'></i> Vendedor
                        </label>
                        <select id="filtro_empleado" name="filtro_empleado">
                            <option value="todos" <?php echo $filtro_empleado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>" 
                                        <?php echo $filtro_empleado == $emp['id_empleado'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_zona">
                            <i class='bx bx-map'></i> Zona
                        </label>
                        <select id="filtro_zona" name="filtro_zona">
                            <option value="todos" <?php echo $filtro_zona == 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <?php foreach ($zonas as $zona): ?>
                                <option value="<?php echo htmlspecialchars($zona); ?>" 
                                        <?php echo $filtro_zona == $zona ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($zona); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_enganche_devuelto">
                            <i class='bx bx-money'></i> Enganche Devuelto
                        </label>
                        <select id="filtro_enganche_devuelto" name="filtro_enganche_devuelto">
                            <option value="todos" <?php echo $filtro_enganche_devuelto == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="si" <?php echo $filtro_enganche_devuelto == 'si' ? 'selected' : ''; ?>>Sí</option>
                            <option value="no" <?php echo $filtro_enganche_devuelto == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_desde">
                            <i class='bx bx-calendar'></i> Cancelado Desde
                        </label>
                        <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="form-group">
                        <label for="fecha_hasta">
                            <i class='bx bx-calendar'></i> Cancelado Hasta
                        </label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Aplicar Filtros
                    </button>
                    <a href="folios_cancelados.php" class="btn btn-secondary">
                        <i class='bx bx-refresh'></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class='bx bx-x-circle'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_cancelados; ?></div>
                    <div class="stat-label">Folios Cancelados</div>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class='bx bx-wallet'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number">$<?php echo number_format($total_comision_cancelada, 2); ?></div>
                    <div class="stat-label">Comisión Cancelada</div>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class='bx bx-money'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number">$<?php echo number_format($total_enganche_devuelto, 2); ?></div>
                    <div class="stat-label">Enganche Devuelto</div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class='bx bx-package'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_productos_recuperados; ?></div>
                    <div class="stat-label">Productos Recuperados</div>
                </div>
            </div>
        </div>

        <!-- Lista de Folios Cancelados -->
        <div class="card">
            <h2>
                <i class='bx bx-list-ul'></i> 
                Listado de Folios Cancelados
                (<?php echo $total_cancelados; ?>)
            </h2>
            
            <!-- Buscador en Tabla -->
            <div class="search-box">
                <i class='bx bx-search'></i>
                <input type="text" 
                       id="searchInput" 
                       placeholder="Buscar en la tabla..."
                       autocomplete="off">
                <button type="button" class="clear-search" id="clearSearch">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <?php if ($total_cancelados > 0): ?>
                <div class="table-container">
                    <table class="table-cancelados" id="tablaCancelados">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Zona</th>
                                <th>Fecha Venta</th>
                                <th>Fecha Cancelación</th>
                                <th>Motivo</th>
                                <th>Total Venta</th>
                                <th>Comisión</th>
                                <th>Enganche</th>
                                <th>Recuperados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="canceladosTableBody">
                            <?php foreach ($folios_cancelados as $folio): 
                                $motivo_texto = [
                                    'morosidad_tardia' => 'Morosidad Tardía',
                                    'morosidad_inmediata' => 'Morosidad Inmediata',
                                    'situacion_extraordinaria' => 'Situación Extraordinaria',
                                    'otro' => 'Otro'
                                ];
                            ?>
                                <tr class="cancelado-row" 
                                    data-cancelado='<?php echo json_encode([
                                    "folio" => $folio['numero_folio'],
                                    "cliente" => $folio['nombre_cliente'],
                                    "vendedor" => $folio['nombre_empleado'],
                                    "zona" => $folio['zona'],
                                    "motivo" => $motivo_texto[$folio['motivo']]
                                ]); ?>'>
                                    <td>
                                        <strong><?php echo htmlspecialchars($folio['numero_folio']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($folio['nombre_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($folio['nombre_empleado']); ?></td>
                                    <td><?php echo htmlspecialchars($folio['zona']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($folio['fecha_hora_venta'])); ?></td>
                                    <td>
                                        <strong style="color: var(--color-danger);">
                                            <?php echo date('d/m/Y', strtotime($folio['fecha_cancelacion'])); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge-motivo <?php echo $folio['motivo']; ?>">
                                            <?php echo $motivo_texto[$folio['motivo']]; ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong>$<?php echo number_format($folio['total_venta'], 2); ?></strong>
                                    </td>
                                    <td class="text-right money-negative">
                                        $<?php echo number_format($folio['monto_comision_cancelada'], 2); ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($folio['enganche_devuelto'] == 1): ?>
                                            <span class="badge-devuelto">
                                                <i class='bx bx-check-circle'></i>
                                                $<?php echo number_format($folio['enganche'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-no-devuelto">
                                                <i class='bx bx-x-circle'></i>
                                                No devuelto
                                            </span>
                                        <?php endif; ?>
                                    </td>
<td class="text-center">
    <?php 
    $total_productos = $folio['total_productos_vendidos']; // 👈 CAMBIO AQUÍ
    $recuperados = $folio['total_productos_recuperados'];
    echo $recuperados . '/' . $total_productos;
    ?>
</td>
                                    <td class="text-center">
                                        <button type="button" class="btn-action btn-view" 
                                                onclick="verCancelacion(<?php echo $folio['id_cancelacion']; ?>)"
                                                title="Ver detalles de cancelación">
                                            <i class='bx bx-show'></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="no-results" id="noResults" style="display: none;">
                    <i class='bx bx-search-alt'></i>
                    <p>No se encontraron folios cancelados</p>
                </div>

                <!-- Paginación -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        Mostrando <strong id="showingStart">1</strong> - <strong id="showingEnd">10</strong> de <strong id="totalRecords"><?php echo $total_cancelados; ?></strong> registros
                    </div>
                    
                    <div class="pagination-controls">
                        <div class="pagination-buttons">
                            <button type="button" class="btn-page" id="btnFirst">
                                <i class='bx bx-chevrons-left'></i>
                            </button>
                            <button type="button" class="btn-page" id="btnPrev">
                                <i class='bx bx-chevron-left'></i>
                            </button>
                            <span id="pageNumbers"></span>
                            <button type="button" class="btn-page" id="btnNext">
                                <i class='bx bx-chevron-right'></i>
                            </button>
                            <button type="button" class="btn-page" id="btnLast">
                                <i class='bx bx-chevrons-right'></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-check-circle'></i>
                    <p>No hay folios cancelados con los filtros seleccionados</p>
                    <p class="text-muted">Intenta cambiar los criterios de búsqueda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para ver detalles de cancelación -->
    <div id="modalCancelacion" class="modal" style="display: none;">
        <div class="modal-content">
            <button onclick="cerrarModal()" class="btn-close-modal">×</button>
            <div id="contenidoModal">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>

    <script src="assets/js/folios_cancelados.js"></script>
</body>
</html>