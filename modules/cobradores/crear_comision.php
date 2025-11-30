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

// PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $id_semana = $_POST['id_semana'];
        $total_ventas = floatval($_POST['total_ventas']);
        $total_cobros = floatval($_POST['total_cobros']);
        $total_gasolina = floatval($_POST['total_gasolina']);
        $prestamo = floatval($_POST['prestamo']);
        $observaciones = trim($_POST['observaciones']);
        
        // Validar préstamo
        if ($prestamo > 1500) {
            $mensaje = "Error: El préstamo no puede exceder de $1,500.00";
            $tipo_mensaje = "error";
        } else {
            try {
                // Obtener zona del empleado
                $query_zona = "SELECT zona FROM Empleados WHERE id_empleado = :id";
                $stmt_zona = $conn->prepare($query_zona);
                $stmt_zona->bindParam(':id', $id_empleado);
                $stmt_zona->execute();
                $zona_data = $stmt_zona->fetch(PDO::FETCH_ASSOC);
                $zona = $zona_data['zona'];
                
                // Calcular comisión del 10%
                $comision_cobro = $total_cobros * 0.10;
                
                // Calcular total: ventas + comision_cobro + gasolina - prestamo
                $total_comision = $total_ventas + $comision_cobro + $total_gasolina - $prestamo;
                
                $sql = "INSERT INTO Comisiones_Cobradores 
                        (id_empleado, id_semana, zona, total_ventas, total_cobros, 
                         comision_cobro, total_gasolina, prestamo, total_comision, observaciones, estado) 
                        VALUES 
                        (:id_empleado, :id_semana, :zona, :total_ventas, :total_cobros, 
                         :comision_cobro, :total_gasolina, :prestamo, :total_comision, :observaciones, 'pendiente')";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id_empleado', $id_empleado);
                $stmt->bindParam(':id_semana', $id_semana);
                $stmt->bindParam(':zona', $zona);
                $stmt->bindParam(':total_ventas', $total_ventas);
                $stmt->bindParam(':total_cobros', $total_cobros);
                $stmt->bindParam(':comision_cobro', $comision_cobro);
                $stmt->bindParam(':total_gasolina', $total_gasolina);
                $stmt->bindParam(':prestamo', $prestamo);
                $stmt->bindParam(':total_comision', $total_comision);
                $stmt->bindParam(':observaciones', $observaciones);
                
                if ($stmt->execute()) {
                    $mensaje = "Comisión registrada exitosamente";
                    $tipo_mensaje = "success";
                    
                    // Redirigir después de 2 segundos
                    header("refresh:2;url=index.php");
                }
            } catch (PDOException $e) {
                $mensaje = "Error al registrar la comisión: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// Obtener semanas disponibles
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Obtener cobradores activos
$query_cobradores = "SELECT * FROM Empleados WHERE rol = 'cobrador' AND estado = 'activo' ORDER BY nombre";
$stmt_cobradores = $conn->query($query_cobradores);
$cobradores = $stmt_cobradores->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Comisión - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-plus-circle'></i> Registrar Nueva Comisión</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class='bx bx-arrow-back'></i> Volver
            </a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card" id="formComision">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="crear">

            <h2><i class='bx bx-info-circle'></i> Información General</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="id_semana">
                        <i class='bx bx-calendar-week'></i> Semana de Cobro *
                    </label>
                    <select name="id_semana" id="id_semana" required>
                        <option value="">Seleccionar semana</option>
                        <?php foreach ($semanas as $sem): ?>
                            <option value="<?php echo $sem['id_semana']; ?>">
                                <?php echo $sem['mes']; ?> <?php echo $sem['anio']; ?> - Semana <?php echo $sem['numero_semana']; ?> 
                                (<?php echo date('d/m', strtotime($sem['fecha_inicio'])); ?> - 
                                 <?php echo date('d/m', strtotime($sem['fecha_fin'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_empleado">
                        <i class='bx bx-user'></i> Cobrador *
                    </label>
                    <select name="id_empleado" id="id_empleado" required>
                        <option value="">Seleccionar cobrador</option>
                        <?php foreach ($cobradores as $cob): ?>
                            <option value="<?php echo $cob['id_empleado']; ?>" data-zona="<?php echo $cob['zona']; ?>">
                                <?php echo $cob['nombre'] . ' ' . $cob['apellido_paterno'] . ' ' . $cob['apellido_materno']; ?> 
                                - Zona: <?php echo $cob['zona']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="zona_info" class="form-text"></small>
                </div>
            </div>

            <h2 style="margin-top: 30px;"><i class='bx bx-dollar'></i> Montos</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="total_ventas">
                        <i class='bx bx-shopping-bag'></i> Total Ventas *
                    </label>
                    <input type="number" 
                           name="total_ventas" 
                           id="total_ventas" 
                           step="0.01" 
                           min="0" 
                           value="0.00"
                           required
                           oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label for="total_cobros">
                        <i class='bx bx-money'></i> Total Cobros *
                    </label>
                    <input type="number" 
                           name="total_cobros" 
                           id="total_cobros" 
                           step="0.01" 
                           min="0" 
                           value="0.00"
                           required
                           oninput="calcularTotal()">
                    <small class="form-text">Comisión: 10%</small>
                </div>

                <div class="form-group">
                    <label for="total_gasolina">
                        <i class='bx bxs-gas-pump'></i> Gasolina
                    </label>
                    <input type="number" 
                           name="total_gasolina" 
                           id="total_gasolina" 
                           step="0.01" 
                           min="0" 
                           value="0.00"
                           oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label for="prestamo">
                        <i class='bx bx-wallet'></i> Préstamo
                    </label>
                    <input type="number" 
                           name="prestamo" 
                           id="prestamo" 
                           step="0.01" 
                           min="0" 
                           max="1500" 
                           value="0.00"
                           oninput="calcularTotal()">
                    <small class="form-text text-danger">Máximo: $1,500.00</small>
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
                          placeholder="Notas adicionales..."></textarea>
                <small class="char-counter" id="counter-observaciones">0/500</small>
            </div>

            <!-- Resumen de Cálculo -->
            <div class="calculo-resumen">
                <h3><i class='bx bx-calculator'></i> Resumen de Cálculo</h3>
                <table class="tabla-resumen">
                    <tr>
                        <td>Total Ventas:</td>
                        <td class="text-right">$<span id="display_ventas">0.00</span></td>
                    </tr>
                    <tr>
                        <td>Comisión Cobros (10%):</td>
                        <td class="text-right text-success">+$<span id="display_comision">0.00</span></td>
                    </tr>
                    <tr>
                        <td>Gasolina:</td>
                        <td class="text-right text-success">+$<span id="display_gasolina">0.00</span></td>
                    </tr>
                    <tr>
                        <td>Préstamo:</td>
                        <td class="text-right text-danger">-$<span id="display_prestamo">0.00</span></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL A PAGAR:</strong></td>
                        <td class="text-right"><strong>$<span id="display_total">0.00</span></strong></td>
                    </tr>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-save'></i> Registrar Comisión
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class='bx bx-x'></i> Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }

            // Mostrar zona al seleccionar cobrador
            const selectEmpleado = document.getElementById('id_empleado');
            const zonaInfo = document.getElementById('zona_info');
            
            selectEmpleado.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const zona = selectedOption.getAttribute('data-zona');
                if (zona) {
                    zonaInfo.textContent = `Zona asignada: ${zona}`;
                    zonaInfo.style.color = '#2196F3';
                    zonaInfo.style.fontWeight = 'bold';
                } else {
                    zonaInfo.textContent = '';
                }
            });

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

        function calcularTotal() {
            const ventas = parseFloat(document.getElementById('total_ventas').value) || 0;
            const cobros = parseFloat(document.getElementById('total_cobros').value) || 0;
            const gasolina = parseFloat(document.getElementById('total_gasolina').value) || 0;
            const prestamo = parseFloat(document.getElementById('prestamo').value) || 0;
            
            // Validar préstamo máximo
            if (prestamo > 1500) {
                alert('El préstamo no puede exceder de $1,500.00');
                document.getElementById('prestamo').value = 1500;
                return;
            }
            
            const comisionCobro = cobros * 0.10;
            const total = ventas + comisionCobro + gasolina - prestamo;
            
            // Actualizar displays
            document.getElementById('display_ventas').textContent = ventas.toFixed(2);
            document.getElementById('display_comision').textContent = comisionCobro.toFixed(2);
            document.getElementById('display_gasolina').textContent = gasolina.toFixed(2);
            document.getElementById('display_prestamo').textContent = prestamo.toFixed(2);
            document.getElementById('display_total').textContent = total.toFixed(2);
        }
    </script>
</body>
</html>