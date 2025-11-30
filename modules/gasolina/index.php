<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

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

$mensaje = '';
$tipo_mensaje = '';

// CREAR - Registrar nueva carga
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $datos = [
            'tipo_carga' => $_POST['tipo_carga'],
            'registrado_por' => $_SESSION['usuario'],
            'observaciones' => trim($_POST['observaciones'] ?? '')
        ];
        
        if ($_POST['tipo_carga'] === 'litros') {
            $datos['id_vehiculo'] = intval($_POST['id_vehiculo']);
            $datos['placas'] = trim($_POST['placas']);
            $datos['litros'] = floatval($_POST['litros']);
            $datos['precio_litro'] = floatval($_POST['precio_litro']);
            
            // Validaciones
            if ($datos['id_vehiculo'] <= 0) {
                $mensaje = "Error: Debe seleccionar un vehículo válido";
                $tipo_mensaje = "error";
            } elseif ($datos['litros'] <= 0) {
                $mensaje = "Error: La cantidad de litros debe ser mayor a 0";
                $tipo_mensaje = "error";
            } elseif ($datos['precio_litro'] <= 0) {
                $mensaje = "Error: El precio por litro debe ser mayor a 0";
                $tipo_mensaje = "error";
            } else {
                $resultado = registrarGasolina($datos);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
            }
        } else {
            $datos['id_empleado'] = intval($_POST['id_empleado']);
            $datos['monto_efectivo'] = floatval($_POST['monto_efectivo']);
            
            // Validaciones
            if ($datos['id_empleado'] <= 0) {
                $mensaje = "Error: Debe seleccionar un empleado válido";
                $tipo_mensaje = "error";
            } elseif ($datos['monto_efectivo'] <= 0) {
                $mensaje = "Error: El monto de efectivo debe ser mayor a 0";
                $tipo_mensaje = "error";
            } else {
                $resultado = registrarGasolina($datos);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
            }
        }
    }
}

// ELIMINAR - Borrar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_registro = intval($_POST['id_registro']);
        $resultado = eliminarRegistro($id_registro);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
    }
}

// LEER - Obtener historial con filtros y paginación
$filtro_tipo = isset($_GET['filtro_tipo']) ? $_GET['filtro_tipo'] : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Configuración de paginación
$registros_por_pagina = 10; // Cantidad de registros por página
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros para las consultas
$filtros = [
    'tipo' => $filtro_tipo,
    'fecha_inicio' => $filtro_fecha_inicio,
    'fecha_fin' => $filtro_fecha_fin
];

// Obtener total de registros (para paginación)
$total_registros_paginacion = contarRegistros($filtros);
$total_paginas = ceil($total_registros_paginacion / $registros_por_pagina);

// Obtener registros paginados
$filtros_paginados = $filtros;
$filtros_paginados['limit'] = $registros_por_pagina;
$filtros_paginados['offset'] = $offset;
$registros = obtenerHistorial($filtros_paginados);

// Obtener estadísticas (sin paginación - datos completos)
$estadisticas = obtenerEstadisticas($filtros);
$total_registros = $estadisticas['total_registros'];
$total_litros = $estadisticas['total_litros'];
$total_efectivo = $estadisticas['total_efectivo'];
$total_gasto = $estadisticas['total_gasto'];

