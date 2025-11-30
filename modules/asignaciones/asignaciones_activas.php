<?php
// Configuración de zona horaria: NECESARIO para que DateTime() en PHP funcione correctamente
date_default_timezone_set('America/Mexico_City'); 
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

// Mostrar mensaje flash
$mensaje = '';
$tipo_mensaje = '';
if (isset($_SESSION['mensaje_flash'])) {
    $mensaje = $_SESSION['mensaje_flash'];
    $tipo_mensaje = $_SESSION['tipo_mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
    unset($_SESSION['tipo_mensaje_flash']);
}

$asignaciones = obtenerAsignacionesActivas($conn);

// Obtener detalles de productos para cada asignación
$asignaciones_con_productos = [];
foreach ($asignaciones as $asignacion) {
    $productos = obtenerProductosAsignacion($conn, $asignacion['id_asignacion']);
    $asignacion['productos'] = $productos;
    $asignaciones_con_productos[] = $asignacion;
}
$asignaciones = $asignaciones_con_productos;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignaciones Activas - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/asignaciones.css">
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-time-five'></i> Asignaciones Activas</h1>

        <?php if ($mensaje): ?>
            <div class="alerta <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>
                <i class='bx bx-list-ul'></i> 
                Asignaciones en Ruta (<?php echo count($asignaciones); ?>)
            </h2>

            <?php if (count($asignaciones) > 0): ?>
                <div class="asignaciones-grid">
                    <?php foreach ($asignaciones as $asignacion): ?>
                        
                        <?php 
                            // === CORRECCIÓN: La fecha ya está en hora local ===
                            // NO necesitamos conversión porque se insertó correctamente desde PHP
                            $fecha_salida = new DateTime($asignacion['fecha_hora_salida']);
                            $ahora = new DateTime();
                            $diff = $fecha_salida->diff($ahora);
                            
                            $tiempo_en_ruta = '';
                            if ($diff->y > 0) $tiempo_en_ruta .= $diff->y . 'a ';
                            if ($diff->m > 0) $tiempo_en_ruta .= $diff->m . 'm ';
                            if ($diff->d > 0) $tiempo_en_ruta .= $diff->d . 'd ';
                            
                            // Siempre mostrar horas y minutos
                            if (empty($tiempo_en_ruta) || $diff->d == 0) {
                                $tiempo_en_ruta .= $diff->h . 'h ' . $diff->i . 'm';
                            }
                            $tiempo_en_ruta = trim($tiempo_en_ruta);
                        ?>
                        
                        <div class="asignacion-card">
                            <div class="asignacion-header">
                                <div class="asignacion-id">
                                    #<?php echo str_pad($asignacion['id_asignacion'], 4, '0', STR_PAD_LEFT); ?>
                                </div>
                                <span class="estado-badge abierta">
                                    <i class='bx bx-loader-circle'></i> En Ruta
                                </span>
                            </div>

                            <div class="asignacion-info">
                                <div class="info-row">
                                    <i class='bx bx-user'></i>
                                    <span>
                                        <strong>Empleado:</strong> 
                                        <?php echo htmlspecialchars($asignacion['nombre_empleado']); ?>
                                    </span>
                                </div>

                                <div class="info-row">
                                    <i class='bx bx-car'></i>
                                    <span>
                                        <strong>Vehículo:</strong> 
                                        <?php echo htmlspecialchars($asignacion['vehiculo_desc']); ?>
                                    </span>
                                </div>

                                <div class="info-row">
                                    <i class='bx bx-calendar'></i>
                                    <span>
                                        <strong>Salida:</strong> 
                                        <?php echo $fecha_salida->format('d/m/Y h:i A'); ?>
                                    </span>
                                </div>

                                <div class="info-row">
                                    <i class='bx bx-time'></i>
                                    <span>
                                        <strong>Tiempo en ruta:</strong> 
                                        <?php echo $tiempo_en_ruta; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="asignacion-productos">
                                <div class="productos-count">
                                    <i class='bx bx-package'></i>
                                    <span>Productos Cargados (<?php echo count($asignacion['productos']); ?>)</span>
                                </div>

                                <?php if (count($asignacion['productos']) > 0): ?>
                                    <table style="width: 100%; font-size: 0.9rem;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                                <th style="text-align: left; padding: 8px; color: #6b7280;">Producto</th>
                                                <th style="text-align: center; padding: 8px; color: #6b7280;">Cant.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($asignacion['productos'] as $producto): ?>
                                                <tr>
                                                    <td style="padding: 8px;">
                                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                                    </td>
                                                    <td style="text-align: center; padding: 8px; font-weight: 700; color: #0c3c78;">
                                                        <?php echo $producto['cantidad_cargada']; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <?php if ($asignacion['foto_salida']): ?>
                                <div style="margin: 15px 0;">
                                    <a href="assets/images/<?php echo htmlspecialchars($asignacion['foto_salida']); ?>" 
                                       target="_blank"
                                       style="display: inline-flex; align-items: center; gap: 8px; color: #3b82f6; text-decoration: none;">
                                        <i class='bx bx-image'></i>
                                        Ver foto de evidencia
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="asignacion-acciones">
                                <a href="registrar_entrada.php?id=<?php echo $asignacion['id_asignacion']; ?>" 
                                   class="btn btn-primary btn-full">
                                    <i class='bx bx-download'></i> Registrar Entrada
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-package'></i>
                    <p>No hay asignaciones activas</p>
                    <p class="text-muted">Todas las asignaciones han sido cerradas o aún no hay salidas registradas</p>
                    <div style="margin-top: 30px;">
                        <a href="nueva_salida.php" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Nueva Salida
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($asignaciones) > 0): ?>
            <div class="card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                <h2><i class='bx bx-stats'></i> Resumen Rápido</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: #0c3c78;">
                            <?php echo count($asignaciones); ?>
                        </div>
                        <div style="color: #6b7280; margin-top: 8px;">
                            Asignaciones en Ruta
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: #f59e0b;">
                            <?php 
                            $total_productos = array_sum(array_column($asignaciones, 'total_productos'));
                            echo $total_productos; 
                            ?>
                        </div>
                        <div style="color: #6b7280; margin-top: 8px;">
                            Productos Cargados
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: #10b981;">
                            <?php 
                            $empleados_unicos = count(array_unique(array_column($asignaciones, 'id_empleado')));
                            echo $empleados_unicos; 
                            ?>
                        </div>
                        <div style="color: #6b7280; margin-top: 8px;">
                            Empleados Activos
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refrescar cada 30 segundos para actualizar el tiempo en ruta
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>