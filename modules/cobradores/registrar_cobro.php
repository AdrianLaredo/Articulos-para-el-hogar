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

function obtenerRangoSemanaActual() {
    $hoy = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    $dia_semana = (int)$hoy->format('w');
    
    if ($dia_semana == 6) {
        return [
            'inicio' => null,
            'fin' => null,
            'es_sabado' => true,
            'mensaje' => 'Los sábados no se pueden registrar cobros. La semana laboral es de Domingo a Viernes.'
        ];
    }
    
    if ($dia_semana == 0) {
        $inicio_semana = clone $hoy;
    } else {
        $inicio_semana = clone $hoy;
        $inicio_semana->modify("-{$dia_semana} days");
    }
    
    $fin_semana = clone $inicio_semana;
    $fin_semana->modify("+5 days");
    
    return [
        'inicio' => $inicio_semana->format('Y-m-d'),
        'fin' => $fin_semana->format('Y-m-d'),
        'es_sabado' => false,
        'inicio_formatted' => $inicio_semana->format('d/m/Y'),
        'fin_formatted' => $fin_semana->format('d/m/Y')
    ];
}

$mensaje = '';
$tipo_mensaje = '';
$recien_registrado = false;

$rango_semana = obtenerRangoSemanaActual();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $_SESSION['mensaje'] = "Error: Token de seguridad inválido";
        $_SESSION['tipo_mensaje'] = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $fecha = $_POST['fecha'];
        $monto_cobrado = floatval($_POST['monto_cobrado']);
        $clientes_visitados = intval($_POST['clientes_visitados']);
        $observaciones = trim($_POST['observaciones']);
        $monto_prestamo = floatval($_POST['monto_prestamo'] ?? 0);
        $motivo_prestamo = trim($_POST['motivo_prestamo'] ?? '');
        $registrado_por = $_SESSION['usuario'];
        
        if ($rango_semana['es_sabado']) {
            $_SESSION['mensaje'] = "Error: " . $rango_semana['mensaje'];
            $_SESSION['tipo_mensaje'] = "error";
        } 
        elseif ($fecha < $rango_semana['inicio'] || $fecha > $rango_semana['fin']) {
            $_SESSION['mensaje'] = "❌ Error: Solo puedes registrar cobros de la semana actual (Domingo a Viernes: " . 
                       $rango_semana['inicio_formatted'] . " - " . 
                       $rango_semana['fin_formatted'] . ")";
            $_SESSION['tipo_mensaje'] = "error";
        } else {
            try {
                $sql_check = "SELECT COUNT(*) FROM Cobros_Diarios WHERE id_empleado = :id_empleado AND fecha = :fecha";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':id_empleado', $id_empleado);
                $stmt_check->bindParam(':fecha', $fecha);
                $stmt_check->execute();
                $existe = $stmt_check->fetchColumn();
                
                if ($existe > 0) {
                    $_SESSION['mensaje'] = "❌ Error: Ya existe un cobro registrado para este empleado en esta fecha";
                    $_SESSION['tipo_mensaje'] = "error";
                } else {
                    if ($monto_prestamo > 0) {
                        $fecha_obj = new DateTime($fecha);
                        $dia_semana = (int)$fecha_obj->format('w');
                        
                        if ($dia_semana == 6) {
                            $_SESSION['mensaje'] = "⚠️ Error: No se pueden registrar préstamos los sábados.";
                            $_SESSION['tipo_mensaje'] = "error";
                            header("Location: registrar_cobro.php");
                            exit;
                        }
                        
                        if ($monto_prestamo > 1500) {
                            $_SESSION['mensaje'] = "⚠️ Error: El préstamo no puede exceder de $1,500.00 por semana";
                            $_SESSION['tipo_mensaje'] = "error";
                            header("Location: registrar_cobro.php");
                            exit;
                        }
                        
                        $sql_semana = "SELECT id_semana, fecha_inicio, fecha_fin FROM Semanas_Cobro 
                                       WHERE :fecha BETWEEN fecha_inicio AND fecha_fin 
                                       AND activa = 1 
                                       LIMIT 1";
                        $stmt_semana = $conn->prepare($sql_semana);
                        $stmt_semana->bindParam(':fecha', $fecha);
                        $stmt_semana->execute();
                        $semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$semana) {
                            $_SESSION['mensaje'] = "⚠️ Error: No se encontró una semana activa";
                            $_SESSION['tipo_mensaje'] = "error";
                            header("Location: registrar_cobro.php");
                            exit;
                        }
                        
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
                        
                        $nuevo_total = $total_prestado + $monto_prestamo;
                        
                        if ($nuevo_total > 1500) {
                            $disponible = 1500 - $total_prestado;
                            if ($disponible > 0) {
                                $_SESSION['mensaje'] = "⚠️ Error: Este empleado ya tiene $" . number_format($total_prestado, 2) . " prestados. Solo puede prestar $" . number_format($disponible, 2) . " más (máximo $1,500.00/semana)";
                            } else {
                                $_SESSION['mensaje'] = "🚫 Error: Límite semanal alcanzado ($1,500.00). No puede solicitar más préstamos esta semana.";
                            }
                            $_SESSION['tipo_mensaje'] = "error";
                            header("Location: registrar_cobro.php");
                            exit;
                        }
                    }
                    
                    $conn->beginTransaction();
                    
                    $fecha_registro = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
                    
                    $sql = "INSERT INTO Cobros_Diarios 
                            (id_empleado, fecha, monto_cobrado, clientes_visitados, observaciones, registrado_por, fecha_registro) 
                            VALUES 
                            (:id_empleado, :fecha, :monto_cobrado, :clientes_visitados, :observaciones, :registrado_por, :fecha_registro)";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_empleado', $id_empleado);
                    $stmt->bindParam(':fecha', $fecha);
                    $stmt->bindParam(':monto_cobrado', $monto_cobrado);
                    $stmt->bindParam(':clientes_visitados', $clientes_visitados);
                    $stmt->bindParam(':observaciones', $observaciones);
                    $stmt->bindParam(':registrado_por', $registrado_por);
                    $stmt->bindParam(':fecha_registro', $fecha_registro);
                    
                    $stmt->execute();
                    
                    if ($monto_prestamo > 0 && isset($semana)) {
                        $sql_prestamo = "INSERT INTO Prestamos_Empleados 
                                        (id_empleado, id_semana, monto, monto_pendiente, fecha_prestamo, motivo, estado) 
                                        VALUES 
                                        (:id_empleado, :id_semana, :monto, :monto_pendiente, :fecha_prestamo, :motivo, 'activo')";
                        
                        $stmt_prestamo = $conn->prepare($sql_prestamo);
                        $stmt_prestamo->bindParam(':id_empleado', $id_empleado);
                        $stmt_prestamo->bindParam(':id_semana', $semana['id_semana']);
                        $stmt_prestamo->bindParam(':monto', $monto_prestamo);
                        $stmt_prestamo->bindParam(':monto_pendiente', $monto_prestamo);
                        $stmt_prestamo->bindParam(':fecha_prestamo', $fecha);
                        $stmt_prestamo->bindParam(':motivo', $motivo_prestamo);
                        
                        $stmt_prestamo->execute();
                    }
                    
                    $conn->commit();
                    
                    $mensaje_final = "✅ Cobro registrado exitosamente";
                    if ($monto_prestamo > 0) {
                        $mensaje_final .= " | Préstamo de $" . number_format($monto_prestamo, 2) . " registrado";
                    }
                    
                    $_SESSION['mensaje'] = $mensaje_final;
                    $_SESSION['tipo_mensaje'] = "success";
                    header("Location: registrar_cobro.php?success=1&t=" . time());
                    exit;
                }
            } catch (PDOException $e) {
                if (isset($conn) && $conn->inTransaction()) {
                    $conn->rollBack();
                }
                $_SESSION['mensaje'] = "❌ Error al registrar: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "error";
            }
        }
    }
    
    header("Location: registrar_cobro.php");
    exit;
}

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
    
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $recien_registrado = true;
    }
}

