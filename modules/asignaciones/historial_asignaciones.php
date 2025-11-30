<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Filtros
$filtro_estado = isset($_GET['filtro_estado']) ? $_GET['filtro_estado'] : 'todos';
$filtro_empleado = isset($_GET['filtro_empleado']) ? $_GET['filtro_empleado'] : 'todos';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Construir consulta base para contar total
$sql_count = "SELECT COUNT(*) as total 
              FROM Asignaciones a
              INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
              INNER JOIN Vehiculos v ON a.placas = v.placas
              WHERE 1=1";

$params = [];

if ($filtro_estado !== 'todos') {
    $sql_count .= " AND a.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($filtro_empleado !== 'todos') {
    $sql_count .= " AND a.id_empleado = :id_empleado";
    $params[':id_empleado'] = $filtro_empleado;
}

if ($fecha_desde) {
    // Si usa SQLite, DATE() solo funciona con formato YYYY-MM-DD
    $sql_count .= " AND a.fecha_hora_salida >= :fecha_desde_inicio";
    $params[':fecha_desde_inicio'] = $fecha_desde . ' 00:00:00';
}

if ($fecha_hasta) {
    // Si usa SQLite, DATE() solo funciona con formato YYYY-MM-DD
    $sql_count .= " AND a.fecha_hora_salida <= :fecha_hasta_fin";
    $params[':fecha_hasta_fin'] = $fecha_hasta . ' 23:59:59';
}


// Obtener total de registros
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Ajustar página actual si es necesario
if ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
}

// Construir consulta principal con paginación
$sql = "SELECT a.*, 
                e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno as nombre_empleado,
                e.rol,
                v.marca || ' ' || v.modelo || ' (' || a.placas || ')' as vehiculo_desc,
                (SELECT COUNT(*) FROM Detalle_Asignacion WHERE id_asignacion = a.id_asignacion) as total_productos,
                (SELECT COALESCE(SUM(cantidad_cargada), 0) FROM Detalle_Asignacion WHERE id_asignacion = a.id_asignacion) as total_cargado,
                (SELECT COALESCE(SUM(cantidad_vendida), 0) FROM Detalle_Asignacion WHERE id_asignacion = a.id_asignacion) as total_vendido,
                (SELECT COALESCE(SUM(cantidad_devuelta), 0) FROM Detalle_Asignacion WHERE id_asignacion = a.id_asignacion) as total_devuelto,
                (SELECT COUNT(*) FROM Folios_Venta WHERE id_asignacion = a.id_asignacion) as total_folios
        FROM Asignaciones a
        INNER JOIN Empleados e ON a.id_empleado = e.id_empleado
        INNER JOIN Vehiculos v ON a.placas = v.placas
        WHERE 1=1";

// Aplicar mismos filtros
if ($filtro_estado !== 'todos') {
    $sql .= " AND a.estado = :estado";
}

if ($filtro_empleado !== 'todos') {
    $sql .= " AND a.id_empleado = :id_empleado";
}

if ($fecha_desde) {
    $sql .= " AND a.fecha_hora_salida >= :fecha_desde_inicio";
}

if ($fecha_hasta) {
    $sql .= " AND a.fecha_hora_salida <= :fecha_hasta_fin";
}

$sql .= " ORDER BY a.fecha_hora_salida DESC 
          LIMIT :limit OFFSET :offset";

// Preparar y ejecutar consulta principal
$stmt = $conn->prepare($sql);

// Vincular parámetros de filtros
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Vincular parámetros de paginación
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados para filtro
$sql_empleados = "SELECT id_empleado, nombre || ' ' || apellido_paterno || ' ' || apellido_materno as nombre_completo 
                  FROM Empleados ORDER BY nombre";
