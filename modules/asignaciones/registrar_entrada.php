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
$asignacion = null;
$productos = [];

// Obtener ID de asignación
$id_asignacion = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_asignacion == 0) {
  $sql = "SELECT id_asignacion FROM Asignaciones WHERE estado = 'abierta' ORDER BY fecha_hora_salida DESC LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->execute();
  $primera = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($primera) {
    $id_asignacion = $primera['id_asignacion'];
  }
}

if ($id_asignacion > 0) {
  $asignacion = obtenerAsignacion($conn, $id_asignacion);
  if ($asignacion && $asignacion['estado'] == 'abierta') {
    $sql = "SELECT da.*, 
            p.nombre, 
            p.precio_costo, 
            COALESCE(da.precio_cargado, p.precio_venta) as precio_venta 
        FROM Detalle_Asignacion da
        INNER JOIN Productos p ON da.id_producto = p.id_producto
        WHERE da.id_asignacion = :id_asignacion
        ORDER BY p.nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_asignacion', $id_asignacion);
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $mensaje = "Asignación no encontrada o ya está cerrada";
    $tipo_mensaje = "error";
  }
} else {
  $mensaje = "No hay asignaciones activas para registrar entrada";
  $tipo_mensaje = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cerrar') {
  $id_asignacion = $_POST['id_asignacion'];
  $fecha_hora_regreso = $_POST['fecha_hora_regreso'];
  $folios = json_decode($_POST['folios_json'], true);
  
  try {
    $conn->beginTransaction();

    foreach ($folios as $folio) {
      $fecha_hora_venta_insert = date('Y-m-d H:i:s');

      $sql_check = "SELECT COUNT(*) as count FROM Folios_Venta WHERE numero_folio = :numero_folio";
      $stmt_check = $conn->prepare($sql_check);
      $stmt_check->bindParam(':numero_folio', $folio['numero_folio']);
      $stmt_check->execute();
      $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
      
      if ($check['count'] > 0) {
        throw new Exception("El número de folio {$folio['numero_folio']} ya existe");
      }

      $total_venta = 0;
      foreach ($folio['productos'] as $producto) {
        $total_venta += $producto['precio_unitario'] * $producto['cantidad'];
      }

      $enganche = floatval($folio['enganche']);
      $saldo_pendiente = $total_venta - $enganche;
      $tipo_pago = $saldo_pendiente <= 0 ? 'contado' : 'credito';

      $sql_folio = "INSERT INTO Folios_Venta (
              id_asignacion, 
              numero_folio,
              nombre_cliente, 
              zona, 
              direccion,
              enganche,
              total_venta,
              saldo_pendiente,
              tipo_pago,
              observaciones,
              fecha_hora_venta
            ) VALUES (
              :id_asignacion,
              :numero_folio,
              :nombre_cliente,
              :zona,
              :direccion,
              :enganche,
              :total_venta,
              :saldo_pendiente,
              :tipo_pago,
              :observaciones,
              :fecha_hora_venta_insert
            )";
      
      $stmt_folio = $conn->prepare($sql_folio);
      $stmt_folio->bindParam(':id_asignacion', $id_asignacion);
      $stmt_folio->bindParam(':numero_folio', $folio['numero_folio']);
      $stmt_folio->bindParam(':nombre_cliente', $folio['nombre_cliente']);
      $stmt_folio->bindParam(':zona', $folio['zona']);
      $stmt_folio->bindParam(':direccion', $folio['direccion']);
      $stmt_folio->bindParam(':enganche', $enganche);
      $stmt_folio->bindParam(':total_venta', $total_venta);
      $stmt_folio->bindParam(':saldo_pendiente', $saldo_pendiente);
      $stmt_folio->bindParam(':tipo_pago', $tipo_pago);
      $stmt_folio->bindParam(':observaciones', $folio['observaciones']);
      $stmt_folio->bindParam(':fecha_hora_venta_insert', $fecha_hora_venta_insert);
      $stmt_folio->execute();

      $id_folio = $conn->lastInsertId();

      if ($enganche > 0) {
        $sql_enganche = "INSERT INTO Desglose_Enganche (
                  id_folio,
                  numero_folio,
                  monto,
                  metodo_pago,
                  concepto,
                  registrado_por
                ) VALUES (
                  :id_folio,
                  :numero_folio,
                  :monto,
                  :metodo_pago,
                  'Enganche inicial',
                  :registrado_por
                )";
        
        $stmt_enganche = $conn->prepare($sql_enganche);
        $stmt_enganche->bindParam(':id_folio', $id_folio);
        $stmt_enganche->bindParam(':numero_folio', $folio['numero_folio']);
        $stmt_enganche->bindParam(':monto', $enganche);
        $metodo_pago = isset($folio['metodo_pago']) ? $folio['metodo_pago'] : 'efectivo';
        $stmt_enganche->bindParam(':metodo_pago', $metodo_pago);
        $stmt_enganche->bindParam(':registrado_por', $_SESSION['usuario']);
        $stmt_enganche->execute();
      }

      $sql_detalle = "INSERT INTO Detalle_Folio_Venta (
                id_folio, 
                id_producto, 
                cantidad_vendida,
                precio_unitario,
                subtotal,
                porcentaje_comision,
                monto_comision
              ) VALUES (
                :id_folio,
                :id_producto,
                :cantidad,
                :precio_unitario,
                :subtotal,
                :comision_por_unidad,
                :monto_comision
              )";
      
      $stmt_detalle = $conn->prepare($sql_detalle);
      $total_comision_folio = 0;

      foreach ($folio['productos'] as $producto) {
        $subtotal = $producto['precio_unitario'] * $producto['cantidad'];
        $comision_por_unidad = floatval($producto['comision']);
        $monto_comision = $producto['cantidad'] * $comision_por_unidad;
        $total_comision_folio += $monto_comision;

        $stmt_detalle->bindParam(':id_folio', $id_folio);
        $stmt_detalle->bindParam(':id_producto', $producto['id_producto']);
        $stmt_detalle->bindParam(':cantidad', $producto['cantidad']);
        $stmt_detalle->bindParam(':precio_unitario', $producto['precio_unitario']);
        $stmt_detalle->bindParam(':subtotal', $subtotal);
        $stmt_detalle->bindParam(':comision_por_unidad', $comision_por_unidad);
        $stmt_detalle->bindParam(':monto_comision', $monto_comision);
        $stmt_detalle->execute();

        $sql_update = "UPDATE Detalle_Asignacion 
               SET cantidad_vendida = cantidad_vendida + :cantidad
               WHERE id_asignacion = :id_asignacion AND id_producto = :id_producto";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':cantidad', $producto['cantidad']);
        $stmt_update->bindParam(':id_asignacion', $id_asignacion);
        $stmt_update->bindParam(':id_producto', $producto['id_producto']);
        $stmt_update->execute();
      }
    }

    $sql_productos = "SELECT id_producto, cantidad_cargada, cantidad_vendida 
            FROM Detalle_Asignacion 
            WHERE id_asignacion = :id_asignacion";
    $stmt_prod = $conn->prepare($sql_productos);
    $stmt_prod->bindParam(':id_asignacion', $id_asignacion);
    $stmt_prod->execute();
    $productos_asignacion = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productos_asignacion as $prod) {
      $devueltos = $prod['cantidad_cargada'] - $prod['cantidad_vendida'];

      $sql_devueltos = "UPDATE Detalle_Asignacion 
              SET cantidad_devuelta = :devueltos
              WHERE id_asignacion = :id_asignacion AND id_producto = :id_producto";
      $stmt_dev = $conn->prepare($sql_devueltos);
      $stmt_dev->bindParam(':devueltos', $devueltos);
      $stmt_dev->bindParam(':id_asignacion', $id_asignacion);
      $stmt_dev->bindParam(':id_producto', $prod['id_producto']);
      $stmt_dev->execute();

      if ($devueltos > 0) {
        actualizarStock($conn, $prod['id_producto'], $devueltos);
      }
    }

    $sql_comision_total = "SELECT 
                  SUM(dfv.monto_comision) as total_comision,
                  SUM(dfv.subtotal) as total_vendido
                FROM Folios_Venta fv
                INNER JOIN Detalle_Folio_Venta dfv ON fv.id_folio = dfv.id_folio
                WHERE fv.id_asignacion = :id_asignacion";

    $stmt_com = $conn->prepare($sql_comision_total);
    $stmt_com->bindParam(':id_asignacion', $id_asignacion);
    $stmt_com->execute();
    $comision_data = $stmt_com->fetch(PDO::FETCH_ASSOC);

    if ($comision_data && $comision_data['total_comision'] > 0) {
      $sql_insert_comision = "INSERT INTO Comisiones_Asignacion (
                    id_asignacion,
                    id_empleado,
                    total_vendido,
                    total_comision
                  ) VALUES (
                    :id_asignacion,
                    :id_empleado,
                    :total_vendido,
                    :total_comision
                  )";

      $stmt_ins_com = $conn->prepare($sql_insert_comision);
      $stmt_ins_com->bindParam(':id_asignacion', $id_asignacion);
      $stmt_ins_com->bindParam(':id_empleado', $asignacion['id_empleado']);
      $stmt_ins_com->bindParam(':total_vendido', $comision_data['total_vendido']);
      $stmt_ins_com->bindParam(':total_comision', $comision_data['total_comision']);
      $stmt_ins_com->execute();
    }

    $sql_cerrar = "UPDATE Asignaciones 
           SET fecha_hora_regreso = :fecha_regreso, estado = 'cerrada'
           WHERE id_asignacion = :id_asignacion";
    $stmt_cerrar = $conn->prepare($sql_cerrar);
    $stmt_cerrar->bindParam(':fecha_regreso', $fecha_hora_regreso);
    $stmt_cerrar->bindParam(':id_asignacion', $id_asignacion);
    $stmt_cerrar->execute();

    $conn->commit();

    $mensaje = "Asignación cerrada exitosamente. Se generaron " . count($folios) . " folio(s) de venta.";
    $tipo_mensaje = "success";

    echo "<script>
      alert('Operación exitosa. Asignación cerrada correctamente.');
      if (window.parent && window.parent.dashboardMenu) {
        window.parent.dashboardMenu.reloadSection('historial-asignaciones');
        window.parent.dashboardMenu.reloadSection('contratos');
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

      setTimeout(function() {
        window.location.href = 'asignaciones_activas.php';
      }, 200);
    </script>";
    exit();

  } catch (Exception $e) {
    $conn->rollBack();
    $mensaje = "Error al cerrar asignación: " . $e->getMessage();
    $tipo_mensaje = "error";
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Entrada - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/asignaciones.css">

</head>
<body>
    <div class="container">
        <div class="header-actions">
            <a href="asignaciones_activas.php" class="btn-back">
                <i class='bx bx-arrow-back'></i> Volver
            </a>
        </div>

        <h1><i class='bx bx-download'></i> Registrar Entrada con Folios y Comisiones</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($asignacion): ?>
            <form method="POST" action="" id="formCerrar">
                <input type="hidden" name="action" value="cerrar">
                <input type="hidden" name="id_asignacion" value="<?php echo $asignacion['id_asignacion']; ?>">
                <input type="hidden" name="folios_json" id="folios_json">

                <!-- INFORMACIÓN DE LA ASIGNACIÓN -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0;"><i class='bx bx-info-circle'></i> Información de la Asignación</h2>

                        <button type="button" class="btn-traspaso" onclick="abrirModalTraspaso()">
                            <i class='bx bx-transfer'></i> Registrar Traspaso
                        </button>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Asignación</label>
                            <input type="text" value="#<?php echo str_pad($asignacion['id_asignacion'], 4, '0', STR_PAD_LEFT); ?>" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                            <label>Empleado</label>
                            <input type="text" value="<?php echo htmlspecialchars($asignacion['nombre_empleado']); ?>" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                            <label>Vehículo</label>
                            <input type="text" value="<?php echo htmlspecialchars($asignacion['marca'] . ' ' . $asignacion['modelo']); ?>" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                            <label>Fecha/Hora Regreso *</label>
                            <input type="datetime-local" name="fecha_hora_regreso" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>

                    <h3><i class='bx bx-package'></i> Productos Cargados con Precios de Venta</h3>
                    <div class="tabla-productos">
                        <table id="tablaProductosCargados">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cargado</th>
                                    <th>Precio Costo</th>
                                    <th>Precio Venta</th>
                                    <th>Vendido</th>
                                    <th>Disponible</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $prod): ?>
                                    <tr data-id="<?php echo $prod['id_producto']; ?>" 
                                        data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                        data-cargado="<?php echo $prod['cantidad_cargada']; ?>"
                                        data-precio-venta="<?php echo $prod['precio_venta']; ?>">
                                        <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                        <td class="text-center"><strong><?php echo $prod['cantidad_cargada']; ?></strong></td>
                                        <td class="text-center">$<?php echo number_format($prod['precio_costo'], 2); ?></td>
                                        <td class="text-center precio-venta">$<?php echo number_format($prod['precio_venta'], 2); ?></td>
                                        <td class="text-center cantidad-vendida">0</td>
                                        <td class="text-center disponible-venta"><?php echo $prod['cantidad_cargada']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FOLIOS DE VENTA MEJORADOS -->
                <div class="card">
                    <h2><i class='bx bx-file'></i> Folios de Venta con Enganche y Comisiones</h2>
                    <p style="color: #6b7280; margin-bottom: 20px;">
                        Asigna número de folio manual, enganche y comisión por artículo.
                    </p>

                    <button type="button" class="btn btn-primary" onclick="agregarFolioMejorado()">
                        <i class='bx bx-plus'></i> Agregar Nuevo Folio
                    </button>

                    <div id="foliosContainer" style="margin-top: 30px;">
                        <!-- Los folios se agregarán aquí dinámicamente -->
                    </div>
                </div>

                <!-- RESUMEN -->
                <div class="card">
                    <h2><i class='bx bx-check-circle'></i> Resumen Final</h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: 700; color: #0c3c78;">
                                <?php echo array_sum(array_column($productos, 'cantidad_cargada')); ?>
                            </div>
                            <div>Total Cargados</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: 700; color: #10b981;" id="totalVendidos">0</div>
                            <div>Total Vendidos</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;" id="totalComisiones">$0.00</div>
                            <div>Total Comisiones</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: 700; color: #991b1b;" id="totalFolios">0</div>
                            <div>Folios Generados</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large" id="btnCerrar">
                            <i class='bx bx-check-circle'></i> Cerrar Asignación
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="modal-traspaso" id="modalTraspaso">
        <div class="modal-content-traspaso">
            <div class="modal-header-traspaso">
                <h3>
                    <i class='bx bx-transfer'></i>
                    Registrar Traspaso de Producto
                </h3>
                <button type="button" class="btn-close-modal" onclick="cerrarModalTraspaso()">×</button>
            </div>

            <div class="alert-traspaso" id="alertTraspaso"></div>

            <div class="info-box-traspaso">
                <p>
                    <strong>Asignación Origen:</strong> 
                    #<?php echo str_pad($asignacion['id_asignacion'], 4, '0', STR_PAD_LEFT); ?> - 
                    <?php echo htmlspecialchars($asignacion['nombre_empleado']); ?>
                </p>
            </div>

            <form id="formTraspaso">
                <input type="hidden" name="id_asignacion_origen" value="<?php echo $asignacion['id_asignacion']; ?>">

                <div class="form-group-traspaso">
                    <label for="id_producto_traspaso">
                        <i class='bx bx-package'></i> Producto a Traspasar *
                    </label>
                    <select name="id_producto" id="id_producto_traspaso" required>
                        <option value="">Seleccionar producto...</option>
                        <?php foreach ($productos as $prod): 
                            $disponible = $prod['cantidad_cargada'] - $prod['cantidad_vendida'];
                            if ($disponible > 0):
                        ?>
                            <option value="<?php echo $prod['id_producto']; ?>" data-disponible="<?php echo $disponible; ?>">
                                <?php echo htmlspecialchars($prod['nombre']); ?> (Disponible: <?php echo $disponible; ?>)
                            </option>
                        <?php 
                            endif;
                        endforeach; ?>
                    </select>
                </div>

                <div class="form-group-traspaso">
                    <label for="cantidad_traspaso">
                        <i class='bx bx-hash'></i> Cantidad *
                    </label>
                    <input type="number" name="cantidad" id="cantidad_traspaso" min="1" required placeholder="Cantidad a traspasar">
                </div>

                <div class="form-group-traspaso">
                    <label for="id_asignacion_destino">
                        <i class='bx bx-user'></i> Traspasar a Empleado *
                    </label>
                    <select name="id_asignacion_destino" id="id_asignacion_destino" required>
                        <option value="">Seleccionar empleado en ruta...</option>
                        <?php 
                        $asignaciones_disponibles = obtenerAsignacionesParaTraspaso($conn, $asignacion['id_asignacion']);
                        if (count($asignaciones_disponibles) > 0):
                            foreach ($asignaciones_disponibles as $asig):
                        ?>
                            <option value="<?php echo $asig['id_asignacion']; ?>" class="empleado-option">
                                Asig. #<?php echo str_pad($asig['id_asignacion'], 4, '0', STR_PAD_LEFT); ?> - 
                                <?php echo htmlspecialchars($asig['nombre_empleado']); ?> 
                                (<?php echo $asig['productos_cargados']; ?> productos)
                            </option>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <option value="" disabled>No hay otras asignaciones activas</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group-traspaso">
                    <label for="fecha_hora_traspaso">
                        <i class='bx bx-calendar'></i> Fecha y Hora del Traspaso *
                    </label>
                    <input type="datetime-local" name="fecha_hora_traspaso" id="fecha_hora_traspaso" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <div class="form-group-traspaso">
                    <label for="observaciones_traspaso">
                        <i class='bx bx-note'></i> Observaciones
                    </label>
                    <textarea name="observaciones" id="observaciones_traspaso" rows="3" placeholder="Ej: Traspaso para completar venta del cliente..."></textarea>
                </div>

                <div class="modal-footer-traspaso">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalTraspaso()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitTraspaso">
                        <i class='bx bx-check'></i> Registrar Traspaso
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/registrar_entrada.js"></script>
<script>
        function abrirModalTraspaso() {
            document.getElementById('modalTraspaso').classList.add('active');
            document.getElementById('formTraspaso').reset();
            document.getElementById('alertTraspaso').classList.remove('active', 'success', 'error');
            document.getElementById('fecha_hora_traspaso').value = '<?php echo date('Y-m-d\TH:i'); ?>';
        }

        function cerrarModalTraspaso() {
            document.getElementById('modalTraspaso').classList.remove('active');
        }

        document.getElementById('modalTraspaso').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalTraspaso();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalTraspaso();
            }
        });

        document.getElementById('id_producto_traspaso').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const disponible = parseInt(option.dataset.disponible || 0);
            const inputCantidad = document.getElementById('cantidad_traspaso');

            if (disponible > 0) {
                inputCantidad.max = disponible;
                inputCantidad.value = 1;
                inputCantidad.placeholder = `Máximo ${disponible}`;
            } else {
                inputCantidad.max = 0;
                inputCantidad.value = '';
                inputCantidad.placeholder = 'No disponible';
            }
        });

        document.getElementById('formTraspaso').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const alertBox = document.getElementById('alertTraspaso');
            const btnSubmit = document.getElementById('btnSubmitTraspaso');

            btnSubmit.disabled = true;
            btnSubmit.classList.add('btn-loading');
            btnSubmit.innerHTML = '<i class="bx bx-loader-alt"></i> Procesando...';

            fetch('procesar_traspaso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alertBox.classList.remove('success', 'error');
                alertBox.textContent = data.message;

                if (data.success) {
                    alertBox.classList.add('success', 'active');

                    if (window.parent && window.parent.dashboardMenu) {
                        window.parent.dashboardMenu.reloadSection('historial-asignaciones');
                    }

                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alertBox.classList.add('error', 'active');
                    btnSubmit.disabled = false;
                    btnSubmit.classList.remove('btn-loading');
                    btnSubmit.innerHTML = '<i class="bx bx-check"></i> Registrar Traspaso';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertBox.classList.remove('success');
                alertBox.classList.add('error', 'active');
                alertBox.textContent = 'Error al procesar la solicitud. Por favor intenta nuevamente.';
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('btn-loading');
                btnSubmit.innerHTML = '<i class="bx bx-check"></i> Registrar Traspaso';
            });
        });
    </script>
</body>
</html>