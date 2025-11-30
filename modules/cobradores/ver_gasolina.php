<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Filtros
$filtro_empleado = isset($_GET['empleado']) ? $_GET['empleado'] : '';
$filtro_semana = isset($_GET['semana']) ? $_GET['semana'] : '';

// Obtener todos los empleados activos
$query_empleados = "SELECT * FROM Empleados WHERE estado = 'activo' ORDER BY nombre";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Obtener semanas
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Construir query con filtros
$where = ["1=1"];
$params = [];

if ($filtro_empleado) {
    $where[] = "g.id_empleado = :empleado";
    $params[':empleado'] = $filtro_empleado;
}
if ($filtro_semana) {
    $where[] = "g.id_semana = :semana";
    $params[':semana'] = $filtro_semana;
}

$where_clause = implode(" AND ", $where);

// Obtener gastos
$query_gastos = "SELECT * FROM Vista_Gasolina_Semanal WHERE $where_clause ORDER BY fecha DESC";
$stmt_gastos = $conn->prepare($query_gastos);
foreach ($params as $key => $value) {
    $stmt_gastos->bindValue($key, $value);
}
$stmt_gastos->execute();
$gastos = $stmt_gastos->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$query_totales = "
    SELECT 
        COUNT(*) as total_registros,
        COALESCE(SUM(g.monto), 0) as total_monto
    FROM Detalle_Gasolina_Semanal g
    WHERE $where_clause
";
$stmt_totales = $conn->prepare($query_totales);
foreach ($params as $key => $value) {
    $stmt_totales->bindValue($key, $value);
}
$stmt_totales->execute();
$totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Gastos de Gasolina - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-receipt'></i> Gastos de Gasolina</h1>
            <div class="header-actions">
                <a href="registrar_gasolina.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Registrar Gasolina
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card filtros-card">
            <h2><i class='bx bx-filter'></i> Filtros</h2>
            <form method="GET" action="" class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="empleado"><i class='bx bx-user'></i> Empleado</label>
                        <select name="empleado" id="empleado" class="form-control">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>" <?php echo $filtro_empleado == $emp['id_empleado'] ? 'selected' : ''; ?>>
                                    <?php echo $emp['nombre'] . ' ' . $emp['apellido_paterno']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="semana"><i class='bx bx-calendar-week'></i> Semana</label>
                        <select name="semana" id="semana" class="form-control">
                            <option value="">Todas las semanas</option>
                            <?php foreach ($semanas as $sem): ?>
                                <option value="<?php echo $sem['id_semana']; ?>" <?php echo $filtro_semana == $sem['id_semana'] ? 'selected' : ''; ?>>
                                    <?php echo $sem['mes']; ?> - Semana <?php echo $sem['numero_semana']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-search'></i> Filtrar
                    </button>
                    <?php if ($filtro_empleado || $filtro_semana): ?>
                        <a href="ver_gasolina.php" class="btn btn-secondary">
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

            <div class="summary-card green">
                <div class="card-icon">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Total Gastado</h3>
                    <p class="amount">$<?php echo number_format($totales['total_monto'], 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de Gastos -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Listado de Gastos (<?php echo count($gastos); ?>)</h2>

            <?php if (count($gastos) > 0): ?>
                <div class="table-container">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>Fecha (Viernes)</th>
                                <th>Empleado</th>
                                <th>Rol</th>
                                <th>Zona</th>
                                <th>Semana</th>
                                <th>Monto en Efectivo</th>
                                <th>Observaciones</th>
                                <th>Registrado Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastos as $gasto): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($gasto['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($gasto['nombre_empleado']); ?></td>
                                    <td>
                                        <span class="rol-badge <?php echo $gasto['rol_empleado']; ?>">
                                            <?php echo ucfirst($gasto['rol_empleado']); ?>
                                        </span>
                                    </td>
                                    <td><span class="zona-badge"><?php echo $gasto['zona']; ?></span></td>
                                    <td>
                                        <span class="badge-info">
                                            <?php echo $gasto['mes']; ?> S<?php echo $gasto['numero_semana']; ?>
                                        </span>
                                    </td>
                                    <td class="text-bold">$<?php echo number_format($gasto['monto'], 2); ?></td>
                                    <td>
                                        <?php if ($gasto['observaciones']): ?>
                                            <small><?php echo htmlspecialchars(substr($gasto['observaciones'], 0, 50)); ?><?php echo strlen($gasto['observaciones']) > 50 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo $gasto['registrado_por']; ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-search-alt' style="font-size: 48px;"></i>
                    <p>No hay gastos de gasolina registrados con los filtros seleccionados</p>
                    <a href="registrar_gasolina.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Registrar Primer Gasto
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
        .badge-info {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #FFF3E0;
            color: #F57C00;
        }
    </style>
</body>
</html>