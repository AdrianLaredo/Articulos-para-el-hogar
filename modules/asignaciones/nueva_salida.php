<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
 header("Location: ../login/login.php");
 exit;
}

$mensaje = '';
$tipo_mensaje = '';
$mostrar_popup = false;
$numero_asignacion_popup = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
  $id_empleado = $_POST['id_empleado'];
  $placas = $_POST['placas'];
  $productos = json_decode($_POST['productos_json'], true);

  $foto_salida = '';
  if (isset($_FILES['foto_salida']) && $_FILES['foto_salida']['error'] === 0) {
    $resultado_imagen = subirFotoEvidencia($_FILES['foto_salida']);
    if ($resultado_imagen['success']) {
      $foto_salida = $resultado_imagen['filename'];
    } else {
      $mensaje = $resultado_imagen['message'];
      $tipo_mensaje = "error";
    }
  } else {
    $mensaje = "La foto de evidencia es obligatoria";
    $tipo_mensaje = "error";
  }
  if ($tipo_mensaje !== 'error' && !empty($productos)) {
    try {
      $conn->beginTransaction();

      $fecha_hora_salida = date('Y-m-d H:i:s');

      $sql = "INSERT INTO Asignaciones (id_empleado, placas, foto_salida, estado, fecha_hora_salida) 
          VALUES (:id_empleado, :placas, :foto_salida, 'abierta', :fecha_hora_salida)";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':id_empleado', $id_empleado);
      $stmt->bindParam(':placas', $placas);
      $stmt->bindParam(':foto_salida', $foto_salida);
      $stmt->bindParam(':fecha_hora_salida', $fecha_hora_salida);
      $stmt->execute();

      $id_asignacion = $conn->lastInsertId();

      $sql_detalle = "INSERT INTO Detalle_Asignacion (id_asignacion, id_producto, cantidad_cargada) 
              VALUES (:id_asignacion, :id_producto, :cantidad)";
      $stmt_detalle = $conn->prepare($sql_detalle);

      foreach ($productos as $producto) {
        $stmt_detalle->bindParam(':id_asignacion', $id_asignacion);
        $stmt_detalle->bindParam(':id_producto', $producto['id_producto']);
        $stmt_detalle->bindParam(':cantidad', $producto['cantidad']);
        $stmt_detalle->execute();

        actualizarStock($conn, $producto['id_producto'], -$producto['cantidad']);
      }

      $conn->commit();

      $mostrar_popup = true;
      $numero_asignacion_popup = str_pad($id_asignacion, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
      $conn->rollBack();
      $mensaje = "Error al registrar la salida: " . $e->getMessage();
      $tipo_mensaje = "error";
    }
  } elseif (empty($productos)) {
    $mensaje = "Debes agregar al menos un producto";
    $tipo_mensaje = "error";
  }
}

$sql_empleados = "
  SELECT e.id_empleado, (e.nombre || ' ' || e.apellido_paterno || ' ' || e.apellido_materno) AS nombre_completo
  FROM Empleados e
  WHERE e.estado = 'activo'
   AND e.id_empleado NOT IN (
     SELECT a.id_empleado FROM Asignaciones a WHERE a.estado = 'abierta'
   )
  ORDER BY e.nombre
";
$stmt_emp = $conn->prepare($sql_empleados);
$stmt_emp->execute();
$empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

$sql_vehiculos = "SELECT placas, (marca || ' ' || modelo || ' (' || placas || ')') AS descripcion 
         FROM Vehiculos ORDER BY marca";
$stmt_veh = $conn->prepare($sql_vehiculos);
$stmt_veh->execute();
$vehiculos = $stmt_veh->fetchAll(PDO::FETCH_ASSOC);

$sql_productos = "SELECT id_producto, nombre, stock, precio_venta 
         FROM Productos 
         WHERE estado = 'disponible' AND stock > 0 
         ORDER BY nombre";
