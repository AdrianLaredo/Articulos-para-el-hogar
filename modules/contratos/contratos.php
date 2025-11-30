<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions_contratos.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Verificar si hay mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Filtros
$filtro_estado = isset($_GET['filtro_estado']) ? $_GET['filtro_estado'] : 'todos';
$filtro_empleado = isset($_GET['filtro_empleado']) ? $_GET['filtro_empleado'] : 'todos';
$filtro_zona = isset($_GET['filtro_zona']) ? $_GET['filtro_zona'] : 'todos';
$filtro_folio = isset($_GET['filtro_folio']) ? $_GET['filtro_folio'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Obtener folios con filtros y detectar traspasos
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
            fv.observaciones,
            COALESCE(fv.estado, 'activo') as estado,
            (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) as nombre_empleado,
            e.rol,
            a.id_asignacion,
            (SELECT SUM(dfv.monto_comision) 
             FROM Detalle_Folio_Venta dfv 
             WHERE dfv.id_folio = fv.id_folio 
             AND dfv.comision_cancelada = 0) as total_comision,
            (SELECT COUNT(*) 
             FROM Detalle_Folio_Venta dfv
             INNER JOIN Detalle_Asignacion da ON dfv.id_producto = da.id_producto 
                 AND da.id_asignacion = fv.id_asignacion
             WHERE dfv.id_folio = fv.id_folio
             AND EXISTS (
                 SELECT 1 FROM Traspasos_Asignaciones t
                 WHERE t.id_asignacion_destino = fv.id_asignacion
                 AND t.id_producto = dfv.id_producto
                 AND t.fecha_hora_traspaso <= fv.fecha_hora_venta
             )) as tiene_productos_traspasados
        FROM Folios_Venta fv
        INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        WHERE 1=1";

$params = [];

// Filtro por número de folio específico
if (!empty($filtro_folio)) {
    $sql .= " AND fv.numero_folio LIKE :folio";
    $params[':folio'] = '%' . $filtro_folio . '%';
}

// Filtro por estado del folio
if ($filtro_estado !== 'todos') {
    if ($filtro_estado == 'liquidado') {
        $sql .= " AND fv.saldo_pendiente <= 0 AND COALESCE(fv.estado, 'activo') = 'activo'";
    } elseif ($filtro_estado == 'credito') {
        $sql .= " AND fv.tipo_pago = 'credito' AND fv.saldo_pendiente > 0 AND COALESCE(fv.estado, 'activo') = 'activo'";
    } elseif ($filtro_estado == 'contado') {
        $sql .= " AND fv.tipo_pago = 'contado' AND COALESCE(fv.estado, 'activo') = 'activo'";
    } elseif ($filtro_estado == 'cancelado') {
        $sql .= " AND COALESCE(fv.estado, 'activo') = 'cancelado'";
    }
} else {
    // Por defecto, mostrar solo folios activos
    $sql .= " AND COALESCE(fv.estado, 'activo') = 'activo'";
}

// Filtro por empleado
if ($filtro_empleado !== 'todos') {
    $sql .= " AND a.id_empleado = :id_empleado";
    $params[':id_empleado'] = $filtro_empleado;
}

// Filtro por zona
if ($filtro_zona !== 'todos') {
    $sql .= " AND fv.zona = :zona";
    $params[':zona'] = $filtro_zona;
}

// Filtro por fecha
if ($fecha_desde) {
    $sql .= " AND DATE(fv.fecha_hora_venta) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND DATE(fv.fecha_hora_venta) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$sql .= " ORDER BY fv.fecha_hora_venta DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$folios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados para filtro
$sql_empleados = "SELECT id_empleado, (nombre || ' ' || apellido_paterno || ' ' || apellido_materno) as nombre_completo 
                  FROM Empleados 
                  WHERE estado = 'activo' 
                  ORDER BY nombre";
$stmt_emp = $conn->prepare($sql_empleados);
$stmt_emp->execute();
$empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

// Obtener zonas únicas para filtro
$sql_zonas = "SELECT DISTINCT zona FROM Folios_Venta WHERE zona IS NOT NULL AND zona != '' ORDER BY zona";
$stmt_zonas = $conn->prepare($sql_zonas);
$stmt_zonas->execute();
$zonas = $stmt_zonas->fetchAll(PDO::FETCH_COLUMN);

// Calcular estadísticas
$total_folios = count($folios);
$total_contados = 0;
$total_comisiones = 0;
$total_con_traspasos = 0;
$total_cancelados = 0;

foreach ($folios as $folio) {
    if ($folio['tipo_pago'] == 'contado') {
        $total_contados++;
    }
    $total_comisiones += $folio['total_comision'];
    if ($folio['tiene_productos_traspasados'] > 0) {
        $total_con_traspasos++;
    }
    if ($folio['estado'] == 'cancelado') {
        $total_cancelados++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folios de Venta - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/contratos.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-file'></i> Módulo de Folios de Venta</h1>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Búsqueda Rápida por Folio -->
        <div class="folio-search-section">
            <h2><i class='bx bx-search-alt'></i> Búsqueda Rápida por Folio</h2>
            <form method="GET" action="" class="quick-search-form">
                <div class="form-group">
                    <label for="filtro_folio">
                        <i class='bx bx-hash'></i> Número de Folio
                    </label>
                    <input type="text" 
                           id="filtro_folio" 
                           name="filtro_folio" 
                           value="<?php echo htmlspecialchars($filtro_folio); ?>"
                           placeholder="Ej: FV-001-2024, VENTA-123, etc."
                           style="background: #fffbea; font-weight: bold;">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-search'></i> Buscar Folio
                </button>
                <?php if (!empty($filtro_folio)): ?>
                    <a href="contratos.php" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Limpiar Búsqueda
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
                        <label for="filtro_estado">
                            <i class='bx bx-category'></i> Estado del Folio
                        </label>
                        <select id="filtro_estado" name="filtro_estado">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos (Activos)</option>
                            <option value="credito" <?php echo $filtro_estado == 'credito' ? 'selected' : ''; ?>>Crédito</option>
                            <option value="contado" <?php echo $filtro_estado == 'contado' ? 'selected' : ''; ?>>Contado</option>
                            <option value="liquidado" <?php echo $filtro_estado == 'liquidado' ? 'selected' : ''; ?>>Liquidado</option>
                            <option value="cancelado" <?php echo $filtro_estado == 'cancelado' ? 'selected' : ''; ?>>Cancelados</option>
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
                        <label for="fecha_desde">
                            <i class='bx bx-calendar'></i> Fecha Desde
                        </label>
                        <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="form-group">
                        <label for="fecha_hasta">
                            <i class='bx bx-calendar'></i> Fecha Hasta
                        </label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Aplicar Filtros
                    </button>
                    <a href="contratos.php<?php echo !empty($filtro_folio) ? '?filtro_folio=' . urlencode($filtro_folio) : ''; ?>" class="btn btn-secondary">
                        <i class='bx bx-refresh'></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Estadísticas Rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-file'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_folios; ?></div>
                    <div class="stat-label">Total Folios</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--color-success);">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_contados; ?></div>
                    <div class="stat-label">Contados</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class='bx bx-transfer'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_con_traspasos; ?></div>
                    <div class="stat-label">Con Traspasos</div>
                </div>
            </div>

            <?php if ($filtro_estado == 'cancelado'): ?>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--color-danger);">
                    <i class='bx bx-x-circle'></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_cancelados; ?></div>
                    <div class="stat-label">Cancelados</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lista de Folios -->
        <div class="card">
            <h2>
                <i class='bx bx-list-ul'></i> 
                <?php if (!empty($filtro_folio)): ?>
                    Resultados para: "<?php echo htmlspecialchars($filtro_folio); ?>" 
                <?php else: ?>
                    Folios de Venta 
                <?php endif; ?>
                (<?php echo $total_folios; ?>)
            </h2>
            
            <!-- Buscador en Tabla -->
            <div class="search-box">
                <i class='bx bx-search'></i>
                <input type="text" 
                       id="searchInput" 
                       placeholder="Buscar en la tabla por folio, cliente, zona..."
                       autocomplete="off">
                <button type="button" class="clear-search" id="clearSearch">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <?php if ($total_folios > 0): ?>
                <div class="table-container">
                    <table class="table-contratos" id="tablaFolios">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Zona</th>
                                <th>Fecha Venta</th>
                                <th>Total Venta</th>
                                <th>Enganche</th>
                                <th>Tipo</th>
                                <th>Comisión</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="foliosTableBody">
                            <?php foreach ($folios as $folio): ?>
                                <tr class="contrato-row <?php echo !empty($filtro_folio) && stripos($folio['numero_folio'], $filtro_folio) !== false ? 'folio-highlight' : ''; ?> <?php echo $folio['tiene_productos_traspasados'] > 0 ? 'row-con-traspaso' : ''; ?> <?php echo $folio['estado'] == 'cancelado' ? 'row-cancelado' : ''; ?>" 
                                    data-contrato='<?php echo json_encode([
                                    "folio" => $folio['numero_folio'],
                                    "cliente" => $folio['nombre_cliente'],
                                    "vendedor" => $folio['nombre_empleado'],
                                    "zona" => $folio['zona']
                                ]); ?>'>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <strong><?php echo htmlspecialchars($folio['numero_folio']); ?></strong>
                                            <?php if ($folio['tiene_productos_traspasados'] > 0): ?>
                                                <span class="badge-traspaso tooltip-traspaso" 
                                                      data-tooltip="Este folio incluye productos recibidos por traspaso">
                                                    <i class='bx bx-transfer'></i>
                                                    Traspaso
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($folio['nombre_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($folio['nombre_empleado']); ?></td>
                                    <td><?php echo htmlspecialchars($folio['zona']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($folio['fecha_hora_venta'])); ?></td>
                                    <td class="text-right">
                                        <strong>$<?php echo number_format($folio['total_venta'], 2); ?></strong>
                                    </td>
                                    <td class="text-right money-positive">
                                        $<?php echo number_format($folio['enganche'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="estado-badge <?php echo $folio['tipo_pago']; ?>">
                                            <?php echo ucfirst($folio['tipo_pago']); ?>
                                        </span>
                                    </td>
                                    <td class="text-right money-neutral">
                                        $<?php echo number_format($folio['total_comision'], 2); ?>
                                    </td>
                                    <td>
                                        <?php if ($folio['estado'] == 'cancelado'): ?>
                                            <span class="badge-cancelado">
                                                <i class='bx bx-x-circle'></i> Cancelado
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-activo">
                                                <i class='bx bx-check-circle'></i> Activo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-action btn-view" 
                                                onclick="verFolio(<?php echo $folio['id_folio']; ?>)"
                                                title="Ver folio completo">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        
                                        <!-- Botón Cancelar (solo admin y folio activo) -->
                                        <?php if ($folio['estado'] != 'cancelado' && $_SESSION['rol'] == 'admin'): ?>
                                            <button type="button" class="btn-action btn-cancel" 
                                                    onclick="abrirModalCancelacion(<?php echo $folio['id_folio']; ?>)"
                                                    title="Cancelar folio">
                                                <i class='bx bx-x-circle'></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mensaje de sin resultados -->
                <div class="no-results" id="noResults" style="display: none;">
                    <i class='bx bx-search-alt' style="font-size: 48px; color: #bdc3c7;"></i>
                    <p>No se encontraron folios que coincidan con tu búsqueda</p>
                </div>

                <!-- Paginación -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        Mostrando <strong id="showingStart">1</strong> - <strong id="showingEnd">10</strong> de <strong id="totalRecords"><?php echo $total_folios; ?></strong> registros
                    </div>
                    
                    <div class="pagination-controls">
                        <div class="pagination-buttons">
                            <button type="button" class="btn-page" id="btnFirst" title="Primera página">
                                <i class='bx bx-chevrons-left'></i>
                            </button>
                            <button type="button" class="btn-page" id="btnPrev" title="Anterior">
                                <i class='bx bx-chevron-left'></i>
                            </button>
                            <span id="pageNumbers" style="display: flex; gap: 8px;"></span>
                            <button type="button" class="btn-page" id="btnNext" title="Siguiente">
                                <i class='bx bx-chevron-right'></i>
                            </button>
                            <button type="button" class="btn-page" id="btnLast" title="Última página">
                                <i class='bx bx-chevrons-right'></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-file'></i>
                    <p>
                        <?php if (!empty($filtro_folio)): ?>
                            No se encontraron folios con el folio "<?php echo htmlspecialchars($filtro_folio); ?>"
                        <?php else: ?>
                            No se encontraron folios con los filtros seleccionados
                        <?php endif; ?>
                    </p>
                    <p class="text-muted">Intenta cambiar los criterios de búsqueda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para ver folio -->
    <div id="modalFolio" class="modal" style="display: none;">
        <div class="modal-content">
            <button onclick="cerrarModal()" class="btn-close-modal">×</button>
            <div id="contenidoModal">
                <!-- El contenido se cargará aquí -->
            </div>
        </div>
    </div>

    <!-- Modal para CANCELAR folio -->
    <div id="modalCancelacion" class="modal" style="display: none;">
        <div class="modal-content modal-cancelacion">
            <div class="modal-header-cancel">
                <h2>
                    <i class='bx bx-x-circle' style="color: var(--color-danger);"></i>
                    Cancelar Folio de Venta
                </h2>
                <button onclick="cerrarModalCancelacion()" class="btn-close-modal">×</button>
            </div>
            <div class="modal-body" id="contenidoCancelacion">
                <!-- El contenido se carga dinámicamente -->
            </div>
        </div>
    </div>

    <script src="assets/js/contratos.js"></script>
    <script>
        function verFolio(idFolio) {
            document.getElementById('modalFolio').style.display = 'flex';
            document.getElementById('contenidoModal').innerHTML = '<p style="text-align: center; padding: 40px;">Cargando folio...</p>';
            
            fetch(`ver_contrato.php?id=${idFolio}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('contenidoModal').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('contenidoModal').innerHTML = '<p style="color: red;">Error al cargar el folio</p>';
                });
        }
        
        function cerrarModal() {
            document.getElementById('modalFolio').style.display = 'none';
        }
        
        document.getElementById('modalFolio').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
        
        document.getElementById('modalCancelacion').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalCancelacion();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalCancelacion();
            }
        });

        const mensaje = document.getElementById('mensaje');
        if (mensaje) {
            setTimeout(() => {
                mensaje.style.opacity = '0';
                setTimeout(() => mensaje.style.display = 'none', 300);
            }, 5000);
        }
    </script>
</body>
</html>