<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

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

// REGISTRAR GASOLINA SEMANAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $id_semana = $_POST['id_semana'];
        $monto = floatval($_POST['monto']);
        $observaciones = trim($_POST['observaciones']);
        $registrado_por = $_SESSION['usuario'];
        
        // Obtener el viernes de esa semana
        $query_semana = "SELECT fecha_inicio, fecha_fin FROM Semanas_Cobro WHERE id_semana = :id";
        $stmt_semana = $conn->prepare($query_semana);
        $stmt_semana->bindParam(':id', $id_semana);
        $stmt_semana->execute();
        $semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);
        
        // Buscar el viernes entre fecha_inicio y fecha_fin
        $fecha_inicio = new DateTime($semana['fecha_inicio']);
        $fecha_fin = new DateTime($semana['fecha_fin']);
        $fecha_viernes = null;
        
        $fecha_actual = clone $fecha_inicio;
        while ($fecha_actual <= $fecha_fin) {
            if ($fecha_actual->format('w') == 5) { // 5 = viernes
                $fecha_viernes = $fecha_actual->format('Y-m-d');
                break;
            }
            $fecha_actual->modify('+1 day');
        }
        
        if (!$fecha_viernes) {
            $mensaje = "Error: No se encontró un viernes en esta semana";
            $tipo_mensaje = "error";
        } else {
            try {
                // Verificar si ya existe gasolina para ese empleado en esa semana
                $sql_check = "SELECT COUNT(*) FROM Detalle_Gasolina_Semanal 
                              WHERE id_empleado = :id_empleado AND id_semana = :id_semana";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':id_empleado', $id_empleado);
                $stmt_check->bindParam(':id_semana', $id_semana);
                $stmt_check->execute();
                $existe = $stmt_check->fetchColumn();
                
                if ($existe > 0) {
                    $mensaje = "Error: Ya existe un registro de gasolina para este empleado en esta semana";
                    $tipo_mensaje = "error";
                } else {
                    $sql = "INSERT INTO Detalle_Gasolina_Semanal 
                            (id_empleado, id_semana, fecha, monto, observaciones, registrado_por) 
                            VALUES 
                            (:id_empleado, :id_semana, :fecha, :monto, :observaciones, :registrado_por)";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_empleado', $id_empleado);
                    $stmt->bindParam(':id_semana', $id_semana);
                    $stmt->bindParam(':fecha', $fecha_viernes);
                    $stmt->bindParam(':monto', $monto);
                    $stmt->bindParam(':observaciones', $observaciones);
                    $stmt->bindParam(':registrado_por', $registrado_por);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Gasto de gasolina registrado exitosamente para el viernes " . date('d/m/Y', strtotime($fecha_viernes));
                        $tipo_mensaje = "success";
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error al registrar gasolina: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// Obtener todos los empleados activos
$query_empleados = "SELECT * FROM Empleados WHERE estado = 'activo' ORDER BY nombre";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Obtener semanas activas
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Obtener registros recientes
$query_recientes = "SELECT * FROM Vista_Gasolina_Semanal LIMIT 10";
$stmt_recientes = $conn->query($query_recientes);
$gastos_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Gasolina - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bxs-gas-pump'></i> Registrar Gasolina Semanal</h1>
            <div class="header-actions">
                <a href="ver_gasolina.php" class="btn btn-secondary">
                    <i class='bx bx-list-ul'></i> Ver Historial
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Información Importante -->
        <div class="card" style="background: #FFF3E0; border-left: 4px solid #FF9800;">
            <h2><i class='bx bx-info-circle'></i> Información Importante</h2>
            <p><strong>📅 La gasolina se registra automáticamente para el VIERNES de la semana seleccionada.</strong></p>
            <p>• Cada empleado puede registrar UN gasto de gasolina por semana</p>
            <p>• Solo ingresa el monto en efectivo que se le dio</p>
            <p>• El sistema asignará automáticamente la fecha del viernes</p>
        </div>

        <!-- Formulario de Registro -->
        <div class="card">
            <h2><i class='bx bx-edit'></i> Registrar Gasto de Gasolina</h2>
            <form method="POST" id="formGasolina">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="registrar">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_empleado">
                            <i class='bx bx-user'></i> Empleado *
                        </label>
                        <select name="id_empleado" id="id_empleado" required>
                            <option value="">Seleccionar empleado</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>">
                                    <?php echo $emp['nombre'] . ' ' . $emp['apellido_paterno']; ?> 
                                    (<?php echo ucfirst($emp['rol']); ?> - <?php echo $emp['zona']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_semana">
                            <i class='bx bx-calendar-week'></i> Semana *
                        </label>
                        <select name="id_semana" id="id_semana" required onchange="mostrarFechaViernes()">
                            <option value="">Seleccionar semana</option>
                            <?php foreach ($semanas as $sem): ?>
                                <option value="<?php echo $sem['id_semana']; ?>" 
                                        data-inicio="<?php echo $sem['fecha_inicio']; ?>"
                                        data-fin="<?php echo $sem['fecha_fin']; ?>">
                                    <?php echo $sem['mes']; ?> - Semana <?php echo $sem['numero_semana']; ?> 
                                    (<?php echo date('d/m', strtotime($sem['fecha_inicio'])); ?> - 
                                     <?php echo date('d/m', strtotime($sem['fecha_fin'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="info_fecha_viernes" class="form-text" style="color: #2196F3; font-weight: bold;"></small>
                    </div>

                    <div class="form-group">
                        <label for="monto">
                            <i class='bx bx-money'></i> Monto en Efectivo *
                        </label>
                        <input type="number" 
                               name="monto" 
                               id="monto" 
                               step="0.01" 
                               min="0"
                               value="0.00"
                               required
                               placeholder="Ej: 500.00">
                        <small class="form-text">Cantidad entregada en efectivo para gasolina</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones">
                        <i class='bx bx-note'></i> Observaciones
                    </label>
                    <textarea name="observaciones" 
                              id="observaciones" 
                              rows="3" 
                              maxlength="500"
                              placeholder="Notas adicionales (opcional)..."></textarea>
                    <small class="char-counter" id="counter-observaciones">0/500</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Registrar Gasolina
                    </button>
                    <button type="reset" class="btn btn-secondary" onclick="document.getElementById('info_fecha_viernes').textContent = '';">
                        <i class='bx bx-reset'></i> Limpiar
                    </button>
                </div>
            </form>
        </div>

        <!-- Registros Recientes -->
        <?php if (count($gastos_recientes) > 0): ?>
        <div class="card">
            <h2><i class='bx bx-time-five'></i> Últimos Registros de Gasolina</h2>
            <div class="table-container">
                <table class="table-comisiones">
                    <thead>
                        <tr>
                            <th>Fecha (Viernes)</th>
                            <th>Empleado</th>
                            <th>Rol</th>
                            <th>Zona</th>
                            <th>Semana</th>
                            <th>Monto</th>
                            <th>Observaciones</th>
                            <th>Registrado Por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos_recientes as $gasto): ?>
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
                                        <small><?php echo htmlspecialchars(substr($gasto['observaciones'], 0, 30)); ?></small>
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
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // Contador de caracteres
            const observaciones = document.getElementById('observaciones');
            const counter = document.getElementById('counter-observaciones');
            
            observaciones.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/500`;
                
                counter.classList.remove('warning', 'limit');
                if (length >= 500) {
                    counter.classList.add('limit');
                } else if (length >= 400) {
                    counter.classList.add('warning');
                }
            });
        });

        function mostrarFechaViernes() {
            const select = document.getElementById('id_semana');
            const option = select.options[select.selectedIndex];
            const infoFecha = document.getElementById('info_fecha_viernes');
            
            if (option.value) {
                const fechaInicio = new Date(option.getAttribute('data-inicio') + 'T00:00:00');
                const fechaFin = new Date(option.getAttribute('data-fin') + 'T00:00:00');
                
                // Buscar el viernes
                let fechaActual = new Date(fechaInicio);
                let viernes = null;
                
                while (fechaActual <= fechaFin) {
                    if (fechaActual.getDay() === 5) { // 5 = viernes
                        viernes = fechaActual;
                        break;
                    }
                    fechaActual.setDate(fechaActual.getDate() + 1);
                }
                
                if (viernes) {
                    const dia = viernes.getDate();
                    const mes = viernes.getMonth() + 1;
                    const anio = viernes.getFullYear();
                    infoFecha.textContent = `📅 Se registrará para el viernes ${dia}/${mes}/${anio}`;
                } else {
                    infoFecha.textContent = '⚠️ No se encontró un viernes en esta semana';
                    infoFecha.style.color = '#f44336';
                }
            } else {
                infoFecha.textContent = '';
            }
        }
    </script>

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