$query_empleados = "SELECT 
    id_empleado,
    nombre,
    apellido_paterno,
    COALESCE(apellido_materno, '') as apellido_materno,
    (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo,
    rol,
    zona,
    estado
    FROM Empleados 
    WHERE estado = 'activo' 
    ORDER BY nombre, apellido_paterno";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');

$query_recientes_hoy = "
    SELECT 
        cd.*,
        vcd.nombre_empleado,
        vcd.dia_semana,
        vcd.zona,
        COALESCE(pe.monto, 0) as monto_prestamo
    FROM Cobros_Diarios cd
    INNER JOIN Vista_Cobros_Diarios vcd ON cd.id_cobro = vcd.id_cobro
    LEFT JOIN Prestamos_Empleados pe ON cd.id_empleado = pe.id_empleado 
        AND cd.fecha = pe.fecha_prestamo 
        AND pe.estado = 'activo'
    WHERE cd.fecha = :hoy 
    ORDER BY cd.fecha_registro DESC 
    LIMIT 5";
$stmt_recientes_hoy = $conn->prepare($query_recientes_hoy);
$stmt_recientes_hoy->bindValue(':hoy', $hoy);
$stmt_recientes_hoy->execute();
$cobros_hoy = $stmt_recientes_hoy->fetchAll(PDO::FETCH_ASSOC);

if (count($cobros_hoy) == 0) {
    $query_recientes = "
        SELECT 
            cd.*,
            vcd.nombre_empleado,
            vcd.dia_semana,
            vcd.zona,
            COALESCE(pe.monto, 0) as monto_prestamo
        FROM Cobros_Diarios cd
        INNER JOIN Vista_Cobros_Diarios vcd ON cd.id_cobro = vcd.id_cobro
        LEFT JOIN Prestamos_Empleados pe ON cd.id_empleado = pe.id_empleado 
            AND cd.fecha = pe.fecha_prestamo 
            AND pe.estado = 'activo'
        ORDER BY cd.fecha_registro DESC 
        LIMIT 5";
    $stmt_recientes = $conn->query($query_recientes);
    $cobros_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cobros_recientes = $cobros_hoy;
}

$query_total_hoy = "SELECT 
                    COUNT(DISTINCT cd.id_cobro) as total_registros,
                    COALESCE(SUM(cd.monto_cobrado), 0) as total_cobrado,
                    COALESCE(SUM(cd.clientes_visitados), 0) as total_clientes,
                    COALESCE(SUM(pe.monto), 0) as total_prestamos
                    FROM Cobros_Diarios cd
                    LEFT JOIN Prestamos_Empleados pe ON cd.id_empleado = pe.id_empleado 
                        AND cd.fecha = pe.fecha_prestamo 
                        AND pe.estado = 'activo'
                    WHERE cd.fecha = :hoy";
$stmt_total_hoy = $conn->prepare($query_total_hoy);
$stmt_total_hoy->bindValue(':hoy', $hoy);
$stmt_total_hoy->execute();
$totales_hoy = $stmt_total_hoy->fetch(PDO::FETCH_ASSOC);

$total_neto = $totales_hoy['total_cobrado'] - $totales_hoy['total_prestamos'];

$dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$dia_hoy = $dias_semana[date('w')];

$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Registrar Cobro Diario - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-plus-circle'></i> Registrar Cobro Diario</h1>
            <div class="header-actions">
                <a href="ver_cobros.php" class="btn btn-secondary">
                    <i class='bx bx-list-ul'></i> Ver Todos los Cobros
                </a>
            </div>
        </div>

        <?php if ($rango_semana['es_sabado']): ?>
            <div class="mensaje error">
                <i class='bx bx-error-circle'></i>
                <div>
                    <strong>Hoy es Sábado (<?php echo date('d/m/Y'); ?>) - No se pueden registrar cobros</strong><br>
                    <small>La semana laboral es de Domingo a Viernes. Regresa el próximo Domingo para registrar cobros de la nueva semana.</small>
                </div>
            </div>
        <?php else: ?>
            <div class="semana-info">
                <i class='bx bx-calendar-week'></i>
                <div class="semana-info-content">
                    <strong>Semana Actual</strong>
                    <span class="rango-fechas">
                        <?php echo $rango_semana['inicio_formatted']; ?> - <?php echo $rango_semana['fin_formatted']; ?>
                    </span>
                    <span class="dia-actual">
                        Hoy: <strong><?php echo $dia_hoy; ?></strong> (<?php echo date('d/m/Y'); ?>) - 
                        Hora: <strong id="hora-servidor"><?php echo date('H:i:s'); ?></strong>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <div><?php echo $mensaje; ?></div>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de Resumen con nuevo orden -->
        <div class="summary-cards">
            <!-- 1. Registros Hoy -->
            <div class="summary-card blue">
                <div class="card-icon">
                    <i class='bx bx-file'></i>
                </div>
                <div class="card-content">
                    <h3>Registros Hoy</h3>
                    <p class="amount"><?php echo number_format($totales_hoy['total_registros']); ?></p>
                    <small class="subtitle"><?php echo $dia_hoy . ', ' . date('d/m/Y'); ?></small>
                </div>
            </div>

            <!-- 2. Total Cobrado Hoy -->
            <div class="summary-card green">
                <div class="card-icon">
                    <i class='bx bx-dollar-circle'></i>
                </div>
                <div class="card-content">
                    <h3>Total Cobrado Hoy</h3>
                    <p class="amount">$<?php echo number_format($totales_hoy['total_cobrado'], 2); ?></p>
                </div>
            </div>

            <!-- 3. Total Préstamos -->
            <div class="summary-card orange">
                <div class="card-icon">
                    <i class='bx bx-wallet'></i>
                </div>
                <div class="card-content">
                    <h3>Total Préstamos</h3>
                    <p class="amount">$<?php echo number_format($totales_hoy['total_prestamos'], 2); ?></p>
                </div>
            </div>

            <!-- 4. Total Neto -->
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

            <!-- 5. Clientes Visitados -->
            <div class="summary-card purple">
                <div class="card-icon">
                    <i class='bx bx-group'></i>
                </div>
                <div class="card-content">
                    <h3>Clientes Visitados</h3>
                    <p class="amount"><?php echo number_format($totales_hoy['total_clientes']); ?></p>
                </div>
            </div>
        </div>

        <?php if (!$rango_semana['es_sabado']): ?>
        <div class="card">
            <h2><i class='bx bx-edit'></i> Registrar Nuevo Cobro</h2>
            
            <form id="formCobro" method="POST" action="registrar_cobro.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="registrar">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_empleado"><i class='bx bx-user'></i> Empleado <span class="required">*</span></label>
                        <select name="id_empleado" id="id_empleado" required>
                            <option value="">Seleccionar empleado</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo $empleado['id_empleado']; ?>" 
                                        data-zona="<?php echo $empleado['zona']; ?>"
                                        data-rol="<?php echo $empleado['rol']; ?>">
                                    <?php echo htmlspecialchars($empleado['nombre_completo']); ?> - 
                                    <?php echo $empleado['rol']; ?> (<?php echo $empleado['zona']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="info-empleado" class="form-helper"></small>
                    </div>

                    <div class="form-group">
                        <label for="fecha"><i class='bx bx-calendar'></i> Fecha <span class="required">*</span></label>
                        <input 
                            type="date" 
                            name="fecha" 
                            id="fecha" 
                            required 
                            min="<?php echo $rango_semana['inicio']; ?>" 
                            max="<?php echo $rango_semana['fin']; ?>"
                            value="<?php echo $hoy; ?>">
                        <small id="info-dia" class="form-helper"></small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monto_cobrado"><i class='bx bx-dollar'></i> Monto Cobrado <span class="required">*</span></label>
                        <input 
                            type="number" 
                            name="monto_cobrado" 
                            id="monto_cobrado" 
                            step="0.01" 
                            min="0" 
                            required 
                            placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="clientes_visitados"><i class='bx bx-user-check'></i> Clientes Visitados <span class="required">*</span></label>
                        <input 
                            type="number" 
                            name="clientes_visitados" 
                            id="clientes_visitados" 
                            min="0" 
                            required 
                            placeholder="0">
                    </div>
                </div>

                <!-- SECCIÓN DE PRÉSTAMOS -->
                <div class="prestamo-section">
                    <div class="prestamo-header" onclick="togglePrestamoSection()">
                        <i class='bx bx-wallet'></i>
                        <h3>¿Desea registrar un préstamo? (Opcional)</h3>
                        <i class='bx bx-chevron-down' id="prestamo-toggle-icon"></i>
                    </div>
                    
                    <div class="prestamo-content" id="prestamo-content" style="display: none;">
                        <div class="alert-info-box" style="margin-bottom: 15px;">
                            <i class='bx bx-info-circle'></i>
                            <div>
                                <strong>Límite de Préstamos Semanal</strong>
                                <p>Máximo $1,500.00 por semana por empleado. El sistema validará automáticamente préstamos previos.</p>
                            </div>
                        </div>

                        <div id="limite-info-prestamo" class="limite-info" style="display: none;">
                            <i class='bx bx-error-circle'></i>
                            <span id="limite-texto-prestamo"></span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="monto_prestamo"><i class='bx bx-money'></i> Monto del Préstamo</label>
                                <input 
                                    type="number" 
                                    name="monto_prestamo" 
                                    id="monto_prestamo" 
                                    step="0.01" 
                                    min="0"
                                    max="1500" 
                                    placeholder="0.00">
                                <small class="form-helper">Dejar en 0 si no hay préstamo</small>
                            </div>

                            <div class="form-group">
                                <label for="motivo_prestamo"><i class='bx bx-note'></i> Motivo del Préstamo</label>
                                <input 
                                    type="text"
                                    name="motivo_prestamo" 
                                    id="motivo_prestamo" 
                                    maxlength="200"
                                    placeholder="Ej: Emergencia médica, pago de servicios, etc.">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones"><i class='bx bx-note'></i> Observaciones del Cobro</label>
                    <textarea 
                        name="observaciones" 
                        id="observaciones" 
                        rows="3" 
                        maxlength="500"
                        placeholder="Opcional: Agrega observaciones sobre este cobro"></textarea>
                    <span class="char-counter" id="counter-observaciones">0/500</span>
                </div>

                <div class="alert-info-box">
                    <i class='bx bx-time-five'></i>
                    <div>
                        <strong>Hora de Registro</strong>
                        <p>Hora actual de México (CDMX): <strong id="hora-actual"><?php echo date('H:i:s'); ?></strong></p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Registrar Cobro
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Limpiar Formulario
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>
                <i class='bx bx-history'></i> 
                Últimos Cobros de Hoy
                <?php if (count($cobros_hoy) > 0): ?>
                    <span class="badge-hoy">HOY</span>
                <?php else: ?>
                    <span class="badge-info">RECIENTES</span>
                <?php endif; ?>
            </h2>

            <?php if (count($cobros_recientes) > 0): ?>
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
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cobros_recientes as $cobro): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($cobro['fecha'])); ?></td>
                                    <td>
                                        <span class="badge-info">
                                            <?php echo '●' . substr($cobro['dia_semana'], 0, 3); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($cobro['nombre_empleado']); ?></td>
                                    <td><span class="zona-badge"><?php echo $cobro['zona']; ?></span></td>
                                    <td class="text-bold text-success">$<?php echo number_format($cobro['monto_cobrado'], 2); ?></td>
                                    <td class="text-bold <?php echo $cobro['monto_prestamo'] > 0 ? 'text-warning' : 'text-muted'; ?>">
                                        <?php if ($cobro['monto_prestamo'] > 0): ?>
                                            $<?php echo number_format($cobro['monto_prestamo'], 2); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $cobro['clientes_visitados']; ?></td>
                                    <td>
                                        <?php if ($cobro['observaciones']): ?>
                                            <small><?php echo htmlspecialchars(substr($cobro['observaciones'], 0, 30)); ?><?php echo strlen($cobro['observaciones']) > 30 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo date('H:i', strtotime($cobro['fecha_registro'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-calendar-x'></i>
                    <h3>No hay cobros registrados hoy</h3>
                    <p>Los cobros que registres hoy aparecerán aquí</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
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

        function togglePrestamoSection() {
            const content = document.getElementById('prestamo-content');
            const icon = document.getElementById('prestamo-toggle-icon');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.classList.remove('bx-chevron-down');
                icon.classList.add('bx-chevron-up');
            } else {
                content.style.display = 'none';
                icon.classList.remove('bx-chevron-up');
                icon.classList.add('bx-chevron-down');
            }
        }

        function actualizarLimitePrestamo() {
            const empleadoId = document.getElementById('id_empleado').value;
            const fechaInput = document.getElementById('fecha').value;
            const limiteInfo = document.getElementById('limite-info-prestamo');
            const limiteTexto = document.getElementById('limite-texto-prestamo');
            const montoPrestamo = document.getElementById('monto_prestamo');
            
            if (!empleadoId || !fechaInput) {
                limiteInfo.style.display = 'none';
                return;
            }
            
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
                limiteInfo.style.display = 'flex';
                if (disponible > 0) {
                    limiteTexto.textContent = `Ya prestado esta semana: $${prestado.toFixed(2)} | Disponible: $${disponible.toFixed(2)}`;
                    limiteInfo.style.background = '#FFF3E0';
                    limiteInfo.style.borderColor = '#FF9800';
                    limiteTexto.style.color = '#E65100';
                } else {
                    limiteTexto.textContent = `⚠️ Este empleado ya alcanzó su límite semanal ($1,500.00)`;
                    limiteInfo.style.background = '#FEE2E2';
                    limiteInfo.style.borderColor = '#EF4444';
                    limiteTexto.style.color = '#991B1B';
                }
                montoPrestamo.max = disponible > 0 ? disponible : 0;
            } else {
                limiteInfo.style.display = 'flex';
                limiteTexto.textContent = `Disponible esta semana: $1,500.00`;
                limiteInfo.style.background = '#E8F5E9';
                limiteInfo.style.borderColor = '#4CAF50';
                limiteTexto.style.color = '#1B5E20';
                montoPrestamo.max = 1500;
            }
        }

        function actualizarHora() {
            const now = new Date();
            const horas = String(now.getHours()).padStart(2, '0');
            const minutos = String(now.getMinutes()).padStart(2, '0');
            const segundos = String(now.getSeconds()).padStart(2, '0');
            const horaActual = `${horas}:${minutos}:${segundos}`;
            
            const horaServidor = document.getElementById('hora-servidor');
            const horaActualSpan = document.getElementById('hora-actual');
            
            if (horaServidor) horaServidor.textContent = horaActual;
            if (horaActualSpan) horaActualSpan.textContent = horaActual;
        }

        setInterval(actualizarHora, 1000);

        document.addEventListener('DOMContentLoaded', function() {
            const selectEmpleado = document.getElementById('id_empleado');
            const infoEmpleado = document.getElementById('info-empleado');
            const inputFecha = document.getElementById('fecha');
            const infoDia = document.getElementById('info-dia');
            
            if (selectEmpleado && infoEmpleado) {
                selectEmpleado.addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    if (option.value) {
                        const rol = option.dataset.rol;
                        const zona = option.dataset.zona;
                        infoEmpleado.textContent = `${rol} - Zona: ${zona}`;
                        infoEmpleado.style.color = '#10B981';
                        actualizarLimitePrestamo();
                    } else {
                        infoEmpleado.textContent = '';
                    }
                });
            }

            function mostrarDia() {
                if (!inputFecha.value) return;
                
                const fecha = new Date(inputFecha.value + 'T00:00:00');
                const dia = fecha.getDay();
                const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                
                infoDia.textContent = dias[dia] + ' - Fecha válida';
                infoDia.style.color = '#10B981';
                
                actualizarLimitePrestamo();
            }
            
            inputFecha.addEventListener('change', mostrarDia);
            mostrarDia();

            const observaciones = document.getElementById('observaciones');
            const counterObservaciones = document.getElementById('counter-observaciones');
            
            if (observaciones && counterObservaciones) {
                observaciones.addEventListener('input', function() {
                    const length = this.value.length;
                    counterObservaciones.textContent = `${length}/500`;
                    
                    counterObservaciones.classList.remove('warning', 'limit');
                    if (length >= 500) {
                        counterObservaciones.classList.add('limit');
                    } else if (length >= 400) {
                        counterObservaciones.classList.add('warning');
                    }
                });
            }

            const montoPrestamo = document.getElementById('monto_prestamo');
            if (montoPrestamo) {
                montoPrestamo.addEventListener('input', actualizarLimitePrestamo);
            }

            const formCobro = document.getElementById('formCobro');
            if (formCobro) {
                formCobro.addEventListener('reset', function() {
                    setTimeout(() => {
                        if (infoEmpleado) infoEmpleado.textContent = '';
                        if (inputFecha && infoDia) mostrarDia();
                        if (counterObservaciones) {
                            counterObservaciones.textContent = '0/500';
                            counterObservaciones.classList.remove('warning', 'limit');
                        }
                        document.getElementById('limite-info-prestamo').style.display = 'none';
                        document.getElementById('prestamo-content').style.display = 'none';
                        document.getElementById('prestamo-toggle-icon').classList.remove('bx-chevron-up');
                        document.getElementById('prestamo-toggle-icon').classList.add('bx-chevron-down');
                    }, 10);
                });
            }

            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);

                <?php if ($recien_registrado): ?>
                setTimeout(() => {
                    mensaje.style.opacity = '0';
                    mensaje.style.transition = 'opacity 0.5s';
                    setTimeout(() => mensaje.remove(), 500);
                }, 5000);
                <?php endif; ?>
            }
        });
    </script>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-helper {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .required {
            color: var(--danger-color);
        }

        .semana-info {
            background: var(--white);
            color: var(--text-dark);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .semana-info i {
            font-size: 24px;
            color: var(--primary-color);
            background: #E3F2FD;
            padding: 12px;
            border-radius: 8px;
        }

        .semana-info-content {
            flex: 1;
        }

        .semana-info strong {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rango-fechas {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            display: block;
            margin-bottom: 4px;
        }

        .dia-actual {
            font-size: 12px;
            color: var(--text-muted);
        }

        .prestamo-section {
            margin: 25px 0;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .prestamo-header {
            background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }

        .prestamo-header:hover {
            background: linear-gradient(135deg, #E9ECEF 0%, #DEE2E6 100%);
            border-bottom-color: var(--primary-color);
        }

        .prestamo-header i:first-child {
            font-size: 22px;
            color: var(--primary-color);
            background: var(--white);
            padding: 8px;
            border-radius: 6px;
        }

        .prestamo-header h3 {
            flex: 1;
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .prestamo-header i:last-child {
            font-size: 22px;
            color: var(--text-muted);
            transition: transform 0.3s;
        }

        .prestamo-content {
            padding: 20px;
            background: var(--white);
        }

        .limite-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border-radius: 6px;
            border: 2px solid;
            margin-bottom: 15px;
            font-size: 13px;
            font-weight: 500;
        }

        .limite-info i {
            font-size: 18px;
        }

        .badge-hoy {
            display: inline-block;
            padding: 4px 10px;
            background: var(--success-color);
            color: var(--white);
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
        }

        .badge-info {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            background: #FFF3E0;
            color: var(--warning-color);
            text-transform: uppercase;
        }

        .alert-info-box {
            background: #E3F2FD;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-info-box i {
            font-size: 24px;
            color: #2196F3;
        }

        .alert-info-box div {
            flex: 1;
        }

        .alert-info-box strong {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            color: #1976D2;
        }

        .alert-info-box p {
            margin: 0;
            font-size: 13px;
            color: #424242;
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

        .text-success {
            color: var(--success-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }
    </style>
    <script src="assets/js/script_navegacion_dashboard.js"></script>
</body>
</html>