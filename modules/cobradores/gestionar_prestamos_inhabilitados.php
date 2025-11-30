<?php
/**
 * gestionar_prestamos_inhabilitados.php
 * Permite inhabilitar préstamos (total o parcialmente)
 * Los montos inhabilitados son absorbidos por la empresa
 */

session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

// Prevenir caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_comision = $_GET['id'];
$usuario_actual = $_SESSION['usuario'];

// Obtener datos de la comisión
$query_comision = "SELECT * FROM Vista_Comisiones_Completo WHERE id_comision = :id";
$stmt_comision = $conn->prepare($query_comision);
$stmt_comision->bindParam(':id', $id_comision);
$stmt_comision->execute();
$comision = $stmt_comision->fetch(PDO::FETCH_ASSOC);

if (!$comision) {
    header("Location: index.php");
    exit;
}

// Obtener préstamos inhabilitados existentes
$query_inhabilitados = "SELECT * FROM Prestamos_Inhabilitados WHERE id_comision = :id ORDER BY fecha_registro";
$stmt_inhabilitados = $conn->prepare($query_inhabilitados);
$stmt_inhabilitados->bindParam(':id', $id_comision);
$stmt_inhabilitados->execute();
$inhabilitados = $stmt_inhabilitados->fetchAll(PDO::FETCH_ASSOC);

