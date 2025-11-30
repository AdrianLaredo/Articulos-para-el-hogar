<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';
date_default_timezone_set('America/Mexico_City');

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

// REGISTRAR PAGO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $tipo_pago = $_POST['tipo_pago'];
        $id_semana = $_POST['id_semana'];
        $sueldo_fijo = floatval($_POST['sueldo_fijo']);
        $gasolina = floatval($_POST['gasolina']);
        $observaciones = trim($_POST['observaciones']);
        $registrado_por = $_SESSION['usuario'];
        
        try {
            if ($tipo_pago === 'empleado') {
                $id_empleado_original = $_POST['id_empleado'];
                
                $es_admin = $id_empleado_original >= 10000;
                
                if ($es_admin) {
                    $id_usuario = $id_empleado_original - 10000;
                    
                    $sql_admin = "SELECT 
                        (nombre || ' ' || apellido_paterno || ' ' || apellido_materno) as nombre_completo
                        FROM Usuarios 
                        WHERE id = :id_usuario";
                    $stmt_admin = $conn->prepare($sql_admin);
                    $stmt_admin->bindParam(':id_usuario', $id_usuario);
                    $stmt_admin->execute();
                    $admin_data = $stmt_admin->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$admin_data) {
                        $mensaje = "Error: Administrador no encontrado";
                        $tipo_mensaje = "error";
                    } else {
                        $sql_check = "SELECT COUNT(*) FROM Pagos_Sueldos_Fijos 
                                     WHERE id_semana = :id_semana
                                     AND observaciones LIKE :observaciones_buscar";
                        $stmt_check = $conn->prepare($sql_check);
                        $stmt_check->bindParam(':id_semana', $id_semana);
                        $obs_buscar = "ADMIN ID:" . $id_usuario . "%";
                        $stmt_check->bindParam(':observaciones_buscar', $obs_buscar);
                        $stmt_check->execute();
                        $existe = $stmt_check->fetchColumn();
                        
                        if ($existe > 0) {
                            $mensaje = "Error: Ya existe un pago registrado para este administrador en esta semana";
                            $tipo_mensaje = "error";
                        } else {
                            $total_pagar = $sueldo_fijo + $gasolina;
                            $verificacion = verificarFondosSuficientes($conn, $id_semana, $total_pagar);
                            
                            if (!$verificacion['suficiente']) {
                                $mensaje = "❌ " . $verificacion['mensaje'] . 
                                          "<br><strong>Saldo disponible:</strong> $" . number_format($verificacion['saldo_restante'], 2) .
                                          "<br><strong>Monto a pagar:</strong> $" . number_format($total_pagar, 2);
                                $tipo_mensaje = "error";
                            } else {
                                $conn->beginTransaction();
                                
                                try {
                                    $sql = "INSERT INTO Pagos_Sueldos_Fijos 
                                            (id_empleado, id_semana, sueldo_fijo, gasolina, observaciones, registrado_por, estado) 
                                            VALUES 
                                            (NULL, :id_semana, :sueldo_fijo, :gasolina, :observaciones, :registrado_por, 'pendiente')";
                                    
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bindParam(':id_semana', $id_semana);
                                    $stmt->bindParam(':sueldo_fijo', $sueldo_fijo);
                                    $stmt->bindParam(':gasolina', $gasolina);
                                    
                                    $obs_completas = "ADMIN ID:" . $id_usuario . " - " . $admin_data['nombre_completo'] . " (Gerencia)";
                                    if (!empty($observaciones)) {
                                        $obs_completas .= " | " . $observaciones;
                                    }
                                    $stmt->bindParam(':observaciones', $obs_completas);
                                    $stmt->bindParam(':registrado_por', $registrado_por);
                                    $stmt->execute();
                                    
                                    $fondos = calcularFondosDisponibles($conn, $id_semana);
                                    guardarHistorialFondos($conn, $id_semana, $fondos);
                                    
                                    $conn->commit();
                                    
                                    header("Location: registrar_pago.php?success=3&saldo=" . $fondos['saldo_restante'] . "&nombre=" . urlencode($admin_data['nombre_completo']));
                                    exit;
                                    
                                } catch (Exception $e) {
                                    $conn->rollBack();
                                    $mensaje = "Error al registrar pago: " . $e->getMessage();
                                    $tipo_mensaje = "error";
                                }
                            }
                        }
                    }
                } else {
                    $id_empleado = $id_empleado_original;
                    
                    $sql_check = "SELECT COUNT(*) FROM Pagos_Sueldos_Fijos 
                                 WHERE id_empleado = :id_empleado AND id_semana = :id_semana";
                    $stmt_check = $conn->prepare($sql_check);
                    $stmt_check->bindParam(':id_empleado', $id_empleado);
                    $stmt_check->bindParam(':id_semana', $id_semana);
                    $stmt_check->execute();
                    $existe = $stmt_check->fetchColumn();
                    
                    if ($existe > 0) {
                        $mensaje = "Error: Ya existe un pago registrado para este empleado en esta semana";
                        $tipo_mensaje = "error";
                    } else {
                        $total_pagar = $sueldo_fijo + $gasolina;
                        $verificacion = verificarFondosSuficientes($conn, $id_semana, $total_pagar);
                        
                        if (!$verificacion['suficiente']) {
                            $mensaje = "❌ " . $verificacion['mensaje'] . 
                                      "<br><strong>Saldo disponible:</strong> $" . number_format($verificacion['saldo_restante'], 2) .
                                      "<br><strong>Monto a pagar:</strong> $" . number_format($total_pagar, 2);
                            $tipo_mensaje = "error";
                        } else {
                            $conn->beginTransaction();
                            
                            try {
                                $sql = "INSERT INTO Pagos_Sueldos_Fijos 
                                        (id_empleado, id_semana, sueldo_fijo, gasolina, observaciones, registrado_por, estado) 
                                        VALUES 
                                        (:id_empleado, :id_semana, :sueldo_fijo, :gasolina, :observaciones, :registrado_por, 'pendiente')";
                                
                                $stmt = $conn->prepare($sql);
                                $stmt->bindParam(':id_empleado', $id_empleado);
                                $stmt->bindParam(':id_semana', $id_semana);
                                $stmt->bindParam(':sueldo_fijo', $sueldo_fijo);
                                $stmt->bindParam(':gasolina', $gasolina);
                                $stmt->bindParam(':observaciones', $observaciones);
                                $stmt->bindParam(':registrado_por', $registrado_por);
                                $stmt->execute();
                                
                                $fondos = calcularFondosDisponibles($conn, $id_semana);
                                guardarHistorialFondos($conn, $id_semana, $fondos);
                                
                                $conn->commit();
                                
                                header("Location: registrar_pago.php?success=1&saldo=" . $fondos['saldo_restante']);
                                exit;
                                
                            } catch (Exception $e) {
                                $conn->rollBack();
                                $mensaje = "Error al registrar pago: " . $e->getMessage();
                                $tipo_mensaje = "error";
                            }
                        }
                    }
                }
            } else {
                $nombre_manual = trim($_POST['nombre_manual']);
                
                if (empty($nombre_manual)) {
                    $mensaje = "Error: Debes ingresar el nombre del beneficiario";
                    $tipo_mensaje = "error";
                } else {
                    $total_pagar = $sueldo_fijo + $gasolina;
                    $verificacion = verificarFondosSuficientes($conn, $id_semana, $total_pagar);
                    
                    if (!$verificacion['suficiente']) {
                        $mensaje = "❌ " . $verificacion['mensaje'] . 
                                  "<br><strong>Saldo disponible:</strong> $" . number_format($verificacion['saldo_restante'], 2) .
                                  "<br><strong>Monto a pagar:</strong> $" . number_format($total_pagar, 2);
                        $tipo_mensaje = "error";
                    } else {
                        $conn->beginTransaction();
                        
                        try {
                            $sql = "INSERT INTO Pagos_Sueldos_Fijos 
                                    (id_empleado, id_semana, sueldo_fijo, gasolina, observaciones, registrado_por, estado) 
                                    VALUES 
                                    (NULL, :id_semana, :sueldo_fijo, :gasolina, :observaciones, :registrado_por, 'pendiente')";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(':id_semana', $id_semana);
                            $stmt->bindParam(':sueldo_fijo', $sueldo_fijo);
                            $stmt->bindParam(':gasolina', $gasolina);
                            
                            $obs_completas = "PAGO MANUAL - Beneficiario: " . $nombre_manual;
                            if (!empty($observaciones)) {
                                $obs_completas .= " | " . $observaciones;
                            }
                            $stmt->bindParam(':observaciones', $obs_completas);
                            $stmt->bindParam(':registrado_por', $registrado_por);
                            $stmt->execute();
                            
                            $fondos = calcularFondosDisponibles($conn, $id_semana);
                            guardarHistorialFondos($conn, $id_semana, $fondos);
                            
                            $conn->commit();
                            
                            header("Location: registrar_pago.php?success=2&saldo=" . $fondos['saldo_restante'] . "&nombre=" . urlencode($nombre_manual));
                            exit;
                            
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $mensaje = "Error al registrar pago manual: " . $e->getMessage();
                            $tipo_mensaje = "error";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

if (isset($_GET['success'])) {
    $saldo = floatval($_GET['saldo'] ?? 0);
    if ($_GET['success'] == 1) {
        $mensaje = "✅ Pago registrado exitosamente. <strong>Saldo restante:</strong> $" . number_format($saldo, 2);
        $tipo_mensaje = "success";
    } elseif ($_GET['success'] == 2) {
        $nombre = $_GET['nombre'] ?? 'empleado externo';
        $mensaje = "✅ Pago manual registrado para: <strong>" . htmlspecialchars($nombre) . "</strong>. Saldo restante: $" . number_format($saldo, 2);
        $tipo_mensaje = "success";
    } elseif ($_GET['success'] == 3) {
        $nombre = $_GET['nombre'] ?? 'administrador';
        $mensaje = "✅ Pago registrado exitosamente para: <strong>" . htmlspecialchars($nombre) . " (Gerencia)</strong>. Saldo restante: $" . number_format($saldo, 2);
        $tipo_mensaje = "success";
    }
}

$query_empleados = "
    SELECT 
        id_empleado,
        nombre_completo,
        rol,
        zona
    FROM (
        SELECT 
            id_empleado,
            (nombre || ' ' || apellido_paterno || ' ' || COALESCE(apellido_materno, '')) as nombre_completo,
            CASE 
                WHEN rol = 'vendedor' THEN 'Vendedor'
                WHEN rol = 'cobrador' THEN 'Cobrador'
                ELSE rol
            END as rol,
            zona
        FROM Empleados 
        WHERE estado = 'activo'
        
        UNION ALL
        
        SELECT 
            (id + 10000) as id_empleado,
            (nombre || ' ' || apellido_paterno || ' ' || apellido_materno) as nombre_completo,
            'Gerencia' as rol,
            'Oficina' as zona
        FROM Usuarios 
        WHERE estado = 'activo' AND rol = 'admin'
    )
    ORDER BY rol, nombre_completo
";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Obtener SOLO la semana actual
$semanas_activas = obtenerSemanasActivas($conn);
$semana_actual = $semanas_activas[1] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pago - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/pagos.css">
    
    <style>
        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0;
            list-style: none;
            position: relative;
        }
        
        .wizard-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .wizard-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .wizard-step.active .wizard-step-circle {
            background: var(--color-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
        }
        
        .wizard-step.completed .wizard-step-circle {
            background: var(--color-success);
            color: white;
        }
        
        .wizard-step-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        
        .wizard-step.active .wizard-step-label {
            color: var(--color-primary);
            font-weight: 600;
        }
        
        .tipo-pago-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .tipo-pago-btn {
            padding: 30px 20px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .tipo-pago-btn:hover {
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .tipo-pago-btn.active {
            border-color: var(--color-primary);
            background: rgba(33, 150, 243, 0.05);
        }
        
        .tipo-pago-btn i {
            font-size: 3rem;
            color: var(--color-primary);
            margin-bottom: 10px;
        }
        
        .tipo-pago-btn h3 {
            margin: 10px 0 5px 0;
            color: #333;
        }
        
        .tipo-pago-btn p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .select2-container--default .select2-selection--single {
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 33px;
            padding-left: 10px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--color-primary);
        }
        
        .section-hidden {
            display: none;
        }
        
        .fondos-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 5px solid var(--color-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fondos-disponibles {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .fondos-disponibles i {
            font-size: 3rem;
            color: var(--color-primary);
        }
        
        .fondos-disponibles > div {
            flex: 1;
        }
        
        .fondos-info label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: block;
        }
        
        .fondos-info .valor {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary);
        }
        
        .fondos-info.sin-fondos {
            border-left-color: var(--color-danger);
        }
        
        .fondos-info.sin-fondos .valor {
            color: var(--color-danger);
        }
        
        .fondos-info.sin-fondos i {
            color: var(--color-danger);
        }
        
        .alerta-fondos {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .alerta-fondos i {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .resumen-pago {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid var(--color-primary);
        }
        
        .resumen-pago h3 {
            margin-top: 0;
            color: var(--color-primary);
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .resumen-item:last-child {
            border-bottom: none;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--color-primary);
            padding-top: 15px;
        }
        
       /* Estilos para mostrar semana actual (solo lectura) */
.semana-actual-display {
    background: #f8f9fa;
    color: #333;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    border-left: 5px solid var(--color-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.semana-actual-display i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: var(--color-primary);
}

.semana-actual-display h3 {
    margin: 0 0 10px 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--color-primary);
}

.semana-actual-display .semana-detalle {
    font-size: 1.1rem;
    color: #666;
}
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-money-withdraw'></i> Registrar Pago de Sueldo</h1>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <ul class="wizard-steps">
            <li class="wizard-step active" id="step1-indicator">
                <div class="wizard-step-circle">1</div>
                <div class="wizard-step-label">Tipo de Pago</div>
            </li>
            <li class="wizard-step" id="step2-indicator">
                <div class="wizard-step-circle">2</div>
                <div class="wizard-step-label">Beneficiario</div>
            </li>
            <li class="wizard-step" id="step3-indicator">
                <div class="wizard-step-circle">3</div>
                <div class="wizard-step-label">Semana</div>
            </li>
            <li class="wizard-step" id="step4-indicator">
                <div class="wizard-step-circle">4</div>
                <div class="wizard-step-label">Monto</div>
            </li>
            <li class="wizard-step" id="step5-indicator">
                <div class="wizard-step-circle">5</div>
                <div class="wizard-step-label">Confirmar</div>
            </li>
        </ul>

        <form method="POST" action="" id="formRegistrarPago">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="registrar">
            <input type="hidden" name="tipo_pago" id="tipo_pago" value="">
            <input type="hidden" name="id_semana" id="id_semana" value="<?php echo $semana_actual ? $semana_actual['id_semana'] : ''; ?>">

            <!-- PASO 1: Tipo de Pago -->
            <div class="card wizard-section" id="step1">
                <h2><i class='bx bx-select-multiple'></i> Paso 1: Selecciona el Tipo de Pago</h2>
                
                <div class="tipo-pago-buttons">
                    <div class="tipo-pago-btn" onclick="seleccionarTipoPago('empleado')">
                        <i class='bx bx-user-circle'></i>
                        <h3>Empleado Registrado</h3>
                        <p>Pago a empleado activo de la nómina</p>
                    </div>
                    
                    <div class="tipo-pago-btn" onclick="seleccionarTipoPago('manual')">
                        <i class='bx bx-edit'></i>
                        <h3>Pago Manual</h3>
                        <p>Empleado temporal o externo</p>
                    </div>
                </div>
            </div>

            <!-- PASO 2A: Empleado -->
            <div class="card wizard-section section-hidden" id="step2-empleado">
                <h2><i class='bx bx-user-circle'></i> Paso 2: Selecciona el Empleado</h2>
                
                <div class="form-group">
                    <label for="id_empleado">
                        <i class='bx bx-search'></i> Buscar Empleado *
                    </label>
                    <select name="id_empleado" id="id_empleado" class="form-control" style="width: 100%;">
                        <option value="">-- Escribe para buscar --</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo $emp['id_empleado']; ?>" 
                                    data-rol="<?php echo $emp['rol']; ?>"
                                    data-zona="<?php echo $emp['zona']; ?>">
                                <?php echo htmlspecialchars($emp['nombre_completo']); ?> 
                                (<?php echo ucfirst($emp['rol']); ?> - <?php echo $emp['zona']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="infoEmpleado" style="display: none; margin-top: 15px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--color-primary);">
                    <h4 style="margin-top: 0; color: var(--color-primary);">
                        <i class='bx bx-info-circle'></i> Información del Empleado
                    </h4>
                    <p style="margin: 5px 0;"><strong>Rol:</strong> <span id="displayRol"></span></p>
                    <p style="margin: 5px 0;"><strong>Zona:</strong> <span id="displayZona"></span></p>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="cancelarFormulario()" style="margin-right: auto;">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="volverPaso(1)">
                        <i class='bx bx-arrow-back'></i> Volver
                    </button>
                    <button type="button" class="btn btn-primary" onclick="irPaso(3)" id="btnSiguienteEmpleado">
                        Siguiente <i class='bx bx-arrow-right'></i>
                    </button>
                </div>
            </div>

            <!-- PASO 2B: Pago Manual -->
            <div class="card wizard-section section-hidden" id="step2-manual">
                <h2><i class='bx bx-edit'></i> Paso 2: Datos del Beneficiario</h2>
                
                <div class="form-group">
                    <label for="nombre_manual">
                        <i class='bx bx-user'></i> Nombre Completo del Beneficiario *
                    </label>
                    <input type="text" 
                           name="nombre_manual" 
                           id="nombre_manual" 
                           class="form-control"
                           placeholder="Ej: Juan Pérez García"
                           maxlength="200">
                    <small class="form-text">
                        <i class='bx bx-info-circle'></i> 
                        Empleado temporal o externo no registrado en nómina
                    </small>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="cancelarFormulario()" style="margin-right: auto;">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="volverPaso(1)">
                        <i class='bx bx-arrow-back'></i> Volver
                    </button>
                    <button type="button" class="btn btn-primary" onclick="irPaso(3)" id="btnSiguienteManual">
                        Siguiente <i class='bx bx-arrow-right'></i>
                    </button>
                </div>
            </div>

            <!-- PASO 3: Semana (SOLO VISUALIZACIÓN) -->
            <div class="card wizard-section section-hidden" id="step3">
                <h2><i class='bx bx-calendar-week'></i> Paso 3: Semana de Pago</h2>
                
                <?php if ($semana_actual): ?>
                    <div class="semana-actual-display">
                        <i class='bx bx-calendar-check'></i>
                        <h3>Semana Actual</h3>
                        <div class="semana-detalle">
                            <?php echo $semana_actual['mes'] . ' ' . $semana_actual['anio']; ?> - 
                            Semana <?php echo $semana_actual['numero_semana']; ?>
                            <br>
                            <?php echo date('d/m', strtotime($semana_actual['fecha_inicio'])); ?> - 
                            <?php echo date('d/m', strtotime($semana_actual['fecha_fin'])); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class='bx bx-error-circle'></i>
                        <strong>Error:</strong> No se pudo determinar la semana actual. Por favor contacta al administrador.
                    </div>
                <?php endif; ?>

                <!-- Fondos Disponibles -->
                <div id="fondosInfo" class="fondos-info" style="display: none;">
                    <div class="fondos-disponibles">
                        <i class='bx bx-wallet'></i>
                        <div>
                            <label>Saldo Disponible para esta Semana</label>
                            <div class="valor" id="fondosValor">$0.00</div>
                        </div>
                    </div>
                </div>
                
                <div id="alertaFondos" class="alerta-fondos">
                    <i class='bx bx-error-circle'></i>
                    <strong>⚠️ Sin fondos disponibles</strong>
                    <p style="margin: 5px 0 0 0;">No hay saldo suficiente en esta semana. No podrás continuar con el registro del pago.</p>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="cancelarFormulario()" style="margin-right: auto;">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="volverPaso(2)">
                        <i class='bx bx-arrow-back'></i> Volver
                    </button>
                    <button type="button" class="btn btn-primary" onclick="irPaso(4)" id="btnSiguienteSemana">
                        Siguiente <i class='bx bx-arrow-right'></i>
                    </button>
                </div>
            </div>

            <!-- PASO 4: Montos -->
            <div class="card wizard-section section-hidden" id="step4">
                <h2><i class='bx bx-calculator'></i> Paso 4: Ingresa los Montos</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sueldo_fijo">
                            <i class='bx bx-money'></i> Sueldo Fijo *
                        </label>
                        <input type="number" 
                               name="sueldo_fijo" 
                               id="sueldo_fijo" 
                               step="0.01" 
                               min="0"
                               value="0.00"
                               class="form-control"
                               oninput="calcularTotal()">
                        <small class="form-text">Cantidad fija que se pagará</small>
                    </div>

                    <div class="form-group">
                        <label for="gasolina">
                            <i class='bx bxs-gas-pump'></i> Gasolina
                        </label>
                        <input type="number" 
                               name="gasolina" 
                               id="gasolina" 
                               step="0.01" 
                               min="0"
                               value="0.00"
                               class="form-control"
                               oninput="calcularTotal()">
                        <small class="form-text">Monto adicional por gasolina (opcional)</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones">
                        <i class='bx bx-note'></i> Observaciones
                    </label>
                    <textarea name="observaciones" 
                              id="observaciones" 
                              rows="3" 
                              class="form-control"
                              maxlength="500"
                              placeholder="Notas adicionales (opcional)"></textarea>
                </div>

                <div class="total-pagar" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <label style="color: white; font-size: 1.1rem;">Total a Pagar:</label>
                    <div class="valor" id="totalPagar" style="font-size: 2.5rem; font-weight: 700; color: white;">$0.00</div>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="cancelarFormulario()" style="margin-right: auto;">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="volverPaso(3)">
                        <i class='bx bx-arrow-back'></i> Volver
                    </button>
                    <button type="button" class="btn btn-primary" onclick="irPaso(5)" id="btnSiguienteMonto">
                        Siguiente <i class='bx bx-arrow-right'></i>
                    </button>
                </div>
            </div>

            <!-- PASO 5: Confirmación -->
            <div class="card wizard-section section-hidden" id="step5">
                <h2><i class='bx bx-check-circle'></i> Paso 5: Confirmar Información</h2>
                
                <div class="resumen-pago">
                    <h3><i class='bx bx-receipt'></i> Resumen del Pago</h3>
                    
                    <div class="resumen-item">
                        <span><strong>Tipo de Pago:</strong></span>
                        <span id="resumenTipo">-</span>
                    </div>
                    
                    <div class="resumen-item">
                        <span><strong>Beneficiario:</strong></span>
                        <span id="resumenBeneficiario">-</span>
                    </div>
                    
                    <div class="resumen-item">
                        <span><strong>Semana:</strong></span>
                        <span id="resumenSemana"><?php echo $semana_actual ? $semana_actual['mes'] . ' ' . $semana_actual['anio'] . ' - Semana ' . $semana_actual['numero_semana'] : '-'; ?></span>
                    </div>
                    
                    <div class="resumen-item">
                        <span><strong>Sueldo Fijo:</strong></span>
                        <span id="resumenSueldo">$0.00</span>
                    </div>
                    
                    <div class="resumen-item">
                        <span><strong>Gasolina:</strong></span>
                        <span id="resumenGasolina">$0.00</span>
                    </div>
                    
                    <div class="resumen-item">
                        <span><strong>TOTAL:</strong></span>
                        <span id="resumenTotal">$0.00</span>
                    </div>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <button type="button" class="btn btn-secondary" onclick="cancelarFormulario()" style="margin-right: 10px;">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="volverPaso(4)" style="margin-right: 10px;">
                        <i class='bx bx-arrow-back'></i> Volver
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnConfirmar" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class='bx bx-check-circle'></i> Confirmar y Registrar Pago
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let pasoActual = 1;
        let tipoPagoSeleccionado = '';
        
        $(document).ready(function() {
            $('#id_empleado').select2({
                placeholder: '🔍 Escribe para buscar un empleado...',
                allowClear: true,
                language: {
                    noResults: function() {
                        return "No se encontraron empleados";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });
            
            $('#id_empleado').on('change', function() {
                cargarDatosEmpleado();
            });
        });
        
        function actualizarIndicadores(paso) {
            for (let i = 1; i <= 5; i++) {
                const indicador = document.getElementById('step' + i + '-indicator');
                indicador.classList.remove('active', 'completed');
                
                if (i < paso) {
                    indicador.classList.add('completed');
                } else if (i === paso) {
                    indicador.classList.add('active');
                }
            }
        }
        
        function seleccionarTipoPago(tipo) {
            tipoPagoSeleccionado = tipo;
            document.getElementById('tipo_pago').value = tipo;
            
            document.querySelectorAll('.tipo-pago-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            setTimeout(() => {
                irPaso(2);
            }, 300);
        }
        
        function irPaso(numero) {
            if (numero === 3) {
                if (tipoPagoSeleccionado === 'empleado') {
                    if (!$('#id_empleado').val()) {
                        alert('Por favor selecciona un empleado');
                        return;
                    }
                } else {
                    if (!$('#nombre_manual').val().trim()) {
                        alert('Por favor ingresa el nombre del beneficiario');
                        return;
                    }
                }
                
                // Cargar fondos automáticamente al llegar al paso 3
                setTimeout(() => {
                    actualizarFondosDisponibles();
                }, 100);
            }
            
            if (numero === 4) {
                const fondosInfo = document.getElementById('fondosInfo');
                const saldoActual = document.getElementById('fondosValor').textContent;
                
                if (fondosInfo.style.display === 'none' || saldoActual === '$0.00' || saldoActual === 'Cargando...') {
                    alert('Por favor espera a que se carguen los fondos disponibles');
                    return;
                }
                
                if (fondosInfo.classList.contains('sin-fondos')) {
                    alert('❌ No hay fondos suficientes para registrar pagos en esta semana. No puedes continuar.');
                    return;
                }
            }
            
            if (numero === 5) {
                const sueldo = parseFloat($('#sueldo_fijo').val()) || 0;
                if (sueldo <= 0) {
                    alert('El sueldo fijo debe ser mayor a 0');
                    return;
                }
                actualizarResumen();
            }
            
            document.querySelectorAll('.wizard-section').forEach(section => {
                section.classList.add('section-hidden');
            });
            
            if (numero === 2) {
                if (tipoPagoSeleccionado === 'empleado') {
                    document.getElementById('step2-empleado').classList.remove('section-hidden');
                } else {
                    document.getElementById('step2-manual').classList.remove('section-hidden');
                }
            } else {
                document.getElementById('step' + numero).classList.remove('section-hidden');
            }
            
            actualizarIndicadores(numero);
            pasoActual = numero;
            window.scrollTo({top: 0, behavior: 'smooth'});
        }
        
        function volverPaso(pasoAnterior) {
            irPaso(pasoAnterior);
        }
        
        function cargarDatosEmpleado() {
            const selectElement = $('#id_empleado');
            const selectedOption = selectElement.find('option:selected');
            
            if (selectElement.val()) {
                document.getElementById('displayRol').textContent = selectedOption.data('rol');
                document.getElementById('displayZona').textContent = selectedOption.data('zona');
                document.getElementById('infoEmpleado').style.display = 'block';
            } else {
                document.getElementById('infoEmpleado').style.display = 'none';
            }
        }
        
        function actualizarFondosDisponibles() {
            const semana = document.getElementById('id_semana').value;
            const fondosInfo = document.getElementById('fondosInfo');
            const alertaFondos = document.getElementById('alertaFondos');
            const btnSiguienteSemana = document.getElementById('btnSiguienteSemana');
            
            if (!semana) {
                alert('Error: No se pudo determinar la semana actual');
                return;
            }
            
            document.getElementById('fondosValor').textContent = 'Cargando...';
            fondosInfo.style.display = 'block';
            fondosInfo.classList.remove('sin-fondos');
            alertaFondos.style.display = 'none';
            
            if (btnSiguienteSemana) {
                btnSiguienteSemana.disabled = true;
                btnSiguienteSemana.style.opacity = '0.5';
                btnSiguienteSemana.style.cursor = 'not-allowed';
            }
            
            fetch('ajax_fondos.php?semana=' + semana)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        fondosInfo.style.display = 'none';
                        alertaFondos.style.display = 'block';
                        alertaFondos.innerHTML = `
                            <i class='bx bx-error-circle'></i>
                            <strong>⚠️ Error al cargar fondos</strong>
                            <p style="margin: 5px 0 0 0;">${data.error}</p>
                        `;
                    } else {
                        let saldo = parseFloat(data.saldo_restante);
                        
                        
                        // Si no es un número válido, establecer en 0
                        if (isNaN(saldo) || saldo === null || saldo === undefined) {
                            saldo = 0;
                            console.warn('Saldo inválido recibido del servidor:', data.saldo_restante);
                        }
                        document.getElementById('fondosValor').textContent = 
                            '$' + saldo.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        fondosInfo.style.display = 'block';
                        
                        if (saldo <= 0) {
                            fondosInfo.classList.add('sin-fondos');
                            alertaFondos.style.display = 'block';
                            if (btnSiguienteSemana) {
                                btnSiguienteSemana.disabled = true;
                                btnSiguienteSemana.style.opacity = '0.5';
                                btnSiguienteSemana.style.cursor = 'not-allowed';
                            }
                        } else {
                            fondosInfo.classList.remove('sin-fondos');
                            alertaFondos.style.display = 'none';
                            if (btnSiguienteSemana) {
                                btnSiguienteSemana.disabled = false;
                                btnSiguienteSemana.style.opacity = '1';
                                btnSiguienteSemana.style.cursor = 'pointer';
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    fondosInfo.style.display = 'none';
                    alertaFondos.style.display = 'block';
                    alertaFondos.innerHTML = `
                        <i class='bx bx-error-circle'></i>
                        <strong>⚠️ Error de conexión</strong>
                        <p style="margin: 5px 0 0 0;">No se pudo conectar al servidor. Verifica tu conexión.</p>
                    `;
                })
                .finally(() => {
                    if (btnSiguienteSemana && !fondosInfo.classList.contains('sin-fondos') && fondosInfo.style.display !== 'none') {
                        btnSiguienteSemana.disabled = false;
                        btnSiguienteSemana.style.opacity = '1';
                        btnSiguienteSemana.style.cursor = 'pointer';
                    }
                });
        }
        
        function calcularTotal() {
            const sueldo = parseFloat(document.getElementById('sueldo_fijo').value) || 0;
            const gasolina = parseFloat(document.getElementById('gasolina').value) || 0;
            const total = sueldo + gasolina;
            
            document.getElementById('totalPagar').textContent = 
                '$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        function actualizarResumen() {
            document.getElementById('resumenTipo').textContent = 
                tipoPagoSeleccionado === 'empleado' ? 'Empleado Registrado' : 'Pago Manual';
            
            if (tipoPagoSeleccionado === 'empleado') {
                document.getElementById('resumenBeneficiario').textContent = 
                    $('#id_empleado option:selected').text();
            } else {
                document.getElementById('resumenBeneficiario').textContent = 
                    $('#nombre_manual').val();
            }
            
            const sueldo = parseFloat($('#sueldo_fijo').val()) || 0;
            const gasolina = parseFloat($('#gasolina').val()) || 0;
            const total = sueldo + gasolina;
            
            document.getElementById('resumenSueldo').textContent = 
                '$' + sueldo.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('resumenGasolina').textContent = 
                '$' + gasolina.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('resumenTotal').textContent = 
                '$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                const timestamp = new Date().getTime();
                const separator = pagina.includes('?') ? '&' : '?';
                const urlConTimestamp = pagina + separator + '_t=' + timestamp;
                
                window.parent.postMessage({
                    type: 'navigate', 
                    page: pagina,
                    fullUrl: urlConTimestamp,
                    forceReload: true
                }, '*');
            } else {
                window.location.href = pagina;
            }
        }
        
        function cancelarFormulario() {
            if (confirm('¿Estás seguro de cancelar? Se perderá toda la información ingresada.')) {
                document.getElementById('formRegistrarPago').reset();
                $('#id_empleado').val(null).trigger('change');
                
                pasoActual = 1;
                tipoPagoSeleccionado = '';
                document.getElementById('tipo_pago').value = '';
                
                document.querySelectorAll('.wizard-section').forEach(section => {
                    section.classList.add('section-hidden');
                });
                
                document.getElementById('step1').classList.remove('section-hidden');
                actualizarIndicadores(1);
                
                document.getElementById('infoEmpleado').style.display = 'none';
                document.getElementById('fondosInfo').style.display = 'none';
                document.getElementById('alertaFondos').style.display = 'none';
                
                window.scrollTo({top: 0, behavior: 'smooth'});
            }
        }
    </script>
</body>
</html>