$stmt_emp = $conn->prepare($sql_empleados);
$stmt_emp->execute();
$empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Historial de Asignaciones - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/asignaciones.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-history'></i> Historial de Asignaciones</h1>

        <div class="card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <h2><i class='bx bx-filter'></i> Filtros</h2>
            <form method="GET" action="" class="filtros-form">
                <input type="hidden" name="pagina" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filtro_estado">Estado</label>
                        <select id="filtro_estado" name="filtro_estado">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="abierta" <?php echo $filtro_estado == 'abierta' ? 'selected' : ''; ?>>Abiertas</option>
                            <option value="cerrada" <?php echo $filtro_estado == 'cerrada' ? 'selected' : ''; ?>>Cerradas</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filtro_empleado">Empleado</label>
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
                        <label for="fecha_desde">Fecha Desde</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="form-group">
                        <label for="fecha_hasta">Fecha Hasta</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Buscar
                    </button>
                    <a href="historial_asignaciones.php" class="btn btn-secondary">
                        <i class='bx bx-refresh'></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Resultados (<?php echo $total_registros; ?>)</h2>

            <?php if (count($asignaciones) > 0): ?>
                <div class="tabla-productos">
                    <table>
                        <thead>
                            <tr>
                                <th>Asignación</th>
                                <th>Empleado</th>
                                <th>Vehículo</th>
                                <th>Fecha Salida</th>
                                <th>Fecha Regreso</th>
                                <th>Cargado</th>
                                <th>Vendido</th>
                                <th>Devuelto</th>
                                <th>Folios</th>
                                <th>Traspasos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asignaciones as $asignacion): ?>
                                <?php 
                                    // === CORRECCIÓN: Las fechas ya están en hora local ===
                                    // NO necesitamos conversión UTC porque se insertaron correctamente
                                    $fecha_salida = new DateTime($asignacion['fecha_hora_salida']);
                                    $fecha_salida_display = $fecha_salida->format('d/m/Y H:i');

                                    // Fecha de regreso también en hora local
                                    $fecha_regreso_display = '<span class="text-muted">En ruta</span>';
                                    if ($asignacion['fecha_hora_regreso']) {
                                        $fecha_regreso = new DateTime($asignacion['fecha_hora_regreso']);
                                        $fecha_regreso_display = $fecha_regreso->format('d/m/Y H:i');
                                    }
                                    
                                    // Obtener el estado de traspaso (usando la función existente)
                                    $tiene_traspasos = tieneTranspasos($conn, $asignacion['id_asignacion']);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($asignacion['id_asignacion'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($asignacion['nombre_empleado']); ?></td>
                                    <td><?php echo htmlspecialchars($asignacion['vehiculo_desc']); ?></td>
                                    
                                    <td><?php echo $fecha_salida_display; ?></td>
                                    
                                    <td><?php echo $fecha_regreso_display; ?></td>
                                    
                                    <td class="text-center"><strong><?php echo $asignacion['total_cargado']; ?></strong></td>
                                    <td class="text-center" style="color: #10b981; font-weight: 700;">
                                        <?php echo $asignacion['total_vendido']; ?>
                                    </td>
                                    <td class="text-center" style="color: #f59e0b;">
                                        <?php echo $asignacion['total_devuelto']; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        if ($asignacion['total_folios'] == 0 && $asignacion['estado'] == 'cerrada') {
                                            echo '<span style="color: #6b7280; font-style: italic;">Sin ventas</span>';
                                        } else {
                                            echo $asignacion['total_folios'];
                                        }
                                        ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php 
                                        if ($tiene_traspasos): 
                                        ?>
                                            <span class="tooltip-traspaso" data-tooltip="Esta asignación tiene traspasos">
                                                <i class='bx bx-transfer icono-traspaso'></i>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #d1d5db;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <span class="estado-badge <?php echo $asignacion['estado']; ?>">
                                            <?php echo ucfirst($asignacion['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="verDetalle(<?php echo $asignacion['id_asignacion']; ?>)"
                                                title="Ver detalles">
                                            <i class='bx bx-show'></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> asignaciones
                        </div>
                        <div class="pagination-controls">
                            <div class="pagination-buttons">
                                <?php if ($pagina_actual > 1): ?>
                                    <a href="?pagina=1&filtro_estado=<?php echo $filtro_estado; ?>&filtro_empleado=<?php echo $filtro_empleado; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                                       class="btn-page" title="Primera página">
                                        <i class='bx bx-chevrons-left'></i>
                                    </a>
                                    <a href="?pagina=<?php echo $pagina_actual - 1; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_empleado=<?php echo $filtro_empleado; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                                       class="btn-page" title="Página anterior">
                                        <i class='bx bx-chevron-left'></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $rango_inicio = max(1, $pagina_actual - 2);
                                $rango_fin = min($total_paginas, $pagina_actual + 2);
                                
                                for ($i = $rango_inicio; $i <= $rango_fin; $i++): 
                                ?>
                                    <a href="?pagina=<?php echo $i; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_empleado=<?php echo $filtro_empleado; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                                       class="btn-page <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($pagina_actual < $total_paginas): ?>
                                    <a href="?pagina=<?php echo $pagina_actual + 1; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_empleado=<?php echo $filtro_empleado; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                                       class="btn-page" title="Página siguiente">
                                        <i class='bx bx-chevron-right'></i>
                                    </a>
                                    <a href="?pagina=<?php echo $total_paginas; ?>&filtro_estado=<?php echo $filtro_estado; ?>&filtro_empleado=<?php echo $filtro_empleado; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                                       class="btn-page" title="Última página">
                                        <i class='bx bx-chevrons-right'></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-search'></i>
                    <p>No se encontraron asignaciones con los filtros seleccionados</p>
                    <p class="text-muted">Intenta cambiar los criterios de búsqueda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="modalDetalle" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; overflow-y: auto;">
        <div style="max-width: 900px; margin: 50px auto; background: white; border-radius: 12px; padding: 30px; position: relative;">
            <button onclick="cerrarModal()" style="position: absolute; top: 20px; right: 20px; background: #fee2e2; color: #991b1b; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem;">
                ×
            </button>
            <div id="contenidoModal">
                </div>
        </div>
    </div>

    <script>
        function verDetalle(idAsignacion) {
            // Mostrar modal
            document.getElementById('modalDetalle').style.display = 'block';
            document.getElementById('contenidoModal').innerHTML = '<p style="text-align: center; padding: 40px;">Cargando...</p>';
            
            // Cargar detalles vía AJAX
            fetch(`ver_detalle.php?id=${idAsignacion}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('contenidoModal').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('contenidoModal').innerHTML = '<p style="color: red;">Error al cargar los detalles</p>';
                });
        }
        
        function cerrarModal() {
            document.getElementById('modalDetalle').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalDetalle').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</body>
</html>