// Obtener listas para formularios
$vehiculos = obtenerVehiculos();
// Obtener vehículos directamente (solución que funciona)
try {
    $sql_vehiculos = "SELECT 
                        id_vehiculo,
                        placas, 
                        marca,
                        modelo,
                        color,
                        (marca || ' ' || modelo || ' (' || placas || ')') as descripcion 
                    FROM Vehiculos 
                    ORDER BY marca, modelo";
    
    $stmt_veh = $conn->query($sql_vehiculos);
    $vehiculos = $stmt_veh->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error al obtener vehículos: " . $e->getMessage());
    $vehiculos = [];
}
$empleados = obtenerEmpleados();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Gasolina</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/gasolina.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bxs-gas-pump'></i> Módulo de Gasolina</h1>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-receipt'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_registros; ?></h3>
                    <p>Total Registros</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class='bx bx-tint'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_litros, 2); ?> L</h3>
                    <p>Total Litros</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class='bx bx-money'></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_efectivo, 2); ?></h3>
                    <p>Total Efectivo</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_gasto, 2); ?></h3>
                    <p>Gasto Total</p>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="nuevo">
                    <i class='bx bx-plus-circle'></i> Nuevo Registro
                </button>
                <button class="tab-btn" data-tab="historial">
                    <i class='bx bx-history'></i> Historial
                </button>
            </div>

            <!-- TAB: Nuevo Registro -->
            <div class="tab-content active" id="nuevo">
                <div class="card">
                    <h2><i class='bx bx-plus-circle'></i> Registrar Carga de Gasolina</h2>
                    
                    <!-- Selector de Tipo -->
                    <div class="tipo-selector">
                        <label class="tipo-option">
                            <input type="radio" name="tipoCarga" value="litros" checked>
                            <div class="tipo-card">
                                <i class='bx bxs-car'></i>
                                <h3>Por Litros</h3>
                                <p>Asignar a vehículo</p>
                            </div>
                        </label>
                        <label class="tipo-option">
                            <input type="radio" name="tipoCarga" value="efectivo">
                            <div class="tipo-card">
                                <i class='bx bx-money'></i>
                                <h3>Por Efectivo</h3>
                                <p>Asignar a empleado</p>
                            </div>
                        </label>
                    </div>

                    <!-- Formulario -->
                    <form method="POST" action="" class="form-gasolina" id="formGasolina">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="crear">
                        <input type="hidden" name="tipo_carga" id="tipoCargaInput" value="litros">

                        <!-- Sección Litros -->
                        <div class="seccion-form" id="seccionLitros">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="vehiculo">
                                        <i class='bx bx-car'></i> Vehículo *
                                    </label>
                                    <select id="vehiculo" name="id_vehiculo" required>
                                        <option value="">Seleccione un vehículo...</option>
                                        <?php foreach ($vehiculos as $veh): ?>
                                            <option value="<?php echo $veh['id_vehiculo']; ?>"
                                                    data-placas="<?php echo htmlspecialchars($veh['placas']); ?>"
                                                    data-color="<?php echo htmlspecialchars($veh['color']); ?>"
                                                    data-modelo="<?php echo htmlspecialchars($veh['modelo']); ?>"
                                                    data-marca="<?php echo htmlspecialchars($veh['marca']); ?>">
                                                <?php echo htmlspecialchars($veh['descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="placas">
                                        <i class='bx bx-id-card'></i> Placas
                                    </label>
                                    <input type="text" id="placas" name="placas" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Color</label>
                                    <input type="text" id="color" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Modelo</label>
                                    <input type="text" id="modelo" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Marca</label>
                                    <input type="text" id="marca" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="litros">
                                        <i class='bx bx-tint'></i> Litros *
                                    </label>
                                    <input type="number" id="litros" name="litros" 
                                           step="0.01" min="0.01" placeholder="0.00" required>
                                </div>

                                <div class="form-group">
                                    <label for="precioLitro">
                                        <i class='bx bx-dollar'></i> Precio/Litro *
                                    </label>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="number" id="precioLitro" name="precio_litro"
                                               step="0.01" min="0.01" placeholder="0.00"
                                               value="23.65" required readonly>
                                        <button type="button" id="btnEditarPrecio" class="btn-editar-precio" 
                                                title="Cambiar precio">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Total</label>
                                    <div class="total-display">
                                        $<span id="totalLitros">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección Efectivo -->
                        <div class="seccion-form" id="seccionEfectivo" style="display: none;">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="empleado">
                                        <i class='bx bx-user'></i> Empleado *
                                    </label>
                                    <select id="empleado" name="id_empleado">
                                        <option value="">Seleccione un empleado...</option>
                                        <?php foreach ($empleados as $e): ?>
                                            <option value="<?php echo $e['id_empleado']; ?>">
                                                <?php echo htmlspecialchars($e['nombre'] . ' ' . $e['apellido_paterno'] . ' ' . $e['apellido_materno']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label for="montoEfectivo">
                                        <i class='bx bx-money'></i> Monto de Efectivo *
                                    </label>
                                    <input type="number" id="montoEfectivo" name="monto_efectivo" 
                                           step="0.01" min="0.01" placeholder="0.00" class="input-large">
                                </div>

                                <div class="form-group full-width">
                                    <label>Total Efectivo</label>
                                    <div class="total-display total-efectivo">
                                        $<span id="totalEfectivo">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="form-group full-width">
                            <label for="observaciones">
                                <i class='bx bx-note'></i> Observaciones
                            </label>
                            <textarea id="observaciones" name="observaciones" rows="3" 
                                      placeholder="Notas adicionales..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-save'></i> Guardar Registro
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class='bx bx-reset'></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB: Historial -->
            <div class="tab-content" id="historial">
                <div class="card">
                    <h2><i class='bx bx-history'></i> Historial de Registros</h2>
                    
                    <!-- Filtros -->
                    <div class="filtros-box">
                        <div class="filtro-item">
                            <label>Fecha Inicio</label>
                            <input type="date" id="filtroFechaInicio" value="<?php echo $filtro_fecha_inicio; ?>">
                        </div>
                        <div class="filtro-item">
                            <label>Fecha Fin</label>
                            <input type="date" id="filtroFechaFin" value="<?php echo $filtro_fecha_fin; ?>">
                        </div>
                        <div class="filtro-item">
                            <label>Tipo</label>
                            <select id="filtroTipo">
                                <option value="">Todos</option>
                                <option value="litros" <?php echo $filtro_tipo === 'litros' ? 'selected' : ''; ?>>Litros</option>
                                <option value="efectivo" <?php echo $filtro_tipo === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="aplicarFiltros()">
                            <i class='bx bx-filter'></i> Filtrar
                        </button>
                    </div>

                    <!-- Búsqueda -->
                    <div class="search-box">
                        <i class='bx bx-search'></i>
                        <input type="text" id="searchInput" 
                               placeholder="Buscar por vehículo, empleado, placas..." 
                               autocomplete="off">
                        <button type="button" class="clear-search" id="clearSearch">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>

                    <!-- Grid de Registros -->
                    <?php if (count($registros) > 0): ?>
                        <div class="registros-grid" id="registrosGrid">
                            <?php foreach ($registros as $reg): ?>
                                <div class="registro-card" data-registro='<?php echo json_encode([
                                    "descripcion" => $reg['descripcion'],
                                    "placas" => $reg['placas'] ?? '',
                                    "tipo" => $reg['tipo_carga']
                                ]); ?>'>
                                    <div class="registro-header">
                                        <span class="tipo-badge <?php echo $reg['tipo_carga']; ?>">
                                            <i class='bx <?php echo $reg['tipo_carga'] === 'litros' ? 'bx-tint' : 'bx-money'; ?>'></i>
                                            <?php echo ucfirst($reg['tipo_carga']); ?>
                                        </span>
                                        <span class="fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($reg['fecha_registro'])); ?>
                                        </span>
                                    </div>
                                    <div class="registro-body">
                                        <h3><?php echo htmlspecialchars($reg['descripcion']); ?></h3>
                                        <?php if ($reg['tipo_carga'] === 'litros'): ?>
                                            <div class="registro-detalles">
                                                <div class="detalle-item">
                                                    <i class='bx bx-tint'></i>
                                                    <span><?php echo number_format($reg['litros'], 2); ?> L</span>
                                                </div>
                                                <div class="detalle-item">
                                                    <i class='bx bx-dollar'></i>
                                                    <span>$<?php echo number_format($reg['precio_litro'], 2); ?>/L</span>
                                                </div>
                                                <div class="detalle-item total">
                                                    <i class='bx bx-calculator'></i>
                                                    <span>$<?php echo number_format($reg['total_gasto'], 2); ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="registro-detalles">
                                                <div class="detalle-item total">
                                                    <i class='bx bx-money'></i>
                                                    <span>$<?php echo number_format($reg['monto_efectivo'], 2); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="registro-footer">
                                            <span class="registrado-por">
                                                <i class='bx bx-user'></i>
                                                <?php echo htmlspecialchars($reg['registrado_por']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="registro-acciones">
                                        <button onclick="verDetalle(<?php echo $reg['id_registro']; ?>)" 
                                                class="btn-action btn-view" title="Ver detalle">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        <form method="POST" action="" style="display: inline;"
                                              onsubmit="return confirm('¿Eliminar este registro?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id_registro" value="<?php echo $reg['id_registro']; ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Eliminar">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="no-results" id="noResults" style="display: none;">
                            <i class='bx bx-search-alt'></i>
                            <p>No se encontraron registros</p>
                        </div>

                        <!-- Paginación -->
                        <?php if ($total_paginas > 1): ?>
                            <div class="pagination">
                                <div class="pagination-info">
                                    Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $registros_por_pagina, $total_registros_paginacion); ?> de <?php echo $total_registros_paginacion; ?> registros
                                </div>
                                <div class="pagination-buttons">
                                    <?php if ($pagina_actual > 1): ?>
                                        <a href="?pagina=1&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&fecha_inicio=<?php echo urlencode($filtro_fecha_inicio); ?>&fecha_fin=<?php echo urlencode($filtro_fecha_fin); ?>" 
                                           class="pagination-btn" title="Primera página">
                                            <i class='bx bx-chevrons-left'></i>
                                        </a>
                                        <a href="?pagina=<?php echo $pagina_actual - 1; ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&fecha_inicio=<?php echo urlencode($filtro_fecha_inicio); ?>&fecha_fin=<?php echo urlencode($filtro_fecha_fin); ?>" 
                                           class="pagination-btn" title="Página anterior">
                                            <i class='bx bx-chevron-left'></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $rango_inicio = max(1, $pagina_actual - 2);
                                    $rango_fin = min($total_paginas, $pagina_actual + 2);
                                    
                                    for ($i = $rango_inicio; $i <= $rango_fin; $i++): 
                                    ?>
                                        <a href="?pagina=<?php echo $i; ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&fecha_inicio=<?php echo urlencode($filtro_fecha_inicio); ?>&fecha_fin=<?php echo urlencode($filtro_fecha_fin); ?>" 
                                           class="pagination-btn <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($pagina_actual < $total_paginas): ?>
                                        <a href="?pagina=<?php echo $pagina_actual + 1; ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&fecha_inicio=<?php echo urlencode($filtro_fecha_inicio); ?>&fecha_fin=<?php echo urlencode($filtro_fecha_fin); ?>" 
                                           class="pagination-btn" title="Página siguiente">
                                            <i class='bx bx-chevron-right'></i>
                                        </a>
                                        <a href="?pagina=<?php echo $total_paginas; ?>&filtro_tipo=<?php echo urlencode($filtro_tipo); ?>&fecha_inicio=<?php echo urlencode($filtro_fecha_inicio); ?>&fecha_fin=<?php echo urlencode($filtro_fecha_fin); ?>" 
                                           class="pagination-btn" title="Última página">
                                            <i class='bx bx-chevrons-right'></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-receipt'></i>
                            <p>No hay registros disponibles</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/gasolina.js"></script>
</body>
</html>