$stmt_prod = $conn->prepare($sql_productos);
$stmt_prod->execute();
$productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Salida - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/asignaciones.css">
    <style>
        /* Estilos personalizados para Select2 */
        .select2-container--default .select2-selection--single {
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 33px;
            padding-left: 10px;
            color: #333;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #999;
        }
        
        .select2-dropdown {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4caf50;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px 12px;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #4caf50;
            outline: none;
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        /* Popup Elegante y Creativo */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(8px);
        }
        
        .popup-overlay.active {
            display: flex;
        }
        
        .popup {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 24px;
            padding: 0;
            max-width: 420px;
            width: 90%;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            animation: popupSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .popup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #059669, #047857);
        }
        
        .popup-content {
            padding: 40px 35px 35px 35px;
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .success-animation {
            position: relative;
            margin: 0 auto 25px auto;
        }
        
        .success-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            animation: circleScale 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 
                0 10px 25px rgba(16, 185, 129, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        
        .success-circle::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.4) 0%, transparent 50%);
        }
        
        .success-circle i {
            font-size: 36px;
            color: white;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .popup-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.025em;
            text-align: center;
            width: 100%;
            display: block;
        }
        
        .popup-subtitle {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 25px;
            line-height: 1.5;
            font-weight: 500;
            text-align: center;
            width: 100%;
        }
        
        .assignment-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 2px solid #d1fae5;
            border-radius: 16px;
            padding: 16px 24px;
            margin: 15px 0;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .assignment-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
            animation: shimmer 3s infinite;
        }
        
        .badge-label {
            font-size: 14px;
            color: #0c3c78;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-number {
            font-size: 28px;
            font-weight: 800;
            color: #0c3c78;
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .progress-container {
            background: rgba(241, 245, 249, 0.8);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0 20px 0;
            border: 1px solid #e2e8f0;
            width: 100%;
            box-sizing: border-box;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .progress-label {
            font-size: 14px;
            color: #475569;
            font-weight: 600;
        }
        
        .progress-time {
            font-size: 14px;
            color: #10b981;
            font-weight: 700;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #34d399);
            border-radius: 3px;
            width: 100%;
            animation: progressCountdown 2.5s linear forwards;
            position: relative;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 20px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6));
            animation: shimmer 1.5s infinite;
        }
        
        .popup-footer {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            text-align: center;
        }
        
        .floating-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .particle:nth-child(1) {
            width: 40px;
            height: 40px;
            top: 10%;
            right: 10%;
            animation-delay: 0s;
        }
        
        .particle:nth-child(2) {
            width: 25px;
            height: 25px;
            bottom: 15%;
            left: 10%;
            animation-delay: 2s;
        }
        
        .particle:nth-child(3) {
            width: 30px;
            height: 30px;
            top: 60%;
            right: 20%;
            animation-delay: 4s;
        }

        @keyframes popupSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes circleScale {
            0% {
                opacity: 0;
                transform: scale(0) rotate(-180deg);
            }
            70% {
                transform: scale(1.1) rotate(10deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0);
            }
        }
        
        @keyframes progressCountdown {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            33% {
                transform: translateY(-15px) rotate(120deg);
            }
            66% {
                transform: translateY(8px) rotate(240deg);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-upload'></i> Nueva Salida de Almacén</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class='bx bx-file-blank'></i> Registrar Salida</h2>
            <form method="POST" action="" enctype="multipart/form-data" id="formSalida">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="productos_json" id="productos_json">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_empleado">
                            <i class='bx bx-user'></i> Empleado *
                        </label>
                        <select id="id_empleado" name="id_empleado" class="form-control" style="width: 100%;" required>
                            <option value="">-- Escribe para buscar un empleado --</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id_empleado']; ?>">
                                    <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="placas">
                            <i class='bx bx-car'></i> Vehículo *
                        </label>
                        <select id="placas" name="placas" class="form-control" style="width: 100%;" required>
                            <option value="">-- Escribe para buscar un vehículo --</option>
                            <?php foreach ($vehiculos as $veh): ?>
                                <option value="<?php echo $veh['placas']; ?>">
                                    <?php echo htmlspecialchars($veh['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class='bx bx-calendar'></i> Fecha y Hora de Salida
                        </label>
                        <input type="text" value="<?php echo date('d/m/Y H:i'); ?>" readonly class="readonly-field">
                    </div>
                </div>

                <!-- Productos a cargar -->
                <div class="productos-section">
                    <h3><i class='bx bx-package'></i> Productos a Cargar</h3>
                    
                    <div class="agregar-producto">
                        <select id="producto_select" class="form-control" style="width: 100%;">
                            <option value="">Escribe para buscar un producto</option>
                            <?php foreach ($productos as $prod): ?>
                                <option value="<?php echo $prod['id_producto']; ?>" 
                                        data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                        data-stock="<?php echo $prod['stock']; ?>"
                                        data-precio="<?php echo $prod['precio_venta']; ?>">
                                    <?php echo htmlspecialchars($prod['nombre']); ?> (Stock: <?php echo $prod['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="cantidad_producto" min="1" placeholder="Cantidad" value="1">
                        <button type="button" class="btn btn-secondary" onclick="agregarProducto()">
                            <i class='bx bx-plus'></i> Agregar
                        </button>
                    </div>

                    <div class="tabla-productos">
                        <table id="tablaProductos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Stock Disponible</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <tr class="empty-row">
                                    <td colspan="4" class="text-center text-muted">
                                        No hay productos agregados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Foto de evidencia -->
                <div class="foto-section">
                    <h3><i class='bx bx-camera'></i> Foto de Evidencia *</h3>
                    
                    <div class="camera-container" id="cameraContainer">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <div id="preview" style="display: none;">
                            <img id="foto-preview" src="" alt="Foto capturada">
                        </div>
                    </div>

                    <div class="camera-controls">
                        <button type="button" class="btn btn-primary" id="btnIniciarCamera">
                            <i class='bx bx-camera'></i> Activar Cámara (PC)
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnTomarFoto" style="display: none;">
                            <i class='bx bx-camera'></i> Tomar Foto
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnNuevaFoto" style="display: none;">
                            <i class='bx bx-refresh'></i> Tomar Otra Foto
                        </button>

                        <input type="file" name="foto_salida" id="foto_salida" accept="image/*" capture="environment" style="display: none;">
                        <label for="foto_salida" class="btn btn-primary" style="width: 100%;">
                            <i class='bx bx-camera'></i> Tomar Foto (Móvil/Tablet)
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <i class='bx bx-save'></i> Registrar Salida
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="popup-overlay" id="popupConfirmacion">
        <div class="popup">
            <div class="floating-particles">
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
            </div>
            
            <div class="popup-content">
                <div class="success-animation">
                    <div class="success-circle">
                        <i class='bx bx-check'></i>
                    </div>
                </div>
                
                <h2 class="popup-title">¡Salida registrada!</h2>
                <p class="popup-subtitle">La salida de almacén ha sido registrada correctamente</p>
                
                <div class="assignment-badge">
                    <span class="badge-label">Asignación</span>
                    <span class="badge-number" id="popupNumero">#0000</span>
                </div>
                
                <div class="progress-container">
                    <div class="progress-info">
                        <span class="progress-label">Redireccionando automáticamente</span>
                        <span class="progress-time">2.5s</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                </div>
                
                <div class="popup-footer">
                    <i class='bx bx-loader-circle bx-spin'></i>
                    <span>Redirigiendo a asignaciones activas...</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/nueva_salida.js"></script>
    <script>
        function mostrarPopup(numeroAsignacion) {
            const popup = document.getElementById('popupConfirmacion');

            document.getElementById('popupNumero').textContent = '#' + numeroAsignacion;

            popup.classList.add('active');

            setTimeout(() => {
                redirigirAAsignaciones();
            }, 2500);
        }

       function redirigirAAsignaciones() {
    const iframeHistorial = window.parent.document.getElementById('iframeHistorial');
    if (iframeHistorial) {
        iframeHistorial.src = iframeHistorial.src;
    }

    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage('inventario-updated', '*');
        }

        localStorage.setItem('inventario_updated', Date.now().toString());

        if (window.parent && window.parent.dashboardMenu && window.parent.dashboardMenu.reloadSection) {
            window.parent.dashboardMenu.reloadSection('inventario');
        }
    } catch (e) {
        console.log('No se pudo notificar al inventario:', e);
    }

    if (window.parent && window.parent !== window) {
        if (window.parent.dashboardMenu && window.parent.dashboardMenu.navigateTo) {
            window.parent.dashboardMenu.navigateTo('asignaciones-activas');
        }
        if (window.parent.dashboardMenu && window.parent.dashboardMenu.reloadSection) {
            window.parent.dashboardMenu.reloadSection('asignaciones-activas');
        }
    } else {
        window.location.href = 'asignaciones_activas.php';
    }
}

        <?php if ($mostrar_popup): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                mostrarPopup('<?php echo $numero_asignacion_popup; ?>');
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>