// Calcular montos
$prestamo_total = floatval($comision['prestamo']);
$prestamo_inhabilitado_actual = floatval($comision['prestamo_inhabilitado']);
$prestamo_descontado = $prestamo_total - $prestamo_inhabilitado_actual;
$disponible_inhabilitar = $prestamo_descontado;

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['accion'])) {
            
            // AGREGAR MONTO INHABILITADO
            if ($_POST['accion'] === 'agregar') {
                $monto = floatval($_POST['monto']);
                $observaciones = trim($_POST['observaciones']);
                
                if ($monto <= 0) {
                    throw new Exception("El monto debe ser mayor a cero");
                }
                
                if ($monto > $disponible_inhabilitar) {
                    throw new Exception("El monto excede el préstamo disponible para inhabilitar ($" . number_format($disponible_inhabilitar, 2) . ")");
                }
                
                $sql_insert = "INSERT INTO Prestamos_Inhabilitados 
                              (id_comision, monto_inhabilitado, observaciones, registrado_por) 
                              VALUES (:id_comision, :monto, :obs, :usuario)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bindParam(':id_comision', $id_comision);
                $stmt_insert->bindParam(':monto', $monto);
                $stmt_insert->bindParam(':obs', $observaciones);
                $stmt_insert->bindParam(':usuario', $usuario_actual);
                $stmt_insert->execute();
                
                $mensaje = "Préstamo inhabilitado agregado correctamente. Monto: $" . number_format($monto, 2);
                $tipo_mensaje = 'success';
                
                // Recargar página para ver cambios
                header("Location: gestionar_prestamos_inhabilitados.php?id=$id_comision&msg=success");
                exit;
            }
            
            // ELIMINAR MONTO INHABILITADO
            if ($_POST['accion'] === 'eliminar') {
                $id_inhabilitado = intval($_POST['id_inhabilitado']);
                
                $sql_delete = "DELETE FROM Prestamos_Inhabilitados WHERE id_inhabilitado = :id";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bindParam(':id', $id_inhabilitado);
                $stmt_delete->execute();
                
                $mensaje = "Registro eliminado correctamente";
                $tipo_mensaje = 'success';
                
                header("Location: gestionar_prestamos_inhabilitados.php?id=$id_comision&msg=deleted");
                exit;
            }
            
            // INHABILITAR TODO EL PRÉSTAMO
            if ($_POST['accion'] === 'inhabilitar_todo') {
                if ($disponible_inhabilitar <= 0) {
                    throw new Exception("No hay monto disponible para inhabilitar");
                }
                
                $observaciones = "Préstamo inhabilitado completamente";
                
                $sql_insert = "INSERT INTO Prestamos_Inhabilitados 
                              (id_comision, monto_inhabilitado, observaciones, registrado_por) 
                              VALUES (:id_comision, :monto, :obs, :usuario)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bindParam(':id_comision', $id_comision);
                $stmt_insert->bindParam(':monto', $disponible_inhabilitar);
                $stmt_insert->bindParam(':obs', $observaciones);
                $stmt_insert->bindParam(':usuario', $usuario_actual);
                $stmt_insert->execute();
                
                $mensaje = "Préstamo inhabilitado completamente. Monto: $" . number_format($disponible_inhabilitar, 2);
                $tipo_mensaje = 'success';
                
                header("Location: gestionar_prestamos_inhabilitados.php?id=$id_comision&msg=all");
                exit;
            }
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Mensaje de URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        $mensaje = "Operación realizada correctamente";
        $tipo_mensaje = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $mensaje = "Registro eliminado correctamente";
        $tipo_mensaje = 'success';
    } elseif ($_GET['msg'] === 'all') {
        $mensaje = "Préstamo inhabilitado completamente";
        $tipo_mensaje = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Préstamos Inhabilitados</title>
    <link rel="stylesheet" href="cobradores.css">
    <style>
        .container-inhabilitados {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .info-comision {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .info-comision h2 {
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .info-item label {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .info-item .valor {
            font-size: 20px;
            font-weight: bold;
        }
        
        .resumen-prestamo {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .resumen-prestamo h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .resumen-card {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .resumen-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .resumen-card.inhabilitado {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .resumen-card.descontado {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .resumen-card.disponible {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .resumen-card label {
            display: block;
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .resumen-card .monto {
            font-size: 32px;
            font-weight: bold;
        }
        
        .acciones-rapidas {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .acciones-rapidas h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .form-inhabilitado {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-inhabilitado h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .tabla-inhabilitados {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tabla-inhabilitados h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
        }
        
        tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .mensaje {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .info-grid,
            .resumen-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-inhabilitados">
        
        <!-- Información de la Comisión -->
        <div class="info-comision">
            <h2>📋 Gestión de Préstamos Inhabilitados</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Empleado</label>
                    <div class="valor"><?php echo htmlspecialchars($comision['nombre_completo']); ?></div>
                </div>
                <div class="info-item">
                    <label>Periodo</label>
                    <div class="valor"><?php echo $comision['mes'] . ' ' . $comision['anio']; ?></div>
                </div>
                <div class="info-item">
                    <label>Semana</label>
                    <div class="valor">Semana <?php echo $comision['numero_semana']; ?></div>
                </div>
                <div class="info-item">
                    <label>Zona</label>
                    <div class="valor"><?php echo $comision['zona']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <?php endif; ?>
        
        <!-- Resumen de Préstamo -->
        <div class="resumen-prestamo">
            <h3>💰 Resumen de Préstamo</h3>
            <div class="resumen-grid">
                <div class="resumen-card total">
                    <label>Préstamo Total</label>
                    <div class="monto">$<?php echo number_format($prestamo_total, 2); ?></div>
                </div>
                <div class="resumen-card inhabilitado">
                    <label>Monto Inhabilitado</label>
                    <div class="monto">$<?php echo number_format($prestamo_inhabilitado_actual, 2); ?></div>
                    <small>Absorbido por empresa</small>
                </div>
                <div class="resumen-card descontado">
                    <label>Préstamo a Descontar</label>
                    <div class="monto">$<?php echo number_format($prestamo_descontado, 2); ?></div>
                    <small>Se descuenta al empleado</small>
                </div>
                <div class="resumen-card disponible">
                    <label>Disponible para Inhabilitar</label>
                    <div class="monto">$<?php echo number_format($disponible_inhabilitar, 2); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="acciones-rapidas">
            <h3>⚡ Acciones Rápidas</h3>
            <div class="btn-group">
                <?php if ($disponible_inhabilitar > 0): ?>
                <button class="btn btn-danger" onclick="mostrarFormulario()">
                    ➕ Inhabilitar Monto Personalizado
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de inhabilitar TODO el préstamo restante ($<?php echo number_format($disponible_inhabilitar, 2); ?>)? La empresa absorberá este monto.');">
                    <input type="hidden" name="accion" value="inhabilitar_todo">
                    <button type="submit" class="btn btn-danger">
                        🚫 Inhabilitar Todo ($<?php echo number_format($disponible_inhabilitar, 2); ?>)
                    </button>
                </form>
                <?php else: ?>
                <div style="padding: 10px; background: #d1ecf1; color: #0c5460; border-radius: 8px;">
                    ✅ El préstamo ya está completamente inhabilitado
                </div>
                <?php endif; ?>
                
                <a href="ver_comision.php?id=<?php echo $id_comision; ?>" class="btn btn-secondary">
                    ← Volver a Comisión
                </a>
            </div>
        </div>
        
        <!-- Formulario para Agregar (Oculto por defecto) -->
        <div class="form-inhabilitado" id="formInhabilitar" style="display: none;">
            <h3>➕ Inhabilitar Monto Personalizado</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="agregar">
                
                <div class="form-group">
                    <label>Monto a Inhabilitar *</label>
                    <input type="number" 
                           name="monto" 
                           step="0.01" 
                           min="0.01" 
                           max="<?php echo $disponible_inhabilitar; ?>" 
                           required
                           placeholder="Ej: 150.00">
                    <small style="color: #666;">Máximo disponible: $<?php echo number_format($disponible_inhabilitar, 2); ?></small>
                </div>
                
                <div class="form-group">
                    <label>Observaciones *</label>
                    <textarea name="observaciones" 
                              rows="3" 
                              required
                              placeholder="Ej: Empleado no alcanzó comisión suficiente para cubrir préstamo"></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        💾 Guardar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="ocultarFormulario()">
                        ✖️ Cancelar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tabla de Préstamos Inhabilitados -->
        <div class="tabla-inhabilitados">
            <h3>📊 Historial de Montos Inhabilitados</h3>
            
            <?php if (count($inhabilitados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Monto</th>
                        <th>Observaciones</th>
                        <th>Registrado Por</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inhabilitados as $index => $inh): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td style="font-weight: bold; color: #f5576c;">
                            $<?php echo number_format($inh['monto_inhabilitado'], 2); ?>
                        </td>
                        <td><?php echo htmlspecialchars($inh['observaciones']); ?></td>
                        <td><?php echo htmlspecialchars($inh['registrado_por']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($inh['fecha_registro'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este registro? El monto volverá a ser descontado al empleado.');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_inhabilitado" value="<?php echo $inh['id_inhabilitado']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                    🗑️ Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="1">TOTAL:</td>
                        <td style="color: #f5576c;">
                            $<?php echo number_format($prestamo_inhabilitado_actual, 2); ?>
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px;">📭</div>
                <p>No hay montos inhabilitados para esta comisión</p>
                <small>El préstamo completo será descontado al empleado</small>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <script>
        function mostrarFormulario() {
            document.getElementById('formInhabilitar').style.display = 'block';
            document.getElementById('formInhabilitar').scrollIntoView({ behavior: 'smooth' });
        }
        
        function ocultarFormulario() {
            document.getElementById('formInhabilitar').style.display = 'none';
        }
        
        // Auto-ocultar mensajes después de 5 segundos
        setTimeout(function() {
            var mensajes = document.querySelectorAll('.mensaje');
            mensajes.forEach(function(mensaje) {
                mensaje.style.transition = 'opacity 0.5s';
                mensaje.style.opacity = '0';
                setTimeout(function() {
                    mensaje.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
    
    <!-- Script de navegación para dashboard -->
    <script src="script_navegacion_dashboard.js"></script>
</body>
</html>